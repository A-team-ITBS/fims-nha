<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * General Ledger posting (fims.general_ledger) — the step after
 * DisbursementVoucherService::saveJournalEntry() prepares a balanced Journal
 * Entry Voucher: once the DV it belongs to is approved on the Accounting side
 * (FOR_ACCOUNTING -> FOR_TREASURY), the JEV itself is approved/posted and its
 * lines are written to the General Ledger. Subsidiary Ledger breakdown
 * (sl_code/sl_detail_id) and Responsibility Center (rc_id) are carried
 * straight over from the JEV line — no separate SL posting step.
 *
 * Call sites (both inside DisbursementVoucherService::changeStatus(), in the
 * same DB transaction as the status_id update — see that method):
 *   - postJev()   on FOR_ACCOUNTING -> FOR_TREASURY. Idempotent: a JEV already
 *                 POSTED is a no-op, not a duplicate (post-once, enforced by a
 *                 partial unique index on general_ledger.jev_line_id WHERE
 *                 reversed_gl_id IS NULL — see "01 - fims/01 - table/38").
 *   - reverseJev() on FOR_TREASURY -> CANCELLED (the only transition in
 *                 DisbursementVoucherService::TRANSITIONS where a DV can be
 *                 cancelled after its JEV was already posted — RELEASED is
 *                 terminal, nothing moves out of it). Never deletes a posted
 *                 row; inserts an offsetting entry instead (debit/credit
 *                 swapped), same "never delete, link instead" convention
 *                 fims.check_cancellations.replacement_check_id already uses.
 *
 * Four separate reporting modules, all read-only, all built on top of fims.general_ledger /
 * fims.v_journals / fims.v_trial_balance (see "01 - fims/05 - views/01") — each its own page
 * (app/pages/{journals,general-ledger,subsidiary-ledger,trial-balance}-page.php):
 *   - fetchJournals()         Journals (GJ — General Journal) — the book of original entry, every
 *                             posted line in chronological order, grouped by JEV. Filterable, paged.
 *   - fetchGeneralLedger()    General Ledger — pick ONE account, see every posting to it in
 *                             order with a running balance (debit-normal or credit-normal per
 *                             DEBIT_NORMAL_TYPES, not just a raw debit-minus-credit number).
 *   - fetchSubsidiaryLedger() Subsidiary Ledger — the same ledger-card idea as General Ledger,
 *                             but keyed by SL code/detail (fims.sl_masters/sl_details) instead
 *                             of a GL account — e.g. one vendor's Accounts Payable sub-ledger.
 *   - fetchTrialBalance()     Trial Balance — total debit/credit per account, as of now.
 *
 * Three more special-journal tabs sit alongside GJ on the Journals page, each sourced from its own
 * standalone table rather than fims.general_ledger (a payment-method/collection record shouldn't
 * need the GL posting pipeline to know about it — see "01 - table/39"/"40" for why):
 *   - fetchCkdj()  Check Disbursements Journal — every fims.checks row, joined back to the DV/JEV
 *                  it pays (dv_number is a soft reference, same as fims.checks itself uses).
 *   - fetchAdadj() Advice to Debit Account Disbursement Journal — same idea as CKDJ, sourced from
 *                  fims.ada_releases instead of fims.checks (a payment released via bank advice,
 *                  not a physical check).
 *   - fetchCrdj()  Cash Receipts Journal — every fims.official_receipts row (collecting officer's
 *                  collections, one row per credited account via official_receipt_lines) — not
 *                  itself posted to the GL; a later JEV journalizes the eventual bank deposit.
 */
class GeneralLedgerService
{
    private string $table         = 'fims.general_ledger';
    private string $tableJev      = 'fims.journal_entry_vouchers';
    private string $tableJevLines = 'fims.journal_entry_voucher_lines';
    private string $tableSettingsStatus = 'fims.settings_status';
    private string $tableViewJournals     = 'fims.v_journals';
    private string $tableViewTrialBalance = 'fims.v_trial_balance';
    private string $tableCoa        = 'fims.chart_of_accounts';
    private string $tableSlMasters  = 'fims.sl_masters';
    private string $tableDv               = 'fims.disbursement_vouchers';
    private string $tableChecks           = 'fims.checks';
    private string $tableBankAccounts     = 'fims.bank_accounts';
    private string $tableAdaReleases      = 'fims.ada_releases';
    private string $tableOfficialReceipts      = 'fims.official_receipts';
    private string $tableOfficialReceiptLines  = 'fims.official_receipt_lines';

    // Accounts with these account_type values increase with a DEBIT (so running balance =
    // cumulative debit - cumulative credit); everything else (Liability/Equity/Revenue) increases
    // with a CREDIT (running balance = cumulative credit - cumulative debit). Used by
    // fetchGeneralLedger()/fetchSubsidiaryLedger() to sign the running balance the normal
    // accounting way instead of just showing a raw debit-minus-credit number.
    private const DEBIT_NORMAL_TYPES = ['Asset', 'Expense'];

    private const STATUS_FOR = 'journal_entry_voucher';

    /**
     * Looked up by name each time rather than hardcoding an id — same reasoning as
     * DisbursementVoucherService::getStatusIdByName(): a seed row's id depends on insert order,
     * which isn't guaranteed stable across environments/re-seeds.
     */
    private function getStatusIdByName(string $name): ?int
    {
        $row = App\ExecuteRow(
            "SELECT id FROM {$this->tableSettingsStatus} WHERE status_for = " . $this->str(self::STATUS_FOR) . ' AND name = ' . $this->str($name),
            'DB'
        );

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    /**
     * Posts a balanced, prepared JEV to the General Ledger and flips it DRAFT -> APPROVED -> POSTED.
     * No status/balance check here — the caller (DisbursementVoucherService::changeStatus(), the
     * FOR_ACCOUNTING -> FOR_TREASURY transition) already requires a balanced JEV before calling
     * this; this method only owns the posting itself. Expects to run inside the caller's own open
     * transaction (same "no transaction management here, the caller owns it" convention as
     * DisbursementVoucherService::persistJournalEntry()).
     *
     * Idempotent: if the JEV is already POSTED, this is a no-op success (not an error) — a retried
     * call, or changeStatus() being called twice for any reason, can't double-post.
     */
    public function postJev(int $jevId, int $userId): array
    {
        $jev = App\ExecuteRow(
            "SELECT jev.jev_id, jev.jev_no, jev.fiscal_yr, jev.book_id, jev.status_id, st.name AS status
             FROM {$this->tableJev} jev
             LEFT JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
             WHERE jev.jev_id = {$jevId}",
            'DB'
        );
        if (!$jev) {
            return ['success' => false, 'message' => "Journal Entry Voucher #{$jevId} not found."];
        }
        if ($jev['status'] === 'POSTED') {
            return ['success' => true, 'message' => 'Journal Entry Voucher already posted.', 'data' => ['jev_id' => $jevId, 'status' => 'POSTED']];
        }

        $lines = App\ExecuteRows(
            "SELECT jevl_id, account_code, sl_code, sl_detail_id, rc_id, debit, credit, description
             FROM {$this->tableJevLines} WHERE jev_id = {$jevId} ORDER BY line_number",
            'DB'
        ) ?: [];
        if (count($lines) < 2) {
            return ['success' => false, 'message' => "Journal Entry Voucher {$jev['jev_no']} has no lines to post."];
        }

        $uid = $userId > 0 ? (string) $userId : 'NULL';

        foreach ($lines as $line) {
            // Post-once guard: a line already in general_ledger (e.g. a retried call) is skipped,
            // not re-inserted — jev_line_id is UNIQUE, so this WHERE NOT EXISTS is belt-and-braces
            // against the same race the UNIQUE constraint itself already blocks.
            App\Execute(
                "INSERT INTO {$this->table}
                    (jev_id, jev_line_id, account_code, sl_code, sl_detail_id, rc_id, fiscal_yr, book_id,
                     debit, credit, reference_no, description, posted_by)
                 SELECT {$jevId}, " . (int) $line['jevl_id'] . ', '
                    . $this->str($line['account_code']) . ', '
                    . $this->strOrNull($line['sl_code']) . ', '
                    . $this->intOrNull($line['sl_detail_id']) . ', '
                    . $this->strOrNull($line['rc_id']) . ', '
                    . $this->intOrNull($jev['fiscal_yr']) . ', '
                    . $this->intOrNull($jev['book_id']) . ', '
                    . (float) $line['debit'] . ', ' . (float) $line['credit'] . ', '
                    . $this->str($jev['jev_no']) . ', '
                    . $this->strOrNull($line['description']) . ', ' . $uid . "
                 WHERE NOT EXISTS (SELECT 1 FROM {$this->table} WHERE jev_line_id = " . (int) $line['jevl_id'] . ' AND reversed_gl_id IS NULL)',
                'DB'
            );
        }

        $postedId = $this->getStatusIdByName('POSTED');
        if ($postedId === null) {
            return ['success' => false, 'message' => "Status 'POSTED' is not configured in fims.settings_status for journal entry vouchers."];
        }
        App\Execute(
            "UPDATE {$this->tableJev} SET status_id = {$postedId}, posted_by = {$uid}, posted_at = CURRENT_TIMESTAMP
             WHERE jev_id = {$jevId}",
            'DB'
        );

        return ['success' => true, 'message' => "Journal Entry Voucher {$jev['jev_no']} posted to the General Ledger.", 'data' => ['jev_id' => $jevId, 'status' => 'POSTED']];
    }

    /**
     * Reverses a POSTED JEV: one offsetting fims.general_ledger row per original line (debit and
     * credit swapped, reversed_gl_id pointing at the row it reverses), and flips the JEV to
     * REVERSED. Original rows are never touched/deleted — same "never delete, link instead"
     * convention as fims.check_cancellations.replacement_check_id. A no-op (not an error) if the
     * JEV isn't currently POSTED — nothing to reverse. Expects to run inside the caller's own open
     * transaction, same convention as postJev().
     */
    public function reverseJev(int $jevId, int $userId): array
    {
        $jev = App\ExecuteRow(
            "SELECT jev.jev_id, jev.jev_no, st.name AS status
             FROM {$this->tableJev} jev
             LEFT JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
             WHERE jev.jev_id = {$jevId}",
            'DB'
        );
        if (!$jev) {
            return ['success' => false, 'message' => "Journal Entry Voucher #{$jevId} not found."];
        }
        if ($jev['status'] !== 'POSTED') {
            return ['success' => true, 'message' => 'Journal Entry Voucher is not posted — nothing to reverse.', 'data' => ['jev_id' => $jevId, 'status' => $jev['status']]];
        }

        $postedRows = App\ExecuteRows(
            "SELECT gl_id, jev_line_id, account_code, sl_code, sl_detail_id, rc_id, fiscal_yr, book_id, debit, credit
             FROM {$this->table} WHERE jev_id = {$jevId} AND reversed_gl_id IS NULL",
            'DB'
        ) ?: [];

        $uid = $userId > 0 ? (string) $userId : 'NULL';

        foreach ($postedRows as $row) {
            App\Execute(
                "INSERT INTO {$this->table}
                    (jev_id, jev_line_id, account_code, sl_code, sl_detail_id, rc_id, fiscal_yr, book_id,
                     debit, credit, reference_no, description, reversed_gl_id, posted_by)
                 VALUES ({$jevId}, " . (int) $row['jev_line_id'] . ', ' . $this->str($row['account_code']) . ', '
                    . $this->strOrNull($row['sl_code']) . ', '
                    . $this->intOrNull($row['sl_detail_id']) . ', '
                    . $this->strOrNull($row['rc_id']) . ', '
                    . $this->intOrNull($row['fiscal_yr']) . ', '
                    . $this->intOrNull($row['book_id']) . ', '
                    . (float) $row['credit'] . ', ' . (float) $row['debit'] . ', ' // swapped
                    . $this->str($jev['jev_no'] . '-REV') . ', '
                    . $this->str("Reversal of GL #{$row['gl_id']}") . ', ' . (int) $row['gl_id'] . ', ' . $uid . ')',
                'DB'
            );
        }

        $reversedId = $this->getStatusIdByName('REVERSED');
        if ($reversedId === null) {
            return ['success' => false, 'message' => "Status 'REVERSED' is not configured in fims.settings_status for journal entry vouchers."];
        }
        App\Execute("UPDATE {$this->tableJev} SET status_id = {$reversedId} WHERE jev_id = {$jevId}", 'DB');

        return ['success' => true, 'message' => "Journal Entry Voucher {$jev['jev_no']} reversed.", 'data' => ['jev_id' => $jevId, 'status' => 'REVERSED']];
    }

    /** fims.v_journals, paged and optionally filtered by fiscal year / book / account code / DV / JEV number. */
    public function fetchJournals(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $where = ['1=1'];
            if (!empty($filters['fiscal_yr']) && ctype_digit((string) $filters['fiscal_yr'])) {
                $where[] = 'fiscal_yr = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['book_id']) && ctype_digit((string) $filters['book_id'])) {
                $where[] = 'book_id = ' . (int) $filters['book_id'];
            }
            if (!empty($filters['account_code'])) {
                $where[] = 'account_code = ' . $this->str($filters['account_code']);
            }
            if (!empty($filters['jev_no'])) {
                $where[] = 'jev_no = ' . $this->str($filters['jev_no']);
            }
            if (!empty($filters['dv_no'])) {
                $where[] = 'dv_no = ' . $this->str($filters['dv_no']);
            }
            $whereSql = implode(' AND ', $where);

            if ($page === null) {
                $data = App\ExecuteRows("SELECT * FROM {$this->tableViewJournals} WHERE {$whereSql}", 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $limit  = max(1, $limit);
            $offset = (max(1, $page) - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableViewJournals} WHERE {$whereSql}", 'DB');
            $data   = App\ExecuteRows(
                "SELECT * FROM {$this->tableViewJournals} WHERE {$whereSql} ORDER BY posting_date, gl_id LIMIT {$limit} OFFSET {$offset}",
                'DB'
            ) ?: [];

            return [
                'success'    => true,
                'data'       => $data,
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => (int) $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('GeneralLedgerService::fetchJournals ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Journals register.'];
        }
    }

    /**
     * Check Disbursements Journal — every fims.checks row, joined back to the DV/JEV it pays
     * (dv_number is a soft reference — see "01 - table/39"). Not paged — small, report-style
     * listing, same as the other two special journals below. Optionally filtered by fiscal year /
     * check or DV number / payee.
     */
    public function fetchCkdj(?int $fiscalYr = null, ?string $search = null, ?int $bookId = null): array
    {
        try {
            $where = ['1=1'];
            if ($fiscalYr !== null) {
                $where[] = 'd.fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($bookId !== null) {
                $where[] = 'jev.book_id = ' . (int) $bookId;
            }
            if ($search !== null && $search !== '') {
                $like    = $this->str('%' . $search . '%');
                $where[] = "(c.check_number ILIKE {$like} OR c.dv_number ILIKE {$like} OR c.payee ILIKE {$like})";
            }

            $data = App\ExecuteRows(
                "SELECT c.check_id, c.check_number, c.check_date, c.dv_number, jev.jev_no, c.payee, c.amount, c.status,
                        ba.bank_name, ba.account_name AS bank_account_name, ba.account_number AS bank_account_number
                 FROM {$this->tableChecks} c
                 LEFT JOIN {$this->tableDv} d ON d.dv_no = c.dv_number
                 LEFT JOIN {$this->tableJev} jev ON jev.dv_id = d.dv_id
                 LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = c.bank_account_id
                 WHERE " . implode(' AND ', $where) . '
                 ORDER BY c.check_date, c.check_id',
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            App\LogError('GeneralLedgerService::fetchCkdj ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Check Disbursements Journal.'];
        }
    }

    /**
     * Advice to Debit Account Disbursement Journal — every fims.ada_releases row, joined back to
     * the DV/JEV it pays, same shape as fetchCkdj() but sourced from the ADA table instead of
     * fims.checks (see "01 - table/39"). Optionally filtered by fiscal year / ADA or DV number / payee.
     */
    public function fetchAdadj(?int $fiscalYr = null, ?string $search = null, ?int $bookId = null): array
    {
        try {
            $where = ['1=1'];
            if ($fiscalYr !== null) {
                $where[] = 'd.fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($bookId !== null) {
                $where[] = 'jev.book_id = ' . (int) $bookId;
            }
            if ($search !== null && $search !== '') {
                $like    = $this->str('%' . $search . '%');
                $where[] = "(a.ada_no ILIKE {$like} OR a.dv_number ILIKE {$like} OR a.payee ILIKE {$like})";
            }

            $data = App\ExecuteRows(
                "SELECT a.ada_id, a.ada_no, a.ada_date, a.dv_number, jev.jev_no, a.region, a.payee, a.amount, a.status,
                        ba.bank_name, ba.account_name AS bank_account_name, ba.account_number AS bank_account_number
                 FROM {$this->tableAdaReleases} a
                 LEFT JOIN {$this->tableDv} d ON d.dv_no = a.dv_number
                 LEFT JOIN {$this->tableJev} jev ON jev.dv_id = d.dv_id
                 LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = a.bank_account_id
                 WHERE " . implode(' AND ', $where) . '
                 ORDER BY a.ada_date, a.ada_id',
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            App\LogError('GeneralLedgerService::fetchAdadj ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Advice to Debit Account Disbursement Journal.'];
        }
    }

    /**
     * Cash Receipts Journal — every fims.official_receipts row, one result row per credited
     * account (official_receipt_lines — see "01 - table/40"). Not sourced from fims.general_ledger
     * — these collections aren't posted to the GL until a later JEV journalizes the deposit.
     * Optionally filtered by fiscal year / OR number / payor / collecting officer.
     */
    public function fetchCrdj(?int $fiscalYr = null, ?string $search = null, ?int $bookId = null): array
    {
        try {
            $where = ['1=1'];
            if ($fiscalYr !== null) {
                $where[] = 'r.fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($bookId !== null) {
                $where[] = 'r.book_id = ' . (int) $bookId;
            }
            if ($search !== null && $search !== '') {
                $like    = $this->str('%' . $search . '%');
                $where[] = "(r.or_no ILIKE {$like} OR r.payor_name ILIKE {$like} OR r.collecting_officer ILIKE {$like})";
            }

            $data = App\ExecuteRows(
                "SELECT r.receipt_id, r.or_no, r.or_date, r.collecting_officer, r.payor_name, r.particulars,
                        r.debit_account_code, dc.account_title AS debit_account_title, r.total_amount,
                        l.line_number, l.account_code AS credit_account_code, c.account_title AS credit_account_title,
                        l.amount, l.description
                 FROM {$this->tableOfficialReceiptLines} l
                 JOIN {$this->tableOfficialReceipts} r ON r.receipt_id = l.receipt_id
                 JOIN {$this->tableCoa} c  ON c.account_code = l.account_code
                 JOIN {$this->tableCoa} dc ON dc.account_code = r.debit_account_code
                 WHERE " . implode(' AND ', $where) . '
                 ORDER BY r.or_date, r.receipt_id, l.line_number',
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            App\LogError('GeneralLedgerService::fetchCrdj ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Cash Receipts Journal.'];
        }
    }

    /**
     * fims.v_trial_balance, optionally scoped to one fiscal year and/or one book (fims.books —
     * see "01 - table/41"/"03/30"), with each row tagged with its major_group (the root ancestor —
     * Assets/Liabilities/Equity/Revenue/Expenses) and account_group (the ancestor directly below
     * the root — e.g. "Cash and Cash Equivalents") per chart_of_accounts.parent_account_id, for the
     * official NGAS Trial Balance layout: a bold major_group header, then a bold account_group
     * sub-header, then indented account rows — matching the standard COA report, not just a flat
     * account_code list.
     *
     * The view is grouped by (fiscal_yr, book_id, account_code) — finer than this method's
     * own output — so a single book is a plain WHERE, but "all books" (no $bookId) has to
     * re-aggregate the view's rows back across book_id here, or the same account posted
     * under two different books would show as two separate rows instead of one combined total.
     */
    public function fetchTrialBalance(?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            $where = ['1=1'];
            if ($fiscalYr !== null) {
                $where[] = 'v.fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($bookId !== null) {
                $where[] = 'v.book_id = ' . (int) $bookId;
            }
            $whereSql = implode(' AND ', $where);

            $data = App\ExecuteRows(
                "SELECT v.account_code, v.account_id, v.account_title, v.account_type,
                        SUM(v.total_debit)  AS total_debit,
                        SUM(v.total_credit) AS total_credit,
                        SUM(v.total_debit) - SUM(v.total_credit) AS balance
                 FROM {$this->tableViewTrialBalance} v
                 WHERE {$whereSql}
                 GROUP BY v.account_code, v.account_id, v.account_title, v.account_type
                 ORDER BY v.account_code",
                'DB'
            ) ?: [];
            $totalDebit  = round(array_sum(array_column($data, 'total_debit')), 2);
            $totalCredit = round(array_sum(array_column($data, 'total_credit')), 2);

            $this->attachAccountGroups($data);

            return ['success' => true, 'data' => $data, 'total_debit' => $totalDebit, 'total_credit' => $totalCredit, 'balanced' => $totalDebit === $totalCredit];
        } catch (\Exception $e) {
            App\LogError('GeneralLedgerService::fetchTrialBalance ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Trial Balance.'];
        }
    }

    /**
     * Walks each row's chart_of_accounts ancestor chain (one recursive query, scoped to just the
     * account_ids present in $data — not the whole chart) and tags it with major_group/
     * account_group, mutating $data in place. Hierarchy depth isn't uniform across the chart (a
     * few accounts sit directly under the root with no intermediate group — e.g. "2-08 Deposit
     * Liabilities"), so account_group is left null rather than guessed whenever there's no real
     * middle ancestor, so the frontend can skip printing an empty sub-header for those rows.
     */
    private function attachAccountGroups(array &$data): void
    {
        if (!$data) {
            return;
        }
        $accountIds = array_unique(array_column($data, 'account_id'));
        $idList     = implode(',', array_map(fn ($id) => $this->str($id), $accountIds));

        $rows = App\ExecuteRows(
            "WITH RECURSIVE chain AS (
                 SELECT account_id AS leaf_id, account_id, account_title, parent_account_id, 0 AS depth
                 FROM {$this->tableCoa} WHERE account_id IN ({$idList})
                 UNION ALL
                 SELECT chain.leaf_id, c.account_id, c.account_title, c.parent_account_id, chain.depth + 1
                 FROM {$this->tableCoa} c
                 JOIN chain ON c.account_id = chain.parent_account_id
             )
             SELECT leaf_id, account_title, depth FROM chain ORDER BY leaf_id, depth",
            'DB'
        ) ?: [];

        $chains = [];
        foreach ($rows as $r) {
            $chains[$r['leaf_id']][] = $r['account_title']; // depth 0 (leaf) first, depth max (root) last
        }

        foreach ($data as &$row) {
            $chain                = $chains[$row['account_id']] ?? [];
            $count                = count($chain);
            $row['major_group']   = $count >= 1 ? $chain[$count - 1] : null;
            $row['account_group'] = $count >= 3 ? $chain[$count - 2] : null;
            unset($row['account_id']);
        }
        unset($row);
    }

    /**
     * General Ledger — one account's ledger card: every posted line for $accountCode, oldest
     * first, with a running balance signed the normal accounting way (see DEBIT_NORMAL_TYPES).
     * $fiscalYr is optional; when given, the running balance only covers that fiscal year's rows
     * (there is no brought-forward/beginning balance from a prior system — see §2.8 of the ERD doc).
     * $accountCode omitted/blank: every posted account's ledger card at once (see
     * fetchAllGeneralLedgers()) — the page's default view before one account is picked.
     */
    public function fetchGeneralLedger(?string $accountCode, ?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            if ($accountCode === null || trim($accountCode) === '') {
                return $this->fetchAllGeneralLedgers($fiscalYr, $bookId);
            }

            $account = App\ExecuteRow(
                "SELECT account_code, account_title, account_type FROM {$this->tableCoa} WHERE account_code = " . $this->str($accountCode),
                'DB'
            );
            if (!$account) {
                return ['success' => false, 'message' => "Account code '{$accountCode}' does not exist in the chart of accounts."];
            }
            $debitNormal = in_array($account['account_type'], self::DEBIT_NORMAL_TYPES, true);
            $sign        = $debitNormal ? '(debit - credit)' : '(credit - debit)';

            $where = ['account_code = ' . $this->str($accountCode)];
            if ($fiscalYr !== null) {
                $where[] = 'fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($bookId !== null) {
                $where[] = 'book_id = ' . (int) $bookId;
            }
            $data = App\ExecuteRows(
                "SELECT gl_id, posting_date, jev_no, dv_no, sl_code, sl_name, debit, credit, reference_no, description, reversed_gl_id,
                        SUM({$sign}) OVER (ORDER BY posting_date, gl_id) AS running_balance
                 FROM {$this->tableViewJournals}
                 WHERE " . implode(' AND ', $where) . '
                 ORDER BY posting_date, gl_id',
                'DB'
            ) ?: [];

            $totalDebit  = round(array_sum(array_column($data, 'debit')), 2);
            $totalCredit = round(array_sum(array_column($data, 'credit')), 2);

            return [
                'success' => true,
                'grouped' => false,
                'account' => $account,
                'debit_normal' => $debitNormal,
                'data' => $data,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'ending_balance' => $data ? (float) end($data)['running_balance'] : 0.0,
            ];
        } catch (\Exception $e) {
            App\LogError('GeneralLedgerService::fetchGeneralLedger ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the General Ledger.'];
        }
    }

    /**
     * Every posted account's ledger card at once — same running-balance shape as
     * fetchGeneralLedger(), just partitioned by account_code (window function, one pass) instead
     * of filtered to a single account. Used for the General Ledger page's default view, before one
     * account is picked.
     */
    private function fetchAllGeneralLedgers(?int $fiscalYr, ?int $bookId = null): array
    {
        $where = $fiscalYr !== null ? 'AND v.fiscal_yr = ' . (int) $fiscalYr : '';
        $where .= $bookId !== null ? ' AND v.book_id = ' . (int) $bookId : '';
        $rows  = App\ExecuteRows(
            "SELECT v.gl_id, v.posting_date, v.jev_no, v.dv_no, v.account_code, v.account_title, c.account_type,
                    v.sl_code, v.sl_name, v.debit, v.credit, v.reference_no, v.description, v.reversed_gl_id,
                    SUM(CASE WHEN c.account_type IN ('Asset', 'Expense') THEN (v.debit - v.credit) ELSE (v.credit - v.debit) END)
                        OVER (PARTITION BY v.account_code ORDER BY v.posting_date, v.gl_id) AS running_balance
             FROM {$this->tableViewJournals} v
             JOIN {$this->tableCoa} c ON c.account_code = v.account_code
             WHERE 1 = 1 {$where}
             ORDER BY v.account_code, v.posting_date, v.gl_id",
            'DB'
        ) ?: [];

        $accounts = [];
        foreach ($rows as $row) {
            $code = $row['account_code'];
            if (!isset($accounts[$code])) {
                $accounts[$code] = [
                    'account' => ['account_code' => $code, 'account_title' => $row['account_title'], 'account_type' => $row['account_type']],
                    'debit_normal' => in_array($row['account_type'], self::DEBIT_NORMAL_TYPES, true),
                    'data' => [],
                    'total_debit' => 0.0,
                    'total_credit' => 0.0,
                ];
            }
            unset($row['account_code'], $row['account_title'], $row['account_type']);
            $accounts[$code]['data'][]        = $row;
            $accounts[$code]['total_debit']  += (float) $row['debit'];
            $accounts[$code]['total_credit'] += (float) $row['credit'];
        }
        foreach ($accounts as &$a) {
            $a['total_debit']    = round($a['total_debit'], 2);
            $a['total_credit']   = round($a['total_credit'], 2);
            $a['ending_balance'] = $a['data'] ? (float) end($a['data'])['running_balance'] : 0.0;
        }
        unset($a);

        return ['success' => true, 'grouped' => true, 'accounts' => array_values($accounts)];
    }

    /**
     * Subsidiary Ledger — same ledger-card idea as fetchGeneralLedger(), but keyed by SL code
     * (and optionally SL detail) instead of a GL account. The normal-balance side follows the SL
     * master's own control account (fims.sl_masters.account_code) when it has one; a master with
     * no control account (nullable, see "01 - table/23") falls back to credit-normal, since every
     * SL master seeded so far is an Accounts Payable breakdown — the far more common case.
     * $slCode omitted/blank: every SL master with at least one posting, all at once (see
     * fetchAllSubsidiaryLedgers()) — the page's default view before one SL code is picked.
     */
    public function fetchSubsidiaryLedger(?string $slCode, ?int $slDetailId = null, ?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            if ($slCode === null || trim($slCode) === '') {
                return $this->fetchAllSubsidiaryLedgers($fiscalYr, $bookId);
            }

            $master = App\ExecuteRow(
                "SELECT sm.sl_code, sm.sl_name, sm.account_code, c.account_type
                 FROM {$this->tableSlMasters} sm
                 LEFT JOIN {$this->tableCoa} c ON c.account_code = sm.account_code
                 WHERE sm.sl_code = " . $this->str($slCode),
                'DB'
            );
            if (!$master) {
                return ['success' => false, 'message' => "SL code '{$slCode}' does not exist."];
            }
            $debitNormal = in_array($master['account_type'], self::DEBIT_NORMAL_TYPES, true);
            $sign        = $debitNormal ? '(debit - credit)' : '(credit - debit)';

            $where = ['sl_code = ' . $this->str($slCode)];
            if ($slDetailId !== null) {
                $where[] = 'sl_detail_id = ' . (int) $slDetailId;
            }
            if ($fiscalYr !== null) {
                $where[] = 'fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($bookId !== null) {
                $where[] = 'book_id = ' . (int) $bookId;
            }
            $data = App\ExecuteRows(
                "SELECT gl_id, posting_date, jev_no, dv_no, account_code, account_title, sl_detail_id, sl_detail_name, debit, credit, reference_no, description, reversed_gl_id,
                        SUM({$sign}) OVER (ORDER BY posting_date, gl_id) AS running_balance
                 FROM {$this->tableViewJournals}
                 WHERE " . implode(' AND ', $where) . '
                 ORDER BY posting_date, gl_id',
                'DB'
            ) ?: [];

            $totalDebit  = round(array_sum(array_column($data, 'debit')), 2);
            $totalCredit = round(array_sum(array_column($data, 'credit')), 2);

            return [
                'success' => true,
                'grouped' => false,
                'master' => $master,
                'debit_normal' => $debitNormal,
                'data' => $data,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'ending_balance' => $data ? (float) end($data)['running_balance'] : 0.0,
            ];
        } catch (\Exception $e) {
            App\LogError('GeneralLedgerService::fetchSubsidiaryLedger ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Subsidiary Ledger.'];
        }
    }

    /**
     * Every SL master with at least one posting, all at once — same running-balance shape as
     * fetchSubsidiaryLedger(), partitioned by sl_code (window function, one pass) instead of
     * filtered to a single SL code. Does not split by sl_detail_id — same master-level grouping
     * fetchSubsidiaryLedger() uses when $slDetailId is omitted. Used for the Subsidiary Ledger
     * page's default view, before one SL code is picked.
     */
    private function fetchAllSubsidiaryLedgers(?int $fiscalYr, ?int $bookId = null): array
    {
        $where = $fiscalYr !== null ? 'AND v.fiscal_yr = ' . (int) $fiscalYr : '';
        $where .= $bookId !== null ? ' AND v.book_id = ' . (int) $bookId : '';
        $rows  = App\ExecuteRows(
            "SELECT v.gl_id, v.posting_date, v.jev_no, v.dv_no, v.account_code, v.account_title, v.sl_code, sm.sl_name,
                    v.sl_detail_id, v.sl_detail_name, v.debit, v.credit, v.reference_no, v.description, v.reversed_gl_id,
                    c.account_type,
                    SUM(CASE WHEN c.account_type IN ('Asset', 'Expense') THEN (v.debit - v.credit) ELSE (v.credit - v.debit) END)
                        OVER (PARTITION BY v.sl_code ORDER BY v.posting_date, v.gl_id) AS running_balance
             FROM {$this->tableViewJournals} v
             JOIN {$this->tableSlMasters} sm ON sm.sl_code = v.sl_code
             LEFT JOIN {$this->tableCoa} c ON c.account_code = sm.account_code
             WHERE v.sl_code IS NOT NULL {$where}
             ORDER BY v.sl_code, v.posting_date, v.gl_id",
            'DB'
        ) ?: [];

        $masters = [];
        foreach ($rows as $row) {
            $code = $row['sl_code'];
            if (!isset($masters[$code])) {
                $masters[$code] = [
                    'master' => ['sl_code' => $code, 'sl_name' => $row['sl_name']],
                    'debit_normal' => in_array($row['account_type'], self::DEBIT_NORMAL_TYPES, true),
                    'data' => [],
                    'total_debit' => 0.0,
                    'total_credit' => 0.0,
                ];
            }
            unset($row['sl_name'], $row['account_type']);
            $masters[$code]['data'][]        = $row;
            $masters[$code]['total_debit']  += (float) $row['debit'];
            $masters[$code]['total_credit'] += (float) $row['credit'];
        }
        foreach ($masters as &$m) {
            $m['total_debit']    = round($m['total_debit'], 2);
            $m['total_credit']   = round($m['total_credit'], 2);
            $m['ending_balance'] = $m['data'] ? (float) end($m['data'])['running_balance'] : 0.0;
        }
        unset($m);

        return ['success' => true, 'grouped' => true, 'masters' => array_values($masters)];
    }

    // ── small SQL helpers (same shape as DisbursementVoucherService) ──

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function intOrNull(mixed $v): string
    {
        return ($v !== null && $v !== '' && ctype_digit((string) $v)) ? (string) (int) $v : 'NULL';
    }
}
