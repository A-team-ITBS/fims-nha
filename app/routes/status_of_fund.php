<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\StatusOfFundService;

/*
 * Status of Funds — monitoring. Read-only, backs app/pages/status-of-fund-page.php (sidebar Registry → Status of Funds).
 * A fund source is a group of fims.fund_tracking records (NG Subsidy, Trust Funds, Supplemental Funds) — see StatusOfFundService
 * for what each figure means.
 *
 *   GET /status_of_fund/overview?fiscal_year=      one summary row per fund source + totals + the fiscal years on offer
 *   GET /status_of_fund/source/{key}?fiscal_year=  one fund source in full (key: NG | TRUST | SUPP): its figures, the per-program
 *                                                  split (NG / SUPP) and the per-trust-fund split (TRUST). 404 for another key
 *
 * fiscal_year omitted = every year combined.
 */

$StatusOfFundService = new StatusOfFundService();

$sofYear = static function ($request): ?int {
    $v = $request->getQueryParams()['fiscal_year'] ?? '';

    return $v !== '' && is_numeric($v) ? (int) $v : null;
};

$app->get('/status_of_fund/overview', function ($request, $response, $args) use ($StatusOfFundService, $sofYear) {
    $result = $StatusOfFundService->fetchOverview($sofYear($request));

    return $response->withJson($result, $result['success'] ? 200 : 500);
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/status_of_fund/source/{key}', function ($request, $response, $args) use ($StatusOfFundService, $sofYear) {
    $result = $StatusOfFundService->fetchSource((string) $args['key'], $sofYear($request));

    return $response->withJson($result, $result['success'] ? 200 : ($result['status'] ?? 500));
})->add($accessMiddleware)->add($jwtMiddleware);
