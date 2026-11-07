<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\ChartOfAccountsService;

$ChartOfAccountsService = new ChartOfAccountsService();

// Fetch all — no pagination (for dropdowns); ?prefix=5-01 scopes to postable
// accounts under that chart-of-accounts branch.
$app->get('/chart_of_accounts/all', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $params = $request->getQueryParams();
        $prefix = $params['prefix'] ?? '';

        $result = $prefix !== ''
            ? $ChartOfAccountsService->fetchByPrefix($prefix)
            : $ChartOfAccountsService->fetchAlls(null, 10, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/chart_of_accounts/list', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $ChartOfAccountsService->fetchAlls($page, $limit, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/chart_of_accounts/read/{id}', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $result = $ChartOfAccountsService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
