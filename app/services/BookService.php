<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Lookup for fims.books (Main Office, CISFA, NGC, MPLC/PPMO, PPMO — see
 * "01 - table/41"). Read-only — this is a short, fixed list of NHA
 * office/program entities, not something a page needs to create/edit, so no
 * create()/update()/delete() here, unlike TypeOfFundService.
 */
class BookService
{
    private string $table = 'fims.books';

    /** All books, for the "Book" filter dropdown on General Ledger/Subsidiary Ledger/Journals/Trial Balance. */
    public function fetchAlls(): array
    {
        try {
            $data = App\ExecuteRows("SELECT book_id, code, name FROM {$this->table} ORDER BY book_id", 'DB');

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
