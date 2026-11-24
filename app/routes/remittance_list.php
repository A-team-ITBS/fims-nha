<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\RemittanceListService;

/**
 * Remittance List (Deductions) API — the page app/pages/remittance-list-page.php (Reports menu). "Remit — create DV" opens the
 * new-DV form (the Voucher page's own, shown on the Remittance List page) filled with dv-data below; saving that form creates the DV
 * through DisbursementVoucherService::create(), which links the month's vouchers.
 *   GET /remittance-list/by-dv?page=&limit=&fiscal_yr=&type=&agency=&status=&search=  one page of VOUCHERS, each with its matching lines + totals
 *   GET /remittance-list/list?page=&limit=&fiscal_yr=&type=&agency=&status=&search=   one page of withheld lines (flat) + totals
 *   GET /remittance-list/all?fiscal_yr=&type=&agency=&status=&search=                 every matching line (the page's CSV export)
 *   GET /remittance-list/months?fiscal_yr=                                             per month + BIR tax: withheld / ready to remit / in a Remittance DV / remitted
 *   GET /remittance-list/dv-data/{YYYY-MM}/{EWT|VAT|PENALTY_TAX}                      Remittance DV form fill for that month's withholding
 * See RemittanceListService for what is listed and when a voucher's deductions appear.
 */
$RemittanceListService = new RemittanceListService();
$remittanceListKeys    = ['fiscal_yr', 'type', 'agency', 'status', 'search'];

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

// The "Pay to BIR by month" panel: per month and BIR tax, what is withheld / ready to remit / in a Remittance DV / remitted
$app->get('/remittance-list/months', function ($request, $response, $args) use ($RemittanceListService) {
    try {
        $result = $RemittanceListService->fetchMonths(array_intersect_key($request->getQueryParams(), ['fiscal_yr' => 1]));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (Exception $e) {
        LogError('GET /remittance-list/months ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// DV form fill for a month's Remittance DV (Voucher?remit_period=YYYY-MM&remit_type={type}): claimant = BIR, the month's total, breakdown by rate, entry Dr liability / Cr Cash in Bank
$app->get('/remittance-list/dv-data/{period:[0-9]{4}-[0-9]{2}}/{type:[A-Za-z_]+}', function ($request, $response, $args) use ($RemittanceListService) {
    try {
        $result = $RemittanceListService->dvData((string) $args['period'], (string) $args['type'], isset($request->getQueryParams()['vendor']) ? (string) $request->getQueryParams()['vendor'] : null);

        return $response->withJson($result, $result['success'] ? 200 : 400);
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
