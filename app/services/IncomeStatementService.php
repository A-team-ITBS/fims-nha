<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Income Statement (Statement of Financial Performance), read-only, built from ONE query over
 * fims.general_ledger + fims.chart_of_accounts (no new tables/views) — same approach as
 * BalanceSheetService.php, adapted for a period statement instead of a position-as-of statement.
 *
 * How the numbers are derived
 *   - Period, not cumulative: unlike the Balance Sheet (a position "as of" year-end), an income
 *     statement covers ONE fiscal year, so postings are filtered to fiscal_yr = the requested year
 *     exactly (fiscal_yr is nullable on the GL, so posting_date's year is the fallback, same as
 *     BalanceSheetService). The single-entity query also returns the prior year's total for the
 *     comparative column.
 *   - Hierarchy: every Revenue (4) / Expense (5) leaf account is tagged with its level-2 "group"
 *     (e.g. "4-02 Service and Business Income", the child of the class root "4-00-00-000 Revenue")
 *     and, where the chart goes one level deeper, its level-3 "subgroup" (e.g. "4-02-01 Service
 *     Income") — walking the parent_account_id chain by depth-from-root exactly like
 *     BalanceSheetService::hierarchyCte(), not by matching code prefixes.
 *   - Sign: Revenue accounts are credit-normal (credit - debit, i.e. -(debit - credit));
 *     Expense accounts are debit-normal (debit - credit).
 *   - Statement layout (SECTIONS below) follows the NGAS Statement of Financial Performance:
 *     Revenue; less Current Operating Expenses (Personnel Services, Maintenance & Other Operating
 *     Expenses, Financial Expenses, Financial Assistance, Non-Cash Expenses); Surplus/(Deficit)
 *     from Current Operations; add Other Non-Operating Income/(Loss); less Discounts and Rebates;
 *     less Losses; Surplus/(Deficit) Before Subsidy; add/(deduct) Subsidy Income; Surplus/(Deficit)
 *     After Subsidy. "Financial Assistance" (chart group 5-02-14) and "Losses"/"Discounts and
 *     Rebates" (chart subgroups 5-05-04/5-05-05, normally under Non-Cash Expenses) are pulled out
 *     of their parent chart group and shown as their own line, matching how NHA Accounting
 *     presents the statement even though the chart nests them elsewhere.
 *   - This is the clean, chart-driven split. It does NOT replicate the one manual reclassification
 *     some NHA worksheets make — netting the "Assistance/Subsidies/Contribution-Others" expense
 *     account (5-02-14-990) against Subsidy Income — because that is a one-off presentation choice
 *     by Accounting, not something derivable from the chart of accounts itself. That account stays
 *     under Financial Assistance here. Tell us if it should move; it is a one-line change.
 */
class IncomeStatementService
{
    private string $tableGl  = 'fims.general_ledger';
    private string $tableCoa = 'fims.chart_of_accounts';

    private const VIEW_MODES = ['consolidated', 'detailed', 'condensed'];

    // Level-2 chart groups ("4-02" = Revenue's first child, "5-05" = Expenses' 5th child, etc.)
    // that belong to "Other Non-Operating Income/(Loss)" instead of plain Revenue.
    private const NON_OPERATING_INCOME_GROUPS = ['4-05', '4-06'];
    // ...and to "Subsidy Income" instead of plain Revenue.
    private const SUBSIDY_INCOME_GROUPS = ['4-03'];

    private const SECTIONS = [
        'revenue'                     => ['label' => 'Revenue',                                    'sign' => 1,  'order' => 1],
        'current_operating_expenses'  => ['label' => 'Less: Current Operating Expenses',            'sign' => -1, 'order' => 2],
        'other_non_operating_income'  => ['label' => 'Add: Other Non-Operating Income/(Loss)',      'sign' => 1,  'order' => 3],
        'discounts_and_rebates'       => ['label' => 'Less: Discounts and Rebates',                 'sign' => -1, 'order' => 4],
        'losses'                      => ['label' => 'Less: Losses',                                'sign' => -1, 'order' => 5],
        'subsidy_income'              => ['label' => 'Add (Deduct): Subsidy Income',                'sign' => 1,  'order' => 6],
    ];

    /** Same recursive CTEs as BalanceSheetService::hierarchyCte() — see that file for how they work. */
    private function hierarchyCte(): string
    {
        return "WITH RECURSIVE climb AS (
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
                 up AS (
                     SELECT account_id AS acc_id, account_id, parent_account_id, 0 AS depth
                     FROM {$this->tableCoa}
                     UNION ALL
                     SELECT up.acc_id, p.account_id, p.parent_account_id, up.depth + 1
                     FROM up
                     JOIN {$this->tableCoa} p ON p.account_id = up.parent_account_id
                 ),
                 up_depth AS (
                     SELECT acc_id, MAX(depth) AS root_depth FROM up GROUP BY acc_id
                 ),
                 root_of AS (
                     SELECT u.acc_id, u.account_id AS root_id
                     FROM up u JOIN up_depth d ON d.acc_id = u.acc_id
                     WHERE u.depth = GREATEST(d.root_depth - 1, 0)
                 ),
                 subgroup_of AS (
                     SELECT u.acc_id, u.account_id AS subgroup_id
                     FROM up u JOIN up_depth d ON d.acc_id = u.acc_id
                     WHERE d.root_depth >= 2 AND u.depth = d.root_depth - 2
                 )";
    }

    /**
     * @param int|null    $fiscalYr The fiscal year this statement covers (default: current year)
     * @param int|null    $bookId   Optional fims.books filter; null = all books
     * @param string|null $viewMode One of self::VIEW_MODES; null/unrecognized = 'consolidated'
     */
    public function fetchIncomeStatement(?int $fiscalYr = null, ?int $bookId = null, ?string $viewMode = null): array
    {
        try {
            $fy   = $fiscalYr ?: (int) date('Y');
            $prev = $fy - 1;
            $yr   = 'COALESCE(g.fiscal_yr, EXTRACT(YEAR FROM g.posting_date)::int)';
            $book = $bookId !== null ? ' AND g.book_id = ' . (int) $bookId : '';

            $rows = App\ExecuteRows(
                $this->hierarchyCte() . ",
                 posted AS (
                     SELECT gl_of.gl_account_id,
                            SUM(CASE WHEN {$yr} = {$fy}   THEN g.debit - g.credit ELSE 0 END) AS net_cur,
                            SUM(CASE WHEN {$yr} = {$prev} THEN g.debit - g.credit ELSE 0 END) AS net_prev
                     FROM {$this->tableGl} g
                     JOIN {$this->tableCoa} c ON c.account_code = g.account_code
                     JOIN gl_of              ON gl_of.leaf_id = c.account_id
                     WHERE ({$yr} = {$fy} OR {$yr} = {$prev}){$book}
                     GROUP BY gl_of.gl_account_id
                 )
                 SELECT gl.account_code,
                        gl.account_title,
                        LEFT(LTRIM(gl.account_code, '0'), 1) AS class,
                        r.account_code          AS group_code,
                        r.account_title         AS group_title,
                        sg.account_code         AS subgroup_code,
                        sg.account_title        AS subgroup_title,
                        COALESCE(p.net_cur, 0)  AS net_cur,
                        COALESCE(p.net_prev, 0) AS net_prev
                 FROM {$this->tableCoa} gl
                 JOIN root_of ro          ON ro.acc_id = gl.account_id
                 JOIN {$this->tableCoa} r ON r.account_id = ro.root_id
                 LEFT JOIN subgroup_of so ON so.acc_id = gl.account_id
                 LEFT JOIN {$this->tableCoa} sg ON sg.account_id = so.subgroup_id
                 LEFT JOIN posted p      ON p.gl_account_id = gl.account_id
                 WHERE gl.posting_allowed = TRUE
                   AND gl.status = 'Active'
                   AND gl.account_id IN (SELECT leaf_id FROM gl_of WHERE leaf_id = gl_account_id)
                   AND LEFT(LTRIM(gl.account_code, '0'), 1) IN ('4', '5')
                 ORDER BY gl.account_code",
                'DB'
            ) ?: [];

            $mode = in_array($viewMode, self::VIEW_MODES, true) ? $viewMode : 'consolidated';

            return ['success' => true, 'data' => $this->build($rows, $fy, $prev, $bookId, $mode)];
        } catch (\Exception $e) {
            App\LogError('IncomeStatementService::fetchIncomeStatement ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Income Statement.'];
        }
    }

    /**
     * Consolidated Statement of Financial Performance — one column per fims.books entity
     * (Main Office, CISFA, NGC, MPLC/PPMO) side by side, plus a TOTAL column, for one fiscal year.
     * No Adjustments or Eliminating-entry columns — intercompany amounts between the entities are
     * simply summed into TOTAL as-is (same scope decision as
     * BalanceSheetService::fetchConsolidatedBalanceSheet()).
     */
    public function fetchConsolidatedIncomeStatement(?int $fiscalYr = null): array
    {
        try {
            $fy = $fiscalYr ?: (int) date('Y');
            $yr = 'COALESCE(g.fiscal_yr, EXTRACT(YEAR FROM g.posting_date)::int)';

            $rows = App\ExecuteRows(
                $this->hierarchyCte() . ",
                 posted AS (
                     SELECT gl_of.gl_account_id, g.book_id,
                            SUM(CASE WHEN {$yr} = {$fy} THEN g.debit - g.credit ELSE 0 END) AS net_cur
                     FROM {$this->tableGl} g
                     JOIN {$this->tableCoa} c ON c.account_code = g.account_code
                     JOIN gl_of              ON gl_of.leaf_id = c.account_id
                     WHERE {$yr} = {$fy}
                     GROUP BY gl_of.gl_account_id, g.book_id
                 )
                 SELECT gl.account_code,
                        gl.account_title,
                        LEFT(LTRIM(gl.account_code, '0'), 1) AS class,
                        r.account_code         AS group_code,
                        r.account_title        AS group_title,
                        sg.account_code        AS subgroup_code,
                        sg.account_title       AS subgroup_title,
                        b.book_id,
                        b.name                 AS book_name,
                        COALESCE(p.net_cur, 0) AS net_cur
                 FROM {$this->tableCoa} gl
                 JOIN root_of ro          ON ro.acc_id = gl.account_id
                 JOIN {$this->tableCoa} r ON r.account_id = ro.root_id
                 LEFT JOIN subgroup_of so ON so.acc_id = gl.account_id
                 LEFT JOIN {$this->tableCoa} sg ON sg.account_id = so.subgroup_id
                 CROSS JOIN fims.books b
                 LEFT JOIN posted p      ON p.gl_account_id = gl.account_id AND p.book_id = b.book_id
                 WHERE gl.posting_allowed = TRUE
                   AND gl.status = 'Active'
                   AND gl.account_id IN (SELECT leaf_id FROM gl_of WHERE leaf_id = gl_account_id)
                   AND LEFT(LTRIM(gl.account_code, '0'), 1) IN ('4', '5')
                 ORDER BY gl.account_code, b.book_id",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $this->buildConsolidated($rows, $fy)];
        } catch (\Exception $e) {
            App\LogError('IncomeStatementService::fetchConsolidatedIncomeStatement ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Consolidated Statement of Financial Performance.'];
        }
    }

    /**
     * Maps a chart row to the statement section it belongs in, and the group/subgroup it should
     * be filed under within that section — pulling "Financial Assistance" (5-02-14) and
     * "Losses"/"Discounts and Rebates" (5-05-04/5-05-05) out of their parent chart group so they
     * print as their own line, per the class comment above.
     *
     * @return array{section:string, group_code:string, group_title:string, subgroup_code:?string, subgroup_title:?string}
     */
    private function classify(array $r): array
    {
        $gc  = (string) $r['group_code'];
        $sgc = $r['subgroup_code'] !== null ? (string) $r['subgroup_code'] : null;

        if ($r['class'] === '4') {
            if (in_array($gc, self::SUBSIDY_INCOME_GROUPS, true)) {
                $section = 'subsidy_income';
            } elseif (in_array($gc, self::NON_OPERATING_INCOME_GROUPS, true)) {
                $section = 'other_non_operating_income';
            } else {
                $section = 'revenue';
            }

            return ['section' => $section, 'group_code' => $gc, 'group_title' => $r['group_title'], 'subgroup_code' => $sgc, 'subgroup_title' => $r['subgroup_title']];
        }

        // class === '5'
        if ($gc === '5-02' && $sgc === '5-02-14') {
            return ['section' => 'current_operating_expenses', 'group_code' => $sgc, 'group_title' => $r['subgroup_title'], 'subgroup_code' => null, 'subgroup_title' => null];
        }
        if ($gc === '5-05' && $sgc === '5-05-04') {
            return ['section' => 'losses', 'group_code' => $sgc, 'group_title' => $r['subgroup_title'], 'subgroup_code' => null, 'subgroup_title' => null];
        }
        if ($gc === '5-05' && $sgc === '5-05-05') {
            return ['section' => 'discounts_and_rebates', 'group_code' => $sgc, 'group_title' => $r['subgroup_title'], 'subgroup_code' => null, 'subgroup_title' => null];
        }

        return ['section' => 'current_operating_expenses', 'group_code' => $gc, 'group_title' => $r['group_title'], 'subgroup_code' => $sgc, 'subgroup_title' => $r['subgroup_title']];
    }

    private function build(array $rows, int $fy, int $prev, ?int $bookId, string $viewMode): array
    {
        $sections = [];
        foreach (self::SECTIONS as $key => $meta) {
            $sections[$key] = $meta + ['key' => $key, 'groups' => [], 'subtotal' => 0.0, 'prev_subtotal' => 0.0];
        }

        foreach ($rows as $r) {
            $net  = (float) $r['net_cur'];
            $pnet = (float) $r['net_prev'];
            // Revenue is credit-normal (positive when credited); Expense is debit-normal.
            $sign = $r['class'] === '4' ? -1 : 1;
            $bal  = round($sign * $net, 2);
            $pbal = round($sign * $pnet, 2);

            $c = $this->classify($r);
            $sec = $c['section'];

            $g = &$sections[$sec]['groups'][$c['group_code']];
            $g ??= ['group_code' => $c['group_code'], 'group_title' => $c['group_title'], 'balance' => 0.0, 'prev_balance' => 0.0, 'subgroups' => []];
            $sgKey = $c['subgroup_code'] ?? '';
            $sgRef = &$g['subgroups'][$sgKey];
            $sgRef ??= ['group_code' => $c['subgroup_code'], 'group_title' => $c['subgroup_title'], 'balance' => 0.0, 'prev_balance' => 0.0, 'accounts' => []];
            $sgRef['accounts'][] = ['account_code' => $r['account_code'], 'account_title' => $r['account_title'], 'balance' => $bal, 'prev_balance' => $pbal];
            $sgRef['balance']      += $bal;
            $sgRef['prev_balance'] += $pbal;
            unset($sgRef);
            $g['balance']      += $bal;
            $g['prev_balance'] += $pbal;
            unset($g);

            $sections[$sec]['subtotal']      += $bal;
            $sections[$sec]['prev_subtotal'] += $pbal;
        }

        foreach ($sections as &$s) {
            ksort($s['groups']);
            foreach ($s['groups'] as &$g) {
                $g['balance']      = round($g['balance'], 2);
                $g['prev_balance'] = round($g['prev_balance'], 2);
                ksort($g['subgroups']);
                $g['subgroups'] = array_values($g['subgroups']);
                foreach ($g['subgroups'] as &$sg) {
                    $sg['balance']      = round($sg['balance'], 2);
                    $sg['prev_balance'] = round($sg['prev_balance'], 2);
                }
                unset($sg);
                if ($viewMode !== 'detailed') {
                    $g['subgroups'] = [];
                }
            }
            unset($g);
            $s['groups']        = array_values($s['groups']);
            $s['subtotal']      = round($s['subtotal'], 2);
            $s['prev_subtotal'] = round($s['prev_subtotal'], 2);
            if ($viewMode === 'condensed') {
                $s['groups'] = [];
            }
        }
        unset($s);

        $totals = $this->computeTotals(array_map(fn ($s) => $s['subtotal'], $sections), array_map(fn ($s) => $s['prev_subtotal'], $sections));

        return [
            'fiscal_yr' => $fy,
            'prev_year' => $prev,
            'book_id'   => $bookId,
            'view_mode' => $viewMode,
            'sections'  => array_values($sections),
            'totals'    => $totals,
        ];
    }

    /** @param array<string,float> $subtotal @param array<string,float> $prevSubtotal */
    private function computeTotals(array $subtotal, array $prevSubtotal): array
    {
        $surplusFromOps       = $subtotal['revenue'] - $subtotal['current_operating_expenses'];
        $surplusBeforeSubsidy = $surplusFromOps + $subtotal['other_non_operating_income'] - $subtotal['discounts_and_rebates'] - $subtotal['losses'];
        $surplusAfterSubsidy  = $surplusBeforeSubsidy + $subtotal['subsidy_income'];

        $prevSurplusFromOps       = $prevSubtotal['revenue'] - $prevSubtotal['current_operating_expenses'];
        $prevSurplusBeforeSubsidy = $prevSurplusFromOps + $prevSubtotal['other_non_operating_income'] - $prevSubtotal['discounts_and_rebates'] - $prevSubtotal['losses'];
        $prevSurplusAfterSubsidy  = $prevSurplusBeforeSubsidy + $prevSubtotal['subsidy_income'];

        return [
            'total_revenue'                   => round($subtotal['revenue'], 2),
            'total_current_operating_expenses' => round($subtotal['current_operating_expenses'], 2),
            'surplus_from_operations'         => round($surplusFromOps, 2),
            'surplus_before_subsidy'          => round($surplusBeforeSubsidy, 2),
            'net_subsidy'                     => round($subtotal['subsidy_income'], 2),
            'surplus_after_subsidy'           => round($surplusAfterSubsidy, 2),
            'prev_total_revenue'                   => round($prevSubtotal['revenue'], 2),
            'prev_total_current_operating_expenses' => round($prevSubtotal['current_operating_expenses'], 2),
            'prev_surplus_from_operations'         => round($prevSurplusFromOps, 2),
            'prev_surplus_before_subsidy'          => round($prevSurplusBeforeSubsidy, 2),
            'prev_net_subsidy'                     => round($prevSubtotal['subsidy_income'], 2),
            'prev_surplus_after_subsidy'           => round($prevSurplusAfterSubsidy, 2),
        ];
    }

    private function buildConsolidated(array $rows, int $fy): array
    {
        $books = [];
        foreach ($rows as $r) {
            $books[(int) $r['book_id']] ??= ['book_id' => (int) $r['book_id'], 'name' => $r['book_name']];
        }
        ksort($books);
        $books    = array_values($books);
        $bookIds  = array_column($books, 'book_id');
        $zeroCols = array_fill_keys($bookIds, 0.0);

        $sections = [];
        foreach (self::SECTIONS as $key => $meta) {
            $sections[$key] = $meta + ['key' => $key, 'groups' => [], 'subtotal_by_book' => $zeroCols, 'subtotal_total' => 0.0];
        }

        foreach ($rows as $r) {
            $bookId = (int) $r['book_id'];
            $net    = (float) $r['net_cur'];
            $sign   = $r['class'] === '4' ? -1 : 1;
            $bal    = $sign * $net;

            $c = $this->classify($r);
            $sec = $c['section'];

            $g = &$sections[$sec]['groups'][$c['group_code']];
            $g ??= ['group_code' => $c['group_code'], 'group_title' => $c['group_title'], 'by_book' => $zeroCols, 'total' => 0.0, 'subgroups' => []];
            $sgKey = $c['subgroup_code'] ?? '';
            $sgRef = &$g['subgroups'][$sgKey];
            $sgRef ??= ['group_code' => $c['subgroup_code'], 'group_title' => $c['subgroup_title'], 'by_book' => $zeroCols, 'total' => 0.0, 'accounts' => []];
            $a = &$sgRef['accounts'][$r['account_code']];
            $a ??= ['account_code' => $r['account_code'], 'account_title' => $r['account_title'], 'by_book' => $zeroCols, 'total' => 0.0];
            $a['by_book'][$bookId] = ($a['by_book'][$bookId] ?? 0) + $bal;
            $a['total']           += $bal;
            unset($a);
            $sgRef['by_book'][$bookId] += $bal;
            $sgRef['total']            += $bal;
            unset($sgRef);
            $g['by_book'][$bookId] += $bal;
            $g['total']            += $bal;
            unset($g);

            $sections[$sec]['subtotal_by_book'][$bookId] += $bal;
            $sections[$sec]['subtotal_total']            += $bal;
        }

        foreach ($sections as &$s) {
            ksort($s['groups']);
            foreach ($s['groups'] as &$g) {
                ksort($g['subgroups']);
                $g['subgroups'] = array_values($g['subgroups']);
                foreach ($g['subgroups'] as &$sg) {
                    $sg['accounts'] = array_values($sg['accounts']);
                    foreach ($sg['accounts'] as &$a) {
                        $a['by_book'] = array_map(fn ($v) => round($v, 2), $a['by_book']);
                        $a['total']   = round($a['total'], 2);
                    }
                    unset($a);
                    $sg['by_book'] = array_map(fn ($v) => round($v, 2), $sg['by_book']);
                    $sg['total']   = round($sg['total'], 2);
                }
                unset($sg);
                $g['by_book'] = array_map(fn ($v) => round($v, 2), $g['by_book']);
                $g['total']   = round($g['total'], 2);
            }
            unset($g);
            $s['groups']           = array_values($s['groups']);
            $s['subtotal_by_book'] = array_map(fn ($v) => round($v, 2), $s['subtotal_by_book']);
            $s['subtotal_total']   = round($s['subtotal_total'], 2);
        }
        unset($s);

        $byBookTotals = function (string $metric) use ($sections, $bookIds): array {
            $out = array_fill_keys($bookIds, 0.0);
            switch ($metric) {
                case 'surplus_from_operations':
                    foreach ($bookIds as $id) {
                        $out[$id] = ($sections['revenue']['subtotal_by_book'][$id] ?? 0) - ($sections['current_operating_expenses']['subtotal_by_book'][$id] ?? 0);
                    }
                    break;
                case 'surplus_before_subsidy':
                    foreach ($bookIds as $id) {
                        $ops = ($sections['revenue']['subtotal_by_book'][$id] ?? 0) - ($sections['current_operating_expenses']['subtotal_by_book'][$id] ?? 0);
                        $out[$id] = $ops + ($sections['other_non_operating_income']['subtotal_by_book'][$id] ?? 0)
                            - ($sections['discounts_and_rebates']['subtotal_by_book'][$id] ?? 0)
                            - ($sections['losses']['subtotal_by_book'][$id] ?? 0);
                    }
                    break;
                case 'surplus_after_subsidy':
                    foreach ($bookIds as $id) {
                        $ops = ($sections['revenue']['subtotal_by_book'][$id] ?? 0) - ($sections['current_operating_expenses']['subtotal_by_book'][$id] ?? 0);
                        $beforeSubsidy = $ops + ($sections['other_non_operating_income']['subtotal_by_book'][$id] ?? 0)
                            - ($sections['discounts_and_rebates']['subtotal_by_book'][$id] ?? 0)
                            - ($sections['losses']['subtotal_by_book'][$id] ?? 0);
                        $out[$id] = $beforeSubsidy + ($sections['subsidy_income']['subtotal_by_book'][$id] ?? 0);
                    }
                    break;
            }

            return array_map(fn ($v) => round($v, 2), $out);
        };

        $surplusFromOpsByBook       = $byBookTotals('surplus_from_operations');
        $surplusBeforeSubsidyByBook = $byBookTotals('surplus_before_subsidy');
        $surplusAfterSubsidyByBook  = $byBookTotals('surplus_after_subsidy');

        return [
            'fiscal_yr' => $fy,
            'books'     => $books,
            'sections'  => array_values($sections),
            'totals'    => [
                'total_revenue'                    => $sections['revenue']['subtotal_total'],
                'total_current_operating_expenses' => $sections['current_operating_expenses']['subtotal_total'],
                'surplus_from_operations'          => round(array_sum($surplusFromOpsByBook), 2),
                'surplus_before_subsidy'           => round(array_sum($surplusBeforeSubsidyByBook), 2),
                'net_subsidy'                       => $sections['subsidy_income']['subtotal_total'],
                'surplus_after_subsidy'            => round(array_sum($surplusAfterSubsidyByBook), 2),
                'total_revenue_by_book'                    => $sections['revenue']['subtotal_by_book'],
                'total_current_operating_expenses_by_book' => $sections['current_operating_expenses']['subtotal_by_book'],
                'surplus_from_operations_by_book'          => $surplusFromOpsByBook,
                'surplus_before_subsidy_by_book'           => $surplusBeforeSubsidyByBook,
                'net_subsidy_by_book'                       => $sections['subsidy_income']['subtotal_by_book'],
                'surplus_after_subsidy_by_book'            => $surplusAfterSubsidyByBook,
            ],
        ];
    }
}
