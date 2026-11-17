<?php
namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\Connection;

/**
 * PayrollDeductionService — manages individual deductions per payroll record,
 * including calculation, deduction type management, and tax annualization tracking.
 */
class PayrollDeductionService
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Get all deduction types (active only, by default)
     */
    public function listDeductionTypes($activeOnly = true)
    {
        $qb = $this->db->createQueryBuilder()
            ->select('*')
            ->from('fims.payroll_deduction_types')
            ->orderBy('sort_order', 'ASC')
            ->addOrderBy('name', 'ASC');

        if ($activeOnly) {
            $qb->where('status = :status')
               ->setParameter('status', 'ACTIVE');
        }

        return $qb->fetchAllAssociative();
    }

    /**
     * Get deduction type by code
     */
    public function getDeductionType($code)
    {
        return $this->db->fetchAssociative(
            'SELECT * FROM fims.payroll_deduction_types WHERE code = :code',
            ['code' => $code]
        );
    }

    /**
     * Get deductions for a payroll record
     */
    public function getDeductionsByRecord($recordId)
    {
        return $this->db->fetchAllAssociative(
            'SELECT pd.*, pdt.code, pdt.name, pdt.category, pdt.remittance_agency
             FROM fims.payroll_deductions pd
             JOIN fims.payroll_deduction_types pdt ON pd.deduction_type_id = pdt.deduction_type_id
             WHERE pd.payroll_record_id = :record_id
             ORDER BY pdt.sort_order, pdt.name',
            ['record_id' => $recordId]
        );
    }

    /**
     * Add deduction to payroll record
     */
    public function addDeduction($recordId, $deductionTypeId, $data, $userId)
    {
        $deductionType = $this->db->fetchAssociative(
            'SELECT * FROM fims.payroll_deduction_types WHERE deduction_type_id = :id',
            ['id' => $deductionTypeId]
        );

        if (!$deductionType) {
            throw new \Exception('Deduction type not found.');
        }

        $deduction = [
            'payroll_record_id' => $recordId,
            'deduction_type_id' => $deductionTypeId,
            'deduction_base' => floatval($data['deduction_base'] ?? 0),
            'rate_percent' => floatval($data['rate_percent'] ?? $deductionType['default_rate'] ?? 0),
            'fixed_amount' => floatval($data['fixed_amount'] ?? 0),
            'maximum_base' => !empty($data['maximum_base']) ? floatval($data['maximum_base']) : null,
            'reference_no' => $data['reference_no'] ?? null,
            'reference_date' => !empty($data['reference_date']) ? $data['reference_date'] : null,
            'is_withheld_tax' => $deductionType['category'] === 'TAX',
            'atc_code' => $deductionType['atc_code'] ?? null,
            'principal_amount' => !empty($data['principal_amount']) ? floatval($data['principal_amount']) : null,
            'balance_amount' => !empty($data['balance_amount']) ? floatval($data['balance_amount']) : null,
            'monthly_amortization' => !empty($data['monthly_amortization']) ? floatval($data['monthly_amortization']) : null,
            'remarks' => $data['remarks'] ?? null,
            'created_by' => $userId,
        ];

        $this->db->insert('fims.payroll_deductions', $deduction);
        return $this->db->lastInsertId('fims.payroll_deductions_payroll_deduction_id_seq');
    }

    /**
     * Update deduction
     */
    public function updateDeduction($deductionId, $data, $userId)
    {
        $updates = [
            'deduction_base' => floatval($data['deduction_base'] ?? 0),
            'rate_percent' => floatval($data['rate_percent'] ?? 0),
            'fixed_amount' => floatval($data['fixed_amount'] ?? 0),
            'maximum_base' => !empty($data['maximum_base']) ? floatval($data['maximum_base']) : null,
            'reference_no' => $data['reference_no'] ?? null,
            'reference_date' => !empty($data['reference_date']) ? $data['reference_date'] : null,
            'remarks' => $data['remarks'] ?? null,
            'updated_by' => $userId,
            'updated_at' => new \DateTime(),
        ];

        return $this->db->update('fims.payroll_deductions', $updates, ['payroll_deduction_id' => $deductionId]);
    }

    /**
     * Remove deduction from payroll record
     */
    public function removeDeduction($deductionId)
    {
        return $this->db->delete('fims.payroll_deductions', ['payroll_deduction_id' => $deductionId]);
    }

    /**
     * Calculate BIR tax withholding based on gross and tax regime
     * (Simplified: standard deduction method. In practice, use actual BIR tax tables per marital status/dependents)
     */
    public function calculateBirTax($grossAmount, $taxRegime = 'STANDARD')
    {
        // This is a placeholder. Implement actual BIR tax calculation:
        // - For standard deduction (most common): 8% of excess over ₱250,000 annual
        // - For itemized deductions: 32% top marginal rate on taxable income
        // - Adjust for family status, dependent count, etc.
        // For now, return 0 (user must manually enter)
        return 0;
    }

    /**
     * Get withholding tax deductions for a period (for remittance tracking)
     */
    public function getTaxDeductionsByPeriod($periodId)
    {
        return $this->db->fetchAllAssociative(
            'SELECT
                pd.*, pr.employee_id, pe.full_name, pe.tin,
                pdt.code, pdt.name, pdt.atc_code, pdt.remittance_agency
             FROM fims.payroll_deductions pd
             JOIN fims.payroll_records pr ON pd.payroll_record_id = pr.payroll_record_id
             JOIN fims.payroll_employees pe ON pr.employee_id = pe.employee_id
             JOIN fims.payroll_deduction_types pdt ON pd.deduction_type_id = pdt.deduction_type_id
             WHERE pr.payroll_period_id = :period_id
             AND pd.is_withheld_tax = TRUE
             ORDER BY pe.last_name, pdt.sort_order',
            ['period_id' => $periodId]
        );
    }

    /**
     * Get deductions by category for a period (for remittance consolidation)
     */
    public function getDeductionsByCategory($periodId, $category)
    {
        return $this->db->fetchAllAssociative(
            'SELECT
                pdt.code, pdt.name, pdt.remittance_agency,
                SUM(pd.calculated_amount) as total_amount,
                COUNT(DISTINCT pr.employee_id) as employee_count
             FROM fims.payroll_deductions pd
             JOIN fims.payroll_records pr ON pd.payroll_record_id = pr.payroll_record_id
             JOIN fims.payroll_deduction_types pdt ON pd.deduction_type_id = pdt.deduction_type_id
             WHERE pr.payroll_period_id = :period_id
             AND pdt.category = :category
             GROUP BY pdt.code, pdt.name, pdt.remittance_agency',
            ['period_id' => $periodId, 'category' => $category]
        );
    }

    /**
     * Update YTD deductions for tax annualization
     * Called at each payroll period to track cumulative tax for annual reconciliation
     */
    public function updateYtdDeductions($employeeId, $fiscalYear, $userId)
    {
        // Sum all tax deductions for the year up to current period
        $ytd = $this->db->fetchAllAssociative(
            'SELECT
                pd.deduction_type_id,
                SUM(pd.calculated_amount) as total_ytd
             FROM fims.payroll_deductions pd
             JOIN fims.payroll_records pr ON pd.payroll_record_id = pr.payroll_record_id
             WHERE pr.employee_id = :employee_id
             AND pr.payroll_period_id IN (
                 SELECT payroll_period_id FROM fims.payroll_periods
                 WHERE fiscal_yr = :fiscal_yr AND payroll_month < 13
             )
             AND pd.is_withheld_tax = TRUE
             GROUP BY pd.deduction_type_id',
            ['employee_id' => $employeeId, 'fiscal_yr' => $fiscalYear]
        );

        foreach ($ytd as $row) {
            $this->db->update(
                'fims.payroll_deductions',
                ['ytd_deductions' => floatval($row['total_ytd'])],
                ['deduction_type_id' => $row['deduction_type_id']]
            );
        }
    }
}
