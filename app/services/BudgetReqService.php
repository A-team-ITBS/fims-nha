<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

class BudgetReqService
{
    private string $table        = 'fims.budget_req';
    private string $tableProgram = 'fims.program';
    private string $tableProject = 'fims.project';

    private function validateBody(array $body): ?array
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

            $joinSql = "SELECT br.*, pg.program_name, pj.project_name, pj.program_code AS project_program_code
                        FROM {$this->table} br
                        LEFT JOIN {$this->tableProgram} pg ON pg.program_code = br.program_code
                        LEFT JOIN {$this->tableProject} pj ON pj.project_code = br.project_code
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql . ' ORDER BY br.fiscal_yr DESC, br.id DESC', 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} br WHERE {$whereSql}", 'DB');
            $data  = App\ExecuteRows($joinSql . " ORDER BY br.fiscal_yr DESC, br.id DESC LIMIT {$limit} OFFSET {$offset}", 'DB');

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

    public function fetchById(int|string $id): array
    {
        try {
            $id  = (int) $id;
            $sql = "SELECT br.*, pg.program_name, pj.project_name
                    FROM {$this->table} br
                    LEFT JOIN {$this->tableProgram} pg ON pg.program_code = br.program_code
                    LEFT JOIN {$this->tableProject} pj ON pj.project_code = br.project_code
                    WHERE br.id = {$id}";

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

            $err = $this->validateBody($body);
            if ($err) {
                return $err;
            }

            $programCode = trim((string) ($body['program_code'] ?? ''));
            $projectCode = trim((string) ($body['project_code'] ?? ''));

            $columns = ['fiscal_yr', 'amount'];
            $values  = [
                App\QuotedValue($fiscalYr, App\DATATYPE_NUMBER, 'DB'),
                App\QuotedValue($amount, App\DATATYPE_NUMBER, 'DB'),
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
                    RETURNING id';

            $row = App\ExecuteRow($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Budget request created successfully.',
                'id'      => $row['id'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update(int|string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $err = $this->validateBody($body);
            if ($err) {
                return $err;
            }

            $fiscalYr    = $body['fiscal_yr'] ?? null;
            $amount      = $body['amount'] ?? null;
            $programCode = trim((string) ($body['program_code'] ?? ''));
            $projectCode = trim((string) ($body['project_code'] ?? ''));

            $setParts = [];

            if ($fiscalYr !== null && $fiscalYr !== '') {
                $setParts[] = 'fiscal_yr = ' . App\QuotedValue($fiscalYr, App\DATATYPE_NUMBER, 'DB');
            }
            if ($amount !== null && $amount !== '' && is_numeric($amount)) {
                $setParts[] = 'amount = ' . App\QuotedValue($amount, App\DATATYPE_NUMBER, 'DB');
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
                    WHERE id = ' . (int) $id;

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Budget request updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $sql = "DELETE FROM {$this->table} WHERE id = " . (int) $id;
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Budget request #{$id} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
