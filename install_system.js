#!/usr/bin/env node

const fs = require('fs-extra');
const path = require('path');
const os = require('os');
const inquirer = require('inquirer');
const chalk = require('chalk');

function parseArgs(argv) {
    const rest = argv.slice(2);
    const flags = new Set(rest);
    const getValue = (name) => {
        const prefix = `--${name}=`;
        const found = rest.find((a) => a.startsWith(prefix));
        return found ? found.slice(prefix.length).trim() : null;
    };
    return {
        skipSql: flags.has('--skip-sql'),
        skipComposer: flags.has('--skip-composer'),
        sqlOnly: flags.has('--sql-only'),
        composerOnly: flags.has('--composer-only'),
        consolidated: flags.has('--consolidated'),
        resetOnly: flags.has('--reset-only'),
        truncateOnly: flags.has('--truncate-only'),
        restoreSchemaOnly: flags.has('--restore-schema-only'),
        restoreSchemaFrom: getValue('restore-schema-from'),
        restoreSchemaName: getValue('restore-schema'),
        restoreTableOnly: flags.has('--restore-table-only'),
        restoreTableFrom: getValue('restore-table-from'),
        restoreTableName: getValue('restore-table')
    };
}

// A "maintenance" SQL file is one under the 07 - schema_maintenance_structure
// phase (granular layout) OR the final NN - maintenance.sql produced by the
// consolidator. Used both to hoist these files to the end of a full install
// and to filter down to just them for --reset-only.
function isMaintenanceFile(f) {
    const posix = String(f).replace(/\\/g, '/');
    return posix.includes('/07 - schema_maintenance_structure/')
        || /[\\/]\d\d - maintenance\.sql$/i.test(posix);
}

// Empties every transaction table in regional/fmd/accounting/treasury while
// keeping master/config data (shared, uac, *_config/_types/... tables).
// Same script `npm run demo-reset` uses, run here WITHOUT reseeding demo data.
const TRUNCATE_TRANSACTIONS_SQL = path.join(
    process.cwd(), '_assets', 'sql', '07 - CROSS-MODULE-DEMO', '01 - RESET_truncate_transactions.sql'
);

// Demo-only SQL trees that should never run in the standard installer flow.
const EXCLUDED_SQL_DIR_NAMES = new Set([
    '07 - cross-module-demo',
    '03 - afims'
]);

async function walkFiles(rootDir, shouldIncludeFile, shouldSkipDir) {
    const results = [];
    async function walk(currentDir) {
        const entries = await fs.readdir(currentDir, { withFileTypes: true });
        for (const entry of entries) {
            const fullPath = path.join(currentDir, entry.name);
            if (entry.isDirectory()) {
                if (!shouldSkipDir(fullPath)) {
                    await walk(fullPath);
                }
            } else if (entry.isFile()) {
                if (shouldIncludeFile(fullPath)) {
                    results.push(fullPath);
                }
            }
        }
    }
    if (await fs.pathExists(rootDir)) {
        await walk(rootDir);
    }
    return results;
}

function spawnAsync(command, args, options) {
    const { spawn } = require('child_process');
    return new Promise((resolve, reject) => {
        const child = spawn(command, args, { shell: false, ...options });
        let stdout = '';
        let stderr = '';

        if (child.stdout) {
            child.stdout.on('data', (chunk) => {
                stdout += chunk.toString();
                if (stdout.length > 20000) stdout = stdout.slice(-20000);
            });
        }

        if (child.stderr) {
            child.stderr.on('data', (chunk) => {
                stderr += chunk.toString();
                if (stderr.length > 8000) stderr = stderr.slice(-8000);
            });
        }

        child.on('error', reject);
        child.on('close', (code) => {
            resolve({ code, stdout, stderr });
        });
    });
}

async function canRunCommand(command, args = ['--version'], options = {}) {
    try {
        const { code } = await spawnAsync(command, args, {
            stdio: ['ignore', 'ignore', 'pipe'],
            env: process.env,
            ...options
        });
        return code === 0;
    } catch (error) {
        if (error && error.code === 'ENOENT') return false;
        return false;
    }
}

async function resolveComposerCommand() {
    if (process.env.COMPOSER_PATH && await fs.pathExists(process.env.COMPOSER_PATH)) {
        return { command: process.env.COMPOSER_PATH, shell: process.platform === 'win32' };
    }

    if (process.platform === 'win32') {
        if (await canRunCommand('composer', ['--version'], { shell: true })) {
            return { command: 'composer', shell: true };
        }
        // Try common wrapper names that require cmd.exe/shell to execute.
        if (await canRunCommand('composer.bat', ['--version'], { shell: true })) {
            return { command: 'composer.bat', shell: true };
        }
        if (await canRunCommand('composer.cmd', ['--version'], { shell: true })) {
            return { command: 'composer.cmd', shell: true };
        }

        const { composerPath } = await inquirer.prompt([
            {
                type: 'input',
                name: 'composerPath',
                message: 'Composer not found. Enter full path to composer.bat/composer.cmd (leave blank to try `composer`):'
            }
        ]);

        const trimmed = (composerPath || '').trim();
        if (!trimmed) return { command: 'composer', shell: true };
        if (!await fs.pathExists(trimmed)) throw new Error(`Composer not found at: ${trimmed}`);
        return { command: trimmed, shell: true };
    }

    if (await canRunCommand('composer', ['--version'])) {
        return { command: 'composer', shell: false };
    }

    const { composerPath } = await inquirer.prompt([
        {
            type: 'input',
            name: 'composerPath',
            message: 'Composer not found. Enter full path to `composer` (leave blank to try `composer`):'
        }
    ]);

    const trimmed = (composerPath || '').trim();
    if (!trimmed) return { command: 'composer', shell: false };
    if (!await fs.pathExists(trimmed)) throw new Error(`Composer not found at: ${trimmed}`);
    return { command: trimmed, shell: false };
}

async function findWindowsPsqlCandidates() {
    const roots = [
        'C:\\Program Files\\PostgreSQL',
        'C:\\Program Files (x86)\\PostgreSQL'
    ];

    const candidates = [];
    for (const root of roots) {
        if (!await fs.pathExists(root)) continue;
        const entries = await fs.readdir(root, { withFileTypes: true });
        for (const entry of entries) {
            if (!entry.isDirectory()) continue;
            const psqlPath = path.join(root, entry.name, 'bin', 'psql.exe');
            if (await fs.pathExists(psqlPath)) {
                candidates.push(psqlPath);
            }
        }
    }

    candidates.sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));
    return candidates;
}

async function resolvePsqlCommand() {
    // Allow explicit override.
    if (process.env.PSQL_PATH && await fs.pathExists(process.env.PSQL_PATH)) {
        return process.env.PSQL_PATH;
    }

    // Default: use PATH.
    if (await canRunCommand('psql')) return 'psql';

    // If psql isn't available, guide the user.
    if (process.platform === 'win32') {
        const candidates = await findWindowsPsqlCandidates();
        const choices = [
            ...candidates.map((p) => ({ name: p, value: p })),
            { name: 'Use default `psql` (from PATH)', value: 'psql' },
            { name: 'Enter custom path...', value: '__custom__' }
        ];

        const { picked } = await inquirer.prompt([
            {
                type: 'list',
                name: 'picked',
                message: 'psql was not found in PATH. Choose a psql.exe to use:',
                choices
            }
        ]);

        if (picked === '__custom__') {
            const { customPath } = await inquirer.prompt([
                {
                    type: 'input',
                    name: 'customPath',
                    message: 'Enter full path to psql.exe (leave blank to use default `psql`):'
                }
            ]);
            const trimmed = (customPath || '').trim();
            if (!trimmed) return 'psql';
            if (!await fs.pathExists(trimmed)) {
                throw new Error(`psql.exe not found at: ${trimmed}`);
            }
            return trimmed;
        }

        if (picked !== 'psql' && !await fs.pathExists(picked)) {
            throw new Error(`psql.exe not found at: ${picked}`);
        }
        return picked;
    }

    const { customPath } = await inquirer.prompt([
        {
            type: 'input',
            name: 'customPath',
            message: 'psql was not found in PATH. Enter full path to `psql` (leave blank to keep using `psql`):'
        }
    ]);

    const trimmed = (customPath || '').trim();
    if (!trimmed) return 'psql';
    if (!await fs.pathExists(trimmed)) {
        throw new Error(`psql not found at: ${trimmed}`);
    }
    return trimmed;
}

async function resolvePgDumpCommand(psqlCommand) {
    if (process.env.PG_DUMP_PATH && await fs.pathExists(process.env.PG_DUMP_PATH)) {
        return process.env.PG_DUMP_PATH;
    }

    // pg_dump ships right next to psql in the same bin/ folder — if we already
    // resolved an explicit psql path, try its sibling before anything else.
    if (psqlCommand !== 'psql' && await fs.pathExists(psqlCommand)) {
        const dir = path.dirname(psqlCommand);
        const ext = path.extname(psqlCommand);
        const candidate = path.join(dir, `pg_dump${ext}`);
        if (await fs.pathExists(candidate)) return candidate;
    }

    if (await canRunCommand('pg_dump')) return 'pg_dump';

    const { customPath } = await inquirer.prompt([
        {
            type: 'input',
            name: 'customPath',
            message: 'pg_dump was not found. Enter full path to pg_dump/pg_dump.exe (leave blank to try `pg_dump`):'
        }
    ]);

    const trimmed = (customPath || '').trim();
    if (!trimmed) return 'pg_dump';
    if (!await fs.pathExists(trimmed)) {
        throw new Error(`pg_dump not found at: ${trimmed}`);
    }
    return trimmed;
}

async function promptPostgresConfig() {
    const answers = await inquirer.prompt([
        {
            type: 'input',
            name: 'host',
            message: 'PostgreSQL host:',
            default: 'localhost'
        },
        {
            type: 'input',
            name: 'port',
            message: 'PostgreSQL port:',
            default: '5432',
            validate: (input) => (/^\d+$/.test(input) ? true : 'Port must be a number')
        },
        {
            type: 'input',
            name: 'user',
            message: 'PostgreSQL username:',
            default: 'postgres'
        },
        {
            type: 'password',
            name: 'password',
            message: 'PostgreSQL password:',
            mask: '*'
        },
        {
            type: 'input',
            name: 'database',
            message: 'Database name:',
            default: 'health_sync_db'
        }
    ]);

    return {
        host: answers.host,
        port: Number(answers.port),
        user: answers.user,
        password: answers.password ?? '',
        database: answers.database
    };
}

const SQL_HISTORY_PATH = path.join(process.cwd(), '.sql_run_history.json');

async function loadSqlHistory() {
    try {
        if (await fs.pathExists(SQL_HISTORY_PATH)) {
            const raw = await fs.readFile(SQL_HISTORY_PATH, 'utf8');
            return JSON.parse(raw);
        }
    } catch {
        // corrupted or missing — start fresh
    }
    return {};
}

async function saveSqlHistory(history) {
    await fs.writeFile(SQL_HISTORY_PATH, JSON.stringify(history, null, 2), 'utf8');
}

function toPosixPath(filePath) {
    return String(filePath).replace(/\\/g, '/');
}

function getTemplatePlaceholders(template) {
    const re = /\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/g;
    const keys = new Set();
    let match;
    while ((match = re.exec(template)) !== null) {
        keys.add(match[1]);
    }
    return Array.from(keys).sort((a, b) => a.localeCompare(b));
}

function renderTemplate(template, values) {
    return template.replace(/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/g, (_, key) => {
        const val = values[key];
        return val === undefined || val === null ? '' : String(val);
    });
}

async function loadConfigTemplate() {
    const templatePath = path.join(process.cwd(), 'config.sample.php');
    if (!await fs.pathExists(templatePath)) {
        return null;
    }
    return fs.readFile(templatePath, 'utf8');
}

async function promptConfigTemplateValues({ pg, templatePlaceholders }) {
    const questions = [];
    const defaults = {
        host: pg.host,
        port: String(pg.port),
        username: pg.user,
        password: pg.password,
        db_name: pg.database,
        localhost_name: process.env.LOCALHOST_NAME || 'localhost'
    };

    for (const key of templatePlaceholders) {
        if (key === 'current_path') continue; // system-specific
        if (key in defaults) continue; // already have a value
        questions.push({
            type: 'input',
            name: key,
            message: `Enter value for {{${key}}}:`,
            default: ''
        });
    }

    // If localhost_name is needed, prompt once with a sensible default.
    if (templatePlaceholders.includes('localhost_name')) {
        questions.unshift({
            type: 'input',
            name: 'localhost_name',
            message: 'Localhost/domain name for temp upload download URL (e.g., gf360-pampanga.localhost):',
            default: defaults.localhost_name
        });
    }

    // If template uses db placeholders but SQL step was skipped, pg is still provided by caller.
    const extra = questions.length ? await inquirer.prompt(questions) : {};
    return { ...defaults, ...extra };
}

async function ensureSystemConfig({ systemDir, template, values, overwriteAll }) {
    const srcDir = path.join(systemDir, 'src');
    const targetPath = path.join(srcDir, 'config.development.php');

    if (!await fs.pathExists(srcDir)) {
        // Not a PHPMaker system folder; skip quietly.
        return { wrote: false, skipped: true, targetPath };
    }

    const alreadyExists = await fs.pathExists(targetPath);
    if (alreadyExists && !overwriteAll) {
        return { wrote: false, skipped: true, targetPath };
    }

    await fs.ensureDir(srcDir);
    // Use repo root for temp uploads (shared across systems)
    const currentPath = toPosixPath(process.cwd());

    const rendered = renderTemplate(template, {
        ...values,
        current_path: currentPath
    });

    await fs.writeFile(targetPath, rendered, 'utf8');

    // Ensure temp_uploads folder exists for the configured UPLOAD_TEMP_PATH.
    await fs.ensureDir(path.join(process.cwd(), 'temp_uploads'));

    return { wrote: true, skipped: false, targetPath };
}

async function getSqlFiles(sqlRoot) {
    const sqlFiles = await walkFiles(
        sqlRoot,
        (filePath) => path.extname(filePath).toLowerCase() === '.sql',
        (dirPath) => {
            const base = path.basename(dirPath).toLowerCase();
            return base === 'node_modules' || base === '.git' || EXCLUDED_SQL_DIR_NAMES.has(base);
        }
    );

    sqlFiles.sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));
    return sqlFiles;
}

function escapeSqlLiteral(value) {
    return String(value).replace(/'/g, "''");
}

function quotePgIdent(value) {
    return `"${String(value).replace(/"/g, '""')}"`;
}

async function runPsqlCommand(psqlCommand, pg, database, sql) {
    const args = [
        '--no-psqlrc',
        '--set',
        'ON_ERROR_STOP=1',
        '--host',
        pg.host,
        '--port',
        String(pg.port),
        '--username',
        pg.user,
        '--dbname',
        database,
        '--command',
        sql
    ];

    const { code, stdout, stderr } = await spawnAsync(psqlCommand, args, {
        env: { ...process.env, PGPASSWORD: pg.password, PGCLIENTENCODING: "UTF8" },
        stdio: ['ignore', 'pipe', 'pipe']
    });

    return { code, stdout, stderr };
}

async function listSchemaTables(psqlCommand, pg, database, schema) {
    const sql = `SELECT tablename FROM pg_tables WHERE schemaname = '${escapeSqlLiteral(schema)}' ORDER BY tablename;`;
    const args = [
        '--no-psqlrc',
        '--tuples-only',
        '--no-align',
        '--set',
        'ON_ERROR_STOP=1',
        '--host',
        pg.host,
        '--port',
        String(pg.port),
        '--username',
        pg.user,
        '--dbname',
        database,
        '--command',
        sql
    ];

    const { code, stdout, stderr } = await spawnAsync(psqlCommand, args, {
        env: { ...process.env, PGPASSWORD: pg.password, PGCLIENTENCODING: "UTF8" },
        stdio: ['ignore', 'pipe', 'pipe']
    });

    if (code !== 0) {
        throw new Error((stderr || '').trim() || `Failed listing tables in schema "${schema}".`);
    }

    return (stdout || '').split('\n').map((s) => s.trim()).filter(Boolean);
}

// Tables OUTSIDE `schema` can hold a foreign key into it (e.g. a treasury
// table referencing uac.departments); plain TRUNCATE refuses to run rather
// than silently violate referential integrity. This walks pg_constraint the
// same way TRUNCATE ... CASCADE would, so the full blast radius can be shown
// to the user before they decide to add CASCADE.
async function getCascadeTruncateClosure(psqlCommand, pg, database, schema, table = null) {
    const seedFilter = table
        ? `schemaname = '${escapeSqlLiteral(schema)}' AND tablename = '${escapeSqlLiteral(table)}'`
        : `schemaname = '${escapeSqlLiteral(schema)}'`;
    const sql = `
        WITH RECURSIVE dep(oid) AS (
            SELECT (quote_ident(schemaname) || '.' || quote_ident(tablename))::regclass::oid
            FROM pg_tables WHERE ${seedFilter}
            UNION
            SELECT c.conrelid
            FROM pg_constraint c
            JOIN dep d ON c.confrelid = d.oid
            WHERE c.contype = 'f'
        )
        SELECT DISTINCT n.nspname || '.' || t.relname
        FROM dep
        JOIN pg_class t ON t.oid = dep.oid
        JOIN pg_namespace n ON n.oid = t.relnamespace
        ORDER BY 1;
    `;
    const args = [
        '--no-psqlrc',
        '--tuples-only',
        '--no-align',
        '--set',
        'ON_ERROR_STOP=1',
        '--host',
        pg.host,
        '--port',
        String(pg.port),
        '--username',
        pg.user,
        '--dbname',
        database,
        '--command',
        sql
    ];

    const { code, stdout, stderr } = await spawnAsync(psqlCommand, args, {
        env: { ...process.env, PGPASSWORD: pg.password, PGCLIENTENCODING: "UTF8" },
        stdio: ['ignore', 'pipe', 'pipe']
    });

    if (code !== 0) {
        throw new Error((stderr || '').trim() || `Failed computing cascade-truncate closure for schema "${schema}".`);
    }

    return (stdout || '').split('\n').map((s) => s.trim()).filter(Boolean);
}

async function truncateSchemaTables(psqlCommand, pg, schema, tables, { cascade = false } = {}) {
    if (tables.length === 0) return;
    const list = tables.map((t) => `${quotePgIdent(schema)}.${quotePgIdent(t)}`).join(', ');
    const sql = `TRUNCATE TABLE ${list} RESTART IDENTITY${cascade ? ' CASCADE' : ''};`;
    const { code, stderr } = await runPsqlCommand(psqlCommand, pg, pg.database, sql);
    if (code !== 0) {
        throw new Error((stderr || '').trim() || `Failed to truncate schema "${schema}" tables.`);
    }
}

async function resolveMaintenanceDatabase(psqlCommand, pg) {
    const candidates = ['postgres', 'template1'];
    for (const db of candidates) {
        try {
            const { code } = await runPsqlCommand(psqlCommand, pg, db, 'SELECT 1;');
            if (code === 0) return db;
        } catch {
            // ignore and try next
        }
    }
    return 'postgres';
}

async function databaseExists(psqlCommand, pg, maintenanceDb, databaseName) {
    const sql = `SELECT 1 FROM pg_database WHERE datname='${escapeSqlLiteral(databaseName)}';`;
    const args = [
        '--no-psqlrc',
        '--tuples-only',
        '--no-align',
        '--set',
        'ON_ERROR_STOP=1',
        '--host',
        pg.host,
        '--port',
        String(pg.port),
        '--username',
        pg.user,
        '--dbname',
        maintenanceDb,
        '--command',
        sql
    ];

    const { code, stdout, stderr } = await spawnAsync(psqlCommand, args, {
        env: { ...process.env, PGPASSWORD: pg.password, PGCLIENTENCODING: "UTF8" },
        stdio: ['ignore', 'pipe', 'pipe']
    });

    if (code !== 0) {
        const err = (stderr || '').trim();
        throw new Error(err ? `Failed checking database existence: ${err}` : 'Failed checking database existence.');
    }

    return (stdout || '').trim() === '1';
}

async function ensureDatabaseReady(psqlCommand, pg) {
    const maintenanceDb = await resolveMaintenanceDatabase(psqlCommand, pg);
    const exists = await databaseExists(psqlCommand, pg, maintenanceDb, pg.database);

    if (!exists) {
        const { code, stderr } = await runPsqlCommand(
            psqlCommand,
            pg,
            maintenanceDb,
            `CREATE DATABASE ${quotePgIdent(pg.database)};`
        );
        if (code !== 0) {
            throw new Error((stderr || '').trim() || `Failed to create database ${pg.database}.`);
        }
        console.log(chalk.green(`Created database ${pg.database}.`));
        return;
    }

    const { overwrite } = await inquirer.prompt([
        {
            type: 'confirm',
            name: 'overwrite',
            message: `Database "${pg.database}" already exists. Overwrite it (DROP and recreate)?`,
            default: false
        }
    ]);

    if (!overwrite) {
        throw new Error(`Database "${pg.database}" already exists (aborted).`);
    }

    const terminateSql = `SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='${escapeSqlLiteral(pg.database)}' AND pid <> pg_backend_pid();`;
    await runPsqlCommand(psqlCommand, pg, maintenanceDb, terminateSql);

    const dropSql = `DROP DATABASE IF EXISTS ${quotePgIdent(pg.database)};`;
    const createSql = `CREATE DATABASE ${quotePgIdent(pg.database)};`;

    {
        const { code, stderr } = await runPsqlCommand(psqlCommand, pg, maintenanceDb, dropSql);
        if (code !== 0) throw new Error((stderr || '').trim() || `Failed to drop database ${pg.database}.`);
    }
    {
        const { code, stderr } = await runPsqlCommand(psqlCommand, pg, maintenanceDb, createSql);
        if (code !== 0) throw new Error((stderr || '').trim() || `Failed to create database ${pg.database}.`);
    }

    console.log(chalk.yellow(`Recreated database ${pg.database}.`));
}

async function runSqlFiles(sqlFiles, pg, psqlCommand) {
    if (sqlFiles.length === 0) {
        console.log(chalk.yellow('No SQL files found to execute.'));
        return;
    }

    const history = await loadSqlHistory();

    console.log(chalk.cyan(`Found ${sqlFiles.length} SQL file(s) to execute.`));

    const { runMode } = await inquirer.prompt([
        {
            type: 'list',
            name: 'runMode',
            message: 'How do you want to run the SQL files?',
            choices: [
                { name: `Run ALL SQL files (${sqlFiles.length} file(s))`, value: 'all' },
                { name: 'Run only NEW SQL files (skip already-executed ones)', value: 'new' },
                { name: 'Cancel', value: 'cancel' }
            ]
        }
    ]);

    if (runMode === 'cancel') return;

    let filesToRun = sqlFiles;

    if (runMode === 'new') {
        filesToRun = sqlFiles.filter((f) => {
            const rel = path.relative(process.cwd(), f);
            return !history[rel];
        });

        if (filesToRun.length === 0) {
            console.log(chalk.green('All SQL files have already been executed. Nothing new to run.'));
            return;
        }

        console.log(chalk.cyan(`${filesToRun.length} new SQL file(s) will be run.`));
    }

    const { confirmSql } = await inquirer.prompt([
        {
            type: 'confirm',
            name: 'confirmSql',
            message: `Run ${filesToRun.length} SQL file(s) against ${pg.user}@${pg.host}:${pg.port}/${pg.database}?`,
            default: true
        }
    ]);

    if (!confirmSql) return;

    if (runMode === 'all') {
        await ensureDatabaseReady(psqlCommand, pg);
    } else {
        // For new-only mode, just verify the DB exists — no DROP/recreate offer
        const maintenanceDb = await resolveMaintenanceDatabase(psqlCommand, pg);
        const exists = await databaseExists(psqlCommand, pg, maintenanceDb, pg.database);
        if (!exists) {
            throw new Error(`Database "${pg.database}" does not exist. Run all SQL files first to initialize it.`);
        }
    }

    for (let i = 0; i < filesToRun.length; i++) {
        const filePath = filesToRun[i];
        const display = path.relative(process.cwd(), filePath);
        process.stdout.write(chalk.gray(`[SQL ${i + 1}/${filesToRun.length}] `) + chalk.white(`${display} ... `));

        const args = [
            '--no-psqlrc',
            '--set',
            'ON_ERROR_STOP=1',
            '--host',
            pg.host,
            '--port',
            String(pg.port),
            '--username',
            pg.user,
            '--dbname',
            pg.database,
            '--file',
            filePath
        ];

        try {
            const { code, stderr } = await spawnAsync(psqlCommand, args, {
                env: { ...process.env, PGPASSWORD: pg.password, PGCLIENTENCODING: "UTF8" },
                stdio: ['ignore', 'ignore', 'pipe']
            });

            if (code !== 0) {
                process.stdout.write(chalk.red('FAILED') + '\n');
                if (stderr) console.log(chalk.red(stderr.trim()));
                throw new Error(`psql exited with code ${code} while executing ${display}`);
            }

            process.stdout.write(chalk.green('OK') + '\n');

            // Record this file as executed so it is skipped in future "new only" runs
            const rel = path.relative(process.cwd(), filePath);
            history[rel] = new Date().toISOString();
            await saveSqlHistory(history);
        } catch (error) {
            if (error && error.code === 'ENOENT') {
                throw new Error("psql not found. Install PostgreSQL client tools (psql) or set `PSQL_PATH` to the full path of psql/psql.exe.");
            }
            throw error;
        }
    }
}

async function runMaintenanceReset(sqlFiles, pg, psqlCommand) {
    if (sqlFiles.length === 0) {
        console.log(chalk.yellow('No maintenance SQL files found under 07 - schema_maintenance_structure.'));
        return;
    }

    console.log(chalk.cyan(`Found ${sqlFiles.length} maintenance file(s):`));
    for (const f of sqlFiles) {
        console.log('  ' + chalk.gray(path.relative(process.cwd(), f)));
    }

    const { confirmSql } = await inquirer.prompt([
        {
            type: 'confirm',
            name: 'confirmSql',
            message: `Run maintenance against ${pg.user}@${pg.host}:${pg.port}/${pg.database}?  (DB will NOT be dropped or altered — only sequences reset)`,
            default: true
        }
    ]);
    if (!confirmSql) return;

    // Verify DB exists — no DROP/recreate offer in reset mode.
    const maintenanceDb = await resolveMaintenanceDatabase(psqlCommand, pg);
    const exists = await databaseExists(psqlCommand, pg, maintenanceDb, pg.database);
    if (!exists) {
        throw new Error(`Database "${pg.database}" does not exist. Run the full installer first.`);
    }

    await runSqlFilesWithNotices(sqlFiles, pg, psqlCommand);
}

async function runTruncateTransactions(pg, psqlCommand) {
    if (!await fs.pathExists(TRUNCATE_TRANSACTIONS_SQL)) {
        throw new Error(`Truncate script not found: ${path.relative(process.cwd(), TRUNCATE_TRANSACTIONS_SQL)}`);
    }

    console.log(chalk.red.bold('WARNING: this permanently EMPTIES all transaction tables in'));
    console.log(chalk.red.bold('         regional, fmd, accounting and treasury (and rows that reference them via CASCADE).'));
    console.log(chalk.gray('Kept: shared/uac/public master data, *_config/_types/_templates/_definitions/_rates/_codes tables,'));
    console.log(chalk.gray('      SL registry, fiscal_period_state, sweep_configurations, FMD PR/PO tables.'));

    const maintenanceDb = await resolveMaintenanceDatabase(psqlCommand, pg);
    const exists = await databaseExists(psqlCommand, pg, maintenanceDb, pg.database);
    if (!exists) {
        throw new Error(`Database "${pg.database}" does not exist. Run the full installer first.`);
    }

    // Typing the DB name (not just y/n) so nobody wipes the wrong database by habit.
    console.log(chalk.white(`Target: ${pg.user}@${pg.host}:${pg.port}/${pg.database}`));
    const { confirmName } = await inquirer.prompt([
        {
            type: 'input',
            name: 'confirmName',
            message: `Type ${pg.database} to confirm (anything else = cancel):`,
            // A stray Enter left over from the previous prompt (e.g. a pasted
            // DB name ending in a newline) would otherwise submit this blank.
            validate: (input) => (String(input || '').trim()
                ? true
                : `Please type ${pg.database} (or "no" to cancel)`)
        }
    ]);
    // Accept the name pasted with the quotes shown in older prompts.
    const typed = String(confirmName || '').trim().replace(/^["']|["']$/g, '');
    if (typed !== pg.database) {
        console.log(chalk.yellow(typed
            ? `"${typed}" does not match "${pg.database}". Nothing was truncated.`
            : 'No name entered. Nothing was truncated.'));
        return;
    }

    await runSqlFilesWithNotices([TRUNCATE_TRANSACTIONS_SQL], pg, psqlCommand);
}

// pg_dump's boilerplate preamble (SET statement_timeout, etc.) is version-gated
// to whatever pg_dump binary produced it, not to the server being restored
// into — e.g. `SET transaction_timeout = 0;` was added in PG17's pg_dump and
// breaks a restore against an older server with "unrecognized configuration
// parameter". None of these session settings affect the data being loaded, so
// drop them rather than requiring an exact-version-matched pg_dump/psql pair.
const DUMP_PREAMBLE_SET_RE = /^SET\s+(statement_timeout|lock_timeout|idle_in_transaction_session_timeout|transaction_timeout|client_encoding|standard_conforming_strings|check_function_bodies|xmloption|client_min_messages|row_security|default_table_access_method)\b.*;\s*$/i;
const DUMP_PREAMBLE_SEARCH_PATH_RE = /^SELECT pg_catalog\.set_config\('search_path',.*;\s*$/i;

async function stripUnsupportedDumpPreamble(sqlFilePath) {
    const original = await fs.readFile(sqlFilePath, 'utf8');
    const filtered = original
        .split('\n')
        .filter((line) => !DUMP_PREAMBLE_SET_RE.test(line) && !DUMP_PREAMBLE_SEARCH_PATH_RE.test(line))
        .join('\n');

    if (filtered !== original) {
        await fs.writeFile(sqlFilePath, filtered, 'utf8');
    }
}

// Copies DATA ONLY for one schema from another database on the same server
// into pg.database (e.g. fims_back.uac -> fims.uac after a reset). Schema
// structure is assumed identical between source and target; this does not
// touch DDL, roles, or sequences.
async function runSchemaDataRestore(pg, psqlCommand, pgDumpCommand, { sourceDatabase, schema } = {}) {
    const maintenanceDb = await resolveMaintenanceDatabase(psqlCommand, pg);

    const targetExists = await databaseExists(psqlCommand, pg, maintenanceDb, pg.database);
    if (!targetExists) {
        throw new Error(`Target database "${pg.database}" does not exist. Run the full installer first.`);
    }

    let sourceDb = sourceDatabase;
    if (!sourceDb) {
        const answer = await inquirer.prompt([
            {
                type: 'input',
                name: 'sourceDb',
                message: 'Source database to copy data FROM (e.g. fims_back):',
                validate: (input) => (String(input || '').trim() ? true : 'Required')
            }
        ]);
        sourceDb = answer.sourceDb.trim();
    }

    if (sourceDb === pg.database) {
        throw new Error('Source and target database are the same — nothing to restore.');
    }

    const sourceExists = await databaseExists(psqlCommand, pg, maintenanceDb, sourceDb);
    if (!sourceExists) {
        throw new Error(`Source database "${sourceDb}" does not exist.`);
    }

    let schemaName = schema;
    if (!schemaName) {
        const answer = await inquirer.prompt([
            {
                type: 'input',
                name: 'schemaName',
                message: 'Schema to copy data for:',
                default: 'uac',
                validate: (input) => (String(input || '').trim() ? true : 'Required')
            }
        ]);
        schemaName = answer.schemaName;
    }
    schemaName = schemaName.trim();

    console.log(chalk.white(`This will load DATA ONLY for schema "${schemaName}" from "${sourceDb}" into "${pg.database}".`));
    console.log(chalk.gray('Assumes target tables are empty (e.g. right after a reset/recreate) and the schema'));
    console.log(chalk.gray('structure matches on both sides — existing rows may cause duplicate-key errors.'));

    const { confirmRestore } = await inquirer.prompt([
        {
            type: 'confirm',
            name: 'confirmRestore',
            message: `Proceed copying ${sourceDb}.${schemaName} -> ${pg.database}.${schemaName}?`,
            default: false
        }
    ]);
    if (!confirmRestore) {
        console.log(chalk.yellow('Cancelled. Nothing was copied.'));
        return;
    }

    const { truncateFirst } = await inquirer.prompt([
        {
            type: 'confirm',
            name: 'truncateFirst',
            message: `Truncate existing rows in ${pg.database}.${schemaName} first? (recommended — avoids duplicate-key errors against existing seed/master data)`,
            default: true
        }
    ]);

    if (truncateFirst) {
        const targetTables = await listSchemaTables(psqlCommand, pg, pg.database, schemaName);
        if (targetTables.length > 0) {
            console.log(chalk.cyan(`Truncating ${targetTables.length} table(s) in ${pg.database}.${schemaName}...`));
            try {
                await truncateSchemaTables(psqlCommand, pg, schemaName, targetTables);
            } catch (error) {
                if (!/foreign key constraint/i.test(error.message)) throw error;

                console.log(chalk.yellow(`A table outside "${schemaName}" references it via foreign key, so a plain TRUNCATE is blocked:`));
                console.log(chalk.gray(error.message));

                const closure = await getCascadeTruncateClosure(psqlCommand, pg, pg.database, schemaName);
                const inSchema = new Set(targetTables.map((t) => `${schemaName}.${t}`));
                const outside = closure.filter((q) => !inSchema.has(q));

                console.log(chalk.red.bold(`TRUNCATE ... CASCADE would ALSO empty ${outside.length} table(s) outside "${schemaName}":`));
                for (const q of outside) console.log('  ' + chalk.red(q));

                const { confirmCascade } = await inquirer.prompt([
                    {
                        type: 'confirm',
                        name: 'confirmCascade',
                        message: 'Proceed with TRUNCATE ... CASCADE (this empties the tables listed above too)?',
                        default: false
                    }
                ]);

                if (!confirmCascade) {
                    throw new Error(
                        `Cancelled: truncate of "${schemaName}" is blocked by foreign key references and CASCADE was declined. ` +
                        `Empty the referencing table(s) manually first, or re-run and answer "No" to skip truncation.`
                    );
                }

                await truncateSchemaTables(psqlCommand, pg, schemaName, targetTables, { cascade: true });
            }
        }
    }

    const tmpFile = path.join(os.tmpdir(), `restore-${schemaName}-${Date.now()}.sql`);

    try {
        console.log(chalk.cyan(`Dumping "${schemaName}" data from ${sourceDb}...`));
        const dumpArgs = [
            '--no-owner',
            '--no-privileges',
            '--data-only',
            '--disable-triggers',
            '--schema', schemaName,
            '--host', pg.host,
            '--port', String(pg.port),
            '--username', pg.user,
            '--dbname', sourceDb,
            '--file', tmpFile
        ];
        const { code: dumpCode, stderr: dumpErr } = await spawnAsync(pgDumpCommand, dumpArgs, {
            env: { ...process.env, PGPASSWORD: pg.password, PGCLIENTENCODING: 'UTF8' },
            stdio: ['ignore', 'ignore', 'pipe']
        });
        if (dumpCode !== 0) {
            throw new Error((dumpErr || '').trim() || `pg_dump exited with code ${dumpCode}`);
        }

        await stripUnsupportedDumpPreamble(tmpFile);

        console.log(chalk.cyan(`Loading "${schemaName}" data into ${pg.database}...`));
        const loadArgs = [
            '--no-psqlrc',
            '--set', 'ON_ERROR_STOP=1',
            '--host', pg.host,
            '--port', String(pg.port),
            '--username', pg.user,
            '--dbname', pg.database,
            '--file', tmpFile
        ];
        const { code: loadCode } = await spawnAsync(psqlCommand, loadArgs, {
            env: { ...process.env, PGPASSWORD: pg.password, PGCLIENTENCODING: 'UTF8' },
            stdio: ['ignore', 'inherit', 'inherit']
        });
        if (loadCode !== 0) {
            throw new Error(`psql exited with code ${loadCode} while loading "${schemaName}" data.`);
        }
    } catch (error) {
        if (error && error.code === 'ENOENT') {
            throw new Error("pg_dump or psql not found. Install PostgreSQL client tools, or set `PG_DUMP_PATH`/`PSQL_PATH`.");
        }
        throw error;
    } finally {
        await fs.remove(tmpFile).catch(() => {});
    }

    console.log(chalk.green(`Restored "${schemaName}" data from ${sourceDb} into ${pg.database}.`));
    console.log(chalk.gray('Tip: run `node install_system.js --reset-only` afterwards to resync sequences.'));
}

// Same as runSchemaDataRestore but scoped to a single table instead of a
// whole schema (e.g. fims_back.uac.departments -> fims.uac.departments).
async function runTableDataRestore(pg, psqlCommand, pgDumpCommand, { sourceDatabase, schema, table } = {}) {
    const maintenanceDb = await resolveMaintenanceDatabase(psqlCommand, pg);

    const targetExists = await databaseExists(psqlCommand, pg, maintenanceDb, pg.database);
    if (!targetExists) {
        throw new Error(`Target database "${pg.database}" does not exist. Run the full installer first.`);
    }

    let sourceDb = sourceDatabase;
    if (!sourceDb) {
        const answer = await inquirer.prompt([
            {
                type: 'input',
                name: 'sourceDb',
                message: 'Source database to copy data FROM (e.g. fims_back):',
                validate: (input) => (String(input || '').trim() ? true : 'Required')
            }
        ]);
        sourceDb = answer.sourceDb.trim();
    }

    if (sourceDb === pg.database) {
        throw new Error('Source and target database are the same — nothing to restore.');
    }

    const sourceExists = await databaseExists(psqlCommand, pg, maintenanceDb, sourceDb);
    if (!sourceExists) {
        throw new Error(`Source database "${sourceDb}" does not exist.`);
    }

    let schemaName = schema;
    if (!schemaName) {
        const answer = await inquirer.prompt([
            {
                type: 'input',
                name: 'schemaName',
                message: 'Schema containing the table:',
                default: 'uac',
                validate: (input) => (String(input || '').trim() ? true : 'Required')
            }
        ]);
        schemaName = answer.schemaName;
    }
    schemaName = schemaName.trim();

    let tableName = table;
    if (!tableName) {
        const answer = await inquirer.prompt([
            {
                type: 'input',
                name: 'tableName',
                message: 'Table name to copy data for:',
                validate: (input) => (String(input || '').trim() ? true : 'Required')
            }
        ]);
        tableName = answer.tableName;
    }
    tableName = tableName.trim();

    console.log(chalk.white(`This will load DATA ONLY for table "${schemaName}.${tableName}" from "${sourceDb}" into "${pg.database}".`));
    console.log(chalk.gray('Assumes the target table is empty and its structure matches on both sides —'));
    console.log(chalk.gray('existing rows may cause duplicate-key errors.'));

    const { confirmRestore } = await inquirer.prompt([
        {
            type: 'confirm',
            name: 'confirmRestore',
            message: `Proceed copying ${sourceDb}.${schemaName}.${tableName} -> ${pg.database}.${schemaName}.${tableName}?`,
            default: false
        }
    ]);
    if (!confirmRestore) {
        console.log(chalk.yellow('Cancelled. Nothing was copied.'));
        return;
    }

    const { truncateFirst } = await inquirer.prompt([
        {
            type: 'confirm',
            name: 'truncateFirst',
            message: `Truncate existing rows in ${pg.database}.${schemaName}.${tableName} first? (recommended — avoids duplicate-key errors against existing seed/master data)`,
            default: true
        }
    ]);

    if (truncateFirst) {
        try {
            await truncateSchemaTables(psqlCommand, pg, schemaName, [tableName]);
        } catch (error) {
            if (!/foreign key constraint/i.test(error.message)) throw error;

            console.log(chalk.yellow(`A table outside "${schemaName}.${tableName}" references it via foreign key, so a plain TRUNCATE is blocked:`));
            console.log(chalk.gray(error.message));

            const closure = await getCascadeTruncateClosure(psqlCommand, pg, pg.database, schemaName, tableName);
            const target = `${schemaName}.${tableName}`;
            const outside = closure.filter((q) => q !== target);

            console.log(chalk.red.bold(`TRUNCATE ... CASCADE would ALSO empty ${outside.length} table(s):`));
            for (const q of outside) console.log('  ' + chalk.red(q));

            const { confirmCascade } = await inquirer.prompt([
                {
                    type: 'confirm',
                    name: 'confirmCascade',
                    message: 'Proceed with TRUNCATE ... CASCADE (this empties the tables listed above too)?',
                    default: false
                }
            ]);

            if (!confirmCascade) {
                throw new Error(
                    `Cancelled: truncate of "${target}" is blocked by foreign key references and CASCADE was declined. ` +
                    `Empty the referencing table(s) manually first, or re-run and answer "No" to skip truncation.`
                );
            }

            await truncateSchemaTables(psqlCommand, pg, schemaName, [tableName], { cascade: true });
        }
    }

    const tmpFile = path.join(os.tmpdir(), `restore-${schemaName}-${tableName}-${Date.now()}.sql`);

    try {
        console.log(chalk.cyan(`Dumping "${schemaName}.${tableName}" data from ${sourceDb}...`));
        const dumpArgs = [
            '--no-owner',
            '--no-privileges',
            '--data-only',
            '--disable-triggers',
            '--table', `${schemaName}.${tableName}`,
            '--host', pg.host,
            '--port', String(pg.port),
            '--username', pg.user,
            '--dbname', sourceDb,
            '--file', tmpFile
        ];
        const { code: dumpCode, stderr: dumpErr } = await spawnAsync(pgDumpCommand, dumpArgs, {
            env: { ...process.env, PGPASSWORD: pg.password, PGCLIENTENCODING: 'UTF8' },
            stdio: ['ignore', 'ignore', 'pipe']
        });
        if (dumpCode !== 0) {
            throw new Error((dumpErr || '').trim() || `pg_dump exited with code ${dumpCode}`);
        }

        await stripUnsupportedDumpPreamble(tmpFile);

        console.log(chalk.cyan(`Loading "${schemaName}.${tableName}" data into ${pg.database}...`));
        const loadArgs = [
            '--no-psqlrc',
            '--set', 'ON_ERROR_STOP=1',
            '--host', pg.host,
            '--port', String(pg.port),
            '--username', pg.user,
            '--dbname', pg.database,
            '--file', tmpFile
        ];
        const { code: loadCode } = await spawnAsync(psqlCommand, loadArgs, {
            env: { ...process.env, PGPASSWORD: pg.password, PGCLIENTENCODING: 'UTF8' },
            stdio: ['ignore', 'inherit', 'inherit']
        });
        if (loadCode !== 0) {
            throw new Error(`psql exited with code ${loadCode} while loading "${schemaName}.${tableName}" data.`);
        }
    } catch (error) {
        if (error && error.code === 'ENOENT') {
            throw new Error("pg_dump or psql not found. Install PostgreSQL client tools, or set `PG_DUMP_PATH`/`PSQL_PATH`.");
        }
        throw error;
    } finally {
        await fs.remove(tmpFile).catch(() => {});
    }

    console.log(chalk.green(`Restored "${schemaName}.${tableName}" data from ${sourceDb} into ${pg.database}.`));
    console.log(chalk.gray('Tip: run `node install_system.js --reset-only` afterwards to resync sequences.'));
}

async function runSqlFilesWithNotices(sqlFiles, pg, psqlCommand) {
    for (let i = 0; i < sqlFiles.length; i++) {
        const filePath = sqlFiles[i];
        const display = path.relative(process.cwd(), filePath);
        console.log(chalk.gray(`[SQL ${i + 1}/${sqlFiles.length}] `) + chalk.white(display));

        const args = [
            '--no-psqlrc',
            '--set',
            'ON_ERROR_STOP=1',
            '--host',
            pg.host,
            '--port',
            String(pg.port),
            '--username',
            pg.user,
            '--dbname',
            pg.database,
            '--file',
            filePath
        ];

        // stdio: inherit so RAISE NOTICE messages are visible — the reset
        // logs one NOTICE per sequence, and that's the whole point.
        try {
            const { code } = await spawnAsync(psqlCommand, args, {
                env: { ...process.env, PGPASSWORD: pg.password, PGCLIENTENCODING: "UTF8" },
                stdio: ['ignore', 'inherit', 'inherit']
            });
            if (code !== 0) {
                throw new Error(`psql exited with code ${code} while executing ${display}`);
            }
            console.log(chalk.green('  → OK'));
        } catch (error) {
            if (error && error.code === 'ENOENT') {
                throw new Error("psql not found. Install PostgreSQL client tools (psql) or set `PSQL_PATH` to the full path of psql/psql.exe.");
            }
            throw error;
        }
    }
}

async function findComposerProjectDirs(rootDir) {
    const composerFiles = await walkFiles(
        rootDir,
        (filePath) => path.basename(filePath).toLowerCase() === 'composer.json',
        (dirPath) => {
            const parts = dirPath.split(path.sep).map((p) => p.toLowerCase());
            return (
                parts.includes('node_modules') ||
                parts.includes('.git') ||
                parts.includes('vendor') ||
                parts.includes('__base__') ||
                parts.includes('_assets')
            );
        }
    );

    const dirs = Array.from(new Set(composerFiles.map((f) => path.dirname(f))));
    dirs.sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));
    return dirs;
}

async function chooseComposerProjectDirs(projectDirs) {
    const { selectedDirs } = await inquirer.prompt([
        {
            type: 'checkbox',
            name: 'selectedDirs',
            message: 'Select which system folder(s) to run `composer install` in:',
            choices: projectDirs.map((dir) => ({
                name: path.relative(process.cwd(), dir),
                value: dir,
                checked: true
            })),
            validate: (answer) => answer.length > 0 ? true : 'Select at least one folder (or cancel with Ctrl+C).'
        }
    ]);
    return selectedDirs;
}

async function runComposerInstall(projectDirs, { pgConfig } = {}) {
    if (projectDirs.length === 0) {
        console.log(chalk.yellow('No composer.json files found (excluding vendor).'));
        return;
    }

    console.log(chalk.cyan(`Found ${projectDirs.length} Composer project(s).`));
    projectDirs = await chooseComposerProjectDirs(projectDirs);

    if (projectDirs.length === 0) {
        console.log(chalk.yellow('No system folder selected. Skipping Composer install.'));
        return;
    }

    const { confirmComposer } = await inquirer.prompt([
        {
            type: 'confirm',
            name: 'confirmComposer',
            message: `Run \`composer install\` in ${projectDirs.length} selected project(s)?`,
            default: true
        }
    ]);
    if (!confirmComposer) return;

    // Prepare per-system config before installing dependencies
    const configTemplate = await loadConfigTemplate();
    let templateValues = null;
    let overwriteAllConfigs = false;

    if (configTemplate) {
        const placeholders = getTemplatePlaceholders(configTemplate);
        if (placeholders.length > 0) {
            // Ask for DB details once (reuse SQL step config if available).
            const pg = pgConfig || await promptPostgresConfig();
            templateValues = await promptConfigTemplateValues({ pg, templatePlaceholders: placeholders });

            const { overwrite } = await inquirer.prompt([
                {
                    type: 'confirm',
                    name: 'overwrite',
                    message: 'Overwrite existing `src/config.development.php` files if they already exist?',
                    default: false
                }
            ]);
            overwriteAllConfigs = overwrite;

            for (const dir of projectDirs) {
                const systemCode = path.basename(dir);
                const valuesWithSystem = {
                    ...templateValues,
                    db_name: templateValues.db_name || pg.database,
                    // Keep these available if template ever uses them
                    system_code: systemCode
                };
                await ensureSystemConfig({
                    systemDir: dir,
                    template: configTemplate,
                    values: valuesWithSystem,
                    overwriteAll: overwriteAllConfigs
                });
            }
        }
    }

    const composerExec = await resolveComposerCommand();

    for (let i = 0; i < projectDirs.length; i++) {
        const dir = projectDirs[i];
        const display = path.relative(process.cwd(), dir);
        console.log(chalk.gray(`[Composer ${i + 1}/${projectDirs.length}]`) + ' ' + chalk.white(display));

        try {
            const { code } = await spawnAsync(
                composerExec.command,
                ['install', '--no-interaction', '--prefer-dist'],
                { cwd: dir, stdio: 'inherit', env: process.env, shell: composerExec.shell }
            );
            if (code !== 0) {
                throw new Error(`composer exited with code ${code} in ${display}`);
            }
        } catch (error) {
            if (error && error.code === 'ENOENT') {
                throw new Error("composer not found. Install Composer or set `COMPOSER_PATH` to the full path of composer/composer.bat.");
            }
            throw error;
        }
    }
}

function hasExplicitStepFlags(args) {
    return args.skipSql || args.skipComposer || args.sqlOnly || args.composerOnly || args.resetOnly || args.truncateOnly
        || args.restoreSchemaOnly || !!args.restoreSchemaFrom
        || args.restoreTableOnly || !!args.restoreTableFrom;
}

async function promptRunMode() {
    const { runMode } = await inquirer.prompt([
        {
            type: 'list',
            name: 'runMode',
            message: 'What do you want to run?',
            choices: [
                { name: 'Run SQL only', value: 'sql-only' },
                { name: 'Run Composer install only', value: 'composer-only' },
                { name: 'Run both SQL and Composer install', value: 'both' },
                { name: 'Truncate transaction data (keeps master/config data)', value: 'truncate-only' },
                { name: 'Restore schema data from another DB (e.g. fims_back.uac -> this DB)', value: 'restore-schema-only' },
                { name: 'Restore single table data from another DB (e.g. fims_back.uac.departments -> this DB)', value: 'restore-table-only' },
                { name: 'Cancel', value: 'cancel' }
            ]
        }
    ]);
    return runMode;
}

async function main() {
    const args = parseArgs(process.argv);
    let skipSql = args.skipSql || args.composerOnly;
    let skipComposer = args.skipComposer || args.sqlOnly || args.resetOnly || args.truncateOnly || args.restoreSchemaOnly || args.restoreTableOnly;
    let truncateOnly = args.truncateOnly;
    let restoreSchemaOnly = args.restoreSchemaOnly || !!args.restoreSchemaFrom;
    let restoreTableOnly = args.restoreTableOnly || !!args.restoreTableFrom;

    console.log(chalk.bold('Install System'));
    console.log(chalk.gray(`Workspace: ${process.cwd()}`));

    if (!hasExplicitStepFlags(args)) {
        const runMode = await promptRunMode();
        if (runMode === 'cancel') {
            console.log(chalk.yellow('Cancelled.'));
            return;
        }
        skipSql = runMode === 'composer-only';
        skipComposer = runMode === 'sql-only' || runMode === 'truncate-only' || runMode === 'restore-schema-only' || runMode === 'restore-table-only';
        truncateOnly = runMode === 'truncate-only';
        restoreSchemaOnly = runMode === 'restore-schema-only';
        restoreTableOnly = runMode === 'restore-table-only';
    }

    if (truncateOnly) {
        const psqlCommand = await resolvePsqlCommand();
        const pgConfig = await promptPostgresConfig();
        await runTruncateTransactions(pgConfig, psqlCommand);
        console.log(chalk.green('Done.'));
        return;
    }

    if (restoreSchemaOnly) {
        const psqlCommand = await resolvePsqlCommand();
        const pgDumpCommand = await resolvePgDumpCommand(psqlCommand);
        const pgConfig = await promptPostgresConfig(); // target DB (the one being restored INTO)
        await runSchemaDataRestore(pgConfig, psqlCommand, pgDumpCommand, {
            sourceDatabase: args.restoreSchemaFrom,
            schema: args.restoreSchemaName
        });
        console.log(chalk.green('Done.'));
        return;
    }

    if (restoreTableOnly) {
        const psqlCommand = await resolvePsqlCommand();
        const pgDumpCommand = await resolvePgDumpCommand(psqlCommand);
        const pgConfig = await promptPostgresConfig(); // target DB (the one being restored INTO)

        let schema = null;
        let table = null;
        if (args.restoreTableName) {
            const dotIndex = args.restoreTableName.indexOf('.');
            if (dotIndex !== -1) {
                schema = args.restoreTableName.slice(0, dotIndex);
                table = args.restoreTableName.slice(dotIndex + 1);
            } else {
                table = args.restoreTableName;
            }
        }

        await runTableDataRestore(pgConfig, psqlCommand, pgDumpCommand, {
            sourceDatabase: args.restoreTableFrom,
            schema,
            table
        });
        console.log(chalk.green('Done.'));
        return;
    }

    let pgConfig = null;

    if (!skipSql) {
        const psqlCommand = await resolvePsqlCommand();
        pgConfig = await promptPostgresConfig();

        let sqlRoots;
        if (args.consolidated) {
            // Consolidated mode: 10 pre-merged per-schema files.
            // Regenerate first so we never run a stale snapshot.
            const consolidateScript = path.join(process.cwd(), 'consolidate_sql.js');
            if (!await fs.pathExists(consolidateScript)) {
                throw new Error(`--consolidated requires consolidate_sql.js at repo root.`);
            }
            console.log(chalk.cyan('Refreshing consolidated SQL files...'));
            const { code, stderr } = await spawnAsync(
                process.execPath,
                [consolidateScript],
                { stdio: ['ignore', 'ignore', 'pipe'], env: process.env }
            );
            if (code !== 0) {
                throw new Error(`consolidate_sql.js failed: ${(stderr || '').trim()}`);
            }
            console.log(chalk.green('Consolidated SQL refreshed.'));

            sqlRoots = [path.join(process.cwd(), '_assets', 'sql-consolidated', 'by-phase')];
        } else {
            // Default: scan _assets/sql, then append system-specific SQL folders.
            // Each folder is sorted internally (numeric) and kept grouped in this order.
            sqlRoots = [
                path.join(process.cwd(), '_assets', 'sql'),
                path.join(process.cwd(), 'ACCOUNTING-BAPD', 'sql')
            ];
        }

        let sqlFiles = [];
        for (const root of sqlRoots) {
            sqlFiles = sqlFiles.concat(await getSqlFiles(root));
        }

        if (args.resetOnly) {
            // Filter down to just maintenance files and run them without any
            // "run all vs new" or "DROP database" prompts. Reset means:
            // touch sequences only, keep data.
            sqlFiles = sqlFiles.filter(isMaintenanceFile);
            await runMaintenanceReset(sqlFiles, pgConfig, psqlCommand);
        } else {
            // Hoist any maintenance file(s) to the very END of the run list.
            // Maintenance (e.g. reset-all-sequences) needs to see the final
            // MAX(id) for every sequence, so it must run after ALL seed data
            // from ALL source roots. Without this, BAPD seed data (which
            // runs after _assets/sql phase 07) would leave BAPD sequences
            // out of sync.
            const maintenance    = sqlFiles.filter(isMaintenanceFile);
            const nonMaintenance = sqlFiles.filter((f) => !isMaintenanceFile(f));
            sqlFiles = [...nonMaintenance, ...maintenance];

            await runSqlFiles(sqlFiles, pgConfig, psqlCommand);
        }
        console.log();
    }

    if (!skipComposer) {
        const composerDirs = await findComposerProjectDirs(process.cwd());
        await runComposerInstall(composerDirs, { pgConfig });
        console.log();
    }

    console.log(chalk.green('Done.'));
}

main().catch((error) => {
    console.error(chalk.red(`Error: ${error.message}`));
    process.exit(1);
});
