<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * GCG QUARTERLY REPORT — a copy of the client's template GENERAL LEDGER\GCG_Quarterly_Template_Preview.xlsx (sheet
 * "GCG Report"): A1 "GCG Report (Q1 2026)" (merged A–AR, bold 12, centred), A2 "National Housing Authority (NHA)"
 * (merged, bold 9, centred), row 4 the header (bold 9, wrapped, bordered, 48 pt high, "name\naccount"), data from row 5,
 * a yellow bold subtotal per position (position in A, count in F), a bold GRAND TOTAL (count in F); frozen below the
 * header, auto-filter on it; Arial 9; the template's column widths.
 * Print type GCG: PDF (Dompdf, legal landscape — the same sheet printed Excel-style "down, then over": the identity
 * columns + BASIC … on the first pages, the remaining columns on the next, header repeated on every page) + Export to
 * Excel (POST /print/gcg/excel — the template itself, filled). Opened from Payroll Management → Year-to-Date Report.
 *
 * Payload: { fiscal_yr, period } — period Q1 | Q2 | Q3 | Q4.
 * Data: the quarter's imported monthly payroll — one row per employee (fims.payroll_record_items summed over the months).
 * A line goes to a column by its item where the template has a column the chart of accounts doesn't (ITEM_COLUMN — the
 * template's 5-01-02-170…270 / 5-01-04-040…060 codes aren't in our chart), otherwise by its 4-part account
 * (ACCOUNT_COLUMN). EARNING / REFUND / EMPLOYER_SHARE add, DEDUCTION takes away (leave without pay, undertime). NET PAY =
 * the payroll records' net amount (fims.payroll_records.net_amount). PS lines with no template column (MEDICAL, 3K, 50K,
 * MYFA_2017 …) are listed below the report so nothing is lost; loans / withholdings / recoupments aren't PS expense.
 * POSITION, JOB GRADE (salary grade) come from the payroll directory (blank until filled); STEP isn't kept (blank).
 */
class GcgReportPrintService
{
    /** Template columns H … AQ, in order: key => [header, account / code shown under it ('' = none)]. NET PAY (AR) follows. */
    public const COLUMNS = [
        'BASIC'   => ['BASIC SALARY', ''],
        'PERA'    => ['PERA', '5-01-02-010'],
        'RA'      => ['Representation Allowance', '5-01-02-020'],
        'TA'      => ['Transportation Allowance', '5-01-02-030'],
        'CLOTH'   => ['Clothing & Uniform Allow.', '5-01-02-040'],
        'SUBS'    => ['Subsistence Allow.', '5-01-02-050'],
        'LAUNDRY' => ['Laundry Allowance', '5-01-02-060'],
        'QA'      => ['Quarters Allowance', '5-01-02-070'],
        'PIA'     => ['Productivity Incentive Allow.', '5-01-02-080'],
        'HON'     => ['Honoraria', '5-01-02-100'],
        'HAZ'     => ['Hazard Pay', '5-01-02-110'],
        'LONG'    => ['Longevity Pay', '5-01-02-120'],
        'OTNP'    => ['OT & Night Pay', '5-01-02-130'],
        'YEB'     => ['Year-End Bonus', '5-01-02-140'],
        'CG'      => ['Cash Gift', '5-01-02-150'],
        'MID'     => ['Mid-Year Bonus', '5-01-02-160'],
        'CNA'     => ['C.N.A Incentive', '5-01-02-170'],
        'PBB'     => ['PBB', '5-01-02-180'],
        'LOY'     => ['Loyalty Award', '5-01-02-190'],
        'SCA'     => ['SCA', '5-01-02-200'],
        'PRAISE'  => ['PRAISE Incentive', '5-01-02-210'],
        'SRI'     => ['SRI', '5-01-02-220'],
        'MEAL'    => ['Meal Subsidy', '5-01-02-230'],
        'CHILD'   => ["Children's Allowance", '5-01-02-240'],
        'RICE'    => ['Rice Subsidy', '5-01-02-250'],
        'CASHIER' => ["Cashier's Allowance", '5-01-02-260'],
        'GAD'     => ['GAD Incentive', '5-01-02-270'],
        'MLC'     => ['Monetization of LC', '5-01-04-040'],
        'SOA'     => ['SOA Pay', '5-01-04-050'],
        'GPAI'    => ['GPAI', '5-01-04-060'],
        'LIFE'    => ['Life & Retirement', '5-01-03-010'],
        'HDMF'    => ['Pag-ibig', '5-01-03-020'],
        'PHIC'    => ['Philhealth', '5-01-03-030'],
        'ECC'     => ['ECC', '5-01-03-040'],
        'PFAI'    => ['Provident/Welfare Fund', '5-01-03-050'],
        'TL'      => ['Terminal Leave', '5-01-04-030'],
    ];

    /** Identity columns A–G: [header, Excel width (chars)]. */
    private const IDENTITY = [
        ['EMP. NO.', 10], ['LAST NAME', 18], ['FIRST NAME', 15], ['MIDDLE NAME', 15], ['POSITION', 22], ['JOB GRADE', 9], ['STEP', 7],
    ];
    private const AMOUNT_WIDTH = 13;   // Excel width of H … AR

    /** Payroll items with a template column of their own (the template's code isn't an account in our chart). */
    private const ITEM_COLUMN = [
        '2016_CNA_IN_REF'       => 'CNA',
        'LOYALTY'               => 'LOY',
        'MEAL_SUBSIDY'          => 'MEAL',
        'CHILDREN\'S_ALLOWANCE' => 'CHILD',
        'CHILDREN\'S_ALLOW_ADJ' => 'CHILD',
        'RICE_SUBSIDY'          => 'RICE',
    ];

    /** Every other PS line by its account (4-part GL; a sub-ledger counts under its GL). */
    private const ACCOUNT_COLUMN = [
        '5-01-01-010' => 'BASIC', '5-01-02-010' => 'PERA', '5-01-02-020' => 'RA', '5-01-02-030' => 'TA',
        '5-01-02-040' => 'CLOTH', '5-01-02-050' => 'SUBS', '5-01-02-060' => 'LAUNDRY', '5-01-02-070' => 'QA',
        '5-01-02-080' => 'PIA', '5-01-02-100' => 'HON', '5-01-02-110' => 'HAZ', '5-01-02-120' => 'LONG',
        '5-01-02-130' => 'OTNP', '5-01-02-140' => 'YEB', '5-01-02-150' => 'CG', '5-01-02-160' => 'MID',
        '5-01-03-010' => 'LIFE', '5-01-03-020' => 'HDMF', '5-01-03-030' => 'PHIC', '5-01-03-040' => 'ECC',
        '5-01-03-050' => 'PFAI', '5-01-04-030' => 'TL',
    ];

    public const PERIODS = ['Q1' => [1, 3], 'Q2' => [4, 6], 'Q3' => [7, 9], 'Q4' => [10, 12]];

    private const YELLOW = 'FFFF00';

    /** The report data, or throws (→ HTTP 400) when nothing was imported for the quarter. */
    public function model(array $p): array
    {
        $year   = (int) ($p['fiscal_yr'] ?? 0);
        $period = strtoupper(trim((string) ($p['period'] ?? '')));
        if ($year < 2000 || $year > 2100 || !isset(self::PERIODS[$period])) {
            throw new \InvalidArgumentException('Choose the fiscal year and the quarter (Q1–Q4).');
        }
        [$from, $to] = self::PERIODS[$period];
        $label = "{$period} {$year}";
        $where = "p.fiscal_yr = {$year} AND p.payroll_month BETWEEN {$from} AND {$to}";

        $lines = App\ExecuteRows(
            "SELECT r.employee_id, i.item_code, i.item_name, i.category,
                    COALESCE(NULLIF(li.account_code, ''), i.account_code, '') AS account_code, SUM(li.amount) AS amount
               FROM fims.payroll_record_items li
               JOIN fims.payroll_records r ON r.payroll_record_id = li.payroll_record_id
               JOIN fims.payroll_periods p ON p.payroll_period_id = r.payroll_period_id
               JOIN fims.payroll_items i ON i.payroll_item_id = li.payroll_item_id
              WHERE {$where}
              GROUP BY r.employee_id, i.item_code, i.item_name, i.category, 5",
            'DB'
        ) ?: [];
        $net = [];
        foreach (App\ExecuteRows("SELECT r.employee_id, SUM(r.net_amount) AS net FROM fims.payroll_records r
                                    JOIN fims.payroll_periods p ON p.payroll_period_id = r.payroll_period_id
                                   WHERE {$where} GROUP BY r.employee_id", 'DB') ?: [] as $n) {
            $net[(int) $n['employee_id']] = round((float) $n['net'], 2);
        }
        if (!$lines && !$net) {
            throw new \InvalidArgumentException("No payroll imported for {$label}.");
        }

        $amounts  = [];   // employee_id => [column => amount]
        $unmapped = [];   // item_code => [name, account, amount]
        foreach ($lines as $l) {
            $sign = match ((string) $l['category']) {
                'EARNING', 'REFUND', 'EMPLOYER_SHARE' => 1,
                'DEDUCTION' => -1,
                default => 0,
            };
            $amt = $sign * (float) $l['amount'];
            if ($amt == 0.0) {
                continue;
            }
            $acct = (string) $l['account_code'];
            $gl   = implode('-', array_slice(explode('-', $acct), 0, 4));
            $col  = self::ITEM_COLUMN[(string) $l['item_code']] ?? self::ACCOUNT_COLUMN[$gl] ?? null;
            if ($col === null) {
                if (str_starts_with($acct, '5-')) {   // a PS expense with no template column — listed below the report
                    $unmapped[$l['item_code']] ??= ['name' => (string) $l['item_name'], 'account' => $gl, 'amount' => 0.0];
                    $unmapped[$l['item_code']]['amount'] += $amt;
                }
                continue;
            }
            $id = (int) $l['employee_id'];
            $amounts[$id][$col] = ($amounts[$id][$col] ?? 0.0) + $amt;
        }

        $ids  = implode(',', array_map('intval', array_unique(array_merge(array_column($lines, 'employee_id'), array_keys($net)))));
        $emps = App\ExecuteRows("SELECT employee_id, employee_no, last_name, first_name, middle_name, position, salary_grade
                                   FROM fims.payroll_employees WHERE employee_id IN ({$ids})", 'DB') ?: [];
        // grouped by position (as the old export: position, then name); a blank position goes last
        $key = static fn ($e) => [trim((string) $e['position']) === '' ? 1 : 0, strtoupper(trim((string) $e['position'])),
            strtoupper((string) $e['last_name']), strtoupper((string) $e['first_name'])];
        usort($emps, static fn ($a, $b) => $key($a) <=> $key($b));

        $zero  = array_fill_keys(array_keys(self::COLUMNS), 0.0) + ['NET' => 0.0];
        $rows  = [];
        $grand = $zero;
        $sub   = null;
        foreach ($emps as $e) {
            $pos = strtoupper(trim((string) $e['position']));
            if ($sub === null || $pos !== $sub['position']) {
                if ($sub !== null) {
                    $rows[] = $sub;
                }
                $sub = ['type' => 'sub', 'position' => $pos, 'count' => 0, 'amounts' => $zero];
            }
            $id = (int) $e['employee_id'];
            $a  = [];
            foreach ($zero as $k => $_) {
                $a[$k] = $k === 'NET' ? ($net[$id] ?? 0.0) : round($amounts[$id][$k] ?? 0.0, 2);
                $sub['amounts'][$k] += $a[$k];
                $grand[$k] += $a[$k];
            }
            $sub['count']++;
            $rows[] = ['type' => 'emp', 'employee_no' => (string) $e['employee_no'], 'last_name' => strtoupper((string) $e['last_name']),
                'first_name' => strtoupper((string) $e['first_name']), 'middle_name' => strtoupper((string) $e['middle_name']),
                'position' => $pos, 'grade' => (string) $e['salary_grade'], 'step' => '', 'amounts' => $a];
        }
        if ($sub !== null) {
            $rows[] = $sub;
        }
        $rows[] = ['type' => 'grand', 'count' => count($emps), 'amounts' => array_map(static fn ($v) => round($v, 2), $grand)];

        $unmapped = array_values(array_filter(array_map(static fn ($u) => ['amount' => round($u['amount'], 2)] + $u, $unmapped),
            static fn ($u) => $u['amount'] != 0.0));

        return ['year' => $year, 'period' => $period, 'title' => "GCG Report ({$label})", 'rows' => $rows,
            'unmapped' => $unmapped, 'file_base' => "GCG_Quarterly_{$period}_{$year}"];
    }

    /** Header text of an amount column ("name\ncode", as the template). */
    private static function header(string $k): string
    {
        return $k === 'NET' ? 'NET PAY' : self::COLUMNS[$k][0] . (self::COLUMNS[$k][1] !== '' ? "\n" . self::COLUMNS[$k][1] : '');
    }

    /** The row's cells A … AR as [value, kind] — kind: text | num. */
    private static function cells(array $r): array
    {
        $id = match ($r['type']) {
            'emp'   => [$r['employee_no'], $r['last_name'], $r['first_name'], $r['middle_name'], $r['position'], $r['grade'], $r['step']],
            'sub'   => [$r['position'], '', '', '', '', $r['count'], ''],
            default => ['GRAND TOTAL', '', '', '', '', $r['count'], ''],
        };

        return [$id, $r['amounts']];
    }

    /** The report as a PDF, or null (logged). */
    public function pdf(array $d): ?string
    {
        return GlobalService::renderPdf($this->html($d), 'legal', 'Helvetica', 'landscape');   // the page size is the CSS @page one
    }

    private function html(array $d): string
    {
        $e  = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $m  = static fn ($v) => number_format((float) $v, 2);
        $pt = static fn (float $chars) => round($chars * 5.25 + 4, 1);   // Excel width → points (Arial 9)

        // As the sheet: every column A … AR on one line — the page is as wide as the sheet (the preview modal goes full
        // screen for it); the header row repeats on each page (Dompdf repeats <thead>).
        $groups = [['id' => true, 'keys' => array_merge(array_keys(self::COLUMNS), ['NET'])]];
        $sheetW = array_sum(array_map(static fn ($c) => $pt($c[1]), self::IDENTITY)) + count($groups[0]['keys']) * $pt(self::AMOUNT_WIDTH);
        $pageW  = ceil($sheetW + 2 * 20 + 20);
        $pageH  = round($pageW * 0.42);   // a wide landscape page — about 70 rows

        $out = [];
        foreach ($groups as $gi => $g) {
            $cols  = '';
            $head  = '';
            $width = 0;
            if ($g['id']) {
                foreach (self::IDENTITY as [$h, $c]) {
                    $cols  .= '<col style="width:' . $pt($c) . 'pt;">';
                    $head  .= '<th style="width:' . ($pt($c) - 4) . 'pt;">' . $e($h) . '</th>';
                    $width += $pt($c);
                }
            }
            foreach ($g['keys'] as $k) {
                $cols  .= '<col style="width:' . $pt(self::AMOUNT_WIDTH) . 'pt;">';
                $head  .= '<th style="width:' . ($pt(self::AMOUNT_WIDTH) - 4) . 'pt;">' . nl2br($e(self::header($k))) . '</th>';
                $width += $pt(self::AMOUNT_WIDTH);
            }
            $body = '';
            foreach ($d['rows'] as $r) {
                [$id, $amt] = self::cells($r);
                $cls   = $r['type'] === 'sub' ? ' class="sub"' : ($r['type'] === 'grand' ? ' class="grand"' : '');
                $body .= '<tr' . $cls . '>';
                if ($g['id']) {
                    foreach ($id as $i => $v) {
                        if ($r['type'] !== 'emp' && $i <= 4) {
                            // as in Excel, a subtotal / total label in A runs over the empty B–E beside it
                            $body .= $i === 0 ? '<td colspan="5"><div class="clip">' . $e($v) . '</div></td>' : '';
                            continue;
                        }
                        $body .= '<td' . ($i >= 5 ? ' class="c"' : '') . '><div class="clip" style="width:' . ($pt(self::IDENTITY[$i][1]) - 4) . 'pt;">' . $e($v) . '</div></td>';
                    }
                }
                foreach ($g['keys'] as $k) {
                    $body .= '<td class="r">' . $m($amt[$k] ?? 0) . '</td>';
                }
                $body .= '</tr>';
            }
            $out[] = ($gi === 0 ? '<div class="t1" style="width:' . $width . 'pt;">' . $e($d['title']) . '</div><div class="t2" style="width:' . $width . 'pt;">National Housing Authority (NHA)</div>' : '')
                . '<table class="g" style="width:' . $width . 'pt;"><colgroup>' . $cols . '</colgroup><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>';
        }
        if ($d['unmapped']) {
            $list = '';
            foreach ($d['unmapped'] as $u) {
                $list .= '<tr><td>' . $e($u['name']) . '</td><td>' . $e($u['account']) . '</td><td class="r">' . $m($u['amount']) . '</td></tr>';
            }
            $out[count($out) - 1] .= '<div class="note" style="margin-top:14pt;">Not in a GCG column — personal services paid in the quarter with no matching column:</div>'
                . '<table class="g"><thead><tr><th>Payroll item</th><th>Account</th><th>Amount</th></tr></thead><tbody>' . $list . '</tbody></table>';
        }

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . '@page{size:' . $pageW . 'pt ' . $pageH . 'pt;margin:22pt 20pt;}body{font-family:Helvetica,Arial,sans-serif;font-size:7pt;color:#000;}'
            . '.t1{text-align:center;font-weight:bold;font-size:12pt;margin:0 0 2pt;}.t2{text-align:center;font-weight:bold;font-size:9pt;margin:0 0 14pt;}'
            . '.g{border-collapse:collapse;}.g th,.g td{border:.5pt solid #000;padding:1.5pt 2pt;}'
            . '.g th{font-weight:bold;text-align:center;vertical-align:middle;height:36pt;line-height:1.2;}'
            . '.r{text-align:right;white-space:nowrap;}.c{text-align:center;}.clip{overflow:hidden;white-space:nowrap;}.spill{overflow:visible;white-space:nowrap;height:8pt;}'
            . '.sub td{font-weight:bold;background:#' . self::YELLOW . ';}.grand td{font-weight:bold;}'
            . '.note{margin:0 0 4pt;font-weight:bold;}'
            . '</style></head><body>' . implode('<div style="page-break-before:always;"></div>', $out) . '</body></html>';
    }

    /** The report as an .xlsx — the template sheet, filled. */
    public function xlsx(array $d): string
    {
        $x      = new XlsxWriter('GCG Report');
        $font   = ['name' => 'Arial', 'sz' => 9];
        $title  = $x->style(['font' => ['name' => 'Arial', 'sz' => 12, 'bold' => true], 'align' => ['h' => 'center', 'v' => 'center', 'wrap' => true]]);
        $sub    = $x->style(['font' => $font + ['bold' => true], 'align' => ['h' => 'center', 'v' => 'center', 'wrap' => true]]);
        $head   = $x->style(['font' => $font + ['bold' => true], 'border' => '000000', 'align' => ['h' => 'center', 'v' => 'center', 'wrap' => true]]);
        $fmt    = '#,##0.00';
        $st = [
            'emp'   => [$x->style(['font' => $font, 'border' => '000000']), $x->style(['font' => $font, 'border' => '000000', 'numFmt' => $fmt, 'align' => ['h' => 'right']])],
            'sub'   => [$x->style(['font' => $font + ['bold' => true], 'border' => '000000', 'fill' => self::YELLOW]),
                        $x->style(['font' => $font + ['bold' => true], 'border' => '000000', 'fill' => self::YELLOW, 'numFmt' => $fmt])],
            'grand' => [$x->style(['font' => $font + ['bold' => true], 'border' => '000000']), $x->style(['font' => $font + ['bold' => true], 'border' => '000000', 'numFmt' => $fmt])],
        ];

        $keys = array_merge(array_keys(self::COLUMNS), ['NET']);
        $id   = count(self::IDENTITY);
        $last = $id + count($keys) - 1;   // AR
        $x->set(0, 0, $d['title'], $title);
        $x->merge(0, 0, 0, $last);
        $x->set(1, 0, 'National Housing Authority (NHA)', $sub);
        $x->merge(1, 0, 1, $last);
        foreach (self::IDENTITY as $i => [$h, $w]) {
            $x->set(3, $i, $h, $head);
            $x->colWidth($i, $w);
        }
        foreach ($keys as $j => $k) {
            $x->set(3, $id + $j, self::header($k), $head);
            $x->colWidth($id + $j, self::AMOUNT_WIDTH);
        }
        $x->rowHeight(3, 48);
        $x->freeze(4);                    // the template: frozen at A5
        $x->autoFilter(3, 0, $last);      // auto-filter on the header row
        $x->gridLines();

        $row = 3;
        foreach ($d['rows'] as $r) {
            $row++;
            [$idv, $amt] = self::cells($r);
            [$t, $n] = $st[$r['type']];
            foreach ($idv as $i => $v) {
                $x->set($row, $i, $v, $t);
            }
            foreach ($keys as $j => $k) {
                $x->set($row, $id + $j, (float) ($amt[$k] ?? 0), $n);
            }
        }
        if ($d['unmapped']) {
            $row += 2;
            $x->set($row, 0, 'Not in a GCG column — personal services paid in the quarter with no matching column:', $x->style(['font' => $font + ['bold' => true]]));
            foreach ($d['unmapped'] as $u) {
                $row++;
                $x->set($row, 1, $u['name'], $x->style(['font' => $font]));
                $x->set($row, 4, $u['account'], $x->style(['font' => $font]));
                $x->set($row, 5, (float) $u['amount'], $x->style(['font' => $font, 'numFmt' => $fmt]));
            }
        }

        return $x->render();
    }
}
