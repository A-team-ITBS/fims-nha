# NHA FIMS — Production Deployment Guide

This guide walks a Linux server admin through deploying NHA FIMS
on an on-premises Ubuntu 22.04+ server.

Target audience: whoever administers the NHA server. No prior Docker knowledge assumed.

---

## 0. About `deploy.sh`

**This guide assumes a `deploy.sh` script already exists on the server — it is not part of this
repository.** `deploy.sh` is listed in `.gitignore` deliberately: it's environment-specific
(paths, SSH keys, notification hooks may differ per install) and is provisioned once per server
rather than checked in. If you don't have one yet, it needs to perform the 5 steps described in
[§4](#4-regular-deployments) — a minimal version looks like:

```bash
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
COMPOSE="docker compose -f docker-compose.prod.yml --env-file .env.prod"

[[ "${1:-}" != "--no-pull" ]] && git pull
[[ "${1:-}" != "--no-backup" ]] && $COMPOSE exec -T backup /usr/local/bin/backup.sh
$COMPOSE build php
$COMPOSE up -d
$COMPOSE ps   # eyeball health here, or add a real wait-loop on the `php` healthcheck
```

**Don't confuse this with `.github/workflows/deploy.yml`.** That workflow is ITBS's own CI/CD —
on push to `dev`/`beta-phase` it SSHes into ITBS's `itbsstudio.com` servers and runs a
*different* `deploy.sh` living at a fixed path there (`/var/www/nha-fims.itbsstudio.com/deploy.sh`,
`/var/www/nha-fims-dev.itbsstudio.com/deploy.sh`). It has nothing to do with the on-premises NHA
server this guide describes.

---

## 1. Server prerequisites

| Resource       | Minimum | Recommended       |
|----------------|---------|-------------------|
| CPU            | 4 cores | 8 cores           |
| RAM            | 8 GB    | 16 GB             |
| System disk    | 100 GB SSD | 200 GB SSD     |
| Data disk (DB) | 200 GB SSD | 500 GB SSD     |
| Backup disk    | 500 GB  | 1 TB              |
| OS             | Ubuntu 22.04 LTS | Ubuntu 22.04 LTS |

### Install Docker + Compose

```bash
# On the NHA server as root or with sudo:
curl -fsSL https://get.docker.com | sh
apt-get install -y docker-compose-plugin git

# Verify:
docker --version
docker compose version
```

### Open firewall (public mode only)

If TLS_MODE=public (real internet-facing HTTPS via Let's Encrypt):

```bash
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp    # HTTP/3
ufw reload
```

For internal-only (LAN/VPN), keep 80/443 closed publicly and expose only
within your NHA network.

---

## 2. First-time deployment

```bash
# 1. Clone the repo
cd /opt
git clone <repo-url> nha-fims
cd nha-fims

# 2. Create production env file from template
cp .env.prod.example .env.prod
chmod 600 .env.prod

# 3. Edit .env.prod and set REAL values
#    - DB_PASSWORD          — run `openssl rand -base64 24`
#    - JWT_SECRET           — run `openssl rand -base64 48`
#    - MINIO_ROOT_USER/PASSWORD
#    - ONLYOFFICE_JWT_SECRET
#    - FIMS_DOMAIN          — set to the actual hostname/IP users will use
#    - TLS_MODE_DIRECTIVE   — "internal" or "public"
#    - Any 3rd-party API tokens (PIMS, LIBIS, etc.)
nano .env.prod

# 4. Run deploy
chmod +x deploy.sh
./deploy.sh --no-backup      # first time: no DB yet, skip pre-deploy backup

# 5. Load the FIMS schema (first time only) — see section 3 below.
#    There is no pre-baked schema.sql mounted into this stack; the postgres
#    service has no /docker-entrypoint-initdb.d volume, so you must run the
#    by-phase SQL files directly (section 3's primary method).

# 6. Create the MinIO bucket (first time only)
docker compose -f docker-compose.prod.yml --env-file .env.prod \
    exec minio mc alias set local http://localhost:9000 \
        "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"
docker compose -f docker-compose.prod.yml --env-file .env.prod \
    exec minio mc mb local/nha-fims
```

Open **https://\<FIMS_DOMAIN\>/** in a browser. First-time visit with
`TLS_MODE=internal` will show a "not trusted" warning — accept it.

---

## 3. Load the database schema

First-time only. From the project directory. **This is the only verified-working method** — the
`postgres` service in `docker-compose.prod.yml` has no `/docker-entrypoint-initdb.d` mount, so
there is no pre-baked `schema.sql` to point `psql -f` at; you must load the by-phase files
directly:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod \
    cp _assets/sql-consolidated postgres:/tmp/sql

docker compose -f docker-compose.prod.yml --env-file .env.prod \
    exec postgres bash -c '
      cd /tmp/sql/by-phase &&
      for f in *.sql; do
        echo "== $f ==";
        psql -U postgres -d govflow360_nha -f "$f";
      done
    '
```

If you have a real DB dump from staging/prod, restore that instead:

```bash
gunzip -c backup.sql.gz | \
  docker compose -f docker-compose.prod.yml --env-file .env.prod \
    exec -T postgres psql -U postgres -d govflow360_nha
```

---

## 4. Regular deployments

Every subsequent deploy:

```bash
cd /opt/nha-fims
./deploy.sh
```

That single command:
1. `git pull` latest code
2. Pre-deploy DB backup
3. Rebuild the PHP image
4. Restart containers
5. Wait for health

Typical duration: 2–4 minutes.

---

## 5. Backups

Automatic — the `backup` sidecar dumps Postgres nightly at **02:00** (container TZ).
Files land in the `backups` volume, keep the last 30 days.

Check what's there:
```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod \
    exec backup ls -lh /backups
```

Copy a backup out to the host filesystem:
```bash
docker cp nha-backup:/backups/latest.sql.gz /mnt/backups/nha-fims/
```

Restore a specific backup:
```bash
gunzip -c /path/to/dump.sql.gz | \
    docker compose -f docker-compose.prod.yml --env-file .env.prod \
      exec -T postgres psql -U postgres -d govflow360_nha
```

**Recommendation:** bind the `backups` volume to a real mounted disk in
`docker-compose.prod.yml` so backups survive if the Docker volume gets wiped.

---

## 6. Switching to public HTTPS (Let's Encrypt)

Once NHA gives you a real domain (e.g., `fims-uat.nha.gov.ph`):

1. Point the domain DNS at the server's public IP
2. Open firewall for 80/443 (see section 1)
3. Edit `.env.prod`:
   ```
   FIMS_DOMAIN=fims-uat.nha.gov.ph
   TLS_MODE_DIRECTIVE=public
   ACME_EMAIL=admin@nha.gov.ph
   ```
4. Restart Caddy:
   ```bash
   docker compose -f docker-compose.prod.yml --env-file .env.prod \
       up -d caddy
   ```
Caddy will request and install a valid Let's Encrypt cert automatically.

---

## 7. Monitoring

Check what's running:
```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod ps
```

Live logs:
```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod logs -f
```

Just one service:
```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod logs -f php
```

Disk usage:
```bash
docker system df
```

---

## 8. Troubleshooting

**Site is unreachable / Caddy fails to get cert**
- If `TLS_MODE=public`, verify ports 80/443 open AND domain resolves to this server.
- Check Caddy logs: `docker compose ... logs -f caddy`

**PHP shows 502 Bad Gateway**
- PHP container likely crashed. Check: `docker compose ... logs --tail=100 php`
- Verify DB is healthy: `docker compose ... ps`

**"Database connection failed"**
- Confirm `.env.prod` has correct `DB_PASSWORD`
- `docker compose ... exec postgres psql -U postgres -l`  (should list govflow360_nha)

**Backups aren't running**
- `docker compose ... exec backup crontab -l`  should show the daily job
- Check logs: `docker compose ... logs backup`

**Out of memory / slow**
- `docker stats` to see per-container usage
- Bump `pm.max_children` in `_docker/php/www.prod.conf` if PHP is saturated
- Bump `deploy.resources.limits.memory` in `docker-compose.prod.yml` for containers hitting the limit

---

## 9. Rollback

If a deploy breaks something:

```bash
# See recent commits
git log --oneline -10

# Roll back
git checkout <previous-commit-sha>
./deploy.sh --no-pull

# If DB was migrated, restore the pre-deploy backup:
gunzip -c /backups/govflow360_nha_<timestamp>.sql.gz | \
    docker compose ... exec -T postgres psql -U postgres -d govflow360_nha
```

---

## 10. Stopping / uninstalling

```bash
# Stop but keep data
docker compose -f docker-compose.prod.yml --env-file .env.prod down

# WIPE EVERYTHING including databases and uploads
docker compose -f docker-compose.prod.yml --env-file .env.prod down -v
```

---

## File map

```
Dockerfile.prod           Multi-stage build (composer → runtime)
docker-compose.prod.yml   Production stack
.env.prod.example         Env template (copy → .env.prod, fill secrets)
deploy.sh                 One-command deploy
_docker/
  caddy/Caddyfile         Reverse proxy + HTTPS + module routing
  php/php.prod.ini        Production PHP settings (opcache, security)
  php/www.prod.conf       PHP-FPM pool (60 workers)
  backup/Dockerfile       Backup sidecar image
  backup/backup.sh        pg_dump + rotation (30-day default, RETENTION_DAYS env)
  backup/entrypoint.sh    cron scheduler (daily at 02:00 container time)
```

## Services this stack runs (and one it doesn't)

`docker-compose.prod.yml` brings up: `caddy` (reverse proxy/TLS), `php` (FPM), `postgres`,
`redis` (cache + BullMQ job queues), `minio` (S3-compatible object storage), and `backup`
(the nightly pg_dump sidecar).

**ONLYOFFICE is NOT one of these containers.** `.env.prod.example` has `ONLYOFFICE_SERVER_URL`
and `ONLYOFFICE_JWT_SECRET` because the PHP app talks to an ONLYOFFICE Document Server as an
*external* dependency — point those at wherever your org runs (or will run) that service; this
stack does not deploy it for you.

For questions or issues, check the project's development docs or contact
the ITBS engineering team.
