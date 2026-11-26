<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * BIR Form 2316 — Certificate of Compensation Payment / Tax Withheld (September 2021 ENCS), for ONE employee and fiscal
 * year, from the stored tax annualization (fims.payroll_annualization — Payroll Management → Annualization,
 * PayrollManagementService::annualize()). PDF only: _shared/template/bir_2316.pdf (the old ACCOUNTING template, its
 * AcroForm fields named by hand) filled with pdftk (PrintService::fillPdfForm()).
 *
 * Like the old ACCOUNTING 2316 window (income-tax-withheld.php), the print preview first shows an Edit Form prefilled by
 * prefill() (GET /print/prefill/bir2316?year=&employee_id=):
 *   editable — 2 period; Part I employee TIN, RDO, registered / home / foreign address + ZIP, birth date, contact no.,
 *              minimum wage earner (and its rates); Part III previous employer (TIN, name, address, ZIP, its taxable
 *              compensation and tax withheld); the signatories and dates; the employee's CTC / valid ID;
 *   locked   — 1 year, 5 the employee's name, Part II the employer (NHA, fims.app_settings with Bir2307PrintService's
 *              fallback values), and every amount (19–52) — from the annualization: correct the payroll and annualize again.
 * The edits travel in the payload (EDITABLE keys) and are not saved.
 *
 * Amounts (as the old PayrollService::getForm2316Data()): 19 gross, 20 non-taxable, 21 taxable (present employer);
 * 22 previous employer's taxable; 23 = 21 + 22; 24 tax due on 23 (TRAIN table); 25A withheld here, 25B by the previous
 * employer; 26 = 24 (the year's tax as adjusted — the December adjustment settles the difference); 27 PERA tax credit (0);
 * 28 = 26 + 27. Non-taxable (34–38): 13th month & other benefits up to ₱90,000, de minimis, contributions, other
 * non-taxable (PERA), their total. Taxable (39–52): 39 taxable pay less the contributions, 48 benefits over ₱90,000,
 * 52 = 21 (RA / TA aren't kept apart from basic pay by the payroll import, so 40 / 41 stay blank — in 39).
 */
class Bir2316PrintService
{
    private const TEMPLATE = 'bir_2316.pdf';

    public const EDITABLE = [
        'period_from', 'period_to', 'employee_tin', 'rdo_code', 'employee_address', 'employee_zip', 'employee_home_address',
        'employee_home_zip', 'employee_foreign_address', 'date_of_birth', 'contact_number', 'is_mwe', 'mwe_rate_day', 'mwe_rate_month',
        'prev_employer_tin', 'prev_employer_name', 'prev_employer_address', 'prev_employer_zip', 'taxable_previous', 'tax_withheld_previous',
        'employer_signatory', 'date_signed', 'employee_signatory', 'date_signed_2', 'ctc_no', 'ctc_place', 'ctc_date', 'ctc_amount',
    ];

    /** The Edit Form's data: { editable: {…EDITABLE}, locked: {year, employee_name, employer_*, amounts {…}} }. */
    public function prefill(int $year, int $employeeId): array
    {
        [$a, $set] = $this->load($year, $employeeId);
        $ed = self::editableDefaults($a, $set);

        return [
            'editable' => $ed,
            'locked'   => [
                'year'             => $year,
                'employee_no'      => $a['employee_no'],
                'employee_name'    => self::employeeName($a),
                'employer_tin'     => $set['bir_payor_tin'],
                'employer_name'    => $set['bir_payor_name'],
                'employer_address' => $set['bir_payor_address'],
                'employer_zip'     => $set['bir_payor_zip'],
                'amounts'          => self::amounts($a, $ed),
            ],
        ];
    }

    /** The form's field values. Payload: { fiscal_yr, employee_id } + optionally the EDITABLE values. */
    public function model(array $p): array
    {
        $year = (int) ($p['fiscal_yr'] ?? 0);
        $emp  = (int) ($p['employee_id'] ?? 0);
        [$a, $set] = $this->load($year, $emp);
        $def = self::editableDefaults($a, $set);
        $v   = [];
        foreach (self::EDITABLE as $k) {   // an edited value (blank included) wins over the prefilled one
            $v[$k] = array_key_exists($k, $p) && $p[$k] !== null ? (is_bool($p[$k]) ? $p[$k] : PrintService::text($p[$k])) : $def[$k];
        }
        $m = self::amounts($a, $v);

        $s     = static fn (string $k) => trim((string) ($v[$k] ?? ''));
        $fmt   = static fn ($x) => (float) $x != 0.0 ? number_format((float) $x, 2) : '';
        $tin   = static function (string $t): array {   // 3-3-3 + a 4-box branch code
            $t = preg_replace('/\D/', '', $t);

            return [substr($t, 0, 3), substr($t, 3, 3), substr($t, 6, 3), strlen($t) > 9 ? str_pad(substr($t, 9, 4), 4, '0') : ''];
        };
        $date8 = static fn (string $k) => ($x = strtotime($s($k))) ? date('mdY', $x) : '';
        $mmdd  = static fn (string $k) => substr(preg_replace('/\D/', '', $s($k)), 0, 4);   // MMDD boxes
        [$e1, $e2, $e3, $e4] = $tin($s('employee_tin'));
        [$r1, $r2, $r3, $r4] = $tin($set['bir_payor_tin']);
        [$p1, $p2, $p3, $p4] = $tin($s('prev_employer_tin'));
        $mwe = in_array($v['is_mwe'], [true, 1, '1', 'true', 'on', 'yes'], true);

        $fields = [
            'FTY_1' => (string) $year, 'FP_MM_DD' => $mmdd('period_from') ?: '0101', 'TO_MM_DD' => $mmdd('period_to') ?: '1231',
            // Part I — employee (3–11)
            'TIN_1' => $e1, 'TIN_2' => $e2, 'TIN_3' => $e3, 'TIN_4' => $e4,
            'RDO_CODE' => $s('rdo_code'), 'EMP_Name' => self::employeeName($a),
            'REG_ADDRESS' => $s('employee_address'), 'A_ZIP_CODE' => $s('employee_zip'),
            'HOME_ADDRESS' => $s('employee_home_address'), 'C_ZIP_CODE' => $s('employee_home_zip'),
            'FOREIGN_ADDRESS' => $s('employee_foreign_address'), 'DATE_BIRTH' => $date8('date_of_birth'), 'CON_NUMBER' => $s('contact_number'),
            'RATE_DAY' => $mwe ? $fmt($v['mwe_rate_day']) : '', 'RATE_MONTH' => $mwe ? $fmt($v['mwe_rate_month']) : '', 'SMW_P_MONTH' => $mwe ? 'X' : '',
            // Part II — present employer (NHA, main employer)
            'EMP_TIN' => $r1, 'EMP_TIN_1' => $r2, 'EMP_TIN_2' => $r3, 'EMP_TIN_3' => $r4,
            'EMPYER_NAME' => $set['bir_payor_name'], 'REG_ADDRESS_1' => $set['bir_payor_address'], 'A_ZIP_CODE_1' => $set['bir_payor_zip'],
            'MAIN_EMP' => 'X', 'SECOND_EMP' => '',
            // Part III — previous employer
            'T_EMP_TIN_1' => $p1, 'T_EMP_TIN_2' => $p2, 'T_EMP_TIN_3' => $p3, 'T_EMP_TIN_4' => $p4,
            'EMPYR_NAME_1' => $s('prev_employer_name'), 'REG_ADDRESS_2' => $s('prev_employer_address'), '18A_ZIP_CODE' => $s('prev_employer_zip'),
            // Part IV-A — summary (19–28)
            'GCIP' => $fmt($m['i19']), 'IPE_38' => $fmt($m['i20']), 'TCIP' => $fmt($m['i21']), 'ATCI' => $fmt($m['i22']), 'GTCI' => $fmt($m['i23']),
            'TAX_DUE' => $fmt($m['i24']), 'ATW_25A' => $fmt($m['i25a']), 'ATW_25B' => $fmt($m['i25b']), 'TATWA' => $fmt($m['i26']),
            'TC_PERA' => $fmt($m['i27']), 'TTW' => $fmt($m['i28']),
            // Part IV-B A — non-taxable / exempt (29–38; 29–33 are minimum wage earners' — not computed)
            'BASIC_SALARY_MWE' => '', 'HOLIDAY_PAY_MWE' => '', 'OT_PAY_MWE' => '', 'NIGHT_SHIFT_MWE' => '', 'HAZARD_PAY_MWE' => '',
            '13TH_OTHER' => $fmt($m['i34']), 'DE_MINIMIS_B' => $fmt($m['i35']), 'CONTRIBUTIONS' => $fmt($m['i36']),
            'COMPENSATION' => $fmt($m['i37']), 'NON_COMPENSATION' => $fmt($m['i38']),
            // Part IV-B B — taxable (39–52)
            'BASIC_SALARY' => $fmt($m['i39']), 'REPRESENTATION' => '', 'TRANSPORTATION' => '', 'COLA' => '', 'FHA' => '',
            '44A' => '', '44A_1' => '', '44B' => '', '44B_1' => '', 'COMMISSION' => '', 'PROF_SHA' => '', 'FEES_INC' => '',
            'TAXABLE_BEN' => $fmt($m['i48']), 'HAZARD_PAY' => '', 'OT_PAY_3' => '', '51A' => '', '51A_1' => '', '51B' => '', '51B_1' => '',
            'TTCI' => $fmt($m['i52']),
            // 53–56 signatures (PEAASP and ESPN are each one field shared by 53/55 and 54/56)
            'PEAASP' => $s('employer_signatory'), 'DATE_SIGNED' => $date8('date_signed'),
            'ESPN' => $s('employee_signatory'), 'DATE_SIGNED_2' => $date8('date_signed_2'),
            'CTCVALID_ID_NO' => $s('ctc_no'), 'PLACE_ISSUE' => $s('ctc_place'), 'DATE_ISSUED' => $date8('ctc_date'), 'AMOUNT_CTC' => $fmt($v['ctc_amount']),
        ];

        return ['fields' => $fields, 'file_base' => 'BIR2316-' . $year . '-' . preg_replace('/[^A-Za-z0-9\-]/', '_', (string) $a['employee_no'])];
    }

    /** The filled 2316 as PDF bytes, or null (logged). */
    public function pdf(array $m): ?string
    {
        return PrintService::fillPdfForm(self::TEMPLATE, $m['fields']);
    }

    /** The employee's stored annualization for the year (+ employee details) and NHA's settings — or throws. */
    private function load(int $year, int $employeeId): array
    {
        if ($year < 2000 || $employeeId <= 0) {
            throw new \InvalidArgumentException('fiscal_yr and employee_id are required.');
        }
        $a = App\ExecuteRow(
            "SELECT a.*, e.employee_no, e.last_name, e.first_name, e.middle_name, e.tin
               FROM fims.payroll_annualization a
               JOIN fims.payroll_employees e ON e.employee_id = a.employee_id
              WHERE a.fiscal_yr = {$year} AND a.employee_id = {$employeeId}",
            'DB'
        );
        if (!$a) {
            throw new \InvalidArgumentException("This employee isn't annualized for {$year} yet — run Annualize first (Payroll Management → Annualization).");
        }

        return [$a, Bir2307PrintService::agencySettings()];
    }

    /** "LAST, FIRST MIDDLE" as the 2316's item 5. */
    private static function employeeName(array $a): string
    {
        return strtoupper(trim(implode(', ', array_filter([trim((string) $a['last_name']), trim(trim((string) $a['first_name']) . ' ' . trim((string) $a['middle_name']))]))));
    }

    private static function editableDefaults(array $a, array $set): array
    {
        return [
            'period_from' => '0101', 'period_to' => '1231',
            'employee_tin' => PrintService::text($a['tin']), 'rdo_code' => '', 'employee_address' => '', 'employee_zip' => '',
            'employee_home_address' => '', 'employee_home_zip' => '', 'employee_foreign_address' => '', 'date_of_birth' => '',
            'contact_number' => '', 'is_mwe' => false, 'mwe_rate_day' => '', 'mwe_rate_month' => '',
            'prev_employer_tin' => '', 'prev_employer_name' => '', 'prev_employer_address' => '', 'prev_employer_zip' => '',
            'taxable_previous' => '', 'tax_withheld_previous' => '',
            'employer_signatory' => $set['bir_signatory_name'], 'date_signed' => '',
            'employee_signatory' => self::employeeName($a), 'date_signed_2' => '',
            'ctc_no' => '', 'ctc_place' => '', 'ctc_date' => '', 'ctc_amount' => '',
        ];
    }

    /** Items 19–52 from the annualization row and the previous employer's figures. */
    private static function amounts(array $a, array $v): array
    {
        $n    = static fn ($x) => round((float) str_replace(',', '', (string) $x), 2);
        $i21  = $n($a['taxable_income']);
        $i22  = $n($v['taxable_previous'] ?? 0);
        $i23  = round($i21 + $i22, 2);
        $i24  = PayrollManagementService::annualTax($i23);
        $i25a = $n($a['tax_withheld']);
        $i25b = $n($v['tax_withheld_previous'] ?? 0);
        $benefitsTaxable = round($n($a['benefits']) - $n($a['benefits_exempt']), 2);

        return [
            'i19' => $n($a['gross_compensation']), 'i20' => $n($a['total_non_taxable']), 'i21' => $i21, 'i22' => $i22, 'i23' => $i23,
            'i24' => $i24, 'i25a' => $i25a, 'i25b' => $i25b, 'i26' => $i24, 'i27' => 0.0, 'i28' => $i24,
            'adjustment' => round($i24 - $i25a - $i25b, 2),
            'i34' => $n($a['benefits_exempt']), 'i35' => $n($a['de_minimis_exempt']), 'i36' => $n($a['contributions']),
            'i37' => $n($a['non_taxable_pay']), 'i38' => $n($a['total_non_taxable']),
            'i39' => round($n($a['taxable_pay']) - $n($a['contributions']), 2), 'i48' => $benefitsTaxable, 'i52' => $i21,
        ];
    }
}
