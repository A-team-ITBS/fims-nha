<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BudgetReqService;

$BudgetReqService = new BudgetReqService();

// Fetch all — no pagination
$app->get('/budget_req/all', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $params = $request->getQueryParams();
        $result = $BudgetReqService->fetchAlls(null, 10, $params['fiscal_yr'] ?? '', $params['program_code'] ?? '');

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

        $result = $BudgetReqService->fetchAlls($page, $limit, $params['fiscal_yr'] ?? '', $params['program_code'] ?? '');

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

// Add one Program (+ its project blocks + line items) to the budget
// request for the given fiscal year — creates that request (status Draft)
// if it doesn't exist yet, or appends to it if it does and is still Draft.
// Rejected if that fiscal year's request has moved past Draft.
// body: { fiscal_yr, program_code, sub_program_code, projects: [ { project_code, line_items: [{ account_id, particulars, proposed_amount }, ...] }, ... ] }
$app->post('/budget_req/add', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $body     = $request->getParsedBody();
        $projects = $body['projects'] ?? [];

        if (!is_array($projects) || empty($projects)) {
            return $response->withJson(['success' => false, 'message' => 'projects is required and must be a non-empty array.'], 400);
        }

        $result = $BudgetReqService->addProgram($body, $projects);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update one Program's project blocks + line items (fully replaced).
// Sibling programs under the same budget request are untouched. Rejected
// once that request has moved past Draft.
// body: { program_code, sub_program_code, projects: [...] }
$app->put('/budget_req/program/update/{budget_req_program_id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $body     = $request->getParsedBody();
        $projects = $body['projects'] ?? [];

        if (!is_array($projects) || empty($projects)) {
            return $response->withJson(['success' => false, 'message' => 'projects is required and must be a non-empty array.'], 400);
        }

        $result = $BudgetReqService->updateProgram($args['budget_req_program_id'], $body, $projects);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Transition a Draft budget request to "For Review" — locks it from
// further program additions/edits.
$app->put('/budget_req/submit-for-review/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $result = $BudgetReqService->submitForReview($args['id']);

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
