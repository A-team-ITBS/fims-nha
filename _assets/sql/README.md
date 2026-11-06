# SQL Schema Directory — FIMS NHA

This folder contains the SQL scripts that build the FIMS NHA database — currently
**371 `.sql` files** across 9 phase folders. Scripts are discovered and executed in order by
`install_system.js` at the repo root:

```bash
npm run install-system   # SQL + composer install
npm run sql              # SQL only
npm run sql-consolidated # SQL only, from the consolidated build (see below)
npm run sql-reset        # maintenance files only (resets sequences, keeps data)
npm run sql-truncate     # empties transaction tables (regional/fmd/accounting/treasury), keeps master data
```

> **Important:** Files are sorted by **natural (numeric-aware) sort of their full path** before
> execution. The number prefix on every folder and file is what controls run order.
> Follow the naming convention or the install runs things in the wrong order.

---

## Folder Structure Overview

```
_assets/sql/
│
├── 00 - public/                          # Phase 1: extensions and schemas
├── 01 - schema_table_structure/          # Phase 2: CREATE TABLE
├── 02 - schema_view_structure/           # Phase 3: CREATE VIEW
├── 03 - schema_function_structure/       # Phase 4: CREATE FUNCTION
├── 04 - schema_alter_table_structure/    # Phase 5: ALTER TABLE (+ ALTER VIEW)
├── 05 - schema_sample_data_structure/    # Phase 6: INSERT seed/sample data
├── 06 - schema_truncate_structure/       # Phase 7: TRUNCATE helpers (dev only)
├── 07 - CROSS-MODULE-DEMO/               # NOT run by the installer — manual demo seed
└── 07 - schema_maintenance_structure/    # Runs LAST, always (see Maintenance below)
```

| Phase                                | `.sql` files | Notes                                        |
|--------------------------------------|--------------|----------------------------------------------|
| `00 - public`                        | 2            | `init.sql`, `schema.sql`                     |
| `01 - schema_table_structure`        | 77           | `CREATE TABLE` only                          |
| `02 - schema_view_structure`         | 59           | `CREATE VIEW` (53 of them in ACCOUNTING)     |
| `03 - schema_function_structure`     | 6            | one per module                               |
| `04 - schema_alter_table_structure`  | 128          | the largest phase — all incremental changes  |
| `05 - schema_sample_data_structure`  | 88           | seed / demo data                             |
| `06 - schema_truncate_structure`     | 1            | fully commented out — safe to leave in place |
| `07 - CROSS-MODULE-DEMO`             | 8            | **excluded** from the installer              |
| `07 - schema_maintenance_structure`  | 2            | hoisted to the very end of every run         |

---

## Module Subfolders

Each phase is divided into module subfolders. **The numbers are not the same in every phase** —
phase `04` uses a different set from phases `01`/`02`/`03`/`05`. Always copy the numbering that
the phase you're editing already uses.

| Module      | In phases `01`, `02`, `03`, `05` | In phase `04`        |
|-------------|----------------------------------|----------------------|
| shared      | `1.1 - shared`                   | `00 - shared`        |
| UAC         | `01 - UAC`                       | `01 - UAC`           |
| FMD         | `02 - FMD`                       | `01 - FMD`           |
| ACCOUNTING  | `03 - ACCOUNTING`                | `02 - ACCOUNTING`    |
| TREASURY    | `04 - TREASURY`                  | `03 - TREASURY`      |
| WORKFLOW    | `05 - WORKFLOW`                  | `04 - WORKFLOW`      |
| REGIONAL    | `06 - REGIONAL`                  | `05 - REGIONAL`      |
| INTEGRATION | `06 - INTEGRATION`               | `07 - INTEGRATION`   |
| DISTRICT    | `07 - DISTRICT`                  | `06 - DISTRICT`      |

Not every module exists in every phase — only create the folder when you actually have a file
for it.

Inside each module folder, the subfolder name matches the phase type:

```
[module folder]/
├── 01 - table/        # phase 01
├── 02 - views/        # phase 02
├── 03 - function/     # phase 03
├── 04 - alter_table/  # phase 04
├── 05 - alter_view/   # phase 04 — CREATE OR REPLACE VIEW on existing views
├── 05 -sample_data/   # phase 05  ← note: NO space after the dash
├── 06 - truncate/     # phase 06
└── 07 - maintenance/  # phase 07
```

> **Gotcha:** the sample-data folder is literally `05 -sample_data` (no space after the dash),
> unlike every other folder. Copy an existing one rather than typing it; a folder named
> `05 - sample_data` sorts differently and creates a second, parallel folder.

---

## How the Installer Picks Up Files

`install_system.js` walks these roots **in this order**, sorting each one internally:

1. `_assets/sql/`
2. `ACCOUNTING-BAPD/sql/` (11 files — the BAPD module keeps its own SQL tree)

It then applies two rules:

- **Demo trees are skipped.** Any folder named `07 - cross-module-demo` (case-insensitive) is
  excluded, so `07 - CROSS-MODULE-DEMO/` never runs during an install. Run it by hand
  (`00 - run_all.sql`) when you want the cross-module demo dataset — see its own
  [README](07%20-%20CROSS-MODULE-DEMO/README.md).
- **Maintenance files are hoisted to the very end.** Anything under
  `07 - schema_maintenance_structure/` runs after *all* other files from *all* roots. This
  matters because `reset_all_sequences.sql` needs to see the final `MAX(id)` of every table —
  including BAPD seed data, which runs after this folder's phase 07.

### Sort order in practice

The sort is `localeCompare(..., { numeric: true })`, which is a natural sort, not a plain
numeric-prefix sort. For phase `01` the module order actually comes out as:

```
1. 01 - UAC
2. 1.1 - shared     ← second, not first
3. 02 - FMD
4. 03 - ACCOUNTING
5. 04 - TREASURY
6. 05 - WORKFLOW
7. 06 - INTEGRATION
8. 06 - REGIONAL
9. 07 - DISTRICT
```

> **Note:** `1.1 - shared` sorts *after* `01 - UAC`, so shared tables are **not** created first.
> This works today because UAC's tables don't have foreign keys into `shared`. If you add a
> `uac` table that references a `shared` table, the install will fail on a missing relation —
> put the FK in phase `04` instead of in the `CREATE TABLE`.

### Run modes

A full run prompts twice:

1. **Overwrite the database?** If the target database already exists, you're offered
   `DROP DATABASE` and recreate. Say no to keep your data.
2. **Run ALL files, or only NEW ones?** "New" compares against `.sql_run_history.json` at the
   repo root, which records the relative path of every file that has run successfully. Use
   *ALL* on a fresh database and *NEW* after pulling changes.

Because `ON_ERROR_STOP=1` is set, **one SQL error stops the whole install**, and the failed file
is not written to history.

---

## Phase Details

### `00 - public/`
Sets up the PostgreSQL environment. Runs first.

| File          | Purpose                                                                     |
|---------------|-----------------------------------------------------------------------------|
| `init.sql`    | `DROP` then `CREATE EXTENSION "uuid-ossp"` in `public` (for UUID generation) |
| `schema.sql`  | `CREATE SCHEMA IF NOT EXISTS` for all nine module schemas                    |

### `01 - schema_table_structure/`
`CREATE TABLE` statements only. Do **not** put `ALTER TABLE` here — those belong in phase `04`.

### `02 - schema_view_structure/`
`CREATE VIEW` statements. Runs after tables exist. Use
`DROP VIEW IF EXISTS … CASCADE;` before `CREATE VIEW` so re-runs are idempotent — see
[`04 - TREASURY/02 - views/02 - v_bank_transactions.sql`](02%20-%20schema_view_structure/04%20-%20TREASURY/02%20-%20views/02%20-%20v_bank_transactions.sql)
for the house style, including a header comment that states the view's purpose and its row
grain.

### `03 - schema_function_structure/`
`CREATE FUNCTION` / `CREATE PROCEDURE`. May depend on both tables and views.

### `04 - schema_alter_table_structure/`
**All** incremental schema changes — this is where most day-to-day work goes (128 files).
Adding or dropping a column, renaming, changing a type, adding a constraint or FK. Because it
runs after phase `01`, the table already exists.

ACCOUNTING also has a `05 - alter_view/` subfolder here for `CREATE OR REPLACE VIEW` statements
that patch a view defined back in phase `02`.

### `05 - schema_sample_data_structure/`
`INSERT` statements for seed and demo data. Runs after tables, views and functions are ready.

### `06 - schema_truncate_structure/`
Dev-only `TRUNCATE` helpers for wiping a dataset and starting over. The one file present
(`01 - accounting_jev_truncate.sql`) is **entirely commented out**, which is why it can sit in
the installer's path harmlessly. Uncomment it locally when you need it — never on production.

### `07 - CROSS-MODULE-DEMO/`
A hand-run, end-to-end demo dataset that threads one scenario through FMD → BAPD → DCD → GAD →
Treasury → LIBIS → Workflow. Excluded from the installer; run `00 - run_all.sql` yourself.

### `07 - schema_maintenance_structure/`
Runs dead last, after every other root.

| File                          | Purpose                                                  |
|-------------------------------|----------------------------------------------------------|
| `1.1 - shared/…/reset_all_sequences.sql`  | Re-syncs every sequence to its table's `MAX(id)` |
| `1.4 - accounting/…/fix_jev_terminal_status.sql` | Normalizes JEV terminal statuses         |

`npm run sql-reset` runs **only** these files, with no DROP/recreate and no run-mode prompt.

---

## Database Schemas

Created by `00 - public/schema.sql`:

| Schema        | Purpose                                                             |
|---------------|---------------------------------------------------------------------|
| `shared`      | Reference data used by every module (COA, bank accounts, books, …)   |
| `uac`         | User accounts, levels, permissions, audit logs, ID-format configs    |
| `fmd`         | Financial Management Department — appropriations, allocations         |
| `accounting`  | Vouchers, JEVs, ledgers, financial statements                        |
| `treasury`    | Cash management — checks, collections, fund transfers, bank rec       |
| `workflow`    | Approval workflow definitions, instances and history                 |
| `regional`    | Regional office records                                              |
| `district`    | District office records                                              |
| `integration` | External-system integration staging (LIBIS, BCS, PIS)                |

`document` is present but commented out in `schema.sql`.

---

## Naming Rules

### Folder names
Zero-padded number prefix, then ` - `, then a descriptive name:

```
01 - UAC             ✔ correct
03 - ACCOUNTING      ✔ correct
1.1 - shared         ✔ correct in phases 01/02/03/05 — this is the real convention
00 - shared          ✔ correct in phase 04
shared               ✘ wrong  (no number prefix — sorts unpredictably)
```

### File names
Same rule:

```
01 - uac_structure_data.sql     ✔ correct
uac_structure_data.sql          ✘ wrong
```

If a module needs several files in one phase, increment the number and **never reuse one**:

```
01 - users_table.sql
02 - roles_table.sql
03 - permissions_table.sql
```

> **Heads-up:** this rule isn't currently clean everywhere. `04 - schema_alter_table_structure/`
> `02 - ACCOUNTING/04 - alter_table/` already has 11 duplicated prefixes (`12`, `13`, `16`, `22`,
> `24`, `27`, `29`, `32`, `33`, `38`, `44`). Two files sharing a number fall back to
> alphabetical order on the rest of the name, which is fine only as long as they don't depend on
> each other. Don't add to the pile — check the folder before choosing your number.

---

## Where to Put New SQL Files

| What you are doing                                     | Correct location                                            |
|--------------------------------------------------------|-------------------------------------------------------------|
| Creating a new table                                   | `01 - schema_table_structure/[module]/01 - table/`           |
| Adding / removing a column on an existing table        | `04 - schema_alter_table_structure/[module]/04 - alter_table/` |
| Renaming a column or changing a data type              | `04 - schema_alter_table_structure/[module]/04 - alter_table/` |
| Adding a foreign key to an existing table              | `04 - schema_alter_table_structure/[module]/04 - alter_table/` |
| Creating a new view                                    | `02 - schema_view_structure/[module]/02 - views/`             |
| Changing a view that already exists                    | `04 - schema_alter_table_structure/[module]/05 - alter_view/` |
| Creating a new function or procedure                   | `03 - schema_function_structure/[module]/03 - function/`      |
| Inserting default or sample data                       | `05 - schema_sample_data_structure/[module]/05 -sample_data/` |
| A dev-only wipe script                                 | `06 - schema_truncate_structure/[module]/06 - truncate/` (commented out) |
| Something that must run after all seed data            | `07 - schema_maintenance_structure/[module]/07 - maintenance/` |

---

## Common Mistakes That Cause Install Errors

`ON_ERROR_STOP=1` means **one SQL error stops the entire install immediately**.

| Mistake                                                     | Why it fails                                                        |
|-------------------------------------------------------------|---------------------------------------------------------------------|
| Putting `ALTER TABLE` inside `01 - schema_table_structure`   | Conflicts with the original `CREATE TABLE`; wrong phase              |
| Creating a view in phase `01`                                | The tables it reads don't exist yet                                  |
| Missing number prefix on a new file or folder                | Sorts to an unexpected position and runs at the wrong time            |
| Typing `05 - sample_data` instead of `05 -sample_data`       | Creates a second, parallel folder that sorts differently             |
| Editing an existing `.sql` file to add a column              | Duplicate-column error on databases that already ran it — use phase `04` |
| Reusing a number already taken in the same folder            | Two files sort ambiguously; one may run before its dependency        |
| `CREATE VIEW` without a preceding `DROP VIEW IF EXISTS`      | Fails on re-run when the column list changed                         |
| Adding a `uac` table with an FK into `shared`                | `shared` is created *after* `01 - UAC` — put the FK in phase `04`    |

---

## Consolidated Build

`consolidate_sql.js` (repo root) rebuilds `_assets/sql-consolidated/` from this folder:

```
_assets/sql-consolidated/
├── by-phase/    # 13 files: 00 - public.sql … 12 - maintenance.sql (incl. BAPD)
└── by-schema/   # 10 files: one per schema — 01 - shared.sql, 04 - accounting.sql, …
```

`npm run sql-consolidated` re-runs the consolidator and then installs from `by-phase/` instead
of walking this tree — far fewer `psql` invocations, so it's noticeably faster.

**Treat `sql-consolidated/` as a derived artifact.** Always edit the source files here; never
hand-edit a consolidated file, since the next consolidator run overwrites it.

---

## Example: Adding a Column to an Existing Table

**Scenario:** add a `remarks` column to `accounting.vouchers`.

1. Go to `04 - schema_alter_table_structure/02 - ACCOUNTING/04 - alter_table/`
   (note: ACCOUNTING is `02` in phase 04, not `03`).
2. Create a file with the next unused number — the folder currently tops out at `44`, so
   `45 - vouchers_add_remarks.sql`.
3. Write it idempotently:
   ```sql
   ALTER TABLE accounting.vouchers
       ADD COLUMN IF NOT EXISTS remarks TEXT;
   ```
4. `npm run sql` → choose **Run only NEW SQL files**.

## Example: Creating a New Table

**Scenario:** create `fmd.budget_allocations`.

1. Go to `01 - schema_table_structure/02 - FMD/01 - table/`.
2. Create `07 - fmd_budget_allocations.sql` (next unused number in that folder).
3. Write the `CREATE TABLE`, using `IF NOT EXISTS` so re-runs are safe.
4. `npm run sql`.

---

## Running the Install

```bash
npm run install-system    # database + PHP dependencies
npm run sql               # SQL only
npm run sql-consolidated  # SQL only, from the consolidated build (faster)
npm run sql-reset         # maintenance only — resets sequences, keeps data
```

The installer prompts for your PostgreSQL connection details (host, port, username, password,
database name). On Windows, if `psql` isn't on `PATH` it offers the PostgreSQL installs it can
find, or lets you type a path to `psql.exe`.
