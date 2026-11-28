<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * General Ledger print form — NHA Form No. 11, the GENERAL LEDGER sheet the books keep (one sheet per 4-part GL
 * account, per book): "NHA Form No. 11", GENERAL LEDGER, NATIONAL HOUSING AUTHORITY (Agency), Fund (the book) /
 * Account Code, Account Title / Sheet No., then Date (month | day) · Particulars · Reference · Debit · Credit · Balance.
 *
 * It prints the MONTHLY SUMMARY the sheet carries, not every posting:
 *   - the year, then "Jan 1  Beginning Balance" (the year's Opening Balance for the book — fims.opening_balances of the
 *     account and the SL accounts under it — on its normal side);
 *   - per month, one line per journal the account was posted from, dated the month's last day, with that journal's
 *     debit and credit totals; the balance on the month's last line:
 *       CKDJ  Disbursements                  (check payment JEVs)
 *       CRDJ  Collections and Deposits       (JEVs journalized from a Collections report; Main Office: "Collections & Deposits")
 *       ADADJ Various Fund Transfer          (ADA / FTLA payment JEVs; Main Office: "Advice to Debit Account Disb.")
 *       GJ    Various Adjustments            (every other JEV)
 *     — a balance against the account's normal side prints in brackets: (15,000.00);
 *   - "Dec 31  Closing Entry" (GJ) — the year-end closing JEV (jev_classification = CLOSING), on its own line;
 *   - "<year> Dec 31  Ending Balance": total debit, total credit (the beginning balance counted on its side) and the
 *     ending balance — the figure the Trial Balance shows for the account.
 *
 * Data: the General Ledger page's account ledger (GET /general_ledger/ledger — GeneralLedgerService::fetchGeneralLedger,
 * the GL account with its SL accounts' postings rolled in) for one fiscal year and one book, mapped by the preview's
 * prp_glPayload(). Each posting's journal is worked out here from its JEV (looked up by jev_no): PAYMENT JEVs by their
 * doc_ref_no ("CHECK…" → CKDJ, "ADA…" / "FTLA…" → ADADJ), CLOSING JEVs → Closing Entry, a legacy OPENING JEV → the
 * Beginning Balance, a JEV linked from fims.collection_reports → CRDJ, else GJ. A payload line may set `journal`.
 *
 * No letterhead (the sheet has its own title). Printed through PrintService::print('GL', …); DOCX: scripts/gen_gl_docx.js.
 */
class GeneralLedgerPrintService
{
    // The sheet always shows at least this many body rows (blank ones fill the page, as on the printed form).
    public const MIN_ROWS = 34;

    // The journals, in the order they are listed within a month, and their Particulars wording — as on the other books'
    // sheets (CISFA, NGC, MPLC / PPMO); Main Office's sheets word two of them differently (MAIN_OFFICE_WORDING).
    private const JOURNALS = [
        'CKDJ'  => 'Disbursements',
        'CRDJ'  => 'Collections and Deposits',
        'ADADJ' => 'Various Fund Transfer',
        'GJ'    => 'Various Adjustments',
    ];
    private const MAIN_OFFICE_WORDING = [
        'CRDJ'  => 'Collections & Deposits',
        'ADADJ' => 'Advice to Debit Account Disb.',
    ];

    private const MONTHS = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /**
     * Model. Payload (all optional):
     *   account_code, account_title, fund (the book's name), book_id (for the Beginning Balance lookup), sheet_no,
     *   fiscal_year (the year printed; default: the first posting's year),
     *   debit_normal (true default — Asset / Expense; false = credit-normal: the balance is credit - debit),
     *   opening_balance (overrides the Beginning Balance lookup; signed on the normal side),
     *   lines [{ posting_date (Y-m-d), jev_no, doc_ref_no, debit, credit, journal? (CKDJ | CRDJ | ADADJ | GJ | CLOSING | OPENING) }].
     */
    public function model(array $p): array
    {
        $t     = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $money = static fn (float $v) => abs($v) >= 0.005 ? PrintService::money($v) : '';
        // a balance against the account's normal side prints in brackets, as on the books' sheets: (15,000.00)
        // a zero balance prints as a dash
        $bal   = static fn (float $v) => abs($v) < 0.005 ? '-' : ($v < 0 ? '(' . PrintService::money(abs($v)) . ')' : PrintService::money($v));
        $mainOffice = (bool) preg_match('/\bMAIN\b/i', (string) ($p['fund'] ?? ''));
        $label = static fn (string $j) => $mainOffice && isset(self::MAIN_OFFICE_WORDING[$j]) ? self::MAIN_OFFICE_WORDING[$j] : self::JOURNALS[$j];
        $sign  = in_array($p['debit_normal'] ?? true, [false, 'false', 0, '0'], true) ? -1 : 1;   // balance = sign × (debit − credit)
        $lines = array_values(array_filter(is_array($p['lines'] ?? null) ? $p['lines'] : [], 'is_array'));
        $code  = $t('account_code');

        $year = $t('fiscal_year');
        if ($year === '') {
            foreach ($lines as $l) {
                if (preg_match('/^(\d{4})-/', PrintService::text($l['posting_date'] ?? ''), $mm)) {
                    $year = $mm[1];
                    break;
                }
            }
            $year = $year !== '' ? $year : date('Y');
        }

        // Beginning Balance: the payload's, else the book's Opening Balance for the year (signed on the normal side)
        $hasOpening = isset($p['opening_balance']) && $p['opening_balance'] !== '' && $p['opening_balance'] !== null;
        $opening    = $hasOpening ? PrintService::amount($p['opening_balance']) : self::openingBalance($code, (int) $year, $p['book_id'] ?? null, $sign);

        // postings → month (Y-m) → journal → sums; closing entries apart; a legacy OPENING JEV adds to the Beginning Balance
        $journalOf = self::journals(array_values(array_unique(array_filter(array_map(static fn ($l) => PrintService::text($l['jev_no'] ?? ''), $lines)))));
        $months  = [];
        $closing = ['d' => 0.0, 'c' => 0.0, 'any' => false];
        foreach ($lines as $l) {
            // the month a posting belongs to is its JEV's date (the accounting period — a JEV can be posted later than
            // it is dated), and the year its JEV's fiscal year; a line without a known JEV uses its posting date
            $jev  = $journalOf[PrintService::text($l['jev_no'] ?? '')] ?? null;
            $date = $jev && preg_match('/^\d{4}-\d{2}-\d{2}$/', $jev['date']) ? $jev['date'] : substr(PrintService::text($l['posting_date'] ?? ''), 0, 10);
            $fy   = $jev && $jev['fy'] !== '' ? $jev['fy'] : substr($date, 0, 4);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $fy !== $year) {
                continue;
            }
            if (substr($date, 0, 4) !== $year) {   // dated outside its fiscal year: file it under the year's nearest month
                $date = $year . (substr($date, 0, 4) < $year ? '-01-01' : '-12-31');
            }
            $d = PrintService::amount($l['debit'] ?? 0);
            $c = PrintService::amount($l['credit'] ?? 0);
            $journal = strtoupper(PrintService::text($l['journal'] ?? ''))
                ?: ($jev['journal'] ?? self::journalOfRef((string) ($l['doc_ref_no'] ?? '')));
            if ($journal === 'OPENING') {
                $opening += $sign * ($d - $c);
                continue;
            }
            if ($journal === 'CLOSING') {
                $closing['d'] += $d;
                $closing['c'] += $c;
                $closing['any'] = true;
                continue;
            }
            $journal = isset(self::JOURNALS[$journal]) ? $journal : 'GJ';
            $ym = substr($date, 0, 7);
            $months[$ym][$journal]['d'] = ($months[$ym][$journal]['d'] ?? 0) + $d;
            $months[$ym][$journal]['c'] = ($months[$ym][$journal]['c'] ?? 0) + $c;
        }
        ksort($months);

        $rows    = [];
        $balance = round($opening, 2);
        // the Beginning Balance on its own side (as on the sheet): debit side for a debit balance of a debit-normal
        // account or a credit balance of a credit-normal one, else the other side
        $openD = ($sign > 0) === ($opening >= 0) ? abs($opening) : 0.0;
        $openC = ($sign > 0) === ($opening >= 0) ? 0.0 : abs($opening);
        $rows[] = ['kind' => 'line', 'mon' => 'Jan', 'day' => '1', 'text' => 'Beginning Balance', 'ref' => '',
            'd' => $money($openD), 'c' => $money($openC), 'bal' => $bal($balance)];
        $totD = $openD;
        $totC = $openC;

        foreach ($months as $ym => $journals) {
            [$y, $m] = array_map('intval', explode('-', $ym));
            $day   = (string) (int) date('t', mktime(0, 0, 0, $m, 1, $y));   // the month's last day (no ext-calendar on the server)
            $order = array_values(array_intersect(array_keys(self::JOURNALS), array_keys($journals)));
            $rows[] = ['kind' => 'gap'];   // a blank line before each month, as on the sheet
            foreach ($order as $i => $j) {
                $d = round($journals[$j]['d'], 2);
                $c = round($journals[$j]['c'], 2);
                $totD += $d;
                $totC += $c;
                $balance += $sign * ($d - $c);
                $last = $i === count($order) - 1 && !($m === 12 && $closing['any']);
                $rows[] = ['kind' => 'line', 'mon' => $i === 0 ? self::MONTHS[$m] : '', 'day' => $day, 'text' => $label($j), 'ref' => $j,
                    'd' => $money($d), 'c' => $money($c), 'bal' => $last ? $bal($balance) : ''];
            }
        }
        if ($closing['any']) {
            $d = round($closing['d'], 2);
            $c = round($closing['c'], 2);
            $totD += $d;
            $totC += $c;
            $balance += $sign * ($d - $c);
            $hasDec = isset($months[$year . '-12']);
            if (!$hasDec) {
                $rows[] = ['kind' => 'gap'];
            }
            $rows[] = ['kind' => 'line', 'mon' => $hasDec ? '' : 'Dec', 'day' => '31', 'text' => 'Closing Entry', 'ref' => 'GJ',
                'd' => $money($d), 'c' => $money($c), 'bal' => $bal($balance)];
        }

        return [
            'form_no'    => 'NHA Form No. 11',
            'agency'     => 'NATIONAL HOUSING AUTHORITY',
            'fund'       => $t('fund'),
            'title'      => $t('account_title'),
            'code'       => $code,
            'sheet'      => $t('sheet_no'),
            'year'       => $year,
            'rows'       => $rows,
            'min_rows'   => self::MIN_ROWS,
            'ending'     => ['mon' => $year . ' Dec', 'day' => '31', 'text' => 'Ending Balance',
                'd' => PrintService::money($totD), 'c' => PrintService::money($totC), 'bal' => $bal($balance)],
            'header_parts' => [],
            'footer_text'  => '',
            'file_base'  => 'GL-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', $code) ?: 'ACCOUNT') . '-' . $year
                . ($t('fund') !== '' ? '-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $t('fund')) : ''),
        ];
    }

    /** The journal a posting belongs to from its document reference alone — "CHECK…" CKDJ, "ADA…"/"FTLA…" ADADJ, else GJ. */
    public static function journalOfRef(string $docRef): string
    {
        $r = strtoupper(trim($docRef));
        if (str_starts_with($r, 'CHECK')) {
            return 'CKDJ';
        }
        if (str_starts_with($r, 'ADA') || str_starts_with($r, 'FTLA')) {
            return 'ADADJ';
        }

        return 'GJ';
    }

    /**
     * JEV no. → { journal (CKDJ | CRDJ | ADADJ | GJ | CLOSING | OPENING), date (the JEV date), fy (its fiscal year),
     * particulars — the JEV's, else its DV's },
     * from fims.journal_entry_vouchers (jev_type, doc_ref_no, jev_classification, jev_date, fiscal_yr) and
     * fims.collection_reports (collection_jev_id / deposit_jev_id). [] on failure — the postings then fall back to their
     * own doc_ref_no and posting date.
     */
    public static function journals(array $jevNos): array
    {
        if (!$jevNos) {
            return [];
        }
        try {
            $list = implode(', ', array_map(static fn ($n) => App\QuotedValue($n, App\DataType::STRING, 'DB'), $jevNos));
            $rows = App\ExecuteRows(
                "SELECT j.jev_no, j.jev_type, j.doc_ref_no, j.jev_classification, j.jev_date, j.fiscal_yr,
                        COALESCE(NULLIF(TRIM(j.particulars), ''), dv.particulars) AS particulars,
                        EXISTS (SELECT 1 FROM fims.collection_reports cr WHERE cr.collection_jev_id = j.jev_id OR cr.deposit_jev_id = j.jev_id) AS is_collection
                 FROM fims.journal_entry_vouchers j
                 LEFT JOIN fims.disbursement_vouchers dv ON dv.dv_id = j.dv_id
                 WHERE j.jev_no IN ({$list})",
                'DB'
            ) ?: [];
        } catch (\Throwable $e) {
            App\LogError('GeneralLedgerPrintService::journals ' . $e->getMessage());

            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $class = strtoupper((string) ($r['jev_classification'] ?? ''));
            $out[$r['jev_no']]['date'] = substr((string) ($r['jev_date'] ?? ''), 0, 10);
            $out[$r['jev_no']]['fy']   = (string) ($r['fiscal_yr'] ?? '');
            $out[$r['jev_no']]['particulars'] = trim((string) ($r['particulars'] ?? ''));
            $out[$r['jev_no']]['journal'] = match (true) {
                $class === 'CLOSING' => 'CLOSING',
                $class === 'OPENING' => 'OPENING',
                in_array($r['is_collection'] ?? false, [true, 't', 1, '1'], true) => 'CRDJ',
                strtoupper((string) ($r['jev_type'] ?? '')) === 'PAYMENT' => (self::journalOfRef((string) ($r['doc_ref_no'] ?? '')) === 'ADADJ' ? 'ADADJ' : 'CKDJ'),
                default => self::journalOfRef((string) ($r['doc_ref_no'] ?? '')),
            };
        }

        return $out;
    }

    /**
     * The General Ledger sheets there is something to print for an account: every (book, fiscal year) with postings in
     * fims.general_ledger or an Opening Balance — for the account itself and the SL accounts under it (its bottom-level
     * children, the same roll-up as the General Ledger page). Newest year first, then the book's order. $fiscalYr limits
     * it to one year. → [{ book_id, book_name, fiscal_yr, postings, sl_postings, sl_opening }] (postings = posted lines;
     * 0 = only a Beginning Balance; sl_postings / sl_opening = the same for the SL accounts under it alone — what the
     * Subsidiary Ledger print has to show). Used by the General Ledger page's Print menu (GET /print/gl-sheets).
     */
    public static function availableSheets(string $code, ?int $fiscalYr = null): array
    {
        if ($code === '') {
            return [];
        }
        $q     = App\QuotedValue($code, App\DataType::STRING, 'DB');
        $slCodes = "SELECT c.account_code FROM fims.chart_of_accounts c
                  JOIN fims.chart_of_accounts g ON g.account_id = c.parent_account_id
                  WHERE g.account_code = {$q}
                    AND NOT EXISTS (SELECT 1 FROM fims.chart_of_accounts k WHERE k.parent_account_id = c.account_id)";
        $codes = "SELECT {$q} UNION {$slCodes}";
        $year  = $fiscalYr ? ' AND fiscal_yr = ' . (int) $fiscalYr : '';

        return array_map(static fn (array $r) => [
            'book_id'   => (int) $r['book_id'],
            'book_name' => (string) ($r['book_name'] ?? ''),
            'fiscal_yr' => (int) $r['fiscal_yr'],
            'postings'  => (int) $r['postings'],
            'sl_postings' => (int) $r['sl_postings'],
            'sl_opening'  => (int) $r['sl_opening'],
        ], App\ExecuteRows(
            "SELECT s.book_id, b.name AS book_name, s.fiscal_yr, SUM(s.n) AS postings, SUM(s.sl_n) AS sl_postings, SUM(s.sl_ob) AS sl_opening
             FROM (
                 SELECT book_id, fiscal_yr, COUNT(*) AS n,
                        COUNT(*) FILTER (WHERE account_code IN ({$slCodes})) AS sl_n, 0 AS sl_ob
                 FROM fims.general_ledger
                 WHERE account_code IN ({$codes}){$year} GROUP BY book_id, fiscal_yr
                 UNION ALL
                 SELECT book_id, fiscal_yr, 0, 0, COUNT(*) FILTER (WHERE account_code IN ({$slCodes}))
                 FROM fims.opening_balances
                 WHERE account_code IN ({$codes}){$year} AND (COALESCE(debit, 0) <> 0 OR COALESCE(credit, 0) <> 0)
                 GROUP BY book_id, fiscal_yr
             ) s
             LEFT JOIN fims.books b ON b.book_id = s.book_id
             WHERE s.book_id IS NOT NULL
             GROUP BY s.book_id, b.name, s.fiscal_yr
             ORDER BY s.fiscal_yr DESC, s.book_id",
            'DB'
        ) ?: []);
    }

    /**
     * The account's Beginning Balance for the year: fims.opening_balances of the account itself and of the SL accounts
     * under it (its bottom-level children — the General Ledger page rolls their postings into it too), for the book
     * (every book when none is given), signed on the normal side. 0 when none / unreadable.
     */
    public static function openingBalance(string $code, int $year, $bookId, int $sign): float
    {
        if ($code === '' || $year <= 0) {
            return 0.0;
        }
        try {
            $q    = App\QuotedValue($code, App\DataType::STRING, 'DB');
            $book = ($bookId !== null && $bookId !== '' && ctype_digit((string) $bookId)) ? ' AND ob.book_id = ' . (int) $bookId : '';
            $row  = App\ExecuteRow(
                "SELECT COALESCE(SUM(ob.debit), 0) AS d, COALESCE(SUM(ob.credit), 0) AS c
                 FROM fims.opening_balances ob
                 WHERE ob.fiscal_yr = {$year}{$book}
                   AND (ob.account_code = {$q} OR ob.account_code IN (
                        SELECT c.account_code FROM fims.chart_of_accounts c
                        JOIN fims.chart_of_accounts g ON g.account_id = c.parent_account_id
                        WHERE g.account_code = {$q}
                          AND NOT EXISTS (SELECT 1 FROM fims.chart_of_accounts k WHERE k.parent_account_id = c.account_id)))",
                'DB'
            ) ?: [];

            return round($sign * ((float) ($row['d'] ?? 0) - (float) ($row['c'] ?? 0)), 2);
        } catch (\Throwable $e) {
            App\LogError('GeneralLedgerPrintService::openingBalance ' . $e->getMessage());

            return 0.0;
        }
    }

    /**
     * Letter portrait (612 × 792pt), 0.5in sides (540pt), Arial 9pt. The form no., title, agency, the account block and
     * the column headings are the table's <thead>, so they repeat on every page. Mirrors scripts/gen_gl_docx.js.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $v = static fn (string $s) => $s !== '' ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : '&nbsp;';
        $cell = static fn (array $r) => '<td class="mon">' . $v($r['mon'] ?? '') . '</td><td class="day">' . $v($r['day'] ?? '') . '</td>'
            . '<td class="part">' . $v($r['text'] ?? '') . '</td><td class="ref">' . $v($r['ref'] ?? '') . '</td>'
            . '<td class="am">' . $v($r['d'] ?? '') . '</td><td class="am">' . $v($r['c'] ?? '') . '</td><td class="am">' . $v($r['bal'] ?? '') . '</td>';

        $body  = '<tr class="yr"><td colspan="2" class="b">' . $e($m['year']) . '</td><td></td><td></td><td></td><td></td><td></td></tr>';
        $count = 1;
        foreach ($m['rows'] as $r) {
            $body .= '<tr>' . ($r['kind'] === 'gap' ? $cell([]) : $cell($r)) . '</tr>';
            $count++;
        }
        for (; $count < $m['min_rows']; $count++) {
            $body .= '<tr>' . $cell([]) . '</tr>';
        }
        $body .= '<tr class="close">' . $cell($m['ending']) . '</tr>';

        $head = '<tr class="h"><td colspan="7" class="formno">' . $e($m['form_no']) . '</td></tr>'
            . '<tr class="h"><td colspan="7" class="title">GENERAL LEDGER</td></tr>'
            . '<tr class="h"><td colspan="7" class="agency"><div class="ag">' . $e($m['agency']) . '</div><div class="aglbl">Agency</div></td></tr>'
            . '<tr class="h"><td colspan="7"><table class="acct">'
            . '<tr><td class="lbl" style="width:15%;">Fund:</td><td class="ul" style="width:41%;">' . $v($m['fund']) . '</td><td style="width:4%;"></td>'
            . '<td class="lbl" style="width:15%;">Account Code:</td><td class="ul" style="width:25%;">' . $v($m['code']) . '</td></tr>'
            . '<tr><td class="lbl">Account Title:</td><td class="ul">' . $v($m['title']) . '</td><td></td>'
            . '<td class="lbl">Sheet No.</td><td class="ul">' . $v($m['sheet']) . '</td></tr>'
            . '</table></td></tr>'
            . '<tr class="h"><td colspan="7" class="gap"></td></tr>'
            . '<tr class="cols"><th colspan="2">Date</th><th>Particulars</th><th>Reference</th><th>Debit</th><th>Credit</th><th>Balance</th></tr>';

        $pageCss = $media === 'print'
            ? '@page{size:letter portrait;margin:36pt;}body{margin:0;}'
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:612pt;min-height:792pt;margin:0 auto;padding:36pt;box-sizing:border-box;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';
        // Dompdf ignores <colgroup> widths, so the first (hidden) row carries them
        $widths = '<tr class="w"><td style="width:7%;"></td><td style="width:4%;"></td><td style="width:31%;"></td><td style="width:10%;"></td>'
            . '<td style="width:16%;"></td><td style="width:16%;"></td><td style="width:16%;"></td></tr>';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:9pt;color:#000;}'
            . 'table.gl{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . 'table.gl tr.w td{height:0;padding:0;border:none;}'
            . 'table.gl tr.h > td{border:none;padding:1pt 2pt;}'
            . '.formno{font-size:8pt;font-style:italic;}'
            . '.title{text-align:center;font-size:13pt;font-weight:bold;padding:2pt 0 6pt !important;}'
            . '.agency{text-align:center;padding:0 0 6pt !important;}.ag{font-weight:bold;display:inline-block;min-width:45%;border-bottom:.75pt solid #000;}.aglbl{font-size:8pt;}'
            . 'table.acct{width:100%;border-collapse:collapse;table-layout:fixed;}table.acct td{padding:3pt 2pt 1pt;vertical-align:bottom;border:none;}'
            . 'table.acct td.lbl{font-weight:bold;white-space:nowrap;}table.acct td.ul{border-bottom:.75pt solid #000 !important;font-weight:bold;}'
            . 'td.gap{height:4pt;}'
            . 'tr.cols th{border:1pt solid #000;font-weight:bold;text-align:center;padding:3pt 2pt;}'
            . 'tbody td{border-left:.75pt solid #000;border-right:.75pt solid #000;border-bottom:.5pt solid #999;padding:1pt 3pt;height:10.5pt;}'
            . 'tbody td.mon{border-right:none;white-space:nowrap;}tbody td.day{border-left:none;text-align:right;}'
            . 'td.ref{text-align:center;}td.am{text-align:right;white-space:nowrap;}'
            . 'tr.yr td{border-bottom:.5pt solid #999;}.b{font-weight:bold;}'
            . 'tr.close td{border-top:1.2pt solid #000;border-bottom:1.2pt solid #000;font-weight:bold;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '', '')
            . '<table class="gl"><thead>' . $widths . $head . '</thead><tbody>' . $body . '</tbody></table>'
            . PrintService::bodyClose($media, '');
    }
}
