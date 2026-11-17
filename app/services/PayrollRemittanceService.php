<?php
namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\Connection;

/**
 * PayrollRemittanceService — manages remittance of payroll deductions to
 * government agencies (BIR, SSS, PhilHealth, Pag-IBIG) and tracks payment status.
 */
class PayrollRemittanceService
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * List remittances for a period
     */
    public function listRemittancesByPeriod($periodId, $filters = [])
    {
        $qb = $this->db->createQueryBuilder()
            ->select('*')
            ->from('fims.payroll_remittances')
            ->where('payroll_period_id = :period_id')
            ->setParameter('period_id', $periodId)
            ->orderBy('remittance_agency', 'ASC');

        if (!empty($filters['status'])) {
            $qb->andWhere('remittance_status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['agency'])) {
            $qb->andWhere('remittance_agency = :agency')
               ->setParameter('agency', $filters['agency']);
        }

        return $qb->fetchAllAssociative();
    }

    /**
     * Get remittance record
     */
    public function getRemittance($remittanceId)
    {
        return $this->db->fetchAssociative(
            'SELECT * FROM fims.payroll_remittances WHERE payroll_remittance_id = :id',
            ['id' => $remittanceId]
        );
    }

    /**
     * Create remittance record(s) for a payroll period
     * If $consolidated = true, creates one record per agency; otherwise, detail per deduction type
     */
    public function createRemittanceForPeriod($periodId, $userId, $consolidated = true)
    {
        $created = [];

        if ($consolidated) {
            // Consolidated: one remittance per agency per period
            $agencies = $this->db->fetchAllAssociative(
                'SELECT DISTINCT remittance_agency
                 FROM fims.payroll_deduction_types pdt
                 WHERE remittance_agency IS NOT NULL
                 AND status = :status
                 ORDER BY remittance_agency',
                ['status' => 'ACTIVE']
            );

            foreach ($agencies as $agencyRow) {
                $agency = $agencyRow['remittance_agency'];

                // Sum all deductions for this agency in this period
                $total = $this->db->fetchAssociative(
                    'SELECT SUM(pd.calculated_amount) as total_amount
                     FROM fims.payroll_deductions pd
                     JOIN fims.payroll_records pr ON pd.payroll_record_id = pr.payroll_record_id
                     JOIN fims.payroll_deduction_types pdt ON pd.deduction_type_id = pdt.deduction_type_id
                     WHERE pr.payroll_period_id = :period_id
                     AND pdt.remittance_agency = :agency',
                    ['period_id' => $periodId, 'agency' => $agency]
                );

                $amount = floatval($total['total_amount'] ?? 0);

                if ($amount > 0) {
                    $remittance = [
                        'payroll_period_id' => $periodId,
                        'remittance_agency' => $agency,
                        'deduction_type_id' => null, // Consolidated
                        'total_amount' => $amount,
                        'remittance_status' => 'PENDING',
                        'is_consolidated' => true,
                        'created_by' => $userId,
                    ];

                    $this->db->insert('fims.payroll_remittances', $remittance);
                    $created[] = $this->db->lastInsertId('fims.payroll_remittances_payroll_remittance_id_seq');
                }
            }
        } else {
            // Detail: one remittance per deduction type per period
            $deductions = $this->db->fetchAllAssociative(
                'SELECT
                    pdt.deduction_type_id, pdt.code, pdt.name, pdt.remittance_agency,
                    SUM(pd.calculated_amount) as total_amount
                 FROM fims.payroll_deductions pd
                 JOIN fims.payroll_records pr ON pd.payroll_record_id = pr.payroll_record_id
                 JOIN fims.payroll_deduction_types pdt ON pd.deduction_type_id = pdt.deduction_type_id
                 WHERE pr.payroll_period_id = :period_id
                 AND pdt.remittance_agency IS NOT NULL
                 GROUP BY pdt.deduction_type_id, pdt.code, pdt.name, pdt.remittance_agency',
                ['period_id' => $periodId]
            );

            foreach ($deductions as $row) {
                $remittance = [
                    'payroll_period_id' => $periodId,
                    'remittance_agency' => $row['remittance_agency'],
                    'deduction_type_id' => $row['deduction_type_id'],
                    'total_amount' => floatval($row['total_amount'] ?? 0),
                    'remittance_status' => 'PENDING',
                    'is_consolidated' => false,
                    'created_by' => $userId,
                ];

                $this->db->insert('fims.payroll_remittances', $remittance);
                $created[] = $this->db->lastInsertId('fims.payroll_remittances_payroll_remittance_id_seq');
            }
        }

        return $created;
    }

    /**
     * Mark remittance as submitted
     */
    public function markSubmitted($remittanceId, $remittanceNo, $userId)
    {
        return $this->db->update(
            'fims.payroll_remittances',
            [
                'remittance_status' => 'SUBMITTED',
                'remittance_no' => $remittanceNo,
                'remittance_date' => new \DateTime(),
                'updated_by' => $userId,
                'updated_at' => new \DateTime(),
            ],
            ['payroll_remittance_id' => $remittanceId]
        );
    }

    /**
     * Confirm remittance receipt from agency
     */
    public function confirmReceipt($remittanceId, $receiptNo, $receiptDate, $userId)
    {
        return $this->db->update(
            'fims.payroll_remittances',
            [
                'remittance_status' => 'CONFIRMED',
                'remittance_receipt_no' => $receiptNo,
                'receipt_date' => $receiptDate,
                'updated_by' => $userId,
                'updated_at' => new \DateTime(),
            ],
            ['payroll_remittance_id' => $remittanceId]
        );
    }

    /**
     * Link remittance to a disbursement voucher (payment for remittance)
     */
    public function linkToDisbursementVoucher($remittanceId, $dvId)
    {
        return $this->db->update(
            'fims.payroll_remittances',
            ['disbursement_voucher_id' => $dvId],
            ['payroll_remittance_id' => $remittanceId]
        );
    }

    /**
     * Get remittance summary by agency (for dashboard)
     */
    public function getRemittanceSummary($periodId = null)
    {
        $qb = $this->db->createQueryBuilder()
            ->select('
                remittance_agency,
                remittance_status,
                COUNT(*) as count,
                SUM(total_amount) as total_amount
            ')
            ->from('fims.payroll_remittances')
            ->groupBy('remittance_agency', 'remittance_status')
            ->orderBy('remittance_agency', 'ASC');

        if ($periodId) {
            $qb->where('payroll_period_id = :period_id')
               ->setParameter('period_id', $periodId);
        }

        return $qb->fetchAllAssociative();
    }

    /**
     * Get year-to-date remittances for an agency (for compliance/audit trail)
     */
    public function getYtdRemittances($agency, $fiscalYear)
    {
        return $this->db->fetchAllAssociative(
            'SELECT pr.*, pp.period_name
             FROM fims.payroll_remittances pr
             JOIN fims.payroll_periods pp ON pr.payroll_period_id = pp.payroll_period_id
             WHERE pr.remittance_agency = :agency
             AND pp.fiscal_yr = :fiscal_yr
             ORDER BY pp.payroll_month ASC',
            ['agency' => $agency, 'fiscal_yr' => $fiscalYear]
        );
    }

    /**
     * Generate remittance report (for BIR filing, SSS, etc.)
     * Returns employees with their deductions grouped by category
     */
    public function getRemittanceReport($periodId, $agency)
    {
        return $this->db->fetchAllAssociative(
            'SELECT
                pe.employee_no, pe.full_name, pe.tin,
                pdt.code, pdt.name,
                pd.calculated_amount,
                pd.ytd_deductions
             FROM fims.payroll_deductions pd
             JOIN fims.payroll_records pr ON pd.payroll_record_id = pr.payroll_record_id
             JOIN fims.payroll_employees pe ON pr.employee_id = pe.employee_id
             JOIN fims.payroll_deduction_types pdt ON pd.deduction_type_id = pdt.deduction_type_id
             WHERE pr.payroll_period_id = :period_id
             AND pdt.remittance_agency = :agency
             ORDER BY pe.last_name, pe.first_name, pdt.sort_order',
            ['period_id' => $periodId, 'agency' => $agency]
        );
    }
}
