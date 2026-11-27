<?php
// __BASE__/app/routes/bank-management.php
// Backs app/pages/bank-management-page.php — see app/services/BankManagementService.php.
namespace PHPMaker2024\FIMS;

// Static routes must stay registered before the GET /{id} catch-all below —
// FastRoute errors ("shadowed by previously defined variable route") if a
// static route at the same path depth is registered after a dynamic one
// (same gotcha documented in app/routes/check-processing.php).
$app->get("/bank-management/stats", function ($request, $response) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getStats());
});

$app->get("/bank-management/list", function ($request, $response) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getList($request->getQueryParams()));
});

$app->get("/bank-management/all", function ($request, $response) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getAll());
});

$app->get("/bank-management/dormancy-alerts", function ($request, $response) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDormancyAlerts());
});

$app->get("/bank-management/lookup/responsibility-centers", function ($request, $response) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getResponsibilityCenters());
});

$app->get("/bank-management/accounts-for-region", function ($request, $response) {
    $p = $request->getQueryParams();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getAccountsForRegion($p['rc_id'] ?? ''));
});

$app->get("/bank-management/fund-sweep/preview", function ($request, $response) {
    $p = $request->getQueryParams();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->previewFundSweep($p['rc_id'] ?? ''));
});

$app->post("/bank-management/fund-sweep/process", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->processFundSweep($data['rc_id'] ?? '', (array) ($data['account_ids'] ?? [])));
});

$app->get("/bank-management/fund-sweep/deposits", function ($request, $response) {
    $p = $request->getQueryParams();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getFundSweepDepositBreakdown($p['bank_account_id'] ?? 0));
});

$app->post("/bank-management/store", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->store($data));
});

$app->get("/bank-management/{id}", function ($request, $response, $args) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getAccount($args['id']));
});

$app->post("/bank-management/{id}/update", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->update($args['id'], $data));
});

$app->post("/bank-management/{id}/status", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->changeStatus($args['id'], $data));
});

$app->post("/bank-management/{id}/tag-account-code", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->tagAccountCode($args['id'], $data));
});

