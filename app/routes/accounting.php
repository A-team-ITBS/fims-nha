<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\AccountingService;

$AccountingService = new AccountingService();

// Search Chart of Accounts
$app->get('/accounting/coa/search', function ($request, $response, $args) use ($AccountingService) {
    try {
        $p = $request->getQueryParams();
        $result = $AccountingService->searchCoa((string) ($p['q'] ?? ''), (int) ($p['limit'] ?? 20));
        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /accounting/coa/search ' . $e->getMessage());
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Get child accounts (sub-ledgers) for a parent account
$app->get('/accounting/sl/by-account', function ($request, $response, $args) use ($AccountingService) {
    try {
        $p = $request->getQueryParams();
        $result = $AccountingService->getChildAccounts((string) ($p['account_id'] ?? ''), (int) ($p['limit'] ?? 50));
        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /accounting/sl/by-account ' . $e->getMessage());
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Search child accounts (sub-ledgers)
$app->get('/accounting/sl/search', function ($request, $response, $args) use ($AccountingService) {
    try {
        $p = $request->getQueryParams();
        $result = $AccountingService->searchChildAccounts((string) ($p['account_id'] ?? ''), (string) ($p['q'] ?? ''), (int) ($p['limit'] ?? 50));
        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /accounting/sl/search ' . $e->getMessage());
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Create a new child account (sub-ledger) under a parent GL account
$app->post('/accounting/sl/create', function ($request, $response, $args) use ($AccountingService) {
    try {
        $body = $request->getParsedBody() ?: [];
        $result = $AccountingService->createChildAccount(
            (string) ($body['parent_account_id'] ?? ''),
            (string) ($body['account_title'] ?? '')
        );
        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /accounting/sl/create ' . $e->getMessage());
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
