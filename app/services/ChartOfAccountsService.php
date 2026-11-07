<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

class ChartOfAccountsService
{
    private string $table = 'fims.chart_of_accounts';

    private array $allowedFields = [
        'account_code', 'account_title', 'account_type', 'parent_account_id',
        'posting_allowed', 'status', 'source_document',
    ];

    public function fetchAlls(
        ?int $page = null,
        int $limit = 10,
        string $search = '',
        string $accountType = '',
        string $status = ''
    ): array {
        try {
            $where = ['1=1'];

            if ($search !== '') {
                $s       = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(c.account_code ILIKE {$s} OR c.account_title ILIKE {$s})";
            }
            if ($accountType !== '') {
                $where[] = 'c.account_type = ' . App\QuotedValue($accountType, App\DataType::STRING, 'DB');
            }
            if ($status !== '') {
                $where[] = 'c.status = ' . App\QuotedValue($status, App\DataType::STRING, 'DB');
            }
            $whereSql = implode(' AND ', $where);

            $joinSql = "SELECT c.*,
                               p.account_code AS parent_account_code, p.account_title AS parent_account_title
                        FROM {$this->table} c
                        LEFT JOIN {$this->table} p ON p.account_id = c.parent_account_id
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql . ' ORDER BY c.account_code ASC', 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} c WHERE {$whereSql}", 'DB');
            $data  = App\ExecuteRows($joinSql . " ORDER BY c.account_code ASC LIMIT {$limit} OFFSET {$offset}", 'DB');

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

    public function fetchById(string $id): array
    {
        try {
            $sql = "SELECT c.*,
                           p.account_code AS parent_account_code, p.account_title AS parent_account_title
                    FROM {$this->table} c
                    LEFT JOIN {$this->table} p ON p.account_id = c.parent_account_id
                    WHERE c.account_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB');

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Account '{$id}' not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            $code  = trim((string) ($body['account_code'] ?? ''));
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

            $parentId = trim((string) ($body['parent_account_id'] ?? ''));
            if ($parentId !== '') {
                $parentExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->table} WHERE account_id = " . App\QuotedValue($parentId, App\DataType::STRING, 'DB'),
                    'DB'
                );
                if ($parentExists === 0) {
                    return ['success' => false, 'message' => "Parent account '{$parentId}' does not exist."];
                }
            }

            $accountId = $this->generateAccountId();

            $fields = array_intersect_key($body, array_flip($this->allowedFields));
            $fields['account_id'] = $accountId;
            // Normalize empty-string FKs/optional fields to NULL
            foreach (['parent_account_id', 'source_document'] as $k) {
                if (array_key_exists($k, $fields) && trim((string) $fields[$k]) === '') {
                    $fields[$k] = null;
                }
            }
            if (array_key_exists('posting_allowed', $fields)) {
                $fields['posting_allowed'] = App\ConvertToBool($fields['posting_allowed']) ? 'true' : 'false';
            }

            $columns = [];
            $values  = [];
            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');
                $values[]  = $value === null
                    ? 'NULL'
                    : ($key === 'posting_allowed' ? $value : App\QuotedValue($value, App\DataType::STRING, 'DB'));
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $values) . ')';

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Account created successfully.', 'data' => ['account_id' => $accountId]];
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

            if (array_key_exists('parent_account_id', $fields)) {
                $parentId = trim((string) $fields['parent_account_id']);
                if ($parentId === $id) {
                    return ['success' => false, 'message' => 'An account cannot be its own parent.'];
                }
                if ($parentId !== '') {
                    $parentExists = (int) App\ExecuteScalar(
                        "SELECT COUNT(*) FROM {$this->table} WHERE account_id = " . App\QuotedValue($parentId, App\DataType::STRING, 'DB'),
                        'DB'
                    );
                    if ($parentExists === 0) {
                        return ['success' => false, 'message' => "Parent account '{$parentId}' does not exist."];
                    }
                }
            }

            foreach (['parent_account_id', 'source_document'] as $k) {
                if (array_key_exists($k, $fields) && trim((string) $fields[$k]) === '') {
                    $fields[$k] = null;
                }
            }
            if (array_key_exists('posting_allowed', $fields)) {
                $fields['posting_allowed'] = App\ConvertToBool($fields['posting_allowed']) ? 'true' : 'false';
            }

            $setParts = [];
            foreach ($fields as $key => $value) {
                $setParts[] = App\QuotedName($key, 'DB') . ' = ' . ($value === null
                    ? 'NULL'
                    : ($key === 'posting_allowed' ? $value : App\QuotedValue($value, App\DataType::STRING, 'DB')));
            }

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE account_id = ' . App\QuotedValue($id, App\DataType::STRING, 'DB');

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

            $newStatus = App\SameText($check['data']['status'], 'Active') ? 'Inactive' : 'Active';

            App\Execute(
                "UPDATE {$this->table} SET status = " . App\QuotedValue($newStatus, App\DataType::STRING, 'DB') .
                ' WHERE account_id = ' . App\QuotedValue($id, App\DataType::STRING, 'DB'),
                'DB'
            );

            return ['success' => true, 'message' => "Account status set to {$newStatus}.", 'data' => ['status' => $newStatus]];
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

            $accountCode = $check['data']['account_code'];

            $childCount = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE parent_account_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB'),
                'DB'
            );
            if ($childCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete: this account has child accounts under it.'];
            }

            $inUse = (int) App\ExecuteScalar(
                'SELECT COUNT(*) FROM fims.purchase_order_items WHERE account_code = ' . App\QuotedValue($accountCode, App\DataType::STRING, 'DB'),
                'DB'
            );
            if ($inUse > 0) {
                return ['success' => false, 'message' => 'Cannot delete: this account is referenced by existing purchase order items. Set it to Inactive instead.'];
            }

            $sql = "DELETE FROM {$this->table} WHERE account_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Account '{$id}' deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function generateAccountId(): string
    {
        $next = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(account_id FROM 4) AS INTEGER)), 0) + 1
             FROM {$this->table} WHERE account_id ~ '^ACC[0-9]+$'",
            'DB'
        );

        return 'ACC' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
