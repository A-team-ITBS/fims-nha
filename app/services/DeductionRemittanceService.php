<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Deductions & Remittances (page app/pages/remittance-page.php, routes app/routes/deduction_remittance.php).
 *
 * The deductions withheld in each imported payroll month (Payroll Management) are what has to be remitted. The page
 * shows ONE row per month; expanding it lists its agencies, each with its own "Create DV" — ONE remittance DV per
 * agency per month. That DV is the remittance — there is no separate scheduling step, and it doesn't depend on the
 * payroll DV (that one pays the employees, from Payroll Management):
 *
 *   PENDING (agency of an imported month, no remittance DV yet)
 *     ── Accounting "Create DV" on the agency (startDv()) ── the DV form (Disbursement Voucher page) for that agency:
 *        claimant = the agency, no RFA (the government share is budgeted with the payroll); entry Dr the agency's Due to …
 *        (employee share) and Dr its government-share expense / Cr Accounts Payable for the total
 *   IN DV: the DV runs FMD → Accounting → FOR_TREASURY → Treasury pays it (Disbursement Preparation)
 *   REMITTED (its DV released) ── "Confirm Receipt" (the agency's OR) ──► CONFIRMED
 *   The agency's DV cancelled / deleted → it has no DV again (its Create DV comes back).
 *
 * Amounts come from fims.payroll_record_items (the imported payroll), per agency by the item's account:
 *   employee share = DEDUCTION − REFUND on the agency's payable (Due to GSIS / BIR / PhilHealth / Pag-IBIG / other payables)
 *   employer share = EMPLOYER_SHARE items (government share, ECC → GSIS) — remitted together with the employee share
 * Deductions on an expense account (leave without pay, overpayment recovery) only reduce salary and are not remitted;
 * deductions on a receivable (1-…, e.g. NHA housing loan) are collected by NHA itself — shown as INTERNAL, not remitted.
 *
 * Stored in fims.payroll_remittances: one live (non-cancelled) row per month per agency, created when its remittance
 * DV is started (total_amount = the amount then, refreshed until the DV is saved); its DV is disbursement_voucher_id. Stored
 * status SCHEDULED (= "has / gets a remittance DV") / CONFIRMED / CANCELLED; IN_DV and REMITTED are read from the DV.
 * Status moves are logged in fims.status_change_log.
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
    // claimant of the remittance DV
    private const AGENCY_PAYEE = [
        'GSIS'       => 'Government Service Insurance System (GSIS)',
        'BIR'        => 'Bureau of Internal Revenue (BIR)',
        'PHILHEALTH' => 'Philippine Health Insurance Corporation (PhilHealth)',
        'PAGIBIG'    => 'Home Development Mutual Fund (Pag-IBIG Fund)',
        'OTHERS'     => 'Various payees — other payroll deductions (per attached schedule)',
    ];
    // account prefix → agency (first match wins; employer-share expense accounts go with their agency)
    private const AGENCY_BY_PREFIX = [
        '2-02-01-020' => 'GSIS', '5-01-03-010' => 'GSIS', '5-01-03-040' => 'GSIS', // GSIS premiums & loans, gov't share, ECC
        '2-02-01-040' => 'PHILHEALTH', '5-01-03-030' => 'PHILHEALTH',
        '2-02-01-030' => 'PAGIBIG', '5-01-03-020' => 'PAGIBIG',
        '2-02-01-010' => 'BIR',
        '1-'          => 'INTERNAL',
    ];
    private const AP_ACCOUNT    = '2-01-01-010'; // Accounts Payable — what the remittance DV credits (Treasury's payment JEV debits it)
    private const RC_ACCOUNTING = '012';
    private const RC_TREASURY   = '014';
    // Offices (uac.users.rc_id) allowed to do each step; a superadmin may do all.
    //   Accounting creates the remittance DVs · Treasury or Accounting confirms the receipt.
    private const DV_RCS = [self::RC_ACCOUNTING];
    private const CONFIRM_RCS  = [self::RC_TREASURY, self::RC_ACCOUNTING];
    private const STATUS_FOR = 'payroll_remittance'; // fims.status_change_log.status_for

    // ═══ List ════════════════════════════════════════════════════════════════════════════

    /**
     * months: one entry per imported month (newest first) with its agencies, its status (PENDING | IN_PROCESS |
     * REMITTED | CONFIRMED) and how many agencies still need a remittance DV; data: the same agencies flat. Plus KPIs
     * and what the current user may do. Filters: year, month, status (of the month).
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

            // the month's payroll DV (non-cancelled) and the live remittance rows with their remittance DV
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
                    $r = $this->withDv($r);
                    $rems[(int) $r['payroll_period_id'] . '|' . $r['remittance_agency']] = $r;
                }
            }

            $out = [];
            foreach ($groups as $g) {
                $rem = $rems[$g['payroll_period_id'] . '|' . $g['agency']] ?? null;
                $dv  = $dvs[$g['payroll_period_id']] ?? null;
                $row = $g + [
                    'payroll_dv_id'     => $dv ? (int) $dv['dv_id'] : null,
                    'payroll_dv_no'     => $dv['dv_no'] ?? null,
                    'payroll_dv_status' => $dv['dv_status'] ?? null,
                    // a row whose DV was never saved (or was cancelled) still has no remittance DV: Pending
                    'status'            => $g['agency'] === 'INTERNAL' ? 'NOT_REMITTED'
                        : (in_array($rem['display_status'] ?? 'PENDING', ['PENDING', 'SCHEDULED'], true) ? 'PENDING' : $rem['display_status']),
                    'payroll_remittance_id' => $rem ? (int) $rem['payroll_remittance_id'] : null,
                    'scheduled_amount'  => $rem ? round((float) $rem['total_amount'], 2) : null,
                    'due_date'          => $rem['due_date'] ?? null,
                    'remittance_dv_id'     => $rem['remittance_dv_id'] ?? null,
                    'remittance_dv_no'     => $rem['remittance_dv_no'] ?? null,
                    'remittance_dv_status' => $rem['remittance_dv_status'] ?? null,
                    'remittance_date'   => $rem['paid_date'] ?? null,
                    'remittance_no'     => $rem['paid_ref'] ?? null,
                    'remittance_method' => $rem['paid_method'] ?? null,
                    'receipt_no'        => $rem['remittance_receipt_no'] ?? null,
                    'receipt_date'      => $rem['receipt_date'] ?? null,
                    'remarks'           => $rem['remarks'] ?? null,
                ];
                $out[] = $row;
            }
            $months = $this->groupMonths($out);
            if (!empty($f['status'])) {
                $months = array_values(array_filter($months, fn ($m) => $m['status'] === $f['status']));
                $keep   = array_flip(array_column($months, 'payroll_period_id'));
                $out    = array_values(array_filter($out, fn ($r) => isset($keep[$r['payroll_period_id']])));
            }

            $sum = fn (array $rows) => round(array_sum(array_map(fn ($r) => (float) $r['total'], $rows)), 2);
            $by  = fn (array $st) => array_values(array_filter($out, fn ($r) => in_array($r['status'], $st, true)));
            $years = array_map('intval', array_column(App\ExecuteRows(
                "SELECT DISTINCT p.fiscal_yr FROM {$this->tPeriod} p WHERE EXISTS (SELECT 1 FROM {$this->tRecord} r WHERE r.payroll_period_id = p.payroll_period_id) ORDER BY 1 DESC",
                'DB'
            ) ?: [], 'fiscal_yr'));

            return ['success' => true, 'months' => $months, 'data' => $out, 'years' => $years, 'agencies' => self::AGENCIES, 'can' => $this->permissions(),
                'kpis' => [
                    'withheld'       => $sum(array_filter($out, fn ($r) => $r['agency'] !== 'INTERNAL')),
                    'pending'        => $sum($by(['PENDING'])),
                    'pending_count'  => count($by(['PENDING'])),
                    'in_dv'          => $sum($by(['IN_DV'])),
                    'in_dv_count'    => count($by(['IN_DV'])),
                    'remitted'       => $sum($by(['REMITTED', 'CONFIRMED'])),
                    'remitted_count' => count($by(['REMITTED', 'CONFIRMED'])),
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

    /**
     * Accounting: "Create DV" on one agency of a month. Its remittance row is created (or reused, with today's amount)
     * and returned, so the page opens Voucher?remittance={id}. No payroll DV needed.
     */
    public function startDv(int $periodId, string $agency, int $userId): array
    {
        try {
            if ($err = $this->authorize(self::DV_RCS, 'Only Accounting Department staff can create remittance DVs.')) {
                return $err;
            }
            $agency = strtoupper(trim($agency));
            if (!isset(self::AGENCIES[$agency]) || $agency === 'INTERNAL') {
                return ['success' => false, 'message' => 'Choose an agency to remit to.'];
            }
            $g = array_values(array_filter($this->agencyTotals("p.payroll_period_id = {$periodId}"), fn ($x) => $x['agency'] === $agency))[0] ?? null;
            if (!$g || $g['total'] <= 0) {
                return ['success' => false, 'message' => 'This month has nothing to remit to ' . self::AGENCIES[$agency] . '.'];
            }
            $row = App\ExecuteRow("SELECT * FROM {$this->table} WHERE payroll_period_id = {$periodId} AND remittance_agency = " . $this->str($agency)
                . " AND remittance_status <> 'CANCELLED' ORDER BY payroll_remittance_id DESC LIMIT 1", 'DB');
            $row = $row ? $this->withDv($row) : null;
            if ($row && ($row['remittance_dv_id'] || in_array($row['display_status'], ['REMITTED', 'CONFIRMED'], true))) {
                return ['success' => false, 'httpCode' => 409, 'message' => "The {$g['label']} " . self::AGENCIES[$agency]
                    . ' remittance already has DV ' . ($row['remittance_dv_no'] ?? '') . '.'];
            }
            $u = $this->userOrNull($userId);
            if ($row) {   // started before but no DV saved: reuse it with today's amount
                $id = (int) $row['payroll_remittance_id'];
                App\Execute("UPDATE {$this->table} SET total_amount = {$this->num($g['total'])}, amount_pending = {$this->num($g['total'])},
                                    updated_by = {$u}, updated_at = CURRENT_TIMESTAMP WHERE payroll_remittance_id = {$id}", 'DB');
            } else {
                $id = (int) App\ExecuteScalar(
                    "INSERT INTO {$this->table} (payroll_period_id, remittance_agency, total_amount, amount_remitted, amount_pending, remittance_status,
                                                 is_consolidated, tax_return_form, tax_return_period, created_by, updated_by)
                     VALUES ({$periodId}, " . $this->str($agency) . ", {$this->num($g['total'])}, 0, {$this->num($g['total'])}, 'SCHEDULED', TRUE, "
                    . ($agency === 'BIR' ? "'1601-C'" : 'NULL') . ', ' . $this->str(sprintf('%04d-%02d', $g['fiscal_yr'], $g['payroll_month'])) . ", {$u}, {$u})
                     RETURNING payroll_remittance_id",
                    'DB'
                );
                GlobalService::logStatusChange(self::STATUS_FOR, $id, 'SCHEDULED', $userId ?: null);
            }

            return ['success' => true, 'data' => ['payroll_remittance_id' => $id, 'payroll_period_id' => $periodId, 'label' => $g['label'],
                'agency' => $agency, 'agency_name' => self::AGENCIES[$agency], 'amount' => $g['total']]];
        } catch (\Throwable $e) {
            App\LogError('DeductionRemittanceService::startDv ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to start the remittance DV.'];
        }
    }

    /** Accounting: take a SCHEDULED remittance back (PENDING again) — only while it has no live remittance DV. */
    public function unschedule(int $id, int $userId): array
    {
        try {
            if ($err = $this->authorize(self::DV_RCS, 'Only Accounting Department staff can unschedule a remittance.')) {
                return $err;
            }
            $rem = $this->find($id);
            if (!$rem) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'Remittance not found.'];
            }
            if ($rem['display_status'] !== 'SCHEDULED') {
                return ['success' => false, 'httpCode' => 409, 'message' => $rem['remittance_dv_no']
                    ? "This remittance is paid through DV {$rem['remittance_dv_no']} — cancel or delete that DV first."
                    : 'Only a scheduled remittance can be unscheduled.'];
            }
            $u = $this->userOrNull($userId);
            App\Execute("UPDATE {$this->table} SET remittance_status = 'CANCELLED', updated_by = {$u}, updated_at = CURRENT_TIMESTAMP
                          WHERE payroll_remittance_id = {$id} AND remittance_status = 'SCHEDULED'", 'DB');
            GlobalService::logStatusChange(self::STATUS_FOR, $id, 'CANCELLED', $userId ?: null);

            return ['success' => true, 'message' => 'Remittance unscheduled — it is pending again.'];
        } catch (\Throwable $e) {
            App\LogError('DeductionRemittanceService::unschedule ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to unschedule the remittance.'];
        }
    }

    /**
     * Treasury / Accounting: the agency's official receipt / confirmation, once the remittance is REMITTED (its DV was
     * released by Treasury). Records the DV's release date and check no. as the remittance date / reference.
     */
    public function confirm(int $id, array $b, int $userId): array
    {
        $no   = trim((string) ($b['receipt_no'] ?? ''));
        $date = trim((string) ($b['receipt_date'] ?? ''));
        if ($no === '' || !$this->validDate($date)) {
            return ['success' => false, 'message' => 'The agency\'s receipt / confirmation no. and its date are required.'];
        }
        try {
            if ($err = $this->authorize(self::CONFIRM_RCS, 'Only Treasury or Accounting staff can confirm a remittance.')) {
                return $err;
            }
            $rem = $this->find($id);
            if (!$rem) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'Remittance not found.'];
            }
            if ($rem['display_status'] !== 'REMITTED') {
                return ['success' => false, 'httpCode' => 409, 'message' => $rem['remittance_dv_no']
                    ? "The remittance DV {$rem['remittance_dv_no']} is {$rem['remittance_dv_status']} — confirm the agency's receipt once Treasury has released it."
                    : 'Create and release the remittance DV first.'];
            }
            $u = $this->userOrNull($userId);
            App\Execute(
                "UPDATE {$this->table} SET remittance_status = 'CONFIRMED', remittance_receipt_no = " . $this->str($no) . ', receipt_date = ' . $this->str($date) . ',
                        remittance_date = COALESCE(remittance_date, ' . $this->strOrNull($rem['paid_date'] ? substr((string) $rem['paid_date'], 0, 10) : '') . '),
                        remittance_no = COALESCE(remittance_no, ' . $this->strOrNull($rem['paid_ref'] ?? '') . '),
                        remittance_method = COALESCE(remittance_method, ' . $this->strOrNull($rem['paid_method'] ?? '') . "),
                        amount_remitted = total_amount, amount_pending = 0, updated_by = {$u}, updated_at = CURRENT_TIMESTAMP
                  WHERE payroll_remittance_id = {$id} AND remittance_status IN ('SCHEDULED', 'REMITTED')",
                'DB'
            );
            GlobalService::logStatusChange(self::STATUS_FOR, $id, 'CONFIRMED', $userId ?: null);

            return ['success' => true, 'message' => 'Remittance confirmed.'];
        } catch (\Throwable $e) {
            App\LogError('DeductionRemittanceService::confirm ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to confirm the remittance.'];
        }
    }

    // ═══ Remittance DV (DV module: Voucher?remittance={id}, DisbursementVoucherService hooks) ═════

    /**
     * What the DV form fills from a SCHEDULED remittance: claimant = the agency, particulars, account (the agency's
     * payable), amount = total to remit, no deductions, and the accounting entry — Dr each payable of the employee share
     * (less refunds), Dr each government-share expense, Cr Accounts Payable for the total (Treasury's payment JEV then
     * debits Accounts Payable / credits Cash, as for every DV). No RFA: the employee share was budgeted with the payroll and
     * the government share is budgeted together with it.
     */
    public function dvData(int $id): array
    {
        try {
            $rem = $this->find($id);
            if (!$rem) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'Remittance not found.'];
            }
            if ($err = $this->validateDvLink($id)) {
                return ['success' => false, 'message' => $err];
            }
            $periodId = (int) $rem['payroll_period_id'];
            $p        = App\ExecuteRow("SELECT fiscal_yr, payroll_month FROM {$this->tPeriod} WHERE payroll_period_id = {$periodId}", 'DB');
            $label    = (self::MONTHS[(int) $p['payroll_month']] ?? '') . ' ' . $p['fiscal_yr'];
            $group    = [$rem];   // one agency per remittance DV
            $byAcct = [];
            $emp = $gov = 0.0;
            $parts = [];
            foreach ($group as $g) {
                $lines = $this->remittanceLines($periodId, $g['remittance_agency']);
                $total = round(array_sum(array_column($lines, 'debit')) - array_sum(array_column($lines, 'credit')), 2);
                if (abs($total - (float) $g['total_amount']) >= 0.005) {
                    // no DV holds it yet: follow the payroll as it is now
                    App\Execute("UPDATE {$this->table} SET total_amount = {$this->num($total)}, amount_pending = {$this->num($total)}, updated_at = CURRENT_TIMESTAMP
                                  WHERE payroll_remittance_id = " . (int) $g['payroll_remittance_id'], 'DB');
                }
                $parts[] = (self::AGENCIES[$g['remittance_agency']] ?? $g['remittance_agency']) . ' ₱' . number_format($total, 2);
                foreach ($lines as $l) {
                    $amt = $l['debit'] - $l['credit'];
                    $l['kind'] === 'EMPLOYER' ? $gov += $amt : $emp += $amt;
                    $byAcct[$l['account_code']] ??= ['account_code' => $l['account_code'], 'amount' => 0.0, 'description' => $l['description'], 'kind' => $l['kind']];
                    $byAcct[$l['account_code']]['amount'] += $amt;
                }
            }
            // Dr every Due to … (employee share) and government-share expense, Cr Accounts Payable for the total
            $lines = [];
            $mainAcct = null;
            $mainAmt  = -1.0;
            foreach ($byAcct as $a) {
                $amt = round($a['amount'], 2);
                if (abs($amt) < 0.005) {
                    continue;
                }
                $lines[] = ['account_code' => $a['account_code'], 'debit' => $amt > 0 ? $amt : 0.0, 'credit' => $amt < 0 ? -$amt : 0.0, 'description' => $a['description']];
                if ($a['kind'] !== 'EMPLOYER' && $amt > $mainAmt) {
                    [$mainAcct, $mainAmt] = [$a['account_code'], $amt];
                }
            }
            $total = round($emp + $gov, 2);
            $names = array_map(fn ($g) => self::AGENCIES[$g['remittance_agency']] ?? $g['remittance_agency'], $group);
            $lines[] = ['account_code' => self::AP_ACCOUNT, 'debit' => 0.0, 'credit' => $total, 'description' => 'Remittance payable — ' . implode(', ', $names) . " {$label}"];
            $employees = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tRecord} WHERE payroll_period_id = {$periodId}", 'DB');
            $ref = $this->payrollRef($periodId);   // shown for reference only: the remittance DV itself takes no RFA

            return ['success' => true, 'data' => [
                'payroll_remittance_id' => $id,
                'payroll_period_id' => $periodId,
                'label'          => $label,
                'agency'         => $rem['remittance_agency'],
                'agency_name'    => implode(', ', $names),
                'claimant_name'  => count($group) === 1
                    ? (self::AGENCY_PAYEE[$group[0]['remittance_agency']] ?? $names[0])
                    : 'Various agencies — ' . implode(', ', $names) . ' (per attached schedule)',
                'particulars'    => 'Remittance of the ' . implode(', ', $names) . " deductions and government share for the {$label} payroll"
                    . ' (employee share ₱' . number_format($emp, 2) . ', government share ₱' . number_format($gov, 2) . ", {$employees} employees"
                    . ($ref['text'] !== '' ? '; ' . $ref['text'] : '') . ').',
                'account_code'   => $mainAcct ?? ($lines[0]['account_code'] ?? null),
                'gross_amount'   => $total,
                'employee_share' => round($emp, 2),
                'employer_share' => round($gov, 2),
                'lines'          => $lines,
            ] + $ref];
        } catch (\Throwable $e) {
            App\LogError('DeductionRemittanceService::dvData ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to read the remittance for the DV.'];
        }
    }

    /** DisbursementVoucherService::create()/update(): error text, or null when remittance $id may be paid by DV $dvId. */
    public function validateDvLink(int $id, int $dvId = 0): ?string
    {
        $rem = $this->find($id);
        if (!$rem) {
            return "Payroll remittance #{$id} does not exist.";
        }
        $label = self::AGENCIES[$rem['remittance_agency']] ?? $rem['remittance_agency'];
        if (!in_array($rem['remittance_status'], ['SCHEDULED'], true)) {
            return "The {$label} remittance is {$rem['remittance_status']} — only a scheduled remittance gets a DV.";
        }
        if ($rem['remittance_dv_id'] && (int) $rem['remittance_dv_id'] !== $dvId) {
            return "The {$label} remittance is already paid through DV {$rem['remittance_dv_no']}.";
        }

        return null;
    }

    /** Inside the DV's create()/update() transaction: point remittance $id (one agency of a month, or none) at DV $dvId. */
    public function syncDvLink(int $dvId, ?int $id): void
    {
        App\Execute("UPDATE {$this->table} SET disbursement_voucher_id = NULL, updated_at = CURRENT_TIMESTAMP
                      WHERE disbursement_voucher_id = {$dvId}" . ($id ? " AND payroll_remittance_id <> {$id}" : ''), 'DB');
        if ($id) {
            App\Execute("UPDATE {$this->table} SET disbursement_voucher_id = {$dvId}, updated_at = CURRENT_TIMESTAMP
                          WHERE payroll_remittance_id = {$id} AND remittance_status = 'SCHEDULED'", 'DB');
        }
    }

    /** DV cancelled / deleted: its remittance is SCHEDULED again (a new remittance DV can be made). */
    public function releaseDv(int $dvId): void
    {
        App\Execute("UPDATE {$this->table} SET disbursement_voucher_id = NULL, updated_at = CURRENT_TIMESTAMP
                      WHERE disbursement_voucher_id = {$dvId} AND remittance_status = 'SCHEDULED'", 'DB');
    }

    /** DisbursementVoucherService::fetchById(): the remittance this DV pays, or null. */
    public function forDv(int $dvId): ?array
    {
        try {
            $row = App\ExecuteRow(
                "SELECT rm.payroll_remittance_id, rm.remittance_agency, rm.total_amount, rm.due_date, p.fiscal_yr, p.payroll_month
                   FROM {$this->table} rm JOIN {$this->tPeriod} p ON p.payroll_period_id = rm.payroll_period_id
                  WHERE rm.disbursement_voucher_id = {$dvId} AND rm.remittance_status <> 'CANCELLED' LIMIT 1",
                'DB'
            );
        } catch (\Throwable $e) {
            // never block viewing a DV over its remittance link (e.g. a database missing a payroll_remittances column)
            App\LogError('DeductionRemittanceService::forDv ' . $e->getMessage());

            return null;
        }
        if (!$row) {
            return null;
        }
        $all = App\ExecuteRows("SELECT remittance_agency, total_amount FROM {$this->table} WHERE disbursement_voucher_id = {$dvId} AND remittance_status <> 'CANCELLED'", 'DB') ?: [];
        $row['agency_name']  = implode(', ', array_map(fn ($r) => self::AGENCIES[$r['remittance_agency']] ?? $r['remittance_agency'], $all));
        $row['total_amount'] = round(array_sum(array_map('floatval', array_column($all, 'total_amount'))), 2);
        $row['label'] = (self::MONTHS[(int) $row['payroll_month']] ?? '') . ' ' . $row['fiscal_yr'];
        $periodId = (int) App\ExecuteScalar("SELECT payroll_period_id FROM {$this->table} WHERE payroll_remittance_id = " . (int) $row['payroll_remittance_id'], 'DB');

        return $row + $this->payrollRef($periodId);
    }

    /** PayrollManagementService: a month whose deductions are scheduled / remitted can no longer be replaced or deleted. */
    public function liveRemittanceAgencies(int $periodId): array
    {
        $out = [];
        foreach (App\ExecuteRows("SELECT * FROM {$this->table} WHERE payroll_period_id = {$periodId} AND remittance_status <> 'CANCELLED' ORDER BY remittance_agency", 'DB') ?: [] as $r) {
            $r = $this->withDv($r);
            if ($r['remittance_dv_id'] || in_array($r['display_status'], ['REMITTED', 'CONFIRMED'], true)) {
                $out[] = $r['remittance_agency'];   // a row whose DV was never saved doesn't lock the month
            }
        }

        return $out;
    }

    // ═══ Helpers ═══════════════════════════════════════════════════════════════════════════

    /** One remittance row with its remittance DV and the status shown on the page. */
    private function find(int $id): ?array
    {
        $r = App\ExecuteRow("SELECT * FROM {$this->table} WHERE payroll_remittance_id = {$id}", 'DB');

        return $r ? $this->withDv($r) : null;
    }

    /**
     * Adds remittance_dv_* (the linked DV unless cancelled), the paid date / reference (DV release + its check) and
     * display_status: SCHEDULED (no DV yet) · IN_DV (DV in process) · REMITTED (DV released) · CONFIRMED.
     */
    private function withDv(array $r): array
    {
        $dv = null;
        if (!empty($r['disbursement_voucher_id'])) {
            $dv = App\ExecuteRow(
                "SELECT dv.dv_id, dv.dv_no, st.name AS status, dv.treasury_released_at FROM {$this->tDv} dv
                   LEFT JOIN {$this->tSt} st ON st.id = dv.status_id WHERE dv.dv_id = " . (int) $r['disbursement_voucher_id'],
                'DB'
            );
            if ($dv && $dv['status'] === 'CANCELLED') {
                $dv = null;
            }
        }
        $r['remittance_dv_id']     = $dv ? (int) $dv['dv_id'] : null;
        $r['remittance_dv_no']     = $dv['dv_no'] ?? null;
        $r['remittance_dv_status'] = $dv['status'] ?? null;
        $r['paid_date']   = $r['remittance_date'] ?? null;
        $r['paid_ref']    = $r['remittance_no'] ?? null;
        $r['paid_method'] = $r['remittance_method'] ?? null;
        if ($dv && $dv['status'] === 'RELEASED') {
            $check = App\ExecuteRow("SELECT check_number FROM fims.checks WHERE dv_number = " . $this->str($dv['dv_no']) . " AND status <> 'CANCELLED' ORDER BY check_id DESC LIMIT 1", 'DB');
            $r['paid_date']   ??= $dv['treasury_released_at'] ? substr((string) $dv['treasury_released_at'], 0, 10) : null;
            $r['paid_ref']    ??= $check ? 'Check ' . $check['check_number'] : "DV {$dv['dv_no']}";
            $r['paid_method'] ??= $check ? 'CHECK' : null;
        }
        $stored = $r['remittance_status'];
        $r['display_status'] = match (true) {
            $stored === 'CONFIRMED' => 'CONFIRMED',
            $stored === 'REMITTED' => 'REMITTED', // rows recorded before remittance DVs
            $stored === 'SCHEDULED' && $dv && $dv['status'] === 'RELEASED' => 'REMITTED',
            $stored === 'SCHEDULED' && $dv !== null => 'IN_DV',
            default => $stored,
        };

        return $r;
    }

    /** The month's live (non-cancelled) payroll DV, or null. */
    private function payrollDv(int $periodId): ?array
    {
        $dv = App\ExecuteRow(
            "SELECT dv.dv_id, dv.dv_no, st.name AS status FROM {$this->tRecord} r
               JOIN {$this->tDv} dv ON dv.dv_id = r.disbursement_voucher_id
               LEFT JOIN {$this->tSt} st ON st.id = dv.status_id
              WHERE r.payroll_period_id = {$periodId} AND COALESCE(st.name, '') <> 'CANCELLED' LIMIT 1",
            'DB'
        );

        return $dv ?: null;
    }

    /**
     * What a remittance belongs to, for reference only: the month's payroll DV and the RFA(s) behind it
     * (fims.budget_allocation.dv_id). The remittance DV takes no RFA itself — one RFA backs one DV, and the government
     * share is budgeted together with the payroll.
     */
    private function payrollRef(int $periodId): array
    {
        $dv   = $this->payrollDv($periodId);
        $rfas = $dv ? array_values(array_filter(array_column(App\ExecuteRows(
            "SELECT COALESCE(NULLIF(allocation_number, ''), request_referrence_no) AS no FROM fims.budget_allocation
              WHERE dv_id = " . (int) $dv['dv_id'] . " AND allocation_type = 'RFA' AND deleted_at IS NULL ORDER BY allocation_id",
            'DB'
        ) ?: [], 'no'))) : [];
        $text = $dv ? 'payroll DV ' . $dv['dv_no'] . ($rfas ? ', RFA ' . implode(', ', $rfas) : '') : '';

        return ['payroll_dv_id' => $dv ? (int) $dv['dv_id'] : null, 'payroll_dv_no' => $dv['dv_no'] ?? null,
                'payroll_rfa_nos' => $rfas, 'text' => $text];
    }

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
     * Debit lines of a month + agency's remittance entry, one per account: the payables of the employee share (DEDUCTION
     * less REFUND) and the government-share expense accounts. A net credit (refunds larger than deductions) is a credit line.
     */
    private function remittanceLines(int $periodId, string $agency): array
    {
        $rows = App\ExecuteRows(
            "SELECT i.category, ri.account_code, c.account_title, SUM(ri.amount) AS amount
               FROM {$this->tLine} ri
               JOIN {$this->tRecord} r ON r.payroll_record_id = ri.payroll_record_id
               JOIN {$this->tItem} i ON i.payroll_item_id = ri.payroll_item_id
               LEFT JOIN fims.chart_of_accounts c ON c.account_code = ri.account_code
              WHERE r.payroll_period_id = {$periodId} AND i.category IN ('DEDUCTION', 'REFUND', 'EMPLOYER_SHARE')
              GROUP BY i.category, ri.account_code, c.account_title",
            'DB'
        ) ?: [];
        $by = [];
        foreach ($rows as $r) {
            [$ag, $kind, $signed] = $this->classify($r['category'], (string) $r['account_code'], (float) $r['amount']);
            if ($ag !== $agency || $kind === null) {
                continue;
            }
            $acct = (string) $r['account_code'];
            $by[$acct] ??= ['amount' => 0.0, 'title' => $r['account_title'] ?: $acct, 'kind' => $kind];
            $by[$acct]['amount'] += $signed;
        }
        $lines = [];
        foreach ($by as $acct => $x) {
            $amt = round($x['amount'], 2);
            if (abs($amt) < 0.005) {
                continue;
            }
            $desc = ($x['kind'] === 'EMPLOYER' ? "Government share — {$x['title']}" : $x['title']);
            $lines[] = ['account_code' => $acct, 'debit' => $amt > 0 ? $amt : 0.0, 'credit' => $amt < 0 ? -$amt : 0.0, 'description' => $desc, 'kind' => $x['kind']];
        }

        return $lines;
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

    /** What the current user may do on this page (superadmin: everything). */
    private function permissions(): array
    {
        $s  = GlobalService::getUserRcScope();
        $rc = $s['rc_id'] ?? null;

        return ['create_dv' => $s['is_admin'] || in_array($rc, self::DV_RCS, true),
            'confirm' => $s['is_admin'] || in_array($rc, self::CONFIRM_RCS, true)];
    }

    /**
     * The flat agency rows → one entry per month: total to remit, employees, its agencies, how many agencies have a
     * remittance DV / are remitted / confirmed, and the month status:
     *   PENDING     no agency has a remittance DV yet
     *   IN_PROCESS  some agency still needs its DV, or a DV is not released yet
     *   REMITTED    every agency's DV released (receipts not all confirmed)
     *   CONFIRMED   every agency confirmed
     */
    private function groupMonths(array $rows): array
    {
        $months = [];
        foreach ($rows as $r) {
            $id = $r['payroll_period_id'];
            $months[$id] ??= ['payroll_period_id' => $id, 'fiscal_yr' => $r['fiscal_yr'], 'payroll_month' => $r['payroll_month'],
                'label' => $r['label'], 'employees' => 0, 'total' => 0.0, 'agencies' => [],
                'payroll_dv_id' => $r['payroll_dv_id'], 'payroll_dv_no' => $r['payroll_dv_no'], 'payroll_dv_status' => $r['payroll_dv_status']];
            $months[$id]['agencies'][] = $r;
            $months[$id]['employees'] = max($months[$id]['employees'], (int) $r['employees']);
            if ($r['agency'] !== 'INTERNAL') {
                $months[$id]['total'] += (float) $r['total'];
            }
        }
        foreach ($months as &$m) {
            $remit = array_values(array_filter($m['agencies'], fn ($a) => $a['agency'] !== 'INTERNAL'));
            $n     = count($remit);
            $cnt   = fn (array $st) => count(array_filter($remit, fn ($a) => in_array($a['status'], $st, true)));
            $m['total']           = round($m['total'], 2);
            $m['agency_count']    = $n;
            $m['with_dv']         = $cnt(['IN_DV', 'REMITTED', 'CONFIRMED']);
            $m['remitted_count']  = $cnt(['REMITTED', 'CONFIRMED']);
            $m['confirmed_count'] = $cnt(['CONFIRMED']);
            $m['missing_dv']      = $n - $m['with_dv'];
            $ref = $this->payrollRef((int) $m['payroll_period_id']);   // for reference: the month's payroll DV and its RFA
            $m['payroll_rfa_nos'] = $ref['payroll_rfa_nos'];
            $m['status'] = match (true) {
                $n > 0 && $m['confirmed_count'] === $n => 'CONFIRMED',
                $n > 0 && $m['remitted_count'] === $n  => 'REMITTED',
                $m['with_dv'] === 0                     => 'PENDING',
                default                                 => 'IN_PROCESS',
            };
        }
        unset($m);

        return array_values($months);
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
