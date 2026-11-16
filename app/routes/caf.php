<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\CafService;

$CafService = new CafService();

/** Query-string filters shared by /all and /list: search (caf number), fiscal_year, allocation_id, document_type. */
$cafFilters = static function (array $p): array {
    return [
        'search' => (string) ($p['search'] ?? ''),
        'fiscal_year' => $p['fiscal_year'] ?? null,
        'allocation_id' => $p['allocation_id'] ?? null,
        'document_type' => (string) ($p['document_type'] ?? ''),
    ];
};

// Fetch all — no pagination (for dropdowns). Optional: the filters above
$app->get('/caf/all', function ($request, $response, $args) use ($CafService, $cafFilters) {
    try {
        $result = $CafService->fetchAlls(null, 10, $cafFilters($request->getQueryParams()));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /caf/all '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all (with pagination). Optional: page, limit + the filters above
$app->get('/caf/list', function ($request, $response, $args) use ($CafService, $cafFilters) {
    try {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $limit = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $CafService->fetchAlls($page, $limit, $cafFilters($params));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /caf/list '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one
$app->get('/caf/read/{id}', function ($request, $response, $args) use ($CafService) {
    try {
        $result = $CafService->fetchById($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 404);
    } catch (\Exception $e) {
        LogError('GET /caf/read '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Add — the creator (user_id) comes from the verified JWT, never from the body
$app->post('/caf/add', function ($request, $response, $args) use ($CafService) {
    try {
        $userId = $request->getAttribute('user_id');
        $result = $CafService->create(
            $request->getParsedBody() ?: [],
            $userId !== null ? (int) $userId : null
        );

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /caf/add '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update (PATCH — only the fields sent are changed)
$app->patch('/caf/update/{id}', function ($request, $response, $args) use ($CafService) {
    try {
        $result = $CafService->update($args['id'], $request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('PATCH /caf/update '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Delete (soft)
$app->delete('/caf/delete/{id}', function ($request, $response, $args) use ($CafService) {
    try {
        $result = $CafService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('DELETE /caf/delete '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
