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
$app->post('/print/{doc:[A-Za-z][A-Za-z0-9]*}', function ($request, $response, $args) use ($PrintService) {   // digits allowed after the first letter: BIR2307
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

// The General Ledger sheets an account can print — every (book, fiscal year) it has postings or an Opening Balance in
// (GeneralLedgerPrintService::availableSheets): ?account_code=…[&fiscal_yr=…] → { success, data: [{ book_id, book_name,
// fiscal_yr, postings }] }. The General Ledger page's Print menu lists these, so the user only picks what exists.
$app->get('/print/gl-sheets', function ($request, $response, $args) {
    try {
        $params = $request->getQueryParams();
        $code   = trim((string) ($params['account_code'] ?? ''));
        if ($code === '') {
            return $response->withJson(['success' => false, 'message' => 'account_code is required.'], 400);
        }
        $fy = isset($params['fiscal_yr']) && ctype_digit((string) $params['fiscal_yr']) ? (int) $params['fiscal_yr'] : null;

        return $response->withJson(['success' => true, 'data' => Service\GeneralLedgerPrintService::availableSheets($code, $fy)]);
    } catch (\Throwable $e) {
        LogError('GET /print/gl-sheets ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Opening balances for the General Ledger page's Detailed print — ?codes=a,b,c&fiscal_yr=2025[&book_id=1][&sign=1|-1]
// → { success, data: { code: opening } }, each signed on the account's normal side (sign 1 = debit-normal, -1 = credit-normal;
// GeneralLedgerPrintService::openingBalance — the account's own Opening Balance plus its SL accounts', for the book).
$app->get('/print/gl-opening', function ($request, $response, $args) {
    try {
        $params = $request->getQueryParams();
        $year   = isset($params['fiscal_yr']) && ctype_digit((string) $params['fiscal_yr']) ? (int) $params['fiscal_yr'] : 0;
        $book   = isset($params['book_id']) && ctype_digit((string) $params['book_id']) ? (int) $params['book_id'] : null;
        $sign   = ((string) ($params['sign'] ?? '1')) === '-1' ? -1 : 1;
        $codes  = array_slice(array_values(array_unique(array_filter(array_map('trim', explode(',', (string) ($params['codes'] ?? '')))))), 0, 200);
        if (!$codes || $year <= 0) {
            return $response->withJson(['success' => false, 'message' => 'codes and fiscal_yr are required.'], 400);
        }
        $out = [];
        foreach ($codes as $code) {
            $out[$code] = Service\GeneralLedgerPrintService::openingBalance($code, $year, $book, $sign);
        }

        return $response->withJson(['success' => true, 'data' => $out]);
    } catch (\Throwable $e) {
        LogError('GET /print/gl-opening ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// The BIR 2307 of a DV, prefilled for the print preview's edit panel (Bir2307PrintService::prefill()):
// { success, data: { editable: { payee_*, signatory_* }, locked: { dv_no, period, payor_*, rows } } } — 400 when the DV
// has no EWT / percentage-tax line to certify.
$app->get('/print/prefill/bir2307/{id:[0-9]+}', function ($request, $response, $args) {
    try {
        return $response->withJson(['success' => true, 'data' => (new Service\Bir2307PrintService())->prefill((int) $args['id'])]);
    } catch (\InvalidArgumentException $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
        LogError('GET /print/prefill/bir2307 ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// The annualization worksheet as Excel (the print preview's "Export to Excel"): { fiscal_yr, employee_id } →
// { success, filename, mime, data (base64 .xlsx) } — 400 when the employee has no payroll that year.
$app->post('/print/annualws/excel', function ($request, $response, $args) {
    try {
        $svc = new Service\AnnualizationPrintService();
        $d   = $svc->model($request->getParsedBody() ?: []);

        return $response->withJson(['success' => true, 'filename' => $d['file_base'] . '.xlsx',
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'data' => base64_encode($svc->xlsx($d))]);
    } catch (\InvalidArgumentException $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
        LogError('POST /print/annualws/excel ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// The GCG report as Excel (the print preview's Export to Excel): { fiscal_yr, period } → { success, filename, mime, data (base64) }
$app->post('/print/gcg/excel', function ($request, $response, $args) {
    try {
        $svc = new Service\GcgReportPrintService();
        $d   = $svc->model($request->getParsedBody() ?: []);

        return $response->withJson(['success' => true, 'filename' => $d['file_base'] . '.xlsx',
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'data' => base64_encode($svc->xlsx($d))]);
    } catch (\InvalidArgumentException $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
        LogError('POST /print/gcg/excel ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// The BIR 2316 of an employee for a year, prefilled for the print preview's Edit Form (Bir2316PrintService::prefill()):
// ?year=&employee_id= → { success, data: { editable, locked } } — 400 when the employee isn't annualized for that year.
$app->get('/print/prefill/bir2316', function ($request, $response, $args) {
    try {
        $q = $request->getQueryParams();

        return $response->withJson(['success' => true, 'data' => (new Service\Bir2316PrintService())->prefill((int) ($q['year'] ?? 0), (int) ($q['employee_id'] ?? 0))]);
    } catch (\InvalidArgumentException $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
        LogError('GET /print/prefill/bir2316 ' . $e->getMessage());

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
