<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\ProgramSourceService;

$ProgramSourceService = new ProgramSourceService();

// Fetch all — no pagination (for dropdowns)
$app->get('/program_source/all', function ($request, $response, $args) use ($ProgramSourceService) {
    try {
        $params = $request->getQueryParams();
        $result = $ProgramSourceService->fetchAlls(null, 10, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/program_source/list', function ($request, $response, $args) use ($ProgramSourceService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $ProgramSourceService->fetchAlls($page, $limit, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/program_source/read/{id}', function ($request, $response, $args) use ($ProgramSourceService) {
    try {
        $result = $ProgramSourceService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/program_source/add', function ($request, $response, $args) use ($ProgramSourceService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ProgramSourceService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/program_source/update/{id}', function ($request, $response, $args) use ($ProgramSourceService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ProgramSourceService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete (soft)
$app->delete('/program_source/delete/{id}', function ($request, $response, $args) use ($ProgramSourceService) {
    try {
        $result = $ProgramSourceService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
