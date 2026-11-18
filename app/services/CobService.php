<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * Corporate Operating Budget (COB) Monitoring — the budget that has been APPROVED, and how much of it has been used.
 *
 * It is read-only. The budget comes from the Budget Request tables:
 *
 *   fims.budget_req                      header: fiscal year, requesting RC, status
 *     └ fims.budget_req_program          a Program (+ optional Sub-Program) of the request
 *         └ fims.budget_req_project      a Project of that Program
 *             ├ fims.budget_line_items   the chart-of-accounts lines — where the budget amounts live
 *             └ fims.budget_req_project_attachment   supporting documents of the project
 *
 * and what has been drawn against it from the memo requests (CBA / RFA / LAA) through the reservation ledger
 * fims.budget_line_item_earmark (see BudgetLineItemService).
 *
 * ONLY a request whose status is 'Approved' (fims.settings_status, status_for = 'budget_req') is part of the COB — every
 * method here goes through conditions(), which always starts from that, so a Draft / For Review / Endorsed / Returned
 * request never shows up in a table, a total or a filter option.
 *
 * The eight columns of the COB Monitoring sheet, per budget line item (every row, group and total is a SUM of these):
 *
 *   approved_amount     the amount FMD suggested in the Budget Proposal module, as first suggested — before any Budget Revision or CBA touched
 *                       the line: budget_line_items.suggested_amount + cba_requested - adjustment (suggested_amount is the line's live balance,
 *                       see the NOTE below)
 *   revision_amount     budget_line_items.adjustment: the net of the approved Budget Revisions (Revision / Realignment), signed — a decrease
 *                       or a realignment out is negative
 *   revised_amount      approved_amount + revision_amount (the adjustment is signed, so this is the Approved Amount less a decrease), worked
 *                       out per line — so a line no revision has touched shows its Approved Amount. It equals suggested_amount + cba_requested.
 *                       The stored budget_line_items.revised_amount column (written only when a Budget Revision is approved, 0 otherwise) is
 *                       no longer read here
 *   cba_requested      what approved CBAs (and CFAs) took off the line: their earmark rows' original_amount, only those that
 *                       actually reduced the balance (balance_applied) and are not released
 *   available_amount    revised_amount - cba_requested   (Available for Earmarking)
 *   rfa_approved        what approved RFAs drew down the CBAs' reservations (their 'converted' earmark rows)
 *   laa_issued          the same for LAAs
 *   remaining_balance   revised_amount - rfa_approved - laa_issued  (a CBA only RESERVES money; an RFA / LAA actually uses it)
 *
 * Five more figures per line item are for the RFA / LAA Monitoring page (rfa-laa-monitoring-page.php), which starts at the Revised Amount:
 *
 *   revised_computed    the same figure as the COB page's revised_amount (approved_amount + revision_amount = suggested_amount + cba_requested),
 *                       so both pages show one Revised Amount. It started from budget_line_items.proposed_amount (proposed + adjustment) before,
 *                       which ignored what FMD suggested below the proposal — see COB-MONITORING.md
 *   rfa_laa_amount      rfa_approved + laa_issued — what approved RFAs and issued LAAs have drawn from the CBAs
 *   disbursement        what the disbursement vouchers connected to the line's RFAs / LAAs (budget_allocation.dv_id) have disbursed: each voucher's
 *                       gross_amount spread over the draws of the RFAs / LAAs that point at it (disbursementSql()); cancelled / rejected vouchers
 *                       count for nothing. The stored budget_line_items.disbursement column, which nothing writes, is not used
 *   unallocated_amount  revised_computed - rfa_approved - laa_issued — the revised budget not yet drawn by an RFA or LAA
 *   account_payable     rfa_laa_amount - disbursement — what the RFAs / LAAs have drawn that has not been disbursed yet. Worked out per line, so
 *                       it takes the vouchers into account; the stored budget_line_items.account_payable column is not used
 *
 * Revised, Available and Remaining are all worked out, so the identities  Approved + Revision = Revised (Revision is signed: a decrease is negative,
 * i.e. Approved less the decrease),  Revised - CBA = Available  and  Revised - RFA - LAA = Remaining  hold on every row, group and total.
 * NOTE: suggested_amount is also the line's LIVE balance — an approved CBA takes its amount off it and an approved Budget Revision
 * moves it by the adjustment (BudgetRevisionService::approve(), a private method of the review flow). Reading it as the Approved Amount therefore showed a revised line's NEW amount
 * under Approved, and subtracting the adjustment from it took the revision back out of Revised. The Approved Amount is rebuilt as
 * suggested_amount + cba_requested - adjustment instead; see COB-MONITORING.md.
 *
 * Deleted memo requests do not count. A CBA approved before the "charge the line on approval" rule never took anything off the
 * line, so it is not in CBA Requested either.
 *
 * With rollup(..., $withAllocations = true) — only the RFA / LAA Monitoring page asks — every project node also carries, as children, the RFAs and
 * LAAs that drew against its budget lines (type 'allocation': RFA / LAA Amount = what it drew there) and, under each RFA, its disbursement voucher
 * (type 'dv': Disbursement = the voucher's gross_amount). They are detail rows: no total counts them.
 *
 * CO and COE. A budget line is told apart by its chart-of-accounts code (expenseClassSql()): 1… is CO (capital outlay), 5-01… is PS and 5-02… is MOOE — and
 * PS and MOOE together are the COE. A line with no program is COE too: a COE budget request carries line items of its own, with no Program, Sub-Program or
 * Project (budget_line_items.budget_req_project_id is NULL), and they were the "No program" / "No project" rows of the tree. With
 * rollup(..., $splitCoe = true) — both monitoring pages ask — the tree starts with two root nodes, 'CO' (Program > Sub-Program > Originating Unit >
 * Project, over the lines that have a program) and 'COE' (PS / MOOE > Originating Unit > the request; type 'class' then 'request'), see splitCoe().
 * fetchById() gives every line item its `expense_class` ('CO' / 'PS' / 'MOOE' / null) and the programless ones come back in `unassigned_line_items` — the COE.
 *
 * Views:
 *   summary()       the KPI cards
 *   rollup()        a tree, by 'fund_source' (Type of Fund > Component > Program > Sub-Program > Project),
 *                   'component' (Component > Type of Fund > ...), 'region' (Region > Program > Sub-Program > Project) or
 *                   'program' (Program > Sub-Program > Originating Unit > Project)
 *   fetchById()     one approved request in full: its programs, projects, line items and attachments
 *   filterOptions() what the filter drop-downs offer (only values that exist in approved data)
 *
 * Every method takes $scopeRcId — the RC the caller is limited to ('' = every RC), as resolved by
 * GlobalService::resolveBudgetReqRcFilter() — and shows that RC plus its descendants, like the Budget Request list.
 */
class CobService
{
    /**
     * The amount columns every row, group and total carries (see the header comment for what each is): the eight of the COB Monitoring sheet,
     * then the five more the RFA / LAA Monitoring page shows (revised_computed, rfa_laa_amount, disbursement, unallocated_amount, account_payable). The COB
     * page, its View modal and its report name the keys they show, so the extra four change nothing there.
     */
    public const AMOUNT_KEYS = ['approved_amount', 'revision_amount', 'revised_amount', 'cba_requested', 'available_amount', 'rfa_approved', 'laa_issued', 'remaining_balance',
        'revised_computed', 'rfa_laa_amount', 'disbursement', 'unallocated_amount', 'account_payable'];

    public const LAYOUTS = ['region', 'fund_source', 'component', 'program'];

    /** What each expense class (see expenseClassSql()) is called on a COE tree's PS / MOOE / Other rows. */
    private const CLASS_LABELS = [
        'PS' => 'Personal Services',
        'MOOE' => 'Maintenance and Other Operating Expenses',
        'OTHER' => 'Other (not PS / MOOE)',
    ];

    private string $tableBudgetReq = 'fims.budget_req';
    private string $tableLineItems = 'fims.budget_line_items';
    private string $tableAttachment = 'fims.budget_req_project_attachment';
    private string $tableEarmark = 'fims.budget_line_item_earmark';
    private string $tableAllocation = 'fims.budget_allocation';

    // -------------------------------------------------------------------------
    // Shared SQL
    // -------------------------------------------------------------------------

    /**
     * What the memo requests have drawn against each line item, one row per line item (so joining it multiplies nothing):
     *   cba_requested  CBA / CFA earmark rows that took their amount off the line (balance_applied) and are not released
     *   rfa_approved   the 'converted' rows attributed to an RFA — what that RFA drew from its parent CBA
     *   laa_issued     the same for an LAA
     * A memo request that has been deleted (deleted_at) counts for nothing.
     */
    private function earmarkSql(): string
    {
        return "SELECT e.budget_line_item_id,
                       SUM(CASE WHEN UPPER(a.allocation_type) IN ('CBA', 'CFA') AND e.balance_applied AND e.status IN ('active', 'converted')
                                THEN e.original_amount ELSE 0 END) AS cba_requested,
                       SUM(CASE WHEN UPPER(a.allocation_type) = 'RFA' AND e.status = 'converted' THEN e.amount ELSE 0 END) AS rfa_approved,
                       SUM(CASE WHEN UPPER(a.allocation_type) = 'LAA' AND e.status = 'converted' THEN e.amount ELSE 0 END) AS laa_issued
                FROM {$this->tableEarmark} e
                JOIN {$this->tableAllocation} a ON a.allocation_id = e.allocation_id AND a.deleted_at IS NULL
                GROUP BY e.budget_line_item_id";
    }

    /**
     * What the disbursement vouchers connected to RFAs / LAAs have disbursed, one row per budget line item (so joining it multiplies nothing).
     * A voucher is connected to an allocation by budget_allocation.dv_id. Its gross_amount is spread over the 'converted' earmark rows of EVERY
     * RFA / LAA that points at it, in proportion to what each row drew (e.amount) — so a voucher that pays two RFAs, or an RFA that draws on two
     * budget lines, is never counted twice, and the shares add back up to the voucher's gross_amount. A cancelled or rejected voucher counts for
     * nothing, and neither does a deleted allocation. This is the Disbursement of the RFA / LAA Monitoring page.
     */
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
                LEFT JOIN ({$this->earmarkSql()}) ear ON ear.budget_line_item_id = li.budget_line_item_id
                LEFT JOIN ({$this->disbursementSql()}) dvl ON dvl.budget_line_item_id = li.budget_line_item_id
                LEFT JOIN (
                    SELECT budget_req_program_id, project_code, COUNT(*) AS attachment_count
                    FROM {$this->tableAttachment}
                    GROUP BY budget_req_program_id, project_code
                ) att ON att.budget_req_program_id = brpg.budget_req_program_id AND att.project_code = brpj.project_code";
    }

    /**
     * The expense class of a line item, read off its chart-of-accounts code (the same prefix rule as DisbursementVoucherService::deriveExpenseClass()
     * and the Budget Proposal "View as COB"; chart_of_accounts has no stored column for it):
     *   1…    -> 'CO'    capital outlay
     *   5-01… -> 'PS'    Personal Services       (a COE class)
     *   5-02… -> 'MOOE'  Maintenance & Other Operating Expenses (a COE class)
     * NULL for any other account, and for a line with no account. Needs the `coa` join.
     */
    private function expenseClassSql(): string
    {
        return "CASE WHEN coa.account_code LIKE '5-01%' THEN 'PS'
                     WHEN coa.account_code LIKE '5-02%' THEN 'MOOE'
                     WHEN coa.account_code LIKE '1%' THEN 'CO' END";
    }

    /**
     * Which side of the CO / COE split a flat row (one with an `expense_class` and a `budget_req_program_id`) is on, and its class:
     *   - COE when the line has NO program (a COE budget request's own line items belong to no Program or Project) or its account is PS / MOOE;
     *     its class is then 'PS', 'MOOE', or 'OTHER' (a programless line whose account is neither)
     *   - CO otherwise (class 'CO')
     *
     * @return array{0: string, 1: string} [bucket 'CO'|'COE', class 'CO'|'PS'|'MOOE'|'OTHER']
     */
    private function costTypeOf(array $r): array
    {
        $class = (string) ($r['expense_class'] ?? '');
        if (empty($r['budget_req_program_id']) || $class === 'PS' || $class === 'MOOE') {
            return ['COE', $class === 'PS' || $class === 'MOOE' ? $class : 'OTHER'];
        }

        return ['CO', 'CO'];
    }

    /** One line item's figures (see the header comment), aliased to AMOUNT_KEYS — the building block of every SUM. */
    private function lineAmountExprs(): array
    {
        $cba = 'COALESCE(ear.cba_requested, 0)';
        $rfa = 'COALESCE(ear.rfa_approved, 0)';
        $laa = 'COALESCE(ear.laa_issued, 0)';
        // The COB sheet's Approved Amount: the amount as first suggested, BEFORE any Budget Revision or CBA touched the line. suggested_amount is the line's
        // LIVE balance — an approved Budget Revision adds its signed adjustment to it and an approved CBA takes its amount off it — so the original is the live
        // amount, plus what the CBAs took off (cba_requested), less the adjustment. (Reading suggested_amount as it stands put the revised figure under
        // Approved and made Approved - Revision undo a revision that was already in it: a line cut from 352,418 to 130,000 showed 130,000 as Approved and
        // 352,418 as Revised.)
        $approved = "(COALESCE(li.suggested_amount, 0) + {$cba} - COALESCE(li.adjustment, 0))";
        // The COB sheet's Revised Amount: the Approved Amount net of the Revision / Realignment. The adjustment is signed (a decrease is negative, shown in
        // brackets), so "Approved less the revision" is Approved + adjustment — which is suggested_amount + cba_requested, the line as revised, before its CBAs.
        // One definition: Available and Remaining below start from it, so they follow it.
        $revised = "({$approved} + COALESCE(li.adjustment, 0))";
        // the RFA / LAA Monitoring page's Revised Amount: the SAME figure as the COB sheet's (above), so the two pages can never disagree. It used to be
        // proposed_amount + adjustment, which starts from what the office proposed and so ignores what FMD suggested in Budget Processing — the approved
        // budget, and what CBAs draw against; a line FMD cut showed its full proposed amount here and the cut as "unallocated". One definition, used by
        // revised_computed and by unallocated_amount below.
        $revisedCalc = $revised;
        $rfaLaa = "({$rfa} + {$laa})";
        // Disbursement = what the vouchers connected to the line's RFAs / LAAs have disbursed (disbursementSql()); the stored
        // budget_line_items.disbursement column, which nothing writes, is not used
        $disb = 'COALESCE(dvl.dv_gross, 0)';

        return [
            'approved_amount' => $approved,
            'revision_amount' => 'li.adjustment',
            'revised_amount' => $revised,
            'cba_requested' => $cba,
            'available_amount' => "({$revised} - {$cba})",
            'rfa_approved' => $rfa,
            'laa_issued' => $laa,
            'remaining_balance' => "({$revised} - {$rfa} - {$laa})",
            // the RFA / LAA Monitoring page's own figures. Its Revised Amount is the COB sheet's (worked out per line, not read from the recorded
            // revised_amount column); the rest is what that page adds
            'revised_computed' => $revisedCalc,
            'rfa_laa_amount' => $rfaLaa,
            'disbursement' => $disb,
            // Unallocated Amount = Revised Amount - RFA / LAA Amount (so it foots on every row, group and total)
            'unallocated_amount' => "({$revisedCalc} - {$rfaLaa})",
            // Account Payable = RFA / LAA Amount - Disbursement (it foots on every row, group and total)
            'account_payable' => "({$rfaLaa} - {$disb})",
        ];
    }

    /**
     * The result for a query that threw: logged with its real cause, and returned with a message the page can show.
     */
    private function failure(string $method, string $message, \Throwable $e, array $extra = []): array
    {
        App\LogError('CobService::'.$method.' '.$e->getMessage());

        return ['success' => false, 'message' => $message] + $extra;
    }

    /** The SUMs of the eight figures, aliased to AMOUNT_KEYS, plus how many line items they cover. */
    private function sumsSql(): string
    {
        $parts = ['COUNT(*) AS line_count'];
        foreach ($this->lineAmountExprs() as $alias => $expr) {
            $parts[] = "COALESCE(SUM({$expr}), 0) AS {$alias}";
        }

        return implode(",\n                ", $parts);
    }

    /** The same figures for ONE line item (no SUM), aliased to AMOUNT_KEYS. */
    private function lineSql(): string
    {
        $parts = [];
        foreach ($this->lineAmountExprs() as $alias => $expr) {
            $parts[] = "{$expr} AS {$alias}";
        }

        return implode(",\n                        ", $parts);
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

    /** @return array<string, float> the eight amount columns of a DB row, as floats */
    private function amountsOf(array $row): array
    {
        $out = [];
        foreach (self::AMOUNT_KEYS as $k) {
            $out[$k] = $this->num($row[$k] ?? 0);
        }

        return $out;
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

            return ['success' => true, 'data' => [
                'requests' => (int) ($row['requests'] ?? 0),
                'programs' => (int) ($row['programs'] ?? 0),
                'projects' => (int) ($row['projects'] ?? 0),
                'line_items' => (int) ($row['line_count'] ?? 0),
            ] + $this->amountsOf($row)];
        } catch (\Throwable $e) {
            return $this->failure('summary', 'Failed to load the COB summary.', $e);
        }
    }

    // -------------------------------------------------------------------------
    // Roll-ups — the tree tabs
    // -------------------------------------------------------------------------

    /**
     * The tree for one tab. Every node is the same shape — { type, key, label, code, ...amounts, line_count, children[] } —
     * whatever its level, so one renderer draws all three tabs. A node's amounts are the sums of everything below it, so every
     * level foots to the one above. Nodes whose Type of Fund / Component / Program / ... was never set are kept (labelled
     * 'Unassigned' / 'No program' ...) and sorted last: nothing approved is ever left out. A program block with no Sub-Program
     * simply has no Sub-Program level: its projects sit directly under the program.
     *
     * With $splitCoe the tree is cut in two at the top — a 'CO' node (the layout's own tree, over the lines that belong to a program) and a
     * 'COE' node (PS / MOOE -> Originating Unit -> the request, or the project when the line has one) — see splitCoe(). Off by default: the
     * Generate Report and the other callers see the tree as it always was, where a programless line sits under "No program".
     *
     * @return array{success: bool, layout_by?: string, data?: array, totals?: array, message?: string}
     */
    public function rollup(string $layoutBy, array $filters, string $scopeRcId = '', bool $withAllocations = false, bool $splitCoe = false): array
    {
        if (!in_array($layoutBy, self::LAYOUTS, true)) {
            return ['success' => false, 'message' => 'Unknown layout_by: '.$layoutBy];
        }

        try {
            $f = $this->normalizeFilters($filters);

            // One flat row per (fund, component, office, program block, project) — the leaf of every tree. The project
            // is kept apart per request (a project code is generated per RC and year, so this is effectively one per project).
            $rows = App\ExecuteRows(
                'SELECT tof.type_of_fund_id, tof.name AS type_of_fund_name,
                        ct.component_type_id, ct.name AS component_name,
                        br.budget_reference_number, br.fiscal_yr,
                        br.rc_id, rc.rc_code, rc.rc_name, rc.office_type AS rc_office_type,
                        rc.parent_rc_id, prc.rc_code AS parent_rc_code, prc.rc_name AS parent_rc_name,
                        brpg.budget_req_program_id, brpg.program_code, pg.program_name,
                        brpg.sub_program_code, sp.sub_program_name,
                        brpj.project_code, pj.project_name, brpj.location,
                        COALESCE(att.attachment_count, 0) AS attachment_count,
                        '.$this->expenseClassSql().' AS expense_class,
                        '.$this->sumsSql().'
                 '.$this->fromSql().'
                 WHERE '.implode(' AND ', $this->conditions($f, $scopeRcId)).'
                 GROUP BY tof.type_of_fund_id, tof.name, ct.component_type_id, ct.name,
                          br.budget_reference_number, br.fiscal_yr,
                          br.rc_id, rc.rc_code, rc.rc_name, rc.office_type, rc.parent_rc_id, prc.rc_code, prc.rc_name,
                          brpg.budget_req_program_id, brpg.program_code, pg.program_name, brpg.sub_program_code, sp.sub_program_name,
                          brpj.project_code, pj.project_name, brpj.location, att.attachment_count, '.$this->expenseClassSql(),
                'DB'
            ) ?: [];

            $tree = $splitCoe ? $this->splitCoe($rows, $layoutBy) : $this->buildTree($rows, $this->levelsFor($layoutBy));
            $totals = $this->zeroAmounts();
            foreach ($tree as $node) {
                $totals = $this->addTo($totals, $node);
            }

            // The RFA / LAA Monitoring page also wants, under every project, the RFAs and LAAs drawn against it and under each RFA its
            // disbursement voucher. They are detail rows added AFTER the totals, so no figure above them changes; if they cannot be read
            // the tree is still returned, with a message.
            $extra = [];
            if ($withAllocations) {
                try {
                    $tree = $this->attachAllocations($tree, $this->allocationRows($f, $scopeRcId, $splitCoe));
                } catch (\Throwable $e) {
                    App\LogError('CobService::rollup (allocations) '.$e->getMessage());
                    $extra['allocation_error'] = 'The RFAs / LAAs under the projects could not be loaded.';
                }
            }

            return ['success' => true, 'layout_by' => $layoutBy, 'data' => $tree, 'totals' => $totals] + $extra;
        } catch (\Throwable $e) {
            return $this->failure('rollup', 'Failed to load the COB roll-up.', $e);
        }
    }

    /**
     * The RFAs and LAAs that have drawn against each project, with the voucher of each, keyed like the tree's project nodes
     * (budget reference no. | program block | project code). One row per (project, allocation): the allocation's drawn amount on THAT
     * project is the sum of its 'converted' earmark rows on the project's budget lines — the same rows that make up RFA Approved and
     * LAA Issued. A deleted allocation does not count. An allocation's disbursement voucher is the row of fims.disbursement_vouchers that
     * budget_allocation.dv_id points at — for an RFA or an LAA alike: any allocation with a dv_id gets its voucher as its child (today only RFAs are
     * linked to one by the voucher form, DisbursementVoucherService::linkRfas()). The voucher is shown whatever its status — a cancelled or rejected one
     * too, its status pill says so. A voucher's status is read through fims.settings_status (status_id): the table has no status column of its own any
     * more ("03 - alter_table/21").
     * The same filters and the same RC scope as the tree itself. With $splitCoe the keys are the COE tree's (leafKey()): a COE request's lines are told
     * apart by class, so what an RFA drew on the PS lines and on the MOOE lines each hangs on its own leaf.
     *
     * @return array<string, list<array>> project key => allocation nodes, each with its voucher (if any) as its only child
     */
    private function allocationRows(array $f, string $scopeRcId, bool $splitCoe = false): array
    {
        $rows = App\ExecuteRows(
            'SELECT br.budget_reference_number, brpg.budget_req_program_id, brpj.project_code, '.$this->expenseClassSql().' AS expense_class,
                    a.allocation_id, UPPER(a.allocation_type) AS allocation_type, a.allocation_number, a.request_referrence_no, a.description,
                    COALESCE(SUM(e.amount), 0) AS drawn_amount,
                    dv.dv_id, dv.dv_no, dv.dv_date, dv.claimant_name, dv.gross_amount AS dv_amount,
                    dst.name AS dv_status, dst.color AS dv_status_color
             '.$this->fromSql()."
             JOIN {$this->tableEarmark} e ON e.budget_line_item_id = li.budget_line_item_id AND e.status = 'converted'
             JOIN {$this->tableAllocation} a ON a.allocation_id = e.allocation_id AND a.deleted_at IS NULL
                                            AND UPPER(a.allocation_type) IN ('RFA', 'LAA')
             LEFT JOIN fims.disbursement_vouchers dv ON dv.dv_id = a.dv_id
             LEFT JOIN fims.settings_status dst ON dst.id = dv.status_id
             WHERE ".implode(' AND ', $this->conditions($f, $scopeRcId)).'
             GROUP BY br.budget_reference_number, brpg.budget_req_program_id, brpj.project_code, '.$this->expenseClassSql().',
                      a.allocation_id, a.allocation_type, a.allocation_number, a.request_referrence_no, a.description,
                      dv.dv_id, dv.dv_no, dv.dv_date, dv.claimant_name, dv.gross_amount, dst.name, dst.color
             ORDER BY a.allocation_type, a.allocation_number, a.allocation_id',
            'DB'
        ) ?: [];

        $byProject = [];
        foreach ($rows as $r) {
            $key = $this->leafKey($r, $splitCoe && $this->costTypeOf($r)[0] === 'COE');
            $number = trim((string) ($r['allocation_number'] ?? '')) !== ''
                ? (string) $r['allocation_number']
                : (string) ($r['request_referrence_no'] ?? ('#'.$r['allocation_id']));

            $children = [];
            if (!empty($r['dv_id'])) {
                // a voucher row: its gross_amount goes in the Disbursement column — unless the voucher is cancelled / rejected, which counts for nothing
                // in the totals (disbursementSql()), so its row shows a dash too and says it is not counted
                $void = in_array(strtoupper((string) ($r['dv_status'] ?? '')), ['CANCELLED', 'REJECTED'], true);
                $children[] = [
                    'type' => 'dv', 'key' => 'd'.$r['dv_id'], 'label' => (string) ($r['dv_no'] ?? ('DV #'.$r['dv_id'])), 'code' => 'DV', 'unassigned' => false,
                    'dv_id' => (int) $r['dv_id'], 'dv_date' => $r['dv_date'] ?? null, 'claimant_name' => $r['claimant_name'] ?? null,
                    'status_name' => $r['dv_status'] ?? null, 'status_color' => $r['dv_status_color'] ?? null,
                    'counted' => !$void,
                    'disbursement' => $void ? 0.0 : $this->num($r['dv_amount'] ?? 0),
                ] + $this->zeroAmounts() + ['children' => []];
            }

            // an allocation row: what it drew on this project goes in the RFA / LAA Amount column
            $byProject[$key][] = [
                'type' => 'allocation', 'key' => 'a'.$r['allocation_id'], 'label' => $number, 'code' => (string) $r['allocation_type'], 'unassigned' => false,
                'allocation_id' => (int) $r['allocation_id'], 'allocation_type' => (string) $r['allocation_type'],
                'description' => $r['description'] ?? null,
                'rfa_laa_amount' => $this->num($r['drawn_amount'] ?? 0),
            ] + $this->zeroAmounts() + ['children' => $children];
        }

        return $byProject;
    }

    /** Hangs each project's allocations (allocationRows()) on its project node — or, in the COE tree, on its request node — wherever in the tree the node sits. */
    private function attachAllocations(array $nodes, array $byProject): array
    {
        foreach ($nodes as &$node) {
            if (in_array($node['type'] ?? '', ['project', 'request'], true)) {
                $node['children'] = $byProject[$node['key']] ?? [];
            } elseif (!empty($node['children'])) {
                $node['children'] = $this->attachAllocations($node['children'], $byProject);
            }
        }
        unset($node);

        return $nodes;
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
     * The levels of a tree, top to bottom: each is ['type' => ..., 'of' => fn(row) => [key, attributes] | null]. A level whose
     * closure answers null does not apply to that row (a program block with no Sub-Program) and is skipped for it.
     * Program, Sub-Program and Project close every tree; the first levels are what the tab is about.
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
        // a Region node shows its RC id as its code ("R04"), like the sheet; the RC code only orders the regions
        $region = ['type' => 'region', 'of' => function (array $r): array {
            [$id, $name, $sort] = $this->regionOf($r);

            return [$id, ['label' => $name, 'code' => $id === 'central' ? null : $id, 'sort' => $sort, 'unassigned' => false]];
        }];
        $unit = $this->unitLevel();
        $program = ['type' => 'program', 'of' => static fn (array $r): array => [
            $r['program_code'] ?? 'none',
            [
                'label' => $r['program_name'] ?? ($r['program_code'] ?? 'No program'),
                'code' => $r['program_code'] ?? null,
                'unassigned' => empty($r['program_code']),
            ],
        ]];
        $subProgram = ['type' => 'subprogram', 'of' => static fn (array $r): ?array => empty($r['sub_program_code']) ? null : [
            ($r['program_code'] ?? '').'|'.$r['sub_program_code'],
            ['label' => $r['sub_program_name'] ?? $r['sub_program_code'], 'code' => $r['sub_program_code'], 'unassigned' => false],
        ]];
        $project = ['type' => 'project', 'of' => fn (array $r): array => [$this->leafKey($r, false), $this->projectAttrs($r)]];

        return match ($layoutBy) {
            'fund_source' => [$fund, $component, $program, $subProgram, $project],
            'component' => [$component, $fund, $program, $subProgram, $project],
            // a program block with no Sub-Program has no Sub-Program level (see insertRow): its offices sit directly under the program
            'program' => [$program, $subProgram, $unit, $project],
            default => [$region, $program, $subProgram, $project],
        };
    }

    /**
     * An Originating Unit is the office (RC) that requested the budget — fims.budget_req.rc_id. Like a Region node it shows its RC id as its
     * code and is ordered by its RC code ('sort'). A request with no RC is kept, as 'No originating unit', and sorted last.
     */
    private function unitLevel(): array
    {
        return ['type' => 'unit', 'of' => static fn (array $r): array => [
            $r['rc_id'] ?? 'none',
            [
                'label' => $r['rc_name'] ?? ($r['rc_id'] ?? 'No originating unit'),
                'code' => $r['rc_id'] ?? null,
                'sort' => $r['rc_code'] ?? '',
                'unassigned' => empty($r['rc_id']),
            ],
        ]];
    }

    /** The attributes of a project node (its figures are added by insertRow()). A line with no project is kept as 'No project', unassigned. */
    private function projectAttrs(array $r): array
    {
        [$regionId] = $this->regionOf($r);

        return [
            'label' => $r['project_name'] ?? ($r['project_code'] ?? 'No project'),
            'code' => $r['project_code'] ?? null,
            'location' => $r['location'] ?? null,
            'unassigned' => empty($r['project_code']),
            'budget_reference_number' => $r['budget_reference_number'] ?? null,
            'fiscal_yr' => isset($r['fiscal_yr']) ? (int) $r['fiscal_yr'] : null,
            'rc_id' => $r['rc_id'] ?? null,
            'rc_name' => $r['rc_name'] ?? null,
            'region_id' => $regionId,
            'attachment_count' => (int) ($r['attachment_count'] ?? 0),
        ];
    }

    /**
     * The key of a tree's last node — what allocationRows() matches the RFAs / LAAs on: budget reference no. | program block | project code. A
     * leaf of the COE tree ($coe) also carries its class, because there a request's PS lines and MOOE lines are different leaves and must not
     * both pick up the same RFAs.
     */
    private function leafKey(array $r, bool $coe): string
    {
        $key = ($r['budget_reference_number'] ?? '').'|'.($r['budget_req_program_id'] ?? '').'|'.($r['project_code'] ?? '');

        return $coe ? $key.'|'.$this->costTypeOf($r)[1] : $key;
    }

    /**
     * The levels of the COE half of a split tree: the expense class (PS / MOOE / Other), the Originating Unit, then the leaf — the PROJECT when the
     * line has one, else the REQUEST itself (type 'request', named by its budget reference number): a COE budget request has no Program or Project.
     */
    private function coeLevels(): array
    {
        $class = ['type' => 'class', 'of' => function (array $r): array {
            $c = $this->costTypeOf($r)[1];

            return [$c, ['label' => self::CLASS_LABELS[$c] ?? $c, 'code' => $c === 'OTHER' ? null : $c, 'unassigned' => false]];
        }];
        $leaf = ['type' => 'project', 'of' => function (array $r): array {
            $attrs = $this->projectAttrs($r);
            if (empty($r['project_code'])) {
                $attrs = ['type' => 'request', 'label' => (string) ($r['budget_reference_number'] ?? 'No reference no.'), 'code' => null, 'unassigned' => false] + $attrs;
            }

            return [$this->leafKey($r, true), $attrs];
        }];

        return [$class, $this->unitLevel(), $leaf];
    }

    /**
     * The tree cut in two: 'CO' (the layout's own levels, over the rows that belong to a program) and 'COE' (coeLevels(), over the rows that do not,
     * plus any line whose account is PS / MOOE — see costTypeOf()). Each half is a root node carrying the sums of everything under it, so the tree
     * still foots to its totals; a half with no rows is left out.
     */
    private function splitCoe(array $rows, string $layoutBy): array
    {
        $co = $coe = [];
        foreach ($rows as $row) {
            if ($this->costTypeOf($row)[0] === 'COE') {
                $coe[] = $row;
            } else {
                $co[] = $row;
            }
        }

        $out = [];
        if ($co) {
            $out[] = $this->bucketNode('CO', 'Capital Outlay', $this->buildTree($co, $this->levelsFor($layoutBy)));
        }
        if ($coe) {
            $out[] = $this->bucketNode('COE', 'Current Operating Expenditures', $this->buildTree($coe, $this->coeLevels()));
        }

        return $out;
    }

    /** A root node of a split tree: the code is its key, its figures the sums of its children. */
    private function bucketNode(string $code, string $label, array $children): array
    {
        $node = ['type' => 'bucket', 'key' => $code, 'label' => $label, 'code' => $code, 'unassigned' => false] + $this->zeroAmounts() + ['children' => $children];
        foreach ($children as $child) {
            $node = $this->addTo($node, $child);
        }

        return $node;
    }

    /**
     * Which Region an office belongs to: a District office -> its Regional office (the RC above it); a Regional office -> itself;
     * every other RC (the central departments / groups) -> 'Central Office'.
     *
     * @return array{0: string, 1: string, 2: string} [key (the region's rc_id, or 'central'), name, rc_code to order by]
     */
    private function regionOf(array $r): array
    {
        $type = strtolower(trim((string) ($r['rc_office_type'] ?? '')));
        if ($type === 'district' && !empty($r['parent_rc_id'])) {
            return [(string) $r['parent_rc_id'], (string) ($r['parent_rc_name'] ?? $r['parent_rc_id']), (string) ($r['parent_rc_code'] ?? '')];
        }
        if ($type === 'regional' && !empty($r['rc_id'])) {
            return [(string) $r['rc_id'], (string) ($r['rc_name'] ?? $r['rc_id']), (string) ($r['rc_code'] ?? '')];
        }

        return ['central', 'Central Office', ''];
    }

    /** Folds the flat leaf rows into the nested tree the levels describe, then sorts it. */
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
        $answer = null;
        for (; $depth < count($levels); $depth++) {   // skip the levels that do not apply to this row
            $answer = $levels[$depth]['of']($row);
            if ($answer !== null) {
                break;
            }
        }
        if ($answer === null) {
            return;
        }

        [$key, $attrs] = $answer;
        $key = (string) $key;
        if (!isset($siblings[$key])) {
            // a level normally fixes its nodes' type; a node may say otherwise itself (the COE tree's leaf is a 'request' when the line has no project)
            $type = $attrs['type'] ?? $levels[$depth]['type'];
            unset($attrs['type']);
            $siblings[$key] = ['type' => $type, 'key' => $key] + $attrs + $this->zeroAmounts() + ['children' => []];
        }
        $siblings[$key] = $this->addTo($siblings[$key], $row);

        if ($depth + 1 < count($levels)) {
            $this->insertRow($siblings[$key]['children'], $levels, $depth + 1, $row);
        }
    }

    /** Children become lists, every level is put in its reporting order. */
    private function finishNodes(array $siblings): array
    {
        $nodes = [];
        foreach ($siblings as $node) {
            $node['children'] = $this->finishNodes($node['children']);
            $nodes[] = $node;
        }
        usort($nodes, [$this, 'compareNodes']);

        return $nodes;
    }

    /**
     * Reporting order. An 'Unassigned' node is always last. Types of Fund keep the COB's fixed order — NG Support first, any
     * other fund next, then Trust Fund, then Corporate Receipts. Components follow Capital Outlay, Other-Capital Outlay, COE.
     * Regions go by their RC code (Central Office first); programs, sub-programs and projects by their code, as on the sheet;
     * anything else reads alphabetically.
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
            case 'class':   // PS, then MOOE, then anything else
                $rank = ['PS' => 0, 'MOOE' => 1];
                $byRank = ($rank[$a['code'] ?? ''] ?? 2) <=> ($rank[$b['code'] ?? ''] ?? 2);
                if ($byRank !== 0) {
                    return $byRank;
                }
                break;
            case 'region':
                $byCode = strcmp((string) ($a['sort'] ?? ''), (string) ($b['sort'] ?? ''));
                if ($byCode !== 0) {
                    return $byCode;
                }
                break;
            case 'unit':   // offices by their RC code, so a Region and its Districts stay together
                $byCode = strnatcasecmp((string) ($a['sort'] ?? ''), (string) ($b['sort'] ?? ''));
                if ($byCode !== 0) {
                    return $byCode;
                }
                break;
            case 'program':
            case 'subprogram':
            case 'project':
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
    // One approved request
    // -------------------------------------------------------------------------

    /**
     * One APPROVED budget request in full — its programs, each program's projects, each project's line items and attachments.
     * Not found when it does not exist, is not Approved, or belongs to an RC outside the caller's scope (the three look the same).
     * Line items of an older request with no program block come back in `unassigned_line_items`. Every line item, project,
     * program and the request itself carries the eight figures.
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
                        {$this->expenseClassSql()} AS expense_class,
                        {$this->lineSql()}
                 FROM {$this->tableLineItems} li
                 LEFT JOIN fims.chart_of_accounts coa ON coa.account_id = li.account_id
                 LEFT JOIN fims.type_of_fund tof ON tof.type_of_fund_id = li.type_of_fund_id
                 LEFT JOIN fims.component_type ct ON ct.component_type_id = li.component_id
                 LEFT JOIN ({$this->earmarkSql()}) ear ON ear.budget_line_item_id = li.budget_line_item_id
                 LEFT JOIN ({$this->disbursementSql()}) dvl ON dvl.budget_line_item_id = li.budget_line_item_id
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
            $all = $this->zeroAmounts();
            foreach ($lineRows as $line) {
                $line = $this->lineOut($line);
                $all = $this->addTo($all, $line);
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
            return $this->failure('fetchById', 'Failed to load the budget request.', $e, ['httpCode' => 500]);
        }
    }

    private function lineOut(array $line): array
    {
        return array_merge($line, $this->amountsOf($line), ['line_count' => 1]);
    }

    private function sumLines(array $lines): array
    {
        $sum = $this->zeroAmounts();
        foreach ($lines as $line) {
            $sum = $this->addTo($sum, $line);
        }

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
            return $this->failure('filterOptions', 'Failed to load the COB filters.', $e);
        }
    }
}
