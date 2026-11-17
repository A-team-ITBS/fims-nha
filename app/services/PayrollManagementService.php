<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Payroll Management (page app/pages/payroll-page.php, routes app/routes/payroll_management.php).
 *
 * IMPORT ONLY: brings in the monthly employee payroll (the Regular FIMS MONTHLY PAYROLL Excel template) and lists one
 * summary row per imported month (Payroll Month, Year, Import Date, Status, Total Payroll Amount = gross).
 * No DV is created here — a payroll DV is prepared in the DV module from an imported month.
 *
 * Writes into the payroll tables of the Payroll module (no tables of its own):
 *   fims.payroll_periods       one per month (fiscal_yr + payroll_month); created when missing; prepared_at/by = import date/user
 *   fims.payroll_employees     employees not in the directory yet are added (existing ones are left as they are)
 *   fims.payroll_records       one per employee per month, with the category totals (gross / net are generated from them)
 *   fims.payroll_record_items  one per employee per payroll item with an amount
 *
 * Column mapping: every amount column of the template is matched to a Payroll Setup item (fims.payroll_items.item_code,
 * compared without the template's surrounding quotes, case-insensitive). The item's category decides what the amount is
 * (EARNING / DEDUCTION / REFUND / EMPLOYER_SHARE) and its account is snapshotted on the line. A column with amounts but
 * no ACTIVE item stops the import, so the totals are never silently wrong — add the item in Payroll Setup first.
 * Identity columns (1INCOME.* / 2INCOME.*) and Excel's "Expr…" helper columns are not amounts.
 */
class PayrollManagementService
{
    private string $tPeriod = 'fims.payroll_periods';
    private string $tRecord = 'fims.payroll_records';
    private string $tLine   = 'fims.payroll_record_items';
    private string $tEmp    = 'fims.payroll_employees';
    private string $tItem   = 'fims.payroll_items';
    private string $tDv     = 'fims.disbursement_vouchers';
    private string $tSt     = 'fims.settings_status';

    private const SHEET_NAME = 'MONTHLY PAYROLL';
    private const MONTHS = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    private const COL_EMPNO = '1INCOME.EMPNO';
    private const COL_DEPT  = '1INCOME.DEPTCD';
    private const COL_LNAME = '1INCOME.LNAME';
    private const COL_FNAME = '1INCOME.FNAME';
    private const COL_MI    = '1INCOME.MI';

    // record total column per item category
    private const TOTAL_COL = [
        'EARNING'        => 'total_earnings',
        'DEDUCTION'      => 'total_deductions',
        'REFUND'         => 'total_refunds',
        'EMPLOYER_SHARE' => 'total_employer_share',
    ];

    // ═══ Summary list ══════════════════════════════════════════════════════════════════════

    /** One row per imported month (newest first) + KPIs + the years that have imports. Filters: year, status. */
    public function fetchList(array $filters = []): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT p.payroll_period_id, p.fiscal_yr, p.payroll_month, p.period_name, p.status AS period_status,
                        COALESCE(p.prepared_at, MIN(r.created_at)) AS imported_at, u.username AS imported_by_name,
                        COUNT(r.payroll_record_id) AS employee_count,
                        COALESCE(SUM(r.gross_amount), 0) AS total_gross,
                        COALESCE(SUM(r.total_deductions), 0) AS total_deductions,
                        COALESCE(SUM(r.total_refunds), 0) AS total_refunds,
                        COALESCE(SUM(r.net_amount), 0) AS total_net,
                        COALESCE(SUM(r.total_employer_share), 0) AS total_employer_share,
                        COUNT(dv.dv_id) FILTER (WHERE COALESCE(st.name, '') <> 'CANCELLED') AS linked_count,
                        STRING_AGG(DISTINCT dv.dv_no, ', ') FILTER (WHERE COALESCE(st.name, '') <> 'CANCELLED') AS dv_nos,
                        STRING_AGG(DISTINCT st.name, ', ') FILTER (WHERE COALESCE(st.name, '') <> 'CANCELLED') AS dv_statuses
                   FROM {$this->tPeriod} p
                   JOIN {$this->tRecord} r ON r.payroll_period_id = p.payroll_period_id
                   LEFT JOIN {$this->tDv} dv ON dv.dv_id = r.disbursement_voucher_id
                   LEFT JOIN {$this->tSt} st ON st.id = dv.status_id
                   LEFT JOIN uac.users u ON u.user_id = p.prepared_by
                  " . (ctype_digit((string) ($filters['year'] ?? '')) ? 'WHERE p.fiscal_yr = ' . (int) $filters['year'] : '') . '
                  GROUP BY p.payroll_period_id, u.username
                  ORDER BY p.fiscal_yr DESC, p.payroll_month DESC',
                'DB'
            ) ?: [];

            $out = [];
            foreach ($rows as $r) {
                $count  = (int) $r['employee_count'];
                $linked = (int) $r['linked_count'];
                $r['import_status'] = $linked === 0 ? 'IMPORTED' : ($linked >= $count ? 'DV_CREATED' : 'PARTIAL_DV');
                $r['month_name'] = self::MONTHS[(int) $r['payroll_month']] ?? (string) $r['period_name'];
                if (in_array($filters['status'] ?? '', ['IMPORTED', 'DV_CREATED', 'PARTIAL_DV'], true) && $r['import_status'] !== $filters['status']) {
                    continue;
                }
                $out[] = $r;
            }
            $years = array_map('intval', array_column(App\ExecuteRows(
                "SELECT DISTINCT p.fiscal_yr FROM {$this->tPeriod} p WHERE EXISTS (SELECT 1 FROM {$this->tRecord} r WHERE r.payroll_period_id = p.payroll_period_id) ORDER BY 1 DESC",
                'DB'
            ) ?: [], 'fiscal_yr'));

            return ['success' => true, 'data' => $out, 'years' => $years, 'kpis' => [
                'imports'     => count($out),
                'total_gross' => round(array_sum(array_map('floatval', array_column($out, 'total_gross'))), 2),
                'total_net'   => round(array_sum(array_map('floatval', array_column($out, 'total_net'))), 2),
                'without_dv'  => count(array_filter($out, fn ($r) => $r['import_status'] !== 'DV_CREATED')),
            ]];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::fetchList ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the imported payrolls.'];
        }
    }

    // ═══ Import ══════════════════════════════════════════════════════════════════════════

    /** Parse the uploaded file and return its totals and what blocks / needs checking — nothing is saved. */
    public function preview($file, array $data): array
    {
        try {
            $period = $this->period($data);
            if (isset($period['error'])) {
                return ['success' => false, 'message' => $period['error']];
            }
            $parsed = $this->parseUpload($file);
            if (!$parsed['success']) {
                return $parsed;
            }
            $existing = $this->periodState($period['month'], $period['year']);

            return ['success' => true, 'data' => $this->summaryOf($parsed, $period) + ['existing' => $existing]];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::preview ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to read the payroll file.'];
        }
    }

    /**
     * Import the file for a payroll month. A month that already has records is refused unless `replace` = 1, and can
     * never be replaced while a DV uses it (cancel or delete that DV first) or while the period is LOCKED / CLOSED.
     */
    public function import($file, array $data, int $userId): array
    {
        try {
            $period = $this->period($data);
            if (isset($period['error'])) {
                return ['success' => false, 'message' => $period['error']];
            }
            $parsed = $this->parseUpload($file);
            if (!$parsed['success']) {
                return $parsed;
            }
            if ($parsed['blocking']) {
                return ['success' => false, 'message' => 'The payroll file cannot be imported yet.', 'errors' => $parsed['blocking']];
            }
            $label    = self::MONTHS[$period['month']] . ' ' . $period['year'];
            $existing = $this->periodState($period['month'], $period['year']);
            if ($existing) {
                if ($existing['blocked']) {
                    return ['success' => false, 'httpCode' => 409, 'message' => $existing['blocked']];
                }
                if ($existing['record_count'] > 0 && (empty($data['replace']) || (string) $data['replace'] === '0')) {
                    return ['success' => false, 'httpCode' => 409, 'code' => 'EXISTS', 'message' => "A payroll for {$label} was already imported. Import again to replace it."];
                }
            }

            $uid = $this->userIdOrNull($userId);
            $u   = $uid === null ? 'NULL' : (string) $uid;
            App\Execute('BEGIN', 'DB');
            try {
                if ($existing) {
                    $periodId = (int) $existing['payroll_period_id'];
                    // payroll_record_items go with their record (ON DELETE CASCADE)
                    App\Execute("DELETE FROM {$this->tRecord} WHERE payroll_period_id = {$periodId}", 'DB');
                    App\Execute("UPDATE {$this->tPeriod} SET prepared_by = {$u}, prepared_at = CURRENT_TIMESTAMP, updated_by = {$u}, updated_at = CURRENT_TIMESTAMP
                                  WHERE payroll_period_id = {$periodId}", 'DB');
                } else {
                    $start = sprintf('%04d-%02d-01', $period['year'], $period['month']);
                    $end   = date('Y-m-t', strtotime($start));
                    $periodId = (int) App\ExecuteScalar(
                        "INSERT INTO {$this->tPeriod} (fiscal_yr, payroll_month, period_name, period_start_date, period_end_date, status, prepared_by, prepared_at, created_by)
                         VALUES ({$period['year']}, {$period['month']}, " . $this->str($label) . ", '{$start}', '{$end}', 'OPEN', {$u}, CURRENT_TIMESTAMP, {$u})
                         RETURNING payroll_period_id",
                        'DB'
                    );
                }

                $empIds = $this->upsertEmployees($parsed['employees'], $u);

                foreach (array_chunk($parsed['employees'], 200) as $chunk) {
                    $values = array_map(function ($e) use ($periodId, $empIds, $u) {
                        $t = $e['totals'];

                        return "({$periodId}, {$empIds[$e['employee_no']]}, "
                            . $this->num($t['EARNING']) . ', ' . $this->num($t['DEDUCTION']) . ', ' . $this->num($t['REFUND']) . ', ' . $this->num($t['EMPLOYER_SHARE'])
                            . ", 'DRAFT', {$u}, CURRENT_TIMESTAMP, {$u})";
                    }, $chunk);
                    $ids = App\ExecuteRows(
                        "INSERT INTO {$this->tRecord} (payroll_period_id, employee_id, total_earnings, total_deductions, total_refunds, total_employer_share, status, prepared_by, prepared_at, created_by)
                         VALUES " . implode(', ', $values) . ' RETURNING payroll_record_id, employee_id',
                        'DB'
                    ) ?: [];
                    $recByEmp = array_column($ids, 'payroll_record_id', 'employee_id');

                    $lines = [];
                    foreach ($chunk as $e) {
                        $recId = (int) $recByEmp[$empIds[$e['employee_no']]];
                        foreach ($e['lines'] as $itemId => $l) {
                            $lines[] = "({$recId}, {$itemId}, " . $this->num($l['amount']) . ', ' . $this->strOrNull($l['account_code']) . ", {$u})";
                        }
                    }
                    foreach (array_chunk($lines, 1000) as $lc) {
                        App\Execute("INSERT INTO {$this->tLine} (payroll_record_id, payroll_item_id, amount, account_code, created_by) VALUES " . implode(', ', $lc), 'DB');
                    }
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'httpCode' => 201, 'message' => "{$label} payroll imported: " . count($parsed['employees']) . ' employees.',
                'data' => ['payroll_period_id' => $periodId] + $this->summaryOf($parsed, $period)];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::import ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to import the payroll.'];
        }
    }

    /** Remove a month's imported records (the period row stays) — only while no DV uses them and the period is OPEN. */
    public function delete(int|string $periodId, int $userId): array
    {
        try {
            $p = App\ExecuteRow("SELECT fiscal_yr, payroll_month FROM {$this->tPeriod} WHERE payroll_period_id = " . (int) $periodId, 'DB');
            if (!$p) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'Payroll month not found.'];
            }
            $state = $this->periodState((int) $p['payroll_month'], (int) $p['fiscal_yr']);
            if (!$state || $state['record_count'] === 0) {
                return ['success' => false, 'httpCode' => 404, 'message' => 'This month has no imported payroll.'];
            }
            if ($state['blocked']) {
                return ['success' => false, 'httpCode' => 409, 'message' => $state['blocked']];
            }
            $uid = $this->userIdOrNull($userId);
            $u   = $uid === null ? 'NULL' : (string) $uid;
            App\Execute('BEGIN', 'DB');
            try {
                App\Execute("DELETE FROM {$this->tRecord} WHERE payroll_period_id = " . (int) $periodId, 'DB');
                App\Execute("UPDATE {$this->tPeriod} SET prepared_by = NULL, prepared_at = NULL, updated_by = {$u}, updated_at = CURRENT_TIMESTAMP WHERE payroll_period_id = " . (int) $periodId, 'DB');
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => 'Imported payroll deleted.'];
        } catch (\Throwable $e) {
            App\LogError('PayrollManagementService::delete ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to delete the imported payroll.'];
        }
    }

    // ═══ File parsing ══════════════════════════════════════════════════════════════════════

    /**
     * The uploaded template → employees with their amounts per payroll item:
     * ['success', 'file_name', 'employees', 'totals', 'blocking' (errors that stop the import), 'warnings', 'skipped_columns'].
     */
    private function parseUpload($file): array
    {
        if (!$file || !method_exists($file, 'getError') || $file->getError() !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Choose the payroll Excel file (.xlsx) to import.'];
        }
        $name = (string) $file->getClientFilename();
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xlsx') {
            return ['success' => false, 'message' => 'The payroll file must be an Excel .xlsx file (the FIMS MONTHLY PAYROLL template).'];
        }
        $tmp = tempnam(sys_get_temp_dir(), 'payroll');
        $file->moveTo($tmp);
        try {
            $rows = $this->readXlsx($tmp);
        } finally {
            @unlink($tmp);
        }
        if ($rows === null) {
            return ['success' => false, 'message' => 'The file could not be opened as an Excel workbook.'];
        }

        // header row: the first of rows 1-5 that holds the employee number column
        $headerRow = null;
        foreach ($rows as $r => $cells) {
            if ($r > 5) {
                break;
            }
            if (in_array(self::COL_EMPNO, array_map(fn ($v) => strtoupper(trim((string) $v)), $cells), true)) {
                $headerRow = $r;
                break;
            }
        }
        if ($headerRow === null) {
            return ['success' => false, 'message' => 'This is not the FIMS MONTHLY PAYROLL template: no "1INCOME.EMPNO" column in its first rows.'];
        }
        $headers = array_map(fn ($v) => trim((string) $v), $rows[$headerRow]);
        $codes   = array_map(fn ($v) => trim((string) $v), $rows[$headerRow + 1] ?? []);
        // the account-code row under the headers is optional; when present it is skipped as data
        $hasCodeRow = (bool) array_filter($codes, fn ($c) => preg_match('/^\d-\d{2}-\d{2}/', $c));
        $col = [];
        foreach ($headers as $c => $h) {
            $col[strtoupper($h)] ??= $c;
        }
        foreach ([self::COL_EMPNO, self::COL_LNAME, self::COL_FNAME] as $req) {
            if (!isset($col[$req])) {
                return ['success' => false, 'message' => "The template is missing the {$req} column."];
            }
        }

        // match every amount column to a Payroll Setup item
        $items = [];
        foreach (App\ExecuteRows("SELECT payroll_item_id, item_code, item_name, category, account_code, status FROM {$this->tItem}", 'DB') ?: [] as $it) {
            $items[$this->itemKey($it['item_code'])] = $it;
        }
        $amountCols = [];   // col index => item row
        $unmapped   = [];   // col index => ['column', 'code']
        $inactive   = [];
        $noAccount  = [];
        foreach ($headers as $c => $h) {
            $u = strtoupper($h);
            if ($h === '' || preg_match('/^EXPR\d*$/i', $h) || str_starts_with($u, '1INCOME.') || str_starts_with($u, '2INCOME.')) {
                continue;
            }
            $item = $items[$this->itemKey($h)] ?? null;
            if (!$item) {
                $unmapped[$c] = ['column' => $this->itemKey($h), 'code' => $hasCodeRow ? ($codes[$c] ?? '') : ''];
            } elseif ($item['status'] !== 'ACTIVE') {
                $inactive[] = $item['item_code'];
            } else {
                $amountCols[$c] = $item;
                if (empty($item['account_code'])) {
                    $noAccount[$item['item_code']] = true;
                }
            }
        }

        $employees = [];
        $errors = [];
        $warnings = [];
        $seen = [];
        $unmappedUsed = [];
        $tot = ['EARNING' => 0.0, 'DEDUCTION' => 0.0, 'REFUND' => 0.0, 'EMPLOYER_SHARE' => 0.0];
        $first = $headerRow + ($hasCodeRow ? 2 : 1);
        foreach ($rows as $r => $cells) {
            if ($r < $first) {
                continue;
            }
            $empNo = trim((string) ($cells[$col[self::COL_EMPNO]] ?? ''));
            $last  = trim((string) ($cells[$col[self::COL_LNAME]] ?? ''));
            if ($empNo === '' || $last === '') {
                continue; // blank, subtotal or note rows
            }
            if (isset($seen[$empNo])) {
                $errors[] = "Employee no. {$empNo} appears more than once (rows {$seen[$empNo]} and {$r}) — one row per employee.";
                continue;
            }
            $seen[$empNo] = $r;
            $emp = ['row_no' => $r, 'employee_no' => $empNo, 'last_name' => $last,
                'first_name'  => trim((string) ($cells[$col[self::COL_FNAME]] ?? '')),
                'middle_name' => isset($col[self::COL_MI]) ? trim((string) ($cells[$col[self::COL_MI]] ?? '')) : '',
                'department'  => isset($col[self::COL_DEPT]) ? trim((string) ($cells[$col[self::COL_DEPT]] ?? '')) : '',
                'totals' => ['EARNING' => 0.0, 'DEDUCTION' => 0.0, 'REFUND' => 0.0, 'EMPLOYER_SHARE' => 0.0], 'lines' => []];

            foreach ($unmapped as $c => $um) {
                $num = $this->toNumber($cells[$c] ?? '');
                if ($num !== null && abs($num) >= 0.005) {
                    $unmappedUsed[$c] = $um;
                }
            }
            foreach ($amountCols as $c => $item) {
                $v = $cells[$c] ?? '';
                if ($v === '' || $v === null) {
                    continue;
                }
                $num = $this->toNumber($v);
                if ($num === null) {
                    if (count($errors) < 30) {
                        $errors[] = "Row {$r}, column {$item['item_code']}: \"{$v}\" is not a number.";
                    }
                    continue;
                }
                if (abs($num) < 0.005) {
                    continue;
                }
                $id = (int) $item['payroll_item_id'];
                $emp['lines'][$id] ??= ['amount' => 0.0, 'account_code' => $item['account_code']];
                $emp['lines'][$id]['amount'] = round($emp['lines'][$id]['amount'] + $num, 2);
                $emp['totals'][$item['category']] += $num;
            }
            foreach ($emp['totals'] as $k => $v) {
                $emp['totals'][$k] = round($v, 2);
                $tot[$k] += $emp['totals'][$k];
            }
            $net = $emp['totals']['EARNING'] + $emp['totals']['REFUND'] - $emp['totals']['DEDUCTION'];
            if ($emp['totals']['EARNING'] <= 0) {
                $warnings[] = "Row {$r} ({$empNo} {$last}): no earnings.";
            } elseif ($net < 0) {
                $warnings[] = "Row {$r} ({$empNo} {$last}): deductions exceed pay (net " . number_format($net, 2) . ').';
            }
            $employees[] = $emp;
        }
        if (!$employees && !$errors) {
            return ['success' => false, 'message' => 'The payroll file has no employee rows (each needs an employee no. and a last name).'];
        }
        foreach ($tot as $k => $v) {
            $tot[$k] = round($v, 2);
        }

        $blocking = $errors;
        if ($unmappedUsed) {
            $blocking[] = 'These columns have amounts but are not in Payroll Setup (add each as a payroll item with this item code): '
                . implode(', ', array_map(fn ($u) => $u['column'] . ($u['code'] !== '' ? " [{$u['code']}]" : ''), $unmappedUsed)) . '.';
        }
        if ($noAccount) {
            $warnings[] = 'Payroll items with no account yet (set it in Payroll Setup before preparing the DV): ' . implode(', ', array_keys($noAccount)) . '.';
        }
        // template columns that are not set up and carry no amounts in this file are simply ignored (not reported)
        return ['success' => true, 'file_name' => $name, 'employees' => $employees, 'totals' => $tot,
            'blocking' => array_slice($blocking, 0, 40), 'warnings' => array_slice($warnings, 0, 50), 'skipped_columns' => array_values(array_unique($inactive))];
    }

    /** Minimal .xlsx reader: the "MONTHLY PAYROLL" sheet (else the first) as [rowNo => [colIndex => value]]; null if not a workbook. */
    private function readXlsx(string $path): ?array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return null;
        }
        try {
            $wb   = @simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
            $rels = @simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
            if (!$wb || !$rels) {
                return null;
            }
            $targets = [];
            foreach ($rels->Relationship as $rel) {
                $targets[(string) $rel['Id']] = (string) $rel['Target'];
            }
            $sheetPath = null;
            foreach ($wb->sheets->sheet as $s) {
                $rid    = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $target = $targets[$rid] ?? null;
                if ($target === null) {
                    continue;
                }
                $target = ltrim(str_starts_with($target, '/') ? substr($target, 1) : 'xl/' . $target, '/');
                $sheetPath ??= $target;
                if (strtoupper(trim((string) $s['name'])) === self::SHEET_NAME) {
                    $sheetPath = $target;
                    break;
                }
            }
            $shared = [];
            if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach (simplexml_load_string($ss)->si as $si) {
                    $t = '';
                    if (isset($si->t)) {
                        $t = (string) $si->t;
                    } else {
                        foreach ($si->r as $run) {
                            $t .= (string) $run->t;
                        }
                    }
                    $shared[] = $t;
                }
            }
            $xml = $sheetPath ? $zip->getFromName($sheetPath) : false;
            if ($xml === false) {
                return null;
            }
            $sheet = simplexml_load_string($xml);
            $out = [];
            foreach ($sheet->sheetData->row as $row) {
                $r = (int) $row['r'];
                foreach ($row->c as $c) {
                    $idx = $this->colIndex(preg_replace('/\d+/', '', (string) $c['r']));
                    $t   = (string) $c['t'];
                    if ($t === 's') {
                        $v = $shared[(int) $c->v] ?? '';
                    } elseif ($t === 'inlineStr') {
                        $v = (string) $c->is->t;
                    } else {
                        $v = (string) $c->v;
                    }
                    $out[$r][$idx] = $v;
                }
            }

            return $out;
        } finally {
            $zip->close();
        }
    }

    // ═══ Helpers ═══════════════════════════════════════════════════════════════════════════

    /** Adds the employees not in the directory yet; returns employee_no => employee_id for every employee of the file. */
    private function upsertEmployees(array $employees, string $u): array
    {
        $ids = [];
        foreach (array_chunk($employees, 200) as $chunk) {
            $values = array_map(fn ($e) => '(' . $this->str($e['employee_no']) . ', ' . $this->str($e['first_name']) . ', ' . $this->str($e['last_name'])
                . ', ' . $this->strOrNull($e['middle_name']) . ', ' . $this->strOrNull($e['department']) . ", {$u})", $chunk);
            // existing employees are left unchanged (the no-op update only makes RETURNING give their id)
            $rows = App\ExecuteRows(
                "INSERT INTO {$this->tEmp} (employee_no, first_name, last_name, middle_name, department, created_by)
                 VALUES " . implode(', ', $values) . '
                 ON CONFLICT (employee_no) DO UPDATE SET employee_no = EXCLUDED.employee_no
                 RETURNING employee_id, employee_no',
                'DB'
            ) ?: [];
            foreach ($rows as $r) {
                $ids[$r['employee_no']] = (int) $r['employee_id'];
            }
        }

        return $ids;
    }

    /** The month's period with its record count and why it cannot be (re)imported / deleted now; null when there is no period. */
    private function periodState(int $month, int $year): ?array
    {
        $p = App\ExecuteRow("SELECT payroll_period_id, status FROM {$this->tPeriod} WHERE fiscal_yr = {$year} AND payroll_month = {$month}", 'DB');
        if (!$p) {
            return null;
        }
        $id    = (int) $p['payroll_period_id'];
        $count = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tRecord} WHERE payroll_period_id = {$id}", 'DB');
        $dvs   = App\ExecuteScalar(
            "SELECT STRING_AGG(DISTINCT dv.dv_no, ', ') FROM {$this->tRecord} r
               JOIN {$this->tDv} dv ON dv.dv_id = r.disbursement_voucher_id
               LEFT JOIN {$this->tSt} st ON st.id = dv.status_id
              WHERE r.payroll_period_id = {$id} AND COALESCE(st.name, '') <> 'CANCELLED'",
            'DB'
        );
        $label   = self::MONTHS[$month] . ' ' . $year;
        $blocked = null;
        if (in_array(strtoupper((string) $p['status']), ['LOCKED', 'CLOSED'], true)) {
            $blocked = "The {$label} payroll period is {$p['status']}.";
        } elseif ($dvs) {
            $blocked = "The {$label} payroll is used by Disbursement Voucher {$dvs}. Cancel or delete that DV first.";
        }

        return ['payroll_period_id' => $id, 'record_count' => $count, 'blocked' => $blocked];
    }

    private function summaryOf(array $parsed, array $period): array
    {
        $t = $parsed['totals'];

        return [
            'payroll_month' => $period['month'], 'payroll_year' => $period['year'],
            'label' => self::MONTHS[$period['month']] . ' ' . $period['year'],
            'file_name' => $parsed['file_name'], 'employee_count' => count($parsed['employees']),
            'total_gross' => $t['EARNING'], 'total_deductions' => $t['DEDUCTION'], 'total_refunds' => $t['REFUND'],
            'total_net' => round($t['EARNING'] + $t['REFUND'] - $t['DEDUCTION'], 2), 'total_employer_share' => $t['EMPLOYER_SHARE'],
            'blocking' => $parsed['blocking'], 'warnings' => $parsed['warnings'], 'skipped_columns' => $parsed['skipped_columns'],
        ];
    }

    private function period(array $data): array
    {
        $m = (int) ($data['payroll_month'] ?? 0);
        $y = (int) ($data['payroll_year'] ?? 0);
        if ($m < 1 || $m > 12) {
            return ['error' => 'Choose the payroll month.'];
        }
        if ($y < 2000 || $y > 2100) {
            return ['error' => 'Choose the payroll year.'];
        }

        return ['month' => $m, 'year' => $y];
    }

    /** Template header / item code → comparison key: without the template's surrounding quotes, upper case. */
    private function itemKey(string $s): string
    {
        return strtoupper(trim(trim($s), "'"));
    }

    private function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }

    private function toNumber(mixed $v): ?float
    {
        $s = str_replace([',', ' ', '₱'], '', trim((string) $v));
        if ($s === '' || $s === '-') {
            return 0.0;
        }
        if (preg_match('/^\((.+)\)$/', $s, $m)) {
            $s = '-' . $m[1];
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /** created_by etc. reference uac.users — only stamp a user that exists there (same rule as PayrollSetupService). */
    private function userIdOrNull(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        return (int) App\ExecuteScalar("SELECT COUNT(*) FROM uac.users WHERE user_id = {$userId}", 'DB') > 0 ? $userId : null;
    }

    private function num(float $v): string
    {
        return sprintf('%.2f', $v);
    }

    private function str(string $v): string
    {
        return App\QuotedValue($v, App\DataType::STRING, 'DB');
    }

    private function strOrNull(?string $v): string
    {
        return $v === null || $v === '' ? 'NULL' : $this->str($v);
    }
}
