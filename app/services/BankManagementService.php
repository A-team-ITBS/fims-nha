<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs the Bank Account Management module (app/pages/bank-management-page.php),
 * ported from the TREASURY reference project's
 * _assets/TREASURY/app/pages/bankaccmanagement.php +
 * _assets/TREASURY/app/services/BankAccManServices.php.
 *
 * TREASURY's version joins against its own shared.fund_sources lookup table
 * and derives last_transaction_date live from treasury.soa_transactions —
 * neither exists in this project, so fund_source is a plain text field
 * (same convention as fims.disbursement_vouchers.fund_source) and
 * last_transaction_date is a manually-entered field rather than
 * auto-derived. Region/Office is always keyed by Responsibility Center
 * (fims.responsibility_code.rc_id), not uac.departments — same convention
 * already used by fims.budget_allocation.requesting_rc_id (see
 * "03 - alter_table/14 - budget_allocation_requesting_department_id_to_
 * requesting_rc_id.sql"). rc_id has no FK, like that column; this service
 * validates it exists before saving instead.
 *
 * `status` (ACTIVE/DORMANT/CLOSED) is new and is kept in sync with the
 * pre-existing `is_active` boolean so CheckProcessingService::
 * getBankAccounts()'s "WHERE is_active = TRUE" filter (used across the
 * Check Disbursement Voucher module's Bank Account dropdowns) keeps
 * working unmodified. "Near dormant" is a computed warning window (an
 * ACTIVE account whose last_transaction_date is 300–365 days old), not a
 * stored status — same distinction TREASURY's own KPI cards make.
 */
class BankManagementService
{
    private string $tableBankAccounts     = 'fims.bank_accounts';
    private string $tableAuditLog         = 'fims.bank_account_audit_log';
    private string $tableResponsibilityCode = 'fims.responsibility_code';
    private string $tableChartOfAccounts    = 'fims.chart_of_accounts';

    // ── small SQL helpers (same shape as CheckProcessingService) ──

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function dateOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function bool(mixed $v): string
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE';
    }

    private function ok($data = [])
    {
        return json_encode(["success" => true, "data" => $data]);
    }

    private function dataTable($draw, array $rows)
    {
        return json_encode([
            "draw" => (int) $draw,
            "recordsTotal" => count($rows),
            "recordsFiltered" => count($rows),
            "data" => $rows,
        ]);
    }

    private const ACCOUNT_TYPES = ['CURRENT', 'SAVINGS', 'NON_DRAWING_SAVINGS', 'TIME_DEPOSIT', 'TRUST'];
    private const STATUSES = ['ACTIVE', 'DORMANT', 'CLOSED'];

    // ── Stats (KPI cards) ──
    public function getStats()
    {
        $row = App\ExecuteRow(
            "SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'ACTIVE') AS active,
                COUNT(*) FILTER (WHERE status = 'DORMANT') AS dormant,
                COUNT(*) FILTER (WHERE status = 'CLOSED') AS closed,
                COUNT(*) FILTER (
                    WHERE status = 'ACTIVE'
                      AND last_transaction_date IS NOT NULL
                      AND last_transaction_date <= CURRENT_DATE - INTERVAL '300 days'
                      AND last_transaction_date >  CURRENT_DATE - INTERVAL '365 days'
                ) AS near_dormant
             FROM {$this->tableBankAccounts}",
            'DB'
        );

        return $this->ok([
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'dormant' => (int) ($row['dormant'] ?? 0),
            'closed' => (int) ($row['closed'] ?? 0),
            'near_dormant' => (int) ($row['near_dormant'] ?? 0),
        ]);
    }

    // ── List (DataTables-shaped, filterable) ──
    public function getList($params)
    {
        $where = ['1=1'];
        $status = strtoupper(trim((string) ($params['status'] ?? '')));
        if (in_array($status, self::STATUSES, true)) {
            $where[] = "ba.status = " . $this->str($status);
        }
        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $needle = $this->str('%' . $search . '%');
            $where[] = "(ba.account_name ILIKE {$needle} OR ba.account_number ILIKE {$needle} OR ba.bank_name ILIKE {$needle})";
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT ba.*, rc.rc_code, rc.rc_name,
                    gl.account_code AS gl_account_code, gl.account_title AS gl_account_title,
                    sl.account_code AS sl_account_code, sl.account_title AS sl_account_title,
                    md.account_name AS main_depository_account_name
             FROM {$this->tableBankAccounts} ba
             LEFT JOIN {$this->tableResponsibilityCode} rc ON rc.rc_id = ba.rc_id
             LEFT JOIN {$this->tableChartOfAccounts} gl ON gl.account_id = ba.coa_account_id
             LEFT JOIN {$this->tableChartOfAccounts} sl ON sl.account_id = ba.sl_code
             LEFT JOIN {$this->tableBankAccounts} md ON md.bank_account_id = ba.main_depository_account_id
             WHERE {$whereSql}
             ORDER BY ba.account_name",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    // ── Single account ──
    public function getAccount($id)
    {
        $account = $this->fetchAccount((int) $id);
        if (!$account) {
            return json_encode(["success" => false, "message" => "Bank account not found."]);
        }

        return $this->ok($account);
    }

    private function fetchAccount(int $id): ?array
    {
        return App\ExecuteRow(
            "SELECT ba.*, rc.rc_code, rc.rc_name,
                    gl.account_code AS gl_account_code, gl.account_title AS gl_account_title,
                    sl.account_code AS sl_account_code, sl.account_title AS sl_account_title,
                    md.account_name AS main_depository_account_name
             FROM {$this->tableBankAccounts} ba
             LEFT JOIN {$this->tableResponsibilityCode} rc ON rc.rc_id = ba.rc_id
             LEFT JOIN {$this->tableChartOfAccounts} gl ON gl.account_id = ba.coa_account_id
             LEFT JOIN {$this->tableChartOfAccounts} sl ON sl.account_id = ba.sl_code
             LEFT JOIN {$this->tableBankAccounts} md ON md.bank_account_id = ba.main_depository_account_id
             WHERE ba.bank_account_id = {$id}",
            'DB'
        ) ?: null;
    }

    // Validates a submitted main_depository_account_id against
    // fims.bank_accounts itself (self-referencing FK — see
    // "55 - bank_accounts_add_main_depository_account_id.sql"). Empty is
    // valid (no Main Depository account assigned). An account can't be
    // assigned to itself.
    private function mainDepositoryAccountIdExists(string $mainDepositoryAccountId, int $ownAccountId): bool
    {
        if ($mainDepositoryAccountId === '') {
            return true;
        }
        if ((int) $mainDepositoryAccountId === $ownAccountId) {
            return false;
        }

        return (bool) App\ExecuteRow(
            "SELECT bank_account_id FROM {$this->tableBankAccounts} WHERE bank_account_id = " . $this->str($mainDepositoryAccountId),
            'DB'
        );
    }

    // Validates a submitted Region/Office value against fims.responsibility_code
    // (rc_id has no FK — see this class's docblock). Empty is valid (Main Office).
    private function rcIdExists(string $rcId): bool
    {
        return $rcId === '' || (bool) App\ExecuteRow(
            "SELECT rc_id FROM {$this->tableResponsibilityCode} WHERE rc_id = " . $this->str($rcId),
            'DB'
        );
    }

    // Every bank account already on file, so the Register/Edit Account
    // modal can offer them as candidates for the Assigned Main Depository
    // Account field (refreshAssignedMainDepositorySelect() in
    // bank-management-page.php) — "any" account, not just the account
    // currently being registered/edited (which, if brand new, can't
    // appear here yet since it has no bank_account_id until saved). Main
    // Depository (is_main_depository) is just a plain per-account flag,
    // set/unset via store()/update() — no exclusivity, no grouping: any
    // number of accounts can be tagged at once, independently of each
    // other and of their Bank Name or Region/Office. rc_code/rc_name are
    // joined in purely for display context.
    public function getAccountsForRegion($rcId)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT ba.bank_account_id, ba.account_name, ba.bank_name, ba.is_main_depository, ba.rc_id,
                    ba.current_balance, ba.main_depository_account_id, md.account_name AS main_depository_account_name,
                    rc.rc_code, rc.rc_name
             FROM {$this->tableBankAccounts} ba
             LEFT JOIN {$this->tableResponsibilityCode} rc ON rc.rc_id = ba.rc_id
             LEFT JOIN {$this->tableBankAccounts} md ON md.bank_account_id = ba.main_depository_account_id
             ORDER BY ba.account_name",
            'DB'
        );

        return $this->ok($rows ?: []);
    }

    // ── Fund Sweeping ──
    // Bulk-prepares DRAFT FTLAs (fims.fund_transfers, via FtlaService —
    // same create() the manual FTLA form posts to) that move each of a
    // Region/Office's bank accounts' un-swept deposits into its
    // already-assigned main_depository_account_id (see migration
    // "55 - bank_accounts_add_main_depository_account_id.sql"). "Balance"
    // here is the SUM of that account's fims.collection_report_deposits
    // rows where is_swept = FALSE, NOT fims.bank_accounts.current_balance
    // — nothing in this app ever keeps current_balance in sync, whereas
    // Collection Reports' own Deposit section is the actual record of
    // money going into these accounts. This does NOT move money itself —
    // it only creates the paperwork; the normal FTLA approval workflow
    // (DRAFT → … → COMPLETED) still has to run its course before anything
    // is actually transferred. transfer_type = SETTLEMENT_TO_MAIN, the
    // type FtlaService already defines for exactly this case.
    //
    // Accounts with no main_depository_account_id assigned, or no
    // un-swept deposits, are skipped (not an error) — callers should check
    // the "skipped" list. Once processFundSweep() creates a draft FTLA for
    // an account, every un-swept deposit it just summed gets marked
    // is_swept = TRUE (see migration "56 - collection_report_deposits_
    // add_swept_tracking.sql") so the same money is never swept twice.
    private function unsweptDepositBalanceSql(): string
    {
        return "(SELECT COALESCE(SUM(amount), 0) FROM fims.collection_report_deposits crd
                 WHERE crd.bank_account_id = ba.bank_account_id AND crd.is_swept = FALSE)";
    }

    public function previewFundSweep($rcId)
    {
        $rcId = trim((string) $rcId);
        $balanceSql = $this->unsweptDepositBalanceSql();
        $rows = App\ExecuteRowsAssociative(
            "SELECT ba.bank_account_id, ba.account_name, ba.bank_name,
                    {$balanceSql} AS current_balance,
                    ba.main_depository_account_id, md.account_name AS main_depository_account_name
             FROM {$this->tableBankAccounts} ba
             LEFT JOIN {$this->tableBankAccounts} md ON md.bank_account_id = ba.main_depository_account_id
             WHERE " . ($rcId === '' ? 'ba.rc_id IS NULL' : 'ba.rc_id = ' . $this->str($rcId)) . "
             ORDER BY ba.account_name",
            'DB'
        );

        return $this->ok($rows ?: []);
    }

    // $accountIds, when non-empty, restricts the sweep to just those
    // bank_account_id values (still scoped to $rcId too, so a request
    // can't sweep an account outside the region it claims) — lets the
    // Fund Sweeping tab's per-row checkboxes pick which eligible accounts
    // actually get swept, instead of always sweeping every eligible
    // account in the region.
    public function processFundSweep($rcId, array $accountIds = [])
    {
        $userId = (int) App\CurrentUserID();
        $rcId = trim((string) $rcId);
        $balanceSql = $this->unsweptDepositBalanceSql();
        $accountIds = array_values(array_filter(array_map('intval', $accountIds)));
        $accountFilter = $accountIds ? ' AND ba.bank_account_id IN (' . implode(',', $accountIds) . ')' : '';
        $rows = App\ExecuteRowsAssociative(
            "SELECT ba.bank_account_id, ba.account_name, {$balanceSql} AS current_balance, ba.main_depository_account_id
             FROM {$this->tableBankAccounts} ba
             WHERE " . ($rcId === '' ? 'ba.rc_id IS NULL' : 'ba.rc_id = ' . $this->str($rcId)) . $accountFilter,
            'DB'
        ) ?: [];

        $ftla = new FtlaService();
        $created = [];
        $skipped = [];
        foreach ($rows as $row) {
            $amount = (float) $row['current_balance'];
            $toId = $row['main_depository_account_id'];
            if ($amount <= 0 || !$toId || (int) $toId === (int) $row['bank_account_id']) {
                $skipped[] = ['bank_account_id' => $row['bank_account_id'], 'account_name' => $row['account_name']];
                continue;
            }
            $result = $ftla->create([
                'transfer_type' => 'SETTLEMENT_TO_MAIN',
                'transfer_date' => date('Y-m-d'),
                'from_bank_account_id' => $row['bank_account_id'],
                // Also pass from_accounts (not just the scalar
                // from_bank_account_id) — the edit form's "Debit (Source)
                // Bank Account" select is only ever populated from
                // d.from_accounts (ftlm_setDebitAccounts() in
                // ftla-form.php), never from the scalar column directly.
                // Without this, from_accounts stays '[]' and the source
                // account shows untagged/empty when editing the draft.
                'from_accounts' => [['bank_account_id' => $row['bank_account_id'], 'amount' => $amount]],
                'to_bank_account_id' => $toId,
                'transfer_amount' => $amount,
                'purpose' => 'Fund Sweep — ' . $row['account_name'] . ' to Main Depository',
                'status' => 'DRAFT',
            ], $userId);
            if ($result['success'] ?? false) {
                $fundTransferId = (int) ($result['data']['fund_transfer_id'] ?? 0);
                App\Execute(
                    "UPDATE fims.collection_report_deposits
                     SET is_swept = TRUE, swept_fund_transfer_id = {$fundTransferId}
                     WHERE bank_account_id = {$row['bank_account_id']} AND is_swept = FALSE",
                    'DB'
                );
                $created[] = ['bank_account_id' => $row['bank_account_id'], 'account_name' => $row['account_name'], 'ftla_number' => $result['data']['ftla_number'] ?? null];
            } else {
                $skipped[] = ['bank_account_id' => $row['bank_account_id'], 'account_name' => $row['account_name'], 'reason' => $result['message'] ?? 'Could not create FTLA.'];
            }
        }

        return $this->ok(['created' => $created, 'skipped' => $skipped]);
    }

    // Validates a submitted GL account code against fims.chart_of_accounts — this
    // column IS a real FK (see migration "31 - jev_payment_entries_and_bank_account_code.sql"),
    // so an invalid code would otherwise fail as an opaque DB constraint error. Empty is
    // valid (no GL account tagged yet).
    private function accountCodeExists(string $accountCode): bool
    {
        return $accountCode === '' || (bool) App\ExecuteRow(
            "SELECT account_code FROM {$this->tableChartOfAccounts} WHERE account_code = " . $this->str($accountCode),
            'DB'
        );
    }

    // coa_account_id/sl_code (GL/SL two-step picker) both reference
    // chart_of_accounts.account_id — same convention as
    // fims.vendors.coa_account_id/sl_code and
    // fims.accountable_officers.coa_account_id/sl_code. Empty is valid (not
    // yet tagged).
    private function accountIdExists(string $accountId): bool
    {
        return $accountId === '' || (bool) App\ExecuteRow(
            "SELECT account_id FROM {$this->tableChartOfAccounts} WHERE account_id = " . $this->str($accountId),
            'DB'
        );
    }

    // Resolves a chart_of_accounts account_code from its account_id — used
    // for either the GL (coa_account_id) or SL (sl_code) side of the
    // two-step picker, so the legacy account_code column (still read by
    // GeneralLedgerService::postPaymentJev(), Cash Position's opening-
    // balance match, Collections' account picker, etc.) stays in sync with
    // whichever one is actually the more specific code (see store()'s own
    // comment).
    private function accountCodeForId(string $accountId): ?string
    {
        if ($accountId === '') {
            return null;
        }
        $row = App\ExecuteRow(
            "SELECT account_code FROM {$this->tableChartOfAccounts} WHERE account_id = " . $this->str($accountId),
            'DB'
        );

        return $row['account_code'] ?? null;
    }

    // ── Create ──
    public function store($data)
    {
        $accountName = trim((string) ($data['account_name'] ?? ''));
        $accountNumber = trim((string) ($data['account_number'] ?? ''));
        $bankName = trim((string) ($data['bank_name'] ?? ''));
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));

        if ($accountName === '' || $accountNumber === '' || $bankName === '') {
            return json_encode(["success" => false, "message" => "Account Name, Account Number, and Bank Name are required."]);
        }
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'ACTIVE';
        }
        $rcId = trim((string) ($data['rc_id'] ?? ''));
        if (!$this->rcIdExists($rcId)) {
            return json_encode(["success" => false, "message" => "Select a valid Region/Office."]);
        }
        $coaAccountId = trim((string) ($data['coa_account_id'] ?? ''));
        if (!$this->accountIdExists($coaAccountId)) {
            return json_encode(["success" => false, "message" => "Select a valid GL Account."]);
        }
        $slCode = trim((string) ($data['sl_code'] ?? ''));
        if (!$this->accountIdExists($slCode)) {
            return json_encode(["success" => false, "message" => "Select a valid Sub-Ledger Account."]);
        }
        // SL, when chosen, is the more specific/leaf code this bank account
        // actually represents — account_code must reflect THAT, not the GL
        // parent it rolls up to, or every lookup keyed on account_code
        // (Cash Position's opening-balance match, Collections' picker, etc.)
        // matches the wrong, broader account.
        $accountCode = $slCode !== ''
            ? ($this->accountCodeForId($slCode) ?? '')
            : ($coaAccountId !== '' ? ($this->accountCodeForId($coaAccountId) ?? '') : trim((string) ($data['account_code'] ?? '')));
        if (!$this->accountCodeExists($accountCode)) {
            return json_encode(["success" => false, "message" => "Select a valid GL Account Code."]);
        }
        if (App\ExecuteRow("SELECT bank_account_id FROM {$this->tableBankAccounts} WHERE account_number = " . $this->str($accountNumber), 'DB')) {
            return json_encode(["success" => false, "message" => "An account with this Account Number already exists."]);
        }
        $mainDepositoryAccountId = trim((string) ($data['main_depository_account_id'] ?? ''));
        if (!$this->mainDepositoryAccountIdExists($mainDepositoryAccountId, 0)) {
            return json_encode(["success" => false, "message" => "Select a valid Main Depository account."]);
        }

        $accountType = strtoupper(trim((string) ($data['account_type'] ?? '')));
        if (!in_array($accountType, self::ACCOUNT_TYPES, true)) {
            $accountType = null;
        }
        $userId = (int) App\CurrentUserID();

        $isMainDepository = filter_var($data['is_main_depository'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $row = App\ExecuteRow(
            "INSERT INTO {$this->tableBankAccounts}
                (account_name, account_number, bank_name, bank_branch, account_type,
                 rc_id, account_code, coa_account_id, sl_code, is_main_depository,
                 main_depository_account_id, status, is_active, date_opened,
                 last_transaction_date, created_by)
             VALUES (
                " . $this->str($accountName) . ', ' . $this->str($accountNumber) . ', ' . $this->str($bankName) . ', '
                . $this->strOrNull($data['bank_branch'] ?? null) . ', ' . $this->strOrNull($accountType) . ', '
                . $this->strOrNull($rcId) . ', ' . $this->strOrNull($accountCode) . ', ' . $this->strOrNull($coaAccountId) . ', '
                . $this->strOrNull($slCode) . ', ' . ($isMainDepository ? 'TRUE' : 'FALSE') . ', '
                . $this->strOrNull($mainDepositoryAccountId) . ', '
                . $this->str($status) . ', ' . ($status === 'ACTIVE' ? 'TRUE' : 'FALSE') . ', '
                . $this->dateOrNull($data['date_opened'] ?? null) . ', ' . $this->dateOrNull($data['last_transaction_date'] ?? null)
                . ", {$userId})
             RETURNING bank_account_id",
            'DB'
        );
        $accountId = (int) $row['bank_account_id'];

        $this->writeAuditLog($accountId, 'CREATE', null, $this->fetchAccount($accountId), $userId);

        return $this->ok(['bank_account_id' => $accountId]);
    }

    // ── Update ──
    public function update($id, $data)
    {
        $accountId = (int) $id;
        $before = $this->fetchAccount($accountId);
        if (!$before) {
            return json_encode(["success" => false, "message" => "Bank account not found."]);
        }

        $accountName = trim((string) ($data['account_name'] ?? ''));
        $accountNumber = trim((string) ($data['account_number'] ?? ''));
        $bankName = trim((string) ($data['bank_name'] ?? ''));
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));

        if ($accountName === '' || $accountNumber === '' || $bankName === '') {
            return json_encode(["success" => false, "message" => "Account Name, Account Number, and Bank Name are required."]);
        }
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'ACTIVE';
        }
        $rcId = trim((string) ($data['rc_id'] ?? ''));
        if (!$this->rcIdExists($rcId)) {
            return json_encode(["success" => false, "message" => "Select a valid Region/Office."]);
        }
        $coaAccountId = trim((string) ($data['coa_account_id'] ?? ''));
        if (!$this->accountIdExists($coaAccountId)) {
            return json_encode(["success" => false, "message" => "Select a valid GL Account."]);
        }
        $slCode = trim((string) ($data['sl_code'] ?? ''));
        if (!$this->accountIdExists($slCode)) {
            return json_encode(["success" => false, "message" => "Select a valid Sub-Ledger Account."]);
        }
        // See store()'s own comment — SL (when chosen) is the specific code,
        // not the GL parent it rolls up to.
        $accountCode = $slCode !== ''
            ? ($this->accountCodeForId($slCode) ?? '')
            : ($coaAccountId !== '' ? ($this->accountCodeForId($coaAccountId) ?? '') : trim((string) ($data['account_code'] ?? '')));
        if (!$this->accountCodeExists($accountCode)) {
            return json_encode(["success" => false, "message" => "Select a valid GL Account Code."]);
        }
        $dupe = App\ExecuteRow(
            "SELECT bank_account_id FROM {$this->tableBankAccounts}
             WHERE account_number = " . $this->str($accountNumber) . " AND bank_account_id <> {$accountId}",
            'DB'
        );
        if ($dupe) {
            return json_encode(["success" => false, "message" => "Another account already uses this Account Number."]);
        }
        $mainDepositoryAccountId = trim((string) ($data['main_depository_account_id'] ?? ''));
        if (!$this->mainDepositoryAccountIdExists($mainDepositoryAccountId, $accountId)) {
            return json_encode(["success" => false, "message" => "Select a valid Main Depository account."]);
        }

        $accountType = strtoupper(trim((string) ($data['account_type'] ?? '')));
        if (!in_array($accountType, self::ACCOUNT_TYPES, true)) {
            $accountType = null;
        }
        $userId = (int) App\CurrentUserID();
        $isMainDepository = filter_var($data['is_main_depository'] ?? false, FILTER_VALIDATE_BOOLEAN);

        App\Execute(
            "UPDATE {$this->tableBankAccounts} SET
                account_name = " . $this->str($accountName) . ",
                account_number = " . $this->str($accountNumber) . ",
                bank_name = " . $this->str($bankName) . ",
                bank_branch = " . $this->strOrNull($data['bank_branch'] ?? null) . ",
                account_type = " . $this->strOrNull($accountType) . ",
                rc_id = " . $this->strOrNull($rcId) . ",
                account_code = " . $this->strOrNull($accountCode) . ",
                coa_account_id = " . $this->strOrNull($coaAccountId) . ",
                sl_code = " . $this->strOrNull($slCode) . ",
                is_main_depository = " . ($isMainDepository ? 'TRUE' : 'FALSE') . ",
                main_depository_account_id = " . $this->strOrNull($mainDepositoryAccountId) . ",
                status = " . $this->str($status) . ",
                is_active = " . ($status === 'ACTIVE' ? 'TRUE' : 'FALSE') . ",
                date_opened = " . $this->dateOrNull($data['date_opened'] ?? null) . ",
                last_transaction_date = " . $this->dateOrNull($data['last_transaction_date'] ?? null) . ",
                updated_at = CURRENT_TIMESTAMP
             WHERE bank_account_id = {$accountId}",
            'DB'
        );

        $this->writeAuditLog($accountId, 'UPDATE', $before, $this->fetchAccount($accountId), $userId);

        return $this->ok(['bank_account_id' => $accountId]);
    }

    // ── Quick GL/SL account tag ──
    public function tagAccountCode($id, $data)
    {
        $accountId = (int) $id;
        $coaAccountId = trim((string) ($data['coa_account_id'] ?? ''));
        if (!$this->accountIdExists($coaAccountId)) {
            return json_encode(["success" => false, "message" => "Select a valid GL Account."]);
        }
        $slCode = trim((string) ($data['sl_code'] ?? ''));
        if (!$this->accountIdExists($slCode)) {
            return json_encode(["success" => false, "message" => "Select a valid Sub-Ledger Account."]);
        }
        // See store()'s own comment — SL (when chosen) is the specific code,
        // not the GL parent it rolls up to.
        $accountCode = $slCode !== '' ? ($this->accountCodeForId($slCode) ?? '') : ($coaAccountId !== '' ? ($this->accountCodeForId($coaAccountId) ?? '') : '');
        $before = $this->fetchAccount($accountId);
        if (!$before) {
            return json_encode(["success" => false, "message" => "Bank account not found."]);
        }

        $userId = (int) App\CurrentUserID();
        App\Execute(
            "UPDATE {$this->tableBankAccounts}
             SET account_code = " . $this->strOrNull($accountCode) . ",
                 coa_account_id = " . $this->strOrNull($coaAccountId) . ",
                 sl_code = " . $this->strOrNull($slCode) . ",
                 updated_at = CURRENT_TIMESTAMP
             WHERE bank_account_id = {$accountId}",
            'DB'
        );

        $this->writeAuditLog($accountId, 'UPDATE', $before, $this->fetchAccount($accountId), $userId);

        return $this->ok(['bank_account_id' => $accountId]);
    }

    // ── Status change ──
    public function changeStatus($id, $data)
    {
        $accountId = (int) $id;
        $status = strtoupper(trim((string) ($data['status'] ?? '')));
        if (!in_array($status, self::STATUSES, true)) {
            return json_encode(["success" => false, "message" => "Invalid status."]);
        }
        $before = $this->fetchAccount($accountId);
        if (!$before) {
            return json_encode(["success" => false, "message" => "Bank account not found."]);
        }

        $userId = (int) App\CurrentUserID();
        App\Execute(
            "UPDATE {$this->tableBankAccounts}
             SET status = " . $this->str($status) . ", is_active = " . ($status === 'ACTIVE' ? 'TRUE' : 'FALSE') . ",
                 updated_at = CURRENT_TIMESTAMP
             WHERE bank_account_id = {$accountId}",
            'DB'
        );

        $this->writeAuditLog($accountId, 'STATUS_CHANGE', $before, $this->fetchAccount($accountId), $userId);

        return $this->ok(['bank_account_id' => $accountId, 'status' => $status]);
    }

    private function writeAuditLog(int $accountId, string $action, ?array $before, ?array $after, int $userId): void
    {
        App\Execute(
            "INSERT INTO {$this->tableAuditLog} (bank_account_id, action, before_data, after_data, performed_by)
             VALUES ({$accountId}, " . $this->str($action) . ', '
                . ($before !== null ? $this->str(json_encode($before)) . '::jsonb' : 'NULL') . ', '
                . ($after !== null ? $this->str(json_encode($after)) . '::jsonb' : 'NULL')
                . ", {$userId})",
            'DB'
        );
    }

    // ── Dormancy alerts ──
    public function getDormancyAlerts()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT bank_account_id, account_name, account_number, bank_name, last_transaction_date,
                    (CURRENT_DATE - last_transaction_date)::int AS days_inactive
             FROM {$this->tableBankAccounts}
             WHERE status = 'ACTIVE'
               AND last_transaction_date IS NOT NULL
               AND last_transaction_date <= CURRENT_DATE - INTERVAL '300 days'
               AND last_transaction_date >  CURRENT_DATE - INTERVAL '365 days'
             ORDER BY last_transaction_date",
            'DB'
        );

        return $this->ok($rows);
    }

    // ── Lookups ──
    public function getResponsibilityCenters()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT rc_id, rc_code, rc_name
             FROM {$this->tableResponsibilityCode}
             WHERE status = 'Active'
             ORDER BY rc_code",
            'DB'
        );

        return $this->ok($rows);
    }

}
