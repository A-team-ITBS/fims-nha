<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\CobReportService;
use PHPMaker2024\FIMS\Service\CobService;
use PHPMaker2024\FIMS\Service\GlobalService;

/*
 * Corporate Operating Budget (COB) — monitoring. Read-only, backs app/pages/cob-page.php.
 * Everything is built from the APPROVED budget requests (fims.budget_req and its programs / projects / line items /
 * attachments) — see CobService for what each figure means. No write routes: the COB is never edited, it is the result
 * of requests being approved.
 *
 *   GET /cob/filters                 the options of the filter drop-downs (values that exist in approved data)
 *   GET /cob/summary                 the KPI cards: counts + totals
 *   GET /cob/rollup?layout_by=       the tree tabs: region | fund_source | component | program. Every node carries the eight columns of the COB
 *                                    Monitoring sheet: approved_amount, revision_amount, revised_amount, cba_requested, available_amount,
 *                                    rfa_approved, laa_issued, remaining_balance
 *   GET /cob/read/{budget_reference_number}   one approved request in full, attachments included
 *   GET /cob/report                  the Generate Report dialog: layout_by, depth (0-4), unit (full|thousand|million|billion), format =
 *                                    json (the model + both previews) | pdf | xlsx (the file: { filename, mime, data: base64 }) — CobReportService
 *
 * Filters (all optional, same on summary / rollup / report): fiscal_yr, type_of_fund_id, component_id, program_code,
 * project_code, account_id, region_id ('central' = the central offices), search.
 *
 * Like the Budget Request list, a user who belongs to an RC sees that RC and its descendants only; a superadmin / user
 * without an RC sees every RC; the central review RC (FMD) sees every RC when the request carries central=1 — the server
 * checks the caller's own RC, so the parameter alone grants nothing (GlobalService::resolveBudgetReqRcFilter()).
 */

$CobService = new CobService();
$CobReportService = new CobReportService($CobService);

/** The RC the caller is limited to ('' = every RC) — security, so it comes from the logged-in user, never from a filter. */
$cobScopeRcId = static function ($request): string {
    $scope = GlobalService::getUserRcScope((int) $request->getAttribute('user_id'));

    return GlobalService::resolveBudgetReqRcFilter($scope, $request->getQueryParams());
};

/** One handler per read endpoint: runs $call, answers its JSON — 200 on success, the result's own httpCode (default 500) otherwise. */
$cobRespond = static function ($response, callable $call, string $what) {
    try {
        $result = $call();
        $code = $result['success'] ? 200 : ($result['httpCode'] ?? 500);
        unset($result['httpCode']);

        return $response->withJson($result, $code);
    } catch (\Throwable $e) {
        LogError("GET {$what} ".$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
};

$app->get('/cob/filters', function ($request, $response, $args) use ($CobService, $cobScopeRcId, $cobRespond) {
    return $cobRespond($response, fn () => $CobService->filterOptions($cobScopeRcId($request)), '/cob/filters');
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/cob/summary', function ($request, $response, $args) use ($CobService, $cobScopeRcId, $cobRespond) {
    return $cobRespond($response, fn () => $CobService->summary($request->getQueryParams(), $cobScopeRcId($request)), '/cob/summary');
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/cob/rollup', function ($request, $response, $args) use ($CobService, $cobScopeRcId, $cobRespond) {
    $params = $request->getQueryParams();
    $layoutBy = (string) ($params['layout_by'] ?? 'fund_source');
    if (!in_array($layoutBy, CobService::LAYOUTS, true)) {
        return $response->withJson(['success' => false, 'message' => 'layout_by must be one of: '.implode(', ', CobService::LAYOUTS)], 400);
    }

    return $cobRespond($response, fn () => $CobService->rollup($layoutBy, $params, $cobScopeRcId($request)), '/cob/rollup');
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/cob/read/{id}', function ($request, $response, $args) use ($CobService, $cobScopeRcId, $cobRespond) {
    return $cobRespond($response, fn () => $CobService->fetchById((string) $args['id'], $cobScopeRcId($request)), '/cob/read');
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/cob/report', function ($request, $response, $args) use ($CobReportService, $cobScopeRcId, $cobRespond) {
    $params = $request->getQueryParams();

    return $cobRespond($response, fn () => $CobReportService->report(
        (string) ($params['layout_by'] ?? 'fund_source'),
        (int) ($params['depth'] ?? 0),
        $params,
        $cobScopeRcId($request),
        (string) ($params['unit'] ?? 'full'),
        (string) ($params['format'] ?? 'json')
    ), '/cob/report');
})->add($accessMiddleware)->add($jwtMiddleware);
