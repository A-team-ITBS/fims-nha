<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\GeneralLedgerService;

// General Ledger reads (fims.v_journals / fims.v_trial_balance — see GeneralLedgerService.php and
// system_docs/ERD/README.md §2.8). Posting itself (postJev()/reverseJev()) is never called from a
// route — it only ever runs inside DisbursementVoucherService::changeStatus(), so there is no
// POST/PUT here, read-only like /voucher/journal-entries. Every endpoint below accepts an optional
// book_id ("Book" filter — see "01 - table/41" and "03 - alter_table/30"), same as the fiscal_yr
// filter they already had.
$GeneralLedgerService = new GeneralLedgerService();

$glIntOrNull = fn ($v): ?int => $v !== null && $v !== '' ? (int) $v : null;

$journalsFilterKeys = ['fiscal_yr', 'book_id', 'account_code', 'jev_no', 'dv_no'];

// Journals (General Journal) register, paged. Optional: page, limit, fiscal_yr, book_id, account_code, jev_no, dv_no
$app->get('/general_ledger/journals', function ($request, $response, $args) use ($GeneralLedgerService, $journalsFilterKeys) {
    try {
        $params  = $request->getQueryParams();
        $filters = array_intersect_key($params, array_flip($journalsFilterKeys));
        $result  = $GeneralLedgerService->fetchJournals(
            isset($params['page']) ? (int) $params['page'] : 1,
            isset($params['limit']) ? (int) $params['limit'] : 25,
            $filters
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/journals ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Check Disbursements Journal — fims.checks, not paged. Optional: fiscal_yr, book_id, search
$app->get('/general_ledger/ckdj', function ($request, $response, $args) use ($GeneralLedgerService, $glIntOrNull) {
    try {
        $params = $request->getQueryParams();
        $result = $GeneralLedgerService->fetchCkdj(
            $glIntOrNull($params['fiscal_yr'] ?? null),
            $params['search'] ?? null,
            $glIntOrNull($params['book_id'] ?? null)
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/ckdj ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Advice to Debit Account Disbursement Journal — fims.ada_releases, not paged. Optional: fiscal_yr, book_id, search
$app->get('/general_ledger/adadj', function ($request, $response, $args) use ($GeneralLedgerService, $glIntOrNull) {
    try {
        $params = $request->getQueryParams();
        $result = $GeneralLedgerService->fetchAdadj(
            $glIntOrNull($params['fiscal_yr'] ?? null),
            $params['search'] ?? null,
            $glIntOrNull($params['book_id'] ?? null)
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/adadj ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Cash Receipts Journal — fims.official_receipts/official_receipt_lines, not paged. Optional: fiscal_yr, book_id, search
$app->get('/general_ledger/crdj', function ($request, $response, $args) use ($GeneralLedgerService, $glIntOrNull) {
    try {
        $params = $request->getQueryParams();
        $result = $GeneralLedgerService->fetchCrdj(
            $glIntOrNull($params['fiscal_yr'] ?? null),
            $params['search'] ?? null,
            $glIntOrNull($params['book_id'] ?? null)
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/crdj ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Trial Balance, as of whatever has been posted so far. Optional: fiscal_yr, book_id
$app->get('/general_ledger/trial-balance', function ($request, $response, $args) use ($GeneralLedgerService, $glIntOrNull) {
    try {
        $params = $request->getQueryParams();
        $result = $GeneralLedgerService->fetchTrialBalance(
            $glIntOrNull($params['fiscal_yr'] ?? null),
            $glIntOrNull($params['book_id'] ?? null)
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/trial-balance ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// General Ledger — one account's ledger card with a running balance, or (account_code omitted)
// every account's ledger card at once — the page's default view before one is picked. Optional:
// account_code, fiscal_yr, book_id. Account picker: reuse GET /chart_of_accounts/all
// (posting_allowed accounts) — not duplicated here.
$app->get('/general_ledger/ledger', function ($request, $response, $args) use ($GeneralLedgerService, $glIntOrNull) {
    try {
        $params      = $request->getQueryParams();
        $accountCode = trim((string) ($params['account_code'] ?? ''));
        $result      = $GeneralLedgerService->fetchGeneralLedger(
            $accountCode !== '' ? $accountCode : null,
            $glIntOrNull($params['fiscal_yr'] ?? null),
            $glIntOrNull($params['book_id'] ?? null),
            $glIntOrNull($params['month'] ?? null),
            trim((string) ($params['fund'] ?? '')) ?: null
        );

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/ledger ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// General Ledger drill-down — every SL account under ONE GL account (account_code, required), each with its
// own JEV lines + running balance. Powers the "click a GL code" popup on the General Ledger page — see
// GeneralLedgerService::fetchGlSubLedgers(). Optional: fiscal_yr, book_id.
$app->get('/general_ledger/ledger-sl', function ($request, $response, $args) use ($GeneralLedgerService, $glIntOrNull) {
    try {
        $params      = $request->getQueryParams();
        $accountCode = trim((string) ($params['account_code'] ?? ''));
        if ($accountCode === '') {
            return $response->withJson(['success' => false, 'message' => 'account_code is required.'], 400);
        }
        $result = $GeneralLedgerService->fetchGlSubLedgers(
            $accountCode,
            $glIntOrNull($params['fiscal_yr'] ?? null),
            $glIntOrNull($params['book_id'] ?? null)
        );

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/ledger-sl ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// General Ledger page tree (GL → SL → sub-SL, with rolled-up totals) and one account's own postings (lazy, on click).
// See GeneralLedgerService::fetchLedgerTree() / fetchAccountPostings(). Optional: fiscal_yr, book_id.
$app->get('/general_ledger/tree', function ($request, $response, $args) use ($GeneralLedgerService, $glIntOrNull) {
    try {
        $params = $request->getQueryParams();
        $result = $GeneralLedgerService->fetchLedgerTree($glIntOrNull($params['fiscal_yr'] ?? null), $glIntOrNull($params['book_id'] ?? null), $glIntOrNull($params['month'] ?? null), trim((string) ($params['fund'] ?? '')) ?: null);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/tree ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/general_ledger/funds', function ($request, $response, $args) use ($GeneralLedgerService) {
    try {
        $result = $GeneralLedgerService->fetchFunds();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/funds ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->get('/general_ledger/postings', function ($request, $response, $args) use ($GeneralLedgerService, $glIntOrNull) {
    try {
        $params      = $request->getQueryParams();
        $accountCode = trim((string) ($params['account_code'] ?? ''));
        if ($accountCode === '') {
            return $response->withJson(['success' => false, 'message' => 'account_code is required.'], 400);
        }
        $result = $GeneralLedgerService->fetchAccountPostings($accountCode, $glIntOrNull($params['fiscal_yr'] ?? null), $glIntOrNull($params['book_id'] ?? null), $glIntOrNull($params['month'] ?? null), trim((string) ($params['fund'] ?? '')) ?: null);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/postings ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Schedule of one GL account as of a date — its balance by Sector / Region → office → project → contract (the NHA
// "SCHEDULE OF …" report). Required: account_code, as_of (YYYY-MM-DD). Optional: book_id. See GeneralLedgerService::fetchSchedule().
$app->get('/general_ledger/schedule', function ($request, $response, $args) use ($GeneralLedgerService, $glIntOrNull) {
    try {
        $params      = $request->getQueryParams();
        $accountCode = trim((string) ($params['account_code'] ?? ''));
        $asOf        = trim((string) ($params['as_of'] ?? ''));
        if ($accountCode === '') {
            return $response->withJson(['success' => false, 'message' => 'account_code is required.'], 400);
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $asOf);
        if (!$d || $d->format('Y-m-d') !== $asOf) {
            return $response->withJson(['success' => false, 'message' => 'as_of must be a date (YYYY-MM-DD).'], 400);
        }
        $result = $GeneralLedgerService->fetchSchedule($accountCode, $asOf, $glIntOrNull($params['book_id'] ?? null));

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/schedule ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Subsidiary Ledger — one SL account's ledger card with a running balance, or (sl_code omitted)
// every SL account's ledger card at once — the page's default view before one is picked. An "SL
// account" is a child fims.chart_of_accounts row (parent_account_id IS NOT NULL) under a control
// account — see GeneralLedgerService::fetchSubsidiaryLedger(). Optional: sl_code, fiscal_yr,
// book_id. SL picker: reuse GET /chart_of_accounts/all?postable_only=1, filtered client-side to
// rows with a parent_account_id — not duplicated here.
$app->get('/general_ledger/subsidiary-ledger', function ($request, $response, $args) use ($GeneralLedgerService, $glIntOrNull) {
    try {
        $params = $request->getQueryParams();
        $slCode = trim((string) ($params['sl_code'] ?? ''));
        $slCode = $slCode !== '' ? $slCode : null;
        $result     = $GeneralLedgerService->fetchSubsidiaryLedger(
            $slCode,
            $glIntOrNull($params['fiscal_yr'] ?? null),
            $glIntOrNull($params['book_id'] ?? null)
        );

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/subsidiary-ledger ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
