<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\CollectingOfficerService;

// Backs the "Collecting Officers" tab on accountable-officer-page.php —
// see CollectingOfficerService's docblock for why this is a separate
// table/service from Accountable Officers rather than a shared one.
$CollectingOfficerService = new CollectingOfficerService();

// Fetch all — no pagination (for dropdowns). Optional: search
$app->get('/collecting-officers/all', function ($request, $response, $args) use ($CollectingOfficerService) {
    try {
        $p      = $request->getQueryParams();
        $result = $CollectingOfficerService->fetchAlls(null, 10, (string) ($p['search'] ?? ''));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /collecting-officers/all ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all (with pagination + summary). Optional: page, limit, search, status (ACTIVE|INACTIVE)
$app->get('/collecting-officers/list', function ($request, $response, $args) use ($CollectingOfficerService) {
    try {
        $p      = $request->getQueryParams();
        $result = $CollectingOfficerService->fetchAlls(
            isset($p['page']) ? (int) $p['page'] : 1,
            isset($p['limit']) ? (int) $p['limit'] : 10,
            (string) ($p['search'] ?? ''),
            (string) ($p['status'] ?? '')
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /collecting-officers/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one
$app->get('/collecting-officers/read/{id}', function ($request, $response, $args) use ($CollectingOfficerService) {
    try {
        $result = $CollectingOfficerService->fetchById($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 404);
    } catch (\Exception $e) {
        LogError('GET /collecting-officers/read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Add
$app->post('/collecting-officers/add', function ($request, $response, $args) use ($CollectingOfficerService) {
    try {
        $result = $CollectingOfficerService->create($request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /collecting-officers/add ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update
$app->put('/collecting-officers/update/{id}', function ($request, $response, $args) use ($CollectingOfficerService) {
    try {
        $result = $CollectingOfficerService->update($args['id'], $request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('PUT /collecting-officers/update ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
