<?php
namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\Connection;

/**
 * PayrollEmployeeService — manages employee records for payroll processing,
 * including import from CSV files and HRIS integration endpoints.
 */
class PayrollEmployeeService
{
    private Connection $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Get all active employees, optionally filtered
     */
    public function listEmployees($filters = [])
    {
        $qb = $this->db->createQueryBuilder()
            ->select('*')
            ->from('fims.payroll_employees')
            ->where('employment_status = :status')
            ->setParameter('status', 'ACTIVE');

        if (!empty($filters['department'])) {
            $qb->andWhere('department = :dept')
               ->setParameter('dept', $filters['department']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $qb->andWhere('(full_name ILIKE :search OR employee_no ILIKE :search OR tin ILIKE :search)')
               ->setParameter('search', $search);
        }

        return $qb->fetchAllAssociative();
    }

    /**
     * Get employee by ID
     */
    public function getEmployee($employeeId)
    {
        return $this->db->fetchAssociative(
            'SELECT * FROM fims.payroll_employees WHERE employee_id = :id',
            ['id' => $employeeId]
        );
    }

    /**
     * Create or update employee record
     */
    public function saveEmployee($data)
    {
        $data['updated_at'] = new \DateTime();

        if (empty($data['employee_id'])) {
            // Insert
            $data['created_at'] = new \DateTime();
            return $this->db->insert('fims.payroll_employees', $data);
        } else {
            // Update
            $id = $data['employee_id'];
            unset($data['employee_id']);
            return $this->db->update('fims.payroll_employees', $data, ['employee_id' => $id]);
        }
    }

    /**
     * Import employees from CSV file
     * Expected columns: employee_no, first_name, last_name, middle_name, tin, sss_no,
     *                   position, department, monthly_salary, date_hired
     *
     * Returns: ['imported' => int, 'updated' => int, 'errors' => []]
     */
    public function importFromCsv($filePath, $userId)
    {
        $result = ['imported' => 0, 'updated' => 0, 'errors' => []];

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $result['errors'][] = 'File not found or not readable.';
            return $result;
        }

        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle); // Read header row
        $lineNo = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;
            if (empty(array_filter($row))) continue; // Skip empty rows

            try {
                $data = array_combine($headers, $row);

                // Validate required fields
                if (empty($data['employee_no']) || empty($data['first_name']) || empty($data['last_name'])) {
                    $result['errors'][] = "Line $lineNo: Missing required fields (employee_no, first_name, last_name).";
                    continue;
                }

                // Check if employee already exists
                $existing = $this->db->fetchOne(
                    'SELECT employee_id FROM fims.payroll_employees WHERE employee_no = :no',
                    ['no' => $data['employee_no']]
                );

                $record = [
                    'employee_no' => $data['employee_no'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'tin' => $data['tin'] ?? null,
                    'sss_no' => $data['sss_no'] ?? null,
                    'philhealth_no' => $data['philhealth_no'] ?? null,
                    'pagibig_no' => $data['pagibig_no'] ?? null,
                    'position' => $data['position'] ?? null,
                    'department' => $data['department'] ?? null,
                    'monthly_salary' => floatval($data['monthly_salary'] ?? 0),
                    'date_hired' => !empty($data['date_hired']) ? $data['date_hired'] : null,
                    'employment_status' => 'ACTIVE',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ];

                if ($existing) {
                    // Update
                    unset($record['created_by']);
                    $this->db->update('fims.payroll_employees', $record, ['employee_id' => $existing]);
                    $result['updated']++;
                } else {
                    // Insert
                    $this->db->insert('fims.payroll_employees', $record);
                    $result['imported']++;
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Line $lineNo: " . $e->getMessage();
            }
        }

        fclose($handle);
        return $result;
    }

    /**
     * Bulk deactivate employees
     */
    public function deactivateEmployees($employeeIds)
    {
        return $this->db->update(
            'fims.payroll_employees',
            ['employment_status' => 'SEPARATED'],
            ['employee_id' => $employeeIds],
            [\Doctrine\DBAL\ParameterType::INTEGER]
        );
    }
}
