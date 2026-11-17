<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\AccountableOfficerService;

$AccountableOfficerService = new AccountableOfficerService();

// Fetch all — no pagination (for dropdowns). Optional: search
$app->get('/accountable-officers/all', function ($request, $response, $args) use ($AccountableOfficerService) {
    try {
        $p      = $request->getQueryParams();
        $result = $AccountableOfficerService->fetchAlls(null, 10, (string) ($p['search'] ?? ''));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /accountable-officers/all ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all (with pagination + summary). Optional: page, limit, search, status (ACTIVE|INACTIVE)
$app->get('/accountable-officers/list', function ($request, $response, $args) use ($AccountableOfficerService) {
    try {
        $p      = $request->getQueryParams();
        $result = $AccountableOfficerService->fetchAlls(
            isset($p['page']) ? (int) $p['page'] : 1,
            isset($p['limit']) ? (int) $p['limit'] : 10,
            (string) ($p['search'] ?? ''),
            (string) ($p['status'] ?? '')
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /accountable-officers/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one
$app->get('/accountable-officers/read/{id}', function ($request, $response, $args) use ($AccountableOfficerService) {
    try {
        $result = $AccountableOfficerService->fetchById($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 404);
    } catch (\Exception $e) {
        LogError('GET /accountable-officers/read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Add
$app->post('/accountable-officers/add', function ($request, $response, $args) use ($AccountableOfficerService) {
    try {
        $result = $AccountableOfficerService->create($request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /accountable-officers/add ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update
$app->put('/accountable-officers/update/{id}', function ($request, $response, $args) use ($AccountableOfficerService) {
    try {
        $result = $AccountableOfficerService->update($args['id'], $request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('PUT /accountable-officers/update ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
