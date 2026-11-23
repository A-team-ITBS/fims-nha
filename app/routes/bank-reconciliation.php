<?php
// __BASE__/app/routes/bank-reconciliation.php
// Backs app/pages/bank-reconciliation-page.php — see app/services/BankReconciliationService.php.
namespace PHPMaker2024\FIMS;

$app->get("/bank-reconciliation/list", function ($request, $response) {
    $service = new Service\BankReconciliationService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getList($request->getQueryParams()));
});

$app->get("/bank-reconciliation/bank-accounts", function ($request, $response) {
    $service = new Service\BankReconciliationService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getBankAccounts());
});

$app->get("/bank-reconciliation/book-balance", function ($request, $response) {
    $params = $request->getQueryParams();
    $service = new Service\BankReconciliationService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getBookBalance($params['bank_account_id'] ?? 0, $params['as_of_date'] ?? date('Y-m-d')));
});

$app->get("/bank-reconciliation/history/{bank_account_id}", function ($request, $response, $args) {
    $service = new Service\BankReconciliationService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getHistory($args['bank_account_id']));
});

$app->get("/bank-reconciliation/ledger", function ($request, $response) {
    $params = $request->getQueryParams();
    $service = new Service\BankReconciliationService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getLedger($params['bank_account_id'] ?? 0, $params['as_of_date'] ?? date('Y-m-d')));
});

$app->post("/bank-reconciliation/clear-item", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\BankReconciliationService();
    return $response->withHeader('Content-Type', 'application/json')->write(
        $service->setCleared($data['bank_account_id'] ?? 0, $data['gl_id'] ?? 0, !empty($data['cleared']))
    );
});

$app->post("/bank-reconciliation/save", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\BankReconciliationService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->save($data));
});

$app->get("/bank-reconciliation/{id}", function ($request, $response, $args) {
    $service = new Service\BankReconciliationService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getOne($args['id']));
});
