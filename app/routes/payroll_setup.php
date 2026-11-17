<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\PayrollSetupService;

/**
 * Payroll Setup API — the only API the Payroll Setup page (app/pages/payroll-setup-page.php) calls.
 * Items, the account list for the Account picker, and new sub-ledger accounts (created together with the item).
 */
$PayrollSetupService = new PayrollSetupService();

// Active chart accounts for the Account picker: account_code, account_title, posting_allowed (no pagination)
$app->get('/payroll-setup/accounts', function ($request, $response, $args) use ($PayrollSetupService) {
    try {
        $result = $PayrollSetupService->fetchAccounts();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /payroll-setup/accounts ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all (with pagination + KPI summary). Optional: page, limit, search, category, status, account (with|none)
// `all=1` returns every match without pagination.
$app->get('/payroll-setup/list', function ($request, $response, $args) use ($PayrollSetupService) {
    try {
        $p      = $request->getQueryParams();
        $all    = !empty($p['all']);
        $result = $PayrollSetupService->fetchAlls(
            $all ? null : (isset($p['page']) ? (int) $p['page'] : 1),
            isset($p['limit']) ? (int) $p['limit'] : 10,
            (string) ($p['search'] ?? ''),
            (string) ($p['category'] ?? ''),
            (string) ($p['status'] ?? ''),
            (string) ($p['account'] ?? '')
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /payroll-setup/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one
$app->get('/payroll-setup/read/{id}', function ($request, $response, $args) use ($PayrollSetupService) {
    try {
        $result = $PayrollSetupService->fetchById($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 404);
    } catch (\Exception $e) {
        LogError('GET /payroll-setup/read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Add. Optional `new_accounts` [{account_code, account_title}, ...] (parent first): the item's account_code does not exist yet,
// so the missing 5th and/or 6th part accounts are created with the item (and removed again if the item fails to save).
$app->post('/payroll-setup/add', function ($request, $response, $args) use ($PayrollSetupService) {
    try {
        $result = $PayrollSetupService->create($request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /payroll-setup/add ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update (item_code is immutable). Same optional `new_accounts` as add.
$app->put('/payroll-setup/update/{id}', function ($request, $response, $args) use ($PayrollSetupService) {
    try {
        $result = $PayrollSetupService->update($args['id'], $request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('PUT /payroll-setup/update ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
