<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BookService;

$BookService = new BookService();

// Fetch all — no pagination (for dropdowns, e.g. the "Book" filter on General Ledger/Subsidiary
// Ledger/Journals/Trial Balance — see GeneralLedgerService.php)
$app->get('/books/all', function ($request, $response, $args) use ($BookService) {
    try {
        $result = $BookService->fetchAlls();

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
