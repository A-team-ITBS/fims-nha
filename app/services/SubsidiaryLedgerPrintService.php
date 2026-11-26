<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Subsidiary Ledger print form (PrintService type 'SL') — the layout of the books' own SUBSIDIARY LEDGER sheets (e.g.
 * the Main Office "SL-MO-SAVINGS" workbook: one sheet per bank account under 1-01-02-030 Cash in Bank – Savings):
 *
 *   <BOOK> - <GL ACCOUNT TITLE> <YEAR>                                   <Fund (book)>   <GL account code>
 *   SUBSIDIARY LEDGER                                                    <BANK  account no.>  (or the SL code)
 *                                                                        <account name>       (or the SL title)
 *   DATE | PARTICULARS | FOLIO | JEV# | DEBITS | CREDITS | BALANCE
 *   <year>
 *   JAN  | Beginning balance |      |         |        |         | <beginning>
 *   <MON>| <JEV particulars>  | <journal> | <JEV no.> | <debit> | <credit> |      — one line per posting, no running balance
 *   …blank rows…
 *   DEC  | Ending balance     |      |         | <total debits> | <total credits> | <ending>   (totals exclude the beginning)
 *
 * One sheet per SL account (a bottom-level child of the GL account in fims.chart_of_accounts) with postings or a
 * beginning balance in the book and year; the GL account's own postings are not an SL and are left out (they are on the
 * General Ledger sheet). When the SL account is a bank account's sub-ledger (fims.bank_accounts.sl_code / account_code),
 * the header shows the bank, account number and account name — as on the books' sheets; otherwise the SL code and title.
 *
 * Data: the General Ledger page's GET /general_ledger/ledger-sl (sub_ledgers), mapped by the preview's prp_glSlPayload()
 * into `ledgers`; the Subsidiary Ledger page sends one SL (prp_slPayload). Per posting, worked out here from its JEV
 * (GeneralLedgerPrintService::journals): the month (JEV date), the year (JEV fiscal year), the particulars (the JEV's,
 * else its DV's, else the posting's own description) and the FOLIO (journal: CKDJ / CRDJ / ADADJ / GJ). Beginning balance: the SL
 * account's Opening Balance for the book and year (fims.opening_balances). No letterhead. DOCX: scripts/gen_sl_docx.js.
 */
class SubsidiaryLedgerPrintService
{
    // The sheet always shows at least this many body rows (blank ones fill the page, as on the printed form);
    // a Particulars wraps at about PART_CHARS characters, so a long one counts as several lines.
    public const MIN_ROWS = 32;
    private const PART_CHARS = 52;
    private const MONTHS = ['', 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

    /**
     * Model. Payload (all optional):
     *   gl_account_code, gl_account_title (looked up from the first SL's parent when not given), fund (the book's name),
     *   book_id (for the beginning balances), fiscal_year, debit_normal (true default; false = credit - debit),
     *   ledgers [{ sl_code, sl_name, opening_balance?, lines }] — one sheet each (the General Ledger page);
     *   or one SL (the Subsidiary Ledger page): sl_code, sl_name, opening_balance?, lines.
     *   lines [{ posting_date, jev_no, doc_ref_no?, particulars?, debit, credit, journal? }].
     */
    public function model(array $p): array
    {
        $t    = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $sign = in_array($p['debit_normal'] ?? true, [false, 'false', 0, '0'], true) ? -1 : 1;   // balance = sign × (debit − credit)
        $ledgers = array_values(array_filter(is_array($p['ledgers'] ?? null) ? $p['ledgers'] : [], 'is_array'));
        $single  = !$ledgers;
        if ($single) {
            $ledgers = [['sl_code' => $p['sl_code'] ?? '', 'sl_name' => $p['sl_name'] ?? '', 'opening_balance' => $p['opening_balance'] ?? null,
                'lines' => $p['lines'] ?? []]];
        }

        // every JEV the postings come from: its date, fiscal year, particulars and journal
        $jevNos = [];
        foreach ($ledgers as $l) {
            foreach ((is_array($l['lines'] ?? null) ? $l['lines'] : []) as $ln) {
                $no = PrintService::text($ln['jev_no'] ?? ($ln['ref'] ?? ''));
                if ($no !== '') {
                    $jevNos[$no] = true;
                }
            }
        }
        $jevs = GeneralLedgerPrintService::journals(array_keys($jevNos));

        $year = $t('fiscal_year');
        if ($year === '') {
            foreach ($ledgers as $l) {
                foreach ((array) ($l['lines'] ?? []) as $ln) {
                    $no = PrintService::text($ln['jev_no'] ?? ($ln['ref'] ?? ''));
                    $d  = $jevs[$no]['date'] ?? substr(PrintService::text($ln['posting_date'] ?? ''), 0, 10);
                    if (preg_match('/^(\d{4})-/', (string) $d, $m)) {
                        $year = $jevs[$no]['fy'] ?? $m[1];
                        break 2;
                    }
                }
            }
            $year = $year !== '' ? $year : date('Y');
        }
        $bookId = $p['book_id'] ?? null;
        $bal    = static fn (float $v) => $v <= -0.005 ? '(' . PrintService::money(abs($v)) . ')' : PrintService::money(abs($v) < 0.005 ? 0.0 : $v);
        $money  = static fn (float $v) => abs($v) >= 0.005 ? PrintService::money($v) : '';

        $sheets = [];
        foreach ($ledgers as $l) {
            $code    = PrintService::text($l['sl_code'] ?? '');
            $hasOpen = isset($l['opening_balance']) && $l['opening_balance'] !== '' && $l['opening_balance'] !== null;
            $opening = $hasOpen ? PrintService::amount($l['opening_balance']) : self::openingBalance($code, (int) $year, $bookId, $sign);

            $rows = [];
            foreach ((is_array($l['lines'] ?? null) ? $l['lines'] : []) as $ln) {
                $no   = PrintService::text($ln['jev_no'] ?? ($ln['ref'] ?? ''));
                $jev  = $jevs[$no] ?? null;
                $date = $jev && preg_match('/^\d{4}-\d{2}-\d{2}$/', $jev['date']) ? $jev['date'] : substr(PrintService::text($ln['posting_date'] ?? ''), 0, 10);
                $fy   = $jev && $jev['fy'] !== '' ? $jev['fy'] : substr($date, 0, 4);
                if ($fy !== $year) {
                    continue;
                }
                $d = round(PrintService::amount($ln['debit'] ?? 0), 2);
                $c = round(PrintService::amount($ln['credit'] ?? 0), 2);
                $journal = strtoupper(PrintService::text($ln['journal'] ?? '')) ?: ($jev['journal'] ?? GeneralLedgerPrintService::journalOfRef((string) ($ln['doc_ref_no'] ?? '')));
                if ($journal === 'OPENING') {   // a legacy opening JEV is the beginning balance
                    $opening += $sign * ($d - $c);
                    continue;
                }
                // the entry's explanation, as on the books' sheets: the JEV's particulars (else its DV's), then the
                // posting's own description (a JEV line's description is usually just the account name)
                $part = $jev['particulars'] ?? '';
                if ($part === '') {
                    $part = PrintService::text($ln['particulars'] ?? '');
                }
                if ($part === '' && $journal === 'CLOSING') {
                    $part = 'Closing Entry';
                }
                $mon = preg_match('/^\d{4}-(\d{2})/', $date, $mm) ? self::MONTHS[(int) $mm[1]] : '';
                $rows[] = ['sort' => $date . '|' . $no, 'mon' => $mon, 'text' => $part, 'folio' => $journal === 'CLOSING' ? 'GJ' : $journal,
                    'jev' => $no, 'd' => $d, 'c' => $c];
            }
            if (!$rows && abs($opening) < 0.005 && !$single) {
                continue;   // an SL with nothing in this book and year gets no sheet
            }
            usort($rows, static fn ($a, $b) => strcmp($a['sort'], $b['sort']));
            $totD = array_sum(array_column($rows, 'd'));
            $totC = array_sum(array_column($rows, 'c'));
            $ending = $opening + $sign * ($totD - $totC);

            $bank = self::bankAccount($code);
            $sheets[] = [
                'sl_code'  => $code,
                'id_line'  => $bank ? trim($bank['bank_name'] . '     ' . $bank['account_number']) : $code,
                'name'     => $bank ? ($bank['account_name'] !== '' ? $bank['account_name'] : PrintService::text($l['sl_name'] ?? '')) : PrintService::text($l['sl_name'] ?? ''),
                'opening'  => $bal(round($opening, 2)),
                'rows'     => array_map(static fn ($r) => ['mon' => $r['mon'], 'text' => $r['text'], 'folio' => $r['folio'], 'jev' => $r['jev'],
                    'd' => $money($r['d']), 'c' => $money($r['c'])], $rows),
                'totals'   => ['d' => PrintService::money($totD), 'c' => PrintService::money($totC), 'bal' => $bal(round($ending, 2))],
            ];
        }
        if (!$sheets) {   // nothing under the GL account in this book and year: one blank sheet
            $sheets[] = ['sl_code' => '', 'id_line' => '', 'name' => '', 'opening' => $bal(0.0), 'rows' => [],
                'totals' => ['d' => PrintService::money(0), 'c' => PrintService::money(0), 'bal' => $bal(0.0)]];
        }

        $glCode  = $t('gl_account_code');
        $glTitle = $t('gl_account_title');
        $firstSl = '';
        foreach ($sheets as $sh) {
            if ($sh['sl_code'] !== '') {
                $firstSl = $sh['sl_code'];
                break;
            }
        }
        if ($firstSl !== '' && ($glCode === '' || $glTitle === '')) {
            $parent  = self::parentAccount($firstSl);
            $glCode  = $glCode !== '' ? $glCode : ($parent['account_code'] ?? '');
            $glTitle = $glTitle !== '' ? $glTitle : ($parent['account_title'] ?? '');
        }
        $fund  = $t('fund');
        $title = trim(mb_strtoupper(($fund !== '' ? $fund . ' - ' : '') . $glTitle) . ' ' . $year);
        $fileCode = $glCode !== '' ? $glCode : ($firstSl !== '' ? $firstSl : 'ACCOUNT');

        return [
            'title'     => $title,
            'fund'      => $fund,
            'gl_code'   => $glCode,
            'year'      => $year,
            'sheets'    => $sheets,
            'min_rows'  => self::MIN_ROWS,
            'header_parts' => [],
            'footer_text'  => '',
            'file_base' => 'SL-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', $fileCode) ?: 'ACCOUNT') . '-' . $year
                . ($fund !== '' ? '-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $fund) : ''),
        ];
    }

    /**
     * The SL account's Beginning Balance for the year: its fims.opening_balances for the book (every book when none is
     * given), signed on the normal side. 0 when none / unreadable.
     */
    private static function openingBalance(string $code, int $year, $bookId, int $sign): float
    {
        if ($code === '' || $year <= 0) {
            return 0.0;
        }
        try {
            $book = ($bookId !== null && $bookId !== '' && ctype_digit((string) $bookId)) ? ' AND book_id = ' . (int) $bookId : '';
            $row  = App\ExecuteRow(
                'SELECT COALESCE(SUM(debit), 0) AS d, COALESCE(SUM(credit), 0) AS c FROM fims.opening_balances
                 WHERE fiscal_yr = ' . $year . $book . ' AND account_code = ' . App\QuotedValue($code, App\DataType::STRING, 'DB'),
                'DB'
            ) ?: [];

            return round($sign * ((float) ($row['d'] ?? 0) - (float) ($row['c'] ?? 0)), 2);
        } catch (\Throwable $e) {
            App\LogError('SubsidiaryLedgerPrintService::openingBalance ' . $e->getMessage());

            return 0.0;
        }
    }

    /**
     * The bank account an SL account belongs to (fims.bank_accounts tagged to it — sl_code is the SL's account_id, or
     * account_code is its code): { bank_name, account_number, account_name }, [] when none.
     */
    private static function bankAccount(string $code): array
    {
        if ($code === '') {
            return [];
        }
        try {
            $q   = App\QuotedValue($code, App\DataType::STRING, 'DB');
            $row = App\ExecuteRow(
                "SELECT ba.bank_name, ba.account_number, ba.account_name
                 FROM fims.bank_accounts ba
                 LEFT JOIN fims.chart_of_accounts c ON c.account_id::text = ba.sl_code::text
                 WHERE c.account_code = {$q} OR ba.account_code = {$q}
                 ORDER BY (c.account_code = {$q}) DESC NULLS LAST, ba.bank_account_id
                 LIMIT 1",
                'DB'
            );

            return $row ? array_map(static fn ($v) => trim((string) $v), $row) : [];
        } catch (\Throwable $e) {
            App\LogError('SubsidiaryLedgerPrintService::bankAccount ' . $e->getMessage());

            return [];
        }
    }

    /**
     * The GL account an SL belongs to: the SL's parent in fims.chart_of_accounts ({ account_code, account_title }),
     * [] when it has none or the table can't be read.
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

    /**
     * Letter portrait (612 × 792pt), 0.5in sides, Arial 9pt. Each sheet is its own table starting on a new page; the
     * title block and the column headings are its <thead>, so they repeat on every page of that sheet. Mirrors
     * scripts/gen_sl_docx.js.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $v = static fn (string $s) => $s !== '' ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : '&nbsp;';
        $cells = static fn (array $r) => '<td class="dt">' . $v($r['mon'] ?? '') . '</td><td class="part">' . $v($r['text'] ?? '') . '</td>'
            . '<td class="c">' . $v($r['folio'] ?? '') . '</td><td class="c">' . $v($r['jev'] ?? '') . '</td>'
            . '<td class="am">' . $v($r['d'] ?? '') . '</td><td class="am">' . $v($r['c'] ?? '') . '</td><td class="am">' . $v($r['bal'] ?? '') . '</td>';
        // Dompdf ignores <colgroup> widths, so the first (hidden) row carries them
        $widths = '<tr class="w"><td style="width:7%;"></td><td style="width:36%;"></td><td style="width:7%;"></td><td style="width:13%;"></td>'
            . '<td style="width:12.5%;"></td><td style="width:12.5%;"></td><td style="width:12%;"></td></tr>';

        $tables = [];
        foreach ($m['sheets'] as $sh) {
            $body  = '<tr class="yr"><td class="b">' . $e($m['year']) . '</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            $body .= '<tr>' . $cells(['mon' => 'JAN', 'text' => 'Beginning balance', 'bal' => $sh['opening']]) . '</tr>';
            $count = 2;
            foreach ($sh['rows'] as $r) {
                $body  .= '<tr>' . $cells($r) . '</tr>';
                $count += max(1, (int) ceil(mb_strlen($r['text']) / self::PART_CHARS));
            }
            for (; $count < $m['min_rows']; $count++) {
                $body .= '<tr>' . $cells([]) . '</tr>';
            }
            $tt = $sh['totals'];
            $body .= '<tr class="tot">' . $cells(['mon' => 'DEC', 'text' => 'Ending balance', 'd' => $tt['d'], 'c' => $tt['c'], 'bal' => $tt['bal']]) . '</tr>';

            $head = '<tr class="h"><td colspan="4" class="title">' . $e($m['title']) . '</td><td colspan="3"></td></tr>'
                . '<tr class="h"><td colspan="4"></td><td class="r">' . $v($m['fund']) . '</td><td colspan="2" class="r b">' . $v($m['gl_code']) . '</td></tr>'
                . '<tr class="h"><td colspan="4" class="sub">SUBSIDIARY LEDGER</td><td colspan="3" class="r b">' . $v($sh['id_line']) . '</td></tr>'
                . '<tr class="h"><td colspan="4"></td><td colspan="3" class="r">' . $v($sh['name']) . '</td></tr>'
                . '<tr class="h"><td colspan="7" class="gap"></td></tr>'
                . '<tr class="cols"><th>DATE</th><th>PARTICULARS</th><th>FOLIO</th><th>JEV#</th><th>DEBITS</th><th>CREDITS</th><th>BALANCE</th></tr>';
            $tables[] = '<table class="sl"><thead>' . $widths . $head . '</thead><tbody>' . $body . '</tbody></table>';
        }

        $pageCss = $media === 'print'
            ? '@page{size:letter portrait;margin:36pt;}body{margin:0;}.sheet.next{page-break-before:always;}'
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:612pt;min-height:792pt;margin:0 auto;padding:36pt;box-sizing:border-box;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}'
              . '.sheet.next{margin-top:16px;}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:9pt;color:#000;}'
            . 'table.sl{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . 'table.sl tr.w td{height:0;padding:0;border:none;}'
            . 'table.sl tr.h > td{border:none;padding:1pt 3pt;}'
            . '.title{font-size:11pt;font-weight:bold;padding-bottom:6pt !important;}.sub{font-size:11pt;font-weight:bold;}'
            . '.r{text-align:right;white-space:nowrap;}.b{font-weight:bold;}td.gap{height:6pt;}'
            . 'tr.cols th{border:1pt solid #000;font-weight:bold;text-align:center;padding:3pt 2pt;}'
            . 'tbody td{border-left:.75pt solid #000;border-right:.75pt solid #000;border-bottom:.5pt solid #999;padding:1pt 3pt;height:10.5pt;vertical-align:top;}'
            . 'td.dt,td.c{text-align:center;white-space:nowrap;}td.am{text-align:right;white-space:nowrap;}'
            . 'tr.yr td{border-bottom:.5pt solid #999;}'
            . 'tr.tot td{border-top:1.2pt solid #000;border-bottom:1.2pt solid #000;font-weight:bold;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '', '')
            . implode('</div><div class="sheet next">', $tables)
            . PrintService::bodyClose($media, '');
    }
}
