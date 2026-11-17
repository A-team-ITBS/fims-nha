<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * FarReportService — DBM Financial Accountability Reports, read-only, built straight from the FIMS tables with
 * LEFT JOINs (no SQL views, nothing written). Routes: app/routes/far_report.php. Diagram: FAR-ERD.md (repo root).
 *
 *   FAR 1   fetchFar1()   Appropriations, Allotments, Obligations, Disbursements and Balances — per Program / Project
 *   FAR 1-A fetchFar1a()  the same figures per Object of Expenditure (PS / MOOE / CO / Others) and account code
 *   FAR 3   fetchFar3()   Aging of Due and Demandable (approved but unpaid) Obligations, as of a date
 *   FAR 4   fetchFar4()   Monthly Report of Disbursements — checks + ADA per bank account, against the NCAs received
 *   FAR 5   fetchFar5()   Quarterly Report of Revenues and Other Receipts — Official Receipts per revenue account
 *
 * Where the numbers come from:
 *   Appropriation  Approved budget requests: budget_req -> budget_req_program -> budget_req_project -> budget_line_items
 *                  (suggested_amount, else proposed_amount)
 *   Allotment      Approved LAAs (budget_allocation, allocation_type LAA); SAROs (fund_tracking type 1) as a total
 *   (RFA / LAA program, project and line_transaction live in budget_allocation_project — one row per project block.)
 *   Obligation     Approved RFAs (budget_allocation, allocation_type RFA) — the sum of their line_transaction
 *                  "requested_amount"s. Obligation date = when it was Approved (status_change_log), else updated_at.
 *   Disbursement   An obligation is disbursed when the DV it is drawn into (budget_allocation.dv_id) is released by
 *                  Treasury (disbursement_vouchers.treasury_released_at). One DV can cover several RFAs, so the RFA's
 *                  own amount is used, not the DV's.
 *   FAR 4          checks (check_date) + ada_releases (ada_date), NCAs = fund_tracking type 2 by bank account number.
 *   FAR 5          official_receipts + official_receipt_lines (the credited / revenue accounts).
 *
 * Object of Expenditure (FAR 1-A, FAR 4) comes from the account code: 5-01 PS, 5-02 / 5-03 MOOE, 1-06 / 1-07 CO.
 * RC scope (FAR 1, 1-A, 3): the route passes rc_id; that RC and the RCs under it. FAR 4 / 5 are agency-wide.
 */
class FarReportService
{
    private const TT_SARO = 1;
    private const TT_NCA = 2;

    /* ═════════════════════════════ FAR 1 ═════════════════════════════ */

    /** Filters: fiscal_year (default this year), rc_id (from the route). One row per Program / Project + totals. */
    public function fetchFar1(array $filters = []): array
    {
        try {
            $fy = $this->fiscalYear($filters);
            $rows = [];
            $key = fn ($r) => ($r['program_code'] ?? '') . '|' . ($r['project_code'] ?? '');
            $blank = fn ($r) => [
                'program_code' => $r['program_code'] ?? null, 'program_name' => $r['program_name'] ?? null,
                'project_code' => $r['project_code'] ?? null, 'project_name' => $r['project_name'] ?? null,
                'appropriation' => 0.0, 'allotment' => 0.0,
                'obligation_q1' => 0.0, 'obligation_q2' => 0.0, 'obligation_q3' => 0.0, 'obligation_q4' => 0.0, 'obligation' => 0.0,
                'disbursement_q1' => 0.0, 'disbursement_q2' => 0.0, 'disbursement_q3' => 0.0, 'disbursement_q4' => 0.0, 'disbursement' => 0.0,
            ];

            // 1. Appropriations
            foreach ($this->appropriationRows($fy, $filters, 'brp.program_code, brj.project_code') as $r) {
                $rows[$key($r)] ??= $blank($r);
                $rows[$key($r)]['appropriation'] += (float) $r['amount'];
            }

            // 2. Allotments (approved LAAs)
            foreach ($this->allocationRows('LAA', $fy, $filters) as $r) {
                $rows[$key($r)] ??= $blank($r);
                $rows[$key($r)]['allotment'] += (float) $r['amount'];
            }

            // 3. Obligations and disbursements (approved RFAs), by quarter
            foreach ($this->allocationRows('RFA', $fy, $filters) as $r) {
                $k = $key($r);
                $rows[$k] ??= $blank($r);
                $amt = (float) $r['amount'];
                $q = $this->quarterOf($r['obligated_at'], $fy);
                if ($q) {
                    $rows[$k]["obligation_q{$q}"] += $amt;
                    $rows[$k]['obligation'] += $amt;
                }
                $dq = $this->quarterOf($r['disbursed_at'], $fy);
                if ($dq) {
                    $rows[$k]["disbursement_q{$dq}"] += $amt;
                    $rows[$k]['disbursement'] += $amt;
                }
            }

            $rows = array_values(array_map([$this, 'withBalances'], $rows));
            usort($rows, fn ($a, $b) => strcmp($a['program_code'] . '|' . $a['project_code'], $b['program_code'] . '|' . $b['project_code']));

            $totals = $this->sumColumns($rows);
            $totals['saro_allotment'] = $this->saroTotal($fy);

            return ['success' => true, 'fiscal_year' => $fy, 'data' => $rows, 'totals' => $totals];
        } catch (\Throwable $e) {
            App\LogError('FarReportService::fetchFar1 ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to build FAR No. 1.'];
        }
    }

    /* ═════════════════════════════ FAR 1-A ═════════════════════════════ */

    /** Filters: fiscal_year, rc_id. One row per account, grouped by object class (PS / MOOE / CO / Others) + totals. */
    public function fetchFar1a(array $filters = []): array
    {
        try {
            $fy = $this->fiscalYear($filters);
            $rows = [];
            $blank = fn ($code, $title) => [
                'object_class' => $this->objectClass($code), 'account_code' => $code, 'account_title' => $title,
                'appropriation' => 0.0, 'allotment' => 0.0, 'obligation' => 0.0, 'disbursement' => 0.0,
            ];

            foreach ($this->appropriationRows($fy, $filters, 'coa.account_code, coa.account_title') as $r) {
                $code = $r['account_code'] ?? '';
                $rows[$code] ??= $blank($code, $r['account_title']);
                $rows[$code]['appropriation'] += (float) $r['amount'];
            }

            // Allotments, obligations and disbursements: one row per line of each approved LAA / RFA
            foreach (['LAA', 'RFA'] as $type) {
                foreach ($this->allocationLineRows($type, $fy, $filters) as $r) {
                    $code = $r['account_code'] ?? '';
                    $rows[$code] ??= $blank($code, $r['account_title']);
                    $amt = (float) $r['amount'];
                    if ($type === 'LAA') {
                        $rows[$code]['allotment'] += $amt;
                        continue;
                    }
                    if ($this->quarterOf($r['obligated_at'], $fy)) {
                        $rows[$code]['obligation'] += $amt;
                    }
                    if ($this->quarterOf($r['disbursed_at'], $fy)) {
                        $rows[$code]['disbursement'] += $amt;
                    }
                }
            }

            $order = ['PS' => 1, 'MOOE' => 2, 'CO' => 3, 'Others' => 4];
            $rows = array_values(array_map([$this, 'withBalances'], $rows));
            usort($rows, fn ($a, $b) => [$order[$a['object_class']], $a['account_code']] <=> [$order[$b['object_class']], $b['account_code']]);

            // Subtotal per object class
            $byClass = [];
            foreach ($rows as $r) {
                $byClass[$r['object_class']][] = $r;
            }
            $groups = [];
            foreach ($byClass as $class => $items) {
                $groups[] = ['object_class' => $class, 'accounts' => $items, 'subtotal' => $this->sumColumns($items)];
            }

            return ['success' => true, 'fiscal_year' => $fy, 'data' => $groups, 'totals' => $this->sumColumns($rows)];
        } catch (\Throwable $e) {
            App\LogError('FarReportService::fetchFar1a ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to build FAR No. 1-A.'];
        }
    }

    /* ═════════════════════════════ FAR 3 ═════════════════════════════ */

    /**
     * Filters: as_of (YYYY-MM-DD, default today), rc_id — no fiscal year: every approved RFA of ANY year not yet paid on as_of
     * (no DV, or its DV not released by Treasury by then), aged from the due date: the IAR date of the RFA's
     * PO (goods accepted), else the obligation date.
     */
    public function fetchFar3(array $filters = []): array
    {
        try {
            $asOf = $this->dateFilter($filters, 'as_of') ?? date('Y-m-d');

            $rows = [];
            $buckets = ['0_30' => 0.0, '31_90' => 0.0, '91_365' => 0.0, 'over_365' => 0.0];
            foreach ($this->allocationRows('RFA', null, $filters, true) as $r) {
                $obligated = substr((string) $r['obligated_at'], 0, 10);
                if ($obligated === '' || $obligated > $asOf) {
                    continue;   // not yet obligated on the as-of date
                }
                $paid = substr((string) $r['disbursed_at'], 0, 10);
                if ($paid !== '' && $paid <= $asOf) {
                    continue;   // already paid
                }
                $due = substr((string) ($r['iar_date'] ?: $obligated), 0, 10);
                $days = max(0, (int) ((strtotime($asOf) - strtotime($due)) / 86400));
                $bucket = $days <= 30 ? '0_30' : ($days <= 90 ? '31_90' : ($days <= 365 ? '91_365' : 'over_365'));
                $amt = round((float) $r['amount'], 2);
                $buckets[$bucket] += $amt;

                $rows[] = [
                    'allocation_id' => (int) $r['allocation_id'],
                    'allocation_number' => $r['allocation_number'],
                    'request_referrence_no' => $r['request_referrence_no'],
                    'payee' => $r['vendor_name'],
                    'rc_name' => $r['rc_name'],
                    'program_code' => $r['program_code'],
                    'project_code' => $r['project_code'],
                    'po_number' => $r['po_number'],
                    'dv_no' => $r['dv_no'],
                    'obligated_date' => $obligated,
                    'due_date' => $due,
                    'days_outstanding' => $days,
                    'age_bucket' => $bucket,
                    'amount' => $amt,
                ];
            }
            usort($rows, fn ($a, $b) => $b['days_outstanding'] <=> $a['days_outstanding']);

            $buckets = array_map(fn ($v) => round($v, 2), $buckets);

            return [
                'success' => true, 'as_of' => $asOf, 'data' => $rows,
                'totals' => $buckets + ['total' => round(array_sum($buckets), 2), 'count' => count($rows)],
            ];
        } catch (\Throwable $e) {
            App\LogError('FarReportService::fetchFar3 ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to build FAR No. 3.'];
        }
    }

    /* ═════════════════════════════ FAR 4 ═════════════════════════════ */

    /**
     * Filters: fiscal_year, month (1-12, default this month). Per bank account: NCAs received up to the month's
     * end, the month's disbursements (checks + ADA, by object class), disbursed to date, and the NCA balance.
     * `details` lists every check / ADA of the month.
     */
    public function fetchFar4(array $filters = []): array
    {
        try {
            $fy = $this->fiscalYear($filters);
            $month = $this->intFilter($filters, 'month') ?? (int) date('n');
            if ($month < 1 || $month > 12) {
                return ['success' => false, 'message' => 'month must be 1 to 12.', 'httpCode' => 400];
            }
            $monthStart = sprintf('%04d-%02d-01', $fy, $month);
            $monthEnd = date('Y-m-t', strtotime($monthStart));
            $yearStart = sprintf('%04d-01-01', $fy);

            // Every check / ADA of the year up to the month's end, with its DV's account (for the object class)
            $disbursements = App\ExecuteRows(
                "SELECT 'CHECK' AS mode, c.check_number AS doc_no, c.check_date AS doc_date, c.payee, c.amount,
                        c.bank_account_id, dv.dv_no, dv.account_code
                 FROM fims.checks c
                 LEFT JOIN fims.disbursement_vouchers dv ON dv.dv_id = c.dv_id
                 WHERE c.check_date BETWEEN {$this->q($yearStart)} AND {$this->q($monthEnd)}
                   AND COALESCE(c.status, '') NOT ILIKE 'cancel%' AND COALESCE(c.status, '') NOT ILIKE 'void%'
                 UNION ALL
                 SELECT 'ADA', a.ada_no, a.ada_date, a.payee, a.amount, a.bank_account_id, dv.dv_no, dv.account_code
                 FROM fims.ada_releases a
                 LEFT JOIN fims.disbursement_vouchers dv ON dv.dv_no = a.dv_number
                 WHERE a.ada_date BETWEEN {$this->q($yearStart)} AND {$this->q($monthEnd)}
                   AND COALESCE(a.status, '') NOT ILIKE 'cancel%'
                 ORDER BY 3, 2",
                'DB'
            ) ?: [];

            // NCAs received up to the month's end, per bank account
            $ncas = App\ExecuteRows(
                'SELECT ba.bank_account_id, SUM(ft.amount) AS amount
                 FROM fims.fund_tracking ft
                 LEFT JOIN fims.bank_accounts ba ON ba.account_number = ft.bank_account_no
                 WHERE ft.deleted_at IS NULL AND ft.tracking_type_id = ' . self::TT_NCA . "
                   AND ft.fiscal_year = {$fy} AND ft.date <= {$this->q($monthEnd)}
                 GROUP BY ba.bank_account_id",
                'DB'
            ) ?: [];

            $accounts = [];
            foreach (App\ExecuteRows('SELECT bank_account_id, account_name, account_number, bank_name FROM fims.bank_accounts ORDER BY bank_account_id', 'DB') ?: [] as $b) {
                $accounts[(int) $b['bank_account_id']] = $b + [
                    'nca_received' => 0.0, 'checks' => 0.0, 'ada' => 0.0,
                    'ps' => 0.0, 'mooe' => 0.0, 'co' => 0.0, 'others' => 0.0,
                    'total_month' => 0.0, 'disbursed_to_date' => 0.0,
                ];
            }
            $accounts[0] = ['bank_account_id' => null, 'account_name' => '(No bank account)', 'account_number' => null, 'bank_name' => null]
                + array_fill_keys(['nca_received', 'checks', 'ada', 'ps', 'mooe', 'co', 'others', 'total_month', 'disbursed_to_date'], 0.0);

            foreach ($ncas as $n) {
                $accounts[(int) $n['bank_account_id']]['nca_received'] += (float) $n['amount'];
            }

            $details = [];
            foreach ($disbursements as $d) {
                $id = (int) $d['bank_account_id'];
                if (!isset($accounts[$id])) {
                    $id = 0;
                }
                $amt = (float) $d['amount'];
                $accounts[$id]['disbursed_to_date'] += $amt;
                if ($d['doc_date'] < $monthStart) {
                    continue;   // earlier month: only counts toward "to date"
                }
                $class = strtolower($this->objectClass($d['account_code']));
                $accounts[$id][$d['mode'] === 'CHECK' ? 'checks' : 'ada'] += $amt;
                $accounts[$id][$class] += $amt;
                $accounts[$id]['total_month'] += $amt;
                $details[] = $d + ['object_class' => strtoupper($class) === 'OTHERS' ? 'Others' : strtoupper($class)];
            }

            $rows = [];
            foreach ($accounts as $a) {
                if ($a['nca_received'] == 0 && $a['disbursed_to_date'] == 0) {
                    continue;
                }
                $a['nca_balance'] = $a['nca_received'] - $a['disbursed_to_date'];
                $rows[] = $this->round2($a);
            }

            return [
                'success' => true, 'fiscal_year' => $fy, 'month' => $month, 'period' => [$monthStart, $monthEnd],
                'data' => $rows, 'details' => $details, 'totals' => $this->sumColumns($rows),
            ];
        } catch (\Throwable $e) {
            App\LogError('FarReportService::fetchFar4 ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to build FAR No. 4.'];
        }
    }

    /* ═════════════════════════════ FAR 5 ═════════════════════════════ */

    /**
     * Filters: fiscal_year, quarter (1-4, default this quarter). Per revenue (credited) account: the amount
     * collected in each month of the quarter, the quarter total and the year-to-date total.
     */
    public function fetchFar5(array $filters = []): array
    {
        try {
            $fy = $this->fiscalYear($filters);
            $quarter = $this->intFilter($filters, 'quarter') ?? (int) ceil(date('n') / 3);
            if ($quarter < 1 || $quarter > 4) {
                return ['success' => false, 'message' => 'quarter must be 1 to 4.', 'httpCode' => 400];
            }
            $m1 = ($quarter - 1) * 3 + 1;
            $qEnd = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $fy, $m1 + 2)));

            $rows = App\ExecuteRows(
                "SELECT l.account_code, coa.account_title,
                        SUM(CASE WHEN EXTRACT(MONTH FROM r.or_date) = {$m1} THEN l.amount ELSE 0 END) AS month1,
                        SUM(CASE WHEN EXTRACT(MONTH FROM r.or_date) = " . ($m1 + 1) . " THEN l.amount ELSE 0 END) AS month2,
                        SUM(CASE WHEN EXTRACT(MONTH FROM r.or_date) = " . ($m1 + 2) . " THEN l.amount ELSE 0 END) AS month3,
                        SUM(CASE WHEN EXTRACT(MONTH FROM r.or_date) BETWEEN {$m1} AND " . ($m1 + 2) . " THEN l.amount ELSE 0 END) AS quarter_total,
                        SUM(l.amount) AS year_to_date,
                        COUNT(DISTINCT r.receipt_id) AS receipts
                 FROM fims.official_receipts r
                 LEFT JOIN fims.official_receipt_lines l ON l.receipt_id = r.receipt_id
                 LEFT JOIN fims.chart_of_accounts coa  ON coa.account_code = l.account_code
                 WHERE EXTRACT(YEAR FROM r.or_date) = {$fy} AND r.or_date <= {$this->q($qEnd)}
                 GROUP BY l.account_code, coa.account_title
                 ORDER BY l.account_code",
                'DB'
            ) ?: [];
            $rows = array_map(fn ($r) => $this->round2($r), $rows);

            $months = array_map(fn ($m) => date('M', mktime(0, 0, 0, $m, 1)), [$m1, $m1 + 1, $m1 + 2]);

            return [
                'success' => true, 'fiscal_year' => $fy, 'quarter' => $quarter, 'months' => $months,
                'data' => $rows,
                // an OR with several revenue lines is counted under each account, so count the ORs themselves here
                'totals' => ['receipts' => (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM fims.official_receipts WHERE EXTRACT(YEAR FROM or_date) = {$fy} AND or_date <= {$this->q($qEnd)}",
                    'DB'
                )] + $this->sumColumns($rows),
            ];
        } catch (\Throwable $e) {
            App\LogError('FarReportService::fetchFar5 ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to build FAR No. 5.'];
        }
    }

    /* ═════════════════════════════ Queries ═════════════════════════════ */

    /** Approved budget requests' line items for the year, grouped by $groupBy (columns from brp / brj / coa). */
    private function appropriationRows(int $fy, array $filters, string $groupBy): array
    {
        $where = ["br.fiscal_yr = {$fy}", "st.name = 'Approved'"];
        if ($rc = $this->rcWhere($filters, 'br.rc_id')) {
            $where[] = $rc;
        }
        $names = str_contains($groupBy, 'program_code')
            ? ', MAX(pg.program_name) AS program_name, MAX(pj.project_name) AS project_name'
            : '';

        return App\ExecuteRows(
            "SELECT {$groupBy}{$names}, SUM(COALESCE(li.suggested_amount, li.proposed_amount, 0)) AS amount
             FROM fims.budget_req br
             LEFT JOIN fims.settings_status st     ON st.id = br.status_id
             LEFT JOIN fims.budget_req_program brp ON brp.budget_reference_number = br.budget_reference_number
             LEFT JOIN fims.budget_req_project brj ON brj.budget_req_program_id = brp.budget_req_program_id
             LEFT JOIN fims.budget_line_items li   ON li.budget_req_project_id = brj.budget_req_project_id
             LEFT JOIN fims.chart_of_accounts coa  ON coa.account_id = li.account_id
             LEFT JOIN fims.program pg             ON pg.program_code = brp.program_code
             LEFT JOIN fims.project pj             ON pj.project_code = brj.project_code
             WHERE " . implode(' AND ', $where) . "
             GROUP BY {$groupBy}",
            'DB'
        ) ?: [];
    }

    /**
     * Approved RFAs / LAAs of the year, one row each: amount, obligation date, Treasury release date of its DV.
     * $withDetails adds the payee, RC, DV number and IAR date (FAR 3).
     */
    private function allocationRows(string $type, ?int $fy, array $filters, bool $withDetails = false): array
    {
        $details = $withDetails
            ? ', a.allocation_number, a.request_referrence_no, a.po_number, v.vendor_name, rc.rc_name, dv.dv_no, iar.iar_date'
            : '';

        return App\ExecuteRows(
            "SELECT a.allocation_id, bap.program_code, bap.project_code, pg.program_name, pj.project_name,
                    {$this->amountSql('bap')} AS amount,
                    COALESCE(ok.changed_at, a.updated_at, a.created_at) AS obligated_at,
                    dv.treasury_released_at AS disbursed_at{$details}
             FROM fims.budget_allocation a
             {$this->allocationJoins()}
             WHERE {$this->allocationWhere($type, $fy, $filters)}
             ORDER BY a.allocation_id, bap.sort_order",
            'DB'
        ) ?: [];
    }

    /** Approved RFAs / LAAs of the year, one row per line_transaction line, with its account (FAR 1-A). */
    private function allocationLineRows(string $type, int $fy, array $filters): array
    {
        return App\ExecuteRows(
            "SELECT ln.value->>'account_code' AS account_code,
                    COALESCE(coa.account_title, ln.value->>'account_title') AS account_title,
                    {$this->numericSql("ln.value->'requested_amount'")} AS amount,
                    COALESCE(ok.changed_at, a.updated_at, a.created_at) AS obligated_at,
                    dv.treasury_released_at AS disbursed_at
             FROM fims.budget_allocation a
             {$this->allocationJoins()}
             CROSS JOIN LATERAL jsonb_array_elements({$this->linesSql('bap')}) AS ln(value)
             LEFT JOIN fims.chart_of_accounts coa ON coa.account_code = ln.value->>'account_code'
             WHERE {$this->allocationWhere($type, $fy, $filters)} AND jsonb_typeof(ln.value) = 'object'",
            'DB'
        ) ?: [];
    }

    /** The LEFT JOINs every allocation query shares. */
    private function allocationJoins(): string
    {
        // the RFA / LAA project blocks (program, project and line_transaction moved there from budget_allocation)
        return "LEFT JOIN fims.settings_status st      ON st.id = a.status_id
             LEFT JOIN fims.budget_allocation_project bap ON bap.allocation_id = a.allocation_id AND bap.deleted_at IS NULL
             LEFT JOIN fims.program pg               ON pg.program_code = bap.program_code
             LEFT JOIN fims.project pj               ON pj.project_code = bap.project_code
             LEFT JOIN fims.vendors v                ON v.vendor_id = a.vendor_id
             LEFT JOIN fims.responsibility_code rc   ON rc.rc_id = a.requesting_rc_id
             LEFT JOIN fims.disbursement_vouchers dv ON dv.dv_id = a.dv_id
             LEFT JOIN LATERAL (
                 SELECT MAX(l.changed_at) AS changed_at FROM fims.status_change_log l
                 WHERE l.status_for = 'budget_allocation' AND l.record_id = a.allocation_id::text AND l.status = 'Approved'
             ) ok ON TRUE
             LEFT JOIN LATERAL (
                 SELECT MIN(i.created_at) AS iar_date FROM fims.inspection_acceptance_report i
                 WHERE a.po_number IS NOT NULL AND i.po_number = a.po_number
             ) iar ON TRUE";
    }

    private function allocationWhere(string $type, ?int $fy, array $filters): string
    {
        $where = [
            'a.deleted_at IS NULL',
            'UPPER(a.allocation_type) = ' . $this->q($type),
            "st.name = 'Approved'",
        ];
        if ($fy !== null) {   // null = every year (FAR 3)
            $where[] = "a.fiscal_year = {$fy}";
        }
        if ($rc = $this->rcWhere($filters, 'a.requesting_rc_id')) {
            $where[] = $rc;
        }

        return implode(' AND ', $where);
    }

    /** Total of the year's SAROs (fund_tracking type 1). */
    private function saroTotal(int $fy): float
    {
        return round((float) App\ExecuteScalar(
            'SELECT COALESCE(SUM(amount), 0) FROM fims.fund_tracking WHERE deleted_at IS NULL AND tracking_type_id = ' . self::TT_SARO . " AND fiscal_year = {$fy}",
            'DB'
        ), 2);
    }

    /* ═════════════════════════════ Helpers ═════════════════════════════ */

    /** line_transaction as a JSON array (an object becomes a one-item array, anything else an empty one). */
    private function linesSql(string $alias): string
    {
        return "CASE jsonb_typeof({$alias}.line_transaction)
                    WHEN 'array'  THEN {$alias}.line_transaction
                    WHEN 'object' THEN jsonb_build_array({$alias}.line_transaction)
                    ELSE '[]'::jsonb END";
    }

    /** A JSON number or numeric string as numeric (commas ignored), else 0. */
    private function numericSql(string $json): string
    {
        return "CASE WHEN jsonb_typeof({$json}) = 'number' THEN ({$json})::text::numeric
                     WHEN jsonb_typeof({$json}) = 'string' AND REPLACE({$json} #>> '{}', ',', '') ~ '^-?[0-9]+([.][0-9]+)?$'
                       THEN REPLACE({$json} #>> '{}', ',', '')::numeric
                     ELSE 0 END";
    }

    /** Sum of the line_transaction "requested_amount"s of the allocation aliased $alias. */
    private function amountSql(string $alias): string
    {
        return "COALESCE((SELECT SUM({$this->numericSql("ln.value->'requested_amount'")})
                          FROM jsonb_array_elements({$this->linesSql($alias)}) AS ln(value)
                          WHERE jsonb_typeof(ln.value) = 'object'), 0)";
    }

    /** PS / MOOE / CO / Others from an account code (e.g. 5-01-01-010 = PS). */
    private function objectClass(?string $code): string
    {
        $code = (string) $code;

        return match (true) {
            str_starts_with($code, '5-01') => 'PS',
            str_starts_with($code, '5-02'), str_starts_with($code, '5-03') => 'MOOE',
            str_starts_with($code, '1-06'), str_starts_with($code, '1-07') => 'CO',
            default => 'Others',
        };
    }

    /** Adds the FAR balance columns to a row with appropriation / allotment / obligation / disbursement. */
    private function withBalances(array $r): array
    {
        $r['unreleased_appropriation'] = $r['appropriation'] - $r['allotment'];
        $r['unobligated_allotment'] = $r['allotment'] - $r['obligation'];
        $r['unpaid_obligation'] = $r['obligation'] - $r['disbursement'];

        return $this->round2($r);
    }

    /** Sum of every numeric (float) column over $rows. */
    private function sumColumns(array $rows): array
    {
        $totals = [];
        foreach ($rows as $r) {
            foreach ($r as $k => $v) {
                if (is_float($v)) {
                    $totals[$k] = ($totals[$k] ?? 0) + $v;
                }
            }
        }

        return array_map(fn ($v) => round($v, 2), $totals);
    }

    /** Rounds the amount columns of a row to 2 decimals (and makes numeric DB strings floats). */
    private function round2(array $r): array
    {
        $text = ['account_code', 'account_title', 'account_name', 'account_number', 'bank_name', 'program_code', 'program_name', 'project_code', 'project_name', 'object_class', 'bank_account_id'];
        foreach ($r as $k => $v) {
            if (!in_array($k, $text, true) && is_numeric($v)) {
                $r[$k] = round((float) $v, 2);
            }
        }

        return $r;
    }

    /** "rc IN (that RC and the RCs under it)" for the rc_id filter set by the route, or '' for every RC. */
    private function rcWhere(array $filters, string $column): string
    {
        $rcId = trim((string) ($filters['rc_id'] ?? ''));
        if ($rcId === '') {
            return '';
        }
        $ids = GlobalService::getRcIdsInScope($rcId) ?: [$rcId];

        return $column . ' IN (' . implode(', ', array_map(fn ($id) => $this->q($id), $ids)) . ')';
    }

    /** 1-based quarter of a date if it falls in fiscal year $fy, else 0. */
    private function quarterOf(?string $date, int $fy): int
    {
        if (!$date || (int) substr($date, 0, 4) !== $fy) {
            return 0;
        }

        return (int) ceil((int) substr($date, 5, 2) / 3);
    }

    private function fiscalYear(array $filters): int
    {
        return $this->intFilter($filters, 'fiscal_year') ?? (int) date('Y');
    }

    private function dateFilter(array $filters, string $key): ?string
    {
        $v = trim((string) ($filters[$key] ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
    }

    private function intFilter(array $filters, string $key): ?int
    {
        $v = $filters[$key] ?? null;

        return ($v !== null && $v !== '' && is_numeric($v)) ? (int) $v : null;
    }

    private function q(string $value): string
    {
        return App\QuotedValue($value, DataType::STRING, 'DB');
    }
}
