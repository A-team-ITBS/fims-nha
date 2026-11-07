<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

class ProjectService
{
    private string $table        = 'fims.project';
    private string $tableProgram = 'fims.program';

    private array $allowedFields = ['project_code', 'project_name', 'program_code'];

    public function fetchAlls(?int $page = null, int $limit = 10, string $search = '', string $programCode = ''): array
    {
        try {
            $where = ['1=1'];

            if ($search !== '') {
                $s       = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(p.project_code ILIKE {$s} OR p.project_name ILIKE {$s})";
            }
            if ($programCode !== '') {
                $where[] = 'p.program_code = ' . App\QuotedValue($programCode, App\DataType::STRING, 'DB');
            }
            $whereSql = implode(' AND ', $where);

            $joinSql = "SELECT p.*, pg.program_name, pg.\"group\"
                        FROM {$this->table} p
                        LEFT JOIN {$this->tableProgram} pg ON pg.program_code = p.program_code
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql . ' ORDER BY p.project_code ASC', 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} p WHERE {$whereSql}", 'DB');
            $data  = App\ExecuteRows($joinSql . " ORDER BY p.project_code ASC LIMIT {$limit} OFFSET {$offset}", 'DB');

            return [
                'success'    => true,
                'data'       => $data ?: [],
                'pagination' => [
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

    public function fetchByCode(string $code): array
    {
        try {
            $sql = "SELECT p.*, pg.program_name, pg.\"group\"
                    FROM {$this->table} p
                    LEFT JOIN {$this->tableProgram} pg ON pg.program_code = p.program_code
                    WHERE p.project_code = " . App\QuotedValue($code, App\DataType::STRING, 'DB');

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Project '{$code}' not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            $code    = trim((string) ($body['project_code'] ?? ''));
            $name    = trim((string) ($body['project_name'] ?? ''));
            $program = trim((string) ($body['program_code'] ?? ''));

            if ($code === '' || $name === '' || $program === '') {
                return ['success' => false, 'message' => 'project_code, project_name and program_code are required.'];
            }

            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE project_code = " . App\QuotedValue($code, App\DataType::STRING, 'DB'),
                'DB'
            );
            if ($exists > 0) {
                return ['success' => false, 'message' => "Project code '{$code}' already exists."];
            }

            $programExists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . App\QuotedValue($program, App\DataType::STRING, 'DB'),
                'DB'
            );
            if ($programExists === 0) {
                return ['success' => false, 'message' => "Program code '{$program}' does not exist."];
            }

            $fields = array_intersect_key($body, array_flip($this->allowedFields));

            $columns = [];
            $values  = [];
            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');
                $values[]  = App\QuotedValue($value, App\DataType::STRING, 'DB');
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $values) . ')';

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Project created successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update(string $code, array $body): array
    {
        try {
            $check = $this->fetchByCode($code);
            if (!$check['success']) {
                return $check;
            }

            // project_code is the primary key — not editable here.
            $fields = array_intersect_key($body, array_flip(['project_name', 'program_code']));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            if (!empty($fields['program_code'])) {
                $programExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . App\QuotedValue($fields['program_code'], App\DataType::STRING, 'DB'),
                    'DB'
                );
                if ($programExists === 0) {
                    return ['success' => false, 'message' => "Program code '{$fields['program_code']}' does not exist."];
                }
            }

            $setParts = [];
            foreach ($fields as $key => $value) {
                $setParts[] = App\QuotedName($key, 'DB') . ' = ' . App\QuotedValue($value, App\DataType::STRING, 'DB');
            }

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE project_code = ' . App\QuotedValue($code, App\DataType::STRING, 'DB');

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Project updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete(string $code): array
    {
        try {
            $check = $this->fetchByCode($code);
            if (!$check['success']) {
                return $check;
            }

            $sql = "DELETE FROM {$this->table} WHERE project_code = " . App\QuotedValue($code, App\DataType::STRING, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Project '{$code}' deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
