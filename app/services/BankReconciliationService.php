<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs the Bank Reconciliation module (app/pages/bank-reconciliation-page.php),
 * Reports > Bank Reconciliation — see "01 - table/51 - fims_bank_reconciliations.sql"
 * for the table's docblock (book_balance snapshot caveat, one-per-account-per-month)
 * and "03 - alter_table/59 - bank_reconciliation_match_coa_template.sql" for the
 * official COA-style line-up this worksheet follows.
 *
 * Per Books: Book Balance (ending balance of the GL ledger register, see getLedger())
 *            Add:  Unrecorded Deposits, Credit Memo, Interest Income, Book Error (under)
 *            Less: Debit Memo, Withholding Tax, Bank Charges/Cost of Checkbooks, Book Error (over)
 *            = Adjusted Book Balance
 * Per Bank:  Bank Balance (manual, or the latest uploaded SOA balance)
 *            Add:  Deposit in Transit (auto-derived from the ledger's un-cleared debit
 *                  lines — see getLedger()/setCleared()), Bank Error (under)
 *            Less: Outstanding Checks (auto-derived, un-cleared credit lines), Bank Error (over)
 *            = Adjusted Bank Balance
 * Reconciled when both adjusted balances match (within a centavo, floating point).
 */
class BankReconciliationService
{
    private string $tableRecon       = 'fims.bank_reconciliations';
    private string $tableBankAccounts = 'fims.bank_accounts';
    private string $tableGl          = 'fims.general_ledger';
    private string $tableCleared     = 'fims.bank_reconciliation_cleared_items';
    private string $tableCoa         = 'fims.chart_of_accounts';
    private string $tableSoa         = 'fims.soa_transactions';
    private string $tableJev             = 'fims.journal_entry_vouchers';
    private string $tableSettingsStatus  = 'fims.settings_status';

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
     * A bank account is tagged with its GL control account's code (e.g. "1-01-02-010"), but
     * postings land in fims.general_ledger under that account's SL child codes (e.g.
     * "1-01-02-010-01" — see fims.chart_of_accounts.parent_account_id, same convention as
     * GeneralLedgerService::fetchGlSubLedgers()). An exact account_code match therefore misses
     * every posting; this resolves the control code plus all of its SL children so the ledger
     * SQL can match on the whole set via IN (...).
     */
    private function resolveAccountCodes(string $accountCode): array
    {
        $children = App\ExecuteRowsAssociative(
            "SELECT c.account_code
             FROM {$this->tableCoa} c
             JOIN {$this->tableCoa} parent ON parent.account_id = c.parent_account_id
             WHERE parent.account_code = " . $this->str($accountCode),
            'DB'
        ) ?: [];

        return array_merge([$accountCode], array_column($children, 'account_code'));
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
        $codeList = implode(',', array_map(fn ($c) => $this->str($c), $this->resolveAccountCodes($bank['account_code'])));
        $row = App\ExecuteRow(
            "SELECT COALESCE(SUM(debit - credit), 0) AS bal
             FROM {$this->tableGl}
             WHERE account_code IN ({$codeList})
               AND posting_date <= {$date}",
            'DB'
        );

        return $this->ok(['book_balance' => round((float) ($row['bal'] ?? 0), 2)]);
    }

    // Balance of the most recent uploaded SOA line on/before $asOfDate — the
    // actual bank-statement balance, same "latest" convention as
    // CashPositionSoaService::getLatestSoaBalance(). Null when nothing has
    // been uploaded yet for this account.
    private function getLatestSoaBalance(int $bankAccountId, string $asOfDate): array
    {
        $row = App\ExecuteRow(
            "SELECT balance, transaction_date FROM {$this->tableSoa}
             WHERE bank_account_id = {$bankAccountId} AND balance IS NOT NULL
               AND transaction_date <= " . $this->str($asOfDate) . '
             ORDER BY transaction_date DESC, soa_transaction_id DESC LIMIT 1',
            'DB'
        );

        return [
            'balance' => $row ? round((float) $row['balance'], 2) : null,
            'as_of_date' => $row['transaction_date'] ?? null,
        ];
    }

    /**
     * Greedy match of GL ledger lines against uploaded SOA (bank statement)
     * lines — a GL debit (book-side deposit) matches an SOA credit_amount
     * (bank-side deposit) of the same amount, a GL credit (book-side check/
     * payment) matches an SOA debit_amount (bank-side withdrawal); same
     * sign-flip the Per Bank adjustments already use. Each SOA line can only
     * satisfy one GL line. Within $toleranceDays, closest date wins — good
     * enough for statement lines that post a few days after the book entry
     * (the usual "outstanding check" gap), without needing exact-day matches.
     * Returns gl_id => soa_transaction_id for every line that matched.
     */
    private function matchSoaLines(int $bankAccountId, string $asOfDate, array $glRows): array
    {
        $soaRows = App\ExecuteRowsAssociative(
            "SELECT soa_transaction_id, transaction_date, debit_amount, credit_amount
             FROM {$this->tableSoa}
             WHERE bank_account_id = {$bankAccountId} AND transaction_date <= " . $this->str($asOfDate) . '
             ORDER BY transaction_date, soa_transaction_id',
            'DB'
        ) ?: [];
        if (!$soaRows) {
            return [];
        }

        $toleranceDays = 15;
        $used = [];
        $matched = [];

        foreach ($glRows as $gl) {
            $debit = $this->num($gl['debit']);
            $credit = $this->num($gl['credit']);
            $isDeposit = $debit > 0;
            $amount = $isDeposit ? $debit : $credit;
            if ($amount <= 0) {
                continue;
            }
            $glTime = strtotime($gl['posting_date']);

            $bestId = null;
            $bestDiff = null;
            foreach ($soaRows as $soa) {
                $soaId = $soa['soa_transaction_id'];
                if (isset($used[$soaId])) {
                    continue;
                }
                $soaAmount = (float) ($isDeposit ? $soa['credit_amount'] : $soa['debit_amount']);
                if (abs($soaAmount - $amount) >= 0.01) {
                    continue;
                }
                $diffDays = abs((strtotime($soa['transaction_date']) - $glTime)) / 86400;
                if ($diffDays > $toleranceDays) {
                    continue;
                }
                if ($bestDiff === null || $diffDays < $bestDiff) {
                    $bestDiff = $diffDays;
                    $bestId = $soaId;
                }
            }
            if ($bestId !== null) {
                $used[$bestId] = true;
                $matched[$gl['gl_id']] = $bestId;
            }
        }

        return $matched;
    }

    /**
     * Ledger register for one bank account, period $periodStart through
     * $asOfDate inclusive: a Beginning Balance (everything strictly BEFORE
     * $periodStart — never re-listed as a row, so nothing is double-counted
     * between the aggregate and the line items), every posted GL line for
     * the account's tagged GL account_code within the period, a running
     * balance, and whether each line has cleared the bank yet. "Cleared" is
     * auto-matched against the uploaded SOA (bank statement, see
     * matchSoaLines()) unless the user has explicitly overridden it
     * (fims.bank_reconciliation_cleared_items.forced_cleared — see
     * setCleared()). Mirrors the Date/Reference/Particulars/Debit/Credit/
     * Balance register the worksheet is built from.
     */
    public function getLedger($bankAccountId, $periodStart, $asOfDate)
    {
        $id = (int) $bankAccountId;
        $bank = App\ExecuteRow(
            "SELECT bank_account_id, account_code, account_name FROM {$this->tableBankAccounts} WHERE bank_account_id = {$id}",
            'DB'
        );
        if (!$bank) {
            return $this->fail('Bank account not found.');
        }
        if (empty($bank['account_code'])) {
            return $this->ok(['has_gl_account' => false, 'beginning_balance' => 0, 'rows' => [], 'ending_balance' => 0, 'outstanding_deposits' => [], 'outstanding_checks' => [], 'soa_rows' => []]);
        }

        $codeList = implode(',', array_map(fn ($c) => $this->str($c), $this->resolveAccountCodes($bank['account_code'])));
        $start = $this->str($periodStart);
        $date = $this->str($asOfDate);

        $beginRow = App\ExecuteRow(
            "SELECT COALESCE(SUM(debit - credit), 0) AS bal
             FROM {$this->tableGl}
             WHERE account_code IN ({$codeList}) AND posting_date < {$start}",
            'DB'
        );
        $beginningBalance = round((float) ($beginRow['bal'] ?? 0), 2);

        $rows = App\ExecuteRowsAssociative(
            "SELECT g.gl_id, g.posting_date, g.reference_no, g.description, g.debit, g.credit,
                    c.forced_cleared
             FROM {$this->tableGl} g
             LEFT JOIN {$this->tableCleared} c ON c.gl_id = g.gl_id AND c.bank_account_id = {$id}
             WHERE g.account_code IN ({$codeList}) AND g.posting_date >= {$start} AND g.posting_date <= {$date}
             ORDER BY g.posting_date, g.gl_id",
            'DB'
        ) ?: [];

        foreach ($rows as &$row) {
            $row['debit'] = $this->num($row['debit']);
            $row['credit'] = $this->num($row['credit']);
        }
        unset($row);

        $soaMatches = $this->matchSoaLines($id, $asOfDate, $rows);
        $soaBalance = $this->getLatestSoaBalance($id, $asOfDate);
        $glById = [];
        foreach ($rows as $r) {
            $glById[$r['gl_id']] = $r;
        }

        $running = $beginningBalance;
        $outstandingDeposits = [];
        $outstandingChecks = [];
        foreach ($rows as &$row) {
            $running = round($running + $row['debit'] - $row['credit'], 2);
            $row['balance'] = $running;
            $row['soa_matched'] = isset($soaMatches[$row['gl_id']]);
            $row['overridden'] = $row['forced_cleared'] !== null;
            $row['cleared'] = $row['forced_cleared'] !== null ? (bool) $row['forced_cleared'] : $row['soa_matched'];
            unset($row['forced_cleared']);

            if (!$row['cleared']) {
                if ($row['debit'] > 0) {
                    $outstandingDeposits[] = $row;
                } elseif ($row['credit'] > 0) {
                    $outstandingChecks[] = $row;
                }
            }
        }
        unset($row);

        // Bank side of the same period, for the user to visually match against the
        // book-side rows above — each line flagged with which (if any) GL line it
        // satisfied in matchSoaLines(), or left unmatched (a bank-only item, e.g. a
        // charge/interest the book hasn't recorded yet — see Per Book's Credit
        // Memo/Interest Income/Debit Memo fields).
        $soaIdToGl = array_flip($soaMatches);
        $soaRows = App\ExecuteRowsAssociative(
            "SELECT soa_transaction_id, transaction_date, description, debit_amount, credit_amount, check_no
             FROM {$this->tableSoa}
             WHERE bank_account_id = {$id} AND transaction_date >= {$start} AND transaction_date <= {$date}
             ORDER BY transaction_date, soa_transaction_id",
            'DB'
        ) ?: [];
        foreach ($soaRows as &$soaRow) {
            $soaRow['debit_amount'] = $this->num($soaRow['debit_amount']);
            $soaRow['credit_amount'] = $this->num($soaRow['credit_amount']);
            $matchedGlId = $soaIdToGl[$soaRow['soa_transaction_id']] ?? null;
            $soaRow['matched'] = $matchedGlId !== null;
            $soaRow['matched_reference_no'] = $matchedGlId !== null ? ($glById[$matchedGlId]['reference_no'] ?? null) : null;
        }
        unset($soaRow);

        return $this->ok([
            'has_gl_account' => true,
            'beginning_balance' => $beginningBalance,
            'rows' => $rows,
            'ending_balance' => $running,
            'outstanding_deposits' => $outstandingDeposits,
            'outstanding_checks' => $outstandingChecks,
            'soa_bank_balance' => $soaBalance['balance'],
            'soa_as_of_date' => $soaBalance['as_of_date'],
            'soa_rows' => $soaRows,
        ]);
    }

    // ── Toggle whether one GL line has cleared the bank — always an explicit
    // override (TRUE forces cleared, FALSE forces NOT cleared even if the SOA
    // auto-match says otherwise). See getLedger()'s forced_cleared handling. ──
    public function setCleared($bankAccountId, $glId, bool $cleared)
    {
        $id = (int) $bankAccountId;
        $gl = (int) $glId;
        $userId = (int) App\CurrentUserID();
        App\Execute(
            "INSERT INTO {$this->tableCleared} (bank_account_id, gl_id, forced_cleared, cleared_by)
             VALUES ({$id}, {$gl}, " . ($cleared ? 'TRUE' : 'FALSE') . ", {$userId})
             ON CONFLICT (bank_account_id, gl_id) DO UPDATE SET
                 forced_cleared = EXCLUDED.forced_cleared, cleared_by = EXCLUDED.cleared_by, cleared_at = CURRENT_TIMESTAMP",
            'DB'
        );

        return $this->ok(['gl_id' => $gl, 'cleared' => $cleared]);
    }

    // ── Create / update (upsert by bank_account_id + period) ──
    //
    // Line-up matches NHA's official COA-style Bank Reconciliation Statement
    // (see "03 - alter_table/59 - bank_reconciliation_match_coa_template.sql"):
    //   Per Books Add:  Unrecorded Deposits, Unrecorded Credit Memo, Unrecorded Interest Income,
    //                   Book Error (under recording of deposits / misposting)
    //   Per Books Less: Unrecorded Debit Memo, Unrecorded Withholding Tax,
    //                   Unrecorded Bank Charges/Cost of Checkbooks,
    //                   Book Error (over recording of deposits / misposting)
    //   Per Bank  Add:  Deposit in Transit (auto-derived, see below), Bank Error (under validation)
    //   Per Bank  Less: Outstanding Checks (auto-derived, see below), Bank Error (over validation)
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

        $periodStart = date('Y-m-d', mktime(0, 0, 0, $periodMonth, 1, $periodYear));
        $asOfDate = date('Y-m-t', mktime(0, 0, 0, $periodMonth, 1, $periodYear));

        // ── Per Books ──
        $bookBalance          = $this->num($data['book_balance'] ?? 0);
        $addUnrecordedDeposit = $this->num($data['add_unrecorded_deposit'] ?? 0);
        $addCreditMemo        = $this->num($data['add_credit_memo'] ?? 0);
        $addInterestIncome    = $this->num($data['add_interest_income'] ?? 0);
        $addBookErrorUnder    = $this->num($data['add_book_error_under'] ?? 0);
        $lessDebitMemo        = $this->num($data['less_debit_memo'] ?? 0);
        $lessWithholdingTax   = $this->num($data['less_withholding_tax'] ?? 0);
        $lessBankCharges      = $this->num($data['less_bank_charges'] ?? 0);
        $lessBookErrorOver    = $this->num($data['less_book_error_over'] ?? 0);
        $adjustedBookBalance  = round(
            $bookBalance + $addUnrecordedDeposit + $addCreditMemo + $addInterestIncome + $addBookErrorUnder
            - $lessDebitMemo - $lessWithholdingTax - $lessBankCharges - $lessBookErrorOver,
            2
        );

        // Deposits in transit / outstanding checks come from the ledger's cleared
        // markers, not the client — same "never trust the client for computed
        // totals" rule as adjusted_book_balance/adjusted_bank_balance/status below.
        $ledger = json_decode($this->getLedger($bankAccountId, $periodStart, $asOfDate), true);
        $ledgerData = ($ledger['success'] ?? false) ? $ledger['data'] : ['outstanding_deposits' => [], 'outstanding_checks' => []];
        $addDepositInTransit  = round(array_sum(array_column($ledgerData['outstanding_deposits'], 'debit')), 2);
        $lessOutstandingCheck = round(array_sum(array_column($ledgerData['outstanding_checks'], 'credit')), 2);

        // ── Per Bank ──
        $bankBalance         = $this->num($data['bank_balance'] ?? 0);
        $addBankErrorUnder   = $this->num($data['add_bank_error_under'] ?? 0);
        $lessBankErrorOver   = $this->num($data['less_bank_error_over'] ?? 0);
        $adjustedBankBalance = round(
            $bankBalance + $addDepositInTransit + $addBankErrorUnder - $lessOutstandingCheck - $lessBankErrorOver,
            2
        );

        $status = abs($adjustedBookBalance - $adjustedBankBalance) < 0.01 ? 'RECONCILED' : 'UNRECONCILED';
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $userId = (int) App\CurrentUserID();

        $existing = App\ExecuteRow(
            "SELECT reconciliation_id FROM {$this->tableRecon}
             WHERE bank_account_id = {$bankAccountId} AND period_year = {$periodYear} AND period_month = {$periodMonth}",
            'DB'
        );

        $values = "{$bankAccountId}, {$periodYear}, {$periodMonth}, " . $this->str($asOfDate) . ", "
            . "{$bookBalance}, {$addUnrecordedDeposit}, {$lessBankCharges}, {$addCreditMemo}, {$addInterestIncome}, {$lessDebitMemo}, {$adjustedBookBalance}, "
            . "{$bankBalance}, {$addDepositInTransit}, {$lessOutstandingCheck}, {$adjustedBankBalance}, "
            . $this->str($status) . ', ' . ($remarks === '' ? 'NULL' : $this->str($remarks)) . ", {$userId}, "
            . "{$lessWithholdingTax}, {$addBookErrorUnder}, {$lessBookErrorOver}, {$addBankErrorUnder}, {$lessBankErrorOver}";

        if ($existing) {
            $id = (int) $existing['reconciliation_id'];
            App\Execute(
                "UPDATE {$this->tableRecon} SET
                    as_of_date = " . $this->str($asOfDate) . ",
                    book_balance = {$bookBalance}, add_unrecorded_deposit = {$addUnrecordedDeposit},
                    less_bank_charges = {$lessBankCharges}, add_credit_memo = {$addCreditMemo},
                    add_interest_income = {$addInterestIncome}, less_debit_memo = {$lessDebitMemo},
                    less_withholding_tax = {$lessWithholdingTax}, add_book_error_under = {$addBookErrorUnder},
                    less_book_error_over = {$lessBookErrorOver},
                    adjusted_book_balance = {$adjustedBookBalance},
                    bank_balance = {$bankBalance}, add_deposit_in_transit = {$addDepositInTransit},
                    less_outstanding_check = {$lessOutstandingCheck},
                    add_bank_error_under = {$addBankErrorUnder}, less_bank_error_over = {$lessBankErrorOver},
                    adjusted_bank_balance = {$adjustedBankBalance},
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
                     book_balance, add_unrecorded_deposit, less_bank_charges, add_credit_memo, add_interest_income, less_debit_memo, adjusted_book_balance,
                     bank_balance, add_deposit_in_transit, less_outstanding_check, adjusted_bank_balance,
                     status, remarks, prepared_by,
                     less_withholding_tax, add_book_error_under, less_book_error_over, add_bank_error_under, less_bank_error_over)
                 VALUES ({$values})
                 RETURNING reconciliation_id",
                'DB'
            );
            $id = (int) $row['reconciliation_id'];
        }

        return $this->ok(['reconciliation_id' => $id, 'status' => $status, 'adjusted_book_balance' => $adjustedBookBalance, 'adjusted_bank_balance' => $adjustedBankBalance]);
    }

    // Per Books item key => [label, side ('add'|'less'), default contra account_code or null].
    // 'add' items increase Cash (Dr Cash / Cr contra); 'less' items decrease Cash (Dr contra / Cr Cash).
    // Bank Error (under/over) is NOT here — it's the bank's own error to correct on ITS books, not
    // NHA's, so it never generates a JEV.
    private const JEV_ITEMS = [
        'add_unrecorded_deposit' => ['label' => 'Unrecorded Deposits',                       'side' => 'add',  'default_account' => null],
        'add_credit_memo'        => ['label' => 'Unrecorded Credit Memo',                    'side' => 'add',  'default_account' => null],
        'add_interest_income'    => ['label' => 'Unrecorded Interest Income',                'side' => 'add',  'default_account' => '4-02-02-210'], // Interest Income
        'add_book_error_under'   => ['label' => 'Book Error (under recording / misposting)', 'side' => 'add',  'default_account' => null],
        'less_debit_memo'        => ['label' => 'Unrecorded Debit Memo',                     'side' => 'less', 'default_account' => null],
        'less_withholding_tax'   => ['label' => 'Unrecorded Withholding Tax',                'side' => 'less', 'default_account' => '1-99-02-080'], // Withholding Tax at Source
        'less_bank_charges'      => ['label' => 'Unrecorded Bank Charges/Cost of Checkbooks', 'side' => 'less', 'default_account' => '5-03-01-040'], // Bank Charges
        'less_book_error_over'   => ['label' => 'Book Error (over recording / misposting)',  'side' => 'less', 'default_account' => null],
    ];

    /**
     * Per Books items with a non-zero amount on a saved reconciliation — what the "Adjusting Journal
     * Entry" panel offers to book, each with its suggested contra account (null when there is no
     * confident default — the preparer must pick one). auto_postable is true when every item already
     * resolves to an account (all defaults present) AND the reconciliation is RECONCILED — the two
     * conditions postAdjustingJev() itself requires — so the page can fire the posting automatically
     * without waiting for a click when nothing is missing (see loadJevPanel() in the page script).
     */
    public function getJevPreview($id)
    {
        $row = $this->fetch((int) $id);
        if (!$row) {
            return $this->fail('Reconciliation not found.');
        }
        if (!empty($row['jev_id'])) {
            $jevStatus = App\ExecuteScalar(
                "SELECT st.name FROM {$this->tableJev} jev
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.jev_id = " . (int) $row['jev_id'],
                'DB'
            );

            return $this->ok(['already_posted' => true, 'jev_id' => (int) $row['jev_id'], 'jev_no' => $row['jev_no'], 'jev_status' => $jevStatus]);
        }

        $items = [];
        $autoPostable = $row['status'] === 'RECONCILED';
        foreach (self::JEV_ITEMS as $key => $meta) {
            $amount = $this->num($row[$key] ?? 0);
            if ($amount > 0) {
                $items[] = ['key' => $key, 'label' => $meta['label'], 'side' => $meta['side'], 'amount' => round($amount, 2), 'default_account' => $meta['default_account']];
                if ($meta['default_account'] === null) {
                    $autoPostable = false;
                }
            }
        }

        return $this->ok(['already_posted' => false, 'status' => $row['status'], 'items' => $items, 'auto_postable' => $autoPostable && !empty($items)]);
    }

    /**
     * Posts the adjusting Journal Entry Voucher for a saved reconciliation's non-zero Per Books
     * items (see JEV_ITEMS/getJevPreview()) straight to the General Ledger — one line per item
     * (credited for an Add item, debited for a Less item) against the contra account the preparer
     * chose, plus one net Cash line against the bank account's own GL account_code. Only allowed
     * once the reconciliation is RECONCILED (Adjusted Book Balance = Adjusted Bank Balance) — that's
     * the whole point of this worksheet: post the adjustments once they're proven to explain the gap,
     * not before. Delegates the JEV itself to DisbursementVoucherService::createStandaloneJev() (same
     * standalone-JEV path Property Record and Infra IAR already use for numbering/validation), then
     * immediately posts it via GeneralLedgerService::postJev() — same two-step most JEVs go through,
     * just chained here instead of left for Accounting to post separately from the JEV screen.
     *
     * $contraAccounts: [item_key => account_code, ...] — required for every non-zero item that has
     * no default_account (see JEV_ITEMS); a default may still be overridden.
     */
    public function postAdjustingJev($id, int $bookId, array $contraAccounts, int $userId)
    {
        $row = $this->fetch((int) $id);
        if (!$row) {
            return $this->fail('Reconciliation not found.');
        }
        if (!empty($row['jev_id'])) {
            return $this->fail("This reconciliation already has adjusting JEV {$row['jev_no']} — a reconciliation can only post one.");
        }
        if ($row['status'] !== 'RECONCILED') {
            return $this->fail('This reconciliation is Unreconciled — the Adjusted Book Balance and Adjusted Bank Balance must match before an adjusting entry can post.');
        }

        $bank = App\ExecuteRow(
            "SELECT account_code FROM {$this->tableBankAccounts} WHERE bank_account_id = " . (int) $row['bank_account_id'],
            'DB'
        );
        if (!$bank || empty($bank['account_code'])) {
            return $this->fail('This bank account has no GL account tagged — tag one in Bank Account Management before posting an adjusting entry.');
        }
        $cashAccountCode = $bank['account_code'];

        $lines = [];
        $netCash = 0.0;
        foreach (self::JEV_ITEMS as $key => $meta) {
            $amount = round($this->num($row[$key] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $accountCode = trim((string) ($contraAccounts[$key] ?? $meta['default_account'] ?? ''));
            if ($accountCode === '') {
                return $this->fail("Select a contra account for \"{$meta['label']}\".");
            }
            $account = App\ExecuteRow("SELECT posting_allowed FROM {$this->tableCoa} WHERE account_code = " . $this->str($accountCode), 'DB');
            if (!$account) {
                return $this->fail("Account code '{$accountCode}' does not exist in the chart of accounts.");
            }
            if (!$account['posting_allowed']) {
                return $this->fail("Account code '{$accountCode}' is not postable (it's a summary/header account).");
            }

            if ($meta['side'] === 'add') {
                $lines[] = ['account_code' => $accountCode, 'debit' => 0, 'credit' => $amount, 'description' => $meta['label']];
                $netCash += $amount;
            } else {
                $lines[] = ['account_code' => $accountCode, 'debit' => $amount, 'credit' => 0, 'description' => $meta['label']];
                $netCash -= $amount;
            }
        }

        if (!$lines) {
            return $this->fail('Nothing to post — every Per Books Add/Less item on this reconciliation is zero.');
        }

        $netCash = round($netCash, 2);
        $cashLine = $netCash > 0
            ? ['account_code' => $cashAccountCode, 'debit' => $netCash, 'credit' => 0, 'description' => 'Cash in Bank — Bank Reconciliation adjusting entry']
            : ['account_code' => $cashAccountCode, 'debit' => 0, 'credit' => -$netCash, 'description' => 'Cash in Bank — Bank Reconciliation adjusting entry'];
        array_unshift($lines, $cashLine);

        $particulars = 'Bank Reconciliation adjusting entry — ' . $row['bank_name'] . ' ' . $row['account_name']
            . ' (' . str_pad((string) $row['period_month'], 2, '0', STR_PAD_LEFT) . '/' . $row['period_year'] . ')';

        $voucher = new DisbursementVoucherService();
        $result = $voucher->createStandaloneJev([
            'jev_date'    => $row['as_of_date'],
            'book_id'     => $bookId,
            'particulars' => $particulars,
            'lines'       => $lines,
        ], $userId);

        if (!($result['success'] ?? false)) {
            return json_encode($result);
        }

        $jevId = (int) $result['data']['jev_id'];
        $jevNo = $result['data']['jev_no'];
        // Link it to the reconciliation regardless of whether the GL post below succeeds — the JEV
        // already exists either way, and "already has adjusting JEV X" must keep this from re-posting.
        App\Execute(
            "UPDATE {$this->tableRecon} SET jev_id = {$jevId}, jev_no = " . $this->str($jevNo) . ', updated_at = CURRENT_TIMESTAMP
             WHERE reconciliation_id = ' . (int) $id,
            'DB'
        );

        $gl = new GeneralLedgerService();
        $postResult = $gl->postJev($jevId, $userId);
        if (!($postResult['success'] ?? false)) {
            return $this->ok(['jev_id' => $jevId, 'jev_no' => $jevNo, 'posted' => false, 'message' => "Adjusting JEV {$jevNo} was created but could not be posted to the General Ledger automatically: " . ($postResult['message'] ?? 'unknown error') . ' Post it manually from the Journal Entry Voucher screen.']);
        }

        return $this->ok(['jev_id' => $jevId, 'jev_no' => $jevNo, 'posted' => true, 'message' => "Adjusting JEV {$jevNo} posted to the General Ledger."]);
    }
}
