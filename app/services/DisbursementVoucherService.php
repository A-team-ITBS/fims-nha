<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Disbursement Vouchers (fims.disbursement_vouchers).
 *
 * One generic voucher shape for any claimant/payment type - the paper General
 * Form No. 1 is itself a single layout used for vendor payments, payroll,
 * reimbursements, etc.; what differs is just what gets written in
 * Particulars/Amount, not the schema. A claimant is optionally a registered
 * vendor (vendor_id set, fields snapshotted from fims.vendors) or free text.
 * VAT/W-Tax default to 0 and only apply when the voucher actually withholds.
 *
 * "RFA" has no dedicated table - fims.budget_allocation rows where
 * allocation_type = 'RFA' stand in for it. That table's own CRUD backend
 * doesn't exist yet (the CBA/RFA form is frontend-only), so this service only
 * adds a narrow, read-only lookup over it (fetchRfaOptions / fetchRfaDetail) -
 * no write path, no FK.
 *
 * Status is a plain column moved by changeStatus(), modeling the real four-office
 * review chain (Originating -> FMD -> Accounting -> Treasury) as a straight-line
 * pipeline, same state-machine shape as PurchaseOrderService otherwise:
 *   DRAFT            -> FOR_FMD_REVIEW | CANCELLED                 (Originating submits)
 *   FOR_FMD_REVIEW    -> FOR_ACCOUNTING | REJECTED | CANCELLED      (FMD checks the obligation, approves)
 *   FOR_ACCOUNTING    -> FOR_TREASURY | REJECTED | CANCELLED        (Accounting processes the DV)
 *   FOR_TREASURY      -> RELEASED | CANCELLED                       (Treasury releases payment)
 *   REJECTED          -> CANCELLED           (or edited + re-saved via update())
 *   RELEASED / CANCELLED are terminal.
 *
 * Accounting's stage is a placeholder for real work this project doesn't model yet
 * (Journal Entry Voucher, ATC/withholding-tax code, generated accounting entries) -
 * only the stage transition itself exists today.
 *
 * changeStatus() enforces who may move a voucher into each stage (see
 * STAGE_HOLDER_RC / authorizeTransition()): the caller's uac.users.rc_id (Responsibility
 * Center — fims.responsibility_code, the org-chart column this project already
 * uses for fims.budget_req.rc_id) must match the office that owns the target
 * stage, unless they're a sysadmin (IsSysAdmin() bypasses everything, matching
 * the rest of this project's convention). Submitting to FMD (DRAFT ->
 * FOR_FMD_REVIEW) is open to anyone, same as creating the voucher in the first
 * place — there's no "Originating office" concept to gate against.
 */
class DisbursementVoucherService
{
    private string $table       = 'fims.disbursement_vouchers';
    private string $tableVendor = 'fims.vendors';
    private string $tableRfa    = 'fims.budget_allocation';
    private string $tableDept   = 'uac.departments';
    private string $tableUsers  = 'uac.users';
    private string $tableFiscal = 'fims.fiscal_year';
    private string $tableCoa    = 'fims.chart_of_accounts';

    // Classification tag only (reporting/filtering) — same set fims.purchase_orders.expense_class
    // uses; it never changes which fields the voucher form shows.
    private const EXPENSE_CLASSES = ['MOOE', 'PS', 'CAPITAL_OUTLAY', 'OTHER'];

    private const EDITABLE      = ['DRAFT', 'REJECTED'];
    private const DELETABLE     = ['DRAFT', 'CANCELLED'];
    private const SAVE_STATUSES = ['DRAFT', 'FOR_FMD_REVIEW'];
    private const TRANSITIONS   = [
        'DRAFT'           => ['FOR_FMD_REVIEW', 'CANCELLED'],
        'FOR_FMD_REVIEW'  => ['FOR_ACCOUNTING', 'REJECTED', 'CANCELLED'],
        'FOR_ACCOUNTING'  => ['FOR_TREASURY', 'REJECTED', 'CANCELLED'],
        'FOR_TREASURY'    => ['RELEASED', 'CANCELLED'],
        'REJECTED'        => ['CANCELLED'],
        'RELEASED'        => [],
        'CANCELLED'       => [],
    ];

    // Responsibility Center (fims.responsibility_code.rc_id) that HOLDS the voucher while it sits
    // in this status — the only office allowed to move it forward, reject it, or (together with
    // the creator) cancel it from here. Keyed by the status itself, not by where it's headed: e.g.
    // STAGE_HOLDER_RC['FOR_FMD_REVIEW'] is FMD because FMD is who's holding it for review right
    // now, which is also, not coincidentally, who must approve moving it on to FOR_ACCOUNTING.
    // DRAFT has no entry (Originating holds it, open to anyone) — see authorizeTransition().
    private const STAGE_HOLDER_RC = [
        'FOR_FMD_REVIEW' => 'RC-013', // Financial Management Department (FMD)
        'FOR_ACCOUNTING' => 'RC-012', // Accounting Department
        'FOR_TREASURY'   => 'RC-014', // Treasury Department
    ];
    private const STAGE_RC_LABEL = [
        'RC-013' => 'Financial Management Department (FMD)',
        'RC-012' => 'Accounting Department',
        'RC-014' => 'Treasury Department',
    ];

    // ── small SQL helpers (same shape as PurchaseOrderService) ──

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function numOrNull(mixed $v): string
    {
        return is_numeric($v) ? (string) (float) $v : 'NULL';
    }

    private function intOrNull(mixed $v): string
    {
        return ($v !== null && $v !== '' && ctype_digit((string) $v)) ? (string) (int) $v : 'NULL';
    }

    private function validDate(mixed $v): bool
    {
        if (!is_string($v)) {
            return false;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $v);

        return $d !== false && $d->format('Y-m-d') === $v;
    }

    // ── validation ──

    /**
     * Validates the form payload shared by create() and update().
     * Returns [errors[], fields[]].
     */
    private function prepare(array $body): array
    {
        $errors = [];
        $fields = [];

        // DV date
        $dvDate = trim((string) ($body['dv_date'] ?? ''));
        if ($dvDate === '') {
            $errors[] = 'DV Date (dv_date) is required.';
        } elseif (!$this->validDate($dvDate)) {
            $errors[] = 'DV Date must be a valid date (YYYY-MM-DD).';
        }
        $fields['dv_date'] = $dvDate;

        // Fiscal year
        $fy = $body['fiscal_yr'] ?? '';
        if ($fy === '' || $fy === null || !ctype_digit((string) $fy)) {
            $errors[] = 'Fiscal Year (fiscal_yr) is required.';
        } elseif ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = " . (int) $fy, 'DB') === 0) {
            $errors[] = "Fiscal year {$fy} does not exist.";
        }
        $fields['fiscal_yr'] = (int) $fy;

        // Expense class (optional) — classification tag only, never branches validation/fields
        $expenseClass = strtoupper(trim((string) ($body['expense_class'] ?? '')));
        if ($expenseClass !== '' && !in_array($expenseClass, self::EXPENSE_CLASSES, true)) {
            $errors[] = 'expense_class must be one of: ' . implode(', ', self::EXPENSE_CLASSES);
        }
        $fields['expense_class'] = $expenseClass;

        // RFA (optional) — soft reference into fims.budget_allocation (allocation_type = 'RFA')
        $rfa   = null;
        $rfaId = $body['rfa_allocation_id'] ?? '';
        if ($rfaId !== '' && $rfaId !== null) {
            if (!ctype_digit((string) $rfaId)) {
                $errors[] = 'rfa_allocation_id must be numeric.';
            } else {
                $rfa = App\ExecuteRow(
                    "SELECT allocation_id, allocation_number, requesting_department_id
                     FROM {$this->tableRfa} WHERE allocation_id = " . (int) $rfaId . " AND allocation_type = 'RFA'",
                    'DB'
                );
                if (!$rfa) {
                    $errors[] = "RFA #{$rfaId} does not exist.";
                }
            }
        }
        $fields['rfa_allocation_id']         = $rfa ? (int) $rfa['allocation_id'] : null;
        $fields['rfa_allocation_number']     = $rfa['allocation_number'] ?? '';
        $fields['requesting_department_id']  = $rfa['requesting_department_id'] ?? null;

        $fields['dept_project'] = trim((string) ($body['dept_project'] ?? ''));
        $fields['fund_source']  = trim((string) ($body['fund_source'] ?? ''));

        // Account code must exist in the chart of accounts (same check PurchaseOrderService does)
        $acctCode = trim((string) ($body['account_code'] ?? ''));
        if ($acctCode !== '' && (int) App\ExecuteScalar('SELECT COUNT(*) FROM ' . $this->tableCoa . ' WHERE account_code = ' . $this->str($acctCode), 'DB') === 0) {
            $errors[] = "Account code '{$acctCode}' does not exist in the chart of accounts.";
        }
        $fields['account_code'] = $acctCode;

        $fields['remarks'] = trim((string) ($body['remarks'] ?? ''));

        // Ledger figures — budget_amount/obligation_amount can be client-supplied (there is no
        // authoritative allotment-release system yet); balance_amount is always server-computed
        // in create()/update() as obligation_amount - net_amount, never trusted from the client.
        $fields['budget_amount']     = is_numeric($body['budget_amount'] ?? null) ? (float) $body['budget_amount'] : 0.0;
        $fields['obligation_amount'] = is_numeric($body['obligation_amount'] ?? null) ? (float) $body['obligation_amount'] : $fields['budget_amount'];

        // Claimant — an optional registered vendor (snapshot wins over manually-typed fields so
        // the claimant record always matches the vendor on file), or free text otherwise.
        $vendor   = null;
        $vendorId = $body['vendor_id'] ?? '';
        if ($vendorId !== '' && $vendorId !== null) {
            if (!ctype_digit((string) $vendorId)) {
                $errors[] = 'vendor_id must be numeric.';
            } else {
                $vendor = App\ExecuteRow(
                    "SELECT vendor_id, vendor_name, address, tin, COALESCE(status, 'ACTIVE') AS status FROM {$this->tableVendor} WHERE vendor_id = " . (int) $vendorId,
                    'DB'
                );
                if (!$vendor) {
                    $errors[] = "Vendor with ID {$vendorId} does not exist.";
                } elseif ($vendor['status'] !== 'ACTIVE') {
                    $errors[] = "Vendor '{$vendor['vendor_name']}' is not active.";
                }
            }
        }
        $fields['vendor_id']         = $vendor ? (int) $vendor['vendor_id'] : null;
        $fields['claimant_name']     = $vendor['vendor_name'] ?? trim((string) ($body['claimant_name'] ?? ''));
        $fields['claimant_address']  = $vendor['address'] ?? trim((string) ($body['claimant_address'] ?? ''));
        $fields['employee_no_tin']   = $vendor['tin'] ?? trim((string) ($body['employee_no_tin'] ?? ''));

        if ($fields['claimant_name'] === '') {
            $errors[] = 'Claimant Name (or a selected Vendor) is required.';
        }

        $particulars = trim((string) ($body['particulars'] ?? ''));
        if ($particulars === '') {
            $errors[] = 'Particulars is required.';
        }
        $fields['particulars'] = $particulars;

        $gross = $body['gross_amount'] ?? null;
        if (!is_numeric($gross) || (float) $gross <= 0) {
            $errors[] = 'Amount must be greater than zero.';
        }
        $gross = is_numeric($gross) ? (float) $gross : 0.0;

        $vatRate  = is_numeric($body['vat_rate'] ?? null) ? (float) $body['vat_rate'] : 0.0;
        $wtaxRate = is_numeric($body['wtax_rate'] ?? null) ? (float) $body['wtax_rate'] : 0.0;
        $vatAmt   = is_numeric($body['vat_amount'] ?? null) ? (float) $body['vat_amount'] : round($gross * $vatRate / 100, 2);
        $wtaxAmt  = is_numeric($body['wtax_amount'] ?? null) ? (float) $body['wtax_amount'] : round($gross * $wtaxRate / 100, 2);

        $fields['gross_amount'] = $gross;
        $fields['vat_rate']     = $vatRate;
        $fields['vat_amount']   = $vatAmt;
        $fields['wtax_rate']    = $wtaxRate;
        $fields['wtax_amount']  = $wtaxAmt;
        $fields['net_amount']   = round($gross - $vatAmt - $wtaxAmt, 2);

        $fields['po_number'] = trim((string) ($body['po_number'] ?? ''));
        $fields['po_amount'] = is_numeric($body['po_amount'] ?? null) ? (float) $body['po_amount'] : null;

        // Status the user is saving as
        $status = strtoupper(trim((string) ($body['status'] ?? 'DRAFT')));
        if (!in_array($status, self::SAVE_STATUSES, true)) {
            $errors[] = 'status must be DRAFT or FOR_FMD_REVIEW when saving; use the status action to move it through FMD/Accounting/Treasury, reject, or cancel.';
        }
        $fields['status'] = $status;

        return [$errors, $fields];
    }

    // ── RFA lookup (read-only; fims.budget_allocation has no CRUD backend of its own) ──

    /** Stamps budget_amount (ceiling, from po_amount or summed line_transaction) onto an RFA row. */
    private function withRfaBudget(array $rfa): array
    {
        $budgetAmount = is_numeric($rfa['po_amount']) && (float) $rfa['po_amount'] > 0 ? (float) $rfa['po_amount'] : 0.0;
        if ($budgetAmount <= 0) {
            $lines = json_decode((string) ($rfa['line_transaction'] ?? '[]'), true) ?: [];
            foreach ($lines as $line) {
                $budgetAmount += (float) ($line['allocated_amount'] ?? 0);
            }
        }
        $rfa['budget_amount'] = round($budgetAmount, 2);

        return $rfa;
    }

    /**
     * RFA picker options — one RFA can back only one DV at a time, so an RFA already referenced by
     * a non-cancelled/rejected voucher is left out entirely (not just disabled). $excludeDvId lets
     * an in-progress edit keep showing the RFA it's already using (that voucher is its own user,
     * not a competing one).
     */
    public function fetchRfaOptions(array $filters = [], ?int $excludeDvId = null): array
    {
        try {
            $where = ["a.allocation_type = 'RFA'", $this->rfaUnusedSql($excludeDvId)];
            if (!empty($filters['fiscal_yr'])) {
                $where[] = 'a.fiscal_year = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['search'])) {
                $kw      = App\QuotedValue('%' . $filters['search'] . '%', App\DataType::STRING, 'DB');
                $where[] = "(a.allocation_number ILIKE {$kw} OR a.memo_ref_no ILIKE {$kw})";
            }
            $whereSql = implode(' AND ', $where);

            $rows = App\ExecuteRows(
                "SELECT a.allocation_id, a.allocation_number, a.memo_ref_no, a.fiscal_year,
                        a.requesting_department_id, d.department_name,
                        a.vendor_id, v.vendor_name, v.address AS vendor_address, v.tin AS vendor_tin,
                        a.po_number, a.po_amount, a.line_transaction
                 FROM {$this->tableRfa} a
                 LEFT JOIN {$this->tableDept}   d ON d.department_id = a.requesting_department_id
                 LEFT JOIN {$this->tableVendor} v ON v.vendor_id = a.vendor_id
                 WHERE {$whereSql}
                 ORDER BY a.allocation_id DESC",
                'DB'
            ) ?: [];

            $rows = array_map(fn (array $r) => $this->withRfaBudget($r), $rows);

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchRfaOptions ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load RFAs.'];
        }
    }

    /** NOT EXISTS clause: true when no other active voucher already uses this RFA. */
    private function rfaUnusedSql(?int $excludeDvId): string
    {
        $exclude = $excludeDvId !== null ? " AND dv.dv_id <> {$excludeDvId}" : '';

        return "NOT EXISTS (
            SELECT 1 FROM {$this->table} dv
            WHERE dv.rfa_allocation_id = a.allocation_id
              AND dv.status NOT IN ('CANCELLED', 'REJECTED'){$exclude}
        )";
    }

    /** One RFA plus its budget ceiling; fails if another active voucher already uses it. */
    public function fetchRfaDetail(int|string $allocationId, ?int $excludeDvId = null): array
    {
        try {
            $id  = (int) $allocationId;
            $rfa = App\ExecuteRow(
                "SELECT a.allocation_id, a.allocation_number, a.memo_ref_no, a.fiscal_year,
                        a.requesting_department_id, d.department_name,
                        a.vendor_id, v.vendor_name, v.address AS vendor_address, v.tin AS vendor_tin,
                        a.po_number, a.po_amount, a.line_transaction
                 FROM {$this->tableRfa} a
                 LEFT JOIN {$this->tableDept}   d ON d.department_id = a.requesting_department_id
                 LEFT JOIN {$this->tableVendor} v ON v.vendor_id = a.vendor_id
                 WHERE a.allocation_id = {$id} AND a.allocation_type = 'RFA'",
                'DB'
            );
            if (!$rfa) {
                return ['success' => false, 'message' => "RFA #{$id} not found.", 'httpCode' => 404];
            }

            $exclude = $excludeDvId !== null ? " AND dv_id <> {$excludeDvId}" : '';
            $used    = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE rfa_allocation_id = {$id} AND status NOT IN ('CANCELLED', 'REJECTED'){$exclude}",
                'DB'
            ) > 0;
            if ($used) {
                return ['success' => false, 'message' => "RFA {$rfa['allocation_number']} is already used by another disbursement voucher.", 'httpCode' => 400];
            }

            return ['success' => true, 'data' => $this->withRfaBudget($rfa)];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchRfaDetail ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load RFA.'];
        }
    }

    // ── queries ──

    public function fetchAlls(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $where = ['1=1'];
            if (!empty($filters['status'])) {
                $where[] = 'dv.status = ' . $this->str(strtoupper($filters['status']));
            }
            if (!empty($filters['fiscal_yr'])) {
                $where[] = 'dv.fiscal_yr = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['expense_class'])) {
                $where[] = 'dv.expense_class = ' . $this->str(strtoupper($filters['expense_class']));
            }
            if (!empty($filters['search'])) {
                $kw      = App\QuotedValue('%' . $filters['search'] . '%', App\DataType::STRING, 'DB');
                $where[] = "(dv.dv_no ILIKE {$kw} OR dv.claimant_name ILIKE {$kw} OR dv.rfa_allocation_number ILIKE {$kw})";
            }
            $whereSql = implode(' AND ', $where);

            $select = "SELECT dv.* FROM {$this->table} dv WHERE {$whereSql} ORDER BY dv.created_at DESC, dv.dv_id DESC";

            if ($page === null) {
                return ['success' => true, 'data' => App\ExecuteRows($select, 'DB') ?: []];
            }

            $limit  = max(1, $limit);
            $offset = (max(1, $page) - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} dv WHERE {$whereSql}", 'DB');

            $summary = App\ExecuteRow(
                "SELECT COALESCE(SUM(dv.net_amount), 0) AS total_amount,
                        COUNT(*) FILTER (WHERE dv.status = 'RELEASED')                              AS approved,
                        COUNT(*) FILTER (WHERE dv.status IN ('FOR_FMD_REVIEW','FOR_ACCOUNTING','FOR_TREASURY')) AS pending,
                        COUNT(*) FILTER (WHERE dv.status IN ('REJECTED','CANCELLED'))                AS rejected
                 FROM {$this->table} dv WHERE {$whereSql}",
                'DB'
            );

            return [
                'success'    => true,
                'data'       => App\ExecuteRows($select . " LIMIT {$limit} OFFSET {$offset}", 'DB') ?: [],
                'summary'    => $summary,
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchAlls ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load disbursement vouchers.'];
        }
    }

    public function fetchFiscalYears(): array
    {
        try {
            $rows = App\ExecuteRows("SELECT fiscal_yr FROM {$this->tableFiscal} ORDER BY fiscal_yr DESC", 'DB') ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchFiscalYears ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load fiscal years.'];
        }
    }

    public function fetchById(int|string $id): array
    {
        try {
            $dvId = (int) $id;
            $row  = App\ExecuteRow("SELECT * FROM {$this->table} WHERE dv_id = {$dvId}", 'DB');
            if (!$row) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }

            $row['allowed_transitions'] = self::TRANSITIONS[$row['status']] ?? [];
            $row['is_editable']        = in_array($row['status'], self::EDITABLE, true);

            return ['success' => true, 'data' => $row];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchById ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load disbursement voucher.'];
        }
    }

    /** Next free "{YY}-{MM}-{NNNN}" for the month of $dvDate (uses MAX, so deletes can't cause a clash). */
    public function fetchDvNumber(string $dvDate): array
    {
        try {
            if (!$this->validDate($dvDate)) {
                return ['success' => false, 'message' => 'dv_date must be a valid date (YYYY-MM-DD).'];
            }

            return ['success' => true, 'data' => ['dv_no' => $this->nextDvNumber($dvDate)]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchDvNumber ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to generate DV number.'];
        }
    }

    private function nextDvNumber(string $dvDate): string
    {
        $prefix = substr($dvDate, 2, 2) . '-' . substr($dvDate, 5, 2) . '-';
        $max    = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(dv_no FROM '[0-9]+$') AS INTEGER)), 0)
             FROM {$this->table}
             WHERE dv_no LIKE " . App\QuotedValue($prefix . '%', App\DataType::STRING, 'DB') . " AND dv_no ~ '-[0-9]+$'",
            'DB'
        );

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    // ── writes ──

    public function create(array $body, int $userId): array
    {
        try {
            [$errors, $f] = $this->prepare($body);

            $dvNo = trim((string) ($body['dv_no'] ?? ''));
            if ($dvNo !== '' && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE dv_no = " . $this->str($dvNo), 'DB') > 0) {
                $errors[] = "DV Number '{$dvNo}' already exists.";
            }
            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }
            if ($dvNo === '') {
                $dvNo = $this->nextDvNumber($f['dv_date']);
            }

            $balance = round($f['obligation_amount'] - $f['net_amount'], 2);

            $submitted = $f['status'] === 'FOR_FMD_REVIEW';
            $uid       = $userId > 0 ? (string) $userId : 'NULL';

            $row = App\ExecuteRow(
                "INSERT INTO {$this->table}
                    (dv_no, dv_date, fiscal_yr, expense_class, status,
                     rfa_allocation_id, rfa_allocation_number, requesting_department_id, dept_project, fund_source,
                     vendor_id, claimant_name, claimant_address, employee_no_tin,
                     particulars, gross_amount, vat_rate, vat_amount, wtax_rate, wtax_amount,
                     po_number, po_amount,
                     account_code, budget_amount, obligation_amount, balance_amount,
                     remarks, submitted_at, submitted_by, created_by)
                 VALUES ("
                . $this->str($dvNo) . ', ' . $this->str($f['dv_date']) . ', ' . $f['fiscal_yr'] . ', ' . $this->strOrNull($f['expense_class']) . ', ' . $this->str($f['status']) . ', '
                . $this->intOrNull($f['rfa_allocation_id']) . ', ' . $this->strOrNull($f['rfa_allocation_number']) . ', '
                . $this->intOrNull($f['requesting_department_id']) . ', ' . $this->strOrNull($f['dept_project']) . ', '
                . $this->strOrNull($f['fund_source']) . ', '
                . $this->intOrNull($f['vendor_id']) . ', '
                . $this->strOrNull($f['claimant_name']) . ', ' . $this->strOrNull($f['claimant_address']) . ', '
                . $this->strOrNull($f['employee_no_tin']) . ', '
                . $this->strOrNull($f['particulars']) . ', ' . (float) $f['gross_amount'] . ', '
                . (float) $f['vat_rate'] . ', ' . (float) $f['vat_amount'] . ', ' . (float) $f['wtax_rate'] . ', ' . (float) $f['wtax_amount'] . ', '
                . $this->strOrNull($f['po_number']) . ', ' . $this->numOrNull($f['po_amount']) . ', '
                . $this->strOrNull($f['account_code']) . ', ' . (float) $f['budget_amount'] . ', ' . (float) $f['obligation_amount'] . ', '
                . (float) $balance . ', '
                . $this->strOrNull($f['remarks']) . ', '
                . ($submitted ? "CURRENT_TIMESTAMP, {$uid}" : 'NULL, NULL') . ', '
                . $uid . ')
                 RETURNING dv_id, dv_no',
                'DB'
            );

            return [
                'success'  => true,
                'message'  => 'Disbursement Voucher created successfully.',
                'data'     => ['dv_id' => (int) $row['dv_id'], 'dv_no' => $row['dv_no'], 'status' => $f['status']],
                'httpCode' => 201,
            ];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::create ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to create disbursement voucher.'];
        }
    }

    /** Full replace of the editable fields; DRAFT / REJECTED only. dv_no never changes. */
    public function update(int|string $id, array $body, int $userId): array
    {
        try {
            $dvId = (int) $id;
            $cur  = App\ExecuteRow("SELECT dv_id, status FROM {$this->table} WHERE dv_id = {$dvId}", 'DB');
            if (!$cur) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            if (!in_array($cur['status'], self::EDITABLE, true)) {
                return ['success' => false, 'message' => "Only DRAFT or REJECTED disbursement vouchers can be edited (current status: {$cur['status']})."];
            }

            [$errors, $f] = $this->prepare($body);
            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }

            $balance = round($f['obligation_amount'] - $f['net_amount'], 2);

            $submitted = $f['status'] === 'FOR_FMD_REVIEW';
            $uid       = $userId > 0 ? (string) $userId : 'NULL';

            App\Execute(
                "UPDATE {$this->table} SET
                    dv_date = " . $this->str($f['dv_date']) . ',
                    fiscal_yr = ' . $f['fiscal_yr'] . ',
                    expense_class = ' . $this->strOrNull($f['expense_class']) . ',
                    rfa_allocation_id = ' . $this->intOrNull($f['rfa_allocation_id']) . ',
                    rfa_allocation_number = ' . $this->strOrNull($f['rfa_allocation_number']) . ',
                    requesting_department_id = ' . $this->intOrNull($f['requesting_department_id']) . ',
                    dept_project = ' . $this->strOrNull($f['dept_project']) . ',
                    fund_source = ' . $this->strOrNull($f['fund_source']) . ',
                    vendor_id = ' . $this->intOrNull($f['vendor_id']) . ',
                    claimant_name = ' . $this->strOrNull($f['claimant_name']) . ',
                    claimant_address = ' . $this->strOrNull($f['claimant_address']) . ',
                    employee_no_tin = ' . $this->strOrNull($f['employee_no_tin']) . ',
                    particulars = ' . $this->strOrNull($f['particulars']) . ',
                    gross_amount = ' . (float) $f['gross_amount'] . ',
                    vat_rate = ' . (float) $f['vat_rate'] . ',
                    vat_amount = ' . (float) $f['vat_amount'] . ',
                    wtax_rate = ' . (float) $f['wtax_rate'] . ',
                    wtax_amount = ' . (float) $f['wtax_amount'] . ',
                    po_number = ' . $this->strOrNull($f['po_number']) . ',
                    po_amount = ' . $this->numOrNull($f['po_amount']) . ',
                    account_code = ' . $this->strOrNull($f['account_code']) . ',
                    budget_amount = ' . (float) $f['budget_amount'] . ',
                    obligation_amount = ' . (float) $f['obligation_amount'] . ',
                    balance_amount = ' . (float) $balance . ',
                    remarks = ' . $this->strOrNull($f['remarks']) . ',
                    status = ' . $this->str($f['status']) . ',
                    submitted_at = ' . ($submitted ? 'CURRENT_TIMESTAMP' : 'NULL') . ',
                    submitted_by = ' . ($submitted ? $uid : 'NULL') . ',
                    rejected_at = NULL, rejected_by = NULL, rejection_reason = NULL,
                    updated_by = ' . $uid . ",
                    updated_at = CURRENT_TIMESTAMP
                 WHERE dv_id = {$dvId}",
                'DB'
            );

            return ['success' => true, 'message' => 'Disbursement Voucher updated successfully.', 'data' => ['dv_id' => $dvId, 'status' => $f['status']]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::update ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update disbursement voucher.'];
        }
    }

    /** The Responsibility Center (uac.users.rc_id) the current user's account belongs to, if any. */
    private function currentUserRcId(): ?string
    {
        $userId = (int) App\CurrentUserID();
        if ($userId <= 0) {
            return null;
        }

        return App\ExecuteScalar("SELECT rc_id FROM {$this->tableUsers} WHERE user_id = {$userId}", 'DB') ?: null;
    }

    /**
     * Null if the current user may move $cur['status'] -> $new, otherwise the error message to
     * return. Sysadmin always passes (IsSysAdmin() bypass, project-wide convention). Whoever holds
     * $cur['status'] (STAGE_HOLDER_RC) may move it forward or reject it; CANCELLED is allowed by
     * that same holder OR the voucher's own creator. DRAFT (Originating holds it, no entry in
     * STAGE_HOLDER_RC) is open to anyone authenticated for both submitting and cancelling their own
     * draft — "their own" is enforced by the creator check in the CANCELLED branch below.
     */
    private function authorizeTransition(array $cur, string $new): ?string
    {
        if (App\IsSysAdmin()) {
            return null;
        }

        $holderRc = self::STAGE_HOLDER_RC[$cur['status']] ?? null; // null while DRAFT
        $userRc   = $this->currentUserRcId();

        if ($new === 'CANCELLED') {
            $isCreator = (int) ($cur['created_by'] ?? 0) > 0 && (int) $cur['created_by'] === (int) App\CurrentUserID();
            $isHolder  = $holderRc !== null && $userRc === $holderRc;

            return ($isCreator || $isHolder) ? null : 'Only the voucher\'s creator or the office currently holding it can cancel it.';
        }
        if ($holderRc === null) {
            return null; // DRAFT -> FOR_FMD_REVIEW: Originating submits, open to anyone authenticated.
        }
        $label = self::STAGE_RC_LABEL[$holderRc] ?? $holderRc;

        return $userRc === $holderRc ? null : "Only {$label} staff can do that.";
    }

    /** Moves a DV along TRANSITIONS. REJECTED needs a reason; $notes is stored as rejection_reason. */
    public function changeStatus(int|string $id, string $newStatus, int $userId, ?string $notes = null): array
    {
        try {
            $dvId = (int) $id;
            $new  = strtoupper(trim($newStatus));
            $cur  = App\ExecuteRow("SELECT dv_id, status, created_by FROM {$this->table} WHERE dv_id = {$dvId}", 'DB');
            if (!$cur) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }

            $allowed = self::TRANSITIONS[$cur['status']] ?? [];
            if (!in_array($new, $allowed, true)) {
                return ['success' => false, 'message' => "Cannot change a {$cur['status']} disbursement voucher to {$new}."];
            }
            $authError = $this->authorizeTransition($cur, $new);
            if ($authError !== null) {
                return ['success' => false, 'message' => $authError, 'httpCode' => 403];
            }
            $notes = trim((string) $notes);
            if ($new === 'REJECTED' && $notes === '') {
                return ['success' => false, 'message' => 'A reason is required when rejecting a disbursement voucher.'];
            }

            $uid = $userId > 0 ? (string) $userId : 'NULL';
            $set = ['status = ' . $this->str($new), 'updated_at = CURRENT_TIMESTAMP', "updated_by = {$uid}"];
            if ($new === 'FOR_FMD_REVIEW') {
                $set[] = "submitted_at = CURRENT_TIMESTAMP, submitted_by = {$uid}";
                $set[] = 'rejected_at = NULL, rejected_by = NULL, rejection_reason = NULL';
            } elseif ($new === 'FOR_ACCOUNTING') {
                $set[] = "fmd_approved_at = CURRENT_TIMESTAMP, fmd_approved_by = {$uid}";
            } elseif ($new === 'FOR_TREASURY') {
                $set[] = "accounting_processed_at = CURRENT_TIMESTAMP, accounting_processed_by = {$uid}";
            } elseif ($new === 'RELEASED') {
                $set[] = "treasury_released_at = CURRENT_TIMESTAMP, treasury_released_by = {$uid}";
            } elseif ($new === 'REJECTED') {
                $set[] = "rejected_at = CURRENT_TIMESTAMP, rejected_by = {$uid}, rejection_reason = " . $this->str($notes);
            }

            // Guard on the status we read so two concurrent actions can't both win.
            App\Execute(
                "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE dv_id = {$dvId} AND status = " . $this->str($cur['status']),
                'DB'
            );
            $now = App\ExecuteScalar("SELECT status FROM {$this->table} WHERE dv_id = {$dvId}", 'DB');
            if ($now !== $new) {
                return ['success' => false, 'message' => 'The disbursement voucher was changed by someone else. Please reload and try again.'];
            }

            return ['success' => true, 'message' => "Disbursement Voucher is now {$new}.", 'data' => ['dv_id' => $dvId, 'status' => $new]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::changeStatus ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to change disbursement voucher status.'];
        }
    }

    public function delete(int|string $id): array
    {
        try {
            $dvId = (int) $id;
            $cur  = App\ExecuteRow("SELECT dv_no, status FROM {$this->table} WHERE dv_id = {$dvId}", 'DB');
            if (!$cur) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            if (!in_array($cur['status'], self::DELETABLE, true)) {
                return ['success' => false, 'message' => "Only DRAFT or CANCELLED disbursement vouchers can be deleted (current status: {$cur['status']})."];
            }

            App\Execute("DELETE FROM {$this->table} WHERE dv_id = {$dvId}", 'DB');

            return ['success' => true, 'message' => "Disbursement Voucher {$cur['dv_no']} deleted successfully."];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::delete ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to delete disbursement voucher.'];
        }
    }
}
