<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType as DT;

class ChartOfAccountsService
{
    private string $table = 'fims.chart_of_accounts';

    private array $allowedFields = [
        'account_id', 'account_code', 'account_title', 'account_type', 'parent_account_id',
        'posting_allowed', 'status', 'source_document', 'region_code', 'project_code',
        'account_purpose', 'books', 'for_budget_proposal',
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
            $where = ["status = 'Active'"];

            if ($search !== '') {
                $s = App\QuotedValue('%' . $search . '%', DT::STRING, 'DB');
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
            $p = App\QuotedValue($prefix, DT::STRING, 'DB');
            $pDash = App\QuotedValue($prefix . '-%', DT::STRING, 'DB');

            $sql = "SELECT * FROM {$this->table}
                    WHERE (account_code = {$p} OR account_code LIKE {$pDash})
                      AND posting_allowed = TRUE
                      AND status = 'Active'
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
            $where = "status = 'Active' AND posting_allowed = TRUE";
            if ($forBudgetProposalOnly) {
                $where .= ' AND for_budget_proposal = TRUE';
            }
            if ($search !== '') {
                $s = App\QuotedValue('%' . $search . '%', DT::STRING, 'DB');
                $where .= " AND (account_code ILIKE {$s} OR account_title ILIKE {$s})";
            }

            $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY account_code ASC";
            $data = App\ExecuteRows($sql, 'DB');

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // True Subsidiary Ledger accounts — a child account (parent_account_id
    // IS NOT NULL) that is itself a leaf (nothing else has it as a parent).
    // Same definition DisbursementVoucherService::fetchSlCodes() uses for
    // the Accounting Entries grid's SL picker, except this returns full
    // rows (account_id included) since callers like fims.accountable_
    // officers.sl_code / fims.vendors.sl_code store account_id, not the
    // account_code string fetchSlCodes() returns.
    public function fetchSlAccounts(string $search = ''): array
    {
        try {
            $where = ["status = 'Active'", 'parent_account_id IS NOT NULL',
                "NOT EXISTS (SELECT 1 FROM {$this->table} k WHERE k.parent_account_id = {$this->table}.account_id)"];
            if ($search !== '') {
                $s = App\QuotedValue('%' . $search . '%', DT::STRING, 'DB');
                $where[] = "(account_code ILIKE {$s} OR account_title ILIKE {$s})";
            }

            $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . ' ORDER BY account_code ASC';
            $data = App\ExecuteRows($sql, 'DB');

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchById(string $id): array
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE account_id = " . App\QuotedValue($id, DT::STRING, 'DB');
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
                "SELECT COUNT(*) FROM {$this->table} WHERE account_code = " . App\QuotedValue($code, DT::STRING, 'DB'),
                'DB'
            );
            if ($exists > 0) {
                return ['success' => false, 'message' => "Account code '{$code}' already exists."];
            }

            $parentId = trim((string) ($body['parent_account_id'] ?? ''));
            if ($parentId !== '') {
                $parentExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->table} WHERE account_id = " . App\QuotedValue($parentId, DT::STRING, 'DB'),
                    'DB'
                );
                if ($parentExists === 0) {
                    return ['success' => false, 'message' => "Parent account '{$parentId}' does not exist."];
                }
            }

            $fields = array_intersect_key($body, array_flip($this->allowedFields));

            // Generate account_id (sequential) if not provided
            if (!array_key_exists('account_id', $fields) || trim((string) ($fields['account_id'] ?? '')) === '') {
                $maxId = (int) App\ExecuteScalar(
                    "SELECT COALESCE(MAX(CAST(account_id AS INTEGER)), 0) FROM {$this->table} WHERE account_id ~ '^[0-9]+$'",
                    'DB'
                );
                $fields['account_id'] = (string) ($maxId + 1);
            }

            foreach (['parent_account_id', 'region_code', 'project_code', 'source_document', 'account_purpose', 'books'] as $k) {
                if (array_key_exists($k, $fields) && trim((string) $fields[$k]) === '') {
                    $fields[$k] = null;
                }
            }

            if (array_key_exists('posting_allowed', $fields)) {
                $fields['posting_allowed'] = App\ConvertToBool($fields['posting_allowed']) ? 'true' : 'false';
            }

            $columns = [];
            $values = [];
            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');
                $values[] = $value === null ? 'NULL' : ($key === 'posting_allowed' ? $value : App\QuotedValue($value, DT::STRING, 'DB'));
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
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

            if (array_key_exists('parent_account_id', $fields)) {
                $parentId = trim((string) $fields['parent_account_id']);
                if ($parentId === $id) {
                    return ['success' => false, 'message' => 'An account cannot be its own parent.'];
                }
                if ($parentId !== '') {
                    $parentExists = (int) App\ExecuteScalar(
                        "SELECT COUNT(*) FROM {$this->table} WHERE account_id = " . App\QuotedValue($parentId, DT::STRING, 'DB'),
                        'DB'
                    );
                    if ($parentExists === 0) {
                        return ['success' => false, 'message' => "Parent account '{$parentId}' does not exist."];
                    }
                }
            }

            foreach (['parent_account_id', 'region_code', 'project_code', 'source_document', 'account_purpose', 'books'] as $k) {
                if (array_key_exists($k, $fields) && trim((string) $fields[$k]) === '') {
                    $fields[$k] = null;
                }
            }
            if (array_key_exists('posting_allowed', $fields)) {
                $fields['posting_allowed'] = App\ConvertToBool($fields['posting_allowed']) ? 'true' : 'false';
            }

            $setParts = [];
            foreach ($fields as $key => $value) {
                $setParts[] = App\QuotedName($key, 'DB') . ' = ' . ($value === null ? 'NULL' : ($key === 'posting_allowed' ? $value : App\QuotedValue($value, DT::STRING, 'DB')));
            }

            $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . ' WHERE account_id = ' . App\QuotedValue($id, DT::STRING, 'DB');
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

            $newStatus = App\SameText((string) ($check['data']['status'] ?? ''), 'Active') ? 'Inactive' : 'Active';

            App\Execute(
                "UPDATE {$this->table} SET status = " . App\QuotedValue($newStatus, DT::STRING, 'DB') . " WHERE account_id = " . App\QuotedValue($id, DT::STRING, 'DB'),
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

            $childCount = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE parent_account_id = " . App\QuotedValue($id, DT::STRING, 'DB'),
                'DB'
            );
            if ($childCount > 0) {
                return ['success' => false, 'message' => 'Cannot deactivate: this account has child accounts under it.'];
            }

            App\Execute(
                "UPDATE {$this->table} SET status = 'Inactive' WHERE account_id = " . App\QuotedValue($id, DT::STRING, 'DB'),
                'DB'
            );

            return ['success' => true, 'message' => "Account deactivated successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchRegions(): array
    {
        try {
            $data = App\ExecuteRows(
                "SELECT rc_code AS region_code, rc_name AS region_name FROM fims.responsibility_code WHERE status = 'Active' ORDER BY rc_name ASC",
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchProjects(): array
    {
        try {
            $data = App\ExecuteRows(
                "SELECT project_code, project_name FROM fims.project ORDER BY project_code ASC",
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
