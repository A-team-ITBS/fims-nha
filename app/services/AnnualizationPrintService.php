<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * ANNUALIZATION WORKSHEET — the client's "ANNUALIZATION OF INCOME FOR YEAR …" template (ANNUALIZATION TEMPLATE.xlsx, sheet
 * EMP ANZN), with the template's own wording, for ONE employee and fiscal year, from the payroll
 * (PayrollManagementService::annualizationWorksheet()). Print type ANNUALWS, PDF only (Dompdf through
 * GlobalService::renderPdf()); the same sheet as Excel through xlsx() (XlsxWriter) — the print preview's "Export to
 * Excel" (POST /print/annualws/excel).
 *
 * Payload: { fiscal_yr, employee_id }.
 * Layout (as the template): ANNUALIZATION OF INCOME FOR YEAR …; NAME: / DEPT:; the monthly table headed by the year —
 * BASIC · TAX · GSIS · HDMF · PHIC · UNION DUES · TOTAL — rows BIR FORM 2316, JANUARY … DECEMBER, SRI TAX, TOTAL;
 * OTHER ALLOWANCES/ BONUSES: (YEAR END BONUS, MID YEAR BONUS, CHILDREN ALLOWANCE, PROFESSIONAL DEV'T INCENTIVES, MEAL
 * ALLOWANCE — always listed; any other 13th-month / other-benefit item after them under its own name — EXCESS OF DE
 * MINIMIS BENEFITS, TOTAL, (90,000.00), TAXABLE) beside DE MINIMIS BENEFITS: (ACTUAL / LIMIT / EXCESS, the benefits'
 * names from Payroll Setup → De Minimis Limits — seeded with the template's RICE SUBSIDY/MONTH, UCA/YEAR, CASH GIFT,
 * SRI, PEI, LOYALTY — the LIMIT as entered, TOTAL); TAX EXEMPT (GSIS, HDMF, PHIC, UNION DUES, TOTAL); GROSS TAXABLE
 * INCOME, INCOME IN EXCESS OF 90,000 TRESHHOLD (the template's spelling), TAXABLE INCOME, TOTAL TAX DUE, LESS : TAX
 * WITHHELD, TAX DUE / (REFUND); the tax table (IF TAXABLE INCOME IS : / TAX DUE IS:).
 * Not copied: the template's "SCA – DV#…" rows (special payrolls — not in the monthly import) and its formula bugs
 * (rice excess less 2,000 instead of the year's limit, UCA less 6,000 against a 7,000 limit, the excess total that
 * skips Loyalty).
 */
class AnnualizationPrintService
{
    /** The template's fixed OTHER ALLOWANCES/ BONUSES rows and the payroll items (item_code) that fill them. */
    private const BONUS_ROWS = [
        'YEAR END BONUS'                => ['YEAR_END'],
        'MID YEAR BONUS'                => ['MIDYEAR'],
        'CHILDREN ALLOWANCE'            => ["CHILDREN'S_ALLOWANCE", "CHILDREN'S_ALLOW_ADJ"],
        "PROFESSIONAL DEV'T INCENTIVES" => [],
        'MEAL ALLOWANCE'                => ['MEAL_SUBSIDY'],
    ];

    /** The template's tax table, word for word: [IF TAXABLE INCOME IS :, TAX DUE IS:]. */
    private const TAX_TABLE = [
        ['Not over 250,000', '0'],
        ['Over    250,000    but not over    400,000', '15% of the excess over 250,000'],
        ['Over    400,000    but not over    800,000', 'P    22,500    +    20 %   of the excess over      400,000'],
        ['Over    800,000    but not over 2,000,000', 'P  102,500    +    25 %   of the excess over      800,000'],
        ['Over 2,000,000    but not over 8,000,000', 'P  402,500    +    30 %   of the excess over   2,000,000'],
        ['Over 8,000,000', 'P2,202,500    +    35 %   of the excess over  8,000,000'],
    ];

    /** The worksheet data, or throws (→ HTTP 400) when the employee has no payroll that year. */
    public function model(array $p): array
    {
        $r = (new PayrollManagementService())->annualizationWorksheet((int) ($p['fiscal_yr'] ?? 0), (int) ($p['employee_id'] ?? 0));
        if (!$r['success']) {
            throw new \InvalidArgumentException($r['message']);
        }
        $d = $r['data'];
        $e = $d['employee'];
        $d['employee_name'] = strtoupper(trim(implode(', ', array_filter([trim((string) $e['last_name']),
            trim(trim((string) $e['first_name']) . ' ' . trim((string) $e['middle_name']))]))));
        $d['file_base'] = 'Annualization-' . $d['year'] . '-' . preg_replace('/[^A-Za-z0-9\-]/', '_', (string) $e['employee_no']);

        // OTHER ALLOWANCES/ BONUSES as the template lists them: its five rows (always), then any other benefit item
        $byCode = [];
        foreach ($d['benefits'] as $b) {
            $byCode[$b['code']] = $b;
        }
        $rows = [];
        foreach (self::BONUS_ROWS as $label => $codes) {
            $amt = 0.0;
            foreach ($codes as $c) {
                $amt += $byCode[$c]['amount'] ?? 0.0;
                unset($byCode[$c]);
            }
            $rows[] = [$label, round($amt, 2)];
        }
        foreach ($byCode as $b) {
            $rows[] = [strtoupper($b['name']), $b['amount']];
        }
        $d['bonus_rows'] = $rows;

        return $d;
    }

    /** The worksheet as a PDF (letter portrait), or null (logged). */
    public function pdf(array $d): ?string
    {
        return GlobalService::renderPdf($this->html($d), 'letter', 'Helvetica', 'portrait');
    }

    private function html(array $d): string
    {
        $e   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $sp  = static fn ($v) => str_replace('  ', ' &nbsp;', $e($v));   // keep the template's spacing
        $m   = static fn ($v) => (float) $v != 0.0 ? number_format((float) $v, 2) : '-';
        $neg = static fn ($v) => (float) $v < 0 ? '(' . number_format(abs((float) $v), 2) . ')' : number_format((float) $v, 2);
        $t   = $d['totals'];
        $c   = $d['computation'];

        $months = '';
        foreach ($d['months'] as $r) {
            $months .= '<tr><td>' . $e($r['month_name']) . '</td><td class="r">' . $m($r['basic']) . '</td><td class="r">' . $m($r['tax'])
                . '</td><td class="r">' . $m($r['gsis']) . '</td><td class="r">' . $m($r['hdmf']) . '</td><td class="r">' . $m($r['phic'])
                . '</td><td class="r">' . $m($r['union_dues']) . '</td><td class="r b">' . $neg($r['net']) . '</td></tr>';
        }
        $blank = '<td class="r"></td><td class="r"></td><td class="r"></td><td class="r"></td><td class="r"></td><td class="r"></td><td class="r b"></td>';

        $benefits = '';
        foreach ($d['bonus_rows'] as [$label, $amt]) {
            $benefits .= '<tr><td>' . $e($label) . '</td><td class="r">' . $m($amt) . '</td></tr>';
        }
        $benefits .= '<tr><td>EXCESS OF DE MINIMIS BENEFITS</td><td class="r">' . $m($d['de_minimis_excess']) . '</td></tr>'
            . '<tr class="b"><td>TOTAL</td><td class="r tl">' . $m($d['benefits_total']) . '</td></tr>'
            . '<tr class="b"><td></td><td class="r">(' . number_format($d['benefit_cap'], 2) . ')</td></tr>'
            . '<tr class="b"><td>TAXABLE</td><td class="r tl">' . $m($c['benefits_taxable']) . '</td></tr>';

        $deMin = '';
        foreach ($d['de_minimis'] as $x) {
            $deMin .= '<tr><td>' . $e(strtoupper($x['name'])) . '</td><td class="r">' . $m($x['actual']) . '</td><td class="r">'
                . $m($x['limit_entered']) . '</td><td class="r">' . $m($x['excess']) . '</td></tr>';
        }
        $deMin .= '<tr class="b"><td>TOTAL</td><td class="r tl">' . $m(array_sum(array_column($d['de_minimis'], 'actual')))
            . '</td><td class="r tl">' . $m(array_sum(array_column($d['de_minimis'], 'limit_entered')))
            . '</td><td class="r tl">' . $m($d['de_minimis_excess']) . '</td></tr>';

        $taxTable = '';
        foreach (self::TAX_TABLE as [$a, $b]) {
            $taxTable .= '<tr><td>' . $sp($a) . '</td><td>' . $sp($b) . '</td></tr>';
        }

        $body = '<div class="title">ANNUALIZATION OF INCOME FOR YEAR ' . $e($d['year']) . '</div>'
            . '<table class="hd"><tr><td style="width:44px;">NAME:</td><td class="u">' . $e($d['employee_name']) . '</td></tr>'
            . '<tr><td>DEPT:</td><td class="u">' . $e($d['employee']['department']) . '</td></tr></table>'
            . '<table class="g"><thead><tr><th style="width:18%;">' . $e($d['year']) . '</th><th>BASIC</th><th>TAX</th><th>GSIS</th><th>HDMF</th><th>PHIC</th><th>UNION DUES</th><th>TOTAL</th></tr></thead><tbody>'
            . '<tr><td>BIR FORM 2316</td>' . $blank . '</tr>'
            . $months
            . '<tr><td>SRI TAX</td>' . $blank . '</tr>'
            . '<tr class="b"><td>TOTAL</td><td class="r tl">' . $m($t['basic']) . '</td><td class="r tl">' . $m($t['tax']) . '</td><td class="r tl">'
            . $m($t['gsis']) . '</td><td class="r tl">' . $m($t['hdmf']) . '</td><td class="r tl">' . $m($t['phic']) . '</td><td class="r tl">'
            . $m($t['union_dues']) . '</td><td class="r tl">' . $neg($t['net']) . '</td></tr></tbody></table>'
            . '<table class="two"><tr><td style="width:47%;vertical-align:top;">'
            . '<div class="sec">OTHER ALLOWANCES/ BONUSES:</div><table class="s">' . $benefits . '</table>'
            . '</td><td style="width:6%;"></td><td style="vertical-align:top;">'
            . '<div class="sec">DE MINIMIS BENEFITS:</div><table class="s"><tr class="h"><td></td><td class="r">ACTUAL</td><td class="r">LIMIT</td><td class="r">EXCESS</td></tr>' . $deMin . '</table>'
            . '</td></tr></table>'
            . '<table class="two"><tr><td style="width:47%;vertical-align:top;">'
            . '<div class="sec">TAX EXEMPT</div><table class="s">'
            . '<tr><td>GSIS</td><td class="r">' . $m($d['exempt']['gsis']) . '</td></tr><tr><td>HDMF</td><td class="r">' . $m($d['exempt']['hdmf']) . '</td></tr>'
            . '<tr><td>PHIC</td><td class="r">' . $m($d['exempt']['phic']) . '</td></tr><tr><td>UNION DUES</td><td class="r">' . $m($d['exempt']['union_dues']) . '</td></tr>'
            . '<tr class="b"><td>TOTAL</td><td class="r tl">' . $m($d['exempt']['total']) . '</td></tr></table>'
            . '</td><td style="width:6%;"></td><td style="vertical-align:top;">'
            . '<table class="s" style="margin-top:13pt;">'
            . '<tr><td>GROSS TAXABLE INCOME</td><td class="r">' . $neg($c['regular_taxable']) . '</td></tr>'
            . '<tr><td>INCOME IN EXCESS OF 90,000 TRESHHOLD</td><td class="r">' . $m($c['benefits_taxable']) . '</td></tr>'
            . '<tr><td>TAXABLE INCOME</td><td class="r tl">' . $m($c['gross_taxable']) . '</td></tr>'
            . '<tr><td>TOTAL TAX DUE</td><td class="r">' . $m($c['tax_due']) . '</td></tr>'
            . '<tr><td>LESS &nbsp;: &nbsp;TAX WITHHELD</td><td class="r">' . $m($c['tax_withheld']) . '</td></tr>'
            . '<tr class="b"><td>TAX DUE / (REFUND)</td><td class="r tl dbl">' . $neg($c['balance']) . '</td></tr></table>'
            . '</td></tr></table>'
            . '<table class="tt"><tr><th>IF TAXABLE INCOME IS :</th><th>TAX DUE IS:</th></tr>' . $taxTable . '</table>';

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . '@page{size:letter portrait;margin:32pt 40pt 28pt;}body{font-family:Helvetica,Arial,sans-serif;font-size:8.5pt;color:#000;}'
            . '.title{text-align:center;font-size:12pt;font-weight:bold;margin:0 0 10pt;}'
            . '.hd{width:60%;border-collapse:collapse;margin-bottom:8pt;}.hd td{padding:2pt 3pt;font-weight:bold;}.hd .u{border-bottom:.6pt solid #000;font-weight:normal;}'
            . '.g{width:100%;border-collapse:collapse;}.g th,.g td{border:.6pt solid #000;padding:1.8pt 4pt;}.g th{background:#eee;font-size:8pt;}'
            . '.two{width:100%;border-collapse:collapse;margin-top:12pt;}.sec{font-weight:bold;margin:0 0 4pt;}'
            . '.s{width:100%;border-collapse:collapse;}.s td{padding:1.6pt 3pt;}.s .h td{font-weight:bold;font-size:7.5pt;}'
            . '.r{text-align:right;white-space:nowrap;}.b td,td.b{font-weight:bold;}.tl{border-top:.6pt solid #000;}.dbl{border-bottom:2pt double #000;}'
            . '.tt{margin-top:16pt;border-collapse:collapse;}.tt th{text-align:left;font-weight:bold;text-decoration:underline;padding:2pt 14pt 4pt 3pt;}'
            . '.tt td{padding:1.5pt 14pt 1.5pt 3pt;font-size:8pt;}'
            . '</style></head><body>' . $body . '</body></html>';
    }

    /** The worksheet as an .xlsx (one sheet "EMP ANZN", the template's layout and wording) — the print preview's Export to Excel. */
    public function xlsx(array $d): string
    {
        $x     = new XlsxWriter('EMP ANZN');
        $bold  = $x->style(['font' => ['bold' => true]]);
        $bu    = $x->style(['font' => ['bold' => true, 'u' => true]]);
        $title = $x->style(['font' => ['bold' => true, 'sz' => 12], 'align' => ['h' => 'center']]);
        $head  = $x->style(['font' => ['bold' => true], 'fill' => 'EEEEEE', 'border' => '000000', 'align' => ['h' => 'center']]);
        $cell  = $x->style(['border' => '000000']);
        $num   = $x->style(['numFmt' => '#,##0.00;(#,##0.00);"-"', 'border' => '000000']);
        $numB  = $x->style(['numFmt' => '#,##0.00;(#,##0.00);"-"', 'border' => '000000', 'font' => ['bold' => true]]);
        $n     = $x->style(['numFmt' => '#,##0.00;(#,##0.00);"-"']);
        $nB    = $x->style(['numFmt' => '#,##0.00;(#,##0.00);"-"', 'font' => ['bold' => true]]);
        $t = $d['totals'];
        $c = $d['computation'];

        $x->set(0, 0, 'ANNUALIZATION OF INCOME FOR YEAR ' . $d['year'], $title);
        $x->merge(0, 0, 0, 7);
        $x->set(2, 0, 'NAME:   ' . $d['employee_name'], $bold);
        $x->set(3, 0, 'DEPT:      ' . $d['employee']['department'], $bold);

        $row = 5;
        foreach ([(int) $d['year'], 'BASIC', 'TAX', 'GSIS', 'HDMF', 'PHIC', 'UNION DUES', 'TOTAL'] as $i => $h) {
            $x->set($row, $i, $h, $head);
        }
        $row++;
        $x->set($row, 0, 'BIR FORM 2316', $cell);
        for ($i = 1; $i <= 7; $i++) {
            $x->set($row, $i, '', $num);
        }
        foreach ($d['months'] as $r) {
            $row++;
            $x->set($row, 0, $r['month_name'], $cell);
            foreach (['basic', 'tax', 'gsis', 'hdmf', 'phic', 'union_dues'] as $i => $k) {
                $x->set($row, $i + 1, (float) $r[$k], $num);
            }
            $x->set($row, 7, (float) $r['net'], $numB);
        }
        $row++;
        $x->set($row, 0, 'SRI TAX', $cell);
        for ($i = 1; $i <= 7; $i++) {
            $x->set($row, $i, '', $num);
        }
        $row++;
        $x->set($row, 0, 'TOTAL', $head);
        foreach (['basic', 'tax', 'gsis', 'hdmf', 'phic', 'union_dues', 'net'] as $i => $k) {
            $x->set($row, $i + 1, (float) $t[$k], $numB);
        }

        // OTHER ALLOWANCES/ BONUSES: (A–B) beside DE MINIMIS BENEFITS: (D–G)
        $row += 3;
        $top = $row;
        $x->set($row, 0, 'OTHER ALLOWANCES/ BONUSES:', $bold);
        foreach ($d['bonus_rows'] as [$label, $amt]) {
            $row++;
            $x->set($row, 0, $label);
            $x->set($row, 1, (float) $amt, $n);
        }
        $row++; $x->set($row, 0, 'EXCESS OF DE MINIMIS BENEFITS'); $x->set($row, 1, (float) $d['de_minimis_excess'], $n);
        $row++; $x->set($row, 0, 'TOTAL', $bold); $x->set($row, 1, (float) $d['benefits_total'], $n);
        $row++; $x->set($row, 1, -(float) $d['benefit_cap'], $nB);
        $row++; $x->set($row, 0, 'TAXABLE', $bold); $x->set($row, 1, (float) $c['benefits_taxable'], $nB);
        $left = $row;

        $row = $top;
        $x->set($row, 3, 'DE MINIMIS BENEFITS:', $bold);
        $row++;
        $x->set($row, 4, 'ACTUAL', $bold); $x->set($row, 5, 'LIMIT', $bold); $x->set($row, 6, 'EXCESS', $bold);
        foreach ($d['de_minimis'] as $mm) {
            $row++;
            $x->set($row, 3, strtoupper($mm['name']));
            $x->set($row, 4, (float) $mm['actual'], $n); $x->set($row, 5, (float) $mm['limit_entered'], $n); $x->set($row, 6, (float) $mm['excess'], $n);
        }
        $row++;
        $x->set($row, 3, 'TOTAL', $bold);
        $x->set($row, 4, (float) array_sum(array_column($d['de_minimis'], 'actual')), $nB);
        $x->set($row, 5, (float) array_sum(array_column($d['de_minimis'], 'limit_entered')), $nB);
        $x->set($row, 6, (float) $d['de_minimis_excess'], $nB);

        // TAX EXEMPT (A–B), then the computation (A, value in D)
        $row = max($left, $row) + 3;
        $x->set($row, 0, 'TAX EXEMPT', $bold);
        foreach (['GSIS' => 'gsis', 'HDMF' => 'hdmf', 'PHIC' => 'phic', 'UNION DUES' => 'union_dues'] as $label => $k) {
            $row++; $x->set($row, 0, $label); $x->set($row, 1, (float) $d['exempt'][$k], $n);
        }
        $row++; $x->set($row, 0, 'TOTAL', $bold); $x->set($row, 1, (float) $d['exempt']['total'], $nB);

        $row += 2;
        foreach ([['GROSS TAXABLE INCOME', 'regular_taxable', $n, 0], ['INCOME IN EXCESS OF 90,000 TRESHHOLD', 'benefits_taxable', $n, 0],
                  ['TAXABLE INCOME', 'gross_taxable', $n, 0], ['TOTAL TAX DUE', 'tax_due', $n, 0], ['LESS  :  TAX WITHHELD', 'tax_withheld', $n, 0],
                  ['TAX DUE / (REFUND)', 'balance', $nB, $bold]] as [$label, $k, $st, $ls]) {
            $x->set($row, 0, $label, $ls); $x->set($row, 3, (float) $c[$k], $st);
            $row++;
        }

        // the tax table
        $row += 2;
        $x->set($row, 0, 'IF TAXABLE INCOME IS :', $bu); $x->set($row, 2, 'TAX DUE IS:', $bu);
        $row++;
        foreach (self::TAX_TABLE as [$a, $b]) {
            $row++; $x->set($row, 0, $a); $x->set($row, 2, $b === '0' ? 0 : $b);
        }

        $x->colWidth(0, 38);
        foreach ([1, 2, 3, 4, 5, 6, 7] as $col) {
            $x->colWidth($col, 15);
        }
        $x->colWidth(3, 30);

        return $x->render();
    }
}
