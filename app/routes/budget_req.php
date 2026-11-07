<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BudgetReqService;

$BudgetReqService = new BudgetReqService();

// Fetch all — no pagination
$app->get('/budget_req/all', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $params = $request->getQueryParams();
        $result = $BudgetReqService->fetchAlls(null, 10, $params['fiscal_yr'] ?? '', $params['type'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/budget_req/list', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $BudgetReqService->fetchAlls($page, $limit, $params['fiscal_yr'] ?? '', $params['type'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/budget_req/read/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $result = $BudgetReqService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/budget_req/add', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $body   = $request->getParsedBody();
        $result = $BudgetReqService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Create a PS/MOOE header + its line items together —
// body: { fiscal_yr, expense_class_id, line_items: [{ account_id, requested_amount, particulars }, ...] }
$app->post('/budget_req/add-with-items', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $body      = $request->getParsedBody();
        $lineItems = $body['line_items'] ?? [];

        if (!is_array($lineItems) || empty($lineItems)) {
            return $response->withJson(['success' => false, 'message' => 'line_items is required and must be a non-empty array.'], 400);
        }

        $result = $BudgetReqService->createWithLineItems($body, $lineItems);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/budget_req/update/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $body   = $request->getParsedBody();
        $result = $BudgetReqService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/budget_req/delete/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $result = $BudgetReqService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
