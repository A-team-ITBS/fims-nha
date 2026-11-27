<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs the Cash Position module (app/pages/cash-position-page.php):
 * cashpositionupdates.php and availablecashbalancestracking.php
 * (_assets/TREASURY/app/services/CashPosUpServices.php /
 * AvailableCashBalancesServices.php), plus a Reports tab. A fourth tab,
 * Dashboard (cashpositiondashboard.php / CashPosDasboardServices.php), was
 * ported here too but has since been removed — getDashboardKpis(),
 * getDashboardList(), and getAccountDetail() went with it, along with their
 * routes in app/routes/cash-position.php.
 *
 * Schema differences from TREASURY, all deliberate (matching this
 * project's existing Bank Account Management conventions, not TREASURY's):
 *   - No shared.fund_sources table — fund_source is a plain text field on
 *     fims.bank_accounts (grouping is by that string, not a fund_source_id
 *     FK). fims.cash_position therefore has no fund_source_id of its own
 *     either; a position row's fund source is always its account's.
 *   - No uac.departments "region" filter — "Region/Office" is the same
 *     Responsibility Center (fims.responsibility_code) used everywhere
 *     else in this project (Bank Account Management, budget allocation).
 *   - Total Receipts/Disbursements ARE auto-computed from the real ledger
 *     (fims.bank_transactions, populated by SOA uploads — see
 *     CashPositionSoaService) via getComputedBalance(), same as TREASURY:
 *     readonly in the Manual Entry form unless Update Type = COLLECTION.
 *     update_type (RECEIPT|DISBURSEMENT|TRANSFER|ADJUSTMENT|COLLECTION|
 *     SOA_UPLOAD) is required on save, same as TREASURY's
 *     saveManualEntry() — but, same as TREASURY, it is never persisted as
 *     its own column; it only gates which fields are editable.
 *   - Bank Statement Balance auto-pulls from the latest SOA upload but
 *     stays editable (not TREASURY's hard readonly), since a manual
 *     correction is still a legitimate use case here.
 *   - No WEEKLY aggregation (TREASURY's cashPositionSource() CTE) — only
 *     an exact-date or per-account-latest-as-of-date view.
 *   - "Outstanding checks" (Available Cash Balances tab) comes from this
 *     project's fims.check_register.current_status IN ('ISSUED',
 *     'OUTSTANDING') — the closest equivalent to TREASURY's ('ISSUED',
 *     'RELEASED'), since this project's register vocabulary has no
 *     RELEASED status (that belongs to fims.checks' own, separate status
 *     machine — see CheckProcessingService's docblock).
 *   - available_balance is a DB-generated column (closing_balance -
 *     restricted_amount) — this actually fixes a real gap flagged in
 *     TREASURY's own saveManualEntry()/updateCashPosition(), which never
 *     wrote that column at all despite every report reading it.
 */
class CashPositionService
{
    private string $tableCashPosition = 'fims.cash_position';
    private string $tableBankAccounts = 'fims.bank_accounts';
    private string $tableCheckRegister = 'fims.check_register';
    private string $tableResponsibilityCode = 'fims.responsibility_code';
    private string $tableActivityLog = 'fims.cash_position_activity_log';
    private string $tableBankTransactions = 'fims.bank_transactions';
    private string $tableChartOfAccounts = 'fims.chart_of_accounts';
    private string $tableViewJournals = 'fims.v_journals';
    private string $tableOpeningBalances = 'fims.opening_balances';
    private string $tableChecks = 'fims.checks';
    private string $tableFundTransfers = 'fims.fund_transfers';

    private const UPDATE_TYPES = ['RECEIPT', 'DISBURSEMENT', 'TRANSFER', 'ADJUSTMENT', 'COLLECTION', 'SOA_UPLOAD'];
    // Same normal-balance convention as GeneralLedgerService::DEBIT_NORMAL_TYPES —
    // duplicated here (rather than instantiating that service) since this is
    // the only GL read CashPositionService needs.
    private const DEBIT_NORMAL_TYPES = ['Asset', 'Expense'];

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

    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
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

    // LEFT JOIN LATERAL onto fims.cash_position for one bank account:
    // exact-date match when $asOfDate is given, else that account's most
    // recent row on/before CURRENT_DATE (TREASURY's "per-account latest"
    // default behavior when no position_date filter is supplied).
    private function cashPositionJoin(string $asOfDate): string
    {
        if ($asOfDate !== '') {
            $date = $this->str($asOfDate);

            return "LEFT JOIN {$this->tableCashPosition} cp ON cp.bank_account_id = ba.bank_account_id AND cp.position_date = {$date}";
        }

        return "LEFT JOIN LATERAL (
                    SELECT * FROM {$this->tableCashPosition} cp2
                    WHERE cp2.bank_account_id = ba.bank_account_id AND cp2.position_date <= CURRENT_DATE
                    ORDER BY cp2.position_date DESC LIMIT 1
                ) cp ON TRUE";
    }

    private function accountWhere($filters): array
    {
        $where = ["ba.status <> 'CLOSED'"];
        $fundSource = trim((string) ($filters['fund_source'] ?? ''));
        if ($fundSource !== '') {
            $where[] = 'ba.fund_source = ' . $this->str($fundSource);
        }
        $rcId = trim((string) ($filters['rc_id'] ?? ''));
        if ($rcId !== '') {
            $where[] = 'ba.rc_id = ' . $this->str($rcId);
        }
        $bankAccountId = (int) ($filters['bank_account_id'] ?? 0);
        if ($bankAccountId > 0) {
            $where[] = "ba.bank_account_id = {$bankAccountId}";
        }

        return $where;
    }

    // ── Lookups (shared across all 3 tabs) ──
    public function getFundSourcesLookup()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT DISTINCT fund_source FROM {$this->tableBankAccounts}
             WHERE fund_source IS NOT NULL AND fund_source <> '' ORDER BY fund_source",
            'DB'
        );

        return $this->ok(array_map(fn ($r) => $r['fund_source'], $rows));
    }

    public function getRegionsLookup()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT rc_id, rc_code, rc_name FROM {$this->tableResponsibilityCode} WHERE status = 'Active' ORDER BY rc_code",
            'DB'
        );

        return $this->ok($rows);
    }

    public function getBankAccountsLookup()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT bank_account_id, account_name, account_number, bank_name, fund_source
             FROM {$this->tableBankAccounts} WHERE status = 'ACTIVE' ORDER BY account_name",
            'DB'
        );

        return $this->ok($rows);
    }

    // ── Position Updates ──
    public function getUpdatesKpis()
    {
        $row = App\ExecuteRow(
            "SELECT
                COALESCE(SUM(cp.opening_balance), 0) AS total_opening,
                COALESCE(SUM(cp.closing_balance), 0) AS total_ending,
                COALESCE(SUM(cp.restricted_amount), 0) AS total_restricted,
                COALESCE(SUM(cp.available_balance), 0) AS total_available,
                COUNT(DISTINCT ba.bank_account_id) AS total_accounts
             FROM {$this->tableBankAccounts} ba
             {$this->cashPositionJoin('')}
             WHERE ba.status = 'ACTIVE'",
            'DB'
        );

        return $this->ok([
            'total_opening' => $this->num($row['total_opening'] ?? 0),
            'total_ending' => $this->num($row['total_ending'] ?? 0),
            'total_restricted' => $this->num($row['total_restricted'] ?? 0),
            'total_available' => $this->num($row['total_available'] ?? 0),
            'total_accounts' => (int) ($row['total_accounts'] ?? 0),
        ]);
    }

    public function getUpdatesList($params)
    {
        $asOfDate = trim((string) ($params['position_date'] ?? ''));
        $where = implode(' AND ', array_merge(["ba.status = 'ACTIVE'"], array_slice($this->accountWhere($params), 1)));
        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $needle = $this->str('%' . $search . '%');
            $where .= " AND (ba.account_name ILIKE {$needle} OR ba.account_number ILIKE {$needle} OR ba.bank_name ILIKE {$needle})";
        }

        $rows = App\ExecuteRowsAssociative(
            "SELECT cp.cash_position_id, COALESCE(cp.position_date, " . ($asOfDate !== '' ? $this->str($asOfDate) : 'CURRENT_DATE') . "::date) AS position_date,
                    ba.bank_account_id, ba.account_number, ba.account_name, ba.bank_name, ba.bank_branch, ba.fund_source,
                    COALESCE(cp.opening_balance,0) AS opening_balance, COALESCE(cp.total_receipts,0) AS total_receipts,
                    COALESCE(cp.total_disbursements,0) AS total_disbursements, COALESCE(cp.closing_balance,0) AS closing_balance,
                    cp.bank_statement_balance, COALESCE(cp.restricted_amount,0) AS restricted_amount,
                    COALESCE(cp.available_balance,0) AS available_balance, cp.reconciled, cp.reference_number, cp.remarks
             FROM {$this->tableBankAccounts} ba
             {$this->cashPositionJoin($asOfDate)}
             WHERE {$where}
             ORDER BY ba.bank_name, ba.account_name",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    // Row-click drill-down from the Position Updates list: every real
    // disbursement this system itself recorded against this bank account on
    // this exact date — checks released, FTLA fund transfers, any other
    // GL-posted credit to the account's tagged Cash code.
    //
    // Reads fims.v_journals (same GL-backed view glBalanceAsOf() already
    // uses for this account), NOT fims.bank_transactions — that table was
    // designed to carry exactly this (transaction_type CHECK|COLLECTION|
    // FTLA|INTEREST, see "01 - table/37 - fims_soa_bank_transactions.sql"'s
    // own docblock) but nothing actually populates it any more: the only
    // writer was CashPositionSoaService::confirmUpload()'s old per-SOA-line
    // auto-post, which was removed once SOA upload stopped touching this
    // ledger (see that service's own comments) — so this drill-down always
    // came back empty. v_journals already carries every check/FTLA payment
    // (they post through GeneralLedgerService::createPaymentJev()/postJev())
    // without needing a second, separately-maintained ledger table at all.
    //
    // A disbursement is a CREDIT to the account's Cash GL code (an asset
    // account — debit increases it, credit decreases it), same convention
    // BankReconciliationService::getLedger() uses.
    //
    // transaction_type/counterpart are inferred, not stored: dv_no joined
    // back to fims.checks (via dv_number) names the actual check/payee when
    // one exists; otherwise doc_ref_no matched to fims.fund_transfers.
    // ftla_number identifies an FTLA transfer. Neither join fires for a
    // plain adjustment JEV, which is why the type still falls back to 'GL'.
    public function getDisbursementTransactions(int $bankAccountId, string $positionDate): string
    {
        if ($bankAccountId <= 0 || $positionDate === '') {
            return $this->ok([]);
        }
        $account = App\ExecuteRow(
            "SELECT account_code FROM {$this->tableBankAccounts} WHERE bank_account_id = {$bankAccountId}",
            'DB'
        );
        $accountCode = trim((string) ($account['account_code'] ?? ''));
        if ($accountCode === '') {
            return $this->ok([]);
        }

        $rows = App\ExecuteRowsAssociative(
            "SELECT v.gl_id, v.jev_no, v.reference_no, v.description, v.credit AS amount,
                    chk.check_number, chk.payee, ft.ftla_number, ft.purpose AS ftla_purpose
             FROM {$this->tableViewJournals} v
             LEFT JOIN {$this->tableChecks} chk ON chk.dv_number = v.dv_no AND chk.bank_account_id = {$bankAccountId}
             LEFT JOIN {$this->tableFundTransfers} ft ON ft.ftla_number = v.doc_ref_no
             WHERE v.account_code = " . $this->str($accountCode) . '
               AND v.posting_date = ' . $this->str($positionDate) . '
               AND v.credit > 0
             ORDER BY v.gl_id',
            'DB'
        ) ?: [];

        foreach ($rows as &$row) {
            $row['transaction_id'] = $row['jev_no'] ?: ('GL-' . $row['gl_id']);
            $row['reference_number'] = $row['reference_no'] ?: $row['jev_no'];
            $row['transaction_type'] = $row['check_number'] ? 'CHECK' : ($row['ftla_number'] ? 'FTLA' : 'GL');
            $row['counterpart'] = $row['payee'] ?: $row['ftla_purpose'];
            $row['status'] = 'POSTED';
            $row['amount'] = $this->num($row['amount']);
            unset($row['gl_id'], $row['jev_no'], $row['reference_no'], $row['check_number'], $row['payee'], $row['ftla_number'], $row['ftla_purpose']);
        }
        unset($row);

        return $this->ok($rows);
    }

    public function getCashPositionById($id)
    {
        $row = App\ExecuteRow(
            "SELECT cp.*, ba.account_name, ba.account_number, ba.bank_name, ba.fund_source
             FROM {$this->tableCashPosition} cp
             JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = cp.bank_account_id
             WHERE cp.cash_position_id = " . (int) $id,
            'DB'
        );
        if (!$row) {
            return json_encode(["success" => false, "message" => "Record not found."]);
        }
        if ($row['restricted_breakdown']) {
            $row['restricted_breakdown'] = json_decode($row['restricted_breakdown'], true);
        }

        return $this->ok($row);
    }

    // Prior day's closing_balance, rolled forward as the new day's opening
    // balance (same rollforward concept used by Check Preparation's cash
    // sufficiency check) — PLUS, now that fims.bank_transactions is real
    // (populated by SOA uploads), that date's actual receipts/
    // disbursements summed from the ledger, exactly matching TREASURY's
    // getComputedBalance(). The Manual Entry form pre-fills and locks all
    // three fields from this (unless Update Type = COLLECTION, which
    // leaves Total Receipts editable).
    //
    // When there's no prior fims.cash_position row at all (this account's
    // very first entry), Beginning Balance now falls back to this bank
    // account's own tagged GL account's balance as of the day before
    // position_date (glBalanceAsOf()) rather than asking for a blind
    // manual guess — only genuinely untagged accounts (no coa_account_id
    // tagged via Bank Account Management's GL/SL picker) still fall
    // through to a manual entry.
    public function getComputedBalance($bankAccountId, $positionDate)
    {
        $bankAccountId = (int) $bankAccountId;
        $positionDate = trim((string) $positionDate);
        if ($bankAccountId <= 0 || $positionDate === '') {
            return $this->ok(['opening_balance' => null, 'opening_balance_source' => 'NONE', 'total_receipts' => 0, 'total_disbursements' => 0]);
        }
        $prior = App\ExecuteRow(
            "SELECT closing_balance FROM {$this->tableCashPosition}
             WHERE bank_account_id = {$bankAccountId} AND position_date < " . $this->str($positionDate) . "
             ORDER BY position_date DESC LIMIT 1",
            'DB'
        );

        if ($prior) {
            $openingBalance = (float) $prior['closing_balance'];
            $source = 'PRIOR_CLOSING';
        } else {
            $openingBalance = $this->openingBalanceFromTable($bankAccountId, $positionDate);
            if ($openingBalance !== null) {
                $source = 'OPENING_BALANCE';
            } else {
                $openingBalance = $this->glBalanceAsOf($bankAccountId, $positionDate);
                $source = $openingBalance !== null ? 'GENERAL_LEDGER' : 'NONE';
            }
        }

        $ledger = App\ExecuteRow(
            "SELECT COALESCE(SUM(amount) FILTER (WHERE direction = 'INFLOW'), 0) AS total_receipts,
                    COALESCE(SUM(amount) FILTER (WHERE direction != 'INFLOW'), 0) AS total_disbursements
             FROM {$this->tableBankTransactions}
             WHERE bank_account_id = {$bankAccountId} AND transaction_date = " . $this->str($positionDate),
            'DB'
        );

        return $this->ok([
            'opening_balance' => $openingBalance,
            'opening_balance_source' => $source,
            'total_receipts' => $this->num($ledger['total_receipts'] ?? 0),
            'total_disbursements' => $this->num($ledger['total_disbursements'] ?? 0),
        ]);
    }

    // First-tier fallback for a bank account's Beginning Balance when
    // there's no prior day's closing_balance yet: fims.opening_balances'
    // debit column for that account's tagged GL account code, summed
    // across every book (fiscal_yr + book_id + account_code) for the
    // position_date's fiscal year — this project's fiscal year is just the
    // calendar year (fims.fiscal_year.fiscal_yr has no date-range columns),
    // so no separate fiscal-year-lookup table is needed. Summed across all
    // books rather than one specific book_id: fims.chart_of_accounts.books
    // is a free-text, comma-separated tag (e.g. "MO, CISFA, NGC") that
    // doesn't even match fims.books.code exactly, so there's no reliable
    // single book_id to pick for a given bank account. Returns null — not
    // 0 — when no opening_balances row exists at all for that account/year
    // (as opposed to one existing with a legitimately zero debit), so
    // getComputedBalance() can fall through to glBalanceAsOf() below.
    private function openingBalanceFromTable(int $bankAccountId, string $positionDate): ?float
    {
        $account = App\ExecuteRow(
            "SELECT account_code FROM {$this->tableBankAccounts} WHERE bank_account_id = {$bankAccountId}",
            'DB'
        );
        $accountCode = trim((string) ($account['account_code'] ?? ''));
        if ($accountCode === '') {
            return null;
        }
        $fiscalYr = (int) substr($positionDate, 0, 4);

        $row = App\ExecuteRow(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(debit), 0) AS total_debit
             FROM {$this->tableOpeningBalances}
             WHERE account_code = " . $this->str($accountCode) . " AND fiscal_yr = {$fiscalYr}",
            'DB'
        );
        if ((int) ($row['cnt'] ?? 0) === 0) {
            return null;
        }

        return $this->num($row['total_debit'] ?? 0);
    }

    // Called by OpeningBalanceService::saveOpeningBalance() right after it
    // commits that fiscal year's lines directly into fims.opening_balances
    // (no JEV involved — see that service's own docblock) — refreshes the
    // opening_balance (and recomputed closing_balance) of every bank account
    // tagged to this GL account_code's EARLIEST fims.cash_position row for
    // that same fiscal year, CREATING one (dated fiscal_yr-01-01, the
    // calendar-year fiscal year's own first day) if the account doesn't have
    // any row for that year yet — a brand-new account/year otherwise has no
    // row for this to update at all, so Cash Position would stay silently
    // blank forever despite a real opening balance being on record. Only
    // this one row per account ever actually originates from an opening
    // balance — every later day's "opening balance" is just the prior day's
    // closing_balance (see getComputedBalance()), so there's nothing to
    // cascade forward. A no-op only when the account isn't tagged to this
    // code at all.
    public function refreshEarliestOpeningBalanceForAccountCode(string $accountCode, int $fiscalYr, float $debitTotal, ?int $createdBy = null): void
    {
        $accountCode = trim($accountCode);
        if ($accountCode === '' || $fiscalYr <= 0) {
            return;
        }
        $accounts = App\ExecuteRowsAssociative(
            "SELECT bank_account_id FROM {$this->tableBankAccounts} WHERE account_code = " . $this->str($accountCode),
            'DB'
        ) ?: [];

        foreach ($accounts as $acct) {
            $bankAccountId = (int) $acct['bank_account_id'];
            $earliest = App\ExecuteRow(
                "SELECT cash_position_id, total_receipts, total_disbursements, adjustment_amount, adjustment_direction
                 FROM {$this->tableCashPosition}
                 WHERE bank_account_id = {$bankAccountId} AND EXTRACT(YEAR FROM position_date) = {$fiscalYr}
                 ORDER BY position_date ASC LIMIT 1",
                'DB'
            );

            if (!$earliest) {
                App\Execute(
                    "INSERT INTO {$this->tableCashPosition}
                        (bank_account_id, position_date, opening_balance, total_receipts, total_disbursements, closing_balance, created_by)
                     VALUES ({$bankAccountId}, " . $this->str("{$fiscalYr}-01-01") . ', '
                        . $this->num($debitTotal) . ', 0, 0, ' . $this->num($debitTotal) . ', ' . ($createdBy ? (int) $createdBy : 'NULL') . ')
                     ON CONFLICT (bank_account_id, position_date) DO NOTHING',
                    'DB'
                );
                continue;
            }

            // Same formula as computeManualEntryFields() — opening +
            // receipts - disbursements, signed adjustment.
            $adjAmount = $this->num($earliest['adjustment_amount'] ?? 0);
            $adjDirection = strtoupper(trim((string) ($earliest['adjustment_direction'] ?? '')));
            $signedAdj = $adjDirection === 'DEDUCTION' ? -$adjAmount : $adjAmount;
            $receipts = $this->num($earliest['total_receipts'] ?? 0);
            $disbursements = $this->num($earliest['total_disbursements'] ?? 0);
            $closing = $debitTotal + $receipts - $disbursements + $signedAdj;

            App\Execute(
                "UPDATE {$this->tableCashPosition} SET
                    opening_balance = " . $this->num($debitTotal) . ",
                    closing_balance = " . $this->num($closing) . "
                 WHERE cash_position_id = " . (int) $earliest['cash_position_id'],
                'DB'
            );
        }
    }

    // This bank account's tagged GL account's balance as of the day before
    // $positionDate (every posting strictly before it) — same normal-
    // balance sign convention as GeneralLedgerService::fetchGeneralLedger()
    // (Asset/Expense accounts are debit-normal; everything else is
    // credit-normal). fims.bank_accounts.account_code is kept in sync with
    // whatever GL account Bank Account Management's two-step picker tags
    // (see BankManagementService::accountCodeForId()), so this is always
    // the same account_code that module shows as "tagged." Returns null —
    // not 0 — when the account isn't tagged to any GL account at all, so
    // the caller can tell "genuinely untagged" apart from "tagged, zero
    // balance so far."
    private function glBalanceAsOf(int $bankAccountId, string $positionDate): ?float
    {
        $account = App\ExecuteRow(
            "SELECT account_code FROM {$this->tableBankAccounts} WHERE bank_account_id = {$bankAccountId}",
            'DB'
        );
        $accountCode = trim((string) ($account['account_code'] ?? ''));
        if ($accountCode === '') {
            return null;
        }
        $coa = App\ExecuteRow(
            "SELECT account_type FROM {$this->tableChartOfAccounts} WHERE account_code = " . $this->str($accountCode),
            'DB'
        );
        if (!$coa) {
            return null;
        }
        $sign = in_array($coa['account_type'], self::DEBIT_NORMAL_TYPES, true) ? '(debit - credit)' : '(credit - debit)';

        $row = App\ExecuteRow(
            "SELECT COALESCE(SUM({$sign}), 0) AS balance
             FROM {$this->tableViewJournals}
             WHERE account_code = " . $this->str($accountCode) . ' AND posting_date < ' . $this->str($positionDate),
            'DB'
        );

        return $this->num($row['balance'] ?? 0);
    }

    // Upsert keyed on (bank_account_id, position_date) — this project's
    // fims.cash_position has a real unique index on that pair (no
    // fund_source dimension needed, since fund_source lives on the bank
    // account, not the position row), so this is a plain ON CONFLICT
    // instead of TREASURY's SELECT-then-INSERT/UPDATE dance.
    public function saveManualEntry($data)
    {
        $bankAccountId = (int) ($data['bank_account_id'] ?? 0);
        $positionDate = trim((string) ($data['position_date'] ?? ''));
        $updateType = strtoupper(trim((string) ($data['update_type'] ?? '')));
        if ($bankAccountId <= 0) {
            return json_encode(["success" => false, "message" => "Select a bank account."]);
        }
        if ($positionDate === '') {
            return json_encode(["success" => false, "message" => "Position date is required."]);
        }
        if (!in_array($updateType, self::UPDATE_TYPES, true)) {
            return json_encode(["success" => false, "message" => "Select a valid Update Type."]);
        }
        if (trim((string) ($data['opening_balance'] ?? '')) === '') {
            return json_encode(["success" => false, "message" => "Beginning balance is required."]);
        }
        if (!App\ExecuteRow("SELECT bank_account_id FROM {$this->tableBankAccounts} WHERE bank_account_id = {$bankAccountId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Bank account not found."]);
        }

        [$closing, $restricted, $breakdownJson] = $this->computeManualEntryFields($data);
        $userId = (int) App\CurrentUserID();

        $row = App\ExecuteRow(
            "INSERT INTO {$this->tableCashPosition}
                (bank_account_id, position_date, opening_balance, total_receipts, total_disbursements, closing_balance,
                 bank_statement_balance, restricted_amount, restricted_breakdown,
                 adjustment_amount, adjustment_direction, adjustment_details,
                 reconciled, reference_number, remarks, created_by)
             VALUES (
                {$bankAccountId}, " . $this->str($positionDate) . ', ' . $this->num($data['opening_balance'] ?? 0) . ', '
                . $this->num($data['total_receipts'] ?? 0) . ', ' . $this->num($data['total_disbursements'] ?? 0) . ", {$closing}, "
                . (trim((string) ($data['bank_statement_balance'] ?? '')) === '' ? 'NULL' : $this->num($data['bank_statement_balance'])) . ", {$restricted}, "
                . $breakdownJson . ', ' . $this->num($data['adjustment_amount'] ?? 0) . ', '
                . $this->strOrNull($data['adjustment_direction'] ?? null) . ', ' . $this->strOrNull($data['adjustment_details'] ?? null) . ', '
                . (filter_var($data['reconciled'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE') . ', '
                . $this->strOrNull($data['reference_number'] ?? null) . ', ' . $this->strOrNull($data['remarks'] ?? null) . ", {$userId})
             ON CONFLICT (bank_account_id, position_date) DO UPDATE SET
                opening_balance = EXCLUDED.opening_balance, total_receipts = EXCLUDED.total_receipts,
                total_disbursements = EXCLUDED.total_disbursements, closing_balance = EXCLUDED.closing_balance,
                bank_statement_balance = EXCLUDED.bank_statement_balance,
                restricted_amount = EXCLUDED.restricted_amount, restricted_breakdown = EXCLUDED.restricted_breakdown,
                adjustment_amount = EXCLUDED.adjustment_amount, adjustment_direction = EXCLUDED.adjustment_direction,
                adjustment_details = EXCLUDED.adjustment_details, reconciled = EXCLUDED.reconciled,
                reference_number = EXCLUDED.reference_number, remarks = EXCLUDED.remarks
             RETURNING cash_position_id, (xmax = 0) AS inserted",
            'DB'
        );

        $this->writeActivityLog(
            $bankAccountId,
            $positionDate,
            $row['inserted'] === 't' || $row['inserted'] === true ? 'CREATE' : 'UPDATE',
            "Ending balance set to " . number_format($closing, 2),
            $userId
        );

        return $this->ok(['cash_position_id' => $row['cash_position_id']]);
    }

    public function updateCashPosition($id, $data)
    {
        $id = (int) $id;
        $existing = App\ExecuteRow("SELECT bank_account_id, position_date FROM {$this->tableCashPosition} WHERE cash_position_id = {$id}", 'DB');
        if (!$existing) {
            return json_encode(["success" => false, "message" => "Record not found."]);
        }
        [$closing, $restricted, $breakdownJson] = $this->computeManualEntryFields($data);

        App\Execute(
            "UPDATE {$this->tableCashPosition} SET
                opening_balance = " . $this->num($data['opening_balance'] ?? 0) . ",
                total_receipts = " . $this->num($data['total_receipts'] ?? 0) . ",
                total_disbursements = " . $this->num($data['total_disbursements'] ?? 0) . ",
                closing_balance = {$closing},
                bank_statement_balance = " . (($data['bank_statement_balance'] ?? '') === '' ? 'NULL' : $this->num($data['bank_statement_balance'])) . ",
                restricted_amount = {$restricted},
                restricted_breakdown = {$breakdownJson},
                adjustment_amount = " . $this->num($data['adjustment_amount'] ?? 0) . ",
                adjustment_direction = " . $this->strOrNull($data['adjustment_direction'] ?? null) . ",
                adjustment_details = " . $this->strOrNull($data['adjustment_details'] ?? null) . ",
                reconciled = " . (filter_var($data['reconciled'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE') . ",
                reference_number = " . $this->strOrNull($data['reference_number'] ?? null) . ",
                remarks = " . $this->strOrNull($data['remarks'] ?? null) . "
             WHERE cash_position_id = {$id}",
            'DB'
        );

        $this->writeActivityLog(
            (int) $existing['bank_account_id'],
            $existing['position_date'],
            'UPDATE',
            "Ending balance set to " . number_format($closing, 2),
            (int) App\CurrentUserID()
        );

        return $this->ok(['cash_position_id' => $id]);
    }

    private function writeActivityLog(int $bankAccountId, string $positionDate, string $action, string $details, int $userId): void
    {
        App\Execute(
            "INSERT INTO {$this->tableActivityLog} (bank_account_id, position_date, action, details, performed_by)
             VALUES ({$bankAccountId}, " . $this->str($positionDate) . ', ' . $this->str($action) . ', ' . $this->str($details) . ", {$userId})",
            'DB'
        );
    }

    // Backs Position Updates' "Recent Update Activity" panel.
    public function getActivityLog($limit = 10)
    {
        $limit = max(1, min(100, (int) $limit));
        $rows = App\ExecuteRowsAssociative(
            "SELECT l.activity_id, l.bank_account_id, l.position_date, l.action, l.details, l.performed_at,
                    ba.account_name, ba.bank_name
             FROM {$this->tableActivityLog} l
             JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = l.bank_account_id
             ORDER BY l.performed_at DESC
             LIMIT {$limit}",
            'DB'
        );

        return $this->ok($rows);
    }

    // Ending = Beginning + Receipts - Disbursements +/- Adjustment (same
    // formula as TREASURY's _computeManualEntryFields()). Restricted =
    // sum of the itemized breakdown if any lines were given, else the
    // flat restricted_amount input.
    private function computeManualEntryFields($data): array
    {
        $opening = $this->num($data['opening_balance'] ?? 0);
        $receipts = $this->num($data['total_receipts'] ?? 0);
        $disbursements = $this->num($data['total_disbursements'] ?? 0);
        $adjAmount = $this->num($data['adjustment_amount'] ?? 0);
        $adjDirection = strtoupper(trim((string) ($data['adjustment_direction'] ?? '')));
        $signedAdj = $adjDirection === 'DEDUCTION' ? -$adjAmount : $adjAmount;
        $closing = $opening + $receipts - $disbursements + $signedAdj;

        $breakdown = $data['restricted_breakdown'] ?? null;
        if (is_string($breakdown)) {
            $breakdown = json_decode($breakdown, true);
        }
        if (is_array($breakdown) && !empty($breakdown)) {
            $restricted = array_sum(array_map(fn ($item) => $this->num($item['amount'] ?? 0), $breakdown));
            $breakdownJson = $this->str(json_encode(array_values($breakdown))) . '::jsonb';
        } else {
            $restricted = $this->num($data['restricted_amount'] ?? 0);
            $breakdownJson = 'NULL';
        }

        return [$closing, $restricted, $breakdownJson];
    }

    // ── Available Cash Balances Tracking ──
    // Book -> Available -> Usable waterfall per bank account (grouped by
    // fund_source client-side, same as the Dashboard list): Usable =
    // Available minus outstanding checks, floored at 0.
    public function getAvailableBalances($filters)
    {
        $where = implode(' AND ', array_merge(["ba.status = 'ACTIVE'"], array_slice($this->accountWhere($filters), 1)));

        $rows = App\ExecuteRowsAssociative(
            "SELECT ba.bank_account_id, ba.account_name, ba.account_number, ba.bank_name, ba.fund_source,
                    cp.position_date, COALESCE(cp.closing_balance,0) AS closing_balance,
                    COALESCE(cp.restricted_amount,0) AS restricted_amount, COALESCE(cp.available_balance,0) AS available_balance,
                    COALESCE(oc.outstanding_amount, 0) AS outstanding_checks
             FROM {$this->tableBankAccounts} ba
             {$this->cashPositionJoin('')}
             LEFT JOIN (
                 SELECT bank_account_id, SUM(amount) AS outstanding_amount
                 FROM {$this->tableCheckRegister}
                 WHERE current_status IN ('ISSUED', 'OUTSTANDING')
                 GROUP BY bank_account_id
             ) oc ON oc.bank_account_id = ba.bank_account_id
             WHERE {$where}
             ORDER BY ba.fund_source, ba.account_name",
            'DB'
        );
        foreach ($rows as &$row) {
            $row['closing_balance'] = $this->num($row['closing_balance']);
            $row['restricted_amount'] = $this->num($row['restricted_amount']);
            $row['available_balance'] = $this->num($row['available_balance']);
            $row['outstanding_checks'] = $this->num($row['outstanding_checks']);
            $row['usable_balance'] = max(0.0, $row['available_balance'] - $row['outstanding_checks']);
        }

        return $this->ok($rows);
    }

    // Fund-source-level summary with the same Book/Available/Usable
    // waterfall, for fund sources whose total usable balance is below
    // threshold — backs an alert banner above the Available Balances
    // table (TREASURY's own low-balance alert concept).
    public function getLowBalanceAlerts($threshold = 500000)
    {
        $threshold = is_numeric($threshold) ? (float) $threshold : 500000.0;
        $rows = App\ExecuteRowsAssociative(
            "SELECT ba.fund_source,
                    COALESCE(SUM(cp.available_balance), 0) AS available_balance,
                    COALESCE(SUM(oc.outstanding_amount), 0) AS outstanding_checks
             FROM {$this->tableBankAccounts} ba
             {$this->cashPositionJoin('')}
             LEFT JOIN (
                 SELECT bank_account_id, SUM(amount) AS outstanding_amount
                 FROM {$this->tableCheckRegister}
                 WHERE current_status IN ('ISSUED', 'OUTSTANDING')
                 GROUP BY bank_account_id
             ) oc ON oc.bank_account_id = ba.bank_account_id
             WHERE ba.status = 'ACTIVE'
             GROUP BY ba.fund_source",
            'DB'
        );
        $alerts = [];
        foreach ($rows as $row) {
            $usable = max(0.0, $this->num($row['available_balance']) - $this->num($row['outstanding_checks']));
            if ($usable < $threshold) {
                $alerts[] = [
                    'fund_source' => $row['fund_source'] ?: 'Unassigned',
                    'available_balance' => $this->num($row['available_balance']),
                    'outstanding_checks' => $this->num($row['outstanding_checks']),
                    'usable_balance' => $usable,
                ];
            }
        }

        return $this->ok(['threshold' => $threshold, 'count' => count($alerts), 'alerts' => $alerts]);
    }

    public function getGrandTotals()
    {
        $row = App\ExecuteRow(
            "SELECT
                COALESCE(SUM(cp.closing_balance), 0) AS grand_book_balance,
                COALESCE(SUM(cp.available_balance), 0) AS grand_available_balance,
                COALESCE(SUM(cp.restricted_amount), 0) AS grand_restricted_amount,
                COALESCE((SELECT SUM(amount) FROM {$this->tableCheckRegister} WHERE current_status IN ('ISSUED', 'OUTSTANDING')), 0) AS grand_outstanding_checks
             FROM {$this->tableBankAccounts} ba
             {$this->cashPositionJoin('')}
             WHERE ba.status = 'ACTIVE'",
            'DB'
        );
        $available = $this->num($row['grand_available_balance'] ?? 0);
        $outstanding = $this->num($row['grand_outstanding_checks'] ?? 0);

        return $this->ok([
            'grand_book_balance' => $this->num($row['grand_book_balance'] ?? 0),
            'grand_available_balance' => $available,
            'grand_restricted_amount' => $this->num($row['grand_restricted_amount'] ?? 0),
            'grand_outstanding_checks' => $outstanding,
            'grand_usable_balance' => max(0.0, $available - $outstanding),
            'as_of_date' => date('Y-m-d'),
        ]);
    }

    // Per-account rows for one fund source only — backs a "View Accounts"
    // drill-down from the Available Balances group header (independent
    // fetch, not reusing the already-loaded flat list, for API parity with
    // TREASURY's getAccountsByFundSource()).
    public function getAccountsByFundSource($fundSource)
    {
        $fundSource = trim((string) $fundSource);
        if ($fundSource === '') {
            return json_encode(["success" => false, "message" => "Fund source is required."]);
        }

        return $this->getAvailableBalances(['fund_source' => $fundSource]);
    }

    // Daily trend of ending/available/restricted balances for every
    // account sharing a fund source, summed per date — backs a "View
    // Trend" chart/table for one fund source group.
    public function getBalanceHistory($fundSource, $dateFrom, $dateTo, $limit = 30)
    {
        $fundSource = trim((string) $fundSource);
        $limit = max(1, min(90, (int) $limit));
        if ($fundSource === '') {
            return json_encode(["success" => false, "message" => "Fund source is required."]);
        }
        $where = ["ba.fund_source = " . $this->str($fundSource)];
        $dateFrom = trim((string) $dateFrom);
        if ($dateFrom !== '') {
            $where[] = 'cp.position_date >= ' . $this->str($dateFrom);
        }
        $dateTo = trim((string) $dateTo);
        if ($dateTo !== '') {
            $where[] = 'cp.position_date <= ' . $this->str($dateTo);
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT cp.position_date,
                    SUM(cp.closing_balance) AS book_balance, SUM(cp.available_balance) AS available_balance,
                    SUM(cp.restricted_amount) AS restricted_amount, SUM(cp.total_receipts) AS total_receipts,
                    SUM(cp.total_disbursements) AS total_disbursements
             FROM {$this->tableCashPosition} cp
             JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = cp.bank_account_id
             WHERE {$whereSql}
             GROUP BY cp.position_date
             ORDER BY cp.position_date DESC
             LIMIT {$limit}",
            'DB'
        );

        return $this->ok(['fund_source' => $fundSource, 'history' => array_reverse($rows)]);
    }
}
