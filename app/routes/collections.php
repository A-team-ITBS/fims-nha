<?php
// __BASE__/app/routes/collections.php
// Backs app/pages/collections-page.php — see app/services/CollectionsService.php.
namespace PHPMaker2024\FIMS;

// Static routes must stay registered before the GET /{id} catch-all below —
// FastRoute errors ("shadowed by previously defined variable route") if a
// static route at the same path depth is registered after a dynamic one
// (same gotcha documented in app/routes/check-processing.php and
// app/routes/bank-management.php).
$app->get("/collections/list", function ($request, $response) {
    $service = new Service\CollectionsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getList($request->getQueryParams()));
});

$app->get("/collections/lookup/responsibility-centers", function ($request, $response) {
    $service = new Service\CollectionsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getResponsibilityCenters());
});

$app->get("/collections/lookup/gl-accounts", function ($request, $response) {
    $service = new Service\CollectionsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getGlAccounts());
});

$app->get("/collections/lookup/bank-accounts", function ($request, $response) {
    $service = new Service\CollectionsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getBankAccounts());
});

$app->get("/collections/{id}/jev-prefill/{leg}", function ($request, $response, $args) {
    $service = new Service\CollectionsService();
    $result  = $service->proformaJev((int) $args['id'], (string) $args['leg']);
    return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
});

$app->post("/collections/{id}/jev-prefill/{leg}", function ($request, $response, $args) {
    $data = $request->getParsedBody() ?: [];
    $service = new Service\CollectionsService();
    $result  = $service->submitJev((int) $args['id'], (string) $args['leg'], $data, (int) CurrentUserID());
    return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
});

$app->post("/collections/store", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CollectionsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->store($data));
});

$app->get("/collections/{id}", function ($request, $response, $args) {
    $service = new Service\CollectionsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getReport($args['id']));
});

$app->post("/collections/{id}/update", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CollectionsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->update($args['id'], $data));
});

$app->post("/collections/{id}/delete", function ($request, $response, $args) {
    $service = new Service\CollectionsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->delete($args['id']));
});
