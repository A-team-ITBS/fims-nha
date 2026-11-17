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
 *     The same query also returns the prior year-end (fiscal_yr <= year - 1) for the comparative column.
 *   - SL/child accounts roll up into their GL account the same way
 *     GeneralLedgerService::fetchTrialBalance() does (climb only through postable parents).
 *   - Each GL account is tagged with its level-2 chart-of-accounts group (the child of the class
 *     root, e.g. "1-01 Cash and Cash Equivalents" under "1-00-00-000 Assets"). Grouping walks the
 *     parent_account_id chain instead of matching code prefixes, since code formats vary
 *     ('1-01', '2-08-01', '1-01-01-020').
 *   - Sign: assets are debit-normal (debit - credit); liabilities/equity are credit-normal.
 *   - Revenue (4) and Expense (5) accounts are netted into one equity line,
 *     "Net Surplus/(Deficit) — unclosed". Whether or not CLOSING JEVs have moved that into the
 *     3-xx accounts, the sheet balances either way; after closing this line simply becomes 0.
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

    /**
     * @param int|null $fiscalYr Position as of the end of this fiscal year (default: current year)
     * @param int|null $bookId   Optional fims.books filter; null = all books
     */
    public function fetchBalanceSheet(?int $fiscalYr = null, ?int $bookId = null): array
    {
        try {
            $fy   = $fiscalYr ?: (int) date('Y');
            $prev = $fy - 1;
            $yr   = 'COALESCE(g.fiscal_yr, EXTRACT(YEAR FROM g.posting_date)::int)';
            $book = $bookId !== null ? ' AND g.book_id = ' . (int) $bookId : '';

            // The single query. net_* = SUM(debit - credit) cumulative to that year-end.
            $rows = App\ExecuteRows(
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
                 up AS (
                     SELECT account_id AS acc_id, account_id, parent_account_id, 0 AS depth
                     FROM {$this->tableCoa}
                     UNION ALL
                     SELECT up.acc_id, p.account_id, p.parent_account_id, up.depth + 1
                     FROM up
                     JOIN {$this->tableCoa} p ON p.account_id = up.parent_account_id
                 ),
                 root_of AS (
                     -- Level-2 ancestor = the child of the class root (e.g. 1-01 Cash and Cash
                     -- Equivalents under 1-00-00-000 Assets); the account itself if it is the root.
                     SELECT DISTINCT ON (acc_id) acc_id, account_id AS root_id
                     FROM (SELECT up.*, ROW_NUMBER() OVER (PARTITION BY acc_id ORDER BY depth DESC) AS rn FROM up) t
                     WHERE rn <= 2
                     ORDER BY acc_id, rn DESC
                 )
                 SELECT gl.account_code,
                        gl.account_title,
                        LEFT(LTRIM(gl.account_code, '0'), 1) AS class,
                        r.account_code  AS group_code,
                        r.account_title AS group_title,
                        SUM(CASE WHEN {$yr} <= {$fy}   THEN g.debit - g.credit ELSE 0 END) AS net_cur,
                        SUM(CASE WHEN {$yr} <= {$prev} THEN g.debit - g.credit ELSE 0 END) AS net_prev
                 FROM {$this->tableGl} g
                 JOIN {$this->tableCoa} c ON c.account_code = g.account_code
                 JOIN gl_of              ON gl_of.leaf_id = c.account_id
                 JOIN {$this->tableCoa} gl ON gl.account_id = gl_of.gl_account_id
                 JOIN root_of ro         ON ro.acc_id = gl.account_id
                 JOIN {$this->tableCoa} r  ON r.account_id = ro.root_id
                 WHERE {$yr} <= {$fy}{$book}
                 GROUP BY gl.account_code, gl.account_title, r.account_code, r.account_title
                 ORDER BY gl.account_code",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $this->build($rows, $fy, $prev, $bookId)];
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

    private function build(array $rows, int $fy, int $prev, ?int $bookId): array
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
            if (abs($net) < 0.005 && abs($pnet) < 0.005) {
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
            $g ??= ['group_code' => $gk, 'group_title' => $r['group_title'], 'balance' => 0.0, 'prev_balance' => 0.0, 'accounts' => []];
            $g['accounts'][]       = ['account_code' => $r['account_code'], 'account_title' => $r['account_title'], 'balance' => $bal, 'prev_balance' => $pbal];
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

        foreach ($sections as &$s) {
            ksort($s['groups']);
            foreach ($s['groups'] as &$g) {
                $g['balance']      = round($g['balance'], 2);
                $g['prev_balance'] = round($g['prev_balance'], 2);
            }
            unset($g);
            $s['groups']        = array_values($s['groups']);
            $s['subtotal']      = round($s['subtotal'], 2);
            $s['prev_subtotal'] = round($s['prev_subtotal'], 2);
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
}
