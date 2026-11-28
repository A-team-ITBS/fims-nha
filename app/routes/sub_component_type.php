<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\SubComponentTypeService;

$SubComponentTypeService = new SubComponentTypeService();

// Fetch all — no pagination (for dropdowns). ?component=Other-Capital Outlay|Capital Outlay narrows
// the list to that Component's own bucket — see SubComponentTypeService::fetchAlls()'s own doc comment.
$app->get('/sub_component_type/all', function ($request, $response, $args) use ($SubComponentTypeService) {
    try {
        $params = $request->getQueryParams();
        $result = $SubComponentTypeService->fetchAlls(null, 10, $params['search'] ?? '', $params['component'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination) — same ?component= filter as /all above.
$app->get('/sub_component_type/list', function ($request, $response, $args) use ($SubComponentTypeService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $SubComponentTypeService->fetchAlls($page, $limit, $params['search'] ?? '', $params['component'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/sub_component_type/read/{id}', function ($request, $response, $args) use ($SubComponentTypeService) {
    try {
        $result = $SubComponentTypeService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/sub_component_type/add', function ($request, $response, $args) use ($SubComponentTypeService) {
    try {
        $body   = $request->getParsedBody();
        $result = $SubComponentTypeService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/sub_component_type/update/{id}', function ($request, $response, $args) use ($SubComponentTypeService) {
    try {
        $body   = $request->getParsedBody();
        $result = $SubComponentTypeService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete (soft)
$app->delete('/sub_component_type/delete/{id}', function ($request, $response, $args) use ($SubComponentTypeService) {
    try {
        $result = $SubComponentTypeService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
