<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\IncomeStatementService;

// Income Statement — read-only, mirrors app/routes/balance_sheet.php (see
// IncomeStatementService.php for how it's derived).
$IncomeStatementService = new IncomeStatementService();

// GET /income_statement?fiscal_yr=2026&book_id=1&view_mode=detailed — all optional
// (default: current year, all books, consolidated). view_mode is one of
// "consolidated" (group subtotals only), "detailed" (every account), "condensed" (section totals only).
$app->get('/income_statement', function ($request, $response, $args) use ($IncomeStatementService) {
    try {
        $params = $request->getQueryParams();
        $result = $IncomeStatementService->fetchIncomeStatement(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null,
            isset($params['book_id']) && $params['book_id'] !== '' ? (int) $params['book_id'] : null,
            isset($params['view_mode']) && $params['view_mode'] !== '' ? (string) $params['view_mode'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /income_statement ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// GET /income_statement/consolidated?fiscal_yr=2026 — the multi-entity roll-up (Main Office,
// CISFA, NGC, MPLC/PPMO side by side + TOTAL), for one fiscal year. No Adjustments or
// Eliminating-entry columns.
$app->get('/income_statement/consolidated', function ($request, $response, $args) use ($IncomeStatementService) {
    try {
        $params = $request->getQueryParams();
        $result = $IncomeStatementService->fetchConsolidatedIncomeStatement(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /income_statement/consolidated ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
