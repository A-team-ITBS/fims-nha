<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BalanceSheetService;

// Balance Sheet — read-only, one endpoint (see BalanceSheetService.php for how it's derived).
$BalanceSheetService = new BalanceSheetService();

// GET /balance_sheet?fiscal_yr=2026&book_id=1 — both optional (default: current year, all books)
$app->get('/balance_sheet', function ($request, $response, $args) use ($BalanceSheetService) {
    try {
        $params = $request->getQueryParams();
        $result = $BalanceSheetService->fetchBalanceSheet(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null,
            isset($params['book_id']) && $params['book_id'] !== '' ? (int) $params['book_id'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /balance_sheet ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
