<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\StatementOfChangesService;

// Statement of Changes in Net Assets/Equity — read-only endpoints.
$StatementOfChangesService = new StatementOfChangesService();

// GET /statement_of_changes?fiscal_yr=2026&book_id=1 — single-entity view, one fiscal year.
// fiscal_yr and book_id are optional (default: current year, all books consolidated).
$app->get('/statement_of_changes', function ($request, $response, $args) use ($StatementOfChangesService) {
    try {
        $params = $request->getQueryParams();
        $result = $StatementOfChangesService->fetchStatementOfChanges(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null,
            isset($params['book_id']) && $params['book_id'] !== '' ? (int) $params['book_id'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /statement_of_changes ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// GET /statement_of_changes/equity?fiscal_yr=2026&book_id=1 — SCNAE layout: one column per equity
// component, prior year above current year, Consolidated + per-book blocks (just that book when book_id is set).
$app->get('/statement_of_changes/equity', function ($request, $response, $args) use ($StatementOfChangesService) {
    try {
        $params = $request->getQueryParams();
        $result = $StatementOfChangesService->fetchEquityStatement(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null,
            isset($params['book_id']) && $params['book_id'] !== '' ? (int) $params['book_id'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /statement_of_changes/equity ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// GET /statement_of_changes/consolidated?fiscal_yr=2026 — multi-entity roll-up,
// all books side-by-side plus TOTAL column.
$app->get('/statement_of_changes/consolidated', function ($request, $response, $args) use ($StatementOfChangesService) {
    try {
        $params = $request->getQueryParams();
        $result = $StatementOfChangesService->fetchConsolidatedStatementOfChanges(
            isset($params['fiscal_yr']) && $params['fiscal_yr'] !== '' ? (int) $params['fiscal_yr'] : null
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /statement_of_changes/consolidated ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
