<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs the Collections module (app/pages/collections-page.php) —
 * a Collecting Officer's periodic "Collection Report": what was collected
 * (by GL/revenue account, e.g. Installment Sales Receivable, Permit Fees —
 * see fims.collection_report_lines) and what of that was deposited to the
 * bank, by period covered (see fims.collection_report_deposits). Same
 * "record first, journalize later" shape as fims.official_receipts — not
 * yet posted to fims.general_ledger. See migration
 * "51 - fims_collection_reports.sql".
 *
 * The two sections' totals are independent — this report does not enforce
 * that deposits reconcile to collections.
 *
 * Region/Office is a real FK into fims.responsibility_code (rc_id) — unlike
 * fims.bank_accounts.rc_id (see BankManagementService's docblock), this is
 * a brand-new table, so there's no legacy reason to skip the FK.
 */
class CollectionsService
{
    private string $tableReports   = 'fims.collection_reports';
    private string $tableLines     = 'fims.collection_report_lines';
    private string $tableDeposits  = 'fims.collection_report_deposits';
    private string $tableRc        = 'fims.responsibility_code';
    private string $tableCoa       = 'fims.chart_of_accounts';
    private string $tableBankAccounts = 'fims.bank_accounts';

    // ── small SQL helpers (same shape as BankManagementService) ──

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function dateOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function num(mixed $v): string
    {
        $n = (float) str_replace(',', '', (string) ($v ?? 0));

        return (string) $n;
    }

    private function ok($data = [])
    {
        return json_encode(["success" => true, "data" => $data]);
    }

    private function dataTable($draw, array $rows)
    {
        return json_encode([
            "draw" => (int) $draw,
            "recordsTotal" => count($rows),
            "recordsFiltered" => count($rows),
            "data" => $rows,
        ]);
    }

    // ── List (DataTables-shaped, filterable) ──
    public function getList($params)
    {
        $where = ['1=1'];
        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $needle = $this->str('%' . $search . '%');
            $where[] = "(cr.collecting_office ILIKE {$needle} OR rc.rc_code ILIKE {$needle} OR rc.rc_name ILIKE {$needle})";
        }
        $whereSql = implode(' AND ', $where);

        // deposit_count/swept_deposit_count let the list show a per-report
        // Swept/Partially Swept/Not Swept indicator (is_swept comes from
        // Fund Sweeping — see migration "56 - collection_report_deposits_
        // add_swept_tracking.sql" — without re-fetching every deposit row).
        $rows = App\ExecuteRowsAssociative(
            "SELECT cr.*, rc.rc_code, rc.rc_name,
                    COALESCE(dep.deposit_count, 0) AS deposit_count,
                    COALESCE(dep.swept_deposit_count, 0) AS swept_deposit_count
             FROM {$this->tableReports} cr
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = cr.rc_id
             LEFT JOIN (
                 SELECT collection_report_id, COUNT(*) AS deposit_count,
                        COUNT(*) FILTER (WHERE is_swept) AS swept_deposit_count
                 FROM {$this->tableDeposits}
                 GROUP BY collection_report_id
             ) dep ON dep.collection_report_id = cr.collection_report_id
             WHERE {$whereSql}
             ORDER BY cr.report_date DESC, cr.collection_report_id DESC",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    // ── Single report (header + both line sections) ──
    public function getReport($id)
    {
        $report = $this->fetchReport((int) $id);
        if (!$report) {
            return json_encode(["success" => false, "message" => "Collection report not found."]);
        }
        $report['lines'] = App\ExecuteRowsAssociative(
            "SELECT crl.*, coa.account_code, coa.account_title
             FROM {$this->tableLines} crl
             LEFT JOIN {$this->tableCoa} coa ON coa.account_id = crl.coa_account_id
             WHERE crl.collection_report_id = {$report['collection_report_id']}
             ORDER BY crl.line_number",
            'DB'
        ) ?: [];
        $report['deposits'] = App\ExecuteRowsAssociative(
            "SELECT crd.*, ba.account_name, ba.bank_name
             FROM {$this->tableDeposits} crd
             LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = crd.bank_account_id
             WHERE crd.collection_report_id = {$report['collection_report_id']}
             ORDER BY crd.line_number",
            'DB'
        ) ?: [];

        return $this->ok($report);
    }

    private function fetchReport(int $id): ?array
    {
        return App\ExecuteRow(
            "SELECT cr.*, rc.rc_code, rc.rc_name
             FROM {$this->tableReports} cr
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = cr.rc_id
             WHERE cr.collection_report_id = {$id}",
            'DB'
        ) ?: null;
    }

    // Validates a submitted Region/Office against fims.responsibility_code
    // — a real FK on this table (see this class's docblock), but validated
    // here first too so a bad rc_id surfaces as a clean message instead of
    // an opaque DB constraint error.
    private function rcIdExists(string $rcId): bool
    {
        return (bool) App\ExecuteRow(
            "SELECT rc_id FROM {$this->tableRc} WHERE rc_id = " . $this->str($rcId),
            'DB'
        );
    }

    private function coaAccountIdExists(string $accountId): bool
    {
        return (bool) App\ExecuteRow(
            "SELECT account_id FROM {$this->tableCoa} WHERE account_id = " . $this->str($accountId),
            'DB'
        );
    }

    private function bankAccountIdExists(string $bankAccountId): bool
    {
        return (bool) App\ExecuteRow(
            "SELECT bank_account_id FROM {$this->tableBankAccounts} WHERE bank_account_id = " . $this->str($bankAccountId),
            'DB'
        );
    }

    // Validates and totals the "Collection(s)" lines submitted in $data —
    // shared by store()/update() so both reject the same bad input the
    // same way before anything is written.
    private function validateLines(array $lines): array
    {
        $clean = [];
        $total = 0.0;
        foreach ($lines as $line) {
            $coaAccountId = trim((string) ($line['coa_account_id'] ?? ''));
            $amount = (float) str_replace(',', '', (string) ($line['amount'] ?? 0));
            if ($coaAccountId === '' || $amount <= 0) {
                continue;
            }
            if (!$this->coaAccountIdExists($coaAccountId)) {
                return ['error' => 'One of the Collection lines has an invalid GL Account.'];
            }
            $clean[] = ['coa_account_id' => $coaAccountId, 'amount' => $amount];
            $total += $amount;
        }
        if (!$clean) {
            return ['error' => 'At least one Collection line is required.'];
        }

        return ['lines' => $clean, 'total' => $total];
    }

    // Validates and totals the "Deposit" lines — Bank + Period Covered
    // (From/To) + Amount.
    // Does NOT enforce "at least one deposit" itself — store() always
    // requires one (a brand-new report can't have any swept deposits yet);
    // update() only requires one when the report doesn't already have
    // swept deposits of its own covering that requirement (see its own
    // check), since a fully-swept report's deposits aren't resubmitted at
    // all (see this class's docblock / "56 - collection_report_deposits_
    // add_swept_tracking.sql").
    private function validateDeposits(array $deposits): array
    {
        $clean = [];
        $total = 0.0;
        foreach ($deposits as $dep) {
            $bankAccountId = trim((string) ($dep['bank_account_id'] ?? ''));
            $amount = (float) str_replace(',', '', (string) ($dep['amount'] ?? 0));
            if ($bankAccountId === '' || $amount <= 0) {
                continue;
            }
            if (!$this->bankAccountIdExists($bankAccountId)) {
                return ['error' => 'One of the Deposit lines has an invalid Bank Account.'];
            }
            $clean[] = [
                'bank_account_id' => $bankAccountId,
                'period_from' => trim((string) ($dep['period_from'] ?? '')),
                'period_to' => trim((string) ($dep['period_to'] ?? '')),
                'amount' => $amount,
            ];
            $total += $amount;
        }

        return ['deposits' => $clean, 'total' => $total];
    }

    private function insertLines(int $reportId, array $lines): void
    {
        foreach ($lines as $i => $line) {
            App\Execute(
                "INSERT INTO {$this->tableLines} (collection_report_id, line_number, coa_account_id, amount)
                 VALUES ({$reportId}, " . ($i + 1) . ', ' . $this->str($line['coa_account_id']) . ', ' . $this->num($line['amount']) . ')',
                'DB'
            );
        }
    }

    // $startingLineNumber lets update() continue numbering after whatever
    // already-swept rows it left untouched, instead of always starting at
    // 1 (which would collide with uq_collection_report_deposits_report_line
    // when a swept row still occupies line_number 1).
    private function insertDeposits(int $reportId, array $deposits, int $startingLineNumber = 1): void
    {
        foreach ($deposits as $i => $dep) {
            App\Execute(
                "INSERT INTO {$this->tableDeposits} (collection_report_id, line_number, bank_account_id, period_from, period_to, amount)
                 VALUES ({$reportId}, " . ($startingLineNumber + $i) . ', ' . $this->str($dep['bank_account_id']) . ', '
                    . $this->dateOrNull($dep['period_from']) . ', ' . $this->dateOrNull($dep['period_to']) . ', ' . $this->num($dep['amount']) . ')',
                'DB'
            );
        }
    }

    // ── Create ──
    public function store($data)
    {
        $reportDate = trim((string) ($data['report_date'] ?? ''));
        if ($reportDate === '') {
            return json_encode(["success" => false, "message" => "Date is required."]);
        }
        $rcId = trim((string) ($data['rc_id'] ?? ''));
        if ($rcId === '' || !$this->rcIdExists($rcId)) {
            return json_encode(["success" => false, "message" => "Select a valid Region."]);
        }

        $lineResult = $this->validateLines((array) ($data['lines'] ?? []));
        if (isset($lineResult['error'])) {
            return json_encode(["success" => false, "message" => $lineResult['error']]);
        }
        $depositResult = $this->validateDeposits((array) ($data['deposits'] ?? []));
        if (isset($depositResult['error'])) {
            return json_encode(["success" => false, "message" => $depositResult['error']]);
        }
        if (!$depositResult['deposits']) {
            return json_encode(["success" => false, "message" => "At least one Deposit line is required."]);
        }

        $userId = (int) App\CurrentUserID();
        $collectingOffice = trim((string) ($data['collecting_office'] ?? ''));

        $row = App\ExecuteRow(
            "INSERT INTO {$this->tableReports}
                (report_date, rc_id, collecting_office, total_collections, total_deposits, created_by)
             VALUES (" . $this->str($reportDate) . ', ' . $this->str($rcId) . ', ' . $this->strOrNull($collectingOffice) . ', '
                . $this->num($lineResult['total']) . ', ' . $this->num($depositResult['total']) . ", {$userId})
             RETURNING collection_report_id",
            'DB'
        );
        $reportId = (int) $row['collection_report_id'];

        $this->insertLines($reportId, $lineResult['lines']);
        $this->insertDeposits($reportId, $depositResult['deposits']);

        return $this->ok(['collection_report_id' => $reportId]);
    }

    // ── Update ── (lines are replaced wholesale — delete then reinsert.
    // Deposits are NOT: a deposit already swept by Fund Sweeping
    // (is_swept = TRUE — see "56 - collection_report_deposits_add_swept_
    // tracking.sql") is left completely untouched here. Only un-swept
    // deposit rows get deleted and reinserted from $data['deposits'], which
    // the Edit modal never includes swept rows in to begin with (they're
    // shown read-only, not resubmitted) — this is defense in depth so a
    // direct API call can't accidentally erase a deposit's swept tracking
    // by resubmitting it unmodified.)
    public function update($id, $data)
    {
        $reportId = (int) $id;
        $before = $this->fetchReport($reportId);
        if (!$before) {
            return json_encode(["success" => false, "message" => "Collection report not found."]);
        }

        $reportDate = trim((string) ($data['report_date'] ?? ''));
        if ($reportDate === '') {
            return json_encode(["success" => false, "message" => "Date is required."]);
        }
        $rcId = trim((string) ($data['rc_id'] ?? ''));
        if ($rcId === '' || !$this->rcIdExists($rcId)) {
            return json_encode(["success" => false, "message" => "Select a valid Region."]);
        }

        $lineResult = $this->validateLines((array) ($data['lines'] ?? []));
        if (isset($lineResult['error'])) {
            return json_encode(["success" => false, "message" => $lineResult['error']]);
        }
        $depositResult = $this->validateDeposits((array) ($data['deposits'] ?? []));
        if (isset($depositResult['error'])) {
            return json_encode(["success" => false, "message" => $depositResult['error']]);
        }

        $sweptRow = App\ExecuteRow(
            "SELECT COALESCE(SUM(amount), 0) AS total, COALESCE(MAX(line_number), 0) AS max_line
             FROM {$this->tableDeposits} WHERE collection_report_id = {$reportId} AND is_swept = TRUE",
            'DB'
        );
        $sweptTotal = (float) ($sweptRow['total'] ?? 0);
        $sweptMaxLine = (int) ($sweptRow['max_line'] ?? 0);
        if (!$depositResult['deposits'] && $sweptTotal <= 0) {
            return json_encode(["success" => false, "message" => "At least one Deposit line is required."]);
        }

        $collectingOffice = trim((string) ($data['collecting_office'] ?? ''));
        $totalDeposits = $sweptTotal + $depositResult['total'];

        App\Execute(
            "UPDATE {$this->tableReports} SET
                report_date = " . $this->str($reportDate) . ",
                rc_id = " . $this->str($rcId) . ",
                collecting_office = " . $this->strOrNull($collectingOffice) . ",
                total_collections = " . $this->num($lineResult['total']) . ",
                total_deposits = " . $this->num($totalDeposits) . ",
                updated_at = CURRENT_TIMESTAMP
             WHERE collection_report_id = {$reportId}",
            'DB'
        );

        App\Execute("DELETE FROM {$this->tableLines} WHERE collection_report_id = {$reportId}", 'DB');
        App\Execute("DELETE FROM {$this->tableDeposits} WHERE collection_report_id = {$reportId} AND is_swept = FALSE", 'DB');
        $this->insertLines($reportId, $lineResult['lines']);
        $this->insertDeposits($reportId, $depositResult['deposits'], $sweptMaxLine + 1);

        return $this->ok(['collection_report_id' => $reportId]);
    }

    // ── Delete ── (lines/deposits cascade via ON DELETE CASCADE)
    public function delete($id)
    {
        $reportId = (int) $id;
        if (!$this->fetchReport($reportId)) {
            return json_encode(["success" => false, "message" => "Collection report not found."]);
        }
        App\Execute("DELETE FROM {$this->tableReports} WHERE collection_report_id = {$reportId}", 'DB');

        return $this->ok(['collection_report_id' => $reportId]);
    }

    // ── Lookups ──
    public function getResponsibilityCenters()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT rc_id, rc_code, rc_name FROM {$this->tableRc} WHERE status = 'Active' ORDER BY rc_code",
            'DB'
        );

        return $this->ok($rows ?: []);
    }

    // Posting-allowed accounts only — the Collection(s) section always
    // posts an amount against a specific revenue/receivable account, same
    // restriction fims.chart_of_accounts.posting_allowed already encodes
    // elsewhere (e.g. fims.general_ledger entries). A plain <select> (not
    // a search-as-you-type picker, unlike bank-management's GL/SL
    // picker) — simpler widget, matches the sketch's "[select]" box, and
    // native <select> already supports type-ahead for ~1,000 options.
    public function getGlAccounts()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT account_id, account_code, account_title FROM {$this->tableCoa}
             WHERE status = 'Active' AND posting_allowed = TRUE
             ORDER BY account_code",
            'DB'
        );

        return $this->ok($rows ?: []);
    }

    public function getBankAccounts()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT bank_account_id, account_name, bank_name FROM {$this->tableBankAccounts}
             WHERE status = 'ACTIVE' ORDER BY account_name",
            'DB'
        );

        return $this->ok($rows ?: []);
    }
}
