<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\PayrollManagementService;

/**
 * Payroll Management API — the page app/pages/payroll-page.php (import only; Payroll Setup is a separate module).
 *   GET    /payroll-management/list?year=&status=   summary rows per imported month + KPIs
 *   POST   /payroll-management/preview              multipart payroll_file + payroll_month + payroll_year → totals (nothing saved)
 *   POST   /payroll-management/import               same + replace=1 to replace that month's import
 *   DELETE /payroll-management/delete/{periodId}    remove a month's imported records (no active DV only)
 */
$PayrollManagementService = new PayrollManagementService();

$payrollMgmtJson = function ($response, array $r) {
    return $response->withJson($r, $r['httpCode'] ?? ($r['success'] ? 200 : 400));
};

$app->get('/payroll-management/list', function ($request, $response, $args) use ($PayrollManagementService, $payrollMgmtJson) {
    try {
        return $payrollMgmtJson($response, $PayrollManagementService->fetchList($request->getQueryParams()));
    } catch (\Exception $e) {
        LogError('GET /payroll-management/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->post('/payroll-management/preview', function ($request, $response, $args) use ($PayrollManagementService, $payrollMgmtJson) {
    try {
        $files = $request->getUploadedFiles();

        return $payrollMgmtJson($response, $PayrollManagementService->preview($files['payroll_file'] ?? null, $request->getParsedBody() ?: []));
    } catch (\Exception $e) {
        LogError('POST /payroll-management/preview ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->post('/payroll-management/import', function ($request, $response, $args) use ($PayrollManagementService, $payrollMgmtJson) {
    try {
        $files = $request->getUploadedFiles();

        return $payrollMgmtJson($response, $PayrollManagementService->import($files['payroll_file'] ?? null, $request->getParsedBody() ?: [], (int) $request->getAttribute('user_id')));
    } catch (\Exception $e) {
        LogError('POST /payroll-management/import ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->delete('/payroll-management/delete/{id:[0-9]+}', function ($request, $response, $args) use ($PayrollManagementService, $payrollMgmtJson) {
    try {
        return $payrollMgmtJson($response, $PayrollManagementService->delete($args['id'], (int) $request->getAttribute('user_id')));
    } catch (\Exception $e) {
        LogError('DELETE /payroll-management/delete ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
