<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Statement of Changes in Net Assets/Equity — read-only, built from fims.general_ledger +
 * fims.chart_of_accounts (no new tables). Shares BalanceSheetService's cumulative-equity
 * convention (see that file's "Net Surplus/(Deficit) — unclosed" note) so the two reports agree
 * and this one's Closing Balance for year Y always equals its own Opening Balance for year Y+1,
 * whether or not NHA has posted year-end closing JEVs that sweep revenue/expense into equity.
 *
 * How the numbers are derived
 *   - Equity(y)   = actual 3-xx GL postings, cumulative through year y (credit - debit; equity is
 *                   credit-normal, same sign convention as BalanceSheetService's equity section).
 *   - Unclosed(y) = revenue (4-xx) minus expense (5-xx), cumulative through year y — i.e. every
 *                   year's operating result that hasn't (yet) been closed into a 3-xx account.
 *                   Cumulative, not just the requested year, for the same reason
 *                   BalanceSheetService sums it cumulatively: once closing JEVs start being
 *                   posted, Unclosed(y) naturally shrinks toward 0 without any code change here.
 *   - Opening Balance (Jan 1 of fiscal year Y)  = Equity(Y-1) + Unclosed(Y-1)
 *   - Closing Balance (Dec 31 of fiscal year Y) = Equity(Y)   + Unclosed(Y)
 *   - Surplus/(Deficit) for the period = Unclosed(Y) - Unclosed(Y-1), i.e. just Y's own
 *     revenue/expense result (not cumulative) — the line NHA actually wants to see move.
 *   - Adjustments = Equity(Y) - Equity(Y-1), i.e. whatever was posted directly to a 3-xx account
 *     during year Y itself (capital contributions, dividends, impairment, prior-year
 *     adjustments, or a closing JEV). The chart doesn't tag these by sub-type, so today they
 *     all land in this one catch-all line; Dividends/Impairment/etc. stay at 0 until Accounting
 *     tags those postings distinctly (e.g. by sl_code or a dedicated adjustment type).
 *   - Opening + Surplus/(Deficit) + Adjustments = Closing, by construction.
 */
class StatementOfChangesService
{
    private string $tableGl  = 'fims.general_ledger';
    private string $tableCoa = 'fims.chart_of_accounts';

    /** Equity(y)/Unclosed(y) through both {$fy} and {$fy}-1, plus Unclosed's {$fy}-only slice,
     *  in one pass over the GL. $bookFilter is "" (all books) or "AND g.book_id = N". */
    private function equitySql(string $yr, int $fy, string $bookFilter): string
    {
        $prev = $fy - 1;

        return "SELECT
                    SUM(CASE WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '3' AND {$yr} <= {$fy}   THEN g.credit - g.debit ELSE 0 END) AS equity_cur,
                    SUM(CASE WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '3' AND {$yr} <= {$prev} THEN g.credit - g.debit ELSE 0 END) AS equity_prev,
                    SUM(CASE WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '4' AND {$yr} <= {$fy}   THEN -(g.debit - g.credit)
                             WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '5' AND {$yr} <= {$fy}   THEN g.debit - g.credit ELSE 0 END) AS unclosed_cur,
                    SUM(CASE WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '4' AND {$yr} <= {$prev} THEN -(g.debit - g.credit)
                             WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '5' AND {$yr} <= {$prev} THEN g.debit - g.credit ELSE 0 END) AS unclosed_prev
                 FROM {$this->tableGl} g
                 JOIN {$this->tableCoa} c ON c.account_code = g.account_code
                 WHERE LEFT(LTRIM(c.account_code, '0'), 1) IN ('3', '4', '5')
                   {$bookFilter}";
    }

    /** Equity(y)/Unclosed(y) -> the four report figures described in the class doc comment. */
    private function deriveFigures(array $row): array
    {
        $equityCur    = (float) ($row['equity_cur'] ?? 0);
        $equityPrev   = (float) ($row['equity_prev'] ?? 0);
        $unclosedCur  = (float) ($row['unclosed_cur'] ?? 0);
        $unclosedPrev = (float) ($row['unclosed_prev'] ?? 0);

        return [
            'opening'         => round($equityPrev + $unclosedPrev, 2),
            'closing'         => round($equityCur + $unclosedCur, 2),
            'surplus_deficit' => round($unclosedCur - $unclosedPrev, 2),
            'adjustments'     => round($equityCur - $equityPrev, 2),
        ];
    }

    /**
     * GET /statement_of_changes?fiscal_yr=2026&book_id=1
     * Single-entity view: opening + changes + closing for one fiscal year, one book.
     */
    public function fetchStatementOfChanges(?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            $fy = $fiscalYr ?: (int) date('Y');
            $yr = 'COALESCE(g.fiscal_yr, EXTRACT(YEAR FROM g.posting_date)::int)';
            $bookFilter = $bookId ? "AND g.book_id = {$bookId}" : '';

            $row = App\ExecuteRow($this->equitySql($yr, $fy, $bookFilter), 'DB') ?: [];
            $figures = $this->deriveFigures($row);

            return [
                'success' => true,
                'data' => [
                    'fiscal_yr'       => $fy,
                    'book_id'         => $bookId,
                    'opening_balance' => ['total' => $figures['opening']],
                    'closing_balance' => ['total' => $figures['closing']],
                    'surplus_deficit' => $figures['surplus_deficit'],
                    'adjustments'     => $figures['adjustments'],
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('StatementOfChangesService::fetchStatementOfChanges ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Statement of Changes in Net Assets/Equity.'];
        }
    }

    /**
     * GET /statement_of_changes/consolidated?fiscal_yr=2026
     * Multi-entity view: opening + changes + closing for one fiscal year, broken out per
     * fims.books entity so the page can show a Consolidated total and a Main Office (NHA) column.
     */
    public function fetchConsolidatedStatementOfChanges(?int $fiscalYr = null): array
    {
        try {
            $fy = $fiscalYr ?: (int) date('Y');
            $yr = 'COALESCE(g.fiscal_yr, EXTRACT(YEAR FROM g.posting_date)::int)';
            $prev = $fy - 1;

            $rows = App\ExecuteRows(
                "SELECT b.book_id, b.name AS book_name,
                        SUM(CASE WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '3' AND {$yr} <= {$fy}   THEN g.credit - g.debit ELSE 0 END) AS equity_cur,
                        SUM(CASE WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '3' AND {$yr} <= {$prev} THEN g.credit - g.debit ELSE 0 END) AS equity_prev,
                        SUM(CASE WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '4' AND {$yr} <= {$fy}   THEN -(g.debit - g.credit)
                                 WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '5' AND {$yr} <= {$fy}   THEN g.debit - g.credit ELSE 0 END) AS unclosed_cur,
                        SUM(CASE WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '4' AND {$yr} <= {$prev} THEN -(g.debit - g.credit)
                                 WHEN LEFT(LTRIM(c.account_code, '0'), 1) = '5' AND {$yr} <= {$prev} THEN g.debit - g.credit ELSE 0 END) AS unclosed_prev
                 FROM fims.books b
                 LEFT JOIN {$this->tableGl} g ON g.book_id = b.book_id
                 LEFT JOIN {$this->tableCoa} c ON c.account_code = g.account_code
                                               AND LEFT(LTRIM(c.account_code, '0'), 1) IN ('3', '4', '5')
                 GROUP BY b.book_id, b.name
                 ORDER BY b.book_id",
                'DB'
            ) ?: [];

            $byBook = [];
            foreach ($rows as $row) {
                $byBook[(int) $row['book_id']] = $this->deriveFigures($row) + ['book_name' => $row['book_name']];
            }

            return [
                'success' => true,
                'data' => [
                    'fiscal_yr' => $fy,
                    'by_book'   => $byBook,
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('StatementOfChangesService::fetchConsolidatedStatementOfChanges ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Consolidated Statement of Changes.'];
        }
    }
}
