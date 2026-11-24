<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\GlobalService;
use PHPMaker2024\FIMS\Service\PrintPayloadService;
use PHPMaker2024\FIMS\Service\PrintService;

// Printed forms — one endpoint for every form (CBA, CAF, RFA, LAA, …), run by
// PrintService (the forms are registered in PrintService::DOCS).
// Preview modal: app/pages/readone/print-preview-readone.php (prp_open(payload, { type })).
$PrintService = new PrintService();
$PrintPayloadService = new PrintPayloadService();

// The print payload of a SAVED record, so a list can print it without opening a form:
//   GET /print/payload/allocation/{id}   a memo request (CBA / RFA / LAA) -> { success, doc: CBA|RFA|LAA, payload }
//   GET /print/payload/caf/{id}          a CAF                            -> { success, doc: CAF, payload }
// The page then calls prp_open(payload, { type: doc }) — the preview, PDF and DOCX all go through POST /print/{doc} below.
// The CBA / RFA / LAA forms and the CAF are for FMD users and administrators to print: for any other user both answer 403.
$app->get('/print/payload/allocation/{id:[0-9]+}', function ($request, $response, $args) use ($PrintPayloadService) {
    try {
        $result = $PrintPayloadService->forAllocation($args['id'], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Throwable $e) {
        LogError('GET /print/payload/allocation ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/print/payload/caf/{id:[0-9]+}', function ($request, $response, $args) use ($PrintPayloadService) {
    try {
        $result = $PrintPayloadService->forCaf($args['id'], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Throwable $e) {
        LogError('GET /print/payload/caf ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Print a form from a payload. {doc} = cba | caf | rfa | laa
// Body: { format: html|pdf|docx, ...payload } — payload fields are documented on each
// form's model() (CbaPrintService, CafPrintService, RfaPrintService, LaaPrintService); `letterhead`
// = this print's header items + footer_text (see LetterheadLayoutService).
// html → { success, html };  pdf / docx → { success, filename, mime, data: base64 }
// {doc} = cba | rfa | laa | caf (PrintPayloadService::FMD_ONLY_DOCS) is for FMD users (uac.users.rc_id ->
// fims.responsibility_code.rc_code is the FMD's) and superadmins to print: 403 for anyone else — the preview, the PDF and the
// DOCX all come through here, so none of them can be reached with the button hidden. The other forms are not restricted.
$app->post('/print/{doc:[A-Za-z]+}', function ($request, $response, $args) use ($PrintService) {
    try {
        if (in_array(strtoupper((string) $args['doc']), PrintPayloadService::FMD_ONLY_DOCS, true)
            && !GlobalService::canPrintAllocationForm((int) $request->getAttribute('user_id'))) {
            return $response->withJson(['success' => false, 'message' => PrintPayloadService::PRINT_DENIED], 403);
        }
        $body = $request->getParsedBody();
        if (!is_array($body) || !$body) {
            $body = json_decode((string) $request->getBody(), true) ?: [];
        }
        $format = (string) ($body['format'] ?? 'html');
        unset($body['format']);

        $result = $PrintService->print($args['doc'], $body, $format);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Throwable $e) {
        LogError('POST /print/' . $args['doc'] . ' ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Current holder of a signatory role: ?role=FMD%20Department%20Manager → { success, name|null }
// name is "FIRST M. LAST[, SUFFIX]"; add &format=first_last for just "FIRST LAST" (what the CBA prints for the
// FMD Department Manager — the user holding that uac.user_levels role, first_name + last_name of uac.users).
$app->get('/print/resolve-signatory', function ($request, $response, $args) {
    try {
        $query = $request->getQueryParams();
        $role  = trim((string) ($query['role'] ?? ''));
        if (!in_array($role, PrintService::SIGNATORY_ROLES, true)) {
            return $response->withJson(['success' => false, 'message' => 'Unsupported role.'], 400);
        }
        $firstLastOnly = (string) ($query['format'] ?? '') === 'first_last';

        return $response->withJson(['success' => true, 'name' => PrintService::resolveSignatoryName($role, $firstLastOnly)]);
    } catch (\Throwable $e) {
        LogError('GET /print/resolve-signatory ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
