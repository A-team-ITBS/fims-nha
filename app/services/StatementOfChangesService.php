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

    /** Equity columns of the SCNAE view, in display order. 'other' is only returned when something is posted to it. */
    private const EQUITY_COLUMNS = [
        'surplus'             => 'Accumulated Surplus',
        'gov_equity'          => 'Government Equity',
        'contributed_capital' => 'Contributed Capital',
        'revaluation_surplus' => 'Revaluation Surplus',
        'other'               => 'Other Equity',
    ];

    /**
     * GET /statement_of_changes/equity?fiscal_yr=2026&book_id=1
     * SCNAE layout (the audited NHA format): one column per equity component, the prior year's roll-forward
     * (opening -> changes -> closing) above the current year's, one block per entity (Consolidated, then each
     * book; or just the picked book).
     *
     * Same cumulative convention as the class doc comment above, applied per component:
     *   opening(c, Y) = cumulative 3-xx postings of component c through Y-1; closing = through Y; movement = closing - opening.
     *   Accumulated Surplus additionally carries the unclosed revenue - expense (4-xx / 5-xx).
     * Component is decided by the account code, never by the title:
     *   3-01-01-010 and 3-07 -> surplus | 3-01-01-020 -> gov_equity | 3-01-01-030 -> contributed_capital |
     *   3-02 -> revaluation_surplus | any other 3-xx -> other.
     * Not available from the GL today (no tag distinguishes them): Subsidy from National Government as an equity
     * movement (it is revenue, so it sits inside Surplus/(Deficit)) and Prior Period Adjustments.
     */
    public function fetchEquityStatement(?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            $fy  = $fiscalYr ?: (int) date('Y');
            $yr  = 'COALESCE(g.fiscal_yr, EXTRACT(YEAR FROM g.posting_date)::int)';
            $acc = "LTRIM(c.account_code, '0')";
            $comp = "CASE WHEN LEFT({$acc}, 1) IN ('4', '5') THEN 'unclosed'
                          WHEN {$acc} LIKE '3-01-01-010%' OR {$acc} LIKE '3-07%' THEN 'surplus'
                          WHEN {$acc} LIKE '3-01-01-020%' THEN 'gov_equity'
                          WHEN {$acc} LIKE '3-01-01-030%' THEN 'contributed_capital'
                          WHEN {$acc} LIKE '3-02%' THEN 'revaluation_surplus'
                          ELSE 'other' END";
            $bookWhere = $bookId ? "AND g.book_id = {$bookId}" : '';

            $books = App\ExecuteRows(
                'SELECT book_id, name FROM fims.books ' . ($bookId ? "WHERE book_id = {$bookId} " : '') . 'ORDER BY book_id',
                'DB'
            ) ?: [];

            $rows = App\ExecuteRows(
                "SELECT g.book_id, {$comp} AS comp, {$yr} AS yr, SUM(g.credit - g.debit) AS net
                 FROM {$this->tableGl} g
                 JOIN {$this->tableCoa} c ON c.account_code = g.account_code
                 WHERE LEFT({$acc}, 1) IN ('3', '4', '5') AND {$yr} <= {$fy} {$bookWhere}
                 GROUP BY g.book_id, 2, 3",
                'DB'
            ) ?: [];

            // $net[book_id][component][year] = credit - debit (revenue/expense too: credit - debit is their surplus effect)
            $net = [];
            foreach ($rows as $r) {
                $net[(int) $r['book_id']][$r['comp']][(int) $r['yr']] = (float) $r['net'];
            }

            $bookIds  = array_map(fn ($b) => (int) $b['book_id'], $books);
            $showOther = false;
            foreach ($bookIds as $id) {
                if (abs($this->cumulative($net, [$id], 'other', $fy)) > 0.004) {
                    $showOther = true;
                }
            }
            $columns = [];
            foreach (self::EQUITY_COLUMNS as $key => $label) {
                if ($key !== 'other' || $showOther) {
                    $columns[] = ['key' => $key, 'label' => $label];
                }
            }
            $keys = array_column($columns, 'key');

            $entity = function (string $name, ?int $id, array $ids) use ($net, $fy, $keys): array {
                $periods = [$this->equityPeriod($net, $ids, $fy - 1, $keys), $this->equityPeriod($net, $ids, $fy, $keys)];
                $active = false;
                foreach ($periods as $p) {
                    $active = $active || abs($p['opening']['total']) > 0.004 || abs($p['closing']['total']) > 0.004;
                }

                return ['book_id' => $id, 'name' => $name, 'has_activity' => $active, 'periods' => $periods];
            };

            $entities = [];
            if (!$bookId) {
                $entities[] = $entity('Consolidated', null, $bookIds);
            }
            foreach ($books as $b) {
                $entities[] = $entity($b['name'], (int) $b['book_id'], [(int) $b['book_id']]);
            }

            return ['success' => true, 'data' => [
                'fiscal_yr' => $fy,
                'columns'   => $columns,
                'entities'  => $entities,
            ]];
        } catch (\Exception $e) {
            App\LogError('StatementOfChangesService::fetchEquityStatement ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Statement of Changes by equity component.'];
        }
    }

    /** Sum of one component's postings through $upToYear, over the given books. */
    private function cumulative(array $net, array $bookIds, string $comp, int $upToYear): float
    {
        $sum = 0.0;
        foreach ($bookIds as $id) {
            foreach ($net[$id][$comp] ?? [] as $year => $amount) {
                if ($year <= $upToYear) {
                    $sum += $amount;
                }
            }
        }

        return $sum;
    }

    /** One fiscal year's roll-forward (opening -> changes -> closing) across the given books. */
    private function equityPeriod(array $net, array $bookIds, int $year, array $keys): array
    {
        $cum  = fn (string $c, int $y) => $this->cumulative($net, $bookIds, $c, $y);
        $blank = array_fill_keys($keys, 0.0);
        $withTotal = function (array $v) use ($keys): array {
            $v = array_map(fn ($x) => round($x, 2), $v);
            $v['total'] = round(array_sum(array_intersect_key($v, array_flip($keys))), 2);

            return $v;
        };
        $move = fn (string $c) => $cum($c, $year) - $cum($c, $year - 1);

        $opening = $blank;
        $closing = $blank;
        foreach ($keys as $k) {
            $opening[$k] = $cum($k, $year - 1);
            $closing[$k] = $cum($k, $year);
        }
        // the unclosed revenue - expense belongs to Accumulated Surplus
        $opening['surplus'] += $cum('unclosed', $year - 1);
        $closing['surplus'] += $cum('unclosed', $year);

        $surplusRow = $blank;
        $surplusRow['surplus'] = $move('unclosed');
        $capitalRow = $blank;
        $capitalRow['contributed_capital'] = $move('contributed_capital');
        $revalRow = $blank;
        $revalRow['revaluation_surplus'] = $move('revaluation_surplus');
        $otherRow = $blank;                                  // anything else posted straight to 3-xx during the year
        $otherRow['surplus'] = $move('surplus');
        $otherRow['gov_equity'] = $move('gov_equity');
        if (isset($otherRow['other'])) {
            $otherRow['other'] = $move('other');
        }

        return [
            'year'          => $year,
            'opening_label' => 'BALANCE AT JANUARY 1, ' . $year,
            'changes_label' => 'CHANGES IN NET ASSETS/EQUITY FOR CY ' . $year,
            'closing_label' => 'BALANCE AT DECEMBER 31, ' . $year,
            'opening'       => $withTotal($opening),
            'closing'       => $withTotal($closing),
            'rows'          => [
                ['type' => 'label', 'label' => 'Add/(Deduct):'],
                ['type' => 'item', 'label' => 'Surplus/(Deficit) for the period'] + $withTotal($surplusRow),
                ['type' => 'label', 'label' => 'Adjustments:'],
                ['type' => 'item', 'label' => 'Contributed Capital'] + $withTotal($capitalRow),
                ['type' => 'item', 'label' => 'Revaluation Increment/(Decrement)'] + $withTotal($revalRow),
                ['type' => 'item', 'label' => 'Other equity adjustments'] + $withTotal($otherRow),
            ],
        ];
    }
}
