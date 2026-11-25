<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Payroll Setup (page app/pages/payroll-setup-page.php, Accounts Set-up → "Payroll Setup").
 * Everything that page needs lives here, so it talks to a single API (app/routes/payroll_setup.php):
 *
 *   - payroll items (fims.payroll_items): the setup catalogue of everything a payroll can carry —
 *     earnings, deductions, refunds and employer shares — each pointing at the Chart of Accounts account it posts to;
 *   - the account list for the page's Account picker (read-only view of fims.chart_of_accounts);
 *   - creating NEW sub-ledger accounts (the 5th and/or 6th part of an account code, like "Add Sub-Ledger" on the Chart of
 *     Accounts page) together with the item that uses them — a new 6th part may sit under a new 5th part.
 *
 * The posting side is NOT stored: it follows from the category
 * (EARNING / REFUND / EMPLOYER_SHARE post as a debit, DEDUCTION as a credit).
 * Which agency an item belongs to is not stored either — it is the account's parent in the chart.
 */
class PayrollSetupService
{
    private string $table    = 'fims.payroll_items';
    private string $tableCoa = 'fims.chart_of_accounts';

    public const CATEGORIES = ['EARNING', 'DEDUCTION', 'REFUND', 'EMPLOYER_SHARE'];
    // how an item counts in the year-end tax annualization (fims.payroll_items.tax_treatment — 01 - table/48)
    public const TAX_TREATMENTS = ['TAXABLE', 'NON_TAXABLE', 'DE_MINIMIS_RICE', 'DE_MINIMIS_UNIFORM', 'BENEFIT_90K', 'CONTRIBUTION', 'WITHHOLDING_TAX', 'NONE'];
    public const STATUSES   = ['ACTIVE', 'INACTIVE'];

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function truthy(mixed $v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
    }

    private function normaliseCode(mixed $v): string
    {
        return strtoupper(trim((string) $v));
    }

    /** created_by / updated_by reference uac.users — only stamp a user that really exists there (the JWT may come from another app). */
    private function userIdOrNull(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }
        $n = (int) App\ExecuteScalar("SELECT COUNT(*) FROM uac.users WHERE user_id = {$userId}", 'DB');

        return $n > 0 ? $userId : null;
    }

    /* ── Accounts (for the Account picker) ─────────────────────────────── */

    /**
     * Every ACTIVE chart account (code, title, whether it allows posting) — small rows, no pagination.
     * The page lists the postable ones in its dropdown and uses the rest to explain why a typed code is refused.
     */
    public function fetchAccounts(): array
    {
        try {
            $data = App\ExecuteRows(
                "SELECT account_code, account_title, posting_allowed
                 FROM {$this->tableCoa}
                 WHERE status = 'Active'
                 ORDER BY account_code ASC",
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            App\LogError('PayrollSetupService::fetchAccounts ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the chart of accounts.'];
        }
    }

    /** An item may only post to an existing, active chart account that allows posting. */
    private function validateExistingAccount(array $row, string $code): ?array
    {
        if (!$this->truthy($row['posting_allowed'])) {
            return ['success' => false, 'message' => "Account '{$code}' does not allow posting."];
        }
        if (($row['status'] ?? 'Active') !== 'Active') {
            return ['success' => false, 'message' => "Account '{$code}' is not active."];
        }

        return null;
    }

    private function partCount(string $code): int
    {
        return count(explode('-', $code));
    }

    private function parentCode(string $code): string
    {
        return substr($code, 0, (int) strrpos($code, '-'));
    }

    /**
     * Decides what to do about the account an item will carry. Returns:
     *   ['error' => <failure array>]                              — refuse
     *   ['create' => false]                                       — nothing to create (blank, or an account that is fine as it is)
     *   ['create' => true, 'parent' => row, 'chain' => [...]]     — NEW sub-ledger(s) to add first, parent first:
     *                                                               chain = [['code' => ..., 'title' => ...], ...] (1 or 2 accounts)
     * $newAccounts is the optional [{account_code, account_title}, ...] the page sends for the parts that do not exist yet.
     * A new 6th part under a new 5th part needs both entries; a new 6th part under an existing 5th part needs only its own.
     * The account the item uses ($code, the last part) is titled with the ITEM NAME unless an entry gives it a title; a new
     * 5th part that is only created to make room for a 6th part has no such default and must come with its own title.
     */
    private function planAccount(string $code, array $newAccounts, string $itemName = ''): array
    {
        if ($code === '') {
            return ['create' => false];
        }
        $row = App\ExecuteRow(
            "SELECT account_code, status, posting_allowed FROM {$this->tableCoa} WHERE account_code = " . $this->str($code),
            'DB'
        );
        if ($row) {
            // already there (also when the page raced another user creating it): just use it, if it is usable
            $err = $this->validateExistingAccount($row, $code);

            return $err ? ['error' => $err] : ['create' => false];
        }
        if (!$newAccounts) {
            return ['error' => ['success' => false, 'message' => "Account '{$code}' does not exist in the Chart of Accounts."]];
        }

        // New sub-ledgers: each code is its parent's code + one more part (5th, then 6th), and they hang under a postable account.
        if (!preg_match('/^\d(-\d{2,3}){4,5}$/', $code)) {
            return ['error' => ['success' => false, 'message' => "A new sub-ledger code must have 5 or 6 parts, like 5-01-02-990-37 (got '{$code}')."]];
        }
        $titles = [];
        foreach ($newAccounts as $n) {
            if (is_array($n)) {
                $titles[trim((string) ($n['account_code'] ?? ''))] = trim((string) ($n['account_title'] ?? ''));
            }
        }

        // walk up from the item's account to the nearest account that already exists, collecting the missing ones
        $missing = [];
        $cur     = $code;
        while (true) {
            $missing[]  = $cur;
            $parentCode = $this->parentCode($cur);
            // SELECT * on purpose: the optional attributes (books, account_purpose, region_code, ...) only exist once their alters ran
            $parent = App\ExecuteRow("SELECT * FROM {$this->tableCoa} WHERE account_code = " . $this->str($parentCode), 'DB');
            if ($parent) {
                break;
            }
            if ($this->partCount($parentCode) <= 4) {
                return ['error' => ['success' => false, 'message' => "Parent account '{$parentCode}' does not exist in the Chart of Accounts."]];
            }
            $cur = $parentCode;      // a missing 5th part: it is created too, so it needs its own title
        }
        if (($parent['status'] ?? 'Active') !== 'Active') {
            return ['error' => ['success' => false, 'message' => "Parent account '{$parentCode}' is not active."]];
        }
        if (!$this->truthy($parent['posting_allowed'])) {
            return ['error' => ['success' => false, 'message' => "Parent account '{$parentCode}' does not allow posting, so it cannot have a sub-ledger."]];
        }

        $chain = [];
        foreach (array_reverse($missing) as $c) {
            $title = $titles[$c] ?? '';
            if ($title === '' && $c === $code) {
                $title = $itemName;      // the account the item uses is named after the item
            }
            if ($title === '') {
                return ['error' => ['success' => false, 'message' => "account_title is required for the new sub-ledger {$c}."]];
            }
            if (mb_strlen($title) > 255) {
                return ['error' => ['success' => false, 'message' => 'account_title is too long (max 255 characters).']];
            }
            $chain[] = ['code' => $c, 'title' => $title];
        }

        return ['create' => true, 'parent' => $parent, 'chain' => $chain];
    }

    /** Adds one sub-ledger through the Chart of Accounts' own create(), inheriting the parent's attributes. */
    private function createAccount(string $code, string $title, array $parent, int $userId): array
    {
        $coa   = new ChartOfAccountsService();
        $scope = $coa->getUserScope($userId);

        $body = [
            'account_code'      => $code,
            'account_title'     => $title,
            'account_type'      => $parent['account_type'] ?? '',
            'parent_account_id' => $parent['account_id'],
            'posting_allowed'   => true,      // a payroll item posts to it (or to a child of it), so it must allow posting
        ];
        // inherit whatever optional attributes the parent has (and only those — a column that is not there yet cannot be written)
        foreach (['region_code', 'project_code', 'account_purpose', 'source_document', 'books'] as $k) {
            if (array_key_exists($k, $parent)) {
                $body[$k] = $parent[$k] ?? '';
            }
        }
        if (!empty($scope['region_code']) && array_key_exists('region_code', $parent)) {
            $body['region_code'] = $scope['region_code'];   // a regional user's accounts stay in their region
        }

        return $coa->create($body);
    }

    /**
     * Creates the planned chain parent-first (a new 6th part is created under the new 5th part just made).
     * If any step fails, the ones already created are taken back out. Returns ['success' => true, 'created' => [codes]] or the failure.
     */
    private function createAccounts(array $plan, int $userId): array
    {
        $created = [];
        $parent  = $plan['parent'];
        foreach ($plan['chain'] as $step) {
            $res = $this->createAccount($step['code'], $step['title'], $parent, $userId);
            if (!$res['success']) {
                $this->dropCreatedAccounts($created);

                return $res;
            }
            $created[] = $step['code'];
            // the next step hangs under the account just made
            $parent = App\ExecuteRow("SELECT * FROM {$this->tableCoa} WHERE account_code = " . $this->str($step['code']), 'DB') ?: $parent;
        }

        return ['success' => true, 'created' => $created];
    }

    /** A later write failed: take the (brand-new, unreferenced) accounts back out, children before parents. */
    private function dropCreatedAccounts(array $codes): void
    {
        foreach (array_reverse($codes) as $code) {
            try {
                App\Execute(
                    "DELETE FROM {$this->tableCoa} WHERE account_code = " . $this->str($code)
                    . " AND NOT EXISTS (SELECT 1 FROM {$this->table} WHERE account_code = " . $this->str($code) . ')',
                    'DB'
                );
            } catch (\Exception $e) {
                App\LogError("PayrollSetupService: could not remove sub-ledger {$code} after a failed save: " . $e->getMessage());
            }
        }
    }

    /* ── Payroll items ─────────────────────────────────────────────────── */

    /**
     * $page === null returns every match; otherwise paginates and adds the KPI summary.
     * $category: one of CATEGORIES | $status: ACTIVE|INACTIVE | $account: 'with' (has an account) | 'none' (no account yet)
     */
    public function fetchAlls(?int $page = null, int $limit = 10, string $search = '', string $category = '', string $status = '', string $account = ''): array
    {
        try {
            $where = ['1=1'];
            if ($category !== '') {
                $where[] = 'i.category = ' . $this->str(strtoupper($category));
            }
            if ($status !== '') {
                $where[] = 'i.status = ' . $this->str(strtoupper($status));
            }
            if ($account === 'with') {
                $where[] = 'i.account_code IS NOT NULL';
            } elseif ($account === 'none') {
                $where[] = 'i.account_code IS NULL';
            }
            if ($search !== '') {
                $s       = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(i.item_code ILIKE {$s} OR i.item_name ILIKE {$s} OR i.account_code ILIKE {$s} OR c.account_title ILIKE {$s})";
            }
            $whereSql = implode(' AND ', $where);

            $select = "SELECT i.*, c.account_title
                       FROM {$this->table} i
                       LEFT JOIN {$this->tableCoa} c ON c.account_code = i.account_code
                       WHERE {$whereSql}
                       ORDER BY i.sort_order ASC, i.item_code ASC";

            if ($page === null) {
                return ['success' => true, 'data' => App\ExecuteRows($select, 'DB') ?: []];
            }

            $limit  = max(1, $limit);
            $page   = max(1, $page);
            $offset = ($page - 1) * $limit;
            $total  = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} i LEFT JOIN {$this->tableCoa} c ON c.account_code = i.account_code WHERE {$whereSql}",
                'DB'
            );
            $data = App\ExecuteRows("{$select} LIMIT {$limit} OFFSET {$offset}", 'DB');

            // Counts over every row matching the filters (not just this page) for the KPI cards.
            $summary = App\ExecuteRow(
                "SELECT COUNT(*) FILTER (WHERE i.category = 'EARNING')                    AS earnings,
                        COUNT(*) FILTER (WHERE i.category IN ('DEDUCTION', 'REFUND'))      AS deductions,
                        COUNT(*) FILTER (WHERE i.category = 'EMPLOYER_SHARE')              AS employer_share,
                        COUNT(*) FILTER (WHERE i.account_code IS NULL)                     AS no_account,
                        COUNT(*) FILTER (WHERE i.status = 'ACTIVE')                        AS active,
                        COUNT(*) FILTER (WHERE i.status <> 'ACTIVE')                       AS inactive
                 FROM {$this->table} i LEFT JOIN {$this->tableCoa} c ON c.account_code = i.account_code
                 WHERE {$whereSql}",
                'DB'
            );

            return [
                'success'    => true,
                'data'       => $data ?: [],
                'summary'    => $summary,
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('PayrollSetupService::fetchAlls ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load payroll items.'];
        }
    }

    public function fetchById(int|string $id): array
    {
        try {
            $id   = (int) $id;
            $data = App\ExecuteRow(
                "SELECT i.*, c.account_title
                 FROM {$this->table} i
                 LEFT JOIN {$this->tableCoa} c ON c.account_code = i.account_code
                 WHERE i.payroll_item_id = {$id}",
                'DB'
            );

            if (!$data) {
                return ['success' => false, 'message' => "Payroll item #{$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            App\LogError('PayrollSetupService::fetchById ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load payroll item.'];
        }
    }

    /**
     * $body: item_code, item_name, category (required); status, remarks, account_code (optional).
     * An account_code that is not in the chart yet is created as a sub-ledger when $body['new_accounts'] =
     * [{account_code, account_title}, ...] is sent (parent first: a new 6th part under a new 5th part lists both); if the
     * item then fails to save, the sub-ledgers just created are taken back out so nothing is left behind.
     */
    public function create(array $body, int $userId = 0): array
    {
        try {
            $code     = $this->normaliseCode($body['item_code'] ?? '');
            $name     = trim((string) ($body['item_name'] ?? ''));
            $category = strtoupper(trim((string) ($body['category'] ?? '')));
            $status   = strtoupper(trim((string) ($body['status'] ?? ''))) ?: 'ACTIVE';
            $account  = trim((string) ($body['account_code'] ?? ''));
            $remarks  = trim((string) ($body['remarks'] ?? ''));
            $tax      = strtoupper(trim((string) ($body['tax_treatment'] ?? ''))) ?: 'NONE';
            $new      = is_array($body['new_accounts'] ?? null) ? $body['new_accounts'] : [];
            if (!in_array($tax, self::TAX_TREATMENTS, true)) {
                return ['success' => false, 'message' => 'tax_treatment must be one of: ' . implode(', ', self::TAX_TREATMENTS) . '.'];
            }

            if ($code === '' || $name === '') {
                return ['success' => false, 'message' => 'item_code and item_name are required.'];
            }
            if (!preg_match('/^[A-Z0-9][A-Z0-9_\/\'.&\- ]{0,59}$/', $code)) {
                return ['success' => false, 'message' => 'item_code may only contain letters, digits and _ / \' . & - (max 60 characters).'];
            }
            if (mb_strlen($name) > 150) {
                return ['success' => false, 'message' => 'item_name is too long (max 150 characters).'];
            }
            if (!in_array($category, self::CATEGORIES, true)) {
                return ['success' => false, 'message' => 'category must be one of: ' . implode(', ', self::CATEGORIES) . '.'];
            }
            if (!in_array($status, self::STATUSES, true)) {
                return ['success' => false, 'message' => 'status must be ACTIVE or INACTIVE.'];
            }
            if ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE item_code = " . $this->str($code), 'DB') > 0) {
                return ['success' => false, 'message' => "Item code '{$code}' already exists."];
            }
            $plan = $this->planAccount($account, $new, $name);
            if (isset($plan['error'])) {
                return $plan['error'];
            }

            // all checks passed — only now write
            $createdAccounts = [];
            if ($plan['create']) {
                $made = $this->createAccounts($plan, $userId);
                if (!$made['success']) {
                    return $made;
                }
                $createdAccounts = $made['created'];
            }

            $by      = $this->userIdOrNull($userId);
            $columns = ['item_code', 'item_name', 'category', 'tax_treatment', 'status'];
            $values  = [$this->str($code), $this->str($name), $this->str($category), $this->str($tax), $this->str($status)];
            if ($account !== '') {
                $columns[] = 'account_code';
                $values[]  = $this->str($account);
            }
            if ($remarks !== '') {
                $columns[] = 'remarks';
                $values[]  = $this->str($remarks);
            }
            if ($by !== null) {
                array_push($columns, 'created_by', 'updated_by');
                array_push($values, (string) $by, (string) $by);
            }

            try {
                $row = App\ExecuteRow(
                    "INSERT INTO {$this->table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ') RETURNING payroll_item_id',
                    'DB'
                );
            } catch (\Exception $e) {
                $this->dropCreatedAccounts($createdAccounts);
                throw $e;
            }

            return [
                'success' => true,
                'message' => 'Payroll item created successfully.',
                'data'    => ['payroll_item_id' => (int) $row['payroll_item_id'], 'created_accounts' => $createdAccounts],
            ];
        } catch (\Exception $e) {
            App\LogError('PayrollSetupService::create ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to create payroll item.'];
        }
    }

    /** Same optional $body['new_accounts'] as create(). item_code is immutable. */
    public function update(int|string $id, array $body, int $userId = 0): array
    {
        try {
            $id    = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }
            $current = $check['data'];

            // The code is the key the payroll import matches against, so it never changes.
            if (array_key_exists('item_code', $body) && $this->normaliseCode($body['item_code']) !== $current['item_code']) {
                return ['success' => false, 'message' => 'item_code cannot be changed.'];
            }

            $set  = [];
            $plan = ['create' => false];
            $account = null;
            if (array_key_exists('item_name', $body)) {
                $name = trim((string) $body['item_name']);
                if ($name === '') {
                    return ['success' => false, 'message' => 'item_name cannot be empty.'];
                }
                if (mb_strlen($name) > 150) {
                    return ['success' => false, 'message' => 'item_name is too long (max 150 characters).'];
                }
                $set[] = 'item_name = ' . $this->str($name);
            }
            if (array_key_exists('category', $body)) {
                $category = strtoupper(trim((string) $body['category']));
                if (!in_array($category, self::CATEGORIES, true)) {
                    return ['success' => false, 'message' => 'category must be one of: ' . implode(', ', self::CATEGORIES) . '.'];
                }
                if ($category !== $current['category']) {
                    // gross / net pay of released payrolls are summed by category — don't silently move used items
                    $used = (int) App\ExecuteScalar("SELECT COUNT(*) FROM fims.payroll_record_items WHERE payroll_item_id = {$id}", 'DB');
                    if ($used > 0) {
                        return ['success' => false, 'message' => 'This item is already used in payroll records, so its category cannot change. Set it to INACTIVE and add a new item instead.'];
                    }
                }
                $set[] = 'category = ' . $this->str($category);
            }
            if (array_key_exists('tax_treatment', $body)) {   // only the annualization reads it — free to change any time
                $tax = strtoupper(trim((string) $body['tax_treatment'])) ?: 'NONE';
                if (!in_array($tax, self::TAX_TREATMENTS, true)) {
                    return ['success' => false, 'message' => 'tax_treatment must be one of: ' . implode(', ', self::TAX_TREATMENTS) . '.'];
                }
                $set[] = 'tax_treatment = ' . $this->str($tax);
            }
            if (array_key_exists('status', $body)) {
                $status = strtoupper(trim((string) $body['status']));
                if (!in_array($status, self::STATUSES, true)) {
                    return ['success' => false, 'message' => 'status must be ACTIVE or INACTIVE.'];
                }
                $set[] = 'status = ' . $this->str($status);
            }
            if (array_key_exists('account_code', $body)) {
                $account = trim((string) $body['account_code']);
                // keeping the account the item already has is always allowed (it may have been deactivated since)
                if ($account !== (string) ($current['account_code'] ?? '')) {
                    $plan = $this->planAccount($account, is_array($body['new_accounts'] ?? null) ? $body['new_accounts'] : [], trim((string) ($body['item_name'] ?? $current['item_name'])));
                    if (isset($plan['error'])) {
                        return $plan['error'];
                    }
                }
                $set[] = 'account_code = ' . ($account === '' ? 'NULL' : $this->str($account));
            }
            if (array_key_exists('remarks', $body)) {
                $remarks = trim((string) $body['remarks']);
                $set[]   = 'remarks = ' . ($remarks === '' ? 'NULL' : $this->str($remarks));
            }
            if (!$set) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }
            $set[] = 'updated_at = CURRENT_TIMESTAMP';
            if (($by = $this->userIdOrNull($userId)) !== null) {
                $set[] = "updated_by = {$by}";
            }

            // all checks passed — only now write
            $createdAccounts = [];
            if ($plan['create']) {
                $made = $this->createAccounts($plan, $userId);
                if (!$made['success']) {
                    return $made;
                }
                $createdAccounts = $made['created'];
            }
            try {
                App\Execute("UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE payroll_item_id = {$id}", 'DB');
            } catch (\Exception $e) {
                $this->dropCreatedAccounts($createdAccounts);
                throw $e;
            }

            return ['success' => true, 'message' => 'Payroll item updated successfully.', 'data' => ['created_accounts' => $createdAccounts]];
        } catch (\Exception $e) {
            App\LogError('PayrollSetupService::update ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update payroll item.'];
        }
    }
}
