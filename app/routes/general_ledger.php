<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\GeneralLedgerService;

// General Ledger reads (fims.v_journals / fims.v_trial_balance — see GeneralLedgerService.php and
// system_docs/ERD/README.md §2.8). Posting itself (postJev()/reverseJev()) is never called from a
// route — it only ever runs inside DisbursementVoucherService::changeStatus(), so there is no
// POST/PUT here, read-only like /voucher/journal-entries.
$GeneralLedgerService = new GeneralLedgerService();

$journalsFilterKeys = ['fiscal_yr', 'account_code', 'jev_no', 'dv_no'];

// Journals (General Journal) register, paged. Optional: page, limit, fiscal_yr, account_code, jev_no, dv_no
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

// Trial Balance, as of whatever has been posted so far. Optional: fiscal_yr
$app->get('/general_ledger/trial-balance', function ($request, $response, $args) use ($GeneralLedgerService) {
    try {
        $fiscalYr = $request->getQueryParams()['fiscal_yr'] ?? null;
        $result   = $GeneralLedgerService->fetchTrialBalance($fiscalYr !== null && $fiscalYr !== '' ? (int) $fiscalYr : null);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /general_ledger/trial-balance ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
