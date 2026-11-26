<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\GlobalService;
use PHPMaker2024\FIMS\Service\ProjectService;

$ProjectService = new ProjectService();

// Preview the next auto-generated project code (GlobalService::generateProjectCode)
// for a given Program/Sub-Program — used by the Budget Request form's Project
// picker when the typed name doesn't match an existing project.
$app->get('/project/next-code', function ($request, $response, $args) {
    try {
        $params         = $request->getQueryParams();
        $programCode    = $params['program_code'] ?? '';
        $subProgramCode = $params['sub_program_code'] ?? '';
        $fiscalYr       = isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null;
        // The Region manually picked on the Budget Request form's Project field (optional) — when
        // given, the previewed code's own RC segment matches it instead of the caller's own RC (see
        // GlobalService::generateProjectCode()'s own doc comment for why those can now differ).
        $rcId           = (string) ($params['rc_id'] ?? '');
        if ($programCode === '') {
            return $response->withJson(['success' => false, 'message' => 'program_code is required.'], 400);
        }

        $code = GlobalService::generateProjectCode($programCode, $subProgramCode, (int) $request->getAttribute('user_id'), $fiscalYr, $rcId);

        return $response->withJson(['success' => true, 'data' => ['project_code' => $code]]);
    } catch (\InvalidArgumentException $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
        LogError('GET /project/next-code ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not generate a project code.'], 500);
    }
})->add($jwtMiddleware);

// Resolves the optional `scope=mine` query param into the calling user's own rc_id
// (uac.users.rc_id) — opt-in, so only a caller that asks for it (the CBA Funding
// Allocations project search in budget-allocation-form.php) is scoped; every other
// /project/all or /project/list caller (the CFA Program/Sub-Program/Project chain,
// the Purchase Order form's project picker, the Budget Request form's typeahead)
// keeps seeing every project, unchanged. '' (no filter) when scope isn't "mine" or
// the calling user has no resolvable rc_id.
function project_resolveMineRcId($request, array $params): string
{
    if (($params['scope'] ?? '') !== 'mine') {
        return '';
    }
    try {
        return (string) (GlobalService::getUserRcId((int) $request->getAttribute('user_id')) ?? '');
    } catch (\InvalidArgumentException $e) {
        return '';
    }
}

// Fetch all — no pagination (for dropdowns)
$app->get('/project/all', function ($request, $response, $args) use ($ProjectService) {
    try {
        $params = $request->getQueryParams();
        $rcId   = project_resolveMineRcId($request, $params);
        $result = $ProjectService->fetchAlls(null, 10, $params['search'] ?? '', $params['program_code'] ?? '', $params['sub_program_code'] ?? '', $rcId);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/project/list', function ($request, $response, $args) use ($ProjectService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;
        $rcId   = project_resolveMineRcId($request, $params);

        $result = $ProjectService->fetchAlls($page, $limit, $params['search'] ?? '', $params['program_code'] ?? '', $params['sub_program_code'] ?? '', $rcId);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/project/read/{code}', function ($request, $response, $args) use ($ProjectService) {
    try {
        $result = $ProjectService->fetchByCode($args['code']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/project/add', function ($request, $response, $args) use ($ProjectService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ProjectService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/project/update/{code}', function ($request, $response, $args) use ($ProjectService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ProjectService->update($args['code'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/project/delete/{code}', function ($request, $response, $args) use ($ProjectService) {
    try {
        $result = $ProjectService->delete($args['code']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
