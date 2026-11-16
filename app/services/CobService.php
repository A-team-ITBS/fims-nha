<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * Corporate Operating Budget (COB) — the monitoring view of the budget that has been APPROVED.
 *
 * It is read-only and is built from the Budget Request tables, nothing else:
 *
 *   fims.budget_req                      header: fiscal year, requesting RC, status
 *     └ fims.budget_req_program          a Program (+ optional Sub-Program) of the request
 *         └ fims.budget_req_project      a Project of that Program
 *             ├ fims.budget_line_items   the chart-of-accounts lines — where every amount lives
 *             └ fims.budget_req_project_attachment   supporting documents of the project
 *
 * ONLY a request whose status is 'Approved' (fims.settings_status, status_for = 'budget_req') is part of the COB — every
 * method here goes through conditions(), which always starts from that, so a Draft / For Review / Endorsed / Returned
 * request never shows up in a table, a total or a filter option. (The same rule BudgetLineItemService::fetchApproved()
 * applies to the Memo Request form's COB list.)
 *
 * The figures, per budget line item (everything else is a SUM of these):
 *   fund_balance           = budget_line_items.suggested_amount   what FMD approved and is still free: already reduced
 *                                                                 by every approved CBA, already moved by every approved
 *                                                                 Budget Revision — the line's live balance
 *   adjustment             = budget_line_items.adjustment         net of the approved Budget Revisions (a reference
 *                                                                 figure: fund_balance already includes it)
 *   carry_over_obligation  = budget_line_items.account_payable    obligated, not yet disbursed — the same convention the
 *                                                                 old COB used for its Carry Over Obligation
 *   obligation / disbursement / proposed_amount                   as stored
 *   total                  = fund_balance + carry_over_obligation (adjustment is NOT added: it is inside fund_balance)
 *
 * Views:
 *   summary()    the KPI cards
 *   rollup()     a tree, by 'fund_source' (Type of Fund > Component > Program > Project > Account),
 *                'component' (Component > Type of Fund > ...) or 'region' (Region > Office > ...)
 *   fetchAlls()  the flat COB records — one row per budget line item, paginated
 *   fetchById()  one approved request in full: its programs, projects, line items and attachments
 *   filterOptions() what the filter drop-downs offer (only values that exist in approved data)
 *
 * Every method takes $scopeRcId — the RC the caller is limited to ('' = every RC), as resolved by
 * GlobalService::resolveBudgetReqRcFilter() — and shows that RC plus its descendants, like the Budget Request list.
 */
class CobService
{
    /** The amount columns every row, group and total carries (see the header comment for what each is). */
    public const AMOUNT_KEYS = ['proposed_amount', 'adjustment', 'fund_balance', 'carry_over_obligation', 'obligation', 'disbursement'];

    public const LAYOUTS = ['fund_source', 'component', 'region'];

    private string $tableBudgetReq = 'fims.budget_req';
    private string $tableLineItems = 'fims.budget_line_items';
    private string $tableAttachment = 'fims.budget_req_project_attachment';

    // -------------------------------------------------------------------------
    // Shared SQL
    // -------------------------------------------------------------------------

    /**
     * The joins every COB query shares. One row = one budget line item of a request that has a status; the project /
     * program joins are LEFT, so a line item of an older request that never got its program block still counts (it just
     * has no program or project). Every join is many-to-one, so no join can multiply a line item's amounts.
     */
    private function fromSql(): string
    {
        return "FROM {$this->tableLineItems} li
                JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                JOIN fims.settings_status st ON st.id = br.status_id
                LEFT JOIN fims.budget_req_project brpj ON brpj.budget_req_project_id = li.budget_req_project_id
                LEFT JOIN fims.budget_req_program brpg ON brpg.budget_req_program_id = brpj.budget_req_program_id
                LEFT JOIN fims.program pg ON pg.program_code = brpg.program_code
                LEFT JOIN fims.sub_program sp ON sp.sub_program_code = brpg.sub_program_code
                LEFT JOIN fims.project pj ON pj.project_code = brpj.project_code
                LEFT JOIN fims.type_of_fund tof ON tof.type_of_fund_id = li.type_of_fund_id
                LEFT JOIN fims.component_type ct ON ct.component_type_id = li.component_id
                LEFT JOIN fims.chart_of_accounts coa ON coa.account_id = li.account_id
                LEFT JOIN fims.responsibility_code rc ON rc.rc_id = br.rc_id
                LEFT JOIN fims.responsibility_code prc ON prc.rc_id = rc.parent_rc_id
                LEFT JOIN (
                    SELECT budget_req_program_id, project_code, COUNT(*) AS attachment_count
                    FROM {$this->tableAttachment}
                    GROUP BY budget_req_program_id, project_code
                ) att ON att.budget_req_program_id = brpg.budget_req_program_id AND att.project_code = brpj.project_code";
    }

    /** The SUMs of the amount columns, aliased to AMOUNT_KEYS, plus how many line items they cover. */
    private function sumsSql(): string
    {
        return 'COUNT(*) AS line_count,
                COALESCE(SUM(li.proposed_amount), 0)  AS proposed_amount,
                COALESCE(SUM(li.adjustment), 0)       AS adjustment,
                COALESCE(SUM(li.suggested_amount), 0) AS fund_balance,
                COALESCE(SUM(li.account_payable), 0)  AS carry_over_obligation,
                COALESCE(SUM(li.obligation), 0)       AS obligation,
                COALESCE(SUM(li.disbursement), 0)     AS disbursement';
    }

    /**
     * The filters the COB understands, from a raw query string. Anything missing or malformed is dropped, so a hand-typed URL
     * can only ever narrow the list, never break the query.
     *
     * @return array{fiscal_yr: ?int, type_of_fund_id: ?int, component_id: ?int, program_code: string, project_code: string,
     *               account_id: string, region_id: string, search: string}
     */
    public function normalizeFilters(array $p): array
    {
        $int = static function ($v): ?int {
            return is_scalar($v) && $v !== '' && ($i = filter_var($v, FILTER_VALIDATE_INT)) !== false ? (int) $i : null;
        };
        $str = static fn ($v): string => is_scalar($v) ? trim((string) $v) : '';

        return [
            'fiscal_yr' => $int($p['fiscal_yr'] ?? null),
            'type_of_fund_id' => $int($p['type_of_fund_id'] ?? null),
            'component_id' => $int($p['component_id'] ?? null),
            'program_code' => $str($p['program_code'] ?? null),
            'project_code' => $str($p['project_code'] ?? null),
            'account_id' => $str($p['account_id'] ?? null),
            'region_id' => $str($p['region_id'] ?? null),
            'search' => $str($p['search'] ?? null),
        ];
    }

    private function str(string $v): string
    {
        return App\QuotedValue($v, DataType::STRING, 'DB');
    }

    /** "rc_id IN (...)" for an RC and everything below it, or null when $rcId is empty (no restriction). */
    private function rcInSql(string $rcId): ?string
    {
        if (trim($rcId) === '') {
            return null;
        }
        $ids = GlobalService::getRcIdsInScope($rcId) ?: [$rcId];

        return 'br.rc_id IN ('.implode(', ', array_map(fn ($id) => $this->str((string) $id), $ids)).')';
    }

    /**
     * WHERE conditions: 'Approved' first, always — then the caller's RC scope, then whichever filters are set.
     *
     * @return string[]
     */
    private function conditions(array $f, string $scopeRcId): array
    {
        $where = ["st.status_for = 'budget_req'", "LOWER(st.name) = 'approved'"];

        if (($scope = $this->rcInSql($scopeRcId)) !== null) {
            $where[] = $scope;
        }
        if ($f['fiscal_yr'] !== null) {
            $where[] = 'br.fiscal_yr = '.$f['fiscal_yr'];
        }
        if ($f['type_of_fund_id'] !== null) {
            $where[] = 'li.type_of_fund_id = '.$f['type_of_fund_id'];
        }
        if ($f['component_id'] !== null) {
            $where[] = 'li.component_id = '.$f['component_id'];
        }
        if ($f['program_code'] !== '') {
            $where[] = 'brpg.program_code = '.$this->str($f['program_code']);
        }
        if ($f['project_code'] !== '') {
            $where[] = 'brpj.project_code = '.$this->str($f['project_code']);
        }
        if ($f['account_id'] !== '') {
            $where[] = 'li.account_id = '.$this->str($f['account_id']);
        }
        if ($f['region_id'] !== '') {
            // 'central' = every office that is not a Regional / District one; otherwise the region's RC and its districts.
            $where[] = $f['region_id'] === 'central'
                ? "LOWER(COALESCE(rc.office_type, '')) NOT IN ('regional', 'district')"
                : $this->rcInSql($f['region_id']);
        }
        if ($f['search'] !== '') {
            $s = $this->str('%'.$f['search'].'%');
            $where[] = "(br.budget_reference_number ILIKE {$s} OR pg.program_name ILIKE {$s} OR sp.sub_program_name ILIKE {$s}
                         OR pj.project_name ILIKE {$s} OR brpj.project_code ILIKE {$s} OR coa.account_code ILIKE {$s}
                         OR coa.account_title ILIKE {$s} OR li.particulars ILIKE {$s} OR rc.rc_name ILIKE {$s}
                         OR tof.name ILIKE {$s} OR ct.name ILIKE {$s})";
        }

        return $where;
    }

    private function num(mixed $v): float
    {
        return round((float) $v, 2);
    }

    /** @return array<string, float> the six amount columns of a DB row, as floats */
    private function amountsOf(array $row): array
    {
        $out = [];
        foreach (self::AMOUNT_KEYS as $k) {
            $out[$k] = $this->num($row[$k] ?? 0);
        }

        return $out;
    }

    /** total = fund_balance + carry_over_obligation. adjustment is not added: fund_balance (suggested_amount) already holds it. */
    private function totalOf(array $amounts): float
    {
        return round(($amounts['fund_balance'] ?? 0) + ($amounts['carry_over_obligation'] ?? 0), 2);
    }

    // -------------------------------------------------------------------------
    // Summary (KPI cards)
    // -------------------------------------------------------------------------

    public function summary(array $filters, string $scopeRcId = ''): array
    {
        try {
            $f = $this->normalizeFilters($filters);
            $row = App\ExecuteRow(
                'SELECT COUNT(DISTINCT br.budget_reference_number) AS requests,
                        COUNT(DISTINCT brpg.budget_req_program_id) AS programs,
                        COUNT(DISTINCT brpj.budget_req_project_id) AS projects,
                        '.$this->sumsSql().'
                 '.$this->fromSql().'
                 WHERE '.implode(' AND ', $this->conditions($f, $scopeRcId)),
                'DB'
            ) ?: [];

            $amounts = $this->amountsOf($row);

            return ['success' => true, 'data' => [
                'requests' => (int) ($row['requests'] ?? 0),
                'programs' => (int) ($row['programs'] ?? 0),
                'projects' => (int) ($row['projects'] ?? 0),
                'line_items' => (int) ($row['line_count'] ?? 0),
            ] + $amounts + ['total' => $this->totalOf($amounts)]];
        } catch (\Throwable $e) {
            App\LogError('CobService::summary '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the COB summary.'];
        }
    }

    // -------------------------------------------------------------------------
    // Roll-ups — the tree tabs
    // -------------------------------------------------------------------------

    /**
     * The tree for one tab. Every node is the same shape — { type, key, label, code, sub, ...amounts, total, line_count,
     * children[] } — whatever its level, so one renderer draws all three tabs. A node's amounts are the sums of everything
     * below it, so every level foots to the one above. Nodes whose Type of Fund / Component / Program / ... was never set
     * are kept (labelled 'Unassigned' / 'No program' ...) and sorted last: nothing approved is ever left out.
     *
     * @return array{success: bool, layout_by?: string, data?: array, totals?: array, message?: string}
     */
    public function rollup(string $layoutBy, array $filters, string $scopeRcId = ''): array
    {
        if (!in_array($layoutBy, self::LAYOUTS, true)) {
            return ['success' => false, 'message' => 'Unknown layout_by: '.$layoutBy];
        }

        try {
            $f = $this->normalizeFilters($filters);

            // One flat row per (fund, component, office, program block, project, account) — the leaf of every tree. The project
            // is kept apart per request (a project code is generated per RC and year, so this is effectively one per project).
            $rows = App\ExecuteRows(
                'SELECT tof.type_of_fund_id, tof.name AS type_of_fund_name,
                        ct.component_type_id, ct.name AS component_name,
                        br.budget_reference_number, br.fiscal_yr,
                        br.rc_id, rc.rc_code, rc.rc_name, rc.office_type AS rc_office_type,
                        rc.parent_rc_id, prc.rc_code AS parent_rc_code, prc.rc_name AS parent_rc_name,
                        brpg.budget_req_program_id, brpg.program_code, pg.program_name,
                        brpg.sub_program_code, sp.sub_program_name,
                        brpj.project_code, pj.project_name,
                        li.account_id, coa.account_code, coa.account_title,
                        COALESCE(att.attachment_count, 0) AS attachment_count,
                        '.$this->sumsSql().'
                 '.$this->fromSql().'
                 WHERE '.implode(' AND ', $this->conditions($f, $scopeRcId)).'
                 GROUP BY tof.type_of_fund_id, tof.name, ct.component_type_id, ct.name,
                          br.budget_reference_number, br.fiscal_yr,
                          br.rc_id, rc.rc_code, rc.rc_name, rc.office_type, rc.parent_rc_id, prc.rc_code, prc.rc_name,
                          brpg.budget_req_program_id, brpg.program_code, pg.program_name, brpg.sub_program_code, sp.sub_program_name,
                          brpj.project_code, pj.project_name, li.account_id, coa.account_code, coa.account_title, att.attachment_count',
                'DB'
            ) ?: [];

            $tree = $this->buildTree($rows, $this->levelsFor($layoutBy));
            $totals = $this->zeroAmounts();
            foreach ($tree as $node) {
                $totals = $this->addTo($totals, $node);
            }
            $totals['total'] = $this->totalOf($totals);

            return ['success' => true, 'layout_by' => $layoutBy, 'data' => $tree, 'totals' => $totals];
        } catch (\Throwable $e) {
            App\LogError('CobService::rollup '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the COB roll-up.'];
        }
    }

    private function zeroAmounts(): array
    {
        return array_fill_keys(self::AMOUNT_KEYS, 0.0) + ['line_count' => 0];
    }

    private function addTo(array $into, array $from): array
    {
        foreach (self::AMOUNT_KEYS as $k) {
            $into[$k] = round($into[$k] + (float) ($from[$k] ?? 0), 2);
        }
        $into['line_count'] += (int) ($from['line_count'] ?? 0);

        return $into;
    }

    /**
     * The levels of a tree, top to bottom: each is ['type' => ..., 'of' => fn(row) => [key, attributes]].
     * Program, Project and Account close every tree; the first two levels are what the tab is about.
     */
    private function levelsFor(string $layoutBy): array
    {
        $fund = ['type' => 'fund', 'of' => static fn (array $r): array => [
            $r['type_of_fund_id'] ?? 'none',
            ['label' => $r['type_of_fund_name'] ?? 'Unassigned', 'code' => null, 'unassigned' => empty($r['type_of_fund_id'])],
        ]];
        $component = ['type' => 'component', 'of' => static fn (array $r): array => [
            $r['component_type_id'] ?? 'none',
            ['label' => $r['component_name'] ?? 'Unassigned', 'code' => null, 'unassigned' => empty($r['component_type_id'])],
        ]];
        $region = ['type' => 'region', 'of' => function (array $r): array {
            [$id, $name, $code] = $this->regionOf($r);

            return [$id, ['label' => $name, 'code' => $code, 'unassigned' => false]];
        }];
        $office = ['type' => 'office', 'of' => static fn (array $r): array => [
            $r['rc_id'] ?? 'none',
            ['label' => $r['rc_name'] ?? 'No office', 'code' => $r['rc_code'] ?? null, 'unassigned' => empty($r['rc_id'])],
        ]];
        $program = ['type' => 'program', 'of' => static fn (array $r): array => [
            ($r['program_code'] ?? '').'|'.($r['sub_program_code'] ?? ''),
            [
                'label' => $r['program_name'] ?? ($r['program_code'] ?? 'No program'),
                'code' => $r['program_code'] ?? null,
                'sub' => $r['sub_program_name'] ?? null,
                'unassigned' => empty($r['program_code']),
            ],
        ]];
        $project = ['type' => 'project', 'of' => static fn (array $r): array => [
            ($r['budget_reference_number'] ?? '').'|'.($r['budget_req_program_id'] ?? '').'|'.($r['project_code'] ?? ''),
            [
                'label' => $r['project_name'] ?? ($r['project_code'] ?? 'No project'),
                'code' => $r['project_code'] ?? null,
                'unassigned' => empty($r['project_code']),
                'budget_reference_number' => $r['budget_reference_number'] ?? null,
                'fiscal_yr' => isset($r['fiscal_yr']) ? (int) $r['fiscal_yr'] : null,
                'rc_name' => $r['rc_name'] ?? null,
                'attachment_count' => (int) ($r['attachment_count'] ?? 0),
            ],
        ]];
        $account = ['type' => 'account', 'of' => static fn (array $r): array => [
            $r['account_id'] ?? 'none',
            ['label' => $r['account_title'] ?? 'No account', 'code' => $r['account_code'] ?? null, 'unassigned' => empty($r['account_id'])],
        ]];

        return match ($layoutBy) {
            'fund_source' => [$fund, $component, $program, $project, $account],
            'component' => [$component, $fund, $program, $project, $account],
            default => [$region, $office, $program, $project, $account],
        };
    }

    /**
     * Which Region an office belongs to: a District office -> its Regional office (the RC above it); a Regional office -> itself;
     * every other RC (the central departments / groups) -> 'Central Office'.
     *
     * @return array{0: string, 1: string, 2: ?string} [key, name, rc_code]
     */
    private function regionOf(array $r): array
    {
        $type = strtolower(trim((string) ($r['rc_office_type'] ?? '')));
        if ($type === 'district' && !empty($r['parent_rc_id'])) {
            return [(string) $r['parent_rc_id'], (string) ($r['parent_rc_name'] ?? $r['parent_rc_id']), $r['parent_rc_code'] ?? null];
        }
        if ($type === 'regional' && !empty($r['rc_id'])) {
            return [(string) $r['rc_id'], (string) ($r['rc_name'] ?? $r['rc_id']), $r['rc_code'] ?? null];
        }

        return ['central', 'Central Office', null];
    }

    /** Folds the flat leaf rows into the nested tree the levels describe, then totals and sorts it. */
    private function buildTree(array $rows, array $levels): array
    {
        $root = [];
        foreach ($rows as $row) {
            $this->insertRow($root, $levels, 0, $row);
        }

        return $this->finishNodes($root);
    }

    private function insertRow(array &$siblings, array $levels, int $depth, array $row): void
    {
        $def = $levels[$depth];
        [$key, $attrs] = $def['of']($row);
        $key = (string) $key;

        if (!isset($siblings[$key])) {
            $siblings[$key] = ['type' => $def['type'], 'key' => $key] + $attrs + $this->zeroAmounts() + ['children' => []];
        }
        $siblings[$key] = $this->addTo($siblings[$key], $row);

        if ($depth + 1 < count($levels)) {
            $this->insertRow($siblings[$key]['children'], $levels, $depth + 1, $row);
        }
    }

    /** Children become lists, every node gets its total, and every level is put in its reporting order. */
    private function finishNodes(array $siblings): array
    {
        $nodes = [];
        foreach ($siblings as $node) {
            $node['children'] = $this->finishNodes($node['children']);
            $node['total'] = $this->totalOf($node);
            $nodes[] = $node;
        }
        usort($nodes, [$this, 'compareNodes']);

        return $nodes;
    }

    /**
     * Reporting order. An 'Unassigned' node is always last. Types of Fund keep the COB's fixed order — NG Support first, any
     * other fund next, then Trust Fund, then Corporate Receipts. Components follow their id (Capital Outlay, Other-Capital
     * Outlay, COE ...), offices and regions their RC code (Central Office first), and everything else reads alphabetically.
     */
    private function compareNodes(array $a, array $b): int
    {
        if (($a['unassigned'] ?? false) !== ($b['unassigned'] ?? false)) {
            return ($a['unassigned'] ?? false) ? 1 : -1;
        }

        switch ($a['type']) {
            case 'fund':
                $byRank = $this->fundRank($a['label']) <=> $this->fundRank($b['label']);
                if ($byRank !== 0) {
                    return $byRank;
                }
                break;
            case 'component':
                $byRank = $this->componentRank($a['label']) <=> $this->componentRank($b['label']);
                if ($byRank !== 0) {
                    return $byRank;
                }
                $byId = ((int) $a['key']) <=> ((int) $b['key']);
                if ($byId !== 0) {
                    return $byId;
                }
                break;
            case 'region':
            case 'office':
                $byCode = strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? ''));
                if ($byCode !== 0) {
                    return $byCode;
                }
                break;
            case 'account':
                $byCode = strnatcasecmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? ''));
                if ($byCode !== 0) {
                    return $byCode;
                }
                break;
        }

        return strnatcasecmp((string) $a['label'], (string) $b['label']) ?: strnatcasecmp((string) $a['key'], (string) $b['key']);
    }

    /**
     * 0 Capital Outlay, 1 Other-Capital Outlay, 2 COE, 3 any other component (then by id). By name, not id: the ids of the seeded
     * lookup rows follow insertion order, which a fresh install does not guarantee.
     */
    private function componentRank(string $name): int
    {
        $name = trim($name);
        if (preg_match('/^capital[\s_-]*outlay/i', $name)) {
            return 0;
        }
        if (preg_match('/^other[\s_-]*capital/i', $name)) {
            return 1;
        }
        if (preg_match('/^coe\b/i', $name)) {
            return 2;
        }

        return 3;
    }

    /** 0 NG Support (GAA / NF / UF ...), 1 any other fund, 2 Trust Fund, 3 Corporate Receipt(s). */
    private function fundRank(string $name): int
    {
        $name = trim($name);
        if (preg_match('/^ng[\s_-]*support/i', $name)) {
            return 0;
        }
        if (preg_match('/trust[\s_-]*fund/i', $name)) {
            return 2;
        }
        if (preg_match('/corporate.*receipt/i', $name)) {
            return 3;
        }

        return 1;
    }

    // -------------------------------------------------------------------------
    // Flat records
    // -------------------------------------------------------------------------

    /**
     * The COB records: one row per approved budget line item, newest fiscal year first. Paginated when $page is given.
     * `totals` covers every row matching the filters (not just the page).
     */
    public function fetchAlls(?int $page = null, int $limit = 15, array $filters = [], string $scopeRcId = ''): array
    {
        try {
            $f = $this->normalizeFilters($filters);
            $whereSql = implode(' AND ', $this->conditions($f, $scopeRcId));
            $from = $this->fromSql();

            $limit = max(1, min(500, $limit));
            $paging = '';
            if ($page !== null) {
                $page = max(1, $page);
                $paging = ' LIMIT '.$limit.' OFFSET '.(($page - 1) * $limit);
            }

            $rows = App\ExecuteRows(
                "SELECT li.budget_line_item_id, br.budget_reference_number, br.fiscal_yr, br.submitted_at,
                        br.rc_id, rc.rc_code, rc.rc_name, rc.office_type AS rc_office_type,
                        brpg.budget_req_program_id, brpg.program_code, pg.program_name, brpg.sub_program_code, sp.sub_program_name,
                        brpj.project_code, pj.project_name,
                        li.type_of_fund_id, tof.name AS type_of_fund_name, li.component_id, ct.name AS component_name,
                        li.account_id, coa.account_code, coa.account_title, li.particulars,
                        COALESCE(att.attachment_count, 0) AS attachment_count,
                        li.proposed_amount, li.adjustment, li.suggested_amount AS fund_balance,
                        li.account_payable AS carry_over_obligation, li.obligation, li.disbursement
                 {$from}
                 WHERE {$whereSql}
                 ORDER BY br.fiscal_yr DESC, rc.rc_code ASC NULLS LAST, brpg.program_code ASC NULLS LAST,
                          brpj.project_code ASC NULLS LAST, coa.account_code ASC NULLS LAST, li.budget_line_item_id ASC{$paging}",
                'DB'
            ) ?: [];

            $data = array_map(function (array $r): array {
                $amounts = $this->amountsOf($r);
                $r['attachment_count'] = (int) $r['attachment_count'];
                $r['fiscal_yr'] = $r['fiscal_yr'] !== null ? (int) $r['fiscal_yr'] : null;

                return array_merge($r, $amounts, ['total' => $this->totalOf($amounts)]);
            }, $rows);

            $sums = App\ExecuteRow("SELECT {$this->sumsSql()} {$from} WHERE {$whereSql}", 'DB') ?: [];
            $totals = $this->amountsOf($sums);
            $totals['total'] = $this->totalOf($totals);
            $count = (int) ($sums['line_count'] ?? 0);

            $result = ['success' => true, 'data' => $data, 'totals' => $totals];
            if ($page !== null) {
                $result['pagination'] = [
                    'total' => $count,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($count / $limit),
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            App\LogError('CobService::fetchAlls '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the COB records.'];
        }
    }

    // -------------------------------------------------------------------------
    // One approved request
    // -------------------------------------------------------------------------

    /**
     * One APPROVED budget request in full — its programs, each program's projects, each project's line items and attachments.
     * Not found when it does not exist, is not Approved, or belongs to an RC outside the caller's scope (the three look the same).
     * Line items of an older request with no program block come back in `unassigned_line_items`.
     */
    public function fetchById(string $budgetReferenceNumber, string $scopeRcId = ''): array
    {
        $notFound = ['success' => false, 'httpCode' => 404, 'message' => "Approved budget request {$budgetReferenceNumber} not found."];

        try {
            $brn = $this->str($budgetReferenceNumber);
            $scope = $this->rcInSql($scopeRcId);

            $header = App\ExecuteRow(
                "SELECT br.budget_reference_number, br.fiscal_yr, br.submitted_at, br.rc_id,
                        st.name AS status_name, st.color AS status_color,
                        rc.rc_code, rc.rc_name, rc.office_type AS rc_office_type, prc.rc_name AS parent_rc_name
                 FROM {$this->tableBudgetReq} br
                 JOIN fims.settings_status st ON st.id = br.status_id
                 LEFT JOIN fims.responsibility_code rc ON rc.rc_id = br.rc_id
                 LEFT JOIN fims.responsibility_code prc ON prc.rc_id = rc.parent_rc_id
                 WHERE br.budget_reference_number = {$brn}
                   AND st.status_for = 'budget_req' AND LOWER(st.name) = 'approved'".($scope !== null ? " AND {$scope}" : ''),
                'DB'
            );
            if (!$header) {
                return $notFound;
            }

            $programRows = App\ExecuteRows(
                "SELECT brpg.budget_req_program_id, brpg.program_code, pg.program_name, brpg.sub_program_code, sp.sub_program_name
                 FROM fims.budget_req_program brpg
                 LEFT JOIN fims.program pg ON pg.program_code = brpg.program_code
                 LEFT JOIN fims.sub_program sp ON sp.sub_program_code = brpg.sub_program_code
                 WHERE brpg.budget_reference_number = {$brn}
                 ORDER BY brpg.budget_req_program_id ASC",
                'DB'
            ) ?: [];

            $projectRows = App\ExecuteRows(
                "SELECT brpj.budget_req_project_id, brpj.budget_req_program_id, brpj.project_code, brpj.location, pj.project_name
                 FROM fims.budget_req_project brpj
                 LEFT JOIN fims.project pj ON pj.project_code = brpj.project_code
                 WHERE brpj.budget_reference_number = {$brn}
                 ORDER BY brpj.budget_req_project_id ASC",
                'DB'
            ) ?: [];

            $lineRows = App\ExecuteRows(
                "SELECT li.budget_line_item_id, li.budget_req_project_id, li.account_id, coa.account_code, coa.account_title, li.particulars,
                        li.type_of_fund_id, tof.name AS type_of_fund_name, li.component_id, ct.name AS component_name,
                        li.proposed_amount, li.adjustment, li.suggested_amount AS fund_balance,
                        li.account_payable AS carry_over_obligation, li.obligation, li.disbursement
                 FROM {$this->tableLineItems} li
                 LEFT JOIN fims.chart_of_accounts coa ON coa.account_id = li.account_id
                 LEFT JOIN fims.type_of_fund tof ON tof.type_of_fund_id = li.type_of_fund_id
                 LEFT JOIN fims.component_type ct ON ct.component_type_id = li.component_id
                 WHERE li.budget_reference_number = {$brn}
                 ORDER BY coa.account_code ASC NULLS LAST, li.budget_line_item_id ASC",
                'DB'
            ) ?: [];

            $attachmentRows = App\ExecuteRows(
                "SELECT attachment_id, budget_req_program_id, project_code, document_name, storage_key, external_link, uploaded_at
                 FROM {$this->tableAttachment}
                 WHERE budget_reference_number = {$brn}
                 ORDER BY uploaded_at ASC, attachment_id ASC",
                'DB'
            ) ?: [];

            // line items by project row, attachments by (program row, project code)
            $linesByProject = [];
            $unassigned = [];
            foreach ($lineRows as $line) {
                $line = $this->lineOut($line);
                if (empty($line['budget_req_project_id'])) {
                    $unassigned[] = $line;
                } else {
                    $linesByProject[(int) $line['budget_req_project_id']][] = $line;
                }
            }
            $docsByProject = [];
            foreach ($attachmentRows as $doc) {
                $docsByProject[(int) $doc['budget_req_program_id'].'|'.$doc['project_code']][] = $this->attachmentOut($doc);
            }
            $projectsByProgram = [];
            foreach ($projectRows as $project) {
                $lines = $linesByProject[(int) $project['budget_req_project_id']] ?? [];
                $project['line_items'] = $lines;
                $project['attachments'] = $docsByProject[(int) $project['budget_req_program_id'].'|'.$project['project_code']] ?? [];
                $project['totals'] = $this->sumLines($lines);
                $projectsByProgram[(int) $project['budget_req_program_id']][] = $project;
            }

            $programs = [];
            $all = $this->zeroAmounts();
            foreach ($programRows as $program) {
                $projects = $projectsByProgram[(int) $program['budget_req_program_id']] ?? [];
                $programLines = [];
                foreach ($projects as $project) {
                    array_push($programLines, ...$project['line_items']);
                }
                $program['projects'] = $projects;
                $program['totals'] = $this->sumLines($programLines);
                $programs[] = $program;
            }
            foreach ($lineRows as $line) {
                $all = $this->addTo($all, $this->lineOut($line));
            }
            $all['total'] = $this->totalOf($all);

            return ['success' => true, 'data' => [
                'budget_reference_number' => $header['budget_reference_number'],
                'fiscal_yr' => (int) $header['fiscal_yr'],
                'submitted_at' => $header['submitted_at'],
                'status_name' => $header['status_name'],
                'status_color' => $header['status_color'],
                'rc_id' => $header['rc_id'],
                'rc_code' => $header['rc_code'],
                'rc_name' => $header['rc_name'],
                'rc_office_type' => $header['rc_office_type'],
                'rc_parent_name' => $header['parent_rc_name'],
                'totals' => $all,
                'programs' => $programs,
                'unassigned_line_items' => $unassigned,
            ]];
        } catch (\Throwable $e) {
            App\LogError('CobService::fetchById '.$e->getMessage());

            return ['success' => false, 'httpCode' => 500, 'message' => 'Failed to load the budget request.'];
        }
    }

    private function lineOut(array $line): array
    {
        $amounts = $this->amountsOf($line);

        return array_merge($line, $amounts, ['total' => $this->totalOf($amounts), 'line_count' => 1]);
    }

    private function sumLines(array $lines): array
    {
        $sum = $this->zeroAmounts();
        foreach ($lines as $line) {
            $sum = $this->addTo($sum, $line);
        }
        $sum['total'] = $this->totalOf($sum);

        return $sum;
    }

    /** An attachment as the page needs it: an uploaded file becomes a presigned link, a pasted link stays as it is. */
    private function attachmentOut(array $doc): array
    {
        $url = $doc['external_link'] ?? null;
        if (!empty($doc['storage_key'])) {
            try {
                $url = function_exists('GetSignedUrl') ? \GetSignedUrl($doc['storage_key']) : null;
            } catch (\Throwable $e) {
                App\LogError('CobService: could not sign '.$doc['storage_key'].' — '.$e->getMessage());
                $url = null;   // the document is still listed, just not openable
            }
        }

        // A pasted link is user text: only a plain http(s) address is ever handed to the page as something to open.
        if ($url !== null && !preg_match('#^https?://#i', trim((string) $url))) {
            $url = null;
        }

        return [
            'attachment_id' => (int) $doc['attachment_id'],
            'document_name' => $doc['document_name'],
            'uploaded_at' => $doc['uploaded_at'],
            'is_link' => empty($doc['storage_key']),
            'view_url' => $url,
        ];
    }

    // -------------------------------------------------------------------------
    // Filter options
    // -------------------------------------------------------------------------

    /**
     * What the filter drop-downs offer: only values that exist on approved line items (inside the caller's scope), so every
     * choice returns something. Not narrowed by the other filters.
     */
    public function filterOptions(string $scopeRcId = ''): array
    {
        try {
            $f = $this->normalizeFilters([]);
            $where = 'WHERE '.implode(' AND ', $this->conditions($f, $scopeRcId));
            $from = $this->fromSql();
            $rows = fn (string $sql): array => App\ExecuteRows($sql, 'DB') ?: [];

            $regions = $rows(
                "SELECT DISTINCT
                        CASE WHEN LOWER(rc.office_type) = 'district' AND rc.parent_rc_id IS NOT NULL THEN rc.parent_rc_id
                             WHEN LOWER(rc.office_type) = 'regional' THEN rc.rc_id END AS region_id
                 {$from} {$where}"
            );
            $regionIds = array_values(array_filter(array_map(fn ($r) => $r['region_id'], $regions)));
            $regionList = $regionIds
                ? $rows('SELECT rc_id AS region_id, rc_code, rc_name FROM fims.responsibility_code WHERE rc_id IN ('
                    .implode(', ', array_map(fn ($id) => $this->str((string) $id), $regionIds)).') ORDER BY rc_code ASC')
                : [];
            $hasCentral = (bool) $rows("SELECT 1 AS x {$from} {$where} AND LOWER(COALESCE(rc.office_type, '')) NOT IN ('regional', 'district') LIMIT 1");
            if ($hasCentral) {
                array_unshift($regionList, ['region_id' => 'central', 'rc_code' => null, 'rc_name' => 'Central Office']);
            }

            return ['success' => true, 'data' => [
                'fiscal_years' => array_map(fn ($r) => (int) $r['fiscal_yr'], $rows("SELECT DISTINCT br.fiscal_yr {$from} {$where} ORDER BY br.fiscal_yr DESC")),
                'types_of_fund' => $rows("SELECT DISTINCT tof.type_of_fund_id, tof.name {$from} {$where} AND tof.type_of_fund_id IS NOT NULL ORDER BY tof.name ASC"),
                'components' => $rows("SELECT DISTINCT ct.component_type_id AS component_id, ct.name {$from} {$where} AND ct.component_type_id IS NOT NULL ORDER BY ct.component_type_id ASC"),
                'programs' => $rows("SELECT DISTINCT brpg.program_code, pg.program_name {$from} {$where} AND brpg.program_code IS NOT NULL ORDER BY pg.program_name ASC NULLS LAST, brpg.program_code ASC"),
                'regions' => $regionList,
            ]];
        } catch (\Throwable $e) {
            App\LogError('CobService::filterOptions '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the COB filters.'];
        }
    }
}
