<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * IAR Module — INFRASTRUCTURE tab. ONE table (see _assets/sql/01 - fims/01 - table/50 - fims_statement_work_accomplishments.sql):
 *   fims.statement_work_accomplishments   one row per approved Infrastructure RFA that has no purchase order.
 *
 * The RFA is picked from the Budget Allocation: allocation_type = 'RFA', status Approved, category Infrastructure, empty po_number,
 * no active Disbursement Voucher, and not yet recorded here. Picking it loads the project's details (project, location, program,
 * contractor = the RFA's vendor, contract cost = the amount approved on the RFA, project cost = the original cost of the project).
 * The SWA number is generated on save; the user adds the dates (started / completion (revised) / contract duration), the account entries (pre-filled from
 * the RFA) and the Statement of Work Accomplishments (description, cost, % accomplishment).
 *
 * Save payload:
 *   { allocation_id, location?, contract?, date_started?, completion_date_revised?, contract_duration?,
 *     entries: [ { account_code, amount } ],                 // the total may not exceed the contract cost
 *     items:   [ { description, cost, accomplishment_pct } ] }   // at least one
 * Edit: only location, project cost, the dates, the contract duration and the Statement of Work items can change.
 *
 * Overall accomplishment = sum(cost x %) / sum(cost) (a plain average of the % when every cost is 0).
 */
class InfraIarService
{
    private string $t = 'fims.statement_work_accomplishments';

    private PoDrService $po;

    public function __construct()
    {
        $this->po = new PoDrService();   // reused: the allocation lines SQL (both budget_allocation layouts) and the active-DV test
    }

    private function q($value): string
    {
        return App\QuotedValue($value, DataType::STRING, 'DB');
    }

    private function nullable($value): ?string
    {
        $v = trim((string) ($value ?? ''));

        return $v === '' ? null : $v;
    }

    private function sqlOrNull(?string $value): string
    {
        return $value === null ? 'NULL' : $this->q($value);
    }

    private function validDate(string $d): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $d);

        return $dt && $dt->format('Y-m-d') === $d;
    }

    /** Number text ("1,500.5") → "1500.50", or null when invalid / outside [0, $max]. Blank = "0.00". */
    private function num($v, float $max = 1e14): ?string
    {
        $s = str_replace(',', '', trim((string) ($v ?? '')));
        if ($s === '') {
            return '0.00';
        }
        if (!is_numeric($s) || (float) $s < 0 || (float) $s >= $max) {
            return null;
        }

        return number_format((float) $s, 2, '.', '');
    }

    private function jsonSql(array $value): string
    {
        return $this->q(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '::jsonb';
    }

    private function arrayFrom($json): array
    {
        $a = is_string($json) ? json_decode($json, true) : $json;

        return is_array($a) ? array_values(array_filter($a, 'is_array')) : [];
    }

    /** Overall % accomplishment of the Statement of Work items: each item's % is its SHARE of the whole project, so the overall % is their sum (shown as is, even above 100, which a save refuses). */
    public static function overallPct(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $it) {
            $sum += (float) ($it['accomplishment_pct'] ?? 0);
        }

        return round($sum, 2);
    }

    /* ─────────────────────────── the RFA picker ─────────────────────────── */

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $k = "{$table}.{$column}";

        return $cache[$k] ??= (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'fims' AND table_name = " . $this->q($table) . ' AND column_name = ' . $this->q($column),
            'DB'
        ) > 0;
    }

    /** The first project block of the RFA (newer databases: fims.budget_allocation_project) or the allocation's own columns (older ones). */
    private function projectBlockSql(): string
    {
        static $blocks = null;
        $blocks ??= (int) App\ExecuteScalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'fims' AND table_name = 'budget_allocation_project'", 'DB') > 0;
        if ($blocks) {
            return "LEFT JOIN LATERAL (SELECT bap.program_code, bap.project_code FROM fims.budget_allocation_project bap
                                        WHERE bap.allocation_id = a.allocation_id AND bap.deleted_at IS NULL
                                        ORDER BY bap.sort_order, bap.budget_allocation_project_id LIMIT 1) blk ON TRUE";
        }

        return 'LEFT JOIN LATERAL (SELECT a.program_code, a.project_code) blk ON TRUE';
    }

    /**
     * The RFAs an infrastructure IAR can be recorded for: { allocation_id, rfa_number, fiscal_year, requesting_rc_id,
     * requesting_office, vendor_id, contractor, project_code, project_name, program_code, program_name, location, contract_cost,
     * project_cost, lines[ {account_code, account_title, amount} ] }.
     *   contract_cost = sum of the RFA's requested amounts (the amount approved)
     *   location / project_cost = from the budget line items the RFA draws on (budget_req_project.location, the sum of their
     *                       proposed amounts = the original cost); project_cost falls back to the contract cost
     * $exactId: one allocation (ignores the "can still be recorded" rules — used to read a recorded RFA); $search: ILIKE on the RFA
     * number / project / contractor.
     */
    private function rfaSource(?int $exactId = null, string $search = '', int $limit = 0, array $ids = []): array
    {
        $lines = $this->po->allocationLinesSql();
        $where = "UPPER(COALESCE(a.allocation_type, '')) = 'RFA' AND a.deleted_at IS NULL";
        if ($exactId !== null) {
            $where .= ' AND a.allocation_id = ' . (int) $exactId;
        } elseif ($ids) {   // several recorded RFAs (the list): no "can still be recorded" rules
            $where .= ' AND a.allocation_id IN (' . implode(',', array_map('intval', $ids)) . ')';
        } else {
            $where .= " AND COALESCE(TRIM(a.po_number), '') = ''
                        AND a.status_id = (SELECT st.id FROM fims.settings_status st WHERE st.name = 'Approved' AND st.status_for = 'budget_allocation' ORDER BY st.id LIMIT 1)
                        AND EXISTS (SELECT 1 FROM fims.budget_category bc WHERE bc.budget_category_id = a.category_id AND bc.deleted_at IS NULL AND LOWER(TRIM(bc.name)) = 'infrastructure')
                        AND NOT {$this->po->activeDvSql()}
                        AND NOT EXISTS (SELECT 1 FROM {$this->t} s WHERE s.allocation_id = a.allocation_id)";
        }
        if ($search !== '') {
            $s      = $this->q('%' . $search . '%');
            $where .= " AND (a.allocation_number ILIKE {$s} OR pr.project_name ILIKE {$s} OR v.vendor_name ILIKE {$s})";
        }
        $hasProposed = $this->hasColumn('budget_line_items', 'proposed_amount');
        $costSql     = $hasProposed ? 'SUM(bli.proposed_amount)' : 'NULL::numeric';
        $sugSql      = $this->hasColumn('budget_line_items', 'suggested_amount') ? 'SUM(bli.suggested_amount)' : 'NULL::numeric';
        // the line's Revision Amount: the signed net of its APPROVED Budget Revisions / Realignments (budget_line_items.adjustment, kept by the revision ledger's triggers)
        $adjSql      = $this->hasColumn('budget_line_items', 'adjustment') ? 'SUM(COALESCE(bli.adjustment, 0))' : 'NULL::numeric';
        $sql = "SELECT a.allocation_id, a.allocation_number AS rfa_number, a.fiscal_year, a.requesting_rc_id, rc.rc_name AS requesting_office,
                       a.vendor_id, v.vendor_name AS contractor,
                       blk.project_code, pr.project_name, COALESCE(NULLIF(blk.program_code, ''), pr.program_code) AS program_code, pg.program_name,
                       COALESCE(li.location, pl.location) AS location,
                       {$this->po->requestedTotalSql()} AS contract_cost,
                       li.project_cost, li.suggested_total, li.adjustment_total,
                       {$lines} AS allocation_lines
                FROM fims.budget_allocation a
                LEFT JOIN fims.responsibility_code rc ON rc.rc_id = a.requesting_rc_id
                LEFT JOIN fims.vendors v ON v.vendor_id = a.vendor_id
                {$this->projectBlockSql()}
                LEFT JOIN fims.project pr ON pr.project_code = blk.project_code
                LEFT JOIN fims.program pg ON pg.program_code = COALESCE(NULLIF(blk.program_code, ''), pr.program_code)
                LEFT JOIN LATERAL (
                    SELECT (array_agg(NULLIF(TRIM(brp.location), '')) FILTER (WHERE NULLIF(TRIM(brp.location), '') IS NOT NULL))[1] AS location,
                           {$costSql} AS project_cost, {$sugSql} AS suggested_total, {$adjSql} AS adjustment_total
                      FROM jsonb_array_elements({$lines}) ln(value)
                      JOIN fims.budget_line_items bli
                        ON bli.budget_line_item_id = (CASE WHEN ln.value ->> 'budget_line_item_id' ~ '^[0-9]+\$' THEN (ln.value ->> 'budget_line_item_id')::int END)
                      LEFT JOIN fims.budget_req_project brp ON brp.budget_req_project_id = bli.budget_req_project_id
                     WHERE jsonb_typeof(ln.value) = 'object'
                ) li ON TRUE
                LEFT JOIN LATERAL (
                    -- the project's location as entered on its budget request (budget_req_project.location), when the RFA's lines
                    -- carry no budget line item id: the same project code, preferring the RFA's fiscal year, then the latest
                    SELECT NULLIF(TRIM(brp.location), '') AS location
                      FROM fims.budget_req_project brp
                      JOIN fims.budget_req br ON br.budget_reference_number = brp.budget_reference_number
                     WHERE brp.project_code = blk.project_code AND NULLIF(TRIM(brp.location), '') IS NOT NULL
                     ORDER BY (br.fiscal_yr = a.fiscal_year) DESC, brp.budget_req_project_id DESC
                     LIMIT 1
                ) pl ON TRUE
                WHERE {$where}
                ORDER BY a.created_at DESC, a.allocation_id DESC" . ($limit > 0 ? ' LIMIT ' . (int) $limit : '');
        $rows = App\ExecuteRows($sql, 'DB') ?: [];
        foreach ($rows as &$r) {
            $r['lines']         = $this->po->allocationLines($r['allocation_lines']);
            $rfaAmount = round((float) $r['contract_cost'], 2);   // what the RFA requests (the account entries are loaded from it and may not exceed it)
            $sug = $r['suggested_total'] !== null ? round((float) $r['suggested_total'], 2) : 0.0;
            $pro = $r['project_cost'] !== null ? round((float) $r['project_cost'], 2) : 0.0;
            $orig = $sug > 0 ? $sug : ($pro > 0 ? $pro : null);   // the regional's original amount of the lines the RFA draws on
            if ($orig !== null) {
                $vo = $r['adjustment_total'] !== null ? round((float) $r['adjustment_total'], 2) : 0.0;   // only approved Budget Revisions / Realignments move it
                $r['original_contract_cost'] = $orig;
                $r['variation_order']        = $vo;
                $r['contract_cost']          = round($orig + $vo, 2);   // revised contract cost
            } else {                                                         // no budget line on file: nothing to compare with
                $r['original_contract_cost'] = $rfaAmount;
                $r['variation_order']        = 0.0;
                $r['contract_cost']          = $rfaAmount;
            }
            $r['rfa_amount'] = $rfaAmount;
            unset($r['project_cost'], $r['suggested_total'], $r['adjustment_total']);
            unset($r['allocation_lines']);
        }
        unset($r);

        return $rows;
    }

    /** Search for the RFA picker (only the RFAs that can still be recorded). */
    public function searchRfas(string $search = ''): array
    {
        try {
            return ['success' => true, 'data' => $this->rfaSource(null, trim($search), 30)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /* ─────────────────────────── validation ─────────────────────────── */

    /**
     * Validate + normalise the editable parts of a save payload. Returns [cleaned, errorMessage].
     * cleaned = { location, contract, date_started, completion_date_revised, contract_duration, items[] } — plus, when
     * $withEntries, allocation_id and entries[{account_code, account_title, amount}] (a new record).
     */
    private function clean(array $body, bool $withEntries): array
    {
        $out = [];
        if ($withEntries) {
            $aid = (int) ($body['allocation_id'] ?? 0);
            if ($aid <= 0) {
                return [null, 'Please select an RFA number.'];
            }
            $out['allocation_id'] = $aid;

            $raw = $body['entries'] ?? [];
            if (!is_array($raw) || count($raw) < 1) {
                return [null, 'Add at least one account row.'];
            }
            $entries = [];
            foreach (array_values($raw) as $i => $en) {
                $n    = $i + 1;
                $code = trim((string) ($en['account_code'] ?? ''));
                $amt  = $this->num($en['amount'] ?? null);
                if ($code === '') {
                    return [null, "Account row {$n}: please choose an account code."];
                }
                if ($amt === null) {
                    return [null, "Account row {$n}: amount must be a valid amount."];
                }
                if ((float) $amt <= 0) {
                    return [null, "Account row {$n}: enter an amount greater than 0."];
                }
                $entries[] = ['account_code' => $code, 'account_title' => '', 'amount' => $amt];
            }
            $in     = implode(',', array_map(fn($c) => $this->q($c), array_values(array_unique(array_column($entries, 'account_code')))));
            $titles = [];
            foreach (App\ExecuteRows("SELECT account_code, account_title FROM fims.chart_of_accounts WHERE account_code IN ({$in})", 'DB') ?: [] as $r) {
                $titles[$r['account_code']] = (string) $r['account_title'];
            }
            foreach ($entries as &$en) {
                if (!isset($titles[$en['account_code']])) {
                    return [null, "Account code '{$en['account_code']}' was not found in the chart of accounts."];
                }
                $en['account_title'] = $titles[$en['account_code']];
            }
            unset($en);
            $out['entries'] = $entries;
        }

        $loc = $this->nullable($body['location'] ?? null);
        if ($loc !== null && mb_strlen($loc) > 255) {
            return [null, 'Location must be 255 characters or fewer.'];
        }
        $con = $this->nullable($body['contract'] ?? null);   // the Contract: typed by the user
        if ($con !== null && mb_strlen($con) > 255) {
            return [null, 'Contract must be 255 characters or fewer.'];
        }
        $dur = $this->nullable($body['contract_duration'] ?? null);
        if ($dur !== null && mb_strlen($dur) > 100) {
            return [null, 'Contract duration must be 100 characters or fewer.'];
        }
        $start = $this->nullable($body['date_started'] ?? null);
        $end   = $this->nullable($body['completion_date_revised'] ?? null);
        if ($start !== null && !$this->validDate($start)) {
            return [null, 'Date started must be a valid date.'];
        }
        if ($end !== null && !$this->validDate($end)) {
            return [null, 'Completion date (revised) must be a valid date.'];
        }
        if ($start !== null && $end !== null && $end < $start) {
            return [null, 'The completion date (revised) cannot be before the date started.'];
        }
        $out += [
            'location' => $loc, 'contract' => $con, 'contract_duration' => $dur,
            'date_started' => $start, 'completion_date_revised' => $end,
        ];

        // Statement of Work Accomplishments: description, cost, % accomplishment — a line with nothing entered is skipped
        $items = [];
        foreach (array_values(is_array($body['items'] ?? null) ? $body['items'] : []) as $i => $it) {
            if (!is_array($it)) {
                continue;
            }
            $n    = $i + 1;
            $desc = $this->nullable($it['description'] ?? null);
            $blank = $desc === null && trim((string) ($it['cost'] ?? '')) === '' && trim((string) ($it['accomplishment_pct'] ?? '')) === '';
            if ($blank) {
                continue;
            }
            if ($desc === null) {
                return [null, "Work item {$n}: the description is required."];
            }
            $cost = $this->num($it['cost'] ?? null);
            if ($cost === null) {
                return [null, "Work item {$n}: cost must be a valid amount."];
            }
            $pct = $this->num($it['accomplishment_pct'] ?? null, 100.0000001);
            if ($pct === null) {
                return [null, "Work item {$n}: the % accomplishment must be between 0 and 100."];
            }
            $items[] = ['description' => $desc, 'cost' => $cost, 'accomplishment_pct' => $pct];
        }
        if (!$items) {
            return [null, 'Add at least one item to the Statement of Work Accomplishments.'];
        }
        $pctHundredths = 0;
        foreach ($items as $it) {
            $pctHundredths += (int) round((float) $it['accomplishment_pct'] * 100);
        }
        if ($pctHundredths > 10000) {
            return [null, 'The % Accomplishment of the items adds up to ' . number_format($pctHundredths / 100, 2) . '%, which may not exceed 100%.'];
        }
        $out['items'] = $items;

        return [$out, null];
    }

    /* ─────────────────────────── read ─────────────────────────── */

    private const COLS = "s.swa_id, s.swa_number, s.allocation_id, a.allocation_number AS rfa_number, a.vendor_id, s.contract,
                          TO_CHAR(s.date_started, 'YYYY-MM-DD') AS date_started, TO_CHAR(s.completion_date_revised, 'YYYY-MM-DD') AS completion_date_revised,
                          s.contract_duration, s.line_transaction, rc.rc_name AS requesting_office";

    /**
     * The RFA number, project, program, contractor and location are NOT kept on the record (the columns were dropped): they are read live from the RFA / project / program /
     * vendor / budget request (like the IAR reads its PO and supplier), so they are always accurate.
     */
    private function live(array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn($r) => (int) $r['allocation_id'], $rows))));
        if (!$ids) {
            return $rows;
        }
        $by = [];
        foreach ($this->rfaSource(null, '', 0, $ids) as $x) {
            $by[(int) $x['allocation_id']] = $x;
        }
        foreach ($rows as &$r) {
            $x = $by[(int) $r['allocation_id']] ?? null;
            foreach (['rfa_number', 'project_code', 'project_name', 'program_code', 'program_name', 'location', 'vendor_id', 'contractor'] as $k) {
                $r[$k] = $x[$k] ?? ($r[$k] ?? null);   // nothing is kept on the record: an RFA that no longer exists simply leaves them empty
            }
            // the cost figures are not kept either: original / variation / revised come from the RFA's budget line items, rfa_amount from its lines
            foreach (['original_contract_cost', 'variation_order', 'contract_cost', 'rfa_amount'] as $k) {
                $r[$k] = $x ? $x[$k] : 0.0;
            }
        }
        unset($r);

        return $rows;
    }

    private function shape(array $r): array
    {
        $tx    = $this->txOf($r['line_transaction']);
        $items = array_values(array_filter($tx['items'] ?? [], 'is_array'));
        $cost  = 0.0;
        foreach ($items as $it) {
            $cost += (float) ($it['cost'] ?? 0);
        }
        $r['entries']        = array_values(array_filter($tx['lines'] ?? [], 'is_array'));
        $r['items']          = $items;
        $r['items_cost']     = round($cost, 2);
        $r['accomplishment'] = self::overallPct($items);
        $r['jev_id']         = !empty($tx['jev_id']) ? (int) $tx['jev_id'] : null;
        $r['jev_no']         = !empty($tx['jev_no']) ? (string) $tx['jev_no'] : null;
        unset($r['line_transaction']);

        return $r;
    }

    /** The one element of a record's line_transaction: { items[], lines[] (the account entries), jev_id, jev_no } — the same shape as a DR in the IAR module. */
    private function txOf($json): array
    {
        $a = is_string($json) ? json_decode($json, true) : $json;

        return is_array($a) && isset($a[0]) && is_array($a[0]) ? $a[0] : [];
    }

    private function from(): string
    {
        return "FROM {$this->t} s
                LEFT JOIN fims.budget_allocation a ON a.allocation_id = s.allocation_id
                LEFT JOIN fims.responsibility_code rc ON rc.rc_id = a.requesting_rc_id
                {$this->projectBlockSql()}";
    }

    /** Paginated list: one row per infrastructure IAR. */
    public function fetchAlls(int $page = 1, int $limit = 10, string $search = ''): array
    {
        try {
            $where = '1=1';
            if ($search !== '') {
                $s     = $this->q('%' . $search . '%');
                $where = "(s.swa_number ILIKE {$s} OR a.allocation_number ILIKE {$s} OR EXISTS (SELECT 1 FROM jsonb_array_elements(COALESCE(s.line_transaction -> 0 -> 'items', '[]'::jsonb)) it WHERE it ->> 'description' ILIKE {$s})
                           OR EXISTS (SELECT 1 FROM fims.vendors v WHERE v.vendor_id = a.vendor_id AND v.vendor_name ILIKE {$s})
                           OR EXISTS (SELECT 1 FROM fims.project pr WHERE pr.project_code = blk.project_code AND (pr.project_code ILIKE {$s} OR pr.project_name ILIKE {$s}))
                           OR EXISTS (SELECT 1 FROM fims.budget_req_project brp WHERE brp.project_code = blk.project_code AND brp.location ILIKE {$s}))";
            }
            $limit  = max(1, $limit);
            $page   = max(1, $page);
            $offset = ($page - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->t} s LEFT JOIN fims.budget_allocation a ON a.allocation_id = s.allocation_id {$this->projectBlockSql()} WHERE {$where}", 'DB');
            $rows   = App\ExecuteRows('SELECT ' . self::COLS . " {$this->from()} WHERE {$where} ORDER BY s.swa_id DESC LIMIT {$limit} OFFSET {$offset}", 'DB') ?: [];

            return [
                'success'    => true,
                'data'       => array_map(fn($r) => $this->shape($r), $this->live($rows)),
                'pagination' => ['total' => $total, 'per_page' => $limit, 'current_page' => $page, 'last_page' => (int) ceil($total / $limit)],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchById(int $id): array
    {
        try {
            $rows = App\ExecuteRows('SELECT ' . self::COLS . " {$this->from()} WHERE s.swa_id = " . (int) $id, 'DB');

            return $rows ? ['success' => true, 'data' => $this->shape($this->live($rows)[0])] : ['success' => false, 'message' => 'Infrastructure IAR not found.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /* ─────────────────────────── write ─────────────────────────── */

    public function create(array $body, ?int $userId = null): array
    {
        try {
            [$d, $err] = $this->clean($body, true);
            if ($err) {
                return ['success' => false, 'message' => $err];
            }
            $aid = $d['allocation_id'];

            // the RFA must still be recordable: approved Infrastructure, no PO, no active DV, not recorded yet
            $rfa = null;
            foreach ($this->rfaSource(null, '', 0) as $r) {
                if ((int) $r['allocation_id'] === $aid) {
                    $rfa = $r;
                    break;
                }
            }
            if (!$rfa) {
                if ($ex = App\ExecuteRows("SELECT swa_number FROM {$this->t} WHERE allocation_id = {$aid}", 'DB')) {
                    return ['success' => false, 'message' => "This RFA already has an infrastructure record ({$ex[0]['swa_number']})."];
                }
                $dv = App\ExecuteRows("SELECT dv.dv_no FROM fims.budget_allocation a JOIN fims.disbursement_vouchers dv ON dv.dv_id = a.dv_id WHERE a.allocation_id = {$aid} AND {$this->po->activeDvSql()} LIMIT 1", 'DB');
                if ($dv) {
                    return ['success' => false, 'message' => 'A Disbursement Voucher (' . ($dv[0]['dv_no'] ?: 'DV') . ') was already processed for this RFA, so an infrastructure record can no longer be added to it.'];
                }

                return ['success' => false, 'message' => 'The RFA was not found among the approved Infrastructure RFAs without a PO. Please select an RFA from the list.'];
            }

            $totalCents = 0;
            foreach ($d['entries'] as $en) {
                $totalCents += (int) round((float) $en['amount'] * 100);
            }
            $itemCents = $this->itemsCents($d['items']);
            if ($itemCents > $totalCents) {
                return ['success' => false, 'message' => 'The total cost of the Statement of Work Accomplishments (' . number_format($itemCents / 100, 2) . ') may not exceed the total of the Account Entries (' . number_format($totalCents / 100, 2) . ').'];
            }
            if ($totalCents > (int) round($rfa['rfa_amount'] * 100)) {
                return ['success' => false, 'message' => 'The account entries total ' . number_format($totalCents / 100, 2) . ', which exceeds the RFA amount (' . number_format($rfa['rfa_amount'], 2) . ').'];
            }
            $d['swa_number'] = $this->nextSwaNumber();   // generated: SWA-YYYY-MM-0001 (the swa_number column holds the SWA number)

            $by = $userId ? (int) $userId : 'NULL';
            App\Execute(
                "INSERT INTO {$this->t} (swa_number, allocation_id,
                                         contract, date_started, completion_date_revised, contract_duration, line_transaction, created_by)
                 VALUES (" . implode(', ', [
                    $this->q($d['swa_number']), $aid,
                    $this->sqlOrNull($d['contract']), $this->sqlOrNull($d['date_started']), $this->sqlOrNull($d['completion_date_revised']),
                    $this->sqlOrNull($d['contract_duration']), $this->jsonSql([['items' => $d['items'], 'lines' => $d['entries'], 'jev_id' => null, 'jev_no' => null]]), $by,
                ]) . ')',
                'DB'
            );

            return ['success' => true, 'message' => "Infrastructure record {$d['swa_number']} added for RFA {$rfa['rfa_number']}."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** The total cost of the Statement of Work items, in cents. */
    private function itemsCents(array $items): int
    {
        $c = 0;
        foreach ($items as $it) {
            $c += (int) round((float) ($it['cost'] ?? 0) * 100);
        }

        return $c;
    }

    /** Next SWA number of the current month: SWA-YYYY-MM-0001, 0002, … (same pattern as the PO number). */
    public function nextSwaNumber(): string
    {
        $prefix = 'SWA-' . date('Y-m') . '-';
        $max    = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(swa_number FROM '[0-9]+\$') AS INTEGER)), 0)
             FROM {$this->t}
             WHERE swa_number LIKE " . $this->q($prefix . '%') . " AND swa_number ~ '-[0-9]+\$'",
            'DB'
        );

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** EDIT — only the contract, dates, contract duration and the Statement of Work items change; the SWA number, the RFA and the account entries are kept. */
    public function update(int $id, array $body, ?int $userId = null): array
    {
        try {
            $cur = App\ExecuteRows("SELECT swa_id, swa_number, allocation_id, line_transaction FROM {$this->t} WHERE swa_id = " . (int) $id, 'DB');
            if (!$cur) {
                return ['success' => false, 'message' => 'Infrastructure IAR not found.'];
            }
            [$d, $err] = $this->clean($body, false);   // the account entries are never changed here
            if ($err) {
                return ['success' => false, 'message' => $err];
            }
            $tx = $this->txOf($cur[0]['line_transaction']);
            $entryCents = 0;
            foreach ($tx['lines'] ?? [] as $en) {
                $entryCents += (int) round((float) ($en['amount'] ?? 0) * 100);
            }
            $itemCents = $this->itemsCents($d['items']);
            if ($itemCents > $entryCents) {
                return ['success' => false, 'message' => 'The total cost of the Statement of Work Accomplishments (' . number_format($itemCents / 100, 2) . ') may not exceed the total of the Account Entries (' . number_format($entryCents / 100, 2) . ').'];
            }
            $by = $userId ? (int) $userId : 'NULL';
            App\Execute(
                "UPDATE {$this->t} SET contract = " . $this->sqlOrNull($d['contract'])
                . ', date_started = ' . $this->sqlOrNull($d['date_started']) . ', completion_date_revised = ' . $this->sqlOrNull($d['completion_date_revised'])
                . ', contract_duration = ' . $this->sqlOrNull($d['contract_duration']) . ', line_transaction = ' . $this->jsonSql([['items' => $d['items'], 'lines' => $tx['lines'] ?? [], 'jev_id' => $tx['jev_id'] ?? null, 'jev_no' => $tx['jev_no'] ?? null]])
                . ", updated_by = {$by}, updated_at = CURRENT_TIMESTAMP WHERE swa_id = " . (int) $id,
                'DB'
            );

            return ['success' => true, 'message' => "Infrastructure record {$cur[0]['swa_number']} updated."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /* ─────────────────────────── pro-forma JEV ─────────────────────────── */

    /**
     * The pro-forma JEV of an infrastructure IAR: each account entry is a debit, and the total is credited to the contractor's
     * Accounts Payable sub-ledger — or, when the contractor has none, to the default "Account Payable 1" (2-01-01-010-01).
     * { id, swa_number, rfa_number, requesting_rc_id, project_name, contractor, rows[], total, jev_id, jev_no }.
     */
    public function proforma(int $id): array
    {
        try {
            $rows = App\ExecuteRows('SELECT ' . self::COLS . ", a.requesting_rc_id {$this->from()} WHERE s.swa_id = " . (int) $id, 'DB');
            if (!$rows) {
                return ['success' => false, 'message' => 'Infrastructure IAR not found.'];
            }
            $r = $this->shape($this->live($rows)[0]);
            [$code, $title] = $this->po->payableAccount((string) ($r['vendor_id'] ?? ''));
            $out   = [];
            $total = 0.0;
            foreach ($r['entries'] as $en) {
                $amt     = round((float) $en['amount'], 2);
                $total  += $amt;
                $out[]   = ['account_title' => $en['account_title'] ?? '', 'account_code' => $en['account_code'], 'debit' => $amt, 'credit' => 0];
            }
            $total = round($total, 2);
            $out[] = ['account_title' => $title, 'account_code' => $code, 'debit' => 0, 'credit' => $total];

            return ['success' => true, 'data' => [
                'id' => (int) $r['swa_id'], 'swa_number' => $r['swa_number'], 'rfa_number' => $r['rfa_number'], 'requesting_rc_id' => $rows[0]['requesting_rc_id'] ?? null,
                'project_code' => $r['project_code'], 'project_name' => $r['project_name'], 'location' => $r['location'], 'contractor' => $r['contractor'], 'rows' => $out, 'total' => $total,
                'jev_id' => $r['jev_id'], 'jev_no' => $r['jev_no'],
            ]];
        } catch (xception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** The JEV the record's pro-forma was submitted as ({jev_id, jev_no}), or null when it has not been submitted. */
    public function jevOf(int $id): ?array
    {
        $r = App\ExecuteRows("SELECT line_transaction FROM {$this->t} WHERE swa_id = " . (int) $id, 'DB');
        $tx = $r ? $this->txOf($r[0]['line_transaction']) : [];

        return !empty($tx['jev_id']) ? ['jev_id' => (int) $tx['jev_id'], 'jev_no' => (string) ($tx['jev_no'] ?? '')] : null;
    }

    /** Records the JEV its pro-forma was submitted as: the pro-forma cannot be submitted twice. */
    public function linkJev(int $id, int $jevId, string $jevNo): bool
    {
        // jev_id / jev_no are recorded inside the record's line_transaction (like the IAR records them on its DR)
        App\Execute(
            "UPDATE {$this->t} SET line_transaction = jsonb_set(jsonb_set(line_transaction, '{0,jev_id}', to_jsonb(" . (int) $jevId . "::int)), '{0,jev_no}', to_jsonb(" . $this->q($jevNo) . '::text)) WHERE swa_id = ' . (int) $id,
            'DB'
        );

        return true;
    }
}
