<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Balance Sheet (Statement of Financial Position), read-only, built from ONE query over
 * fims.general_ledger + fims.chart_of_accounts (no new tables/views).
 *
 * How the numbers are derived
 *   - Cumulative: a balance sheet is a position "as of" a fiscal year, so every posting with
 *     fiscal_yr <= the requested year is summed (fims.v_trial_balance is per-year only, so it
 *     isn't used here). fiscal_yr is nullable on the GL, so posting_date's year is the fallback.
 *     The single-entity query also returns the prior year-end (fiscal_yr <= year - 1) for the
 *     comparative column.
 *   - SL/child accounts roll up into their GL account the same way
 *     GeneralLedgerService::fetchTrialBalance() does (climb only through postable parents).
 *   - Each GL account is tagged with its level-2 group (e.g. "1-01 Cash and Cash Equivalents",
 *     the child of the class root "1-00-00-000 Assets") AND, where the chart goes one level
 *     deeper, its level-3 subgroup (e.g. "1-01-01 Cash on Hand", the child of the group) — walking
 *     the parent_account_id chain by depth-from-root rather than matching code prefixes, since
 *     code formats vary ('1-01', '2-08-01', '1-01-01-020'). An account with no subgroup (it sits
 *     directly under its group) gets subgroup_code/title = null.
 *   - Sign: assets are debit-normal (debit - credit); liabilities/equity are credit-normal.
 *   - Revenue (4) and Expense (5) accounts are netted into one equity line,
 *     "Net Surplus/(Deficit) — unclosed". Whether or not CLOSING JEVs have moved that into the
 *     3-xx accounts, the sheet balances either way; after closing this line simply becomes 0.
 *   - "Net Value" rows: whenever an account is immediately followed (by account_code order,
 *     within the same subgroup/group) by its contra account (title starting "Accumulated
 *     Depreciation" or "Allowance for Impairment" — the NGAS naming convention, not a flag in the
 *     chart), a computed "Net Value-{account}" row is inserted after the contra row, equal to the
 *     account's balance plus the contra's (the contra is already negative/credit-signed). This
 *     matches the printed NGAS Statement of Financial Position; it is display-only and does not
 *     affect any subtotal (the two real accounts are already included in it).
 *
 * Current / Non-Current split is NOT in the chart (it only has Asset/Liability/Equity), so it is
 * defined by the UACS group maps below — adjust the lists if Accounting classifies differently.
 */
class BalanceSheetService
{
    private string $tableGl  = 'fims.general_ledger';
    private string $tableCoa = 'fims.chart_of_accounts';

    // UACS group keys ("1-01" = first two code segments, leading zero stripped).
    // Assets not listed here are non-current; liabilities not listed are current.
    // Live chart: 1-01 Cash, 1-02 Investments, 1-03 Receivables, 1-04 Inventories are current;
    // 1-05 Investment Property, 1-06 PPE, 1-07+ (incl. 1-11 "Non-Current Assets Held for Sale") are not.
    private const CURRENT_ASSET_GROUPS        = ['1-01', '1-02', '1-03', '1-04'];
    private const NON_CURRENT_LIABILITY_GROUPS = ['2-06', '2-07', '2-09'];

    private const SECTIONS = [
        'current_assets'          => ['label' => 'Current Assets',          'type' => 'asset',     'order' => 1],
        'non_current_assets'      => ['label' => 'Non-Current Assets',      'type' => 'asset',     'order' => 2],
        'current_liabilities'     => ['label' => 'Current Liabilities',     'type' => 'liability', 'order' => 3],
        'non_current_liabilities' => ['label' => 'Non-Current Liabilities', 'type' => 'liability', 'order' => 4],
        'equity'                  => ['label' => 'Net Assets / Equity',     'type' => 'equity',    'order' => 5],
    ];

    // Row granularity a view mode shows — same sections/subtotals/grand-totals in every mode,
    // just how far down each section's rows go. "consolidated" is the default (group subtotals
    // only, e.g. "1-01 Cash and Cash Equivalents" with no accounts under it); "detailed" shows
    // every account, subgroup and Net Value row; "condensed" drops even the groups, down to just
    // the section total (e.g. "Total Current Assets").
    private const VIEW_MODES = ['consolidated', 'detailed', 'condensed'];

    // NGAS naming convention for a contra account (not a chart flag) — used both to italicize the
    // row on the page and, here, to know which account a computed "Net Value" row pairs with.
    private const CONTRA_PATTERN = '/^(Accumulated Depreciation|Allowance for Impairment)/i';

    /** The recursive CTEs shared by both queries below: every leaf's GL-level ancestor (gl_of),
     *  plus its level-2 group and level-3 subgroup (nullable), by depth from the class root. */
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
                     -- Level-2 ancestor = the child of the class root (e.g. 1-01 Cash and Cash
                     -- Equivalents under 1-00-00-000 Assets); the account itself if it is the root.
                     SELECT u.acc_id, u.account_id AS root_id
                     FROM up u JOIN up_depth d ON d.acc_id = u.acc_id
                     WHERE u.depth = GREATEST(d.root_depth - 1, 0)
                 ),
                 subgroup_of AS (
                     -- Level-3 ancestor = the child of the group (e.g. 1-01-01 Cash on Hand under
                     -- 1-01 Cash and Cash Equivalents) — only when the chart actually goes that
                     -- deep (root_depth >= 2); absent otherwise (the account sits under the group).
                     SELECT u.acc_id, u.account_id AS subgroup_id
                     FROM up u JOIN up_depth d ON d.acc_id = u.acc_id
                     WHERE d.root_depth >= 2 AND u.depth = d.root_depth - 2
                 )";
    }

    /**
     * @param int|null    $fiscalYr Position as of the end of this fiscal year (default: current year)
     * @param int|null    $bookId   Optional fims.books filter; null = all books
     * @param string|null $viewMode One of self::VIEW_MODES; null/unrecognized = 'consolidated'
     */
    public function fetchBalanceSheet(?int $fiscalYr = null, ?int $bookId = null, ?string $viewMode = null): array
    {
        try {
            $fy   = $fiscalYr ?: (int) date('Y');
            $prev = $fy - 1;
            $yr   = 'COALESCE(g.fiscal_yr, EXTRACT(YEAR FROM g.posting_date)::int)';
            $book = $bookId !== null ? ' AND g.book_id = ' . (int) $bookId : '';

            // The single query. Drives off chart_of_accounts (every Active, posting-allowed GL
            // account), LEFT JOINed to its cumulative postings — same shape as
            // GeneralLedgerService::fetchTrialBalance() — so an account with no postings yet still
            // comes back as a row with net_cur/net_prev = 0, instead of being silently absent.
            // net_* = SUM(debit - credit) cumulative to that year-end.
            $rows = App\ExecuteRows(
                $this->hierarchyCte() . ",
                 posted AS (
                     SELECT gl_of.gl_account_id,
                            SUM(CASE WHEN {$yr} <= {$fy}   THEN g.debit - g.credit ELSE 0 END) AS net_cur,
                            SUM(CASE WHEN {$yr} <= {$prev} THEN g.debit - g.credit ELSE 0 END) AS net_prev
                     FROM {$this->tableGl} g
                     JOIN {$this->tableCoa} c ON c.account_code = g.account_code
                     JOIN gl_of              ON gl_of.leaf_id = c.account_id
                     WHERE {$yr} <= {$fy}{$book}
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
                   AND LEFT(LTRIM(gl.account_code, '0'), 1) IN ('1', '2', '3', '4', '5')
                 ORDER BY gl.account_code",
                'DB'
            ) ?: [];

            $mode = in_array($viewMode, self::VIEW_MODES, true) ? $viewMode : 'consolidated';

            return ['success' => true, 'data' => $this->build($rows, $fy, $prev, $bookId, $mode)];
        } catch (\Exception $e) {
            App\LogError('BalanceSheetService::fetchBalanceSheet ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Balance Sheet.'];
        }
    }

    /** "01-01" / "1-01-01-020" -> "1-01" (first two segments, leading zero stripped). */
    private function groupKey(string $code): string
    {
        $p = explode('-', $code);

        return ((int) $p[0]) . '-' . ($p[1] ?? '00');
    }

    /**
     * Consolidated Statement of Financial Position — one column per fims.books entity
     * (Main Office, CISFA, NGC, MPLC/PPMO) side by side, plus a TOTAL column, "as at" one
     * fiscal year-end (no prior-year/increase-decrease columns — this is the multi-entity
     * roll-up report, not the single-entity comparative one fetchBalanceSheet() builds).
     * No Adjustments or Eliminating-entry column yet — intercompany balances between the
     * entities are simply summed into TOTAL as-is.
     */
    public function fetchConsolidatedBalanceSheet(?int $fiscalYr = null): array
    {
        try {
            $fy = $fiscalYr ?: (int) date('Y');
            $yr = 'COALESCE(g.fiscal_yr, EXTRACT(YEAR FROM g.posting_date)::int)';

            $rows = App\ExecuteRows(
                $this->hierarchyCte() . ",
                 posted AS (
                     SELECT gl_of.gl_account_id, g.book_id,
                            SUM(CASE WHEN {$yr} <= {$fy} THEN g.debit - g.credit ELSE 0 END) AS net_cur
                     FROM {$this->tableGl} g
                     JOIN {$this->tableCoa} c ON c.account_code = g.account_code
                     JOIN gl_of              ON gl_of.leaf_id = c.account_id
                     WHERE {$yr} <= {$fy}
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
                   AND LEFT(LTRIM(gl.account_code, '0'), 1) IN ('1', '2', '3', '4', '5')
                 ORDER BY gl.account_code, b.book_id",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $this->buildConsolidated($rows, $fy)];
        } catch (\Exception $e) {
            App\LogError('BalanceSheetService::fetchConsolidatedBalanceSheet ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to fetch the Consolidated Statement of Financial Position.'];
        }
    }

    /**
     * Insert computed "Net Value-{title}" rows into an already-ordered (by account_code) account
     * list. $balanceOf/$addBalances abstract over the single-entity scalar shape ('balance') and
     * the consolidated by-book shape (array of balances), so this one routine serves both builds.
     *
     * @param array    $accounts    ordered account rows (each must have 'account_title')
     * @param callable $balanceOf   fn(array $account): mixed — the value(s) to sum for Net Value
     * @param callable $addBalances fn(mixed $a, mixed $b): mixed — elementwise add
     * @param callable $makeRow     fn(string $title, mixed $balance): array — builds the Net Value row
     */
    private function withNetValueRows(array $accounts, callable $balanceOf, callable $addBalances, callable $makeRow): array
    {
        $out  = [];
        $prev = null;
        foreach ($accounts as $a) {
            $out[] = $a;
            if ($prev !== null && preg_match(self::CONTRA_PATTERN, (string) $a['account_title'])) {
                $out[] = $makeRow('Net Value-' . $prev['account_title'], $addBalances($balanceOf($prev), $balanceOf($a)));
            }
            $prev = $a;
        }

        return $out;
    }

    private function build(array $rows, int $fy, int $prev, ?int $bookId, string $viewMode): array
    {
        $sections = [];
        foreach (self::SECTIONS as $key => $meta) {
            $sections[$key] = $meta + ['key' => $key, 'groups' => [], 'subtotal' => 0.0, 'prev_subtotal' => 0.0];
        }
        $surplus = ['balance' => 0.0, 'prev_balance' => 0.0]; // -(revenue + expense nets)

        foreach ($rows as $r) {
            $net  = (float) $r['net_cur'];
            $pnet = (float) $r['net_prev'];

            if ($r['class'] === '4' || $r['class'] === '5') {
                $surplus['balance']      -= $net;
                $surplus['prev_balance'] -= $pnet;
                continue;
            }
            if (!in_array($r['class'], ['1', '2', '3'], true)) {
                continue;
            }

            $gk = $this->groupKey($r['group_code']);
            if ($r['class'] === '1') {
                $sec  = in_array($gk, self::CURRENT_ASSET_GROUPS, true) ? 'current_assets' : 'non_current_assets';
                $sign = 1;
            } elseif ($r['class'] === '2') {
                $sec  = in_array($gk, self::NON_CURRENT_LIABILITY_GROUPS, true) ? 'non_current_liabilities' : 'current_liabilities';
                $sign = -1;
            } else {
                $sec  = 'equity';
                $sign = -1;
            }

            $bal  = round($sign * $net, 2);
            $pbal = round($sign * $pnet, 2);

            $g = &$sections[$sec]['groups'][$gk];
            $g ??= ['group_code' => $gk, 'group_title' => $r['group_title'], 'balance' => 0.0, 'prev_balance' => 0.0, 'subgroups' => []];
            $sgKey = $r['subgroup_code'] ?? '';
            $sgRef = &$g['subgroups'][$sgKey];
            $sgRef ??= ['group_code' => $r['subgroup_code'], 'group_title' => $r['subgroup_title'], 'balance' => 0.0, 'prev_balance' => 0.0, 'accounts' => []];
            $sgRef['accounts'][] = ['account_code' => $r['account_code'], 'account_title' => $r['account_title'], 'balance' => $bal, 'prev_balance' => $pbal];
            $sgRef['balance']         += $bal;
            $sgRef['prev_balance']    += $pbal;
            unset($sgRef);
            $g['balance']         += $bal;
            $g['prev_balance']    += $pbal;
            unset($g);

            $sections[$sec]['subtotal']      += $bal;
            $sections[$sec]['prev_subtotal'] += $pbal;
        }

        // Net surplus/(deficit) of revenue vs expense accounts, shown as its own equity line.
        $surplus = ['balance' => round($surplus['balance'], 2), 'prev_balance' => round($surplus['prev_balance'], 2)];
        $sections['equity']['subtotal']      += $surplus['balance'];
        $sections['equity']['prev_subtotal'] += $surplus['prev_balance'];

        $balanceOf   = fn ($a) => ['balance' => $a['balance'], 'prev_balance' => $a['prev_balance']];
        $addBalances = fn ($a, $b) => ['balance' => round($a['balance'] + $b['balance'], 2), 'prev_balance' => round($a['prev_balance'] + $b['prev_balance'], 2)];
        $makeRow     = fn ($title, $bal) => ['account_code' => '', 'account_title' => $title, 'balance' => $bal['balance'], 'prev_balance' => $bal['prev_balance']];

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
                    $sg['accounts']     = $this->withNetValueRows($sg['accounts'], $balanceOf, $addBalances, $makeRow);
                }
                unset($sg);
                // "consolidated" stops at the group subtotal (e.g. "Cash and Cash Equivalents")
                // without listing the subgroups/accounts under it; "detailed" keeps them.
                if ($viewMode !== 'detailed') {
                    $g['subgroups'] = [];
                }
            }
            unset($g);
            $s['groups']        = array_values($s['groups']);
            $s['subtotal']      = round($s['subtotal'], 2);
            $s['prev_subtotal'] = round($s['prev_subtotal'], 2);
            // "condensed" drops the groups too, down to just the section total (e.g. "Total Current Assets").
            if ($viewMode === 'condensed') {
                $s['groups'] = [];
            }
        }
        unset($s);

        $sum = fn (string $type, string $f) => round(array_sum(array_map(fn ($s) => $s['type'] === $type ? $s[$f] : 0, $sections)), 2);

        $assets = $sum('asset', 'subtotal');
        $liab   = $sum('liability', 'subtotal');
        $equity = $sum('equity', 'subtotal');

        return [
            'fiscal_yr' => $fy,
            'prev_year' => $prev,
            'book_id'   => $bookId,
            'view_mode' => $viewMode,
            'sections'  => array_values($sections),
            'net_surplus_deficit' => $surplus,
            'totals'    => [
                'total_assets'                  => $assets,
                'total_liabilities'             => $liab,
                'total_equity'                  => $equity,
                'total_liabilities_and_equity'  => round($liab + $equity, 2),
                'prev_total_assets'             => $sum('asset', 'prev_subtotal'),
                'prev_total_liabilities'        => $sum('liability', 'prev_subtotal'),
                'prev_total_equity'             => $sum('equity', 'prev_subtotal'),
            ],
            // Assets = Liabilities + Equity. False means the GL itself is out of balance
            // (e.g. missing opening balances or unposted collections), not an API fault.
            'balanced'      => abs($assets - ($liab + $equity)) < 0.005,
            'difference'    => round($assets - ($liab + $equity), 2),
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
        $surplusByBook = $zeroCols;

        foreach ($rows as $r) {
            $bookId = (int) $r['book_id'];
            $net    = (float) $r['net_cur'];

            if ($r['class'] === '4' || $r['class'] === '5') {
                $surplusByBook[$bookId] -= $net;
                continue;
            }
            if (!in_array($r['class'], ['1', '2', '3'], true)) {
                continue;
            }

            $gk = $this->groupKey($r['group_code']);
            if ($r['class'] === '1') {
                $sec  = in_array($gk, self::CURRENT_ASSET_GROUPS, true) ? 'current_assets' : 'non_current_assets';
                $sign = 1;
            } elseif ($r['class'] === '2') {
                $sec  = in_array($gk, self::NON_CURRENT_LIABILITY_GROUPS, true) ? 'non_current_liabilities' : 'current_liabilities';
                $sign = -1;
            } else {
                $sec  = 'equity';
                $sign = -1;
            }

            $bal = $sign * $net;

            $g = &$sections[$sec]['groups'][$gk];
            $g ??= ['group_code' => $gk, 'group_title' => $r['group_title'], 'by_book' => $zeroCols, 'total' => 0.0, 'subgroups' => []];
            $sgKey = $r['subgroup_code'] ?? '';
            $sgRef = &$g['subgroups'][$sgKey];
            $sgRef ??= ['group_code' => $r['subgroup_code'], 'group_title' => $r['subgroup_title'], 'by_book' => $zeroCols, 'total' => 0.0, 'accounts' => []];
            $a = &$sgRef['accounts'][$r['account_code']];
            $a ??= ['account_code' => $r['account_code'], 'account_title' => $r['account_title'], 'by_book' => $zeroCols, 'total' => 0.0];
            $a['by_book'][$bookId]  = ($a['by_book'][$bookId] ?? 0) + $bal;
            $a['total']            += $bal;
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

        // Net surplus/(deficit) of revenue vs expense accounts, per book, shown as its own equity line.
        $surplusTotal = round(array_sum($surplusByBook), 2);
        foreach ($surplusByBook as $bookId => $v) {
            $surplusByBook[$bookId] = round($v, 2);
            $sections['equity']['subtotal_by_book'][$bookId] += $surplusByBook[$bookId];
        }
        $sections['equity']['subtotal_total'] += $surplusTotal;

        $balanceOf   = fn ($a) => $a['by_book'];
        $addBalances = function ($a, $b) {
            $out = [];
            foreach ($a as $bookId => $v) {
                $out[$bookId] = round($v + ($b[$bookId] ?? 0), 2);
            }

            return $out;
        };
        $makeRow = fn ($title, $byBook) => ['account_code' => '', 'account_title' => $title, 'by_book' => $byBook, 'total' => round(array_sum($byBook), 2)];

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
                    $sg['accounts'] = $this->withNetValueRows($sg['accounts'], $balanceOf, $addBalances, $makeRow);
                    $sg['by_book']  = array_map(fn ($v) => round($v, 2), $sg['by_book']);
                    $sg['total']    = round($sg['total'], 2);
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

        $sum = fn (string $type) => array_sum(array_map(fn ($s) => $s['type'] === $type ? $s['subtotal_total'] : 0, $sections));
        $sumByBook = function (string $type) use ($sections, $bookIds) {
            $out = array_fill_keys($bookIds, 0.0);
            foreach ($sections as $s) {
                if ($s['type'] !== $type) {
                    continue;
                }
                foreach ($s['subtotal_by_book'] as $bookId => $v) {
                    $out[$bookId] += $v;
                }
            }

            return array_map(fn ($v) => round($v, 2), $out);
        };

        $assets = round($sum('asset'), 2);
        $liab   = round($sum('liability'), 2);
        $equity = round($sum('equity'), 2);

        return [
            'fiscal_yr' => $fy,
            'books'     => $books,
            'sections'  => array_values($sections),
            'net_surplus_deficit' => ['by_book' => $surplusByBook, 'total' => $surplusTotal],
            'totals'    => [
                'total_assets'                 => $assets,
                'total_liabilities'            => $liab,
                'total_equity'                 => $equity,
                'total_liabilities_and_equity' => round($liab + $equity, 2),
                'total_assets_by_book'                 => $sumByBook('asset'),
                'total_liabilities_by_book'            => $sumByBook('liability'),
                'total_equity_by_book'                 => $sumByBook('equity'),
            ],
            'balanced'   => abs($assets - ($liab + $equity)) < 0.005,
            'difference' => round($assets - ($liab + $equity), 2),
        ];
    }
}
