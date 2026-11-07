<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\ProgramService;

$ProgramService = new ProgramService();

// Fetch all — no pagination (for dropdowns)
$app->get('/program/all', function ($request, $response, $args) use ($ProgramService) {
    try {
        $params = $request->getQueryParams();
        $result = $ProgramService->fetchAlls(null, 10, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/program/list', function ($request, $response, $args) use ($ProgramService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $ProgramService->fetchAlls($page, $limit, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/program/read/{code}', function ($request, $response, $args) use ($ProgramService) {
    try {
        $result = $ProgramService->fetchByCode($args['code']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/program/add', function ($request, $response, $args) use ($ProgramService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ProgramService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/program/update/{code}', function ($request, $response, $args) use ($ProgramService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ProgramService->update($args['code'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/program/delete/{code}', function ($request, $response, $args) use ($ProgramService) {
    try {
        $result = $ProgramService->delete($args['code']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
