<?php
// __BASE__/app/routes/soa-generator.php
// Backs app/pages/soa-generator-page.php — see app/services/SoaGeneratorService.php.
namespace PHPMaker2024\FIMS;

$app->get("/soa-generator/bank-accounts", function ($request, $response) {
    $service = new Service\SoaGeneratorService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getBankAccounts());
});

$app->get("/soa-generator/generate", function ($request, $response) {
    $params = $request->getQueryParams();
    $service = new Service\SoaGeneratorService();
    return $response->withHeader('Content-Type', 'application/json')->write(
        $service->generate(
            $params['bank_account_id'] ?? 0,
            $params['period_start'] ?? date('Y-m-01'),
            $params['period_end'] ?? date('Y-m-d')
        )
    );
});
