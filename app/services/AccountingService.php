<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Accounting helpers - Chart of Accounts search and sub-ledger management
 */
class AccountingService
{
    private string $coaTable = 'fims.chart_of_accounts';

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    /** Search Chart of Accounts by code or title */
    public function searchCoa(string $query, int $limit = 20): array
    {
        try {
            $q = trim((string) $query);
            if (strlen($q) < 1) {
                return ['success' => false, 'message' => 'Search query required.'];
            }

            $limit = max(1, min($limit, 100));
            $search = App\QuotedValue('%' . $q . '%', App\DataType::STRING, 'DB');

            $data = App\ExecuteRows(
                "SELECT account_id, account_code, account_title
                 FROM {$this->coaTable}
                 WHERE (account_code ILIKE {$search} OR account_title ILIKE {$search})
                 ORDER BY account_code ASC
                 LIMIT {$limit}",
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            App\LogError('AccountingService::searchCoa ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to search accounts.'];
        }
    }

    /** Get child accounts (sub-ledgers) for a parent account */
    public function getChildAccounts(string $parentAccountId, int $limit = 50): array
    {
        try {
            $parentId = trim((string) $parentAccountId);
            if (strlen($parentId) < 1) {
                return ['success' => false, 'message' => 'Parent account ID required.'];
            }

            $limit = max(1, min($limit, 200));
            $parentIdQuoted = $this->str($parentId);

            $data = App\ExecuteRows(
                "SELECT account_id, account_code, account_title
                 FROM {$this->coaTable}
                 WHERE TRIM(parent_account_id) = TRIM({$parentIdQuoted})
                 ORDER BY account_code ASC
                 LIMIT {$limit}",
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            App\LogError('AccountingService::getChildAccounts ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to load child accounts.'];
        }
    }

    /** Search child accounts by code or title */
    public function searchChildAccounts(string $parentAccountId, string $query, int $limit = 50): array
    {
        try {
            $parentId = trim((string) $parentAccountId);
            $q = trim((string) $query);

            if (strlen($parentId) < 1) {
                return ['success' => false, 'message' => 'Parent account ID required.'];
            }
            if (strlen($q) < 1) {
                return ['success' => false, 'message' => 'Search query required.'];
            }

            $limit = max(1, min($limit, 200));
            $parentIdQuoted = $this->str($parentId);
            $search = App\QuotedValue('%' . $q . '%', App\DataType::STRING, 'DB');

            $data = App\ExecuteRows(
                "SELECT account_id, account_code, account_title
                 FROM {$this->coaTable}
                 WHERE TRIM(parent_account_id) = TRIM({$parentIdQuoted})
                 AND (account_code ILIKE {$search} OR account_title ILIKE {$search})
                 ORDER BY account_code ASC
                 LIMIT {$limit}",
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            App\LogError('AccountingService::searchChildAccounts ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to search child accounts.'];
        }
    }

    /** Create a new child account (sub-ledger) directly under a parent GL account. */
    public function createChildAccount(string $parentAccountId, string $title): array
    {
        try {
            $parentId = trim((string) $parentAccountId);
            $title    = trim((string) $title);

            if ($parentId === '') {
                return ['success' => false, 'message' => 'Parent GL account is required.'];
            }
            if ($title === '') {
                return ['success' => false, 'message' => 'Sub-ledger title is required.'];
            }

            $parentIdQuoted = $this->str($parentId);
            $parent = App\ExecuteRow(
                "SELECT account_id, account_code, account_type FROM {$this->coaTable} WHERE TRIM(account_id) = TRIM({$parentIdQuoted})",
                'DB'
            );
            if (!$parent) {
                return ['success' => false, 'message' => "Parent account '{$parentId}' does not exist."];
            }

            // Next numeric account_id (existing IDs are plain incrementing integers stored as text).
            $maxId = (int) App\ExecuteScalar(
                "SELECT COALESCE(MAX(CAST(account_id AS INTEGER)), 0) FROM {$this->coaTable} WHERE account_id ~ '^[0-9]+$'",
                'DB'
            );
            $newId = (string) ($maxId + 1);

            // Next sequence suffix among this parent's existing children.
            $childCount = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->coaTable} WHERE TRIM(parent_account_id) = TRIM({$parentIdQuoted})",
                'DB'
            );
            $suffix  = str_pad((string) ($childCount + 1), 2, '0', STR_PAD_LEFT);
            $newCode = trim((string) $parent['account_code']) . '-' . $suffix;

            $row = App\ExecuteRow(
                "INSERT INTO {$this->coaTable} (account_id, account_code, account_title, account_type, parent_account_id, posting_allowed, status)
                 VALUES (" . $this->str($newId) . ', ' . $this->str($newCode) . ', ' . $this->str($title) . ', ' .
                 $this->str((string) ($parent['account_type'] ?? '')) . ', ' . $parentIdQuoted . ", TRUE, 'Active')
                 RETURNING account_id, account_code, account_title",
                'DB'
            );

            return ['success' => true, 'message' => 'Sub-ledger account created successfully.', 'data' => $row];
        } catch (\Exception $e) {
            App\LogError('AccountingService::createChildAccount ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create sub-ledger account.'];
        }
    }
}
