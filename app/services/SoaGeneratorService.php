<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs the SOA Generator (Reports > SOA Generator, app/pages/soa-generator-page.php).
 *
 * Produces a "Statement of Account" shaped exactly like the bank-issued SOA
 * format the Cash Position module's upload flow already parses (Date /
 * Description / Debit / Credit / Balance / Branch / Cheque Number — see
 * CashPositionSoaService::parseXlsx() and fims.soa_transactions) — but built
 * entirely from this system's own recorded transactions, so it can stand in
 * as the "book" side of a reconciliation wherever a bank's real SOA isn't
 * available, or be exported and re-uploaded to compare against one.
 *
 * Does not re-derive transactions from fims.checks / fims.fund_transfers /
 * fims.collection_report_deposits directly — those all eventually post a
 * fims.general_ledger line against the bank account's tagged GL account code
 * (see BankReconciliationService::getLedger(), the single existing "book
 * transactions for this bank account" query), so this service just reshapes
 * that same ledger into the bank-SOA column shape rather than maintaining a
 * second, competing read path.
 */
class SoaGeneratorService
{
    private string $tableBankAccounts = 'fims.bank_accounts';

    private function ok($data = [])
    {
        return json_encode(['success' => true, 'data' => $data]);
    }

    private function fail(string $message)
    {
        return json_encode(['success' => false, 'message' => $message]);
    }

    // ── Bank accounts for the "Bank Account" dropdown ──
    public function getBankAccounts()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT bank_account_id, account_name, account_number, bank_name, bank_branch, account_type
             FROM {$this->tableBankAccounts}
             WHERE status = 'ACTIVE'
             ORDER BY account_name",
            'DB'
        ) ?: [];

        return $this->ok($rows);
    }

    // ── The SOA itself: reshapes BankReconciliationService::getLedger()'s
    // book ledger into Date/Description/Debit/Credit/Balance/Branch/Cheque
    // Number rows, plus the bank account's own header fields. ──
    public function generate($bankAccountId, $periodStart, $periodEnd)
    {
        $id = (int) $bankAccountId;
        $bank = App\ExecuteRow(
            "SELECT bank_account_id, account_name, account_number, bank_name, bank_branch, account_type
             FROM {$this->tableBankAccounts} WHERE bank_account_id = {$id}",
            'DB'
        );
        if (!$bank) {
            return $this->fail('Bank account not found.');
        }

        $ledgerJson = (new BankReconciliationService())->getLedger($id, $periodStart, $periodEnd);
        $ledger     = json_decode($ledgerJson, true);
        if (!($ledger['success'] ?? false)) {
            return $this->fail($ledger['message'] ?? 'Could not build the ledger for this bank account.');
        }
        $data = $ledger['data'];

        if (empty($data['has_gl_account'])) {
            return $this->ok([
                'bank' => $bank,
                'has_gl_account' => false,
                'beginning_balance' => 0,
                'rows' => [],
                'ending_balance' => 0,
            ]);
        }

        $rows = array_map(function ($row) use ($bank) {
            return [
                'date' => $row['posting_date'],
                'description' => $row['description'],
                'debit' => $row['debit'] > 0 ? $row['debit'] : null,
                'credit' => $row['credit'] > 0 ? $row['credit'] : null,
                'balance' => $row['balance'],
                'branch' => $bank['bank_branch'],
                // Checks post their JEV with doc_ref_no = 'CHECK NO. <check_number>' (see
                // CheckProcessingService::saveCheck()) — every other source (collections,
                // fund transfers, adjustments) has no check number, left blank.
                'cheque_number' => $this->extractChequeNumber($row['doc_ref_no'] ?? null),
            ];
        }, $data['rows']);

        return $this->ok([
            'bank' => $bank,
            'has_gl_account' => true,
            'beginning_balance' => $data['beginning_balance'],
            'rows' => $rows,
            'ending_balance' => $data['ending_balance'],
        ]);
    }

    private function extractChequeNumber(?string $docRefNo): string
    {
        if ($docRefNo && preg_match('/^CHECK NO\.\s*(.+)$/i', trim($docRefNo), $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
