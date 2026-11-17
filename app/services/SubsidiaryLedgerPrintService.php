<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Subsidiary Ledger print form — one SL master's (or SL detail's) ledger card, as the official SUBSIDIARY LEDGER
 * sheet: Entity Name / Fund, the SL Account Name box beside GL Account Name / Account Code / Bank Account No., then
 * Date · Particulars · Ref · Amount (Debit · Credit · Balance) and a Totals row.
 *
 * Unlike the General Ledger sheet (a monthly summary per journal), the subsidiary ledger is the DETAIL: one line per
 * posting, with the running balance on every line.
 *
 * Data: the Subsidiary Ledger page's GET /general_ledger/subsidiary-ledger (GeneralLedgerService::fetchSubsidiaryLedger),
 * mapped by the preview's prp_slPayload(). An SL is a child account in fims.chart_of_accounts (there are no SL tables):
 * the sheet's GL Account Name / Account Code are its parent account, looked up here when the payload has none.
 * No letterhead (the sheet has its own title). Printed through
 * PrintService::print('SL', …); DOCX: scripts/gen_sl_docx.js.
 */
class SubsidiaryLedgerPrintService
{
    // The sheet always shows at least this many printed lines (blank rows fill the page, as on the printed form);
    // a Particulars wraps at about PART_CHARS characters, so a long one counts as several lines.
    public const MIN_ROWS = 30;
    private const PART_CHARS = 36;

    /**
     * Model. Payload (all optional):
     *   sl_code, sl_name (the SL account's code and title), sl_detail_name (optional suffix to the name),
     *   gl_account_code, gl_account_title (the SL's parent account — looked up from sl_code when not given), bank_account_no,
     *   entity_name, fund (blank by default — filled in by hand; the page passes the Book filter's name),
     *   fiscal_year (for the file name),
     *   debit_normal (true default; false = credit-normal: the balance is credit - debit),
     *   opening_balance (a "Balance forwarded" first line; none → the balance starts at 0),
     *   lines [{ posting_date (Y-m-d), particulars, ref, debit, credit }].
     */
    public function model(array $p): array
    {
        $t     = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $money = static fn (float $v) => abs($v) >= 0.005 ? PrintService::money($v) : '';
        $sign  = in_array($p['debit_normal'] ?? true, [false, 'false', 0, '0'], true) ? -1 : 1;   // balance = sign × (debit − credit)
        $hasOpening = isset($p['opening_balance']) && $p['opening_balance'] !== '' && $p['opening_balance'] !== null;
        $balance    = $hasOpening ? PrintService::amount($p['opening_balance']) : 0.0;

        $rows = [];
        if ($hasOpening) {
            $rows[] = ['date' => '', 'text' => 'Balance forwarded', 'ref' => '', 'd' => '', 'c' => '', 'bal' => $money($balance)];
        }
        $lines = array_values(is_array($p['lines'] ?? null) ? $p['lines'] : []);
        usort($lines, static fn ($a, $b) => strcmp(substr((string) ($a['posting_date'] ?? ''), 0, 10), substr((string) ($b['posting_date'] ?? ''), 0, 10)));
        $totD = $totC = 0.0;
        foreach ($lines as $l) {
            $d = round(PrintService::amount($l['debit'] ?? 0), 2);
            $c = round(PrintService::amount($l['credit'] ?? 0), 2);
            $totD += $d;
            $totC += $c;
            $balance += $sign * ($d - $c);
            $rows[] = ['date' => self::date(PrintService::text($l['posting_date'] ?? '')), 'text' => PrintService::text($l['particulars'] ?? ''),
                'ref' => PrintService::text($l['ref'] ?? ''), 'd' => $money($d), 'c' => $money($c), 'bal' => PrintService::money($balance)];
        }

        $slName = $t('sl_name');
        $detail = $t('sl_detail_name');
        $code   = $t('sl_code');
        $year   = $t('fiscal_year');
        $glCode  = $t('gl_account_code');
        $glTitle = $t('gl_account_title');
        if ($code !== '' && ($glCode === '' || $glTitle === '')) {
            $parent  = self::parentAccount($code);
            $glCode  = $glCode !== '' ? $glCode : ($parent['account_code'] ?? '');
            $glTitle = $glTitle !== '' ? $glTitle : ($parent['account_title'] ?? '');
        }

        return [
            'entity'    => $t('entity_name'),
            'fund'      => $t('fund'),
            'sl_name'   => mb_strtoupper(trim($slName . ($detail !== '' ? ' — ' . $detail : ''))),
            'sl_code'   => $code,
            'gl_title'  => mb_strtoupper($glTitle),
            'gl_code'   => $glCode,
            'bank'      => $t('bank_account_no'),
            'rows'      => $rows,
            'min_rows'  => self::MIN_ROWS,
            'totals'    => ['d' => PrintService::money($totD), 'c' => PrintService::money($totC), 'bal' => PrintService::money($balance)],
            'header_parts' => [],
            'footer_text'  => '',
            'file_base' => 'SL-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', $code) ?: 'ACCOUNT') . ($year !== '' ? '-' . $year : ''),
        ];
    }

    /**
     * The GL account an SL belongs to: the SL's parent in fims.chart_of_accounts ({ account_code, account_title }),
     * [] when it has none or the table can't be read — the sheet then leaves the GL fields blank.
     */
    private static function parentAccount(string $slCode): array
    {
        try {
            return App\ExecuteRow(
                'SELECT p.account_code, p.account_title FROM fims.chart_of_accounts c
                 JOIN fims.chart_of_accounts p ON p.account_id = c.parent_account_id
                 WHERE c.account_code = ' . App\QuotedValue($slCode, App\DataType::STRING, 'DB'),
                'DB'
            ) ?: [];
        } catch (\Throwable $e) {
            App\LogError('SubsidiaryLedgerPrintService::parentAccount ' . $e->getMessage());

            return [];
        }
    }

    /** Y-m-d → "01/15/2026" (blank / anything else as given). */
    private static function date(string $v): string
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m) ? "{$m[2]}/{$m[3]}/{$m[1]}" : $v;
    }

    /**
     * Letter portrait (612 × 792pt), 0.5in sides (540pt), Times 10pt. The title, Entity Name / Fund, the account box and
     * the column headings are the table's <thead>, so they repeat on every page. Mirrors scripts/gen_sl_docx.js.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $v = static fn (string $s) => $s !== '' ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : '&nbsp;';
        $cells = static fn (array $r) => '<td class="dt">' . $v($r['date'] ?? '') . '</td><td class="part">' . $v($r['text'] ?? '') . '</td>'
            . '<td class="ref">' . $v($r['ref'] ?? '') . '</td><td class="am">' . $v($r['d'] ?? '') . '</td>'
            . '<td class="am">' . $v($r['c'] ?? '') . '</td><td class="am">' . $v($r['bal'] ?? '') . '</td>';

        $body  = '';
        $count = 0;
        foreach ($m['rows'] as $r) {
            $body .= '<tr>' . $cells($r) . '</tr>';
            $count++;
        }
        // blank rows fill the sheet: count the printed lines (a long Particulars wraps — ~PART_CHARS a line)
        $count = 0;
        foreach ($m['rows'] as $r) {
            $count += max(1, (int) ceil(mb_strlen($r['text']) / self::PART_CHARS));
        }
        for (; $count < $m['min_rows']; $count++) {
            $body .= '<tr>' . $cells([]) . '</tr>';
        }
        $tt = $m['totals'];
        $body .= '<tr class="tot"><td></td><td class="c">Totals</td><td></td><td class="am">' . $e($tt['d']) . '</td>'
            . '<td class="am">' . $e($tt['c']) . '</td><td class="am">' . $e($tt['bal']) . '</td></tr>';

        $slBox = '<div class="slname">' . $v($m['sl_name']) . '</div><div class="sllbl">SL Account Name'
            . ($m['sl_code'] !== '' ? ' &nbsp;(' . $e($m['sl_code']) . ')' : '') . '</div>';
        $glBox = '<table class="glbox"><tr><td class="k">GL Account Name :</td><td>' . $v($m['gl_title']) . '</td></tr>'
            . '<tr><td class="k">Account Code :</td><td>' . $v($m['gl_code']) . '</td></tr>'
            . '<tr><td class="k">Bank Account No. :</td><td>' . $v($m['bank']) . '</td></tr></table>';

        $head = '<tr class="h"><td colspan="6" class="title">SUBSIDIARY LEDGER</td></tr>'
            . '<tr class="h"><td colspan="6"><table class="top"><tr>'
            . '<td class="k" style="width:13%;">Entity Name :</td><td style="width:47%;">' . $v($m['entity']) . '</td>'
            . '<td class="k" style="width:8%;text-align:right;">Fund :</td><td style="width:32%;"><div class="ul">' . $v($m['fund']) . '</div></td>'
            . '</tr></table></td></tr>'
            . '<tr class="acct"><td colspan="3" class="slbox">' . $slBox . '</td><td colspan="3" class="glcell">' . $glBox . '</td></tr>'
            . '<tr class="cols"><th rowspan="2">Date</th><th rowspan="2">Particulars</th><th rowspan="2">Ref</th><th colspan="3">Amount</th></tr>'
            . '<tr class="cols"><th class="n">Debit</th><th class="n">Credit</th><th class="n">Balance</th></tr>';

        $pageCss = $media === 'print'
            ? '@page{size:letter portrait;margin:36pt;}body{margin:0;}'
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:612pt;min-height:792pt;margin:0 auto;padding:36pt;box-sizing:border-box;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';
        // Dompdf ignores <colgroup> widths, so the first (hidden) row carries them
        $widths = '<tr class="w"><td style="width:11%;"></td><td style="width:30%;"></td><td style="width:13%;"></td>'
            . '<td style="width:15.3%;"></td><td style="width:15.3%;"></td><td style="width:15.4%;"></td></tr>';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:times,"Times New Roman",serif;font-size:10pt;color:#000;}'
            . 'table.sl{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . 'table.sl tr.w td{height:0;padding:0;border:none;}'
            . 'table.sl tr.h > td{border:none;padding:1pt 0;}'
            . '.title{text-align:center;font-size:13pt;font-weight:bold;letter-spacing:.5pt;padding:0 0 12pt !important;}'
            . 'table.top,table.glbox{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . 'table.top td,table.glbox td{border:none !important;height:auto;padding:1pt 2pt;vertical-align:bottom;font-weight:bold;}'
            . 'table.top td .ul{border-bottom:.75pt solid #000;}td.k{white-space:nowrap;}'
            . 'tr.acct td{border:1.2pt solid #000;vertical-align:middle;}'
            . 'td.slbox{text-align:center;height:52pt;padding:3pt;}.slname{font-weight:bold;min-height:12pt;}.sllbl{font-weight:bold;margin-top:2pt;}'
            . 'td.glcell{padding:3pt 4pt;}table.glbox td{padding:1pt 2pt;vertical-align:top;}table.glbox td.k{width:38%;}'
            . 'tr.cols th{border:1.2pt solid #000;font-weight:bold;text-align:center;padding:2pt;}tr.cols th.n{font-weight:normal;}'
            . 'tbody td{border-left:1.2pt solid #000;border-right:1.2pt solid #000;border-bottom:.75pt solid #000;padding:1.5pt 3pt;height:12pt;vertical-align:top;}'
            . 'td.dt,td.ref{text-align:center;white-space:nowrap;}td.am{text-align:right;white-space:nowrap;}td.c{text-align:center;}'
            . 'tr.tot td{border-top:1.2pt solid #000;border-bottom:1.2pt solid #000;font-weight:bold;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '', '')
            . '<table class="sl"><thead>' . $widths . $head . '</thead><tbody>' . $body . '</tbody></table>'
            . PrintService::bodyClose($media, '');
    }
}
