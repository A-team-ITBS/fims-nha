<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\PoDrService;

$PoDrService = new PoDrService();

// A PO is addressed by its po_number, passed as ?po_number=… (PO numbers may contain "/" or "-").

// List — one entry per PO number, each with its delivery receipt lines (paginated)
$app->get('/po-dr/list', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();

    return $response->withJson($PoDrService->fetchAlls(
        isset($params['page']) ? (int) $params['page'] : 1,
        isset($params['limit']) ? (int) $params['limit'] : 10,
        $params['search'] ?? '',
        (string) ($params['rc_id'] ?? ''),        // filter: requesting office (responsibility_code.rc_id)
        (string) ($params['vendor_id'] ?? ''),    // filter: supplier (vendors.vendor_id)
        (string) ($params['date_from'] ?? ''),    // filter: PO Date from (YYYY-MM-DD)
        (string) ($params['date_to'] ?? '')       // filter: PO Date to (YYYY-MM-DD)
    ));
})->add($jwtMiddleware);

// Values of the list's Requesting Office/Dept and Supplier filter dropdowns (those of the POs that have an IAR)
$app->get('/po-dr/filter-options', function ($request, $response, $args) use ($PoDrService) {
    return $response->withJson($PoDrService->filterOptions());
})->add($jwtMiddleware);

// PO picker: RFA POs of the Budget Allocation (?search= on the PO number) with PO date, office, vendor and PO amount
$app->get('/po-dr/pos', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();

    return $response->withJson($PoDrService->searchPos((string) ($params['search'] ?? '')));
})->add($jwtMiddleware);

// Account Code picker: the chart of accounts (?search= on the code or title)
$app->get('/po-dr/accounts', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();

    // ?infra=1: the Infrastructure tab's picker — the CIP / WIP categories highlighted (see PoDrService::searchInfraAccounts())
    return $response->withJson($PoDrService->searchAccounts((string) ($params['search'] ?? ''), !empty($params['all']), !empty($params['infra']), (string) ($params['cat'] ?? '')));
})->add($jwtMiddleware);

// Read one PO with its delivery receipts
$app->get('/po-dr/read', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();

    return $response->withJson($PoDrService->fetchByPo((string) ($params['po_number'] ?? '')));
})->add($jwtMiddleware);

// Pro-forma JEV of each delivery receipt of a PO
$app->get('/po-dr/proforma', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();

    return $response->withJson($PoDrService->proforma((string) ($params['po_number'] ?? '')));
})->add($jwtMiddleware);

// Submit the pro-forma of ONE delivery receipt as a Journal Entry Voucher (standalone, DRAFT — the same creation as
// POST /voucher/journal-entry/add). body: { po_number, dr_number, jev_date, fiscal_yr, book_id, particulars?, lines[] }.
// The JEV is recorded on the delivery receipt (jev_id / jev_no), and a receipt that already has one cannot be submitted again.
$app->post('/po-dr/proforma-submit', function ($request, $response, $args) use ($PoDrService) {
    try {
        $body = $request->getParsedBody() ?: [];
        $po   = trim((string) ($body['po_number'] ?? ''));
        $dr   = trim((string) ($body['dr_number'] ?? ''));
        if ($po === '' || $dr === '') {
            return $response->withJson(['success' => false, 'message' => 'PO number and delivery receipt no. are required.'], 400);
        }
        if ($done = $PoDrService->jevOf($po, $dr)) {
            return $response->withJson(['success' => false, 'message' => "The pro-forma of {$dr} was already submitted as Journal Entry Voucher {$done['jev_no']}.", 'data' => $done], 409);
        }
        $uid     = (int) $request->getAttribute('user_id');
        $voucher = new \PHPMaker2024\FIMS\Service\DisbursementVoucherService();
        $result  = $voucher->createStandaloneJev($body, $uid);
        if (!empty($result['success'])) {
            $jevId = (int) $result['data']['jev_id'];
            $PoDrService->linkJev($po, $dr, $jevId, (string) $result['data']['jev_no']);
            // Submit for approval right away: DRAFT -> FOR_SECTION_CHIEF (the JEV list's own "Submit" step)
            $sub = $voucher->changeJevStatus($jevId, 'FOR_SECTION_CHIEF', $uid);
            if (!empty($sub['success'])) {
                $result['data']['status'] = 'FOR_SECTION_CHIEF';
                $result['message']        = 'Journal Entry Voucher ' . $result['data']['jev_no'] . ' created and submitted for approval.';
            } else {
                $result['message'] = 'Journal Entry Voucher ' . $result['data']['jev_no'] . ' was created as a draft, but could not be submitted for approval: ' . ($sub['message'] ?? 'unknown error');
            }
        }

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /po-dr/proforma-submit ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($jwtMiddleware);

// Delivery Acceptance (Actions menu): body { po_number, subject_to, inspection_notes? } -> delivery_accepted = TRUE (one-way; no more deliveries afterwards)
$app->post('/po-dr/accept-delivery', function ($request, $response, $args) use ($PoDrService) {
    $body   = $request->getParsedBody() ?: [];
    // the date and "Inspected By" come from the backend (now / the logged-in user); the user types only subject_to (ICS/PAR) and the optional notes
    $result = $PoDrService->acceptDelivery(
        (string) ($body['po_number'] ?? ''),
        (int) $request->getAttribute('user_id'),
        (string) ($body['subject_to'] ?? ''),
        (string) ($body['inspection_notes'] ?? '')
    );

    return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
})->add($jwtMiddleware);

// Add (po_number + lines[])
$app->post('/po-dr/add', function ($request, $response, $args) use ($PoDrService) {
    $result = $PoDrService->create($request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

    return $response->withJson($result, $result['success'] ? 201 : 400);
})->add($jwtMiddleware);

// Update (?po_number=<current PO number>; same payload as add, dr_line_id marks lines to update)
$app->put('/po-dr/update', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();
    $result = $PoDrService->update((string) ($params['po_number'] ?? ''), $request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);

// Delete a PO number with all its lines
$app->delete('/po-dr/delete', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();
    $result = $PoDrService->delete((string) ($params['po_number'] ?? ''));

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);
