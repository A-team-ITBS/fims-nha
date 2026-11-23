<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\ClosingBalanceService;

// Closing Balance Entry — year-end Revenue/Expense sweep, mirrors opening_balance.php.
$ClosingBalanceService = new ClosingBalanceService();

// GET /closing_balance?fiscal_yr=2026&book_id=1
// Preview the closing entry: Revenue/Expense accounts with activity, net income, lock state.
$app->get('/closing_balance', function ($request, $response, $args) use ($ClosingBalanceService) {
    try {
        $params = $request->getQueryParams();
        $result = $ClosingBalanceService->fetchClosingBalanceForm(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null,
            isset($params['book_id']) && $params['book_id'] !== '' ? (int) $params['book_id'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /closing_balance ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// POST /closing_balance
// Recomputes current GL balances server-side and posts the CLOSING JEV.
// Body: {fiscal_yr, book_id}
$app->post('/closing_balance', function ($request, $response, $args) use ($ClosingBalanceService) {
    try {
        $body = $request->getParsedBody() ?: [];
        $userId = CurrentUserID() ?? 0;
        $result = $ClosingBalanceService->closeFiscalYear(
            (int) ($body['fiscal_yr'] ?? 0),
            (int) ($body['book_id'] ?? 0),
            $userId
        );

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /closing_balance ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// POST /closing_balance/reverse
// Reverses the POSTED CLOSING JEV for a fiscal_yr/book_id, unlocking the preview again.
// Body: {fiscal_yr, book_id}
$app->post('/closing_balance/reverse', function ($request, $response, $args) use ($ClosingBalanceService) {
    try {
        $body = $request->getParsedBody() ?: [];
        $userId = CurrentUserID() ?? 0;
        $result = $ClosingBalanceService->reverseClosingBalance(
            (int) ($body['fiscal_yr'] ?? 0),
            (int) ($body['book_id'] ?? 0),
            $userId
        );

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /closing_balance/reverse ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
