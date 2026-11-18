<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\PropertyRecordService;

$PropertyRecordService = new PropertyRecordService();

// Property Records. The user searches a PO number in the Property Inventory System (PIS), picks items and saves them to
// fims.property_records (one row per PO, the picked items as JSONB).

// Search: the PO's items (status 1-4) from PIS + which are already saved + the PO's ONE responsibility center
// GET /property-record/search?po_number=…   → { success, data: { items[], rc{}, summary{}, saved_count, hidden_by_status, … } }
$app->get('/property-record/search', function ($request, $response, $args) use ($PropertyRecordService) {
    $params = $request->getQueryParams();
    $result = $PropertyRecordService->search((string) ($params['po_number'] ?? ''));

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);

// Save the SELECTED items: { po_number, inv_ids: [51422, …] }  (PIS is asked again; only the saved PIS fields are kept)
$app->post('/property-record/save', function ($request, $response, $args) use ($PropertyRecordService) {
    $body   = $request->getParsedBody() ?: [];
    $result = $PropertyRecordService->save(
        (string) ($body['po_number'] ?? ''),
        is_array($body['inv_ids'] ?? null) ? $body['inv_ids'] : [],
        (int) $request->getAttribute('user_id')
    );

    return $response->withJson($result, $result['success'] ? 201 : 400);
})->add($jwtMiddleware);

// The items entered in the IAR module for the property accounts (description, serial / property no., amount, delivery date, PO's RC)
// GET /property-record/iar-items   → { success, data: { items[] } }
$app->get('/property-record/iar-items', function ($request, $response, $args) use ($PropertyRecordService) {
    $result = $PropertyRecordService->iarItems();

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);

// Submit the depreciation pro-forma as a Journal Entry Voucher (sent for approval). body: { jev_date, book_id, particulars, lines[] }
$app->post('/property-record/proforma-submit', function ($request, $response, $args) use ($PropertyRecordService) {
    $result = $PropertyRecordService->submitProforma($request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

    return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
})->add($jwtMiddleware);

// The JEV a depreciation pro-forma was already submitted as (null when it was not): ?book_id=1&p[]=<particulars>&p[]=<standard particulars>
$app->get('/property-record/proforma-jev', function ($request, $response, $args) use ($PropertyRecordService) {
    $params = $request->getQueryParams();

    return $response->withJson(['success' => true, 'data' => $PropertyRecordService->proformaJev((int) ($params['book_id'] ?? 0), (array) ($params['p'] ?? []))]);
})->add($jwtMiddleware);

// Titles of chart-of-accounts codes (the depreciation pro-forma JEV)
// GET /property-record/account-titles?codes=5-05-01-050-02,1-06-05-021   → { success, data: { code: title } }
$app->get('/property-record/account-titles', function ($request, $response, $args) use ($PropertyRecordService) {
    $params = $request->getQueryParams();
    $result = $PropertyRecordService->accountTitles(explode(',', (string) ($params['codes'] ?? '')));

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);

// Everything that is saved (items of every PO, with the PO's responsibility center) + the numbers for the cards
// GET /property-record/list   → { success, data: { items[], po_count, summary{} } }
$app->get('/property-record/list', function ($request, $response, $args) use ($PropertyRecordService) {
    $result = $PropertyRecordService->listSaved();

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);
