<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BalanceSheetService;

// Balance Sheet — read-only, one endpoint (see BalanceSheetService.php for how it's derived).
$BalanceSheetService = new BalanceSheetService();

// GET /balance_sheet?fiscal_yr=2026&book_id=1&view_mode=detailed — all optional
// (default: current year, all books, consolidated). view_mode is one of
// "consolidated" (group subtotals only), "detailed" (every account), "condensed" (section totals only).
$app->get('/balance_sheet', function ($request, $response, $args) use ($BalanceSheetService) {
    try {
        $params = $request->getQueryParams();
        $result = $BalanceSheetService->fetchBalanceSheet(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null,
            isset($params['book_id']) && $params['book_id'] !== '' ? (int) $params['book_id'] : null,
            isset($params['view_mode']) && $params['view_mode'] !== '' ? (string) $params['view_mode'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /balance_sheet ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// GET /balance_sheet/consolidated?fiscal_yr=2026 — the multi-entity roll-up (Main Office, CISFA,
// NGC, MPLC/PPMO side by side + TOTAL), "as at" one fiscal year-end. No Adjustments or
// Eliminating-entry column yet.
$app->get('/balance_sheet/consolidated', function ($request, $response, $args) use ($BalanceSheetService) {
    try {
        $params = $request->getQueryParams();
        $result = $BalanceSheetService->fetchConsolidatedBalanceSheet(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /balance_sheet/consolidated ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
