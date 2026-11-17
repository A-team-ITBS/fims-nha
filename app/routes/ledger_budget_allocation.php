<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\GlobalService;
use PHPMaker2024\FIMS\Service\LedgerBudgetAllocationService;

// Ledger of Budget Allocation (RFA / LAA) — read-only, page app/pages/ledger-budget-allocation.php.
// See LedgerBudgetAllocationService for how the groups and running balances are built.
$LedgerBudgetAllocationService = new LedgerBudgetAllocationService();

// Optional: fiscal_year, allocation_type (RFA / LAA), status (status name), search, parent_id.
// Office scope is the same rule as GET /budget_allocation/list: the logged-in user's RC and the RCs under it;
// FMD (the central review RC) and superadmin / users without an RC see every office.
$app->get('/ledger_budget_allocation/list', function ($request, $response, $args) use ($LedgerBudgetAllocationService) {
    try {
        $params = $request->getQueryParams();
        $filters = array_intersect_key($params, array_flip(['fiscal_year', 'allocation_type', 'status', 'search', 'parent_id']));

        $scope = GlobalService::getUserRcScope((int) $request->getAttribute('user_id'));
        $filters['rc_id'] = GlobalService::resolveBudgetReqRcFilter($scope, $params + ['central' => '1']);

        $result = $LedgerBudgetAllocationService->fetchLedger($filters);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 500));
    } catch (\Exception $e) {
        LogError('GET /ledger_budget_allocation/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
