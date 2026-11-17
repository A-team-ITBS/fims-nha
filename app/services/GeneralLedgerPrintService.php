<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * General Ledger print form — one account's ledger card, as the official GENERAL LEDGER sheet: Entity Name / Account
 * Title / Account Code / Sheet No., then Date (month | day) · Particulars · Ref. · Amount (Debit · Credit · Balance).
 *
 * It prints the MONTHLY SUMMARY the official sheet carries, not every posting: per month, one line per journal the
 * account was posted from — Disbursements (CKDJ / ADADJ), Collections & Deposits (CRDJ), Various Adjustments (GJ) —
 * with that journal's debit and credit totals for the month, the balance on the month's last line, and the
 * "Dec 31 Balance" totals at the end (the opening balance included in its side, as on the sheet).
 *
 * Data: the General Ledger page's account ledger (GET /general_ledger/ledger — GeneralLedgerService::fetchGeneralLedger),
 * mapped by the preview's prp_glPayload(). The journal of a posting comes from its doc_ref_no, the same rule as the JEV's
 * journal label: "CHECK NO. …" → CKDJ, "ADA …" → ADADJ, "OR …" → CRDJ, anything else → GJ.
 *
 * No letterhead (the sheet has its own title). Printed through PrintService::print('GL', …); DOCX: scripts/gen_gl_docx.js.
 */
class GeneralLedgerPrintService
{
    // The sheet always shows at least this many rows (blank ones fill the page, as on the printed form).
    public const MIN_ROWS = 30;

    // The journals, in the order they are listed within a month, and their Particulars wording.
    private const JOURNALS = [
        'CKDJ'  => 'Disbursements',
        'ADADJ' => 'Disbursements',
        'CRDJ'  => 'Collections & Deposits',
        'GJ'    => 'Various Adjustments',
    ];

    private const MONTHS = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /**
     * Model. Payload (all optional):
     *   account_code, account_title, entity_name (blank by default — filled in by hand), sheet_no,
     *   fiscal_year (the year printed under Date, and the closing "Dec 31"; default: the first posting's year),
     *   debit_normal (true default — Asset / Expense; false = credit-normal: the balance is credit - debit),
     *   opening_balance (the "Jan 1 Balance" line; none → no opening line, the balance starts at 0),
     *   lines [{ posting_date (Y-m-d), debit, credit, doc_ref_no?, journal? (CKDJ | ADADJ | CRDJ | GJ — overrides) }].
     */
    public function model(array $p): array
    {
        $t      = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $money  = static fn (float $v) => abs($v) >= 0.005 ? PrintService::money($v) : '';
        $sign   = in_array($p['debit_normal'] ?? true, [false, 'false', 0, '0'], true) ? -1 : 1;   // balance = sign × (debit − credit)
        $hasOpening = isset($p['opening_balance']) && $p['opening_balance'] !== '' && $p['opening_balance'] !== null;
        $opening    = $hasOpening ? (float) str_replace(',', '', (string) $p['opening_balance']) : 0.0;

        // postings → month (Y-m) → journal → sums
        $months = [];
        foreach (array_values(is_array($p['lines'] ?? null) ? $p['lines'] : []) as $l) {
            $date = substr(PrintService::text($l['posting_date'] ?? ''), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            $journal = strtoupper(PrintService::text($l['journal'] ?? '')) ?: self::journalOf((string) ($l['doc_ref_no'] ?? ''));
            if (!isset(self::JOURNALS[$journal])) {
                $journal = 'GJ';
            }
            $ym = substr($date, 0, 7);
            $months[$ym][$journal]['d'] = ($months[$ym][$journal]['d'] ?? 0) + PrintService::amount($l['debit'] ?? 0);
            $months[$ym][$journal]['c'] = ($months[$ym][$journal]['c'] ?? 0) + PrintService::amount($l['credit'] ?? 0);
        }
        ksort($months);

        $year = $t('fiscal_year') ?: ($months ? substr((string) array_key_first($months), 0, 4) : date('Y'));
        $rows = [];
        $balance = $opening;
        $totD = $totC = 0.0;
        if ($hasOpening) {
            $rows[] = ['kind' => 'line', 'mon' => 'Jan', 'day' => '1', 'text' => 'Balance', 'ref' => '', 'd' => '', 'c' => '', 'bal' => $money($opening)];
        }
        foreach ($months as $ym => $journals) {
            [$y, $m] = array_map('intval', explode('-', $ym));
            $day   = date('t', mktime(0, 0, 0, $m, 1, $y));   // the month's last day (no ext-calendar on the server)
            $order = array_values(array_intersect(array_keys(self::JOURNALS), array_keys($journals)));
            if ($rows && !($hasOpening && count($rows) === 1 && $m === 1)) {
                $rows[] = ['kind' => 'gap'];   // a blank line between months, as on the sheet (none under the Jan 1 opening)
            }
            foreach ($order as $i => $j) {
                $d = round($journals[$j]['d'], 2);
                $c = round($journals[$j]['c'], 2);
                $totD += $d;
                $totC += $c;
                $balance += $sign * ($d - $c);
                $last = $i === count($order) - 1;
                $showMon = $i === 0 && !($hasOpening && $m === 1);   // January's name is already on the opening line
                $rows[] = ['kind' => 'line', 'mon' => $showMon ? self::MONTHS[$m] : '', 'day' => $day, 'text' => self::JOURNALS[$j], 'ref' => $j,
                    'd' => $money($d), 'c' => $money($c), 'bal' => $last ? $money($balance) : ''];
            }
        }

        // the closing line: the totals with the opening balance on its own side (as on the sheet), and the ending balance
        if ($opening >= 0) {
            $sign > 0 ? $totD += $opening : $totC += $opening;
        } else {
            $sign > 0 ? $totC += -$opening : $totD += -$opening;
        }
        $code = $t('account_code');

        return [
            'entity'     => $t('entity_name'),
            'title'      => mb_strtoupper($t('account_title')),
            'code'       => $code,
            'sheet'      => $t('sheet_no'),
            'year'       => $year,
            'rows'       => $rows,
            'min_rows'   => self::MIN_ROWS,
            'closing'    => ['mon' => 'Dec', 'day' => '31', 'd' => PrintService::money($totD), 'c' => PrintService::money($totC), 'bal' => PrintService::money($balance)],
            'header_parts' => [],
            'footer_text'  => '',
            'file_base'  => 'GL-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', $code) ?: 'ACCOUNT') . '-' . $year,
        ];
    }

    /** The journal a posting belongs to, from its document reference — "CHECK…" CKDJ, "ADA…" ADADJ, "OR…" CRDJ, else GJ. */
    public static function journalOf(string $docRef): string
    {
        $r = strtoupper(trim($docRef));
        if (str_starts_with($r, 'CHECK')) {
            return 'CKDJ';
        }
        if (str_starts_with($r, 'ADA')) {
            return 'ADADJ';
        }
        if (preg_match('/^(OR|O\.R\.|OFFICIAL RECEIPT)\b/', $r)) {
            return 'CRDJ';
        }

        return 'GJ';
    }

    /**
     * Letter portrait (612 × 792pt), 0.5in sides (540pt), Arial 9pt. The title, the account block and the column
     * headings are the table's <thead>, so they repeat on every page. Mirrors scripts/gen_gl_docx.js.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $v = static fn (string $s) => $s !== '' ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : '&nbsp;';
        $cell = static fn (array $r) => '<td class="mon">' . $v($r['mon'] ?? '') . '</td><td class="day">' . $v($r['day'] ?? '') . '</td>'
            . '<td class="part">' . $v($r['text'] ?? '') . '</td><td class="ref">' . $v($r['ref'] ?? '') . '</td>'
            . '<td class="am">' . $v($r['d'] ?? '') . '</td><td class="am">' . $v($r['c'] ?? '') . '</td><td class="am">' . $v($r['bal'] ?? '') . '</td>';

        $body  = '<tr class="yr"><td colspan="2" class="c b">' . $e($m['year']) . '</td><td></td><td></td><td></td><td></td><td></td></tr>';
        $count = 1;
        foreach ($m['rows'] as $r) {
            $body .= '<tr>' . ($r['kind'] === 'gap' ? $cell([]) : $cell($r)) . '</tr>';
            $count++;
        }
        for (; $count < $m['min_rows']; $count++) {
            $body .= '<tr>' . $cell([]) . '</tr>';
        }
        $cl = $m['closing'];
        $body .= '<tr class="close">' . $cell(['mon' => $cl['mon'], 'day' => $cl['day'], 'text' => 'Balance', 'd' => $cl['d'], 'c' => $cl['c'], 'bal' => $cl['bal']]) . '</tr>';

        $head = '<tr class="h"><td colspan="7" class="title">GENERAL LEDGER</td></tr>'
            . '<tr class="h"><td colspan="7"><table class="acct">'
            . '<tr><td class="lbl" style="width:14%;">Entity Name:</td><td class="ul" style="width:42%;">' . $v($m['entity']) . '</td><td style="width:4%;"></td>'
            . '<td class="lbl" style="width:14%;">Account Code:</td><td class="ul" style="width:26%;">' . $v($m['code']) . '</td></tr>'
            . '<tr><td class="lbl">Account Title:</td><td class="ul">' . $v($m['title']) . '</td><td></td>'
            . '<td class="lbl">Sheet No.:</td><td class="ul">' . $v($m['sheet']) . '</td></tr>'
            . '</table></td></tr>'
            . '<tr class="h"><td colspan="7" class="gap"></td></tr>'
            . '<tr class="cols"><th colspan="2" rowspan="2">Date</th><th rowspan="2">Particulars</th><th rowspan="2">Ref.</th><th colspan="3">Amount</th></tr>'
            . '<tr class="cols"><th>Debit</th><th>Credit</th><th>Balance</th></tr>';

        $pageCss = $media === 'print'
            ? '@page{size:letter portrait;margin:36pt;}body{margin:0;}'
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:612pt;min-height:792pt;margin:0 auto;padding:36pt;box-sizing:border-box;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';
        // Dompdf ignores <colgroup> widths, so the first (hidden) row carries them
        $widths = '<tr class="w"><td style="width:6%;"></td><td style="width:5%;"></td><td style="width:33%;"></td><td style="width:9%;"></td>'
            . '<td style="width:15.5%;"></td><td style="width:15.5%;"></td><td style="width:16%;"></td></tr>';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:9pt;color:#000;}'
            . 'table.gl{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . 'table.gl tr.w td{height:0;padding:0;border:none;}'
            . 'table.gl tr.h > td{border:none;padding:1pt 2pt;}'
            . '.title{text-align:center;font-size:13pt;font-weight:bold;padding:0 0 10pt !important;}'
            . 'table.acct{width:100%;border-collapse:collapse;table-layout:fixed;}table.acct td{padding:3pt 2pt 1pt;vertical-align:bottom;border:none;}'
            . 'table.acct td.lbl{font-weight:bold;white-space:nowrap;}table.acct td.ul{border-bottom:.75pt solid #000 !important;font-weight:bold;}'
            . 'td.gap{height:4pt;}'
            . 'tr.cols th{border:1pt solid #000;font-weight:bold;text-align:center;padding:2pt;}'
            . 'tbody td{border-left:.75pt solid #000;border-right:.75pt solid #000;border-bottom:.5pt solid #999;padding:1pt 3pt;height:10.5pt;}'
            . 'tbody td.mon{border-right:none;}tbody td.day{border-left:none;text-align:right;}'
            . 'td.ref{text-align:center;}td.am{text-align:right;white-space:nowrap;}'
            . 'tr.yr td{border-bottom:.5pt solid #999;}.c{text-align:center;}.b{font-weight:bold;}'
            . 'tr.close td{border-top:1.2pt solid #000;border-bottom:1.2pt solid #000;font-weight:bold;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '', '')
            . '<table class="gl"><thead>' . $widths . $head . '</thead><tbody>' . $body . '</tbody></table>'
            . PrintService::bodyClose($media, '');
    }
}
