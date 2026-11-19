<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs the Bank Reconciliation module (app/pages/bank-reconciliation-page.php),
 * Reports > Bank Reconciliation — see "01 - table/51 - fims_bank_reconciliations.sql"
 * for the table's docblock (book_balance snapshot caveat, one-per-account-per-month).
 *
 * Per Book:  Book Balance (snapshot from the GL) + unrecorded deposit + unreleased
 *            check + credit memo + interest income - debit memo = Adjusted Book Balance
 * Per Bank:  Bank Balance (manual) + deposit in transit - outstanding check
 *            = Adjusted Bank Balance
 * Reconciled when both adjusted balances match (within a centavo, floating point).
 */
class BankReconciliationService
{
    private string $tableRecon       = 'fims.bank_reconciliations';
    private string $tableBankAccounts = 'fims.bank_accounts';
    private string $tableGl          = 'fims.general_ledger';

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    private function ok($data = [])
    {
        return json_encode(['success' => true, 'data' => $data]);
    }

    private function fail(string $message)
    {
        return json_encode(['success' => false, 'message' => $message]);
    }

    // ── Listing (one row per bank account, latest reconciliation if any) ──
    public function getList(array $params)
    {
        $search = trim((string) ($params['search'] ?? ''));
        $status = strtoupper(trim((string) ($params['status'] ?? '')));

        $where = ['1=1'];
        if ($search !== '') {
            $needle = $this->str('%' . $search . '%');
            $where[] = "(ba.account_name ILIKE {$needle} OR ba.account_number ILIKE {$needle} OR ba.bank_name ILIKE {$needle})";
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT ba.bank_account_id, ba.account_name, ba.account_number, ba.bank_name,
                    r.reconciliation_id, r.period_year, r.period_month, r.as_of_date,
                    r.adjusted_book_balance, r.adjusted_bank_balance, r.bank_balance, r.status
             FROM {$this->tableBankAccounts} ba
             LEFT JOIN LATERAL (
                 SELECT * FROM {$this->tableRecon} x
                 WHERE x.bank_account_id = ba.bank_account_id
                 ORDER BY x.period_year DESC, x.period_month DESC
                 LIMIT 1
             ) r ON TRUE
             WHERE {$whereSql}
             ORDER BY ba.account_name",
            'DB'
        ) ?: [];

        if ($status !== '' && in_array($status, ['RECONCILED', 'UNRECONCILED', 'PENDING'], true)) {
            $rows = array_values(array_filter($rows, function ($r) use ($status) {
                $rowStatus = $r['status'] ?? 'PENDING';
                return $rowStatus === $status || ($status === 'PENDING' && $r['reconciliation_id'] === null);
            }));
        }

        return $this->ok($rows);
    }

    // ── Bank accounts for the "Bank" dropdown ──
    public function getBankAccounts()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT bank_account_id, account_name, account_number, bank_name, account_code
             FROM {$this->tableBankAccounts}
             WHERE status = 'ACTIVE'
             ORDER BY account_name",
            'DB'
        ) ?: [];

        return $this->ok($rows);
    }

    // ── History for one bank account ──
    public function getHistory($bankAccountId)
    {
        $id = (int) $bankAccountId;
        $rows = App\ExecuteRowsAssociative(
            "SELECT * FROM {$this->tableRecon}
             WHERE bank_account_id = {$id}
             ORDER BY period_year DESC, period_month DESC",
            'DB'
        ) ?: [];

        return $this->ok($rows);
    }

    // ── One reconciliation ──
    public function getOne($id)
    {
        $row = $this->fetch((int) $id);
        if (!$row) {
            return $this->fail('Reconciliation not found.');
        }

        return $this->ok($row);
    }

    private function fetch(int $id): ?array
    {
        return App\ExecuteRow(
            "SELECT r.*, ba.account_name, ba.account_number, ba.bank_name
             FROM {$this->tableRecon} r
             JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = r.bank_account_id
             WHERE r.reconciliation_id = {$id}",
            'DB'
        ) ?: null;
    }

    /**
     * Cumulative balance (debit - credit) of a bank account's tagged GL
     * account_code in the General Ledger, as of (and including) $asOfDate.
     * Same "Autofilled SL Balance" idea the sketch calls for. Returns null
     * when the bank account has no GL account tagged yet (nothing to
     * auto-fill — the page falls back to manual entry).
     */
    public function getBookBalance($bankAccountId, $asOfDate)
    {
        $id = (int) $bankAccountId;
        $bank = App\ExecuteRow(
            "SELECT account_code, account_name FROM {$this->tableBankAccounts} WHERE bank_account_id = {$id}",
            'DB'
        );
        if (!$bank) {
            return $this->fail('Bank account not found.');
        }
        if (empty($bank['account_code'])) {
            return $this->ok(['book_balance' => null, 'message' => 'This bank account has no GL account tagged yet — enter the Book Balance manually.']);
        }

        $date = $this->str($asOfDate);
        $row = App\ExecuteRow(
            "SELECT COALESCE(SUM(debit - credit), 0) AS bal
             FROM {$this->tableGl}
             WHERE account_code = " . $this->str($bank['account_code']) . "
               AND posting_date <= {$date}",
            'DB'
        );

        return $this->ok(['book_balance' => round((float) ($row['bal'] ?? 0), 2)]);
    }

    // ── Create / update (upsert by bank_account_id + period) ──
    public function save(array $data)
    {
        $bankAccountId = (int) ($data['bank_account_id'] ?? 0);
        $periodYear    = (int) ($data['period_year'] ?? 0);
        $periodMonth   = (int) ($data['period_month'] ?? 0);

        if ($bankAccountId <= 0) {
            return $this->fail('Select a Bank.');
        }
        if ($periodMonth < 1 || $periodMonth > 12 || $periodYear < 2000) {
            return $this->fail('Select a valid reconciliation month.');
        }
        $bank = App\ExecuteRow(
            "SELECT bank_account_id FROM {$this->tableBankAccounts} WHERE bank_account_id = {$bankAccountId}",
            'DB'
        );
        if (!$bank) {
            return $this->fail('Bank account not found.');
        }

        $asOfDate = date('Y-m-t', mktime(0, 0, 0, $periodMonth, 1, $periodYear));

        $bookBalance           = $this->num($data['book_balance'] ?? 0);
        $addUnrecordedDeposit  = $this->num($data['add_unrecorded_deposit'] ?? 0);
        $addUnreleasedCheck    = $this->num($data['add_unreleased_check'] ?? 0);
        $addCreditMemo         = $this->num($data['add_credit_memo'] ?? 0);
        $addInterestIncome     = $this->num($data['add_interest_income'] ?? 0);
        $lessDebitMemo         = $this->num($data['less_debit_memo'] ?? 0);
        $adjustedBookBalance   = round($bookBalance + $addUnrecordedDeposit + $addUnreleasedCheck + $addCreditMemo + $addInterestIncome - $lessDebitMemo, 2);

        $bankBalance           = $this->num($data['bank_balance'] ?? 0);
        $addDepositInTransit   = $this->num($data['add_deposit_in_transit'] ?? 0);
        $lessOutstandingCheck  = $this->num($data['less_outstanding_check'] ?? 0);
        $adjustedBankBalance   = round($bankBalance + $addDepositInTransit - $lessOutstandingCheck, 2);

        $status = abs($adjustedBookBalance - $adjustedBankBalance) < 0.01 ? 'RECONCILED' : 'UNRECONCILED';
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $userId = (int) App\CurrentUserID();

        $existing = App\ExecuteRow(
            "SELECT reconciliation_id FROM {$this->tableRecon}
             WHERE bank_account_id = {$bankAccountId} AND period_year = {$periodYear} AND period_month = {$periodMonth}",
            'DB'
        );

        $values = "{$bankAccountId}, {$periodYear}, {$periodMonth}, " . $this->str($asOfDate) . ", "
            . "{$bookBalance}, {$addUnrecordedDeposit}, {$addUnreleasedCheck}, {$addCreditMemo}, {$addInterestIncome}, {$lessDebitMemo}, {$adjustedBookBalance}, "
            . "{$bankBalance}, {$addDepositInTransit}, {$lessOutstandingCheck}, {$adjustedBankBalance}, "
            . $this->str($status) . ', ' . ($remarks === '' ? 'NULL' : $this->str($remarks)) . ", {$userId}";

        if ($existing) {
            $id = (int) $existing['reconciliation_id'];
            App\Execute(
                "UPDATE {$this->tableRecon} SET
                    as_of_date = " . $this->str($asOfDate) . ",
                    book_balance = {$bookBalance}, add_unrecorded_deposit = {$addUnrecordedDeposit},
                    add_unreleased_check = {$addUnreleasedCheck}, add_credit_memo = {$addCreditMemo},
                    add_interest_income = {$addInterestIncome}, less_debit_memo = {$lessDebitMemo},
                    adjusted_book_balance = {$adjustedBookBalance},
                    bank_balance = {$bankBalance}, add_deposit_in_transit = {$addDepositInTransit},
                    less_outstanding_check = {$lessOutstandingCheck}, adjusted_bank_balance = {$adjustedBankBalance},
                    status = " . $this->str($status) . ",
                    remarks = " . ($remarks === '' ? 'NULL' : $this->str($remarks)) . ",
                    updated_at = CURRENT_TIMESTAMP
                 WHERE reconciliation_id = {$id}",
                'DB'
            );
        } else {
            $row = App\ExecuteRow(
                "INSERT INTO {$this->tableRecon}
                    (bank_account_id, period_year, period_month, as_of_date,
                     book_balance, add_unrecorded_deposit, add_unreleased_check, add_credit_memo, add_interest_income, less_debit_memo, adjusted_book_balance,
                     bank_balance, add_deposit_in_transit, less_outstanding_check, adjusted_bank_balance,
                     status, remarks, prepared_by)
                 VALUES ({$values})
                 RETURNING reconciliation_id",
                'DB'
            );
            $id = (int) $row['reconciliation_id'];
        }

        return $this->ok(['reconciliation_id' => $id, 'status' => $status, 'adjusted_book_balance' => $adjustedBookBalance, 'adjusted_bank_balance' => $adjustedBankBalance]);
    }
}
