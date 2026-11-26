<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BudgetReqService;
use PHPMaker2024\FIMS\Service\GlobalService;

/**
 * The Financial Management Department's own view of the budget requests (page: app/pages/budget-prop-page.php).
 *
 * GET /fmd/budget_req/list — every Regional / District office's request, paged, in the same shape as
 *                            GET /budget_req/list (header + programs[] > projects[] > line_items[], its RC,
 *                            `li_total` = the amount, `submitted_at`, status), so the page's row actions
 *                            (Budget Processing / Budget Preview) can use the rows as they are.
 *   Query: fiscal_yr, program_code (filters), page (default 1), limit (default 15, 1–100).
 *
 * Unlike /budget_req/list, the SCOPE is decided here, not by the client: the list is always every Regional / District office
 * (GlobalService::CENTRAL_REVIEW_RC_CODE_FROM), and the `central`, `rc_id` and `rc_code_from` query parameters are not read, so they
 * can't widen or narrow it. Any logged-in user (the JWT) gets the list — it is no longer limited to the Financial Management Department
 * (it used to answer 403 to every other RC). What a user may DO with a row is still decided per row by `review` (withReview(): its
 * can_decide, from the user's level), so Budget Processing and Return / Approve stay with the Budget Officer / Budget Head.
 */
$FmdBudgetReqService = new BudgetReqService();

$app->get('/fmd/budget_req/list', function ($request, $response, $args) use ($FmdBudgetReqService) {
    try {
        $userId = (int) $request->getAttribute('user_id');

        $params = $request->getQueryParams();
        $text   = fn (string $key): string => is_scalar($params[$key] ?? null) ? trim((string) $params[$key]) : '';   // ?x[]=… is ignored
        $page   = max(1, (int) $text('page'));
        $limit  = $text('limit') === '' ? 15 : min(100, max(1, (int) $text('limit')));

        $result = $FmdBudgetReqService->fetchAlls(
            $page,
            $limit,
            $text('fiscal_yr'),
            $text('program_code'),
            '',                                                   // every RC …
            GlobalService::CENTRAL_REVIEW_RC_CODE_FROM,           // … that is a Regional / District office
            true                                                  // the proposal list: the projects added by approved SUPPLEMENTAL revisions are not in it
        );
        if ($result['success']) {
            // where each row stands in the review chain (For Review -> Budget Officer -> Endorsed -> Budget Head -> Approved)
            // and whether THIS user may decide it now — the row actions are driven by it
            $result['data'] = $FmdBudgetReqService->withReview($result['data'], $userId);
        }

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
