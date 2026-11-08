<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * One line item = one chart-of-accounts allocation under a Project "line
 * item" (fims.budget_req_project), which itself belongs to a budget_req
 * header. Used both to add individual line items and to edit/delete one
 * in isolation.
 */
class BudgetLineItemService
{
    private string $table                 = 'fims.budget_line_items';
    private string $tableBudgetReq        = 'fims.budget_req';
    private string $tableBudgetReqProject = 'fims.budget_req_project';
    private string $tableChartOfAccounts  = 'fims.chart_of_accounts';
    private string $tableProject          = 'fims.project';

    public function fetchAlls(?int $page = null, int $limit = 10, string $fiscalYr = ''): array
    {
        try {
            $where = ['1=1'];
            if ($fiscalYr !== '') {
                $where[] = 'br.fiscal_yr = ' . (int) $fiscalYr;
            }
            $whereSql = implode(' AND ', $where);

            $joinSql = "SELECT li.*, br.fiscal_yr,
                               brp.project_code, pj.project_name,
                               coa.account_code, coa.account_title,
                               st.name AS status_name, st.color AS status_color
                        FROM {$this->table} li
                        JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                        LEFT JOIN {$this->tableBudgetReqProject} brp ON brp.budget_req_project_id = li.budget_req_project_id
                        LEFT JOIN {$this->tableProject} pj ON pj.project_code = brp.project_code
                        LEFT JOIN {$this->tableChartOfAccounts} coa ON coa.account_id = li.account_id
                        LEFT JOIN fims.settings_status st ON st.id = br.status_id
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql . ' ORDER BY br.fiscal_yr DESC, li.budget_line_item_id DESC', 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $countSql = "SELECT COUNT(*) FROM {$this->table} li
                         JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                         WHERE {$whereSql}";
            $total = (int) App\ExecuteScalar($countSql, 'DB');
            $data  = App\ExecuteRows($joinSql . " ORDER BY br.fiscal_yr DESC, li.budget_line_item_id DESC LIMIT {$limit} OFFSET {$offset}", 'DB');

            $sumSql = "SELECT COALESCE(SUM(li.proposed_amount), 0) FROM {$this->table} li
                       JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                       WHERE {$whereSql}";
            $totalAmount = (float) App\ExecuteScalar($sumSql, 'DB');

            return [
                'success'      => true,
                'data'         => $data ?: [],
                'total_amount' => $totalAmount,
                'pagination'   => [
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

    public function fetchById(int|string $id): array
    {
        try {
            $sql = "SELECT li.*, br.fiscal_yr,
                           brp.project_code, pj.project_name,
                           coa.account_code, coa.account_title,
                           st.name AS status_name, st.color AS status_color
                    FROM {$this->table} li
                    JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                    LEFT JOIN {$this->tableBudgetReqProject} brp ON brp.budget_req_project_id = li.budget_req_project_id
                    LEFT JOIN {$this->tableProject} pj ON pj.project_code = brp.project_code
                    LEFT JOIN {$this->tableChartOfAccounts} coa ON coa.account_id = li.account_id
                    LEFT JOIN fims.settings_status st ON st.id = br.status_id
                    WHERE li.budget_line_item_id = " . (int) $id;

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Line item #{$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param array $body budget_reference_number, budget_req_project_id, account_id, proposed_amount, particulars
     */
    public function create(array $body): array
    {
        try {
            $budgetReferenceNumber = trim((string) ($body['budget_reference_number'] ?? ''));
            $budgetReqProjectId    = trim((string) ($body['budget_req_project_id'] ?? ''));
            $accountId             = trim((string) ($body['account_id'] ?? ''));
            $amount                = $body['proposed_amount'] ?? $body['amount'] ?? '';
            $particulars           = trim((string) ($body['particulars'] ?? ''));

            if ($budgetReferenceNumber === '' || $budgetReqProjectId === '' || $accountId === '' || $amount === '' || !is_numeric($amount) || $particulars === '') {
                return ['success' => false, 'message' => 'budget_reference_number, budget_req_project_id, account_id, a numeric proposed_amount, and particulars are required.'];
            }

            $header = App\ExecuteRow(
                "SELECT budget_reference_number FROM {$this->tableBudgetReq} WHERE budget_reference_number = " . (int) $budgetReferenceNumber,
                'DB'
            );
            if (!$header) {
                return ['success' => false, 'message' => "Budget request #{$budgetReferenceNumber} does not exist."];
            }

            $project = App\ExecuteRow(
                "SELECT budget_req_project_id FROM {$this->tableBudgetReqProject} WHERE budget_req_project_id = " . (int) $budgetReqProjectId,
                'DB'
            );
            if (!$project) {
                return ['success' => false, 'message' => "Project line item #{$budgetReqProjectId} does not exist."];
            }

            $account = App\ExecuteRow(
                "SELECT account_id FROM {$this->tableChartOfAccounts}
                 WHERE account_id = " . App\QuotedValue($accountId, DataType::STRING, 'DB') . "
                   AND status = 'Active' AND posting_allowed = TRUE",
                'DB'
            );
            if (!$account) {
                return ['success' => false, 'message' => "Chart of accounts entry '{$accountId}' does not exist or is not postable."];
            }

            $sql = "INSERT INTO {$this->table} (budget_reference_number, budget_req_project_id, account_id, proposed_amount, particulars)
                    VALUES (" . (int) $budgetReferenceNumber . ', '
                        . (int) $budgetReqProjectId . ', '
                        . App\QuotedValue($accountId, DataType::STRING, 'DB') . ', '
                        . App\QuotedValue($amount, DataType::NUMBER, 'DB') . ', '
                        . App\QuotedValue($particulars, DataType::STRING, 'DB') . ')
                    RETURNING budget_line_item_id';

            $row = App\ExecuteRow($sql, 'DB');

            return [
                'success'             => true,
                'message'             => 'Line item created successfully.',
                'budget_line_item_id' => $row['budget_line_item_id'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update(int|string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $accountId   = trim((string) ($body['account_id'] ?? ''));
            $amount      = $body['proposed_amount'] ?? $body['amount'] ?? null;
            $particulars = trim((string) ($body['particulars'] ?? ''));

            $setParts = [];

            if ($accountId !== '') {
                $account = App\ExecuteRow(
                    "SELECT account_id FROM {$this->tableChartOfAccounts}
                     WHERE account_id = " . App\QuotedValue($accountId, DataType::STRING, 'DB') . "
                       AND status = 'Active' AND posting_allowed = TRUE",
                    'DB'
                );
                if (!$account) {
                    return ['success' => false, 'message' => "Chart of accounts entry '{$accountId}' does not exist or is not postable."];
                }
                $setParts[] = 'account_id = ' . App\QuotedValue($accountId, DataType::STRING, 'DB');
            }
            if ($amount !== null && $amount !== '' && is_numeric($amount)) {
                $setParts[] = 'proposed_amount = ' . App\QuotedValue($amount, DataType::NUMBER, 'DB');
            }
            if ($particulars !== '') {
                $setParts[] = 'particulars = ' . App\QuotedValue($particulars, DataType::STRING, 'DB');
            }

            if (empty($setParts)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            App\Execute(
                "UPDATE {$this->table} SET " . implode(', ', $setParts) . ' WHERE budget_line_item_id = ' . (int) $id,
                'DB'
            );

            return ['success' => true, 'message' => 'Line item updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            App\Execute("DELETE FROM {$this->table} WHERE budget_line_item_id = " . (int) $id, 'DB');

            return ['success' => true, 'message' => "Line item #{$id} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
