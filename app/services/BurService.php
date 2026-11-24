<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * Budget Utilization Report (BUR) — read-only, backs app/pages/bur-page.php.
 *
 * Scope: the Current Operating Expenditures half of the budget — Personal Services (chart-of-accounts
 * codes starting '5-01') and Maintenance and Other Operating Expenses ('5-02') — the same PS / MOOE
 * classification CobService::expenseClassSql() uses for the COB's COE bucket. Unlike COB, a line here
 * is counted whether or not it has a Program / Project: PS and MOOE lines are COE REGARDLESS, so this
 * report is a straight chart-of-accounts rollup of every APPROVED budget_req's line items under those
 * two codes, not a program tree.
 *
 * Only accounts that actually carry approved budget show up: fetchRollup() joins fims.budget_line_items
 * to fims.chart_of_accounts (and to fims.budget_req for the 'Approved' status / fiscal year / RC scope),
 * so a chart-of-accounts code with zero approved line items is never in the result — the API filters
 * it out, the page does not. A group/parent code (e.g. "5-01-01 Salaries and Wages") is still included,
 * with the sum of its descendants, whenever at least one of those descendants carries budget — see
 * ancestorsOf(). The two roots, "5-01 Personal Services" and "5-02 Maintenance and Other Operating
 * Expenses", come back with no parent_account_id of their own (their real parent, "5-00-000 Expenses",
 * is never fetched), so the page's tree builder treats them as top-level rows, same trick
 * app/pages/bur-page.php used client-side before this service existed.
 *
 * The seven columns, per BudgetLineBalanceSql::exprs() (li/ear) and the same disbursement join
 * CobService::disbursementSql() uses (kept here, not shared, same as CobService's own copy is not
 * shared with anyone else):
 *   approved_amount    suggested_amount — what FMD suggested in Budget Processing, before any revision
 *   revision_amount    the net of all approved Budget Revisions / Realignments (signed)   -> Increase (Decrease)
 *   revised_amount     approved_amount + revision_amount                                   -> Realigned Budget
 *   utilization        what approved RFAs / LAAs have drawn (rfa_approved + laa_issued)
 *   payment            what the disbursement vouchers connected to those RFAs / LAAs have actually disbursed
 *   account_payable    utilization - payment
 *   balance            revised_amount - reserved_active (still held by an active CBA/CFA) - utilization
 */
class BurService
{
    public const AMOUNT_KEYS = ['approved_amount', 'revision_amount', 'revised_amount', 'utilization', 'payment', 'account_payable', 'balance'];

    private string $tableLineItems = 'fims.budget_line_items';
    private string $tableBudgetReq = 'fims.budget_req';
    private string $tableCoa = 'fims.chart_of_accounts';
    private string $tableEarmark = 'fims.budget_line_item_earmark';
    private string $tableAllocation = 'fims.budget_allocation';

    /** Same shape as CobService::disbursementSql() (kept separate on purpose — see the class doc). */
    private function disbursementSql(): string
    {
        return "SELECT e.budget_line_item_id,
                       SUM(dv.gross_amount * e.amount / NULLIF(tot.total, 0)) AS dv_gross
                FROM {$this->tableEarmark} e
                JOIN {$this->tableAllocation} a ON a.allocation_id = e.allocation_id AND a.deleted_at IS NULL
                                               AND UPPER(a.allocation_type) IN ('RFA', 'LAA')
                JOIN fims.disbursement_vouchers dv ON dv.dv_id = a.dv_id
                JOIN (SELECT a2.dv_id, SUM(e2.amount) AS total
                        FROM {$this->tableEarmark} e2
                        JOIN {$this->tableAllocation} a2 ON a2.allocation_id = e2.allocation_id AND a2.deleted_at IS NULL
                                                        AND UPPER(a2.allocation_type) IN ('RFA', 'LAA')
                       WHERE e2.status = 'converted' AND a2.dv_id IS NOT NULL
                       GROUP BY a2.dv_id) tot ON tot.dv_id = a.dv_id
                WHERE e.status = 'converted'
                  AND UPPER(COALESCE((SELECT s.name FROM fims.settings_status s WHERE s.id = dv.status_id), '')) NOT IN ('CANCELLED', 'REJECTED')
                GROUP BY e.budget_line_item_id";
    }

    /** The seven amount expressions, aliased to AMOUNT_KEYS, built on BudgetLineBalanceSql::exprs() (li/ear) — the app's single balance formula. */
    private function lineAmountExprs(): array
    {
        $b = BudgetLineBalanceSql::exprs('li', 'ear');
        $utilization = "({$b['rfa_approved']} + {$b['laa_issued']})";
        $payment = 'COALESCE(dvl.dv_gross, 0)';

        return [
            'approved_amount' => $b['approved_amount'],
            'revision_amount' => $b['revision_amount'],
            'revised_amount' => $b['revised_amount'],
            'utilization' => $utilization,
            'payment' => $payment,
            'account_payable' => "({$utilization} - {$payment})",
            // what is left once what an active CBA/CFA still holds, and what RFAs/LAAs have drawn, are both taken off
            'balance' => "({$b['revised_amount']} - {$b['reserved_amount']} - {$utilization})",
        ];
    }

    private function sumsSql(): string
    {
        $parts = [];
        foreach ($this->lineAmountExprs() as $alias => $expr) {
            $parts[] = "COALESCE(SUM({$expr}), 0) AS {$alias}";
        }

        return implode(",\n                ", $parts);
    }

    private function str(string $v): string
    {
        return App\QuotedValue($v, DataType::STRING, 'DB');
    }

    /** "br.rc_id IN (...)" for an RC and everything below it, or null when $rcId is empty (no restriction) — same rule as CobService::rcInSql(). */
    private function rcInSql(string $rcId): ?string
    {
        if (trim($rcId) === '') {
            return null;
        }
        $ids = GlobalService::getRcIdsInScope($rcId) ?: [$rcId];

        return 'br.rc_id IN ('.implode(', ', array_map(fn ($id) => $this->str((string) $id), $ids)).')';
    }

    /** @return string[] */
    private function conditions(?int $fiscalYr, string $scopeRcId): array
    {
        $where = ["st.status_for = 'budget_req'", "LOWER(st.name) = 'approved'", "(coa.account_code LIKE '5-01%' OR coa.account_code LIKE '5-02%')"];
        if (($scope = $this->rcInSql($scopeRcId)) !== null) {
            $where[] = $scope;
        }
        if ($fiscalYr !== null) {
            $where[] = 'br.fiscal_yr = '.$fiscalYr;
        }

        return $where;
    }

    private function fromSql(): string
    {
        return "FROM {$this->tableLineItems} li
                JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                JOIN fims.settings_status st ON st.id = br.status_id
                JOIN {$this->tableCoa} coa ON coa.account_id = li.account_id
                LEFT JOIN (".BudgetLineBalanceSql::earmarkAggregateSql($this->tableEarmark, $this->tableAllocation).') ear ON ear.budget_line_item_id = li.budget_line_item_id
                LEFT JOIN ('.$this->disbursementSql().') dvl ON dvl.budget_line_item_id = li.budget_line_item_id';
    }

    private function zeroAmounts(): array
    {
        return array_fill_keys(self::AMOUNT_KEYS, 0.0);
    }

    private function addTo(array $into, array $from): array
    {
        foreach (self::AMOUNT_KEYS as $k) {
            $into[$k] = round($into[$k] + (float) ($from[$k] ?? 0), 2);
        }

        return $into;
    }

    /**
     * Every chart-of-accounts code that carries approved PS / MOOE budget (the query's leaves) PLUS every one of
     * their ancestors up to (and including) '5-01' / '5-02' themselves — their own parent, "5-00-000 Expenses",
     * is deliberately left out so the page's tree builder treats '5-01' / '5-02' as top-level rows.
     *
     * @param int[] $leafIds
     * @return array<string, array{account_id:string, account_code:string, account_title:string, parent_account_id:?string}>
     */
    private function ancestorsOf(array $leafIds): array
    {
        if (!$leafIds) {
            return [];
        }
        $ids = implode(', ', array_map(fn ($id) => $this->str((string) $id), $leafIds));
        $rows = App\ExecuteRows(
            "WITH RECURSIVE anc AS (
                 SELECT account_id, account_code, account_title, parent_account_id
                 FROM {$this->tableCoa}
                 WHERE account_id IN ({$ids})
                 UNION ALL
                 SELECT p.account_id, p.account_code, p.account_title, p.parent_account_id
                 FROM anc
                 JOIN {$this->tableCoa} p ON p.account_id = anc.parent_account_id
             )
             SELECT DISTINCT account_id, account_code, account_title, parent_account_id
             FROM anc
             WHERE account_code LIKE '5-01%' OR account_code LIKE '5-02%'",
            'DB'
        ) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['account_id']] = $r;
        }

        return $out;
    }

    /**
     * The fiscal years that have at least one approved PS / MOOE budget line, within the caller's RC scope — the
     * Fiscal Year filter's options; newest first, so the page can default to the first one.
     */
    public function filterOptions(string $scopeRcId = ''): array
    {
        try {
            $where = implode(' AND ', $this->conditions(null, $scopeRcId));
            $rows = App\ExecuteRows(
                "SELECT DISTINCT br.fiscal_yr {$this->fromSql()} WHERE {$where} ORDER BY br.fiscal_yr DESC",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => ['fiscal_years' => array_map(fn ($r) => (int) $r['fiscal_yr'], $rows)]];
        } catch (\Throwable $e) {
            App\LogError('BurService::filterOptions '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the BUR filters.'];
        }
    }

    /**
     * The report: one row per chart-of-accounts code that carries approved PS / MOOE budget (directly or through a
     * descendant), each with the seven amount columns (see the class doc) and the same account_id / account_code /
     * account_title / parent_account_id shape chart-of-accounts-page.php's tree already uses. Also the grand totals
     * (the two roots' figures added together — the KPI cards).
     */
    public function fetchRollup(?int $fiscalYr, string $scopeRcId = ''): array
    {
        try {
            $leafRows = App\ExecuteRows(
                'SELECT coa.account_id, coa.account_code, coa.account_title, coa.parent_account_id,
                        '.$this->sumsSql().'
                 '.$this->fromSql().'
                 WHERE '.implode(' AND ', $this->conditions($fiscalYr, $scopeRcId)).'
                 GROUP BY coa.account_id, coa.account_code, coa.account_title, coa.parent_account_id',
                'DB'
            ) ?: [];

            $accounts = $this->ancestorsOf(array_map(fn ($r) => (string) $r['account_id'], $leafRows));
            $sums = array_fill_keys(array_keys($accounts), null);
            foreach ($sums as $id => &$v) {
                $v = $this->zeroAmounts();
            }
            unset($v);

            foreach ($leafRows as $leaf) {
                $id = (string) $leaf['account_id'];
                if (!isset($accounts[$id])) {
                    continue; // a code outside 5-01% / 5-02% cannot happen (the WHERE already restricts it), kept defensive only
                }
                // add the leaf's own figures to itself and to every ancestor on its chain (ancestorsOf() already fetched the whole chain)
                $cursor = $id;
                while ($cursor !== null && isset($accounts[$cursor])) {
                    $sums[$cursor] = $this->addTo($sums[$cursor], $leaf);
                    $parent = $accounts[$cursor]['parent_account_id'];
                    $cursor = $parent !== null ? (string) $parent : null;
                }
            }

            $data = [];
            foreach ($accounts as $id => $acc) {
                $data[] = [
                    'account_id' => $acc['account_id'],
                    'account_code' => $acc['account_code'],
                    'account_title' => $acc['account_title'],
                    'parent_account_id' => isset($accounts[(string) $acc['parent_account_id']]) ? $acc['parent_account_id'] : null,
                ] + $sums[$id];
            }

            $totals = $this->zeroAmounts();
            foreach ($data as $row) {
                if ($row['parent_account_id'] === null) { // the two roots, 5-01 and 5-02 — their figures are already the sum of everything under them
                    $totals = $this->addTo($totals, $row);
                }
            }

            return ['success' => true, 'data' => $data, 'totals' => $totals];
        } catch (\Throwable $e) {
            App\LogError('BurService::fetchRollup '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the Budget Utilization Report.'];
        }
    }
}
