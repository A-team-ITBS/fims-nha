<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\OpeningBalanceService;

// Opening Balance Entry — form data and submission endpoints.
$OpeningBalanceService = new OpeningBalanceService();

// GET /opening_balance?fiscal_yr=2027&book_id=1
// Fetch the form data: permanent accounts, SL details, and any existing DRAFT OPENING JEV.
$app->get('/opening_balance', function ($request, $response, $args) use ($OpeningBalanceService) {
    try {
        $params = $request->getQueryParams();
        $result = $OpeningBalanceService->fetchOpeningBalanceForm(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null,
            isset($params['book_id']) && $params['book_id'] !== '' ? (int) $params['book_id'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /opening_balance ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// POST /opening_balance
// Save (or update) opening balance entries and post the JEV to GL.
// Body: {fiscal_yr, book_id, lines: [{account_code, sl_detail_id?, debit, credit}]}
$app->post('/opening_balance', function ($request, $response, $args) use ($OpeningBalanceService) {
    try {
        $body = $request->getParsedBody();
        $userId = CurrentUserID() ?? 0;
        $result = $OpeningBalanceService->saveOpeningBalance($body, $userId);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /opening_balance ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
