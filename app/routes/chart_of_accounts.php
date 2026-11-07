<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\ChartOfAccountsService;

$ChartOfAccountsService = new ChartOfAccountsService();

// Fetch all — no pagination (for dropdowns, e.g. parent account picker)
$app->get('/chart-of-accounts/all', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $params = $request->getQueryParams();
        $result = $ChartOfAccountsService->fetchAlls(
            null,
            10,
            $params['search'] ?? '',
            $params['account_type'] ?? '',
            $params['status'] ?? ''
        );

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/chart-of-accounts/list', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $ChartOfAccountsService->fetchAlls(
            $page,
            $limit,
            $params['search'] ?? '',
            $params['account_type'] ?? '',
            $params['status'] ?? ''
        );

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/chart-of-accounts/read/{id}', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $result = $ChartOfAccountsService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/chart-of-accounts/add', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ChartOfAccountsService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/chart-of-accounts/update/{id}', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ChartOfAccountsService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Toggle status (Active <-> Inactive)
$app->patch('/chart-of-accounts/{id}/toggle-status', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $result = $ChartOfAccountsService->toggleStatus($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/chart-of-accounts/delete/{id}', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $result = $ChartOfAccountsService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
