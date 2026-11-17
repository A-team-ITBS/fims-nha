<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

class ProgramService
{
    private string $table    = 'fims.program';
    private string $tableSub = 'fims.sub_program';

    private array $allowedFields = ['program_code', 'program_name'];

    /**
     * @param bool $withSubPrograms the Programs page's tree: each program row also carries `sub_programs` (its fims.sub_program rows, by
     *                              code) and `sub_program_count`, and a search also matches a program whose SUB-PROGRAM's code or name
     *                              matches (it then comes back with all of its sub-programs). Off by default: the dropdowns that call this
     *                              (/program/all) keep getting plain program rows.
     */
    public function fetchAlls(?int $page = null, int $limit = 10, string $search = '', bool $withSubPrograms = false): array
    {
        try {
            $where = '1=1';
            if ($search !== '') {
                $s   = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $sub = $withSubPrograms
                    ? " OR EXISTS (SELECT 1 FROM {$this->tableSub} sp WHERE sp.program_code = {$this->table}.program_code"
                        . " AND (sp.sub_program_code ILIKE {$s} OR sp.sub_program_name ILIKE {$s}))"
                    : '';
                $group = $this->hasGroupColumn() ? " OR \"group\" ILIKE {$s}" : '';
                $where = "(program_code ILIKE {$s} OR program_name ILIKE {$s}{$group}{$sub})";
            }

            if ($page === null) {
                $sql  = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY program_code ASC";
                $data = App\ExecuteRows($sql, 'DB');

                return ['success' => true, 'data' => $withSubPrograms ? $this->attachSubPrograms($data ?: []) : ($data ?: [])];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", 'DB');

            $sql  = "SELECT * FROM {$this->table}
                     WHERE {$where}
                     ORDER BY program_code ASC
                     LIMIT {$limit} OFFSET {$offset}";
            $data = App\ExecuteRows($sql, 'DB');

            // The Programs page's tree also shows how many sub-programs there are in all — under EVERY program the search matches, not only the
            // page of programs on screen (the rows carry only their own page's sub-programs).
            $totals = $withSubPrograms
                ? ['totals' => ['sub_programs' => (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableSub} all_sub WHERE all_sub.program_code IN (SELECT program_code FROM {$this->table} WHERE {$where})",
                    'DB'
                )]]
                : [];

            return $totals + [
                'success'    => true,
                'data'       => $withSubPrograms ? $this->attachSubPrograms($data ?: []) : ($data ?: []),
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

    /**
     * Does fims.program have a "group" column? The search looks in it when it does. The table as "01 - table/03" creates it has only the
     * code and the name, so a database built from the repo has no such column and a search that named it would fail outright.
     */
    private function hasGroupColumn(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'fims' AND table_name = 'program' AND column_name = 'group'",
                'DB'
            ) > 0;
        }

        return $has;
    }

    /**
     * Adds `sub_programs` (that program's fims.sub_program rows, by code) and `sub_program_count` to each program row — one query for the
     * whole page of programs, not one per program.
     */
    private function attachSubPrograms(array $programs): array
    {
        if (!$programs) {
            return $programs;
        }

        $in = implode(', ', array_map(
            fn ($row) => App\QuotedValue((string) $row['program_code'], DataType::STRING, 'DB'),
            $programs
        ));
        $subs = App\ExecuteRows(
            "SELECT sub_program_code, sub_program_name, program_code FROM {$this->tableSub}
             WHERE program_code IN ({$in}) ORDER BY sub_program_code ASC",
            'DB'
        ) ?: [];

        $byProgram = [];
        foreach ($subs as $sub) {
            $byProgram[(string) $sub['program_code']][] = $sub;
        }
        foreach ($programs as &$row) {
            $row['sub_programs']      = $byProgram[(string) $row['program_code']] ?? [];
            $row['sub_program_count'] = count($row['sub_programs']);
        }
        unset($row);

        return $programs;
    }

    public function fetchByCode(string $code): array
    {
        try {
            $sql  = "SELECT * FROM {$this->table} WHERE program_code = " . App\QuotedValue($code, DataType::STRING, 'DB');
            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Program '{$code}' not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Creates a program. A 3-digit program_code in $body is saved as typed (must be unused);
     * when it is empty the next sequential code comes from GlobalService::generateProgramCode().
     * The code actually saved is returned in data.program_code.
     */
    public function create(array $body, ?int $userId = null): array
    {
        try {
            $name = trim((string) ($body['program_name'] ?? ''));

            if ($name === '') {
                return ['success' => false, 'message' => 'program_name is required.'];
            }

            // A code typed in the form is used as-is (3 digits, must be unused); empty -> next sequential code.
            $manual = trim((string) ($body['program_code'] ?? ''));
            if ($manual !== '') {
                if (!preg_match('/^[0-9]{3}$/', $manual)) {
                    return ['success' => false, 'message' => 'Program code must be exactly 3 digits (e.g. 200).'];
                }
                $code = $manual;
            } else {
                $code = GlobalService::generateProgramCode($userId);
            }
            $body['program_code'] = $code;
            $body['program_name'] = $name;

            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE program_code = " . App\QuotedValue($code, DataType::STRING, 'DB'),
                'DB'
            );
            if ($exists > 0) {
                return ['success' => false, 'message' => "Program code '{$code}' already exists."];
            }

            $fields = array_intersect_key($body, array_flip($this->allowedFields));

            $columns = [];
            $values  = [];
            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');
                $values[]  = App\QuotedValue($value, DataType::STRING, 'DB');
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $values) . ')';

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Program {$code} created successfully.", 'data' => ['program_code' => $code]];
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

            // program_code is the primary key — not editable here.
            $fields = array_intersect_key($body, array_flip(['program_name']));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            $setParts = [];
            foreach ($fields as $key => $value) {
                $setParts[] = App\QuotedName($key, 'DB') . ' = ' . App\QuotedValue($value, DataType::STRING, 'DB');
            }

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE program_code = ' . App\QuotedValue($code, DataType::STRING, 'DB');

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Program updated successfully.'];
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

            // sub_program.program_code references this program with no cascade: refuse with a message rather than let the database's error out
            $subCount = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableSub} WHERE program_code = " . App\QuotedValue($code, DataType::STRING, 'DB'),
                'DB'
            );
            if ($subCount > 0) {
                return ['success' => false, 'message' => "Cannot delete '{$code}' — it still has {$subCount} sub-program(s). Delete them first."];
            }

            $inUse = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM fims.project WHERE program_code = " . App\QuotedValue($code, DataType::STRING, 'DB'),
                'DB'
            );
            if ($inUse > 0) {
                return ['success' => false, 'message' => "Cannot delete '{$code}' — {$inUse} project(s) still reference this program."];
            }

            // Anything else that points at the program (budget requests, memo requests, …): say so rather than let the database's own
            // "violates foreign key constraint" error out. (The two checks above stay as they are: their messages are what users know.)
            $used = GlobalService::describeReferences(GlobalService::referencesTo($this->table, $code), [
                'budget_req_program' => 'budget request(s)',
                'budget_req'         => 'budget request(s)',
                'budget_allocation'  => 'memo request(s)',
            ]);
            if ($used !== '') {
                return ['success' => false, 'message' => "Cannot delete '{$code}' — it is still used by {$used}."];
            }

            $sql = "DELETE FROM {$this->table} WHERE program_code = " . App\QuotedValue($code, DataType::STRING, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Program '{$code}' deleted successfully."];
        } catch (\Exception $e) {
            // used by something between the checks and the DELETE: a message, not the raw database error
            if (stripos($e->getMessage(), 'violates foreign key constraint') !== false) {
                return ['success' => false, 'message' => "Cannot delete '{$code}' — other records still use it."];
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
