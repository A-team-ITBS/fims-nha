<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\DisbursementVoucherService;

$VoucherService = new DisbursementVoucherService();

$voucherFilterKeys = ['status', 'fiscal_yr', 'expense_class', 'search'];
$rfaFilterKeys     = ['fiscal_yr', 'search'];
$jevFilterKeys     = ['fiscal_yr', 'search', 'jev_type', 'jev_classification'];

// Fetch all — no pagination. Optional: status, fiscal_yr, expense_class, search
$app->get('/voucher/all', function ($request, $response, $args) use ($VoucherService, $voucherFilterKeys) {
    try {
        $filters = array_intersect_key($request->getQueryParams(), array_flip($voucherFilterKeys));
        $result  = $VoucherService->fetchAlls(null, 10, $filters);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /voucher/all ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all (with pagination + summary). Optional: page, limit + the filters above
$app->get('/voucher/list', function ($request, $response, $args) use ($VoucherService, $voucherFilterKeys) {
    try {
        $params  = $request->getQueryParams();
        $filters = array_intersect_key($params, array_flip($voucherFilterKeys));
        $result  = $VoucherService->fetchAlls(
            isset($params['page']) ? (int) $params['page'] : 1,
            isset($params['limit']) ? (int) $params['limit'] : 10,
            $filters
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /voucher/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fiscal years (newest first) for dropdowns
$app->get('/voucher/fiscal-years', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->fetchFiscalYears();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /voucher/fiscal-years ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Does a PO have an IAR? ?po_number= → {data:{po_number, has_iar, iar_number}}. The DV form warns / refuses without one.
$app->get('/voucher/po-iar', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->checkPoIar((string) ($request->getQueryParams()['po_number'] ?? ''));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /voucher/po-iar ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Next free DV number for a date: ?dv_date=YYYY-MM-DD (defaults to today)
$app->get('/voucher/dv-number', function ($request, $response, $args) use ($VoucherService) {
    try {
        $date   = $request->getQueryParams()['dv_date'] ?? date('Y-m-d');
        $result = $VoucherService->fetchDvNumber((string) $date);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /voucher/dv-number ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// RFA picker options — fims.budget_allocation WHERE allocation_type = 'RFA'. One RFA backs only one
// DV at a time, so an RFA already used by another active voucher is left out entirely.
// Optional: fiscal_yr, search, exclude_dv_id (so an in-progress edit keeps showing the RFA it
// already uses)

$app->get('/voucher/rfa/all', function ($request, $response, $args) use ($VoucherService, $rfaFilterKeys) {
    try {
        $params      = $request->getQueryParams();
        $filters     = array_intersect_key($params, array_flip($rfaFilterKeys));
        $excludeDvId = $params['exclude_dv_id'] ?? null;
        $result      = $VoucherService->fetchRfaOptions($filters, $excludeDvId !== null ? (int) $excludeDvId : null);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /voucher/rfa/all ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// RFA detail + live ledger (budget ceiling vs. payments already posted). Optional: exclude_dv_id (editing)
$app->get('/voucher/rfa/read/{id}', function ($request, $response, $args) use ($VoucherService) {
    try {
        $excludeDvId = $request->getQueryParams()['exclude_dv_id'] ?? null;
        $result      = $VoucherService->fetchRfaDetail($args['id'], $excludeDvId !== null ? (int) $excludeDvId : null);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 500));
    } catch (\Exception $e) {
        LogError('GET /voucher/rfa/read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one — numeric dv_id
$app->get('/voucher/read/{id}', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->fetchById($args['id']);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 500));
    } catch (\Exception $e) {
        LogError('GET /voucher/read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Add. body.status: DRAFT (default) | FOR_FMD_REVIEW
$app->post('/voucher/add', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->create($request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 201 : 400));
    } catch (\Exception $e) {
        LogError('POST /voucher/add ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update (full replace of fields). DRAFT / REJECTED only
$app->put('/voucher/update/{id}', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->update($args['id'], $request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('PUT /voucher/update ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Change status — body: { status: FOR_FMD_REVIEW|FOR_ACCOUNTING|FOR_DIVISION_MANAGER|FOR_DEPARTMENT_MANAGER|FOR_TREASURY|RELEASED|REJECTED|CANCELLED, notes?: string }
// REJECTED requires notes (stored as rejection_reason).
$app->post('/voucher/{id}/status', function ($request, $response, $args) use ($VoucherService) {
    try {
        $body   = $request->getParsedBody() ?: [];
        $status = trim((string) ($body['status'] ?? ''));
        if ($status === '') {
            return $response->withJson(['success' => false, 'message' => 'status is required.'], 400);
        }
        $result = $VoucherService->changeStatus($args['id'], $status, (int) $request->getAttribute('user_id'), isset($body['notes']) ? (string) $body['notes'] : null);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /voucher/status ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// JEV register — paginated, joined with each JEV's DV. Read-only (a JEV is only created/edited
// from within its DV's Accounting stage via POST /voucher/{id}/journal-entry). Optional: page,
// limit, fiscal_yr, search (JEV no / DV no / claimant)
$app->get('/voucher/journal-entries', function ($request, $response, $args) use ($VoucherService, $jevFilterKeys) {
    try {
        $params  = $request->getQueryParams();
        $filters = array_intersect_key($params, array_flip($jevFilterKeys));
        $result  = $VoucherService->fetchJevList(
            isset($params['page']) ? (int) $params['page'] : 1,
            isset($params['limit']) ? (int) $params['limit'] : 10,
            $filters
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /voucher/journal-entries ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Active BIR ATC (Alphanumeric Tax Code) options for the Accounting stage's dropdown
$app->get('/voucher/atc-codes', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->fetchAtcCodes();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /voucher/atc-codes ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Accounting saves (or edits) this DV's Journal Entry Voucher + withholding tax classification +
// Deductions (Accounting can now edit Deductions too, not just Originating at creation).
// body: { atc_lines: [{atc_code, rate, tax_base, chargeability?}], lines: [{account_code, debit, credit, description?, sl_code?, sl_detail_id?}],
//         deductions: [{deduction_type_id, rate?, gross_amount?, deduction_amount?}], book_id (required whenever lines is non-empty — fims.books) }
// FOR_ACCOUNTING status + Accounting Department (RC-012) only; required (balanced) before FOR_TREASURY.
$app->post('/voucher/{id}/journal-entry', function ($request, $response, $args) use ($VoucherService) {
    try {
        $body   = $request->getParsedBody() ?: [];
        $result = $VoucherService->saveJournalEntry(
            $args['id'],
            is_array($body['atc_lines'] ?? null) ? $body['atc_lines'] : [],
            is_array($body['lines'] ?? null) ? $body['lines'] : [],
            (int) $request->getAttribute('user_id'),
            is_array($body['deductions'] ?? null) ? $body['deductions'] : [],
            $body['book_id'] ?? null,
            $body['jev_classification'] ?? null
        );

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /voucher/journal-entry ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Standalone Journal Entry Voucher (no DV — period-end ACCRUAL/DEPRECIATION/PREPAYMENTS/CLOSING
// entries Accounting books directly, "03 - alter_table/33"). Separate from the DV-embedded
// /voucher/{id}/journal-entry above — these never touch a disbursement_vouchers row at all.
$app->get('/voucher/journal-entry/{id}', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->fetchStandaloneJev((int) $args['id']);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 404));
    } catch (\Exception $e) {
        LogError('GET /voucher/journal-entry/{id} ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Create — DRAFT. body: { jev_date, fiscal_yr, book_id, jev_classification, particulars?, lines: [...] } (lines ≥2, balanced, required)
$app->post('/voucher/journal-entry/add', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->createStandaloneJev($request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /voucher/journal-entry/add ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Full replace — DRAFT only, same body shape as add
$app->put('/voucher/journal-entry/update/{id}', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->updateStandaloneJev((int) $args['id'], $request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('PUT /voucher/journal-entry/update/{id} ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// DRAFT only
$app->delete('/voucher/journal-entry/delete/{id}', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->deleteStandaloneJev((int) $args['id'], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('DELETE /voucher/journal-entry/delete/{id} ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Posts to the General Ledger (GeneralLedgerService::postJev()) — no DV status chain to ride on,
// so this is its own direct action instead of something /voucher/{id}/status triggers.
$app->post('/voucher/journal-entry/{id}/post', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->postStandaloneJev((int) $args['id'], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /voucher/journal-entry/{id}/post ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Accounting PREPARES the payment JEV for a RELEASED DV (Dr Accounts Payable / Cr Cash, built from the
// DV's check) — the "Prepare Payment JEV" action. Creates it at FOR_SECTION_CHIEF to enter the JEV
// review chain. Accounting Dept (RC-012) or superadmin only.
$app->post('/voucher/{id}/prepare-payment-jev', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->preparePaymentJev((int) $args['id'], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 201 : 400));
    } catch (\Exception $e) {
        LogError('POST /voucher/prepare-payment-jev ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Advances a PAYMENT or standalone JEV one step along its review chain (and posts it on the final step)
// — body: { status: FOR_SECTION_CHIEF | FOR_DIVISION_MANAGER | FOR_DEPARTMENT_MANAGER | POSTED }.
// Accounting Dept (RC-012) or superadmin only; SETUP JEVs are refused (they ride the DV's own chain).
$app->post('/voucher/journal-entry/{id}/jev-status', function ($request, $response, $args) use ($VoucherService) {
    try {
        $body   = $request->getParsedBody() ?: [];
        $status = trim((string) ($body['status'] ?? ''));
        if ($status === '') {
            return $response->withJson(['success' => false, 'message' => 'status is required.'], 400);
        }
        $remarks = trim((string) ($body['remarks'] ?? ''));
        $result = $VoucherService->changeJevStatus((int) $args['id'], $status, (int) $request->getAttribute('user_id'), $remarks !== '' ? $remarks : null);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /voucher/journal-entry/{id}/jev-status ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Deduction type options (e.g. Retention) for the DV form's Deductions section
$app->get('/voucher/deduction-types', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->fetchDeductionTypes();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /voucher/deduction-types ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Subsidiary Ledger code search, for the Accounting Entries grid's SL Code picker. Optional: search
$app->get('/voucher/sl-codes', function ($request, $response, $args) use ($VoucherService) {
    try {
        $params = $request->getQueryParams();
        $result = $VoucherService->fetchSlCodes((string) ($params['search'] ?? ''));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /voucher/sl-codes ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// SL Detail options scoped to one SL Code, for the Accounting Entries grid's SL Detail picker
$app->get('/voucher/sl-codes/{sl_code}/details', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->fetchSlDetails($args['sl_code']);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /voucher/sl-codes/details ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Delete — DRAFT or CANCELLED only
$app->delete('/voucher/delete/{id}', function ($request, $response, $args) use ($VoucherService) {
    try {
        $result = $VoucherService->delete($args['id']);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('DELETE /voucher/delete ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
