<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\CashFlowService;

// Statement of Cash Flows — read-only, mirrors app/routes/income_statement.php (see
// CashFlowService.php for how each line is derived).
$CashFlowService = new CashFlowService();

// GET /cash_flow?fiscal_yr=2026&book_id=1 — both optional (default: current year, all books).
$app->get('/cash_flow', function ($request, $response, $args) use ($CashFlowService) {
    try {
        $params = $request->getQueryParams();
        $result = $CashFlowService->fetchCashFlow(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null,
            isset($params['book_id']) && $params['book_id'] !== '' ? (int) $params['book_id'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /cash_flow ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
