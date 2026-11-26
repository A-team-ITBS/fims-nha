<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * Trial Balance print form — the NGAS/COA Trial Balance report the Trial Balance page shows
 * (app/pages/trial-balance-page.php): the NHA title block (logo | NATIONAL HOUSING AUTHORITY / TRIAL BALANCE [— book] /
 * office / as-of date | Pambansang Pabahay + Bagong Pilipinas), then Accounts · Acct. Code · Debit Balances · Credit
 * Balances grouped under a bold major classification row (Assets / Liabilities / Equity / Revenue / Expenses) and an
 * account-group row (a shaded sub-header for Assets; a plain row with its code for the other classes), a Total row and
 * the "I HEREBY CERTIFY …" block with the Head of Accounting Department signature line.
 *
 * Data: the rows the page already loaded (GET /general_ledger/trial-balance → GeneralLedgerService::fetchTrialBalance),
 * sent as the payload, so the PDF is exactly what the page (and its Excel export) shows. A zero prints "-" and a
 * negative amount in brackets, as the Excel template's number format does.
 *
 * No letterhead (the report has its own title block). PDF / HTML only — there is no DOCX layout. Printed through
 * PrintService::print('TB', …).
 */
class TrialBalancePrintService
{
    private const CERTIFICATION = 'I HEREBY CERTIFY that the trial balance is a true and complete statement of all accounts as shown in the ledger '
        . 'prepared and presented in conformity with International Public Sector Accounting Standards (IPSAS).';

    /**
     * Model. Payload (all optional):
     *   fiscal_yr (one year, or '' for every year — file name only), as_of (the date line; default today),
     *   book_name (adds "— <book>" to the title), office (default "MAIN OFFICE"),
     *   rows [{ major_group, account_group, account_group_code, account_title, account_code, total_debit, total_credit }].
     */
    public function model(array $p): array
    {
        $t    = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $rows = [];
        $totD = 0.0;
        $totC = 0.0;
        foreach (is_array($p['rows'] ?? null) ? $p['rows'] : [] as $r) {
            if (!is_array($r)) {
                continue;
            }
            $d = round(PrintService::amount($r['total_debit'] ?? 0), 2);
            $c = round(PrintService::amount($r['total_credit'] ?? 0), 2);
            $totD += $d;
            $totC += $c;
            $rows[] = [
                'major' => PrintService::text($r['major_group'] ?? ''),
                'group' => PrintService::text($r['account_group'] ?? ''),
                'group_code' => PrintService::text($r['account_group_code'] ?? ''),
                'title' => PrintService::text($r['account_title'] ?? ''),
                'code' => PrintService::text($r['account_code'] ?? ''),
                'd' => $d,
                'c' => $c,
            ];
        }

        $fy = $t('fiscal_yr');

        return [
            'office'   => $t('office') !== '' ? $t('office') : 'MAIN OFFICE',
            'book'     => $t('book_name'),
            'as_of'    => $t('as_of') !== '' ? $t('as_of') : date('F j, Y'),
            'rows'     => $rows,
            'total_d'  => round($totD, 2),
            'total_c'  => round($totC, 2),
            'header_parts' => [],
            'footer_text'  => '',
            // the same name the page's Excel export uses: Trial-Balance_<year | all-years>
            'file_base' => 'Trial-Balance_' . (preg_match('/^\d{4}$/', $fy) ? $fy : 'all-years'),
        ];
    }

    /** A4 portrait, 0.5in margins, Arial 9.5pt. $media 'screen' (the preview sheet) or 'print' (the page Dompdf renders). */
    public function html(array $m, string $media = 'screen'): string
    {
        $e   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $num = static fn (float $v) => abs($v) < 0.005 ? '-' : ($v < 0 ? '(' . PrintService::money(abs($v)) . ')' : PrintService::money($v));
        $img = static function (string $file, int $h, string $style = '') {
            $src = PrintService::imageDataUri($file);

            return $src ? '<img src="' . $src . '" style="height:' . $h . 'pt;' . $style . '">' : '';
        };

        // Column widths: Dompdf ignores <colgroup>, so the first row of each table carries them.
        $widths = '<tr class="w"><td style="width:46%;"></td><td style="width:14%;"></td><td style="width:20%;"></td><td style="width:20%;"></td></tr>';

        // Dompdf has no "keep with next", so a heading row could be stranded at the foot of a page with its accounts on the
        // next. The heading rows (major class / account group) are therefore held back and printed together with the first
        // account below them, inside ONE row that cannot be split across pages (a nested table of the same column widths).
        $body = '';
        $pending = '';
        $emit = static function (string $accountRow) use (&$body, &$pending, $widths): void {
            if ($pending === '') {
                $body .= $accountRow;

                return;
            }
            $body .= '<tr class="chunk"><td colspan="4" class="cw"><table class="tb">' . $widths . $pending . $accountRow . '</table></td></tr>';
            $pending = '';
        };
        $lastMajor = null;
        $lastGroup = null;
        foreach ($m['rows'] as $r) {
            if ($r['major'] !== $lastMajor) {
                $pending .= '<tr class="major"><td colspan="4">' . $e($r['major']) . '</td></tr>';
                $lastGroup = null;   // a new major group restarts its own sub-groups
            }
            if ($r['group'] !== '' && $r['group'] !== $lastGroup) {
                // Assets keep the sub-header; every other class lists its group as a plain row with its code
                $pending .= preg_match('/^assets$/i', trim($r['major']))
                    ? '<tr class="group"><td colspan="4">' . $e($r['group']) . '</td></tr>'
                    : '<tr><td>' . $e($r['group']) . '</td><td class="code">' . $e($r['group_code']) . '</td><td></td><td></td></tr>';
            }
            $lastMajor = $r['major'];
            $lastGroup = $r['group'];
            $emit('<tr><td class="acct">' . ($r['title'] !== '' ? $e($r['title']) : '&mdash;') . '</td><td class="code">' . $e($r['code']) . '</td>'
                . '<td class="am">' . $num($r['d']) . '</td><td class="am">' . $num($r['c']) . '</td></tr>');
        }
        if (!$m['rows']) {
            $body = '<tr><td colspan="4" class="none">Nothing posted yet.</td></tr>';
        }
        $body .= '<tr class="total"><td colspan="2">Total</td><td class="am">' . $num($m['total_d']) . '</td><td class="am">' . $num($m['total_c']) . '</td></tr>';

        $title = '<table class="ttl"><tr>'
            . '<td style="width:14%;text-align:center;">' . $img('letterhead/nha-logo.png', 54) . '</td>'
            . '<td style="width:51%;text-align:center;"><div class="b">NATIONAL HOUSING AUTHORITY</div>'
            . '<div class="b">TRIAL BALANCE' . ($m['book'] !== '' ? ' &mdash; ' . $e($m['book']) : '') . '</div>'
            . '<div class="b">' . $e($m['office']) . '</div><div>' . $e($m['as_of']) . '</div></td>'
            . '<td style="width:35%;text-align:center;white-space:nowrap;">' . $img('letterhead/pambansang-pabahay.png', 46, 'margin-right:5pt;')
            . $img('letterhead/bagong-pilipinas.png', 46) . '</td>'
            . '</tr></table>';

        $pageCss = $media === 'print'
            ? '@page{size:A4 portrait;margin:36pt;}body{margin:0;}'
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:523pt;min-height:770pt;margin:0 auto;padding:36pt;box-sizing:content-box;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:9.5pt;color:#000;}'
            . '.b{font-weight:bold;}'
            . 'table.ttl{width:100%;border-collapse:collapse;border:.75pt solid #000;margin-bottom:0;}table.ttl td{padding:7pt 6pt;vertical-align:middle;line-height:1.5;}'
            . 'table.tb{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . 'table.tb th{border:.75pt solid #000;background:#d9e1f2;font-weight:bold;padding:4pt 6pt;white-space:nowrap;}'
            . 'table.tb td{padding:2.5pt 6pt;border-left:.75pt solid #000;border-right:.75pt solid #000;}'
            . 'table.tb tr{page-break-inside:avoid;}'
            . 'table.tb tr.w td{height:0;padding:0 !important;border:0 !important;}'
            . 'table.tb td.cw{padding:0 !important;border:0 !important;}'
            . 'td.am{text-align:right;white-space:nowrap;}td.code{white-space:nowrap;}td.acct{padding-left:16pt !important;}'
            . 'tr.major td{background:#ededed;font-weight:bold;border-top:.75pt solid #000;}'
            . 'tr.group td{font-weight:bold;}'
            . 'tr.total td{font-weight:bold;border-top:.75pt solid #000;border-bottom:3pt double #000;}'
            . 'td.none{text-align:center;padding:24pt 0 !important;}'
            . '.cert{margin-top:28pt;font-size:9.5pt;page-break-inside:avoid;}.cert p{margin:0 0 30pt;text-align:justify;}'
            . '.sig{width:200pt;text-align:center;}.sig .ln{border-top:.75pt solid #000;padding-top:3pt;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '', '')
            . $title
            // Dompdf ignores <colgroup> widths, so the first row of the heading carries them
            . '<table class="tb"><thead><tr><th style="width:46%;">Accounts</th><th style="width:14%;">Acct. Code</th>'
            . '<th style="width:20%;text-align:right;">Debit Balances</th><th style="width:20%;text-align:right;">Credit Balances</th></tr></thead>'
            . '<tbody>' . $body . '</tbody></table>'
            . '<div class="cert"><p>' . $e(self::CERTIFICATION) . '</p>'
            . '<div class="sig"><div class="ln">Signature Over Printed Name</div><div>Head of Accounting Department</div></div></div>'
            . PrintService::bodyClose($media, '');
    }
}
