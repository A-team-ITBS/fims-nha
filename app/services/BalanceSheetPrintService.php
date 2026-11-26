<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * Balance Sheet print form — the Statement of Financial Position the Balance Sheet page shows
 * (app/pages/balance-sheet-page.php), in whichever View is on screen:
 *   Detailed / Condensed → Account Title · Current Year · Prior Year (As Restated) · Increase (Decrease)   (portrait)
 *   Consolidated         → Accounts · one column per book · TOTAL                                          (landscape when there are
 *                                                                                                          more than 4 columns)
 * The NHA title block (logo | the report's title lines | Pambansang Pabahay + Bagong Pilipinas), the table, and the "I HEREBY
 * CERTIFY …" block with the Head of Accounting Department signature line.
 *
 * Data: what the page shows, sent as the payload — the same rows its Excel export is built from — so the PDF, the Excel
 * and the screen can't disagree:
 *   lines   [{ text, bold }]   the title block, top to bottom (agency, statement title, book / office, date, "(In Philippine Peso)")
 *   columns [string, …]        the column headings (the first is the account column)
 *   rows    [{ kind, title, values: [number|null, …] }]   one per table row; `values` has one entry per amount column
 *   kind = major (a section band, no amounts) | group | subgroup | account | detail (an account indented one level further) |
 *          contra | netvalue | subtotal | grand | total (a grand row printed in normal case, not capitals)
 *   widths [percent, …], aligns [left|center|right, …]   optional, one per column (a statement whose columns differ — Cash Flow's Note)
 *   fiscal_yr, view (file name only)
 * A zero prints "-" and a negative in brackets, as the Excel number format does.
 *
 * No letterhead (the report has its own title block). PDF / HTML only — no DOCX layout. Printed through
 * PrintService::print('BS', …); the model chooses the page orientation (PrintService reads `orientation`).
 */
class BalanceSheetPrintService
{
    private const KINDS = ['major', 'group', 'subgroup', 'account', 'detail', 'contra', 'netvalue', 'subtotal', 'grand', 'total'];

    /** Rows that introduce what follows: printed together with the next row, never alone at the foot of a page. */
    private const HEADINGS = ['major', 'group', 'subgroup'];

    private const CERTIFICATION = 'I HEREBY CERTIFY that the foregoing Statement of Financial Position is a true and complete statement of the '
        . 'assets, liabilities and equity as shown in the books of accounts, prepared and presented in conformity with International '
        . 'Public Sector Accounting Standards (IPSAS).';

    // The account column's share of the width by the number of columns; the amount columns split the rest equally.
    private const TITLE_SHARE = [2 => 60, 3 => 46, 4 => 36, 5 => 32, 6 => 30, 7 => 26];

    /**
     * $filePrefix / $certification are what another statement with this layout changes — IncomeStatementPrintService
     * passes its own, as CafPrintService shares CbaPrintService's layout (PrintService calls model($payload) alone).
     */
    public function model(array $p, string $filePrefix = 'Balance-Sheet', string $certification = self::CERTIFICATION): array
    {
        $text = static fn ($v) => PrintService::text($v ?? '');

        $columns = [];
        foreach (is_array($p['columns'] ?? null) ? $p['columns'] : [] as $c) {
            $columns[] = $text($c);
        }
        $ncols = max(count($columns), 2);
        $columns = array_pad($columns, $ncols, '');
        $columns[0] = $columns[0] !== '' ? $columns[0] : 'Account Title';

        $lines = [];
        foreach (is_array($p['lines'] ?? null) ? $p['lines'] : [] as $l) {
            $t = is_array($l) ? $text($l['text'] ?? '') : '';
            if ($t !== '') {
                $lines[] = ['text' => $t, 'bold' => !empty($l['bold'])];
            }
        }

        $rows = [];
        foreach (is_array($p['rows'] ?? null) ? $p['rows'] : [] as $r) {
            if (!is_array($r)) {
                continue;
            }
            $kind = in_array($r['kind'] ?? '', self::KINDS, true) ? $r['kind'] : 'account';
            $values = [];
            $given = is_array($r['values'] ?? null) ? array_values($r['values']) : [];
            for ($i = 0; $i < $ncols - 1; $i++) {   // exactly one value per amount column
                $v = $given[$i] ?? null;
                $values[] = ($v === null || $v === '') ? null : round(PrintService::amount($v), 2);
            }
            $rows[] = ['kind' => $kind, 'title' => $text($r['title'] ?? ''), 'values' => $values];
        }

        // Optional per-column layout, for a statement whose columns differ from the Balance Sheet's (Cash Flow's blank Note column):
        // `widths` percentages (one per column, summing to about 100) and `aligns` left | center | right.
        $widths = [];
        foreach (is_array($p['widths'] ?? null) ? array_values($p['widths']) : [] as $x) {
            $widths[] = is_numeric($x) && $x > 0 ? (float) $x : 0;
        }
        if (count($widths) !== $ncols || in_array(0, $widths, true)) {
            $widths = [];   // not usable → the default split
        }
        $aligns = [];
        foreach (is_array($p['aligns'] ?? null) ? array_values($p['aligns']) : [] as $a) {
            $aligns[] = in_array($a, ['left', 'center', 'right'], true) ? $a : 'right';
        }
        if (count($aligns) !== $ncols) {
            $aligns = [];
        }

        $fy   = $text($p['fiscal_yr'] ?? '');
        $view = strtolower(preg_replace('/[^A-Za-z]/', '', $text($p['view'] ?? '')) ?? '');

        return [
            'lines'       => $lines,
            'columns'     => $columns,
            'widths'      => $widths,
            'aligns'      => $aligns,
            'rows'        => $rows,
            'orientation' => $ncols > 4 ? 'landscape' : 'portrait',   // read by PrintService::print()
            'certification' => $certification,
            'header_parts' => [],
            'footer_text'  => '',
            // the same name the page's Excel export uses: <prefix>_<year>_<view>
            'file_base'   => $filePrefix . '_' . (preg_match('/^\d{4}$/', $fy) ? $fy : 'all-years') . '_' . ($view !== '' ? $view : 'detailed'),
        ];
    }

    /** A4, 0.5in margins, Arial. $media 'screen' (the preview sheet) or 'print' (the page Dompdf renders). */
    public function html(array $m, string $media = 'screen'): string
    {
        $e         = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $ncols     = count($m['columns']);
        $landscape = $m['orientation'] === 'landscape';
        $fontPt    = $landscape ? 8.5 : 9.5;
        $num = static fn (?float $v) => $v === null ? '' : (abs($v) < 0.005 ? '-' : ($v < 0 ? '(' . PrintService::money(abs($v)) . ')' : PrintService::money($v)));
        $img = static function (string $file, int $h, string $style = '') {
            $src = PrintService::imageDataUri($file);

            return $src ? '<img src="' . $src . '" style="height:' . $h . 'pt;' . $style . '">' : '';
        };

        // Column widths (Dompdf ignores <colgroup>, so the first row of each table carries them).
        $titleShare = self::TITLE_SHARE[$ncols] ?? 24;
        $amountShare = round((100 - $titleShare) / ($ncols - 1), 2);
        $w = [$titleShare] + array_fill(1, $ncols - 1, $amountShare);
        if (!empty($m['widths']) && count($m['widths']) === $ncols) {
            $w = $m['widths'];
        }
        $aligns = (!empty($m['aligns']) && count($m['aligns']) === $ncols) ? $m['aligns'] : array_merge(['left'], array_fill(0, $ncols - 1, 'right'));
        $widths = '<tr class="w">' . implode('', array_map(static fn ($x) => '<td style="width:' . $x . '%;"></td>', $w)) . '</tr>';

        $rowHtml = static function (array $r) use ($e, $num, $ncols, $aligns): string {
            if ($r['kind'] === 'major') {
                return '<tr class="major"><td colspan="' . $ncols . '">' . $e($r['title']) . '</td></tr>';
            }
            $cells = '<td class="t">' . ($r['title'] !== '' ? $e($r['title']) : '&nbsp;') . '</td>';
            foreach (array_values($r['values']) as $i => $v) {
                $al = $aligns[$i + 1];
                $cells .= '<td class="am"' . ($al !== 'right' ? ' style="text-align:' . $al . ';"' : '') . '>' . ($v === null ? '&nbsp;' : $num($v)) . '</td>';
            }

            return '<tr class="' . $r['kind'] . '">' . $cells . '</tr>';
        };

        // Dompdf has no "keep with next", so a heading row (section / group / subgroup) could be left alone at the foot of a
        // page with what it introduces on the next. Headings are held back and printed together with the next row inside ONE
        // row that cannot be split across pages (a nested table of the same column widths).
        $body = '';
        $pending = '';
        foreach ($m['rows'] as $r) {
            if (in_array($r['kind'], self::HEADINGS, true)) {
                $pending .= $rowHtml($r);

                continue;
            }
            if ($pending === '') {
                $body .= $rowHtml($r);
            } else {
                $body .= '<tr class="chunk"><td colspan="' . $ncols . '" class="cw"><table class="bs">' . $widths . $pending . $rowHtml($r) . '</table></td></tr>';
                $pending = '';
            }
        }
        $body .= $pending;   // headings with nothing after them (none in practice)
        if ($body === '') {
            $body = '<tr><td colspan="' . $ncols . '" class="none">Nothing to show.</td></tr>';
        }

        $titleLines = '';
        foreach ($m['lines'] as $l) {
            $titleLines .= '<div' . ($l['bold'] ? ' class="b"' : '') . '>' . $e($l['text']) . '</div>';
        }
        $title = '<table class="ttl"><tr>'
            . '<td style="width:14%;text-align:center;">' . $img('letterhead/nha-logo.png', 54) . '</td>'
            . '<td style="width:51%;text-align:center;">' . $titleLines . '</td>'
            . '<td style="width:35%;text-align:center;white-space:nowrap;">' . $img('letterhead/pambansang-pabahay.png', 46, 'margin-right:5pt;')
            . $img('letterhead/bagong-pilipinas.png', 46) . '</td>'
            . '</tr></table>';

        $head = '<tr>';
        foreach ($m['columns'] as $i => $c) {
            $head .= '<th style="width:' . $w[$i] . '%;' . ($aligns[$i] !== 'left' ? 'text-align:' . $aligns[$i] . ';' : '') . '">' . $e($c) . '</th>';
        }
        $head .= '</tr>';

        $contentPt = $landscape ? 770 : 523;   // A4 less 0.5in margins
        $pageCss = $media === 'print'
            ? '@page{size:A4 ' . $m['orientation'] . ';margin:36pt;}body{margin:0;}'
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:' . $contentPt . 'pt;min-height:' . ($landscape ? 480 : 770) . 'pt;margin:0 auto;padding:36pt;box-sizing:content-box;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:' . $fontPt . 'pt;color:#000;}'
            . '.b{font-weight:bold;}'
            . 'table.ttl{width:100%;border-collapse:collapse;border:.75pt solid #000;}table.ttl td{padding:7pt 6pt;vertical-align:middle;line-height:1.5;}'
            . 'table.bs{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . 'table.bs th{border:.75pt solid #000;background:#d9e1f2;font-weight:bold;font-size:' . ($fontPt - 1.5) . 'pt;padding:4pt 6pt;white-space:nowrap;}'
            . 'table.bs td{padding:2.5pt 6pt;border-left:.75pt solid #000;border-right:.75pt solid #000;}'
            . 'table.bs tr{page-break-inside:avoid;}'
            . 'table.bs tr.w td{height:0;padding:0 !important;border:0 !important;}'
            . 'table.bs td.cw{padding:0 !important;border:0 !important;}'
            . 'td.am{text-align:right;white-space:nowrap;}'
            // the page's own hierarchy (app/fims-css/fims.css .fims-report-table-*): band, group, subgroup, accounts, rules
            . 'tr.major td{background:#d6d9e6;font-weight:bold;text-transform:uppercase;border-top:.75pt solid #000;padding:3.5pt 6pt;}'
            . 'tr.group td{background:#f2f3f8;font-weight:bold;text-transform:uppercase;}'
            . 'tr.subgroup td{font-weight:bold;}tr.subgroup td.t{padding-left:16pt;}'
            . 'tr.account td.t,tr.contra td.t,tr.netvalue td.t{padding-left:28pt;}'
            . 'tr.detail td.t{padding-left:46pt;}'
            . 'tr.contra td.t{font-style:italic;}'
            . 'tr.netvalue td,tr.subtotal td{font-weight:bold;border-top:.5pt solid #777;}'
            . 'tr.subtotal td.t{padding-left:6pt;}'
            . 'tr.grand td,tr.total td{background:#f2f3f8;font-weight:bold;border-top:1.2pt solid #000;border-bottom:1.2pt solid #000;}'
            . 'tr.grand td{text-transform:uppercase;}'
            . 'td.none{text-align:center;padding:24pt 0 !important;}'
            . '.cert{margin-top:28pt;page-break-inside:avoid;}.cert p{margin:0 0 30pt;text-align:justify;}'
            . '.sig{width:200pt;text-align:center;}.sig .ln{border-top:.75pt solid #000;padding-top:3pt;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '', '')
            . $title
            . '<table class="bs"><thead>' . $head . '</thead><tbody>' . $body . '</tbody></table>'
            . '<div class="cert"><p>' . $e($m['certification'] ?? self::CERTIFICATION) . '</p>'
            . '<div class="sig"><div class="ln">Signature Over Printed Name</div><div>Head of Accounting Department</div></div></div>'
            . PrintService::bodyClose($media, '');
    }
}
