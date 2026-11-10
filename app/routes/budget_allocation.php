<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BudgetAllocationService;

$BudgetAllocationService = new BudgetAllocationService();

/**
 * Query-string filters shared by /all and /list.
 * search, fiscal_year, status_id, allocation_type, exclude_id
 */
$budgetAllocationFilters = static function (array $p): array {
    return [
        'search'          => (string) ($p['search'] ?? ''),
        'fiscal_year'     => $p['fiscal_year'] ?? null,
        'status_id'       => $p['status_id'] ?? null,
        'allocation_type' => (string) ($p['allocation_type'] ?? ''),
        'exclude_id'      => $p['exclude_id'] ?? null,
    ];
};

// Departments for the Requesting Department / District pickers
$app->get('/budget_allocation/departments', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $result = $BudgetAllocationService->fetchDepartments();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_allocation/departments ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Statuses defined for memo requests (Status filter / form select)
$app->get('/budget_allocation/statuses', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $result = $BudgetAllocationService->fetchStatuses();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_allocation/statuses ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all — no pagination (for dropdowns, e.g. the Parent picker)
$app->get('/budget_allocation/all', function ($request, $response, $args) use ($BudgetAllocationService, $budgetAllocationFilters) {
    try {
        $result = $BudgetAllocationService->fetchAlls(null, 10, $budgetAllocationFilters($request->getQueryParams()));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_allocation/all ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all (with pagination + summary). Optional: page, limit + the filters above
$app->get('/budget_allocation/list', function ($request, $response, $args) use ($BudgetAllocationService, $budgetAllocationFilters) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $BudgetAllocationService->fetchAlls($page, $limit, $budgetAllocationFilters($params));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_allocation/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one
$app->get('/budget_allocation/read/{id}', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $result = $BudgetAllocationService->fetchById($args['id'], true);   // + file_attachments[]

        return $response->withJson($result, $result['success'] ? 200 : 404);
    } catch (\Exception $e) {
        LogError('GET /budget_allocation/read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Add — the creator (user_id) comes from the verified JWT, never from the body
$app->post('/budget_allocation/add', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $userId = $request->getAttribute('user_id');
        $result = $BudgetAllocationService->create(
            $request->getParsedBody() ?: [],
            $userId !== null ? (int) $userId : null
        );

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /budget_allocation/add ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update (PATCH — only the fields sent are changed)
$app->patch('/budget_allocation/update/{id}', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $result = $BudgetAllocationService->update($args['id'], $request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('PATCH /budget_allocation/update ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Delete (soft)
$app->delete('/budget_allocation/delete/{id}', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $result = $BudgetAllocationService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('DELETE /budget_allocation/delete ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
