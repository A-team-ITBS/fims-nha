<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Closing Balance Entry — zero out Revenue/Expense accounts at fiscal year end.
 *
 * Closing is the mirror image of Opening Balance: where Opening seeds permanent accounts
 * (Asset/Liability/Equity) at the start of a year, Closing zeroes out the temporary accounts
 * (Revenue/Expense) at the end of it, sweeping their net (Net Income or Net Loss) into
 * Government Equity. It posts a dedicated Journal Entry Voucher (jev_classification = 'CLOSING')
 * through the same GeneralLedgerService::postJev() pipeline Opening Balance uses.
 *
 * Unlike Opening Balance, amounts are never typed in by a user — they're read live off
 * fims.general_ledger for the fiscal year/book, so the preview always matches whatever has
 * actually been posted. There is no DRAFT-edit step; fetch builds a preview, close posts it.
 */
class ClosingBalanceService
{
    private string $tableJev       = 'fims.journal_entry_vouchers';
    private string $tableJevLines  = 'fims.journal_entry_voucher_lines';
    private string $tableGl        = 'fims.general_ledger';
    private string $tableCoa       = 'fims.chart_of_accounts';
    private string $tableFiscal    = 'fims.fiscal_year';
    private string $tableBooks     = 'fims.books';
    private string $tableSettingsStatus = 'fims.settings_status';
    private string $tableOpeningBalances = 'fims.opening_balances';

    // Government Equity — the Closing counterpart to Opening Balance's own offsetting account.
    private const EQUITY_ACCOUNT_CODE = '3-01-01-020';

    /**
     * GET /closing_balance?fiscal_yr=2026&book_id=1
     * Preview the closing entry: every Revenue/Expense account with activity this fiscal
     * year/book, the amount that would zero it out, the net income/loss, and whether a
     * CLOSING JEV already exists (locked) for this fiscal_yr/book.
     */
    public function fetchClosingBalanceForm(?int $fiscalYr = null, ?int $bookId = null): array
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

            [$accounts, $totalRevenue, $totalExpense] = $this->computeClosingLines($fy, $bid);

            $existingJev = App\ExecuteRow(
                "SELECT jev.jev_id, jev.jev_no, st.name AS status
                 FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$fy}
                   AND jev.jev_classification = 'CLOSING'
                   " . ($bid ? "AND jev.book_id = {$bid}" : '') . "
                 ORDER BY jev.jev_id DESC
                 LIMIT 1",
                'DB'
            );
            $isLocked = $existingJev !== false && $existingJev['status'] === 'POSTED';

            return [
                'success' => true,
                'data' => [
                    'fiscal_yr' => $fy,
                    'book_id' => $bid,
                    'accounts' => $accounts,
                    'total_revenue' => $totalRevenue,
                    'total_expense' => $totalExpense,
                    'net_income' => round($totalRevenue - $totalExpense, 2),
                    'existing_closing_jev' => $existingJev ?: null,
                    'is_locked' => $isLocked,
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('ClosingBalanceService::fetchClosingBalanceForm ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the closing balance preview.'];
        }
    }

    /**
     * POST /closing_balance
     * Recompute the same preview server-side (never trusts client-supplied amounts) and posts
     * it as a CLOSING JEV. Refuses if a CLOSING JEV is already POSTED for this fiscal_yr/book.
     */
    public function closeFiscalYear(int $fiscalYr, int $bookId, int $userId): array
    {
        try {
            if ($fiscalYr <= 0 || (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = {$fiscalYr}", 'DB') === 0) {
                return ['success' => false, 'message' => 'Valid fiscal_yr is required.'];
            }
            if ($bookId <= 0 || (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableBooks} WHERE book_id = {$bookId}", 'DB') === 0) {
                return ['success' => false, 'message' => 'Valid book_id is required.'];
            }

            $posted = App\ExecuteRow(
                "SELECT jev.jev_id FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$fiscalYr}
                   AND jev.book_id = {$bookId}
                   AND jev.jev_classification = 'CLOSING'
                   AND st.name = 'POSTED'",
                'DB'
            );
            if ($posted) {
                return ['success' => false, 'message' => 'Fiscal year ' . $fiscalYr . ' has already been closed for this book.'];
            }

            [$accounts, $totalRevenue, $totalExpense] = $this->computeClosingLines($fiscalYr, $bookId);
            $netIncome = round($totalRevenue - $totalExpense, 2);

            if (count($accounts) === 0) {
                return ['success' => false, 'message' => 'No Revenue or Expense activity found for this fiscal year/book — nothing to close.'];
            }

            $equityAccount = App\ExecuteRow(
                "SELECT account_code FROM {$this->tableCoa} WHERE account_code = " . App\QuotedValue(self::EQUITY_ACCOUNT_CODE, App\DataType::STRING, 'DB')
                    . " AND posting_allowed = TRUE AND status = 'Active'",
                'DB'
            );
            if (!$equityAccount) {
                return ['success' => false, 'message' => 'Equity account ' . self::EQUITY_ACCOUNT_CODE . ' is not postable — cannot close.'];
            }

            $lines = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;
            foreach ($accounts as $acc) {
                if ($acc['closing_debit'] > 0) {
                    $lines[] = ['account_code' => $acc['account_code'], 'debit' => $acc['closing_debit'], 'credit' => 0];
                    $totalDebit += $acc['closing_debit'];
                } elseif ($acc['closing_credit'] > 0) {
                    $lines[] = ['account_code' => $acc['account_code'], 'debit' => 0, 'credit' => $acc['closing_credit']];
                    $totalCredit += $acc['closing_credit'];
                }
            }
            if ($netIncome > 0) {
                $lines[] = ['account_code' => self::EQUITY_ACCOUNT_CODE, 'debit' => 0, 'credit' => $netIncome];
                $totalCredit += $netIncome;
            } elseif ($netIncome < 0) {
                $lines[] = ['account_code' => self::EQUITY_ACCOUNT_CODE, 'debit' => -$netIncome, 'credit' => 0];
                $totalDebit += -$netIncome;
            }

            $totalDebit = round($totalDebit, 2);
            $totalCredit = round($totalCredit, 2);
            if (abs($totalDebit - $totalCredit) > 0.01) {
                return ['success' => false, 'message' => 'Closing entry failed to balance (debit ' . $totalDebit . ' vs credit ' . $totalCredit . ') — this should not happen.'];
            }

            App\Execute('BEGIN', 'DB');
            try {
                $draftStatusId = App\ExecuteScalar(
                    "SELECT id FROM {$this->tableSettingsStatus} WHERE status_for = 'journal_entry_voucher' AND name = 'DRAFT'",
                    'DB'
                );
                $jevNo = $this->nextJevNumber($fiscalYr);
                $jevDate = $fiscalYr . '-12-31';

                $row = App\ExecuteRow(
                    "INSERT INTO {$this->tableJev}
                        (jev_no, jev_date, fiscal_yr, book_id, jev_classification, particulars, total_debit, total_credit, status_id, created_by)
                     VALUES (" . App\QuotedValue($jevNo, App\DataType::STRING, 'DB') . ', '
                        . App\QuotedValue($jevDate, App\DataType::STRING, 'DB') . ', '
                        . (int) $fiscalYr . ', ' . (int) $bookId . ", 'CLOSING', 'Closing Entry — Revenue & Expense sweep to Equity', "
                        . $totalDebit . ', ' . $totalCredit . ', '
                        . (int) $draftStatusId . ', ' . (int) $userId . ')
                     RETURNING jev_id',
                    'DB'
                );
                $jevId = (int) $row['jev_id'];

                $lineNo = 1;
                foreach ($lines as $line) {
                    App\Execute(
                        "INSERT INTO {$this->tableJevLines} (jev_id, line_number, account_code, debit, credit)
                         VALUES ({$jevId}, {$lineNo}, " . App\QuotedValue($line['account_code'], App\DataType::STRING, 'DB') . ', '
                            . (float) $line['debit'] . ', ' . (float) $line['credit'] . ')',
                        'DB'
                    );
                    $lineNo++;
                }

                $glService = new \PHPMaker2024\FIMS\Service\GeneralLedgerService();
                $postResult = $glService->postJev($jevId, $userId);

                if (!$postResult['success']) {
                    App\Execute('ROLLBACK', 'DB');

                    return $postResult;
                }

                App\Execute('COMMIT', 'DB');

                $jev = App\ExecuteRow(
                    "SELECT jev.jev_id, jev.jev_no, st.name AS status
                     FROM {$this->tableJev} jev
                     JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                     WHERE jev.jev_id = {$jevId}",
                    'DB'
                );

                // Auto-chain into next year's Opening Balance — this is the whole point of
                // Closing: the year isn't really "closed" until the next one has a starting
                // point. Runs as its own separate operation (closing's own transaction is
                // already committed above), using the now-accurate carry-forward (Equity
                // now includes the net income we just swept).
                $carryForward = $this->carryForwardToNextYear($fiscalYr, $bookId, $userId);

                return [
                    'success' => true,
                    'message' => 'Fiscal year ' . $fiscalYr . ' closed successfully.',
                    'data' => [
                        'jev_id' => $jev['jev_id'],
                        'jev_no' => $jev['jev_no'],
                        'status' => $jev['status'],
                        'net_income' => $netIncome,
                        'carry_forward' => $carryForward,
                    ],
                ];
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }
        } catch (\Exception $e) {
            App\LogError('ClosingBalanceService::closeFiscalYear ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to close the fiscal year.'];
        }
    }

    /**
     * Reads live fims.general_ledger totals for every postable Revenue/Expense account in this
     * fiscal_yr/book and works out the line that would zero each one out. Revenue is credit-normal
     * (closing_debit = credit - debit); Expense is debit-normal (closing_credit = debit - credit).
     * Accounts with no net activity are left out — nothing to close on them.
     *
     * @return array{0: array, 1: float, 2: float} [accounts, totalRevenue, totalExpense]
     */
    private function computeClosingLines(int $fiscalYr, ?int $bookId): array
    {
        $rows = App\ExecuteRows(
            "SELECT
                ac.account_code,
                ac.account_title,
                ac.account_type,
                COALESCE(SUM(gl.debit), 0) AS total_debit,
                COALESCE(SUM(gl.credit), 0) AS total_credit
             FROM {$this->tableCoa} ac
             LEFT JOIN {$this->tableGl} gl
                ON gl.account_code = ac.account_code
                AND gl.fiscal_yr = {$fiscalYr}
                " . ($bookId ? "AND gl.book_id = {$bookId}" : '') . "
             WHERE ac.posting_allowed = TRUE
               AND ac.status = 'Active'
               AND ac.account_type IN ('Revenue', 'Expense')
             GROUP BY ac.account_code, ac.account_title, ac.account_type
             HAVING COALESCE(SUM(gl.debit), 0) <> 0 OR COALESCE(SUM(gl.credit), 0) <> 0
             ORDER BY ac.account_type, ac.account_code",
            'DB'
        ) ?: [];

        $accounts = [];
        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($rows as $row) {
            $debit = (float) $row['total_debit'];
            $credit = (float) $row['total_credit'];

            if ($row['account_type'] === 'Revenue') {
                $amount = round($credit - $debit, 2);
                $closingDebit = $amount > 0 ? $amount : 0;
                $closingCredit = $amount < 0 ? -$amount : 0;
                $totalRevenue += $amount;
            } else {
                $amount = round($debit - $credit, 2);
                $closingCredit = $amount > 0 ? $amount : 0;
                $closingDebit = $amount < 0 ? -$amount : 0;
                $totalExpense += $amount;
            }

            $accounts[] = [
                'account_code' => $row['account_code'],
                'account_title' => $row['account_title'],
                'account_type' => $row['account_type'],
                'current_debit' => $debit,
                'current_credit' => $credit,
                'closing_debit' => $closingDebit,
                'closing_credit' => $closingCredit,
            ];
        }

        return [$accounts, round($totalRevenue, 2), round($totalExpense, 2)];
    }

    /**
     * Generate the next JEV number for a given fiscal year. Pattern: JEV-YYYY-NNN, same
     * counter/format OpeningBalanceService uses — both are "SETUP" jev_type, same sequence space.
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
     * Automatically seeds (and posts) the NEXT fiscal year's Opening Balance from this year's
     * now-closed ending balances, reusing OpeningBalanceService's own carry-forward computation
     * and save path — so it's exactly the same figures/validation a manual carry-forward would
     * produce, just triggered automatically instead of requiring a separate click.
     *
     * Never fails the Closing itself — Closing has already committed by the time this runs.
     * Any reason it can't proceed (next year not configured, already locked, nothing to carry)
     * comes back as a 'skipped' status for the caller to show, not an exception.
     *
     * @return array{status: string, lines_saved?: int, reason?: string}
     */
    private function carryForwardToNextYear(int $fiscalYr, int $bookId, int $userId): array
    {
        try {
            $nextYear = $fiscalYr + 1;

            if ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = {$nextYear}", 'DB') === 0) {
                return ['status' => 'skipped', 'reason' => "Fiscal year {$nextYear} is not configured yet."];
            }

            $alreadyLocked = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableOpeningBalances} WHERE fiscal_yr = {$nextYear} AND book_id = {$bookId}",
                'DB'
            ) > 0;
            if (!$alreadyLocked) {
                $legacyJev = App\ExecuteRow(
                    "SELECT jev.jev_id FROM {$this->tableJev} jev
                     JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                     WHERE jev.fiscal_yr = {$nextYear}
                       AND jev.book_id = {$bookId}
                       AND jev.jev_classification = 'OPENING'
                       AND st.name = 'POSTED'",
                    'DB'
                );
                $alreadyLocked = $legacyJev !== false;
            }
            if ($alreadyLocked) {
                return ['status' => 'skipped', 'reason' => "Fiscal year {$nextYear} already has a saved Opening Balance for this book."];
            }

            $openingService = new OpeningBalanceService();
            $carry = $openingService->fetchCarryForward($nextYear, $bookId);
            if (!$carry['success']) {
                return ['status' => 'skipped', 'reason' => $carry['message']];
            }
            if (count($carry['data']['lines']) === 0) {
                return ['status' => 'skipped', 'reason' => 'No ending balances to carry forward.'];
            }

            $saveResult = $openingService->saveOpeningBalance([
                'fiscal_yr' => $nextYear,
                'book_id' => $bookId,
                'lines' => array_map(
                    fn ($line) => ['account_code' => $line['account_code'], 'debit' => $line['debit'], 'credit' => $line['credit']],
                    $carry['data']['lines']
                ),
            ], $userId);

            if (!$saveResult['success']) {
                return ['status' => 'skipped', 'reason' => $saveResult['message'] ?? 'Failed to post the carried-forward Opening Balance.'];
            }

            return ['status' => 'posted', 'lines_saved' => $saveResult['data']['lines_saved'], 'fiscal_yr' => $nextYear];
        } catch (\Exception $e) {
            App\LogError('ClosingBalanceService::carryForwardToNextYear ' . $e->getMessage());

            return ['status' => 'skipped', 'reason' => 'An error occurred while carrying forward to next year.'];
        }
    }

    /**
     * POST /closing_balance/reverse
     * Reverses the POSTED CLOSING JEV for this fiscal_yr/book_id, unlocking the preview again.
     *
     * An Opening Balance is otherwise fully locked once submitted (no edit/reverse/clear via
     * its own page) — but a carried-forward one only exists because THIS Closing auto-created
     * it, so undoing the Closing also undoes that side effect automatically (via
     * OpeningBalanceService::deleteCarryForwardRows() — internal-only, no route of its own).
     * A legacy JEV-based next-year Opening (saved before this design existed) is a real posted
     * ledger transaction and is NOT auto-removed — that one still blocks, same as before.
     */
    public function reverseClosingBalance(int $fiscalYr, int $bookId, int $userId): array
    {
        try {
            if (!GlobalService::getUserRcScope()['is_admin']) {
                return ['success' => false, 'message' => 'Only an administrator can reverse a posted Closing Entry.', 'httpCode' => 403];
            }

            $jev = App\ExecuteRow(
                "SELECT jev.jev_id, jev.jev_no FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$fiscalYr}
                   AND jev.book_id = {$bookId}
                   AND jev.jev_classification = 'CLOSING'
                   AND st.name = 'POSTED'",
                'DB'
            );
            if (!$jev) {
                return ['success' => false, 'message' => "No posted Closing Entry found for fiscal year {$fiscalYr}, this book."];
            }

            $nextYear = $fiscalYr + 1;
            $nextYearHasCarriedRows = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableOpeningBalances} WHERE fiscal_yr = {$nextYear} AND book_id = {$bookId}",
                'DB'
            ) > 0;

            $legacyJev = App\ExecuteRow(
                "SELECT jev.jev_no FROM {$this->tableJev} jev
                 JOIN {$this->tableSettingsStatus} st ON st.id = jev.status_id
                 WHERE jev.fiscal_yr = {$nextYear}
                   AND jev.book_id = {$bookId}
                   AND jev.jev_classification = 'OPENING'
                   AND st.name = 'POSTED'",
                'DB'
            );
            if ($legacyJev) {
                return ['success' => false, 'message' => "Fiscal year {$nextYear} has a legacy Opening Balance JEV ({$legacyJev['jev_no']}) posted. Reverse that first — it's a real ledger transaction, not something this can clean up automatically."];
            }

            App\Execute('BEGIN', 'DB');
            try {
                if ($nextYearHasCarriedRows) {
                    (new OpeningBalanceService())->deleteCarryForwardRows($nextYear, $bookId);
                }

                $glService = new GeneralLedgerService();
                $result = $glService->reverseJev((int) $jev['jev_id'], $userId);
                if (!$result['success']) {
                    App\Execute('ROLLBACK', 'DB');

                    return $result;
                }
                App\Execute('COMMIT', 'DB');

                $msg = "Closing Entry {$jev['jev_no']} reversed — this fiscal year/book is unlocked again.";
                if ($nextYearHasCarriedRows) {
                    $msg .= " Fiscal year {$nextYear}'s carried-forward Opening Balance was removed along with it.";
                }

                return ['success' => true, 'message' => $msg, 'data' => $result['data']];
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }
        } catch (\Exception $e) {
            App\LogError('ClosingBalanceService::reverseClosingBalance ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to reverse the Closing Entry.'];
        }
    }
}
