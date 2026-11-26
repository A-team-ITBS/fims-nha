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

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

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

        $rows = $this->describeRows($id, $bank, $data['rows']);

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

    // Reshapes getLedger()'s rows into the bank-SOA column shape, with two
    // deliberate departures from a literal 1:1 copy of our own GL data:
    //
    // 1. Debit/Credit are SWAPPED relative to fims.general_ledger's own
    //    columns. Our GL follows standard double-entry for an asset account
    //    (Debit increases Cash, Credit decreases it — see
    //    GeneralLedgerService::createPaymentJev()'s "credit the bank
    //    account" comment for a check payment). A bank-issued SOA instead
    //    labels from the depositor's point of view — Debit = the bank
    //    debited/withdrew from your account, Credit = the bank credited/
    //    deposited into it — the OPPOSITE sign. Confirmed against two real
    //    bank SOA exports by hand: every Debit-column row decreases the
    //    running Balance, every Credit-column row increases it. The balance
    //    itself is untouched by this — getLedger() already computed it
    //    correctly (debit - credit, our own convention); only which output
    //    column each amount is displayed under changes here.
    //
    // 2. description is replaced with a short, bank-style ALL-CAPS code
    //    (LOCAL CHECK, CASH DEPOSIT, ...) instead of our own verbose
    //    internal text ("Payment via CHECK NO. X") — these won't match any
    //    one bank's actual proprietary codes (every bank's are different
    //    and not published), just the terse style real SOAs use, which is
    //    what this generator is for: looking like a bank statement, not
    //    reproducing one bank's exact internal vocabulary.
    private function describeRows(int $bankAccountId, array $bank, array $rows): array
    {
        $chequeNumbers = [];
        $ftlaNumbers = [];
        foreach ($rows as $row) {
            $docRefNo = trim((string) ($row['doc_ref_no'] ?? ''));
            if ($docRefNo === '') {
                continue;
            }
            if (preg_match('/^CHECK NO\.\s*(.+)$/i', $docRefNo, $m)) {
                $chequeNumbers[trim($m[1])] = true;
            } else {
                $ftlaNumbers[$docRefNo] = true;
            }
        }

        $payeeByCheque = [];
        if ($chequeNumbers) {
            $list = implode(',', array_map(fn ($c) => $this->str($c), array_keys($chequeNumbers)));
            $checkRows = App\ExecuteRowsAssociative(
                "SELECT check_number, payee FROM fims.checks WHERE bank_account_id = {$bankAccountId} AND check_number IN ({$list})",
                'DB'
            ) ?: [];
            foreach ($checkRows as $c) {
                $payeeByCheque[$c['check_number']] = $c['payee'];
            }
        }

        $isFtlaNumber = [];
        if ($ftlaNumbers) {
            $list = implode(',', array_map(fn ($c) => $this->str($c), array_keys($ftlaNumbers)));
            $ftlaRows = App\ExecuteRowsAssociative(
                "SELECT ftla_number FROM fims.fund_transfers WHERE ftla_number IN ({$list})",
                'DB'
            ) ?: [];
            foreach ($ftlaRows as $f) {
                $isFtlaNumber[$f['ftla_number']] = true;
            }
        }

        return array_map(function ($row) use ($bank, $payeeByCheque, $isFtlaNumber) {
            $docRefNo = trim((string) ($row['doc_ref_no'] ?? ''));
            $chequeNumber = $this->extractChequeNumber($docRefNo ?: null);
            $moneyOut = $row['credit'] > 0; // our GL convention: credit = disbursement

            if ($chequeNumber !== '' && isset($payeeByCheque[$chequeNumber])) {
                $description = 'LOCAL CHECK';
            } elseif ($docRefNo !== '' && isset($isFtlaNumber[$docRefNo])) {
                $description = $moneyOut ? 'FUND TRANSFER AUTH' : 'FUND TRANSFER CREDIT';
            } elseif ($moneyOut) {
                $description = 'MISCELLANEOUS DEBIT';
            } else {
                $description = 'CASH DEPOSIT';
            }

            return [
                'date' => $row['posting_date'],
                'description' => $description,
                'debit' => $row['credit'] > 0 ? $row['credit'] : null,
                'credit' => $row['debit'] > 0 ? $row['debit'] : null,
                'balance' => $row['balance'],
                'branch' => $bank['bank_branch'],
                'cheque_number' => $chequeNumber,
            ];
        }, $rows);
    }
}
