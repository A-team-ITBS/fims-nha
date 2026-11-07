<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * Header row for a budget request. Shape depends on the expense class:
 *   CO (and anything not COA-driven) -> program_code XOR project_code, amount, location — this
 *                                        header IS the whole record, no line items.
 *   PS / MOOE                        -> program_code/project_code/amount/location left NULL;
 *                                        the real allocations live in fims.budget_line_items,
 *                                        added via createWithLineItems() / BudgetLineItemService.
 */
class BudgetReqService
{
    private string $table              = 'fims.budget_req';
    private string $tableLineItems     = 'fims.budget_line_items';
    private string $tableProgram       = 'fims.program';
    private string $tableProject       = 'fims.project';
    private string $tableExpenseClass  = 'fims.expense_class';
    private string $tableChartOfAccounts = 'fims.chart_of_accounts';

    // Expense classes whose allocations live in fims.budget_line_items instead
    // of this header, and the COA code prefix each one is scoped to.
    private array $coaPrefixByExpenseClassCode = [
        'PS'   => '5-01',
        'MOOE' => '5-02',
    ];

    private function getExpenseClassCode(string $expenseClassId): ?string
    {
        $row = App\ExecuteRow(
            "SELECT expense_class_code FROM {$this->tableExpenseClass}
             WHERE expense_class_id = " . (int) $expenseClassId . ' AND deleted_at IS NULL',
            'DB'
        );

        return $row['expense_class_code'] ?? null;
    }

    private function getCoaPrefix(string $expenseClassId): ?string
    {
        $code = $this->getExpenseClassCode($expenseClassId);

        return $code !== null ? ($this->coaPrefixByExpenseClassCode[$code] ?? null) : null;
    }

    /**
     * Looked up by name each time rather than hardcoding its id — the id a seed
     * row lands on depends on insert order, which isn't guaranteed to be stable
     * across environments or re-seeds.
     */
    private function getDefaultStatusId(): ?int
    {
        $row = App\ExecuteRow(
            "SELECT id FROM fims.settings_status WHERE status_for = 'budget_req' AND name = 'For Review'",
            'DB'
        );

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    private function validateLineItem(array $lineItem, string $coaPrefix, int $index): ?string
    {
        $accountId   = trim((string) ($lineItem['account_id'] ?? ''));
        $amount      = $lineItem['requested_amount'] ?? $lineItem['amount'] ?? '';
        $particulars = trim((string) ($lineItem['particulars'] ?? ''));
        $label       = 'Line item ' . ($index + 1) . ': ';

        if ($accountId === '') {
            return $label . 'select a chart of accounts entry.';
        }
        if ($amount === '' || !is_numeric($amount)) {
            return $label . 'a numeric amount is required.';
        }
        if ($particulars === '') {
            return $label . 'particulars is required.';
        }

        $account = App\ExecuteRow(
            "SELECT account_code FROM {$this->tableChartOfAccounts}
             WHERE account_id = " . App\QuotedValue($accountId, DataType::STRING, 'DB'),
            'DB'
        );
        if (!$account) {
            return $label . "chart of accounts entry '{$accountId}' does not exist.";
        }
        $code = $account['account_code'];
        if ($code !== $coaPrefix && !str_starts_with($code, $coaPrefix . '-')) {
            return $label . "'{$code}' is not under the {$coaPrefix} branch required for this expense class.";
        }

        return null;
    }

    public function fetchAlls(?int $page = null, int $limit = 10, string $fiscalYr = '', string $type = ''): array
    {
        try {
            $where = ['1=1'];

            if ($fiscalYr !== '') {
                $where[] = 'br.fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($type === 'PROGRAM') {
                $where[] = 'br.program_code IS NOT NULL';
            } elseif ($type === 'PROJECT') {
                $where[] = 'br.project_code IS NOT NULL';
            }
            $whereSql = implode(' AND ', $where);

            // li_count / li_total summarize a PS/MOOE header's line items for display
            // in this header-level list — the per-line-item breakdown lives in
            // BudgetLineItemService, used by the Chart of Accounts filtered view.
            $joinSql = "SELECT br.*, pg.program_name, pj.project_name, ec.expense_class_code, ec.expense_class_name,
                               st.name AS status_name, st.color AS status_color,
                               li.li_count, li.li_total
                        FROM {$this->table} br
                        LEFT JOIN {$this->tableProgram} pg ON pg.program_code = br.program_code
                        LEFT JOIN {$this->tableProject} pj ON pj.project_code = br.project_code
                        LEFT JOIN {$this->tableExpenseClass} ec ON ec.expense_class_id = br.expense_class_id
                        LEFT JOIN fims.settings_status st ON st.id = br.status_id
                        LEFT JOIN (
                            SELECT budget_reference_number, COUNT(*) AS li_count, SUM(requested_amount) AS li_total
                            FROM {$this->tableLineItems}
                            GROUP BY budget_reference_number
                        ) li ON li.budget_reference_number = br.budget_reference_number
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql . ' ORDER BY br.fiscal_yr DESC, br.budget_reference_number DESC', 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} br WHERE {$whereSql}", 'DB');
            $data  = App\ExecuteRows($joinSql . " ORDER BY br.fiscal_yr DESC, br.budget_reference_number DESC LIMIT {$limit} OFFSET {$offset}", 'DB');

            // Two independent sums rather than a correlated subquery — the header sum
            // covers CO rows' own amount; the line-item sum covers PS/MOOE rows' detail,
            // included only in the "All" view (a PROGRAM/PROJECT filter already excludes
            // COA-driven headers via $whereSql, so their line items shouldn't count either).
            $headerAmount = (float) App\ExecuteScalar("SELECT COALESCE(SUM(br.amount), 0) FROM {$this->table} br WHERE {$whereSql}", 'DB');

            $lineItemAmount = 0.0;
            if ($type === '') {
                $liWhere = $fiscalYr !== '' ? 'WHERE br.fiscal_yr = ' . (int) $fiscalYr : '';
                $lineItemAmount = (float) App\ExecuteScalar(
                    "SELECT COALESCE(SUM(li.requested_amount), 0) FROM {$this->tableLineItems} li
                     JOIN {$this->table} br ON br.budget_reference_number = li.budget_reference_number
                     {$liWhere}",
                    'DB'
                );
            }

            $totalAmount = $headerAmount + $lineItemAmount;

            return [
                'success'      => true,
                'data'         => $data ?: [],
                'total_amount' => $totalAmount,
                'pagination'   => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchById(int|string $budgetReferenceNumber): array
    {
        try {
            $id  = (int) $budgetReferenceNumber;
            $sql = "SELECT br.*, pg.program_name, pj.project_name, ec.expense_class_code, ec.expense_class_name,
                           st.name AS status_name, st.color AS status_color
                    FROM {$this->table} br
                    LEFT JOIN {$this->tableProgram} pg ON pg.program_code = br.program_code
                    LEFT JOIN {$this->tableProject} pj ON pj.project_code = br.project_code
                    LEFT JOIN {$this->tableExpenseClass} ec ON ec.expense_class_id = br.expense_class_id
                    LEFT JOIN fims.settings_status st ON st.id = br.status_id
                    WHERE br.budget_reference_number = {$id}";

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Budget request #{$id} not found."];
            }

            $lineItems = App\ExecuteRows(
                "SELECT li.*, coa.account_code, coa.account_title
                 FROM {$this->tableLineItems} li
                 LEFT JOIN {$this->tableChartOfAccounts} coa ON coa.account_id = li.account_id
                 WHERE li.budget_reference_number = {$id}
                 ORDER BY li.budget_line_item_id ASC",
                'DB'
            );
            $data['line_items'] = $lineItems ?: [];

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * CO-style single-row create: program_code XOR project_code, amount, optional location.
     * Rejects expense classes that are COA-driven — use createWithLineItems() for those.
     */
    public function create(array $body): array
    {
        try {
            $fiscalYr       = trim((string) ($body['fiscal_yr'] ?? ''));
            $amount         = $body['amount'] ?? '';
            $expenseClassId = trim((string) ($body['expense_class_id'] ?? ''));
            $programCode    = trim((string) ($body['program_code'] ?? ''));
            $projectCode    = trim((string) ($body['project_code'] ?? ''));
            $location       = trim((string) ($body['location'] ?? ''));

            if ($fiscalYr === '' || $expenseClassId === '' || $amount === '' || !is_numeric($amount)) {
                return ['success' => false, 'message' => 'fiscal_yr, expense_class_id, and a numeric amount are required.'];
            }

            $expenseClassCode = $this->getExpenseClassCode($expenseClassId);
            if ($expenseClassCode === null) {
                return ['success' => false, 'message' => "Expense class #{$expenseClassId} does not exist."];
            }
            if (isset($this->coaPrefixByExpenseClassCode[$expenseClassCode])) {
                return ['success' => false, 'message' => "Expense class '{$expenseClassCode}' allocates through line items — use the line items builder."];
            }

            if (($programCode !== '') === ($projectCode !== '')) {
                return ['success' => false, 'message' => 'Provide exactly one of program_code or project_code.'];
            }
            if ($programCode !== '') {
                $exists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . App\QuotedValue($programCode, DataType::STRING, 'DB'),
                    'DB'
                );
                if ($exists === 0) {
                    return ['success' => false, 'message' => "Program code '{$programCode}' does not exist."];
                }
            } else {
                $exists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableProject} WHERE project_code = " . App\QuotedValue($projectCode, DataType::STRING, 'DB'),
                    'DB'
                );
                if ($exists === 0) {
                    return ['success' => false, 'message' => "Project code '{$projectCode}' does not exist."];
                }
            }

            $columns = ['fiscal_yr', 'expense_class_id', 'amount'];
            $values  = [
                App\QuotedValue($fiscalYr, DataType::NUMBER, 'DB'),
                App\QuotedValue($expenseClassId, DataType::NUMBER, 'DB'),
                App\QuotedValue($amount, DataType::NUMBER, 'DB'),
            ];
            if ($programCode !== '') {
                $columns[] = 'program_code';
                $values[]  = App\QuotedValue($programCode, DataType::STRING, 'DB');
            } else {
                $columns[] = 'project_code';
                $values[]  = App\QuotedValue($projectCode, DataType::STRING, 'DB');
            }
            if ($location !== '') {
                $columns[] = 'location';
                $values[]  = App\QuotedValue($location, DataType::STRING, 'DB');
            }

            $defaultStatusId = $this->getDefaultStatusId();
            if ($defaultStatusId !== null) {
                $columns[] = 'status_id';
                $values[]  = $defaultStatusId;
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $values) . ')
                    RETURNING budget_reference_number';

            $row = App\ExecuteRow($sql, 'DB');

            return [
                'success'                 => true,
                'message'                 => 'Budget request created successfully.',
                'budget_reference_number' => $row['budget_reference_number'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * PS/MOOE create: a bare header (fiscal_yr + expense_class_id only) plus one
     * or more fims.budget_line_items rows — the "+ Add Line Item" flow.
     * Validates every line item before inserting anything.
     */
    public function createWithLineItems(array $header, array $lineItems): array
    {
        try {
            $fiscalYr       = trim((string) ($header['fiscal_yr'] ?? ''));
            $expenseClassId = trim((string) ($header['expense_class_id'] ?? ''));

            if ($fiscalYr === '' || $expenseClassId === '') {
                return ['success' => false, 'message' => 'fiscal_yr and expense_class_id are required.'];
            }
            if (empty($lineItems)) {
                return ['success' => false, 'message' => 'At least one line item is required.'];
            }

            $coaPrefix = $this->getCoaPrefix($expenseClassId);
            if ($coaPrefix === null) {
                return ['success' => false, 'message' => 'This expense class does not allocate through line items.'];
            }

            foreach ($lineItems as $i => $lineItem) {
                $err = $this->validateLineItem($lineItem, $coaPrefix, $i);
                if ($err) {
                    return ['success' => false, 'message' => $err];
                }
            }

            $headerColumns = ['fiscal_yr', 'expense_class_id'];
            $headerValues  = [
                App\QuotedValue($fiscalYr, DataType::NUMBER, 'DB'),
                App\QuotedValue($expenseClassId, DataType::NUMBER, 'DB'),
            ];
            $defaultStatusId = $this->getDefaultStatusId();
            if ($defaultStatusId !== null) {
                $headerColumns[] = 'status_id';
                $headerValues[]  = $defaultStatusId;
            }

            $headerRow = App\ExecuteRow(
                "INSERT INTO {$this->table} (" . implode(', ', $headerColumns) . ')
                 VALUES (' . implode(', ', $headerValues) . ')
                 RETURNING budget_reference_number',
                'DB'
            );
            $budgetReferenceNumber = $headerRow['budget_reference_number'] ?? null;

            $lineItemService = new BudgetLineItemService();
            $createdIds      = [];
            foreach ($lineItems as $lineItem) {
                $result = $lineItemService->create(array_merge($lineItem, [
                    'budget_reference_number' => $budgetReferenceNumber,
                ]));
                $createdIds[] = $result['budget_line_item_id'] ?? null;
            }

            return [
                'success'                 => true,
                'message'                 => count($createdIds) . ' budget request line item(s) created successfully.',
                'budget_reference_number' => $budgetReferenceNumber,
                'line_item_ids'           => $createdIds,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** CO-style header update. For a PS/MOOE header, use BudgetLineItemService to edit its line items instead. */
    public function update(int|string $budgetReferenceNumber, array $body): array
    {
        try {
            $check = $this->fetchById($budgetReferenceNumber);
            if (!$check['success']) {
                return $check;
            }

            $fiscalYr       = $body['fiscal_yr'] ?? null;
            $amount         = $body['amount'] ?? null;
            $programCode    = trim((string) ($body['program_code'] ?? ''));
            $projectCode    = trim((string) ($body['project_code'] ?? ''));
            $expenseClassId = trim((string) ($body['expense_class_id'] ?? ''));
            $location       = $body['location'] ?? null;

            $setParts = [];

            if ($fiscalYr !== null && $fiscalYr !== '') {
                $setParts[] = 'fiscal_yr = ' . App\QuotedValue($fiscalYr, DataType::NUMBER, 'DB');
            }
            if ($amount !== null && $amount !== '' && is_numeric($amount)) {
                $setParts[] = 'amount = ' . App\QuotedValue($amount, DataType::NUMBER, 'DB');
            }
            if ($expenseClassId !== '') {
                $setParts[] = 'expense_class_id = ' . App\QuotedValue($expenseClassId, DataType::NUMBER, 'DB');
            }
            if ($location !== null) {
                $setParts[] = 'location = ' . ($location === '' ? 'NULL' : App\QuotedValue($location, DataType::STRING, 'DB'));
            }

            if ($programCode !== '') {
                $exists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . App\QuotedValue($programCode, DataType::STRING, 'DB'),
                    'DB'
                );
                if ($exists === 0) {
                    return ['success' => false, 'message' => "Program code '{$programCode}' does not exist."];
                }
                $setParts[] = 'program_code = ' . App\QuotedValue($programCode, DataType::STRING, 'DB');
                $setParts[] = 'project_code = NULL';
            } elseif ($projectCode !== '') {
                $exists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableProject} WHERE project_code = " . App\QuotedValue($projectCode, DataType::STRING, 'DB'),
                    'DB'
                );
                if ($exists === 0) {
                    return ['success' => false, 'message' => "Project code '{$projectCode}' does not exist."];
                }
                $setParts[] = 'project_code = ' . App\QuotedValue($projectCode, DataType::STRING, 'DB');
                $setParts[] = 'program_code = NULL';
            }

            if (empty($setParts)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE budget_reference_number = ' . (int) $budgetReferenceNumber;

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Budget request updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Deletes the header — fims.budget_line_items cascades via ON DELETE CASCADE. */
    public function delete(int|string $budgetReferenceNumber): array
    {
        try {
            $check = $this->fetchById($budgetReferenceNumber);
            if (!$check['success']) {
                return $check;
            }

            $sql = "DELETE FROM {$this->table} WHERE budget_reference_number = " . (int) $budgetReferenceNumber;
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Budget request #{$budgetReferenceNumber} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
