<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\InfraIarService;

$InfraIarService = new InfraIarService();

// IAR Module — Infrastructure tab (fims.statement_work_accomplishments). Account Code search: GET /po-dr/accounts?all=1&search=

// List (paginated; ?search= on the IAR no., RFA no., project, location, program, contractor, work items)
$app->get('/infra-iar/list', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();

    return $response->withJson($InfraIarService->fetchAlls(
        isset($params['page']) ? (int) $params['page'] : 1,
        isset($params['limit']) ? (int) $params['limit'] : 10,
        (string) ($params['search'] ?? ''),
        (string) ($params['rc_id'] ?? ''),        // filter: Regional Office/District Office
        (string) ($params['vendor_id'] ?? '')     // filter: Contractor
    ));
})->add($jwtMiddleware);

// Values of the list's Regional Office/District Office and Contractor filter dropdowns
$app->get('/infra-iar/filter-options', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->filterOptions());
})->add($jwtMiddleware);

// RFA picker: approved Infrastructure RFAs without a PO that have no IAR yet, with the project details to load
$app->get('/infra-iar/rfas', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();

    return $response->withJson($InfraIarService->searchRfas((string) ($params['search'] ?? '')));
})->add($jwtMiddleware);

// Read one (?id=)
$app->get('/infra-iar/read', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();
    $result = $InfraIarService->fetchById((int) ($params['id'] ?? 0));

    return $response->withJson($result, $result['success'] ? 200 : 404);
})->add($jwtMiddleware);

// Add
$app->post('/infra-iar/add', function ($request, $response, $args) use ($InfraIarService) {
    $result = $InfraIarService->create($request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

    return $response->withJson($result, $result['success'] ? 201 : 400);
})->add($jwtMiddleware);

// Update (?id=) — location, project cost, dates, contract duration and the Statement of Work items only
$app->put('/infra-iar/update', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();
    $result = $InfraIarService->update((int) ($params['id'] ?? 0), $request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);

// Add Accomplishment (?id=): one more Statement of Work item { description, cost, accomplishment_pct } without opening the whole edit
$app->post('/infra-iar/add-accomplishment', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();
    $result = $InfraIarService->addAccomplishment((int) ($params['id'] ?? 0), $request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);

// Pro-forma JEV (?id=): each account entry is a debit, the total is credited to the contractor's AP sub-ledger, else "Account Payable 1" (2-01-01-010-01)
$app->get('/infra-iar/proforma', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();
    $result = $InfraIarService->proforma((int) ($params['id'] ?? 0));

    return $response->withJson($result, $result['success'] ? 200 : 404);
})->add($jwtMiddleware);

// Submit the pro-forma as a Journal Entry Voucher — the same creation and auto-submit for approval as POST /po-dr/proforma-submit.
// body: { id, jev_date, fiscal_yr, book_id, particulars?, lines[] }. The JEV is recorded on the infrastructure IAR (jev_id / jev_no);
// a record that already has one cannot be submitted again (409).
$app->post('/infra-iar/proforma-submit', function ($request, $response, $args) use ($InfraIarService) {
    try {
        $body = $request->getParsedBody() ?: [];
        $id   = (int) ($body['id'] ?? 0);
        if ($id <= 0) {
            return $response->withJson(['success' => false, 'message' => 'The infrastructure IAR is required.'], 400);
        }
        if ($done = $InfraIarService->jevOf($id)) {
            return $response->withJson(['success' => false, 'message' => "The pro-forma was already submitted as Journal Entry Voucher {$done['jev_no']}.", 'data' => $done], 409);
        }
        $uid     = (int) $request->getAttribute('user_id');
        $voucher = new \PHPMaker2024\FIMS\Service\DisbursementVoucherService();
        $result  = $voucher->createStandaloneJev($body, $uid);
        if (!empty($result['success'])) {
            $jevId = (int) $result['data']['jev_id'];
            $InfraIarService->linkJev($id, $jevId, (string) $result['data']['jev_no']);
            $sub = $voucher->changeJevStatus($jevId, 'FOR_SECTION_CHIEF', $uid);   // DRAFT -> FOR_SECTION_CHIEF
            if (!empty($sub['success'])) {
                $result['data']['status'] = 'FOR_SECTION_CHIEF';
                $result['message']        = 'Journal Entry Voucher ' . $result['data']['jev_no'] . ' created and submitted for approval.';
            } else {
                $result['message'] = 'Journal Entry Voucher ' . $result['data']['jev_no'] . ' was created as a draft, but could not be submitted for approval: ' . ($sub['message'] ?? 'unknown error');
            }
        }

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /infra-iar/proforma-submit ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($jwtMiddleware);

// The cards of the Work In Process page for ONE category (?account= a WIP sub-ledger; empty = all): { ongoing, for_transfer, completed } (counts)
$app->get('/infra-iar/wip-summary', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();

    return $response->withJson($InfraIarService->wipSummary((string) ($params['account'] ?? '')));
})->add($jwtMiddleware);

// Merchandise Inventory page: the projects transferred to Completed Development for Sale (Land / Building) — one row per project.
// ?account= a tab (1-04-01-010-01 | -02), ?book_id= (the transfer JEV's book), ?rc_id= (Regional Office/District Office)
$app->get('/infra-iar/merchandise-inventory', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();

    return $response->withJson($InfraIarService->merchandiseInventory(
        isset($params['page']) ? (int) $params['page'] : 1,
        isset($params['limit']) ? (int) $params['limit'] : 10,
        (string) ($params['search'] ?? ''),
        (string) ($params['account'] ?? ''),
        (string) ($params['book_id'] ?? ''),
        (string) ($params['rc_id'] ?? '')
    ));
})->add($jwtMiddleware);

// The tabs of the Merchandise Inventory page
$app->get('/infra-iar/mi-categories', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->miCategories());
})->add($jwtMiddleware);

// Values of the Merchandise Inventory page's Book and Region filter dropdowns
$app->get('/infra-iar/mi-filter-options', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->miFilterOptions());
})->add($jwtMiddleware);

// Construction in Progress page: the records whose submitted pro-forma JEV carries a CIP account — like work-in-process, but ONE list (no ?account= tab)
$app->get('/infra-iar/construction-in-progress', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();

    return $response->withJson($InfraIarService->constructionInProgress(
        isset($params['page']) ? (int) $params['page'] : 1,
        isset($params['limit']) ? (int) $params['limit'] : 10,
        (string) ($params['search'] ?? ''),
        (string) ($params['account'] ?? ''),      // a tab: one CIP account (the record's stored category)
        (string) ($params['book_id'] ?? ''),
        (string) ($params['rc_id'] ?? ''),
        (string) ($params['status'] ?? '')        // the card clicked, by PHYSICAL accomplishment: ongoing (under 100%) | transfer (90%+) | completed (100%)
    ));
})->add($jwtMiddleware);

// The cards of the Construction in Progress page for ONE category (?account=; empty = all): { ongoing, for_transfer, completed } — by physical accomplishment
$app->get('/infra-iar/cip-summary', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();

    return $response->withJson($InfraIarService->cipSummary((string) ($params['account'] ?? '')));
})->add($jwtMiddleware);

// The tabs of the Construction in Progress page: the CIP categories
$app->get('/infra-iar/cip-categories', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->cipCategories());
})->add($jwtMiddleware);

$app->get('/infra-iar/cip-filter-options', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->cipFilterOptions());
})->add($jwtMiddleware);

// Fixed Assets page: the projects transferred from Construction in Progress to Buildings / Leased Asset Improvements / Land / Other Structures / Other Land Improvements.
// Same query string as /infra-iar/merchandise-inventory (?account= a tab, ?book_id=, ?rc_id=, ?search=).
$app->get('/infra-iar/fixed-assets', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();

    return $response->withJson($InfraIarService->fixedAssets(
        isset($params['page']) ? (int) $params['page'] : 1,
        isset($params['limit']) ? (int) $params['limit'] : 10,
        (string) ($params['search'] ?? ''),
        (string) ($params['account'] ?? ''),
        (string) ($params['book_id'] ?? ''),
        (string) ($params['rc_id'] ?? '')
    ));
})->add($jwtMiddleware);

$app->get('/infra-iar/fa-categories', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->faCategories());
})->add($jwtMiddleware);

// The cards under the MPLC tab: one per MPLC account { account_code, account_title, projects, total_amount }
$app->get('/infra-iar/fa-mplc-cards', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->faMplcCards());
})->add($jwtMiddleware);

$app->get('/infra-iar/fa-filter-options', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->faFilterOptions());
})->add($jwtMiddleware);

// Property and Equipment for Distribution page: the projects transferred to 1-04-02-090-01 Land / 1-04-02-090-02 Building / Community Facilities.
// Same query string as /infra-iar/merchandise-inventory (?account= a tab, ?book_id=, ?rc_id=, ?search=).
$app->get('/infra-iar/property-distribution', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();

    return $response->withJson($InfraIarService->propertyDistribution(
        isset($params['page']) ? (int) $params['page'] : 1,
        isset($params['limit']) ? (int) $params['limit'] : 10,
        (string) ($params['search'] ?? ''),
        (string) ($params['account'] ?? ''),
        (string) ($params['book_id'] ?? ''),
        (string) ($params['rc_id'] ?? '')
    ));
})->add($jwtMiddleware);

$app->get('/infra-iar/ped-categories', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->pedCategories());
})->add($jwtMiddleware);

$app->get('/infra-iar/ped-filter-options', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->pedFilterOptions());
})->add($jwtMiddleware);

// Work-In-Process → Transfer to MI/PED: the data of the reclassification pro-forma (?id=) — the original JEV (book read-only), the old WIP account,
// the amount, the four Debit accounts and whether the project reached 90% of completion (paid / revised contract cost)
$app->get('/infra-iar/transfer-proforma', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();
    $result = $InfraIarService->transferProforma((int) ($params['id'] ?? 0), ($params['kind'] ?? '') === 'fa' ? 'fa' : 'wip');   // ?kind=fa: Construction in Progress -> Fixed Assets

    return $response->withJson($result, $result['success'] ? 200 : 404);
})->add($jwtMiddleware);

// Submit the transfer: Debit the chosen new account, Credit the old WIP account, as a standalone JEV sent for approval (same creation as proforma-submit).
// body: { id, jev_date, debit_account, amount, particulars? } — the book comes from the original JEV, 409 when already transferred.
$app->post('/infra-iar/transfer-submit', function ($request, $response, $args) use ($InfraIarService) {
    try {
        $body = $request->getParsedBody() ?: [];
        $id   = (int) ($body['id'] ?? 0);
        if ($id <= 0) {
            return $response->withJson(['success' => false, 'message' => 'The infrastructure IAR is required.'], 400);
        }
        [$payload, $err] = $InfraIarService->prepareTransfer($id, $body, ($body['kind'] ?? '') === 'fa' ? 'fa' : 'wip');   // body.kind = 'fa': Transfer to Fixed Assets (Construction in Progress page)
        if ($err) {
            return $response->withJson(['success' => false, 'message' => $err[0]], $err[1]);
        }
        $account = $payload['_account'];
        $amount  = $payload['_amount'];
        $oldAcct = $payload['lines'][1]['account_code'];   // the Credit = the old WIP account
        unset($payload['_account'], $payload['_amount']);
        $uid     = (int) $request->getAttribute('user_id');
        $voucher = new \PHPMaker2024\FIMS\Service\DisbursementVoucherService();
        $result  = $voucher->createStandaloneJev($payload, $uid);
        if (!empty($result['success'])) {
            $jevId = (int) $result['data']['jev_id'];
            $InfraIarService->linkTransfer($id, $jevId, (string) $result['data']['jev_no'], $account, $amount, $oldAcct);
            $sub = $voucher->changeJevStatus($jevId, 'FOR_SECTION_CHIEF', $uid);   // DRAFT -> FOR_SECTION_CHIEF
            if (!empty($sub['success'])) {
                $result['data']['status'] = 'FOR_SECTION_CHIEF';
                $result['message']        = 'Journal Entry Voucher ' . $result['data']['jev_no'] . ' created and submitted for approval.';
            } else {
                $result['message'] = 'Journal Entry Voucher ' . $result['data']['jev_no'] . ' was created as a draft, but could not be submitted for approval: ' . ($sub['message'] ?? 'unknown error');
            }
        }

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /infra-iar/transfer-submit ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($jwtMiddleware);

// The SWA number the next record will get (shown read-only in the Add form; the number is assigned again when saving)
$app->get('/infra-iar/next-number', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson(['success' => true, 'data' => ['swa_number' => $InfraIarService->nextSwaNumber()]]);
})->add($jwtMiddleware);

// Work in Process page: the infrastructure records whose pro-forma JEV was submitted and carries the Work-in-Process account (1-04-03-020) — one row per project
$app->get('/infra-iar/work-in-process', function ($request, $response, $args) use ($InfraIarService) {
    $params = $request->getQueryParams();

    return $response->withJson($InfraIarService->workInProcess(
        isset($params['page']) ? (int) $params['page'] : 1,
        isset($params['limit']) ? (int) $params['limit'] : 10,
        (string) ($params['search'] ?? ''),
        (string) ($params['account'] ?? ''),      // a tab: one WIP sub-ledger (1-04-03-020-01 …)
        (string) ($params['book_id'] ?? ''),      // filter: Book of Accounts
        (string) ($params['rc_id'] ?? ''),        // filter: Region (Regional Office/District Office)
        (string) ($params['status'] ?? '')        // the card clicked: ongoing (default) | transfer (90%+, not transferred) | completed (transferred, or 100% paid)
    ));
})->add($jwtMiddleware);

// Values of the Work in Process page's Book and Region filter dropdowns
$app->get('/infra-iar/wip-filter-options', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->wipFilterOptions());
})->add($jwtMiddleware);

// The tabs of the Work in Process page: the WIP sub-ledgers
$app->get('/infra-iar/wip-categories', function ($request, $response, $args) use ($InfraIarService) {
    return $response->withJson($InfraIarService->wipCategories());
})->add($jwtMiddleware);
