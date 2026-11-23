<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\OpeningBalanceService;

// Opening Balance Entry — form data and submission endpoints.
$OpeningBalanceService = new OpeningBalanceService();

// GET /opening_balance?fiscal_yr=2027&book_id=1
// Fetch the form data: permanent accounts, pre-filled from any existing fims.opening_balances
// row for this fiscal_yr/book_id (or a legacy OPENING JEV, for years saved before this service
// stopped posting those — see OpeningBalanceService's own docblock).
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

// GET /opening_balance/carry_forward?fiscal_yr=2027&book_id=1
// Computes the prior fiscal year's cumulative ending balances for permanent accounts, for
// the client to pre-fill into the form (the client still reviews and posts — nothing is
// saved by this call).
$app->get('/opening_balance/carry_forward', function ($request, $response, $args) use ($OpeningBalanceService) {
    try {
        $params = $request->getQueryParams();
        $result = $OpeningBalanceService->fetchCarryForward(
            (int) ($params['fiscal_yr'] ?? 0),
            (int) ($params['book_id'] ?? 0)
        );

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /opening_balance/carry_forward ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// POST /opening_balance
// Saves the fiscal year's beginning balances directly into fims.opening_balances — no JEV, no
// posting to fims.general_ledger (see OpeningBalanceService::saveOpeningBalance()'s own
// docblock). Also refreshes Cash Position's earliest entry for this year for every bank account
// tagged to one of the saved account codes.
// Body: {fiscal_yr, book_id, lines: [{account_code, debit, credit}]}
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

// Note: there is intentionally no /opening_balance/reverse route. Once submitted, an Opening
// Balance is fully locked — no edit, no reverse, no clear. The only way it's ever removed is
// as a side effect of reversing the Closing that auto-carried it forward (see
// ClosingBalanceService::reverseClosingBalance() -> OpeningBalanceService::deleteCarryForwardRows(),
// an internal-only method with no route of its own).
