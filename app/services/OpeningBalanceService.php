<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Opening Balance Entry — seed account balances at the start of a fiscal year.
 *
 * Opening Balance is NOT a Journal Entry Voucher — it never touches fims.general_ledger or
 * fims.journal_entry_vouchers. It's a raw per-account starting figure stored directly in
 * fims.opening_balances (one row per fiscal_yr/book/account_code). fims.v_trial_balance folds
 * these in on top of GL activity (see alter 56), so for a fresh account with no other postings
 * yet, its Beginning Balance and Ending Balance on the Trial Balance report are the same number.
 *
 * Older fiscal years created before this change may still have a legacy OPENING-classified JEV
 * posted to the GL instead — those still work (their effect is already baked into GL activity
 * for that year), this service just doesn't create new ones anymore.
 *
 *   - Filtering chart_of_accounts to permanent accounts only (Asset/Liability/Equity;
 *     Revenue/Expense are excluded — they reset every year).
 *   - Grouping lines by parent account (chart_of_accounts.parent_account_id) for display —
 *     fims.sl_masters/sl_details were dropped (alter 37); SL-level detail is now just a
 *     child row of chart_of_accounts, not a separate table.
 *   - Saving again for an already-saved fiscal_yr/book_id pair is a free upsert (replace the
 *     line set) — no reversal needed, since there's no ledger transaction to protect. Only a
 *     legacy JEV-based year (saved before this change) still requires reversal first.
 */
class OpeningBalanceService
{
    private string $tableOpeningBalances = 'fims.opening_balances';
    private string $tableJev       = 'fims.journal_entry_vouchers';
    private string $tableGl        = 'fims.general_ledger';
    private string $tableCoa       = 'fims.chart_of_accounts';
    private string $tableFiscal    = 'fims.fiscal_year';
    private string $tableBooks     = 'fims.books';
    private string $tableSettingsStatus = 'fims.settings_status';

    /**
     * GET /opening_balance?fiscal_yr=2027&book_id=1
     * Fetch the form data: list of permanent accounts (Asset/Liability/Equity), pre-filled
     * from fims.opening_balances if a row already exists for this fiscal_yr/book_id.
     */
    public function fetchOpeningBalanceForm(?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            $fy = $fiscalYr ?: (int) date('Y');
            $bid = $bookId > 0 ? (int) $bookId : null;

            if ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = {$fy}", 'DB') === 0) {
                return ['success' => false, 'message' => "Fiscal year {$fy} does not exist."];
            }
            if ($bid !== null && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableBooks} WHERE book_id = {$bid}", 'DB') === 0) {
                return ['success' => false, 'message' => "Book #{$bid} does not exist."];
            }

            $accounts = App\ExecuteRows(
                "SELECT
                    ac.account_id,
                    ac.account_code,
                    ac.account_title,
                    ac.account_type,
                    ac.posting_allowed,
                    COALESCE(parent.account_code, ac.account_code) AS group_code,
                    COALESCE(parent.account_title, ac.account_title) AS group_title,
                    COALESCE(ob.debit, 0) AS debit,
                    COALESCE(ob.credit, 0) AS credit
                 FROM {$this->tableCoa} ac
                 LEFT JOIN {$this->tableCoa} parent ON parent.account_id = ac.parent_account_id
                 LEFT JOIN {$this->tableOpeningBalances} ob
                    ON ob.account_code = ac.account_code
                    AND ob.fiscal_yr = {$fy}
                    " . ($bid ? "AND ob.book_id = {$bid}" : '') . "
                 WHERE ac.posting_allowed = TRUE
                   AND ac.status = 'Active'
                   AND ac.account_type IN ('Asset', 'Liability', 'Equity')
                 ORDER BY ac.account_code",
                'DB'
            ) ?: [];

            // Fully locked once submitted — no edit, no reverse, no clear, through this page.
            // Either a saved fims.opening_balances row (the normal path) or a legacy OPENING JEV
            // (years saved before this change) locks it; there is no distinction anymore between
            // the two once posted.
            $legacyJev = App\ExecuteRow(
                "SELECT jev.jev_id, jev.jev_no FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$fy}
                   AND jev.jev_classification = 'OPENING'
                   " . ($bid ? "AND jev.book_id = {$bid}" : '') . "
                   AND st.name = 'POSTED'
                 LIMIT 1",
                'DB'
            );

            $existingRow = $bid ? App\ExecuteRow(
                "SELECT COUNT(*) AS cnt FROM {$this->tableOpeningBalances} WHERE fiscal_yr = {$fy} AND book_id = {$bid}",
                'DB'
            ) : null;
            $hasOpeningBalanceRows = $existingRow && (int) $existingRow['cnt'] > 0;

            return [
                'success' => true,
                'data' => [
                    'fiscal_yr' => $fy,
                    'book_id' => $bid,
                    'accounts' => $accounts,
                    'is_locked' => $legacyJev !== false || $hasOpeningBalanceRows,
                    'legacy_jev' => $legacyJev ?: null,
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('OpeningBalanceService::fetchOpeningBalanceForm ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the opening balance form.'];
        }
    }

    /**
     * GET /opening_balance/carry_forward?fiscal_yr=2027&book_id=1
     * Computes each permanent account's ending balance for the PRIOR fiscal year (fiscal_yr - 1)
     * for this book: that prior year's own beginning balance (fims.opening_balances, defaults to
     * 0) plus its own net GL movement (which already includes that year's Closing sweep into
     * Equity, if posted). Since every year's beginning balance already encapsulates everything
     * before it, this is NOT a cumulative scan across all history — just one year's worth.
     *
     * If the prior year's Closing Entry hasn't been posted yet, that year's Revenue/Expense net
     * income is still sitting in temporary accounts rather than swept into Government Equity —
     * so Equity's carried-forward figure would be understated. Flagged via prior_year_closed
     * rather than blocked, since Asset/Liability accounts are unaffected either way.
     */
    public function fetchCarryForward(int $fiscalYr, int $bookId): array
    {
        try {
            $priorYear = $fiscalYr - 1;

            if ($fiscalYr <= 0 || (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = {$fiscalYr}", 'DB') === 0) {
                return ['success' => false, 'message' => 'Valid fiscal_yr is required.'];
            }
            if ($bookId <= 0 || (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableBooks} WHERE book_id = {$bookId}", 'DB') === 0) {
                return ['success' => false, 'message' => 'Valid book_id is required.'];
            }
            if ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = {$priorYear}", 'DB') === 0) {
                return ['success' => false, 'message' => "There is no prior fiscal year ({$priorYear}) configured to carry forward from."];
            }

            $rows = App\ExecuteRows(
                "SELECT ac.account_code, ac.account_title,
                        COALESCE(ob.debit, 0) + COALESCE(gl.debit, 0) AS total_debit,
                        COALESCE(ob.credit, 0) + COALESCE(gl.credit, 0) AS total_credit
                 FROM {$this->tableCoa} ac
                 LEFT JOIN {$this->tableOpeningBalances} ob
                    ON ob.account_code = ac.account_code AND ob.fiscal_yr = {$priorYear} AND ob.book_id = {$bookId}
                 LEFT JOIN (
                    SELECT account_code, SUM(debit) AS debit, SUM(credit) AS credit
                    FROM {$this->tableGl}
                    WHERE fiscal_yr = {$priorYear} AND book_id = {$bookId}
                    GROUP BY account_code
                 ) gl ON gl.account_code = ac.account_code
                 WHERE ac.posting_allowed = TRUE
                   AND ac.status = 'Active'
                   AND ac.account_type IN ('Asset', 'Liability', 'Equity')
                   AND (ob.debit IS NOT NULL OR ob.credit IS NOT NULL OR gl.debit IS NOT NULL OR gl.credit IS NOT NULL)
                 ORDER BY ac.account_code",
                'DB'
            ) ?: [];

            $lines = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            foreach ($rows as $row) {
                $net = round((float) $row['total_debit'] - (float) $row['total_credit'], 2);
                if ($net === 0.0) {
                    continue;
                }
                $debit = $net > 0 ? $net : 0;
                $credit = $net < 0 ? -$net : 0;
                $lines[] = [
                    'account_code' => $row['account_code'],
                    'account_title' => $row['account_title'],
                    'debit' => $debit,
                    'credit' => $credit,
                ];
                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            $priorClosed = App\ExecuteRow(
                "SELECT jev.jev_id FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$priorYear}
                   AND jev.book_id = {$bookId}
                   AND jev.jev_classification = 'CLOSING'
                   AND st.name = 'POSTED'",
                'DB'
            );

            return [
                'success' => true,
                'data' => [
                    'prior_fiscal_yr' => $priorYear,
                    'prior_year_closed' => $priorClosed !== false,
                    'lines' => $lines,
                    'total_debit' => round($totalDebit, 2),
                    'total_credit' => round($totalCredit, 2),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('OpeningBalanceService::fetchCarryForward ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to compute the carry-forward balances.'];
        }
    }

    /**
     * POST /opening_balance
     * Save the beginning balances directly into fims.opening_balances — no JEV, nothing posted
     * to fims.general_ledger. Still requires total debits to equal total credits across the
     * full set (keeps the Trial Balance globally balanced), but there's no ledger transaction
     * to post, so there's no DRAFT/POSTED status — saving IS the final state.
     *
     * $body: {fiscal_yr, book_id, lines: [{account_code, debit, credit}]}
     * Returns: {success, message, data: {fiscal_yr, book_id, lines_saved}}
     */
    public function saveOpeningBalance(array $body, int $userId): array
    {
        try {
            $fy = (int) ($body['fiscal_yr'] ?? 0);
            $bid = (int) ($body['book_id'] ?? 0);
            $lines = is_array($body['lines'] ?? null) ? $body['lines'] : [];

            $errors = [];

            if ($fy <= 0 || (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = {$fy}", 'DB') === 0) {
                $errors[] = 'Valid fiscal_yr is required.';
            }
            if ($bid <= 0 || (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableBooks} WHERE book_id = {$bid}", 'DB') === 0) {
                $errors[] = 'Valid book_id is required.';
            }
            if (count($lines) === 0) {
                $errors[] = 'At least one line is required.';
            }
            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }

            $totalDebit = 0;
            $totalCredit = 0;
            $cleanLines = [];

            foreach ($lines as $line) {
                $accountCode = trim((string) ($line['account_code'] ?? ''));
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                if ($debit === 0.0 && $credit === 0.0) {
                    continue;
                }
                if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                    $errors[] = "Line for account {$accountCode}: exactly one of debit or credit must be > 0.";
                    continue;
                }

                $account = App\ExecuteRow(
                    "SELECT account_code FROM {$this->tableCoa} WHERE account_code = " . App\QuotedValue($accountCode, App\DataType::STRING, 'DB')
                        . " AND posting_allowed = TRUE AND status = 'Active' AND account_type IN ('Asset', 'Liability', 'Equity')",
                    'DB'
                );
                if (!$account) {
                    $errors[] = "Account {$accountCode} not found or is not postable.";
                    continue;
                }

                $cleanLines[] = ['account_code' => $accountCode, 'debit' => $debit, 'credit' => $credit];
                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if ($errors) {
                return ['success' => false, 'message' => 'Line validation failed.', 'errors' => $errors];
            }
            if (count($cleanLines) < 2) {
                return ['success' => false, 'message' => 'At least two balanced lines are required.'];
            }
            if ($totalDebit <= 0 || abs($totalDebit - $totalCredit) > 0.01) {
                return ['success' => false, 'message' => 'Total debits (' . number_format($totalDebit, 2) . ') must equal total credits (' . number_format($totalCredit, 2) . ').'];
            }

            // Fully locked once submitted — no edit, no reverse, no clear. Saving again for an
            // already-saved fiscal_yr/book is refused outright, same as a legacy JEV-based year.
            $existing = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableOpeningBalances} WHERE fiscal_yr = {$fy} AND book_id = {$bid}",
                'DB'
            );
            if ($existing > 0) {
                return ['success' => false, 'message' => 'Opening balance for this fiscal year and book has already been submitted and is locked. It cannot be edited or cleared.'];
            }

            $legacyJev = App\ExecuteRow(
                "SELECT jev.jev_id FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$fy} AND jev.book_id = {$bid}
                   AND jev.jev_classification = 'OPENING' AND st.name = 'POSTED'",
                'DB'
            );
            if ($legacyJev) {
                return ['success' => false, 'message' => 'This fiscal year/book already has a legacy Opening Balance JEV posted and is locked.'];
            }

            App\Execute('BEGIN', 'DB');
            try {
                foreach ($cleanLines as $line) {
                    App\Execute(
                        "INSERT INTO {$this->tableOpeningBalances} (fiscal_yr, book_id, account_code, debit, credit, created_by)
                         VALUES ({$fy}, {$bid}, " . App\QuotedValue($line['account_code'], App\DataType::STRING, 'DB') . ', '
                            . (float) $line['debit'] . ', ' . (float) $line['credit'] . ', ' . (int) $userId . ')',
                        'DB'
                    );
                }
                App\Execute('COMMIT', 'DB');

                // Best-effort, non-fatal: refreshes each tagged bank account's
                // EARLIEST fims.cash_position row for this fiscal year (the only
                // row that actually originates from an opening balance — see
                // CashPositionService::refreshEarliestOpeningBalanceForAccountCode()'s
                // own docblock) so Cash Position doesn't keep showing a stale
                // GENERAL_LEDGER-derived figure after this save. Summed per
                // account_code in case the same code appears on more than one line.
                try {
                    $debitByAccountCode = [];
                    foreach ($cleanLines as $line) {
                        $debitByAccountCode[$line['account_code']] = ($debitByAccountCode[$line['account_code']] ?? 0) + $line['debit'];
                    }
                    $cashPositionService = new CashPositionService();
                    foreach ($debitByAccountCode as $accountCode => $debitTotal) {
                        $cashPositionService->refreshEarliestOpeningBalanceForAccountCode($accountCode, $fy, $debitTotal);
                    }
                } catch (\Exception $e) {
                    App\LogError('OpeningBalanceService::saveOpeningBalance cash position refresh ' . $e->getMessage());
                }

                return [
                    'success' => true,
                    'message' => 'Opening balance saved successfully.',
                    'data' => [
                        'fiscal_yr' => $fy,
                        'book_id' => $bid,
                        'lines_saved' => count($cleanLines),
                    ],
                ];
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }
        } catch (\Exception $e) {
            App\LogError('OpeningBalanceService::saveOpeningBalance ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to save opening balance.'];
        }
    }

    /**
     * Internal only — not exposed via any route or button. A submitted Opening Balance is fully
     * locked: no edit, no reverse, no clear, through the Opening Balance page itself. The one
     * exception is ClosingBalanceService::reverseClosingBalance(): since a carried-forward
     * Opening Balance only exists because a Closing auto-created it, undoing that Closing must
     * also undo its own side effect, or the carried-forward row would be permanently stranded
     * with no way to remove it. Deletes unconditionally — callers must already have verified the
     * row they're removing is the auto-carried one (ClosingBalanceService does, via fiscal_yr+1).
     */
    public function deleteCarryForwardRows(int $fiscalYr, int $bookId): void
    {
        App\Execute(
            "DELETE FROM {$this->tableOpeningBalances} WHERE fiscal_yr = {$fiscalYr} AND book_id = {$bookId}",
            'DB'
        );
    }
}
