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
 * fims.v_journals / fims.v_trial_balance (see "01 - fims/05 - views/01") are
 * read-only reports over fims.general_ledger — fetchJournals()/
 * fetchTrialBalance() here just add paging/filtering on top of them.
 */
class GeneralLedgerService
{
    private string $table         = 'fims.general_ledger';
    private string $tableJev      = 'fims.journal_entry_vouchers';
    private string $tableJevLines = 'fims.journal_entry_voucher_lines';
    private string $tableSettingsStatus = 'fims.settings_status';
    private string $tableViewJournals     = 'fims.v_journals';
    private string $tableViewTrialBalance = 'fims.v_trial_balance';

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
            "SELECT jev.jev_id, jev.jev_no, jev.fiscal_yr, jev.status_id, st.name AS status
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
                    (jev_id, jev_line_id, account_code, sl_code, sl_detail_id, rc_id, fiscal_yr,
                     debit, credit, reference_no, description, posted_by)
                 SELECT {$jevId}, " . (int) $line['jevl_id'] . ', '
                    . $this->str($line['account_code']) . ', '
                    . $this->strOrNull($line['sl_code']) . ', '
                    . $this->intOrNull($line['sl_detail_id']) . ', '
                    . $this->strOrNull($line['rc_id']) . ', '
                    . $this->intOrNull($jev['fiscal_yr']) . ', '
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
            "SELECT gl_id, jev_line_id, account_code, sl_code, sl_detail_id, rc_id, fiscal_yr, debit, credit
             FROM {$this->table} WHERE jev_id = {$jevId} AND reversed_gl_id IS NULL",
            'DB'
        ) ?: [];

        $uid = $userId > 0 ? (string) $userId : 'NULL';

        foreach ($postedRows as $row) {
            App\Execute(
                "INSERT INTO {$this->table}
                    (jev_id, jev_line_id, account_code, sl_code, sl_detail_id, rc_id, fiscal_yr,
                     debit, credit, reference_no, description, reversed_gl_id, posted_by)
                 VALUES ({$jevId}, " . (int) $row['jev_line_id'] . ', ' . $this->str($row['account_code']) . ', '
                    . $this->strOrNull($row['sl_code']) . ', '
                    . $this->intOrNull($row['sl_detail_id']) . ', '
                    . $this->strOrNull($row['rc_id']) . ', '
                    . $this->intOrNull($row['fiscal_yr']) . ', '
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

    /** fims.v_journals, paged and optionally filtered by fiscal year / account code / DV / JEV number. */
    public function fetchJournals(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $where = ['1=1'];
            if (!empty($filters['fiscal_yr']) && ctype_digit((string) $filters['fiscal_yr'])) {
                $where[] = 'fiscal_yr = ' . (int) $filters['fiscal_yr'];
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

    /** fims.v_trial_balance, optionally scoped to one fiscal year. */
    public function fetchTrialBalance(?int $fiscalYr = null): array
    {
        try {
            $where = $fiscalYr !== null ? 'WHERE fiscal_yr = ' . (int) $fiscalYr : '';
            $data  = App\ExecuteRows(
                "SELECT * FROM {$this->tableViewTrialBalance} {$where} ORDER BY account_code",
                'DB'
            ) ?: [];
            $totalDebit  = round(array_sum(array_column($data, 'total_debit')), 2);
            $totalCredit = round(array_sum(array_column($data, 'total_credit')), 2);

            return ['success' => true, 'data' => $data, 'total_debit' => $totalDebit, 'total_credit' => $totalCredit, 'balanced' => $totalDebit === $totalCredit];
        } catch (\Exception $e) {
            App\LogError('GeneralLedgerService::fetchTrialBalance ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Trial Balance.'];
        }
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
