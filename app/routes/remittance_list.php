<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\RemittanceListService;

/**
 * Remittance List (Deductions) API — the page app/pages/remittance-list-page.php (Reports menu). The page has a month filter, a summary of the
 * month's remittance and ONE "Create DV" button: it opens the new-DV form (the Voucher page's own, shown on the Remittance List page) filled
 * with dv-data below — every BIR tax (EWT · Withholding VAT · Penalty Tax) of every vendor of the month in the one DV; saving that form creates
 * the DV through DisbursementVoucherService::create(), which links the month's vouchers.
 *   GET /remittance-list/by-dv?page=&limit=&fiscal_yr=&period=&type=&agency=&status=&search=  one page of VOUCHERS, each with its matching lines + totals
 *   GET /remittance-list/list?page=&limit=&fiscal_yr=&period=&type=&agency=&status=&search=   one page of withheld lines (flat) + totals
 *   GET /remittance-list/all?fiscal_yr=&period=&type=&agency=&status=&search=                 every matching line (the page's CSV export)
 *   GET /remittance-list/periods?fiscal_yr=                                                     the months that have BIR tax withheld (the month filter's options)
 *   GET /remittance-list/summary?fiscal_yr=&period=                                             the month's remittance: per BIR tax (and the rates of what is ready) — withheld / ready to remit / in a Remittance DV / remitted — and its DVs
 *   GET /remittance-list/tax-summary?period=YYYY-MM                                             the "Summary of Tax Deductions" of ONE month in detail: totals, per tax and ATC code, per vendor (with TIN), and the Remittance DVs already made
 *   GET /remittance-list/taxes?fiscal_yr=&period=&scope={OPEN|REMITTED}                         the month's tax lines for the page's two tabs: OPEN = what the next Create DV pays, REMITTED = already in a Remittance DV
 *   GET /remittance-list/dv-data/{YYYY-MM}                                                      Remittance DV form fill for that month's withholding (all taxes, all vendors)
 * period = 'YYYY-MM' (the voucher's DV date). See RemittanceListService for what is listed and when a voucher's deductions appear.
 */
$RemittanceListService = new RemittanceListService();
$remittanceListKeys    = ['fiscal_yr', 'period', 'type', 'agency', 'status', 'search'];

$app->get('/remittance-list/list', function ($request, $response, $args) use ($RemittanceListService, $remittanceListKeys) {
    try {
        $params  = $request->getQueryParams();
        $filters = array_intersect_key($params, array_flip($remittanceListKeys));
        $result  = $RemittanceListService->fetchAlls(
            isset($params['page']) ? (int) $params['page'] : 1,
            isset($params['limit']) ? (int) $params['limit'] : 15,
            $filters
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /remittance-list/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// One entry per voucher, its matching deduction / ATC lines under `items` (the page's default "By voucher" view)
$app->get('/remittance-list/by-dv', function ($request, $response, $args) use ($RemittanceListService, $remittanceListKeys) {
    try {
        $params  = $request->getQueryParams();
        $filters = array_intersect_key($params, array_flip($remittanceListKeys));
        $result  = $RemittanceListService->fetchByDv(
            isset($params['page']) ? (int) $params['page'] : 1,
            isset($params['limit']) ? (int) $params['limit'] : 15,
            $filters
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /remittance-list/by-dv ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// The month filter's options: every month that has deductions, newest first, with what was withheld and what is ready to remit
$app->get('/remittance-list/periods', function ($request, $response, $args) use ($RemittanceListService) {
    try {
        $result = $RemittanceListService->fetchPeriods(array_intersect_key($request->getQueryParams(), ['fiscal_yr' => 1]));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /remittance-list/periods ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// The summary of the remittance for the chosen month (none = every month): per BIR tax and rate, where it stands, and the Remittance DVs already made
$app->get('/remittance-list/summary', function ($request, $response, $args) use ($RemittanceListService) {
    try {
        $result = $RemittanceListService->fetchSummary(array_intersect_key($request->getQueryParams(), ['fiscal_yr' => 1, 'period' => 1]));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /remittance-list/summary ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// The page's "Summary of Tax Deductions": per month of a year, what each BIR tax withheld and how much of it is remitted / ready / not released yet
$app->get('/remittance-list/tax-summary', function ($request, $response, $args) use ($RemittanceListService) {
    try {
        $result = $RemittanceListService->fetchMonthDetail((string) ($request->getQueryParams()['period'] ?? ''));

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /remittance-list/tax-summary ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// The tax lines of the month for the page's "To remit" (scope OPEN) and "Remitted" (scope REMITTED) tabs
$app->get('/remittance-list/taxes', function ($request, $response, $args) use ($RemittanceListService) {
    try {
        $result = $RemittanceListService->fetchTaxLines(array_intersect_key($request->getQueryParams(), ['fiscal_yr' => 1, 'period' => 1, 'scope' => 1]));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /remittance-list/taxes ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// DV form fill for a month's Remittance DV (Voucher?remit_period=YYYY-MM): claimant = BIR, the month's total, breakdown by tax and rate, entry Dr liabilities / Cr Cash in Bank
$app->get('/remittance-list/dv-data/{period:[0-9]{4}-[0-9]{2}}', function ($request, $response, $args) use ($RemittanceListService) {
    try {
        $result = $RemittanceListService->dvData((string) $args['period']);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));   // 403 for anyone but Accounting
    } catch (\Exception $e) {
        LogError('GET /remittance-list/dv-data ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/remittance-list/all', function ($request, $response, $args) use ($RemittanceListService, $remittanceListKeys) {
    try {
        $filters = array_intersect_key($request->getQueryParams(), array_flip($remittanceListKeys));
        $result  = $RemittanceListService->fetchAlls(null, 15, $filters);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /remittance-list/all ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
