<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BurService;
use PHPMaker2024\FIMS\Service\GlobalService;

/*
 * Budget Utilization Report (BUR) — monitoring. Read-only, backs app/pages/bur-page.php.
 * Personal Services (chart-of-accounts '5-01') and Maintenance and Other Operating Expenses ('5-02'),
 * rolled up from every APPROVED budget request's line items — see BurService for what each figure means.
 *
 *   GET /bur/filters              the Fiscal Year filter's options (years that have approved PS / MOOE budget)
 *   GET /bur/rollup?fiscal_yr=    the report: one row per chart-of-accounts code that carries approved budget
 *                                 (directly or through a descendant), plus the grand totals (the KPI cards).
 *                                 fiscal_yr omitted = every year combined.
 *
 * Like the COB, a user who belongs to an RC sees that RC and its descendants only; a superadmin / user without
 * an RC sees every RC (GlobalService::resolveBudgetReqRcFilter()).
 */

$BurService = new BurService();

/** The RC the caller is limited to ('' = every RC) — security, so it comes from the logged-in user. */
$burScopeRcId = static function ($request): string {
    $scope = GlobalService::getUserRcScope((int) $request->getAttribute('user_id'));

    return GlobalService::resolveBudgetReqRcFilter($scope, $request->getQueryParams());
};

$app->get('/bur/filters', function ($request, $response, $args) use ($BurService, $burScopeRcId) {
    $result = $BurService->filterOptions($burScopeRcId($request));

    return $response->withJson($result, $result['success'] ? 200 : 500);
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/bur/rollup', function ($request, $response, $args) use ($BurService, $burScopeRcId) {
    $params = $request->getQueryParams();
    $fiscalYr = isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null;

    $result = $BurService->fetchRollup($fiscalYr, $burScopeRcId($request));

    return $response->withJson($result, $result['success'] ? 200 : 500);
})->add($accessMiddleware)->add($jwtMiddleware);
