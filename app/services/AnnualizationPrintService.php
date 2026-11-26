<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * ANNUALIZATION WORKSHEET — the client's "Annualization of Income for Year …" template (ANNUALIZATION TEMPLATE.xlsx, sheet
 * EMP ANZN) for ONE employee and fiscal year, from the payroll (PayrollManagementService::annualizationWorksheet()).
 * Print type ANNUALWS, PDF only (Dompdf through GlobalService::renderPdf()); the same sheet as Excel through xlsx()
 * (XlsxWriter) — the print preview's "Export to Excel" (POST /print/annualws/excel).
 *
 * Payload: { fiscal_yr, employee_id }.
 * Layout (as the template): title; NAME / DEPT; the monthly table — BASIC · TAX · GSIS · HDMF · PHIC · UNION DUES · TOTAL
 * (net) — with its TOTAL row; OTHER ALLOWANCES / BONUSES (each 13th month / other-benefit item + EXCESS OF DE MINIMIS
 * BENEFITS, TOTAL, less 90,000, TAXABLE) beside DE MINIMIS BENEFITS (actual / limit / excess); TAX EXEMPT (GSIS, HDMF,
 * PHIC, union dues, total); the computation (income — basic less contributions; income in excess of the 90,000
 * threshold; gross taxable income; total tax due; less tax withheld; tax due / (refund)); the TRAIN tax table.
 * Not copied from the client's sheet: its "BIR FORM 2316" (previous employer), "SCA – DV#…" and "SRI TAX" rows — those
 * aren't in the monthly payroll (parked); its UCA excess formula (6,000 against a 7,000 limit) and the de minimis excess
 * total that skipped Loyalty.
 */
class AnnualizationPrintService
{
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
        $m   = static fn ($v) => (float) $v != 0.0 ? number_format((float) $v, 2) : '-';
        $neg = static fn ($v) => (float) $v < 0 ? '(' . number_format(abs((float) $v), 2) . ')' : number_format((float) $v, 2);
        $t   = $d['totals'];
        $c   = $d['computation'];

        $months = '';
        foreach ($d['months'] as $r) {
            $months .= '<tr><td>' . $e($r['month_name']) . '</td><td class="r">' . $m($r['basic']) . '</td><td class="r">' . $m($r['tax'])
                . '</td><td class="r">' . $m($r['gsis']) . '</td><td class="r">' . $m($r['hdmf']) . '</td><td class="r">' . $m($r['phic'])
                . '</td><td class="r">' . $m($r['union_dues']) . '</td><td class="r">' . $neg($r['net']) . '</td></tr>';
        }
        $benefits = '';
        foreach ($d['benefits'] as $b) {
            $benefits .= '<tr><td>' . $e(strtoupper($b['name'])) . '</td><td class="r">' . $m($b['amount']) . '</td></tr>';
        }
        $benefits .= '<tr><td>EXCESS OF DE MINIMIS BENEFITS</td><td class="r">' . $m($d['de_minimis_excess']) . '</td></tr>'
            . '<tr class="b"><td>TOTAL</td><td class="r tl">' . $m($d['benefits_total']) . '</td></tr>'
            . '<tr><td>LESS: EXEMPT (13TH MONTH &amp; OTHER BENEFITS)</td><td class="r">(' . number_format($d['benefit_cap'], 2) . ')</td></tr>'
            . '<tr class="b"><td>TAXABLE</td><td class="r tl">' . $m($c['benefits_taxable']) . '</td></tr>';
        $deMin = '';
        foreach ($d['de_minimis'] as $x) {
            $deMin .= '<tr><td>' . $e(strtoupper($x['name'])) . (!empty($x['limit_note']) ? ' <span class="muted">(' . $e($x['limit_note']) . ')</span>' : '')
                . '</td><td class="r">' . $m($x['actual']) . '</td><td class="r">'
                . $m($x['limit']) . '</td><td class="r">' . $m($x['excess']) . '</td></tr>';
        }
        $deMin = $deMin ?: '<tr><td colspan="4" class="muted">No de minimis benefit set up</td></tr>';
        $deMinTot = array_sum(array_column($d['de_minimis'], 'actual'));
        $deMin .= '<tr class="b"><td>TOTAL</td><td class="r tl">' . $m($deMinTot) . '</td><td></td><td class="r tl">' . $m($d['de_minimis_excess']) . '</td></tr>';

        $taxTable = '';
        $tbl = [
            ['Not over 250,000', '0'],
            ['Over 250,000 but not over 400,000', '15% of the excess over 250,000'],
            ['Over 400,000 but not over 800,000', 'P 22,500 + 20% of the excess over 400,000'],
            ['Over 800,000 but not over 2,000,000', 'P 102,500 + 25% of the excess over 800,000'],
            ['Over 2,000,000 but not over 8,000,000', 'P 402,500 + 30% of the excess over 2,000,000'],
            ['Over 8,000,000', 'P 2,202,500 + 35% of the excess over 8,000,000'],
        ];
        foreach ($tbl as [$a, $b]) {
            $taxTable .= '<tr><td>' . $a . '</td><td>' . $b . '</td></tr>';
        }

        $body = '<div class="title">ANNUALIZATION OF INCOME FOR YEAR ' . $e($d['year']) . '</div>'
            . '<table class="hd"><tr><td style="width:60px;">NAME:</td><td class="u">' . $e($d['employee_name']) . '</td>'
            . '<td style="width:70px;" class="r">EMP NO.:</td><td class="u" style="width:110px;">' . $e($d['employee']['employee_no']) . '</td></tr>'
            . '<tr><td>DEPT:</td><td class="u">' . $e($d['employee']['department']) . '</td><td class="r">TIN:</td><td class="u">' . $e($d['employee']['tin']) . '</td></tr></table>'
            . '<table class="g"><thead><tr><th style="width:18%;">' . $e($d['year']) . '</th><th>BASIC</th><th>TAX</th><th>GSIS</th><th>HDMF</th><th>PHIC</th><th>UNION DUES</th><th>TOTAL</th></tr></thead><tbody>'
            . '<tr><td>BIR FORM 2316</td><td class="r">-</td><td class="r">-</td><td class="r">-</td><td class="r">-</td><td class="r">-</td><td class="r">-</td><td class="r">-</td></tr>'
            . $months
            . '<tr class="b"><td>TOTAL</td><td class="r tl">' . $m($t['basic']) . '</td><td class="r tl">' . $m($t['tax']) . '</td><td class="r tl">'
            . $m($t['gsis']) . '</td><td class="r tl">' . $m($t['hdmf']) . '</td><td class="r tl">' . $m($t['phic']) . '</td><td class="r tl">'
            . $m($t['union_dues']) . '</td><td class="r tl">' . $neg($t['net']) . '</td></tr></tbody></table>'
            . '<table class="two"><tr><td style="width:47%;vertical-align:top;">'
            . '<div class="sec">OTHER ALLOWANCES / BONUSES:</div><table class="s">' . $benefits . '</table>'
            . '</td><td style="width:6%;"></td><td style="vertical-align:top;">'
            . '<div class="sec">DE MINIMIS BENEFITS:</div><table class="s"><tr class="h"><td></td><td class="r">ACTUAL</td><td class="r">LIMIT</td><td class="r">EXCESS</td></tr>' . $deMin . '</table>'
            . '</td></tr></table>'
            . '<table class="two"><tr><td style="width:47%;vertical-align:top;">'
            . '<div class="sec">TAX EXEMPT</div><table class="s">'
            . '<tr><td>GSIS</td><td class="r">' . $m($d['exempt']['gsis']) . '</td></tr><tr><td>HDMF</td><td class="r">' . $m($d['exempt']['hdmf']) . '</td></tr>'
            . '<tr><td>PHIC</td><td class="r">' . $m($d['exempt']['phic']) . '</td></tr><tr><td>UNION DUES</td><td class="r">' . $m($d['exempt']['union_dues']) . '</td></tr>'
            . '<tr class="b"><td>TOTAL</td><td class="r tl">' . $m($d['exempt']['total']) . '</td></tr></table>'
            . '</td><td style="width:6%;"></td><td style="vertical-align:top;">'
            . '<div class="sec">GROSS TAXABLE INCOME</div><table class="s">'
            . '<tr><td>INCOME (BASIC LESS TAX EXEMPT)</td><td class="r">' . $neg($c['regular_taxable']) . '</td></tr>'
            . '<tr><td>INCOME IN EXCESS OF 90,000 THRESHOLD</td><td class="r">' . $m($c['benefits_taxable']) . '</td></tr>'
            . '<tr class="b"><td>TAXABLE INCOME</td><td class="r tl">' . $m($c['gross_taxable']) . '</td></tr>'
            . '<tr><td>TOTAL TAX DUE</td><td class="r">' . $m($c['tax_due']) . '</td></tr>'
            . '<tr><td>LESS: TAX WITHHELD</td><td class="r">' . $m($c['tax_withheld']) . '</td></tr>'
            . '<tr class="b"><td>TAX DUE / (REFUND)</td><td class="r tl dbl">' . $neg($c['balance']) . '</td></tr></table>'
            . '</td></tr></table>'
            . '<div class="sec" style="margin-top:14px;">TAX TABLE (TRAIN, 2023 ONWARD)</div>'
            . '<table class="g tt"><thead><tr><th style="text-align:left;">IF TAXABLE INCOME IS:</th><th style="text-align:left;">TAX DUE IS:</th></tr></thead><tbody>' . $taxTable . '</tbody></table>';

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . '@page{size:letter portrait;margin:42pt 40pt;}body{font-family:Helvetica,Arial,sans-serif;font-size:8.5pt;color:#000;}'
            . '.title{text-align:center;font-size:12pt;font-weight:bold;margin:0 0 12pt;}'
            . '.hd{width:100%;border-collapse:collapse;margin-bottom:8pt;}.hd td{padding:2pt 3pt;font-weight:bold;}.hd .u{border-bottom:.6pt solid #000;font-weight:normal;}'
            . '.g{width:100%;border-collapse:collapse;}.g th,.g td{border:.6pt solid #000;padding:2.5pt 4pt;}.g th{background:#eee;font-size:8pt;}'
            . '.two{width:100%;border-collapse:collapse;margin-top:12pt;}.sec{font-weight:bold;margin:0 0 4pt;}'
            . '.s{width:100%;border-collapse:collapse;}.s td{padding:2pt 3pt;}.s .h td{font-weight:bold;font-size:7.5pt;}'
            . '.r{text-align:right;white-space:nowrap;}.b td{font-weight:bold;}.tl{border-top:.6pt solid #000;}.dbl{border-bottom:2pt double #000;}'
            . '.muted{color:#666;font-style:italic;}.tt td{font-size:8pt;}'
            . '</style></head><body>' . $body . '</body></html>';
    }

    /** The worksheet as an .xlsx (one sheet, the template's layout) — for the print preview's Export to Excel. */
    public function xlsx(array $d): string
    {
        $x     = new XlsxWriter('EMP ANZN');
        $bold  = $x->style(['font' => ['bold' => true]]);
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
        $x->set(2, 0, 'NAME:', $bold);  $x->set(2, 1, $d['employee_name']);
        $x->set(2, 5, 'EMP NO.:', $bold); $x->set(2, 6, (string) $d['employee']['employee_no']);
        $x->set(3, 0, 'DEPT:', $bold);  $x->set(3, 1, (string) $d['employee']['department']);
        $x->set(3, 5, 'TIN:', $bold);   $x->set(3, 6, (string) $d['employee']['tin']);

        $row = 5;
        foreach ([(string) $d['year'], 'BASIC', 'TAX', 'GSIS', 'HDMF', 'PHIC', 'UNION DUES', 'TOTAL'] as $i => $h) {
            $x->set($row, $i, $h, $head);
        }
        $row++;
        $x->set($row, 0, 'BIR FORM 2316', $cell);
        for ($i = 1; $i <= 7; $i++) {
            $x->set($row, $i, 0, $num);
        }
        foreach ($d['months'] as $r) {
            $row++;
            $x->set($row, 0, $r['month_name'], $cell);
            foreach (['basic', 'tax', 'gsis', 'hdmf', 'phic', 'union_dues', 'net'] as $i => $k) {
                $x->set($row, $i + 1, (float) $r[$k], $num);
            }
        }
        $row++;
        $x->set($row, 0, 'TOTAL', $head);
        foreach (['basic', 'tax', 'gsis', 'hdmf', 'phic', 'union_dues', 'net'] as $i => $k) {
            $x->set($row, $i + 1, (float) $t[$k], $numB);
        }

        // OTHER ALLOWANCES / BONUSES (A–B) beside DE MINIMIS (D–G)
        $row += 3;
        $top = $row;
        $x->set($row, 0, 'OTHER ALLOWANCES/ BONUSES:', $bold);
        foreach ($d['benefits'] as $b) {
            $row++;
            $x->set($row, 0, strtoupper($b['name']));
            $x->set($row, 1, (float) $b['amount'], $n);
        }
        $row++; $x->set($row, 0, 'EXCESS OF DE MINIMIS BENEFITS'); $x->set($row, 1, (float) $d['de_minimis_excess'], $n);
        $row++; $x->set($row, 0, 'TOTAL', $bold); $x->set($row, 1, (float) $d['benefits_total'], $nB);
        $row++; $x->set($row, 0, 'LESS: EXEMPT (13TH MONTH & OTHER BENEFITS)'); $x->set($row, 1, -(float) $d['benefit_cap'], $n);
        $row++; $x->set($row, 0, 'TAXABLE', $bold); $x->set($row, 1, (float) $c['benefits_taxable'], $nB);
        $left = $row;

        $row = $top;
        $x->set($row, 3, 'DE MINIMIS BENEFITS:', $bold);
        $row++;
        $x->set($row, 4, 'ACTUAL', $bold); $x->set($row, 5, 'LIMIT', $bold); $x->set($row, 6, 'EXCESS', $bold);
        foreach ($d['de_minimis'] as $m) {
            $row++;
            $x->set($row, 3, strtoupper($m['name']) . (!empty($m['limit_note']) ? ' (' . $m['limit_note'] . ')' : ''));
            $x->set($row, 4, (float) $m['actual'], $n); $x->set($row, 5, (float) $m['limit'], $n); $x->set($row, 6, (float) $m['excess'], $n);
        }
        $row++;
        $x->set($row, 3, 'TOTAL', $bold);
        $x->set($row, 4, (float) array_sum(array_column($d['de_minimis'], 'actual')), $nB);
        $x->set($row, 6, (float) $d['de_minimis_excess'], $nB);

        // TAX EXEMPT (A–B) beside GROSS TAXABLE INCOME (D–G)
        $row = max($left, $row) + 3;
        $top = $row;
        $x->set($row, 0, 'TAX EXEMPT', $bold);
        foreach (['GSIS' => 'gsis', 'HDMF' => 'hdmf', 'PHIC' => 'phic', 'UNION DUES' => 'union_dues'] as $label => $k) {
            $row++; $x->set($row, 0, $label); $x->set($row, 1, (float) $d['exempt'][$k], $n);
        }
        $row++; $x->set($row, 0, 'TOTAL', $bold); $x->set($row, 1, (float) $d['exempt']['total'], $nB);

        $row = $top;
        $x->set($row, 3, 'GROSS TAXABLE INCOME', $bold);
        foreach ([['INCOME (BASIC LESS TAX EXEMPT)', 'regular_taxable', $n], ['INCOME IN EXCESS OF 90,000 THRESHOLD', 'benefits_taxable', $n],
                  ['TAXABLE INCOME', 'gross_taxable', $nB], ['TOTAL TAX DUE', 'tax_due', $n], ['LESS: TAX WITHHELD', 'tax_withheld', $n],
                  ['TAX DUE / (REFUND)', 'balance', $nB]] as [$label, $k, $st]) {
            $row++; $x->set($row, 3, $label, $st === $nB ? $bold : 0); $x->set($row, 6, (float) $c[$k], $st);
        }

        // TRAIN tax table
        $row += 3;
        $x->set($row, 0, 'IF TAXABLE INCOME IS :', $bold); $x->set($row, 3, 'TAX DUE IS:', $bold);
        foreach ([['Not over 250,000', '0'], ['Over 250,000 but not over 400,000', '15% of the excess over 250,000'],
                  ['Over 400,000 but not over 800,000', 'P 22,500 + 20% of the excess over 400,000'],
                  ['Over 800,000 but not over 2,000,000', 'P 102,500 + 25% of the excess over 800,000'],
                  ['Over 2,000,000 but not over 8,000,000', 'P 402,500 + 30% of the excess over 2,000,000'],
                  ['Over 8,000,000', 'P 2,202,500 + 35% of the excess over 8,000,000']] as [$a, $b]) {
            $row++; $x->set($row, 0, $a); $x->set($row, 3, $b);
        }

        $x->colWidth(0, 34);
        foreach ([1, 2, 3, 4, 5, 6, 7] as $col) {
            $x->colWidth($col, 15);
        }
        $x->colWidth(3, 30);

        return $x->render();
    }
}
