<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * IAR (Inspection and Acceptance Report) Module — ONE table
 * (see _assets/sql/01 - fims/01 - table/25 - fims_inspection_acceptance_report.sql):
 *   fims.inspection_acceptance_report   one row per PO: po_number, iar_number (typed by the user, unique), si_number, si_date and
 *                                       line_transaction (JSONB) = the PO's delivery receipts (DR):
 *   [ { dr_number, delivery_date, total_amount,
 *       lines: [ { account_code, account_title, amount } ],
 *       items?: [ { description, serial_no, amount, property_no } ] } ]   // items only for a property / equipment account row of >= 50,000
 *
 * The PO is picked from the Budget Allocation (the po_number of an RFA row — see poSource()). One save handles ONE DR. A DR is
 * identified inside its PO by its dr_number (unique within the PO); a serial number is unique across all DRs.
 * The DR total may not exceed the PO balance (PO amount - the totals of the PO's other DRs).
 *
 * Save payload:
 *   { po_number, iar_number, si_number?, si_date?,
 *     dr: { dr_ref? (edit: the dr_number the DR had when it was loaded), dr_number, delivery_date,
 *           entries: [ { account_code, amount } ],                           // account codes from fims.chart_of_accounts
 *           items?: [ { description?, serial_no?, amount?, property_no? } ] } }   // many items, one line each; required only for property / equipment account rows of >= 50,000 (needsItems())
 *
 * API shape of a delivery receipt (read / list):
 *   { dr_number, delivery_date, total_amount, entries: [ { account_code, account_title, amount } ],
 *     items: [ { description, serial_no, amount, property_no } ] }     // items is [] when none were added
 */
class PoDrService
{
    private string $h = 'fims.inspection_acceptance_report';   // po_number, iar_number, si_number, si_date, line_transaction

    /** An account row of this amount or more needs items (description required, serial, amount, property no. — one line each). */
    private const ITEM_MIN_AMOUNT = 50000;   // ...but only on a property / equipment account (PRIORITY_ACCOUNTS) — see needsItems()

    /** Property, plant & equipment accounts (the property record categories): offered first in the Account Code picker. */
    private const PRIORITY_ACCOUNTS = [
        '1-06-05-020', '1-06-05-030', '1-06-05-070', '1-06-05-080', '1-06-05-110',
        '1-06-05-130', '1-06-05-990', '1-06-06-010', '1-06-07-010', '1-06-99-990',
    ];

    /**
     * Does this account row need items? Only a row of ITEM_MIN_AMOUNT or more whose account is a property / equipment category
     * (PRIORITY_ACCOUNTS, or a sub-ledger of one, e.g. 1-06-05-020-01). Any other account — whatever the amount — has no item validation.
     */
    public static function needsItems(string $code, float $amount): bool
    {
        if ($amount < self::ITEM_MIN_AMOUNT) {
            return false;
        }
        foreach (self::PRIORITY_ACCOUNTS as $p) {
            if ($code === $p || str_starts_with($code, $p . '-')) {
                return true;
            }
        }

        return false;
    }

    /** Pro-forma JEV: the DR total is credited to Accounts Payable. */
    private const PAYABLE_ACCOUNT = '2-01-01-010';

    /** The default credit sub-ledger when the vendor has none: "Account Payable 1". */
    private const DEFAULT_PAYABLE_SL = '2-01-01-010-01';

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

    /** Money text ("1,500.50") → number with 2 decimals, or null when it isn't a valid non-negative amount. */
    private function money($v): ?string
    {
        $s = str_replace(',', '', trim((string) ($v ?? '')));
        if ($s === '') {
            return '0.00';
        }
        if (!is_numeric($s) || (float) $s < 0 || (float) $s >= 1e14) {
            return null;
        }

        return number_format((float) $s, 2, '.', '');
    }

    /** SQL literal for a JSONB value. */
    private function jsonSql(array $value): string
    {
        return $this->q(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '::jsonb';
    }

    /** The delivery receipts stored in line_transaction (a JSON string from the database, or an array). */
    private function drsFrom($json): array
    {
        $a = is_string($json) ? json_decode($json, true) : $json;

        return is_array($a) ? array_values(array_filter($a, 'is_array')) : [];
    }

    /** One stored delivery receipt → the API shape. */
    private function shape(array $d): array
    {
        return [
            'dr_number'     => (string) ($d['dr_number'] ?? ''),
            'delivery_date' => $d['delivery_date'] ?? null,
            'total_amount'  => $d['total_amount'] ?? '0.00',
            'entries'       => array_values(array_filter($d['lines'] ?? [], 'is_array')),
            'items'         => array_values(array_filter($d['items'] ?? [], 'is_array')),
            'jev_id'        => $d['jev_id'] ?? null,    // set once its pro-forma was submitted as a JEV
            'jev_no'        => $d['jev_no'] ?? null,
        ];
    }

    /** A cleaned DR → the element stored in line_transaction. */
    private function stored(array $dr): array
    {
        $out = [
            'dr_number'     => $dr['dr_number'],
            'delivery_date' => $dr['delivery_date'],
            'total_amount'  => $dr['total'],
            'lines'         => $dr['entries'],
        ];
        if ($dr['items']) {
            $out['items'] = $dr['items'];
        }

        return $out;
    }

    /**
     * Validate + normalise. Returns [po_number, dr[], errorMessage, header[]]:
     *   dr = { ref, dr_number, delivery_date, total, entries[{account_code, account_title, amount}], items[] }
     */
    private function clean(array $body, bool $withItems = true): array
    {
        $po = trim((string) ($body['po_number'] ?? ''));
        if ($po === '') {
            return [null, null, 'PO number is required.'];
        }
        if (mb_strlen($po) > 100) {
            return [null, null, 'PO number must be 100 characters or fewer.'];
        }

        $iar = trim((string) ($body['iar_number'] ?? ''));
        if ($iar === '') {
            return [null, null, 'IAR number is required.'];
        }
        if (mb_strlen($iar) > 50) {
            return [null, null, 'IAR number must be 50 characters or fewer.'];
        }

        // Sales Invoice # / date belong to the PO's IAR header
        $si  = $this->nullable($body['si_number'] ?? null);
        $sid = $this->nullable($body['si_date'] ?? null);
        if ($si !== null && mb_strlen($si) > 100) {
            return [null, null, 'Sales invoice no. must be 100 characters or fewer.'];
        }
        if ($sid !== null && !$this->validDate($sid)) {
            return [null, null, 'Sales invoice date must be a valid date.'];
        }

        $d = $body['dr'] ?? null;
        if (!is_array($d)) {
            return [null, null, 'Delivery receipt details are required.'];
        }
        $dr = trim((string) ($d['dr_number'] ?? ''));
        $dd = trim((string) ($d['delivery_date'] ?? ''));
        if ($dr === '') {
            return [null, null, 'Delivery receipt no. is required.'];
        }
        if (mb_strlen($dr) > 100) {
            return [null, null, 'Delivery receipt no. must be 100 characters or fewer.'];
        }
        if ($dd === '' || !$this->validDate($dd)) {
            return [null, null, 'Delivery date is required and must be a valid date.'];
        }

        // Account rows: an account code + an amount; the DR total is the sum of the amounts. There is no debit/credit to
        // balance here — the only limit is the PO balance (checked in drError()).
        $raw = $d['entries'] ?? [];
        if (!is_array($raw) || count($raw) < 1) {
            return [null, null, 'Add at least one account row.'];
        }
        $entries    = [];
        $totalCents = 0;   // in cents, so the sum is exact
        foreach (array_values($raw) as $i => $en) {
            $n    = $i + 1;
            $code = trim((string) ($en['account_code'] ?? ''));
            $amt  = $this->money($en['amount'] ?? null);
            if ($code === '') {
                return [null, null, "Row {$n}: please choose an account code."];
            }
            if ($amt === null) {
                return [null, null, "Row {$n}: amount must be a valid amount."];
            }
            if ((float) $amt <= 0) {
                return [null, null, "Row {$n}: enter an amount greater than 0."];
            }
            $totalCents += (int) round((float) $amt * 100);
            $entries[] = ['account_code' => $code, 'account_title' => '', 'amount' => $amt];
        }
        $total = number_format($totalCents / 100, 2, '.', '');

        // every account code must exist in the chart of accounts; its title is stored with the row
        $codes  = array_values(array_unique(array_column($entries, 'account_code')));
        $in     = implode(',', array_map(fn($c) => $this->q($c), $codes));
        $titles = [];
        foreach (App\ExecuteRows("SELECT account_code, account_title FROM fims.chart_of_accounts WHERE account_code IN ({$in})", 'DB') ?: [] as $r) {
            $titles[$r['account_code']] = (string) $r['account_title'];
        }
        // (a subsidiary ledger, e.g. 5-02-03-010-01, is a child row of its GL account in the chart of accounts, so it is found here too)
        foreach ($entries as &$en) {
            if (!isset($titles[$en['account_code']])) {
                return [null, null, "Account code '{$en['account_code']}' was not found in the chart of accounts."];
            }
            $en['account_title'] = $titles[$en['account_code']];
        }
        unset($en);

        // Only the account rows of ITEM_MIN_AMOUNT or more need items (an account row below it has none). The items must be
        // described (description required) and their amounts must add up to the total of those account rows
        // (e.g. rows of 50,000 + 300,000 → items totalling 350,000). A line with nothing entered is skipped; every serial no. must differ.
        $qualCents = 0;
        $needBy    = [];   // account code => cents of its account rows that need items
        foreach ($entries as $en) {
            if (self::needsItems($en['account_code'], (float) $en['amount'])) {
                $c          = (int) round((float) $en['amount'] * 100);
                $qualCents += $c;
                $needBy[$en['account_code']] = ($needBy[$en['account_code']] ?? 0) + $c;
            }
        }
        $items = [];
        if ($withItems && $qualCents > 0) {   // (an edit keeps the stored items, so they are not checked again)
            $itemCents = 0;
            $haveBy    = [];   // account code => cents of the items assigned to it (the item's category)
            $serials = [];
            foreach (array_values(is_array($d['items'] ?? null) ? $d['items'] : []) as $i => $it) {
                if (!is_array($it)) {
                    continue;
                }
                $n   = $i + 1;
                $row = [
                    'description' => $this->nullable($it['description'] ?? null),
                    'serial_no'   => $this->nullable($it['serial_no'] ?? null),
                    'amount'      => null,
                    'property_no' => $this->nullable($it['property_no'] ?? null),
                ];
                $acct = $this->nullable($it['account_code'] ?? null);   // the account row the item belongs to (kept to show it on the Property Records page)
                if ($acct !== null && isset($titles[$acct])) {
                    $row['account_code'] = $acct;
                }
                $amtRaw = trim((string) ($it['amount'] ?? ''));
                if ($amtRaw !== '') {
                    $amt = $this->money($amtRaw);
                    if ($amt === null) {
                        return [null, null, "Item {$n}: amount must be a valid amount."];
                    }
                    $row['amount'] = $amt;
                }
                foreach (['serial_no' => 'Serial no.', 'property_no' => 'Property no.'] as $k => $label) {
                    if ($row[$k] !== null && mb_strlen($row[$k]) > 100) {
                        return [null, null, "Item {$n}: {$label} must be 100 characters or fewer."];
                    }
                }
                $row = array_filter($row, fn($v) => $v !== null);   // only what was entered is stored
                if (!$row) {
                    continue;
                }
                if (!isset($row['description'])) {
                    return [null, null, "Item {$n}: description is required."];
                }
                if (!isset($row['amount'])) {
                    return [null, null, "Item {$n}: amount is required."];
                }
                $itemCents += (int) round((float) $row['amount'] * 100);
                if (isset($row['account_code'])) {
                    if (!isset($needBy[$row['account_code']])) {
                        return [null, null, "Item {$n}: category {$row['account_code']} is not a property / equipment account row of " . number_format(self::ITEM_MIN_AMOUNT, 2) . ' or more.'];
                    }
                    $haveBy[$row['account_code']] = ($haveBy[$row['account_code']] ?? 0) + (int) round((float) $row['amount'] * 100);
                }
                if (isset($row['serial_no'])) {
                    $key = mb_strtolower($row['serial_no']);
                    if (isset($serials[$key])) {
                        return [null, null, "Serial no. '{$row['serial_no']}' is entered more than once."];
                    }
                    $serials[$key] = true;
                }
                $items[] = $row;
            }
            // every item has a category: the items of each account must add up to that account's amount (a 600,000 account may be
            // split into several items); otherwise (no categories sent) only the overall total is compared
            if ($items && count(array_filter($items, fn($x) => isset($x['account_code']))) === count($items)) {
                foreach ($needBy as $code => $cents) {
                    if (($haveBy[$code] ?? 0) !== $cents) {
                        return [null, null, "The items of {$code} total " . number_format(($haveBy[$code] ?? 0) / 100, 2) . ' but must equal ' . number_format($cents / 100, 2) . '.'];
                    }
                }
            }
            if ($itemCents !== $qualCents) {
                return [null, null, 'The items total ' . number_format($itemCents / 100, 2) . ' but must equal ' . number_format($qualCents / 100, 2)
                    . ' (the property / equipment account rows of ' . number_format(self::ITEM_MIN_AMOUNT, 2) . ' or more).'];
            }
        }

        return [$po, [
            'ref'           => $this->nullable($d['dr_ref'] ?? null),
            'dr_number'     => $dr,
            'delivery_date' => $dd,
            'total'         => $total,
            'entries'       => $entries,
            'items'         => $items,
        ], null, ['iar_number' => $iar, 'si_number' => $si, 'si_date' => $sid]];
    }

    /** True when the IAR number already belongs to another PO's IAR ($exceptPo = that PO is not counted). */
    private function iarTaken(string $iar, ?string $exceptPo = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->h} WHERE iar_number = " . $this->q($iar);
        if ($exceptPo !== null) {
            $sql .= ' AND po_number <> ' . $this->q($exceptPo);
        }

        return (int) App\ExecuteScalar($sql, 'DB') > 0;
    }

    /**
     * Checks a DR against the PO's other DRs. $drs = the PO's stored DRs, $ref = the dr_number of the DR being edited (null on
     * add) — it is left out of every check. $storedPo = the PO number the DRs are stored under. Returns an error message or null:
     *   - the DR number is already used by another DR of this PO
     *   - a serial number of an item belongs to a DR anywhere
     *   - the DR total would exceed the PO balance (PO amount - the other DRs' totals); a PO that isn't an RFA PO has no limit
     */
    private function drError(string $storedPo, array $dr, array $drs, ?string $ref, ?array $info): ?string
    {
        $others = array_values(array_filter($drs, fn($d) => $ref === null || (string) ($d['dr_number'] ?? '') !== $ref));
        foreach ($others as $d) {
            if ((string) ($d['dr_number'] ?? '') === $dr['dr_number']) {
                return "Delivery receipt '{$dr['dr_number']}' is already recorded for this PO.";
            }
        }

        foreach ($dr['items'] as $it) {
            $ser = $it['serial_no'] ?? null;
            if ($ser === null) {
                continue;
            }
            $sql = "SELECT COUNT(*) FROM {$this->h} i, jsonb_array_elements(i.line_transaction) AS d(value),
                           jsonb_array_elements(COALESCE(d.value -> 'items', '[]'::jsonb)) AS it(value)
                    WHERE it.value ->> 'serial_no' = " . $this->q($ser);
            if ($ref !== null) {
                $sql .= ' AND NOT (i.po_number = ' . $this->q($storedPo) . " AND d.value ->> 'dr_number' = " . $this->q($ref) . ')';
            }
            if ((int) App\ExecuteScalar($sql, 'DB') > 0) {
                return "Serial no. '{$ser}' is already registered.";
            }
        }

        if ($info) {
            $delivered    = array_sum(array_map(fn($d) => (float) ($d['total_amount'] ?? 0), $others));
            $balanceCents = (int) round(((float) $info['po_amount'] - $delivered) * 100);
            if ((int) round((float) $dr['total'] * 100) > $balanceCents) {
                return 'The delivery receipt total (' . number_format((float) $dr['total'], 2) . ') exceeds the PO balance ('
                    . number_format(max(0, $balanceCents) / 100, 2) . ' of ' . number_format((float) $info['po_amount'], 2) . ').';
            }
        }

        return null;
    }

    /**
     * SQL for the sum of "requested_amount" over the lines of fims.budget_allocation.line_transaction (jsonb: an array of
     * lines, or one line object). A blank / non-numeric amount counts as 0. Constant SQL, no user input.
     */
    public function requestedTotalSql(): string
    {
        return "COALESCE((
                    SELECT SUM(CASE
                                 WHEN jsonb_typeof(ln.value->'requested_amount') = 'number' THEN (ln.value->>'requested_amount')::numeric
                                 WHEN jsonb_typeof(ln.value->'requested_amount') = 'string'
                                      AND REPLACE(ln.value->>'requested_amount', ',', '') ~ '^-?[0-9]+([.][0-9]+)?\$'
                                   THEN REPLACE(ln.value->>'requested_amount', ',', '')::numeric
                                 ELSE 0
                               END)
                    FROM jsonb_array_elements(" . $this->allocationLinesSql() . ") AS ln(value)
                    WHERE jsonb_typeof(ln.value) = 'object'
                ), 0)";
    }

    /**
     * SQL (correlated to the outer alias `a` = fims.budget_allocation) for ALL the lines of an allocation as ONE jsonb array. Newer
     * databases keep the lines on the project blocks (fims.budget_allocation_project.line_transaction — one row per block, the
     * column was dropped from budget_allocation); an older database still has budget_allocation.line_transaction. Whichever exists is used.
     */
    public function allocationLinesSql(): string
    {
        static $blocks = null;
        $blocks ??= (int) App\ExecuteScalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'fims' AND table_name = 'budget_allocation_project'", 'DB') > 0;
        if ($blocks) {
            return "(SELECT COALESCE(jsonb_agg(bl.value), '[]'::jsonb)
                       FROM fims.budget_allocation_project bap
                       CROSS JOIN LATERAL jsonb_array_elements(CASE jsonb_typeof(bap.line_transaction)
                                                                 WHEN 'array'  THEN bap.line_transaction
                                                                 WHEN 'object' THEN jsonb_build_array(bap.line_transaction)
                                                                 ELSE '[]'::jsonb
                                                               END) AS bl(value)
                      WHERE bap.allocation_id = a.allocation_id AND bap.deleted_at IS NULL)";
        }

        return "(CASE jsonb_typeof(a.line_transaction) WHEN 'array' THEN a.line_transaction WHEN 'object' THEN jsonb_build_array(a.line_transaction) ELSE '[]'::jsonb END)";
    }

    /** The budget allocation's line_transaction → [ {account_code, account_title, amount (= requested_amount)} ] for pre-filling the form. */
    public function allocationLines($raw): array
    {
        $a = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_array($a) && $a && array_keys($a) !== range(0, count($a) - 1)) {
            $a = [$a];                                   // a single line object
        }
        $out = [];
        foreach (is_array($a) ? $a : [] as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $code = trim((string) ($ln['account_code'] ?? ''));
            $amt  = str_replace(',', '', trim((string) ($ln['requested_amount'] ?? '')));
            if ($code === '' || !is_numeric($amt) || (float) $amt <= 0) {
                continue;
            }
            $out[] = ['account_code' => $code, 'account_title' => (string) ($ln['account_title'] ?? ''), 'amount' => number_format((float) $amt, 2, '.', '')];
        }

        return $out;
    }

    /**
     * The POs the IAR can be recorded against: the PO numbers of the Budget Allocation's RFA rows (allocation_type = 'RFA').
     * One row per PO number: { po_number, po_date, requesting_rc_id, requesting_office, vendor_id, vendor_name, po_amount, dr_total, lines }.
     *   po_date          = the PO's own date when the PO exists in the Purchase Order module (fims.purchase_orders, same
     *                      po_number, not CANCELLED); otherwise the RFA's creation date (budget_allocation has no PO date column)
     *   vendor_id/_name  = the RFA's vendor; when the RFA has none, the vendor of that Purchase Order
     *   requesting_office = fims.responsibility_code.rc_name of requesting_rc_id
     *   po_amount        = sum of line_transaction[].requested_amount
     *   dr_total         = total of the delivery receipts already recorded for the PO (PO balance = po_amount - dr_total)
     *   lines            = the RFA's line_transaction as account_code / account_title / amount, to pre-fill the form
     * $exact: PO numbers to look up; $search: ILIKE filter on the PO number; both empty = every PO.
     * $onlyWithBalance: leave out the POs that have no balance left (po_amount - dr_total <= 0) — used by the PO picker.
     */
    private function poSource(?array $exact = null, string $search = '', int $limit = 0, bool $onlyWithBalance = false): array
    {
        $where = "UPPER(COALESCE(a.allocation_type, '')) = 'RFA' AND a.deleted_at IS NULL AND COALESCE(TRIM(a.po_number), '') <> ''";
        if ($exact !== null) {
            if (!$exact) {
                return [];
            }
            $where .= ' AND a.po_number IN (' . implode(',', array_map(fn($p) => $this->q($p), $exact)) . ')';
        }
        if ($search !== '') {
            $where .= ' AND a.po_number ILIKE ' . $this->q('%' . $search . '%');
        }
        if ($onlyWithBalance) {   // the PO picker (adding an IAR): a PO whose DV was already processed is not offered
            $where .= ' AND NOT ' . $this->activeDvSql();
            // a PO that already has an IAR is not offered either: further deliveries are added from that row's Actions → Add Deliveries
            $where .= " AND NOT EXISTS (SELECT 1 FROM {$this->h} iar WHERE iar.po_number = a.po_number)";
        }
        $sql = "SELECT * FROM (
                    SELECT DISTINCT ON (a.po_number)
                           a.po_number, TO_CHAR(COALESCE(pom.po_date, a.created_at), 'YYYY-MM-DD') AS po_date, a.created_at,
                           a.allocation_number AS rfa_number, a.requesting_rc_id, rc.rc_name AS requesting_office,
                           COALESCE(a.vendor_id, pom.vendor_id) AS vendor_id, COALESCE(v.vendor_name, pom.vendor_name) AS vendor_name,
                           " . $this->requestedTotalSql() . " AS po_amount,
                           (SELECT COALESCE(SUM((d.value ->> 'total_amount')::numeric), 0)
                              FROM {$this->h} i, jsonb_array_elements(i.line_transaction) AS d(value)
                             WHERE i.po_number = a.po_number) AS dr_total,
                           " . $this->allocationLinesSql() . " AS allocation_lines
                    FROM fims.budget_allocation a
                    LEFT JOIN fims.responsibility_code rc ON rc.rc_id = a.requesting_rc_id
                    LEFT JOIN fims.vendors v ON v.vendor_id = a.vendor_id
                    LEFT JOIN LATERAL (
                        SELECT p.po_date, p.vendor_id, p.vendor_name
                        FROM fims.purchase_orders p
                        WHERE p.po_number = a.po_number AND p.status <> 'CANCELLED'
                        ORDER BY p.po_id DESC
                        LIMIT 1
                    ) pom ON TRUE
                    WHERE {$where}
                    ORDER BY a.po_number, a.created_at DESC, a.allocation_id DESC
                ) po" . ($onlyWithBalance ? ' WHERE po.po_amount - po.dr_total > 0' : '') . " ORDER BY po.created_at DESC, po.po_number" . ($limit > 0 ? ' LIMIT ' . (int) $limit : '');
        $rows = App\ExecuteRows($sql, 'DB') ?: [];
        foreach ($rows as &$r) {
            $r['lines'] = $this->allocationLines($r['allocation_lines']);
            unset($r['created_at'], $r['allocation_lines']);
        }
        unset($r);

        return $rows;
    }

    /** Search for the PO picker (RFA POs only; a PO with no balance left is not offered). */
    public function searchPos(string $search = ''): array
    {
        try {
            return ['success' => true, 'data' => $this->poSource(null, trim($search), 30, true)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Search for the Account Code picker, by code or title: the postable, active accounts of the chart of accounts — except a
     * GL account that has subsidiary ledgers. An SL is a child row of its GL account (chart_of_accounts.parent_account_id), e.g.
     * 5-02-03-010-01 "Office Supplies" under 5-02-03-010 "Office Supplies Expenses"; when a GL account has postable SLs only its
     * SLs are offered. Typing the GL code (5-02-03-010) finds its SLs too.
     */
    public function searchAccounts(string $search = '', bool $all = false): array
    {
        try {
            $where = "COALESCE(c.posting_allowed, TRUE) AND COALESCE(c.status, 'Active') ILIKE 'active'
                      AND NOT EXISTS (SELECT 1 FROM fims.chart_of_accounts ch
                                       WHERE ch.parent_account_id = c.account_id AND ch.posting_allowed AND COALESCE(ch.status, 'Active') ILIKE 'active')";
            if (trim($search) !== '') {
                $s      = $this->q('%' . trim($search) . '%');
                $where .= " AND (c.account_code ILIKE {$s} OR c.account_title ILIKE {$s} OR p.account_code ILIKE {$s})";
            }
            // With nothing typed, ONLY the property / equipment accounts (PRIORITY_ACCOUNTS and their sub-ledgers) are offered; once the
            // user types, the whole chart of accounts is searched (the property accounts still come first).
            $prio = implode(',', array_map(fn($c) => $this->q($c), self::PRIORITY_ACCOUNTS));
            if (trim($search) === '' && !$all) {   // $all: every account is offered even with nothing typed (the pro-forma of the Property Records page)
                $where .= " AND (c.account_code IN ({$prio}) OR p.account_code IN ({$prio}))";
            }
            $rows = App\ExecuteRows(
                "SELECT account_code, account_title FROM (
                    SELECT DISTINCT ON (c.account_code) c.account_code, c.account_title,
                           CASE WHEN c.account_code IN ({$prio}) OR p.account_code IN ({$prio}) THEN 0 ELSE 1 END AS prio
                      FROM fims.chart_of_accounts c
                      LEFT JOIN fims.chart_of_accounts p ON p.account_id = c.parent_account_id
                     WHERE {$where}
                     ORDER BY c.account_code
                 ) t ORDER BY prio, account_code LIMIT 30",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** po_number => PO details, for the given PO numbers. */
    private function poInfo(array $poNumbers): array
    {
        $by = [];
        foreach ($this->poSource(array_values(array_unique($poNumbers))) as $r) {
            $by[$r['po_number']] = $r;
        }

        return $by;
    }

    /** The PO's details + what has been delivered so far: balance = PO amount - total of its delivery receipts. */
    private function withPo(array $row, ?array $info, array $receipts): array
    {
        $drTotal = round(array_sum(array_map(fn($r) => (float) $r['total_amount'], $receipts)), 2);
        $poAmt   = $info ? round((float) $info['po_amount'], 2) : null;

        return $row + [
            'po_date'           => $info['po_date'] ?? null,
            'requesting_rc_id'  => $info['requesting_rc_id'] ?? null,
            'requesting_office' => $info['requesting_office'] ?? null,
            'vendor_id'         => $info['vendor_id'] ?? null,
            'vendor_name'       => $info['vendor_name'] ?? null,
            'rfa_number'        => $info['rfa_number'] ?? null,
            'lines'             => $info['lines'] ?? [],   // the RFA's account lines, to pre-fill a new delivery receipt
            'po_amount'         => $poAmt,
            'dr_total'          => $drTotal,
            'balance'           => $poAmt === null ? null : round($poAmt - $drTotal, 2),
        ];
    }

    /** IAR row → { po_number, iar_number, si_number, si_date, receipts[] } (receipts in the API shape). */
    private function shapeRow(array $r): array
    {
        return [
            'po_number'  => $r['po_number'],
            'iar_number' => $r['iar_number'],
            'si_number'  => $r['si_number'],
            'si_date'    => $r['si_date'],
            'delivery_accepted'    => in_array($r['delivery_accepted'] ?? false, [true, 't', 'true', 1, '1'], true),
            'delivery_accepted_at' => $r['delivery_accepted_at'] ?? null,   // "Date"
            'inspected_by'         => $r['inspected_by'] ?? null,           // "Inspected By": the user's name, read live from uac.users
            'subject_to'           => $r['subject_to'] ?? null,
            'inspection_notes'     => $r['inspection_notes'] ?? null,
            'receipts'   => array_map(fn($d) => $this->shape($d), $this->drsFrom($r['line_transaction'])),
        ];
    }

    private const COLS = "i.po_number, i.iar_number, i.si_number, TO_CHAR(i.si_date, 'YYYY-MM-DD') AS si_date, i.line_transaction,
                              i.delivery_accepted, TO_CHAR(i.delivery_accepted_at, 'YYYY-MM-DD') AS delivery_accepted_at, i.subject_to, i.inspection_notes,
                              (SELECT NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM(u.first_name), ''), NULLIF(TRIM(u.middle_name), ''), NULLIF(TRIM(u.last_name), ''), NULLIF(TRIM(u.suffix), ''))), '')
                                 FROM uac.users u WHERE u.user_id = i.delivery_accepted_by) AS inspected_by";

    /** One entry per PO number: { po_number, iar_number, …, receipts: [ … ] } (paginated). */
    public function fetchAlls(int $page = 1, int $limit = 10, string $search = ''): array
    {
        try {
            $where = '1=1';
            if ($search !== '') {
                // the delivery receipts live in line_transaction: matching its text covers DR no., account codes / titles,
                // description, serial and property numbers
                $s     = $this->q('%' . $search . '%');
                $where = "(i.po_number ILIKE {$s} OR i.iar_number ILIKE {$s} OR i.si_number ILIKE {$s} OR i.line_transaction::text ILIKE {$s})";
            }

            $limit  = max(1, $limit);
            $page   = max(1, $page);
            $offset = ($page - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->h} i WHERE {$where}", 'DB');

            $rows = App\ExecuteRows(
                'SELECT ' . self::COLS . " FROM {$this->h} i WHERE {$where} ORDER BY i.iar_id DESC LIMIT {$limit} OFFSET {$offset}",
                'DB'
            ) ?: [];

            $info = $this->poInfo(array_column($rows, 'po_number'));
            $data = [];
            foreach ($rows as $r) {
                $row    = $this->shapeRow($r);
                $data[] = $this->withPo($row, $info[$row['po_number']] ?? null, $row['receipts']);
            }

            // KPI cards: totals over EVERY row matching the search, not just this page
            $sumPo = 0.0;
            $sumDr = 0.0;
            $all   = App\ExecuteRows('SELECT ' . self::COLS . " FROM {$this->h} i WHERE {$where}", 'DB') ?: [];
            $allInfo = $this->poInfo(array_column($all, 'po_number'));
            foreach ($all as $r) {
                $row    = $this->shapeRow($r);
                $full   = $this->withPo($row, $allInfo[$row['po_number']] ?? null, $row['receipts']);
                $sumPo += (float) ($full['po_amount'] ?? 0);
                $sumDr += (float) ($full['dr_total'] ?? 0);
            }

            return [
                'success'    => true,
                'data'       => $data,
                'summary'    => ['total_po_amount' => round($sumPo, 2), 'total_dr_amount' => round($sumDr, 2)],
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchByPo(string $poNumber): array
    {
        try {
            $rows = App\ExecuteRows('SELECT ' . self::COLS . " FROM {$this->h} i WHERE i.po_number = " . $this->q($poNumber), 'DB');
            if (!$rows) {
                return ['success' => false, 'message' => "PO '{$poNumber}' not found."];
            }
            $row = $this->shapeRow($rows[0]);

            return ['success' => true, 'data' => $this->withPo($row, $this->poInfo([$poNumber])[$poNumber] ?? null, $row['receipts'])];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * The account a pro-forma JEV credits: the vendor's own Accounts Payable sub-ledger (fims.vendors.sl_code -> COA child row, e.g.
     * 2-01-01-010-01), the same way the DV does. A vendor with none (or no vendor) gets the default "Account Payable 1" (2-01-01-010-01);
     * when that is missing, the first active sub-ledger under 2-01-01-010, else the 2-01-01-010 control account. Returns [code, title].
     */
    public function payableAccount(string $vendorId = ''): array
    {
        $vendorId = trim($vendorId);
        if ($vendorId !== '') {
            $sl = App\ExecuteRows(
                'SELECT c.account_code, c.account_title FROM fims.vendors v JOIN fims.chart_of_accounts c ON c.account_id = v.sl_code OR c.account_code = v.sl_code'
                . ' WHERE v.vendor_id = ' . $this->q($vendorId) . ' LIMIT 1',
                'DB'
            );
            if ($sl) {
                return [(string) $sl[0]['account_code'], (string) $sl[0]['account_title']];
            }
        }
        $first = App\ExecuteRows(
            'SELECT c.account_code, c.account_title FROM fims.chart_of_accounts c JOIN fims.chart_of_accounts p ON p.account_id = c.parent_account_id'
            . ' WHERE p.account_code = ' . $this->q(self::PAYABLE_ACCOUNT) . " AND c.status = 'Active'"
            . ' ORDER BY (c.account_code = ' . $this->q(self::DEFAULT_PAYABLE_SL) . ') DESC, c.account_code LIMIT 1',
            'DB'
        );
        if ($first) {
            return [(string) $first[0]['account_code'], (string) $first[0]['account_title']];
        }
        $title = (string) (App\ExecuteScalar('SELECT account_title FROM fims.chart_of_accounts WHERE account_code = ' . $this->q(self::PAYABLE_ACCOUNT) . ' LIMIT 1', 'DB') ?: 'Accounts Payable');

        return [self::PAYABLE_ACCOUNT, $title];
    }

    /**
     * Pro-forma JEV of every delivery receipt of a PO (one entry per DR): each stored account row is a debit, and the
     * DR total is credited to Accounts Payable (2-01-01-010). Titles are the ones stored with the rows.
     */
    public function proforma(string $poNumber): array
    {
        try {
            $res = $this->fetchByPo($poNumber);
            if (!$res['success'] || !$res['data']['receipts']) {
                return ['success' => false, 'message' => "No delivery receipts found for PO '{$poNumber}'."];
            }

            [$payableCode, $payableTitle] = $this->payableAccount((string) ($res['data']['vendor_id'] ?? ''));
            $receipts = [];
            foreach ($res['data']['receipts'] as $dr) {
                $rows = [];
                foreach ($dr['entries'] as $en) {
                    $rows[] = [
                        'account_title' => $en['account_title'] ?? '',
                        'account_code'  => $en['account_code'],
                        'debit'         => round((float) $en['amount'], 2),
                        'credit'        => 0,
                    ];
                }
                $rows[] = [
                    'account_title' => $payableTitle,
                    'account_code'  => $payableCode,
                    'debit'         => 0,
                    'credit'        => round((float) $dr['total_amount'], 2),
                ];
                $receipts[] = [
                    'dr_number' => (string) $dr['dr_number'], 'delivery_date' => $dr['delivery_date'] ?? null, 'rows' => $rows, 'total' => round((float) $dr['total_amount'], 2),
                    'jev_id' => $dr['jev_id'] ?? null, 'jev_no' => $dr['jev_no'] ?? null,   // set once its pro-forma was submitted as a JEV
                ];
            }

            return ['success' => true, 'data' => [
                'po_number' => $poNumber, 'iar_number' => $res['data']['iar_number'], 'rfa_number' => $res['data']['rfa_number'] ?? null,
                'requesting_rc_id' => $res['data']['requesting_rc_id'] ?? null,   // the JEV lines' Responsibility Center when it is submitted
                'receipts' => $receipts,
            ]];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** The JEV a delivery receipt's pro-forma was submitted as ({jev_id, jev_no}), or null when it has not been submitted. */
    public function jevOf(string $poNumber, string $drNumber): ?array
    {
        $rows = App\ExecuteRows("SELECT line_transaction FROM {$this->h} WHERE po_number = " . $this->q($poNumber), 'DB');
        foreach ($rows ? $this->drsFrom($rows[0]['line_transaction']) : [] as $d) {
            if ((string) ($d['dr_number'] ?? '') === $drNumber && !empty($d['jev_id'])) {
                return ['jev_id' => (int) $d['jev_id'], 'jev_no' => (string) ($d['jev_no'] ?? '')];
            }
        }

        return null;
    }

    /** Records on the delivery receipt (in line_transaction) the JEV its pro-forma was submitted as: the pro-forma cannot be submitted twice. */
    public function linkJev(string $poNumber, string $drNumber, int $jevId, string $jevNo): bool
    {
        App\Execute('BEGIN', 'DB');
        try {
            $cur   = App\ExecuteRows("SELECT line_transaction FROM {$this->h} WHERE po_number = " . $this->q($poNumber) . ' FOR UPDATE', 'DB');
            $drs   = $cur ? $this->drsFrom($cur[0]['line_transaction']) : [];
            $found = false;
            foreach ($drs as $i => $d) {
                if ((string) ($d['dr_number'] ?? '') === $drNumber) {
                    $drs[$i]['jev_id'] = $jevId;
                    $drs[$i]['jev_no'] = $jevNo;
                    $found = true;
                }
            }
            if ($found) {
                App\Execute("UPDATE {$this->h} SET line_transaction = " . $this->jsonSql($drs) . ' WHERE po_number = ' . $this->q($poNumber), 'DB');
            }
            App\Execute('COMMIT', 'DB');

            return $found;
        } catch (\Exception $e) {
            App\Execute('ROLLBACK', 'DB');
            App\LogError('PoDrService::linkJev ' . $e->getMessage());

            return false;
        }
    }

    /** Adds ONE delivery receipt to the PO (creating the PO's IAR row when the PO has none yet). */
    /**
     * SQL condition (alias `a` = fims.budget_allocation): the RFA is linked to a Disbursement Voucher that is still active,
     * i.e. a DV was already processed for its PO (a CANCELLED / REJECTED DV frees the RFA again, as in the DV's RFA picker).
     */
    public function activeDvSql(): string
    {
        return "(a.dv_id IS NOT NULL AND a.dv_id NOT IN (
                    SELECT dv.dv_id FROM fims.disbursement_vouchers dv
                    LEFT JOIN fims.settings_status st ON st.id = dv.status_id
                    WHERE st.name IN ('CANCELLED', 'REJECTED')))";
    }

    /** The DV number of the active Disbursement Voucher already processed for the PO, or null when there is none. */
    public function dvOf(string $poNumber): ?string
    {
        $r = App\ExecuteRows(
            "SELECT dv.dv_no FROM fims.budget_allocation a
               JOIN fims.disbursement_vouchers dv ON dv.dv_id = a.dv_id
              WHERE a.po_number = " . $this->q($poNumber) . " AND a.deleted_at IS NULL AND {$this->activeDvSql()}
              LIMIT 1",
            'DB'
        );

        return $r ? (string) ($r[0]['dv_no'] ?: 'DV') : null;
    }

    /**
     * Actions -> Delivery Acceptance: marks the PO's delivery as accepted (delivery_accepted = TRUE, with when / who). One-way; no more
     * delivery receipts can be added afterwards. Other modules can key off the flag.
     */
    public function acceptDelivery(string $poNumber, ?int $userId = null, string $subjectTo = '', string $notes = ''): array
    {
        try {
            $po        = trim($poNumber);
            $subjectTo = trim($subjectTo);
            $notes     = trim($notes);
            if ($subjectTo === '') {
                return ['success' => false, 'message' => 'Subject to ICS/PAR is required.'];
            }
            if (mb_strlen($subjectTo) > 100) {
                return ['success' => false, 'message' => 'Subject to ICS/PAR must be 100 characters or fewer.'];
            }
            if (mb_strlen($notes) > 2000) {
                return ['success' => false, 'message' => 'Inspection notes must be 2,000 characters or fewer.'];
            }
            $cur = App\ExecuteRows("SELECT delivery_accepted, line_transaction FROM {$this->h} WHERE po_number = " . $this->q($po), 'DB');
            if (!$cur) {
                return ['success' => false, 'message' => "PO '{$po}' has no IAR yet.", 'httpCode' => 404];
            }
            if (in_array($cur[0]['delivery_accepted'], [true, 't', 'true', 1, '1'], true)) {
                return ['success' => false, 'message' => "The delivery of PO '{$po}' was already accepted.", 'httpCode' => 409];
            }
            // every delivery receipt's pro-forma JEV must have been submitted first
            $pending = [];
            foreach ($this->drsFrom($cur[0]['line_transaction']) as $d) {
                if (empty($d['jev_id'])) {
                    $pending[] = (string) ($d['dr_number'] ?? '');
                }
            }
            if ($pending) {
                return ['success' => false, 'message' => 'Submit the pro-forma JEV of every delivery receipt first. Not yet submitted: ' . implode(', ', $pending) . '.'];
            }
            $by = $userId ? (int) $userId : 'NULL';
            App\Execute("UPDATE {$this->h} SET delivery_accepted = TRUE, delivery_accepted_at = CURRENT_TIMESTAMP, delivery_accepted_by = {$by}, "
                . 'subject_to = ' . $this->q($subjectTo) . ', inspection_notes = ' . $this->sqlOrNull($notes === '' ? null : $notes)
                . " WHERE po_number = " . $this->q($po), 'DB');

            $this->notifyGsd($po, $userId, $subjectTo, $notes);

            return ['success' => true, 'message' => "Delivery of PO '{$po}' accepted."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** The Responsibility Center of the General Services Department (fims.responsibility_code): notified of every delivery acceptance. */
    private const GSD_RC = '010';

    /**
     * Tell every active GSD user (uac.users.rc_id = GSD_RC) that a delivery was accepted. Never throws and never blocks the acceptance —
     * the acceptance is already saved. The sender is left out of the recipients; event_key makes a repeat harmless.
     */
    private function notifyGsd(string $po, ?int $userId, string $subjectTo, string $notes): void
    {
        try {
            $r    = App\ExecuteRows("SELECT iar_number FROM {$this->h} WHERE po_number = " . $this->q($po), 'DB');
            $iar  = $r ? (string) $r[0]['iar_number'] : '';
            $body = "Delivery Acceptance for PO {$po}" . ($iar !== '' ? " (IAR {$iar})" : '') . ". Subject to: {$subjectTo}." . ($notes !== '' ? " Notes: {$notes}" : '')
                . ' Open it to prepare the Disbursement Voucher.';
            try {   // the link goes to the DV (Voucher page), which opens the new-DV modal with this PO (and its vendor) filled in — not to the IAR module
                $link = '/' . ltrim((string) App\GetUrl('Voucher'), '/');
            } catch (\Throwable $e) {
                $link = '/Voucher';
            }
            $link .= '?po=' . rawurlencode($po);
            NotificationService::toRc(self::GSD_RC, "Delivery Acceptance: PO {$po}", $body, $link, [
                'sender_id' => $userId && $userId > 0 ? $userId : null,
                'event_key' => "delivery_accepted:{$po}",
            ]);
        } catch (\Throwable $e) {
            App\LogError("PoDrService: could not notify GSD of the acceptance of PO {$po} — " . $e->getMessage());
        }
    }

    public function create(array $body, ?int $userId = null): array
    {
        try {
            [$po, $dr, $err, $hdr] = $this->clean($body) + [null, null, null, null];   // an error result has only 3 items
            if ($err) {
                return ['success' => false, 'message' => $err];
            }
            if ($dv = $this->dvOf($po)) {   // a DV was already processed for this PO: no more IAR / delivery receipts
                return ['success' => false, 'message' => "A Disbursement Voucher ({$dv}) was already processed for PO '{$po}', so an IAR can no longer be added to it."];
            }
            // The PO must be picked from the Budget Allocation (an RFA's PO number), not typed freely.
            $poInfo = $this->poInfo([$po])[$po] ?? null;
            if (!$poInfo) {
                return ['success' => false, 'message' => "PO '{$po}' was not found in the Budget Allocation (RFA). Please select a PO from the list."];
            }
            $iar = $hdr['iar_number'];
            $by  = $userId ? (int) $userId : 'NULL';

            App\Execute('BEGIN', 'DB');
            try {
                $cur = App\ExecuteRows("SELECT iar_number, line_transaction, delivery_accepted FROM {$this->h} WHERE po_number = " . $this->q($po) . ' FOR UPDATE', 'DB');
                if ($cur && in_array($cur[0]['delivery_accepted'], [true, 't', 'true', 1, '1'], true)) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => "The delivery of PO '{$po}' was already accepted, so no more delivery receipts can be added."];
                }
                if ($cur && $cur[0]['iar_number'] !== $iar) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => "PO '{$po}' already has IAR number '{$cur[0]['iar_number']}'. Use that IAR number, or edit the PO to change it."];
                }
                if (!$cur && $this->iarTaken($iar)) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => "IAR number '{$iar}' is already used by another PO."];
                }
                $drs = $cur ? $this->drsFrom($cur[0]['line_transaction']) : [];
                if ($msg = $this->drError($po, $dr, $drs, null, $poInfo)) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => $msg];
                }
                $drs[] = $this->stored($dr);

                if ($cur) {
                    // the PO already has its IAR (same number, checked above): add the DR, fill in the invoice details that were given
                    $set = ['line_transaction = ' . $this->jsonSql($drs)];
                    if ($hdr['si_number'] !== null) $set[] = 'si_number = ' . $this->q($hdr['si_number']);
                    if ($hdr['si_date'] !== null)   $set[] = 'si_date = ' . $this->q($hdr['si_date']);
                    App\Execute("UPDATE {$this->h} SET " . implode(', ', $set) . ", updated_by = {$by}, updated_at = CURRENT_TIMESTAMP WHERE po_number = " . $this->q($po), 'DB');
                } else {
                    App\Execute(
                        "INSERT INTO {$this->h} (iar_number, po_number, si_number, si_date, line_transaction, created_by) VALUES ("
                            . $this->q($iar) . ', ' . $this->q($po) . ', ' . $this->sqlOrNull($hdr['si_number']) . ', '
                            . $this->sqlOrNull($hdr['si_date']) . ', ' . $this->jsonSql($drs) . ", {$by})",
                        'DB'
                    );
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => $cur ? "Delivery receipt {$dr['dr_number']} added to PO {$po} ({$iar})." : "PO {$po} added successfully ({$iar})."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * EDIT — only the Sales Invoice # and the Sales Invoice date can be changed (the delivery receipt is picked with payload
     * dr.dr_ref = its dr_number). Everything else — the PO number, the IAR number, the DR number / date, the account rows,
     * the amounts and the items — is kept as stored, whatever is sent for it is ignored, and no other DR can be added (that is
     * the Add form). So the PO balance cannot be affected by an edit.
     */
    public function update(string $oldPo, array $body, ?int $userId = null): array
    {
        try {
            $check = $this->fetchByPo($oldPo);
            if (!$check['success']) {
                return $check;
            }
            if (!empty($check['data']['delivery_accepted'])) {   // Delivery Acceptance locks the record: no more deliveries and no edits
                return ['success' => false, 'message' => "The delivery of PO '{$oldPo}' was already accepted, so the record can no longer be edited."];
            }

            // the PO of an IAR cannot be changed
            if (trim((string) ($body['po_number'] ?? '')) !== $oldPo) {
                return ['success' => false, 'message' => 'The PO number cannot be changed. Add a new record for the other PO.'];
            }

            // the DR being edited (by the dr_number it had when loaded) — its stored account rows are kept as they are:
            // they are put into the payload only so that the usual validation of the whole DR runs on the unchanged total
            $ref    = trim((string) ($body['dr']['dr_ref'] ?? ''));
            $stored = null;
            foreach ($check['data']['receipts'] as $r) {
                if ($ref !== '' && (string) $r['dr_number'] === $ref) {
                    $stored = $r;
                }
            }
            if ($stored === null) {
                return ['success' => false, 'message' => 'Select the delivery receipt to edit.'];
            }
            // Only the Sales Invoice # / date can be edited: the IAR number, the DR number / date, the account rows and the items
            // are the stored ones, whatever is sent for them is ignored.
            $oldIar = $check['data']['iar_number'];
            $body['iar_number']           = $oldIar;
            $body['dr']['dr_number']      = $stored['dr_number'];
            $body['dr']['delivery_date']  = $stored['delivery_date'];
            $body['dr']['entries'] = array_map(fn($e) => ['account_code' => $e['account_code'], 'amount' => $e['amount']], $stored['entries']);

            [$po, $dr, $err, $hdr] = $this->clean($body, false) + [null, null, null, null];   // an error result has only 3 items
            if ($err) {
                return ['success' => false, 'message' => $err];
            }
            $iar        = $oldIar;
            $by         = $userId ? (int) $userId : 'NULL';

            App\Execute('BEGIN', 'DB');
            try {
                $cur = App\ExecuteRows("SELECT line_transaction FROM {$this->h} WHERE po_number = " . $this->q($oldPo) . ' FOR UPDATE', 'DB');
                $drs = $cur ? $this->drsFrom($cur[0]['line_transaction']) : [];
                $pos = null;
                foreach ($drs as $i => $d) {
                    if ($dr['ref'] !== null && (string) ($d['dr_number'] ?? '') === $dr['ref']) {
                        $pos = $i;
                    }
                }
                if ($pos === null) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => 'Select the delivery receipt to edit.'];
                }
                // The items: only their description, serial no. and property no. can be changed (matched by position; the amount and the
                // category stay as stored). The description is required, and a serial no. may not repeat.
                $oldItems = array_values(array_filter(is_array($drs[$pos]['items'] ?? null) ? $drs[$pos]['items'] : [], 'is_array'));
                if ($oldItems) {
                    $posted = array_values(array_filter(is_array($body['dr']['items'] ?? null) ? $body['dr']['items'] : [], 'is_array'));
                    if (count($posted) !== count($oldItems)) {
                        App\Execute('ROLLBACK', 'DB');

                        return ['success' => false, 'message' => 'The items of this delivery receipt cannot be added or removed here.'];
                    }
                    $newItems = [];
                    $serials  = [];
                    foreach ($oldItems as $k => $old) {
                        $n    = $k + 1;
                        $desc = $this->nullable($posted[$k]['description'] ?? null);
                        $ser  = $this->nullable($posted[$k]['serial_no'] ?? null);
                        $prop = $this->nullable($posted[$k]['property_no'] ?? null);
                        $err  = $desc === null ? "Item {$n}: description is required."
                            : (($ser !== null && mb_strlen($ser) > 100) ? "Item {$n}: Serial no. must be 100 characters or fewer."
                            : (($prop !== null && mb_strlen($prop) > 100) ? "Item {$n}: Property no. must be 100 characters or fewer."
                            : (($ser !== null && isset($serials[mb_strtolower($ser)])) ? "Serial no. '{$ser}' is entered more than once." : null)));
                        if ($err) {
                            App\Execute('ROLLBACK', 'DB');

                            return ['success' => false, 'message' => $err];
                        }
                        if ($ser !== null) {
                            $serials[mb_strtolower($ser)] = true;
                        }
                        unset($old['description'], $old['serial_no'], $old['property_no']);
                        $newItems[] = ['description' => $desc] + ($ser !== null ? ['serial_no' => $ser] : []) + ($prop !== null ? ['property_no' => $prop] : []) + $old;
                    }
                    $dr['items'] = $newItems;
                }
                if ($msg = $this->drError($oldPo, $dr, $drs, $dr['ref'], null)) {     // DR number + serial checks; no balance check: the total is unchanged
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => $msg];
                }
                // the stored delivery receipt is kept as it is (account rows, amounts, categories); only the items' description / serial no. /
                // property no. and the invoice number / date of the IAR change
                if ($oldItems) {
                    $drs[$pos]['items'] = $dr['items'];
                }
                App\Execute("UPDATE {$this->h} SET iar_number = " . $this->q($iar) . ', po_number = ' . $this->q($oldPo) . ', si_number = ' . $this->sqlOrNull($hdr['si_number'])
                    . ', si_date = ' . $this->sqlOrNull($hdr['si_date']) . ', line_transaction = ' . $this->jsonSql($drs)
                    . ", updated_by = {$by}, updated_at = CURRENT_TIMESTAMP WHERE po_number = " . $this->q($oldPo), 'DB');
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => "Delivery receipt {$dr['dr_number']} of PO {$po} updated successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Deletes the PO's IAR with all its delivery receipts. */
    public function delete(string $poNumber): array
    {
        try {
            $check = $this->fetchByPo($poNumber);
            if (!$check['success']) {
                return $check;
            }
            if (!empty($check['data']['delivery_accepted'])) {
                return ['success' => false, 'message' => "The delivery of PO '{$poNumber}' was already accepted, so the record can no longer be deleted."];
            }
            App\Execute("DELETE FROM {$this->h} WHERE po_number = " . $this->q($poNumber), 'DB');

            return ['success' => true, 'message' => "PO {$poNumber} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
