<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * CRUD for fims.caf (Certificate as to Availability of Funds), one row per CAF issued
 * against a budget allocation. Soft-deleted via deleted_at, same as BudgetCategoryService.
 * Same method set and response shape as the CRUDServices reference.
 *
 * Writable fields: caf_number, document_type, allocation_id, fiscal_year, line_transaction (jsonb).
 * user_id is the creator — it comes from the verified JWT (see create()), never from the body.
 * Creating a CAF is restricted (create()): FMD users only, only for an Approved RFA, and only while that RFA has no CAF — a
 * superadmin is exempt.
 */
class CafService
{
    private string $table = 'fims.caf';

    private const CAF_NUMBER_MAX = 100;     // the column is VARCHAR(100)
    private const DOCUMENT_TYPE_MAX = 255;  // the column is VARCHAR(255)

    private const STRING_FIELDS = [
        'caf_number' => self::CAF_NUMBER_MAX,
        'document_type' => self::DOCUMENT_TYPE_MAX,
    ];
    private const INT_FIELDS = ['allocation_id', 'fiscal_year'];

    // -----------------------------------------------------------------------
    // Fetch all — no pagination (for /caf/all, e.g. dropdowns)
    // Fetch with pagination (for /caf/list) when $page is provided
    // $filters (all optional): search (caf_number), fiscal_year, allocation_id, document_type
    // -----------------------------------------------------------------------
    public function fetchAlls(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $where = $this->listWhere($filters);

            if ($page === null) {
                $data = App\ExecuteRows("SELECT * FROM {$this->table} WHERE {$where} ORDER BY id ASC", 'DB');

                return ['success' => true, 'data' => array_map([$this, 'decodeRow'], $data ?: [])];
            }

            $limit = min(200, max(1, $limit));
            $page = max(1, $page);
            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", 'DB');
            $data = App\ExecuteRows(
                "SELECT * FROM {$this->table}
                 WHERE {$where}
                 ORDER BY id ASC
                 LIMIT {$limit} OFFSET {$offset}",
                'DB'
            );

            return [
                'success' => true,
                'data' => array_map([$this, 'decodeRow'], $data ?: []),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('CafService::fetchAlls '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load CAFs.'];
        }
    }

    // -----------------------------------------------------------------------
    // Fetch one by ID — /caf/read/{id}
    // -----------------------------------------------------------------------
    public function fetchById(int|string $id): array
    {
        try {
            $id = (int) $id; // safe cast — no QuotedValue needed for integers
            $data = App\ExecuteRow("SELECT * FROM {$this->table} WHERE id = {$id} AND deleted_at IS NULL", 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "CAF #{$id} not found."];
            }

            return ['success' => true, 'data' => $this->decodeRow($data)];
        } catch (\Exception $e) {
            App\LogError('CafService::fetchById '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to load CAF.'];
        }
    }

    // -----------------------------------------------------------------------
    // Create — /caf/add   ($userId = the creator, from the verified JWT)
    //
    // WHO and FOR WHAT (createRefusal()): only a user of the FMD may create a CAF, only for an RFA whose status is Approved,
    // and only while that RFA has no CAF yet. A superadmin is exempt from all three. The CAF form and the "Create CAF" action
    // only mirror this — the refusal here is what enforces it (a 403, or a 409 for an RFA that already has a CAF).
    // -----------------------------------------------------------------------
    public function create(array $body, ?int $userId = null): array
    {
        try {
            if ($refusal = $this->createRefusal($body, $userId)) {
                return ['success' => false, 'message' => $refusal[1], 'httpCode' => $refusal[0]];
            }

            [$clean, $err] = $this->normalize($body);
            if ($err) {
                return ['success' => false, 'message' => $err];
            }
            if (!$clean) {
                return ['success' => false, 'message' => 'No valid fields provided.'];
            }
            if ($err = $this->validateReferences($clean)) {
                return ['success' => false, 'message' => $err];
            }

            $columns = [];
            $values = [];
            foreach ($clean as $field => $value) {
                $columns[] = App\QuotedName($field, 'DB');
                $values[] = $this->literal($field, $value);
            }
            if ($userId !== null && $userId !== 0) {
                $columns[] = App\QuotedName('user_id', 'DB');
                $values[] = (string) $userId;
            }

            $row = App\ExecuteRow(
                "INSERT INTO {$this->table} (".implode(', ', $columns).') VALUES ('.implode(', ', $values).') RETURNING id, caf_number',
                'DB'
            );

            return [
                'success' => true,
                'message' => 'CAF created successfully.',
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'caf_number' => $row['caf_number'] ?? null,
            ];
        } catch (\Exception $e) {
            App\LogError('CafService::create '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to create CAF.'];
        }
    }

    // -----------------------------------------------------------------------
    // Update — /caf/update/{id}   (PATCH: only the fields sent change)
    // -----------------------------------------------------------------------
    public function update(int|string $id, array $body): array
    {
        try {
            $id = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            [$clean, $err] = $this->normalize($body);
            if ($err) {
                return ['success' => false, 'message' => $err];
            }
            if (!$clean) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }
            if ($err = $this->validateReferences($clean)) {
                return ['success' => false, 'message' => $err];
            }

            $set = [];
            foreach ($clean as $field => $value) {
                $set[] = App\QuotedName($field, 'DB').' = '.$this->literal($field, $value);
            }
            $set[] = 'updated_at = CURRENT_TIMESTAMP';

            App\Execute("UPDATE {$this->table} SET ".implode(', ', $set)." WHERE id = {$id} AND deleted_at IS NULL", 'DB');

            return ['success' => true, 'message' => 'CAF updated successfully.'];
        } catch (\Exception $e) {
            App\LogError('CafService::update '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to update CAF.'];
        }
    }

    // -----------------------------------------------------------------------
    // Delete (soft) — /caf/delete/{id}
    // -----------------------------------------------------------------------
    public function delete(int|string $id): array
    {
        try {
            $id = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            App\Execute(
                "UPDATE {$this->table} SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = {$id}",
                'DB'
            );

            return ['success' => true, 'message' => "CAF #{$id} deleted successfully."];
        } catch (\Exception $e) {
            App\LogError('CafService::delete '.$e->getMessage());

            return ['success' => false, 'message' => 'Failed to delete CAF.'];
        }
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * Why this user may not create this CAF, as [HTTP status, message], or null when they may. A superadmin
     * (GlobalService::isSuperAdminUser) is never refused. Everybody else must be of the FMD (GlobalService::isFmdUser — fails
     * closed, so a user we can't place is refused) and the CAF must be for an existing, not deleted RFA — an allocation of type
     * RFA — whose status is Approved and that has no CAF yet (a deleted CAF does not count).
     *
     * @return array{0: int, 1: string}|null
     */
    private function createRefusal(array $body, ?int $userId): ?array
    {
        if (GlobalService::isSuperAdminUser($userId)) {
            return null;
        }
        if (!GlobalService::isFmdUser($userId)) {
            return [403, 'Only the Financial Management Department (FMD) can create a CAF.'];
        }

        $raw = $body['allocation_id'] ?? null;
        $id  = is_scalar($raw) ? filter_var(trim((string) $raw), FILTER_VALIDATE_INT) : false;
        if ($id === false || $id < 1) {
            return [403, 'A CAF can only be created for an Approved RFA — no RFA was given.'];
        }

        $rfa = App\ExecuteRow(
            'SELECT a.allocation_type, st.name AS status_name FROM fims.budget_allocation a
             LEFT JOIN fims.settings_status st ON st.id = a.status_id
             WHERE a.allocation_id = '.$id.' AND a.deleted_at IS NULL',
            'DB'
        );
        if (!$rfa) {
            return [403, "Budget allocation #{$id} does not exist."];
        }
        if (strtoupper(trim((string) ($rfa['allocation_type'] ?? ''))) !== 'RFA') {
            return [403, 'Only an RFA can have a CAF — this request is not an RFA.'];
        }
        $status = trim((string) ($rfa['status_name'] ?? ''));
        if (strtolower($status) !== 'approved') {
            return [403, 'A CAF can only be created for an Approved RFA — this RFA is '.($status !== '' ? $status : 'without a status').'.'];
        }
        // one CAF per RFA: it already has one
        $existing = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE allocation_id = {$id} AND deleted_at IS NULL", 'DB');
        if ($existing > 0) {
            return [409, 'This RFA already has a CAF — an RFA can only have one.'];
        }

        return null;
    }

    /** WHERE clause for the list: active rows + the optional filters. Every value is quoted or cast. */
    private function listWhere(array $filters): string
    {
        $where = 'deleted_at IS NULL';

        $search = is_scalar($filters['search'] ?? null) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $where .= ' AND caf_number ILIKE '.App\QuotedValue('%'.$search.'%', DataType::STRING, 'DB');
        }

        $documentType = is_scalar($filters['document_type'] ?? null) ? trim((string) $filters['document_type']) : '';
        if ($documentType !== '') {
            $where .= ' AND LOWER(document_type) = LOWER('.App\QuotedValue($documentType, DataType::STRING, 'DB').')';
        }

        foreach (self::INT_FIELDS as $field) {
            $v = $filters[$field] ?? null;
            if (is_scalar($v) && $v !== '' && ($int = filter_var($v, FILTER_VALIDATE_INT)) !== false) {
                $where .= " AND {$field} = {$int}";
            }
        }

        return $where;
    }

    /**
     * Whitelist + validate the body. Only the fields present in $body are returned, so the same
     * method serves create and PATCH. null / '' clears a field.
     *
     * @return array{0: array<string, mixed>, 1: ?string} [clean fields, error message or null]
     */
    private function normalize(array $body): array
    {
        $clean = [];

        foreach (self::STRING_FIELDS as $field => $max) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $raw = $body[$field];
            if ($raw !== null && !is_scalar($raw)) {
                return [[], "{$field} must be text."];
            }
            $v = trim((string) ($raw ?? ''));
            if (mb_strlen($v) > $max) {
                return [[], "{$field} must be {$max} characters or fewer."];
            }
            $clean[$field] = $v === '' ? null : $v;
        }

        foreach (self::INT_FIELDS as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $raw = $body[$field];
            if ($raw !== null && !is_scalar($raw)) {
                return [[], "{$field} must be a whole number."];
            }
            $v = trim((string) ($raw ?? ''));
            if ($v === '') {
                $clean[$field] = null;
            } elseif (($int = filter_var($v, FILTER_VALIDATE_INT)) === false || $int < 1) {
                return [[], "{$field} must be a positive whole number."];
            } else {
                $clean[$field] = $int;
            }
        }

        if (array_key_exists('line_transaction', $body)) {
            $lt = $body['line_transaction'];
            if ($lt === null || $lt === '') {
                $clean['line_transaction'] = null;
            } else {
                $decoded = $lt;
                if (is_string($lt)) {
                    $decoded = json_decode($lt, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return [[], 'line_transaction is not valid JSON.'];
                    }
                }
                if (!is_array($decoded)) {
                    return [[], 'line_transaction must be a JSON array or object.'];
                }
                $clean['line_transaction'] = $decoded;
            }
        }

        return [$clean, null];
    }

    /** SQL literal for one normalized value. Everything user-supplied goes through QuotedValue or a strict cast. */
    private function literal(string $field, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (isset(self::STRING_FIELDS[$field])) {
            return App\QuotedValue((string) $value, DataType::STRING, 'DB');
        }
        if (in_array($field, self::INT_FIELDS, true)) {
            return (string) (int) $value;
        }
        if ($field === 'line_transaction') {
            return App\QuotedValue(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), DataType::STRING, 'DB').'::jsonb';
        }

        throw new \LogicException("No SQL literal rule for field '{$field}'.");
    }

    /** Friendly errors for bad references (the FKs would reject them too, but with an opaque DB message). */
    private function validateReferences(array $clean): ?string
    {
        if (($clean['allocation_id'] ?? null) !== null) {
            $n = (int) App\ExecuteScalar(
                'SELECT COUNT(*) FROM fims.budget_allocation WHERE allocation_id = '.(int) $clean['allocation_id'].' AND deleted_at IS NULL',
                'DB'
            );
            if ($n === 0) {
                return "Budget allocation #{$clean['allocation_id']} does not exist.";
            }
        }

        if (($clean['fiscal_year'] ?? null) !== null) {
            $n = (int) App\ExecuteScalar(
                'SELECT COUNT(*) FROM fims.fiscal_year WHERE fiscal_yr = '.(int) $clean['fiscal_year'],
                'DB'
            );
            if ($n === 0) {
                return "Fiscal year {$clean['fiscal_year']} does not exist.";
            }
        }

        return null;
    }

    /** jsonb comes back as a string — hand the client the decoded structure. */
    private function decodeRow(array $row): array
    {
        if (isset($row['line_transaction']) && is_string($row['line_transaction'])) {
            $row['line_transaction'] = json_decode($row['line_transaction'], true);
        }

        return $row;
    }
}
