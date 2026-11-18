<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * CRUD for fims.budget_allocation — the Memo Request page (memo / PR / PO
 * tracking per fiscal year, requesting RC and district).
 *
 * Soft-deleted via deleted_at, same as ComponentTypeService. A new memo request
 * starts as the 'Draft' status (fims.settings_status, status_for =
 * 'budget_allocation'). Program / Sub-Program / Project are referenced by code.
 * requesting_district_id points at uac.departments and requesting_rc_id at
 * fims.responsibility_code (rc_id); neither carries a DB foreign key (see
 * "08 - budget_allocation_add_fk.sql"), so they are validated here instead.
 *
 * user_id (the creator) and requesting_rc_id (the creator's Responsibility Center,
 * uac.users.rc_id) are set on create only and are never taken from the body.
 */
class BudgetAllocationService
{
    private string $table = 'fims.budget_allocation';
    private string $tableFiscalYear = 'fims.fiscal_year';
    private string $tableVendor = 'fims.vendors';
    private string $tableStatus = 'fims.settings_status';
    private string $tableProgram = 'fims.program';
    private string $tableSubProgram = 'fims.sub_program';
    private string $tableProject = 'fims.project';
    private string $tableAllocationProject = 'fims.budget_allocation_project';
    private string $tableDepartment = 'uac.departments';
    private string $tableUser = 'uac.users';
    private string $tableRc = 'fims.responsibility_code';

    /** settings_status.status_for value that scopes this table's statuses. */
    private const STATUS_FOR = 'budget_allocation';

    /** Longest "Return" remarks accepted (characters); the Process modal's textarea uses the same limit. */
    public const REMARKS_MAX_LENGTH = 2000;

    /**
     * The statuses of the review step, by NAME (their ids differ per install, so they are looked up — see
     * getStatusIdByName()). A request is "For Review" once its requester submits it; the Financial Management
     * Department then moves it step by step to Approved, or to Returned (process(), REVIEW_STEPS). Seeded by
     * sample_data/06 and 07 and seed_data/04.
     */
    private const STATUS_FOR_REVIEW = 'For Review';
    private const STATUS_FOR_DIVISION_MANAGER = 'For Division Manager';
    private const STATUS_FOR_DEPARTMENT_MANAGER = 'For Department Manager';
    private const STATUS_APPROVED = 'Approved';
    private const STATUS_RETURNED = 'Returned';
    private const STATUS_RETURNED_TO_SECTION_CHIEF = 'Returned to Section Chief';
    private const STATUS_RETURNED_TO_DIVISION_MANAGER = 'Returned to Division Manager';

    /**
     * The review chain inside FMD, one step per status: who acts on a request in that status (the NAME of a
     * uac.user_levels row — its id differs per install), the status an approval ('next') moves it to and the status a
     * Return ('return') moves it to. Only the last approval ('Approved') gives the request its allocation number.
     *
     *   For Review  -> FMD Section Chief -> For Division Manager -> FMD Division Manager
     *               -> For Department Manager -> FMD Department Manager -> Approved
     *
     * A Return goes back ONE step, to the user who endorsed it, who then sees it in their own status and can endorse
     * it again or return it further; only the Section Chief's Return reaches the requesting office ('Returned'):
     *   Department Manager returns -> 'Returned to Division Manager' (acted on by the Division Manager)
     *   Division Manager returns   -> 'Returned to Section Chief'    (acted on by the Section Chief)
     *   Section Chief returns      -> 'Returned'                     (back to the requesting office)
     */
    private const REVIEW_STEPS = [
        self::STATUS_FOR_REVIEW => [
            'actor' => 'FMD Section Chief',      'next' => self::STATUS_FOR_DIVISION_MANAGER,   'return' => self::STATUS_RETURNED,
        ],
        self::STATUS_FOR_DIVISION_MANAGER => [
            'actor' => 'FMD Division Manager',   'next' => self::STATUS_FOR_DEPARTMENT_MANAGER, 'return' => self::STATUS_RETURNED_TO_SECTION_CHIEF,
        ],
        self::STATUS_FOR_DEPARTMENT_MANAGER => [
            'actor' => 'FMD Department Manager', 'next' => self::STATUS_APPROVED,               'return' => self::STATUS_RETURNED_TO_DIVISION_MANAGER,
        ],
        self::STATUS_RETURNED_TO_SECTION_CHIEF => [
            'actor' => 'FMD Section Chief',      'next' => self::STATUS_FOR_DIVISION_MANAGER,   'return' => self::STATUS_RETURNED,
        ],
        self::STATUS_RETURNED_TO_DIVISION_MANAGER => [
            'actor' => 'FMD Division Manager',   'next' => self::STATUS_FOR_DEPARTMENT_MANAGER, 'return' => self::STATUS_RETURNED_TO_SECTION_CHIEF,
        ],
    ];

    /**
     * The clock an allocation number is stamped with. The number carries the year, quarter and month of the day it
     * is given, and the agency works in Philippine time — the server itself runs on UTC, which would date a number
     * wrongly for the first eight hours of a month.
     */
    private const ALLOCATION_NUMBER_TIMEZONE = 'Asia/Manila';

    /** VARCHAR columns → max length. Program / Sub-Program / Project moved to fims.budget_allocation_project
     *  (one row per project block, see normalizeProjectBlocksShape() / resolveProjectBlocks()) — a record can
     *  now cover several, so they are no longer columns on this table. */
    private const STRING_FIELDS = [
        'allocation_type' => 20,
        'allocation_number' => 100,
        'document_type' => 20,
        'pr_number' => 50,
        'po_number' => 50,
        'workflow_route' => 20,
    ];

    /** VARCHAR columns of ONE project block (fims.budget_allocation_project) → max length. Same widths as the
     *  record-level columns these replaced (see the STRING_FIELDS comment above, pre-multi-project). */
    private const BLOCK_STRING_FIELDS = [
        'program_code' => 64,
        'sub_program_code' => 64,
        'project_code' => 64,
    ];

    private const INT_FIELDS = [
        'fiscal_year', 'requesting_district_id',
        'vendor_id', 'parent_id', 'category_id', 'status_id',
    ];

    /**
     * VARCHAR(50), the creator's fims.responsibility_code.rc_id. Deliberately NOT in STRING_FIELDS:
     * it is never read from the body — create() fills it from the verified user (see requestingRcOf()).
     */
    private const REQUESTING_RC_FIELD = 'requesting_rc_id';

    /**
     * request_referrence_no (VARCHAR(50), "RR-001", "RR-002", ...) is generated BY THE DATABASE: the column's DEFAULT
     * takes the next value of a sequence (alter_table/19). It is deliberately in none of the writable lists above, so
     * a body value is ignored: create() never names the column (the default always applies) and update() can never
     * change or blank it. Note the spelling — it is the column's name. List / read return it; create returns the
     * one just generated.
     */
    private const GENERATED_REFERENCE_FIELD = 'request_referrence_no';

    private const AMOUNT_FIELDS = ['pr_amount', 'po_amount'];

    /**
     * A project block's Program / Sub-Program / Project that does not exist yet can be TYPED instead of picked: the
     * block then carries its name (and no code) and it is created here, together with the memo request — see
     * resolveProjectBlocks(). block key => level. The three name fields are VARCHAR(255).
     */
    private const TYPED_NAME_FIELDS = ['program' => 'program_name', 'sub_program' => 'sub_program_name', 'project' => 'project_name'];
    private const TYPED_NAME_MAX = 255;

    /** Largest amount accepted (NUMERIC(18,2) holds more; this keeps float math exact to the centavo). */
    private const MAX_AMOUNT = 9999999999999.99;

    /** Every column of this table — used by list / all (per-block data, including line_transaction, is attached
     *  separately as `projects` — see fetchProjectBlocksFor()). */
    private const LIST_COLUMNS = 'a.allocation_id, a.request_referrence_no, a.allocation_type, a.allocation_number, a.fiscal_year,
        a.requesting_rc_id, a.requesting_district_id,
        a.vendor_id, a.parent_id, a.document_type, a.description, a.pr_number, a.pr_amount, a.po_number, a.po_amount,
        a.workflow_route, a.category_id, a.is_bidding, a.multiple_node_id, a.status_id, a.user_id,
        a.created_at, a.updated_at';

    // -----------------------------------------------------------------------
    // Fetch all — no pagination (for /budget_allocation/all, e.g. the Parent picker)
    // Fetch with pagination (for /budget_allocation/list) when $page is provided
    //
    // $filters: search, fiscal_year, status_id, status (a status NAME), allocation_type, exclude_id,
    //           rc_id (the requesting office — see below)
    // -----------------------------------------------------------------------
    public function fetchAlls(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $where = ['a.deleted_at IS NULL'];

            $search = trim((string) ($filters['search'] ?? ''));
            if ($search !== '') {
                $s = App\QuotedValue('%'.$search.'%', DataType::STRING, 'DB');
                $where[] = "(a.request_referrence_no ILIKE {$s} OR a.allocation_number ILIKE {$s} OR a.pr_number ILIKE {$s}
                             OR a.po_number ILIKE {$s} OR a.description ILIKE {$s} OR v.vendor_name ILIKE {$s})";
            }
            if (($fy = $this->intFilter($filters, 'fiscal_year')) !== null) {
                $where[] = "a.fiscal_year = {$fy}";
            }
            if (($statusId = $this->intFilter($filters, 'status_id')) !== null) {
                $where[] = "a.status_id = {$statusId}";
            }
            // status: a status NAME (settings_status.name of a memo-request status, e.g. "Approved") for callers that
            // cannot know the id — it differs per install, which is also why Draft is looked up by name. A name that
            // matches no status matches no row: it never falls back to "everything".
            $statusName = trim((string) ($filters['status'] ?? ''));
            if ($statusName !== '') {
                $where[] = "a.status_id IN (SELECT s.id FROM {$this->tableStatus} s WHERE s.status_for = "
                    .App\QuotedValue(self::STATUS_FOR, DataType::STRING, 'DB')
                    .' AND LOWER(s.name) = LOWER('.App\QuotedValue($statusName, DataType::STRING, 'DB').'))';
            }
            if (($excludeId = $this->intFilter($filters, 'exclude_id')) !== null) {
                $where[] = "a.allocation_id <> {$excludeId}";
            }
            // rc_id: only the requests of this office (requesting_rc_id) AND of the offices under it — a parent office also
            // sees its child offices' requests (a Region 3 user sees Region 3's district offices too), exactly as
            // BudgetReqService::fetchAlls does. '' = every office. The routes fill this from the LOGGED-IN user, never
            // from what the client sent (see budget_allocation.php).
            $rcId = trim((string) ($filters['rc_id'] ?? ''));
            if ($rcId !== '') {
                $rcIds = GlobalService::getRcIdsInScope($rcId) ?: [$rcId];
                $where[] = 'a.requesting_rc_id IN ('.implode(', ', array_map(
                    fn ($id) => App\QuotedValue($id, DataType::STRING, 'DB'),
                    $rcIds
                )).')';
            }
            // allocation_type: one type ("CBA") or several, comma-separated ("RFA,LAA" — the RFA / LAA memo request page lists both).
            $types = array_values(array_filter(array_map('trim', explode(',', (string) ($filters['allocation_type'] ?? ''))), 'strlen'));
            if ($types) {
                $where[] = 'UPPER(a.allocation_type) IN ('.implode(', ', array_map(
                    fn ($t) => 'UPPER('.App\QuotedValue($t, DataType::STRING, 'DB').')',
                    $types
                )).')';
            }
            $rcId = trim((string) ($filters['rc_id'] ?? ''));
            if ($rcId !== '') {
                // The user's RC plus every descendant RC (a Region also sees its district offices) — not a flat equality.
                $rcIds = GlobalService::getRcIdsInScope($rcId) ?: [$rcId];
                $quotedRcIds = implode(', ', array_map(
                    fn ($id) => App\QuotedValue($id, DataType::STRING, 'DB'),
                    $rcIds
                ));
                $where[] = "a.requesting_rc_id IN ({$quotedRcIds})";
            }
            $whereSql = implode(' AND ', $where);
            $order = 'ORDER BY a.created_at DESC, a.allocation_id DESC';

            if ($page === null) {
                $data = App\ExecuteRows($this->baseSelect(self::LIST_COLUMNS)." WHERE {$whereSql} {$order}", 'DB') ?: [];
                $this->attachProjects($data);

                return ['success' => true, 'data' => $data];
            }

            $limit = min(200, max(1, $limit));
            $page = max(1, $page);
            $offset = ($page - 1) * $limit;

            // Count + totals cover every row matching the filters (not just this page) for the KPI cards.
            $core = "{$this->table} a LEFT JOIN {$this->tableVendor} v ON v.vendor_id = a.vendor_id";
            $summary = App\ExecuteRow(
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(a.pr_amount), 0) AS total_pr_amount,
                        COALESCE(SUM(a.po_amount), 0) AS total_po_amount,
                        COALESCE(SUM({$this->requestedTotalSql()}), 0) AS total_requested_amount
                 FROM {$core} WHERE {$whereSql}",
                'DB'
            );
            $total = (int) ($summary['total'] ?? 0);

            $data = App\ExecuteRows(
                $this->baseSelect(self::LIST_COLUMNS)." WHERE {$whereSql} {$order} LIMIT {$limit} OFFSET {$offset}",
                'DB'
            ) ?: [];
            $this->attachProjects($data);

            return [
                'success' => true,
                'data' => $data,
                'summary' => $summary,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('BudgetAllocationService::fetchAlls '.$e->getMessage());

            return $this->fail('Failed to load memo requests.');
        }
    }

    // -----------------------------------------------------------------------
    // Fetch one by ID — /budget_allocation/read/{id}
    //
    // $withFiles adds file_attachments[]: the Supporting Documents of its multiple_node_id
    // (fims.file_attachments -> fims.ip_filemeta, see FileUploadServices::attachmentsFor()).
    // Internal callers (update / delete) leave it off.
    // -----------------------------------------------------------------------
    public function fetchById(int|string $id, bool $withFiles = false): array
    {
        try {
            $id = (int) $id; // safe cast — no QuotedValue needed for integers
            $data = App\ExecuteRow($this->baseSelect('a.*')." WHERE a.allocation_id = {$id} AND a.deleted_at IS NULL", 'DB');

            if (!$data) {
                return $this->fail("Memo request #{$id} not found.");
            }
            $rows = [$data];
            $this->attachProjects($rows);
            $data = $rows[0];
            if ($withFiles) {
                $data['file_attachments'] = (new FileUploadServices())->attachmentsFor($data['multiple_node_id'] ?? null);
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            App\LogError('BudgetAllocationService::fetchById '.$e->getMessage());

            return $this->fail('Failed to load memo request.');
        }
    }

    // -----------------------------------------------------------------------
    // Create — /budget_allocation/add
    //
    // Program / Sub-Program / Project: send the code of an existing one (program_code, ...) OR the name of a new one
    // (program_name, ...). A new one is created — found by name first, so typing an existing name never duplicates it —
    // in the SAME transaction as the memo request: if anything fails, nothing at all is saved.
    // -----------------------------------------------------------------------
    public function create(array $body, ?int $userId = null): array
    {
        $inTransaction = false;
        try {
            [$clean, $err] = $this->normalize($body);
            if ($err) {
                return $this->fail($err);
            }
            // parent_id is never client-authoritative — see firstParentIdOf(), called below once this
            // record's blocks (and their own parent_allocation_id) are resolved and validated.
            unset($clean['parent_id']);
            [$rawBlocks, $err] = $this->normalizeProjectBlocksShape($body);
            if ($err) {
                return $this->fail($err);
            }
            if ($rawBlocks === null) {
                return $this->fail('projects is required: at least one project is needed.');
            }
            if (($clean['fiscal_year'] ?? null) === null) {
                return $this->fail('fiscal_year is required.');
            }
            // The requesting office is the creator's own Responsibility Center: uac.users.rc_id, looked up from
            // the verified user — whatever the body says about it is ignored. A user without an RC leaves it NULL.
            if ($userId !== null && $userId !== 0 && ($rcId = $this->requestingRcOf($userId)) !== null) {
                $clean[self::REQUESTING_RC_FIELD] = $rcId;
            }

            // The row and its project block(s) are always one transaction: a failure partway (a bad reference, a
            // typed Program/Project that fails to create) must leave neither behind.
            App\Execute('BEGIN', 'DB');
            $inTransaction = true;

            [$blocks, $created, $err] = $this->resolveProjectBlocks($rawBlocks, $clean['fiscal_year'] ?? null, $userId);
            if ($err) {
                App\Execute('ROLLBACK', 'DB');

                return $this->fail($err);
            }

            if ($err = $this->validateReferences($clean, $blocks)) {
                App\Execute('ROLLBACK', 'DB');

                return $this->fail($err);
            }

            // parent_id stays a maintained LEGACY DISPLAY MIRROR of the first block's own parent_allocation_id
            // (RFA/LAA only) — see migration 53. Nothing that moves money may read it; the authoritative,
            // per-draw parent is each block's own parent_allocation_id (validateReferences() above, process()
            // below). Kept for the list/read join and the Ledger page, which still group by one parent.
            $clean['parent_id'] = $this->firstParentIdOf($blocks, (string) ($clean['allocation_type'] ?? ''));

            if (($clean['status_id'] ?? null) === null && ($draft = $this->getStatusIdByName('Draft')) !== null) {
                $clean['status_id'] = $draft;
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

            // RETURNING also hands back the reference number the database generated for this row (RR-001, ...).
            $row = App\ExecuteRow(
                "INSERT INTO {$this->table} (".implode(', ', $columns).') VALUES ('.implode(', ', $values).') RETURNING allocation_id, '.self::GENERATED_REFERENCE_FIELD,
                'DB'
            );
            $allocationId = isset($row['allocation_id']) ? (int) $row['allocation_id'] : 0;
            if ($allocationId <= 0) {
                App\Execute('ROLLBACK', 'DB');
                $inTransaction = false;

                return $this->fail('Failed to create memo request.');
            }

            $this->insertProjectBlocks($allocationId, $blocks);

            App\Execute('COMMIT', 'DB');
            $inTransaction = false;

            // Usually 'Draft' (the default just above), but a caller-supplied status_id is honored too.
            $initialStatus = App\ExecuteScalar("SELECT name FROM {$this->tableStatus} WHERE id = ".(int) $clean['status_id'], 'DB') ?: 'Draft';
            GlobalService::logStatusChange('budget_allocation', $allocationId, (string) $initialStatus, $userId);

            $result = [
                'success' => true,
                'message' => 'Memo request created successfully.',
                'allocation_id' => $allocationId,
                self::GENERATED_REFERENCE_FIELD => $row[self::GENERATED_REFERENCE_FIELD] ?? null,
            ];
            if ($created) {
                $result['created'] = $created;   // the codes of the Program / Sub-Program / Project this call created, per block
            }

            return $result;
        } catch (\Exception $e) {
            if ($inTransaction) {
                App\Execute('ROLLBACK', 'DB');
            }
            App\LogError('BudgetAllocationService::create '.$e->getMessage());

            return $this->fail('Failed to create memo request.');
        }
    }

    // -----------------------------------------------------------------------
    // Update — /budget_allocation/update/{id}   (PATCH: only the fields sent change)
    // -----------------------------------------------------------------------
    public function update(int|string $id, array $body): array
    {
        $inTransaction = false;
        try {
            $id = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            [$clean, $err] = $this->normalize($body);
            if ($err) {
                return $this->fail($err);
            }
            // parent_id is never client-authoritative — see firstParentIdOf(), re-derived below only when
            // the body actually touches `projects`. A body that sent ONLY parent_id (no other field, no
            // `projects`) is therefore the same as an empty update, below.
            unset($clean['parent_id']);
            [$rawBlocks, $err] = $this->normalizeProjectBlocksShape($body);
            if ($err) {
                return $this->fail($err);
            }
            if (!$clean && $rawBlocks === null) {
                return $this->fail('No valid fields provided for update.');
            }
            if (array_key_exists('fiscal_year', $clean) && $clean['fiscal_year'] === null) {
                return $this->fail('fiscal_year cannot be empty.');
            }

            App\Execute('BEGIN', 'DB');
            $inTransaction = true;

            // Typed (new) Program / Sub-Program / Project per block: created first, inside the same transaction as
            // the change itself. $rawBlocks === null means the body didn't touch `projects` at all — the stored
            // blocks are left exactly as they are (and are what validateReferences() below checks against).
            $created = [];
            $blocks = null;
            if ($rawBlocks !== null) {
                $effectiveFiscalYear = array_key_exists('fiscal_year', $clean) ? $clean['fiscal_year'] : ($check['data']['fiscal_year'] ?? null);
                [$blocks, $created, $err] = $this->resolveProjectBlocks($rawBlocks, $effectiveFiscalYear, null);
                if ($err) {
                    App\Execute('ROLLBACK', 'DB');

                    return $this->fail($err);
                }
            }

            if ($err = $this->validateReferences($clean, $blocks ?? $check['data']['projects'], $check['data'], $id)) {
                App\Execute('ROLLBACK', 'DB');

                return $this->fail($err);
            }

            // Re-derive the parent_id legacy mirror (see firstParentIdOf()) only when the body actually
            // touched `projects` — otherwise the stored blocks (and whatever parent_id already mirrors
            // them) are left exactly as they are.
            if ($blocks !== null) {
                $effectiveType = array_key_exists('allocation_type', $clean) ? $clean['allocation_type'] : ($check['data']['allocation_type'] ?? '');
                $clean['parent_id'] = $this->firstParentIdOf($blocks, (string) $effectiveType);
            }

            if ($clean) {
                $set = [];
                foreach ($clean as $field => $value) {
                    $set[] = App\QuotedName($field, 'DB').' = '.$this->literal($field, $value);
                }
                $set[] = 'updated_at = CURRENT_TIMESTAMP';
                App\Execute("UPDATE {$this->table} SET ".implode(', ', $set)." WHERE allocation_id = {$id} AND deleted_at IS NULL", 'DB');
            }

            if ($blocks !== null) {
                $this->replaceProjectBlocks($id, $blocks);
            }

            App\Execute('COMMIT', 'DB');
            $inTransaction = false;

            $result = ['success' => true, 'message' => 'Memo request updated successfully.'];
            if ($created) {
                $result['created'] = $created;
            }

            return $result;
        } catch (\Exception $e) {
            if ($inTransaction) {
                App\Execute('ROLLBACK', 'DB');
            }
            App\LogError('BudgetAllocationService::update '.$e->getMessage());

            return $this->fail('Failed to update memo request.');
        }
    }

    // -----------------------------------------------------------------------
    // Delete (soft) — /budget_allocation/delete/{id}
    // Blocked while other memo requests still have this one as their parent, and blocked for an Approved
    // RFA/LAA that already has a non-cancelled/rejected disbursement voucher against it (see below).
    // Deleting an Approved CBA/CFA releases its reservations (its approval never moved money on the line
    // item itself — see BudgetLineItemService::createForApproval()); deleting an Approved RFA/LAA reverses
    // its draw — obligation down, the parent CBA's earmark restored
    // (BudgetLineItemService::releaseForAllocation() / reverseConversions()). Either way the soft delete
    // and that reversal are ONE transaction, so a failure part-way leaves both untouched.
    // -----------------------------------------------------------------------
    public function delete(int|string $id): array
    {
        $inTransaction = false;
        try {
            $id = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            // "Still a parent" is now a BLOCK-level question (parent_allocation_id — a CBA can be funding
            // several different RFA/LAA blocks, not just the one request that happens to mirror it as its
            // "first" parent_id) — the old header-only `WHERE parent_id = {$id}` check would miss a CBA
            // that is someone's SECOND or later parent, which is exactly the case this feature adds.
            $children = (int) App\ExecuteScalar(
                "SELECT COUNT(DISTINCT c.allocation_id)
                 FROM {$this->tableAllocationProject} p
                 JOIN {$this->table} c ON c.allocation_id = p.allocation_id AND c.deleted_at IS NULL
                 WHERE p.parent_allocation_id = {$id} AND p.deleted_at IS NULL",
                'DB'
            );
            if ($children > 0) {
                return $this->fail("Memo request #{$id} is the parent of {$children} other memo request(s) — delete or re-parent those first.");
            }

            // An Approved RFA/LAA's delete reverses a real financial commitment (obligation, the parent
            // CBA's earmark) — reversing one that has already been paid out is worse than refusing the
            // delete, so it's blocked outright while a non-cancelled/rejected voucher still points at it.
            $allocationType = strtoupper(trim((string) ($check['data']['allocation_type'] ?? '')));
            $isApproved = strtolower(trim((string) ($check['data']['status_name'] ?? ''))) === 'approved';
            $dvId = (int) ($check['data']['dv_id'] ?? 0);
            if (($allocationType === 'RFA' || $allocationType === 'LAA') && $isApproved && $dvId > 0) {
                $dvStatus = strtoupper(trim((string) App\ExecuteScalar(
                    "SELECT s.name FROM fims.disbursement_vouchers dv
                     LEFT JOIN {$this->tableStatus} s ON s.id = dv.status_id
                     WHERE dv.dv_id = {$dvId}",
                    'DB'
                )));
                if ($dvStatus !== '' && !in_array($dvStatus, ['CANCELLED', 'REJECTED'], true)) {
                    return $this->fail("Memo request #{$id} has a disbursement voucher against it that is not cancelled or rejected — cancel or reject it first.");
                }
            }

            App\Execute('BEGIN', 'DB');
            $inTransaction = true;

            App\Execute(
                "UPDATE {$this->table} SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE allocation_id = {$id}",
                'DB'
            );

            // Frees whatever this request was holding against its COB(s) and reverses whatever its approval
            // did to them — a no-op if it was never Approved (nothing held, nothing applied).
            (new BudgetLineItemService())->releaseForAllocation($id);

            App\Execute('COMMIT', 'DB');
            $inTransaction = false;

            return ['success' => true, 'message' => "Memo request #{$id} deleted successfully."];
        } catch (\Exception $e) {
            if ($inTransaction) {
                try {
                    App\Execute('ROLLBACK', 'DB');
                } catch (\Throwable $ignored) {
                }
            }
            App\LogError('BudgetAllocationService::delete '.$e->getMessage());

            return $this->fail('Failed to delete memo request.');
        }
    }

    // -----------------------------------------------------------------------
    // Lookups for the Memo Request form
    // -----------------------------------------------------------------------

    /** Active departments (uac.departments) for the Requesting Department / District pickers. */
    public function fetchDepartments(): array
    {
        try {
            $data = App\ExecuteRows(
                "SELECT department_id, department_name, department_code, department_acronym, department_type
                 FROM {$this->tableDepartment}
                 WHERE COALESCE(is_active, TRUE)
                 ORDER BY department_name",
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            App\LogError('BudgetAllocationService::fetchDepartments '.$e->getMessage());

            return $this->fail('Failed to load departments.');
        }
    }

    /** Statuses defined for memo requests (settings_status.status_for = 'budget_allocation'). */
    public function fetchStatuses(): array
    {
        try {
            $data = App\ExecuteRows(
                "SELECT id, name, color FROM {$this->tableStatus}
                 WHERE status_for = ".App\QuotedValue(self::STATUS_FOR, DataType::STRING, 'DB').'
                 ORDER BY id',
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            App\LogError('BudgetAllocationService::fetchStatuses '.$e->getMessage());

            return $this->fail('Failed to load statuses.');
        }
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    // -----------------------------------------------------------------------
    // Review — /budget_allocation/process/{id}
    //
    // The Financial Management Department (FMD) reviews a request that its office submitted for review, in three steps
    // (REVIEW_STEPS): For Review (FMD Section Chief) -> For Division Manager (FMD Division Manager) ->
    // For Department Manager (FMD Department Manager) -> Approved. At each step:
    //   approve -> the next status of the chain; the LAST approval gives 'Approved' and the ALLOCATION NUMBER (below);
    //   return  -> ONE step back, to the user who endorsed it (status 'Returned to Division Manager' / 'Returned to
    //              Section Chief', which that user acts on); the Section Chief's return gives 'Returned' (back to the
    //              requesting office). No number either way. EVERY return needs REMARKS (the reason: required, at most
    //              REMARKS_MAX_LENGTH characters): they are saved on budget_allocation.remarks (alter_table/28) in the
    //              same UPDATE as the status change, and the Process / View modal shows them on a returned request. An
    //              approval leaves them as they were.
    // Only an FMD user may do this (the central review RC, GlobalService::isCentralReviewRc — read with getUserRcId, which
    // fails closed), only to a request that is in one of the three review statuses, and only when the user holds the
    // user level of that step. The status change and the number are written in ONE transaction, and the UPDATE itself
    // re-checks the status, so two reviewers acting at once cannot both succeed.
    //
    // Returns { success, message, status_name, allocation_number }; a refusal for the caller's office or user level also
    // carries `forbidden: true` (the route answers 403 for it).
    // -----------------------------------------------------------------------
    public function process(int|string $id, string $decision, ?int $userId = null, ?string $remarks = null): array
    {
        $inTransaction = false;
        try {
            $decision = strtolower(trim($decision));
            if (!in_array($decision, ['approve', 'return'], true)) {
                return $this->fail("decision must be 'approve' or 'return'.");
            }
            if (!$this->isReviewer($userId)) {
                return $this->fail('Only the Financial Management Department can process memo requests.') + ['forbidden' => true];
            }

            $id = (int) $id;
            $found = $this->fetchById($id);
            if (!$found['success']) {
                return $found;
            }
            $row = $found['data'];

            // Which step of the chain is it at, and is this user the one who acts on that step?
            $step = $this->reviewStepOf($row);
            if ($step === null) {
                return $this->fail('Only a memo request that is under review ('.implode(', ', array_keys(self::REVIEW_STEPS))
                    .') can be approved or returned — this one is '
                    .(($row['status_name'] ?? '') !== '' ? $row['status_name'] : 'without a status').'.');
            }
            if (!$this->hasUserLevel($userId, $step['actor'])) {
                return $this->fail("This memo request is {$step['status']}: only the {$step['actor']} can approve or return it at this step.")
                    + ['forbidden' => true];
            }

            // A Return always says why (checked after the permission checks above, so a refusal for the caller comes first).
            $remarks = is_string($remarks) ? trim($remarks) : '';
            if ($decision === 'return') {
                if ($remarks === '') {
                    return $this->fail('Remarks are required to return a memo request — tell the next person what to fix.');
                }
                if (mb_strlen($remarks) > self::REMARKS_MAX_LENGTH) {
                    return $this->fail('Remarks must be at most '.self::REMARKS_MAX_LENGTH.' characters.');
                }
            }

            // The status the decision gives. Missing = the seed was not run: say so, change nothing.
            $currentId = (int) ($row['status_id'] ?? 0);
            $targetName = $decision === 'approve' ? $step['next'] : $step['return'];
            $targetId = $this->getStatusIdByName($targetName);
            if ($targetId === null) {
                return $this->fail("The status \"{$targetName}\" is not set up in the database yet, so nothing was changed. "
                    .'Ask an administrator to run the SQL installer (node install_system.js --sql-only).');
            }
            $isFinal = $targetName === self::STATUS_APPROVED;

            App\Execute('BEGIN', 'DB');
            $inTransaction = true;

            $set = ["status_id = {$targetId}", 'updated_at = CURRENT_TIMESTAMP'];
            if ($decision === 'return') {
                $set[] = 'remarks = '.App\QuotedValue($remarks, DataType::STRING, 'DB');
            }
            $number = trim((string) ($row['allocation_number'] ?? ''));
            if ($isFinal && $number === '') {
                // (one that already has a number keeps it — approving never renumbers)
                $number = $this->nextAllocationNumber((string) ($row['allocation_type'] ?? ''), $row['document_type'] ?? null);
                $set[] = 'allocation_number = '.App\QuotedValue($number, DataType::STRING, 'DB');
            }
            $done = App\ExecuteRow(
                "UPDATE {$this->table} SET ".implode(', ', $set)
                ." WHERE allocation_id = {$id} AND deleted_at IS NULL AND status_id = {$currentId} RETURNING allocation_id",
                'DB'
            );
            if (!$done) {   // somebody else got there first (or deleted it) between the check above and now
                App\Execute('ROLLBACK', 'DB');
                $inTransaction = false;

                return $this->fail('This memo request was changed by someone else in the meantime. Reload the list and try again.');
            }

            // The final approval moves money against each COB — the ceiling is a derived "available" figure
            // (BudgetLineBalanceSql::availableExpr(), via BudgetLineItemService::lockAndGetAvailable()), never
            // a single stored column. A CBA (or CFA) only RESERVES: it inserts an 'active' earmark row for its
            // requested_amount R, nothing else (createForApproval()). An RFA/LAA instead DRAWS DOWN its OWN
            // parent CBA's earmark PER BLOCK (parent_allocation_id — it can draw from several different CBAs
            // at once) and is the ONLY thing that ever adds to budget_line_items.obligation; approving one
            // with any block missing a parent, or one that would draw more than its own parent CBA still has
            // actively earmarked for a COB, is refused. Either way, refusing here rolls
            // back the status change too.
            if ($isFinal) {
                $allocationType = strtoupper(trim((string) ($row['allocation_type'] ?? '')));
                $lineItemService = new BudgetLineItemService();
                $blocks = $row['projects'] ?? [];

                // Lock every budget_line_item this approval touches, across EVERY block, in one ascending-id
                // pass before the per-block loop below — BudgetLineBalanceSql's locking contract requires a
                // consistent lock order across everything one call touches; a block-by-block loop alone only
                // honors that within each individual convertEarmarkForRfaApproval()/createForApproval() call,
                // not across the whole approval, which matters more now that a request can draw from several
                // parent CBAs (more line items touched per approval than before).
                $lineItemIds = [];
                foreach ($blocks as $block) {
                    $lt = $block['line_transaction'] ?? null;
                    if (!is_array($lt)) {
                        continue;
                    }
                    foreach (array_is_list($lt) ? $lt : [$lt] as $line) {
                        if (is_array($line) && isset($line['budget_line_item_id']) && is_numeric($line['budget_line_item_id'])) {
                            $lineItemIds[(int) $line['budget_line_item_id']] = true;
                        }
                    }
                }
                if ($lineItemIds) {
                    $sortedIds = array_keys($lineItemIds);
                    sort($sortedIds, SORT_NUMERIC);
                    App\ExecuteRows(
                        'SELECT 1 FROM fims.budget_line_items WHERE budget_line_item_id IN ('.implode(', ', $sortedIds).') ORDER BY budget_line_item_id FOR UPDATE',
                        'DB'
                    );
                }

                if ($allocationType === 'RFA' || $allocationType === 'LAA') {
                    // Each of THIS request's own project blocks draws against ITS OWN parent CBA
                    // (parent_allocation_id — an RFA/LAA can draw from several different CBAs at once, one
                    // per block; validateReferences() already required every block to have one, even at
                    // Draft save, but this is checked again here as the last line of defense before money
                    // actually moves).
                    foreach ($blocks as $i => $block) {
                        $blockParentId = (int) ($block['parent_allocation_id'] ?? 0);
                        if ($blockParentId <= 0) {
                            App\Execute('ROLLBACK', 'DB');
                            $inTransaction = false;

                            return $this->fail('Project block '.($i + 1)." has no Parent CBA — a {$allocationType} can only be approved once every project block draws from one.");
                        }
                        $earmark = $lineItemService->convertEarmarkForRfaApproval(
                            $id,
                            (int) $block['budget_allocation_project_id'],
                            $blockParentId,
                            (string) ($block['project_code'] ?? ''),
                            $block['line_transaction'] ?? null
                        );
                        if (!$earmark['success']) {
                            App\Execute('ROLLBACK', 'DB');
                            $inTransaction = false;

                            return $this->fail($earmark['message']);
                        }
                    }
                } else {
                    foreach ($blocks as $block) {
                        $earmark = $lineItemService->createForApproval($id, (int) $block['budget_allocation_project_id'], $block['line_transaction'] ?? null);
                        if (!$earmark['success']) {
                            App\Execute('ROLLBACK', 'DB');
                            $inTransaction = false;

                            return $this->fail($earmark['message']);
                        }
                    }
                }
            }

            App\Execute('COMMIT', 'DB');
            $inTransaction = false;

            // LAA/RFA/CBA are all fims.budget_allocation rows (allocation_type tells them apart) —
            // one shared log entry per decision, regardless of type.
            GlobalService::logStatusChange('budget_allocation', $id, $targetName, $userId, $decision === 'return' ? $remarks : null);

            if ($decision === 'return') {
                $message = isset(self::REVIEW_STEPS[$targetName])
                    ? 'Memo request returned to the '.self::REVIEW_STEPS[$targetName]['actor']." (now {$targetName})."
                    : 'Memo request returned to the requesting office.';
            } elseif ($isFinal) {
                $message = 'Memo request approved.'.($number !== '' ? " Allocation number: {$number}." : '');
            } else {
                $message = "Memo request endorsed: it is now {$targetName} (".self::REVIEW_STEPS[$targetName]['actor'].').';
            }

            return [
                'success' => true,
                'message' => $message,
                'status_name' => $targetName,
                'allocation_number' => $number !== '' ? $number : null,
            ];
        } catch (\Exception $e) {
            if ($inTransaction) {
                try {
                    App\Execute('ROLLBACK', 'DB');
                } catch (\Throwable $ignored) {
                }
            }
            App\LogError('BudgetAllocationService::process '.$e->getMessage());

            return $this->fail('Failed to process the memo request.');
        }
    }

    /**
     * Where a request stands in the review chain, for the "Process" modal (GET /budget_allocation/read/{id} adds it as
     * data.review): in_review, the status it is in, who acts on it (actor, a user level name), the status an approval
     * gives (next_status), whether that is the final approval (is_final) and whether THIS user may decide it now
     * (can_decide: an FMD user holding the step's user level). process() enforces the same rules again.
     */
    public function reviewInfo(array $row, ?int $userId): array
    {
        $step = $this->reviewStepOf($row);
        if ($step === null) {
            return ['in_review' => false, 'can_decide' => false];
        }

        return [
            'in_review' => true,
            'status' => $step['status'],
            'actor' => $step['actor'],
            'next_status' => $step['next'],
            'is_final' => $step['next'] === self::STATUS_APPROVED,
            // where a Return sends it, and whom (the actor of that step; null = back to the requesting office)
            'return_status' => $step['return'],
            'return_actor' => self::REVIEW_STEPS[$step['return']]['actor'] ?? null,
            'is_returned' => in_array($step['status'], [self::STATUS_RETURNED_TO_SECTION_CHIEF, self::STATUS_RETURNED_TO_DIVISION_MANAGER], true),
            'can_decide' => $this->isReviewer($userId) && $this->hasUserLevel($userId, $step['actor']),
        ];
    }

    /** The REVIEW_STEPS entry (+ 'status') of the status this row is in, or null when it is not under review. */
    private function reviewStepOf(array $row): ?array
    {
        $name = strtolower(trim((string) ($row['status_name'] ?? '')));
        foreach (self::REVIEW_STEPS as $status => $step) {
            if ($name === strtolower($status)) {
                return $step + ['status' => $status];
            }
        }

        return null;
    }

    /**
     * Does this user hold the user level called $levelName? uac.users.user_level_id is a VARCHAR that may list several
     * ids ("3,10"); each is looked up in uac.user_levels and compared by NAME, ignoring case. A missing user, no level,
     * or a lookup failure is "no" — never "yes".
     */
    private function hasUserLevel(?int $userId, string $levelName): bool
    {
        try {
            $userId = (int) $userId;
            if ($userId <= 0) {
                return false;
            }
            $raw = (string) App\ExecuteScalar("SELECT user_level_id FROM {$this->tableUser} WHERE user_id = {$userId}", 'DB');
            $ids = [];
            foreach (explode(',', $raw) as $part) {
                if (preg_match('/^-?\d+$/', $part = trim($part))) {
                    $ids[] = (int) $part;
                }
            }
            if (!$ids) {
                return false;
            }

            return (int) App\ExecuteScalar(
                'SELECT COUNT(*) FROM uac.user_levels WHERE user_level_id IN ('.implode(', ', $ids).')'
                .' AND LOWER(TRIM(name)) = LOWER('.App\QuotedValue(trim($levelName), DataType::STRING, 'DB').')',
                'DB'
            ) > 0;
        } catch (\Throwable $e) {
            App\LogError('BudgetAllocationService::hasUserLevel '.$e->getMessage());

            return false;
        }
    }

    /** Is this user of the central review RC (FMD)? A missing user, no RC, or a lookup failure is "no" — never "yes". */
    private function isReviewer(?int $userId): bool
    {
        try {
            $userId = (int) $userId;

            return $userId > 0 && GlobalService::isCentralReviewRc(GlobalService::getUserRcId($userId));
        } catch (\Throwable $e) {
            App\LogError('BudgetAllocationService::isReviewer '.$e->getMessage());

            return false;
        }
    }

    // -----------------------------------------------------------------------
    // Allocation number templates — the ones the Memo Request form shows (buildAllocationNumber() in
    // form/budget-allocation-form.php), by allocation type and document type. With y = year, yy = its last two digits,
    // q = quarter (1-4), m = month within the quarter (1-3) of the day the number is given:
    //
    //   CBA   CBA <y>-<q><m>-86-<series>          e.g.  CBA 2026-42-86-01
    //   RFA   <yy><q><m>-<code>-<series>          e.g.  2642-87-01     code 57 for document type COE, 87 for CO
    //   LAA   LAA <y>-<q><m>-<series>             e.g.  LAA 2026-42-01
    //   other <TYPE> <y>-<q><m>-<series>
    //
    // <series> is two digits at least (01, 02, … 99, 100). It counts within one PREFIX (everything before the series),
    // so it starts again at 01 each month for each type — and never repeats within a prefix. Numbers of deleted
    // requests count too, so a number is never given twice.
    // -----------------------------------------------------------------------
    public static function allocationNumberPrefix(string $type, ?string $documentType, \DateTimeInterface $at): string
    {
        $type = strtoupper(trim($type));
        $type = $type === '' ? 'CBA' : $type;
        $y = $at->format('Y');
        $yy = substr($y, -2);
        $mo = (int) $at->format('n');
        $q = (int) ceil($mo / 3);
        $qm = $q.($mo - ($q - 1) * 3);

        return match ($type) {
            'CBA' => "CBA {$y}-{$qm}-86-",
            'RFA' => "{$yy}{$qm}-".(strtoupper(trim((string) $documentType)) === 'COE' ? '57' : '87').'-',
            'LAA' => "LAA {$y}-{$qm}-",
            default => "{$type} {$y}-{$qm}-",
        };
    }

    public static function formatAllocationNumber(string $prefix, int $series): string
    {
        return $prefix.str_pad((string) $series, 2, '0', STR_PAD_LEFT);
    }

    /**
     * The next free allocation number for this type / document type, today (Philippine time). Call it INSIDE the
     * transaction that stores the number: it takes a transaction-level advisory lock first, so two approvals running
     * at once are numbered one after the other and cannot read the same "last series" — the lock is released by the
     * COMMIT / ROLLBACK of that transaction.
     */
    private function nextAllocationNumber(string $type, ?string $documentType): string
    {
        App\ExecuteScalar("SELECT pg_advisory_xact_lock(hashtext('fims.budget_allocation.allocation_number'))", 'DB');

        $prefix = self::allocationNumberPrefix($type, $documentType, new \DateTimeImmutable('now', new \DateTimeZone(self::ALLOCATION_NUMBER_TIMEZONE)));
        $len = mb_strlen($prefix);
        $from = $len + 1;
        $last = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CASE WHEN SUBSTRING(allocation_number FROM {$from}) ~ '^[0-9]{1,6}\$'
                                      THEN CAST(SUBSTRING(allocation_number FROM {$from}) AS INTEGER) END), 0)
             FROM {$this->table}
             WHERE LEFT(allocation_number, {$len}) = ".App\QuotedValue($prefix, DataType::STRING, 'DB'),
            'DB'
        );

        return self::formatAllocationNumber($prefix, $last + 1);
    }

    /**
     * Looked up by name each time rather than hardcoding its id — the id a seed
     * row lands on depends on insert order (same approach as BudgetReqService).
     */
    private function getStatusIdByName(string $name): ?int
    {
        $row = App\ExecuteRow(
            "SELECT id FROM {$this->tableStatus} WHERE status_for = ".App\QuotedValue(self::STATUS_FOR, DataType::STRING, 'DB')
            .' AND name = '.App\QuotedValue($name, DataType::STRING, 'DB'),
            'DB'
        );

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    /**
     * SQL for the sum of "requested_amount" over the lines of EVERY project block's line_transaction (jsonb; an
     * array of lines, or a single line object) belonging to this allocation row. A line whose requested_amount is
     * missing, blank or not a number counts as 0, so one bad line can never fail the whole list. Commas in a text
     * amount ("1,500,000.00") are ignored. Constant SQL (correlated to the outer query's `a` alias), no user input.
     */
    private function requestedTotalSql(): string
    {
        return "COALESCE((
                    SELECT SUM(CASE
                                 WHEN jsonb_typeof(ln.value->'requested_amount') = 'number' THEN (ln.value->>'requested_amount')::numeric
                                 WHEN jsonb_typeof(ln.value->'requested_amount') = 'string'
                                      AND REPLACE(ln.value->>'requested_amount', ',', '') ~ '^-?[0-9]+([.][0-9]+)?$'
                                   THEN REPLACE(ln.value->>'requested_amount', ',', '')::numeric
                                 ELSE 0
                               END)
                    FROM {$this->tableAllocationProject} bap
                    CROSS JOIN LATERAL jsonb_array_elements(CASE jsonb_typeof(bap.line_transaction)
                                                WHEN 'array'  THEN bap.line_transaction
                                                WHEN 'object' THEN jsonb_build_array(bap.line_transaction)
                                                ELSE '[]'::jsonb
                                              END) AS ln(value)
                    WHERE bap.allocation_id = a.allocation_id AND bap.deleted_at IS NULL
                      AND jsonb_typeof(ln.value) = 'object'
                ), 0)";
    }

    /** SELECT + every join the list / read views need; callers append WHERE / ORDER BY. Program / Sub-Program /
     *  Project (now per project block) are attached separately — see attachProjects(). */
    private function baseSelect(string $columns): string
    {
        return "SELECT {$columns},
                       st.name  AS status_name,
                       st.color AS status_color,
                       v.vendor_name,
                       rc.rc_code     AS requesting_rc_code,
                       rc.rc_name     AS requesting_rc_name,
                       rc.office_type AS requesting_rc_office_type,
                       dd.department_name AS requesting_district_name,
                       -- FIRST parent only (a.parent_id is a maintained legacy mirror — see firstParentIdOf(),
                       -- migration 53); a caller that needs every parent CBA an RFA/LAA draws from reads the
                       -- `parents` array attachProjects() attaches instead.
                       pa.request_referrence_no AS parent_request_referrence_no,
                       {$this->requestedTotalSql()} AS requested_total
                FROM {$this->table} a
                LEFT JOIN {$this->tableStatus} st     ON st.id = a.status_id
                LEFT JOIN {$this->tableVendor} v      ON v.vendor_id = a.vendor_id
                LEFT JOIN {$this->tableRc} rc         ON rc.rc_id = a.requesting_rc_id
                LEFT JOIN {$this->tableDepartment} dd ON dd.department_id = a.requesting_district_id
                LEFT JOIN {$this->table} pa           ON pa.allocation_id = a.parent_id";
    }

    /**
     * Attaches `projects` (every project block of each row, see fetchProjectBlocksFor()) IN PLACE, plus a
     * compatibility mirror of the FIRST block's program_code / sub_program_code / project_code / program_name /
     * sub_program_name / project_name onto the row itself — callers written before multi-project support (print
     * payloads, the ledger, read-only views) that only ever read one project per record keep working unchanged
     * for the (still by far the common) single-block case; a multi-block record simply shows its first block
     * there until those callers are updated to read the full `projects` array. line_transaction, unlike those
     * single-value fields, is MERGED across every block (in block order) rather than taken from the first one
     * alone — a caller summing/listing lines off this field must see every one of them.
     *
     * Also attaches `parents` — the distinct parent CBAs across this row's own blocks, in block order, each
     * {allocation_id, request_referrence_no, allocation_number} — for RFA/LAA, which can now draw from
     * several at once. The row's own `parent_id` column (and baseSelect()'s `parent_request_referrence_no`)
     * stays a mirror of only the FIRST one — see firstParentIdOf(); a caller that needs the full set reads
     * `parents` instead.
     *
     * @param array<int,array<string,mixed>> &$rows rows from baseSelect(), modified in place
     */
    private function attachProjects(array &$rows): void
    {
        if (!$rows) {
            return;
        }
        $byAllocation = $this->fetchProjectBlocksFor(array_column($rows, 'allocation_id'));
        foreach ($rows as &$row) {
            $blocks = $byAllocation[(int) $row['allocation_id']] ?? [];
            $row['projects'] = $blocks;
            $first = $blocks[0] ?? null;
            foreach (['program_code', 'sub_program_code', 'project_code', 'program_name', 'sub_program_name', 'project_name'] as $field) {
                $row[$field] = $first[$field] ?? null;
            }
            $lines = [];
            $parents = [];
            foreach ($blocks as $block) {
                $blockLines = $block['line_transaction'] ?? null;
                if (is_array($blockLines)) {
                    array_push($lines, ...(array_is_list($blockLines) ? $blockLines : [$blockLines]));
                }
                $pid = $block['parent_allocation_id'] ?? null;
                if ($pid !== null && !isset($parents[$pid])) {
                    $parents[$pid] = [
                        'allocation_id' => (int) $pid,
                        'request_referrence_no' => $block['parent_request_referrence_no'] ?? null,
                        'allocation_number' => $block['parent_allocation_number'] ?? null,
                    ];
                }
            }
            $row['line_transaction'] = $lines ?: null;
            $row['parents'] = array_values($parents);
        }
        unset($row);
    }

    /**
     * Every project block (fims.budget_allocation_project) of the given allocations, with Program / Sub-Program /
     * Project names joined in, ordered by sort_order — the batched read-side counterpart to insertProjectBlocks() /
     * replaceProjectBlocks(). One query regardless of how many allocation ids are passed (same batching precedent
     * as BudgetReqService::attachProgramsToHeaders()).
     *
     * @param array<int,int|string> $allocationIds
     *
     * @return array<int, array<int, array<string,mixed>>> allocation_id => its blocks, decoded line_transaction
     */
    private function fetchProjectBlocksFor(array $allocationIds): array
    {
        $allocationIds = array_values(array_unique(array_map('intval', $allocationIds)));
        if (!$allocationIds) {
            return [];
        }

        $rows = App\ExecuteRows(
            "SELECT p.budget_allocation_project_id, p.allocation_id, p.sort_order,
                    p.program_code, p.sub_program_code, p.project_code, p.line_transaction,
                    p.parent_allocation_id,
                    pg.program_name, sp.sub_program_name, pj.project_name,
                    pa.request_referrence_no AS parent_request_referrence_no,
                    pa.allocation_number     AS parent_allocation_number
             FROM {$this->tableAllocationProject} p
             LEFT JOIN {$this->tableProgram} pg    ON pg.program_code = p.program_code
             LEFT JOIN {$this->tableSubProgram} sp ON sp.sub_program_code = p.sub_program_code
             LEFT JOIN {$this->tableProject} pj    ON pj.project_code = p.project_code
             LEFT JOIN {$this->table} pa           ON pa.allocation_id = p.parent_allocation_id
             WHERE p.deleted_at IS NULL AND p.allocation_id IN (".implode(', ', $allocationIds).')
             ORDER BY p.allocation_id, p.sort_order, p.budget_allocation_project_id',
            'DB'
        ) ?: [];

        $byAllocation = [];
        foreach ($rows as $row) {
            if (isset($row['line_transaction']) && is_string($row['line_transaction'])) {
                $row['line_transaction'] = json_decode($row['line_transaction'], true);
            }
            $row['budget_allocation_project_id'] = (int) $row['budget_allocation_project_id'];
            $row['parent_allocation_id'] = isset($row['parent_allocation_id']) ? (int) $row['parent_allocation_id'] : null;
            $byAllocation[(int) $row['allocation_id']][] = $row;
        }

        return $byAllocation;
    }

    /** The Responsibility Center (rc_id) of a user: uac.users.rc_id, that column only. Null when the user has none. */
    private function requestingRcOf(int $userId): ?string
    {
        $rcId = trim((string) App\ExecuteScalar("SELECT rc_id FROM {$this->tableUser} WHERE user_id = {$userId}", 'DB'));

        return $rcId === '' ? null : $rcId;
    }

    private function intFilter(array $filters, string $key): ?int
    {
        $v = $filters[$key] ?? null;

        return ($v !== null && $v !== '' && is_numeric($v)) ? (int) $v : null;
    }

    /**
     * Validates the SHAPE of the request body's `projects` array — one entry per project block — without
     * resolving typed names or touching the database yet (see resolveProjectBlocks() for that, which must run
     * inside the caller's open transaction). Each block may carry program_code / sub_program_code / project_code
     * (picked) and/or program_name / sub_program_name / project_name (typed — see resolveProjectBlocks()),
     * optionally budget_allocation_project_id (update(): which existing block this is — absent means "new block"),
     * and line_transaction (that block's own itemized COB draws, same shape the record's line_transaction used to
     * carry directly).
     *
     * @return array{0: ?array, 1: ?string} [blocks (raw, still unresolved), error] — blocks is null when the body
     *                                       has no `projects` key at all (create() requires it; update() leaves
     *                                       the stored blocks untouched when it's absent)
     */
    private function normalizeProjectBlocksShape(array $body): array
    {
        if (!array_key_exists('projects', $body)) {
            return [null, null];
        }
        $projects = $body['projects'];
        if (is_string($projects)) {
            $projects = json_decode($projects, true);
        }
        if (!is_array($projects) || !array_is_list($projects)) {
            return [null, 'projects must be a JSON array of project blocks.'];
        }
        if (!$projects) {
            return [null, 'At least one project is required.'];
        }

        $blocks = [];
        foreach ($projects as $i => $block) {
            $n = $i + 1;
            if (!is_array($block)) {
                return [null, "Project block {$n} must be an object."];
            }

            $clean = [];
            if (isset($block['budget_allocation_project_id']) && $block['budget_allocation_project_id'] !== '') {
                if (!is_numeric($block['budget_allocation_project_id'])) {
                    return [null, "Project block {$n}: budget_allocation_project_id must be a whole number."];
                }
                $clean['budget_allocation_project_id'] = (int) $block['budget_allocation_project_id'];
            }

            // The parent CBA THIS block draws its budget from (RFA/LAA only — see validateReferences(),
            // which rejects it on any other allocation_type and requires it on every RFA/LAA block, even
            // on a Draft save). array_key_exists (not isset) so an explicit null can clear a previously
            // picked parent on update().
            if (array_key_exists('parent_allocation_id', $block)) {
                $v = trim((string) ($block['parent_allocation_id'] ?? ''));
                if ($v === '') {
                    $clean['parent_allocation_id'] = null;
                } elseif (!preg_match('/^\d+$/', $v) || (int) $v > 2147483647) {
                    return [null, "Project block {$n}: parent_allocation_id must be a whole number."];
                } else {
                    $clean['parent_allocation_id'] = (int) $v;
                }
            }

            foreach (self::BLOCK_STRING_FIELDS as $field => $max) {
                if (!array_key_exists($field, $block)) {
                    continue;
                }
                if ($block[$field] !== null && !is_scalar($block[$field])) {
                    return [null, "Project block {$n}: {$field} must be a plain value."];
                }
                $v = trim((string) ($block[$field] ?? ''));
                if (mb_strlen($v) > $max) {
                    return [null, "Project block {$n}: {$field} must be {$max} characters or fewer."];
                }
                $clean[$field] = $v === '' ? null : $v;
            }

            foreach (self::TYPED_NAME_FIELDS as $key) {
                if (!array_key_exists($key, $block)) {
                    continue;
                }
                $v = $block[$key];
                if ($v !== null && !is_scalar($v)) {
                    return [null, "Project block {$n}: {$key} must be a plain value."];
                }
                $v = trim((string) preg_replace('/\s+/u', ' ', (string) ($v ?? '')));
                if ($v === '') {
                    continue;
                }
                if (mb_strlen($v) > self::TYPED_NAME_MAX) {
                    return [null, "Project block {$n}: {$key} must be ".self::TYPED_NAME_MAX.' characters or fewer.'];
                }
                $clean[$key] = $v;
            }

            if (array_key_exists('line_transaction', $block)) {
                $lt = $block['line_transaction'];
                if ($lt === null || $lt === '') {
                    $clean['line_transaction'] = null;
                } else {
                    $decoded = $lt;
                    if (is_string($lt)) {
                        $decoded = json_decode($lt, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            return [null, "Project block {$n}: line_transaction is not valid JSON."];
                        }
                    }
                    if (!is_array($decoded)) {
                        return [null, "Project block {$n}: line_transaction must be a JSON array or object."];
                    }
                    $clean['line_transaction'] = $decoded;
                }
            } else {
                $clean['line_transaction'] = null;
            }

            $blocks[] = $clean;
        }

        return [$blocks, null];
    }

    /**
     * Turns each block's typed name(s) into codes, creating what does not exist yet — in the order Program,
     * Sub-Program, Project, because each belongs to the one before it (same find-or-create precedent the record
     * itself used to have — see findOrCreateProgram() / findOrCreateSubProgram() / findOrCreateProject() below,
     * unchanged). A level whose code was PICKED on that block wins over any typed name for it. Must run inside the
     * caller's open transaction (the caller rolls back on an error, so nothing created here survives a failed save).
     *
     * @param array<int,array<string,mixed>> $rawBlocks  from normalizeProjectBlocksShape()
     * @param ?int                           $fiscalYear the record's effective fiscal_year (a newly typed Project
     *                                                    is created under it)
     *
     * @return array{0: array, 1: array<string,string>, 2: ?string} [resolved blocks, created codes (keyed
     *                                                                "projects.<i>.<field>"), error]
     */
    private function resolveProjectBlocks(array $rawBlocks, ?int $fiscalYear, ?int $userId): array
    {
        $resolved = [];
        $created = [];

        foreach ($rawBlocks as $i => $block) {
            $n = $i + 1;
            $program = $block['program_code'] ?? null;
            $sub = $block['sub_program_code'] ?? null;
            $project = $block['project_code'] ?? null;

            if ($program === null && isset($block['program_name'])) {
                [$program, $isNew, $err] = $this->findOrCreateProgram($block['program_name'], $userId);
                if ($err) {
                    return [[], $created, "Project block {$n}: {$err}"];
                }
                if ($isNew) {
                    $created["projects.{$i}.program_code"] = $program;
                }
            }
            if ($sub === null && isset($block['sub_program_name'])) {
                if ($program === null) {
                    return [[], $created, "Project block {$n}: a new sub-program needs a program — pick or type the program first."];
                }
                [$sub, $isNew, $err] = $this->findOrCreateSubProgram($block['sub_program_name'], (string) $program);
                if ($err) {
                    return [[], $created, "Project block {$n}: {$err}"];
                }
                if ($isNew) {
                    $created["projects.{$i}.sub_program_code"] = $sub;
                }
            }
            if ($project === null && isset($block['project_name'])) {
                if ($program === null) {
                    return [[], $created, "Project block {$n}: a new project needs a program — pick or type the program first."];
                }
                [$project, $isNew, $err] = $this->findOrCreateProject($block['project_name'], (string) $program, $sub, $fiscalYear);
                if ($err) {
                    return [[], $created, "Project block {$n}: {$err}"];
                }
                if ($isNew) {
                    $created["projects.{$i}.project_code"] = $project;
                }
            }

            $resolved[] = [
                'budget_allocation_project_id' => $block['budget_allocation_project_id'] ?? null,
                'program_code' => $program,
                'sub_program_code' => $sub,
                'project_code' => $project,
                'line_transaction' => $block['line_transaction'] ?? null,
                'parent_allocation_id' => $block['parent_allocation_id'] ?? null,
            ];
        }

        return [$resolved, $created, null];
    }

    /** SQL literal for a nullable VARCHAR block field. */
    private function blockStringLiteral(?string $value): string
    {
        return $value === null ? 'NULL' : App\QuotedValue($value, DataType::STRING, 'DB');
    }

    /** SQL literal for a nullable INTEGER block field (e.g. parent_allocation_id) — already a validated int by the time it gets here. */
    private function blockIntLiteral(?int $value): string
    {
        return $value === null ? 'NULL' : (string) $value;
    }

    /**
     * budget_allocation.parent_id's value as a maintained LEGACY DISPLAY MIRROR of the FIRST (lowest
     * sort_order) block's own parent_allocation_id — RFA/LAA only; CBA/CFA never have one. See migration
     * 53: nothing that moves money may read this column — the authoritative per-draw parent is each
     * block's own parent_allocation_id (validateReferences(), process()). Called from create()/update()
     * right after blocks are resolved and validated, so create and update can never derive it differently.
     */
    private function firstParentIdOf(array $blocks, string $allocationType): ?int
    {
        $allocationType = strtoupper(trim($allocationType));
        if ($allocationType !== 'RFA' && $allocationType !== 'LAA') {
            return null;
        }
        $parentId = ($blocks[0] ?? null)['parent_allocation_id'] ?? null;

        return $parentId !== null ? (int) $parentId : null;
    }

    /** SQL literal (::jsonb) for a block's line_transaction. */
    private function blockLineTransactionLiteral(mixed $value): string
    {
        return $value === null ? 'NULL'
            : App\QuotedValue(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), DataType::STRING, 'DB').'::jsonb';
    }

    /** create(): every resolved block is brand new — one INSERT per block, in the order given (sort_order). */
    private function insertProjectBlocks(int $allocationId, array $blocks): void
    {
        foreach ($blocks as $i => $block) {
            App\Execute(
                "INSERT INTO {$this->tableAllocationProject} (allocation_id, sort_order, program_code, sub_program_code, project_code, line_transaction, parent_allocation_id)
                 VALUES ({$allocationId}, {$i}, "
                    .$this->blockStringLiteral($block['program_code']).', '
                    .$this->blockStringLiteral($block['sub_program_code']).', '
                    .$this->blockStringLiteral($block['project_code']).', '
                    .$this->blockLineTransactionLiteral($block['line_transaction']).', '
                    .$this->blockIntLiteral($block['parent_allocation_id'] ?? null).')',
                'DB'
            );
        }
    }

    /**
     * update(): a block carrying a budget_allocation_project_id that still belongs to this allocation is updated
     * IN PLACE (never dropped and recreated — fims.budget_line_item_earmark rows reference a block's id, and that
     * FK is ON DELETE CASCADE); a block with no id (or one that doesn't match) is a new block, inserted. Any
     * existing block not present among $blocks is SOFT-deleted (deleted_at), never hard-deleted, for the same
     * earmark-safety reason — see the table's own SQL file.
     */
    private function replaceProjectBlocks(int $allocationId, array $blocks): void
    {
        $existingIds = array_map('intval', array_column(
            App\ExecuteRows(
                "SELECT budget_allocation_project_id FROM {$this->tableAllocationProject}
                 WHERE allocation_id = {$allocationId} AND deleted_at IS NULL",
                'DB'
            ) ?: [],
            'budget_allocation_project_id'
        ));
        $keptIds = [];

        foreach ($blocks as $i => $block) {
            $programLit = $this->blockStringLiteral($block['program_code']);
            $subLit = $this->blockStringLiteral($block['sub_program_code']);
            $projectLit = $this->blockStringLiteral($block['project_code']);
            $ltLit = $this->blockLineTransactionLiteral($block['line_transaction']);
            $parentLit = $this->blockIntLiteral($block['parent_allocation_id'] ?? null);

            $blockId = $block['budget_allocation_project_id'] ?? null;
            if ($blockId !== null && in_array((int) $blockId, $existingIds, true)) {
                $blockId = (int) $blockId;
                $keptIds[] = $blockId;
                App\Execute(
                    "UPDATE {$this->tableAllocationProject}
                     SET sort_order = {$i}, program_code = {$programLit}, sub_program_code = {$subLit},
                         project_code = {$projectLit}, line_transaction = {$ltLit}, parent_allocation_id = {$parentLit}, updated_at = CURRENT_TIMESTAMP
                     WHERE budget_allocation_project_id = {$blockId} AND allocation_id = {$allocationId}",
                    'DB'
                );
            } else {
                App\Execute(
                    "INSERT INTO {$this->tableAllocationProject} (allocation_id, sort_order, program_code, sub_program_code, project_code, line_transaction, parent_allocation_id)
                     VALUES ({$allocationId}, {$i}, {$programLit}, {$subLit}, {$projectLit}, {$ltLit}, {$parentLit})",
                    'DB'
                );
            }
        }

        $removedIds = array_diff($existingIds, $keptIds);
        if ($removedIds) {
            App\Execute(
                "UPDATE {$this->tableAllocationProject} SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE budget_allocation_project_id IN (".implode(', ', $removedIds).')',
                'DB'
            );
        }
    }

    /** @return array{0: ?string, 1: bool, 2: ?string} [program_code, created?, error] — an existing program with that name (any case) is reused */
    private function findOrCreateProgram(string $name, ?int $userId): array
    {
        $row = App\ExecuteRow(
            "SELECT program_code FROM {$this->tableProgram} WHERE LOWER(program_name) = LOWER(".App\QuotedValue($name, DataType::STRING, 'DB').') ORDER BY program_code LIMIT 1',
            'DB'
        );
        if ($row) {
            return [(string) $row['program_code'], false, null];
        }

        $result = (new ProgramService())->create(['program_name' => $name], $userId);
        if (!($result['success'] ?? false)) {
            return [null, false, "Could not create the program \"{$name}\": ".($result['message'] ?? 'unknown error')];
        }

        return [(string) $result['data']['program_code'], true, null];
    }

    /** @return array{0: ?string, 1: bool, 2: ?string} [sub_program_code, created?, error] — reused when this program already has one with that name */
    private function findOrCreateSubProgram(string $name, string $programCode): array
    {
        $row = App\ExecuteRow(
            "SELECT sub_program_code FROM {$this->tableSubProgram} WHERE program_code = ".App\QuotedValue($programCode, DataType::STRING, 'DB')
            .' AND LOWER(sub_program_name) = LOWER('.App\QuotedValue($name, DataType::STRING, 'DB').') ORDER BY sub_program_code LIMIT 1',
            'DB'
        );
        if ($row) {
            return [(string) $row['sub_program_code'], false, null];
        }

        try {
            $next = GlobalService::nextSubProgramNumber($programCode);
            $code = GlobalService::formatSubProgramCode($next['prefix'], $next['next']);
        } catch (\InvalidArgumentException $e) {
            return [null, false, "Could not create the sub-program \"{$name}\": ".$e->getMessage()];
        }

        $result = (new SubProgramService())->create(['sub_program_code' => $code, 'sub_program_name' => $name, 'program_code' => $programCode]);
        if (!($result['success'] ?? false)) {
            return [null, false, "Could not create the sub-program \"{$name}\": ".($result['message'] ?? 'unknown error')];
        }

        return [$code, true, null];
    }

    /**
     * @return array{0: ?string, 1: bool, 2: ?string} [project_code, created?, error] — reused when the same program AND
     *                                                sub-program (or, with no sub-program, a project that has none) already has one with that name
     */
    private function findOrCreateProject(string $name, string $programCode, ?string $subProgramCode, ?int $fiscalYr): array
    {
        $subCondition = $subProgramCode === null
            ? 'sub_program_code IS NULL'
            : 'sub_program_code = '.App\QuotedValue($subProgramCode, DataType::STRING, 'DB');
        $row = App\ExecuteRow(
            "SELECT project_code FROM {$this->tableProject} WHERE program_code = ".App\QuotedValue($programCode, DataType::STRING, 'DB')
            ." AND {$subCondition} AND LOWER(project_name) = LOWER(".App\QuotedValue($name, DataType::STRING, 'DB').') ORDER BY project_code LIMIT 1',
            'DB'
        );
        if ($row) {
            return [(string) $row['project_code'], false, null];
        }

        $result = (new ProjectService())->create([
            'project_name' => $name,
            'program_code' => $programCode,
            'sub_program_code' => $subProgramCode ?? '',
            'fiscal_yr' => $fiscalYr,
        ]);
        if (!($result['success'] ?? false)) {
            return [null, false, "Could not create the project \"{$name}\": ".($result['message'] ?? 'unknown error')];
        }

        return [(string) $result['project_code'], true, null];
    }

    /**
     * Turns the request body into typed PHP values, one per writable column that
     * is present in the body ('' / null → null). Unknown keys are ignored, which
     * also keeps user_id / created_at / deleted_at / allocation_id out of reach.
     *
     * @return array{0: array<string, mixed>, 1: ?string} [values, error]
     */
    private function normalize(array $body): array
    {
        $clean = [];

        foreach (self::STRING_FIELDS as $field => $max) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            if ($body[$field] !== null && !is_scalar($body[$field])) {
                return [[], "{$field} must be a plain value."];
            }
            $v = trim((string) ($body[$field] ?? ''));
            if (mb_strlen($v) > $max) {
                return [[], "{$field} must be {$max} characters or fewer."];
            }
            $clean[$field] = $v === '' ? null : $v;
        }

        if (array_key_exists('description', $body)) {
            if ($body['description'] !== null && !is_scalar($body['description'])) {
                return [[], 'description must be plain text.'];
            }
            $v = trim((string) ($body['description'] ?? ''));
            $clean['description'] = $v === '' ? null : $v;
        }

        foreach (self::INT_FIELDS as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            if ($body[$field] !== null && !is_scalar($body[$field])) {
                return [[], "{$field} must be a whole number."];
            }
            $v = trim((string) ($body[$field] ?? ''));
            if ($v === '') {
                $clean[$field] = null;
            } elseif (!preg_match('/^\d+$/', $v) || (int) $v > 2147483647) {
                return [[], "{$field} must be a whole number."];
            } else {
                $clean[$field] = (int) $v;
            }
        }

        foreach (self::AMOUNT_FIELDS as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            if ($body[$field] !== null && !is_scalar($body[$field])) {
                return [[], "{$field} must be a number."];
            }
            $v = str_replace(',', '', trim((string) ($body[$field] ?? '')));
            if ($v === '') {
                $clean[$field] = null;
            } elseif (!is_numeric($v) || (float) $v < 0 || (float) $v > self::MAX_AMOUNT) {
                return [[], "{$field} must be a number from 0 to 9,999,999,999,999.99."];
            } else {
                $clean[$field] = (float) $v;
            }
        }

        if (array_key_exists('is_bidding', $body)) {
            $b = $body['is_bidding'];
            $clean['is_bidding'] = ($b === null || $b === '') ? null : filter_var($b, FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('multiple_node_id', $body)) {
            $v = trim((string) ($body['multiple_node_id'] ?? ''));
            if ($v === '') {
                $clean['multiple_node_id'] = null;
            } elseif (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v)) {
                return [[], 'multiple_node_id must be a UUID.'];
            } else {
                $clean['multiple_node_id'] = strtolower($v);
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
        if (isset(self::STRING_FIELDS[$field]) || $field === 'description' || $field === self::REQUESTING_RC_FIELD) {
            return App\QuotedValue((string) $value, DataType::STRING, 'DB');
        }
        if (in_array($field, self::INT_FIELDS, true)) {
            return (string) (int) $value;
        }
        if (in_array($field, self::AMOUNT_FIELDS, true)) {
            return number_format((float) $value, 2, '.', '');
        }
        if ($field === 'is_bidding') {
            return $value ? 'TRUE' : 'FALSE';
        }
        if ($field === 'multiple_node_id') {
            return App\QuotedValue((string) $value, DataType::STRING, 'DB').'::uuid';
        }

        throw new \LogicException("No SQL literal rule for field '{$field}'.");
    }

    private function exists(string $table, string $column, int|string $value, string $extraWhere = ''): bool
    {
        $lit = is_int($value) ? (string) $value : App\QuotedValue($value, DataType::STRING, 'DB');

        return (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$table} WHERE {$column} = {$lit}{$extraWhere}", 'DB') > 0;
    }

    /**
     * Friendly errors for bad references (the FKs would reject them too, but with
     * an opaque DB message). $current / $id are set on update: the parent may not
     * be the row itself or one of its descendants. $blocks is the record's
     * EFFECTIVE set of project blocks — resolved ones from resolveProjectBlocks()
     * when the body touched `projects`, otherwise the stored blocks (fetchById()'s
     * `projects`) — each checked for its own Program → Sub-Program → Project chain.
     *
     * @param array<int,array<string,mixed>> $blocks
     *
     * @return ?string error message, or null when everything checks out
     */
    private function validateReferences(array $clean, array $blocks, ?array $current = null, ?int $id = null): ?string
    {
        $checks = [
            ['fiscal_year',               $this->tableFiscalYear, 'fiscal_yr',     '',                                             'Fiscal year'],
            ['vendor_id',                 $this->tableVendor,     'vendor_id',     '',                                             'Vendor'],
            ['status_id',                 $this->tableStatus,     'id',            " AND status_for = '".self::STATUS_FOR."'", 'Status'],
            [self::REQUESTING_RC_FIELD,   $this->tableRc,         'rc_id',         '',                                             'Requesting RC'],
            ['requesting_district_id',    $this->tableDepartment, 'department_id', '',                                             'Requesting district'],
        ];
        foreach ($checks as [$field, $table, $column, $extra, $label]) {
            $value = $clean[$field] ?? null;
            if ($value !== null && !$this->exists($table, $column, $value, $extra)) {
                return "{$label} '{$value}' does not exist.";
            }
        }

        // ── Parent CBA(s) ─────────────────────────────────────────────────────
        // The parent now lives PER BLOCK (fims.budget_allocation_project.parent_allocation_id) — an RFA/LAA
        // can draw from several different CBAs at once, one per block; budget_allocation.parent_id is only
        // a maintained legacy display mirror of the first one (see firstParentIdOf(), migration 53) and is
        // never itself validated or read here. A top-level body `parent_id` is simply ignored/overwritten
        // by create()/update() — the authoritative input is each block's own parent_allocation_id.
        $effective = static fn (string $f) => array_key_exists($f, $clean) ? $clean[$f] : ($current[$f] ?? null);
        $effectiveType = strtoupper(trim((string) ($effective('allocation_type') ?? '')));
        $isRfaOrLaa = $effectiveType === 'RFA' || $effectiveType === 'LAA';

        if (!$blocks) {
            return 'At least one project is required.';
        }

        // One pass to collect/validate every distinct parent CBA referenced across this record's blocks,
        // batched into a couple of queries rather than one per block.
        //   - CBA / CFA never draw from a parent: a parent_allocation_id on one of their blocks is rejected.
        //   - RFA / LAA requires a parent on EVERY block, even on a Draft save (not just at Approval) — a
        //     block can never be saved half-configured.
        //   - The same parent CBA may not be selected on two blocks of the same record (each parent funds
        //     exactly one block — ambiguous otherwise).
        //   - Each parent must exist, be a CBA, be Approved, and not be this row itself. Cycles are
        //     structurally impossible once a CBA can never itself have a parent — a strictly stronger
        //     guarantee than the old hop-limited parent_id chain walk this replaces, so no walk is needed.
        $parentIds = [];
        foreach ($blocks as $i => $block) {
            $n = $i + 1;
            $blockParentId = $block['parent_allocation_id'] ?? null;

            if (!$isRfaOrLaa) {
                if ($blockParentId !== null) {
                    return "Project block {$n}: a {$effectiveType} does not draw from a parent CBA.";
                }
                continue;
            }

            if ($blockParentId === null) {
                return "Project block {$n}: a Parent CBA is required.";
            }
            if (isset($parentIds[$blockParentId])) {
                return "Project block {$n}: parent CBA #{$blockParentId} is already selected on another project block — each parent CBA funds one block.";
            }
            $parentIds[$blockParentId] = true;
        }

        $parentProjectCodesByParent = [];
        if ($parentIds) {
            $parentIdList = array_map('intval', array_keys($parentIds));
            $approvedStatusId = $this->getStatusIdByName(self::STATUS_APPROVED);
            $parentById = [];
            foreach (App\ExecuteRows(
                "SELECT allocation_id, allocation_type, status_id FROM {$this->table}
                 WHERE allocation_id IN (".implode(', ', $parentIdList).') AND deleted_at IS NULL',
                'DB'
            ) ?: [] as $pr) {
                $parentById[(int) $pr['allocation_id']] = $pr;
            }
            foreach ($parentIdList as $pid) {
                if ($id !== null && $pid === $id) {
                    return "Parent CBA #{$pid}: a memo request cannot be its own parent.";
                }
                $pr = $parentById[$pid] ?? null;
                if (!$pr) {
                    return "Parent CBA #{$pid} does not exist.";
                }
                if (strtoupper(trim((string) $pr['allocation_type'])) !== 'CBA') {
                    return "Parent CBA #{$pid} is not a CBA.";
                }
                if ($approvedStatusId === null || (int) $pr['status_id'] !== $approvedStatusId) {
                    return "Parent CBA #{$pid} is not Approved.";
                }
            }

            // RFA / LAA: every project block must be one the parent CBA itself has a block for — the
            // parent-earmark lookup (convertEarmarkForRfaApproval() / validateRfaAmounts()) matches by
            // project_code, so a project that isn't one of the parent's own blocks could otherwise never
            // resolve an earmark at all.
            foreach (App\ExecuteRows(
                "SELECT allocation_id, project_code FROM {$this->tableAllocationProject}
                 WHERE allocation_id IN (".implode(', ', $parentIdList).') AND deleted_at IS NULL',
                'DB'
            ) ?: [] as $cr) {
                $parentProjectCodesByParent[(int) $cr['allocation_id']][] = $cr['project_code'];
            }
        }

        // ── Edit-lock: a block that already has money earmarked against it (its Approved RFA/LAA already
        // drew, or it is a CBA/CFA block already reserved) can no longer change its parent, project, or
        // line items — those are exactly what that earmark is keyed to. Only meaningful on update(), for
        // an EXISTING block (one carrying its own budget_allocation_project_id) whose parent/project/lines
        // actually changed from what is currently stored.
        if ($id !== null && $current !== null) {
            $oldBlocksById = [];
            foreach (($current['projects'] ?? []) as $ob) {
                if (isset($ob['budget_allocation_project_id'])) {
                    $oldBlocksById[(int) $ob['budget_allocation_project_id']] = $ob;
                }
            }
            foreach ($blocks as $i => $block) {
                $blockId = $block['budget_allocation_project_id'] ?? null;
                if ($blockId === null || !isset($oldBlocksById[(int) $blockId])) {
                    continue; // a brand-new block — nothing to protect yet
                }
                $old = $oldBlocksById[(int) $blockId];
                $changed = (($block['parent_allocation_id'] ?? null) !== ($old['parent_allocation_id'] ?? null))
                    || (($block['project_code'] ?? null) !== ($old['project_code'] ?? null))
                    || (json_encode($block['line_transaction'] ?? null) !== json_encode($old['line_transaction'] ?? null));
                if (!$changed) {
                    continue;
                }
                $hasEarmark = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM fims.budget_line_item_earmark
                     WHERE budget_allocation_project_id = ".(int) $blockId." AND status IN ('active', 'converted')",
                    'DB'
                ) > 0;
                if ($hasEarmark) {
                    $n = $i + 1;

                    return "Project block {$n}: this block already has money earmarked against it — its Parent CBA, Project, or line items can no longer be changed.";
                }
            }
        }

        foreach ($blocks as $i => $block) {
            $n = $i + 1;
            $program = $block['program_code'] ?? null;
            $sub = $block['sub_program_code'] ?? null;
            $project = $block['project_code'] ?? null;

            // ── Program → Sub-Program → Project must belong together ─────────
            if ($program !== null && !$this->exists($this->tableProgram, 'program_code', $program)) {
                return "Project block {$n}: program '{$program}' does not exist.";
            }
            if ($sub !== null) {
                $row = App\ExecuteRow(
                    "SELECT program_code FROM {$this->tableSubProgram} WHERE sub_program_code = ".App\QuotedValue($sub, DataType::STRING, 'DB'),
                    'DB'
                );
                if (!$row) {
                    return "Project block {$n}: sub-program '{$sub}' does not exist.";
                }
                if ($program === null) {
                    return "Project block {$n}: select a program for the sub-program.";
                }
                if ($row['program_code'] !== $program) {
                    return "Project block {$n}: sub-program '{$sub}' does not belong to program '{$program}'.";
                }
            }
            if ($project === null) {
                return "Project block {$n}: a project is required.";
            }
            $row = App\ExecuteRow(
                "SELECT program_code, sub_program_code FROM {$this->tableProject} WHERE project_code = ".App\QuotedValue($project, DataType::STRING, 'DB'),
                'DB'
            );
            if (!$row) {
                return "Project block {$n}: project '{$project}' does not exist.";
            }
            if ($program === null) {
                return "Project block {$n}: select a program for the project.";
            }
            if ($row['program_code'] !== $program) {
                return "Project block {$n}: project '{$project}' does not belong to program '{$program}'.";
            }
            if ($sub !== null && ($row['sub_program_code'] ?? null) !== null && $row['sub_program_code'] !== $sub) {
                return "Project block {$n}: project '{$project}' belongs to sub-program '{$row['sub_program_code']}', not '{$sub}'.";
            }

            if ($isRfaOrLaa) {
                $blockParentId = (int) ($block['parent_allocation_id'] ?? 0);
                if (!in_array($project, $parentProjectCodesByParent[$blockParentId] ?? [], true)) {
                    return "Project block {$n}: project '{$project}' is not one of parent CBA #{$blockParentId}'s project blocks.";
                }

                // This block's Requested Amount per COB must not exceed what its OWN parent CBA still has
                // actively earmarked there — the same ceiling convertEarmarkForRfaApproval() enforces at
                // Approval time, checked here too (create and update) so a request isn't accepted only to
                // be rejected later purely over an amount that was already wrong when it was submitted.
                if (($block['line_transaction'] ?? null) !== null) {
                    $check = (new BudgetLineItemService())->validateRfaAmounts($blockParentId, (string) $project, $block['line_transaction']);
                    if (!$check['success']) {
                        return "Project block {$n}: ".$check['message'];
                    }
                }
            }
        }

        return null;
    }
}
