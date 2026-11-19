<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Opening Balance Entry — seed account balances at the start of a fiscal year.
 *
 * Opening Balance is a dedicated Journal Entry Voucher (jev_classification = 'OPENING')
 * that posts to the GL exactly like any other transaction. No new ledger table or
 * balance-storage mechanism — just normal DR/CR entries that anchor all subsequent
 * reports (Trial Balance, Balance Sheet, Statement of Changes) to the correct starting
 * point instead of zero.
 *
 * Reuses the existing JEV → postJev() → fims.general_ledger pipeline. The only new
 * pieces are:
 *   - Filtering chart_of_accounts to permanent accounts only (Asset/Liability/Equity;
 *     Revenue/Expense are excluded — they reset every year).
 *   - Grouping lines by parent account (chart_of_accounts.parent_account_id) for display —
 *     fims.sl_masters/sl_details were dropped (alter 37); SL-level detail is now just a
 *     child row of chart_of_accounts, not a separate table.
 *   - Rejecting duplicate OPENING JEVs for a fiscal_yr/book_id pair (unless the prior one
 *     is still DRAFT and the user is editing it in-place).
 */
class OpeningBalanceService
{
    private string $tableJev       = 'fims.journal_entry_vouchers';
    private string $tableJevLines  = 'fims.journal_entry_voucher_lines';
    private string $tableGl        = 'fims.general_ledger';
    private string $tableCoa       = 'fims.chart_of_accounts';
    private string $tableFiscal    = 'fims.fiscal_year';
    private string $tableBooks     = 'fims.books';
    private string $tableSettingsStatus = 'fims.settings_status';

    /**
     * GET /opening_balance?fiscal_yr=2027&book_id=1
     * Fetch the form data: list of permanent accounts (Asset/Liability/Equity),
     * optionally nested SL details, and any existing DRAFT OPENING JEV for this
     * fiscal_yr/book_id if one exists (for edit-in-place).
     */
    public function fetchOpeningBalanceForm(?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            $fy = $fiscalYr ?: (int) date('Y');
            $bid = $bookId > 0 ? (int) $bookId : null;

            // Verify fiscal year exists
            if ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = {$fy}", 'DB') === 0) {
                return ['success' => false, 'message' => "Fiscal year {$fy} does not exist."];
            }

            // If book_id provided, verify it exists
            if ($bid !== null && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableBooks} WHERE book_id = {$bid}", 'DB') === 0) {
                return ['success' => false, 'message' => "Book #{$bid} does not exist."];
            }

            // Fetch permanent accounts (Asset, Liability, Equity) — exclude temporary (Revenue, Expense).
            // Sub-ledger detail is no longer a separate table (fims.sl_masters/sl_details were
            // dropped — see alter 37); SL-level detail is now expressed as child rows of
            // chart_of_accounts via parent_account_id, so we self-join for the group header.
            $accounts = App\ExecuteRows(
                "SELECT
                    ac.account_id,
                    ac.account_code,
                    ac.account_title,
                    ac.account_type,
                    ac.posting_allowed,
                    COALESCE(parent.account_code, ac.account_code) AS group_code,
                    COALESCE(parent.account_title, ac.account_title) AS group_title,
                    -- Pre-fill from existing DRAFT OPENING JEV, if present
                    COALESCE(jl.debit, 0) AS debit,
                    COALESCE(jl.credit, 0) AS credit
                 FROM {$this->tableCoa} ac
                 LEFT JOIN {$this->tableCoa} parent ON parent.account_id = ac.parent_account_id
                 LEFT JOIN {$this->tableJevLines} jl
                    ON jl.account_code = ac.account_code
                    AND jl.jev_id IN (
                        SELECT jev_id FROM {$this->tableJev} jev
                        JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                        WHERE jev.fiscal_yr = {$fy}
                          AND jev.jev_classification = 'OPENING'
                          " . ($bid ? "AND jev.book_id = {$bid}" : '') . "
                          AND st.name = 'DRAFT'
                        LIMIT 1  -- only one DRAFT OPENING per (fiscal_yr, book_id)
                    )
                 WHERE ac.posting_allowed = TRUE
                   AND ac.status = 'Active'
                   AND ac.account_type IN ('Asset', 'Liability', 'Equity')
                 ORDER BY ac.account_code",
                'DB'
            ) ?: [];

            // Check if a DRAFT OPENING JEV already exists for this fiscal_yr/book_id
            $existingJev = App\ExecuteRow(
                "SELECT jev.jev_id, jev.jev_no, st.name AS status
                 FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$fy}
                   AND jev.jev_classification = 'OPENING'
                   " . ($bid ? "AND jev.book_id = {$bid}" : '') . "
                   AND st.name = 'DRAFT'
                 LIMIT 1",
                'DB'
            );

            // Check if a POSTED OPENING JEV already exists (blocks creation of new ones)
            $postedJev = App\ExecuteRow(
                "SELECT jev.jev_id, jev.jev_no, st.name AS status
                 FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$fy}
                   AND jev.jev_classification = 'OPENING'
                   " . ($bid ? "AND jev.book_id = {$bid}" : '') . "
                   AND st.name = 'POSTED'
                 LIMIT 1",
                'DB'
            );

            return [
                'success' => true,
                'data' => [
                    'fiscal_yr' => $fy,
                    'book_id' => $bid,
                    'accounts' => $accounts,
                    'existing_draft_jev' => $existingJev,
                    'existing_posted_jev' => $postedJev,
                    'can_edit' => $existingJev !== false && $existingJev['status'] === 'DRAFT',
                    'is_locked' => $postedJev !== false,
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('OpeningBalanceService::fetchOpeningBalanceForm ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the opening balance form.'];
        }
    }

    /**
     * GET /opening_balance/carry_forward?fiscal_yr=2027&book_id=1
     * Computes each permanent (Asset/Liability/Equity) account's true cumulative ending
     * balance through the end of the prior fiscal year (fiscal_yr - 1) for this book — i.e.
     * ALL general_ledger activity up to and including that year, not just that year's own
     * postings. This is what the new fiscal year's Opening Balance should equal.
     *
     * Note: if the prior year's Closing Entry hasn't been posted yet, that year's Revenue/
     * Expense net income is still sitting in temporary accounts rather than swept into
     * Government Equity — so Equity's carried-forward figure would be understated. We flag
     * this via prior_year_closed rather than blocking, since Asset/Liability accounts are
     * unaffected either way.
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
                        COALESCE(SUM(gl.debit), 0) AS total_debit,
                        COALESCE(SUM(gl.credit), 0) AS total_credit
                 FROM {$this->tableCoa} ac
                 LEFT JOIN {$this->tableGl} gl
                    ON gl.account_code = ac.account_code
                    AND gl.book_id = {$bookId}
                    AND gl.fiscal_yr <= {$priorYear}
                 WHERE ac.posting_allowed = TRUE
                   AND ac.status = 'Active'
                   AND ac.account_type IN ('Asset', 'Liability', 'Equity')
                 GROUP BY ac.account_code, ac.account_title
                 HAVING COALESCE(SUM(gl.debit), 0) <> COALESCE(SUM(gl.credit), 0)
                 ORDER BY ac.account_code",
                'DB'
            ) ?: [];

            $lines = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            foreach ($rows as $row) {
                $net = round((float) $row['total_debit'] - (float) $row['total_credit'], 2);
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
     * Save (or update) opening balance entries and post the JEV to GL.
     *
     * $body: {fiscal_yr, book_id, lines: [{account_code, sl_detail_id?, debit, credit}]}
     * Returns: {success, message, data: {jev_id, jev_no, status}}
     */
    public function saveOpeningBalance(array $body, int $userId): array
    {
        try {
            $fy = (int) ($body['fiscal_yr'] ?? 0);
            $bid = (int) ($body['book_id'] ?? 0);
            $lines = is_array($body['lines'] ?? null) ? $body['lines'] : [];

            $errors = [];

            // Validate fiscal year
            if ($fy <= 0 || (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = {$fy}", 'DB') === 0) {
                $errors[] = 'Valid fiscal_yr is required.';
            }

            // Validate book
            if ($bid <= 0 || (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableBooks} WHERE book_id = {$bid}", 'DB') === 0) {
                $errors[] = 'Valid book_id is required.';
            }

            if (count($lines) === 0) {
                $errors[] = 'At least one line is required.';
            }

            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }

            // Validate and clean lines
            $totalDebit = 0;
            $totalCredit = 0;
            $cleanLines = [];

            foreach ($lines as $line) {
                $accountCode = trim((string) ($line['account_code'] ?? ''));
                $debit = (float) ($line['debit'] ?? 0);
                $credit = (float) ($line['credit'] ?? 0);

                // Skip lines with zero amounts
                if ($debit === 0.0 && $credit === 0.0) {
                    continue;
                }

                // Validate exactly one of debit or credit is > 0
                if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                    $errors[] = "Line for account {$accountCode}: exactly one of debit or credit must be > 0.";
                    continue;
                }

                // Validate account exists and is postable
                $account = App\ExecuteRow(
                    "SELECT account_code FROM {$this->tableCoa} WHERE account_code = " . App\QuotedValue($accountCode, App\DataType::STRING, 'DB')
                        . " AND posting_allowed = TRUE AND status = 'Active' AND account_type IN ('Asset', 'Liability', 'Equity')",
                    'DB'
                );
                if (!$account) {
                    $errors[] = "Account {$accountCode} not found or is not postable.";
                    continue;
                }

                $cleanLines[] = [
                    'account_code' => $accountCode,
                    'debit' => $debit,
                    'credit' => $credit,
                ];

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if ($errors) {
                return ['success' => false, 'message' => 'Line validation failed.', 'errors' => $errors];
            }

            if (count($cleanLines) < 2) {
                return ['success' => false, 'message' => 'At least two balanced lines are required.'];
            }

            // Check balance
            if (!$errors && ($totalDebit <= 0 || abs($totalDebit - $totalCredit) > 0.01)) {
                return ['success' => false, 'message' => 'Total debits (' . number_format($totalDebit, 2) . ') must equal total credits (' . number_format($totalCredit, 2) . ').'];
            }

            // Check for existing POSTED OPENING JEV (block re-creation)
            $posted = App\ExecuteRow(
                "SELECT jev.jev_id FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$fy}
                   AND jev.book_id = {$bid}
                   AND jev.jev_classification = 'OPENING'
                   AND st.name = 'POSTED'",
                'DB'
            );
            if ($posted) {
                return ['success' => false, 'message' => 'Opening balance for this fiscal year and book has already been posted. Reverse the entry first if you need to edit it.'];
            }

            // Check for DRAFT OPENING JEV (edit-in-place if present)
            $draft = App\ExecuteRow(
                "SELECT jev.jev_id FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$fy}
                   AND jev.book_id = {$bid}
                   AND jev.jev_classification = 'OPENING'
                   AND st.name = 'DRAFT'",
                'DB'
            );

            $jevId = $draft['jev_id'] ?? null;

            App\Execute('BEGIN', 'DB');
            try {
                if ($jevId) {
                    // Edit-in-place: replace lines on existing DRAFT JEV
                    App\Execute("DELETE FROM {$this->tableJevLines} WHERE jev_id = {$jevId}", 'DB');
                    $lineNo = 1;
                    foreach ($cleanLines as $line) {
                        App\Execute(
                            "INSERT INTO {$this->tableJevLines} (jev_id, line_number, account_code, debit, credit)
                             VALUES ({$jevId}, {$lineNo}, " . App\QuotedValue($line['account_code'], App\DataType::STRING, 'DB') . ', '
                                . (float) $line['debit'] . ', ' . (float) $line['credit'] . ')',
                            'DB'
                        );
                        $lineNo++;
                    }
                    // Update totals on header
                    App\Execute(
                        "UPDATE {$this->tableJev} SET total_debit = {$totalDebit}, total_credit = {$totalCredit}, updated_by = {$userId}, updated_at = CURRENT_TIMESTAMP
                         WHERE jev_id = {$jevId}",
                        'DB'
                    );
                } else {
                    // Create new DRAFT JEV
                    $draftStatusId = App\ExecuteScalar(
                        "SELECT id FROM {$this->tableSettingsStatus} WHERE status_for = 'journal_entry_voucher' AND name = 'DRAFT'",
                        'DB'
                    );
                    $jevNo = $this->nextJevNumber($fy);
                    $jevDate = $fy . '-01-01';  // Opening balance always Jan 1 of the fiscal year

                    $row = App\ExecuteRow(
                        "INSERT INTO {$this->tableJev}
                            (jev_no, jev_date, fiscal_yr, book_id, jev_classification, particulars, total_debit, total_credit, status_id, created_by)
                         VALUES (" . App\QuotedValue($jevNo, App\DataType::STRING, 'DB') . ', '
                            . App\QuotedValue($jevDate, App\DataType::STRING, 'DB') . ', '
                            . (int) $fy . ', ' . (int) $bid . ", 'OPENING', 'Opening Balance Entry', "
                            . (float) $totalDebit . ', ' . (float) $totalCredit . ', '
                            . (int) $draftStatusId . ', ' . (int) $userId . ')
                         RETURNING jev_id',
                        'DB'
                    );
                    $jevId = (int) $row['jev_id'];

                    $lineNo = 1;
                    foreach ($cleanLines as $line) {
                        App\Execute(
                            "INSERT INTO {$this->tableJevLines} (jev_id, line_number, account_code, debit, credit)
                             VALUES ({$jevId}, {$lineNo}, " . App\QuotedValue($line['account_code'], App\DataType::STRING, 'DB') . ', '
                                . (float) $line['debit'] . ', ' . (float) $line['credit'] . ')',
                            'DB'
                        );
                        $lineNo++;
                    }
                }

                // Post the JEV to GL via GeneralLedgerService::postJev()
                $glService = new GeneralLedgerService();
                $postResult = $glService->postJev($jevId, $userId);

                if (!$postResult['success']) {
                    App\Execute('ROLLBACK', 'DB');

                    return $postResult;
                }

                App\Execute('COMMIT', 'DB');

                // Refresh Cash Position's earliest entry for every bank
                // account tagged to a debited GL code in this JEV — see
                // CashPositionService::refreshEarliestOpeningBalanceForAccountCode()'s
                // docblock. Best-effort: runs after COMMIT (the JEV is
                // already correctly posted either way), and a failure here
                // doesn't fail the whole save — Cash Position would just
                // pick the new opening balance up next time
                // getComputedBalance() runs for that account/year instead.
                $debitByAccountCode = [];
                foreach ($cleanLines as $line) {
                    if ($line['debit'] > 0) {
                        $debitByAccountCode[$line['account_code']] = ($debitByAccountCode[$line['account_code']] ?? 0) + $line['debit'];
                    }
                }
                try {
                    $cashPositionService = new CashPositionService();
                    foreach ($debitByAccountCode as $accountCode => $debitTotal) {
                        $cashPositionService->refreshEarliestOpeningBalanceForAccountCode($accountCode, $fy, $debitTotal);
                    }
                } catch (\Exception $e) {
                    App\LogError('OpeningBalanceService::saveOpeningBalance cash position refresh: ' . $e->getMessage());
                }

                // Fetch the posted JEV for the response
                $jev = App\ExecuteRow(
                    "SELECT jev.jev_id, jev.jev_no, st.name AS status
                     FROM {$this->tableJev} jev
                     JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                     WHERE jev.jev_id = {$jevId}",
                    'DB'
                );

                return [
                    'success' => true,
                    'message' => 'Opening balance posted successfully.',
                    'data' => [
                        'jev_id' => $jev['jev_id'],
                        'jev_no' => $jev['jev_no'],
                        'status' => $jev['status'],
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
     * Generate the next JEV number for a given date (fiscal year).
     * Pattern: JEV-YYYY-NNN (e.g., JEV-2027-001)
     */
    private function nextJevNumber(int $fiscalYear): string
    {
        $count = (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM {$this->tableJev} WHERE fiscal_yr = {$fiscalYear}",
            'DB'
        );

        return 'JEV-' . $fiscalYear . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * POST /opening_balance/reverse
     * Reverses the POSTED OPENING JEV for this fiscal_yr/book_id — the only way to unlock the
     * form once it's been posted (editing a DRAFT in place is fine; a POSTED one must be undone
     * through the GL, never edited/deleted directly). Superadmin only — same bar as reversing
     * any other posted JEV elsewhere in the app.
     */
    public function reverseOpeningBalance(int $fiscalYr, int $bookId, int $userId): array
    {
        try {
            if (!GlobalService::getUserRcScope()['is_admin']) {
                return ['success' => false, 'message' => 'Only an administrator can reverse a posted Opening Balance.', 'httpCode' => 403];
            }

            $jev = App\ExecuteRow(
                "SELECT jev.jev_id, jev.jev_no FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$fiscalYr}
                   AND jev.book_id = {$bookId}
                   AND jev.jev_classification = 'OPENING'
                   AND st.name = 'POSTED'",
                'DB'
            );
            if (!$jev) {
                return ['success' => false, 'message' => "No posted Opening Balance found for fiscal year {$fiscalYr}, this book."];
            }

            App\Execute('BEGIN', 'DB');
            try {
                $glService = new GeneralLedgerService();
                $result = $glService->reverseJev((int) $jev['jev_id'], $userId);
                if (!$result['success']) {
                    App\Execute('ROLLBACK', 'DB');

                    return $result;
                }
                App\Execute('COMMIT', 'DB');

                return ['success' => true, 'message' => "Opening Balance {$jev['jev_no']} reversed — this fiscal year/book is unlocked again.", 'data' => $result['data']];
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }
        } catch (\Exception $e) {
            App\LogError('OpeningBalanceService::reverseOpeningBalance ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to reverse the Opening Balance.'];
        }
    }
}
