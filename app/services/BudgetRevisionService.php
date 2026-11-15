<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Budget revisions: a request to change a BUDGET LINE's amount — a chart-of-accounts line item of a budget request
 * (fims.budget_line_items) — optionally moving the difference to a second budget line (the To line).
 *
 * A line's CURRENT amount is  proposed_amount + adjustment.  Approving a revision moves `adjustment` ("net adjustments
 * applied to the proposed amount" — the running record of approved revisions) AND `suggested_amount` (the line's live
 * balance, the figure a Memo Request draws on) by the same delta; proposed_amount stays exactly as the office filed it:
 *   From line  adjustment, suggested_amount += (to_amount − its current amount)       → its current amount becomes to_amount
 *   To line    adjustment, suggested_amount += (the From line's current amount − to_amount)   → it receives the difference
 * so a transfer deducts from one line and adds to the other by the same amount.
 * (Because suggested_amount moves too, a line's Available Balance is suggested_amount alone — adding adjustment on top would
 * count every revision twice. See BudgetLineItemService::fetchApproved().)
 * A revision that would leave the From line's suggested_amount negative is refused — when it is filed, when it is edited and
 * again when it is approved (fromBalanceError()); an increase is never refused.
 * See _assets/sql/01 - fims/03 - alter_table/23 - budget_revision_on_budget_reference_number.sql.
 */
class BudgetRevisionService
{
    // The statuses live in fims.settings_status (status_for = 'budget_revision'), looked up by NAME — their ids differ per
    // install (see statusId()). status_id is NOT NULL and is not on the form, so a newly filed revision starts as Pending;
    // review() then moves it to Approved or Declined. Seeded by "_assets/sql/01 - fims/04 - seed_data/08 - budget_revision_statuses.sql".
    private const STATUS_FOR      = 'budget_revision';
    private const STATUS_PENDING  = 'Pending';
    private const STATUS_APPROVED = 'Approved';
    private const STATUS_DECLINED = 'Declined';

    /** @var array<string, int> status name => id, read once per request */
    private array $statusIds = [];

    // A line's current amount is kept below 10^13 (proposed_amount is NUMERIC(15,2)).
    private const MAX_LINE_AMOUNT = 10000000000000;

    private string $table          = 'fims.budget_revision';
    private string $tableLines     = 'fims.budget_line_items';
    private string $tableReq       = 'fims.budget_req';
    private string $tableReqProj   = 'fims.budget_req_project';
    private string $tableReqProg   = 'fims.budget_req_program';
    private string $tableProgram   = 'fims.program';
    private string $tableProject   = 'fims.project';
    private string $tableAccounts  = 'fims.chart_of_accounts';
    private string $tableRc        = 'fims.responsibility_code';
    private string $tableStatus    = 'fims.settings_status';
    private string $tableUser      = 'uac.users';

    // The JOINs that turn a revision's line id (rv.$col) into the line and everything shown with it, under the alias prefix $p:
    // {p}l the line, {p}b its budget request (+ {p}rc its office, {p}st its status), {p}bp its project (+ {p}bg its program row,
    // {p}pg / {p}pj their names), {p}coa its account. All LEFT JOINs: a line that no longer exists leaves everything NULL.
    private function lineJoins(string $p, string $col): string
    {
        return "LEFT JOIN {$this->tableLines} {$p}l ON {$p}l.budget_line_item_id = rv.{$col}
                LEFT JOIN {$this->tableReq} {$p}b ON {$p}b.budget_reference_number = {$p}l.budget_reference_number
                LEFT JOIN {$this->tableRc} {$p}rc ON {$p}rc.rc_id = {$p}b.rc_id
                LEFT JOIN {$this->tableStatus} {$p}st ON {$p}st.id = {$p}b.status_id
                LEFT JOIN {$this->tableReqProj} {$p}bp ON {$p}bp.budget_req_project_id = {$p}l.budget_req_project_id
                LEFT JOIN {$this->tableReqProg} {$p}bg ON {$p}bg.budget_req_program_id = {$p}bp.budget_req_program_id
                LEFT JOIN {$this->tableProgram} {$p}pg ON {$p}pg.program_code = {$p}bg.program_code
                LEFT JOIN {$this->tableProject} {$p}pj ON {$p}pj.project_code = {$p}bp.project_code
                LEFT JOIN {$this->tableAccounts} {$p}coa ON {$p}coa.account_id = {$p}l.account_id";
    }

    // The columns of one line, named with the prefix $pre ('' for the From line, 'to_' for the To line).
    private function lineSelect(string $p, string $pre): string
    {
        return "{$p}b.budget_reference_number AS {$pre}budget_reference_number, {$p}b.fiscal_yr AS {$pre}fiscal_yr,
                       {$p}rc.rc_code AS {$pre}rc_code, {$p}rc.rc_name AS {$pre}rc_name,
                       {$p}st.name AS {$pre}budget_req_status_name, {$p}st.color AS {$pre}budget_req_status_color,
                       {$p}bg.program_code AS {$pre}program_code, {$p}pg.program_name AS {$pre}program_name,
                       {$p}bp.project_code AS {$pre}project_code, {$p}pj.project_name AS {$pre}project_name,
                       {$p}l.account_id AS {$pre}account_id, {$p}coa.account_code AS {$pre}account_code,
                       {$p}coa.account_title AS {$pre}account_title, {$p}l.particulars AS {$pre}particulars,
                       {$p}l.proposed_amount AS {$pre}proposed_amount, {$p}l.adjustment AS {$pre}line_adjustment,
                       ({$p}l.proposed_amount + {$p}l.adjustment) AS {$pre}line_amount";
    }

    // FROM + JOIN part shared by the list, count, sum and read queries.
    // fl… = the From line (from_budget_line_item_id) and what hangs off it; tl… = the optional To line.
    private function joinSql(): string
    {
        // rs = the REVISION's own status. Matched on status_for too, so a status_id that still holds an old number (the seed was not
        // run yet) is not read as some other module's status.
        return "FROM {$this->table} rv
                LEFT JOIN {$this->tableStatus} rs ON rs.id = rv.status_id AND rs.status_for = " . App\QuotedValue(self::STATUS_FOR, App\DataType::STRING, 'DB') . "
                " . $this->lineJoins('f', 'from_budget_line_item_id') . "
                " . $this->lineJoins('t', 'to_budget_line_item_id') . "
                LEFT JOIN {$this->tableUser} us ON us.user_id = rv.user_id";
    }

    // `status` is the readable form of the status — its name in capitals: PENDING / APPROVED / DECLINED, or UNKNOWN when status_id
    // matches no revision status — and status_name / status_color are the settings_status row itself (the page paints its badge
    // with that colour); requested_by_* is who filed it
    // (both NULL for the built-in administrator, whose user_id -1 has no users row).
    // The From line comes back as budget_reference_number / fiscal_yr / rc_* / budget_req_status_* (its request), program_* / project_*,
    // account_* / particulars, proposed_amount / line_adjustment / line_amount (its CURRENT amount); the To line (all NULL when
    // there is none) as the to_* equivalents. A line that no longer exists leaves its fields NULL — the revision still lists.
    private function selectSql(): string
    {
        return "SELECT rv.*,
                       " . $this->lineSelect('f', '') . ",
                       " . $this->lineSelect('t', 'to_') . ",
                       COALESCE(UPPER(rs.name), 'UNKNOWN') AS status, rs.name AS status_name, rs.color AS status_color,
                       us.username AS requested_by_username,
                       NULLIF(TRIM(CONCAT_WS(' ', us.first_name, us.last_name)), '') AS requested_by_name "
            . $this->joinSql();
    }

    // The id of the revision status called $name (Pending / Approved / Declined), or null when it is not set up — the seed was not run.
    private function statusId(string $name): ?int
    {
        if (!array_key_exists($name, $this->statusIds)) {
            $row = App\ExecuteRow(
                "SELECT id FROM {$this->tableStatus} WHERE status_for = " . App\QuotedValue(self::STATUS_FOR, App\DataType::STRING, 'DB')
                . ' AND name = ' . App\QuotedValue($name, App\DataType::STRING, 'DB'),
                'DB'
            );
            if (!isset($row['id'])) {
                return null;   // not cached: a status added after this is picked up on the next call
            }
            $this->statusIds[$name] = (int) $row['id'];
        }

        return $this->statusIds[$name];
    }

    // The response for a status that is not set up: say what to run, change nothing.
    private function missingStatus(string $name): array
    {
        return ['success' => false, 'message' => "The status \"{$name}\" is not set up in the database yet, so nothing was changed. "
            . 'Run "_assets/sql/01 - fims/04 - seed_data/08 - budget_revision_statuses.sql" on the database '
            . '(or ask an administrator to run the SQL installer: node install_system.js --sql-only), then try again.'];
    }

    private function lineExists(int $id): bool
    {
        return (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM {$this->tableLines} WHERE budget_line_item_id = " . App\QuotedValue($id, App\DataType::NUMBER, 'DB'),
            'DB'
        ) > 0;
    }

    // The CURRENT amount of one line (proposed_amount + adjustment).
    private function currentAmountOf(int $id): float
    {
        return round((float) App\ExecuteScalar(
            "SELECT proposed_amount + adjustment FROM {$this->tableLines} WHERE budget_line_item_id = "
            . App\QuotedValue($id, App\DataType::NUMBER, 'DB'),
            'DB'
        ), 2);
    }

    private function isAmount(mixed $value): bool
    {
        return is_numeric($value) && (float) $value >= 0;
    }

    // A budget line id: null when none was sent (null / ''), false when what was sent is not a usable id, otherwise the id.
    private function parseId(mixed $raw): int|null|false
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = filter_var($raw, FILTER_VALIDATE_INT);

        return ($id === false || $id <= 0) ? false : $id;
    }

    // A revision with a To line MOVES funds: the From line goes down to To Amount and the To line goes up by the difference.
    // So the To line must exist, be a different line, and To Amount must be lower than From Amount (an increase would mean taking
    // funds out of the To line). Returns an error message, or null when the combination is fine.
    private function transferError(int $fromId, int $toId, mixed $fromAmount, mixed $toAmount): ?string
    {
        if ($toId === $fromId) {
            return 'to_budget_line_item_id must be different from from_budget_line_item_id.';
        }
        if (!$this->lineExists($toId)) {
            return 'to_budget_line_item_id does not exist.';
        }
        if (round((float) $toAmount, 2) >= round((float) $fromAmount, 2)) {
            return 'To Amount must be lower than From Amount to move funds to the To budget line.';
        }

        return null;
    }

    // The From line must not end up with a negative balance. Approving moves its suggested_amount (its live balance — what is left
    // after the CBAs/CFAs already approved against it) by (To Amount − its current amount, proposed_amount + adjustment). So a
    // revision that LOWERS the line by more than its suggested_amount would leave it below zero, and is refused; an increase
    // (or a decrease that fits) is always fine. Measured against the line's live figures — the same ones approve() uses, never the
    // stored from_amount, which can be stale. Returns an error message, or null when the revision is fine.
    private function fromBalanceError(int $fromId, mixed $toAmount): ?string
    {
        $line = App\ExecuteRow(
            "SELECT suggested_amount, proposed_amount + adjustment AS current_amount FROM {$this->tableLines} WHERE budget_line_item_id = "
            . App\QuotedValue($fromId, App\DataType::NUMBER, 'DB'),
            'DB'
        );
        if (!$line) {
            return null;   // a missing line is reported by the callers' own lineExists() checks
        }

        $suggested = round((float) $line['suggested_amount'], 2);
        $change    = round((float) $toAmount - (float) $line['current_amount'], 2);   // negative = the line goes down
        if ($change >= 0 || round($suggested + $change, 2) >= 0) {
            return null;
        }

        return 'The From budget line has only ' . number_format($suggested, 2) . ' left (its suggested amount), so it cannot go down by '
            . number_format(-$change, 2) . ' — that would leave it negative (' . number_format($suggested + $change, 2) . ').';
    }

    // reason is optional long text — empty string is stored as NULL.
    // Quoted through the connection rather than App\QuotedValue(): that helper turns
    // line breaks and backslashes into literal "\n" / "\\" text, which PostgreSQL
    // would store as-is and corrupt a multi-line reason.
    private function reasonSql(array $body): string
    {
        $reason = trim((string) ($body['reason'] ?? ''));

        return $reason === '' ? 'NULL' : App\Conn('DB')->quote($reason);
    }

    // A failed statement, as the response. A database that has not had the revision migration yet fails in a recognisable way —
    // the from_ / to_budget_line_item_id columns do not exist — and for that the message says what to run; the database's own
    // message is kept after it.
    private function failure(\Throwable $e): array
    {
        $message = $e->getMessage();
        if (preg_match('/_budget_line_item_id.{0,80}does not exist/is', $message)) {
            $message = 'Budget revisions need a database update that has not been applied yet. Run '
                . '"_assets/sql/01 - fims/03 - alter_table/23 - budget_revision_on_budget_reference_number.sql" on the database, then reload. '
                . '(' . $message . ')';
        }

        return ['success' => false, 'message' => $message];
    }

    // $budgetReference: a budget reference number ("BP-001") — the list keeps the revisions that touch a line of that budget request,
    // as the From line or the To line.
    public function fetchAlls(?int $page = null, int $limit = 10, string $fiscalYr = '', string $budgetReference = ''): array
    {
        try {
            $where = ['rv.deleted_at IS NULL'];

            if ($fiscalYr !== '') {
                $where[] = 'fb.fiscal_yr = ' . (int) $fiscalYr;
            }
            if (trim($budgetReference) !== '') {
                // a revision touches both of its lines, so it belongs to either one's budget request
                $ref     = App\QuotedValue(trim($budgetReference), App\DataType::STRING, 'DB');
                $where[] = "(fl.budget_reference_number = {$ref} OR tl.budget_reference_number = {$ref})";
            }
            $whereSql = implode(' AND ', $where);

            if ($page === null) {
                $data = App\ExecuteRows($this->selectSql() . " WHERE {$whereSql} ORDER BY rv.id DESC", 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $page   = max(1, $page);
            $limit  = max(1, $limit);
            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) {$this->joinSql()} WHERE {$whereSql}", 'DB');
            $data  = App\ExecuteRows(
                $this->selectSql() . " WHERE {$whereSql} ORDER BY rv.id DESC LIMIT {$limit} OFFSET {$offset}",
                'DB'
            );

            $totalChange = (float) App\ExecuteScalar(
                "SELECT COALESCE(SUM(rv.to_amount - rv.from_amount), 0) {$this->joinSql()} WHERE {$whereSql}",
                'DB'
            );

            return [
                'success'      => true,
                'data'         => $data ?: [],
                'total_change' => $totalChange,
                'pagination'   => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            return $this->failure($e);
        }
    }

    public function fetchById(int|string $id): array
    {
        try {
            $id   = (int) $id;
            $data = App\ExecuteRow($this->selectSql() . " WHERE rv.id = {$id} AND rv.deleted_at IS NULL", 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Budget revision request #{$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return $this->failure($e);
        }
    }

    public function create(array $body): array
    {
        try {
            // The filing user is whoever is logged in (from the session / JWT), never the request body.
            $userId = App\CurrentUserID();

            $fromId     = $this->parseId($body['from_budget_line_item_id'] ?? null);
            $fromAmount = $body['from_amount'] ?? '';
            $toAmount   = $body['to_amount'] ?? '';

            if ($fromId === null || $fromId === false) {
                return ['success' => false, 'message' => 'from_budget_line_item_id is required.'];
            }
            if (!$this->isAmount($fromAmount) || !$this->isAmount($toAmount)) {
                return ['success' => false, 'message' => 'from_amount and to_amount must be non-negative numbers.'];
            }
            if (!$this->lineExists($fromId)) {
                return ['success' => false, 'message' => "Budget line #{$fromId} does not exist."];
            }
            // optional To line: when set, the revision moves funds from the From line to it
            $toId = $this->parseId($body['to_budget_line_item_id'] ?? null);
            if ($toId === false) {
                return ['success' => false, 'message' => 'to_budget_line_item_id does not exist.'];
            }
            if ($toId !== null) {
                $error = $this->transferError($fromId, $toId, $fromAmount, $toAmount);
                if ($error !== null) {
                    return ['success' => false, 'message' => $error];
                }
            }
            // the From line may not be left with a negative balance
            $error = $this->fromBalanceError($fromId, $toAmount);
            if ($error !== null) {
                return ['success' => false, 'message' => $error];
            }
            // PHPMaker gives the built-in administrator (superadmin) the id -1, so a negative id
            // is a valid user. Only an empty / non-numeric / 0 id means nobody is logged in.
            if (!is_numeric($userId) || (int) $userId === 0) {
                return ['success' => false, 'message' => 'Unable to determine the current user.'];
            }
            // a new revision starts as Pending (looked up first: when the status is missing nothing is written)
            $pendingId = $this->statusId(self::STATUS_PENDING);
            if ($pendingId === null) {
                return $this->missingStatus(self::STATUS_PENDING);
            }

            $sql = "INSERT INTO {$this->table} (from_budget_line_item_id, to_budget_line_item_id, from_amount, to_amount, reason, user_id, status_id)
                    VALUES ("
                . App\QuotedValue($fromId, App\DataType::NUMBER, 'DB') . ', '
                . ($toId === null ? 'NULL' : App\QuotedValue($toId, App\DataType::NUMBER, 'DB')) . ', '
                . App\QuotedValue($fromAmount, App\DataType::NUMBER, 'DB') . ', '
                . App\QuotedValue($toAmount, App\DataType::NUMBER, 'DB') . ', '
                . $this->reasonSql($body) . ', '
                . App\QuotedValue((int) $userId, App\DataType::NUMBER, 'DB') . ', '
                . $pendingId . ')
                    RETURNING id';

            $row = App\ExecuteRow($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Budget revision request created successfully.',
                'id'      => $row['id'] ?? null,
            ];
        } catch (\Exception $e) {
            return $this->failure($e);
        }
    }

    public function update(int|string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            // An approved revision has already been applied to its lines' adjustments, so
            // changing it afterwards would leave the two out of step.
            if (($check['data']['status'] ?? '') === 'APPROVED') {
                return ['success' => false, 'message' => "Budget revision request #{$id} is already approved and can no longer be edited."];
            }

            $setParts = [];

            // The values the revision will have after this update (the stored ones unless the
            // body changes them) — the From/To line rule below is checked against these.
            $row     = $check['data'];
            $fromId  = $row['from_budget_line_item_id'] === null ? null : (int) $row['from_budget_line_item_id'];
            $toId    = $row['to_budget_line_item_id'] === null ? null : (int) $row['to_budget_line_item_id'];
            $fromAmt = $row['from_amount'];
            $toAmt   = $row['to_amount'];

            $newFrom = $this->parseId($body['from_budget_line_item_id'] ?? null);
            if ($newFrom === false || ($newFrom !== null && !$this->lineExists($newFrom))) {
                return ['success' => false, 'message' => 'from_budget_line_item_id does not exist.'];
            }
            if ($newFrom !== null) {
                $fromId     = $newFrom;
                $setParts[] = 'from_budget_line_item_id = ' . App\QuotedValue($newFrom, App\DataType::NUMBER, 'DB');
            }

            // The To line can be cleared, so it is applied whenever the key is sent ('' = none).
            if (array_key_exists('to_budget_line_item_id', $body)) {
                $toId = $this->parseId($body['to_budget_line_item_id']);
                if ($toId === false) {
                    return ['success' => false, 'message' => 'to_budget_line_item_id does not exist.'];
                }
                $setParts[] = 'to_budget_line_item_id = ' . ($toId === null ? 'NULL' : App\QuotedValue($toId, App\DataType::NUMBER, 'DB'));
            }

            foreach (['from_amount', 'to_amount'] as $field) {
                $value = $body[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                if (!$this->isAmount($value)) {
                    return ['success' => false, 'message' => "{$field} must be a non-negative number."];
                }
                if ($field === 'from_amount') {
                    $fromAmt = $value;
                } else {
                    $toAmt = $value;
                }
                $setParts[] = "{$field} = " . App\QuotedValue($value, App\DataType::NUMBER, 'DB');
            }

            // A revision filed before budget lines were revised has no From line; it can be edited only by giving it one.
            if ($fromId === null) {
                return ['success' => false, 'message' => 'from_budget_line_item_id is required: this revision was filed before budget lines could be revised, so pick its budget line.'];
            }
            if ($toId !== null) {
                $error = $this->transferError($fromId, $toId, $fromAmt, $toAmt);
                if ($error !== null) {
                    return ['success' => false, 'message' => $error];
                }
            }
            // the From line may not be left with a negative balance
            $error = $this->fromBalanceError($fromId, $toAmt);
            if ($error !== null) {
                return ['success' => false, 'message' => $error];
            }

            // reason can be cleared, so it is applied whenever the key is sent.
            if (array_key_exists('reason', $body)) {
                $setParts[] = 'reason = ' . $this->reasonSql($body);
            }

            if (empty($setParts)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            $setParts[] = 'update_at = NOW()';

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE id = ' . (int) $id;

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Budget revision request updated successfully.'];
        } catch (\Exception $e) {
            return $this->failure($e);
        }
    }

    // Approve or decline a PENDING revision. $status is 'APPROVED' or 'DECLINED'.
    // DECLINED only records the decision. APPROVED also applies the change to the budget line(s)
    // (their adjustment) — see approve().
    public function review(int|string $id, string $status): array
    {
        try {
            $target = [
                'APPROVED' => self::STATUS_APPROVED,
                'DECLINED' => self::STATUS_DECLINED,
            ][strtoupper(trim($status))] ?? null;

            if ($target === null) {
                return ['success' => false, 'message' => "status must be 'APPROVED' or 'DECLINED'."];
            }

            // The statuses are looked up first: when one is missing nothing has been read as pending or changed.
            if ($this->statusId(self::STATUS_PENDING) === null) {
                return $this->missingStatus(self::STATUS_PENDING);
            }
            $targetId = $this->statusId($target);
            if ($targetId === null) {
                return $this->missingStatus($target);
            }

            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            if (($check['data']['status'] ?? '') !== 'PENDING') {
                $current = ucfirst(strtolower((string) $check['data']['status']));

                return ['success' => false, 'message' => "Only pending requests can be reviewed — request #{$id} is already {$current}."];
            }

            if ($target === self::STATUS_APPROVED) {
                return $this->approve((int) $id, $check['data']);
            }

            // DECLINED — only the status changes; the budget lines are left alone.
            // The status_id guard makes the change atomic: if someone reviewed it
            // between the read above and this UPDATE, no row matches.
            $affected = App\Execute(
                "UPDATE {$this->table}
                 SET status_id = {$targetId}, update_at = NOW()
                 WHERE id = " . (int) $id . ' AND deleted_at IS NULL AND status_id = ' . $this->statusId(self::STATUS_PENDING),
                'DB'
            );
            if (!$affected) {
                return ['success' => false, 'message' => "Request #{$id} was already reviewed by someone else."];
            }

            return [
                'success' => true,
                'message' => "Budget revision request #{$id} declined.",
                'status'  => 'DECLINED',
            ];
        } catch (\Exception $e) {
            return $this->failure($e);
        }
    }

    // Approve = record the decision AND apply it to the budget line(s), as ONE statement so
    // either everything happens or nothing does. The lines' adjustment AND suggested_amount move, both by the same delta
    // (never proposed_amount):
    //   From line (from_budget_line_item_id) — always:
    //     adjustment, suggested_amount += (to_amount − the amount the line has right now)   → its current amount becomes to_amount
    //   To line (to_budget_line_item_id) — only when the revision has one: it receives what the From line gave up,
    //     adjustment, suggested_amount += (the From line's current amount − to_amount)
    // so the two move by the same amount in opposite directions. The change is measured against the live amount, not
    // from_amount: from_amount can be stale if another revision was approved after this one was filed.
    private function approve(int $id, array $rev): array
    {
        $fromId = $rev['from_budget_line_item_id'] === null ? null : (int) $rev['from_budget_line_item_id'];
        $toId   = $rev['to_budget_line_item_id'] === null ? null : (int) $rev['to_budget_line_item_id'];
        $newAmt = round((float) $rev['to_amount'], 2);

        // the ids of the two statuses the approval moves the revision between (review() has already found them)
        $pendingId  = $this->statusId(self::STATUS_PENDING);
        $approvedId = $this->statusId(self::STATUS_APPROVED);
        if ($pendingId === null) {
            return $this->missingStatus(self::STATUS_PENDING);
        }
        if ($approvedId === null) {
            return $this->missingStatus(self::STATUS_APPROVED);
        }

        if ($newAmt >= self::MAX_LINE_AMOUNT) {
            return ['success' => false, 'message' => 'To Amount is too large for a budget line (maximum 9,999,999,999,999.99).'];
        }
        if ($fromId === null || !$this->lineExists($fromId)) {
            return ['success' => false, 'message' => "The From budget line for revision #{$id} no longer exists."];
        }
        // The balance may have dropped since this was filed (CBAs approved against the line in the meantime): re-check it now.
        $balanceError = $this->fromBalanceError($fromId, $newAmt);
        if ($balanceError !== null) {
            return ['success' => false, 'message' => $balanceError];
        }

        $moved = 0.0;
        $toNow = 0.0;
        if ($toId !== null) {
            if ($toId === $fromId) {
                return ['success' => false, 'message' => "Revision #{$id} has the same budget line as its From and To line."];
            }
            if (!$this->lineExists($toId)) {
                return ['success' => false, 'message' => "The To budget line for revision #{$id} no longer exists."];
            }
            // What the From line has right now decides how much moves; it must really go down.
            $fromNow = $this->currentAmountOf($fromId);
            $toNow   = $this->currentAmountOf($toId);
            $moved   = round($fromNow - $newAmt, 2);
            if ($moved <= 0) {
                return ['success' => false, 'message' => 'To Amount ' . number_format($newAmt, 2) . ' is not lower than the From budget line\'s current amount '
                    . number_format($fromNow, 2) . ', so no funds can be moved to the To budget line.'];
            }
            if ($toNow + $moved >= self::MAX_LINE_AMOUNT) {
                return ['success' => false, 'message' => 'The To budget line cannot hold that much (maximum 9,999,999,999,999.99).'];
            }
        }

        // The three statements below are data-modifying CTEs: they all see the same snapshot, so
        // in from_upd the current amount is the OLD one (adjustment gets the real change), and in to_upd
        // the From line's amount is the one before it was lowered. The WHERE guards in `approved`
        // keep a double approval from applying twice and stop a transfer whose lines changed under us.
        $row = App\ExecuteRow(
            "WITH approved AS (
                 UPDATE {$this->table} rv
                 SET status_id = {$approvedId}, update_at = NOW()
                 WHERE rv.id = {$id} AND rv.deleted_at IS NULL AND rv.status_id = {$pendingId}
                   AND EXISTS (SELECT 1 FROM {$this->tableLines} x WHERE x.budget_line_item_id = rv.from_budget_line_item_id)
                   AND (SELECT rv.to_amount >= g.proposed_amount + g.adjustment
                               OR g.suggested_amount + (rv.to_amount - (g.proposed_amount + g.adjustment)) >= 0
                        FROM {$this->tableLines} g WHERE g.budget_line_item_id = rv.from_budget_line_item_id)
                   AND (rv.to_budget_line_item_id IS NULL
                        OR (rv.to_budget_line_item_id <> rv.from_budget_line_item_id
                            AND EXISTS (SELECT 1 FROM {$this->tableLines} t WHERE t.budget_line_item_id = rv.to_budget_line_item_id)
                            AND rv.to_amount < (SELECT f.proposed_amount + f.adjustment FROM {$this->tableLines} f
                                                WHERE f.budget_line_item_id = rv.from_budget_line_item_id)))
                 RETURNING rv.from_budget_line_item_id, rv.to_budget_line_item_id, rv.to_amount
             ),
             from_upd AS (
                 UPDATE {$this->tableLines} fl
                 SET adjustment       = fl.adjustment       + (approved.to_amount - (fl.proposed_amount + fl.adjustment)),
                     suggested_amount = fl.suggested_amount + (approved.to_amount - (fl.proposed_amount + fl.adjustment))
                 FROM approved
                 WHERE fl.budget_line_item_id = approved.from_budget_line_item_id
                 RETURNING fl.budget_line_item_id, fl.adjustment, fl.suggested_amount
             ),
             to_upd AS (
                 UPDATE {$this->tableLines} tl
                 SET adjustment       = tl.adjustment       + ((fr.proposed_amount + fr.adjustment) - approved.to_amount),
                     suggested_amount = tl.suggested_amount + ((fr.proposed_amount + fr.adjustment) - approved.to_amount)
                 FROM approved
                 JOIN {$this->tableLines} fr ON fr.budget_line_item_id = approved.from_budget_line_item_id
                 WHERE tl.budget_line_item_id = approved.to_budget_line_item_id
                 RETURNING tl.budget_line_item_id, tl.adjustment, tl.suggested_amount
             )
             SELECT f.budget_line_item_id AS from_id, f.adjustment AS from_adjustment, f.suggested_amount AS from_suggested,
                    t.budget_line_item_id AS to_id,   t.adjustment AS to_adjustment,   t.suggested_amount AS to_suggested
             FROM from_upd f
             LEFT JOIN to_upd t ON TRUE",
            'DB'
        );

        if (!$row) {
            return ['success' => false, 'message' => "Request #{$id} could not be approved — it was already reviewed by someone else, or its budget lines changed in the meantime."];
        }

        $result = [
            'success'     => true,
            'message'     => $toId === null
                ? "Budget revision request #{$id} approved and applied to the budget line."
                : "Budget revision request #{$id} approved: " . number_format($moved, 2) . ' moved from the From budget line to the To budget line.',
            'status'      => 'APPROVED',
            // the From line: its id, its new current amount, its new adjustment and suggested amount
            'budget_line' => ['budget_line_item_id' => (int) $row['from_id'], 'amount' => $newAmt, 'adjustment' => $row['from_adjustment'], 'suggested_amount' => $row['from_suggested']],
        ];
        if ($row['to_id'] !== null) {
            $result['to_budget_line'] = ['budget_line_item_id' => (int) $row['to_id'], 'amount' => round($toNow + $moved, 2), 'adjustment' => $row['to_adjustment'], 'suggested_amount' => $row['to_suggested']];
        }

        return $result;
    }

    // Soft delete — the table carries deleted_at, so the row is kept.
    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $sql = "UPDATE {$this->table}
                    SET deleted_at = NOW(), update_at = NOW()
                    WHERE id = " . (int) $id;
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Budget revision request #{$id} deleted successfully."];
        } catch (\Exception $e) {
            return $this->failure($e);
        }
    }
}
