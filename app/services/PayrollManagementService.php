<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Payroll Management (page app/pages/payroll-page.php, routes app/routes/payroll_management.php).
 *
 * IMPORT ONLY: brings in the monthly employee payroll (the Regular FIMS MONTHLY PAYROLL Excel template) and lists one
 * summary row per imported month (Payroll Month, Year, Import Date, Status, Total Payroll Amount = gross).
 * No DV is created here — a payroll DV is prepared in the DV module from an imported month.
 *
 * Writes into the payroll tables of the Payroll module (no tables of its own):
 *   fims.payroll_periods       one per month (fiscal_yr + payroll_month); created when missing; prepared_at/by = import date/user
 *   fims.payroll_employees     employees not in the directory yet are added (existing ones are left as they are)
 *   fims.payroll_records       one per employee per month, with the category totals (gross / net are generated from them)
 *   fims.payroll_record_items  one per employee per payroll item with an amount
 *
 * Column mapping: every amount column of the template is matched to a Payroll Setup item (fims.payroll_items.item_code,
 * compared without the template's surrounding quotes, case-insensitive). The item's category decides what the amount is
 * (EARNING / DEDUCTION / REFUND / EMPLOYER_SHARE) and its account is snapshotted on the line. A column with amounts but
 * no ACTIVE item stops the import, so the totals are never silently wrong — add the item in Payroll Setup first.
 * Identity columns (1INCOME.* / 2INCOME.*) and Excel's "Expr…" helper columns are not amounts.
 */
class PayrollManagementService
{
    private string $tPeriod = 'fims.payroll_periods';
    private string $tRecord = 'fims.payroll_records';
    private string $tLine   = 'fims.payroll_record_items';
    private string $tEmp    = 'fims.payroll_employees';
    private string $tItem   = 'fims.payroll_items';
    private string $tDv     = 'fims.disbursement_vouchers';
    private string $tSt     = 'fims.settings_status';

    private const SHEET_NAME = 'MONTHLY PAYROLL';
    private const MONTHS = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    private const COL_EMPNO = '1INCOME.EMPNO';
    private const COL_DEPT  = '1INCOME.DEPTCD';
    private const COL_LNAME = '1INCOME.LNAME';
    private const COL_FNAME = '1INCOME.FNAME';
    private const COL_MI    = '1INCOME.MI';

    // record total column per item category
    private const TOTAL_COL = [
        'EARNING'        => 'total_earnings',
        'DEDUCTION'      => 'total_deductions',
        'REFUND'         => 'total_refunds',
        'EMPLOYER_SHARE' => 'total_employer_share',
    ];

    // ═══ Summary list ══════════════════════════════════════════════════════════════════════

    /** One row per imported month (newest first) + KPIs + the years that have imports. Filters: year, status. */
    public function fetchList(array $filters = []): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT p.payroll_period_id, p.fiscal_yr, p.payroll_month, p.period_name, p.status AS period_status,
                        COALESCE(p.prepared_at, MIN(r.created_at)) AS imported_at, u.username AS imported_by_name,
                        COUNT(r.payroll_record_id) AS employee_count,
                        COALESCE(SUM(r.gross_amount), 0) AS total_gross,
                        COALESCE(SUM(r.total_deductions), 0) AS total_deductions,
                        COALESCE(SUM(r.total_refunds), 0) AS total_refunds,
                        COALESCE(SUM(r.net_amount), 0) AS total_net,
                        COALESCE(SUM(r.total_employer_share), 0) AS total_employer_share,
                        COUNT(dv.dv_id) FILTER (WHERE COALESCE(st.name, '') <> 'CANCELLED') AS linked_count,
                        STRING_AGG(DISTINCT dv.dv_no, ', ') FILTER (WHERE COALESCE(st.name, '') <> 'CANCELLED') AS dv_nos,
                        MIN(dv.dv_id) FILTER (WHERE COALESCE(st.name, '') <> 'CANCELLED') AS dv_id,
                        STRING_AGG(DISTINCT st.name, ', ') FILTER (WHERE COALESCE(st.name, '') <> 'CANCELLED') AS dv_statuses
                   FROM {$this->tPeriod} p
                   JOIN {$this->tRecord} r ON r.payroll_period_id = p.payroll_period_id
                   LEFT JOIN {$this->tDv} dv ON dv.dv_id = r.disbursement_voucher_id
                   LEFT JOIN {$this->tSt} st ON st.id = dv.status_id
                   LEFT JOIN uac.users u ON u.user_id = p.prepared_by
                  " . (ctype_digit((string) ($filters['year'] ?? '')) ? 'WHERE p.fiscal_yr = ' . (int) $filters['year'] : '') . '
                  GROUP BY p.payroll_period_id, u.username
                  ORDER BY p.fiscal_yr DESC, p.payroll_month DESC',
                'DB'
            ) ?: [];

            $out = [];
            foreach ($rows as $r) {
                $count  = (int) $r['employee_count'];
                $linked = (int) $r['linked_count'];
                $r['import_status'] = $linked === 0 ? 'IMPORTED' : ($linked >= $count ? 'DV_CREATED' : 'PARTIAL_DV');
                $r['month_name'] = self::MONTHS[(int) $r['payroll_month']] ?? (string) $r['period_name'];
                if (in_array($filters['status'] ?? '', ['IMPORTED', 'DV_CREATED', 'PARTIAL_DV'], true) && $r['import_status'] !== $filters['status']) {
                    continue;
                }
                $out[] = $r;
            }
            $years = array_map('intval', array_column(App\ExecuteRows(
                "SELECT DISTINCT p.fiscal_yr FROM {$this->tPeriod} p WHERE EXISTS (SELECT 1 FROM {$this->tRecord} r WHERE r.payroll_period_id = p.payroll_period_id) ORDER BY 1 DESC",
                'DB'
            ) ?: [], 'fiscal_yr'));

            return ['success' => true, 'data' => $out, 'years' => $years, 'kpis' => [
                'imports'     => count($out),
                'total_gross' => round(array_sum(array_map('floatval', array_column($out, 'total_gross'))), 2),
                'total_net'   => round(array_sum(array_map('floatval', array_column($out, 'total_net'))), 2),
                'without_dv'  => count(array_filter($out, fn ($r) => $r['import_status'] !== 'DV_CREATED')),
            ]];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::fetchList ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the imported payrolls.'];
        }
    }

    // ═══ Employee records of an imported month (the "View" of a summary row) ═════════════

    /** The employees of one imported month with their totals; search (employee no. / name), page, limit. */
    public function fetchRecords(int $periodId, array $q = []): array
    {
        try {
            $p = App\ExecuteRow("SELECT payroll_period_id, fiscal_yr, payroll_month, period_name, status FROM {$this->tPeriod} WHERE payroll_period_id = {$periodId}", 'DB');
            if (!$p) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'Payroll month not found.'];
            }
            $where = ["r.payroll_period_id = {$periodId}"];
            $search = trim((string) ($q['search'] ?? ''));
            if ($search !== '') {
                $s = $this->str('%' . $search . '%');
                $where[] = "(e.employee_no ILIKE {$s} OR e.full_name ILIKE {$s} OR e.department ILIKE {$s})";
            }
            $w     = implode(' AND ', $where);
            $from  = "FROM {$this->tRecord} r JOIN {$this->tEmp} e ON e.employee_id = r.employee_id
                      LEFT JOIN {$this->tDv} dv ON dv.dv_id = r.disbursement_voucher_id WHERE {$w}";
            $total = (int) App\ExecuteScalar("SELECT COUNT(*) {$from}", 'DB');
            $limit = max(5, min(200, (int) ($q['limit'] ?? 25)));
            $page  = max(1, (int) ($q['page'] ?? 1));
            $rows  = App\ExecuteRows(
                "SELECT r.payroll_record_id, e.employee_no, e.last_name, e.first_name, e.middle_name, e.department, e.position,
                        r.gross_amount, r.total_deductions, r.total_refunds, r.net_amount, r.total_employer_share, r.status, dv.dv_no
                 {$from}
                 ORDER BY e.last_name, e.first_name, e.employee_no
                 LIMIT {$limit} OFFSET " . (($page - 1) * $limit),
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows,
                'period' => ['payroll_period_id' => (int) $p['payroll_period_id'], 'label' => (self::MONTHS[(int) $p['payroll_month']] ?? '') . ' ' . $p['fiscal_yr'], 'status' => $p['status']],
                'pagination' => ['total' => $total, 'per_page' => $limit, 'current_page' => $page, 'last_page' => (int) ceil($total / $limit)]];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::fetchRecords ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the employee records.'];
        }
    }

    /** One employee's amounts for the month, per payroll item (category, item, account, amount). */
    public function fetchRecordItems(int $recordId): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT i.item_code, i.item_name, i.category, ri.account_code, c.account_title, ri.amount
                   FROM {$this->tLine} ri
                   JOIN {$this->tItem} i ON i.payroll_item_id = ri.payroll_item_id
                   LEFT JOIN fims.chart_of_accounts c ON c.account_code = ri.account_code
                  WHERE ri.payroll_record_id = {$recordId}
                  ORDER BY CASE i.category WHEN 'EARNING' THEN 1 WHEN 'REFUND' THEN 2 WHEN 'DEDUCTION' THEN 3 ELSE 4 END, i.sort_order, i.item_code",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::fetchRecordItems ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the payroll items of this employee.'];
        }
    }

    // ═══ Year-to-Date report ══════════════════════════════════════════════════════════════

    /**
     * Year-to-Date report: one row per imported month of the fiscal year (no employee details) — employees paid, gross,
     * deductions, refunds, net, employer share — plus the totals. Filters: year (required); month (optional: that one
     * month only, else the whole year).
     */
    public function fetchYtd(array $q): array
    {
        try {
            $year = (int) ($q['year'] ?? 0);
            if ($year < 2000 || $year > 2100) {
                return ['success' => false, 'message' => 'Choose the fiscal year.'];
            }
            $where = ["p.fiscal_yr = {$year}"];
            $month = (int) ($q['month'] ?? 0);
            if ($month >= 1 && $month <= 12) {
                $where[] = "p.payroll_month = {$month}";
            }
            if (($q['detail'] ?? '') === 'employees') {
                return $this->ytdEmployees($where);   // Export CSV: every employee of the same period
            }
            $rows = App\ExecuteRows(
                "SELECT p.payroll_month, COUNT(DISTINCT r.employee_id) AS employees,
                        SUM(r.gross_amount) AS gross, SUM(r.total_deductions) AS deductions,
                        SUM(r.total_refunds) AS refunds, SUM(r.net_amount) AS net,
                        SUM(r.total_employer_share) AS employer_share
                   FROM {$this->tRecord} r
                   JOIN {$this->tPeriod} p ON p.payroll_period_id = r.payroll_period_id
                  WHERE " . implode(' AND ', $where) . '
                  GROUP BY p.payroll_month
                  ORDER BY p.payroll_month',
                'DB'
            ) ?: [];
            foreach ($rows as &$r) {
                $r['payroll_month'] = (int) $r['payroll_month'];
                $r['month_name']    = self::MONTHS[$r['payroll_month']];
                $r['employees']     = (int) $r['employees'];
                foreach (['gross', 'deductions', 'refunds', 'net', 'employer_share'] as $k) {
                    $r[$k] = round((float) $r[$k], 2);
                }
            }
            unset($r);
            $sum = fn (string $k) => round(array_sum(array_column($rows, $k)), 2);
            // employees paid in the period (one person counted once across the months)
            $employees = (int) App\ExecuteScalar(
                "SELECT COUNT(DISTINCT r.employee_id) FROM {$this->tRecord} r
                   JOIN {$this->tPeriod} p ON p.payroll_period_id = r.payroll_period_id
                  WHERE " . implode(' AND ', $where),
                'DB'
            );
            $months = array_map('intval', array_column(App\ExecuteRows(
                "SELECT DISTINCT p.payroll_month FROM {$this->tPeriod} p
                  WHERE p.fiscal_yr = {$year} AND EXISTS (SELECT 1 FROM {$this->tRecord} r WHERE r.payroll_period_id = p.payroll_period_id) ORDER BY 1",
                'DB'
            ) ?: [], 'payroll_month'));

            return ['success' => true, 'data' => $rows,
                'months_imported' => array_map(fn ($m) => self::MONTHS[$m], $months),
                'totals' => ['months' => count($rows), 'employees' => $employees, 'gross' => $sum('gross'), 'deductions' => $sum('deductions'),
                    'refunds' => $sum('refunds'), 'net' => $sum('net'), 'employer_share' => $sum('employer_share')]];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::fetchYtd ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to build the Year-to-Date report.'];
        }
    }

    /**
     * The Year-to-Date CSV: one row per employee paid in the period (the whole year or the one month) — employee details
     * and their totals — plus the totals. $where is fetchYtd()'s period filter (alias p).
     */
    private function ytdEmployees(array $where): array
    {
        $rows = App\ExecuteRows(
            "SELECT e.employee_no, e.last_name, e.first_name, e.middle_name, e.position, e.department, e.employment_status,
                    e.tin, e.pagibig_no, e.philhealth_no,
                    COUNT(DISTINCT p.payroll_period_id) AS months,
                    SUM(r.gross_amount) AS ytd_gross, SUM(r.total_deductions) AS ytd_deductions,
                    SUM(r.total_refunds) AS ytd_refunds, SUM(r.net_amount) AS ytd_net,
                    SUM(r.total_employer_share) AS ytd_employer_share
               FROM {$this->tRecord} r
               JOIN {$this->tPeriod} p ON p.payroll_period_id = r.payroll_period_id
               JOIN {$this->tEmp} e ON e.employee_id = r.employee_id
              WHERE " . implode(' AND ', $where) . '
              GROUP BY e.employee_id
              ORDER BY e.last_name, e.first_name, e.employee_no',
            'DB'
        ) ?: [];
        $sum = fn (string $k) => round(array_sum(array_map('floatval', array_column($rows, $k))), 2);

        return ['success' => true, 'data' => $rows,
            'totals' => ['employees' => count($rows), 'ytd_gross' => $sum('ytd_gross'), 'ytd_deductions' => $sum('ytd_deductions'),
                'ytd_refunds' => $sum('ytd_refunds'), 'ytd_net' => $sum('ytd_net'), 'ytd_employer_share' => $sum('ytd_employer_share')]];
    }

    // ═══ Tax annualization (year-end tax on compensation, BIR 2316) ═══════════════════════════

    // 13th month and other benefits: exempt up to ₱90,000 / year. The de minimis limits are data, not constants —
    // fims.payroll_de_minimis_benefits (01 - table/57), editable on Payroll Setup; see deMinimisLimits().
    private const BENEFIT_CAP = 90000.00;
    // TRAIN graduated income tax table (2023 onward): [over, base tax, rate on the excess over `over`]
    private const TAX_TABLE = [[8000000, 2202500, 0.35], [2000000, 402500, 0.30], [800000, 102500, 0.25],
                               [400000, 22500, 0.20], [250000, 0, 0.15]];
    private const PAY_TREATMENTS = ['TAXABLE', 'NON_TAXABLE', 'DE_MINIMIS', 'BENEFIT_90K'];

    /**
     * The de minimis benefits and their yearly limits: [de_minimis_id => {code, name, limit_amount, limit_per, annual_limit}]
     * (annual_limit = limit_amount × 12 for a MONTH limit). An INACTIVE benefit, or a de minimis item linked to none, has
     * no exemption — all of it goes to the ₱90,000 benefits.
     */
    private function deMinimisLimits(): array
    {
        $out = [];
        foreach (App\ExecuteRows("SELECT de_minimis_id, code, name, limit_amount, limit_per FROM fims.payroll_de_minimis_benefits
                                   WHERE status = 'ACTIVE' ORDER BY sort_order, name", 'DB') ?: [] as $r) {
            $amt = round((float) $r['limit_amount'], 2);
            $out[(int) $r['de_minimis_id']] = ['code' => $r['code'], 'name' => $r['name'], 'limit_amount' => $amt, 'limit_per' => $r['limit_per'],
                'annual_limit' => round($r['limit_per'] === 'MONTH' ? $amt * 12 : $amt, 2)];
        }

        return $out;
    }

    /** [total paid, total exempt] of an employee's de minimis amounts per benefit ([de_minimis_id|0 => amount]). */
    private static function deMinimisExempt(array $byBenefit, array $limits): array
    {
        $paid = $exempt = 0.0;
        foreach ($byBenefit as $id => $amt) {
            $amt   = max(0.0, (float) $amt);
            $paid += $amt;
            $exempt += min($amt, $limits[$id]['annual_limit'] ?? 0.0);
        }

        return [round($paid, 2), round($exempt, 2)];
    }

    /** Annual tax due on a taxable compensation income (TAX_TABLE). */
    public static function annualTax(float $taxable): float
    {
        foreach (self::TAX_TABLE as [$over, $base, $rate]) {
            if ($taxable > $over) {
                return round($base + ($taxable - $over) * $rate, 2);
            }
        }

        return 0.0;
    }

    /**
     * Annualize a fiscal year: from every imported payroll line of the year (its Payroll Setup item's tax_treatment, signed
     * by its category — see 01 - table/48), each employee's gross, non-taxable, taxable income, tax due and the
     * adjustment against the tax withheld, stored in fims.payroll_annualization (the year's rows are replaced; see
     * 01 - table/56). The December adjustment is shown, not applied — the payroll is imported, not computed here.
     */
    public function annualize(int $year, int $userId): array
    {
        if ($year < 2000 || $year > 2100) {
            return ['success' => false, 'message' => 'Choose the fiscal year.'];
        }
        try {
            $lines = App\ExecuteRows(
                "SELECT r.employee_id, i.category, i.tax_treatment, COALESCE(i.de_minimis_id, 0) AS de_minimis_id, SUM(li.amount) AS amount
                   FROM {$this->tLine} li
                   JOIN {$this->tRecord} r ON r.payroll_record_id = li.payroll_record_id
                   JOIN {$this->tPeriod} p ON p.payroll_period_id = r.payroll_period_id
                   JOIN {$this->tItem} i ON i.payroll_item_id = li.payroll_item_id
                  WHERE p.fiscal_yr = {$year}
                  GROUP BY r.employee_id, i.category, i.tax_treatment, 4",
                'DB'
            ) ?: [];
            if (!$lines) {
                return ['success' => false, 'message' => "No payroll imported for {$year} — nothing to annualize."];
            }
            $months = [];
            foreach (App\ExecuteRows(
                "SELECT r.employee_id, COUNT(DISTINCT p.payroll_period_id) AS months FROM {$this->tRecord} r
                   JOIN {$this->tPeriod} p ON p.payroll_period_id = r.payroll_period_id WHERE p.fiscal_yr = {$year} GROUP BY r.employee_id",
                'DB'
            ) ?: [] as $m) {
                $months[(int) $m['employee_id']] = (int) $m['months'];
            }

            // per employee: the signed sum per tax treatment
            $emp = [];
            foreach ($lines as $l) {
                $t   = (string) $l['tax_treatment'];
                $amt = (float) $l['amount'];
                $pay = in_array($t, self::PAY_TREATMENTS, true);
                $sign = match ((string) $l['category']) {
                    'EARNING'   => $pay ? 1 : 0,
                    'DEDUCTION' => $pay ? -1 : (in_array($t, ['CONTRIBUTION', 'WITHHOLDING_TAX'], true) ? 1 : 0),
                    'REFUND'    => $pay ? 1 : (in_array($t, ['CONTRIBUTION', 'WITHHOLDING_TAX'], true) ? -1 : 0),
                    default     => 0,
                };
                if ($sign === 0 || $t === 'NONE') {
                    continue;
                }
                $id = (int) $l['employee_id'];
                if ($t === 'DE_MINIMIS') {   // per benefit — each has its own limit
                    $dm = (int) $l['de_minimis_id'];
                    $emp[$id]['DE_MINIMIS'][$dm] = ($emp[$id]['DE_MINIMIS'][$dm] ?? 0.0) + $sign * $amt;
                    continue;
                }
                $emp[$id][$t] = ($emp[$id][$t] ?? 0.0) + $sign * $amt;
            }

            $limits = $this->deMinimisLimits();
            $rows = [];
            foreach ($emp as $id => $s) {
                $get        = static fn (string $k) => round(max(0.0, (float) ($s[$k] ?? 0.0)), 2);
                $taxablePay = $get('TAXABLE');
                $nonTaxPay  = $get('NON_TAXABLE');
                [$deMinPaid, $deMinExempt] = self::deMinimisExempt($s['DE_MINIMIS'] ?? [], $limits);
                $benefits   = round($get('BENEFIT_90K') + ($deMinPaid - $deMinExempt), 2);
                $benExempt  = round(min($benefits, self::BENEFIT_CAP), 2);
                $contrib    = $get('CONTRIBUTION');
                $gross      = round($taxablePay + $nonTaxPay + $deMinPaid + $get('BENEFIT_90K'), 2);
                $nonTax     = round($nonTaxPay + $deMinExempt + $benExempt + $contrib, 2);
                $taxable    = round(max(0.0, $gross - $nonTax), 2);
                $taxDue     = self::annualTax($taxable);
                $withheld   = round($s['WITHHOLDING_TAX'] ?? 0.0, 2);
                $adj        = round($taxDue - $withheld, 2);
                $rows[] = [
                    'employee_id' => $id, 'months_paid' => $months[$id] ?? 0,
                    'taxable_pay' => $taxablePay, 'non_taxable_pay' => $nonTaxPay, 'de_minimis' => $deMinPaid,
                    'de_minimis_exempt' => $deMinExempt, 'benefits' => $benefits, 'benefits_exempt' => $benExempt,
                    'contributions' => $contrib, 'gross_compensation' => $gross, 'total_non_taxable' => $nonTax,
                    'taxable_income' => $taxable, 'tax_due' => $taxDue, 'tax_withheld' => $withheld, 'tax_adjustment' => $adj,
                    'status' => abs($adj) < 1 ? 'CORRECT' : ($adj > 0 ? 'UNDERPAID' : 'OVERPAID'),
                ];
            }

            App\Execute('BEGIN', 'DB');
            try {
                App\Execute("DELETE FROM fims.payroll_annualization WHERE fiscal_yr = {$year}", 'DB');
                foreach ($rows as $r) {
                    $cols = array_keys($r);
                    $vals = array_map(static fn ($v) => is_string($v) ? "'" . $v . "'" : (string) $v, array_values($r));
                    App\Execute('INSERT INTO fims.payroll_annualization (fiscal_yr, ' . implode(', ', $cols) . ', computed_by, computed_at)
                                 VALUES (' . $year . ', ' . implode(', ', $vals) . ', ' . ($userId ?: 'NULL') . ', CURRENT_TIMESTAMP)', 'DB');
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            $count = fn (string $st) => count(array_filter($rows, static fn ($r) => $r['status'] === $st));

            return ['success' => true, 'message' => count($rows) . " employee(s) annualized for {$year}: "
                . $count('UNDERPAID') . ' underpaid, ' . $count('OVERPAID') . ' overpaid, ' . $count('CORRECT') . ' correct.'];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::annualize ' . $e->getMessage());

            return ['success' => false, 'message' => 'The annualization failed.'];
        }
    }

    /**
     * The stored annualization of a year (fims.payroll_annualization + the employee), with totals and when it was last
     * run — and how many lines of the year belong to items with no tax treatment yet (a hint to check Payroll Setup).
     */
    public function fetchAnnualization(int $year): array
    {
        if ($year < 2000 || $year > 2100) {
            return ['success' => false, 'message' => 'Choose the fiscal year.'];
        }
        try {
            $rows = App\ExecuteRows(
                "SELECT a.*, e.employee_no, e.last_name, e.first_name, e.middle_name, e.tin, e.position, e.department
                   FROM fims.payroll_annualization a
                   JOIN {$this->tEmp} e ON e.employee_id = a.employee_id
                  WHERE a.fiscal_yr = {$year}
                  ORDER BY e.last_name, e.first_name, e.employee_no",
                'DB'
            ) ?: [];
            $money = ['taxable_pay', 'non_taxable_pay', 'de_minimis', 'de_minimis_exempt', 'benefits', 'benefits_exempt', 'contributions',
                      'gross_compensation', 'total_non_taxable', 'taxable_income', 'tax_due', 'tax_withheld', 'tax_adjustment'];
            foreach ($rows as &$r) {
                foreach ($money as $k) {
                    $r[$k] = round((float) $r[$k], 2);
                }
                $r['months_paid'] = (int) $r['months_paid'];
            }
            unset($r);
            $sum = fn (string $k) => round(array_sum(array_column($rows, $k)), 2);
            $untreated = (int) App\ExecuteScalar(
                "SELECT COUNT(DISTINCT i.payroll_item_id) FROM {$this->tLine} li
                   JOIN {$this->tRecord} r ON r.payroll_record_id = li.payroll_record_id
                   JOIN {$this->tPeriod} p ON p.payroll_period_id = r.payroll_period_id
                   JOIN {$this->tItem} i ON i.payroll_item_id = li.payroll_item_id
                  WHERE p.fiscal_yr = {$year} AND i.category IN ('EARNING', 'REFUND') AND i.tax_treatment = 'NONE'",
                'DB'
            );

            return ['success' => true, 'data' => $rows,
                'computed_at' => $rows ? max(array_column($rows, 'computed_at')) : null,
                'untreated_items' => $untreated,
                'totals' => ['employees' => count($rows), 'gross_compensation' => $sum('gross_compensation'), 'total_non_taxable' => $sum('total_non_taxable'),
                    'taxable_income' => $sum('taxable_income'), 'tax_due' => $sum('tax_due'), 'tax_withheld' => $sum('tax_withheld'),
                    'tax_adjustment' => $sum('tax_adjustment'),
                    'underpaid' => count(array_filter($rows, static fn ($r) => $r['status'] === 'UNDERPAID')),
                    'overpaid' => count(array_filter($rows, static fn ($r) => $r['status'] === 'OVERPAID'))]];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::fetchAnnualization ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the annualization.'];
        }
    }

    /**
     * One employee's ANNUALIZATION WORKSHEET for a year — the client's "Annualization of Income" template (EMP ANZN sheet),
     * built from the year's payroll lines (same tax treatments, signs and ceilings as annualize()):
     *   months     — per payroll month: basic (TAXABLE pay), tax withheld, GSIS, HDMF, PHIC, union dues (the CONTRIBUTION
     *                lines, told apart by account: 2-02-01-020 GSIS, -030 Pag-IBIG, -040 PhilHealth, anything else union
     *                dues) and the net (basic − tax − contributions);
     *   benefits   — each 13th month / other-benefit item's year total (BENEFIT_90K) + the de minimis excess;
     *   de_minimis — rice / uniform: actual, ceiling, excess;
     *   computation— basic less contributions, benefits over ₱90,000, gross taxable income, tax due (TRAIN), tax withheld,
     *                tax due / (refund).
     * PERA (NON_TAXABLE) is left out of it, as in the client's sheet (exempt in full, it doesn't change the result).
     * Returns ['success' => false, 'message'] when the employee has no payroll in that year.
     */
    public function annualizationWorksheet(int $year, int $employeeId): array
    {
        if ($year < 2000 || $year > 2100 || $employeeId <= 0) {
            return ['success' => false, 'message' => 'Choose the fiscal year and the employee.'];
        }
        $emp = App\ExecuteRow("SELECT employee_id, employee_no, last_name, first_name, middle_name, department, position, tin FROM {$this->tEmp} WHERE employee_id = {$employeeId}", 'DB');
        if (!$emp) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }
        $lines = App\ExecuteRows(
            "SELECT p.payroll_month, i.item_code, i.item_name, i.category, i.tax_treatment,
                    COALESCE(NULLIF(li.account_code, ''), i.account_code, '') AS account_code, SUM(li.amount) AS amount,
                    COALESCE(i.de_minimis_id, 0) AS de_minimis_id
               FROM {$this->tLine} li
               JOIN {$this->tRecord} r ON r.payroll_record_id = li.payroll_record_id
               JOIN {$this->tPeriod} p ON p.payroll_period_id = r.payroll_period_id
               JOIN {$this->tItem} i ON i.payroll_item_id = li.payroll_item_id
              WHERE p.fiscal_yr = {$year} AND r.employee_id = {$employeeId}
              GROUP BY p.payroll_month, i.item_code, i.item_name, i.category, i.tax_treatment, 6, 8",
            'DB'
        ) ?: [];
        if (!$lines) {
            return ['success' => false, 'message' => "No payroll for this employee in {$year}."];
        }

        $blank  = ['basic' => 0.0, 'tax' => 0.0, 'gsis' => 0.0, 'hdmf' => 0.0, 'phic' => 0.0, 'union_dues' => 0.0];
        $months = [];
        $benefits = [];
        // every active de minimis benefit is listed (as the client's sheet), paid or not; a de minimis item linked to no
        // benefit shows as its own row with no limit (all of it is excess)
        $limits = $this->deMinimisLimits();
        $deMin  = [];
        foreach ($limits as $id => $b) {
            $deMin[$id] = ['name' => $b['name'], 'actual' => 0.0, 'limit' => $b['annual_limit'], 'limit_entered' => $b['limit_amount'],
                'limit_note' => $b['limit_per'] === 'MONTH' ? number_format($b['limit_amount'], 2) . ' / month' : null];
        }
        foreach ($lines as $l) {
            $t   = (string) $l['tax_treatment'];
            $pay = in_array($t, self::PAY_TREATMENTS, true);
            $sign = match ((string) $l['category']) {
                'EARNING'   => $pay ? 1 : 0,
                'DEDUCTION' => $pay ? -1 : (in_array($t, ['CONTRIBUTION', 'WITHHOLDING_TAX'], true) ? 1 : 0),
                'REFUND'    => $pay ? 1 : (in_array($t, ['CONTRIBUTION', 'WITHHOLDING_TAX'], true) ? -1 : 0),
                default     => 0,
            };
            if ($sign === 0 || $t === 'NONE' || $t === 'NON_TAXABLE') {
                continue;
            }
            $m   = (int) $l['payroll_month'];
            $amt = $sign * (float) $l['amount'];
            $months[$m] ??= $blank;
            if ($t === 'TAXABLE') {
                $months[$m]['basic'] += $amt;
            } elseif ($t === 'WITHHOLDING_TAX') {
                $months[$m]['tax'] += $amt;
            } elseif ($t === 'CONTRIBUTION') {
                $acct = (string) $l['account_code'];
                $col  = str_starts_with($acct, '2-02-01-020') ? 'gsis' : (str_starts_with($acct, '2-02-01-030') ? 'hdmf'
                      : (str_starts_with($acct, '2-02-01-040') ? 'phic' : 'union_dues'));
                $months[$m][$col] += $amt;
            } elseif ($t === 'BENEFIT_90K') {
                $code = (string) $l['item_code'];
                $benefits[$code] ??= ['name' => (string) $l['item_name'], 'amount' => 0.0];
                $benefits[$code]['amount'] += $amt;
            } elseif ($t === 'DE_MINIMIS') {
                $dm = (int) $l['de_minimis_id'];
                if (!isset($deMin[$dm])) {   // not linked to an (active) benefit: no limit
                    $deMin[$dm] = ['name' => (string) $l['item_name'], 'actual' => 0.0, 'limit' => 0.0, 'limit_entered' => 0.0, 'limit_note' => 'no limit set'];
                }
                $deMin[$dm]['actual'] += $amt;
            }
        }
        ksort($months);
        $rows = [];
        $tot  = $blank + ['net' => 0.0];
        foreach ($months as $m => $v) {
            $v = array_map(static fn ($x) => round($x, 2), $v);
            $v['net'] = round($v['basic'] - $v['tax'] - $v['gsis'] - $v['hdmf'] - $v['phic'] - $v['union_dues'], 2);
            $rows[] = ['month' => $m, 'month_name' => strtoupper(self::MONTHS[$m])] + $v;
            foreach ($tot as $k => $_) {
                $tot[$k] += $v[$k];
            }
        }
        $tot = array_map(static fn ($x) => round($x, 2), $tot);

        $deMinRows = [];
        $excess    = 0.0;
        foreach ($deMin as $d) {
            $actual = round(max(0.0, $d['actual']), 2);
            $x = round(max(0.0, $actual - $d['limit']), 2);
            $excess += $x;
            $deMinRows[] = ['name' => $d['name'], 'actual' => $actual, 'limit' => $d['limit'], 'limit_entered' => $d['limit_entered'],
                'limit_note' => $d['limit_note'], 'excess' => $x];
        }
        $benefitRows = [];
        foreach ($benefits as $code => $b) {
            $benefitRows[] = ['code' => $code, 'name' => $b['name'], 'amount' => round($b['amount'], 2)];
        }
        $benefitTotal = round(array_sum(array_column($benefitRows, 'amount')) + $excess, 2);
        $contrib      = round($tot['gsis'] + $tot['hdmf'] + $tot['phic'] + $tot['union_dues'], 2);
        $regular      = round($tot['basic'] - $contrib, 2);
        $benTaxable   = round(max(0.0, $benefitTotal - self::BENEFIT_CAP), 2);
        $grossTaxable = round(max(0.0, $regular + $benTaxable), 2);
        $taxDue       = self::annualTax($grossTaxable);

        return ['success' => true, 'data' => [
            'year' => $year, 'employee' => $emp, 'months' => $rows, 'totals' => $tot,
            'benefits' => $benefitRows, 'de_minimis_excess' => round($excess, 2), 'benefits_total' => $benefitTotal,
            'benefit_cap' => self::BENEFIT_CAP, 'de_minimis' => $deMinRows,
            'exempt' => ['gsis' => $tot['gsis'], 'hdmf' => $tot['hdmf'], 'phic' => $tot['phic'], 'union_dues' => $tot['union_dues'], 'total' => $contrib],
            'computation' => ['regular_taxable' => $regular, 'benefits_taxable' => $benTaxable, 'gross_taxable' => $grossTaxable,
                'tax_due' => $taxDue, 'tax_withheld' => $tot['tax'], 'balance' => round($taxDue - $tot['tax'], 2)],
            'tax_table' => array_reverse(self::TAX_TABLE),
        ]];
    }

    // ═══ File template ═══════════════════════════════════════════════════════════════════

    /**
     * The columns the import recognises: the identity columns, every ACTIVE Payroll Setup item, and the amount columns
     * of the actual import form with their Payroll Setup status (form_columns).
     */
    public function fetchTemplateColumns(): array
    {
        try {
            $items = $this->templateItems();

            return ['success' => true, 'identity' => [
                ['column' => self::COL_EMPNO, 'label' => 'Employee Number', 'required' => true],
                ['column' => self::COL_DEPT, 'label' => 'Department Code', 'required' => false],
                ['column' => self::COL_LNAME, 'label' => 'Last Name', 'required' => true],
                ['column' => self::COL_FNAME, 'label' => 'First Name', 'required' => true],
                ['column' => self::COL_MI, 'label' => 'Middle Initial', 'required' => false],
            ], 'items' => $items, 'form_file' => basename(self::templateFile()), 'form_columns' => $this->formColumns()];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::fetchTemplateColumns ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the template columns.'];
        }
    }

    /**
     * The download: the payroll office's actual import form, _shared/template/MONTHLY-PAYROLL-TEMPLATE.xlsx (sheet
     * "MONTHLY PAYROLL", its 132 columns, styles, widths and frozen header) with every employee row removed, so the
     * same file comes back filled. The header cells become inline strings and the shared-string list is emptied, so no
     * name from the sample rows is left inside the file; calcChain goes too (its formulas were in the removed rows).
     * Returns the .xlsx bytes, or null when the form is missing / unreadable.
     */
    public function buildTemplate(): ?string
    {
        $src = self::templateFile();
        $in  = new \ZipArchive();
        if (!is_file($src) || $in->open($src) !== true) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'prtpl');
        $out = new \ZipArchive();
        try {
            $sheet = (string) $in->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === '' || !preg_match('~<row r="1"[^>]*>.*?</row>~s', $sheet, $m)) {
                return null;
            }
            $shared = [];
            if (($ss = $in->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach (simplexml_load_string($ss)->si as $si) {
                    $t = isset($si->t) ? (string) $si->t : '';
                    if (!isset($si->t)) {
                        foreach ($si->r as $run) {
                            $t .= (string) $run->t;
                        }
                    }
                    $shared[] = $t;
                }
            }
            $lastCol = 'A';
            $header = preg_replace_callback('~<c r="([A-Z]+)1"([^>]*?)(?:/>|>(.*?)</c>)~s', function ($c) use ($shared, &$lastCol) {
                $lastCol = $c[1];
                $attrs = preg_replace('~\s+t="[^"]*"~', '', $c[2]);
                $inner = $c[3] ?? '';
                $value = '';
                if (preg_match('~<v>(.*?)</v>~s', $inner, $v)) {
                    $value = str_contains($c[2], 't="s"') ? ($shared[(int) $v[1]] ?? '') : html_entity_decode($v[1], ENT_XML1);
                } elseif (preg_match('~<t[^>]*>(.*?)</t>~s', $inner, $v)) {
                    $value = html_entity_decode($v[1], ENT_XML1);
                }

                return $value === '' ? "<c r=\"{$c[1]}1\"{$attrs}/>"
                    : "<c r=\"{$c[1]}1\"{$attrs} t=\"inlineStr\"><is><t>" . htmlspecialchars($value, ENT_XML1) . '</t></is></c>';
            }, $m[0]);
            $sheet = preg_replace('~<sheetData>.*</sheetData>~s', '<sheetData>' . $header . '</sheetData>', $sheet);
            $sheet = preg_replace('~<dimension ref="[^"]*"/>~', '<dimension ref="A1:' . $lastCol . '1"/>', $sheet);
            $sheet = preg_replace(['~topLeftCell="[A-Z]+\d+"~', '~activeCell="[A-Z]+\d+"~', '~sqref="[A-Z]+\d+"~'], ['topLeftCell="A2"', 'activeCell="A2"', 'sqref="A2"'], $sheet);

            if ($out->open($tmp, \ZipArchive::OVERWRITE) !== true) {
                return null;
            }
            for ($i = 0; $i < $in->numFiles; $i++) {
                $name = $in->getNameIndex($i);
                $data = (string) $in->getFromIndex($i);
                if ($name === 'xl/calcChain.xml') {
                    continue;
                }
                if ($name === 'xl/worksheets/sheet1.xml') {
                    $data = $sheet;
                } elseif ($name === 'xl/sharedStrings.xml') {
                    $data = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="0" uniqueCount="0"/>';
                } elseif ($name === '[Content_Types].xml') {
                    $data = preg_replace('~<Override PartName="/xl/calcChain\.xml"[^>]*/>~', '', $data);
                } elseif ($name === 'xl/_rels/workbook.xml.rels') {
                    $data = preg_replace('~<Relationship [^>]*Target="calcChain\.xml"[^>]*/>~', '', $data);
                }
                $out->addFromString($name, $data);
            }
            $out->close();

            return (string) file_get_contents($tmp);
        } finally {
            $in->close();
            @unlink($tmp);
        }
    }

    /** The payroll office's import form (also the download). */
    private static function templateFile(): string
    {
        return dirname(__DIR__, 2) . '/_shared/template/MONTHLY-PAYROLL-TEMPLATE.xlsx';
    }

    /**
     * The amount columns of the import form (row 1, without identity / Expr columns), each with its Payroll Setup
     * status: ACTIVE / INACTIVE item, or NOT_SET_UP — what the File Template tab shows.
     */
    private function formColumns(): array
    {
        $items = [];
        foreach (App\ExecuteRows("SELECT item_code, item_name, category, account_code, status FROM {$this->tItem}", 'DB') ?: [] as $it) {
            $items[$this->itemKey($it['item_code'])] = $it;
        }
        $rows = is_file(self::templateFile()) ? $this->readXlsx(self::templateFile()) : null;
        $out  = [];
        foreach ($rows[1] ?? [] as $h) {
            $h = trim((string) $h);
            $u = strtoupper($h);
            if ($h === '' || preg_match('/^EXPR\d*$/i', $h) || str_starts_with($u, '1INCOME.') || str_starts_with($u, '2INCOME.')) {
                continue;
            }
            $key  = $this->itemKey($h);
            $item = $items[$key] ?? null;
            $out[] = ['column' => $key, 'status' => $item ? $item['status'] : 'NOT_SET_UP', 'category' => $item['category'] ?? null,
                'account_code' => $item['account_code'] ?? null, 'item_name' => $item['item_name'] ?? null];
        }

        return $out;
    }

    private function templateItems(): array
    {
        return App\ExecuteRows(
            "SELECT i.item_code, i.item_name, i.category, i.account_code, c.account_title
               FROM {$this->tItem} i LEFT JOIN fims.chart_of_accounts c ON c.account_code = i.account_code
              WHERE i.status = 'ACTIVE'
              ORDER BY CASE i.category WHEN 'EARNING' THEN 1 WHEN 'REFUND' THEN 2 WHEN 'DEDUCTION' THEN 3 ELSE 4 END, i.sort_order, i.item_code",
            'DB'
        ) ?: [];
    }

    // ═══ DV module (the payroll DV is prepared in Disbursement Voucher from an imported month) ═══
    // Link: fims.payroll_records.disbursement_voucher_id — every record of the month points at the DV.
    // A CANCELLED DV releases the month (releaseDv()); a deleted DV clears it too (and the FK is ON DELETE SET NULL).

    /** fims.deduction_types codes the payroll deductions are shown under on the DV, by account prefix. */
    private const DV_DED_TYPE_BY_PREFIX = [
        '2-02-01-020' => 'PAYROLL_GSIS',
        '2-02-01-040' => 'PAYROLL_PHILHEALTH',
        '2-02-01-030' => 'PAYROLL_PAGIBIG',
        '2-02-01-010' => 'PAYROLL_WTAX',
    ];
    private const DV_DED_TYPE_OTHER = 'PAYROLL_OTHER';
    private const DV_AP_ACCOUNT     = '2-01-01-010'; // Accounts Payable — what Treasury's payment entry debits

    /** Imported months a DV can be prepared from: not used by another live DV (plus the one DV $dvId already uses). */
    public function available(int $dvId = 0): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT p.payroll_period_id, p.fiscal_yr, p.payroll_month,
                        COUNT(r.payroll_record_id) AS employee_count,
                        COALESCE(SUM(r.gross_amount), 0) AS total_gross, COALESCE(SUM(r.net_amount), 0) AS total_net,
                        BOOL_OR(r.disbursement_voucher_id = {$dvId}) AS is_this_dv
                   FROM {$this->tPeriod} p
                   JOIN {$this->tRecord} r ON r.payroll_period_id = p.payroll_period_id
                   LEFT JOIN {$this->tDv} dv ON dv.dv_id = r.disbursement_voucher_id
                   LEFT JOIN {$this->tSt} st ON st.id = dv.status_id
                  GROUP BY p.payroll_period_id
                 HAVING NOT BOOL_OR(r.disbursement_voucher_id IS NOT NULL AND r.disbursement_voucher_id <> {$dvId} AND COALESCE(st.name, '') <> 'CANCELLED')
                  ORDER BY p.fiscal_yr DESC, p.payroll_month DESC",
                'DB'
            ) ?: [];
            foreach ($rows as &$r) {
                $r['label'] = (self::MONTHS[(int) $r['payroll_month']] ?? '') . ' ' . $r['fiscal_yr'];
            }
            unset($r);

            return ['success' => true, 'data' => $rows];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::available ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the imported payroll months.'];
        }
    }

    /**
     * What the DV form fills from an imported month. Amounts are summed per account from payroll_record_items:
     *   expense side (5-…): EARNING adds, a DEDUCTION / REFUND on an expense account (leave without pay, overpayment
     *     recovery…) changes that same account — this is the DV's gross amount (the debits);
     *   everything else (Due to GSIS / BIR / PhilHealth / Pag-IBIG, other payables, receivables): DEDUCTION credits it,
     *     REFUND debits it back — these are the DV's deductions;
     *   net = gross − deductions = the month's total net pay → Cr Accounts Payable.
     * The employer share is not part of the payroll DV (it is not paid to the employees).
     */
    public function dvData(int $periodId): array
    {
        try {
            $p = App\ExecuteRow("SELECT payroll_period_id, fiscal_yr, payroll_month FROM {$this->tPeriod} WHERE payroll_period_id = {$periodId}", 'DB');
            if (!$p) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'Payroll month not found.'];
            }
            $label = (self::MONTHS[(int) $p['payroll_month']] ?? '') . ' ' . $p['fiscal_yr'];
            $tot = App\ExecuteRow(
                "SELECT COUNT(*) AS n, COALESCE(SUM(net_amount), 0) AS net FROM {$this->tRecord} WHERE payroll_period_id = {$periodId}",
                'DB'
            );
            if (!(int) $tot['n']) {
                return ['success' => false, 'message' => "{$label} has no imported payroll."];
            }
            $sums = App\ExecuteRows(
                "SELECT i.category, ri.account_code, SUM(ri.amount) AS amount
                   FROM {$this->tLine} ri
                   JOIN {$this->tRecord} r ON r.payroll_record_id = ri.payroll_record_id
                   JOIN {$this->tItem} i ON i.payroll_item_id = ri.payroll_item_id
                  WHERE r.payroll_period_id = {$periodId} AND i.category <> 'EMPLOYER_SHARE'
                  GROUP BY i.category, ri.account_code",
                'DB'
            ) ?: [];

            $expense = $liability = [];
            $missing = [];
            foreach ($sums as $s) {
                $acct = (string) ($s['account_code'] ?? '');
                $amt  = (float) $s['amount'];
                if ($acct === '') {
                    $missing[] = $s['category'];
                    continue;
                }
                $isExpense = str_starts_with($acct, '5-');
                $sign = match ($s['category']) {
                    'EARNING' => 1,
                    'REFUND'  => $isExpense ? 1 : -1,
                    default   => $isExpense ? -1 : 1, // DEDUCTION
                };
                if ($isExpense) {
                    $expense[$acct] = ($expense[$acct] ?? 0) + $sign * $amt;
                } else {
                    $liability[$acct] = ($liability[$acct] ?? 0) + $sign * $amt;
                }
            }
            if ($missing) {
                return ['success' => false, 'message' => 'Some payroll items of ' . $label . ' have no account. Set their account in Payroll Setup, then import the month again.'];
            }
            $gross = round(array_sum($expense), 2);
            $dedTotal = round(array_sum($liability), 2);
            $net = round($gross - $dedTotal, 2);
            if ($gross <= 0) {
                return ['success' => false, 'message' => "{$label} has no earnings to pay."];
            }

            $titles = [];
            $codes  = array_unique(array_merge(array_keys($expense), array_keys($liability), [self::DV_AP_ACCOUNT]));
            foreach (App\ExecuteRows('SELECT account_code, account_title FROM fims.chart_of_accounts WHERE account_code IN (' . implode(', ', array_map(fn ($c) => $this->str($c), $codes)) . ')', 'DB') ?: [] as $c) {
                $titles[$c['account_code']] = $c['account_title'];
            }

            // DV deductions, grouped into the payroll deduction types (fallback: OTHER)
            $types = [];
            foreach (App\ExecuteRows("SELECT deduction_type_id, code, name, account_code FROM fims.deduction_types WHERE is_active", 'DB') ?: [] as $t) {
                $types[$t['code']] = $t;
            }
            $byType = [];
            foreach ($liability as $acct => $amt) {
                $code = self::DV_DED_TYPE_OTHER;
                foreach (self::DV_DED_TYPE_BY_PREFIX as $prefix => $c) {
                    if (str_starts_with($acct, $prefix)) {
                        $code = $c;
                        break;
                    }
                }
                if (!isset($types[$code])) {
                    $code = isset($types[self::DV_DED_TYPE_OTHER]) ? self::DV_DED_TYPE_OTHER : 'OTHER';
                }
                // a type with no default account (the plain "OTHER" fallback) needs one line per account
                $key = empty($types[$code]['account_code'] ?? null) ? "{$code}|{$acct}" : $code;
                $byType[$key] = ($byType[$key] ?? 0) + $amt;
            }
            $warnings = [];
            if (array_filter($byType, fn ($a) => $a < -0.004)) {
                // refunds larger than a type's deductions: show one combined line instead
                $code = isset($types[self::DV_DED_TYPE_OTHER]) ? self::DV_DED_TYPE_OTHER : 'OTHER';
                if (empty($types[$code]['account_code'] ?? null)) {
                    arsort($liability);
                    $code .= '|' . array_key_first($liability);
                }
                $byType = [$code => $dedTotal];
                $warnings[] = 'Refunds exceed some deductions — the deductions are shown as one combined line.';
            }
            if (!isset($types[self::DV_DED_TYPE_OTHER]) && !isset($types['PAYROLL_GSIS'])) {
                $warnings[] = 'The payroll deduction types (GSIS, PhilHealth, Pag-IBIG, Withholding Tax) are not set up yet, so the deductions show as "Other" — run the deduction types seed (04 - seed_data/08).';
            }
            $deductions = [];
            foreach ($byType as $key => $amt) {
                [$code, $acct] = array_pad(explode('|', (string) $key, 2), 2, null);
                $amt = round($amt, 2);
                if (abs($amt) < 0.005 || !isset($types[$code])) {
                    continue;
                }
                $deductions[] = [
                    'deduction_type_id' => (int) $types[$code]['deduction_type_id'],
                    'code'              => $code,
                    'name'              => $types[$code]['name'],
                    'rate'              => null,
                    'deduction_amount'  => $amt,
                    'account_code'      => $acct, // only for a type without a default account; else the type's own
                ];
            }

            // accounting entry: Dr expense, Cr payables / receivables, Cr Accounts Payable for the net pay
            $lines = [];
            $desc  = "Payroll — {$label}";
            arsort($expense);
            foreach ($expense as $acct => $amt) {
                $amt = round($amt, 2);
                if (abs($amt) >= 0.005) {
                    $lines[] = ['account_code' => $acct, 'description' => $desc, 'debit' => $amt > 0 ? $amt : 0, 'credit' => $amt < 0 ? -$amt : 0];
                }
            }
            ksort($liability);
            foreach ($liability as $acct => $amt) {
                $amt = round($amt, 2);
                if (abs($amt) >= 0.005) {
                    $lines[] = ['account_code' => $acct, 'description' => $titles[$acct] ?? $desc, 'debit' => $amt < 0 ? -$amt : 0, 'credit' => $amt > 0 ? $amt : 0];
                }
            }
            if ($net > 0) {
                $lines[] = ['account_code' => self::DV_AP_ACCOUNT, 'description' => "Net pay — {$label}", 'debit' => 0, 'credit' => $net];
            }

            return ['success' => true, 'data' => [
                'payroll_period_id' => (int) $p['payroll_period_id'],
                'label'             => $label,
                'employee_count'    => (int) $tot['n'],
                'gross_amount'      => $gross,
                'total_deductions'  => $dedTotal,
                'net_amount'        => $net,
                'claimant_name'     => 'Officers and Employees of NHA',
                'particulars'       => "Payment of salaries and other compensation of {$tot['n']} officers and employees for the month of {$label}.",
                'account_code'      => array_key_first($expense),
                'deductions'        => $deductions,
                'lines'             => $lines,
                'warnings'          => $warnings,
            ]];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::dvData ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to read the imported payroll for the DV.'];
        }
    }

    /** DisbursementVoucherService::create()/update(): error text, or null when month $periodId may be used by DV $dvId. */
    public function validateDvLink(int $periodId, int $dvId = 0): ?string
    {
        $avail = $this->available($dvId);
        if (!$avail['success']) {
            return $avail['message'];
        }
        foreach ($avail['data'] as $r) {
            if ((int) $r['payroll_period_id'] === $periodId) {
                return null;
            }
        }
        $p = App\ExecuteRow("SELECT fiscal_yr, payroll_month FROM {$this->tPeriod} WHERE payroll_period_id = {$periodId}", 'DB');

        return $p ? 'The ' . (self::MONTHS[(int) $p['payroll_month']] ?? '') . " {$p['fiscal_yr']} payroll has no imported records or is already used by another Disbursement Voucher."
            : "Payroll month #{$periodId} does not exist.";
    }

    /** DisbursementVoucherService::create()/update(), inside their transaction: point month $periodId (or none) at DV $dvId. */
    public function syncDvLink(int $dvId, ?int $periodId): void
    {
        App\Execute("UPDATE {$this->tRecord} SET disbursement_voucher_id = NULL, updated_at = CURRENT_TIMESTAMP
                      WHERE disbursement_voucher_id = {$dvId}" . ($periodId ? " AND payroll_period_id <> {$periodId}" : ''), 'DB');
        if ($periodId) {
            App\Execute("UPDATE {$this->tRecord} SET disbursement_voucher_id = {$dvId}, updated_at = CURRENT_TIMESTAMP
                          WHERE payroll_period_id = {$periodId}", 'DB');
        }
    }

    /** DisbursementVoucherService::changeStatus() (CANCELLED) / delete(): the month is free for a new DV again. */
    public function releaseDv(int $dvId): void
    {
        App\Execute("UPDATE {$this->tRecord} SET disbursement_voucher_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE disbursement_voucher_id = {$dvId}", 'DB');
    }

    /** DisbursementVoucherService::fetchById(): the imported month this DV pays, or null. */
    public function forDv(int $dvId): ?array
    {
        $row = App\ExecuteRow(
            "SELECT p.payroll_period_id, p.fiscal_yr, p.payroll_month, COUNT(*) AS employee_count,
                    SUM(r.gross_amount) AS total_gross, SUM(r.net_amount) AS total_net
               FROM {$this->tRecord} r JOIN {$this->tPeriod} p ON p.payroll_period_id = r.payroll_period_id
              WHERE r.disbursement_voucher_id = {$dvId}
              GROUP BY p.payroll_period_id LIMIT 1",
            'DB'
        );
        if (!$row) {
            return null;
        }
        $row['label'] = (self::MONTHS[(int) $row['payroll_month']] ?? '') . ' ' . $row['fiscal_yr'];

        return $row;
    }

    // ═══ Import ══════════════════════════════════════════════════════════════════════════

    /** Parse the uploaded file and return its totals and what blocks / needs checking — nothing is saved. */
    public function preview($file, array $data): array
    {
        try {
            $period = $this->period($data);
            if (isset($period['error'])) {
                return ['success' => false, 'message' => $period['error']];
            }
            $parsed = $this->parseUpload($file);
            if (!$parsed['success']) {
                return $parsed;
            }
            $existing = $this->periodState($period['month'], $period['year']);

            return ['success' => true, 'data' => $this->summaryOf($parsed, $period) + ['existing' => $existing]];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::preview ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to read the payroll file.'];
        }
    }

    /**
     * Import the file for a payroll month. A month that already has records is refused unless `replace` = 1, and can
     * never be replaced while a DV uses it (cancel or delete that DV first) or while the period is LOCKED / CLOSED.
     */
    public function import($file, array $data, int $userId): array
    {
        try {
            $period = $this->period($data);
            if (isset($period['error'])) {
                return ['success' => false, 'message' => $period['error']];
            }
            $parsed = $this->parseUpload($file);
            if (!$parsed['success']) {
                return $parsed;
            }
            if ($parsed['blocking']) {
                return ['success' => false, 'message' => 'The payroll file cannot be imported yet.', 'errors' => $parsed['blocking']];
            }
            $label    = self::MONTHS[$period['month']] . ' ' . $period['year'];
            $existing = $this->periodState($period['month'], $period['year']);
            if ($existing) {
                if ($existing['blocked']) {
                    return ['success' => false, 'httpCode' => 409, 'message' => $existing['blocked']];
                }
                if ($existing['record_count'] > 0 && (empty($data['replace']) || (string) $data['replace'] === '0')) {
                    return ['success' => false, 'httpCode' => 409, 'code' => 'EXISTS', 'message' => "A payroll for {$label} was already imported. Import again to replace it."];
                }
            }

            $uid = $this->userIdOrNull($userId);
            $u   = $uid === null ? 'NULL' : (string) $uid;
            App\Execute('BEGIN', 'DB');
            try {
                if ($existing) {
                    $periodId = (int) $existing['payroll_period_id'];
                    // payroll_record_items go with their record (ON DELETE CASCADE)
                    App\Execute("DELETE FROM {$this->tRecord} WHERE payroll_period_id = {$periodId}", 'DB');
                    App\Execute("UPDATE {$this->tPeriod} SET prepared_by = {$u}, prepared_at = CURRENT_TIMESTAMP, updated_by = {$u}, updated_at = CURRENT_TIMESTAMP
                                  WHERE payroll_period_id = {$periodId}", 'DB');
                } else {
                    $start = sprintf('%04d-%02d-01', $period['year'], $period['month']);
                    $end   = date('Y-m-t', strtotime($start));
                    $periodId = (int) App\ExecuteScalar(
                        "INSERT INTO {$this->tPeriod} (fiscal_yr, payroll_month, period_name, period_start_date, period_end_date, status, prepared_by, prepared_at, created_by)
                         VALUES ({$period['year']}, {$period['month']}, " . $this->str($label) . ", '{$start}', '{$end}', 'OPEN', {$u}, CURRENT_TIMESTAMP, {$u})
                         RETURNING payroll_period_id",
                        'DB'
                    );
                }

                $empIds = $this->upsertEmployees($parsed['employees'], $u);

                foreach (array_chunk($parsed['employees'], 200) as $chunk) {
                    $values = array_map(function ($e) use ($periodId, $empIds, $u) {
                        $t = $e['totals'];

                        return "({$periodId}, {$empIds[$e['employee_no']]}, "
                            . $this->num($t['EARNING']) . ', ' . $this->num($t['DEDUCTION']) . ', ' . $this->num($t['REFUND']) . ', ' . $this->num($t['EMPLOYER_SHARE'])
                            . ", 'DRAFT', {$u}, CURRENT_TIMESTAMP, {$u})";
                    }, $chunk);
                    $ids = App\ExecuteRows(
                        "INSERT INTO {$this->tRecord} (payroll_period_id, employee_id, total_earnings, total_deductions, total_refunds, total_employer_share, status, prepared_by, prepared_at, created_by)
                         VALUES " . implode(', ', $values) . ' RETURNING payroll_record_id, employee_id',
                        'DB'
                    ) ?: [];
                    $recByEmp = array_column($ids, 'payroll_record_id', 'employee_id');

                    $lines = [];
                    foreach ($chunk as $e) {
                        $recId = (int) $recByEmp[$empIds[$e['employee_no']]];
                        foreach ($e['lines'] as $itemId => $l) {
                            $lines[] = "({$recId}, {$itemId}, " . $this->num($l['amount']) . ', ' . $this->strOrNull($l['account_code']) . ", {$u})";
                        }
                    }
                    foreach (array_chunk($lines, 1000) as $lc) {
                        App\Execute("INSERT INTO {$this->tLine} (payroll_record_id, payroll_item_id, amount, account_code, created_by) VALUES " . implode(', ', $lc), 'DB');
                    }
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'httpCode' => 201, 'message' => "{$label} payroll imported: " . count($parsed['employees']) . ' employees.',
                'data' => ['payroll_period_id' => $periodId] + $this->summaryOf($parsed, $period)];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::import ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to import the payroll.'];
        }
    }

    /** Remove a month's imported records (the period row stays) — only while no DV uses them and the period is OPEN. */
    public function delete(int|string $periodId, int $userId): array
    {
        try {
            $p = App\ExecuteRow("SELECT fiscal_yr, payroll_month FROM {$this->tPeriod} WHERE payroll_period_id = " . (int) $periodId, 'DB');
            if (!$p) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'Payroll month not found.'];
            }
            $state = $this->periodState((int) $p['payroll_month'], (int) $p['fiscal_yr']);
            if (!$state || $state['record_count'] === 0) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'This month has no imported payroll.'];
            }
            if ($state['blocked']) {
                return ['success' => false, 'httpCode' => 409, 'message' => $state['blocked']];
            }
            $uid = $this->userIdOrNull($userId);
            $u   = $uid === null ? 'NULL' : (string) $uid;
            App\Execute('BEGIN', 'DB');
            try {
                App\Execute("DELETE FROM {$this->tRecord} WHERE payroll_period_id = " . (int) $periodId, 'DB');
                App\Execute("UPDATE {$this->tPeriod} SET prepared_by = NULL, prepared_at = NULL, updated_by = {$u}, updated_at = CURRENT_TIMESTAMP WHERE payroll_period_id = " . (int) $periodId, 'DB');
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => 'Imported payroll deleted.'];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::delete ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to delete the imported payroll.'];
        }
    }

    // ═══ File parsing ══════════════════════════════════════════════════════════════════════

    /**
     * The uploaded template → employees with their amounts per payroll item:
     * ['success', 'file_name', 'employees', 'totals', 'blocking' (errors that stop the import), 'warnings', 'skipped_columns'].
     */
    private function parseUpload($file): array
    {
        if (!$file || !method_exists($file, 'getError') || $file->getError() !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Choose the payroll Excel file (.xlsx) to import.'];
        }
        $name = (string) $file->getClientFilename();
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xlsx') {
            return ['success' => false, 'message' => 'The payroll file must be an Excel .xlsx file (the FIMS MONTHLY PAYROLL template).'];
        }
        $tmp = tempnam(sys_get_temp_dir(), 'payroll');
        $file->moveTo($tmp);
        try {
            $rows = $this->readXlsx($tmp);
        } finally {
            @unlink($tmp);
        }
        if ($rows === null) {
            return ['success' => false, 'message' => 'The file could not be opened as an Excel workbook.'];
        }

        // header row: the first of rows 1-5 that holds the employee number column
        $headerRow = null;
        foreach ($rows as $r => $cells) {
            if ($r > 5) {
                break;
            }
            if (in_array(self::COL_EMPNO, array_map(fn ($v) => strtoupper(trim((string) $v)), $cells), true)) {
                $headerRow = $r;
                break;
            }
        }
        if ($headerRow === null) {
            return ['success' => false, 'message' => 'This is not the FIMS MONTHLY PAYROLL template: no "1INCOME.EMPNO" column in its first rows.'];
        }
        $headers = array_map(fn ($v) => trim((string) $v), $rows[$headerRow]);
        $codes   = array_map(fn ($v) => trim((string) $v), $rows[$headerRow + 1] ?? []);
        // the account-code row under the headers is optional; when present it is skipped as data
        $hasCodeRow = (bool) array_filter($codes, fn ($c) => preg_match('/^\d-\d{2}-\d{2}/', $c));
        $col = [];
        foreach ($headers as $c => $h) {
            $col[strtoupper($h)] ??= $c;
        }
        foreach ([self::COL_EMPNO, self::COL_LNAME, self::COL_FNAME] as $req) {
            if (!isset($col[$req])) {
                return ['success' => false, 'message' => "The template is missing the {$req} column."];
            }
        }

        // match every amount column to a Payroll Setup item
        $items = [];
        foreach (App\ExecuteRows("SELECT payroll_item_id, item_code, item_name, category, account_code, status FROM {$this->tItem}", 'DB') ?: [] as $it) {
            $items[$this->itemKey($it['item_code'])] = $it;
        }
        $amountCols = [];   // col index => item row
        $unmapped   = [];   // col index => ['column', 'code']
        $inactive   = [];
        $noAccount  = [];
        foreach ($headers as $c => $h) {
            $u = strtoupper($h);
            if ($h === '' || preg_match('/^EXPR\d*$/i', $h) || str_starts_with($u, '1INCOME.') || str_starts_with($u, '2INCOME.')) {
                continue;
            }
            $item = $items[$this->itemKey($h)] ?? null;
            if (!$item) {
                $unmapped[$c] = ['column' => $this->itemKey($h), 'code' => $hasCodeRow ? ($codes[$c] ?? '') : ''];
            } elseif ($item['status'] !== 'ACTIVE') {
                $inactive[] = $item['item_code'];
            } else {
                $amountCols[$c] = $item;
                if (empty($item['account_code'])) {
                    $noAccount[$item['item_code']] = true;
                }
            }
        }

        $employees = [];
        $errors = [];
        $warnings = [];
        $seen = [];
        $unmappedUsed = [];
        $tot = ['EARNING' => 0.0, 'DEDUCTION' => 0.0, 'REFUND' => 0.0, 'EMPLOYER_SHARE' => 0.0];
        $first = $headerRow + ($hasCodeRow ? 2 : 1);
        foreach ($rows as $r => $cells) {
            if ($r < $first) {
                continue;
            }
            $empNo = trim((string) ($cells[$col[self::COL_EMPNO]] ?? ''));
            $last  = trim((string) ($cells[$col[self::COL_LNAME]] ?? ''));
            if ($empNo === '' || $last === '') {
                continue; // blank, subtotal or note rows
            }
            if (isset($seen[$empNo])) {
                $errors[] = "Employee no. {$empNo} appears more than once (rows {$seen[$empNo]} and {$r}) — one row per employee.";
                continue;
            }
            $seen[$empNo] = $r;
            $emp = ['row_no' => $r, 'employee_no' => $empNo, 'last_name' => $last,
                'first_name'  => trim((string) ($cells[$col[self::COL_FNAME]] ?? '')),
                'middle_name' => isset($col[self::COL_MI]) ? trim((string) ($cells[$col[self::COL_MI]] ?? '')) : '',
                'department'  => isset($col[self::COL_DEPT]) ? trim((string) ($cells[$col[self::COL_DEPT]] ?? '')) : '',
                'totals' => ['EARNING' => 0.0, 'DEDUCTION' => 0.0, 'REFUND' => 0.0, 'EMPLOYER_SHARE' => 0.0], 'lines' => []];

            foreach ($unmapped as $c => $um) {
                $num = $this->toNumber($cells[$c] ?? '');
                if ($num !== null && abs($num) >= 0.005) {
                    $unmappedUsed[$c] = $um;
                }
            }
            foreach ($amountCols as $c => $item) {
                $v = $cells[$c] ?? '';
                if ($v === '' || $v === null) {
                    continue;
                }
                $num = $this->toNumber($v);
                if ($num === null) {
                    if (count($errors) < 30) {
                        $errors[] = "Row {$r}, column {$item['item_code']}: \"{$v}\" is not a number.";
                    }
                    continue;
                }
                if (abs($num) < 0.005) {
                    continue;
                }
                $id = (int) $item['payroll_item_id'];
                $emp['lines'][$id] ??= ['amount' => 0.0, 'account_code' => $item['account_code']];
                $emp['lines'][$id]['amount'] = round($emp['lines'][$id]['amount'] + $num, 2);
                $emp['totals'][$item['category']] += $num;
            }
            foreach ($emp['totals'] as $k => $v) {
                $emp['totals'][$k] = round($v, 2);
                $tot[$k] += $emp['totals'][$k];
            }
            $net = $emp['totals']['EARNING'] + $emp['totals']['REFUND'] - $emp['totals']['DEDUCTION'];
            if ($emp['totals']['EARNING'] <= 0) {
                $warnings[] = "Row {$r} ({$empNo} {$last}): no earnings.";
            } elseif ($net < 0) {
                $warnings[] = "Row {$r} ({$empNo} {$last}): deductions exceed pay (net " . number_format($net, 2) . ').';
            }
            $employees[] = $emp;
        }
        if (!$employees && !$errors) {
            return ['success' => false, 'message' => 'The payroll file has no employee rows (each needs an employee no. and a last name).'];
        }
        foreach ($tot as $k => $v) {
            $tot[$k] = round($v, 2);
        }

        $blocking = $errors;
        if ($unmappedUsed) {
            $blocking[] = 'These columns have amounts but are not in Payroll Setup (add each as a payroll item with this item code): '
                . implode(', ', array_map(fn ($u) => $u['column'] . ($u['code'] !== '' ? " [{$u['code']}]" : ''), $unmappedUsed)) . '.';
        }
        if ($noAccount) {
            $warnings[] = 'Payroll items with no account yet (set it in Payroll Setup before preparing the DV): ' . implode(', ', array_keys($noAccount)) . '.';
        }
        // template columns that are not set up and carry no amounts in this file are simply ignored (not reported)
        return ['success' => true, 'file_name' => $name, 'employees' => $employees, 'totals' => $tot,
            'blocking' => array_slice($blocking, 0, 40), 'warnings' => array_slice($warnings, 0, 50), 'skipped_columns' => array_values(array_unique($inactive))];
    }

    /** Minimal .xlsx reader: the "MONTHLY PAYROLL" sheet (else the first) as [rowNo => [colIndex => value]]; null if not a workbook. */
    private function readXlsx(string $path): ?array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        try {
            $wb   = @simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
            $rels = @simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
            if (!$wb || !$rels) {
                return null;
            }
            $targets = [];
            foreach ($rels->Relationship as $rel) {
                $targets[(string) $rel['Id']] = (string) $rel['Target'];
            }
            $sheetPath = null;
            foreach ($wb->sheets->sheet as $s) {
                $rid    = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $target = $targets[$rid] ?? null;
                if ($target === null) {
                    continue;
                }
                $target = ltrim(str_starts_with($target, '/') ? substr($target, 1) : 'xl/' . $target, '/');
                $sheetPath ??= $target;
                if (strtoupper(trim((string) $s['name'])) === self::SHEET_NAME) {
                    $sheetPath = $target;
                    break;
                }
            }
            $shared = [];
            if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach (simplexml_load_string($ss)->si as $si) {
                    $t = '';
                    if (isset($si->t)) {
                        $t = (string) $si->t;
                    } else {
                        foreach ($si->r as $run) {
                            $t .= (string) $run->t;
                        }
                    }
                    $shared[] = $t;
                }
            }
            $xml = $sheetPath ? $zip->getFromName($sheetPath) : false;
            if ($xml === false) {
                return null;
            }
            $sheet = simplexml_load_string($xml);
            $out = [];
            foreach ($sheet->sheetData->row as $row) {
                $r = (int) $row['r'];
                foreach ($row->c as $c) {
                    $idx = $this->colIndex(preg_replace('/\d+/', '', (string) $c['r']));
                    $t   = (string) $c['t'];
                    if ($t === 's') {
                        $v = $shared[(int) $c->v] ?? '';
                    } elseif ($t === 'inlineStr') {
                        $v = (string) $c->is->t;
                    } else {
                        $v = (string) $c->v;
                    }
                    $out[$r][$idx] = $v;
                }
            }

            return $out;
        } finally {
            $zip->close();
        }
    }

    // ═══ Helpers ═══════════════════════════════════════════════════════════════════════════

    /** Adds the employees not in the directory yet; returns employee_no => employee_id for every employee of the file. */
    private function upsertEmployees(array $employees, string $u): array
    {
        $ids = [];
        foreach (array_chunk($employees, 200) as $chunk) {
            $values = array_map(fn ($e) => '(' . $this->str($e['employee_no']) . ', ' . $this->str($e['first_name']) . ', ' . $this->str($e['last_name'])
                . ', ' . $this->strOrNull($e['middle_name']) . ', ' . $this->strOrNull($e['department']) . ", {$u})", $chunk);
            // existing employees are left unchanged (the no-op update only makes RETURNING give their id)
            $rows = App\ExecuteRows(
                "INSERT INTO {$this->tEmp} (employee_no, first_name, last_name, middle_name, department, created_by)
                 VALUES " . implode(', ', $values) . '
                 ON CONFLICT (employee_no) DO UPDATE SET employee_no = EXCLUDED.employee_no
                 RETURNING employee_id, employee_no',
                'DB'
            ) ?: [];
            foreach ($rows as $r) {
                $ids[$r['employee_no']] = (int) $r['employee_id'];
            }
        }

        return $ids;
    }

    /** The month's period with its record count and why it cannot be (re)imported / deleted now; null when there is no period. */
    private function periodState(int $month, int $year): ?array
    {
        $p = App\ExecuteRow("SELECT payroll_period_id, status FROM {$this->tPeriod} WHERE fiscal_yr = {$year} AND payroll_month = {$month}", 'DB');
        if (!$p) {
            return null;
        }
        $id    = (int) $p['payroll_period_id'];
        $count = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tRecord} WHERE payroll_period_id = {$id}", 'DB');
        $dvs   = App\ExecuteScalar(
            "SELECT STRING_AGG(DISTINCT dv.dv_no, ', ') FROM {$this->tRecord} r
               JOIN {$this->tDv} dv ON dv.dv_id = r.disbursement_voucher_id
               LEFT JOIN {$this->tSt} st ON st.id = dv.status_id
              WHERE r.payroll_period_id = {$id} AND COALESCE(st.name, '') <> 'CANCELLED'",
            'DB'
        );
        $label   = self::MONTHS[$month] . ' ' . $year;
        $blocked = null;
        if (in_array(strtoupper((string) $p['status']), ['LOCKED', 'CLOSED'], true)) {
            $blocked = "The {$label} payroll period is {$p['status']}.";
        } elseif ($dvs) {
            $blocked = "The {$label} payroll is used by Disbursement Voucher {$dvs}. Cancel or delete that DV first.";
        } elseif ($rem = (new DeductionRemittanceService())->liveRemittanceAgencies($id)) {
            $blocked = "The {$label} deductions already have remittance DVs (" . implode(', ', $rem) . ') in Deductions & Remittances — cancel or delete them first.';
        }

        return ['payroll_period_id' => $id, 'record_count' => $count, 'blocked' => $blocked];
    }

    private function summaryOf(array $parsed, array $period): array
    {
        $t = $parsed['totals'];

        return [
            'payroll_month' => $period['month'], 'payroll_year' => $period['year'],
            'label' => self::MONTHS[$period['month']] . ' ' . $period['year'],
            'file_name' => $parsed['file_name'], 'employee_count' => count($parsed['employees']),
            'total_gross' => $t['EARNING'], 'total_deductions' => $t['DEDUCTION'], 'total_refunds' => $t['REFUND'],
            'total_net' => round($t['EARNING'] + $t['REFUND'] - $t['DEDUCTION'], 2), 'total_employer_share' => $t['EMPLOYER_SHARE'],
            'blocking' => $parsed['blocking'], 'warnings' => $parsed['warnings'], 'skipped_columns' => $parsed['skipped_columns'],
        ];
    }

    private function period(array $data): array
    {
        $m = (int) ($data['payroll_month'] ?? 0);
        $y = (int) ($data['payroll_year'] ?? 0);
        if ($m < 1 || $m > 12) {
            return ['error' => 'Choose the payroll month.'];
        }
        if ($y < 2000 || $y > 2100) {
            return ['error' => 'Choose the payroll year.'];
        }

        return ['month' => $m, 'year' => $y];
    }

    /** Template header / item code → comparison key: without the template's surrounding quotes, upper case. */
    private function itemKey(string $s): string
    {
        return strtoupper(trim(trim($s), "'"));
    }

    private function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }

    private function toNumber(mixed $v): ?float
    {
        $s = str_replace([',', ' ', '₱'], '', trim((string) $v));
        if ($s === '' || $s === '-') {
            return 0.0;
        }
        if (preg_match('/^\((.+)\)$/', $s, $m)) {
            $s = '-' . $m[1];
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /** created_by etc. reference uac.users — only stamp a user that exists there (same rule as PayrollSetupService). */
    private function userIdOrNull(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        return (int) App\ExecuteScalar("SELECT COUNT(*) FROM uac.users WHERE user_id = {$userId}", 'DB') > 0 ? $userId : null;
    }

    private function num(float $v): string
    {
        return sprintf('%.2f', $v);
    }

    private function str(string $v): string
    {
        return App\QuotedValue($v, App\DataType::STRING, 'DB');
    }

    private function strOrNull(?string $v): string
    {
        return $v === null || $v === '' ? 'NULL' : $this->str($v);
    }
}
