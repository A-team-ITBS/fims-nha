<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

class ChartOfAccountsService
{
    private string $table = 'shared.chart_of_accounts';

    private array $allowedFields = [
        'account_code', 'account_title', 'account_type_id', 'account_class_id',
        'expense_class_id', 'allow_transactions', 'is_active', 'books', 'libis_acct_type',
        'account_description', 'normal_balance', 'parent_account_code', 'level',
        'is_control_account', 'sort_order', 'superseded_by_account_code',
    ];

    public function getUserScope(int $userId): array
    {
        return ['is_admin' => true, 'region_code' => null, 'region_name' => null];
    }

    public function fetchAlls(
        ?int $page = null,
        int $limit = 10,
        string $search = '',
        string $accountType = '',
        string $regionCode = '',
        string $projectCode = '',
        string $status = ''
    ): array {
        try {
            $where = ['is_active = true'];

            if ($search !== '') {
                $s = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(account_code ILIKE {$s} OR account_title ILIKE {$s})";
            }

            $whereSql = implode(' AND ', $where);

            $sql = "SELECT * FROM {$this->table} WHERE {$whereSql} ORDER BY account_code ASC";

            if ($page === null) {
                $data = App\ExecuteRows($sql, 'DB');
                return ['success' => true, 'data' => $data ?: []];
            }

            $page = max(1, $page);
            $limit = max(1, $limit);
            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE {$whereSql}", 'DB');
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
            $data = App\ExecuteRows($sql, 'DB');

            return [
                'success' => true,
                'data' => $data ?: [],
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchByPrefix(string $prefix): array
    {
        try {
            $p = App\QuotedValue($prefix, DataType::STRING, 'DB');
            $pDash = App\QuotedValue($prefix . '-%', DataType::STRING, 'DB');

            $sql = "SELECT * FROM {$this->table}
                    WHERE (account_code = {$p} OR account_code LIKE {$pDash})
                      AND allow_transactions = TRUE
                      AND is_active = TRUE
                    ORDER BY account_code ASC";

            $data = App\ExecuteRows($sql, 'DB');
            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchPostable(string $search = '', bool $forBudgetProposalOnly = false): array
    {
        try {
            $where = "is_active = TRUE AND allow_transactions = TRUE";
            if ($search !== '') {
                $s = App\QuotedValue('%' . $search . '%', DataType::STRING, 'DB');
                $where .= " AND (account_code ILIKE {$s} OR account_title ILIKE {$s})";
            }

            $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY account_code ASC";
            $data = App\ExecuteRows($sql, 'DB');

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchById(string $id): array
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE account_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB');
            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Account not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            $code = trim((string) ($body['account_code'] ?? ''));
            $title = trim((string) ($body['account_title'] ?? ''));

            if ($code === '' || $title === '') {
                return ['success' => false, 'message' => 'account_code and account_title are required.'];
            }

            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE account_code = " . App\QuotedValue($code, App\DataType::STRING, 'DB'),
                'DB'
            );
            if ($exists > 0) {
                return ['success' => false, 'message' => "Account code '{$code}' already exists."];
            }

            $fields = array_intersect_key($body, array_flip($this->allowedFields));

            foreach ($this->allowedFields as $k) {
                if (array_key_exists($k, $fields) && trim((string) $fields[$k]) === '') {
                    $fields[$k] = null;
                }
            }

            if (array_key_exists('allow_transactions', $fields)) {
                $fields['allow_transactions'] = App\ConvertToBool($fields['allow_transactions']) ? 'true' : 'false';
            }
            if (array_key_exists('is_active', $fields)) {
                $fields['is_active'] = App\ConvertToBool($fields['is_active']) ? 'true' : 'false';
            }

            $columns = [];
            $values = [];
            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');
                $values[] = $value === null ? 'NULL' : App\QuotedValue($value, App\DataType::STRING, 'DB');
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')";
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Account created successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update(string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $fields = array_intersect_key($body, array_flip($this->allowedFields));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            foreach ($this->allowedFields as $k) {
                if (array_key_exists($k, $fields) && trim((string) $fields[$k]) === '') {
                    $fields[$k] = null;
                }
            }

            if (array_key_exists('allow_transactions', $fields)) {
                $fields['allow_transactions'] = App\ConvertToBool($fields['allow_transactions']) ? 'true' : 'false';
            }
            if (array_key_exists('is_active', $fields)) {
                $fields['is_active'] = App\ConvertToBool($fields['is_active']) ? 'true' : 'false';
            }

            $setParts = [];
            foreach ($fields as $key => $value) {
                $setParts[] = App\QuotedName($key, 'DB') . ' = ' . ($value === null ? 'NULL' : App\QuotedValue($value, App\DataType::STRING, 'DB'));
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . ' WHERE account_id = ' . App\QuotedValue($id, App\DataType::STRING, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Account updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggleStatus(string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $newStatus = (bool) ($check['data']['is_active'] ?? false) ? 'false' : 'true';

            App\Execute(
                "UPDATE {$this->table} SET is_active = {$newStatus} WHERE account_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB'),
                'DB'
            );

            return ['success' => true, 'message' => 'Account status updated.', 'data' => ['is_active' => $newStatus === 'true']];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete(string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            App\Execute(
                "UPDATE {$this->table} SET is_active = false WHERE account_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB'),
                'DB'
            );

            return ['success' => true, 'message' => "Account deactivated successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchRegions(): array
    {
        return ['success' => true, 'data' => []];
    }
}
