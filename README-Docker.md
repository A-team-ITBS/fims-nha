# NHA FIMS - Docker Setup (Full Self-Contained Stack)

This project ships a full Docker stack: **everything** you need to run NHA FIMS
locally lives in containers. You do NOT need PostgreSQL, Redis, or MinIO
installed on Windows — Docker provides them.

## What's inside

| Service    | Image             | Host port    | Purpose                          |
|------------|-------------------|--------------|----------------------------------|
| `nginx`    | `nginx:alpine`    | `8080`       | Web server (entry point)         |
| `php`      | built from `Dockerfile` | (internal) | PHP 8.2-FPM running the modules |
| `postgres` | `postgres:16-alpine` | `5432`    | Main database (`fims`) |
| `redis`    | `redis:7-alpine`  | `6379`       | Cache / queues                   |
| `minio`    | `minio/minio`     | `9010` / `9011` | S3-compatible object storage  |

Volumes persist data between restarts:
`fims-postgres-data`, `fims-redis-data`, `fims-minio-data`.

## Prerequisites

- **Docker Desktop for Windows** (WSL2 backend recommended)
- ~4 GB free disk space for images and volumes

## Quick Start

```bash
# From project root: D:\projects\fims
docker-compose up -d --build
```

First run takes ~5 minutes (downloads Postgres/Redis/MinIO images, builds the
PHP image, runs `composer install` for all 9 modules). Subsequent starts are
fast (a few seconds).

### Access points

| URL                                | What                               |
|------------------------------------|------------------------------------|
| http://localhost:8080/             | Main dashboard                     |
| http://localhost:9011/             | MinIO web console (S3 admin UI)    |

MinIO console login: `s3phatneglo-archive` / `0yq5h3to9`

## Initializing the database

The `postgres` container comes up **empty** — you still need to load the schema.

### Option 1 - Use the project's SQL installer (recommended)
```bash
docker-compose exec php bash -c "cd /var/www/html && node install_system.js --sql-only"
```

### Option 2 - Load consolidated SQL manually
```bash
docker-compose exec -T postgres psql -U postgres -d fims \
  < "_assets/sql-consolidated/by-phase/01 - tables.sql"
```

### Option 3 - Restore from an existing dump
```bash
docker-compose exec -T postgres psql -U postgres -d fims < your_dump.sql
```

## Common commands

```bash
# Start / stop
docker-compose up -d
docker-compose down

# Rebuild after changing Dockerfile
docker-compose up -d --build

# Logs (all services, follow)
docker-compose logs -f

# Just PHP or just Nginx logs
docker-compose logs -f php
docker-compose logs -f nginx

# Shell inside PHP container
docker-compose exec php bash

# Run psql inside the DB container
docker-compose exec postgres psql -U postgres -d govflow360_nha

# Run composer inside a specific module
docker-compose exec php bash -c "cd /var/www/html/UAC && composer install"
```

## Running host-side scripts (Node, etc.)

Some project scripts (`install_system.js`, `consolidate_sql.js`) run on the
Windows host, not inside Docker. When they do, they hit `localhost:5432` and
`localhost:6379` — which works because the containers expose those ports.

If a host script complains it can't connect, remember: `.env` uses the Docker
service hostnames (`postgres`, `redis`, `minio`). For host scripts you can:

1. Run them **inside** the container: `docker-compose exec php <cmd>`, or
2. Temporarily set `DB_HOST=localhost` and `REDIS_HOST=localhost` in `.env`.

## Wiping and starting fresh

```bash
docker-compose down -v          # -v removes the data volumes too
docker-compose up -d --build
```

**Warning:** `-v` deletes the DB, Redis, and MinIO data.

## Troubleshooting

**Port already in use (5432/6379/8080/9010/9011)**
Something on Windows already listens on that port (often a locally installed
Postgres). Stop it, or change the host-side port mapping in `docker-compose.yml`
(e.g., `"5433:5432"`).

**`composer install` failed on a module**
Run it manually to see the error:
```bash
docker-compose exec php bash -c "cd /var/www/html/UAC && composer install -vvv"
```

**File permission issues in `temp_uploads/`**
```bash
docker-compose exec php chown -R www-data:www-data /var/www/html/temp_uploads
```

**Can't connect to DB from PHP**
Verify Postgres is healthy:
```bash
docker-compose ps
docker-compose logs postgres
```

## File structure

```
Dockerfile              PHP-FPM 8.2 image with all extensions
docker-compose.yml      Full stack definition (php, nginx, postgres, redis, minio)
nginx.conf              Nginx routing for all FIMS modules
.env                    Runtime config (uses docker service hostnames)
.dockerignore           Files excluded from the PHP image build
```

Backups of the previous minimal setup are kept as
`docker-compose.yml.backup`, `.env.backup`, `nginx.conf.backup`, `Dockerfile.backup`.
