<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * The single formula for a budget line item's reservation / commitment balance — shared by
 * BudgetLineItemService (transactional approval / revision checks) and CobService (the COB /
 * RFA-LAA Monitoring reports), so there is exactly one definition of "available" in the app.
 *
 * The model (migration "51 - budget_line_items_stable_suggested_amount.sql" made this the rule;
 * see each writer's own doc comment for the full history of what it replaced):
 *   - fims.budget_line_items.suggested_amount: Budget Processing's approved figure for the line
 *     (BudgetLineItemService::applyProcessing()). STABLE — nothing else ever writes it.
 *   - fims.budget_line_items.adjustment: the signed net of every approved Budget Revision
 *     (BudgetRevisionService::approve()).
 *   - fims.budget_line_items.obligation: what approved RFAs/LAAs have actually drawn. The ONLY
 *     writers are BudgetLineItemService::convertEarmarkForRfaApproval() (+R) and
 *     ::reverseConversions() (-R, when an Approved RFA/LAA is deleted).
 *   - fims.budget_line_item_earmark: one row per CBA/CFA/RFA/LAA reservation against a line.
 *     'active' = a CBA/CFA still holding it; 'converted' = drawn down by an RFA/LAA; 'released' =
 *     given back (the allocation that held it was deleted).
 *
 *   revised_amount   = suggested_amount + adjustment
 *   reserved_amount  = SUM(earmark.amount) WHERE status = 'active'
 *   committed_amount = SUM(earmark.amount) WHERE status = 'converted' AND allocation_type IN ('RFA','LAA')
 *   available_amount = revised_amount - reserved_amount - committed_amount
 *
 * Identity: reserved_amount + committed_amount always equals SUM(original_amount) of every
 * non-released CBA/CFA earmark row for the line — a CBA's reservation, plus whatever has since
 * been drawn from it by RFAs/LAAs, always adds back up to what the CBA originally earmarked. That
 * is what lets "Revised - CBA Requested = Available" (the COB sheet's identity) and this class's
 * own available_amount agree.
 *
 * LOCKING CONTRACT: a `SELECT ... FOR UPDATE` on a budget_line_items row is a mutex for its
 * earmark rows ONLY because every writer of those earmark rows takes that lock FIRST, in
 * ascending budget_line_item_id order (across every line item one call touches, when it touches
 * more than one). Every method that writes fims.budget_line_item_earmark rows for a line item
 * MUST follow this contract — BudgetLineItemService::createForApproval(),
 * convertEarmarkForRfaApproval(), reverseConversions() and releaseForAllocation() all do — or the
 * concurrency guarantee this class's callers rely on silently stops holding.
 *
 * `account_payable` is deliberately NOT part of this model — out of scope for migration 51, it
 * keeps its pre-existing CBA/CFA-approval-reservation-marker behavior untouched (see
 * BudgetLineItemService::createForApproval() / releaseForAllocation()).
 */
final class BudgetLineBalanceSql
{
    private function __construct()
    {
        // static-only
    }

    /**
     * One row per budget_line_item_id (so joining it can never multiply a line's amounts),
     * summarizing fims.budget_line_item_earmark joined to fims.budget_allocation (a deleted
     * allocation's rows count for nothing — a.deleted_at IS NULL).
     *
     * No `balance_applied` filter: under the stable-suggested_amount model nothing about a CBA's
     * reservation depends on that flag any more — it is vestigial (always FALSE going forward,
     * see migration 51). Every 'active' / 'converted' row counts.
     *
     * cba_original is carried only for the migration's own verification queries (reserved_active +
     * committed must equal it, line by line) — no displayed figure reads it directly.
     */
    public static function earmarkAggregateSql(
        string $earmarkTable = 'fims.budget_line_item_earmark',
        string $allocationTable = 'fims.budget_allocation'
    ): string {
        return "SELECT e.budget_line_item_id,
                       COALESCE(SUM(CASE WHEN e.status = 'active' THEN e.amount END), 0) AS reserved_active,
                       COALESCE(SUM(CASE WHEN e.status = 'converted' AND UPPER(a.allocation_type) IN ('RFA', 'LAA')
                                          THEN e.amount END), 0) AS committed,
                       COALESCE(SUM(CASE WHEN UPPER(a.allocation_type) = 'RFA' AND e.status = 'converted'
                                          THEN e.amount END), 0) AS rfa_approved,
                       COALESCE(SUM(CASE WHEN UPPER(a.allocation_type) = 'LAA' AND e.status = 'converted'
                                          THEN e.amount END), 0) AS laa_issued,
                       COALESCE(SUM(CASE WHEN UPPER(a.allocation_type) IN ('CBA', 'CFA') AND e.status IN ('active', 'converted')
                                          THEN e.original_amount END), 0) AS cba_original
                FROM {$earmarkTable} e
                JOIN {$allocationTable} a ON a.allocation_id = e.allocation_id AND a.deleted_at IS NULL
                GROUP BY e.budget_line_item_id";
    }

    /**
     * `available_amount`, as a joined expression — $li / $ear are the aliases of budget_line_items
     * and the earmarkAggregateSql() subquery in the caller's own FROM/JOIN.
     */
    public static function availableExpr(string $li = 'li', string $ear = 'ear'): string
    {
        return "(COALESCE({$li}.suggested_amount, 0) + COALESCE({$li}.adjustment, 0)
                 - COALESCE({$ear}.reserved_active, 0) - COALESCE({$ear}.committed, 0))";
    }

    /**
     * The same figure as a correlated scalar subquery, for a caller that cannot add a join to the
     * aggregate (e.g. BudgetRevisionService's approval CTE).
     */
    public static function correlatedAvailableExpr(
        string $li = 'li',
        string $earmarkTable = 'fims.budget_line_item_earmark',
        string $allocationTable = 'fims.budget_allocation'
    ): string {
        return "(COALESCE({$li}.suggested_amount, 0) + COALESCE({$li}.adjustment, 0)
                 - COALESCE((SELECT SUM(e.amount) FROM {$earmarkTable} e
                              JOIN {$allocationTable} a ON a.allocation_id = e.allocation_id AND a.deleted_at IS NULL
                             WHERE e.budget_line_item_id = {$li}.budget_line_item_id
                               AND (e.status = 'active' OR (e.status = 'converted' AND UPPER(a.allocation_type) IN ('RFA', 'LAA')))
                            ), 0))";
    }

    /**
     * The full alias map (see the class doc for what each means) — the single source both
     * BudgetLineItemService::fetchApproved() and CobService::lineAmountExprs() build on, so the two
     * can never independently drift.
     */
    public static function exprs(string $li = 'li', string $ear = 'ear'): array
    {
        $revised = "(COALESCE({$li}.suggested_amount, 0) + COALESCE({$li}.adjustment, 0))";
        $reserved = "COALESCE({$ear}.reserved_active, 0)";
        $committed = "COALESCE({$ear}.committed, 0)";
        $rfa = "COALESCE({$ear}.rfa_approved, 0)";
        $laa = "COALESCE({$ear}.laa_issued, 0)";

        return [
            'approved_amount' => "COALESCE({$li}.suggested_amount, 0)",
            'revision_amount' => "COALESCE({$li}.adjustment, 0)",
            'revised_amount' => $revised,
            'reserved_amount' => $reserved,
            'committed_amount' => $committed,
            'cba_requested' => "({$reserved} + {$committed})",
            'available_amount' => "({$revised} - {$reserved} - {$committed})",
            'rfa_approved' => $rfa,
            'laa_issued' => $laa,
            'remaining_balance' => "({$revised} - {$rfa} - {$laa})",
        ];
    }
}
