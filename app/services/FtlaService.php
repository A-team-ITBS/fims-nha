<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Fund Transfer Letter of Advice (FTLA) — fims.fund_transfers (+ the shared fims.status_change_log).
 *
 * Standalone port of REGIONAL's treasury FTLAService, trimmed to what exists in the
 * `fims` schema — same spirit as PurchaseOrderService. Deliberately NOT ported: the
 * generic workflow engine (workflow.workflow_steps / workflow_statuses / workflow_instances),
 * department/role-based actor gating (DepartmentFilterHelper), and cash-ledger
 * debit/credit side effects (Treasury's treasury.cash_position adjustments on
 * VALIDATED/APPROVED) — fims has no equivalent cash ledger yet.
 *
 * Account shape merges Treasury's three FTLA pages (ftla-preparation.php's single-to
 * + optional multi-from, ftlafundtransfer.php's paired multi-from/multi-to, and
 * ftlasettlement.php's multi-from + single-to) into one: always a single
 * to_bank_account_id destination, plus from_accounts — [] (legacy/no declared
 * source), one row, or several rows whose amounts sum to transfer_amount. See
 * normalizeFromAccounts() and form/ftla-form.php's "Multiple debit accounts" checkbox.
 *
 * Status is a plain column moved by update() along self::NEXT — no role system yet,
 * so (unlike PurchaseOrderService's SUBMITTED/APPROVED/REJECTED actor split) any
 * authenticated user can advance any transition; fetchWorkflowSteps() reflects that
 * with is_preparer always true. Replace with real actor gating once FTLA has one.
 */
class FtlaService
{
    private string $table          = 'fims.fund_transfers';
    private string $tableBank      = 'fims.bank_accounts';
    private string $tableUsers     = 'uac.users';
    private string $tableDv        = 'fims.disbursement_vouchers';
    private string $tableDvStatus  = 'fims.settings_status';

    /** This module's row in fims.status_change_log.status_for — see GlobalService::logStatusChange(). */
    private const STATUS_LOG_FOR = 'fund_transfers';

    /**
     * value => label — the full Treasury vocabulary (ftla-preparation.php,
     * ftlafundtransfer.php, ftlasettlement.php combined), since this module merges
     * all three of their account shapes into one form. See getTransferTypes().
     */
    private const TRANSFER_TYPES = [
        'REGIONAL_FUND_TRANSFER'  => 'Regional Fund Transfer',
        'INTER_ACCOUNT'           => 'Inter-Account Transfer',
        'ERRONEOUS_CHARGEABILITY' => 'Erroneous Chargeability',
        'ERRONEOUS_DEPOSIT'       => 'Erroneous Deposit',
        'REMITTANCE_ECHMLP'       => 'Remittance - ECHMLP',
        'REMITTANCE_HLA'          => 'Remittance - HLA',
        'SUBSIDY_TRANSFER'        => 'Subsidy Transfer',
        'REVERSAL'                => 'Reversal',
        'GENERAL_FUND'            => 'General Fund',
        'TEMPORARY'               => 'Temporary',
        'CROSS_ACCESS'            => 'Cross Access',
        'POOL_MOVEMENT'           => 'Pool Movement',
        'REVERSION'               => 'Reversion',
        'SETTLEMENT_TO_MAIN'      => 'Settlement to Main Depository',
    ];

    /** value => [name, color] — color matches the client's _statusBadge() COLOR_MAP. */
    private const STATUSES = [
        'DRAFT'                   => ['Draft', 'secondary'],
        'FOR_RM_APPROVAL'         => ['For RM Approval', 'warning'],
        'FOR_REVIEW'              => ['For Review', 'warning'],
        'FOR_APPROVAL'            => ['For Approval', 'warning'],
        'APPROVED'                => ['Approved', 'primary'],
        'TRANSMITTED'             => ['Transmitted to Bank', 'info'],
        'VALIDATED'               => ['Validated (Bank Confirmed)', 'success'],
        'SUBMITTED_TO_ACCOUNTING' => ['Submitted to Accounting', 'success'],
        'COMPLETED'               => ['Completed (With Validated FTLA)', 'success'],
        'CANCELLED'               => ['Cancelled', 'danger'],
    ];

    /** Linear progression; DRAFT may also go straight to FOR_REVIEW (RM approval is optional). */
    private const NEXT = [
        'DRAFT'                   => ['FOR_REVIEW', 'FOR_RM_APPROVAL', 'CANCELLED'],
        'FOR_RM_APPROVAL'         => ['FOR_REVIEW'],
        'FOR_REVIEW'              => ['FOR_APPROVAL', 'DRAFT'],
        'FOR_APPROVAL'            => ['APPROVED', 'FOR_REVIEW'],
        'APPROVED'                => ['TRANSMITTED'],
        'TRANSMITTED'             => ['VALIDATED'],
        'VALIDATED'               => ['SUBMITTED_TO_ACCOUNTING'],
        'SUBMITTED_TO_ACCOUNTING' => ['COMPLETED'],
        'COMPLETED'               => [],
        'CANCELLED'               => [],
    ];

    /** A transition back a step, or to CANCELLED, needs a reason. */
    private const REMARKS_REQUIRED = [
        'FOR_REVIEW->DRAFT', 'FOR_APPROVAL->FOR_REVIEW',
        'DRAFT->CANCELLED', 'FOR_RM_APPROVAL->CANCELLED', 'FOR_REVIEW->CANCELLED', 'FOR_APPROVAL->CANCELLED',
    ];

    /** The status's own timestamp column, set to NOW() the moment a record moves into it. */
    private const STATUS_TIMESTAMP_COLUMN = [
        'FOR_RM_APPROVAL'         => 'rm_submitted_at',
        'FOR_REVIEW'              => 'submitted_at',
        'FOR_APPROVAL'            => 'reviewed_at',
        'APPROVED'                => 'approved_date',
        'TRANSMITTED'             => 'transmitted_at',
        'VALIDATED'               => 'validated_date',
        'SUBMITTED_TO_ACCOUNTING' => 'accounting_submission_date',
        'COMPLETED'               => 'completed_at',
    ];

    /** Only a DRAFT record can still be freely edited (matches the client's ftla_editableStatuses). */
    private const EDITABLE = ['DRAFT'];

    /** body key => fims.fund_transfers column, for update()'s "touch only what's present" SET builder. */
    private const PLAIN_STRING_FIELDS = [
        'transfer_type', 'request_reference', 'for_name', 'for_title', 'thru_name', 'thru_title',
        'subject', 'signatory_name_memo', 'signatory_position_memo', 'remarks',
    ];
    private const INT_FIELDS     = ['laa_id', 'rfa_id', 'fund_source_id', 'reviewed_by', 'approved_by', 'prepared_by'];
    private const BOOL_FIELDS    = ['is_temporary'];
    private const DATE_FIELDS    = ['transfer_date', 'repayment_due_date'];
    private const JSON_FIELDS    = ['supporting_documents', 'signatories', 'bank_officers'];

    // ── small SQL helpers (same shape as PurchaseOrderService's) ──

    /**
     * Quotes a string for a plain Postgres '...' literal directly — NOT via the
     * shared App\QuotedValue(STRING), whose AdjustSql() escapes "\n"/"\r"/"\t" the
     * MySQL way (backslash + letter). Postgres' standard_conforming_strings (on by
     * default) does not interpret backslash escapes in a plain literal, so that
     * turns a real newline into the two literal characters "\n" on save — visible
     * here as literal "\n" in a multi-line dv_numbers/purpose/remarks value once
     * printed. Only a single quote needs doubling; every other character, newlines
     * included, is stored byte-for-byte as given.
     */
    private function str(mixed $v): string
    {
        return "'" . str_replace("'", "''", trim((string) $v)) . "'";
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function intOrNull(mixed $v): string
    {
        return ($v === null || $v === '' || !is_numeric($v)) ? 'NULL' : (string) (int) $v;
    }

    private function decimal(mixed $v): string
    {
        return is_numeric($v) ? (string) round((float) $v, 2) : '0';
    }

    private function bool(mixed $v): string
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE';
    }

    private function validDate(mixed $v): bool
    {
        if (!is_string($v) || $v === '') {
            return false;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $v);

        return $d !== false && $d->format('Y-m-d') === $v;
    }

    private function dateOrNull(mixed $v): string
    {
        $v = is_string($v) ? substr(trim($v), 0, 10) : '';

        return $this->validDate($v) ? $this->str($v) : 'NULL';
    }

    /** $v may already be a JSON string (from JSON.stringify on the client) or a PHP array. */
    private function jsonb(mixed $v, string $default): string
    {
        if (is_array($v)) {
            return $this->str(json_encode($v)) . '::jsonb';
        }
        $v = trim((string) ($v ?? ''));
        if ($v === '' || json_decode($v) === null && $v !== 'null') {
            $v = $default;
        }

        return $this->str($v) . '::jsonb';
    }

    private function fileService(): FileUploadServices
    {
        return new FileUploadServices();
    }

    /**
     * Normalizes the "multiple debit accounts" payload (Treasury's from_accounts
     * shape, trimmed to {bank_account_id, amount}): drops rows missing either
     * field, rounds amount to 2dp. Returns [] for a legacy single-account
     * transfer that never declared a source account.
     */
    private function normalizeFromAccounts(mixed $raw): array
    {
        $rows = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $out  = [];
        foreach ($rows as $r) {
            $bankId = (int) ($r['bank_account_id'] ?? 0);
            $amount = is_numeric($r['amount'] ?? null) ? round((float) $r['amount'], 2) : 0.0;
            if ($bankId > 0 && $amount > 0) {
                $out[] = ['bank_account_id' => $bankId, 'amount' => $amount];
            }
        }

        return $out;
    }

    /**
     * Normalizes a plain list of bank account ids (Treasury's to_accounts shape
     * when paired with from_accounts — ftlafundtransfer.php's "pairs" layout, no
     * per-row amount of its own; the pair's amount lives on the from_accounts row
     * at the same index). Drops non-positive/missing ids.
     */
    private function normalizeAccountIds(mixed $raw): array
    {
        $rows = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $out  = [];
        foreach ($rows as $r) {
            $bankId = (int) (is_array($r) ? ($r['bank_account_id'] ?? 0) : $r);
            if ($bankId > 0) {
                $out[] = ['bank_account_id' => $bankId];
            }
        }

        return $out;
    }

    // ── lookups ──

    public function getTransferTypes(): array
    {
        $data = [];
        foreach (self::TRANSFER_TYPES as $value => $label) {
            $data[] = ['value' => $value, 'label' => $label];
        }

        return ['success' => true, 'data' => $data];
    }

    public function getBankAccounts(bool $all = false): array
    {
        try {
            $where = $all ? '1=1' : 'is_active = TRUE';
            $rows  = App\ExecuteRows(
                "SELECT bank_account_id, account_name, account_number, bank_name, office_type, department_type
                 FROM {$this->tableBank} WHERE {$where} ORDER BY bank_name, account_name",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('FtlaService::getBankAccounts ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load bank accounts.'];
        }
    }

    public function getKpis(): array
    {
        try {
            $row = App\ExecuteRow(
                "SELECT
                    COUNT(*) FILTER (WHERE status = 'DRAFT')       AS draft,
                    COUNT(*) FILTER (WHERE status = 'FOR_REVIEW')  AS for_review,
                    COUNT(*) FILTER (WHERE status = 'FOR_APPROVAL') AS for_approval,
                    COALESCE(SUM(transfer_amount) FILTER (WHERE status != 'CANCELLED'), 0) AS total_amount
                 FROM {$this->table}",
                'DB'
            ) ?: ['draft' => 0, 'for_review' => 0, 'for_approval' => 0, 'total_amount' => 0];

            return ['success' => true, 'data' => $row];
        } catch (\Exception $e) {
            App\LogError('FtlaService::getKpis ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load KPIs.'];
        }
    }

    /** data + a top-level total (the client reads res.total directly, not res.pagination.total). */
    public function getList(array $params): array
    {
        try {
            $where = ['1=1'];
            if (!empty($params['status'])) {
                $where[] = 'ft.status = ' . $this->str(strtoupper((string) $params['status']));
            }
            if (!empty($params['bank_id'])) {
                $where[] = 'ft.to_bank_account_id = ' . (int) $params['bank_id'];
            }
            if (!empty($params['month']) && preg_match('/^\d{4}-\d{2}$/', (string) $params['month'])) {
                $where[] = "to_char(ft.transfer_date, 'YYYY-MM') = " . $this->str($params['month']);
            }
            if (!empty($params['date_from']) && $this->validDate((string) $params['date_from'])) {
                $where[] = 'ft.transfer_date >= ' . $this->str($params['date_from']);
            }
            if (!empty($params['date_to']) && $this->validDate((string) $params['date_to'])) {
                $where[] = 'ft.transfer_date <= ' . $this->str($params['date_to']);
            }
            if (!empty($params['search'])) {
                $kw      = App\QuotedValue('%' . $params['search'] . '%', App\DataType::STRING, 'DB');
                $where[] = "(ft.ftla_number ILIKE {$kw} OR ft.purpose ILIKE {$kw})";
            }
            $whereSql = implode(' AND ', $where);

            $select = "SELECT ft.*, b.bank_name AS to_bank_name, b.account_number AS to_account_number, b.account_name AS to_account_name
                       FROM {$this->table} ft
                       LEFT JOIN {$this->tableBank} b ON b.bank_account_id = ft.to_bank_account_id
                       WHERE {$whereSql}
                       ORDER BY ft.created_at DESC, ft.fund_transfer_id DESC";

            $page  = max(1, (int) ($params['page'] ?? 1));
            $limit = max(1, (int) ($params['page_size'] ?? 15));
            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} ft WHERE {$whereSql}", 'DB');
            $rows  = App\ExecuteRows($select . ' LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit), 'DB') ?: [];

            return ['success' => true, 'data' => $rows, 'total' => $total];
        } catch (\Exception $e) {
            App\LogError('FtlaService::getList ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load FTLA records.'];
        }
    }

    public function getRecord(int $id): array
    {
        $row = App\ExecuteRow(
            "SELECT ft.*,
                    b.bank_name AS to_bank_name, b.account_number AS to_account_number, b.account_name AS to_account_name,
                    fb.bank_name AS from_bank_name, fb.account_number AS from_account_number, fb.account_name AS from_account_name,
                    TRIM(CONCAT_WS(' ', pu.first_name, pu.last_name)) AS prepared_by_name,
                    TRIM(CONCAT_WS(' ', ru.first_name, ru.last_name)) AS reviewed_by_name,
                    TRIM(CONCAT_WS(' ', au.first_name, au.last_name)) AS approved_by_name
             FROM {$this->table} ft
             LEFT JOIN {$this->tableBank}  b  ON b.bank_account_id = ft.to_bank_account_id
             LEFT JOIN {$this->tableBank}  fb ON fb.bank_account_id = ft.from_bank_account_id
             LEFT JOIN {$this->tableUsers} pu ON pu.user_id = ft.prepared_by
             LEFT JOIN {$this->tableUsers} ru ON ru.user_id = ft.reviewed_by
             LEFT JOIN {$this->tableUsers} au ON au.user_id = ft.approved_by
             WHERE ft.fund_transfer_id = {$id}",
            'DB'
        );
        if (!$row) {
            return ['success' => false, 'message' => "FTLA #{$id} not found.", 'httpCode' => 404];
        }

        $row['attachments']          = $this->_resolveAttachmentUrls($row['attachments'] ?? '[]');
        $row['supporting_documents'] = json_decode($row['supporting_documents'] ?? '[]', true) ?: [];
        $row['signatories']          = json_decode($row['signatories'] ?? '{}', true) ?: [];
        $row['bank_officers']        = json_decode($row['bank_officers'] ?? '{}', true) ?: [];
        $row['from_accounts']        = $this->_resolveFromAccountNames($row['from_accounts'] ?? '[]');
        $row['to_accounts']          = $this->_resolveFromAccountNames($row['to_accounts'] ?? '[]');
        $row['is_paired']            = count($row['from_accounts']) > 1 && count($row['to_accounts']) === count($row['from_accounts']);
        $row['is_editable']          = in_array($row['status'], self::EDITABLE, true);

        return ['success' => true, 'data' => $row];
    }

    /** Each {bank_account_id, amount} row + its bank_name/account_name/account_number, for the readone/print views. */
    private function _resolveFromAccountNames(mixed $raw): array
    {
        $rows = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        if (!$rows) {
            return [];
        }
        $ids    = array_unique(array_map(static fn ($r) => (int) ($r['bank_account_id'] ?? 0), $rows));
        $banks  = App\ExecuteRows(
            "SELECT bank_account_id, bank_name, account_name, account_number FROM {$this->tableBank}
             WHERE bank_account_id IN (" . implode(',', array_map('intval', $ids)) . ')',
            'DB'
        ) ?: [];
        $byId = [];
        foreach ($banks as $b) {
            $byId[(int) $b['bank_account_id']] = $b;
        }

        return array_map(static function (array $r) use ($byId) {
            $b = $byId[(int) ($r['bank_account_id'] ?? 0)] ?? [];

            return $r + [
                'bank_name'      => $b['bank_name'] ?? null,
                'account_name'   => $b['account_name'] ?? null,
                'account_number' => $b['account_number'] ?? null,
            ];
        }, $rows);
    }

    /** Refreshes each attachment's signed url (durable object key lives in "link"). */
    private function _resolveAttachmentUrls(mixed $raw): array
    {
        $list = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $svc  = $this->fileService();

        return array_map(static function (array $a) use ($svc) {
            if (!empty($a['link'])) {
                $a['url'] = $svc->viewUrlFor($a['link']) ?? ($a['url'] ?? null);
            }

            return $a;
        }, $list);
    }

    // ── number generator (same PREFIX-YYYY-MM-#### convention as PurchaseOrderService) ──

    private function nextFtlaNumber(string $transferDate): string
    {
        $prefix = 'FTLA-' . substr($transferDate, 0, 4) . '-' . substr($transferDate, 5, 2) . '-';
        $max    = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(ftla_number FROM '[0-9]+$') AS INTEGER)), 0)
             FROM {$this->table}
             WHERE ftla_number LIKE " . App\QuotedValue($prefix . '%', App\DataType::STRING, 'DB') . " AND ftla_number ~ '-[0-9]+$'",
            'DB'
        );

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    // ── create / update ──

    public function create(array $body, int $userId): array
    {
        try {
            $transferDate = trim((string) ($body['transfer_date'] ?? ''));
            $transferType = trim((string) ($body['transfer_type'] ?? ''));
            $amount       = $body['transfer_amount'] ?? null;
            $purpose      = trim((string) ($body['purpose'] ?? ''));

            // "Multiple debit accounts" (Treasury's ftla-preparation.php checkbox) and/or
            // "multiple credit accounts" (Treasury's ftlafundtransfer.php paired FROM/TO
            // rows). transfer_amount is always the from_accounts sum when rows are given —
            // never the client-entered total — same principle as Treasury's FtlaFundTransferService.
            $fromAccounts = $this->normalizeFromAccounts($body['from_accounts'] ?? []);
            $toAccountIds = $this->normalizeAccountIds($body['to_accounts'] ?? []);
            $paired       = count($toAccountIds) > 1;

            $errors = [];
            if ($paired && count($toAccountIds) !== count($fromAccounts)) {
                $errors[] = 'Paired debit/credit accounts must have the same number of rows.';
            }

            if ($paired && !$errors) {
                foreach ($fromAccounts as $i => $r) {
                    if ($r['bank_account_id'] === $toAccountIds[$i]['bank_account_id']) {
                        $errors[] = 'Row ' . ($i + 1) . ": the debit and credit account can't be the same.";
                        break;
                    }
                }
                $amount     = array_sum(array_column($fromAccounts, 'amount'));
                $toBankId   = (string) $toAccountIds[0]['bank_account_id'];
                $fromBankId = $fromAccounts[0]['bank_account_id'];
            } else {
                $toBankId = $this->intOrNull(
                    $toAccountIds ? $toAccountIds[0]['bank_account_id'] : ($body['to_bank_account_id'] ?? null)
                );
                if ($fromAccounts) {
                    $amount = array_sum(array_column($fromAccounts, 'amount'));
                }
                $fromBankId = $fromAccounts
                    ? $fromAccounts[0]['bank_account_id']
                    : (!empty($body['from_bank_account_id']) ? (int) $body['from_bank_account_id'] : null);

                foreach ($fromAccounts as $r) {
                    if ($toBankId !== 'NULL' && $r['bank_account_id'] === (int) $toBankId) {
                        $errors[] = 'A debit (source) account cannot be the same as the destination account.';
                        break;
                    }
                }
            }

            if (!$this->validDate($transferDate)) {
                $errors[] = 'Transfer Date is required and must be a valid date (YYYY-MM-DD).';
            }
            if ($transferType === '') {
                $errors[] = 'Transfer Type is required.';
            }
            if (!is_numeric($amount) || (float) $amount <= 0) {
                $errors[] = 'Transfer Amount must be greater than zero.';
            }
            if ($purpose === '') {
                $errors[] = 'Purpose / Particulars is required.';
            }
            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }

            $statusIn = strtoupper(trim((string) ($body['status'] ?? '')));
            // "Save Draft" sends status=DRAFT explicitly; "Submit" sends no status at all,
            // which defaults to the first real step after DRAFT (no FOR_RM_APPROVAL gating
            // without real org data to route it through — see the class doc comment).
            $status = ($statusIn === 'DRAFT' || $statusIn === 'CANCELLED') ? $statusIn : 'FOR_REVIEW';

            $uid        = $userId > 0 ? (string) $userId : 'NULL';
            $preparedBy = !empty($body['prepared_by']) ? $this->intOrNull($body['prepared_by']) : $uid;

            // ftla_number is normally auto-generated (PREFIX-YYYY-MM-####,
            // same convention as PurchaseOrderService) — but a manually
            // typed one is honored instead when given (e.g. to match a
            // paper FTLA number already issued before this system existed).
            // ftla_number has a UNIQUE constraint on the table, so a
            // duplicate manual entry is checked here first for a clean
            // error instead of an opaque DB constraint failure.
            $ftlaNumber = trim((string) ($body['ftla_number'] ?? ''));
            if ($ftlaNumber !== '') {
                $dupe = App\ExecuteRow(
                    "SELECT fund_transfer_id FROM {$this->table} WHERE ftla_number = " . $this->str($ftlaNumber),
                    'DB'
                );
                if ($dupe) {
                    return ['success' => false, 'message' => "FTLA number \"{$ftlaNumber}\" is already in use."];
                }
            } else {
                $ftlaNumber = $this->nextFtlaNumber($transferDate);
            }

            $timestampCol = self::STATUS_TIMESTAMP_COLUMN[$status] ?? null;
            $extraCol     = $timestampCol ? ", {$timestampCol}" : '';
            $extraVal     = $timestampCol ? ', CURRENT_TIMESTAMP' : '';

            $row = App\ExecuteRow(
                "INSERT INTO {$this->table} (
                    ftla_number, transfer_type, transfer_date, to_bank_account_id, from_bank_account_id,
                    from_accounts, to_accounts, transfer_amount,
                    is_temporary, repayment_due_date, purpose, request_reference, dv_numbers,
                    supporting_documents, signatories, bank_officers,
                    laa_id, rfa_id, fund_source_id,
                    for_name, for_title, thru_name, thru_title, subject,
                    signatory_name_memo, signatory_position_memo,
                    prepared_by, status, created_by, created_at{$extraCol}
                ) VALUES (
                    {$this->str($ftlaNumber)}, {$this->str($transferType)}, {$this->str($transferDate)},
                    {$toBankId}, {$this->intOrNull($fromBankId)},
                    {$this->jsonb($fromAccounts, '[]')}, {$this->jsonb($toAccountIds, '[]')}, {$this->decimal($amount)},
                    {$this->bool($body['is_temporary'] ?? false)}, {$this->dateOrNull($body['repayment_due_date'] ?? null)},
                    {$this->str($purpose)}, {$this->strOrNull($body['request_reference'] ?? null)},
                    {$this->strOrNull($body['dv_numbers'] ?? '')},
                    {$this->jsonb($body['supporting_documents'] ?? null, '[]')},
                    {$this->jsonb($body['signatories'] ?? null, '{}')},
                    {$this->jsonb($body['bank_officers'] ?? null, '{}')},
                    {$this->intOrNull($body['laa_id'] ?? null)}, {$this->intOrNull($body['rfa_id'] ?? null)},
                    {$this->intOrNull($body['fund_source_id'] ?? null)},
                    {$this->strOrNull($body['for_name'] ?? null)}, {$this->strOrNull($body['for_title'] ?? null)},
                    {$this->strOrNull($body['thru_name'] ?? null)}, {$this->strOrNull($body['thru_title'] ?? null)},
                    {$this->strOrNull($body['subject'] ?? null)},
                    {$this->strOrNull($body['signatory_name_memo'] ?? null)}, {$this->strOrNull($body['signatory_position_memo'] ?? null)},
                    {$preparedBy}, {$this->str($status)}, {$uid}, CURRENT_TIMESTAMP{$extraVal}
                ) RETURNING fund_transfer_id, ftla_number",
                'DB'
            );
            if (empty($row['fund_transfer_id'])) {
                return ['success' => false, 'message' => 'Failed to create FTLA record.'];
            }

            GlobalService::logStatusChange(self::STATUS_LOG_FOR, (int) $row['fund_transfer_id'], $status, $userId);

            return [
                'success'  => true,
                'message'  => "FTLA {$row['ftla_number']} created successfully.",
                'data'     => ['fund_transfer_id' => (int) $row['fund_transfer_id'], 'ftla_number' => $row['ftla_number'], 'status' => $status],
                'httpCode' => 201,
            ];
        } catch (\Exception $e) {
            App\LogError('FtlaService::create ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to create FTLA record.'];
        }
    }

    /**
     * Builds its SET clause only from whatever keys are present in the body — a bare
     * {status: 'X'} call (from the list page's row actions / the status-tracking modal)
     * touches only status-related columns; a full edit from the form modal touches
     * every field it sends. $remarks is stored in fims.status_change_log, not on the row.
     */
    public function update(int|string $id, array $body, int $userId, ?string $remarks = null): array
    {
        try {
            $ftId = (int) $id;
            $cur  = App\ExecuteRow("SELECT fund_transfer_id, status, ftla_number, to_bank_account_id FROM {$this->table} WHERE fund_transfer_id = {$ftId}", 'DB');
            if (!$cur) {
                return ['success' => false, 'message' => "FTLA #{$ftId} not found.", 'httpCode' => 404];
            }

            $set = [];
            $hasFieldEdit = false;
            foreach (self::PLAIN_STRING_FIELDS as $f) {
                if (array_key_exists($f, $body)) {
                    $set[] = "{$f} = " . $this->strOrNull($body[$f]);
                    $hasFieldEdit = true;
                }
            }
            foreach (self::INT_FIELDS as $f) {
                if (array_key_exists($f, $body)) {
                    $set[] = "{$f} = " . $this->intOrNull($body[$f]);
                    $hasFieldEdit = true;
                }
            }
            foreach (self::BOOL_FIELDS as $f) {
                if (array_key_exists($f, $body)) {
                    $set[] = "{$f} = " . $this->bool($body[$f]);
                    $hasFieldEdit = true;
                }
            }
            foreach (self::DATE_FIELDS as $f) {
                if (array_key_exists($f, $body)) {
                    $set[] = "{$f} = " . $this->dateOrNull($body[$f]);
                    $hasFieldEdit = true;
                }
            }
            foreach (self::JSON_FIELDS as $f) {
                if (array_key_exists($f, $body)) {
                    $set[] = "{$f} = " . $this->jsonb($body[$f], $f === 'signatories' || $f === 'bank_officers' ? '{}' : '[]');
                    $hasFieldEdit = true;
                }
            }
            if (array_key_exists('dv_numbers', $body)) {
                $set[]        = 'dv_numbers = ' . $this->strOrNull($body['dv_numbers']);
                $hasFieldEdit = true;
            }

            // "Multiple debit accounts" and/or "multiple credit accounts" (paired FROM/TO
            // rows) — same shapes/precedence as create(). to_bank_account_id is handled
            // here too (not in the generic INT_FIELDS loop) since paired mode derives it
            // from to_accounts[0] instead of trusting the body's own value.
            $fromAccountsProvided = array_key_exists('from_accounts', $body);
            $toAccountsProvided   = array_key_exists('to_accounts', $body);
            $fromAccounts         = $fromAccountsProvided ? $this->normalizeFromAccounts($body['from_accounts']) : null;
            $toAccountIds         = $toAccountsProvided ? $this->normalizeAccountIds($body['to_accounts']) : null;
            $paired               = $toAccountsProvided && count($toAccountIds) > 1;
            $pairedAmount         = null;

            if ($paired) {
                $pairFrom = $fromAccounts ?? [];
                if (count($toAccountIds) !== count($pairFrom)) {
                    return ['success' => false, 'message' => 'Paired debit/credit accounts must have the same number of rows.'];
                }
                foreach ($pairFrom as $i => $r) {
                    if ($r['bank_account_id'] === $toAccountIds[$i]['bank_account_id']) {
                        return ['success' => false, 'message' => "Row " . ($i + 1) . ": the debit and credit account can't be the same."];
                    }
                }
                $set[]        = 'from_accounts = ' . $this->jsonb($pairFrom, '[]');
                $set[]        = 'to_accounts = ' . $this->jsonb($toAccountIds, '[]');
                $set[]        = 'from_bank_account_id = ' . $this->intOrNull($pairFrom[0]['bank_account_id'] ?? null);
                $set[]        = 'to_bank_account_id = ' . $this->intOrNull($toAccountIds[0]['bank_account_id']);
                $pairedAmount = array_sum(array_column($pairFrom, 'amount'));
                $hasFieldEdit = true;
            } else {
                if ($toAccountsProvided) {
                    $set[]        = 'to_accounts = ' . $this->jsonb($toAccountIds, '[]');
                    $set[]        = 'to_bank_account_id = ' . $this->intOrNull(
                        $toAccountIds ? $toAccountIds[0]['bank_account_id'] : ($body['to_bank_account_id'] ?? null)
                    );
                    $hasFieldEdit = true;
                } elseif (array_key_exists('to_bank_account_id', $body)) {
                    $set[]        = 'to_bank_account_id = ' . $this->intOrNull($body['to_bank_account_id']);
                    $hasFieldEdit = true;
                }

                if ($fromAccountsProvided) {
                    $toBankIdForCheck = $toAccountIds[0]['bank_account_id'] ?? (array_key_exists('to_bank_account_id', $body)
                        ? (int) $body['to_bank_account_id']
                        : (int) ($cur['to_bank_account_id'] ?? 0));
                    foreach ($fromAccounts as $r) {
                        if ($toBankIdForCheck > 0 && $r['bank_account_id'] === $toBankIdForCheck) {
                            return ['success' => false, 'message' => 'A debit (source) account cannot be the same as the destination account.'];
                        }
                    }
                    $fromBankId = $fromAccounts
                        ? $fromAccounts[0]['bank_account_id']
                        : (!empty($body['from_bank_account_id']) ? (int) $body['from_bank_account_id'] : null);
                    $set[]        = 'from_accounts = ' . $this->jsonb($fromAccounts, '[]');
                    $set[]        = 'from_bank_account_id = ' . $this->intOrNull($fromBankId);
                    $hasFieldEdit = true;
                } elseif (array_key_exists('from_bank_account_id', $body)) {
                    $set[]        = 'from_bank_account_id = ' . $this->intOrNull($body['from_bank_account_id']);
                    $hasFieldEdit = true;
                }
            }

            if (array_key_exists('transfer_amount', $body)) {
                $amt = $pairedAmount ?? ($fromAccounts ? array_sum(array_column($fromAccounts, 'amount')) : $body['transfer_amount']);
                if (!is_numeric($amt) || (float) $amt <= 0) {
                    return ['success' => false, 'message' => 'Transfer Amount must be greater than zero.'];
                }
                $set[]        = 'transfer_amount = ' . $this->decimal($amt);
                $hasFieldEdit = true;
            } elseif ($pairedAmount !== null) {
                $set[]        = 'transfer_amount = ' . $this->decimal($pairedAmount);
                $hasFieldEdit = true;
            } elseif ($fromAccountsProvided && $fromAccounts) {
                $set[]        = 'transfer_amount = ' . $this->decimal(array_sum(array_column($fromAccounts, 'amount')));
                $hasFieldEdit = true;
            }

            if ($hasFieldEdit && !in_array($cur['status'], self::EDITABLE, true)) {
                return ['success' => false, 'message' => "Only a DRAFT FTLA can be edited (current status: {$cur['status']})."];
            }

            // A full-edit save (the form modal's "Submit" button) sends no status at all —
            // same convention as create(): default a DRAFT record forward to FOR_REVIEW so
            // editing-then-submitting actually advances it, instead of silently staying DRAFT.
            // A bare {status: 'X'} call (list page row actions / status modal) always states
            // its target explicitly, so this default never fires for those.
            $statusKey = array_key_exists('status', $body) && trim((string) $body['status']) !== ''
                ? strtoupper(trim((string) $body['status']))
                : ($hasFieldEdit && $cur['status'] === 'DRAFT' ? 'FOR_REVIEW' : null);

            $newStatus = null;
            if ($statusKey !== null) {
                $newStatus = $statusKey;
                $allowed   = self::NEXT[$cur['status']] ?? [];
                if (!in_array($newStatus, $allowed, true)) {
                    return ['success' => false, 'message' => "Cannot move a {$cur['status']} FTLA to {$newStatus}."];
                }
                $transitionKey = "{$cur['status']}->{$newStatus}";
                $remarks       = trim((string) $remarks);
                if (in_array($transitionKey, self::REMARKS_REQUIRED, true) && $remarks === '') {
                    return ['success' => false, 'message' => 'A reason is required for this action.'];
                }

                $set[] = 'status = ' . $this->str($newStatus);
                if ($col = self::STATUS_TIMESTAMP_COLUMN[$newStatus] ?? null) {
                    $set[] = "{$col} = CURRENT_TIMESTAMP";
                }
            }

            if (!$set) {
                return ['success' => false, 'message' => 'Nothing to update.'];
            }

            $uid   = $userId > 0 ? (string) $userId : 'NULL';
            $set[] = "updated_by = {$uid}";
            $set[] = 'updated_at = CURRENT_TIMESTAMP';

            // Guards on the status read above so two concurrent actions can't both win.
            App\Execute(
                "UPDATE {$this->table} SET " . implode(', ', $set) . "
                 WHERE fund_transfer_id = {$ftId} AND status = " . $this->str($cur['status']),
                'DB'
            );
            $nowStatus = App\ExecuteScalar("SELECT status FROM {$this->table} WHERE fund_transfer_id = {$ftId}", 'DB');
            if ($newStatus !== null && $nowStatus !== $newStatus) {
                return ['success' => false, 'message' => 'This FTLA was changed by someone else. Please reload and try again.'];
            }

            if ($newStatus !== null) {
                GlobalService::logStatusChange(self::STATUS_LOG_FOR, $ftId, $newStatus, $userId, $remarks ?: null);
            }

            return [
                'success' => true,
                'message' => $newStatus ? "FTLA is now {$newStatus}." : "FTLA {$cur['ftla_number']} updated successfully.",
                'data'    => ['fund_transfer_id' => $ftId, 'ftla_number' => $cur['ftla_number'], 'status' => $nowStatus],
            ];
        } catch (\Exception $e) {
            App\LogError('FtlaService::update ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update FTLA record.'];
        }
    }

    // ── attachments ──

    public function uploadAttachment(array $body, array $files, int $userId): array
    {
        $docType = trim((string) ($body['document_type'] ?? 'OTHER'));
        $ftId    = (int) ($body['fund_transfer_id'] ?? 0);
        $file    = $files['file'] ?? null;

        $result = $this->fileService()->uploadFileToStorage($file);
        if (!$result['success']) {
            return $result;
        }

        $entry = [
            'document_type' => $docType,
            'name'          => $result['document_name'],
            'link'          => $result['link'],
            'url'           => $result['preview_url'],
            'size'          => $result['size'],
            'uploaded_at'   => date('Y-m-d H:i:s'),
            'uploaded_by'   => $userId > 0 ? $userId : null,
        ];

        if ($ftId > 0) {
            try {
                App\Execute(
                    "UPDATE {$this->table}
                     SET attachments = COALESCE(attachments, '[]'::jsonb) || " . $this->str(json_encode([$entry])) . "::jsonb,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE fund_transfer_id = {$ftId}",
                    'DB'
                );
            } catch (\Exception $e) {
                App\LogError('FtlaService::uploadAttachment ' . $e->getMessage());

                return ['success' => false, 'message' => 'File was uploaded but could not be attached to the FTLA record.'];
            }
        }

        return ['success' => true, 'message' => 'Uploaded.', 'data' => $entry];
    }

    // ── workflow (no engine — see class doc comment) ──

    public function fetchWorkflowMeta(): array
    {
        $statuses = [];
        foreach (self::STATUSES as $value => [$name, $color]) {
            $statuses[] = [
                'value'            => $value,
                'name'             => $name,
                'color'            => $color,
                'is_editable'      => in_array($value, self::EDITABLE, true),
                'requires_remarks' => false,
                'confirm_message'  => "Move this FTLA to \"{$name}\"?",
            ];
        }

        return ['success' => true, 'data' => ['statuses' => $statuses]];
    }

    public function fetchWorkflowSteps(): array
    {
        $order = array_values(array_filter(array_keys(self::STATUSES), fn ($s) => $s !== 'CANCELLED'));
        $steps = [];
        $statusActorOrderMap = [];

        foreach ($order as $i => $status) {
            $nextList = self::NEXT[$status] ?? [];
            if (!$nextList) {
                continue; // terminal status — nothing to offer
            }
            $stepOrder = $i + 1;
            $statusActorOrderMap[$status] = $stepOrder;

            $statuses = [];
            foreach ($nextList as $next) {
                [$name, $color]  = self::STATUSES[$next];
                $isReturn        = in_array("{$status}->{$next}", self::REMARKS_REQUIRED, true);
                $statuses[] = [
                    'value'            => $next,
                    'name'             => $isReturn ? "Return to {$name}" : "Move to {$name}",
                    'color'            => $next === 'CANCELLED' ? 'danger' : $color,
                    'confirm_message'  => ($isReturn ? "Return this FTLA to \"{$name}\"?" : "Move this FTLA to \"{$name}\"?"),
                    'requires_remarks' => $isReturn,
                ];
            }
            $steps[] = ['step_order' => $stepOrder, 'statuses' => $statuses];
        }

        return [
            'success' => true,
            'data'    => [
                'is_preparer'            => true, // no actor/role gating yet — see class doc comment
                'preparer_step_order'    => 1,
                'steps'                  => $steps,
                'status_actor_order_map' => $statusActorOrderMap,
            ],
        ];
    }

    /** No role-based signatory assignment exists yet for FTLA — the form's signatory fields stay blank by default. */
    public function getDefaultSignatories(): array
    {
        return ['success' => true, 'data' => []];
    }

    public function getTimeline(int $id): array
    {
        $row = App\ExecuteRow("SELECT * FROM {$this->table} WHERE fund_transfer_id = {$id}", 'DB');
        if (!$row) {
            return ['success' => false, 'message' => "FTLA #{$id} not found."];
        }

        $order      = array_keys(self::STATUSES);
        $currentIdx = array_search($row['status'], $order, true);
        $timestamps = ['DRAFT' => $row['created_at']];
        foreach (self::STATUS_TIMESTAMP_COLUMN as $status => $col) {
            $timestamps[$status] = $row[$col] ?? null;
        }

        $steps = [];
        foreach ($order as $i => $status) {
            if ($status === 'CANCELLED' && $row['status'] !== 'CANCELLED') {
                continue; // only show the cancelled row when it actually happened
            }
            [$name, $color] = self::STATUSES[$status];
            $steps[] = [
                'step_order'  => $i + 1,
                'step_label'  => $name,
                'step_status' => $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'active' : 'pending'),
                'status_name' => $name,
                'status_color' => $color,
                'action_at'   => $timestamps[$status] ?? null,
            ];
        }

        return ['success' => true, 'data' => $steps];
    }

    public function getHistory(int $id): array
    {
        $rows = App\ExecuteRows(
            "SELECT l.status, l.remarks, l.changed_at,
                    TRIM(CONCAT_WS(' ', u.first_name, u.last_name)) AS actor_name
             FROM fims.status_change_log l
             LEFT JOIN {$this->tableUsers} u ON u.user_id = l.changed_by
             WHERE l.status_for = " . $this->str(self::STATUS_LOG_FOR) . " AND l.record_id = " . $this->str((string) $id) . "
             ORDER BY l.changed_at ASC, l.status_change_log_id ASC",
            'DB'
        ) ?: [];

        $rows = array_map(function ($r) {
            [$name, $color] = self::STATUSES[$r['status']] ?? [$r['status'], 'secondary'];

            return $r + ['status_name' => $name, 'status_color' => $color, 'action_at' => $r['changed_at']];
        }, $rows);

        return ['success' => true, 'data' => $rows];
    }

    /** Compares the FTLA's attached DV numbers against fims.disbursement_vouchers. */
    public function getDvUtilization(int $id): array
    {
        $rec = App\ExecuteRow("SELECT fund_transfer_id, ftla_number, transfer_amount, dv_numbers FROM {$this->table} WHERE fund_transfer_id = {$id}", 'DB');
        if (!$rec) {
            return ['success' => false, 'message' => 'Record not found.'];
        }

        $numbers = array_values(array_filter(array_map('trim', explode("\n", (string) ($rec['dv_numbers'] ?? '')))));
        $documents  = [];
        $totalFound = 0.0;

        if ($numbers) {
            $inList = implode(',', array_map(fn ($n) => $this->str($n), $numbers));
            $rows   = App\ExecuteRows(
                "SELECT dv.dv_no, dv.claimant_name, dv.net_amount, st.name AS status_name, st.color AS status_color
                 FROM {$this->tableDv} dv
                 LEFT JOIN {$this->tableDvStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_no IN ({$inList})",
                'DB'
            ) ?: [];

            $byNumber = [];
            foreach ($rows as $r) {
                $byNumber[$r['dv_no']] = $r;
            }
            foreach ($numbers as $num) {
                if (isset($byNumber[$num])) {
                    $r          = $byNumber[$num];
                    $amount     = (float) ($r['net_amount'] ?? 0);
                    $totalFound += $amount;
                    $documents[] = [
                        'dv_number'    => $num,
                        'payee_name'   => $r['claimant_name'],
                        'amount'       => $amount,
                        'status_name'  => $r['status_name'] ?? '—',
                        'status_color' => $r['status_color'] ?? 'secondary',
                        'found'        => true,
                    ];
                } else {
                    $documents[] = [
                        'dv_number' => $num, 'payee_name' => null, 'amount' => null,
                        'status_name' => 'Not Found', 'status_color' => 'secondary', 'found' => false,
                    ];
                }
            }
        }

        $transferAmount = (float) ($rec['transfer_amount'] ?? 0);

        return ['success' => true, 'data' => [
            'fund_transfer_id'  => $id,
            'ftla_number'       => $rec['ftla_number'],
            'transfer_amount'   => $transferAmount,
            'total_dv_amount'   => $totalFound,
            'variance'          => round($transferAmount - $totalFound, 2),
            'documents'         => $documents,
        ]];
    }

    // ── printing — delegates to FtlaPrintService via the shared PrintService (PrintService::DOCS['FTLA']) ──

    /**
     * The saved record as a FtlaPrintService payload: transfers[] from from_accounts
     * (empty → model() falls back to the single to-account fields), signatories from
     * the stored signatories/bank_officers jsonb, overridable by $overrides (the form's
     * "sig_overrides" — same field names as ftla-form.php's _getSigOverrides()).
     */
    private function _buildPrintPayload(int $id, array $overrides = []): array
    {
        $rec = $this->getRecord($id);
        if (!$rec['success']) {
            return $rec;
        }
        $row  = $rec['data'];
        $sigs = is_array($row['signatories']) ? $row['signatories'] : [];
        $bank = is_array($row['bank_officers']) ? $row['bank_officers'] : [];
        $ov   = is_array($overrides['sig_overrides'] ?? null) ? $overrides['sig_overrides'] : [];
        $pick = static fn (string $ovKey, string $sigKey, string $default = '') => trim((string) ($ov[$ovKey] ?? $sigs[$sigKey] ?? $default));

        // Paired mode (Treasury's ftlafundtransfer.php "pairs" shape): each from_accounts[i]
        // funds the to_accounts[i] at the same index, not the single to_bank_account_id —
        // FtlaPrintService::model() auto-picks its "pairs" layout from this shape.
        if (!empty($row['is_paired'])) {
            $transfers = [];
            foreach ($row['from_accounts'] as $i => $fa) {
                $ta          = $row['to_accounts'][$i] ?? [];
                $transfers[] = [
                    'from_account_number' => $fa['account_number'] ?? '',
                    'from_account_name'   => $fa['account_name'] ?? ($fa['bank_name'] ?? ''),
                    'to_account_number'   => $ta['account_number'] ?? '',
                    'to_account_name'     => $ta['account_name'] ?? ($ta['bank_name'] ?? ''),
                    'amount'              => $fa['amount'] ?? 0,
                ];
            }
        } else {
            $transfers = array_map(static fn (array $fa) => [
                'from_account_number' => $fa['account_number'] ?? '',
                'from_account_name'   => $fa['account_name'] ?? ($fa['bank_name'] ?? ''),
                'to_account_number'   => $row['to_account_number'] ?? '',
                'to_account_name'     => $row['to_account_name'] ?? '',
                'amount'              => $fa['amount'] ?? 0,
            ], $row['from_accounts'] ?? []);
        }

        $dvNumbers = array_values(array_filter(array_map('trim', explode("\n", (string) ($row['dv_numbers'] ?? '')))));

        return ['success' => true, 'data' => [
            'ftla_number'         => $row['ftla_number'],
            'transfer_type'       => $row['transfer_type'] ?? '',
            'date'                => $row['transfer_date'],
            'to_bank_name'        => $row['to_bank_name'] ?? '',
            'transfers'           => $transfers,
            // Legacy/scalar-only records (from_bank_account_id set, no from_accounts rows)
            // never reach $transfers above — fall back to the joined from_bank_account_id's
            // own name/number so the single-account layout isn't left blank.
            'from_account_name'   => $row['from_accounts'][0]['account_name'] ?? ($row['from_account_name'] ?? $row['from_bank_name'] ?? ''),
            'from_account_number' => $row['from_accounts'][0]['account_number'] ?? ($row['from_account_number'] ?? ''),
            'to_account_name'     => $row['to_account_name'] ?? '',
            'to_account_number'   => $row['to_account_number'] ?? '',
            'amount'              => $row['transfer_amount'] ?? 0,
            'purpose'             => $row['purpose'] ?? '',
            'sig_left_name'       => $pick('sig1_name', 'sig1_name', $row['reviewed_by_name'] ?? ''),
            'sig_left_title'      => $pick('sig1_title', 'sig1_title'),
            'sig_right_name'      => $pick('sig2_name', 'sig2_name', $row['approved_by_name'] ?? ''),
            'sig_right_title'     => $pick('sig2_title', 'sig2_title'),
            'dv_numbers'          => $dvNumbers,
            'fund_classification' => $sigs['fund_classification'] ?? '',
            'bank_sig_verified'   => $pick('bk_sig', 'sig_verified', $bank['sig_verified'] ?? ''),
            'bank_checked'        => $pick('bk_chk', 'checked', $bank['checked'] ?? ''),
            'bank_approved'       => $pick('bk_app', 'approved', $bank['approved'] ?? ''),
            'bank_posted'         => $pick('bk_pst', 'posted', $bank['posted'] ?? ''),
        ]];
    }

    public function printDocx(int $id, array $body = []): array
    {
        $payload = $this->_buildPrintPayload($id, $body);
        if (!$payload['success']) {
            return $payload;
        }

        return (new PrintService())->print('FTLA', $payload['data'], 'docx');
    }

    public function printPdf(int $id, array $body = []): array
    {
        $payload = $this->_buildPrintPayload($id, $body);
        if (!$payload['success']) {
            return $payload;
        }

        return (new PrintService())->print('FTLA', $payload['data'], 'pdf');
    }
}
