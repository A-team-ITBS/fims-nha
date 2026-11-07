<?php
// __BASE__/app/routes/service.php
namespace PHPMaker2024\FIMS;

$app->get("/Service", function ($request, $response, $args) {
    $apiService = new Service\ApiService();
    $result = $apiService->generateOAI();
    return $response->write($result);
}); // ->add($jwtMiddleware) 