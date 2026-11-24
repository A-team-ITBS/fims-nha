<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

// =============================================================================
// FundTrackingServices
// Table   : fims.fund_tracking
//           (_assets/sql/01 - fims/01 - table/29 - fims_fund_tracking.sql)
// Lookups : tracking_type_id  -> fims.fund_tracking_type.fund_tracking_type_id (26)
//           nature_of_fund_id -> fims.nature_of_fund.nature_of_fund_id         (27)
//           agency_name       -> the agency's NAME as plain text. fims.agency (28)
//                                is the lookup list it is picked from, but the
//                                column stores the name, not agency_id.
//           status_id         -> fims.settings_status.id
//           parent_id         -> fims.fund_tracking.id (self reference)
//
// Notes
//  * fims.fund_tracking declares no FOREIGN KEYs, so validateReferences() checks
//    in code that every id/code a request points at exists.
//  * Soft-deleted via deleted_at: every read filters it out, delete() stamps it.
//  * user_id is the verified (JWT) user, passed in by the route — never read from
//    the request body. created_at / updated_at / deleted_at are never writable.
//  * `unreleased` (NUMERIC 18,2) and `sub_program` (VARCHAR 225, free text) were dropped ("03 - alter_table/41") and are back
//    ("03 - alter_table/47" and "45"). `sub_program` is a plain column. `unreleased` is returned but never written by a request: it is the
//    balance of a SARO — its amount less its NCAs — kept up to date by refreshSaroUnreleased(), and only SAROs have one. `date` is entered on
//    the SARO / NCA / Supplemental form and accepted on add and update (YYYY-MM-DD); a request that sends none — the Trust Fund and
//    OR forms have no Date field — is dated the day it is created (CURRENT_DATE), and so are the children saved with a SARO / Trust
//    Fund. The Excel importers keep the date their sheet carries.
//  * The Supplemental Fund details (fmd.supplemental_fund_details) and the regions
//    view (shared.v_rc_regions) have no SQL in this repo yet. Those code paths
//    only run when a request uses them, and a failed lookup never blocks the
//    record itself — see the "Supplemental Fund details" sections below.
// =============================================================================
class FundTrackingServices
{
    private string $table = 'fims.fund_tracking';
    private string $tableTrackingTypes = 'fims.fund_tracking_type';
    private string $tableNatureOfFund = 'fims.nature_of_fund';
    private string $tableAgency = 'fims.agency';
    private string $tableSettingsStatus = 'fims.settings_status';
    private string $tableSupplementalDetails = 'fmd.supplemental_fund_details';
    private string $tableBankAccounts = 'fims.bank_accounts';
    private string $viewRegions = 'shared.v_rc_regions';

    // Condition every read of fund_tracking (alias ft_rec) starts from.
    private const LIVE = 'ft_rec.deleted_at IS NULL';

    // Supplemental Fund details (1:1 child row, see saveSupplementalDetails()):
    // field => type, in table column order. Sent under the `supplemental` key of
    // the create/update body.
    private const SUPPLEMENTAL_STATUSES = ['IN_PROCESS', 'AT_NDRRMC', 'AT_OP', 'APPROVED', 'DENIED'];

    private const SUPPLEMENTAL_FIELDS = [
        'status' => 'status',                 // one of SUPPLEMENTAL_STATUSES; blank = IN_PROCESS
        'regional_office' => 'region',        // must match shared.v_rc_regions.rc_name
        'budgetary_requirement' => 'decimal',
        'date_received' => 'date',
        'date_forwarded_ndrrmc' => 'date',
        'days_fmd' => 'int',                  // working days: date_received -> date_forwarded_ndrrmc
        'date_forwarded_op' => 'date',
        'days_ndrrmc' => 'int',               // working days: date_forwarded_ndrrmc -> date_forwarded_op
        'date_approved_op' => 'date',
        'days_op' => 'int',                   // working days: date_forwarded_op -> date_approved_op
        'days_total' => 'int',                // days_fmd + days_ndrrmc + days_op
    ];

    // Field type maps (used by quoteValue())
    private array $intFields = ['fiscal_year', 'tracking_type_id', 'nature_of_fund_id', 'status_id', 'parent_id', 'user_id', 'bank_account_id'];
    private array $decimalFields = ['amount'];
    private array $uuidFields = ['multiple_node_id'];

    // Columns a request may write (user_id is added by the route, not the client). `date` is entered on the SARO / NCA / Supplemental
    // form; when a request sends none (the Trust Fund and OR forms have no Date field) create() / insertRow() stamp the day it is created.
    private array $allowedFields = [
        'fiscal_year',
        'tracking_type_id',
        'saro_id',
        'nature_of_fund_id',
        'description',
        'sub_program',         // free text, VARCHAR(225)
        'date',                // the date the form enters; a request that sends none is dated the day it is created (create())
        'amount',
        'bank_account_no',
        'bank_account_id',       // fims.bank_accounts.bank_account_id this SARO's bank account resolves to, if picked from the list (soft reference, see "03 - alter_table/61")
        'agency_name',          // agency name as text (see header)
        'remarks',              // text — Supplemental records' Remarks (saro-processing-form.php)
        'purpose',
        'multiple_node_id',
        'status_id',
        'parent_id',
    ];

    // Single-quoted SQL string literal (same escaping the rest of this class uses).
    private function qs(mixed $value): string
    {
        return "'".str_replace("'", "''", (string) $value)."'";
    }

    // -----------------------------------------------------------------------
    // Base SELECT — JOINs tracking type, agency, nature of fund and the parent
    // record. Callers append their own WHERE and
    // must include self::LIVE in it.
    // NOTE: child rows (other rows whose parent_id points at this row) are
    // batch-fetched separately in attachChildren() and nested under the
    // `children` key as an array.
    // -----------------------------------------------------------------------
    private function baseSelect(): string
    {
        return 'SELECT
                    ft_rec.id,
                    ft_rec.fiscal_year,
                    ft_rec.tracking_type_id,
                    ft_rec.saro_id,
                    ft_rec.nature_of_fund_id,
                    ft_rec.description,
                    ft_rec.sub_program,
                    ft_rec.unreleased,
                    ft_rec.date,
                    ft_rec.amount,
                    ft_rec.bank_account_no,
                    ft_rec.bank_account_id,
                    ft_rec.dv_id,
                    ft_rec.agency_name,
                    ft_rec.remarks,
                    ft_rec.purpose,
                    ft_rec.multiple_node_id,
                    ft_rec.status_id,
                    ft_rec.parent_id,
                    ft_rec.user_id,
                    ft_rec.created_at,
                    ft_rec.updated_at,
                    tt.fund_tracking_code  AS tt_code,
                    tt.fund_tracking_name  AS tt_name,
                    ag.agency_id           AS ag_id,
                    nof.name               AS nof_name,
                    par.id                 AS par_id,
                    par.fiscal_year        AS par_fiscal_year,
                    par.tracking_type_id   AS par_tracking_type_id,
                    par.saro_id            AS par_saro_id,
                    par.description        AS par_description,
                    par.amount             AS par_amount,
                    par.date               AS par_date,
                    par.parent_id          AS par_parent_id,
                    par.multiple_node_id   AS par_multiple_node_id,
                    par_tt.fund_tracking_code AS par_tt_code,
                    par_tt.fund_tracking_name AS par_tt_name
                FROM '.$this->table.' ft_rec
                LEFT JOIN '.$this->tableTrackingTypes.' tt     ON tt.fund_tracking_type_id = ft_rec.tracking_type_id
                LEFT JOIN '.$this->tableAgency.'        ag     ON ag.name                  = ft_rec.agency_name
                LEFT JOIN '.$this->tableNatureOfFund.'  nof    ON nof.nature_of_fund_id    = ft_rec.nature_of_fund_id
                LEFT JOIN '.$this->table.'              par    ON par.id = ft_rec.parent_id AND par.deleted_at IS NULL
                LEFT JOIN '.$this->tableTrackingTypes.' par_tt ON par_tt.fund_tracking_type_id = par.tracking_type_id';
    }

    // -----------------------------------------------------------------------
    // Nest JOIN columns into sub-objects
    // -----------------------------------------------------------------------
    private function nestRow(array $row): array
    {
        $row['tracking_type'] = $row['tt_code'] !== null ? [
            'fund_tracking_type_id' => $row['tracking_type_id'],
            'fund_tracking_code' => $row['tt_code'],
            'fund_tracking_name' => $row['tt_name'],
        ] : null;

        // agency_name is the agency's name as text. agency_id is filled in only
        // when that name exists in fims.agency (null otherwise), and the raw
        // text stays on $row['agency_name'] untouched.
        $row['agency'] = trim((string) ($row['agency_name'] ?? '')) !== '' ? [
            'agency_id' => $row['ag_id'],
            'name' => $row['agency_name'],
        ] : null;

        $row['nature_of_fund'] = $row['nof_name'] !== null ? [
            'nature_of_fund_id' => $row['nature_of_fund_id'],
            'name' => $row['nof_name'],
        ] : null;

        // `parent` holds the single fund_tracking row referenced by this
        // row's own parent_id (via the `par` self-join in baseSelect()).
        // Always an array — empty [] when parent_id is null / doesn't
        // resolve (or the parent is soft-deleted), otherwise a one-item array
        // containing the parent row's core fields (parent's own
        // children/parent are NOT recursively expanded, to avoid unbounded
        // depth/cycles).
        $row['parent'] = $row['par_id'] !== null ? [[
            'id' => $row['par_id'],
            'fiscal_year' => $row['par_fiscal_year'],
            'tracking_type_id' => $row['par_tracking_type_id'],
            'saro_id' => $row['par_saro_id'],
            'description' => $row['par_description'],
            'amount' => $row['par_amount'],
            'date' => $row['par_date'],
            'parent_id' => $row['par_parent_id'],
            'multiple_node_id' => $row['par_multiple_node_id'],
            'tracking_type' => $row['par_tt_code'] !== null ? [
                'fund_tracking_type_id' => $row['par_tracking_type_id'],
                'fund_tracking_code' => $row['par_tt_code'],
                'fund_tracking_name' => $row['par_tt_name'],
            ] : null,
        ]] : [];

        // `children` holds all OTHER fund_tracking rows whose parent_id
        // points AT this row's own id — i.e. if this row is id 2999,
        // children contains every row with parent_id = 2999. Populated in
        // bulk by attachChildren() after the main query. Defaults to [] here.
        $row['children'] = [];

        unset(
            $row['tt_code'],
            $row['tt_name'],
            $row['ag_id'],
            $row['nof_name'],
            $row['par_id'],
            $row['par_fiscal_year'],
            $row['par_tracking_type_id'],
            $row['par_saro_id'],
            $row['par_description'],
            $row['par_amount'],
            $row['par_date'],
            $row['par_parent_id'],
            $row['par_multiple_node_id'],
            $row['par_tt_code'],
            $row['par_tt_name']
        );

        return $row;
    }

    // -----------------------------------------------------------------------
    // Batch-attach children — for a given set of already-nested rows, find
    // ALL (non-deleted) fund_tracking rows whose parent_id points AT any of
    // these rows' own id, and nest them (fully, via nestRow()) inside that
    // row's `children` key — an array with one entry per child.
    //
    // Example: if a row's id is 2999, `children` contains every row in
    // fund_tracking where parent_id = 2999.
    //
    // Single batched query (WHERE parent_id IN (...)) instead of N+1
    // per-row lookups. Children are themselves fully nested via nestRow(),
    // but their own `children` key is left as [] (grandchildren are NOT
    // recursively expanded, to avoid unbounded depth/cycles).
    //
    // $typeIds — optional tracking_type_id whitelist (from the `type`
    // filter on /fund_tracking/list). When given, a child is only attached
    // if ITS OWN tracking_type_id is in this list — a parent matching the
    // filter no longer drags in every child regardless of the child's own
    // type.
    // $natureCond — optional SQL condition on ft_rec (the child alias); only
    // children satisfying it are attached (see fetchFiltered()'s nature_tree).
    // -----------------------------------------------------------------------
    private function attachChildren(array $rows, array $typeIds = [], string $natureCond = ''): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $ids = array_column($rows, 'id');
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return $rows;
        }

        $idList = implode(',', $ids);

        $typeFilter = !empty($typeIds)
            ? ' AND ft_rec.tracking_type_id IN ('.implode(',', array_map('intval', $typeIds)).')'
            : '';

        $natureFilter = $natureCond !== '' ? " AND ({$natureCond})" : '';

        $sql = $this->baseSelect().' WHERE '.self::LIVE." AND ft_rec.parent_id IN ({$idList}){$typeFilter}{$natureFilter} ORDER BY ft_rec.id ASC";
        $childRows = App\ExecuteRows($sql, 'DB') ?: [];

        // Group nested children by their parent_id (which points at the
        // owning row's id)
        $childrenByParentId = [];
        foreach ($childRows as $childRow) {
            $pid = (int) $childRow['parent_id'];
            $nestedChild = $this->nestRow($childRow);
            $nestedChild['children'] = []; // don't recurse into grandchildren
            $childrenByParentId[$pid][] = $nestedChild;
        }

        foreach ($rows as &$row) {
            $row['children'] = $childrenByParentId[(int) $row['id']] ?? [];
        }
        unset($row);

        return $rows;
    }

    // -----------------------------------------------------------------------
    // Parse a raw query string for every occurrence of a repeated key —
    // e.g. parseRepeatedParam('type=SARO&type=NCA', 'type') === ['SARO', 'NCA']
    //
    // PHP's native query-string parsing (and therefore Slim's
    // getQueryParams()) collapses repeated non-array keys into a single,
    // last-value-wins entry, so ?type=SARO&type=NCA can't be read via
    // $params['type'] alone. This reads the raw query string directly.
    // -----------------------------------------------------------------------
    public function parseRepeatedParam(string $queryString, string $key): array
    {
        $values = [];

        if ($queryString === '') {
            return $values;
        }

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            $k = urldecode($parts[0]);

            if ($k === $key) {
                $values[] = isset($parts[1]) ? urldecode(str_replace('+', ' ', $parts[1])) : '';
            }
        }

        return $values;
    }

    // -----------------------------------------------------------------------
    // Safe SQL value quoting (no DATATYPE_* constants)
    // -----------------------------------------------------------------------
    private function quoteValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }

        if (in_array($key, $this->intFields)) {
            return (string) (int) $value;
        }

        if (in_array($key, $this->decimalFields)) {
            return (string) (float) $value;
        }

        if (in_array($key, $this->uuidFields)) {
            return "'".str_replace("'", "''", (string) $value)."'::UUID";
        }

        // String / date
        return "'".str_replace("'", "''", (string) $value)."'";
    }

    // -----------------------------------------------------------------------
    // Resolve tracking_type_id — accepts EITHER:
    //   (a) a raw fund_tracking_type_id (int/numeric string) — confirmed to
    //       exist (and not be soft-deleted) before use.
    //   (b) a tracking type CODE or NAME ("SARO", "NCA", "TRUST FUND", "OR",
    //       "Trust Fund" ...) — exact, case-insensitive match on
    //       fims.fund_tracking_type.fund_tracking_code, then on
    //       fund_tracking_name. "_" and " " are interchangeable.
    // "TRUST", "TRUST FUND" and "TRUST_FUND" all mean the Trust Fund type,
    // whichever of them the table uses as its code (the seed in
    // 26 - fims_fund_tracking_type.sql uses "TRUST FUND"; older clients and
    // sheets say "TRUST_FUND").
    //
    // GAAAO and SUPPLEMENTAL are NOT tracking types any more (they are
    // natures of fund), so those values resolve to null.
    // Returns null when nothing matches.
    // -----------------------------------------------------------------------
    private function resolveTrackingTypeId(int|string|null $type): ?int
    {
        if ($type === null || trim((string) $type) === '') {
            return null;
        }

        if (is_numeric($type)) {
            $id = (int) $type;

            $exists = App\ExecuteScalar(
                "SELECT fund_tracking_type_id FROM {$this->tableTrackingTypes}
                  WHERE fund_tracking_type_id = {$id}
                    AND deleted_at IS NULL
                  LIMIT 1",
                'DB'
            );

            return $exists ? (int) $exists : null;
        }

        $value = str_replace('_', ' ', strtoupper(trim((string) $type)));
        $candidates = in_array($value, ['TRUST', 'TRUST FUND'], true) ? ['TRUST', 'TRUST FUND'] : [$value];
        $list = implode(', ', array_map(fn (string $c) => $this->qs($c), $candidates));

        $code = "UPPER(REPLACE(fund_tracking_code, '_', ' '))";
        $name = "UPPER(REPLACE(fund_tracking_name, '_', ' '))";

        // A code match wins over a name match.
        $id = App\ExecuteScalar(
            "SELECT fund_tracking_type_id FROM {$this->tableTrackingTypes}
              WHERE deleted_at IS NULL
                AND ({$code} IN ({$list}) OR {$name} IN ({$list}))
              ORDER BY ({$code} IN ({$list})) DESC, fund_tracking_type_id ASC
              LIMIT 1",
            'DB'
        );

        return $id ? (int) $id : null;
    }

    // -----------------------------------------------------------------------
    // A plausible fiscal year as an int, else null. fiscal_year is stored as
    // a plain integer on fims.fund_tracking (no fiscal-period table to resolve
    // against any more).
    // -----------------------------------------------------------------------
    private function normalizeFiscalYear(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $year = (int) $value;

        return ($year >= 1900 && $year <= 2200 && (float) $value == $year) ? $year : null;
    }

    // -----------------------------------------------------------------------
    // Resolve nature_of_fund_id from an id or a free-text name (e.g.
    // "Insertions", "Equity", "GAAAO", "Unprogrammed", "SUPPLEMENTAL") against
    // fims.nature_of_fund. Exact match first, then a partial (fuzzy) match.
    // No auto-create — this is a small, fixed lookup table; returns null when
    // nothing matches, in which case the importer logs a soft warning and
    // imports without it (nature_of_fund is optional).
    // -----------------------------------------------------------------------
    private function resolveNatureOfFundId(int|string|null $natureOfFund): ?int
    {
        if ($natureOfFund === null || $natureOfFund === '') {
            return null;
        }

        // ── Case (a): already a nature_of_fund_id — just confirm it exists ──
        if (is_numeric($natureOfFund)) {
            $id = (int) $natureOfFund;

            $exists = App\ExecuteScalar(
                "SELECT nature_of_fund_id FROM {$this->tableNatureOfFund}
                  WHERE nature_of_fund_id = {$id}
                  LIMIT 1",
                'DB'
            );

            return $exists ? (int) $exists : null;
        }

        // ── Case (b): free-text name — resolve it ────────────────────────────
        $safe = str_replace("'", "''", trim((string) $natureOfFund));

        // Step 1 — exact match
        $id = App\ExecuteScalar(
            "SELECT nature_of_fund_id FROM {$this->tableNatureOfFund}
              WHERE UPPER(name) = UPPER('{$safe}')
              LIMIT 1",
            'DB'
        );

        if ($id) {
            return (int) $id;
        }

        // Step 2 — fall back to a partial (fuzzy) match
        $id = App\ExecuteScalar(
            "SELECT nature_of_fund_id FROM {$this->tableNatureOfFund}
              WHERE UPPER(name) LIKE UPPER('%{$safe}%')
              ORDER BY nature_of_fund_id ASC
              LIMIT 1",
            'DB'
        );

        return $id ? (int) $id : null;
    }

    // -----------------------------------------------------------------------
    // Resolve an agency NAME for the Trust Fund import (e.g. "DPWH"): the
    // canonical name of the matching fims.agency row (exact, case-insensitive —
    // no fuzzy match, since agency names in the source registry are short
    // codes/acronyms where a partial match would be too eager). If no row
    // matches, the agency is auto-created (fims.agency.name is its only
    // required column) and its name returned — the import only ever supplies a
    // NAME, so a name with no existing match is a genuinely new agency, not an
    // error. The result is what gets stored in fund_tracking.agency_name.
    // Returns null only if the auto-create itself fails.
    // -----------------------------------------------------------------------
    private function resolveAgencyName(?string $agencyName): ?string
    {
        if ($agencyName === null || trim($agencyName) === '') {
            return null;
        }

        $safe = str_replace("'", "''", trim($agencyName));

        $lookup = "SELECT name FROM {$this->tableAgency}
                    WHERE UPPER(name) = UPPER('{$safe}')
                    ORDER BY (name = '{$safe}') DESC, agency_id ASC
                    LIMIT 1";

        $found = App\ExecuteScalar($lookup, 'DB');
        if ($found !== false && $found !== null && $found !== '') {
            return (string) $found;
        }

        try {
            $inserted = App\ExecuteRow(
                "INSERT INTO {$this->tableAgency} (name) VALUES ('{$safe}') RETURNING name",
                'DB'
            );

            if (!empty($inserted['name'])) {
                return (string) $inserted['name'];
            }
        } catch (\Exception $e) {
            // Another row in this same import batch (or a concurrent
            // request) may have just created a matching agency — re-check
            // by name before giving up.
            $found = App\ExecuteScalar($lookup, 'DB');
            if ($found !== false && $found !== null && $found !== '') {
                return (string) $found;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // Best-effort lookup used by the Trust Fund import: resolves an existing
    // (non-deleted) fims.fund_tracking record by saro_id (e.g. an
    // already-imported SARO/NCA/OR entry sharing the same "Saro No./OR No./CA
    // No." value) so the new TRUST record's parent_id can link back to it.
    // Returns null (not an error) when nothing matches.
    // -----------------------------------------------------------------------
    private function resolveParentFundTrackingId(?string $referenceNumber): ?int
    {
        if (empty($referenceNumber)) {
            return null;
        }

        $safe = str_replace("'", "''", trim($referenceNumber));

        $id = App\ExecuteScalar(
            "SELECT id FROM {$this->table}
              WHERE UPPER(saro_id) = UPPER('{$safe}')
                AND deleted_at IS NULL
              ORDER BY id ASC
              LIMIT 1",
            'DB'
        );

        return $id ? (int) $id : null;
    }

    // -----------------------------------------------------------------------
    // fims.fund_tracking has no FOREIGN KEYs, so check here that what a
    // create/update request points at is real, and that its values fit the
    // column types (a bad value would otherwise surface as a raw SQL error).
    // Only keys present in $fields are checked; '' / null mean "clear it" and
    // are fine. Returns an error message, or null when everything is valid.
    // -----------------------------------------------------------------------
    private function validateReferences(array $fields): ?string
    {
        $filled = fn (string $key): bool => isset($fields[$key]) && $fields[$key] !== '' && $fields[$key] !== null;

        if ($filled('tracking_type_id')
            && (!is_numeric($fields['tracking_type_id']) || $this->resolveTrackingTypeId((int) $fields['tracking_type_id']) === null)) {
            return 'tracking_type_id does not match any fund tracking type.';
        }

        if ($filled('nature_of_fund_id')
            && (!is_numeric($fields['nature_of_fund_id']) || $this->resolveNatureOfFundId((int) $fields['nature_of_fund_id']) === null)) {
            return 'nature_of_fund_id does not match any nature of fund.';
        }

        if ($filled('status_id')) {
            $statusId = is_numeric($fields['status_id']) ? (int) $fields['status_id'] : 0;
            $exists = $statusId > 0 ? (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableSettingsStatus} WHERE id = {$statusId}", 'DB') : 0;
            if ($exists === 0) {
                return 'status_id does not match any status.';
            }
        }

        if ($filled('bank_account_id')) {
            $bankAccountId = is_numeric($fields['bank_account_id']) ? (int) $fields['bank_account_id'] : 0;
            $exists = $bankAccountId > 0 ? (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableBankAccounts} WHERE bank_account_id = {$bankAccountId}", 'DB') : 0;
            if ($exists === 0) {
                return 'bank_account_id does not match any bank account.';
            }
        }

        if ($filled('parent_id')) {
            $parentId = is_numeric($fields['parent_id']) ? (int) $fields['parent_id'] : 0;
            $exists = $parentId > 0 ? (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE id = {$parentId} AND deleted_at IS NULL", 'DB') : 0;
            if ($exists === 0) {
                return 'parent_id does not match any fund tracking record.';
            }
        }

        if ($filled('fiscal_year') && $this->normalizeFiscalYear($fields['fiscal_year']) === null) {
            return 'fiscal_year must be a valid year (e.g. 2026).';
        }

        if ($filled('amount') && (!is_numeric($fields['amount']) || abs((float) $fields['amount']) >= 1e16)) {
            return 'amount must be a number with at most 16 digits before the decimals.';
        }

        if ($filled('multiple_node_id')
            && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $fields['multiple_node_id'])) {
            return 'multiple_node_id must be a UUID.';
        }

        if ($filled('date')) {
            $day = date_create_from_format('Y-m-d', (string) $fields['date']);
            if (!$day || $day->format('Y-m-d') !== (string) $fields['date']) {
                return 'date must be a valid date (YYYY-MM-DD).';
            }
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // Fetch all — no pagination (/fund_tracking/all)
    // Fetch with pagination (/fund_tracking/list) when $page is provided
    // $search — optional, matched against saro_id (ILIKE, partial)
    // $lean   — pickers that only need the record itself (id, reference, name,
    //           tracking type, agency, amount, date):
    //           skips the second query that batch-fetches every row's children
    //           and drops the nested `parent` / `children` arrays, which
    //           otherwise multiply the payload. Default (false) is unchanged.
    // -----------------------------------------------------------------------
    public function fetchAlls(?int $page = null, int $limit = 10, ?string $search = null, bool $lean = false): array
    {
        try {
            $base = $this->baseSelect();

            $where = [self::LIVE];
            if (!empty($search)) {
                $where[] = 'ft_rec.saro_id ILIKE '.$this->qs('%'.$search.'%');
            }
            $whereClause = 'WHERE '.implode(' AND ', $where);

            if ($page === null) {
                $sql = "{$base} {$whereClause} ORDER BY ft_rec.id ASC";
                $rows = App\ExecuteRows($sql, 'DB');
                $nested = array_map([$this, 'nestRow'], $rows ?: []);
                $nested = $lean ? $this->stripRelations($nested) : $this->attachChildren($nested);

                return [
                    'success' => true,
                    'data' => $nested,
                ];
            }

            $page = max(1, $page);
            $limit = max(1, $limit);
            $offset = ($page - 1) * $limit;
            $total = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} ft_rec {$whereClause}",
                'DB'
            );

            $sql = "{$base} {$whereClause}
                     ORDER BY ft_rec.id ASC
                     LIMIT {$limit} OFFSET {$offset}";
            $rows = App\ExecuteRows($sql, 'DB');
            $nested = array_map([$this, 'nestRow'], $rows ?: []);
            $nested = $lean ? $this->stripRelations($nested) : $this->attachChildren($nested);

            return [
                'success' => true,
                'data' => $nested,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Lean rows (fetchAlls $lean): the record without its `parent` / `children`
    // relations — nestRow() always adds both, attachChildren() fills the second.
    // -----------------------------------------------------------------------
    private function stripRelations(array $rows): array
    {
        foreach ($rows as &$row) {
            unset($row['parent'], $row['children']);
        }
        unset($row);

        return $rows;
    }

    // -----------------------------------------------------------------------
    // "Not this nature of fund" — the SQL condition behind `exclude_nature_of_fund` (a nature of fund id or name, e.g.
    // ?exclude_nature_of_fund=SUPPLEMENTAL) for any alias of fims.fund_tracking: the records of that nature are left out.
    // Nature of fund is optional, so a record with NONE is kept — a plain NOT IN would drop it (NULL NOT IN (...) is never
    // true) — and so is everything when the name matches no nature of fund at all. Returns '' when no value is given (or
    // not a single plain value), meaning "no condition".
    // -----------------------------------------------------------------------
    private function excludeNatureCond(string $alias, mixed $nature): string
    {
        $nature = is_scalar($nature) ? trim((string) $nature) : '';
        if ($nature === '') {
            return '';
        }

        $match = is_numeric($nature)
            ? 'nature_of_fund_id = '.(int) $nature
            : 'UPPER(name) = UPPER('.$this->qs($nature).')';

        return "({$alias}.nature_of_fund_id IS NULL OR {$alias}.nature_of_fund_id NOT IN (SELECT nature_of_fund_id FROM {$this->tableNatureOfFund} WHERE {$match}))";
    }

    // -----------------------------------------------------------------------
    // Fetch with filters — server-side DataTables
    // Supports: fiscal_year (exact) and fiscal_year_from / fiscal_year_to (an
    //           inclusive range, e.g. 2010-2012), tracking_type_id,
    //           type (repeatable), nature_of_fund (id or name),
    //           exclude_nature_of_fund (id or name — the records of that
    //           nature are left out, children included),
    //           status_id, parent_id, multiple_node_id, and a
    //           saro_id filter (ILIKE, partial) accepted under any
    //           of: saro_id, reference_number (legacy), saro_reference_number (legacy), search
    //
    // root_type — see the `root_type` block below: with roots_only, narrows the
    // root rows to one tracking type while `type` still picks the children.
    //
    // nature_tree — with roots_only + nature_of_fund: build the tree around the
    // nature of fund instead of filtering the roots by it. A root row is any
    // parentless record that is of that nature OR has a child of that nature
    // (e.g. a SARO with Supplemental records under it), and only the children
    // of that nature are attached to it. Search by reference number also
    // matches those children. Totals/pages count these root rows.
    //
    // roots_only — when truthy, both the COUNT and the paginated SELECT are
    // restricted to ft_rec.parent_id IS NULL ("root" records — normally the
    // SARO side of a SARO/NCA pair; an NCA imported with no parent to
    // inherit from also has parent_id NULL, see importFromExcel()'s
    // CARRY-FORWARD PARENT doc comment). Children are still attached
    // afterward via attachChildren(), unbounded by $limit, exactly as
    // without this flag.
    //
    // Needed because a tree-rendering caller only ever displays root rows,
    // nesting children under them — pagination over the FLAT row set
    // (roots+children mixed, the default behavior of /fund_tracking/list) can
    // hand back a page made up entirely of child rows whose parent landed on a
    // different page, leaving nothing for that caller to render even though
    // the total count is non-zero.
    // -----------------------------------------------------------------------
    public function fetchFiltered(array $params): array
    {
        try {
            $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
            $limit = isset($params['limit']) ? max(1, (int) $params['limit']) : 10;
            $offset = ($page - 1) * $limit;
            $rootsOnly = !empty($params['roots_only']);

            $where = [self::LIVE];
            $typeIds = [];

            if ($rootsOnly) {
                $where[] = 'ft_rec.parent_id IS NULL';
            }

            if (isset($params['fiscal_year']) && $params['fiscal_year'] !== '') {
                $where[] = 'ft_rec.fiscal_year = '.(int) $params['fiscal_year'];
            }

            // Fiscal year RANGE filter (e.g. 2010-2012), independent of the
            // single fiscal_year filter above; both may be applied.
            $fiscalYearFrom = isset($params['fiscal_year_from']) && $params['fiscal_year_from'] !== '' ? (int) $params['fiscal_year_from'] : null;
            $fiscalYearTo = isset($params['fiscal_year_to']) && $params['fiscal_year_to'] !== '' ? (int) $params['fiscal_year_to'] : null;
            if ($fiscalYearFrom !== null && $fiscalYearTo !== null) {
                $where[] = 'ft_rec.fiscal_year BETWEEN '.$fiscalYearFrom.' AND '.$fiscalYearTo;
            } elseif ($fiscalYearFrom !== null) {
                $where[] = 'ft_rec.fiscal_year >= '.$fiscalYearFrom;
            } elseif ($fiscalYearTo !== null) {
                $where[] = 'ft_rec.fiscal_year <= '.$fiscalYearTo;
            }

            if (!empty($params['tracking_type_id'])) {
                $where[] = 'ft_rec.tracking_type_id = '.(int) $params['tracking_type_id'];
                $typeIds[] = (int) $params['tracking_type_id'];
            }

            // `type` — one or more tracking type codes/ids (e.g.
            // ?type=SARO&type=NCA), resolved via resolveTrackingTypeId() and
            // combined with OR (IN clause). If none of the given values
            // resolve to a real tracking type, force an empty result set
            // instead of silently ignoring the filter. The same $typeIds is
            // reused below in attachChildren() so children are filtered by
            // their OWN tracking_type_id too, not pulled in regardless of type.
            if (!empty($params['type'])) {
                $typeValues = is_array($params['type']) ? $params['type'] : [$params['type']];

                foreach ($typeValues as $typeValue) {
                    if ($typeValue === '' || $typeValue === null) {
                        continue;
                    }

                    $resolvedId = $this->resolveTrackingTypeId($typeValue);
                    if ($resolvedId) {
                        $typeIds[] = $resolvedId;
                    }
                }

                $typeIds = array_values(array_unique($typeIds));

                $where[] = !empty($typeIds)
                    ? 'ft_rec.tracking_type_id IN ('.implode(',', $typeIds).')'
                    : '1 = 0';
            }

            // `root_type` — one tracking type code/id the ROOT rows must have;
            // meant for roots_only. `type` also decides which children come back
            // with each root (children are filtered by `type` too), so a tree of
            // Trust Funds needs type=TRUST&type=OR — which makes an OR with no
            // parent a root row as well, taking up rows and pages. root_type=
            // TRUST keeps the roots to Trust Funds while their ORs are still
            // attached. It sits in the same WHERE as the COUNT below, so totals and
            // pages stay exact. A value that resolves to no tracking type yields an
            // empty result set instead of being silently ignored.
            if (!empty($params['root_type'])) {
                $rootType = is_array($params['root_type']) ? reset($params['root_type']) : $params['root_type'];
                $rootTypeId = $this->resolveTrackingTypeId($rootType);
                $where[] = $rootTypeId ? 'ft_rec.tracking_type_id = '.$rootTypeId : '1 = 0';
            }

            // `nature_of_fund` — a nature of fund id or name (e.g.
            // ?nature_of_fund=SUPPLEMENTAL or ?nature_of_fund=5), independent
            // of tracking type. A name that matches no row yields an empty
            // result set rather than silently ignoring the filter. Uses a
            // subquery on ft_rec so the COUNT query below needs no extra join.
            // Builds that condition for any alias (the tree below also needs it on children)
            // `tracking_group` (SARO_NCA | NO_SARO_NCA) narrows those same records by
            // whether they are tied to a SARO/NCA — see trackingGroupCond(). Because the tree reuses
            // this condition for both roots and children, a root is kept when it, or
            // one of its children, is in the group, and only those children come back.
            $natureCondFor = function (string $alias) use ($params): string {
                $nof = $params['nature_of_fund'];
                if (is_numeric($nof)) {
                    $cond = "{$alias}.nature_of_fund_id = ".(int) $nof;
                } else {
                    $cond = "{$alias}.nature_of_fund_id IN (SELECT nature_of_fund_id FROM {$this->tableNatureOfFund} WHERE UPPER(name) = UPPER(".$this->qs(trim((string) $nof))."))";
                }

                $groupCond = $this->trackingGroupCond($alias, $params['tracking_group'] ?? null);
                if ($groupCond !== '') {
                    $cond = "({$cond} AND {$groupCond})";
                }

                // `supplemental_status` (PENDING | APPROVED | DENIED) — same idea, by status
                $statusCond = $this->supplementalStatusCond($alias, $params['supplemental_status'] ?? null);

                return $statusCond !== '' ? "({$cond} AND {$statusCond})" : $cond;
            };
            $natureTree = $rootsOnly && !empty($params['nature_tree'])
                && isset($params['nature_of_fund']) && $params['nature_of_fund'] !== '';
            $childNatureCond = '';

            if (isset($params['nature_of_fund']) && $params['nature_of_fund'] !== '') {
                if ($natureTree) {
                    $childNatureCond = $natureCondFor('ft_rec');
                    $where[] = '('.$natureCondFor('ft_rec')
                        .' OR EXISTS (SELECT 1 FROM '.$this->table.' nc WHERE nc.parent_id = ft_rec.id AND nc.deleted_at IS NULL AND '
                        .$natureCondFor('nc').'))';
                } else {
                    $where[] = $natureCondFor('ft_rec');
                }
            }

            // `exclude_nature_of_fund` — the opposite of `nature_of_fund`: records of that nature are left out. It sits in the
            // same WHERE as the COUNT below (totals and pages stay exact) and is also applied to the children attached to each
            // row, so an excluded record never comes back nested under a kept one either.
            $excludeNatureCond = $this->excludeNatureCond('ft_rec', $params['exclude_nature_of_fund'] ?? null);
            if ($excludeNatureCond !== '') {
                $where[] = $excludeNatureCond;
            }

            if (!empty($params['status_id'])) {
                $where[] = 'ft_rec.status_id = '.(int) $params['status_id'];
            }

            if (!empty($params['parent_id'])) {
                $where[] = 'ft_rec.parent_id = '.(int) $params['parent_id'];
            }

            if (!empty($params['multiple_node_id'])) {
                $where[] = 'ft_rec.multiple_node_id = '.$this->qs($params['multiple_node_id']).'::UUID';
            }

            // Accept the current column name, the legacy query param name
            // (reference_number and saro_reference_number, still used by existing consumers), and
            // `search` (used by the SARO/NCA list page's filter + table
            // search inputs — matches /fund_tracking/all's `search` param).
            $refFilter = $params['saro_id'] ?? $params['reference_number'] ?? $params['saro_reference_number'] ?? $params['search'] ?? null;
            if (!empty($refFilter)) {
                $val = $this->qs('%'.$refFilter.'%');
                $where[] = $natureTree
                    ? "(ft_rec.saro_id ILIKE {$val} OR EXISTS (SELECT 1 FROM {$this->table} sc WHERE sc.parent_id = ft_rec.id AND sc.deleted_at IS NULL AND sc.saro_id ILIKE {$val}))"
                    : "ft_rec.saro_id ILIKE {$val}";
            }

            // `agency_or_program_search` — free text matched against the agency
            // name (a record has no sub-program / program any more; the name of
            // the parameter is kept for the callers that already send it).
            // Separate from `search` above (which stays saro_id-only,
            // relied on by the SARO/NCA list page) — used by the Trust Fund list
            // page, whose records don't reliably carry a meaningful
            // saro_id.
            if (!empty($params['agency_or_program_search'])) {
                $where[] = 'ft_rec.agency_name ILIKE '.$this->qs('%'.$params['agency_or_program_search'].'%');
            }

            $whereClause = 'WHERE '.implode(' AND ', $where);

            $total = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} ft_rec {$whereClause}",
                'DB'
            );

            $sql = $this->baseSelect()." {$whereClause}
                     ORDER BY ft_rec.id ASC
                     LIMIT {$limit} OFFSET {$offset}";
            $rows = App\ExecuteRows($sql, 'DB');
            $nested = array_map([$this, 'nestRow'], $rows ?: []);
            // the conditions a child must meet (on ft_rec — the child alias in attachChildren): the nature_tree one, if any,
            // and the exclusion above; each parenthesised so they combine safely
            $childCond = implode(' AND ', array_map(
                fn (string $c) => '('.$c.')',
                array_filter([$natureTree ? $childNatureCond : '', $excludeNatureCond], fn (string $c) => $c !== '')
            ));
            $nested = $this->attachChildren($nested, $typeIds, $childCond);
            $nested = $this->attachSupplementalStatus($nested);

            return [
                'success' => true,
                'data' => $nested,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Fund Sourcing Determination — /fund_tracking/fund_sourcing_determination
    // SRS Ref  : 1.2.1.3.1 — SARO Allocations, NCA Received, Available Funds
    //            1.2.1.3.2 — Trust Funds and Supplemental Funds
    //
    // Aggregates fims.fund_tracking by tracking type (SARO / NCA / TRUST —
    // fims.fund_tracking_type.fund_tracking_code) so an FMD Officer can
    // determine available government funds (SARO allocation vs. NCA received)
    // and available special funds (Trust / Supplemental) at a glance. Each row
    // is already tagged with its own type via tracking_type_id (an NCA row's
    // parent_id points back at its SARO row — see baseSelect()/
    // attachChildren() — but a straight per-type SUM here doesn't need that
    // self-join since we're totaling by type, not pairing individual SARO/NCA
    // records).
    //
    // available_funds = total SARO allocation - total NCA received (the
    // portion of allocated SARO not yet covered by a released NCA).
    // Optional filters: fiscal_year (exact) and the fiscal_year_from /
    // fiscal_year_to range.
    // -----------------------------------------------------------------------
    public function fetchFundSourcingDetermination(
        ?int $fiscalYear = null,
        ?int $fiscalYearFrom = null,
        ?int $fiscalYearTo = null,
        ?string $excludeNature = null
    ): array {
        try {
            // Resolved via resolveTrackingTypeId() — the same code/id resolver
            // every other tracking-type filter in this class uses (see
            // fetchFiltered()'s `type` param) — rather than hardcoded ids.
            // Supplemental Funds are NOT a tracking type: they are matched by
            // NATURE OF FUND further down.
            $typeIdMap = [
                'SARO'  => $this->resolveTrackingTypeId('SARO'),
                'NCA'   => $this->resolveTrackingTypeId('NCA'),
                'TRUST' => $this->resolveTrackingTypeId('TRUST'),
            ];
            $typeIds = array_values(array_filter($typeIdMap, fn ($id) => $id !== null));

            // Filters shared by the per-type totals and the Supplemental total
            $filters = [self::LIVE];

            if ($fiscalYear) {
                $filters[] = 'ft_rec.fiscal_year = '.$fiscalYear;
            }

            if ($fiscalYearFrom !== null && $fiscalYearTo !== null) {
                $filters[] = 'ft_rec.fiscal_year BETWEEN '.$fiscalYearFrom.' AND '.$fiscalYearTo;
            } elseif ($fiscalYearFrom !== null) {
                $filters[] = 'ft_rec.fiscal_year >= '.$fiscalYearFrom;
            } elseif ($fiscalYearTo !== null) {
                $filters[] = 'ft_rec.fiscal_year <= '.$fiscalYearTo;
            }

            // $excludeNature (a nature of fund id or name) leaves that nature out of the per-type totals — the SARO / NCA page
            // asks for everything but Supplemental. It is NOT part of $filters: the Supplemental total below shares those.
            $excludeCond = $this->excludeNatureCond('ft_rec', $excludeNature);
            $typeWhere = array_merge(
                [!empty($typeIds) ? 'ft_rec.tracking_type_id IN ('.implode(',', $typeIds).')' : '1 = 0'],
                $filters,
                $excludeCond !== '' ? [$excludeCond] : []
            );

            $sql = "
                SELECT
                    ft_rec.tracking_type_id              AS fund_tracking_type_id,
                    COALESCE(SUM(ft_rec.amount), 0)      AS total_amount,
                    COUNT(ft_rec.id)                     AS record_count
                FROM {$this->table} ft_rec
                WHERE ".implode(' AND ', $typeWhere).'
                GROUP BY ft_rec.tracking_type_id
            ';

            $rows = App\ExecuteRows($sql, 'DB') ?: [];
            $byId = array_column($rows, null, 'fund_tracking_type_id');

            // Supplemental Funds are matched by NATURE OF FUND, not tracking
            // type — same rule as /fund_tracking/list?nature_of_fund=SUPPLEMENTAL
            // — so a record flagged Supplemental is counted whatever its
            // tracking type is. Same filters as above.
            $suppWhere = array_merge(
                ["ft_rec.nature_of_fund_id IN (SELECT nature_of_fund_id FROM {$this->tableNatureOfFund} WHERE UPPER(name) = 'SUPPLEMENTAL')"],
                $filters
            );
            $suppRows = App\ExecuteRows("
                SELECT
                    COALESCE(SUM(ft_rec.amount), 0)  AS total_amount,
                    COUNT(ft_rec.id)                 AS record_count
                FROM {$this->table} ft_rec
                WHERE ".implode(' AND ', $suppWhere), 'DB') ?: [];
            $supp = $suppRows[0] ?? [];

            $get = fn (?int $id, string $field) => $id !== null ? ($byId[$id][$field] ?? null) : null;

            $saroAllocation = (float) ($get($typeIdMap['SARO'], 'total_amount') ?? 0);
            $ncaReceived = (float) ($get($typeIdMap['NCA'], 'total_amount') ?? 0);
            $trustFunds = (float) ($get($typeIdMap['TRUST'], 'total_amount') ?? 0);
            $supplementalFunds = (float) ($supp['total_amount'] ?? 0);

            return [
                'success' => true,
                'data' => [
                    // SRS 1.2.1.3.1 — available government funds
                    'government_funds' => [
                        'saro_allocation' => $saroAllocation,
                        'saro_record_count' => (int) ($get($typeIdMap['SARO'], 'record_count') ?? 0),
                        'nca_received' => $ncaReceived,
                        'nca_record_count' => (int) ($get($typeIdMap['NCA'], 'record_count') ?? 0),
                        'available_funds' => $saroAllocation - $ncaReceived,
                    ],
                    // SRS 1.2.1.3.2 — available special funds
                    'special_funds' => [
                        'trust_funds' => $trustFunds,
                        'trust_funds_record_count' => (int) ($get($typeIdMap['TRUST'], 'record_count') ?? 0),
                        'supplemental_funds' => $supplementalFunds,
                        'supplemental_funds_record_count' => (int) ($supp['record_count'] ?? 0),
                    ],
                ],
                'filters' => [
                    'fiscal_year' => $fiscalYear,
                    'fiscal_year_from' => $fiscalYearFrom,
                    'fiscal_year_to' => $fiscalYearTo,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Fund Sourcing Dashboard — /fund_tracking/dashboard
    // SRS Ref  : 1.2.1.1.2 — SARO Allocations, NCA Received, Available Funds
    //            1.2.1.1.3 — Trust Funds and Supplemental Funds
    //
    // government_funds / special_funds: the same SARO/NCA/Trust/Supplemental
    // aggregation as fetchFundSourcingDetermination() (SRS 1.2.1.3), reused via
    // a direct call rather than duplicated.
    //
    // The Cross-Access / Borrowing section (SRS 1.2.1.1.1) is not part of this
    // response: it read treasury.fund_transfers, which does not exist in this
    // database yet.
    // -----------------------------------------------------------------------
    public function fetchFundSourcingDashboard(?int $fiscalYear = null): array
    {
        $determination = $this->fetchFundSourcingDetermination($fiscalYear);
        if (!$determination['success']) {
            return $determination;
        }

        return [
            'success' => true,
            'data' => [
                // SRS 1.2.1.1.2 — available government funds
                'government_funds' => $determination['data']['government_funds'],
                // SRS 1.2.1.1.3 — available special funds
                'special_funds' => $determination['data']['special_funds'],
            ],
            'filters' => [
                'fiscal_year' => $fiscalYear,
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Fetch one by ID — /fund_tracking/read/{id}
    // -----------------------------------------------------------------------
    public function fetchById(int|string $id): array
    {
        try {
            $id = (int) $id;
            $sql = $this->baseSelect().' WHERE '.self::LIVE." AND ft_rec.id = {$id}";
            $row = App\ExecuteRow($sql, 'DB');

            if (!$row) {
                return ['success' => false, 'message' => "Fund tracking record with ID {$id} not found."];
            }

            $nested = $this->nestRow($row);
            [$nested] = $this->attachChildren([$nested]);

            // When this record is a Supplemental one (nature of fund
            // SUPPLEMENTAL — or, for older data, tracking type SUPPLEMENTAL),
            // also attach the parent SARO's own children (sibling releases
            // under the same parent) so the parent object carries the same
            // context a normal record would.
            $isSupplemental = strtoupper((string) ($nested['nature_of_fund']['name'] ?? '')) === 'SUPPLEMENTAL'
                || strtoupper((string) ($nested['tracking_type']['fund_tracking_code'] ?? '')) === 'SUPPLEMENTAL';
            if (!empty($nested['parent']) && $isSupplemental) {
                $nested['parent'] = $this->attachChildren($nested['parent']);
            }

            // Supporting documents (one-to-many via multiple_node_id), each
            // joined to its fims.ip_filemeta row — same lookup the Memo Request
            // read uses.
            $nested['file_attachments'] = !empty($nested['multiple_node_id'])
                ? (new FileUploadServices())->attachmentsFor((string) $nested['multiple_node_id'])
                : [];

            // Supplemental Fund details (fmd.supplemental_fund_details), or null
            $nested['supplemental'] = $this->fetchSupplementalDetails($id);

            return ['success' => true, 'data' => $nested];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Supplemental Fund details — fmd.supplemental_fund_details, one optional
    // row per fund_tracking record (UNIQUE fund_tracking_id). Returned as
    // `supplemental` on /fund_tracking/read/{id} (null when there is none).
    // -----------------------------------------------------------------------
    private function fetchSupplementalDetails(int $fundTrackingId): ?array
    {
        // A failed lookup (e.g. the table is not created yet) must never block
        // reading the record itself.
        try {
            $row = App\Conn('DB')->executeQuery(
                "SELECT * FROM {$this->tableSupplementalDetails} WHERE fund_tracking_id = ?",
                [$fundTrackingId]
            )->fetchAssociative();
        } catch (\Throwable $e) {
            return null;
        }

        return $row ?: null;
    }

    // SQL condition splitting records into "with SARO/NCA" and "without SARO/NCA".
    // A record is WITH a SARO/NCA when its own tracking type is SARO or NCA
    // (fims.fund_tracking_type), or when its parent record (parent_id) is one — i.e.
    // a Supplemental release that belongs to a SARO/NCA. Everything else, including
    // a Supplemental record standing on its own, is WITHOUT one.
    // $group is 'SARO_NCA' or 'NO_SARO_NCA'; any other value returns '' = no narrowing.
    private function trackingGroupCond(string $alias, mixed $group): string
    {
        $group = strtoupper(trim((string) $group));
        if ($group !== 'SARO_NCA' && $group !== 'NO_SARO_NCA') {
            return '';
        }

        $typeIds = "SELECT fund_tracking_type_id FROM {$this->tableTrackingTypes} WHERE UPPER(TRIM(fund_tracking_code)) IN ('SARO', 'NCA')";
        $with = "(COALESCE({$alias}.tracking_type_id, 0) IN ({$typeIds})"
            ." OR EXISTS (SELECT 1 FROM {$this->table} tg_par WHERE tg_par.id = {$alias}.parent_id AND tg_par.deleted_at IS NULL"
            ." AND tg_par.tracking_type_id IN ({$typeIds})))";

        return $group === 'SARO_NCA' ? $with : "NOT {$with}";
    }

    // SQL condition on a record's Supplemental status, for the page's Status filter.
    // $status is PENDING (stored IN_PROCESS / AT_NDRRMC / AT_OP — still waiting on
    // the approval), APPROVED or DENIED; any other value returns '' = no narrowing.
    // A record with no fmd.supplemental_fund_details row counts as IN_PROCESS, so it
    // is Pending.
    private function supplementalStatusCond(string $alias, mixed $status): string
    {
        $in = [
            'PENDING' => "'IN_PROCESS', 'AT_NDRRMC', 'AT_OP'",
            'APPROVED' => "'APPROVED'",
            'DENIED' => "'DENIED'",
        ][strtoupper(trim((string) $status))] ?? null;

        if ($in === null) {
            return '';
        }

        return "COALESCE((SELECT sd.status FROM {$this->tableSupplementalDetails} sd WHERE sd.fund_tracking_id = {$alias}.id), 'IN_PROCESS') IN ({$in})";
    }

    // Tag every row (and each of its children) with `supplemental_status` — the
    // status of its fmd.supplemental_fund_details row, or null when it has none
    // (a non-Supplemental record, or a Supplemental one saved before the details
    // existed; the page treats the latter as IN_PROCESS). One batched query; a
    // failed lookup (e.g. the table is not created yet) never blocks the list.
    private function attachSupplementalStatus(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
            foreach ($row['children'] ?? [] as $child) {
                $ids[] = (int) $child['id'];
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));

        $byId = [];
        if ($ids) {
            try {
                $found = App\ExecuteRows(
                    "SELECT fund_tracking_id, status FROM {$this->tableSupplementalDetails}
                      WHERE fund_tracking_id IN (".implode(',', $ids).')',
                    'DB'
                ) ?: [];
                foreach ($found as $f) {
                    $byId[(int) $f['fund_tracking_id']] = $f['status'];
                }
            } catch (\Throwable $e) {
                // leave every status null
            }
        }

        foreach ($rows as &$row) {
            $row['supplemental_status'] = $byId[(int) $row['id']] ?? null;
            foreach ($row['children'] ?? [] as $i => $child) {
                $row['children'][$i]['supplemental_status'] = $byId[(int) $child['id']] ?? null;
            }
        }
        unset($row);

        return $rows;
    }

    // -----------------------------------------------------------------------
    // Supplemental status summary — /fund_tracking/supplemental_status_summary
    // Counts (and sums the amount of) every SUPPLEMENTAL record per status, for
    // the FMD/SupplementalPage dashboard. A Supplemental record with no
    // fmd.supplemental_fund_details row counts as IN_PROCESS. Optional
    // $fiscalYear narrows it to one fiscal year, like the page's filter;
    // optional $trackingGroup ('SARO_NCA' / 'NO_SARO_NCA') to one of the page's two tabs.
    // Every status is always present in the result, zero-filled.
    // -----------------------------------------------------------------------
    public function fetchSupplementalStatusSummary(?int $fiscalYear = null, ?string $trackingGroup = null): array
    {
        try {
            $where = self::LIVE." AND UPPER(TRIM(nof.name)) = 'SUPPLEMENTAL'";
            if ($fiscalYear) {
                $where .= ' AND ft_rec.fiscal_year = '.(int) $fiscalYear;
            }
            $groupCond = $this->trackingGroupCond('ft_rec', $trackingGroup);
            if ($groupCond !== '') {
                $where .= " AND {$groupCond}";
            }

            $rows = App\ExecuteRows(
                "SELECT COALESCE(sd.status, 'IN_PROCESS') AS status,
                        COUNT(*) AS total,
                        COALESCE(SUM(ft_rec.amount), 0) AS amount
                   FROM {$this->table} ft_rec
                   JOIN {$this->tableNatureOfFund} nof ON nof.nature_of_fund_id = ft_rec.nature_of_fund_id
                   LEFT JOIN {$this->tableSupplementalDetails} sd ON sd.fund_tracking_id = ft_rec.id
                  WHERE {$where}
                  GROUP BY 1",
                'DB'
            ) ?: [];

            $data = [];
            foreach (self::SUPPLEMENTAL_STATUSES as $s) {
                $data[$s] = ['status' => $s, 'total' => 0, 'amount' => 0.0];
            }
            foreach ($rows as $r) {
                if (isset($data[$r['status']])) {
                    $data[$r['status']] = [
                        'status' => $r['status'],
                        'total' => (int) $r['total'],
                        'amount' => (float) $r['amount'],
                    ];
                }
            }

            return ['success' => true, 'data' => array_values($data)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Validate + convert the `supplemental` object. Returns [array $values,
    // string[] $errors]; $values is keyed by column (NULL for blanks, and for
    // every field the caller left out — the row is saved whole, see below).
    private function prepareSupplementalDetails(array $input): array
    {
        $values = [];
        $errors = [];

        foreach (self::SUPPLEMENTAL_FIELDS as $field => $type) {
            $raw = $input[$field] ?? null;
            if (is_string($raw)) {
                $raw = trim($raw);
            }
            if ($raw === '' || $raw === null) {
                // status is NOT NULL — a blank one means a fresh, unprocessed request
                $values[$field] = $type === 'status' ? 'IN_PROCESS' : null;
                continue;
            }

            switch ($type) {
                case 'status':
                    $status = strtoupper((string) $raw);
                    if (!in_array($status, self::SUPPLEMENTAL_STATUSES, true)) {
                        $errors[] = '"status" must be one of: '.implode(', ', self::SUPPLEMENTAL_STATUSES).'.';
                        $values[$field] = 'IN_PROCESS';
                    } else {
                        $values[$field] = $status;
                    }
                    break;

                case 'region':
                    $name = App\Conn('DB')->executeQuery(
                        "SELECT rc_name FROM {$this->viewRegions} WHERE UPPER(rc_name) = UPPER(?) LIMIT 1",
                        [(string) $raw]
                    )->fetchOne();
                    if ($name === false || $name === null) {
                        $errors[] = '"regional_office" does not match any regional office name.';
                        $values[$field] = null;
                    } else {
                        $values[$field] = (string) $name;
                    }
                    break;

                case 'decimal':
                    if (!is_numeric($raw) || (float) $raw < 0) {
                        $errors[] = "\"{$field}\" must be a non-negative number.";
                        $values[$field] = null;
                    } else {
                        $values[$field] = round((float) $raw, 2);
                    }
                    break;

                case 'int':
                    if (!is_numeric($raw) || (float) $raw != (int) $raw || (int) $raw < 0) {
                        $errors[] = "\"{$field}\" must be a whole number, zero or more.";
                        $values[$field] = null;
                    } else {
                        $values[$field] = (int) $raw;
                    }
                    break;

                case 'date':
                    if (
                        !is_string($raw)
                        || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)
                        || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                    ) {
                        $errors[] = "\"{$field}\" must be a valid date (YYYY-MM-DD).";
                        $values[$field] = null;
                    } else {
                        $values[$field] = $raw;
                    }
                    break;
            }
        }

        return [$values, $errors];
    }

    // Insert-or-update the record's supplemental row. The row is written whole:
    // the form always sends every field (blank = null), so a cleared field
    // clears the stored value. $values comes from prepareSupplementalDetails().
    private function saveSupplementalDetails(int $fundTrackingId, array $values, ?int $createdBy): void
    {
        $cols = array_keys(self::SUPPLEMENTAL_FIELDS);
        $params = [$fundTrackingId];
        foreach ($cols as $c) {
            $params[] = $values[$c] ?? null;
        }
        $params[] = $createdBy;

        $colList = implode(', ', array_merge(['fund_tracking_id'], $cols, ['created_by']));
        $marks = implode(', ', array_fill(0, count($params), '?'));
        $setList = implode(', ', array_map(fn ($c) => "{$c} = EXCLUDED.{$c}", $cols));

        App\Conn('DB')->executeQuery(
            "INSERT INTO {$this->tableSupplementalDetails} ({$colList}) VALUES ({$marks})
             ON CONFLICT (fund_tracking_id) DO UPDATE SET {$setList}, updated_at = CURRENT_TIMESTAMP",
            $params
        );
    }

    // -----------------------------------------------------------------------
    // Generate saro_id — "<NATURE OF FUND>-<FISCAL YEAR>", e.g.
    // "GAAAO-2026" or "INSERTIONS-2026". Nature of fund comes from
    // fims.nature_of_fund.name (non-alphanumerics → hyphens), the year is the
    // record's fiscal_year (falls back to the current year when none is given).
    // Returns null when the nature of fund can't be resolved.
    //
    // The reference number is generated only for GAAAO records, and only as a DEFAULT:
    // create()/update() use it when the client sends none (or a blank one), but keep a
    // reference the client does send (saro-processing-form.php pre-fills this same
    // format, lets the user edit it, and has a Generate link to fill it in again).
    // Other natures of fund take a user-entered value.
    // -----------------------------------------------------------------------
    public function generateReferenceNumber(int|string|null $natureOfFundId, int|string|null $fiscalYear = null): ?string
    {
        $natureId = (int) $natureOfFundId;
        if ($natureId <= 0) {
            return null;
        }

        $type = App\ExecuteScalar(
            "SELECT name FROM {$this->tableNatureOfFund}
              WHERE nature_of_fund_id = {$natureId}
              LIMIT 1",
            'DB'
        );
        if (!$type) {
            return null;
        }

        $year = $this->normalizeFiscalYear($fiscalYear) ?? (int) date('Y');

        $code = preg_replace('/[^A-Z0-9]+/', '-', strtoupper(trim((string) $type)));

        return trim($code, '-').'-'.$year;
    }

    // -----------------------------------------------------------------------
    // True when the nature of fund is GAAAO (exact, case-insensitive match on
    // fims.nature_of_fund.name). Only GAAAO records get a system-generated
    // "<NATURE>-<YEAR>" saro_id; every other nature of fund carries
    // a free-text saro_id entered by the user (e.g. the OR No. for
    // OR records).
    // -----------------------------------------------------------------------
    private function isGaaaoNature(int|string|null $natureOfFundId): bool
    {
        $id = (int) $natureOfFundId;
        if ($id <= 0) {
            return false;
        }

        $name = App\ExecuteScalar(
            "SELECT name FROM {$this->tableNatureOfFund}
              WHERE nature_of_fund_id = {$id}
              LIMIT 1",
            'DB'
        );

        return strtoupper(trim((string) $name)) === 'GAAAO';
    }

    // -----------------------------------------------------------------------
    // The amount of an NCA is deducted from the SARO it is released under, so a SARO can never be released more than it is worth:
    // the NCAs already under it (live ones, not counting $excludeId — the NCA being changed) plus $amount may not exceed the SARO's
    // own amount. It applies only when $parentId is a SARO (an NCA under any other record is not capped). The SARO's amount itself is
    // never rewritten — what it has left is always its amount less its NCAs (the Fund Sourcing totals work the same way).
    // $lock takes a row lock on the SARO, held until the caller's transaction ends, so two NCAs saved at the same moment cannot both
    // fit into what is left. Returns an error message, or null when it fits (an amount of zero always fits).
    // -----------------------------------------------------------------------
    private function ncaOverSaroError(int $parentId, float $amount, ?int $excludeId = null, bool $lock = false): ?string
    {
        $saroTypeId = $this->resolveTrackingTypeId('SARO');
        $ncaTypeId = $this->resolveTrackingTypeId('NCA');
        if ($saroTypeId === null || $ncaTypeId === null || $parentId <= 0) {
            return null;
        }

        $saro = App\ExecuteRow(
            "SELECT saro_id, amount FROM {$this->table}
              WHERE id = {$parentId} AND tracking_type_id = {$saroTypeId} AND deleted_at IS NULL"
            .($lock ? ' FOR UPDATE' : ''),
            'DB'
        );
        if (!$saro) {
            return null;
        }

        $exclude = $excludeId !== null ? " AND id <> {$excludeId}" : '';
        $released = (float) App\ExecuteScalar(
            "SELECT COALESCE(SUM(amount), 0) FROM {$this->table}
              WHERE parent_id = {$parentId} AND tracking_type_id = {$ncaTypeId} AND deleted_at IS NULL{$exclude}",
            'DB'
        );

        $saroAmount = (float) ($saro['amount'] ?? 0);
        $left = round($saroAmount - $released, 2);
        if (round($amount, 2) <= 0 || round($amount, 2) <= $left) {
            return null;
        }

        $peso = fn (float $v): string => '₱'.number_format($v, 2);

        return 'This NCA ('.$peso($amount).') is more than SARO '.($saro['saro_id'] ?? '#'.$parentId).' has left ('
            .$peso(max($left, 0.0)).' of '.$peso($saroAmount).'; '.$peso($released).' already released under it).';
    }

    // -----------------------------------------------------------------------
    // The counterpart of ncaOverSaroError(): a SARO's amount may not be lowered below the NCAs already released under it (the live NCAs
    // whose parent it is). Only a decrease is looked at — a new amount that still covers the NCAs, or that is not lower than the old one
    // (saving it unchanged, raising it), always fits. Returns an error message, or null when it fits.
    // -----------------------------------------------------------------------
    private function saroBelowNcasError(int $saroId, float $newAmount, float $oldAmount): ?string
    {
        $ncaTypeId = $this->resolveTrackingTypeId('NCA');
        if ($ncaTypeId === null || $saroId <= 0) {
            return null;
        }

        $released = (float) App\ExecuteScalar(
            "SELECT COALESCE(SUM(amount), 0) FROM {$this->table}
              WHERE parent_id = {$saroId} AND tracking_type_id = {$ncaTypeId} AND deleted_at IS NULL",
            'DB'
        );

        if (round($newAmount, 2) >= round($released, 2) || round($newAmount, 2) >= round($oldAmount, 2)) {
            return null;
        }

        $peso = fn (float $v): string => '₱'.number_format($v, 2);

        return 'This SARO\'s amount cannot be lowered to '.$peso($newAmount).': '.$peso($released)
            .' has already been released under it (its NCAs), so it must stay at '.$peso($released).' or more.';
    }

    // Most child records (NCAs under a SARO / ORs under a Trust Fund) one create() call may add.
    private const MAX_NCAS = 100;
    private const MAX_ORS = 100;

    // -----------------------------------------------------------------------
    // Child records created together with a new parent record: create()'s `ncas` list under a SARO, and its `ors` list under a
    // Trust Fund (an OR's parent is its Trust Fund). prepareNcas() and prepareOrs() are the two ways in; they differ only in
    // $kind:
    //   parentType / parentName — the tracking type the NEW record must have (a tracking-type code the resolver understands,
    //                             and how it is named in the message), child — the type the children get (and "NCA" / "OR"
    //                             in the messages), max — how many one request may add,
    //   fields  — what an item may carry besides its saro_id (required),
    //   inherit — what a child takes from the new record unless the item gives its own (a key left out inherits, a value
    //             overrides it and must exist, an explicit null / '' means none) — the same carry-forward the Excel import
    //             applies.
    // A child is typed $kind['child'] and points at the new record (its parent_id is filled in once that record has an id). Its
    // reference is always the typed one: a generated GAAAO reference belongs to the parent itself, not to its children.
    //
    // Everything is checked here, BEFORE anything is written, so create() can save the parent and all of its children in one
    // transaction — or none of them.
    // Returns [array $rows, ?string $error] (an error names the child: "NCA #2: ...", "OR #2: ...").
    // -----------------------------------------------------------------------
    private function prepareChildRecords(array $items, array $parent, ?int $userId, array $kind): array
    {
        $child = $kind['child'];

        $parentTypeId = $this->resolveTrackingTypeId($kind['parentType']);
        if ($parentTypeId === null || (int) ($parent['tracking_type_id'] ?? 0) !== $parentTypeId) {
            return [[], "{$child} records can only be created together with a {$kind['parentName']}."];
        }

        $childTypeId = $this->resolveTrackingTypeId($child);
        if ($childTypeId === null) {
            return [[], "The tracking type '{$child}' is not set up, so {$child} records cannot be created."];
        }

        if (count($items) > $kind['max']) {
            return [[], "At most {$kind['max']} {$child} records can be created at once."];
        }

        $rows = [];
        $seen = [];

        foreach (array_values($items) as $i => $item) {
            $label = "{$child} #".($i + 1);

            if (!is_array($item)) {
                return [[], "{$label}: invalid entry."];
            }

            $ref = trim((string) ($item['saro_id'] ?? ''));
            if ($ref === '') {
                return [[], "{$label}: saro_id is required."];
            }

            $key = strtoupper($ref);
            if (isset($seen[$key])) {
                return [[], "{$label}: saro_id '{$ref}' is used more than once in this request."];
            }
            $seen[$key] = true;

            $row = ['tracking_type_id' => $childTypeId, 'saro_id' => $ref];

            foreach ($kind['fields'] as $k) {
                if (array_key_exists($k, $item) && $item[$k] !== null && $item[$k] !== '') {
                    $row[$k] = is_string($item[$k]) ? trim($item[$k]) : $item[$k];
                }
            }

            // the child's own value when the item carries the key (null / '' there means "none" for that child),
            // otherwise the parent's — so an item that leaves them out behaves exactly like the Excel import's carry-forward
            foreach ($kind['inherit'] as $k) {
                if (array_key_exists($k, $item)) {
                    if ($item[$k] !== null && $item[$k] !== '') {
                        $row[$k] = is_string($item[$k]) ? trim($item[$k]) : $item[$k];
                    }
                } elseif (isset($parent[$k]) && $parent[$k] !== '') {
                    $row[$k] = $parent[$k];
                }
            }

            $error = $this->validateReferences($row);
            if ($error !== null) {
                return [[], "{$label}: {$error}"];
            }

            if ($userId !== null && $userId > 0) {
                $row['user_id'] = $userId;
            }

            $rows[] = $row;
        }

        return [$rows, null];
    }

    // NCA records created together with a new SARO (create()'s `ncas`). Each item: saro_id (required), description, amount,
    // bank_account_no, and optionally its own fiscal_year and nature_of_fund_id. (A child's date is the day it is created.)
    private function prepareNcas(array $items, array $saro, ?int $userId): array
    {
        return $this->prepareChildRecords($items, $saro, $userId, [
            'parentType' => 'SARO', 'parentName' => 'SARO', 'child' => 'NCA', 'max' => self::MAX_NCAS,
            'fields' => ['description', 'amount', 'bank_account_no'],
            'inherit' => ['fiscal_year', 'nature_of_fund_id'],
        ]);
    }

    // OR records created together with a new Trust Fund (create()'s `ors`). Each item: saro_id (required), description, amount,
    // bank_account_no, remarks, and optionally its own fiscal_year, nature_of_fund_id and agency_name — an OR takes the Trust
    // Fund's fiscal year, nature of fund and agency unless the item says otherwise. (A child's date is the day it is created.)
    private function prepareOrs(array $items, array $trust, ?int $userId): array
    {
        return $this->prepareChildRecords($items, $trust, $userId, [
            'parentType' => 'TRUST', 'parentName' => 'Trust Fund', 'child' => 'OR', 'max' => self::MAX_ORS,
            'fields' => ['description', 'amount', 'bank_account_no', 'remarks'],
            'inherit' => ['fiscal_year', 'nature_of_fund_id', 'agency_name'],
        ]);
    }

    // -----------------------------------------------------------------------
    // Create — /fund_tracking/add
    // Required: tracking_type_id (nature_of_fund_id is optional). saro_id is
    // generated from nature of fund + the fiscal year (see generateReferenceNumber())
    // when the nature of fund is GAAAO (see isGaaaoNature()) and none is sent (a reference
    // that is sent is kept, so it is optional there); for every other nature of fund it is
    // required and taken as typed by the user.
    //
    // Optional `ncas` (SARO only — see prepareNcas()): NCA records to create under
    // the new SARO. The SARO and every NCA are saved in ONE transaction, so a bad
    // NCA leaves nothing behind. Returns `nca_ids` next to `id`.
    // Optional `ors` (Trust Fund only — see prepareOrs()): the same for OR records
    // under a new Trust Fund; returns `or_ids`. A request carries one list or the
    // other — each is refused under any other tracking type.
    //
    // The record's `date` is the one the body sends (YYYY-MM-DD); without one it is the day it is created (CURRENT_DATE, below). An
    // `sub_program_code` in the body is ignored — that column was removed — and so is `unreleased`, which the API works out itself
    // (refreshSaroUnreleased()). `sub_program` is accepted.
    //
    // $userId — the verified (JWT) user, stored in user_id; never taken from $body.
    // -----------------------------------------------------------------------
    // -----------------------------------------------------------------------
    // A SARO's saved balance: fims.fund_tracking.unreleased = the SARO's amount less every live NCA released under it (the same set
    // ncaOverSaroError() counts). It is kept ONLY on SAROs — an NCA, Trust Fund, OR… has none — and it is worked out here, never taken from
    // a request. Called after anything that can change it: a SARO added / its amount edited, an NCA added / its amount, SARO or type
    // changed / deleted. Pass every id that may be affected (the record itself, its old and its new parent): ids that are not a live SARO
    // are simply skipped. It runs inside the caller's transaction, when there is one.
    // -----------------------------------------------------------------------
    private function refreshSaroUnreleased(int|string|null ...$ids): void
    {
        $saroTypeId = $this->resolveTrackingTypeId('SARO');
        $ncaTypeId = $this->resolveTrackingTypeId('NCA');
        if ($saroTypeId === null || $ncaTypeId === null) {
            return;
        }

        $ids = array_unique(array_filter(array_map('intval', $ids), fn (int $v): bool => $v > 0));
        foreach ($ids as $id) {
            App\Execute(
                "UPDATE {$this->table} s
                    SET unreleased = COALESCE(s.amount, 0) - COALESCE((
                            SELECT SUM(n.amount) FROM {$this->table} n
                             WHERE n.parent_id = s.id AND n.tracking_type_id = {$ncaTypeId} AND n.deleted_at IS NULL
                        ), 0)
                  WHERE s.id = {$id} AND s.tracking_type_id = {$saroTypeId} AND s.deleted_at IS NULL",
                'DB'
            );
            // only SAROs carry a balance: a record that stopped being one (its type was changed) loses it
            App\Execute(
                "UPDATE {$this->table} SET unreleased = NULL
                  WHERE id = {$id} AND unreleased IS NOT NULL AND tracking_type_id IS DISTINCT FROM {$saroTypeId}",
                'DB'
            );
        }
    }

    public function create(array $body, ?int $userId = null): array
    {
        $inTransaction = false;

        try {
            $fields = array_intersect_key($body, array_flip($this->allowedFields));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided.'];
            }

            // Optional Supplemental Fund details — validated up front so a bad
            // value rejects the request before anything is inserted.
            $supplemental = null;
            if (isset($body['supplemental']) && is_array($body['supplemental'])) {
                [$supplemental, $suppErrors] = $this->prepareSupplementalDetails($body['supplemental']);
                if ($suppErrors) {
                    return ['success' => false, 'message' => implode(' ', $suppErrors)];
                }
            }

            if (empty($fields['tracking_type_id'])) {
                return ['success' => false, 'message' => 'tracking_type_id is required.'];
            }

            // An empty value means "not set" — leave the column NULL
            foreach (['fiscal_year', 'parent_id', 'multiple_node_id', 'nature_of_fund_id', 'status_id', 'date'] as $optional) {
                if (array_key_exists($optional, $fields) && ($fields[$optional] === '' || $fields[$optional] === null)) {
                    unset($fields[$optional]);
                }
            }

            $error = $this->validateReferences($fields);
            if ($error !== null) {
                return ['success' => false, 'message' => $error];
            }

            if ($this->isGaaaoNature($fields['nature_of_fund_id'] ?? null)) {
                // GAAAO: the reference is generated from the nature of fund + fiscal year UNLESS the client sends one of its own (the
                // form fills in the generated value and lets the user edit it) — blank means "generate it"
                $given = trim((string) ($fields['saro_id'] ?? ''));
                if ($given !== '') {
                    $fields['saro_id'] = $given;
                } else {
                    $reference = $this->generateReferenceNumber($fields['nature_of_fund_id'], $fields['fiscal_year'] ?? null);
                    if ($reference === null) {
                        return ['success' => false, 'message' => 'nature_of_fund_id does not match any nature of fund.'];
                    }
                    $fields['saro_id'] = $reference;
                }
            } else {
                $referenceNumber = trim((string) ($fields['saro_id'] ?? ''));
                if ($referenceNumber === '') {
                    return ['success' => false, 'message' => 'saro_id is required unless the nature of fund is GAAAO.'];
                }
                $fields['saro_id'] = $referenceNumber;
            }

            if ($userId !== null && $userId > 0) {
                $fields['user_id'] = $userId;
            }

            // Child records to create under this one — NCAs under a SARO (`ncas`), ORs under a Trust Fund (`ors`) — fully
            // validated now, before anything is written. Each list is only accepted under its own parent type, so a request
            // that carries both is refused by whichever does not fit.
            $children = [];
            $childLabel = '';
            foreach ([['ncas', 'prepareNcas', 'NCA'], ['ors', 'prepareOrs', 'OR']] as [$listKey, $prepare, $label]) {
                if (empty($body[$listKey]) || !is_array($body[$listKey])) {
                    continue;
                }

                [$rows, $childError] = $this->{$prepare}($body[$listKey], $fields, $userId);
                if ($childError !== null) {
                    return ['success' => false, 'message' => $childError];
                }

                $children = $rows;
                $childLabel = $label;
            }

            // A SARO created together with its NCAs: they are deducted from it, so they cannot add up to more than it is worth
            if ($childLabel === 'NCA') {
                $releasedNow = array_sum(array_map(fn ($c) => (float) ($c['amount'] ?? 0), $children));
                $saroAmount = (float) ($fields['amount'] ?? 0);
                if (round($releasedNow - $saroAmount, 2) > 0) {
                    return ['success' => false, 'message' => 'The NCA records add up to ₱'.number_format($releasedNow, 2)
                        .', more than the SARO\'s own amount (₱'.number_format($saroAmount, 2).').'];
                }
            }

            // An NCA released under a SARO may not be more than that SARO has left (ncaOverSaroError) — checked below, inside the
            // transaction, with the SARO locked
            $capParent = null;
            $ncaTypeId = $this->resolveTrackingTypeId('NCA');
            if ($ncaTypeId !== null && !empty($fields['parent_id']) && (int) ($fields['tracking_type_id'] ?? 0) === $ncaTypeId) {
                $capParent = (int) $fields['parent_id'];
            }

            $columns = [];
            $values = [];

            foreach ($fields as $key => $value) {
                if ($key === 'date') {
                    continue;   // below
                }
                $columns[] = App\QuotedName($key, 'DB');
                $values[] = $this->quoteValue($key, $value);
            }

            // the date the form entered, else the day the record is created (the forms with no Date field — Trust Fund, OR …)
            $columns[] = App\QuotedName('date', 'DB');
            $values[] = isset($fields['date']) ? $this->quoteValue('date', $fields['date']) : 'CURRENT_DATE';

            $colList = implode(', ', $columns);
            $valList = implode(', ', $values);

            // The record and its children go in together (BEGIN … COMMIT, the same way the rest of the app does it) —
            // when there are no children it is the single INSERT it always was.
            if ($children || $capParent !== null) {
                App\Execute('BEGIN', 'DB');
                $inTransaction = true;
            }

            if ($capParent !== null) {
                $over = $this->ncaOverSaroError($capParent, (float) ($fields['amount'] ?? 0), null, true);
                if ($over !== null) {
                    App\Execute('ROLLBACK', 'DB');
                    $inTransaction = false;

                    return ['success' => false, 'message' => $over];
                }
            }

            $sql = "INSERT INTO {$this->table} ({$colList}) VALUES ({$valList}) RETURNING id";
            $result = App\ExecuteRow($sql, 'DB');
            $newId = $result['id'] ?? null;

            // a SARO saves its balance (its amount — the NCAs saved with it below refresh it again); an NCA refreshes its SARO's
            $this->refreshSaroUnreleased($newId, $fields['parent_id'] ?? null);

            $childIds = [];
            foreach ($children as $i => $child) {
                $child['parent_id'] = $newId;
                $saved = $this->insertRow($child);
                if ($saved['status'] !== 'ok') {
                    throw new \Exception("{$childLabel} #".($i + 1).': '.($saved['message'] ?? 'could not be saved.'));
                }
                $childIds[] = $saved['id'];
            }
            $ncaIds = $childLabel === 'NCA' ? $childIds : [];
            $orIds = $childLabel === 'OR' ? $childIds : [];

            if ($inTransaction) {
                App\Execute('COMMIT', 'DB');
                $inTransaction = false;
            }

            if ($supplemental !== null && $newId) {
                try {
                    $this->saveSupplementalDetails((int) $newId, $supplemental, $userId);
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Fund tracking record #'.$newId.' was created'
                            .($childIds ? ' (with '.count($childIds)." {$childLabel} record(s) under it)" : '')
                            .', but its supplemental details could not be saved: '.$e->getMessage(),
                        'id' => $newId,
                        'nca_ids' => $ncaIds,
                        'or_ids' => $orIds,
                    ];
                }
            }

            return [
                'success' => true,
                'message' => $childIds
                    ? 'Fund tracking record created successfully, with '.count($childIds)." {$childLabel} record(s) under it."
                    : 'Fund tracking record created successfully.',
                'id' => $newId,
                'nca_ids' => $ncaIds,
                'or_ids' => $orIds,
            ];
        } catch (\Exception $e) {
            if ($inTransaction) {
                try {
                    App\Execute('ROLLBACK', 'DB');
                } catch (\Throwable $rollbackError) {
                    // the connection is already gone — nothing more to undo
                }
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Update — /fund_tracking/update/{id}
    // Partial update: only the fields sent change. For the optional ids / codes
    // (fiscal_year, parent_id, multiple_node_id, nature_of_fund_id, status_id)
    // '' leaves the value alone and null clears it. `date` is changed when a valid one is sent (a blank or null one leaves it as it is).
    // user_id and the timestamps are never writable; updated_at is stamped.
    // $userId is only used to attribute a saved supplemental row.
    // -----------------------------------------------------------------------
    public function update(int|string $id, array $body, ?int $userId = null): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            // Optional Supplemental Fund details — validated up front; saved
            // after the main UPDATE below. Omit the key to leave them untouched.
            $supplemental = null;
            if (isset($body['supplemental']) && is_array($body['supplemental'])) {
                [$supplemental, $suppErrors] = $this->prepareSupplementalDetails($body['supplemental']);
                if ($suppErrors) {
                    return ['success' => false, 'message' => implode(' ', $suppErrors)];
                }
            }

            // An empty string for an optional id / code means "leave it as it is"
            // (forms send '' for an unselected dropdown); only an explicit null
            // clears it.
            foreach (['fiscal_year', 'parent_id', 'multiple_node_id', 'nature_of_fund_id', 'status_id', 'date'] as $optional) {
                if (isset($body[$optional]) && $body[$optional] === '') {
                    unset($body[$optional]);
                }
            }

            // saro_id is generated only for GAAAO records, and only as a
            // default: a value the client sends is kept as sent; without one (or with a
            // blank one) it is generated — but, when none is sent, only when the
            // record has none yet or its nature of fund / fiscal year is
            // being changed, so editing other fields never overwrites an
            // existing (e.g. imported or DBM-issued) reference number.
            // Every other nature of fund: the reference number is
            // user-entered, so a client-supplied value is kept
            // (blank is rejected).
            $existing = $check['data'];
            // nature_of_fund_id is optional: an explicit null clears it, so test
            // the key rather than isset()/?? (which would fall back to the old one).
            $natureSent = array_key_exists('nature_of_fund_id', $body);
            $newNatureId = $natureSent ? $body['nature_of_fund_id'] : ($existing['nature_of_fund_id'] ?? null);

            $fields = array_intersect_key($body, array_flip($this->allowedFields));
            // a record always has a date: a null one is "not sent" and leaves the saved date as it is
            if (array_key_exists('date', $fields) && $fields['date'] === null) {
                unset($fields['date']);
            }

            // Reject bad values / dangling references before touching anything
            $error = $this->validateReferences($fields);
            if ($error !== null) {
                return ['success' => false, 'message' => $error];
            }

            if ($this->isGaaaoNature($newNatureId)) {
                // A reference the client sends is kept as sent (the form shows the generated one and lets the user edit it). Without
                // one — or with a blank one, which means "generate it" — it is generated, but only when the record has none yet or its
                // nature of fund / fiscal year is being changed, so editing other fields never overwrites an existing reference.
                $given = array_key_exists('saro_id', $fields) ? trim((string) $fields['saro_id']) : null;
                if ($given !== null && $given !== '') {
                    $fields['saro_id'] = $given;
                } else {
                    unset($fields['saro_id']);
                    $newYear = array_key_exists('fiscal_year', $fields) ? $fields['fiscal_year'] : ($existing['fiscal_year'] ?? null);
                    $natureChanged = $natureSent && (int) $newNatureId !== (int) ($existing['nature_of_fund_id'] ?? 0);
                    $yearChanged = isset($fields['fiscal_year']) && (int) $fields['fiscal_year'] !== (int) ($existing['fiscal_year'] ?? 0);
                    if ($given === '' || empty($existing['saro_id']) || $natureChanged || $yearChanged) {
                        $reference = $this->generateReferenceNumber($newNatureId, $newYear);
                        if ($reference !== null) {
                            $fields['saro_id'] = $reference;
                        }
                    }
                }
            } elseif (array_key_exists('saro_id', $fields)) {
                $referenceNumber = trim((string) $fields['saro_id']);
                if ($referenceNumber === '') {
                    return ['success' => false, 'message' => 'saro_id cannot be blank unless the nature of fund is GAAAO.'];
                }
                $fields['saro_id'] = $referenceNumber;
            }

            if (empty($fields) && $supplemental === null) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            // A record cannot be its own parent
            if (isset($fields['parent_id']) && (int) $fields['parent_id'] === (int) $id) {
                return ['success' => false, 'message' => 'parent_id cannot reference the record\'s own id.'];
            }

            // An NCA's amount is deducted from its SARO: changing its amount, its SARO or its type must still fit into what that
            // SARO has left (not counting this NCA's own saved amount). A change to anything else is not looked at.
            if (array_key_exists('amount', $fields) || array_key_exists('parent_id', $fields) || array_key_exists('tracking_type_id', $fields)) {
                $ncaTypeId = $this->resolveTrackingTypeId('NCA');
                $finalType = array_key_exists('tracking_type_id', $fields) ? $fields['tracking_type_id'] : ($existing['tracking_type_id'] ?? null);
                $finalParent = array_key_exists('parent_id', $fields) ? $fields['parent_id'] : ($existing['parent_id'] ?? null);
                $finalAmount = array_key_exists('amount', $fields) ? $fields['amount'] : ($existing['amount'] ?? null);
                if ($ncaTypeId !== null && (int) $finalType === $ncaTypeId && (int) $finalParent > 0) {
                    $over = $this->ncaOverSaroError((int) $finalParent, (float) $finalAmount, (int) $id);
                    if ($over !== null) {
                        return ['success' => false, 'message' => $over];
                    }
                }
            }

            // …and the other way round: a SARO's amount cannot be lowered below what has already been released under it (its NCAs),
            // or the SARO would go negative. Only a DECREASE below the NCA total is refused, so saving a SARO with its amount unchanged
            // (the form always sends it) or raising it always works — even for one that was already over-released before this rule.
            if (array_key_exists('amount', $fields)) {
                $saroTypeId = $this->resolveTrackingTypeId('SARO');
                $typeNow = array_key_exists('tracking_type_id', $fields) ? $fields['tracking_type_id'] : ($existing['tracking_type_id'] ?? null);
                if ($saroTypeId !== null && (int) $typeNow === $saroTypeId) {
                    $below = $this->saroBelowNcasError((int) $id, (float) ($fields['amount'] ?? 0), (float) ($existing['amount'] ?? 0));
                    if ($below !== null) {
                        return ['success' => false, 'message' => $below];
                    }
                }
            }

            $id = (int) $id;

            if (!empty($fields)) {
                $setParts = [];
                foreach ($fields as $key => $value) {
                    $col = App\QuotedName($key, 'DB');
                    $setParts[] = "{$col} = ".$this->quoteValue($key, $value);
                }
                $setParts[] = 'updated_at = CURRENT_TIMESTAMP';

                $setClause = implode(', ', $setParts);

                $sql = "UPDATE {$this->table} SET {$setClause} WHERE id = {$id} AND deleted_at IS NULL";
                App\Execute($sql, 'DB');

                // the SARO's saved Unreleased follows: this record when it is a SARO (its amount may have changed) and the SARO this record
                // was released under and the one it is released under now (a different amount, SARO or type of an NCA)
                $this->refreshSaroUnreleased($id, $existing['parent_id'] ?? null, array_key_exists('parent_id', $fields) ? $fields['parent_id'] : ($existing['parent_id'] ?? null));
            }

            if ($supplemental !== null) {
                try {
                    $this->saveSupplementalDetails($id, $supplemental, $userId);
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Fund tracking record was updated, but its supplemental details could not be saved: '.$e->getMessage(),
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'Fund tracking record updated successfully.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Delete — /fund_tracking/delete/{id}
    // Soft delete (stamps deleted_at). Blocked while other, non-deleted records
    // still have this one as parent_id — fims.fund_tracking has no FOREIGN KEY
    // to stop that, and the children would be left pointing at a hidden parent.
    // -----------------------------------------------------------------------
    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $id = (int) $id;

            $children = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE parent_id = {$id} AND deleted_at IS NULL",
                'DB'
            );
            if ($children > 0) {
                return [
                    'success' => false,
                    'message' => "Fund tracking record {$id} cannot be deleted: {$children} other record(s) still have it as their parent. Delete or re-parent them first.",
                    'httpCode' => 409,
                ];
            }

            App\Execute(
                "UPDATE {$this->table}
                    SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                  WHERE id = {$id} AND deleted_at IS NULL",
                'DB'
            );

            // a deleted NCA no longer counts: the SARO it was released under gets that amount back
            $this->refreshSaroUnreleased($check['data']['parent_id'] ?? null);

            return [
                'success' => true,
                'message' => "Fund tracking record with ID {$id} deleted successfully.",
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Strips the bank code prefix from an imported "BANK ACCOUNT NUMBER"
    // value (e.g. "LBP#0702-1068-34") down to just the account number
    // itself (e.g. "0702-1068-34") — only the part after the last "#" is
    // kept. Values with no "#" are kept as-is (trimmed).
    // -----------------------------------------------------------------------
    private function extractBankAccountNo(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $raw = trim($raw);
        $hashPos = strrpos($raw, '#');
        if ($hashPos !== false) {
            $raw = trim(substr($raw, $hashPos + 1));
        }

        return $raw !== '' ? $raw : null;
    }

    // -----------------------------------------------------------------------
    // Bulk import — /fund_tracking/import
    //
    // Each Excel row can produce up to TWO fims.fund_tracking records — one
    // for the SARO side of the row, one for the NCA side.
    //
    // The imports do not fill sub_program / unreleased: a sub_program / program / unreleased_saro / unreleased_nca column in the
    // sheet is simply not read.
    //
    // Row keys expected (from the client-side column mapping page):
    //   fiscal_year                 — calendar year ("YEAR OF GAA"), stored as is.
    //                                 May be left blank on continuation rows.
    //   nature_of_fund               — optional, name or id, resolved against
    //                                 fims.nature_of_fund.
    //   saro_reference_number        — "SARO" column → SARO record's
    //                                 saro_id.
    //   saro_name                    — "SARO ID (Coding) + Program" column →
    //                                 SARO record's description.
    //   saro_date                    — "Date of SARO" → SARO record's date (a row
    //                                 without one is dated the day it is imported).
    //   amount                       — "Amount" → SARO record's amount.
    //   nca_release_reference        — "NCA Released (Reference)" → NCA
    //                                 record's saro_id AND description
    //                                 (NCA type reuses this as its description).
    //   nca_amount                   — "NCA Amount" → NCA record's amount.
    //   nca_date                     — "NCA Date" → NCA record's date (same rule).
    //   bank_account_no              — NCA record only, see extractBankAccountNo().
    //   saro_multiple_node_id / nca_multiple_node_id — each record's own
    //                                 multiple_node_id (attachments).
    //
    // tracking_type_id for each record is resolved against
    // fims.fund_tracking_type by code: 'SARO' for the SARO record, 'NCA'
    // for the NCA record.
    //
    // The SARO record is inserted first; if an NCA record is also produced
    // for the same row, its parent_id is set to the SARO record's newly
    // inserted id.
    //
    // A row produces a SARO record only if at least one SARO-IDENTIFYING
    // column (saro_reference_number / saro_name / saro_date) is present, or
    // amount is present and genuinely non-zero. Likewise a row produces an
    // NCA record only if at least one NCA-identifying column
    // (nca_release_reference / nca_date) is present, or nca_amount is
    // present and genuinely non-zero. A row with neither side populated is
    // skipped.
    //
    // CARRY-FORWARD PARENT: when a row has NCA data but its own SARO
    // columns are blank, its parent_id is set to the most recently
    // inserted SARO record from an earlier row in the same import batch
    // (i.e. the nearest SARO row above it in the sheet) rather than being
    // left null. This tracks a running "current SARO id" as rows are
    // processed top-to-bottom, and only resets when a row supplies its own
    // new SARO.
    //
    // FISCAL YEAR / NATURE OF FUND INHERITANCE: a SARO's follow-on NCA release
    // rows in the source sheet are typically continuation rows — they leave
    // YEAR OF GAA blank because they belong to the SARO block above them. When
    // a row's own value is blank:
    //   1. If the row is an NCA-only row with a parent SARO to inherit from
    //      (per CARRY-FORWARD PARENT above), it uses THAT SARO's own
    //      fiscal_year / nature of fund specifically — not just whatever was
    //      resolved most recently elsewhere in the batch.
    //   2. Only if there's no parent SARO to inherit from at all does it fall
    //      back to the last value resolved from any earlier row.
    //
    // Duplicate detection: saro_id already existing (non-deleted) in
    // fims.fund_tracking causes that specific record (SARO or NCA) to be
    // skipped as a duplicate — the other side of the row is unaffected.
    //
    // $userId — the verified (JWT) user, stored in each record's user_id.
    // -----------------------------------------------------------------------
    public function importFromExcel(array $rows, ?int $userId = null): array
    {
        $imported = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = [];
        $rowResults = []; // per-row: ['row'=>N,'data'=>[],'status'=>'ok'|'error'|'duplicate'|'skipped','messages'=>[]]

        // Resolve the two tracking types once per import batch.
        $saroTypeId = $this->resolveTrackingTypeId('SARO');
        $ncaTypeId = $this->resolveTrackingTypeId('NCA');

        // Tracks the id of the most recently inserted SARO record, so
        // NCA-only rows (blank SARO columns) can still be attached to the
        // correct parent SARO above them in the sheet — along with THAT
        // SARO's own fiscal year / nature of fund, so a continuation row
        // inherits its specific parent's values rather than whichever was
        // merely resolved most recently.
        $currentSaroId = null;
        $currentSaroFiscalYear = null;
        $currentSaroNatureOfFundId = null;

        // Secondary fallback only — used when a row has no value of its own
        // AND no parent SARO to inherit from at all (e.g. an NCA-only row
        // appears before any SARO exists in this batch).
        $lastFiscalYear = null;
        $lastNatureOfFundId = null;

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;

            // Trim all string values
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);

            // Skip truly empty rows silently
            $nonEmpty = array_filter($row, fn ($v) => $v !== '' && $v !== null);
            if (empty($nonEmpty)) {
                ++$skipped;
                continue;
            }

            $rowMessages = [];

            // ── Does this row have a SARO side / an NCA side? ───────────────
            // Determined up front since whether this row can inherit its
            // parent SARO's fiscal year / nature of fund depends on whether
            // it's an NCA-only continuation row. Only the
            // identifying/primary columns decide this:
            //   SARO → SARO (reference), SARO ID (Coding)+Program, Amount, Date of SARO
            //   NCA  → NCA Released (Reference), NCA Amount, NCA Date
            // A bare "0" in amount/nca_amount alone (with no
            // reference/name/date) does not count as real data either.
            $hasSaro = !empty($row['saro_reference_number']) || !empty($row['saro_name'])
                || !empty($row['saro_date'])
                || (isset($row['amount']) && $row['amount'] !== '' && $row['amount'] !== null && (float) $row['amount'] != 0);

            $hasNca = !empty($row['nca_release_reference'])
                || !empty($row['nca_date'])
                || (isset($row['nca_amount']) && $row['nca_amount'] !== '' && $row['nca_amount'] !== null && (float) $row['nca_amount'] != 0);

            if (!$hasSaro && !$hasNca) {
                $msg = "Row {$rowNum}: No SARO or NCA data found — row skipped.";
                $errors[] = $msg;
                $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'skipped', 'messages' => [$msg]];
                ++$skipped;
                continue;
            }

            // ── fiscal_year (calendar year) — blank on a continuation row: the parent SARO's, else the last one resolved ─
            $fiscalYear = null;

            if (isset($row['fiscal_year']) && $row['fiscal_year'] !== '' && $row['fiscal_year'] !== null) {
                $resolved = $this->normalizeFiscalYear($row['fiscal_year']);
                if ($resolved === null) {
                    $msg = "Row {$rowNum}: fiscal_year '{$row['fiscal_year']}' is not a valid year. Row will be imported without fiscal_year.";
                    $errors[] = $msg;
                    $rowMessages[] = $msg;
                } else {
                    $fiscalYear = $resolved;
                    $lastFiscalYear = $resolved;
                }
            } elseif (!$hasSaro && $currentSaroFiscalYear) {
                $fiscalYear = $currentSaroFiscalYear;
            } else {
                $fiscalYear = $lastFiscalYear;
            }

            // ── Resolve nature_of_fund (text) → nature_of_fund_id ───────────
            // Optional and shared by both sides of the row, same carry-forward
            // priority as fiscal_year. resolveNatureOfFundId() does
            // NOT auto-create — a name with no match just logs a soft warning
            // and the row imports without nature_of_fund_id.
            $natureOfFundId = null;

            if (!empty($row['nature_of_fund'])) {
                $resolved = $this->resolveNatureOfFundId($row['nature_of_fund']);
                if (!$resolved) {
                    $msg = "Row {$rowNum}: Nature of Fund '{$row['nature_of_fund']}' not found in {$this->tableNatureOfFund}. Row will be imported without nature_of_fund_id.";
                    $errors[] = $msg;
                    $rowMessages[] = $msg;
                } else {
                    $natureOfFundId = $resolved;
                    $lastNatureOfFundId = $resolved;
                }
            } elseif (!$hasSaro && $currentSaroNatureOfFundId) {
                $natureOfFundId = $currentSaroNatureOfFundId;
            } else {
                $natureOfFundId = $lastNatureOfFundId;
            }

            if (!$saroTypeId) {
                $msg = "Row {$rowNum}: Tracking type 'SARO' not found in {$this->tableTrackingTypes} — SARO record(s) will be imported without tracking_type_id.";
                if (!in_array($msg, $errors, true)) {
                    $errors[] = $msg;
                }
                $rowMessages[] = $msg;
            }
            if (!$ncaTypeId) {
                $msg = "Row {$rowNum}: Tracking type 'NCA' not found in {$this->tableTrackingTypes} — NCA record(s) will be imported without tracking_type_id.";
                if (!in_array($msg, $errors, true)) {
                    $errors[] = $msg;
                }
                $rowMessages[] = $msg;
            }

            $saroInsertedId = null;

            // ── SARO record ──────────────────────────────────────────────
            if ($hasSaro) {
                $result = $this->insertTrackingRecord([
                    'fiscal_year' => $fiscalYear,
                    'tracking_type_id' => $saroTypeId,
                    'saro_id' => $row['saro_reference_number'] ?? null,
                    'description' => $row['saro_name'] ?? null,
                    'date' => $row['saro_date'] ?? null,
                    'amount' => $row['amount'] ?? null,
                    'parent_id' => null,
                    'multiple_node_id' => $row['saro_multiple_node_id'] ?? null,
                    'nature_of_fund_id' => $natureOfFundId,
                    'user_id' => $userId,
                ]);

                if ($result['status'] === 'duplicate') {
                    $msg = "Row {$rowNum} (SARO): Reference '{$row['saro_reference_number']}' already exists — skipped as duplicate.";
                    $errors[] = $msg;
                    $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'duplicate', 'messages' => array_merge($rowMessages, [$msg])];
                    ++$duplicates;
                    ++$skipped;
                } elseif ($result['status'] === 'error') {
                    $msg = "Row {$rowNum} (SARO): DB error — {$result['message']}";
                    $errors[] = $msg;
                    $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'error', 'messages' => array_merge($rowMessages, [$msg])];
                    ++$skipped;
                } elseif ($result['status'] === 'ok') {
                    $saroInsertedId = $result['id'];
                    // This row supplied its own new SARO — it becomes the
                    // running "current SARO" (and ITS fiscal year / nature
                    // of fund) for any NCA-only rows below it.
                    $currentSaroId = $saroInsertedId;
                    $currentSaroFiscalYear = $fiscalYear;
                    $currentSaroNatureOfFundId = $natureOfFundId;
                    ++$imported;
                    $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'ok', 'messages' => $rowMessages];
                }
            }

            // ── NCA record — parent_id points to this row's own SARO if it
            // has one, otherwise carries forward to the nearest SARO above ─
            if ($hasNca) {
                $ncaParentId = $hasSaro ? $saroInsertedId : $currentSaroId;

                if (!$hasSaro && !$currentSaroId) {
                    $msg = "Row {$rowNum} (NCA): No SARO found on this row or any prior row in this import — imported without parent_id.";
                    $errors[] = $msg;
                    $rowMessages[] = $msg;
                }

                $result = $this->insertTrackingRecord([
                    'fiscal_year' => $fiscalYear,
                    'tracking_type_id' => $ncaTypeId,
                    'saro_id' => $row['nca_release_reference'] ?? null,
                    'description' => $row['nca_release_reference'] ?? null,
                    'date' => $row['nca_date'] ?? null,
                    'amount' => $row['nca_amount'] ?? null,
                    'parent_id' => $ncaParentId,
                    'multiple_node_id' => $row['nca_multiple_node_id'] ?? null,
                    'bank_account_no' => $this->extractBankAccountNo($row['bank_account_no'] ?? null),
                    'nature_of_fund_id' => $natureOfFundId,
                    'user_id' => $userId,
                ]);

                if ($result['status'] === 'duplicate') {
                    $msg = "Row {$rowNum} (NCA): Reference '{$row['nca_release_reference']}' already exists — skipped as duplicate.";
                    $errors[] = $msg;
                    $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'duplicate', 'messages' => array_merge($rowMessages, [$msg])];
                    ++$duplicates;
                    ++$skipped;
                } elseif ($result['status'] === 'error') {
                    $msg = "Row {$rowNum} (NCA): DB error — {$result['message']}";
                    $errors[] = $msg;
                    $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'error', 'messages' => array_merge($rowMessages, [$msg])];
                    ++$skipped;
                } elseif ($result['status'] === 'ok') {
                    ++$imported;
                    $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'ok', 'messages' => $rowMessages];
                }
            }
        }

        return [
            'success' => $imported > 0 || empty($errors),
            'message' => "{$imported} record(s) imported, {$duplicates} duplicate(s), {$skipped} skipped.",
            'imported' => $imported,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'errors' => $errors,
            'row_results' => $rowResults,
        ];
    }

    // -----------------------------------------------------------------------
    // Insert a single fims.fund_tracking record for the SARO/NCA importer.
    // Accepts an assoc array keyed by destination column name — any
    // missing/blank/null value is simply omitted from the INSERT (see
    // insertRow()).
    //
    // Duplicate check: if saro_id is provided and already exists
    // (case-insensitive, among non-deleted rows) in fims.fund_tracking,
    // returns status 'duplicate' without inserting.
    //
    // Returns: ['status' => 'ok'|'duplicate'|'error', 'id' => int|null,
    //           'message' => string|null]
    // -----------------------------------------------------------------------
    private function insertTrackingRecord(array $fields): array
    {
        if (!empty($fields['saro_id'])) {
            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table}
                  WHERE UPPER(saro_id) = UPPER(".$this->qs($fields['saro_id']).')
                    AND deleted_at IS NULL',
                'DB'
            );
            if ($exists > 0) {
                return ['status' => 'duplicate', 'id' => null, 'message' => null];
            }
        }

        return $this->insertRow($fields);
    }

    // -----------------------------------------------------------------------
    // Bulk import — /fund_tracking/import-trust-fund
    //
    // Trust Fund Registry Excel import — unlike importFromExcel() (SARO/NCA),
    // every non-blank row produces exactly ONE fims.fund_tracking record, with
    // tracking_type_id always resolved to 'TRUST'
    // (fims.fund_tracking_type.fund_tracking_code) — never part of the column
    // mapping since it's implied by this being the Trust Fund import.
    //
    // Row keys expected (from the client-side column mapping page):
    //   agency           — "AGENCY/PRIVATE ENTITIES" column, free text.
    //                       Matched (case-insensitive) against fims.agency.name
    //                       — auto-created if there is no match, see
    //                       resolveAgencyName() — and the agency's name is
    //                       stored in fund_tracking.agency_name. May be left
    //                       blank on continuation rows (see CARRY-FORWARD
    //                       below).
    //   reference_number — "Saro No./OR No./CA No." column. REQUIRED to
    //                       already exist as a saro_id somewhere in
    //                       fims.fund_tracking (typically an already-imported
    //                       SARO/NCA/OR record) — resolveParentFundTrackingId()
    //                       looks it up and its id becomes this new TRUST
    //                       record's parent_id. A Trust Fund entry only ever
    //                       exists as a release against an already-recorded
    //                       SARO/OR/CA, so a saro_id with NO match
    //                       (including a blank one) means the row is skipped —
    //                       it is never created without a parent link.
    //   amount           — "AMOUNT" column → amount.
    //   date             — "DATE OF Saro No./OR No./CA No." column → date.
    //                       Always taken directly from this column (already
    //                       present in the source sheet for every row) —
    //                       NOT inherited from the matched parent record's own
    //                       date. A row without one is dated the day it is
    //                       imported.
    //   remarks          — "REMARKS" column → remarks (free text, e.g. notes
    //                       on returned funds).
    //
    // CARRY-FORWARD: the source registry groups multiple Saro/OR/CA releases
    // under one agency header row, leaving AGENCY blank on every following row
    // in that group. When a row's own "agency" is blank, it inherits the most
    // recently resolved agency name from an earlier row in this same import
    // batch, exactly like importFromExcel()'s fiscal-year inheritance. (A
    // sub_program / program column in the sheet is not read: a record has no
    // sub-program any more.)
    //
    // A row is skipped if it's fully blank, or if its saro_id has no
    // match in fims.fund_tracking (see saro_id above — required, not
    // best-effort). Failing to
    // resolve/auto-create an agency is a soft warning only — the row still
    // imports without agency_name.
    //
    // Duplicate detection: scoped to TRUST records only — a saro_id
    // already used by an existing TRUST row is skipped as a duplicate (guards
    // against re-running the same import twice). A saro_id already
    // used by a non-TRUST row (e.g. the SARO/OR record it's meant to link to
    // via parent_id) is expected and does NOT count as a duplicate.
    //
    // $userId — the verified (JWT) user, stored in each record's user_id.
    // -----------------------------------------------------------------------
    public function importTrustFundFromExcel(array $rows, ?int $userId = null): array
    {
        $imported = 0;
        $skipped = 0;
        $duplicates = 0;
        $errors = [];
        $rowResults = [];

        $trustTypeId = $this->resolveTrackingTypeId('TRUST');

        $lastAgencyName = null;

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;

            // Trim all string values
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);

            // Skip truly empty rows silently
            $nonEmpty = array_filter($row, fn ($v) => $v !== '' && $v !== null);
            if (empty($nonEmpty)) {
                ++$skipped;
                continue;
            }

            $rowMessages = [];

            if (!$trustTypeId) {
                $msg = "Row {$rowNum}: Tracking type 'TRUST' not found in {$this->tableTrackingTypes} — record will be imported without tracking_type_id.";
                if (!in_array($msg, $errors, true)) {
                    $errors[] = $msg;
                }
                $rowMessages[] = $msg;
            }

            // ── Resolve agency (text) → agency name — carry forward when blank ─
            $agencyName = null;
            if (!empty($row['agency'])) {
                $resolved = $this->resolveAgencyName((string) $row['agency']);
                if ($resolved === null) {
                    $msg = "Row {$rowNum}: Agency '{$row['agency']}' could not be resolved or auto-created in {$this->tableAgency}. Row will be imported without agency_name.";
                    $errors[] = $msg;
                    $rowMessages[] = $msg;
                } else {
                    $agencyName = $resolved;
                    $lastAgencyName = $resolved;
                }
            } else {
                $agencyName = $lastAgencyName;
            }

            // ── Required parent link via saro_id match ─────────────
            // A Trust Fund row only exists as a release against an
            // already-recorded SARO/NCA/OR reference — no match means no
            // record is created (see this method's doc comment).
            $parentId = $this->resolveParentFundTrackingId($row['reference_number'] ?? null);

            if (!$parentId) {
                $refDisplay = $row['reference_number'] ?? '';
                $msg = $refDisplay !== ''
                    ? "Row {$rowNum}: Reference '{$refDisplay}' was not found in {$this->table} — a Trust Fund record requires an existing SARO/NCA/OR reference to link to, row skipped."
                    : "Row {$rowNum}: Saro No./OR No./CA No. is missing/blank — a Trust Fund record requires an existing SARO/NCA/OR reference to link to, row skipped.";
                $errors[] = $msg;
                $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'skipped', 'messages' => array_merge($rowMessages, [$msg])];
                ++$skipped;
                continue;
            }

            $result = $this->insertTrustFundRecord([
                'tracking_type_id' => $trustTypeId,
                'saro_id' => $row['reference_number'] ?? null,
                'date' => $row['date'] ?? null,
                'amount' => $row['amount'] ?? null,
                'parent_id' => $parentId,
                'agency_name' => $agencyName,
                'remarks' => $row['remarks'] ?? null,
                'user_id' => $userId,
            ]);

            if ($result['status'] === 'duplicate') {
                $msg = "Row {$rowNum}: Reference '{$row['reference_number']}' already exists as a Trust Fund record — skipped as duplicate.";
                $errors[] = $msg;
                $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'duplicate', 'messages' => array_merge($rowMessages, [$msg])];
                ++$duplicates;
                ++$skipped;
            } elseif ($result['status'] === 'error') {
                $msg = "Row {$rowNum}: DB error — {$result['message']}";
                $errors[] = $msg;
                $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'error', 'messages' => array_merge($rowMessages, [$msg])];
                ++$skipped;
            } else {
                ++$imported;
                $rowResults[] = ['row' => $rowNum, 'data' => $row, 'status' => 'ok', 'messages' => $rowMessages];
            }
        }

        return [
            'success' => $imported > 0 || empty($errors),
            'message' => "{$imported} record(s) imported, {$duplicates} duplicate(s), {$skipped} skipped.",
            'imported' => $imported,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
            'errors' => $errors,
            'row_results' => $rowResults,
        ];
    }

    // -----------------------------------------------------------------------
    // Insert a single fims.fund_tracking TRUST record for the Trust Fund
    // importer. Same as insertTrackingRecord() but with its own duplicate check
    // (scoped to TRUST-type rows only — see importTrustFundFromExcel()'s doc
    // comment).
    //
    // Returns: ['status' => 'ok'|'duplicate'|'error', 'id' => int|null,
    //           'message' => string|null]
    // -----------------------------------------------------------------------
    private function insertTrustFundRecord(array $fields): array
    {
        if (!empty($fields['saro_id']) && !empty($fields['tracking_type_id'])) {
            $trustTypeId = (int) $fields['tracking_type_id'];
            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table}
                  WHERE UPPER(saro_id) = UPPER(".$this->qs($fields['saro_id']).")
                    AND tracking_type_id = {$trustTypeId}
                    AND deleted_at IS NULL",
                'DB'
            );
            if ($exists > 0) {
                return ['status' => 'duplicate', 'id' => null, 'message' => null];
            }
        }

        return $this->insertRow($fields);
    }

    // -----------------------------------------------------------------------
    // INSERT one fims.fund_tracking row from an assoc array keyed by column
    // name (the writable columns plus user_id and the importers' date). A
    // missing / null / blank value is simply left out of the INSERT, an id that
    // isn't a positive number and an amount that isn't numeric too — the
    // importers pass whatever the sheet had, and a bad cell must not abort the
    // row. A row without a date (every create()'s child record, an import row
    // whose sheet had none) is dated CURRENT_DATE — the day it is created.
    //
    // Returns: ['status' => 'ok'|'error', 'id' => int|null, 'message' => string|null]
    // -----------------------------------------------------------------------
    private function insertRow(array $fields): array
    {
        $columns = [];
        $values = [];

        foreach (array_merge($this->allowedFields, ['user_id']) as $key) {   // `date` is already one of the allowed fields
            if (!array_key_exists($key, $fields)) {
                continue;
            }

            $value = $fields[$key];
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            if (in_array($key, $this->intFields, true) && (!is_numeric($value) || (int) $value <= 0)) {
                continue;
            }

            if (in_array($key, $this->decimalFields, true) && !is_numeric($value)) {
                continue;
            }

            $columns[] = App\QuotedName($key, 'DB');
            $values[] = $this->quoteValue($key, $value);
        }

        if (count($columns) < 1) {
            return ['status' => 'error', 'id' => null, 'message' => 'No valid fields to insert.'];
        }

        if (!in_array(App\QuotedName('date', 'DB'), $columns, true)) {
            $columns[] = App\QuotedName('date', 'DB');
            $values[] = 'CURRENT_DATE';
        }

        $colList = implode(', ', $columns);
        $valList = implode(', ', $values);

        try {
            $inserted = App\ExecuteRow(
                "INSERT INTO {$this->table} ({$colList}) VALUES ({$valList}) RETURNING id",
                'DB'
            );

            $newId = !empty($inserted['id']) ? (int) $inserted['id'] : null;
            // a SARO saves its balance; an NCA refreshes the balance of the SARO it is released under
            $this->refreshSaroUnreleased($newId, $fields['parent_id'] ?? null);

            return [
                'status' => 'ok',
                'id' => $newId,
                'message' => null,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'id' => null, 'message' => $e->getMessage()];
        }
    }
}
