<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\FarReportService;
use PHPMaker2024\FIMS\Service\GlobalService;

// DBM Financial Accountability Reports — read-only. See FarReportService for where each figure comes from,
// and FAR-ERD.md (repo root) for the tables behind them.
//
//   GET /far_report/far1   ?fiscal_year=2026
//   GET /far_report/far1a  ?fiscal_year=2026
//   GET /far_report/far3   ?as_of=2026-11-30          (every year's unpaid obligations)
//   GET /far_report/far4   ?fiscal_year=2026&month=11
//   GET /far_report/far5   ?fiscal_year=2026&quarter=4
//
// Office scope (FAR 1, 1-A, 3) is the same rule as GET /ledger_budget_allocation/list: the logged-in user's RC and
// the RCs under it; FMD (the central review RC) and superadmin / users without an RC see every office.
$FarReportService = new FarReportService();

$farReports = [
    'far1'  => ['fetchFar1', ['fiscal_year']],
    'far1a' => ['fetchFar1a', ['fiscal_year']],
    'far3'  => ['fetchFar3', ['as_of']],
    'far4'  => ['fetchFar4', ['fiscal_year', 'month']],
    'far5'  => ['fetchFar5', ['fiscal_year', 'quarter']],
];

foreach ($farReports as $path => [$method, $allowed]) {
    $app->get('/far_report/' . $path, function ($request, $response, $args) use ($FarReportService, $method, $allowed, $path) {
        try {
            $params = $request->getQueryParams();
            $filters = array_intersect_key($params, array_flip($allowed));

            $scope = GlobalService::getUserRcScope((int) $request->getAttribute('user_id'));
            $filters['rc_id'] = GlobalService::resolveBudgetReqRcFilter($scope, $params + ['central' => '1']);

            $result = $FarReportService->$method($filters);

            return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 500));
        } catch (\Exception $e) {
            LogError("GET /far_report/{$path} " . $e->getMessage());

            return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
        }
    })->add($accessMiddleware)->add($jwtMiddleware);
}
