<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

class ChartOfAccountsService
{
    private string $table = 'fims.chart_of_accounts';

    public function fetchAlls(?int $page = null, int $limit = 10, string $search = ''): array
    {
        try {
            $where = "status = 'Active'";
            if ($search !== '') {
                $s      = App\QuotedValue('%' . $search . '%', DataType::STRING, 'DB');
                $where .= " AND (account_code ILIKE {$s} OR account_title ILIKE {$s})";
            }

            if ($page === null) {
                $sql  = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY account_code ASC";
                $data = App\ExecuteRows($sql, 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", 'DB');
            $sql   = "SELECT * FROM {$this->table}
                      WHERE {$where}
                      ORDER BY account_code ASC
                      LIMIT {$limit} OFFSET {$offset}";
            $data  = App\ExecuteRows($sql, 'DB');

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

    /**
     * Postable accounts under a chart-of-accounts code prefix — e.g. '5-01' for
     * Personnel Services, '5-02' for Maintenance and Other Operating Expenses.
     * Used to populate the account picker on the Budget Request form once the
     * expense class resolves to a COA-driven allocation instead of a program/project.
     */
    public function fetchByPrefix(string $prefix): array
    {
        try {
            $p    = App\QuotedValue($prefix, DataType::STRING, 'DB');
            $pDash = App\QuotedValue($prefix . '-%', DataType::STRING, 'DB');

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

    /**
     * All active, postable chart-of-accounts entries — used by the Budget
     * Request form's Account Code picker, which no longer restricts by
     * expense-class-derived prefix.
     */
    public function fetchPostable(string $search = ''): array
    {
        try {
            $where = "status = 'Active' AND posting_allowed = TRUE";
            if ($search !== '') {
                $s      = App\QuotedValue('%' . $search . '%', DataType::STRING, 'DB');
                $where .= " AND (account_code ILIKE {$s} OR account_title ILIKE {$s})";
            }

            $sql  = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY account_code ASC";
            $data = App\ExecuteRows($sql, 'DB');

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchById(string $id): array
    {
        try {
            $sql  = "SELECT * FROM {$this->table} WHERE account_id = " . App\QuotedValue($id, DataType::STRING, 'DB');
            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Chart of accounts entry '{$id}' not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
