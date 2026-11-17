<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\DeductionRemittanceService;

/**
 * Deductions & Remittances API — the page app/pages/remittance-page.php (Payroll menu "Remittance").
 *   GET  /deduction-remittance/list?year=&month=&agency=&status=   per month per agency + KPIs + what the user may do
 *   GET  /deduction-remittance/detail/{periodId}/{agency}          items and employees of one month + agency
 *   POST /deduction-remittance/schedule                            {payroll_period_id, agency, due_date, remarks} (Accounting)
 *   POST /deduction-remittance/{id}/unschedule                     SCHEDULED → CANCELLED (Accounting; no live remittance DV)
 *   POST /deduction-remittance/{id}/confirm                        {receipt_no, receipt_date} once its DV is released (Treasury / Accounting)
 *   GET  /deduction-remittance/dv-data/{id}                        DV form fill (Voucher?remittance={id}): claimant = agency,
 *                                                                  amount, entry Dr payables / gov't share, Cr Accounts Payable
 */
$DeductionRemittanceService = new DeductionRemittanceService();

$dedRemJson = function ($response, array $r) {
    return $response->withJson($r, $r['httpCode'] ?? ($r['success'] ? 200 : 400));
};

$app->get('/deduction-remittance/list', function ($request, $response, $args) use ($DeductionRemittanceService, $dedRemJson) {
    try {
        return $dedRemJson($response, $DeductionRemittanceService->fetchList($request->getQueryParams()));
    } catch (\Exception $e) {
        LogError('GET /deduction-remittance/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/deduction-remittance/detail/{periodId:[0-9]+}/{agency:[A-Za-z]+}', function ($request, $response, $args) use ($DeductionRemittanceService, $dedRemJson) {
    try {
        return $dedRemJson($response, $DeductionRemittanceService->fetchDetail((int) $args['periodId'], (string) $args['agency']));
    } catch (\Exception $e) {
        LogError('GET /deduction-remittance/detail ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/deduction-remittance/dv-data/{id:[0-9]+}', function ($request, $response, $args) use ($DeductionRemittanceService, $dedRemJson) {
    try {
        return $dedRemJson($response, $DeductionRemittanceService->dvData((int) $args['id']));
    } catch (\Exception $e) {
        LogError('GET /deduction-remittance/dv-data ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->post('/deduction-remittance/schedule', function ($request, $response, $args) use ($DeductionRemittanceService, $dedRemJson) {
    try {
        return $dedRemJson($response, $DeductionRemittanceService->schedule($request->getParsedBody() ?: [], (int) $request->getAttribute('user_id')));
    } catch (\Exception $e) {
        LogError('POST /deduction-remittance/schedule ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->post('/deduction-remittance/{id:[0-9]+}/{action:unschedule|confirm}', function ($request, $response, $args) use ($DeductionRemittanceService, $dedRemJson) {
    try {
        $id  = (int) $args['id'];
        $uid = (int) $request->getAttribute('user_id');
        $r   = $args['action'] === 'unschedule'
            ? $DeductionRemittanceService->unschedule($id, $uid)
            : $DeductionRemittanceService->confirm($id, $request->getParsedBody() ?: [], $uid);

        return $dedRemJson($response, $r);
    } catch (\Exception $e) {
        LogError('POST /deduction-remittance/' . ($args['action'] ?? '') . ' ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
