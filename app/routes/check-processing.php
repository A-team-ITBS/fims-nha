<?php
// __BASE__/app/routes/check-processing.php
// Backs app/pages/check-disbursement-voucher-page.php — see app/services/CheckProcessingService.php
// for the STUB IMPLEMENTATION note (treasury/accounting/shared/workflow/fmd schemas
// don't exist in this project's database yet; every endpoint below returns an
// empty-but-structurally-correct response until that migration happens).
namespace PHPMaker2024\FIMS;

// ── Check Preparation ──
$app->get("/check-processing/preparation/bank-accounts", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getBankAccounts());
});

$app->get("/check-processing/preparation/fund-sources", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getFundSources());
});

$app->get("/check-processing/preparation/checks", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
        ->write($service->getChecksList($request->getQueryParams()));
});

$app->get("/check-processing/preparation/dvs-for-prep", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDvsForPrep($request->getQueryParams()));
});

$app->get("/check-processing/preparation/unused-checks/{bankId}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getUnusedChecks($args['bankId']));
});

$app->get("/check-processing/preparation/cash-availability", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    $bankAccountId = $request->getQueryParams()['bank_account_id'] ?? null;
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCashAvailability($bankAccountId));
});

$app->get("/check-processing/preparation/check/{id}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCheck($args['id']));
});

$app->get("/check-processing/preparation/check/{id}/history", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCheckHistory($args['id']));
});

$app->post("/check-processing/preparation/check/{id}/mark-printed", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->markPrinted($args['id']));
});

$app->post("/check-processing/preparation/check/{id}/set-status", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->setCheckStatus($args['id'], $data));
});

// Streams the print-agent installer zip — not JSON, so this bypasses the
// json-write convention the rest of this file uses.
$app->get("/check-processing/print-agent-package", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    $response->getBody()->write($service->generatePrintAgentPackage());
    return $response
        ->withHeader('Content-Type', 'application/zip')
        ->withHeader('Content-Disposition', 'attachment; filename="fims-print-agent-setup.zip"');
});

$app->post("/check-processing/preparation/save", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveCheck($data));
});

$app->get("/check-processing/preparation/subsidy-report", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getSubsidyReport($request->getQueryParams()));
});

// ── Check Registry (blank check stock) ──
$app->get("/check-processing/registry/series-list", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getSeriesList());
});

$app->get("/check-processing/registry/list", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegistryList($request->getQueryParams()));
});

$app->get("/check-processing/registry/booklets", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    $bankAccountId = $request->getQueryParams()['bank_account_id'] ?? null;
    return $response->withHeader('Content-Type', 'application/json')->write($service->getBooklets($bankAccountId));
});

$app->post("/check-processing/registry/update-status", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->updateRegisterStatus($data));
});

$app->post("/check-processing/registry/add-batch", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->addRegisterBatch($data));
});

$app->get("/check-processing/registry/summary", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegistrySummary());
});

// ── Check Inventory / RAF ──
$app->get("/check-processing/raf/summary", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRafSummary());
});

$app->get("/check-processing/raf/years", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRafYears());
});

$app->get("/check-processing/raf/list", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRafList($request->getQueryParams()));
});

// Must stay registered after the static /registry/* routes above — FastRoute
// errors ("shadowed by previously defined variable route") if a dynamic
// route at the same path depth is registered before a static one.
$app->get("/check-processing/registry/{id}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegistryEntry($args['id']));
});

// ── Check Released (unified — was two separate route families, /releases/*
// for "Released to Claimants" and /region-release/* for "Released to
// Regions/Departments"; see CheckProcessingService's docblock and "01 -
// table/38 - fims_check_releases_unified.sql" for the merge). Every static
// route below must stay registered before the dynamic GET/POST
// /releases/{id}... routes further down — FastRoute errors ("shadowed by
// previously defined variable route") if a dynamic route at the same path
// depth is registered first. ──
$app->get("/check-processing/releases", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getReleasesList($request->getQueryParams()));
});

$app->get("/check-processing/releases/unclaimed", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getUnclaimedReleases($request->getQueryParams()));
});

$app->get("/check-processing/releases/email-log", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getEmailLog($request->getQueryParams()));
});

$app->get("/check-processing/releases/departments", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDepartments());
});

$app->get("/check-processing/releases/pending/{deptId}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getPendingReleases($args['deptId']));
});

$app->get("/check-processing/releases/acknowledgment-list", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getAcknowledgmentList($request->getQueryParams()));
});

$app->get("/check-processing/releases/dv-monitoring", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDvReturnMonitoring($request->getQueryParams()));
});

$app->get("/check-processing/releases/bulk-preview-notice", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->previewBulkReleaseNotice($request->getQueryParams()));
});

$app->post("/check-processing/releases/save", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveRelease($data));
});

$app->post("/check-processing/releases/bulk-send-notice", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->sendBulkReleaseNotice($data, $request->getUploadedFiles()['attachment'] ?? []));
});

$app->post("/check-processing/releases/bulk-resend-email/{logId}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->resendBulkReleaseEmail($args['logId']));
});

$app->post("/check-processing/releases/save-drive-link", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveDriveLink($data));
});

$app->get("/check-processing/releases/{id}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRelease($args['id']));
});

$app->get("/check-processing/releases/{id}/preview-notice", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->previewReleaseNotice($args['id'], $request->getQueryParams()));
});

$app->get("/check-processing/releases/{id}/documents", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getReleaseDocuments($args['id']));
});

$app->post("/check-processing/releases/{id}/send-notice", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->sendReleaseNotice($args['id'], $data, $request->getUploadedFiles()['attachment'] ?? []));
});

$app->post("/check-processing/releases/{id}/follow-up", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveReleaseFollowUp($args['id'], $data));
});

$app->post("/check-processing/releases/{id}/set-claimant", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->setReleaseClaimant($args['id'], $data));
});

$app->post("/check-processing/releases/{id}/acknowledge", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->acknowledgeRelease($args['id'], $data));
});

$app->post("/check-processing/releases/{id}/update-status", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->updateReleaseStatus($args['id'], $data));
});

$app->post("/check-processing/releases/{id}/mark-stale", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->markReleaseStale($args['id'], $data));
});

$app->post("/check-processing/releases/{id}/record-dv-return", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->recordDvReturn($args['id'], $data));
});

$app->get("/check-processing/checks/lookup", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->lookupChecks($request->getQueryParams()));
});

$app->get("/check-processing/checks/{id}/department-emails", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCheckDepartmentEmails($args['id']));
});

// ── Check Cancellation & Replacements ──
$app->get("/check-processing/cancellation/kpis", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCancellationKpis());
});

$app->get("/check-processing/cancellation/lookups", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCancellationLookups());
});

$app->get("/check-processing/cancellation/list", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCancellationList($request->getQueryParams()));
});

$app->post("/check-processing/cancellation/cancel", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->cancelCheck($data));
});

$app->post("/check-processing/cancellation/replace", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->replaceCheck($data));
});

$app->get("/check-processing/cancellation/{id}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCancelledCheck($args['id']));
});

$app->get("/check-processing/cancellation/{id}/history", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCancelledCheckHistory($args['id']));
});
