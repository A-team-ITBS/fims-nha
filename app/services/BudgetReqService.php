<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

class BudgetReqService
{
    private string $table              = 'fims.budget_req';
    private string $tableProgram       = 'fims.program';
    private string $tableProject       = 'fims.project';
    private string $tableExpenseClass  = 'fims.expense_class';

    private function validateBody(array $body, bool $requireExpenseClass): ?array
    {
        $programCode = trim((string) ($body['program_code'] ?? ''));
        $projectCode = trim((string) ($body['project_code'] ?? ''));

        if (($programCode !== '') === ($projectCode !== '')) {
            return ['success' => false, 'message' => 'Provide exactly one of program_code or project_code.'];
        }

        if ($programCode !== '') {
            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . App\QuotedValue($programCode, App\DATATYPE_STRING, 'DB'),
                'DB'
            );
            if ($exists === 0) {
                return ['success' => false, 'message' => "Program code '{$programCode}' does not exist."];
            }
        }

        if ($projectCode !== '') {
            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableProject} WHERE project_code = " . App\QuotedValue($projectCode, App\DATATYPE_STRING, 'DB'),
                'DB'
            );
            if ($exists === 0) {
                return ['success' => false, 'message' => "Project code '{$projectCode}' does not exist."];
            }
        }

        $expenseClassId = trim((string) ($body['expense_class_id'] ?? ''));
        if ($expenseClassId === '') {
            if ($requireExpenseClass) {
                return ['success' => false, 'message' => 'expense_class_id is required.'];
            }
        } else {
            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableExpenseClass} WHERE expense_class_id = " . (int) $expenseClassId . ' AND deleted_at IS NULL',
                'DB'
            );
            if ($exists === 0) {
                return ['success' => false, 'message' => "Expense class #{$expenseClassId} does not exist."];
            }
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

            $joinSql = "SELECT br.*, pg.program_name, pj.project_name, ec.expense_class_code, ec.expense_class_name
                        FROM {$this->table} br
                        LEFT JOIN {$this->tableProgram} pg ON pg.program_code = br.program_code
                        LEFT JOIN {$this->tableProject} pj ON pj.project_code = br.project_code
                        LEFT JOIN {$this->tableExpenseClass} ec ON ec.expense_class_id = br.expense_class_id
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql . ' ORDER BY br.fiscal_yr DESC, br.budget_reference_number DESC', 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} br WHERE {$whereSql}", 'DB');
            $data  = App\ExecuteRows($joinSql . " ORDER BY br.fiscal_yr DESC, br.budget_reference_number DESC LIMIT {$limit} OFFSET {$offset}", 'DB');

            $totalAmount = (float) App\ExecuteScalar("SELECT COALESCE(SUM(br.amount), 0) FROM {$this->table} br WHERE {$whereSql}", 'DB');

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
            $sql = "SELECT br.*, pg.program_name, pj.project_name, ec.expense_class_code, ec.expense_class_name
                    FROM {$this->table} br
                    LEFT JOIN {$this->tableProgram} pg ON pg.program_code = br.program_code
                    LEFT JOIN {$this->tableProject} pj ON pj.project_code = br.project_code
                    LEFT JOIN {$this->tableExpenseClass} ec ON ec.expense_class_id = br.expense_class_id
                    WHERE br.budget_reference_number = {$id}";

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Budget request #{$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            $fiscalYr = trim((string) ($body['fiscal_yr'] ?? ''));
            $amount   = $body['amount'] ?? '';

            if ($fiscalYr === '' || $amount === '' || !is_numeric($amount)) {
                return ['success' => false, 'message' => 'fiscal_yr and a numeric amount are required.'];
            }

            $err = $this->validateBody($body, true);
            if ($err) {
                return $err;
            }

            $programCode    = trim((string) ($body['program_code'] ?? ''));
            $projectCode    = trim((string) ($body['project_code'] ?? ''));
            $expenseClassId = trim((string) ($body['expense_class_id'] ?? ''));

            $columns = ['fiscal_yr', 'amount', 'expense_class_id'];
            $values  = [
                App\QuotedValue($fiscalYr, App\DATATYPE_NUMBER, 'DB'),
                App\QuotedValue($amount, App\DATATYPE_NUMBER, 'DB'),
                App\QuotedValue($expenseClassId, App\DATATYPE_NUMBER, 'DB'),
            ];

            if ($programCode !== '') {
                $columns[] = 'program_code';
                $values[]  = App\QuotedValue($programCode, App\DATATYPE_STRING, 'DB');
            } else {
                $columns[] = 'project_code';
                $values[]  = App\QuotedValue($projectCode, App\DATATYPE_STRING, 'DB');
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

    public function update(int|string $budgetReferenceNumber, array $body): array
    {
        try {
            $check = $this->fetchById($budgetReferenceNumber);
            if (!$check['success']) {
                return $check;
            }

            $err = $this->validateBody($body, false);
            if ($err) {
                return $err;
            }

            $fiscalYr       = $body['fiscal_yr'] ?? null;
            $amount         = $body['amount'] ?? null;
            $programCode    = trim((string) ($body['program_code'] ?? ''));
            $projectCode    = trim((string) ($body['project_code'] ?? ''));
            $expenseClassId = trim((string) ($body['expense_class_id'] ?? ''));

            $setParts = [];

            if ($fiscalYr !== null && $fiscalYr !== '') {
                $setParts[] = 'fiscal_yr = ' . App\QuotedValue($fiscalYr, App\DATATYPE_NUMBER, 'DB');
            }
            if ($amount !== null && $amount !== '' && is_numeric($amount)) {
                $setParts[] = 'amount = ' . App\QuotedValue($amount, App\DATATYPE_NUMBER, 'DB');
            }
            if ($expenseClassId !== '') {
                $setParts[] = 'expense_class_id = ' . App\QuotedValue($expenseClassId, App\DATATYPE_NUMBER, 'DB');
            }

            // program_code / project_code are togglable as a pair — whichever
            // side was provided in the body wins, and the other side is cleared
            // so the chk_budget_req_program_xor_project constraint stays satisfied.
            if ($programCode !== '') {
                $setParts[] = 'program_code = ' . App\QuotedValue($programCode, App\DATATYPE_STRING, 'DB');
                $setParts[] = 'project_code = NULL';
            } elseif ($projectCode !== '') {
                $setParts[] = 'project_code = ' . App\QuotedValue($projectCode, App\DATATYPE_STRING, 'DB');
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
