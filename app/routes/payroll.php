<?php
/**
 * Payroll API Routes
 *
 * Endpoints for payroll management:
 * - Employees (list, create, import CSV)
 * - Payroll periods (list, create)
 * - Payroll records (list, create, update, deductions)
 * - Remittances (list, create, track)
 */

namespace PHPMaker2024\FIMS;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

return function ($app) {
    $group = $app->group('/payroll', function ($group) use ($app) {
        $db = $app->getContainer()->get('db');

        // ════════════════════════════════════════════════════════════════
        // EMPLOYEES
        // ════════════════════════════════════════════════════════════════

        $group->get('/employees', function (Request $request, Response $response) use ($db) {
            try {
                $service = new PayrollEmployeeService($db);
                $filters = $request->getQueryParams();

                $employees = $service->listEmployees($filters);

                return jsonResponse($response, [
                    'success' => true,
                    'data' => $employees,
                    'count' => count($employees),
                ]);
            } catch (\Exception $e) {
                return jsonResponse($response, [
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        });

        $group->get('/employees/{id}', function (Request $request, Response $response, $args) use ($db) {
            try {
                $service = new PayrollEmployeeService($db);
                $employee = $service->getEmployee((int)$args['id']);

                if (!$employee) {
                    return jsonResponse($response, [
                        'success' => false,
                        'message' => 'Employee not found.',
                    ], 404);
                }

                return jsonResponse($response, [
                    'success' => true,
                    'data' => $employee,
                ]);
            } catch (\Exception $e) {
                return jsonResponse($response, [
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        });

        $group->post('/employees', function (Request $request, Response $response) use ($db) {
            try {
                $data = $request->getParsedBody();
                $userId = getCurrentUserId($request);

                $service = new PayrollEmployeeService($db);
                $data['created_by'] = $userId;
                $data['updated_by'] = $userId;

                $service->saveEmployee($data);

                return jsonResponse($response, [
                    'success' => true,
                    'message' => 'Employee saved successfully.',
                ]);
            } catch (\Exception $e) {
                return jsonResponse($response, [
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
        });

        $group->post('/employees/import', function (Request $request, Response $response) use ($db) {
            try {
                $uploadedFiles = $request->getUploadedFiles();
                $userId = getCurrentUserId($request);

                if (empty($uploadedFiles['file'])) {
                    return jsonResponse($response, [
                        'success' => false,
                        'message' => 'No file uploaded.',
                    ], 400);
                }

                $file = $uploadedFiles['file'];
                if ($file->getError() !== UPLOAD_ERR_OK) {
                    return jsonResponse($response, [
                        'success' => false,
                        'message' => 'File upload error.',
                    ], 400);
                }

                $tmpPath = sys_get_temp_dir() . '/' . uniqid('payroll_import_');
                $file->moveTo($tmpPath);

                $service = new PayrollEmployeeService($db);
                $result = $service->importFromCsv($tmpPath, $userId);

                unlink($tmpPath);

                return jsonResponse($response, [
                    'success' => true,
                    'message' => "Import complete: {$result['imported']} new, {$result['updated']} updated.",
                    'data' => $result,
                ]);
            } catch (\Exception $e) {
                return jsonResponse($response, [
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
        });

        // ════════════════════════════════════════════════════════════════
        // PAYROLL PERIODS
        // ════════════════════════════════════════════════════════════════

        $group->get('/periods', function (Request $request, Response $response) use ($db) {
            try {
                $periods = $db->fetchAllAssociative(
                    'SELECT * FROM fims.payroll_periods ORDER BY fiscal_yr DESC, payroll_month ASC'
                );

                return jsonResponse($response, [
                    'success' => true,
                    'data' => $periods,
                ]);
            } catch (\Exception $e) {
                return jsonResponse($response, [
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        });

        $group->post('/periods', function (Request $request, Response $response) use ($db) {
            try {
                $data = $request->getParsedBody();
                $userId = getCurrentUserId($request);

                $data['created_by'] = $userId;
                $data['updated_by'] = $userId;

                $db->insert('fims.payroll_periods', $data);

                return jsonResponse($response, [
                    'success' => true,
                    'message' => 'Payroll period created.',
                ]);
            } catch (\Exception $e) {
                return jsonResponse($response, [
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
        });

        // ════════════════════════════════════════════════════════════════
        // PAYROLL RECORDS
        // ════════════════════════════════════════════════════════════════

        $group->get('/records/{periodId}', function (Request $request, Response $response, $args) use ($db) {
            try {
                $service = new PayrollRecordService($db);
                $filters = $request->getQueryParams();

                $records = $service->listRecordsByPeriod((int)$args['periodId'], $filters);

                return jsonResponse($response, [
                    'success' => true,
                    'data' => $records,
                    'count' => count($records),
                ]);
            } catch (\Exception $e) {
                return jsonResponse($response, [
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        });

        $group->get('/records/{recordId}/detail', function (Request $request, Response $response, $args) use ($db) {
            try {
                $service = new PayrollRecordService($db);
                $record = $service->getRecord((int)$args['recordId']);

                if (!$record) {
                    return jsonResponse($response, [
                        'success' => false,
                        'message' => 'Payroll record not found.',
                    ], 404);
                }

                return jsonResponse($response, [
                    'success' => true,
                    'data' => $record,
                ]);
            } catch (\Exception $e) {
                return jsonResponse($response, [
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        });

        // ════════════════════════════════════════════════════════════════
        // DEDUCTIONS
        // ════════════════════════════════════════════════════════════════

        $group->get('/deduction-types', function (Request $request, Response $response) use ($db) {
            try {
                $service = new PayrollDeductionService($db);
                $types = $service->listDeductionTypes(true);

                return jsonResponse($response, [
                    'success' => true,
                    'data' => $types,
                ]);
            } catch (\Exception $e) {
                return jsonResponse($response, [
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }
        });

    });
};

// ────────────────────────────────────────────────────────────────
// Helper Functions
// ────────────────────────────────────────────────────────────────

function jsonResponse($response, $data, $statusCode = 200)
{
    $response = $response->withStatus($statusCode);
    $response = $response->withHeader('Content-Type', 'application/json');
    $response->getBody()->write(json_encode($data));
    return $response;
}

function getCurrentUserId($request)
{
    return $_SESSION['login_user_id'] ?? 1;
}
