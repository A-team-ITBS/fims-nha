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
 *
 * net_amount = gross_amount - total deductions - total withholding tax, service-computed
 * (not a generated column — see "03 - alter_table/17 - disbursement_vouchers_drop_tax_fields_add_sl.sql").
 * Deductions (fims.disbursement_voucher_deductions, e.g. contractor retention) are entered by
 * Originating at create()/update() time, same stage as gross_amount itself. Withholding tax
 * (fims.disbursement_voucher_atc_lines, one row per BIR ATC classification — a DV can withhold
 * under more than one) is added later by Accounting, alongside the Journal Entry Voucher, in
 * saveJournalEntry(). Both recompute net_amount/balance_amount when they change.
 *
 * "RFA" has no dedicated table - fims.budget_allocation rows where
 * allocation_type = 'RFA' stand in for it. That table's own CRUD backend
 * doesn't exist yet (the CBA/RFA form is frontend-only), so this service only
 * adds a narrow, read-only lookup over it (fetchRfaOptions / fetchRfaDetail) -
 * no write path, no FK.
 *
 * Status is a plain column moved by changeStatus(), modeling the real office review
 * chain (Originating -> FMD -> Accounting -> Treasury) as a straight-line pipeline.
 * Accounting itself is a three-step internal review (Section Chief processes the DV,
 * Division Manager reviews, Department Manager approves) before the voucher is released
 * to Treasury — same state-machine shape as PurchaseOrderService otherwise:
 *   DRAFT                  -> FOR_FMD_REVIEW | CANCELLED                       (Originating submits)
 *   FOR_FMD_REVIEW          -> FOR_ACCOUNTING | REJECTED | CANCELLED            (FMD checks the obligation, approves)
 *   FOR_ACCOUNTING          -> FOR_DIVISION_MANAGER | REJECTED | CANCELLED      (Accounting Section Chief processes the DV + prepares the JEV)
 *   FOR_DIVISION_MANAGER    -> FOR_DEPARTMENT_MANAGER | REJECTED | CANCELLED    (Division Manager reviews)
 *   FOR_DEPARTMENT_MANAGER  -> FOR_TREASURY | REJECTED | CANCELLED              (Department Manager approves)
 *   FOR_TREASURY            -> RELEASED | CANCELLED                             (Treasury releases payment)
 *   REJECTED                -> CANCELLED           (or edited + re-saved via update())
 *   RELEASED / CANCELLED are terminal.
 *
 * Accounting's first step (FOR_ACCOUNTING, the Section Chief) requires preparing a Journal
 * Entry Voucher (saveJournalEntry() - fims.journal_entry_vouchers/_lines, a balanced single
 * combined entry: Dr the DV's object-of-expenditure account_code, Cr Due to BIR for any
 * withholding tax, Cr Accounts Payable for the net) - checked by changeStatus() before
 * FOR_ACCOUNTING can advance to FOR_DIVISION_MANAGER. The JEV is posted to the General Ledger
 * only at the end of the chain, when the Department Manager approves it onward to Treasury
 * (FOR_DEPARTMENT_MANAGER -> FOR_TREASURY). ATC withholding lines are optional (0 or more) and saved in
 * the same call. Treasury's actual cash entry (AP -> Cash) isn't modeled; this project
 * treats the JEV as the single combined obligation+payment entry.
 *
 * changeStatus() enforces who may move a voucher into each stage (see
 * STAGE_HOLDER_RC / authorizeTransition()): the caller's uac.users.rc_id (Responsibility
 * Center — fims.responsibility_code, the org-chart column this project already
 * uses for fims.budget_req.rc_id) must match the office that owns the target
 * stage, unless they're a superadmin (uac.users.user_level_id = '-1' bypasses
 * everything — GlobalService::getUserRcScope()['is_admin'], matching the rest of
 * this project's convention; NOT App\IsSysAdmin(), which only tracks PHPMaker's
 * own hardcoded admin-login path and is never true for a DB-authenticated user).
 * Submitting to FMD (DRAFT -> FOR_FMD_REVIEW) is open to anyone, same as creating
 * the voucher in the first place — there's no "Originating office" concept to
 * gate against.
 */
class DisbursementVoucherService
{
    private string $table         = 'fims.disbursement_vouchers';
    private string $tableVendor   = 'fims.vendors';
    private string $tableRfa      = 'fims.budget_allocation';
    private string $tableUsers    = 'uac.users';
    private string $tableFiscal   = 'fims.fiscal_year';
    private string $tableBooks    = 'fims.books';
    private string $tableCoa      = 'fims.chart_of_accounts';
    private string $tableAtc      = 'fims.atc_codes';
    private string $tableJev      = 'fims.journal_entry_vouchers';
    private string $tableJevLines = 'fims.journal_entry_voucher_lines';
    private string $tableViewJev  = 'fims.v_journal_entry_vouchers';
    private string $tableRc              = 'fims.responsibility_code';
    private string $tableDeductionTypes  = 'fims.deduction_types';
    private string $tableDvDeductions    = 'fims.disbursement_voucher_deductions';
    private string $tableDvAtcLines      = 'fims.disbursement_voucher_atc_lines';
    private string $tableSettingsStatus  = 'fims.settings_status';
    private string $tableBudgetCategory  = 'fims.budget_category';
    private string $tableRfaProjects     = 'fims.budget_allocation_project';
    private string $tableFundTracking    = 'fims.fund_tracking';
    private string $tableBankAccounts    = 'fims.bank_accounts';

    // Classification tag only (reporting/filtering) — never changes which fields the voucher form
    // shows. NOT a manual/pickable field: it is derived server-side from the voucher's own
    // account_code (see deriveExpenseClass()) — the account already says what kind of spend this
    // is, so asking the preparer to also pick PS/MOOE/CAPITAL_OUTLAY/EQUIPMENT_OUTLAY separately
    // would just be a second, potentially-contradicting source of truth. Any expense_class the
    // client sends in the request body is ignored.
    // NOTE: fims.purchase_orders.expense_class (PurchaseOrderService::EXPENSE_CLASSES) is still a
    // separate, manually-validated list (['PS', 'MOOE', 'CAPITAL_OUTLAY', 'CO', 'OTHER']) — that
    // module was NOT changed here; only this one's concept of expense_class moved to COA-derived.
    private const EXPENSE_CLASSES = ['MOOE', 'PS', 'CAPITAL_OUTLAY', 'EQUIPMENT_OUTLAY', 'OTHER'];

    // Deduction types a voucher may carry only once (fims.deduction_types.code) — see prepareDeductions().
    private const SINGLE_DEDUCTION_TYPES = ['RETENTION', 'RECOUPMENT', 'PENALTY_TAX', 'VAT'];

    private const EDITABLE      = ['DRAFT', 'REJECTED'];
    private const DELETABLE     = ['DRAFT', 'CANCELLED'];
    private const SAVE_STATUSES = ['DRAFT', 'FOR_FMD_REVIEW'];
    private const TRANSITIONS   = [
        'DRAFT'                  => ['FOR_FMD_REVIEW', 'CANCELLED'],
        'FOR_FMD_REVIEW'         => ['FOR_ACCOUNTING', 'REJECTED', 'CANCELLED'],
        'FOR_ACCOUNTING'         => ['FOR_DIVISION_MANAGER', 'REJECTED', 'CANCELLED'],
        'FOR_DIVISION_MANAGER'   => ['FOR_DEPARTMENT_MANAGER', 'REJECTED', 'CANCELLED'],
        'FOR_DEPARTMENT_MANAGER' => ['FOR_TREASURY', 'REJECTED', 'CANCELLED'],
        'FOR_TREASURY'           => ['RELEASED', 'CANCELLED'],
        'REJECTED'               => ['CANCELLED'],
        'RELEASED'               => [],
        'CANCELLED'              => [],
    ];

    // Responsibility Center (fims.responsibility_code.rc_id) that HOLDS the voucher while it sits
    // in this status — the only office allowed to move it forward, reject it, or (together with
    // the creator) cancel it from here. Keyed by the status itself, not by where it's headed: e.g.
    // STAGE_HOLDER_RC['FOR_FMD_REVIEW'] is FMD because FMD is who's holding it for review right
    // now, which is also, not coincidentally, who must approve moving it on to FOR_ACCOUNTING.
    // DRAFT has no entry (Originating holds it, open to anyone) — see authorizeTransition().
    // The three Accounting steps (FOR_ACCOUNTING -> FOR_DIVISION_MANAGER -> FOR_DEPARTMENT_MANAGER)
    // are all held by the Accounting Department (RC 012) — Section Chief, Division Manager and
    // Department Manager are positions WITHIN that one office, so any Accounting-Department user may
    // advance each step. (Enforcing a distinct approver per step would need per-position roles, which
    // this project doesn't model — see DisbursementVoucherService's docblock.)
    private const STAGE_HOLDER_RC = [
        'FOR_FMD_REVIEW'         => '013', // Financial Management Department (FMD)
        'FOR_ACCOUNTING'         => '012', // Accounting Department — Section Chief processes + prepares JEV
        'FOR_DIVISION_MANAGER'   => '012', // Accounting Department — Division Manager reviews
        'FOR_DEPARTMENT_MANAGER' => '012', // Accounting Department — Department Manager approves
        'FOR_TREASURY'           => '014', // Treasury Department
    ];
    private const STAGE_RC_LABEL = [
        '013' => 'Financial Management Department (FMD)',
        '012' => 'Accounting Department',
        '014' => 'Treasury Department',
    ];

    // fims.settings_status.status_for for this voucher's pipeline status — see
    // "03 - alter_table/21 - disbursement_vouchers_status_to_settings_status.sql" and its seed rows
    // in "02 - sample_data/01 - fims_settings_status.sql". Same lookup-table convention
    // BudgetReqService uses for fims.budget_req.status_id; the ALL_CAPS_UNDERSCORE names above
    // (EDITABLE/DELETABLE/SAVE_STATUSES/TRANSITIONS/STAGE_HOLDER_RC) are unchanged — only where a
    // voucher's current status is stored moved off a free-text column onto this shared table.
    private const STATUS_FOR = 'disbursement_voucher';

    /**
     * Looked up by name each time rather than hardcoding its id — the id a seed row lands on
     * depends on insert order, which isn't guaranteed to be stable across environments or
     * re-seeds. Same convention as BudgetReqService::getStatusIdByName().
     */
    private function getStatusIdByName(string $name): ?int
    {
        $row = App\ExecuteRow(
            "SELECT id FROM {$this->tableSettingsStatus} WHERE status_for = " . $this->str(self::STATUS_FOR) . ' AND name = ' . $this->str($name),
            'DB'
        );

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    private function getDefaultStatusId(): ?int
    {
        return $this->getStatusIdByName('DRAFT');
    }

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

        // Book (fims.books — Main Office/CISFA/NGC/MPLC-PPMO/PPMO) isn't a DV-header field — it's an
        // accounting classification chosen on the Accounting Entries section (same place as Account
        // Code/SL Code), so it's not validated/stored here. Just passed through raw for create()/
        // update()'s own optional Accounting Entries pre-fill call into persistJournalEntry(), which
        // does the real validation — see "03 - alter_table/30".
        $fields['book_id'] = $body['book_id'] ?? null;

        // Same "passed through raw, validated inside persistJournalEntry()" treatment as book_id
        // above — REGULAR | ACCRUAL | DEPRECIATION | PREPAYMENTS | CLOSING, see "03 - alter_table/32".
        $fields['jev_classification'] = $body['jev_classification'] ?? null;

        // Expense class: derived from account_code below (deriveExpenseClass()), once account_code
        // itself is resolved — NOT read from $body here; see the EXPENSE_CLASSES doc comment above.

        // RFA — soft reference into fims.budget_allocation (allocation_type = 'RFA'); at least one is
        // required, same as the paper General Form No. 1 always citing the allotment(s) it's drawn
        // against. The link lives entirely on the RFA SIDE (budget_allocation.dv_id — see
        // "03 - alter_table/23 - budget_allocation_add_dv_id.sql") — this table carries no RFA
        // column of its own at all (the old single rfa_allocation_id/rfa_allocation_number snapshot
        // was dropped in "03 - alter_table/26 - ...-drop_rfa_allocation_columns.sql" once nothing
        // read it anymore). The actual linking (budget_allocation.dv_id set/cleared for every
        // selected/deselected RFA) happens in create()/update() after this voucher's own row is
        // written — see linkRfas().
        $rfaIdsRaw = $body['rfa_allocation_ids'] ?? ($body['rfa_allocation_id'] ?? []);
        if (!is_array($rfaIdsRaw)) {
            $rfaIdsRaw = [$rfaIdsRaw];
        }
        $rfaIdsRaw = array_values(array_unique(array_filter(
            array_map('strval', $rfaIdsRaw),
            fn ($v) => trim($v) !== ''
        )));

        $rfas = [];
        // A payroll REMITTANCE DV (Deductions & Remittances → Create Remittance DV) pays withheld deductions already
        // obligated by the payroll DV's RFA — it draws no new allotment, so it carries no RFA of its own.
        $remittanceId = $body['payroll_remittance_id'] ?? null;
        $fields['_remittanceId'] = ($remittanceId !== null && $remittanceId !== '' && ctype_digit((string) $remittanceId)) ? (int) $remittanceId : null;

        // Likewise a Remittance DV from the Remittance List page (Remit button): it pays what the month's approved vouchers withheld
        // (EWT / Withholding VAT / Penalty Tax → BIR). Validated / linked in create()/update() (RemittanceListService).
        $remitPeriod = trim((string) ($body['remit_period'] ?? ''));
        $remitType   = strtoupper(trim((string) ($body['remit_type'] ?? '')));
        $remitVendor = trim((string) ($body['remit_vendor'] ?? '')); // the one vendor whose withheld tax this Remittance DV pays
        $fields['_dvRemit'] = ($remitPeriod !== '' && $remitType !== '') ? ['period' => $remitPeriod, 'type' => $remitType, 'vendor' => $remitVendor] : null;

        if (!$rfaIdsRaw && !$fields['_remittanceId'] && !$fields['_dvRemit']) {
            $errors[] = 'At least one RFA is required.';
        } else {
            foreach ($rfaIdsRaw as $rfaId) {
                if (!ctype_digit($rfaId)) {
                    $errors[] = "rfa_allocation_ids: '{$rfaId}' must be numeric.";
                    continue;
                }
                $rfa = App\ExecuteRow(
                    "SELECT a.allocation_id, a.allocation_number, a.request_referrence_no, a.requesting_rc_id, a.dv_id, a.fiscal_year,
                            st.name AS status_name
                     FROM {$this->tableRfa} a
                     LEFT JOIN {$this->tableSettingsStatus} st ON st.id = a.status_id
                     WHERE a.allocation_id = " . (int) $rfaId . " AND a.allocation_type = 'RFA'",
                    'DB'
                );
                if (!$rfa) {
                    $errors[] = "RFA #{$rfaId} does not exist.";
                    continue;
                }
                // Only an Approved RFA carries an allotment a DV can draw from.
                if (($rfa['status_name'] ?? '') !== 'Approved') {
                    $label = $rfa['allocation_number'] ?: ($rfa['request_referrence_no'] ?: "#{$rfaId}");
                    $errors[] = "RFA {$label} is " . ($rfa['status_name'] ?: 'not approved') . ' — only an Approved RFA can fund a Disbursement Voucher.';
                    continue;
                }
                $rfas[] = $rfa;
            }
        }
        $fields['requesting_department_id']  = null; // fims.budget_allocation now keys its requesting office by requesting_rc_id (a Responsibility Center), which doesn't fit this INTEGER column — see "14 - budget_allocation_requesting_department_id_to_requesting_rc_id.sql"
        $fields['_rfaRows'] = $rfas; // internal only — never persisted as a DV column, see linkRfas()

        // SARO — soft reference into fims.fund_tracking (one SARO per DV, unlike the RFA list above):
        // the link lives entirely on the SARO SIDE (fund_tracking.dv_id — see "03 - alter_table/61 -
        // fund_tracking_add_dv_bank_account.sql") — this table carries no SARO column of its own. The
        // actual linking (fund_tracking.dv_id set/cleared) happens in create()/update() after this
        // voucher's own row is written — see linkSaro(). Optional: a DV need not cite a SARO.
        $saroIdRaw = trim((string) ($body['saro_id'] ?? ''));
        $saroRow = null;
        if ($saroIdRaw !== '') {
            if (!ctype_digit($saroIdRaw)) {
                $errors[] = 'saro_id must be numeric.';
            } else {
                $saroRow = App\ExecuteRow(
                    "SELECT ft.id, ft.saro_id, ft.dv_id, ft.bank_account_id
                     FROM {$this->tableFundTracking} ft
                     WHERE ft.id = " . (int) $saroIdRaw . ' AND ft.deleted_at IS NULL',
                    'DB'
                );
                if (!$saroRow) {
                    $errors[] = "SARO #{$saroIdRaw} does not exist.";
                }
            }
        }
        $fields['_saroRow'] = $saroRow; // internal only — never persisted as a DV column, see linkSaro()

        // Payroll DV (optional): the imported payroll month this voucher pays (Payroll Management —
        // PayrollManagementService). Validated / linked in create()/update() (payroll_records.disbursement_voucher_id).
        $payrollPeriodId = $body['payroll_period_id'] ?? null;
        $fields['_payrollPeriodId'] = ($payrollPeriodId !== null && $payrollPeriodId !== '' && ctype_digit((string) $payrollPeriodId)) ? (int) $payrollPeriodId : null;

        $fields['dept_project'] = trim((string) ($body['dept_project'] ?? ''));
        $fields['fund_source']  = trim((string) ($body['fund_source'] ?? ''));

        // Account code is required, must exist in the chart of accounts, and must be
        // posting_allowed (same check saveJournalEntry() applies to each JEV line's account_code)
        // — so the Journal Entry's auto-filled Dr line (see readone.php's vje_syncAutoLines) is
        // never blank by the time a DV reaches Accounting.
        $acctCode = trim((string) ($body['account_code'] ?? ''));
        if ($acctCode === '') {
            $errors[] = 'Account Code is required.';
        } else {
            $account = App\ExecuteRow(
                'SELECT posting_allowed FROM ' . $this->tableCoa . ' WHERE account_code = ' . $this->str($acctCode),
                'DB'
            );
            if (!$account) {
                $errors[] = "Account code '{$acctCode}' does not exist in the chart of accounts.";
            } elseif (!$account['posting_allowed']) {
                $errors[] = "Account code '{$acctCode}' is not postable (it's a summary/header account).";
            }
        }
        $fields['account_code']   = $acctCode;
        $fields['expense_class']  = $this->deriveExpenseClass($acctCode);

        $fields['remarks'] = trim((string) ($body['remarks'] ?? ''));

        // Ledger figures — budget_amount/obligation_amount can be client-supplied (there is no
        // authoritative allotment-release system yet); payments_prior_amount and balance_amount
        // are always server-computed in create()/update() (see priorPaymentsForRfa()), never
        // trusted from the client.
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

        $fields['gross_amount'] = $gross;

        // Deductions (optional) — non-tax amounts withheld from gross (e.g. contractor
        // retention), entered by Originating alongside the rest of this form. Withholding tax
        // is separate — added later by Accounting via saveJournalEntry(), not here.
        [$deductionErrors, $deductionLines] = $this->prepareDeductions($body['deductions'] ?? [], $gross);
        $errors            = array_merge($errors, $deductionErrors);
        $fields['deductions'] = $deductionLines;
        $totalDeductions   = array_sum(array_column($deductionLines, 'deduction_amount'));
        $fields['net_amount'] = round($gross - $totalDeductions, 2);
        if ($gross > 0 && $fields['net_amount'] < 0) {
            $errors[] = 'Total deductions (' . number_format($totalDeductions, 2) . ') cannot exceed the Amount (' . number_format($gross, 2) . ').';
        }

        $fields['po_number'] = trim((string) ($body['po_number'] ?? ''));
        $fields['po_amount'] = is_numeric($body['po_amount'] ?? null) ? (float) $body['po_amount'] : null;
        // No IAR, no DV: a PO must have been inspected and accepted before a voucher can be prepared against it.
        if ($fields['po_number'] !== '' && $this->iarOfPo($fields['po_number']) === null) {
            $errors[] = "PO '{$fields['po_number']}' has no IAR (Inspection and Acceptance Report) yet. Record the IAR for this PO before preparing a disbursement voucher.";
        }

        // ATC Code and Accounting Entries (both optional) — normally Accounting's job during
        // FOR_ACCOUNTING via saveJournalEntry(), but Originating can pre-fill either here too; full
        // validation happens in persistJournalEntry(), not here (same "parse now, validate at the
        // point of use" split as `deductions` above).
        $fields['atc_lines'] = is_array($body['atc_lines'] ?? null) ? $body['atc_lines'] : [];
        $fields['lines']     = is_array($body['lines'] ?? null) ? $body['lines'] : [];

        // Supporting Documents (optional) — a batch already uploaded client-side via
        // POST /upload/multi-upload (same fims.file_attachments convention as
        // fims.budget_allocation; see BudgetAllocationService's identical check).
        $nodeId = trim((string) ($body['multiple_node_id'] ?? ''));
        if ($nodeId !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $nodeId)) {
            $errors[] = 'multiple_node_id must be a UUID.';
            $nodeId   = '';
        }
        $fields['multiple_node_id'] = $nodeId !== '' ? strtolower($nodeId) : null;

        // Status the user is saving as — stored as fims.settings_status.id (status_for =
        // 'disbursement_voucher'), looked up by name; see getStatusIdByName()'s doc comment.
        $status = strtoupper(trim((string) ($body['status'] ?? 'DRAFT')));
        if (!in_array($status, self::SAVE_STATUSES, true)) {
            $errors[] = 'status must be DRAFT or FOR_FMD_REVIEW when saving; use the status action to move it through FMD/Accounting/Treasury, reject, or cancel.';
        }
        $fields['status']    = $status;
        $fields['status_id'] = $this->getStatusIdByName($status);
        if ($fields['status_id'] === null && !$errors) {
            $errors[] = "Status '{$status}' is not configured in fims.settings_status for disbursement vouchers.";
        }

        return [$errors, $fields];
    }

    /**
     * expense_class is NOT a field the preparer picks — it's derived from the account_code they
     * already chose, by Chart-of-Accounts prefix. Prefixes mirror this project's own COA seed
     * (_assets/sql/.../03 - fims_chart_of_accounts.sql):
     *   5-01-xx          Personnel Services               -> PS
     *   5-02-xx           Maintenance & Other Op. Expenses -> MOOE
     *   1-06-05 / -06 / -07 / -08 / -09   Machinery & Equipment, Furniture &
     *     Fixtures, Leased Assets, Leased Asset Improvements (movable PPE) -> EQUIPMENT_OUTLAY
     *   1-06-xx (anything else under PPE: Land, Land Improvements,
     *     Infrastructure Assets, Buildings, Heritage Assets, Service
     *     Concession, Construction in Progress — all immovable/civil-works) -> CAPITAL_OUTLAY
     *   anything else (Assets/Liabilities/Equity/Revenue accounts the form
     *     wouldn't normally post a DV to, or an account outside these trees) -> OTHER
     */
    private function deriveExpenseClass(string $accountCode): string
    {
        if (str_starts_with($accountCode, '5-01')) {
            return 'PS';
        }
        if (str_starts_with($accountCode, '5-02')) {
            return 'MOOE';
        }
        if (preg_match('/^1-06-0[5-9]/', $accountCode) === 1) {
            return 'EQUIPMENT_OUTLAY';
        }
        if (str_starts_with($accountCode, '1-06')) {
            return 'CAPITAL_OUTLAY';
        }

        return 'OTHER';
    }

    /**
     * Validates the `deductions` array from create()/update()'s body. Each row needs a known,
     * active deduction_type_id and a gross_amount; deduction_amount is either typed directly or
     * computed from rate (rate NULL means "typed directly", same optionality the paper form's
     * blank Rate column allows). When the chosen type has no default posting account
     * (deduction_types.account_code IS NULL — e.g. "OTHER", see
     * "08 - deduction_types_other_recoupment_penalty_vat.sql"), account_code is required here and
     * validated against the chart of accounts — the Accounting Entries grid can no longer silently
     * skip that line (see fetchDeductions()/the frontend's vf_/vje_ deduction-row rendering).
     * Returns [errors[], rows[]] — rows are ready to insert, in order.
     */
    private function prepareDeductions(mixed $rows, float $dvGrossAmount): array
    {
        $errors = [];
        $out    = [];
        if (!is_array($rows)) {
            return [$errors, $out];
        }
        $seenSingle = []; // codes of the one-per-voucher types already seen in this submission

        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $n = $i + 1;

            $typeId = $row['deduction_type_id'] ?? '';
            if (!ctype_digit((string) $typeId)) {
                $errors[] = "Deduction line {$n}: deduction_type_id is required.";
                continue;
            }
            $type = App\ExecuteRow(
                "SELECT deduction_type_id, code, name, account_code FROM {$this->tableDeductionTypes} WHERE deduction_type_id = " . (int) $typeId . ' AND is_active = TRUE',
                'DB'
            );
            if (!$type) {
                $errors[] = "Deduction line {$n}: deduction type #{$typeId} does not exist or is inactive.";
                continue;
            }
            // Retention, recoupment, penalty and VAT are a single figure per voucher; a second line of the same type is
            // almost always a double click that deducts the amount twice. "Other" (and the payroll types) can repeat.
            if (in_array($type['code'], self::SINGLE_DEDUCTION_TYPES, true)) {
                if (isset($seenSingle[$type['code']])) {
                    $errors[] = "Deduction line {$n}: '{$type['name']}' can only be added once per voucher — remove the duplicate line.";
                    continue;
                }
                $seenSingle[$type['code']] = true;
            }

            $gross = is_numeric($row['gross_amount'] ?? null) ? (float) $row['gross_amount'] : $dvGrossAmount;
            if ($gross < 0) {
                $errors[] = "Deduction line {$n}: gross_amount cannot be negative.";
            }

            $rateRaw = $row['rate'] ?? null;
            $rate    = is_numeric($rateRaw) ? (float) $rateRaw : null;
            if ($rate !== null) {
                $amount = round($gross * $rate / 100, 2);
            } else {
                $amount = is_numeric($row['deduction_amount'] ?? null) ? round((float) $row['deduction_amount'], 2) : null;
                if ($amount === null) {
                    $errors[] = "Deduction line {$n}: either rate or deduction_amount is required.";
                    continue;
                }
            }
            if ($amount < 0) {
                $errors[] = "Deduction line {$n}: deduction_amount cannot be negative.";
            }

            $accountCode = null;
            if (empty($type['account_code'])) {
                $accountCode = trim((string) ($row['account_code'] ?? ''));
                if ($accountCode === '') {
                    $errors[] = "Deduction line {$n}: '{$type['name']}' has no default posting account — pick one.";
                } else {
                    $account = App\ExecuteRow(
                        "SELECT posting_allowed FROM {$this->tableCoa} WHERE account_code = " . $this->str($accountCode),
                        'DB'
                    );
                    if (!$account) {
                        $errors[] = "Deduction line {$n}: account code '{$accountCode}' does not exist in the chart of accounts.";
                    } elseif (!$account['posting_allowed']) {
                        $errors[] = "Deduction line {$n}: account code '{$accountCode}' is not postable (it's a summary/header account).";
                    }
                }
            }

            $out[] = [
                'deduction_type_id' => (int) $typeId,
                'rate'              => $rate,
                'gross_amount'      => $gross,
                'deduction_amount'  => $amount,
                'account_code'      => $accountCode,
            ];
        }

        return [$errors, $out];
    }

    /** Full replace of a DV's deduction lines — same upsert shape as the JEV lines. */
    private function replaceDeductions(int $dvId, array $deductions): void
    {
        App\Execute("DELETE FROM {$this->tableDvDeductions} WHERE dv_id = {$dvId}", 'DB');
        foreach ($deductions as $idx => $d) {
            App\Execute(
                "INSERT INTO {$this->tableDvDeductions} (dv_id, line_number, deduction_type_id, rate, gross_amount, deduction_amount, account_code)
                 VALUES ({$dvId}, " . ($idx + 1) . ', ' . (int) $d['deduction_type_id'] . ', '
                . $this->numOrNull($d['rate']) . ', ' . (float) $d['gross_amount'] . ', ' . (float) $d['deduction_amount'] . ', '
                . $this->strOrNull($d['account_code'] ?? null) . ')',
                'DB'
            );
        }
    }

    // ── Deduction types (read-only lookup) ──

    public function fetchDeductionTypes(): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT deduction_type_id, code, name, description, account_code FROM {$this->tableDeductionTypes} WHERE is_active = TRUE ORDER BY name",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchDeductionTypes ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load deduction types.'];
        }
    }

    // ── Subsidiary Ledger lookup (now uses Chart of Accounts hierarchy) ──
    // SL accounts are Chart of Accounts entries with a parent_account_id (GL parent)

    public function fetchSlCodes(string $search = ''): array
    {
        try {
            // an SL is a BOTTOM-level account (a child with no children of its own) — group accounts are not SLs
            $where = ['c.status = ' . $this->str('Active'), 'c.parent_account_id IS NOT NULL',
                      "NOT EXISTS (SELECT 1 FROM {$this->tableCoa} k WHERE k.parent_account_id = c.account_id)"];
            if ($search !== '') {
                $kw      = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(c.account_code ILIKE {$kw} OR c.account_title ILIKE {$kw})";
            }
            $rows = App\ExecuteRows(
                "SELECT c.account_code AS sl_code, c.account_title AS sl_name, p.account_code
                 FROM {$this->tableCoa} c
                 LEFT JOIN {$this->tableCoa} p ON p.account_id = c.parent_account_id
                 WHERE " . implode(' AND ', $where) . '
                 ORDER BY c.account_code LIMIT 50',
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchSlCodes ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load SL codes.'];
        }
    }

    public function fetchSlDetails(string $slCode): array
    {
        // SL Details are no longer used — COA hierarchy is flat (GL parent -> SL child).
        // Kept for backward compatibility but returns empty array.
        return ['success' => true, 'data' => []];
    }

    // ── RFA lookup (read-only; fims.budget_allocation has no CRUD backend of its own) ──

    /**
     * net_amount of the OTHER voucher currently linked to this RFA (fims.budget_allocation.dv_id —
     * see linkRfas()), if any and still active (not CANCELLED/REJECTED) — "Payments" on the paper
     * form's Box B. Since one RFA only ever points at one DV at a time, this is at most a single
     * voucher's own net_amount, not a sum. $excludeDvId leaves the voucher being edited out of its
     * own total (it's always the one currently linked to its own selected RFAs). Null $rfaId (no
     * RFA linked) or no RFA at all means 0 — there's nothing to accumulate against. This table has
     * no RFA column of its own to query by — see "03 - alter_table/26 -
     * ...-drop_rfa_allocation_columns.sql".
     */
    private function priorPaymentsForRfa(?int $rfaId, ?int $excludeDvId): float
    {
        if (!$rfaId) {
            return 0.0;
        }
        $linkedDvId = (int) (App\ExecuteScalar("SELECT dv_id FROM {$this->tableRfa} WHERE allocation_id = {$rfaId}", 'DB') ?? 0);
        if (!$linkedDvId || $linkedDvId === $excludeDvId) {
            return 0.0;
        }
        // What the DV draws from the RFA is its GROSS amount (the deductions are part of the expense, paid out later
        // through the remittance DVs) — plus, for a payroll DV, the employer (government) share its month carries,
        // which the payroll's RFA also funds (the remittance DVs that pay it carry no RFA of their own).
        $row = App\ExecuteRow(
            "SELECT dv.gross_amount, st.name AS status,
                    (SELECT COALESCE(SUM(r.total_employer_share), 0) FROM fims.payroll_records r WHERE r.disbursement_voucher_id = dv.dv_id) AS employer_share
             FROM {$this->table} dv
             LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
             WHERE dv.dv_id = {$linkedDvId}",
            'DB'
        );
        if (!$row || in_array($row['status'], ['CANCELLED', 'REJECTED'], true)) {
            return 0.0;
        }

        return (float) $row['gross_amount'] + (float) $row['employer_share'];
    }

    /** Same as priorPaymentsForRfa(), summed across every RFA a DV cites. */
    private function priorPaymentsForRfas(array $rfaIds, ?int $excludeDvId): float
    {
        $total = 0.0;
        foreach ($rfaIds as $rfaId) {
            $total += $this->priorPaymentsForRfa((int) $rfaId, $excludeDvId);
        }

        return round($total, 2);
    }

    /**
     * Points every RFA in $rfaRows (prepare()'s `_rfaRows`) at $dvId — fims.budget_allocation.dv_id
     * — and clears dv_id back to NULL on any RFA that USED to point at $dvId but isn't part of this
     * selection anymore (an unchecked chip in the form). One RFA only ever points at one DV at a
     * time (see "03 - alter_table/23 - budget_allocation_add_dv_id.sql"), so this also refuses to
     * steal an RFA that's currently linked to a DIFFERENT, still-active (non-CANCELLED/REJECTED) DV
     * — returns that error message in that case, null otherwise. Called from inside create()/
     * update()'s own transaction, after this voucher's own row has been written (so $dvId is known) —
     * a non-null return should trigger a ROLLBACK, same as a persistJournalEntry() failure.
     */
    private function linkRfas(int $dvId, array $rfaRows): ?string
    {
        $newIds = array_map(fn ($r) => (int) $r['allocation_id'], $rfaRows);

        foreach ($rfaRows as $rfa) {
            $otherDvId = $rfa['dv_id'] !== null ? (int) $rfa['dv_id'] : null;
            if ($otherDvId === null || $otherDvId === $dvId) {
                continue;
            }
            $other = App\ExecuteRow(
                "SELECT dv.dv_no, st.name AS status FROM {$this->table} dv
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_id = {$otherDvId}",
                'DB'
            );
            if ($other && !in_array($other['status'], ['CANCELLED', 'REJECTED'], true)) {
                return "RFA '{$rfa['allocation_number']}' is already linked to Disbursement Voucher {$other['dv_no']} and cannot be selected here.";
            }
        }

        $keep = $newIds ? implode(',', $newIds) : '0';
        App\Execute("UPDATE {$this->tableRfa} SET dv_id = NULL WHERE dv_id = {$dvId} AND allocation_id NOT IN ({$keep})", 'DB');
        if ($newIds) {
            App\Execute("UPDATE {$this->tableRfa} SET dv_id = {$dvId} WHERE allocation_id IN ({$keep})", 'DB');
        }

        return null;
    }

    /**
     * Points $saroRow (prepare()'s `_saroRow`, or null — "no SARO selected") at $dvId —
     * fims.fund_tracking.dv_id — and clears dv_id back to NULL on whatever OTHER fund_tracking row
     * used to point at $dvId (a SARO that was unlinked/swapped out in this save). One DV cites at
     * most one SARO at a time (unlike RFAs, which a DV can cite several of — see linkRfas()), so
     * this is the single-value equivalent. Refuses (returns the error message) to steal a SARO
     * that's currently linked to a DIFFERENT, still-active (non-CANCELLED/REJECTED) DV — same rule
     * linkRfas() enforces for RFAs. Called from inside create()/update()'s own transaction, after
     * this voucher's own row has been written (so $dvId is known) — a non-null return should
     * trigger a ROLLBACK, same as a linkRfas() failure.
     */
    private function linkSaro(int $dvId, ?array $saroRow): ?string
    {
        $newId = $saroRow ? (int) $saroRow['id'] : null;

        if ($saroRow) {
            $otherDvId = isset($saroRow['dv_id']) ? (int) $saroRow['dv_id'] : null;
            if ($otherDvId !== null && $otherDvId !== $dvId) {
                $other = App\ExecuteRow(
                    "SELECT dv.dv_no, st.name AS status FROM {$this->table} dv
                     LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                     WHERE dv.dv_id = {$otherDvId}",
                    'DB'
                );
                if ($other && !in_array($other['status'], ['CANCELLED', 'REJECTED'], true)) {
                    return "SARO '{$saroRow['saro_id']}' is already linked to Disbursement Voucher {$other['dv_no']} and cannot be selected here.";
                }
            }
        }

        $keep = $newId ?: 0;
        App\Execute("UPDATE {$this->tableFundTracking} SET dv_id = NULL WHERE dv_id = {$dvId} AND id <> {$keep}", 'DB');
        if ($newId) {
            App\Execute("UPDATE {$this->tableFundTracking} SET dv_id = {$dvId} WHERE id = {$newId}", 'DB');
        }

        return null;
    }

    /**
     * Stamps budget_amount (ceiling, from po_amount or summed line_transaction) plus paid_amount /
     * remaining_balance onto an RFA row. paid_amount is usually 0 now that one RFA only ever points
     * at one DV at a time (budget_allocation.dv_id — see linkRfas()); it stays non-zero only for a
     * historical RFA whose prior DV was CANCELLED/REJECTED (priorPaymentsForRfa() excludes those
     * from "active", so they don't block re-linking, but their own net_amount still counted here
     * while they held it).
     */
    private function withRfaBudget(array $rfa, ?int $excludeDvId = null): array
    {
        $budgetAmount = is_numeric($rfa['po_amount']) && (float) $rfa['po_amount'] > 0 ? (float) $rfa['po_amount'] : 0.0;
        if ($budgetAmount <= 0) {
            $lines = json_decode((string) ($rfa['line_transaction'] ?? '[]'), true) ?: [];
            foreach ($lines as $line) {
                // RFA allocations carry their amount in requested_amount — allocated_amount
                // ("Cash Requirements") is hidden/unused for this allocation_type (see
                // budget-allocation-form.php's baaApplyCashRequirementsVisibility()), so summing
                // allocated_amount here always landed on 0.
                $budgetAmount += (float) ($line['requested_amount'] ?? $line['allocated_amount'] ?? 0);
            }
        }
        $rfa['budget_amount']      = round($budgetAmount, 2);
        $rfa['paid_amount']        = round($this->priorPaymentsForRfa((int) $rfa['allocation_id'], $excludeDvId), 2);
        $rfa['remaining_balance']  = round($rfa['budget_amount'] - $rfa['paid_amount'], 2);

        $account = $this->rfaFirstLineAccount($rfa);
        $rfa['account_code']  = $account['account_code']  ?? null;
        $rfa['account_title'] = $account['account_title'] ?? null;
        $rfa['expense_class'] = $account ? $this->deriveExpenseClass($account['account_code']) : null;
        $rfa['lines']         = $this->rfaLineItems($rfa);

        return $rfa;
    }

    /**
     * Every itemized line of the RFA (its flattened line_transaction) with the Chart-of-Accounts account
     * behind its budget_line_item_id and its requested amount — what the voucher form's RFA card lists
     * so the preparer sees the account codes and amounts the RFA was approved for, not just the first one.
     * Lines whose budget line item no longer exists keep their amount with a null account.
     *
     * @return array<int, array{account_code: ?string, account_title: ?string, amount: float, description: ?string}>
     */
    private function rfaLineItems(array $rfa): array
    {
        $lines = json_decode((string) ($rfa['line_transaction'] ?? '[]'), true) ?: [];
        $ids   = [];
        foreach ($lines as $line) {
            if (isset($line['budget_line_item_id']) && ctype_digit((string) $line['budget_line_item_id'])) {
                $ids[] = (int) $line['budget_line_item_id'];
            }
        }
        $accounts = [];
        if ($ids) {
            $rows = App\ExecuteRows(
                "SELECT bli.budget_line_item_id, coa.account_code, coa.account_title
                 FROM fims.budget_line_items bli
                 JOIN {$this->tableCoa} coa ON coa.account_id = bli.account_id
                 WHERE bli.budget_line_item_id IN (" . implode(',', array_unique($ids)) . ')',
                'DB'
            ) ?: [];
            foreach ($rows as $r) {
                $accounts[(int) $r['budget_line_item_id']] = $r;
            }
        }

        $out = [];
        foreach ($lines as $line) {
            $id    = isset($line['budget_line_item_id']) && ctype_digit((string) $line['budget_line_item_id']) ? (int) $line['budget_line_item_id'] : null;
            $acct  = $id !== null ? ($accounts[$id] ?? null) : null;
            $desc  = trim((string) ($line['description'] ?? $line['particulars'] ?? ''));
            $out[] = [
                'account_code'  => $acct['account_code']  ?? null,
                'account_title' => $acct['account_title'] ?? null,
                'amount'        => round((float) ($line['requested_amount'] ?? $line['allocated_amount'] ?? 0), 2),
                'description'   => $desc !== '' ? $desc : null,
            ];
        }

        return $out;
    }

    /**
     * The Chart-of-Accounts row behind the FIRST budget_line_item_id named in this RFA's own
     * line_transaction — null if it's empty/unreadable or that line item no longer exists.
     *
     * Backs two voucher-form autofills that need to know this BEFORE a voucher (and its own
     * account_code) exists yet:
     *   - account_code/account_title: the form's Account Code field autofills from it (see
     *     vf_onRfaSelectionChange()) the same way Dept/Project, PO Number and Vendor already do —
     *     the RFA was approved against a specific COB (chart-of-accounts budget line), so the
     *     voucher drawing on it should default to posting to that same account, not a blank field
     *     the preparer has to search the Chart of Accounts for by hand.
     *   - expense_class: the "lock the RFA picker to one class" behavior (vf_renderRfaDropdown()) —
     *     derived the same way deriveExpenseClass() derives a voucher's, by Chart-of-Accounts prefix.
     * Every line an RFA this project's demo seed creates carries is from the same class's accounts
     * (see "07 - budget_request_and_voucher_full_cycle_demo.sql"), so the first line is
     * representative of the whole RFA for both purposes, not just a guess — but if an RFA's lines
     * ever do span more than one account, only the first is reflected; Accounting still reconciles
     * the rest via the Journal Entry Voucher's own per-line account codes regardless.
     */
    private function rfaFirstLineAccount(array $rfa): ?array
    {
        $lines = json_decode((string) ($rfa['line_transaction'] ?? '[]'), true) ?: [];
        $firstLineItemId = null;
        foreach ($lines as $line) {
            if (isset($line['budget_line_item_id']) && ctype_digit((string) $line['budget_line_item_id'])) {
                $firstLineItemId = (int) $line['budget_line_item_id'];
                break;
            }
        }
        if ($firstLineItemId === null) {
            return null;
        }

        $row = App\ExecuteRow(
            "SELECT coa.account_code, coa.account_title FROM fims.budget_line_items bli
             JOIN {$this->tableCoa} coa ON coa.account_id = bli.account_id
             WHERE bli.budget_line_item_id = {$firstLineItemId}",
            'DB'
        );

        return $row ?: null;
    }

    /**
     * WHERE fragment: this RFA is free to pick — unlinked, already linked to the DV currently being
     * edited, or linked to a DV that's since been CANCELLED/REJECTED (same "not blocking" rule
     * linkRfas()'s own exclusivity check uses, so an RFA doesn't get stuck unavailable forever just
     * because nothing else explicitly clears budget_allocation.dv_id when a DV is cancelled).
     */
    /**
     * line_transaction moved off fims.budget_allocation onto its child fims.budget_allocation_project
     * ("03 - alter_table/48 - budget_allocation_drop_project_columns.sql") — a memo request can now
     * cover several Project blocks, each with its own itemized COB draws. This flattens every block's
     * line_transaction array into one combined array per RFA, matching the single array withRfaBudget()
     * and rfaFirstLineAccount() used to read directly off a.line_transaction.
     */
    private function rfaLineTransactionSql(): string
    {
        return "(SELECT COALESCE(jsonb_agg(elem), '[]'::jsonb)
                  FROM {$this->tableRfaProjects} bap
                  CROSS JOIN LATERAL jsonb_array_elements(COALESCE(bap.line_transaction, '[]'::jsonb)) elem
                  WHERE bap.allocation_id = a.allocation_id AND bap.deleted_at IS NULL) AS line_transaction";
    }

    private function rfaAvailableSql(?int $excludeDvId): string
    {
        $exclude = $excludeDvId !== null ? " OR a.dv_id = {$excludeDvId}" : '';

        return "(a.dv_id IS NULL{$exclude} OR a.dv_id IN (
            SELECT dv.dv_id FROM {$this->table} dv
            LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
            WHERE st.name IN ('CANCELLED', 'REJECTED')
        ))";
    }

    /**
     * RFA picker options. One RFA backs only one DV at a time (budget_allocation.dv_id — see
     * linkRfas()), so an RFA already linked to a DIFFERENT, still-active DV is left out entirely;
     * $excludeDvId is the DV currently being edited, so its own already-linked RFAs keep showing
     * (as "already yours", not "taken by someone else").
     */
    public function fetchRfaOptions(array $filters = [], ?int $excludeDvId = null): array
    {
        try {
            // Only Approved RFAs can fund a DV (a Draft / For Review / Returned one has no allotment yet) — except an
            // RFA the DV being edited already cites, so its chip still shows.
            $approved = "a.status_id = (SELECT id FROM {$this->tableSettingsStatus} WHERE status_for = 'budget_allocation' AND name = 'Approved' LIMIT 1)";
            $where = ["a.allocation_type = 'RFA'", $this->rfaAvailableSql($excludeDvId),
                $excludeDvId !== null ? "({$approved} OR a.dv_id = {$excludeDvId})" : $approved];
            if (!empty($filters['fiscal_yr'])) {
                $where[] = 'a.fiscal_year = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['search'])) {
                $kw      = App\QuotedValue('%' . $filters['search'] . '%', App\DataType::STRING, 'DB');
                $where[] = "(a.allocation_number ILIKE {$kw} OR a.request_referrence_no ILIKE {$kw})";
            }
            $whereSql = implode(' AND ', $where);

            $rows = App\ExecuteRows(
                "SELECT a.allocation_id, a.allocation_number, a.request_referrence_no, a.fiscal_year,
                        a.requesting_rc_id, rc.rc_name AS department_name,
                        a.vendor_id, v.vendor_name, v.address AS vendor_address, v.tin AS vendor_tin,
                        a.po_number, a.po_amount, {$this->rfaLineTransactionSql()}, a.dv_id,
                        a.category_id, bc.name AS category_name, a.description
                 FROM {$this->tableRfa} a
                 LEFT JOIN {$this->tableRc}             rc ON rc.rc_id = a.requesting_rc_id
                 LEFT JOIN {$this->tableVendor}         v  ON v.vendor_id = a.vendor_id
                 LEFT JOIN {$this->tableBudgetCategory} bc ON bc.budget_category_id = a.category_id
                 WHERE {$whereSql}
                 ORDER BY a.allocation_id DESC",
                'DB'
            ) ?: [];

            $rows = array_map(fn (array $r) => $this->withRfaBudget($r, $excludeDvId), $rows);

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchRfaOptions ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load RFAs.'];
        }
    }

    /**
     * SARO picker options — fims.fund_tracking WHERE tracking_type_id resolves to 'SARO' (fims.
     * fund_tracking_type.fund_tracking_code). One SARO backs only one DV at a time (fund_tracking.
     * dv_id — see linkSaro()), so a SARO already linked to a DIFFERENT, still-active DV is left out
     * entirely; $excludeDvId is the DV currently being edited, so its own already-linked SARO keeps
     * showing (as "already yours", not "taken by someone else"). Also resolves the SARO's own
     * bank account (bank_account_id -> fims.bank_accounts), so the picker can show it alongside the
     * SARO reference number without a second round trip.
     */
    public function fetchSaroOptions(array $filters = [], ?int $excludeDvId = null): array
    {
        try {
            $saroTypeId = (int) (App\ExecuteScalar(
                "SELECT fund_tracking_type_id FROM fims.fund_tracking_type WHERE UPPER(fund_tracking_code) = 'SARO' AND deleted_at IS NULL LIMIT 1",
                'DB'
            ) ?: 0);
            if (!$saroTypeId) {
                return ['success' => true, 'data' => []]; // 'SARO' isn't configured as a tracking type — nothing to offer
            }

            $available = "(ft.dv_id IS NULL" . ($excludeDvId !== null ? " OR ft.dv_id = {$excludeDvId}" : '') . ' OR ft.dv_id IN ('
                . "SELECT dv.dv_id FROM {$this->table} dv LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id WHERE st.name IN ('CANCELLED', 'REJECTED')))";

            $where = ['ft.deleted_at IS NULL', "ft.tracking_type_id = {$saroTypeId}", $available];
            if (!empty($filters['fiscal_yr'])) {
                $where[] = 'ft.fiscal_year = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['search'])) {
                $kw = App\QuotedValue('%' . $filters['search'] . '%', App\DataType::STRING, 'DB');
                $where[] = "ft.saro_id ILIKE {$kw}";
            }
            $whereSql = implode(' AND ', $where);

            $rows = App\ExecuteRows(
                "SELECT ft.id, ft.saro_id, ft.fiscal_year, ft.description, ft.amount, ft.unreleased, ft.dv_id,
                        ft.bank_account_id, ba.account_name, ba.account_number, ba.bank_name
                 FROM {$this->tableFundTracking} ft
                 LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = ft.bank_account_id
                 WHERE {$whereSql}
                 ORDER BY ft.id DESC",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchSaroOptions ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load SAROs.'];
        }
    }

    /** One RFA plus its budget ceiling and paid_amount/remaining_balance so far (progress payments). */
    public function fetchRfaDetail(int|string $allocationId, ?int $excludeDvId = null): array
    {
        try {
            $id  = (int) $allocationId;
            $rfa = App\ExecuteRow(
                "SELECT a.allocation_id, a.allocation_number, a.request_referrence_no, a.fiscal_year,
                        a.requesting_rc_id, rc.rc_name AS department_name,
                        a.vendor_id, v.vendor_name, v.address AS vendor_address, v.tin AS vendor_tin,
                        a.po_number, a.po_amount, {$this->rfaLineTransactionSql()},
                        a.category_id, bc.name AS category_name, a.description
                 FROM {$this->tableRfa} a
                 LEFT JOIN {$this->tableRc}             rc ON rc.rc_id = a.requesting_rc_id
                 LEFT JOIN {$this->tableVendor}         v  ON v.vendor_id = a.vendor_id
                 LEFT JOIN {$this->tableBudgetCategory} bc ON bc.budget_category_id = a.category_id
                 WHERE a.allocation_id = {$id} AND a.allocation_type = 'RFA'",
                'DB'
            );
            if (!$rfa) {
                return ['success' => false, 'message' => "RFA #{$id} not found.", 'httpCode' => 404];
            }

            return ['success' => true, 'data' => $this->withRfaBudget($rfa, $excludeDvId)];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchRfaDetail ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load RFA.'];
        }
    }

    /**
     * Server-side RFA ceiling for a manual DV (not payroll/remittance — those take their amount from their own
     * import). Sums the selected RFAs' budget and remaining balance (withRfaBudget(); $excludeDvId keeps the DV
     * being edited out of its own prior payments) and:
     *   - errors if the DV's Amount is more than the RFAs' remaining balance, and
     *   - returns the budget so budget_amount/obligation_amount come from the RFAs, not from whatever the
     *     browser sent (the form no longer shows those fields).
     * An RFA with no known ceiling (no PO amount and no itemized lines) is left out of the sum; if none of the
     * selected RFAs has one, nothing is enforced and the client's figures are kept.
     *
     * @return array{0: array<int, string>, 1: ?float} [errors, budgetFromRfas]
     */
    private function checkAmountAgainstRfas(array $f, ?int $excludeDvId): array
    {
        if ($f['_payrollPeriodId'] && $f['_rfaRows']) {
            return $this->checkPayrollAgainstRfas($f, $excludeDvId);
        }
        if ($f['_payrollPeriodId'] || $f['_remittanceId'] || $f['_dvRemit'] || !$f['_rfaRows']) {
            return [[], null];
        }
        $budget = 0.0;
        $remaining = 0.0;
        foreach ($f['_rfaRows'] as $row) {
            $detail = $this->fetchRfaDetail((int) $row['allocation_id'], $excludeDvId);
            if (!$detail['success'] || (float) $detail['data']['budget_amount'] <= 0) {
                continue;
            }
            $budget    += (float) $detail['data']['budget_amount'];
            $remaining += (float) $detail['data']['remaining_balance'];
        }
        if ($budget <= 0) {
            return [[], null];
        }
        $errors = [];
        if ((float) $f['gross_amount'] - $remaining > 0.005) {
            $errors[] = 'Amount (' . number_format((float) $f['gross_amount'], 2) . ') cannot exceed the selected RFA(s)\' remaining balance (' . number_format($remaining, 2) . ').';
        }

        return [$errors, round($budget, 2)];
    }

    /**
     * The RFA rules for a PAYROLL DV (Payroll Management → Create DV), on top of "the RFA is Approved" (prepare()):
     *   - the payroll month's fiscal year must be the RFA's fiscal year;
     *   - every RFA must have a Personnel Services line (account 5-01-…) — a payroll is never MOOE / Capital Outlay;
     *   - the RFAs' remaining balance must cover the payroll's GROSS plus its EMPLOYER (government) share: the
     *     employer share is paid later by the remittance DVs, which carry no RFA of their own, so the payroll's
     *     RFA is what funds it.
     * Returns the RFAs' budget like checkAmountAgainstRfas() does, so budget/obligation come from the RFAs.
     *
     * @return array{0: array<int, string>, 1: ?float} [errors, budgetFromRfas]
     */
    private function checkPayrollAgainstRfas(array $f, ?int $excludeDvId): array
    {
        $periodId = (int) $f['_payrollPeriodId'];
        $payroll  = App\ExecuteRow(
            "SELECT p.fiscal_yr, p.payroll_month,
                    COALESCE(SUM(r.gross_amount), 0) AS gross, COALESCE(SUM(r.total_employer_share), 0) AS employer_share
             FROM fims.payroll_periods p
             LEFT JOIN fims.payroll_records r ON r.payroll_period_id = p.payroll_period_id
             WHERE p.payroll_period_id = {$periodId}
             GROUP BY p.fiscal_yr, p.payroll_month",
            'DB'
        );
        if (!$payroll) {
            return [[], null];   // validateDvLink() reports a missing month
        }
        $months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        $label  = ($months[(int) $payroll['payroll_month']] ?? 'Month ' . $payroll['payroll_month']) . ' ' . $payroll['fiscal_yr'];

        $errors    = [];
        $budget    = 0.0;
        $remaining = 0.0;
        foreach ($f['_rfaRows'] as $row) {
            $rfaLabel = $row['allocation_number'] ?: ('#' . $row['allocation_id']);
            if ((int) ($row['fiscal_year'] ?? 0) !== (int) $payroll['fiscal_yr']) {
                $errors[] = "RFA {$rfaLabel} is for FY {$row['fiscal_year']} — the {$label} payroll needs an FY {$payroll['fiscal_yr']} RFA.";
            }
            $detail = $this->fetchRfaDetail((int) $row['allocation_id'], $excludeDvId);
            if (!$detail['success']) {
                continue;
            }
            $hasPs = false;
            foreach ($detail['data']['lines'] ?? [] as $line) {
                if (str_starts_with((string) ($line['account_code'] ?? ''), '5-01-')) {
                    $hasPs = true;
                    break;
                }
            }
            if (!$hasPs) {
                $errors[] = "RFA {$rfaLabel} has no Personnel Services line (account 5-01-…) — a payroll can't be charged to it.";
            }
            $budget    += (float) $detail['data']['budget_amount'];
            $remaining += (float) $detail['data']['remaining_balance'];
        }

        $gross    = round((float) $payroll['gross'], 2);
        $employer = round((float) $payroll['employer_share'], 2);
        $required = round($gross + $employer, 2);
        if ($required - $remaining > 0.005) {
            $errors[] = 'The ' . $label . ' payroll needs ' . number_format($required, 2) . ' (gross ' . number_format($gross, 2)
                . ' + employer share ' . number_format($employer, 2) . ') but the selected RFA(s) have only '
                . number_format($remaining, 2) . ' remaining.';
        }

        return [$errors, $budget > 0 ? round($budget, 2) : null];
    }

    /**
     * A payroll DV's Amount and deductions are the imported month's, never what the form sent: they are replaced
     * here from Payroll Management (dvData()) before the DV is validated and saved. Any other body is returned as is;
     * so is a payroll body whose month can't be read (validateDvLink() then reports why).
     */
    private function withPayrollAmounts(array $body): array
    {
        $periodId = $body['payroll_period_id'] ?? null;
        if ($periodId === null || $periodId === '' || !ctype_digit((string) $periodId)) {
            return $body;
        }
        $data = (new PayrollManagementService())->dvData((int) $periodId);
        if (!($data['success'] ?? false)) {
            return $body;
        }
        $d = $data['data'];
        $body['gross_amount'] = $d['gross_amount'];
        $body['deductions']   = array_map(static fn (array $x) => [
            'deduction_type_id' => $x['deduction_type_id'] ?? null,
            'deduction_amount'  => $x['deduction_amount'] ?? 0,
        ], is_array($d['deductions'] ?? null) ? $d['deductions'] : []);

        return $body;
    }

    // ── queries ──

    public function fetchAlls(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $from = "{$this->table} dv LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id";

            $where = ['1=1'];
            if (($visible = $this->visibilitySql()) !== null) {
                $where[] = $visible; // Originating sees only its own office's vouchers — see visibilitySql()
            }
            if (!empty($filters['status'])) {
                $where[] = 'st.name = ' . $this->str(strtoupper($filters['status']));
            }
            if (!empty($filters['fiscal_yr'])) {
                $where[] = 'dv.fiscal_yr = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['expense_class'])) {
                $where[] = 'dv.expense_class = ' . $this->str(strtoupper($filters['expense_class']));
            }
            if (!empty($filters['search'])) {
                $kw      = App\QuotedValue('%' . $filters['search'] . '%', App\DataType::STRING, 'DB');
                // RFA no longer has a column on this table (see linkRfas()) — matched via the
                // reverse link (budget_allocation.dv_id) instead.
                $where[] = "(dv.dv_no ILIKE {$kw} OR dv.claimant_name ILIKE {$kw}
                             OR EXISTS (SELECT 1 FROM {$this->tableRfa} a WHERE a.dv_id = dv.dv_id AND a.allocation_number ILIKE {$kw}))";
            }
            $whereSql = implode(' AND ', $where);

            $select = "SELECT dv.*, st.name AS status, st.color AS status_color FROM {$from} WHERE {$whereSql} ORDER BY dv.created_at DESC, dv.dv_id DESC";

            if ($page === null) {
                return ['success' => true, 'data' => App\ExecuteRows($select, 'DB') ?: []];
            }

            $limit  = max(1, $limit);
            $offset = (max(1, $page) - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$from} WHERE {$whereSql}", 'DB');

            $summary = App\ExecuteRow(
                "SELECT COALESCE(SUM(dv.net_amount), 0) AS total_amount,
                        COUNT(*) FILTER (WHERE st.name = 'RELEASED')                              AS approved,
                        COUNT(*) FILTER (WHERE st.name IN ('FOR_FMD_REVIEW','FOR_ACCOUNTING','FOR_DIVISION_MANAGER','FOR_DEPARTMENT_MANAGER','FOR_TREASURY')) AS pending,
                        COUNT(*) FILTER (WHERE st.name IN ('REJECTED','CANCELLED'))                AS rejected
                 FROM {$from} WHERE {$whereSql}",
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

    /**
     * The IAR (Inspection and Acceptance Report, fims.inspection_acceptance_report — one row per PO) recorded for a PO number,
     * or null when the PO has none yet. A DV may only be prepared against a PO that has been inspected and accepted.
     */
    private function iarOfPo(string $poNumber): ?array
    {
        $row = App\ExecuteRow(
            'SELECT iar_number, si_number FROM fims.inspection_acceptance_report WHERE po_number = ' . $this->str($poNumber)
            . " AND COALESCE(TRIM(iar_number), '') <> '' LIMIT 1",
            'DB'
        );

        return $row ?: null;
    }

    /** GET /voucher/po-iar?po_number= — does this PO have an IAR? Drives the DV form's warning. */
    public function checkPoIar(string $poNumber): array
    {
        try {
            $poNumber = trim($poNumber);
            if ($poNumber === '') {
                return ['success' => true, 'data' => ['po_number' => '', 'has_iar' => true, 'iar_number' => null]];
            }
            $iar = $this->iarOfPo($poNumber);

            return ['success' => true, 'data' => [
                'po_number'  => $poNumber,
                'has_iar'    => $iar !== null,
                'iar_number' => $iar['iar_number'] ?? null,
            ]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::checkPoIar ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to check the PO\'s IAR.'];
        }
    }

    public function fetchById(int|string $id): array
    {
        try {
            $dvId = (int) $id;
            // vendor_ap_account_code: the vendor's own Accounts Payable sub-ledger account
            // (fims.vendors.sl_code -> a chart_of_accounts child row, e.g. "2-01-01-010-01")
            // when the claimant is a registered vendor with one set up — what the Accounting
            // Entries grid's auto-generated "Net Amount Payable" line should credit instead of the
            // bare AP control account, so the payable actually lands in that vendor's subsidiary
            // ledger (see readone.php's vje_syncAutoLines()).
            $row  = App\ExecuteRow(
                "SELECT dv.*, st.name AS status, st.color AS status_color,
                        vcoa.account_code AS vendor_ap_account_code
                 FROM {$this->table} dv
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 LEFT JOIN {$this->tableVendor} v ON v.vendor_id = dv.vendor_id
                 LEFT JOIN {$this->tableCoa} vcoa ON vcoa.account_id = v.sl_code
                 WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if (!$row) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            // Same rule as the list: an Originating user can't open another office's voucher by id (reported as "not found", not "forbidden").
            if (($visible = $this->visibilitySql()) !== null
                && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} dv WHERE dv.dv_id = {$dvId} AND {$visible}", 'DB') === 0) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }

            $row['allowed_transitions'] = self::TRANSITIONS[$row['status']] ?? [];
            $row['is_editable']        = in_array($row['status'], self::EDITABLE, true);
            $row['jev']                = $this->fetchJev($dvId);
            $row['payment_jevs']       = $this->fetchPaymentJevs($dvId);
            $row['deductions']         = $this->fetchDeductions($dvId);
            $row['atc_lines']          = $this->fetchAtcLines($dvId);
            $row['payroll']            = (new PayrollManagementService())->forDv($dvId); // imported payroll month this DV pays, or null
            $row['remittance']         = (new DeductionRemittanceService())->forDv($dvId); // payroll remittance this DV pays (GSIS/BIR/…), or null
            $row['dv_remit']           = (new RemittanceListService())->forDv($dvId); // what this Remittance DV pays to BIR (a voucher's EWT / VAT / Penalty Tax), or null

            // Every RFA currently linked to this DV — the link lives on the RFA side
            // (budget_allocation.dv_id, see linkRfas()), so this is a plain reverse lookup, not a
            // column to parse. What the form's multi-select repopulates its chips from, and what the
            // view shows.
            $row['rfas'] = array_values(array_filter(array_map(
                fn (array $r) => ($this->withRfaBudget($r, $dvId)),
                App\ExecuteRows(
                    "SELECT a.allocation_id, a.allocation_number, a.request_referrence_no, a.fiscal_year,
                            a.requesting_rc_id, rc.rc_name AS department_name,
                            a.vendor_id, v.vendor_name, v.address AS vendor_address, v.tin AS vendor_tin,
                            a.po_number, a.po_amount, {$this->rfaLineTransactionSql()}, a.dv_id,
                            a.category_id, bc.name AS category_name, a.description
                     FROM {$this->tableRfa} a
                     LEFT JOIN {$this->tableRc}             rc ON rc.rc_id = a.requesting_rc_id
                     LEFT JOIN {$this->tableVendor}         v  ON v.vendor_id = a.vendor_id
                     LEFT JOIN {$this->tableBudgetCategory} bc ON bc.budget_category_id = a.category_id
                     WHERE a.allocation_type = 'RFA' AND a.dv_id = {$dvId}
                     ORDER BY a.allocation_id",
                    'DB'
                ) ?: []
            )));

            // The SARO currently linked to this DV, if any — the link lives on the SARO side
            // (fund_tracking.dv_id, see linkSaro()), same reverse-lookup idea as `rfas` above. What
            // the form's single-select repopulates from, and what Check Preparation's auto-fill
            // (CheckProcessingService::getSaroForDv()) reads too.
            $row['saro'] = App\ExecuteRow(
                "SELECT ft.id, ft.saro_id, ft.fiscal_year, ft.description, ft.amount, ft.unreleased,
                        ft.bank_account_id, ba.account_name, ba.account_number, ba.bank_name
                 FROM {$this->tableFundTracking} ft
                 LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = ft.bank_account_id
                 WHERE ft.dv_id = {$dvId} AND ft.deleted_at IS NULL
                 LIMIT 1",
                'DB'
            ) ?: null;

            // Supporting Documents attached to this voucher, if any (same attachment convention as
            // fims.budget_allocation — see FileUploadServices::attachmentsFor()).
            $row['file_attachments'] = (new FileUploadServices())->attachmentsFor($row['multiple_node_id'] ?? null);

            // account_title/account_posting_allowed let the view show the account's name (same
            // Chart of Accounts lookup the searchable-select pickers use elsewhere) and flag it if
            // it's somehow not posting_allowed (e.g. a legacy voucher saved before that check
            // existed, or the account was reclassified after the fact) — null/false if account_code
            // is blank or no longer exists in the chart of accounts.
            if ($row['account_code']) {
                $account = App\ExecuteRow(
                    'SELECT account_title, posting_allowed FROM ' . $this->tableCoa . ' WHERE account_code = ' . $this->str($row['account_code']),
                    'DB'
                );
                $row['account_title']           = $account['account_title'] ?? null;
                $row['account_posting_allowed'] = $account ? (bool) $account['posting_allowed'] : false;
            } else {
                $row['account_title']           = null;
                $row['account_posting_allowed'] = null;
            }

            return ['success' => true, 'data' => $row];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchById ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load disbursement voucher.'];
        }
    }

    /**
     * This DV's deduction lines (e.g. retention), set by Originating at creation. account_code is
     * the EFFECTIVE posting account the Accounting Entries grid auto-fills a credit line from:
     * the deduction type's own default (fims.deduction_types.account_code) when it has one, else
     * the per-line override the preparer picked (fims.disbursement_voucher_deductions.account_code
     * — required by prepareDeductions() whenever the type has no default, e.g. "OTHER").
     * type_account_code is the type's own default alone (null for "OTHER") — the frontend uses it
     * to decide whether to show the account-code picker at all.
     */
    private function fetchDeductions(int $dvId): array
    {
        return App\ExecuteRows(
            "SELECT dvd.dv_deduction_id, dvd.line_number, dvd.deduction_type_id, dt.code, dt.name,
                    dt.account_code AS type_account_code,
                    COALESCE(dt.account_code, dvd.account_code) AS account_code,
                    dvd.rate, dvd.gross_amount, dvd.deduction_amount
             FROM {$this->tableDvDeductions} dvd
             JOIN {$this->tableDeductionTypes} dt ON dt.deduction_type_id = dvd.deduction_type_id
             WHERE dvd.dv_id = {$dvId} ORDER BY dvd.line_number",
            'DB'
        ) ?: [];
    }

    /**
     * This DV's BIR withholding tax classification lines, set by Accounting with the JEV.
     * account_code is the ATC's default posting account (fims.atc_codes.account_code), if any —
     * what the Accounting Entries grid auto-fills a credit line from.
     */
    private function fetchAtcLines(int $dvId): array
    {
        return App\ExecuteRows(
            "SELECT dval.dv_atc_line_id, dval.line_number, dval.atc_code, ac.account_code,
                    ac.description AS atc_description,
                    dval.rate, dval.tax_base, dval.withholding_tax, dval.chargeability
             FROM {$this->tableDvAtcLines} dval
             JOIN {$this->tableAtc} ac ON ac.atc_code = dval.atc_code
             WHERE dval.dv_id = {$dvId} ORDER BY dval.line_number",
            'DB'
        ) ?: [];
    }

    // ── Journal Entry Voucher (Accounting stage) ──

    /**
     * The editable SETUP JEV for a DV (header + lines, with any SL breakdown), or null if not
     * prepared yet. dv_id is no longer unique on fims.journal_entry_vouchers — a DV can also have
     * one or more PAYMENT JEVs (see fetchPaymentJevs(), GeneralLedgerService::
     * createPaymentJev()) — so this filters to jev_type = 'SETUP' explicitly, the only one this
     * class's own create/update/saveJournalEntry() ever read or write.
     */
    private function fetchJev(int $dvId): ?array
    {
        $jev = App\ExecuteRow(
            "SELECT jev.*, b.name AS book_name
             FROM {$this->tableJev} jev
             LEFT JOIN {$this->tableBooks} b ON b.book_id = jev.book_id
             WHERE jev.dv_id = {$dvId} AND jev.jev_type = 'SETUP'",
            'DB'
        );
        if (!$jev) {
            return null;
        }

        $jev['lines'] = App\ExecuteRows(
            "SELECT jevl.jevl_id, jevl.line_number, jevl.account_code, ac.account_title, jevl.debit, jevl.credit, jevl.description,
                    jevl.sl_code, jevl.sl_detail_id,
                    jevl.rc_id, rc.rc_name
             FROM {$this->tableJevLines} jevl
             LEFT JOIN {$this->tableCoa}       ac ON ac.account_code = jevl.account_code
             LEFT JOIN {$this->tableRc}        rc ON rc.rc_id = jevl.rc_id
             WHERE jevl.jev_id = " . (int) $jev['jev_id'] . ' ORDER BY jevl.line_number',
            'DB'
        ) ?: [];

        return $jev;
    }

    /**
     * Every PAYMENT JEV against a DV (GeneralLedgerService::createPaymentJev(), e.g. one per
     * check/ADA release) — oldest first; each carries its status (DRAFT until Accounting posts it via
     * postPaymentJev(), then POSTED). A DV's whole JEV picture is its one SETUP JEV (fetchJev(),
     * editable) plus zero or more of these.
     */
    private function fetchPaymentJevs(int $dvId): array
    {
        $jevs = App\ExecuteRows(
            "SELECT jev.*, b.name AS book_name, st.name AS status
             FROM {$this->tableJev} jev
             LEFT JOIN {$this->tableBooks} b ON b.book_id = jev.book_id
             LEFT JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
             WHERE jev.dv_id = {$dvId} AND jev.jev_type = 'PAYMENT'
             ORDER BY jev.jev_id",
            'DB'
        ) ?: [];

        foreach ($jevs as &$jev) {
            $jev['lines'] = App\ExecuteRows(
                "SELECT jevl.jevl_id, jevl.line_number, jevl.account_code, ac.account_title, jevl.debit, jevl.credit, jevl.description,
                        jevl.sl_code, jevl.sl_detail_id,
                        jevl.rc_id, rc.rc_name
                 FROM {$this->tableJevLines} jevl
                 LEFT JOIN {$this->tableCoa}       ac ON ac.account_code = jevl.account_code
                 LEFT JOIN {$this->tableRc}        rc ON rc.rc_id = jevl.rc_id
                 WHERE jevl.jev_id = " . (int) $jev['jev_id'] . ' ORDER BY jevl.line_number',
                'DB'
            ) ?: [];
        }
        unset($jev);

        return $jevs;
    }

    /**
     * JEV register — every Journal Entry Voucher prepared so far, joined with its DV, for the
     * standalone Journal Entry Voucher page (read-only; a JEV is only ever created/edited from
     * within its DV's Accounting stage, see saveJournalEntry()).
     */
    public function fetchJevList(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $where = ['1=1'];
            if (!empty($filters['fiscal_yr'])) {
                $where[] = 'fiscal_yr = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['jev_type'])) {
                $where[] = 'jev_type = ' . App\QuotedValue($filters['jev_type'], App\DataType::STRING, 'DB');
            }
            if (!empty($filters['jev_classification'])) {
                $where[] = 'jev_classification = ' . App\QuotedValue($filters['jev_classification'], App\DataType::STRING, 'DB');
            }
            if (!empty($filters['search'])) {
                $kw      = App\QuotedValue('%' . $filters['search'] . '%', App\DataType::STRING, 'DB');
                $where[] = "(jev_no ILIKE {$kw} OR dv_no ILIKE {$kw} OR claimant_name ILIKE {$kw})";
            }
            $whereSql = implode(' AND ', $where);

            $select = "SELECT * FROM {$this->tableViewJev} WHERE {$whereSql} ORDER BY created_at DESC, jev_id DESC";

            if ($page === null) {
                return ['success' => true, 'data' => $this->flagJevsActedByCurrentUser(App\ExecuteRows($select, 'DB') ?: [])];
            }

            $limit  = max(1, $limit);
            $offset = (max(1, $page) - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableViewJev} WHERE {$whereSql}", 'DB');

            return [
                'success'    => true,
                'data'       => $this->flagJevsActedByCurrentUser(App\ExecuteRows($select . " LIMIT {$limit} OFFSET {$offset}", 'DB') ?: []),
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchJevList ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load journal entry vouchers.'];
        }
    }

    /** True if $userId already moved this JEV along its review chain (any row in its status_change_log). */
    private function userAlreadyActedOnJev(int $jevId, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM fims.status_change_log
             WHERE status_for = 'journal_entry_voucher' AND record_id = '{$jevId}' AND changed_by = {$userId}",
            'DB'
        ) > 0;
    }

    /**
     * Adds `acted_by_me` to each JEV register row — true when the current user already took a step on
     * that JEV's review chain — so the register can hide the endorse/approve action from them.
     * The same rule is enforced server-side in changeJevStatus(). Never true for a superadmin (user id -1 or user level -1),
     * who bypasses it there — so the register keeps showing them the advance action.
     */
    private function flagJevsActedByCurrentUser(array $rows): array
    {
        $userId = (int) App\CurrentUserID();
        $ids    = array_values(array_unique(array_map(fn ($r) => (int) $r['jev_id'], $rows)));
        $acted  = [];
        if ($userId > 0 && $ids && !GlobalService::getUserRcScope()['is_admin']) {
            $list = implode(',', array_map(fn ($id) => "'" . $id . "'", $ids));
            foreach (App\ExecuteRows(
                "SELECT DISTINCT record_id FROM fims.status_change_log
                 WHERE status_for = 'journal_entry_voucher' AND changed_by = {$userId} AND record_id IN ({$list})",
                'DB'
            ) ?: [] as $r) {
                $acted[(string) $r['record_id']] = true;
            }
        }
        foreach ($rows as &$row) {
            $row['acted_by_me'] = isset($acted[(string) $row['jev_id']]);
        }

        return $rows;
    }

    /** Active ATC (BIR Alphanumeric Tax Code) options for the Accounting stage's dropdown. */
    public function fetchAtcCodes(): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT atc_code, description, tax_rate, account_code FROM {$this->tableAtc} WHERE is_active = TRUE ORDER BY atc_code",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchAtcCodes ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load ATC codes.'];
        }
    }

    /** Next free "JEV-{YY}-{MM}-{NNNN}" for the month of $jevDate. */
    private function nextJevNumber(string $jevDate): string
    {
        $prefix = 'JEV-' . substr($jevDate, 2, 2) . '-' . substr($jevDate, 5, 2) . '-';
        $max    = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(jev_no FROM '[0-9]+$') AS INTEGER)), 0)
             FROM {$this->tableJev}
             WHERE jev_no LIKE " . App\QuotedValue($prefix . '%', App\DataType::STRING, 'DB') . " AND jev_no ~ '-[0-9]+$'",
            'DB'
        );

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validates the `atc_lines` array from saveJournalEntry()'s body — withholding tax is optional
     * (0 or more classifications; a DV can withhold under more than one ATC at once).
     * withholding_tax is always server-computed from tax_base/rate, never trusted from the client,
     * same convention as deduction_amount. Returns [errors[], rows[], totalWithholding].
     */
    private function prepareAtcLines(mixed $rows, ?float $dvGross = null): array
    {
        $errors    = [];
        $out       = [];
        $total     = 0.0;
        $baseTotal = 0.0;
        if (!is_array($rows)) {
            return [$errors, $out, $total];
        }

        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $n          = $i + 1;
            $atcCode    = trim((string) ($row['atc_code'] ?? ''));
            $rate       = is_numeric($row['rate'] ?? null) ? (float) $row['rate'] : 0.0;
            $taxBase    = is_numeric($row['tax_base'] ?? null) ? (float) $row['tax_base'] : 0.0;
            $chargeability = trim((string) ($row['chargeability'] ?? ''));
            if ($atcCode === '' && $taxBase <= 0) {
                continue; // a fully blank trailing row
            }
            if ($atcCode === '') {
                $errors[] = "ATC line {$n}: ATC code is required.";
            } elseif ((int) App\ExecuteScalar('SELECT COUNT(*) FROM ' . $this->tableAtc . ' WHERE atc_code = ' . $this->str($atcCode) . ' AND is_active = TRUE', 'DB') === 0) {
                $errors[] = "ATC line {$n}: ATC code '{$atcCode}' does not exist or is inactive.";
            }
            if ($taxBase <= 0) {
                $errors[] = "ATC line {$n}: tax base must be greater than zero.";
            }
            if ($chargeability !== '' && (int) App\ExecuteScalar('SELECT COUNT(*) FROM ' . $this->tableRc . ' WHERE rc_id = ' . $this->str($chargeability), 'DB') === 0) {
                $errors[] = "ATC line {$n}: chargeability '{$chargeability}' is not a known Responsibility Center.";
            }

            $withholdingTax = round($taxBase * $rate / 100, 2);
            $total += $withholdingTax;
            $baseTotal += $taxBase;
            $out[] = [
                'atc_code'        => $atcCode,
                'rate'            => $rate,
                'tax_base'        => $taxBase,
                'withholding_tax' => $withholdingTax,
                'chargeability'   => $chargeability !== '' ? $chargeability : null,
            ];
        }

        // The ATC lines split ONE base between them (e.g. 60k at 2% + 40k at 5%): their Tax Bases adding up to more
        // than the DV's Amount means the same money is being withheld on twice (a second line left at the whole base).
        if ($dvGross !== null && round($baseTotal, 2) - round($dvGross, 2) > 0.005) {
            $errors[] = 'ATC tax bases total ' . number_format($baseTotal, 2) . ', which is more than the Amount (' . number_format($dvGross, 2) . ') — split the base between the ATC lines.';
        }

        return [$errors, $out, round($total, 2)];
    }

    /**
     * Accounting prepares (or edits) the Journal Entry Voucher — and its ATC withholding
     * classification lines — for a DV while it's at the FOR_ACCOUNTING stage; a prerequisite
     * changeStatus() checks before allowing FOR_ACCOUNTING -> FOR_TREASURY (see there). Owns its
     * own transaction (persistJournalEntry() doesn't) since this is a standalone call, not nested
     * inside create()/update()'s.
     */
    public function saveJournalEntry(int|string $dvId, array $atcLines, array $lines, int $userId, array $deductions = [], mixed $bookId = null, mixed $jevClassification = null): array
    {
        try {
            $dvId   = (int) $dvId;
            $status = App\ExecuteScalar(
                "SELECT st.name FROM {$this->table} dv LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if ($status === false || $status === null) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            if ($status !== 'FOR_ACCOUNTING') {
                return ['success' => false, 'message' => "A Journal Entry can only be prepared while the voucher is at the Accounting stage (current status: {$status})."];
            }
            if (!GlobalService::getUserRcScope()['is_admin'] && $this->currentUserRcId() !== self::STAGE_HOLDER_RC['FOR_ACCOUNTING']) {
                return ['success' => false, 'message' => 'Only Accounting Department staff can prepare this Journal Entry.', 'httpCode' => 403];
            }

            App\Execute('BEGIN', 'DB');
            try {
                $result = $this->persistJournalEntry($dvId, $atcLines, $lines, $userId, true, $deductions, $bookId, $jevClassification);
                if (!$result['success']) {
                    App\Execute('ROLLBACK', 'DB');

                    return $result;
                }
                App\Execute('COMMIT', 'DB');

                return $result;
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::saveJournalEntry ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to save the Journal Entry.'];
        }
    }

    /**
     * Core validate+save for a DV's ATC withholding + Journal Entry lines. No status/RC check and
     * no transaction management here — the caller owns both:
     *   - saveJournalEntry() (Accounting, FOR_ACCOUNTING stage only) checks status+RC, then wraps
     *     this in its own BEGIN/COMMIT.
     *   - create()/update() (Originating, optional pre-fill at any editable stage — "ATC Code" and
     *     "Accounting Entries" are optional sections on the DV form) call this from inside their
     *     own already-open transaction, so a validation failure here rolls back the whole DV save.
     *     $requireBalancedEntry is false there: 0 lines/0 ATC rows is a valid "not filled in yet"
     *     state, not an error — Accounting still has to prepare a real one later regardless.
     * Full replace of both atc_lines/lines, same upsert shape as PurchaseOrderService's items.
     * Recomputes the DV's net_amount/balance_amount either way.
     *
     * $deductions is null for the create()/update() pre-fill call sites — they already manage
     * fims.disbursement_voucher_deductions themselves (via their own prepareDeductions()/
     * replaceDeductions() calls, before this runs), so this leaves that table alone. Only
     * saveJournalEntry() (Accounting) passes a real array — a full replace, same "always send the
     * current set" convention atc_lines/lines already use, now that Accounting can edit Deductions
     * too (see app/pages/voucher/readone.php's vje_deductionCard()).
     *
     * $bookId (fims.books — see "03 - alter_table/30") is required whenever $lines is non-empty on
     * Accounting's own save ($requireBalancedEntry) — Book is an accounting classification chosen
     * alongside the Accounting Entries themselves, not a DV-header field, so it's validated here
     * rather than in prepare(). Optional on the Originating pre-fill (see the check below). Moot when
     * $lines is empty (no JEV being saved at all).
     */
    private const JEV_CLASSIFICATIONS = ['OPENING', 'REGULAR', 'ACCRUAL', 'DEPRECIATION', 'PREPAYMENTS', 'CLOSING'];

    /**
     * Shared Dr/Cr line validation — used by both persistJournalEntry() (DV-driven JEVs) and
     * createStandaloneJev()/updateStandaloneJev() (no-DV ACCRUAL/DEPRECIATION/PREPAYMENTS/CLOSING
     * entries, "03 - alter_table/33"). Returns [errors, cleanLines, totalDebit, totalCredit] — does
     * NOT itself enforce "≥2 lines"/"balanced", since callers need that check worded differently
     * (persistJournalEntry's $requireBalancedEntry flag allows 0 lines as "nothing entered yet").
     */
    private function validateJevLines(array $lines): array
    {
        $errors = [];
        $clean = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach (array_values($lines) as $i => $line) {
            $n           = $i + 1;
            $accountCode = trim((string) ($line['account_code'] ?? ''));
            $debit       = is_numeric($line['debit'] ?? null) ? (float) $line['debit'] : 0.0;
            $credit      = is_numeric($line['credit'] ?? null) ? (float) $line['credit'] : 0.0;
            $slCode      = trim((string) ($line['sl_code'] ?? ''));
            $slDetailId  = $line['sl_detail_id'] ?? '';
            $rcId        = trim((string) ($line['rc_id'] ?? ''));
            if ($accountCode === '' && $debit <= 0 && $credit <= 0) {
                continue; // a fully blank trailing row
            }
            if ($accountCode === '') {
                $errors[] = "Line {$n}: account code is required.";
            } else {
                $account = App\ExecuteRow(
                    "SELECT posting_allowed FROM {$this->tableCoa} WHERE account_code = " . $this->str($accountCode),
                    'DB'
                );
                if (!$account) {
                    $errors[] = "Line {$n}: account code '{$accountCode}' does not exist in the chart of accounts.";
                } elseif (!$account['posting_allowed']) {
                    $errors[] = "Line {$n}: account code '{$accountCode}' is not postable (it's a summary/header account).";
                }
            }
            if (($debit > 0) === ($credit > 0)) {
                $errors[] = "Line {$n}: enter either a debit or a credit amount, not both or neither.";
            }
            $slDetailId = ctype_digit((string) $slDetailId) && (int) $slDetailId > 0 ? (int) $slDetailId : null;
            if ($rcId !== '' && (int) App\ExecuteScalar('SELECT COUNT(*) FROM ' . $this->tableRc . ' WHERE rc_id = ' . $this->str($rcId), 'DB') === 0) {
                $errors[] = "Line {$n}: Resp Center '{$rcId}' is not a known Responsibility Center.";
            }
            $totalDebit  += $debit;
            $totalCredit += $credit;
            $clean[] = [
                'account_code' => $accountCode, 'debit' => $debit, 'credit' => $credit,
                'description'  => trim((string) ($line['description'] ?? '')),
                'sl_code'      => $slCode !== '' ? $slCode : null,
                'sl_detail_id' => $slDetailId,
                'rc_id'        => $rcId !== '' ? $rcId : null,
            ];
        }

        return [$errors, $clean, round($totalDebit, 2), round($totalCredit, 2)];
    }

    private function persistJournalEntry(int $dvId, array $atcLines, array $lines, int $userId, bool $requireBalancedEntry, ?array $deductions = null, mixed $bookId = null, mixed $jevClassification = null): array
    {
        $dv = App\ExecuteRow(
            "SELECT dv_id, fiscal_yr, gross_amount, obligation_amount, payments_prior_amount
             FROM {$this->table} WHERE dv_id = {$dvId}",
            'DB'
        );
        if (!$dv) {
            return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
        }

        $bookId = ($bookId !== null && $bookId !== '' && ctype_digit((string) $bookId)) ? (int) $bookId : null;
        // Only Accounting's own save ($requireBalancedEntry) must choose a Book. The Originating
        // pre-fill (create()/update()) has no Book field anymore — section D is hidden from Originating
        // — yet payroll/remittance DVs still pre-fill their entries from those modules, so those are
        // stored with book_id NULL until Accounting picks one. changeStatus() blocks moving the DV past
        // the Section Chief while it's still NULL.
        if ($lines && $bookId === null && $requireBalancedEntry) {
            return ['success' => false, 'message' => 'Book (book_id) is required when Accounting Entries lines are provided.'];
        }
        if ($bookId !== null && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableBooks} WHERE book_id = {$bookId}", 'DB') === 0) {
            return ['success' => false, 'message' => "Book #{$bookId} does not exist."];
        }

        $jevClassification = strtoupper(trim((string) ($jevClassification ?? 'REGULAR')));
        if ($jevClassification === '') {
            $jevClassification = 'REGULAR';
        }
        if (!in_array($jevClassification, self::JEV_CLASSIFICATIONS, true)) {
            return ['success' => false, 'message' => "jev_classification must be one of: " . implode(', ', self::JEV_CLASSIFICATIONS) . '.'];
        }

        $errors = [];

        if ($deductions !== null) {
            [$dedErrors, $cleanDeductions] = $this->prepareDeductions($deductions, (float) $dv['gross_amount']);
            $errors = array_merge($errors, $dedErrors);
        }

        [$atcErrors, $cleanAtc, $totalWithholding] = $this->prepareAtcLines($atcLines, (float) $dv['gross_amount']);
        $errors = array_merge($errors, $atcErrors);

        [$lineErrors, $clean, $totalDebit, $totalCredit] = $this->validateJevLines($lines);
        $errors = array_merge($errors, $lineErrors);
        // 0 lines is only an error when a complete entry is required (the dedicated Accounting
        // save); as an optional pre-fill, "nothing entered yet" isn't a validation failure, just
        // nothing to save. 1 line is always wrong either way — a JEV can't have only one side.
        if (count($clean) === 1 || ($requireBalancedEntry && count($clean) === 0)) {
            $errors[] = 'A Journal Entry needs at least two lines (one debit, one credit).';
        }
        if (count($clean) >= 2 && !$errors && ($totalDebit <= 0 || $totalDebit !== $totalCredit)) {
            $errors[] = "Total debits (" . number_format($totalDebit, 2) . ") must equal total credits (" . number_format($totalCredit, 2) . ') and be greater than zero.';
        }
        if ($errors) {
            return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
        }

        if ($deductions !== null) {
            $this->replaceDeductions($dvId, $cleanDeductions);
        }

        $totalDeductions = (float) (App\ExecuteScalar("SELECT COALESCE(SUM(deduction_amount), 0) FROM {$this->tableDvDeductions} WHERE dv_id = {$dvId}", 'DB') ?? 0);
        $netAmount       = round((float) $dv['gross_amount'] - $totalDeductions - $totalWithholding, 2);
        if ($netAmount < 0) {
            return ['success' => false, 'message' => 'Validation failed.', 'errors' => ['Deductions (' . number_format($totalDeductions, 2) . ') plus withholding tax (' . number_format($totalWithholding, 2) . ') cannot exceed the Amount (' . number_format((float) $dv['gross_amount'], 2) . ').']];
        }
        $balance         = round((float) $dv['obligation_amount'] - ((float) $dv['payments_prior_amount'] + $netAmount), 2);

        $uid = $userId > 0 ? (string) $userId : 'NULL';

        if (count($clean) > 0) {
            $existing = App\ExecuteRow("SELECT jev_id FROM {$this->tableJev} WHERE dv_id = {$dvId}", 'DB');
            if ($existing) {
                $jevId = (int) $existing['jev_id'];
                App\Execute(
                    "UPDATE {$this->tableJev} SET
                        book_id = " . $this->intOrNull($bookId) . ",
                        jev_classification = " . $this->str($jevClassification) . ",
                        total_debit = {$totalDebit}, total_credit = {$totalCredit},
                        updated_by = {$uid}, updated_at = CURRENT_TIMESTAMP
                     WHERE jev_id = {$jevId}",
                    'DB'
                );
                App\Execute("DELETE FROM {$this->tableJevLines} WHERE jev_id = {$jevId}", 'DB');
            } else {
                $jevNo = $this->nextJevNumber(date('Y-m-d'));
                $row = App\ExecuteRow(
                    "INSERT INTO {$this->tableJev} (jev_no, dv_id, fiscal_yr, book_id, jev_classification, total_debit, total_credit, created_by)
                     VALUES (" . $this->str($jevNo) . ", {$dvId}, " . (int) $dv['fiscal_yr'] . ', ' . $this->intOrNull($bookId) . ', ' . $this->str($jevClassification) . ", {$totalDebit}, {$totalCredit}, {$uid})
                     RETURNING jev_id",
                    'DB'
                );
                $jevId = (int) $row['jev_id'];
            }

            foreach ($clean as $idx => $line) {
                App\Execute(
                    "INSERT INTO {$this->tableJevLines} (jev_id, line_number, account_code, debit, credit, description, sl_code, sl_detail_id, rc_id)
                     VALUES ({$jevId}, " . ($idx + 1) . ', ' . $this->str($line['account_code']) . ', '
                    . (float) $line['debit'] . ', ' . (float) $line['credit'] . ', ' . $this->strOrNull($line['description']) . ', '
                    . $this->strOrNull($line['sl_code']) . ', ' . $this->intOrNull($line['sl_detail_id']) . ', '
                    . $this->strOrNull($line['rc_id']) . ')',
                    'DB'
                );
            }
        } else {
            // Explicit "no lines" submission — clears a previously-saved JEV too (full-replace
            // semantics, same as everything else on this form), cascades to its lines.
            App\Execute("DELETE FROM {$this->tableJev} WHERE dv_id = {$dvId}", 'DB');
        }

        App\Execute("DELETE FROM {$this->tableDvAtcLines} WHERE dv_id = {$dvId}", 'DB');
        foreach ($cleanAtc as $idx => $atc) {
            App\Execute(
                "INSERT INTO {$this->tableDvAtcLines} (dv_id, line_number, atc_code, rate, tax_base, withholding_tax, chargeability)
                 VALUES ({$dvId}, " . ($idx + 1) . ', ' . $this->str($atc['atc_code']) . ', '
                . (float) $atc['rate'] . ', ' . (float) $atc['tax_base'] . ', ' . (float) $atc['withholding_tax'] . ', '
                . $this->strOrNull($atc['chargeability']) . ')',
                'DB'
            );
        }

        App\Execute(
            "UPDATE {$this->table} SET
                net_amount = {$netAmount}, balance_amount = {$balance},
                updated_by = {$uid}, updated_at = CURRENT_TIMESTAMP
             WHERE dv_id = {$dvId}",
            'DB'
        );

        return ['success' => true, 'message' => 'Journal Entry saved.', 'data' => ['dv_id' => $dvId, 'net_amount' => $netAmount, 'balance_amount' => $balance]];
    }

    // ── Standalone Journal Entry Voucher (no DV — "03 - alter_table/33") ──
    //
    // Every JEV above is prepared from inside a DV's Accounting stage. Period-end adjusting/closing
    // entries (jev_classification ACCRUAL/DEPRECIATION/PREPAYMENTS/CLOSING) aren't disbursement-
    // driven at all in real NGAS practice — Accounting books them directly in the General Journal,
    // with no claimant, no payment, nothing for FMD/Treasury to act on. These four methods are that
    // standalone path: dv_id is NULL, jev_type stays 'SETUP' (still the only kind this class ever
    // writes — fits the existing partial unique index fine since multiple NULL dv_id rows don't
    // collide), and posting to the GL is this class's own direct action (postStandaloneJev()) rather
    // than something DisbursementVoucherService::changeStatus() triggers on a DV transition.

    /** One standalone JEV + its lines, or null if it doesn't exist / isn't standalone (has a dv_id). */
    public function fetchStandaloneJev(int $jevId): array
    {
        try {
            $jev = App\ExecuteRow(
                "SELECT jev.*, b.name AS book_name, st.name AS jev_status
                 FROM {$this->tableJev} jev
                 LEFT JOIN {$this->tableBooks} b ON b.book_id = jev.book_id
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.jev_id = {$jevId} AND jev.dv_id IS NULL",
                'DB'
            );
            if (!$jev) {
                return ['success' => false, 'message' => "Standalone Journal Entry Voucher #{$jevId} not found.", 'httpCode' => 404];
            }
            $jev['lines'] = App\ExecuteRows(
                "SELECT jevl.jevl_id, jevl.line_number, jevl.account_code, ac.account_title, jevl.debit, jevl.credit, jevl.description,
                        jevl.sl_code, jevl.sl_detail_id,
                        jevl.rc_id, rc.rc_name
                 FROM {$this->tableJevLines} jevl
                 LEFT JOIN {$this->tableCoa}       ac ON ac.account_code = jevl.account_code
                 LEFT JOIN {$this->tableRc}        rc ON rc.rc_id = jevl.rc_id
                 WHERE jevl.jev_id = {$jevId} ORDER BY jevl.line_number",
                'DB'
            ) ?: [];

            // Supporting Documents attached to this standalone JEV, if any — same convention as
            // DisbursementVoucherService::fetchById()'s file_attachments.
            $jev['file_attachments'] = (new FileUploadServices())->attachmentsFor($jev['multiple_node_id'] ?? null);

            // Review-chain history (who moved it to which status, when, with what remarks) — written by
            // changeJevStatus() via GlobalService::logStatusChange(), oldest first.
            $jev['status_history'] = App\ExecuteRows(
                "SELECT l.status, l.remarks, l.changed_at,
                        TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) AS actor_name
                 FROM fims.status_change_log l
                 LEFT JOIN {$this->tableUsers} u ON u.user_id = l.changed_by
                 WHERE l.status_for = 'journal_entry_voucher' AND l.record_id = '{$jevId}'
                 ORDER BY l.changed_at ASC, l.status_change_log_id ASC",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $jev];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchStandaloneJev ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the Journal Entry Voucher.'];
        }
    }

    /**
     * Create a standalone JEV — DRAFT, no dv_id. Accounting Department only (same RC gate as
     * saveJournalEntry()). $body: jev_date, fiscal_yr, book_id, jev_classification, particulars?,
     * lines: [{account_code, debit, credit, description?, sl_code?, sl_detail_id?, rc_id?}] (≥2,
     * balanced — always required here, unlike persistJournalEntry()'s optional-prefill case).
     */
    public function createStandaloneJev(array $body, int $userId): array
    {
        try {
            if (!GlobalService::getUserRcScope()['is_admin'] && $this->currentUserRcId() !== self::STAGE_HOLDER_RC['FOR_ACCOUNTING']) {
                return ['success' => false, 'message' => 'Only Accounting Department staff can prepare a Journal Entry Voucher.', 'httpCode' => 403];
            }

            $errors = [];
            $jevDate = trim((string) ($body['jev_date'] ?? ''));
            if (!$this->validDate($jevDate)) {
                $errors[] = 'jev_date is required and must be a valid date (YYYY-MM-DD).';
            }
            // fiscal_yr is derived from jev_date's year, not taken from the client — fims.fiscal_year
            // rows are plain calendar years (see "01 - table/02"), so the JEV's own date already
            // determines it.
            $fiscalYr = $jevDate !== '' ? (int) substr($jevDate, 0, 4) : 0;
            if ($fiscalYr > 0 && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = {$fiscalYr}", 'DB') === 0) {
                $errors[] = "No fiscal year {$fiscalYr} is configured for this JEV date.";
            }

            $bookId = $body['book_id'] ?? null;
            $bookId = ($bookId !== null && $bookId !== '' && ctype_digit((string) $bookId)) ? (int) $bookId : null;
            if ($bookId === null) {
                $errors[] = 'book_id is required.';
            } elseif ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableBooks} WHERE book_id = {$bookId}", 'DB') === 0) {
                $errors[] = "Book #{$bookId} does not exist.";
            }

            $jevClassification = 'REGULAR';

            [$lineErrors, $clean, $totalDebit, $totalCredit] = $this->validateJevLines(is_array($body['lines'] ?? null) ? $body['lines'] : []);
            $errors = array_merge($errors, $lineErrors);
            if (count($clean) < 2) {
                $errors[] = 'A Journal Entry needs at least two lines (one debit, one credit).';
            } elseif (!$errors && ($totalDebit <= 0 || $totalDebit !== $totalCredit)) {
                $errors[] = 'Total debits (' . number_format($totalDebit, 2) . ') must equal total credits (' . number_format($totalCredit, 2) . ') and be greater than zero.';
            }

            // Supporting Documents (optional) — a batch already uploaded client-side via
            // POST /upload/multi-upload, same convention as DisbursementVoucherService::prepare().
            $nodeId = trim((string) ($body['multiple_node_id'] ?? ''));
            if ($nodeId !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $nodeId)) {
                $errors[] = 'multiple_node_id must be a UUID.';
                $nodeId   = '';
            }
            $multipleNodeId = $nodeId !== '' ? strtolower($nodeId) : null;

            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }

            $draftStatusId = App\ExecuteScalar(
                "SELECT id FROM {$this->tableSettingsStatus} WHERE status_for = 'journal_entry_voucher' AND name = 'DRAFT'",
                'DB'
            );
            $uid = $userId > 0 ? (string) $userId : 'NULL';
            $jevNo = $this->nextJevNumber($jevDate);

            App\Execute('BEGIN', 'DB');
            try {
                $row = App\ExecuteRow(
                    "INSERT INTO {$this->tableJev}
                        (jev_no, jev_date, dv_id, fiscal_yr, book_id, jev_classification, particulars, total_debit, total_credit, status_id, multiple_node_id, created_by)
                     VALUES (" . $this->str($jevNo) . ', ' . $this->str($jevDate) . ', NULL, ' . (int) $fiscalYr . ', ' . (int) $bookId . ', '
                        . $this->str($jevClassification) . ', ' . $this->strOrNull(trim((string) ($body['particulars'] ?? ''))) . ", {$totalDebit}, {$totalCredit}, "
                        . $this->intOrNull($draftStatusId) . ', ' . $this->strOrNull($multipleNodeId) . ", {$uid})
                     RETURNING jev_id",
                    'DB'
                );
                $jevId = (int) $row['jev_id'];

                foreach ($clean as $idx => $line) {
                    App\Execute(
                        "INSERT INTO {$this->tableJevLines} (jev_id, line_number, account_code, debit, credit, description, sl_code, sl_detail_id, rc_id)
                         VALUES ({$jevId}, " . ($idx + 1) . ', ' . $this->str($line['account_code']) . ', '
                        . (float) $line['debit'] . ', ' . (float) $line['credit'] . ', ' . $this->strOrNull($line['description']) . ', '
                        . $this->strOrNull($line['sl_code']) . ', ' . $this->intOrNull($line['sl_detail_id']) . ', '
                        . $this->strOrNull($line['rc_id']) . ')',
                        'DB'
                    );
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => "Journal Entry Voucher {$jevNo} saved as draft.", 'data' => ['jev_id' => $jevId, 'jev_no' => $jevNo]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::createStandaloneJev ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to save the Journal Entry Voucher.'];
        }
    }

    /** Full-replace edit of a standalone JEV — DRAFT only, same validation as createStandaloneJev(). */
    public function updateStandaloneJev(int $jevId, array $body, int $userId): array
    {
        try {
            if (!GlobalService::getUserRcScope()['is_admin'] && $this->currentUserRcId() !== self::STAGE_HOLDER_RC['FOR_ACCOUNTING']) {
                return ['success' => false, 'message' => 'Only Accounting Department staff can edit this Journal Entry Voucher.', 'httpCode' => 403];
            }

            $existing = App\ExecuteRow(
                "SELECT jev.jev_id, st.name AS status FROM {$this->tableJev} jev
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.jev_id = {$jevId} AND jev.dv_id IS NULL",
                'DB'
            );
            if (!$existing) {
                return ['success' => false, 'message' => "Standalone Journal Entry Voucher #{$jevId} not found.", 'httpCode' => 404];
            }
            if ($existing['status'] !== 'DRAFT') {
                return ['success' => false, 'message' => "Only a DRAFT Journal Entry Voucher can be edited (current status: {$existing['status']})."];
            }

            $errors = [];
            $jevDate = trim((string) ($body['jev_date'] ?? ''));
            if (!$this->validDate($jevDate)) {
                $errors[] = 'jev_date is required and must be a valid date (YYYY-MM-DD).';
            }
            // fiscal_yr is derived from jev_date's year, not taken from the client — see
            // createStandaloneJev().
            $fiscalYr = $jevDate !== '' ? (int) substr($jevDate, 0, 4) : 0;
            if ($fiscalYr > 0 && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = {$fiscalYr}", 'DB') === 0) {
                $errors[] = "No fiscal year {$fiscalYr} is configured for this JEV date.";
            }

            $bookId = $body['book_id'] ?? null;
            $bookId = ($bookId !== null && $bookId !== '' && ctype_digit((string) $bookId)) ? (int) $bookId : null;
            if ($bookId === null) {
                $errors[] = 'book_id is required.';
            } elseif ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableBooks} WHERE book_id = {$bookId}", 'DB') === 0) {
                $errors[] = "Book #{$bookId} does not exist.";
            }

            $jevClassification = 'REGULAR';

            [$lineErrors, $clean, $totalDebit, $totalCredit] = $this->validateJevLines(is_array($body['lines'] ?? null) ? $body['lines'] : []);
            $errors = array_merge($errors, $lineErrors);
            if (count($clean) < 2) {
                $errors[] = 'A Journal Entry needs at least two lines (one debit, one credit).';
            } elseif (!$errors && ($totalDebit <= 0 || $totalDebit !== $totalCredit)) {
                $errors[] = 'Total debits (' . number_format($totalDebit, 2) . ') must equal total credits (' . number_format($totalCredit, 2) . ') and be greater than zero.';
            }

            // Supporting Documents — undefined (key absent) means "unchanged, keep whatever this JEV
            // already has"; null/'' means "cleared"; anything else must be a UUID — same convention as
            // createStandaloneJev()/DisbursementVoucherService::prepare().
            $keepMultipleNodeId = !array_key_exists('multiple_node_id', $body);
            $nodeId = trim((string) ($body['multiple_node_id'] ?? ''));
            if ($nodeId !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $nodeId)) {
                $errors[] = 'multiple_node_id must be a UUID.';
                $nodeId   = '';
            }
            $multipleNodeId = $nodeId !== '' ? strtolower($nodeId) : null;

            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }

            $uid = $userId > 0 ? (string) $userId : 'NULL';
            $multipleNodeIdSql = $keepMultipleNodeId ? 'multiple_node_id' : $this->strOrNull($multipleNodeId);

            App\Execute('BEGIN', 'DB');
            try {
                App\Execute(
                    "UPDATE {$this->tableJev} SET
                        jev_date = " . $this->str($jevDate) . ", fiscal_yr = " . (int) $fiscalYr . ", book_id = " . (int) $bookId . ",
                        jev_classification = " . $this->str($jevClassification) . ",
                        particulars = " . $this->strOrNull(trim((string) ($body['particulars'] ?? ''))) . ",
                        total_debit = {$totalDebit}, total_credit = {$totalCredit},
                        multiple_node_id = {$multipleNodeIdSql},
                        updated_by = {$uid}, updated_at = CURRENT_TIMESTAMP
                     WHERE jev_id = {$jevId}",
                    'DB'
                );
                App\Execute("DELETE FROM {$this->tableJevLines} WHERE jev_id = {$jevId}", 'DB');
                foreach ($clean as $idx => $line) {
                    App\Execute(
                        "INSERT INTO {$this->tableJevLines} (jev_id, line_number, account_code, debit, credit, description, sl_code, sl_detail_id, rc_id)
                         VALUES ({$jevId}, " . ($idx + 1) . ', ' . $this->str($line['account_code']) . ', '
                        . (float) $line['debit'] . ', ' . (float) $line['credit'] . ', ' . $this->strOrNull($line['description']) . ', '
                        . $this->strOrNull($line['sl_code']) . ', ' . $this->intOrNull($line['sl_detail_id']) . ', '
                        . $this->strOrNull($line['rc_id']) . ')',
                        'DB'
                    );
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => 'Journal Entry Voucher updated.', 'data' => ['jev_id' => $jevId]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::updateStandaloneJev ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update the Journal Entry Voucher.'];
        }
    }

    /** DRAFT only, no dv_id — deletes the header (lines cascade via FK). */
    public function deleteStandaloneJev(int $jevId, int $userId): array
    {
        try {
            if (!GlobalService::getUserRcScope()['is_admin'] && $this->currentUserRcId() !== self::STAGE_HOLDER_RC['FOR_ACCOUNTING']) {
                return ['success' => false, 'message' => 'Only Accounting Department staff can delete this Journal Entry Voucher.', 'httpCode' => 403];
            }
            $existing = App\ExecuteRow(
                "SELECT jev.jev_id, st.name AS status FROM {$this->tableJev} jev
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.jev_id = {$jevId} AND jev.dv_id IS NULL",
                'DB'
            );
            if (!$existing) {
                return ['success' => false, 'message' => "Standalone Journal Entry Voucher #{$jevId} not found.", 'httpCode' => 404];
            }
            if ($existing['status'] !== 'DRAFT') {
                return ['success' => false, 'message' => "Only a DRAFT Journal Entry Voucher can be deleted (current status: {$existing['status']})."];
            }
            App\Execute("DELETE FROM {$this->tableJev} WHERE jev_id = {$jevId}", 'DB');

            return ['success' => true, 'message' => 'Journal Entry Voucher deleted.'];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::deleteStandaloneJev ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to delete the Journal Entry Voucher.'];
        }
    }

    /**
     * Posts a standalone JEV to the General Ledger — delegates to GeneralLedgerService::postJev(),
     * same posting logic as every DV-driven JEV, just triggered directly here instead of from
     * DisbursementVoucherService::changeStatus() (there's no DV status chain to ride on). Guards
     * dv_id IS NULL so this endpoint can't be used to post a DV-linked JEV out of band, bypassing
     * that voucher's own FOR_ACCOUNTING -> FOR_TREASURY approval chain.
     */
    public function postStandaloneJev(int $jevId, int $userId): array
    {
        try {
            if (!GlobalService::getUserRcScope()['is_admin'] && $this->currentUserRcId() !== self::STAGE_HOLDER_RC['FOR_ACCOUNTING']) {
                return ['success' => false, 'message' => 'Only Accounting Department staff can post this Journal Entry Voucher.', 'httpCode' => 403];
            }
            $jev = App\ExecuteRow("SELECT jev_id, dv_id FROM {$this->tableJev} WHERE jev_id = {$jevId}", 'DB');
            if (!$jev) {
                return ['success' => false, 'message' => "Journal Entry Voucher #{$jevId} not found.", 'httpCode' => 404];
            }
            if ($jev['dv_id'] !== null) {
                return ['success' => false, 'message' => 'This Journal Entry Voucher is tied to a Disbursement Voucher — post it via that voucher\'s own Accounting stage instead.'];
            }

            return (new GeneralLedgerService())->postJev($jevId, $userId);
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::postStandaloneJev ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to post the Journal Entry Voucher.'];
        }
    }

    /**
     * Accounting prepares the PAYMENT Journal Entry for a RELEASED DV — the "Prepare Payment JEV"
     * action. Builds the Dr Accounts Payable / Cr Cash entry from the DV's check (via
     * GeneralLedgerService::createPaymentJev()), creating it at FOR_SECTION_CHIEF so it enters the JEV
     * review chain. Treasury never prepares this — it is Accounting's job (RC 012) or a superadmin.
     */
    public function preparePaymentJev(int $dvId, int $userId): array
    {
        try {
            if (!GlobalService::getUserRcScope()['is_admin'] && $this->currentUserRcId() !== self::STAGE_HOLDER_RC['FOR_ACCOUNTING']) {
                return ['success' => false, 'message' => 'Only Accounting Department staff can prepare a payment Journal Entry.', 'httpCode' => 403];
            }
            $dv = App\ExecuteRow(
                "SELECT dv.dv_id, dv.dv_no, dv.net_amount, st.name AS status
                 FROM {$this->table} dv LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if (!$dv) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            if ($dv['status'] !== 'RELEASED') {
                return ['success' => false, 'message' => "A payment Journal Entry can only be prepared once the voucher is RELEASED (current status: {$dv['status']})."];
            }
            if ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableJev} WHERE dv_id = {$dvId} AND jev_type = 'PAYMENT'", 'DB') > 0) {
                return ['success' => false, 'message' => 'This voucher already has a payment Journal Entry.'];
            }
            // The check Treasury released against this DV — its bank account's Cash account is the Cr line.
            $check = App\ExecuteRow(
                "SELECT bank_account_id, check_number FROM fims.checks
                 WHERE dv_number = " . $this->str($dv['dv_no']) . " AND status <> 'CANCELLED'
                 ORDER BY check_id DESC LIMIT 1",
                'DB'
            );
            if (!$check) {
                return ['success' => false, 'message' => 'No check has been prepared against this voucher yet — Treasury must release it first.'];
            }

            App\Execute('BEGIN', 'DB');
            try {
                $result = (new GeneralLedgerService())->createPaymentJev($dvId, (int) $check['bank_account_id'], (float) $dv['net_amount'], 'CHECK NO. ' . $check['check_number'], $userId);
                if (!$result['success']) {
                    App\Execute('ROLLBACK', 'DB');

                    return $result;
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return $result;
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::preparePaymentJev ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to prepare the payment Journal Entry.'];
        }
    }

    /**
     * Advances a PAYMENT or standalone JEV one step along its review chain, and POSTS it to the General
     * Ledger on the final step. SETUP JEVs are refused here — they ride their own DV's approval chain.
     *   DRAFT                  -> FOR_SECTION_CHIEF      (standalone preparer submits for review)
     *   FOR_SECTION_CHIEF      -> FOR_DIVISION_MANAGER   (Section Chief reviews)
     *   FOR_DIVISION_MANAGER   -> FOR_DEPARTMENT_MANAGER (Division Manager reviews)
     *   FOR_DEPARTMENT_MANAGER -> POSTED                 (Department Manager approves & posts to the GL)
     * Every step is held by the Accounting Department (RC 012) or a superadmin.
     */
    public function changeJevStatus(int $jevId, string $newStatus, int $userId, ?string $remarks = null): array
    {
        $transitions = [
            'DRAFT'                  => ['FOR_SECTION_CHIEF'],
            'FOR_SECTION_CHIEF'      => ['FOR_DIVISION_MANAGER'],
            'FOR_DIVISION_MANAGER'   => ['FOR_DEPARTMENT_MANAGER'],
            'FOR_DEPARTMENT_MANAGER' => ['POSTED'],
        ];
        try {
            if (!GlobalService::getUserRcScope()['is_admin'] && $this->currentUserRcId() !== self::STAGE_HOLDER_RC['FOR_ACCOUNTING']) {
                return ['success' => false, 'message' => 'Only Accounting Department staff can act on this Journal Entry.', 'httpCode' => 403];
            }
            $new = strtoupper(trim($newStatus));
            $jev = App\ExecuteRow(
                "SELECT jev.jev_id, jev.jev_type, jev.dv_id, st.name AS status
                 FROM {$this->tableJev} jev LEFT JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.jev_id = {$jevId}",
                'DB'
            );
            if (!$jev) {
                return ['success' => false, 'message' => "Journal Entry Voucher #{$jevId} not found.", 'httpCode' => 404];
            }
            if ($jev['jev_type'] === 'SETUP' && $jev['dv_id'] !== null) {
                return ['success' => false, 'message' => 'A setup Journal Entry posts through its Disbursement Voucher\'s own approval chain, not here.'];
            }
            $allowed = $transitions[$jev['status']] ?? [];
            if (!in_array($new, $allowed, true)) {
                return ['success' => false, 'message' => "Cannot move a {$jev['status']} Journal Entry to {$new}."];
            }
            // Segregation of duties: each step needs a different person — whoever already submitted /
            // endorsed this entry can't also take the next step on it. A superadmin bypasses it (same as the DV chain's
            // authorizeTransition()), so one admin account can walk an entry through every step.
            if (!GlobalService::getUserRcScope()['is_admin'] && $this->userAlreadyActedOnJev($jevId, $userId)) {
                return ['success' => false, 'message' => 'You have already acted on this Journal Entry. The next step must be done by a different user.', 'httpCode' => 403];
            }

            if ($new === 'POSTED') {
                App\Execute('BEGIN', 'DB');
                try {
                    $result = (new GeneralLedgerService())->postJev($jevId, $userId);
                    if (!$result['success']) {
                        App\Execute('ROLLBACK', 'DB');

                        return $result;
                    }
                    App\Execute('COMMIT', 'DB');
                } catch (\Exception $e) {
                    App\Execute('ROLLBACK', 'DB');
                    throw $e;
                }

                GlobalService::logStatusChange('journal_entry_voucher', $jevId, 'POSTED', $userId, $remarks);

                return ['success' => true, 'message' => 'Journal Entry approved and posted to the General Ledger.', 'data' => ['jev_id' => $jevId, 'status' => 'POSTED']];
            }

            $newId = $this->getJevStatusIdByName($new);
            if ($newId === null) {
                return ['success' => false, 'message' => "Status '{$new}' is not configured for journal entry vouchers."];
            }
            App\Execute("UPDATE {$this->tableJev} SET status_id = {$newId} WHERE jev_id = {$jevId}", 'DB');
            GlobalService::logStatusChange('journal_entry_voucher', $jevId, $new, $userId, $remarks);

            return ['success' => true, 'message' => "Journal Entry is now {$new}.", 'data' => ['jev_id' => $jevId, 'status' => $new]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::changeJevStatus ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update the Journal Entry status.'];
        }
    }

    /** JEV-status id lookup (status_for = 'journal_entry_voucher'); see getStatusIdByName() for the DV equivalent. */
    private function getJevStatusIdByName(string $name): ?int
    {
        $row = App\ExecuteRow(
            "SELECT id FROM {$this->tableSettingsStatus} WHERE status_for = 'journal_entry_voucher' AND name = " . $this->str($name),
            'DB'
        );

        return isset($row['id']) ? (int) $row['id'] : null;
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
            $body = $this->withPayrollAmounts($body);   // a payroll DV's Amount / deductions are the import's
            [$errors, $f] = $this->prepare($body);

            $dvNo = trim((string) ($body['dv_no'] ?? ''));
            if ($dvNo !== '' && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE dv_no = " . $this->str($dvNo), 'DB') > 0) {
                $errors[] = "DV Number '{$dvNo}' already exists.";
            }
            if ($f['_payrollPeriodId'] && ($payrollError = (new PayrollManagementService())->validateDvLink($f['_payrollPeriodId'])) !== null) {
                $errors[] = $payrollError;
            }
            if ($f['_remittanceId'] && ($remError = (new DeductionRemittanceService())->validateDvLink($f['_remittanceId'])) !== null) {
                $errors[] = $remError;
            }
            if ($f['_dvRemit'] && ($dvRemitError = (new RemittanceListService())->validateLink($f['_dvRemit']['period'], $f['_dvRemit']['type'], 0, (float) $f['gross_amount'], $f['_dvRemit']['vendor'])) !== null) {
                $errors[] = $dvRemitError;
            }
            [$rfaErrors, $rfaBudget] = $this->checkAmountAgainstRfas($f, null);
            $errors = array_merge($errors, $rfaErrors);
            if ($rfaBudget !== null) {
                $f['budget_amount'] = $f['obligation_amount'] = $rfaBudget;
            }
            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }
            if ($dvNo === '') {
                $dvNo = $this->nextDvNumber($f['dv_date']);
            }

            $paymentsPrior = $this->priorPaymentsForRfas(array_column($f['_rfaRows'], 'allocation_id'), null);
            $balance       = round($f['obligation_amount'] - ($paymentsPrior + $f['net_amount']), 2);

            $submitted = $f['status'] === 'FOR_FMD_REVIEW';
            $uid       = $userId > 0 ? (string) $userId : 'NULL';

            App\Execute('BEGIN', 'DB');
            try {
                $row = App\ExecuteRow(
                    "INSERT INTO {$this->table}
                        (dv_no, dv_date, fiscal_yr, expense_class, status_id,
                         requesting_department_id, dept_project, fund_source,
                         vendor_id, claimant_name, claimant_address, employee_no_tin,
                         particulars, gross_amount, net_amount,
                         po_number, po_amount,
                         account_code, budget_amount, obligation_amount, payments_prior_amount, balance_amount,
                         remarks, multiple_node_id, submitted_at, submitted_by, created_by)
                     VALUES ("
                    . $this->str($dvNo) . ', ' . $this->str($f['dv_date']) . ', ' . $f['fiscal_yr'] . ', ' . $this->strOrNull($f['expense_class']) . ', ' . (int) $f['status_id'] . ', '
                    . $this->intOrNull($f['requesting_department_id']) . ', ' . $this->strOrNull($f['dept_project']) . ', '
                    . $this->strOrNull($f['fund_source']) . ', '
                    . $this->intOrNull($f['vendor_id']) . ', '
                    . $this->strOrNull($f['claimant_name']) . ', ' . $this->strOrNull($f['claimant_address']) . ', '
                    . $this->strOrNull($f['employee_no_tin']) . ', '
                    . $this->strOrNull($f['particulars']) . ', ' . (float) $f['gross_amount'] . ', ' . (float) $f['net_amount'] . ', '
                    . $this->strOrNull($f['po_number']) . ', ' . $this->numOrNull($f['po_amount']) . ', '
                    . $this->strOrNull($f['account_code']) . ', ' . (float) $f['budget_amount'] . ', ' . (float) $f['obligation_amount'] . ', '
                    . (float) $paymentsPrior . ', ' . (float) $balance . ', '
                    . $this->strOrNull($f['remarks']) . ', ' . $this->strOrNull($f['multiple_node_id']) . ', '
                    . ($submitted ? "CURRENT_TIMESTAMP, {$uid}" : 'NULL, NULL') . ', '
                    . $uid . ')
                     RETURNING dv_id, dv_no',
                    'DB'
                );

                $this->replaceDeductions((int) $row['dv_id'], $f['deductions']);
                if ($f['_payrollPeriodId']) {
                    (new PayrollManagementService())->syncDvLink((int) $row['dv_id'], $f['_payrollPeriodId']);
                }
                if ($f['_remittanceId']) {
                    (new DeductionRemittanceService())->syncDvLink((int) $row['dv_id'], $f['_remittanceId']);
                }
                if ($f['_dvRemit']) {
                    (new RemittanceListService())->syncLink((int) $row['dv_id'], $f['_dvRemit']['period'], $f['_dvRemit']['type'], $userId, $f['_dvRemit']['vendor']);
                }

                $rfaError = $this->linkRfas((int) $row['dv_id'], $f['_rfaRows']);
                if ($rfaError !== null) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => $rfaError];
                }

                $saroError = $this->linkSaro((int) $row['dv_id'], $f['_saroRow']);
                if ($saroError !== null) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => $saroError];
                }

                // Optional ATC Code / Accounting Entries pre-fill — see persistJournalEntry()'s
                // doc comment. A validation failure here rolls back the DV creation too.
                if ($f['atc_lines'] || $f['lines']) {
                    $jevResult = $this->persistJournalEntry((int) $row['dv_id'], $f['atc_lines'], $f['lines'], $userId, false, null, $f['book_id'], $f['jev_classification']);
                    if (!$jevResult['success']) {
                        App\Execute('ROLLBACK', 'DB');

                        return $jevResult;
                    }
                }

                App\Execute('COMMIT', 'DB');
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

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
            $cur  = App\ExecuteRow(
                "SELECT dv.dv_id, st.name AS status FROM {$this->table} dv
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if (!$cur) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            if (!in_array($cur['status'], self::EDITABLE, true)) {
                return ['success' => false, 'message' => "Only DRAFT or REJECTED disbursement vouchers can be edited (current status: {$cur['status']})."];
            }

            $body = $this->withPayrollAmounts($body);   // a payroll DV's Amount / deductions are the import's
            [$errors, $f] = $this->prepare($body);
            if ($f['_payrollPeriodId'] && ($payrollError = (new PayrollManagementService())->validateDvLink($f['_payrollPeriodId'], $dvId)) !== null) {
                $errors[] = $payrollError;
            }
            if ($f['_remittanceId'] && ($remError = (new DeductionRemittanceService())->validateDvLink($f['_remittanceId'], $dvId)) !== null) {
                $errors[] = $remError;
            }
            if ($f['_dvRemit'] && ($dvRemitError = (new RemittanceListService())->validateLink($f['_dvRemit']['period'], $f['_dvRemit']['type'], $dvId, (float) $f['gross_amount'], $f['_dvRemit']['vendor'])) !== null) {
                $errors[] = $dvRemitError;
            }
            [$rfaErrors, $rfaBudget] = $this->checkAmountAgainstRfas($f, $dvId);
            $errors = array_merge($errors, $rfaErrors);
            if ($rfaBudget !== null) {
                $f['budget_amount'] = $f['obligation_amount'] = $rfaBudget;
            }
            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }

            $paymentsPrior = $this->priorPaymentsForRfas(array_column($f['_rfaRows'], 'allocation_id'), $dvId);
            $balance       = round($f['obligation_amount'] - ($paymentsPrior + $f['net_amount']), 2);

            $submitted = $f['status'] === 'FOR_FMD_REVIEW';
            $uid       = $userId > 0 ? (string) $userId : 'NULL';

            App\Execute('BEGIN', 'DB');
            try {
                App\Execute(
                    "UPDATE {$this->table} SET
                        dv_date = " . $this->str($f['dv_date']) . ',
                        fiscal_yr = ' . $f['fiscal_yr'] . ',
                        expense_class = ' . $this->strOrNull($f['expense_class']) . ',
                        requesting_department_id = ' . $this->intOrNull($f['requesting_department_id']) . ',
                        dept_project = ' . $this->strOrNull($f['dept_project']) . ',
                        fund_source = ' . $this->strOrNull($f['fund_source']) . ',
                        vendor_id = ' . $this->intOrNull($f['vendor_id']) . ',
                        claimant_name = ' . $this->strOrNull($f['claimant_name']) . ',
                        claimant_address = ' . $this->strOrNull($f['claimant_address']) . ',
                        employee_no_tin = ' . $this->strOrNull($f['employee_no_tin']) . ',
                        particulars = ' . $this->strOrNull($f['particulars']) . ',
                        gross_amount = ' . (float) $f['gross_amount'] . ',
                        net_amount = ' . (float) $f['net_amount'] . ',
                        po_number = ' . $this->strOrNull($f['po_number']) . ',
                        po_amount = ' . $this->numOrNull($f['po_amount']) . ',
                        account_code = ' . $this->strOrNull($f['account_code']) . ',
                        budget_amount = ' . (float) $f['budget_amount'] . ',
                        obligation_amount = ' . (float) $f['obligation_amount'] . ',
                        payments_prior_amount = ' . (float) $paymentsPrior . ',
                        balance_amount = ' . (float) $balance . ',
                        remarks = ' . $this->strOrNull($f['remarks']) . ',
                        multiple_node_id = ' . $this->strOrNull($f['multiple_node_id']) . ',
                        status_id = ' . (int) $f['status_id'] . ',
                        submitted_at = ' . ($submitted ? 'CURRENT_TIMESTAMP' : 'NULL') . ',
                        submitted_by = ' . ($submitted ? $uid : 'NULL') . ',
                        rejected_at = NULL, rejected_by = NULL, rejection_reason = NULL,
                        updated_by = ' . $uid . ",
                        updated_at = CURRENT_TIMESTAMP
                     WHERE dv_id = {$dvId}",
                    'DB'
                );

                $this->replaceDeductions($dvId, $f['deductions']);
                (new PayrollManagementService())->syncDvLink($dvId, $f['_payrollPeriodId']); // also releases a month no longer picked
                (new DeductionRemittanceService())->syncDvLink($dvId, $f['_remittanceId']);
                (new RemittanceListService())->syncLink($dvId, $f['_dvRemit']['period'] ?? null, $f['_dvRemit']['type'] ?? null, $userId, $f['_dvRemit']['vendor'] ?? null);

                $rfaError = $this->linkRfas($dvId, $f['_rfaRows']);
                if ($rfaError !== null) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => $rfaError];
                }

                $saroError = $this->linkSaro($dvId, $f['_saroRow']);
                if ($saroError !== null) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => $saroError];
                }

                // Optional ATC Code / Accounting Entries pre-fill — see persistJournalEntry()'s
                // doc comment. A validation failure here rolls back the whole update too.
                if ($f['atc_lines'] || $f['lines']) {
                    $jevResult = $this->persistJournalEntry($dvId, $f['atc_lines'], $f['lines'], $userId, false, null, $f['book_id'], $f['jev_classification']);
                    if (!$jevResult['success']) {
                        App\Execute('ROLLBACK', 'DB');

                        return $jevResult;
                    }
                } else {
                    // The form was saved with every ATC row removed (and no entry lines to re-save): the full
                    // replace must clear the ATC rows stored earlier, or they would linger and later reduce the
                    // net again. net_amount/balance_amount above were already computed without any withholding.
                    App\Execute("DELETE FROM {$this->tableDvAtcLines} WHERE dv_id = {$dvId}", 'DB');
                }

                App\Execute('COMMIT', 'DB');
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => 'Disbursement Voucher updated successfully.', 'data' => ['dv_id' => $dvId, 'status' => $f['status']]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::update ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update disbursement voucher.'];
        }
    }

    /**
     * Row-level visibility for LISTING / READING vouchers: null = the user sees every voucher, otherwise a SQL predicate on the
     * voucher aliased `dv` that keeps only what they may see.
     *
     * Superadmin and the offices that work the pipeline (STAGE_RC_LABEL: FMD, Accounting, Treasury — each holds vouchers created by
     * other offices, so hiding those would stop them doing their review) see everything. Any other office is Originating: it sees only
     * the vouchers created by a user of its own RC or of an RC under it (GlobalService::getRcIdsInScope(), so a regional office also
     * sees its district offices'). A user with no RC is unrestricted, same convention as GlobalService::getUserRcScope().
     */
    private function visibilitySql(): ?string
    {
        $scope = GlobalService::getUserRcScope();
        $rcId  = $scope['rc_id'] ?? null;
        if ($scope['is_admin'] || $rcId === null || trim((string) $rcId) === '' || array_key_exists((string) $rcId, self::STAGE_RC_LABEL)) {
            return null;
        }
        $rcIds = GlobalService::getRcIdsInScope((string) $rcId) ?: [(string) $rcId];
        $in    = implode(', ', array_map(fn ($id) => $this->str($id), $rcIds));

        return "dv.created_by IN (SELECT u.user_id FROM {$this->tableUsers} u WHERE u.rc_id IN ({$in}))";
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
     * return. Superadmin always passes (GlobalService::getUserRcScope()['is_admin'] bypass,
     * project-wide convention — see this class's docblock). Whoever holds $cur['status']
     * (STAGE_HOLDER_RC) may move it forward or reject it; CANCELLED is allowed by that same
     * holder OR the voucher's own creator. DRAFT (Originating holds it, no entry in
     * STAGE_HOLDER_RC) is open to anyone authenticated for both submitting and cancelling their own
     * draft — "their own" is enforced by the creator check in the CANCELLED branch below.
     */
    private function authorizeTransition(array $cur, string $new): ?string
    {
        // Whoever already approved/forwarded an earlier step of THIS voucher can't reject it later — not even an admin
        // (the admin bypass below is for moving it forward; rejecting your own approved work isn't a review).
        if ($new === 'REJECTED') {
            $uid = (int) App\CurrentUserID();
            if ($uid > 0 && in_array($uid, [(int) ($cur['fmd_approved_by'] ?? 0), (int) ($cur['accounting_processed_by'] ?? 0), (int) ($cur['division_reviewed_by'] ?? 0)], true)) {
                return 'You already approved this voucher at an earlier step — it must be rejected by a different user.';
            }
        }
        if (GlobalService::getUserRcScope()['is_admin']) {
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
        if ($userRc !== $holderRc) {
            return "Only {$label} staff can do that.";
        }

        // Separation of duties inside Accounting: the three steps (Section Chief -> Division Manager ->
        // Department Manager) are all RC 012, so the office check above alone would let the same person
        // push a voucher through every step. A user who already acted on an earlier Accounting step of
        // THIS voucher can't take a later one (rejecting/cancelling is unaffected — handled above/below).
        $uid = (int) App\CurrentUserID();
        if ($new === 'FOR_DEPARTMENT_MANAGER' && $uid > 0 && (int) ($cur['accounting_processed_by'] ?? 0) === $uid) {
            return 'You already processed this voucher as Section Chief — the Division Manager step must be done by a different user.';
        }
        if ($new === 'FOR_TREASURY' && $uid > 0
            && in_array($uid, [(int) ($cur['accounting_processed_by'] ?? 0), (int) ($cur['division_reviewed_by'] ?? 0)], true)) {
            return 'You already acted on this voucher at an earlier Accounting step — the Department Manager approval must be done by a different user.';
        }

        return null;
    }

    /** Moves a DV along TRANSITIONS. REJECTED needs a reason; $notes is stored as rejection_reason. */
    public function changeStatus(int|string $id, string $newStatus, int $userId, ?string $notes = null): array
    {
        try {
            $dvId = (int) $id;
            $new  = strtoupper(trim($newStatus));
            $cur  = App\ExecuteRow(
                "SELECT dv.dv_id, dv.status_id, st.name AS status, dv.created_by, dv.fmd_approved_by, dv.accounting_processed_by, dv.division_reviewed_by FROM {$this->table} dv
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if (!$cur) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            $newId = $this->getStatusIdByName($new);
            if ($newId === null) {
                return ['success' => false, 'message' => "Status '{$new}' is not configured in fims.settings_status for disbursement vouchers."];
            }

            $allowed = self::TRANSITIONS[$cur['status']] ?? [];
            if (!in_array($new, $allowed, true)) {
                return ['success' => false, 'message' => "Cannot change a {$cur['status']} disbursement voucher to {$new}."];
            }
            $authError = $this->authorizeTransition($cur, $new);
            if ($authError !== null) {
                return ['success' => false, 'message' => $authError, 'httpCode' => 403];
            }
            $jev = null;
            if ($new === 'FOR_DIVISION_MANAGER') {
                // The Accounting Section Chief must have saved a balanced JEV at the FOR_ACCOUNTING
                // step before the voucher can move up the Accounting review chain (Division Manager
                // -> Department Manager). Checked here, where it leaves the Section Chief's hands.
                $jev = $this->fetchJev($dvId);
                if (!$jev || (float) $jev['total_debit'] <= 0 || (float) $jev['total_debit'] !== (float) $jev['total_credit']) {
                    return ['success' => false, 'message' => 'Accounting must save a balanced Journal Entry (debits = credits) before this voucher can move to Division Manager review.'];
                }
                // A payroll/remittance DV's entries are pre-filled by Originating without a Book — see
                // persistJournalEntry() — so it can only be missing here if Accounting never saved.
                if (empty($jev['book_id'])) {
                    return ['success' => false, 'message' => 'Accounting must choose a Book on the Journal Entry and save it before this voucher can move to Division Manager review.'];
                }
            } elseif ($new === 'FOR_TREASURY') {
                // Department Manager's final approval releases the voucher to Treasury and posts the
                // JEV to the General Ledger (see the postJev() call below) — re-check it's still balanced.
                $jev = $this->fetchJev($dvId);
                if (!$jev || (float) $jev['total_debit'] <= 0 || (float) $jev['total_debit'] !== (float) $jev['total_credit']) {
                    return ['success' => false, 'message' => 'A balanced Journal Entry (debits = credits) is required before this voucher can go to Treasury.'];
                }
                if (empty($jev['book_id'])) {
                    return ['success' => false, 'message' => 'The Journal Entry has no Book — Accounting must choose one before this voucher can go to Treasury.'];
                }
            } elseif ($new === 'CANCELLED' && $cur['status'] === 'FOR_TREASURY') {
                // Only this one path can cancel a DV whose JEV was already posted to the General
                // Ledger (FOR_ACCOUNTING -> FOR_TREASURY posts it, see below) — RELEASED is
                // terminal, so there's no later point a posted JEV could still be cancelled from.
                $jev = $this->fetchJev($dvId);
            }
            $notes = trim((string) $notes);
            if ($new === 'REJECTED' && $notes === '') {
                return ['success' => false, 'message' => 'A reason is required when rejecting a disbursement voucher.'];
            }

            $uid = $userId > 0 ? (string) $userId : 'NULL';
            $set = ["status_id = {$newId}", 'updated_at = CURRENT_TIMESTAMP', "updated_by = {$uid}"];
            if ($new === 'FOR_FMD_REVIEW') {
                $set[] = "submitted_at = CURRENT_TIMESTAMP, submitted_by = {$uid}";
                $set[] = 'rejected_at = NULL, rejected_by = NULL, rejection_reason = NULL';
            } elseif ($new === 'FOR_ACCOUNTING') {
                $set[] = "fmd_approved_at = CURRENT_TIMESTAMP, fmd_approved_by = {$uid}";
            } elseif ($new === 'FOR_DIVISION_MANAGER') {
                // Accounting Section Chief finished processing the DV (and its JEV) and forwarded it on.
                $set[] = "accounting_processed_at = CURRENT_TIMESTAMP, accounting_processed_by = {$uid}";
            } elseif ($new === 'FOR_DEPARTMENT_MANAGER') {
                $set[] = "division_reviewed_at = CURRENT_TIMESTAMP, division_reviewed_by = {$uid}";
            } elseif ($new === 'FOR_TREASURY') {
                $set[] = "department_approved_at = CURRENT_TIMESTAMP, department_approved_by = {$uid}";
            } elseif ($new === 'RELEASED') {
                $set[] = "treasury_released_at = CURRENT_TIMESTAMP, treasury_released_by = {$uid}";
            } elseif ($new === 'REJECTED') {
                $set[] = "rejected_at = CURRENT_TIMESTAMP, rejected_by = {$uid}, rejection_reason = " . $this->str($notes);
            }

            App\Execute('BEGIN', 'DB');
            try {
                // Guard on the status we read so two concurrent actions can't both win.
                App\Execute(
                    "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE dv_id = {$dvId} AND status_id = " . (int) $cur['status_id'],
                    'DB'
                );
                $now = App\ExecuteScalar("SELECT status_id FROM {$this->table} WHERE dv_id = {$dvId}", 'DB');
                if ((int) $now !== $newId) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => 'The disbursement voucher was changed by someone else. Please reload and try again.'];
                }

                // Approve/post the JEV to the General Ledger in the same transaction as the DV's
                // own status move — "DV approved on the Accounting side" and "JEV posted" are one
                // atomic step, not two separate actions a caller could do half of.
                if ($new === 'FOR_TREASURY' && $jev) {
                    $glResult = (new GeneralLedgerService())->postJev((int) $jev['jev_id'], $userId);
                    if (!$glResult['success']) {
                        App\Execute('ROLLBACK', 'DB');

                        return $glResult;
                    }
                } elseif ($new === 'CANCELLED' && $jev) {
                    $glResult = (new GeneralLedgerService())->reverseJev((int) $jev['jev_id'], $userId);
                    if (!$glResult['success']) {
                        App\Execute('ROLLBACK', 'DB');

                        return $glResult;
                    }
                }
                if ($new === 'CANCELLED') {
                    (new PayrollManagementService())->releaseDv($dvId); // a payroll month this DV paid can get a new DV
                    (new DeductionRemittanceService())->releaseDv($dvId); // a remittance this DV paid is Scheduled again
                    (new RemittanceListService())->releaseDv($dvId); // a voucher's EWT / VAT / Penalty Tax this DV paid is "Ready to remit" again
                }

                App\Execute('COMMIT', 'DB');
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
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
            $cur  = App\ExecuteRow(
                "SELECT dv.dv_no, st.name AS status FROM {$this->table} dv
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if (!$cur) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            if (!in_array($cur['status'], self::DELETABLE, true)) {
                return ['success' => false, 'message' => "Only DRAFT or CANCELLED disbursement vouchers can be deleted (current status: {$cur['status']})."];
            }

            // A CANCELLED voucher is normally deletable, but NOT if its JEV was ever posted to the
            // General Ledger (GeneralLedgerService::postJev(), on an earlier FOR_ACCOUNTING ->
            // FOR_TREASURY — the only way a CANCELLED voucher gets here is FOR_TREASURY ->
            // CANCELLED, see TRANSITIONS) — fims.general_ledger.jev_id/jev_line_id have no ON
            // DELETE CASCADE (posted ledger rows are never deleted, only reversed — see
            // GeneralLedgerService's docblock), so the DELETE below would otherwise fail on a raw
            // FK violation with no useful message. Check up front instead, same reasoning as the
            // status check above.
            $jevStatus = App\ExecuteScalar(
                "SELECT st.name FROM {$this->tableJev} jev
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.dv_id = {$dvId}",
                'DB'
            );
            if (in_array($jevStatus, ['POSTED', 'REVERSED'], true)) {
                return ['success' => false, 'message' => "This voucher's Journal Entry Voucher has already been posted to the General Ledger and cannot be deleted — posted ledger entries are never removed, only reversed. See the General Ledger / Journals for its posted/reversing entries."];
            }

            // Release any RFA still linked to this voucher (budget_allocation.dv_id) BEFORE deleting
            // it — otherwise dv_id would dangle, and rfaAvailableSql()'s "my cancelled/rejected DV
            // doesn't block the RFA" check can't match a dv_id whose row no longer exists at all.
            App\Execute("UPDATE {$this->tableRfa} SET dv_id = NULL WHERE dv_id = {$dvId}", 'DB');
            (new PayrollManagementService())->releaseDv($dvId); // same for a payroll month this DV paid
            (new DeductionRemittanceService())->releaseDv($dvId); // and a payroll remittance it paid
            (new RemittanceListService())->releaseDv($dvId); // and the voucher deductions it remitted
            App\Execute("DELETE FROM {$this->table} WHERE dv_id = {$dvId}", 'DB');

            return ['success' => true, 'message' => "Disbursement Voucher {$cur['dv_no']} deleted successfully."];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::delete ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to delete disbursement voucher.'];
        }
    }
}
