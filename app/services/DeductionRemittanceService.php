<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Deductions & Remittances (page app/pages/deduction-remittance-page.php, routes app/routes/deduction_remittance.php).
 *
 * One page for both: the deductions withheld in an imported payroll month (Payroll Management) ARE what has to be
 * remitted, so each month is shown per agency, and that same row is scheduled for Treasury and remitted.
 *
 *   PENDING (computed, no row yet) ── Accounting "Schedule for Treasury" (due date) ──► SCHEDULED
 *   SCHEDULED ── Treasury "Record Remittance" (date, method, reference no.) ──► REMITTED ── "Confirm Receipt" (OR) ──► CONFIRMED
 *   SCHEDULED ── Accounting "Unschedule" ──► CANCELLED (the month/agency is PENDING again)
 *
 * Amounts come from fims.payroll_record_items (the imported payroll), per agency by the item's account:
 *   employee share = DEDUCTION − REFUND on the agency's payable (Due to GSIS / BIR / PhilHealth / Pag-IBIG / other payables)
 *   employer share = EMPLOYER_SHARE items (government share, ECC → GSIS) — remitted together with the employee share
 * Deductions on an expense account (leave without pay, overpayment recovery) only reduce salary and are not remitted;
 * deductions on a receivable (1-…, e.g. NHA housing loan) are collected by NHA itself — shown as INTERNAL, not remitted.
 *
 * Stored in fims.payroll_remittances: one live (non-cancelled) row per month (payroll_period_id) per agency, created at
 * scheduling with the amount at that moment (total_amount). Status moves are logged in fims.status_change_log.
 */
class DeductionRemittanceService
{
    private string $table   = 'fims.payroll_remittances';
    private string $tPeriod = 'fims.payroll_periods';
    private string $tRecord = 'fims.payroll_records';
    private string $tLine   = 'fims.payroll_record_items';
    private string $tItem   = 'fims.payroll_items';
    private string $tEmp    = 'fims.payroll_employees';
    private string $tDv     = 'fims.disbursement_vouchers';
    private string $tSt     = 'fims.settings_status';

    private const MONTHS = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    public const AGENCIES = [
        'GSIS'       => 'GSIS',
        'BIR'        => 'BIR',
        'PHILHEALTH' => 'PhilHealth',
        'PAGIBIG'    => 'Pag-IBIG',
        'OTHERS'     => 'Other Payables',
        'INTERNAL'   => 'NHA Receivables (not remitted)',
    ];
    // account prefix → agency (first match wins; employer-share expense accounts go with their agency)
    private const AGENCY_BY_PREFIX = [
        '2-02-01-020' => 'GSIS', '5-01-03-010' => 'GSIS', '5-01-03-040' => 'GSIS', // GSIS premiums & loans, gov't share, ECC
        '2-02-01-040' => 'PHILHEALTH', '5-01-03-030' => 'PHILHEALTH',
        '2-02-01-030' => 'PAGIBIG', '5-01-03-020' => 'PAGIBIG',
        '2-02-01-010' => 'BIR',
        '1-'          => 'INTERNAL',
    ];
    private const RC_ACCOUNTING = '012';
    private const RC_TREASURY   = '014';
    private const RC_FMD        = '013';
    // Offices (uac.users.rc_id) allowed to do each step; a superadmin may do all.
    // TEMPORARY: FMD (013) is added to every step for testing — remove RC_FMD from these three lists to restore
    // Accounting schedules · Treasury remits · Treasury / Accounting confirm.
    private const SCHEDULE_RCS = [self::RC_ACCOUNTING, self::RC_FMD];
    private const REMIT_RCS    = [self::RC_TREASURY, self::RC_FMD];
    private const CONFIRM_RCS  = [self::RC_TREASURY, self::RC_ACCOUNTING, self::RC_FMD];
    private const METHODS = ['ONLINE', 'CHECK', 'BANK_TRANSFER', 'MANUAL'];
    private const STATUS_FOR = 'payroll_remittance'; // fims.status_change_log.status_for

    // ═══ List ════════════════════════════════════════════════════════════════════════════

    /**
     * One row per imported month per agency (newest month first), with its remittance status, plus KPIs and what the
     * current user may do. Filters: year, month, agency, status (PENDING | SCHEDULED | REMITTED | CONFIRMED).
     */
    public function fetchList(array $f = []): array
    {
        try {
            $where = ['1 = 1'];
            if (ctype_digit((string) ($f['year'] ?? ''))) {
                $where[] = 'p.fiscal_yr = ' . (int) $f['year'];
            }
            if (ctype_digit((string) ($f['month'] ?? '')) && (int) $f['month'] >= 1 && (int) $f['month'] <= 12) {
                $where[] = 'p.payroll_month = ' . (int) $f['month'];
            }
            $groups = $this->agencyTotals(implode(' AND ', $where));

            // the month's payroll DV (non-cancelled) and the live remittance rows
            $periodIds = array_unique(array_column($groups, 'payroll_period_id'));
            $dvs = $rems = [];
            if ($periodIds) {
                $in = implode(', ', array_map('intval', $periodIds));
                foreach (App\ExecuteRows(
                    "SELECT r.payroll_period_id, MIN(dv.dv_id) AS dv_id, STRING_AGG(DISTINCT dv.dv_no, ', ') AS dv_no, STRING_AGG(DISTINCT st.name, ', ') AS dv_status
                       FROM {$this->tRecord} r JOIN {$this->tDv} dv ON dv.dv_id = r.disbursement_voucher_id
                       LEFT JOIN {$this->tSt} st ON st.id = dv.status_id
                      WHERE r.payroll_period_id IN ({$in}) AND COALESCE(st.name, '') <> 'CANCELLED'
                      GROUP BY r.payroll_period_id",
                    'DB'
                ) ?: [] as $d) {
                    $dvs[(int) $d['payroll_period_id']] = $d;
                }
                foreach (App\ExecuteRows(
                    "SELECT * FROM {$this->table} WHERE payroll_period_id IN ({$in}) AND remittance_status <> 'CANCELLED'",
                    'DB'
                ) ?: [] as $r) {
                    $rems[(int) $r['payroll_period_id'] . '|' . $r['remittance_agency']] = $r;
                }
            }

            $out = [];
            foreach ($groups as $g) {
                $key = $g['payroll_period_id'] . '|' . $g['agency'];
                $rem = $rems[$key] ?? null;
                $dv  = $dvs[$g['payroll_period_id']] ?? null;
                $row = $g + [
                    'payroll_dv_id'     => $dv ? (int) $dv['dv_id'] : null,
                    'payroll_dv_no'     => $dv['dv_no'] ?? null,
                    'payroll_dv_status' => $dv['dv_status'] ?? null,
                    'status'            => $g['agency'] === 'INTERNAL' ? 'NOT_REMITTED' : ($rem['remittance_status'] ?? 'PENDING'),
                    'payroll_remittance_id' => $rem ? (int) $rem['payroll_remittance_id'] : null,
                    'scheduled_amount'  => $rem ? round((float) $rem['total_amount'], 2) : null,
                    'due_date'          => $rem['due_date'] ?? null,
                    'remittance_date'   => $rem['remittance_date'] ?? null,
                    'remittance_no'     => $rem['remittance_no'] ?? null,
                    'remittance_method' => $rem['remittance_method'] ?? null,
                    'receipt_no'        => $rem['remittance_receipt_no'] ?? null,
                    'receipt_date'      => $rem['receipt_date'] ?? null,
                    'remarks'           => $rem['remarks'] ?? null,
                ];
                if (!empty($f['agency']) && $row['agency'] !== $f['agency']) {
                    continue;
                }
                if (!empty($f['status']) && $row['status'] !== $f['status']) {
                    continue;
                }
                $out[] = $row;
            }

            $sum = fn (array $rows) => round(array_sum(array_map(fn ($r) => (float) $r['total'], $rows)), 2);
            $byStatus = fn (string $s) => array_values(array_filter($out, fn ($r) => $r['status'] === $s));
            $years = array_map('intval', array_column(App\ExecuteRows(
                "SELECT DISTINCT p.fiscal_yr FROM {$this->tPeriod} p WHERE EXISTS (SELECT 1 FROM {$this->tRecord} r WHERE r.payroll_period_id = p.payroll_period_id) ORDER BY 1 DESC",
                'DB'
            ) ?: [], 'fiscal_yr'));

            return ['success' => true, 'data' => $out, 'years' => $years, 'agencies' => self::AGENCIES, 'can' => $this->permissions(),
                'kpis' => [
                    'withheld'        => $sum(array_filter($out, fn ($r) => $r['agency'] !== 'INTERNAL')),
                    'pending'         => $sum($byStatus('PENDING')),
                    'pending_count'   => count($byStatus('PENDING')),
                    'scheduled'       => $sum($byStatus('SCHEDULED')),
                    'scheduled_count' => count($byStatus('SCHEDULED')),
                    'remitted'        => $sum(array_merge($byStatus('REMITTED'), $byStatus('CONFIRMED'))),
                    'remitted_count'  => count($byStatus('REMITTED')) + count($byStatus('CONFIRMED')),
                ]];
        } catch (\Throwable $e) {
            App\LogError('DeductionRemittanceService::fetchList ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the deductions and remittances.'];
        }
    }

    /** One month + agency: its payroll items (employee and employer share) and every employee's amounts. */
    public function fetchDetail(int $periodId, string $agency): array
    {
        try {
            $agency = strtoupper($agency);
            if (!isset(self::AGENCIES[$agency])) {
                return ['success' => false, 'message' => 'Unknown agency.'];
            }
            $p = App\ExecuteRow("SELECT fiscal_yr, payroll_month FROM {$this->tPeriod} WHERE payroll_period_id = {$periodId}", 'DB');
            if (!$p) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'Payroll month not found.'];
            }
            $lines = App\ExecuteRows(
                "SELECT e.employee_no, e.last_name, e.first_name, e.middle_name, i.item_code, i.item_name, i.category, ri.account_code, ri.amount
                   FROM {$this->tLine} ri
                   JOIN {$this->tRecord} r ON r.payroll_record_id = ri.payroll_record_id
                   JOIN {$this->tItem} i ON i.payroll_item_id = ri.payroll_item_id
                   JOIN {$this->tEmp} e ON e.employee_id = r.employee_id
                  WHERE r.payroll_period_id = {$periodId} AND i.category IN ('DEDUCTION', 'REFUND', 'EMPLOYER_SHARE')
                  ORDER BY e.last_name, e.first_name, i.sort_order, i.item_code",
                'DB'
            ) ?: [];
            $items = $employees = [];
            foreach ($lines as $l) {
                [$ag, $kind, $signed] = $this->classify($l['category'], (string) $l['account_code'], (float) $l['amount']);
                if ($ag !== $agency || $kind === null) {
                    continue;
                }
                $items[$l['item_code']] ??= ['item_code' => $l['item_code'], 'item_name' => $l['item_name'], 'category' => $l['category'], 'account_code' => $l['account_code'], 'share' => $kind, 'amount' => 0.0, 'employees' => 0];
                $items[$l['item_code']]['amount'] += $signed;
                $items[$l['item_code']]['employees']++;
                $empKey = $l['employee_no'];
                $employees[$empKey] ??= ['employee_no' => $l['employee_no'], 'name' => trim($l['last_name'] . ', ' . $l['first_name'] . ' ' . $l['middle_name']), 'employee_share' => 0.0, 'employer_share' => 0.0, 'items' => []];
                $employees[$empKey][$kind === 'EMPLOYER' ? 'employer_share' : 'employee_share'] += $signed;
                $employees[$empKey]['items'][] = ['item_code' => $l['item_code'], 'amount' => round($signed, 2)];
            }
            foreach ($items as &$it) {
                $it['amount'] = round($it['amount'], 2);
            }
            unset($it);
            foreach ($employees as &$em) {
                $em['employee_share'] = round($em['employee_share'], 2);
                $em['employer_share'] = round($em['employer_share'], 2);
                $em['total'] = round($em['employee_share'] + $em['employer_share'], 2);
            }
            unset($em);

            return ['success' => true, 'data' => [
                'label' => (self::MONTHS[(int) $p['payroll_month']] ?? '') . ' ' . $p['fiscal_yr'],
                'agency' => $agency, 'agency_name' => self::AGENCIES[$agency],
                'items' => array_values($items), 'employees' => array_values($employees),
            ]];
        } catch (\Throwable $e) {
            App\LogError('DeductionRemittanceService::fetchDetail ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the deduction details.'];
        }
    }

    // ═══ Remittance steps ═══════════════════════════════════════════════════════════════

    /** Accounting: schedule a month's agency remittance for Treasury (due date required). */
    public function schedule(array $b, int $userId): array
    {
        try {
            if ($err = $this->authorize(self::SCHEDULE_RCS, 'Only Accounting Department staff can schedule a remittance.')) {
                return $err;
            }
            $periodId = (int) ($b['payroll_period_id'] ?? 0);
            $agency   = strtoupper(trim((string) ($b['agency'] ?? '')));
            $due      = trim((string) ($b['due_date'] ?? ''));
            if (!isset(self::AGENCIES[$agency]) || $agency === 'INTERNAL') {
                return ['success' => false, 'message' => 'Choose an agency to remit to.'];
            }
            if (!$this->validDate($due)) {
                return ['success' => false, 'message' => 'Due date is required (YYYY-MM-DD).'];
            }
            $g = $this->agencyTotals("p.payroll_period_id = {$periodId}");
            $g = array_values(array_filter($g, fn ($r) => $r['agency'] === $agency))[0] ?? null;
            if (!$g || $g['total'] <= 0) {
                return ['success' => false, 'message' => 'This month has nothing to remit to ' . self::AGENCIES[$agency] . '.'];
            }
            $dv = App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tRecord} r JOIN {$this->tDv} dv ON dv.dv_id = r.disbursement_voucher_id
                   LEFT JOIN {$this->tSt} st ON st.id = dv.status_id
                  WHERE r.payroll_period_id = {$periodId} AND COALESCE(st.name, '') <> 'CANCELLED'",
                'DB'
            );
            if (!(int) $dv) {
                return ['success' => false, 'message' => "Prepare the {$g['label']} payroll DV first — the deductions are remitted once the payroll is paid."];
            }
            if ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE payroll_period_id = {$periodId} AND remittance_agency = " . $this->str($agency) . " AND remittance_status <> 'CANCELLED'", 'DB')) {
                return ['success' => false, 'httpCode' => 409, 'message' => "The {$g['label']} " . self::AGENCIES[$agency] . ' remittance is already scheduled.'];
            }
            $u  = $this->userOrNull($userId);
            $id = (int) App\ExecuteScalar(
                "INSERT INTO {$this->table} (payroll_period_id, remittance_agency, total_amount, amount_remitted, amount_pending, remittance_status,
                                             due_date, is_consolidated, tax_return_form, tax_return_period, remarks, created_by, updated_by)
                 VALUES ({$periodId}, " . $this->str($agency) . ", {$this->num($g['total'])}, 0, {$this->num($g['total'])}, 'SCHEDULED', "
                . $this->str($due) . ', TRUE, ' . ($agency === 'BIR' ? "'1601-C'" : 'NULL') . ', ' . $this->str(sprintf('%04d-%02d', $g['fiscal_yr'], $g['payroll_month'])) . ', '
                . $this->strOrNull($b['remarks'] ?? '') . ", {$u}, {$u}) RETURNING payroll_remittance_id",
                'DB'
            );
            GlobalService::logStatusChange(self::STATUS_FOR, $id, 'SCHEDULED', $userId ?: null);

            return ['success' => true, 'httpCode' => 201, 'message' => "{$g['label']} " . self::AGENCIES[$agency] . ' remittance scheduled for Treasury (₱' . number_format($g['total'], 2) . ", due {$due}).", 'data' => ['payroll_remittance_id' => $id]];
        } catch (\Throwable $e) {
            App\LogError('DeductionRemittanceService::schedule ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to schedule the remittance.'];
        }
    }

    /** Accounting: take a SCHEDULED remittance back (it becomes PENDING again). */
    public function unschedule(int $id, int $userId): array
    {
        return $this->move($id, ['SCHEDULED'], 'CANCELLED', self::SCHEDULE_RCS, 'Only Accounting Department staff can unschedule a remittance.', [], $userId, 'Remittance unscheduled — it is pending again.');
    }

    /** Treasury: the remittance was paid to the agency (date, method, reference no.). */
    public function remit(int $id, array $b, int $userId): array
    {
        $date   = trim((string) ($b['remittance_date'] ?? ''));
        $method = strtoupper(trim((string) ($b['remittance_method'] ?? '')));
        $ref    = trim((string) ($b['remittance_no'] ?? ''));
        if (!$this->validDate($date)) {
            return ['success' => false, 'message' => 'Remittance date is required (YYYY-MM-DD).'];
        }
        if (!in_array($method, self::METHODS, true)) {
            return ['success' => false, 'message' => 'Choose how it was remitted: ' . implode(', ', self::METHODS) . '.'];
        }
        if ($ref === '') {
            return ['success' => false, 'message' => 'Reference no. is required (payment / transaction / check no.).'];
        }

        return $this->move($id, ['SCHEDULED'], 'REMITTED', self::REMIT_RCS, 'Only Treasury Department staff can record a remittance.', [
            'remittance_date = ' . $this->str($date), 'remittance_method = ' . $this->str($method), 'remittance_no = ' . $this->str($ref),
            'amount_remitted = total_amount', 'amount_pending = 0',
        ], $userId, 'Remittance recorded.');
    }

    /** Treasury / Accounting: the agency's official receipt / confirmation for a REMITTED remittance. */
    public function confirm(int $id, array $b, int $userId): array
    {
        $no   = trim((string) ($b['receipt_no'] ?? ''));
        $date = trim((string) ($b['receipt_date'] ?? ''));
        if ($no === '' || !$this->validDate($date)) {
            return ['success' => false, 'message' => 'The agency\'s receipt / confirmation no. and its date are required.'];
        }

        return $this->move($id, ['REMITTED'], 'CONFIRMED', self::CONFIRM_RCS, 'Only Treasury or Accounting staff can confirm a remittance.', [
            'remittance_receipt_no = ' . $this->str($no), 'receipt_date = ' . $this->str($date),
        ], $userId, 'Remittance confirmed.');
    }

    /** PayrollManagementService: a month whose deductions are scheduled / remitted can no longer be replaced or deleted. */
    public function liveRemittanceAgencies(int $periodId): array
    {
        return array_column(App\ExecuteRows(
            "SELECT remittance_agency FROM {$this->table} WHERE payroll_period_id = {$periodId} AND remittance_status <> 'CANCELLED' ORDER BY 1",
            'DB'
        ) ?: [], 'remittance_agency');
    }

    // ═══ Helpers ═══════════════════════════════════════════════════════════════════════════

    /**
     * Totals per month per agency for the months matching $periodWhere (alias p): employee share, employer share,
     * total, employees, items. Months without any remittable amount are left out.
     */
    private function agencyTotals(string $periodWhere): array
    {
        $rows = App\ExecuteRows(
            "SELECT p.payroll_period_id, p.fiscal_yr, p.payroll_month, i.category, ri.account_code,
                    SUM(ri.amount) AS amount, COUNT(DISTINCT r.employee_id) AS employees, COUNT(DISTINCT i.payroll_item_id) AS items
               FROM {$this->tPeriod} p
               JOIN {$this->tRecord} r ON r.payroll_period_id = p.payroll_period_id
               JOIN {$this->tLine} ri ON ri.payroll_record_id = r.payroll_record_id
               JOIN {$this->tItem} i ON i.payroll_item_id = ri.payroll_item_id
              WHERE {$periodWhere} AND i.category IN ('DEDUCTION', 'REFUND', 'EMPLOYER_SHARE')
              GROUP BY p.payroll_period_id, i.category, ri.account_code",
            'DB'
        ) ?: [];
        $g = [];
        foreach ($rows as $r) {
            [$agency, $kind, $signed] = $this->classify($r['category'], (string) $r['account_code'], (float) $r['amount']);
            if ($kind === null) {
                continue;
            }
            $key = $r['payroll_period_id'] . '|' . $agency;
            $g[$key] ??= ['payroll_period_id' => (int) $r['payroll_period_id'], 'fiscal_yr' => (int) $r['fiscal_yr'], 'payroll_month' => (int) $r['payroll_month'],
                'label' => (self::MONTHS[(int) $r['payroll_month']] ?? '') . ' ' . $r['fiscal_yr'], 'month_name' => self::MONTHS[(int) $r['payroll_month']] ?? '',
                'agency' => $agency, 'agency_name' => self::AGENCIES[$agency], 'employee_share' => 0.0, 'employer_share' => 0.0, 'employees' => 0, 'items' => 0];
            $g[$key][$kind === 'EMPLOYER' ? 'employer_share' : 'employee_share'] += $signed;
            $g[$key]['employees'] = max($g[$key]['employees'], (int) $r['employees']);
            $g[$key]['items'] += (int) $r['items'];
        }
        $order = array_flip(array_keys(self::AGENCIES));
        foreach ($g as &$x) {
            $x['employee_share'] = round($x['employee_share'], 2);
            $x['employer_share'] = round($x['employer_share'], 2);
            $x['total'] = round($x['employee_share'] + $x['employer_share'], 2);
        }
        unset($x);
        $g = array_values(array_filter($g, fn ($x) => abs($x['total']) >= 0.005));
        usort($g, fn ($a, $b) => [$b['fiscal_yr'], $b['payroll_month'], $order[$a['agency']]] <=> [$a['fiscal_yr'], $a['payroll_month'], $order[$b['agency']]]);

        return $g;
    }

    /**
     * One payroll item amount → [agency, 'EMPLOYEE' | 'EMPLOYER' | null (not remitted), signed amount].
     * A DEDUCTION / REFUND on an expense account (5-…) changes salary, not a payable, so it is not remitted.
     */
    private function classify(string $category, string $account, float $amount): array
    {
        $agency = 'OTHERS';
        foreach (self::AGENCY_BY_PREFIX as $prefix => $a) {
            if (str_starts_with($account, $prefix)) {
                $agency = $a;
                break;
            }
        }
        if ($category === 'EMPLOYER_SHARE') {
            return [$agency === 'INTERNAL' ? 'OTHERS' : $agency, 'EMPLOYER', $amount];
        }
        if ($account === '' || str_starts_with($account, '5-')) {
            return [$agency, null, 0.0];
        }

        return [$agency, 'EMPLOYEE', $category === 'REFUND' ? -$amount : $amount];
    }

    /** Status move with an RC check, guarded on the current status; logs the change. */
    private function move(int $id, array $from, string $to, array $rcs, string $denied, array $set, int $userId, string $ok): array
    {
        try {
            if ($err = $this->authorize($rcs, $denied)) {
                return $err;
            }
            $cur = App\ExecuteRow("SELECT remittance_status FROM {$this->table} WHERE payroll_remittance_id = {$id}", 'DB');
            if (!$cur) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'Remittance not found.'];
            }
            if (!in_array($cur['remittance_status'], $from, true)) {
                return ['success' => false, 'httpCode' => 409, 'message' => "This remittance is {$cur['remittance_status']}, so it cannot be moved to {$to}."];
            }
            $u = $this->userOrNull($userId);
            $set[] = 'remittance_status = ' . $this->str($to);
            $set[] = "updated_by = {$u}";
            $set[] = 'updated_at = CURRENT_TIMESTAMP';
            App\Execute("UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE payroll_remittance_id = {$id} AND remittance_status = " . $this->str($cur['remittance_status']), 'DB');
            GlobalService::logStatusChange(self::STATUS_FOR, $id, $to, $userId ?: null);

            return ['success' => true, 'message' => $ok];
        } catch (\Throwable $e) {
            App\LogError('DeductionRemittanceService::move ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update the remittance.'];
        }
    }

    /** What the current user may do on this page (superadmin: everything). */
    private function permissions(): array
    {
        $s  = GlobalService::getUserRcScope();
        $rc = $s['rc_id'] ?? null;

        return ['schedule' => $s['is_admin'] || in_array($rc, self::SCHEDULE_RCS, true), 'remit' => $s['is_admin'] || in_array($rc, self::REMIT_RCS, true),
            'confirm' => $s['is_admin'] || in_array($rc, self::CONFIRM_RCS, true)];
    }

    private function authorize(array $rcs, string $denied): ?array
    {
        $s = GlobalService::getUserRcScope();
        if ($s['is_admin'] || in_array($s['rc_id'] ?? null, $rcs, true)) {
            return null;
        }

        return ['success' => false, 'httpCode' => 403, 'message' => $denied];
    }

    private function userOrNull(int $userId): string
    {
        if ($userId <= 0) {
            return 'NULL';
        }

        return (int) App\ExecuteScalar("SELECT COUNT(*) FROM uac.users WHERE user_id = {$userId}", 'DB') > 0 ? (string) $userId : 'NULL';
    }

    private function validDate(string $v): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $v);

        return $d !== false && $d->format('Y-m-d') === $v;
    }

    private function num(float $v): string
    {
        return sprintf('%.2f', $v);
    }

    private function str(string $v): string
    {
        return App\QuotedValue($v, App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }
}
