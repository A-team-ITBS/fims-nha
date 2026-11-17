<?php
// __BASE__/app/routes/fidelity-bond.php
// Backs app/pages/fidelity-bond-page.php — see app/services/FidelityBondService.php.
namespace PHPMaker2024\FIMS;

$app->get("/fidelity-bond/regions", function ($request, $response) {
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegions());
});

$app->get("/fidelity-bond/dashboard", function ($request, $response) {
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDashboard($request->getQueryParams()));
});

$app->get("/fidelity-bond/cl/{id}/summary", function ($request, $response, $args) {
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getClSummary($args['id']));
});

// "ALL" is the all-regions sentinel used by Section A's region dropdown.
$app->get("/fidelity-bond/officers-for-region/{rcId}", function ($request, $response, $args) {
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getOfficersForRegion($args['rcId']));
});

$app->post("/fidelity-bond/apply", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->submitApplication($data));
});

$app->post("/fidelity-bond/officers/add", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->addOfficer($data));
});

$app->post("/fidelity-bond/cl", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->createCl($data));
});

// Encode + post in one call, officers included — see FidelityBondService::
// postNewCl()'s own docblock. The frontend's CL Upload tab now uses this
// exclusively (no more create-draft-then-add-officers-one-by-one flow);
// /cl, /cl/{id}/officers, /cl/{id}/post above stay as-is, just unused by it.
$app->post("/fidelity-bond/cl/post-direct", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->postNewCl($data));
});

// Best-effort autofill from an uploaded scan — multipart/form-data, field
// name "file" (same convention as POST /upload/file). No AI/API key
// involved; local Tesseract OCR + template pattern-matching, see
// FidelityBondService::extractClFromScan()'s own docblock for why results
// can be wrong and must stay editable.
$app->post("/fidelity-bond/cl/extract", function ($request, $response) {
    $uploadedFiles = $request->getUploadedFiles();
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->extractClFromScan($uploadedFiles['file'] ?? null));
});

// Manually backfills a paper CL that predates this system straight into
// the register — see FidelityBondService::addLegacyCl()'s own docblock.
$app->post("/fidelity-bond/cl/legacy", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->addLegacyCl($data));
});

$app->get("/fidelity-bond/cl-register", function ($request, $response) {
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getClRegister($request->getQueryParams()));
});

// Same rows as /cl-register above, laid out as a real .xlsx workbook — see
// FidelityBondService::exportClRegisterXlsx()'s own docblock.
$app->get("/fidelity-bond/cl-register/export", function ($request, $response) {
    $service = new Service\FidelityBondService();
    $bytes = $service->exportClRegisterXlsx($request->getQueryParams());
    $response->getBody()->write($bytes);
    return $response
        ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->withHeader('Content-Disposition', 'attachment; filename="fidelity-bond-cl-register.xlsx"')
        ->withHeader('Cache-Control', 'no-store');
});

$app->get("/fidelity-bond/applicable-officers", function ($request, $response) {
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->searchApplicableOfficers($request->getQueryParams()));
});

// Must stay registered after the static /cl-register, /cl/{id}/summary, etc.
// above — FastRoute errors ("shadowed by previously defined variable
// route") if a static route at the same path depth is registered after a
// dynamic one (same gotcha documented in app/routes/check-processing.php).
$app->get("/fidelity-bond/cl/{id}", function ($request, $response, $args) {
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCl($args['id']));
});

$app->post("/fidelity-bond/cl/{id}/officers", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->addOfficerToCl($args['id'], $data));
});

$app->post("/fidelity-bond/cl/{id}/officers/{clOfficerId}/remove", function ($request, $response, $args) {
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->removeOfficerFromCl($args['id'], $args['clOfficerId']));
});

$app->post("/fidelity-bond/cl/{id}/post", function ($request, $response, $args) {
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->postCl($args['id']));
});

$app->get("/fidelity-bond/history", function ($request, $response) {
    $service = new Service\FidelityBondService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getHistory($request->getQueryParams()));
});
