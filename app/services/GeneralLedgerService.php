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
 *                             but keyed by an SL account — a child fims.chart_of_accounts row
 *                             (parent_account_id IS NOT NULL) under a control account — instead
 *                             of a top-level GL account. e.g. one vendor's Accounts Payable
 *                             sub-ledger, added via the Chart of Accounts page's "Add Sub-Ledger"
 *                             action. fims.sl_masters/sl_details are a retired, unrelated table —
 *                             not used here.
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
            "SELECT jev.jev_id, jev.jev_no, jev.fiscal_yr, jev.book_id, jev.source_module, jev.doc_ref_no, jev.status_id, st.name AS status
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
                    (jev_id, jev_line_id, account_code, sl_code, sl_detail_id, rc_id, fiscal_yr, book_id, source_module, doc_ref_no,
                     debit, credit, reference_no, description, posted_by)
                 SELECT {$jevId}, " . (int) $line['jevl_id'] . ', '
                    . $this->str($line['account_code']) . ', '
                    . $this->strOrNull($line['sl_code']) . ', '
                    . $this->intOrNull($line['sl_detail_id']) . ', '
                    . $this->strOrNull($line['rc_id']) . ', '
                    . $this->intOrNull($jev['fiscal_yr']) . ', '
                    . $this->intOrNull($jev['book_id']) . ', '
                    . $this->strOrNull($jev['source_module']) . ', '
                    . $this->strOrNull($jev['doc_ref_no']) . ', '
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
            "SELECT gl_id, jev_line_id, account_code, sl_code, sl_detail_id, rc_id, fiscal_yr, book_id, source_module, doc_ref_no, debit, credit
             FROM {$this->table} WHERE jev_id = {$jevId} AND reversed_gl_id IS NULL",
            'DB'
        ) ?: [];

        $uid = $userId > 0 ? (string) $userId : 'NULL';

        foreach ($postedRows as $row) {
            App\Execute(
                "INSERT INTO {$this->table}
                    (jev_id, jev_line_id, account_code, sl_code, sl_detail_id, rc_id, fiscal_yr, book_id, source_module, doc_ref_no,
                     debit, credit, reference_no, description, reversed_gl_id, posted_by)
                 VALUES ({$jevId}, " . (int) $row['jev_line_id'] . ', ' . $this->str($row['account_code']) . ', '
                    . $this->strOrNull($row['sl_code']) . ', '
                    . $this->intOrNull($row['sl_detail_id']) . ', '
                    . $this->strOrNull($row['rc_id']) . ', '
                    . $this->intOrNull($row['fiscal_yr']) . ', '
                    . $this->intOrNull($row['book_id']) . ', '
                    . $this->strOrNull($row['source_module']) . ', '
                    . $this->strOrNull($row['doc_ref_no']) . ', '
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

    /**
     * PREPARES (does not post) the PAYMENT entry a released DV's check/ADA represents — the entry that
     * settles the Accounts Payable balance Accounting's SETUP JEV created, and credits Cash. This is
     * ACCOUNTING's job, never Treasury's: it is called from DisbursementVoucherService::preparePaymentJev()
     * (the "Prepare Payment JEV" action an Accounting preparer takes on a released DV), NOT from the
     * Treasury check-release flow.
     *
     * The PAYMENT JEV is created at FOR_SECTION_CHIEF (prepared by Accounting, awaiting Section Chief
     * review) — it is NOT written to the General Ledger here. It then runs the JEV review chain
     * (Section Chief -> Division Manager -> Department Manager) and only the Department Manager's
     * approval posts it (DisbursementVoucherService::changeJevStatus() -> postJev()). Until then,
     * Accounts Payable stays open on the books.
     *
     * A no-op (not an error) if the DV's SETUP JEV never routed through Accounts Payable
     * (self::AP_ACCOUNT_CODE) at all — e.g. a DV whose JEV credited Cash directly instead of AP (see
     * "09 - gl_sl_demo_postings.sql"'s GLDEMO-0002) has nothing left to settle; the obligation was
     * already fully recorded against Cash at setup time.
     *
     * Creates a second, distinct journal_entry_vouchers row (jev_type = 'PAYMENT', same dv_id —
     * see "03 - alter_table/31" for why dv_id is no longer unique) rather than editing the SETUP
     * JEV, so Journals/CKDJ keep a clean, separate JEV number per source document, same as the
     * illustrated JEV-0002 (ACCOUNTING, setup) / JEV-0003 (TREASURY, payment) pair.
     */
    private const AP_ACCOUNT_CODE = '2-01-01-010'; // Accounts Payable — same hardcoded control account app/pages/voucher/{form,readone}.php's auto "Net Amount Payable" line already uses

    public function createPaymentJev(int $dvId, int $bankAccountId, float $amount, string $docRefNo, int $userId): array
    {
        $setupJev = App\ExecuteRow(
            "SELECT jev_id, jev_no, fiscal_yr, book_id
             FROM {$this->tableJev}
             WHERE dv_id = {$dvId} AND jev_type = 'SETUP'",
            'DB'
        );
        if (!$setupJev) {
            return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} has no posted Journal Entry Voucher to settle."];
        }

        // Matches the control account itself (self::AP_ACCOUNT_CODE) OR one of its SL sub-ledger
        // children (e.g. a vendor's own fims.vendors.coa_account_id under it, see
        // voucher/{form,readone}.php's "Net Amount Payable" line) — same single-level
        // parent_account_id convention GeneralLedgerService::fetchSubsidiaryLedger() already uses.
        $apLine = App\ExecuteRow(
            "SELECT jl.account_code, jl.sl_code, jl.sl_detail_id, jl.rc_id
             FROM {$this->tableJevLines} jl
             JOIN {$this->tableCoa} c ON c.account_code = jl.account_code
             LEFT JOIN {$this->tableCoa} p ON p.account_id = c.parent_account_id
             WHERE jl.jev_id = " . (int) $setupJev['jev_id'] . '
               AND jl.credit > 0
               AND (jl.account_code = ' . $this->str(self::AP_ACCOUNT_CODE) . '
                    OR p.account_code = ' . $this->str(self::AP_ACCOUNT_CODE) . ')
             ORDER BY jl.line_number LIMIT 1',
            'DB'
        );
        if (!$apLine) {
            // Nothing routed through Accounts Payable — e.g. already paid straight out of Cash at
            // setup time. Not an error; there's simply no payment entry to post.
            return ['success' => true, 'message' => 'Nothing to settle — the Journal Entry did not route through Accounts Payable.', 'data' => ['posted' => false]];
        }

        // sl_coa.account_code: the bank account's OWN Cash sub-ledger (fims.bank_accounts.sl_code ->
        // a chart_of_accounts child row, e.g. "1-01-02-010-01" — set up via Bank Management's own
        // GL/SL two-step picker, same convention as fims.vendors.sl_code) when one is configured,
        // instead of the bare account_code — same "credit the account that actually has a
        // subsidiary ledger" reasoning the Accounts Payable debit line above already follows.
        $bank = App\ExecuteRow(
            "SELECT ba.account_code, ba.account_name, sl_coa.account_code AS sl_account_code
             FROM {$this->tableBankAccounts} ba
             LEFT JOIN {$this->tableCoa} sl_coa ON sl_coa.account_id = ba.sl_code
             WHERE ba.bank_account_id = {$bankAccountId}",
            'DB'
        );
        if (!$bank || !$bank['account_code']) {
            return ['success' => false, 'message' => $bank
                ? "Bank account '{$bank['account_name']}' has no Chart-of-Accounts Cash account mapped — configure it before releasing checks against it."
                : "Bank account #{$bankAccountId} not found."];
        }
        $bankCreditAccountCode = $bank['sl_account_code'] ?: $bank['account_code'];

        // Created at FOR_SECTION_CHIEF: Accounting has prepared it, it now awaits Section Chief review.
        $startId = $this->getStatusIdByName('FOR_SECTION_CHIEF');
        if ($startId === null) {
            return ['success' => false, 'message' => "Status 'FOR_SECTION_CHIEF' is not configured in fims.settings_status for journal entry vouchers."];
        }

        $jevNo = $this->nextJevNumber(date('Y-m-d'));
        $uid   = $userId > 0 ? (string) $userId : 'NULL';
        $row   = App\ExecuteRow(
            "INSERT INTO {$this->tableJev}
                (jev_no, dv_id, fiscal_yr, book_id, jev_type, source_module, doc_ref_no, particulars, total_debit, total_credit, status_id, created_by)
             VALUES (" . $this->str($jevNo) . ", {$dvId}, " . $this->intOrNull($setupJev['fiscal_yr']) . ', '
                . $this->intOrNull($setupJev['book_id']) . ", 'PAYMENT', 'TREASURY', " . $this->str($docRefNo) . ', '
                . $this->str("Payment via {$docRefNo}") . ', '
                . (float) $amount . ', ' . (float) $amount . ", {$startId}, {$uid})
             RETURNING jev_id",
            'DB'
        );
        $jevId = (int) $row['jev_id'];

        // Debits the SAME account the SETUP JEV actually credited — the control account itself, or
        // a vendor's own SL sub-ledger account under it — never the hardcoded control code, or a
        // vendor with its own SL account would settle against the wrong ledger (control account
        // debited, vendor's SL balance left outstanding forever).
        App\Execute(
            "INSERT INTO {$this->tableJevLines} (jev_id, line_number, account_code, debit, credit, description, sl_code, sl_detail_id, rc_id)
             VALUES ({$jevId}, 1, " . $this->str($apLine['account_code']) . ', ' . (float) $amount . ', 0, '
                . $this->str("Payment via {$docRefNo}") . ', '
                . $this->strOrNull($apLine['sl_code']) . ', ' . $this->intOrNull($apLine['sl_detail_id']) . ', '
                . $this->strOrNull($apLine['rc_id']) . ')',
            'DB'
        );
        App\Execute(
            "INSERT INTO {$this->tableJevLines} (jev_id, line_number, account_code, debit, credit, description)
             VALUES ({$jevId}, 2, " . $this->str($bankCreditAccountCode) . ', 0, ' . (float) $amount . ', '
                . $this->str("Payment via {$docRefNo}") . ')',
            'DB'
        );

        return ['success' => true, 'message' => "Payment Journal Entry Voucher {$jevNo} prepared — now in review (Section Chief → Division Manager → Department Manager posts).", 'data' => ['jev_id' => $jevId, 'jev_no' => $jevNo, 'posted' => false]];
    }

    /**
     * Same numbering scheme as DisbursementVoucherService::nextJevNumber() (kept as a separate
     * private copy, same "small SQL helpers duplicated per service" convention as str()/strOrNull()
     * below) — PAYMENT JEVs share the same JEV-YY-MM-#### sequence as SETUP ones, just a later
     * number for the same month.
     */
    private function nextJevNumber(string $jevDate): string
    {
        $prefix = 'JEV-' . substr($jevDate, 2, 2) . '-' . substr($jevDate, 5, 2) . '-';
        $max    = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(jev_no FROM '[0-9]+$') AS INTEGER)), 0)
             FROM {$this->tableJev}
             WHERE jev_no LIKE " . $this->str($prefix . '%') . " AND jev_no ~ '-[0-9]+$'",
            'DB'
        );

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
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
    /**
     * The ONE JEV a CKDJ / ADADJ row shows, as `jev`: a DV has a SETUP JEV (Dr expense / Cr Accounts Payable) and, once it
     * is paid, a PAYMENT JEV (Dr Accounts Payable / Cr Cash in Bank). A plain `LEFT JOIN … ON jev.dv_id = d.dv_id` returned
     * both, so every paid check / ADA was listed twice. A disbursements journal records the payment, so the PAYMENT JEV
     * wins, falling back to the SETUP JEV (latest first) while the payment has not been journalized yet.
     */
    private function disbursementJevJoin(): string
    {
        return "LEFT JOIN LATERAL (
                    SELECT j.jev_no, j.book_id
                    FROM {$this->tableJev} j
                    WHERE j.dv_id = d.dv_id
                    ORDER BY (j.jev_type = 'PAYMENT') DESC, j.jev_id DESC
                    LIMIT 1
                 ) jev ON TRUE";
    }

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
                 " . $this->disbursementJevJoin() . "
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
                 " . $this->disbursementJevJoin() . "
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

            // Roll SL/child postings up into their GL account. An SL account (e.g. the Chart of
            // Accounts page's "Add Sub-Ledger" action) is only ever created under a parent that is
            // ITSELF already posting_allowed — chart-of-accounts-page.php's coa_addSL() only offers
            // that action on a postable row. A plain classification ancestor (e.g. "1-02-02", not
            // postable) is not an SL parent, so climbing stops there: the GL code for any leaf is
            // the highest ancestor still reached through an unbroken chain of postable parents.
            $data = App\ExecuteRows(
                "WITH RECURSIVE climb AS (
                     SELECT account_id AS leaf_id, account_id, parent_account_id, 0 AS depth
                     FROM {$this->tableCoa}
                     UNION ALL
                     SELECT climb.leaf_id, p.account_id, p.parent_account_id, climb.depth + 1
                     FROM climb
                     JOIN {$this->tableCoa} p ON p.account_id = climb.parent_account_id
                     WHERE p.posting_allowed = TRUE
                 ),
                 gl_of AS (
                     SELECT DISTINCT ON (leaf_id) leaf_id, account_id AS gl_account_id
                     FROM climb
                     ORDER BY leaf_id, depth DESC
                 ),
                 posted AS (
                     SELECT gl_of.gl_account_id,
                            SUM(v.total_debit)  AS total_debit,
                            SUM(v.total_credit) AS total_credit
                     FROM {$this->tableViewTrialBalance} v
                     JOIN gl_of ON gl_of.leaf_id = v.account_id
                     WHERE {$whereSql}
                     GROUP BY gl_of.gl_account_id
                 )
                 SELECT gl.account_code, gl.account_id, gl.account_title, gl.account_type,
                        COALESCE(p.total_debit, 0)  AS total_debit,
                        COALESCE(p.total_credit, 0) AS total_credit,
                        COALESCE(p.total_debit, 0) - COALESCE(p.total_credit, 0) AS balance
                 FROM {$this->tableCoa} gl
                 LEFT JOIN posted p ON p.gl_account_id = gl.account_id
                 WHERE p.gl_account_id IS NOT NULL
                    OR (gl.posting_allowed = TRUE
                        AND gl.status = 'Active'
                        AND gl.account_id IN (SELECT leaf_id FROM gl_of WHERE leaf_id = gl_account_id))
                 ORDER BY gl.account_code",
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
                 SELECT account_id AS leaf_id, account_id, account_code, account_title, parent_account_id, 0 AS depth
                 FROM {$this->tableCoa} WHERE account_id IN ({$idList})
                 UNION ALL
                 SELECT chain.leaf_id, c.account_id, c.account_code, c.account_title, c.parent_account_id, chain.depth + 1
                 FROM {$this->tableCoa} c
                 JOIN chain ON c.account_id = chain.parent_account_id
             )
             SELECT leaf_id, account_code, account_title, depth FROM chain ORDER BY leaf_id, depth",
            'DB'
        ) ?: [];

        $chains = [];
        foreach ($rows as $r) {
            // depth 0 (leaf) first, depth max (root) last
            $chains[$r['leaf_id']][] = ['code' => $r['account_code'], 'title' => $r['account_title']];
        }

        foreach ($data as &$row) {
            $chain                     = $chains[$row['account_id']] ?? [];
            $count                     = count($chain);
            $row['major_group']        = $count >= 1 ? $chain[$count - 1]['title'] : null;
            $row['account_group']      = $count >= 3 ? $chain[$count - 2]['title'] : null;
            $row['account_group_code'] = $count >= 3 ? $chain[$count - 2]['code'] : null;
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

            // The GL account's ledger includes its SL accounts' postings (rolled up, see glRollupCte()) —
            // a picked SL account itself still shows just its own lines.
            $code  = $this->str($accountCode);
            $where = ["(g.account_code = {$code} OR v.account_code = {$code})"];
            if ($fiscalYr !== null) {
                $where[] = 'v.fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($bookId !== null) {
                $where[] = 'v.book_id = ' . (int) $bookId;
            }
            $signV = $debitNormal ? '(v.debit - v.credit)' : '(v.credit - v.debit)';
            $data = App\ExecuteRows(
                $this->glRollupCte() . "
                 SELECT v.gl_id, v.posting_date, v.jev_no, v.dv_no, v.doc_ref_no, v.sl_code, v.sl_name, v.debit, v.credit, v.reference_no, v.description, v.reversed_gl_id,
                        CASE WHEN v.account_code <> g.account_code THEN v.account_code END AS sl_account_code,
                        SUM({$signV}) OVER (ORDER BY v.posting_date, v.gl_id) AS running_balance
                 FROM {$this->tableViewJournals} v
                 JOIN {$this->tableCoa} lc ON lc.account_code = v.account_code
                 JOIN gl_of ON gl_of.leaf_id = lc.account_id
                 JOIN {$this->tableCoa} g ON g.account_id = gl_of.gl_account_id
                 WHERE " . implode(' AND ', $where) . '
                 ORDER BY v.posting_date, v.gl_id',
                'DB'
            ) ?: [];

            $totalDebit  = round(array_sum(array_column($data, 'debit')), 2);
            $totalCredit = round(array_sum(array_column($data, 'credit')), 2);

            return [
                'success' => true,
                'grouped' => false,
                'account' => $account,
                'debit_normal' => $debitNormal,
                'normal_balance' => $debitNormal ? 'DEBIT' : 'CREDIT',
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
     * `WITH gl_of(leaf_id, gl_account_id)` — maps every chart_of_accounts row to its GL account, using the
     * same definition the Subsidiary Ledger page uses for an SL: a LEAF account with a parent is an SL, and
     * its parent is the GL account (e.g. SL 1-01-01-010 → GL 1-01-01). An account that has children of its
     * own (a control account such as 2-01-01-010 with vendor SLs under it), or has no parent, is its own GL
     * account. Callers append their own SELECT.
     */
    private function glRollupCte(): string
    {
        return "WITH gl_of AS (
                    SELECT c.account_id AS leaf_id,
                           CASE WHEN c.parent_account_id IS NULL
                                  OR EXISTS (SELECT 1 FROM {$this->tableCoa} k WHERE k.parent_account_id = c.account_id)
                                THEN c.account_id ELSE c.parent_account_id END AS gl_account_id
                    FROM {$this->tableCoa} c
                )";
    }

    /**
     * General Ledger drill-down — the SL (subsidiary ledger) accounts under ONE GL account, each with
     * its own posted JEV lines and running balance. An SL account is a leaf child of the GL account in
     * fims.chart_of_accounts (same definition fetchSubsidiaryLedger() uses), and its normal-balance side
     * follows the GL account's account_type. Every SL under the account is returned, including ones with
     * no postings yet (empty `data`), so the popup shows the full SL list. $accountCode that has no SL
     * children (e.g. an account with no sub-ledgers) simply returns an empty `sub_ledgers`.
     * Used by the General Ledger page's "click a GL code" popup.
     */
    public function fetchGlSubLedgers(string $accountCode, ?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            $account = App\ExecuteRow(
                "SELECT account_id, account_code, account_title, account_type FROM {$this->tableCoa} WHERE account_code = " . $this->str($accountCode),
                'DB'
            );
            if (!$account) {
                return ['success' => false, 'message' => "Account code '{$accountCode}' does not exist in the chart of accounts."];
            }
            $debitNormal = in_array($account['account_type'], self::DEBIT_NORMAL_TYPES, true);

            $sls = App\ExecuteRows(
                "SELECT c.account_code, c.account_title
                 FROM {$this->tableCoa} c
                 WHERE c.parent_account_id = " . $this->str($account['account_id']) . "
                   AND NOT EXISTS (SELECT 1 FROM {$this->tableCoa} k WHERE k.parent_account_id = c.account_id)
                 ORDER BY c.account_code",
                'DB'
            ) ?: [];

            $subLedgers = [];
            foreach ($sls as $sl) {
                $subLedgers[$sl['account_code']] = [
                    'sl'           => ['account_code' => $sl['account_code'], 'account_title' => $sl['account_title']],
                    'data'         => [],
                    'total_debit'  => 0.0,
                    'total_credit' => 0.0,
                ];
            }

            if ($subLedgers) {
                $where = '';
                if ($fiscalYr !== null) {
                    $where .= ' AND v.fiscal_yr = ' . (int) $fiscalYr;
                }
                if ($bookId !== null) {
                    $where .= ' AND v.book_id = ' . (int) $bookId;
                }
                $codeList = implode(',', array_map(fn ($code) => $this->str($code), array_keys($subLedgers)));
                $sign     = $debitNormal ? '(v.debit - v.credit)' : '(v.credit - v.debit)';
                $rows     = App\ExecuteRows(
                    "SELECT v.gl_id, v.posting_date, v.jev_no, v.dv_no, v.doc_ref_no, v.account_code, v.debit, v.credit,
                            v.reference_no, v.description, v.reversed_gl_id,
                            SUM({$sign}) OVER (PARTITION BY v.account_code ORDER BY v.posting_date, v.gl_id) AS running_balance
                     FROM {$this->tableViewJournals} v
                     WHERE v.account_code IN ({$codeList}) {$where}
                     ORDER BY v.account_code, v.posting_date, v.gl_id",
                    'DB'
                ) ?: [];

                foreach ($rows as $row) {
                    $code = $row['account_code'];
                    unset($row['account_code']);
                    $subLedgers[$code]['data'][]        = $row;
                    $subLedgers[$code]['total_debit']  += (float) $row['debit'];
                    $subLedgers[$code]['total_credit'] += (float) $row['credit'];
                }
            }

            foreach ($subLedgers as &$s) {
                $s['total_debit']    = round($s['total_debit'], 2);
                $s['total_credit']   = round($s['total_credit'], 2);
                $s['ending_balance'] = $s['data'] ? (float) end($s['data'])['running_balance'] : 0.0;
            }
            unset($s);

            // Postings made straight to the GL account itself (not through one of its SL accounts) — shown
            // ahead of the SLs so the popup is never empty for an account that has postings but no SL children.
            $directWhere = '';
            if ($fiscalYr !== null) {
                $directWhere .= ' AND fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($bookId !== null) {
                $directWhere .= ' AND book_id = ' . (int) $bookId;
            }
            $directSign = $debitNormal ? '(debit - credit)' : '(credit - debit)';
            $directRows = App\ExecuteRows(
                "SELECT gl_id, posting_date, jev_no, dv_no, doc_ref_no, debit, credit, reference_no, description, reversed_gl_id,
                        SUM({$directSign}) OVER (ORDER BY posting_date, gl_id) AS running_balance
                 FROM {$this->tableViewJournals}
                 WHERE account_code = " . $this->str($accountCode) . " {$directWhere}
                 ORDER BY posting_date, gl_id",
                'DB'
            ) ?: [];
            $direct = [
                'data'           => $directRows,
                'total_debit'    => round(array_sum(array_column($directRows, 'debit')), 2),
                'total_credit'   => round(array_sum(array_column($directRows, 'credit')), 2),
                'ending_balance' => $directRows ? (float) end($directRows)['running_balance'] : 0.0,
            ];

            return [
                'success'        => true,
                'account'        => ['account_code' => $account['account_code'], 'account_title' => $account['account_title'], 'account_type' => $account['account_type']],
                'debit_normal'   => $debitNormal,
                'normal_balance' => $debitNormal ? 'DEBIT' : 'CREDIT',
                'direct'         => $direct,
                'sub_ledgers'    => array_values($subLedgers),
            ];
        } catch (\Exception $e) {
            App\LogError('GeneralLedgerService::fetchGlSubLedgers ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the sub-ledgers of this account.'];
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
        // Grouped by GL account: an SL account's postings roll up into its GL account (glRollupCte()) rather
        // than listing as a separate "account" — the SLs are drilled into from the GL row.
        $rows  = App\ExecuteRows(
            $this->glRollupCte() . "
             SELECT v.gl_id, v.posting_date, v.jev_no, v.dv_no, v.doc_ref_no, g.account_code, g.account_title, g.account_type,
                    v.sl_code, v.sl_name, v.debit, v.credit, v.reference_no, v.description, v.reversed_gl_id,
                    CASE WHEN v.account_code <> g.account_code THEN v.account_code END AS sl_account_code,
                    SUM(CASE WHEN g.account_type IN ('Asset', 'Expense') THEN (v.debit - v.credit) ELSE (v.credit - v.debit) END)
                        OVER (PARTITION BY g.account_code ORDER BY v.posting_date, v.gl_id) AS running_balance
             FROM {$this->tableViewJournals} v
             JOIN {$this->tableCoa} lc ON lc.account_code = v.account_code
             JOIN gl_of ON gl_of.leaf_id = lc.account_id
             JOIN {$this->tableCoa} g ON g.account_id = gl_of.gl_account_id
             WHERE 1 = 1 {$where}
             ORDER BY g.account_code, v.posting_date, v.gl_id",
            'DB'
        ) ?: [];

        $accounts = [];
        foreach ($rows as $row) {
            $code = $row['account_code'];
            if (!isset($accounts[$code])) {
                $debitNormal = in_array($row['account_type'], self::DEBIT_NORMAL_TYPES, true);
                $accounts[$code] = [
                    'account' => ['account_code' => $code, 'account_title' => $row['account_title'], 'account_type' => $row['account_type']],
                    'debit_normal' => $debitNormal,
                    'normal_balance' => $debitNormal ? 'DEBIT' : 'CREDIT',
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
     * Subsidiary Ledger — same ledger-card idea as fetchGeneralLedger(), but keyed by an SL
     * account (a child fims.chart_of_accounts row, parent_account_id IS NOT NULL) instead of a
     * top-level GL account. The normal-balance side follows the SL account's own parent/control
     * account (chart_of_accounts.parent_account_id -> account_type).
     * $slCode omitted/blank: every SL account with at least one posting, all at once (see
     * fetchAllSubsidiaryLedgers()) — the page's default view before one SL code is picked.
     */
    public function fetchSubsidiaryLedger(?string $slCode, ?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            if ($slCode === null || trim($slCode) === '') {
                return $this->fetchAllSubsidiaryLedgers($fiscalYr, $bookId);
            }

            $slAccount = App\ExecuteRow(
                "SELECT c.account_id, c.account_code, c.account_title, c.parent_account_id, p.account_type
                 FROM {$this->tableCoa} c
                 LEFT JOIN {$this->tableCoa} p ON p.account_id = c.parent_account_id
                 WHERE c.account_code = " . $this->str($slCode) . " AND c.parent_account_id IS NOT NULL
                   AND NOT EXISTS (SELECT 1 FROM {$this->tableCoa} k WHERE k.parent_account_id = c.account_id)",
                'DB'
            );
            if (!$slAccount) {
                return ['success' => false, 'message' => "SL account code '{$slCode}' does not exist or is not a subsidiary ledger."];
            }
            $debitNormal = in_array($slAccount['account_type'], self::DEBIT_NORMAL_TYPES, true);
            $sign        = $debitNormal ? '(debit - credit)' : '(credit - debit)';

            $where = ['account_code = ' . $this->str($slCode)];
            if ($fiscalYr !== null) {
                $where[] = 'fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($bookId !== null) {
                $where[] = 'book_id = ' . (int) $bookId;
            }
            $data = App\ExecuteRows(
                "SELECT gl_id, posting_date, jev_no, dv_no, doc_ref_no, account_code, account_title, debit, credit, reference_no, description, reversed_gl_id,
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
                'master' => ['account_code' => $slAccount['account_code'], 'account_title' => $slAccount['account_title'], 'account_type' => $slAccount['account_type']],
                'debit_normal' => $debitNormal,
                'normal_balance' => $debitNormal ? 'DEBIT' : 'CREDIT',
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
            "SELECT v.gl_id, v.posting_date, v.jev_no, v.dv_no, v.doc_ref_no, v.account_code, v.account_title,
                    v.debit, v.credit, v.reference_no, v.description, v.reversed_gl_id,
                    p.account_type,
                    SUM(CASE WHEN p.account_type IN ('Asset', 'Expense') THEN (v.debit - v.credit) ELSE (v.credit - v.debit) END)
                        OVER (PARTITION BY v.account_code ORDER BY v.posting_date, v.gl_id) AS running_balance
             FROM {$this->tableViewJournals} v
             JOIN {$this->tableCoa} c ON c.account_code = v.account_code AND c.parent_account_id IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM {$this->tableCoa} k WHERE k.parent_account_id = c.account_id)
             LEFT JOIN {$this->tableCoa} p ON p.account_id = c.parent_account_id
             WHERE 1=1 {$where}
             ORDER BY v.account_code, v.posting_date, v.gl_id",
            'DB'
        ) ?: [];

        $accounts = [];
        foreach ($rows as $row) {
            $code = $row['account_code'];
            if (!isset($accounts[$code])) {
                $debitNormal = in_array($row['account_type'], self::DEBIT_NORMAL_TYPES, true);
                $accounts[$code] = [
                    'master' => ['account_code' => $code, 'account_title' => $row['account_title'], 'account_type' => $row['account_type']],
                    'debit_normal' => $debitNormal,
                    'normal_balance' => $debitNormal ? 'DEBIT' : 'CREDIT',
                    'data' => [],
                    'total_debit' => 0.0,
                    'total_credit' => 0.0,
                ];
            }
            unset($row['account_title'], $row['account_type']);
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
