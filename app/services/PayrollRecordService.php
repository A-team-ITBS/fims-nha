<?php
namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\Connection;

/**
 * PayrollRecordService — manages payroll records per employee per period,
 * including creation, deduction calculation, and DV linking.
 */
class PayrollRecordService
{
    private Connection $db;
    private PayrollDeductionService $deductionService;

    public function __construct(Connection $db, PayrollDeductionService $deductionService = null)
    {
        $this->db = $db;
        $this->deductionService = $deductionService;
    }

    /**
     * List payroll records for a period, optionally filtered
     */
    public function listRecordsByPeriod($periodId, $filters = [])
    {
        $qb = $this->db->createQueryBuilder()
            ->select('pr.*, pe.employee_no, pe.full_name, pe.position, pe.department')
            ->from('fims.payroll_records', 'pr')
            ->join('pr', 'fims.payroll_employees', 'pe', 'pr.employee_id = pe.employee_id')
            ->where('pr.payroll_period_id = :period_id')
            ->setParameter('period_id', $periodId)
            ->orderBy('pe.last_name', 'ASC');

        if (!empty($filters['status'])) {
            $qb->andWhere('pr.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['department'])) {
            $qb->andWhere('pe.department = :dept')
               ->setParameter('dept', $filters['department']);
        }

        return $qb->fetchAllAssociative();
    }

    /**
     * Get single payroll record with deductions
     */
    public function getRecord($recordId)
    {
        $record = $this->db->fetchAssociative(
            'SELECT pr.*, pe.employee_no, pe.full_name, pe.tin, pe.monthly_salary
             FROM fims.payroll_records pr
             JOIN fims.payroll_employees pe ON pr.employee_id = pe.employee_id
             WHERE pr.payroll_record_id = :id',
            ['id' => $recordId]
        );

        if ($record) {
            // Load deductions
            $record['deductions'] = $this->db->fetchAllAssociative(
                'SELECT pd.*, pdt.code, pdt.name
                 FROM fims.payroll_deductions pd
                 JOIN fims.payroll_deduction_types pdt ON pd.deduction_type_id = pdt.deduction_type_id
                 WHERE pd.payroll_record_id = :record_id
                 ORDER BY pdt.sort_order ASC',
                ['record_id' => $recordId]
            );
        }

        return $record;
    }

    /**
     * Create payroll record for an employee in a period
     */
    public function createRecord($periodId, $employeeId, $data, $userId)
    {
        $employee = $this->db->fetchAssociative(
            'SELECT * FROM fims.payroll_employees WHERE employee_id = :id',
            ['id' => $employeeId]
        );

        if (!$employee) {
            throw new \Exception('Employee not found.');
        }

        $record = [
            'payroll_period_id' => $periodId,
            'employee_id' => $employeeId,
            'base_salary' => floatval($employee['monthly_salary'] ?? $data['base_salary'] ?? 0),
            'days_worked' => floatval($data['days_worked'] ?? 0),
            'hours_worked' => floatval($data['hours_worked'] ?? 0),
            'regular_pay' => floatval($data['regular_pay'] ?? 0),
            'overtime_pay' => floatval($data['overtime_pay'] ?? 0),
            'bonus_pay' => floatval($data['bonus_pay'] ?? 0),
            'allowances' => floatval($data['allowances'] ?? 0),
            'other_income' => floatval($data['other_income'] ?? 0),
            'total_deductions' => 0,
            'status' => 'DRAFT',
            'created_by' => $userId,
            'updated_by' => $userId,
        ];

        $this->db->insert('fims.payroll_records', $record);
        return $this->db->lastInsertId('fims.payroll_records_payroll_record_id_seq');
    }

    /**
     * Update payroll record and recalculate totals
     */
    public function updateRecord($recordId, $data, $userId)
    {
        // Update header
        $updates = [
            'days_worked' => floatval($data['days_worked'] ?? 0),
            'hours_worked' => floatval($data['hours_worked'] ?? 0),
            'regular_pay' => floatval($data['regular_pay'] ?? 0),
            'overtime_pay' => floatval($data['overtime_pay'] ?? 0),
            'bonus_pay' => floatval($data['bonus_pay'] ?? 0),
            'allowances' => floatval($data['allowances'] ?? 0),
            'other_income' => floatval($data['other_income'] ?? 0),
            'updated_by' => $userId,
            'updated_at' => new \DateTime(),
        ];

        $this->db->update('fims.payroll_records', $updates, ['payroll_record_id' => $recordId]);

        // Recalculate deductions total
        $this->recalculateDeductionsTotal($recordId);
    }

    /**
     * Recalculate total deductions and net amount (denormalized for performance)
     */
    public function recalculateDeductionsTotal($recordId)
    {
        $result = $this->db->fetchAssociative(
            'SELECT SUM(calculated_amount) as total_deductions
             FROM fims.payroll_deductions
             WHERE payroll_record_id = :record_id',
            ['record_id' => $recordId]
        );

        $totalDeductions = floatval($result['total_deductions'] ?? 0);

        $this->db->update(
            'fims.payroll_records',
            ['total_deductions' => $totalDeductions],
            ['payroll_record_id' => $recordId]
        );
    }

    /**
     * Link payroll record to a disbursement voucher
     */
    public function linkToDisbursementVoucher($recordId, $dvId)
    {
        return $this->db->update(
            'fims.payroll_records',
            ['disbursement_voucher_id' => $dvId],
            ['payroll_record_id' => $recordId]
        );
    }

    /**
     * Generate standard deductions for a payroll record
     * (SSS, PhilHealth, Pag-IBIG, BIR tax based on salary)
     */
    public function applyStandardDeductions($recordId, $userId)
    {
        $record = $this->getRecord($recordId);
        if (!$record) {
            throw new \Exception('Payroll record not found.');
        }

        $grossAmount = floatval($record['gross_amount'] ?? 0);

        // Standard deductions (can be customized per employee/period)
        $deductions = [
            ['code' => 'SSS', 'rate' => 3.63],
            ['code' => 'PHILHEALTH', 'rate' => 2.25],
            ['code' => 'PAGIBIG', 'rate' => 1.00],
            ['code' => 'BIR_TAX', 'rate' => 0], // Will be calculated separately
        ];

        foreach ($deductions as $ded) {
            $type = $this->db->fetchAssociative(
                'SELECT deduction_type_id FROM fims.payroll_deduction_types WHERE code = :code',
                ['code' => $ded['code']]
            );

            if ($type) {
                $this->db->insert('fims.payroll_deductions', [
                    'payroll_record_id' => $recordId,
                    'deduction_type_id' => $type['deduction_type_id'],
                    'deduction_base' => $grossAmount,
                    'rate_percent' => $ded['rate'],
                    'created_by' => $userId,
                ]);
            }
        }

        // Recalculate totals
        $this->recalculateDeductionsTotal($recordId);
    }

    /**
     * Approve payroll record (move to APPROVED status)
     */
    public function approve($recordId, $userId)
    {
        return $this->db->update(
            'fims.payroll_records',
            [
                'status' => 'APPROVED',
                'approved_by' => $userId,
                'approved_at' => new \DateTime(),
            ],
            ['payroll_record_id' => $recordId]
        );
    }

    /**
     * Release payroll record (move to RELEASED status, prepare for payment)
     */
    public function release($recordId, $userId, $paymentMethod = null, $bankAccountId = null)
    {
        return $this->db->update(
            'fims.payroll_records',
            [
                'status' => 'RELEASED',
                'payment_method' => $paymentMethod,
                'bank_account_id' => $bankAccountId,
                'release_date' => new \DateTime(),
                'released_by' => $userId,
                'released_at' => new \DateTime(),
            ],
            ['payroll_record_id' => $recordId]
        );
    }

    /**
     * Get payroll summary for a period (total gross, deductions, net)
     */
    public function getPeriodSummary($periodId)
    {
        return $this->db->fetchAssociative(
            'SELECT
                COUNT(DISTINCT pr.employee_id) as employee_count,
                SUM(pr.gross_amount) as total_gross,
                SUM(pr.total_deductions) as total_deductions,
                SUM(pr.net_amount) as total_net
             FROM fims.payroll_records pr
             WHERE pr.payroll_period_id = :period_id',
            ['period_id' => $periodId]
        );
    }
}
