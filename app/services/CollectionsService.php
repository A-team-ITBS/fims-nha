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
    private string $tableJev       = 'fims.journal_entry_vouchers';
    private string $tableCollectingOfficers = 'fims.collecting_officers';

    // The clearing/"undeposited collections" account a Collecting Officer's cash sits in between
    // being collected and being deposited — debited by the Collection leg JEV, credited by the
    // Deposit leg JEV (see proformaJev()/submitJev() below). Already seeded (account_id '4',
    // "02 - sample_data/03 - fims_chart_of_accounts.sql").
    private const CASH_COLLECTING_OFFICER_CODE = '1-01-01-010';

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

    // ── Collections tab: every Collection line across every report,
    // flattened with its parent report's own date/region/office context —
    // the Collections list used to only ever show up bundled into a
    // report's own total_collections figure; this is the line-level
    // breakdown by GL account that total hides. Read-only here — editing a
    // line still happens through its parent report's Register/Edit modal
    // (see collections-page.php's row click), since a line isn't an
    // independently addressable record of its own.
    public function getCollectionsList($params)
    {
        $where = ['1=1'];
        // Lets the Register Deposit form (collections-page.php's
        // colLoadCollectedForRegion()) show just this region's own already-
        // collected lines as reference while entering a Deposit.
        $rcId = trim((string) ($params['rc_id'] ?? ''));
        if ($rcId !== '') {
            $where[] = 'cr.rc_id = ' . $this->str($rcId);
        }
        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $needle = $this->str('%' . $search . '%');
            $where[] = "(cr.collecting_office ILIKE {$needle} OR rc.rc_code ILIKE {$needle} OR rc.rc_name ILIKE {$needle}
                         OR coa.account_code ILIKE {$needle} OR coa.account_title ILIKE {$needle})";
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT crl.line_id, crl.collection_report_id, crl.amount,
                    coa.account_code, coa.account_title,
                    cr.report_date, cr.collecting_office, rc.rc_code, rc.rc_name
             FROM {$this->tableLines} crl
             JOIN {$this->tableReports} cr ON cr.collection_report_id = crl.collection_report_id
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = cr.rc_id
             LEFT JOIN {$this->tableCoa} coa ON coa.account_id = crl.coa_account_id
             WHERE {$whereSql}
             ORDER BY cr.report_date DESC, cr.collection_report_id DESC, crl.line_number",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    // Net "undeposited" balance for a Region/Office — total collected (every Collection(s) line
    // ever recorded for it) minus total deposited (every Deposit line ever recorded for it,
    // regardless of is_swept — a deposit stops being "available to deposit again" the moment it's
    // banked, not when Fund Sweeping later moves it on to the Main Depository). Backs the "Collected
    // Collections in this Region" panel's summary line in the Register Deposit modal — shown
    // alongside the full collected-lines list (not instead of it), since there's no real per-line
    // link between a Collection and the Deposit that covers it (see colLoadCollectedForRegion()'s
    // own comment for why this app deliberately doesn't try to infer and hide specific "covered"
    // lines — that was tried once and reverted for hiding genuinely undeposited money).
    public function getUndepositedSummary($rcId)
    {
        $rcId = trim((string) $rcId);
        if ($rcId === '') {
            return $this->ok(['total_collected' => 0.0, 'total_deposited' => 0.0, 'undeposited_balance' => 0.0]);
        }

        $collected = (float) (App\ExecuteScalar(
            "SELECT COALESCE(SUM(crl.amount), 0) FROM {$this->tableLines} crl
             JOIN {$this->tableReports} cr ON cr.collection_report_id = crl.collection_report_id
             WHERE cr.rc_id = " . $this->str($rcId),
            'DB'
        ) ?? 0);
        $deposited = (float) (App\ExecuteScalar(
            "SELECT COALESCE(SUM(crd.amount), 0) FROM {$this->tableDeposits} crd
             JOIN {$this->tableReports} cr ON cr.collection_report_id = crd.collection_report_id
             WHERE cr.rc_id = " . $this->str($rcId),
            'DB'
        ) ?? 0);

        return $this->ok([
            'total_collected' => round($collected, 2),
            'total_deposited' => round($deposited, 2),
            'undeposited_balance' => round($collected - $deposited, 2),
        ]);
    }

    // ── Deposits tab: every Deposit line across every report, same
    // flattening as getCollectionsList() above, plus is_swept (Fund
    // Sweeping's own tracking — see app/pages/fund-sweeping-page.php) since
    // that status only ever made sense at this per-deposit granularity.
    public function getDepositsList($params)
    {
        $where = ['1=1'];
        // Same purpose as getCollectionsList()'s own rc_id filter — lets
        // colLoadCollectedForRegion() in collections-page.php fetch just
        // this region's own Deposit totals (to net against its Collected
        // totals) instead of every deposit on file.
        $rcId = trim((string) ($params['rc_id'] ?? ''));
        if ($rcId !== '') {
            $where[] = 'cr.rc_id = ' . $this->str($rcId);
        }
        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $needle = $this->str('%' . $search . '%');
            $where[] = "(cr.collecting_office ILIKE {$needle} OR rc.rc_code ILIKE {$needle} OR rc.rc_name ILIKE {$needle}
                         OR ba.account_name ILIKE {$needle} OR ba.bank_name ILIKE {$needle})";
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT crd.deposit_id, crd.collection_report_id, crd.amount,
                    crd.period_from, crd.period_to, crd.is_swept, crd.deposit_slip_url,
                    ba.account_name, ba.bank_name,
                    cr.report_date, cr.collecting_office, rc.rc_code, rc.rc_name
             FROM {$this->tableDeposits} crd
             JOIN {$this->tableReports} cr ON cr.collection_report_id = crd.collection_report_id
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = cr.rc_id
             LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = crd.bank_account_id
             WHERE {$whereSql}
             ORDER BY cr.report_date DESC, cr.collection_report_id DESC, crd.line_number",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $this->withDepositSlipViewUrls($rows ?: []));
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
        $report['deposits'] = $this->withDepositSlipViewUrls(App\ExecuteRowsAssociative(
            "SELECT crd.*, ba.account_name, ba.bank_name
             FROM {$this->tableDeposits} crd
             LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = crd.bank_account_id
             WHERE crd.collection_report_id = {$report['collection_report_id']}
             ORDER BY crd.line_number",
            'DB'
        ) ?: []);

        return $this->ok($report);
    }

    private function fetchReport(int $id): ?array
    {
        return App\ExecuteRow(
            "SELECT cr.*, rc.rc_code, rc.rc_name,
                    trim(both ', ' from concat_ws(', ', nullif(trim(co.last_name), ''),
                        trim(concat_ws(' ', nullif(trim(co.first_name), ''), nullif(trim(co.middle_name), ''), nullif(trim(co.suffix), ''))))
                    ) AS collecting_officer_name,
                    co.sl_code AS collecting_officer_sl_code
             FROM {$this->tableReports} cr
             LEFT JOIN {$this->tableRc} rc ON rc.rc_id = cr.rc_id
             LEFT JOIN {$this->tableCollectingOfficers} co ON co.collecting_officer_id = cr.collecting_officer_id
             WHERE cr.collection_report_id = {$id}",
            'DB'
        ) ?: null;
    }

    // deposit_slip_url stores a stable reference — either an object-storage
    // key (POST /upload/file — see FileUploadServices::uploadFileToStorage())
    // or a plain http(s) link typed in by hand — never a browsable URL by
    // itself (an object key isn't public, and a presigned view URL expires
    // after an hour, so one can't just be stored once and reused). Each
    // read resolves a fresh deposit_slip_view_url via
    // FileUploadServices::viewUrlFor(), same "resolve on read" precedent as
    // FTLA's _resolveAttachmentUrls().
    private function withDepositSlipViewUrls(array $rows): array
    {
        if (!$rows) {
            return $rows;
        }
        $fileUploads = new FileUploadServices();

        return array_map(static function (array $row) use ($fileUploads) {
            $row['deposit_slip_view_url'] = $fileUploads->viewUrlFor($row['deposit_slip_url'] ?? null);

            return $row;
        }, $rows);
    }

    // Validates a submitted Collecting Officer id against
    // fims.collecting_officers — same "clean message instead of an opaque
    // DB constraint error" reasoning as rcIdExists() below.
    private function collectingOfficerIdExists(string $id): bool
    {
        return (bool) App\ExecuteRow(
            "SELECT collecting_officer_id FROM {$this->tableCollectingOfficers} WHERE collecting_officer_id = " . (int) $id,
            'DB'
        );
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

    /**
     * A Deposit line's Bank Account must be a sweepable operating account: one with a Main
     * Depository already assigned (see "55 - bank_accounts_add_main_depository_account_id.sql"),
     * and not itself flagged as a Main Depository — depositing straight into the account Fund
     * Sweeping would otherwise sweep TO defeats the point of the sweep step entirely. Returns the
     * account row (so the caller can name it in an error message) or null if it's not eligible /
     * doesn't exist.
     */
    private function depositableBankAccount(string $bankAccountId): ?array
    {
        $row = App\ExecuteRow(
            "SELECT bank_account_id, account_name, is_main_depository, main_depository_account_id
             FROM {$this->tableBankAccounts} WHERE bank_account_id = " . $this->str($bankAccountId),
            'DB'
        );
        if (!$row || $row['is_main_depository'] === 't' || $row['is_main_depository'] === true || !$row['main_depository_account_id']) {
            return null;
        }

        return $row;
    }

    // Validates and totals the "Collection(s)" lines submitted in $data —
    // shared by store()/update() so both reject the same bad input the
    // same way before anything is written. Does NOT itself require at
    // least one line — a dedicated "Register Deposit" form (see
    // collections-page.php's colSetFormMode()) submits this section empty
    // on purpose; store()/update() enforce "at least one of Collection OR
    // Deposit" instead, not "both".
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
            $account = $this->depositableBankAccount($bankAccountId);
            if (!$account) {
                return ['error' => 'One of the Deposit lines uses a Bank Account that cannot receive deposits — it has no Main Depository account assigned, or it is itself a Main Depository. Assign a Main Depository in Bank Management first, or pick a different account.'];
            }
            $clean[] = [
                'bank_account_id' => $bankAccountId,
                'period_from' => trim((string) ($dep['period_from'] ?? '')),
                'period_to' => trim((string) ($dep['period_to'] ?? '')),
                'amount' => $amount,
                'deposit_slip_url' => trim((string) ($dep['deposit_slip_url'] ?? '')),
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
                "INSERT INTO {$this->tableDeposits} (collection_report_id, line_number, bank_account_id, period_from, period_to, amount, deposit_slip_url)
                 VALUES ({$reportId}, " . ($startingLineNumber + $i) . ', ' . $this->str($dep['bank_account_id']) . ', '
                    . $this->dateOrNull($dep['period_from']) . ', ' . $this->dateOrNull($dep['period_to']) . ', ' . $this->num($dep['amount']) . ', '
                    . $this->strOrNull($dep['deposit_slip_url'] ?? null) . ')',
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
        // A dedicated "Register Collection" or "Register Deposit" form only
        // ever submits its own section — see collections-page.php's
        // colSetFormMode() — so only ONE of the two is required here, not
        // both (the old combined "Register Collection Report" flow still
        // works fine either way, since it always submits both).
        if (!$lineResult['lines'] && !$depositResult['deposits']) {
            return json_encode(["success" => false, "message" => "At least one Collection line or Deposit line is required."]);
        }

        $collectingOfficerId = trim((string) ($data['collecting_officer_id'] ?? ''));
        if ($collectingOfficerId !== '' && !$this->collectingOfficerIdExists($collectingOfficerId)) {
            return json_encode(["success" => false, "message" => "Select a valid Collecting Officer."]);
        }

        $userId = (int) App\CurrentUserID();
        $collectingOffice = trim((string) ($data['collecting_office'] ?? ''));

        $row = App\ExecuteRow(
            "INSERT INTO {$this->tableReports}
                (report_date, rc_id, collecting_office, collecting_officer_id, total_collections, total_deposits, created_by)
             VALUES (" . $this->str($reportDate) . ', ' . $this->str($rcId) . ', ' . $this->strOrNull($collectingOffice) . ', '
                . ($collectingOfficerId !== '' ? (int) $collectingOfficerId : 'NULL') . ', '
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
        // Same relaxation as store() — at least one of Collection OR
        // Deposit (existing swept deposits count as "having" a deposit,
        // same as before this change).
        if (!$lineResult['lines'] && !$depositResult['deposits'] && $sweptTotal <= 0) {
            return json_encode(["success" => false, "message" => "At least one Collection line or Deposit line is required."]);
        }

        $collectingOfficerId = trim((string) ($data['collecting_officer_id'] ?? ''));
        if ($collectingOfficerId !== '' && !$this->collectingOfficerIdExists($collectingOfficerId)) {
            return json_encode(["success" => false, "message" => "Select a valid Collecting Officer."]);
        }

        $collectingOffice = trim((string) ($data['collecting_office'] ?? ''));
        $totalDeposits = $sweptTotal + $depositResult['total'];

        App\Execute(
            "UPDATE {$this->tableReports} SET
                report_date = " . $this->str($reportDate) . ",
                rc_id = " . $this->str($rcId) . ",
                collecting_office = " . $this->strOrNull($collectingOffice) . ",
                collecting_officer_id = " . ($collectingOfficerId !== '' ? (int) $collectingOfficerId : 'NULL') . ",
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
    // Every postable account here is a child (SL) under some non-postable
    // parent (GL) grouping account — same GL/SL split bank-management's own
    // picker uses — so parent_code/parent_title are surfaced alongside each
    // row's own account_code (the SL code) for display, even though only
    // the SL-level account_id is what actually gets submitted.
    // Account codes a Collecting Officer is actually allowed to post a
    // Collection line against — a fixed, curated subset of the full postable
    // chart (receivables a collection can settle, plus the handful of
    // revenue/income accounts collections actually hit), not every
    // posting-allowed account in the COA.
    private const COLLECTION_ACCOUNT_CODES = [
        '1-03-01-050', // Interests Receivable
        '1-03-01-270', // Installment Sales Receivable
        '1-03-02-010', // Operating Lease Receivable
        '2-05-01-990', // Other Deferred Credits
        '4-02-01-010', // Permit Fees
        '4-02-01-040', // Clearance and Certification Fees
        '4-02-01-130', // Processing Fees
        '4-02-02-050', // Rent/Lease Income
        '4-02-02-160', // Sales Revenue
        '4-02-02-210', // Interest Income
        '4-02-02-230', // Fines and Penalties-Business Income
        '4-02-02-990', // Other Business Income
        '4-06-99-990', // Miscellaneous Income
    ];

    // $showAll bypasses the curated whitelist above — "Show other accounts"
    // in the Collection(s) picker, for the rare line that genuinely needs an
    // account outside the common set. Default (false) is what everyday
    // Collection registration should see.
    public function getGlAccounts(bool $showAll = false)
    {
        $whitelistSql = $showAll ? '' : (' AND ac.account_code IN (' .
            implode(',', array_map(fn ($c) => $this->str($c), self::COLLECTION_ACCOUNT_CODES)) . ')');
        $rows = App\ExecuteRowsAssociative(
            "SELECT ac.account_id, ac.account_code, ac.account_title,
                    parent.account_code AS parent_code, parent.account_title AS parent_title
             FROM {$this->tableCoa} ac
             LEFT JOIN {$this->tableCoa} parent ON parent.account_id = ac.parent_account_id
             WHERE ac.status = 'Active' AND ac.posting_allowed = TRUE{$whitelistSql}
             ORDER BY ac.account_code",
            'DB'
        );

        return $this->ok($rows ?: []);
    }

    // Only sweepable operating accounts are offered for a Deposit line — see
    // depositableBankAccount()'s own comment for why an account with no Main Depository
    // assigned, or one that IS a Main Depository, is excluded here rather than merely
    // rejected on save.
    public function getBankAccounts()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT bank_account_id, account_name, bank_name FROM {$this->tableBankAccounts}
             WHERE status = 'ACTIVE' AND is_main_depository = FALSE AND main_depository_account_id IS NOT NULL
             ORDER BY account_name",
            'DB'
        );

        return $this->ok($rows ?: []);
    }

    // ── Journal Entry Voucher prefill (Collection leg + Deposit leg) ──
    //
    // Collections has always been "record first, journalize later" (see this class's docblock) —
    // saving a report never created an accounting entry at all. These two methods let Accounting
    // turn a saved report's Collection(s)/Deposit sections into a standalone JEV for review, same
    // mechanism (DisbursementVoucherService::createStandaloneJev()) and same "pro-forma" shape as
    // the IAR/Property Record modules' own JEV submit — the user still picks a Book and reviews the
    // lines before Save; nothing here posts automatically. $leg is 'collection' or 'deposit'.
    //
    // Collection leg:  Dr Cash-Collecting Officers (total) / Cr each Collection line's GL account.
    // Deposit  leg:     Dr each Deposit line's bank account's GL account / Cr Cash-Collecting
    //                    Officers (total) — the bank account must have a coa_account_id/account_code
    //                    assigned (Bank Account Management) or it's left out with a warning.
    private function jevLegColumn(string $leg): ?string
    {
        return match ($leg) {
            'collection' => 'collection_jev_id',
            'deposit'    => 'deposit_jev_id',
            default      => null,
        };
    }

    /** Prefill data for the Add JEV review modal, or the existing JEV if this leg was already prepared. */
    public function proformaJev(int $id, string $leg): array
    {
        $column = $this->jevLegColumn($leg);
        if ($column === null) {
            return ['success' => false, 'message' => "Unknown leg '{$leg}' (expected 'collection' or 'deposit')."];
        }
        $report = $this->fetchReport($id);
        if (!$report) {
            return ['success' => false, 'message' => 'Collection report not found.', 'httpCode' => 404];
        }

        if (!empty($report[$column])) {
            $jev = App\ExecuteRow(
                "SELECT jev.jev_id, jev.jev_no, st.name AS jev_status
                 FROM {$this->tableJev} jev
                 LEFT JOIN fims.settings_status st ON st.id = jev.status_id
                 WHERE jev.jev_id = " . (int) $report[$column],
                'DB'
            );

            return ['success' => true, 'data' => ['already' => $jev]];
        }

        $particulars = 'Collection Report ' . ($leg === 'collection' ? '— Collections' : '— Deposits')
            . ' (' . $report['report_date'] . ($report['collecting_office'] ? ', ' . $report['collecting_office'] : '') . ')';

        // Tags the Cash-Collecting Officers line (both legs book it, under the same
        // control account) with the specific officer's SL code — same pairing as
        // Collecting Officer Master Data's own GL/SL tagging — so the JEV line is
        // traceable to the officer, not just the control account. sl_name is the SL
        // account's OWN title (chart_of_accounts.account_title for that code), not the
        // officer's name — same "code alone isn't readable" reasoning account_title
        // already gets for every other line here.
        // collecting_officers.sl_code actually holds the SL's chart_of_accounts.account_id
        // (CollectingOfficerService joins it on s.account_id), so resolve it to the real
        // account_code + title. Fall back to a code match for any legacy row storing a code.
        $rawSl = trim((string) ($report['collecting_officer_sl_code'] ?? ''));
        $collectingOfficerSlCode = null;
        $collectingOfficerSlName = null;
        if ($rawSl !== '') {
            $slRow = null;
            $slRow = App\ExecuteRow("SELECT account_code, account_title FROM {$this->tableCoa} WHERE account_id = " . $this->str($rawSl), 'DB');
            if (!$slRow) {
                $slRow = App\ExecuteRow("SELECT account_code, account_title FROM {$this->tableCoa} WHERE account_code = " . $this->str($rawSl), 'DB');
            }
            $collectingOfficerSlCode = $slRow['account_code'] ?? $rawSl;
            $collectingOfficerSlName = $slRow['account_title'] ?? null;
        }

        if ($leg === 'collection') {
            $lines = App\ExecuteRowsAssociative(
                "SELECT coa.account_code, coa.account_title, crl.amount
                 FROM {$this->tableLines} crl
                 LEFT JOIN {$this->tableCoa} coa ON coa.account_id = crl.coa_account_id
                 WHERE crl.collection_report_id = {$id} ORDER BY crl.line_number",
                'DB'
            ) ?: [];
            if (!$lines) {
                return ['success' => false, 'message' => 'This report has no Collection lines to journalize.'];
            }
            $total = array_sum(array_map(fn($l) => (float) $l['amount'], $lines));
            $out = [['account_code' => self::CASH_COLLECTING_OFFICER_CODE, 'account_title' => 'Cash-Collecting Officers', 'debit' => $total, 'credit' => 0, 'sl_code' => $collectingOfficerSlCode, 'sl_name' => $collectingOfficerSlName]];
            foreach ($lines as $l) {
                $out[] = ['account_code' => $l['account_code'], 'account_title' => $l['account_title'], 'debit' => 0, 'credit' => (float) $l['amount']];
            }
        } else {
            $deposits = App\ExecuteRowsAssociative(
                "SELECT ba.account_code, ba.account_name, crd.amount
                 FROM {$this->tableDeposits} crd
                 LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = crd.bank_account_id
                 WHERE crd.collection_report_id = {$id} ORDER BY crd.line_number",
                'DB'
            ) ?: [];
            if (!$deposits) {
                return ['success' => false, 'message' => 'This report has no Deposit lines to journalize.'];
            }
            $warnings = [];
            $out = [];
            $total = 0.0;
            foreach ($deposits as $d) {
                if (empty($d['account_code'])) {
                    $warnings[] = "Bank account '{$d['account_name']}' has no GL account assigned (Bank Account Management) — left out.";
                    continue;
                }
                $out[] = ['account_code' => $d['account_code'], 'account_title' => $d['account_name'], 'debit' => (float) $d['amount'], 'credit' => 0];
                $total += (float) $d['amount'];
            }
            if (!$out) {
                return ['success' => false, 'message' => 'None of this report\'s Deposit bank accounts have a GL account assigned.'];
            }
            $out[] = ['account_code' => self::CASH_COLLECTING_OFFICER_CODE, 'account_title' => 'Cash-Collecting Officers', 'debit' => 0, 'credit' => $total, 'sl_code' => $collectingOfficerSlCode, 'sl_name' => $collectingOfficerSlName];
        }

        return ['success' => true, 'data' => [
            'jev_date' => $report['report_date'], 'rc_id' => $report['rc_id'], 'particulars' => $particulars,
            'lines' => $out, 'warnings' => $warnings ?? [],
        ]];
    }

    /**
     * Creates the standalone JEV (DRAFT) from the server-computed lines above — the client only
     * supplies book_id/particulars (and may not override amounts/accounts), same trust boundary as
     * PropertyRecordService::submitProforma(). Links it back onto the report's collection_jev_id/
     * deposit_jev_id so this leg can't be journalized twice.
     */
    public function submitJev(int $id, string $leg, array $body, int $userId): array
    {
        $column = $this->jevLegColumn($leg);
        if ($column === null) {
            return ['success' => false, 'message' => "Unknown leg '{$leg}' (expected 'collection' or 'deposit')."];
        }
        $bookId = $body['book_id'] ?? null;
        if (!$bookId) {
            return ['success' => false, 'message' => 'Select a Book before submitting.'];
        }

        $prefill = $this->proformaJev($id, $leg);
        if (!($prefill['success'] ?? false)) {
            return $prefill;
        }
        if (!empty($prefill['data']['already'])) {
            return ['success' => false, 'message' => 'This leg was already journalized as ' . $prefill['data']['already']['jev_no'] . '.', 'httpCode' => 409];
        }
        $data = $prefill['data'];

        $voucher = new DisbursementVoucherService();
        $result  = $voucher->createStandaloneJev([
            'jev_date'      => $data['jev_date'],
            'book_id'       => $bookId,
            'particulars'   => trim((string) ($body['particulars'] ?? '')) ?: $data['particulars'],
            'lines'         => array_map(fn($l) => [
                'account_code' => $l['account_code'],
                'rc_id'        => $data['rc_id'] ?? '',
                'debit'        => $l['debit'],
                'credit'       => $l['credit'],
                'sl_code'      => $l['sl_code'] ?? null,
                'description'  => $l['sl_name'] ?? null,
            ], $data['lines']),
        ], $userId);

        if ($result['success'] ?? false) {
            $jevId = (int) $result['data']['jev_id'];
            // createStandaloneJev() defaults source_module to 'ACCOUNTING' (fims.journal_entry_
            // vouchers' own column default) since that's right for the period-end ACCRUAL/
            // DEPRECIATION/etc. JEVs it's otherwise used for — but this one actually comes from the
            // Collections module, not Accounting, so it's corrected here to say so, same register
            // columns (source_module/doc_ref_no) FtlaService's own Payment JEVs already use.
            $sourceModule = $leg === 'collection' ? 'COLLECTION' : 'DEPOSIT';
            App\Execute(
                "UPDATE {$this->tableJev} SET source_module = " . $this->str($sourceModule) . ', doc_ref_no = ' . $this->str('#' . $id) . "
                 WHERE jev_id = {$jevId}",
                'DB'
            );
            App\Execute("UPDATE {$this->tableReports} SET {$column} = {$jevId} WHERE collection_report_id = {$id}", 'DB');
        }

        return $result;
    }
}
