<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\PayrollManagementService;

/**
 * Payroll Management API — the page app/pages/payroll-page.php (import only; Payroll Setup is a separate module).
 *   GET    /payroll-management/list?year=&status=   summary rows per imported month + KPIs
 *   POST   /payroll-management/preview              multipart payroll_file + payroll_month + payroll_year → totals (nothing saved)
 *   POST   /payroll-management/import               same + replace=1 to replace that month's import
 *   DELETE /payroll-management/delete/{periodId}    remove a month's imported records (no active DV only)
 *   GET    /payroll-management/records/{periodId}   employees of an imported month (search, page, limit)
 *   GET    /payroll-management/record-items/{id}    one employee's amounts per payroll item
 *   GET    /payroll-management/ytd?year=&month=&department=&status=&search=   Year-to-Date report per employee
 *   GET    /payroll-management/template-columns     the columns the import recognises (from Payroll Setup)
 *   GET    /payroll-management/template             the actual import form (_shared/template/MONTHLY-PAYROLL-TEMPLATE.xlsx) without employee rows
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

$app->get('/payroll-management/records/{id:[0-9]+}', function ($request, $response, $args) use ($PayrollManagementService, $payrollMgmtJson) {
    try {
        return $payrollMgmtJson($response, $PayrollManagementService->fetchRecords((int) $args['id'], $request->getQueryParams()));
    } catch (\Exception $e) {
        LogError('GET /payroll-management/records ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/payroll-management/record-items/{id:[0-9]+}', function ($request, $response, $args) use ($PayrollManagementService, $payrollMgmtJson) {
    try {
        return $payrollMgmtJson($response, $PayrollManagementService->fetchRecordItems((int) $args['id']));
    } catch (\Exception $e) {
        LogError('GET /payroll-management/record-items ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/payroll-management/ytd', function ($request, $response, $args) use ($PayrollManagementService, $payrollMgmtJson) {
    try {
        return $payrollMgmtJson($response, $PayrollManagementService->fetchYtd($request->getQueryParams()));
    } catch (\Exception $e) {
        LogError('GET /payroll-management/ytd ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/payroll-management/template-columns', function ($request, $response, $args) use ($PayrollManagementService, $payrollMgmtJson) {
    try {
        return $payrollMgmtJson($response, $PayrollManagementService->fetchTemplateColumns());
    } catch (\Exception $e) {
        LogError('GET /payroll-management/template-columns ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/payroll-management/template', function ($request, $response, $args) use ($PayrollManagementService) {
    try {
        $bytes = $PayrollManagementService->buildTemplate();
        if ($bytes === null) {
            return $response->withJson(['success' => false, 'message' => 'Failed to build the payroll template.'], 500);
        }
        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('Content-Disposition', 'attachment; filename="MONTHLY-PAYROLL-TEMPLATE.xlsx"')
            ->withHeader('Cache-Control', 'no-store');
    } catch (\Exception $e) {
        LogError('GET /payroll-management/template ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// ── DV module: the DV form's "Payroll" card (app/pages/voucher/form.php) ──
// GET /payroll-management/available?dv_id=   imported months a DV can be prepared from
// GET /payroll-management/dv-data/{periodId} claimant, particulars, account, gross, deductions and accounting entry
$app->get('/payroll-management/available', function ($request, $response, $args) use ($PayrollManagementService, $payrollMgmtJson) {
    try {
        return $payrollMgmtJson($response, $PayrollManagementService->available((int) ($request->getQueryParams()['dv_id'] ?? 0)));
    } catch (\Exception $e) {
        LogError('GET /payroll-management/available ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/payroll-management/dv-data/{id:[0-9]+}', function ($request, $response, $args) use ($PayrollManagementService, $payrollMgmtJson) {
    try {
        return $payrollMgmtJson($response, $PayrollManagementService->dvData((int) $args['id']));
    } catch (\Exception $e) {
        LogError('GET /payroll-management/dv-data ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
