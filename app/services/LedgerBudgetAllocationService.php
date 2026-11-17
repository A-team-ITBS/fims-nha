<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * Ledger of Budget Allocation — read-only ledger of the RFAs and LAAs (fims.budget_allocation rows with
 * allocation_type RFA / LAA), grouped under the allocation they draw against (parent_id, normally an
 * Approved CBA). Page: app/pages/ledger-budget-allocation.php. Route: app/routes/ledger_budget_allocation.php.
 *
 * Per group (parent):
 *   - ceiling        = the parent's requested total (sum of line_transaction requested_amount — the same figure its
 *                      earmark holds, see BudgetLineItemService::createForApproval())
 *   - each RFA / LAA = an entry; only an APPROVED one is "drawn" (that is when convertEarmarkForRfaApproval() takes it
 *                      off the CBA's earmark), the others are listed but deduct nothing
 *   - running balance = ceiling - approved draws so far, oldest first (created_at, allocation_id)
 *
 * The running balance always counts EVERY approved RFA / LAA of the parent, even ones the filters hide (search,
 * type, status), so a filtered view never shows a wrong balance. RFAs / LAAs without a parent are grouped
 * together with no ceiling.
 *
 * Disbursement Voucher: an RFA / LAA points at the one DV it is drawn into via budget_allocation.dv_id (see
 * DisbursementVoucherService::linkRfas()). One DV can cover several RFAs, so its net_amount is NOT a per-entry
 * amount — only its number, date and status are shown.
 */
class LedgerBudgetAllocationService
{
    private string $table = 'fims.budget_allocation';
    private string $tableStatus = 'fims.settings_status';
    private string $tableVendor = 'fims.vendors';
    private string $tableRc = 'fims.responsibility_code';
    private string $tableProgram = 'fims.program';
    private string $tableProject = 'fims.project';
    private string $tableDv = 'fims.disbursement_vouchers';

    private const TYPES = ['RFA', 'LAA'];
    private const STATUS_APPROVED = 'Approved';

    /**
     * Filters: fiscal_year, allocation_type (RFA / LAA, '' = both), status (a status name, e.g. Approved),
     * search (allocation no. / ref no. / description / vendor / parent's numbers), parent_id,
     * rc_id (set by the route from the logged-in user — never from the client).
     */
    public function fetchLedger(array $filters = []): array
    {
        try {
            $where = ['a.deleted_at IS NULL', "UPPER(a.allocation_type) IN ('RFA', 'LAA')"];

            if (($fy = $this->intFilter($filters, 'fiscal_year')) !== null) {
                $where[] = "a.fiscal_year = {$fy}";
            }
            $type = strtoupper(trim((string) ($filters['allocation_type'] ?? '')));
            if ($type !== '') {
                if (!in_array($type, self::TYPES, true)) {
                    return ['success' => false, 'message' => 'allocation_type must be RFA or LAA.', 'httpCode' => 400];
                }
                $where[] = 'UPPER(a.allocation_type) = ' . App\QuotedValue($type, DataType::STRING, 'DB');
            }
            $status = trim((string) ($filters['status'] ?? ''));
            if ($status !== '') {
                $where[] = 'LOWER(st.name) = LOWER(' . App\QuotedValue($status, DataType::STRING, 'DB') . ')';
            }
            if (($parentId = $this->intFilter($filters, 'parent_id')) !== null) {
                $where[] = "a.parent_id = {$parentId}";
            }
            $search = trim((string) ($filters['search'] ?? ''));
            if ($search !== '') {
                $s = App\QuotedValue('%' . $search . '%', DataType::STRING, 'DB');
                $where[] = "(a.allocation_number ILIKE {$s} OR a.request_referrence_no ILIKE {$s} OR a.description ILIKE {$s}
                             OR v.vendor_name ILIKE {$s} OR pa.allocation_number ILIKE {$s} OR pa.request_referrence_no ILIKE {$s}
                             OR dv.dv_no ILIKE {$s})";
            }
            $rcId = trim((string) ($filters['rc_id'] ?? ''));
            if ($rcId !== '') {
                $rcIds = GlobalService::getRcIdsInScope($rcId) ?: [$rcId];
                $where[] = 'a.requesting_rc_id IN (' . implode(', ', array_map(
                    fn ($id) => App\QuotedValue($id, DataType::STRING, 'DB'),
                    $rcIds
                )) . ')';
            }

            $rows = App\ExecuteRows(
                "SELECT a.allocation_id, a.allocation_type, a.allocation_number, a.request_referrence_no, a.document_type,
                        a.fiscal_year, a.description, a.parent_id, a.created_at,
                        {$this->requestedTotalSql('a')} AS amount,
                        st.name AS status_name, st.color AS status_color,
                        v.vendor_name,
                        rc.rc_code AS requesting_rc_code, rc.rc_name AS requesting_rc_name,
                        dv.dv_id, dv.dv_no, dv.dv_date, dvs.name AS dv_status
                 FROM {$this->table} a
                 LEFT JOIN {$this->tableStatus} st  ON st.id = a.status_id
                 LEFT JOIN {$this->tableVendor} v   ON v.vendor_id = a.vendor_id
                 LEFT JOIN {$this->tableRc} rc      ON rc.rc_id = a.requesting_rc_id
                 LEFT JOIN {$this->table} pa        ON pa.allocation_id = a.parent_id
                 LEFT JOIN {$this->tableDv} dv      ON dv.dv_id = a.dv_id
                 LEFT JOIN {$this->tableStatus} dvs ON dvs.id = dv.status_id
                 WHERE " . implode(' AND ', $where) . '
                 ORDER BY a.created_at, a.allocation_id',
                'DB'
            ) ?: [];

            $parentIds = array_values(array_unique(array_filter(array_map(
                fn ($r) => $r['parent_id'] !== null ? (int) $r['parent_id'] : null,
                $rows
            ))));
            $parents = $this->fetchParents($parentIds);
            $runningBalance = $this->runningBalances($parents);

            // Group: parent groups ordered by the parent's own creation, the "no parent" group last.
            $groups = [];
            foreach ($rows as $r) {
                $pid = $r['parent_id'] !== null ? (int) $r['parent_id'] : 0;
                if (!isset($groups[$pid])) {
                    $p = $parents[$pid] ?? null;
                    $groups[$pid] = [
                        'parent' => $p ? array_diff_key($p, ['_sort' => true]) : null,
                        'ceiling' => $p ? round((float) $p['amount'], 2) : null,
                        'total_drawn' => $p ? round((float) $p['total_drawn'], 2) : null,
                        'balance' => $p ? round((float) $p['amount'] - (float) $p['total_drawn'], 2) : null,
                        'entries' => [],
                    ];
                }
                $r['amount'] = round((float) $r['amount'], 2);
                $r['is_drawn'] = $this->isApproved($r['status_name']);
                $r['running_balance'] = $pid ? ($runningBalance[(int) $r['allocation_id']] ?? null) : null;
                $groups[$pid]['entries'][] = $r;
            }
            uksort($groups, function ($x, $y) use ($parents) {
                if ($x === 0 || $y === 0) {
                    return $x === 0 ? 1 : -1;
                }

                return ($parents[$x]['_sort'] ?? ['', $x]) <=> ($parents[$y]['_sort'] ?? ['', $y]);
            });

            $summary = ['entries' => count($rows), 'rfa_count' => 0, 'laa_count' => 0, 'total_amount' => 0.0, 'total_drawn' => 0.0, 'with_dv' => 0];
            foreach ($rows as $r) {
                $summary[strtoupper($r['allocation_type']) === 'RFA' ? 'rfa_count' : 'laa_count']++;
                $summary['total_amount'] += (float) $r['amount'];
                if ($this->isApproved($r['status_name'])) {
                    $summary['total_drawn'] += (float) $r['amount'];
                }
                if ($r['dv_id'] !== null) {
                    $summary['with_dv']++;
                }
            }
            $summary['total_amount'] = round($summary['total_amount'], 2);
            $summary['total_drawn'] = round($summary['total_drawn'], 2);

            return ['success' => true, 'data' => array_values($groups), 'summary' => $summary];
        } catch (\Exception $e) {
            App\LogError('LedgerBudgetAllocationService::fetchLedger ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load the Ledger of Budget Allocation.'];
        }
    }

    /**
     * The parent allocations (by id) with their ceiling (`amount`) and every approved RFA / LAA drawn against them,
     * oldest first (`draws`), unfiltered — the basis of the running balance.
     */
    private function fetchParents(array $parentIds): array
    {
        if (!$parentIds) {
            return [];
        }
        $in = implode(', ', array_map('intval', $parentIds));

        $parents = [];
        $rows = App\ExecuteRows(
            "SELECT p.allocation_id, p.allocation_type, p.allocation_number, p.request_referrence_no, p.fiscal_year,
                    p.description, p.program_code, pg.program_name, p.project_code, pj.project_name,
                    st.name AS status_name, p.created_at,
                    {$this->requestedTotalSql('p')} AS amount
             FROM {$this->table} p
             LEFT JOIN {$this->tableStatus} st  ON st.id = p.status_id
             LEFT JOIN {$this->tableProgram} pg ON pg.program_code = p.program_code
             LEFT JOIN {$this->tableProject} pj ON pj.project_code = p.project_code
             WHERE p.allocation_id IN ({$in})",
            'DB'
        ) ?: [];
        foreach ($rows as $p) {
            $p['_sort'] = [(string) $p['created_at'], (int) $p['allocation_id']];
            $p['total_drawn'] = 0.0;
            $p['draws'] = [];
            $parents[(int) $p['allocation_id']] = $p;
        }

        $draws = App\ExecuteRows(
            "SELECT a.allocation_id, a.parent_id, {$this->requestedTotalSql('a')} AS amount
             FROM {$this->table} a
             JOIN {$this->tableStatus} st ON st.id = a.status_id
             WHERE a.deleted_at IS NULL AND a.parent_id IN ({$in})
               AND UPPER(a.allocation_type) IN ('RFA', 'LAA')
               AND LOWER(st.name) = LOWER(" . App\QuotedValue(self::STATUS_APPROVED, DataType::STRING, 'DB') . ')
             ORDER BY a.created_at, a.allocation_id',
            'DB'
        ) ?: [];
        foreach ($draws as $d) {
            $pid = (int) $d['parent_id'];
            if (isset($parents[$pid])) {
                $parents[$pid]['draws'][] = ['allocation_id' => (int) $d['allocation_id'], 'amount' => (float) $d['amount']];
                $parents[$pid]['total_drawn'] += (float) $d['amount'];
            }
        }

        return $parents;
    }

    /**
     * allocation_id => the parent's balance right after that entry. An approved entry is the balance after its own
     * draw; any other entry shows the balance as of the last approved draw before it (it deducts nothing).
     */
    private function runningBalances(array $parents): array
    {
        $byId = [];
        foreach ($parents as $pid => $p) {
            $balance = (float) $p['amount'];
            foreach ($p['draws'] as $d) {
                $balance -= $d['amount'];
                $byId[$d['allocation_id']] = round($balance, 2);
            }
        }

        // Non-approved entries: balance after the approved draws created before them.
        $pending = App\ExecuteRows(
            $parents ? "SELECT a.allocation_id, a.parent_id, a.created_at FROM {$this->table} a
                        WHERE a.deleted_at IS NULL AND a.parent_id IN (" . implode(', ', array_map('intval', array_keys($parents))) . ")
                          AND UPPER(a.allocation_type) IN ('RFA', 'LAA')
                        ORDER BY a.parent_id, a.created_at, a.allocation_id" : 'SELECT 1 WHERE FALSE',
            'DB'
        ) ?: [];
        $last = [];
        foreach ($pending as $r) {
            $pid = (int) $r['parent_id'];
            $id = (int) $r['allocation_id'];
            if (array_key_exists($id, $byId)) {
                $last[$pid] = $byId[$id];
            } else {
                $byId[$id] = $last[$pid] ?? round((float) $parents[$pid]['amount'], 2);
            }
        }

        return $byId;
    }

    private function isApproved(?string $statusName): bool
    {
        return $statusName !== null && strcasecmp(trim($statusName), self::STATUS_APPROVED) === 0;
    }

    /**
     * Sum of "requested_amount" over line_transaction for the row aliased $alias — same tolerant rules as
     * BudgetAllocationService::requestedTotalSql() (missing / non-numeric = 0, commas ignored). $alias is a
     * constant from this class, never user input.
     */
    private function requestedTotalSql(string $alias): string
    {
        return "COALESCE((
                    SELECT SUM(CASE
                                 WHEN jsonb_typeof(ln.value->'requested_amount') = 'number' THEN (ln.value->>'requested_amount')::numeric
                                 WHEN jsonb_typeof(ln.value->'requested_amount') = 'string'
                                      AND REPLACE(ln.value->>'requested_amount', ',', '') ~ '^-?[0-9]+([.][0-9]+)?$'
                                   THEN REPLACE(ln.value->>'requested_amount', ',', '')::numeric
                                 ELSE 0
                               END)
                    FROM jsonb_array_elements(CASE jsonb_typeof({$alias}.line_transaction)
                                                WHEN 'array'  THEN {$alias}.line_transaction
                                                WHEN 'object' THEN jsonb_build_array({$alias}.line_transaction)
                                                ELSE '[]'::jsonb
                                              END) AS ln(value)
                    WHERE jsonb_typeof(ln.value) = 'object'
                ), 0)";
    }

    private function intFilter(array $filters, string $key): ?int
    {
        $v = $filters[$key] ?? null;

        return ($v !== null && $v !== '' && is_numeric($v)) ? (int) $v : null;
    }
}
