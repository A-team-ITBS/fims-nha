<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\DeductionRemittanceService;

/**
 * Deductions & Remittances API — the page app/pages/deduction-remittance-page.php (Payroll section).
 *   GET  /deduction-remittance/list?year=&month=&agency=&status=   per month per agency + KPIs + what the user may do
 *   GET  /deduction-remittance/detail/{periodId}/{agency}          items and employees of one month + agency
 *   POST /deduction-remittance/schedule                            {payroll_period_id, agency, due_date, remarks} (Accounting)
 *   POST /deduction-remittance/{id}/unschedule                     SCHEDULED → CANCELLED (Accounting)
 *   POST /deduction-remittance/{id}/remit                          {remittance_date, remittance_method, remittance_no} (Treasury)
 *   POST /deduction-remittance/{id}/confirm                        {receipt_no, receipt_date} (Treasury / Accounting)
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

$app->post('/deduction-remittance/schedule', function ($request, $response, $args) use ($DeductionRemittanceService, $dedRemJson) {
    try {
        return $dedRemJson($response, $DeductionRemittanceService->schedule($request->getParsedBody() ?: [], (int) $request->getAttribute('user_id')));
    } catch (\Exception $e) {
        LogError('POST /deduction-remittance/schedule ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->post('/deduction-remittance/{id:[0-9]+}/{action:unschedule|remit|confirm}', function ($request, $response, $args) use ($DeductionRemittanceService, $dedRemJson) {
    try {
        $id   = (int) $args['id'];
        $uid  = (int) $request->getAttribute('user_id');
        $body = $request->getParsedBody() ?: [];
        $r    = match ($args['action']) {
            'unschedule' => $DeductionRemittanceService->unschedule($id, $uid),
            'remit'      => $DeductionRemittanceService->remit($id, $body, $uid),
            'confirm'    => $DeductionRemittanceService->confirm($id, $body, $uid),
        };

        return $dedRemJson($response, $r);
    } catch (\Exception $e) {
        LogError('POST /deduction-remittance/' . ($args['action'] ?? '') . ' ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
