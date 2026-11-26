<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BudgetRevisionService;

$BudgetRevisionService = new BudgetRevisionService();

// A SUPPLEMENTAL request that has new supporting-document files is sent as multipart FormData: the normal body as a JSON string in the 'data' field, the files as
// doc_file_0, doc_file_1, … (one running index across every project) — the same convention the Budget Request form uses. Any other save is a plain JSON body.
$budgetRevisionParseBody = function ($request): array {
    $raw = $request->getParsedBody();
    if (is_array($raw) && isset($raw['data']) && is_string($raw['data'])) {
        return json_decode($raw['data'], true) ?? [];
    }

    return is_array($raw) ? $raw : [];
};

// Fetch all — no pagination
$app->get('/budget_revision/all', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $params = $request->getQueryParams();
        $result = $BudgetRevisionService->fetchAlls(null, 10, $params['fiscal_yr'] ?? '', $params['budget_reference_number'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/budget_revision/list', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $BudgetRevisionService->fetchAlls($page, $limit, $params['fiscal_yr'] ?? '', $params['budget_reference_number'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/budget_revision/read/{id}', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $result = $BudgetRevisionService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add — the service reads the current user itself (CurrentUserID()), not the form
$app->post('/budget_revision/add', function ($request, $response, $args) use ($BudgetRevisionService, $budgetRevisionParseBody) {
    try {
        $result = $BudgetRevisionService->create($budgetRevisionParseBody($request), $request->getUploadedFiles());

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update — PUT (plain JSON) or POST (multipart FormData with new supporting-document files: PHP only fills the uploaded files on a POST)
$app->map(['PUT', 'POST'], '/budget_revision/update/{id}', function ($request, $response, $args) use ($BudgetRevisionService, $budgetRevisionParseBody) {
    try {
        $result = $BudgetRevisionService->update($args['id'], $budgetRevisionParseBody($request), $request->getUploadedFiles());

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Removes one saved supporting document of a supplemental project (immediately, not with Save) — only while the request is still Saved.
$app->delete('/budget_revision/attachment/delete/{id}', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $result = $BudgetRevisionService->deleteSupplementalAttachment((int) $args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Process — submits a SAVED request for review (Saved -> Submitted); from then on it can no longer be edited. No body.
$app->put('/budget_revision/process/{id}', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $result = $BudgetRevisionService->process($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Review — a SUBMITTED request only. body: { "status": "APPROVED" | "DECLINED" }
$app->put('/budget_revision/review/{id}', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $body   = $request->getParsedBody();
        $result = $BudgetRevisionService->review($args['id'], (string) ($body['status'] ?? ''));

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete (soft)
$app->delete('/budget_revision/delete/{id}', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $result = $BudgetRevisionService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
