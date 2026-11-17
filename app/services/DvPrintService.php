<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * DV (Disbursement Voucher — General Form No. 1, revised November 3, 1992)
 * print form. PDF ONLY: the official form's PDF is filled with pdftk (no HTML /
 * DOCX layout of its own), ported from the old REGIONAL
 * DisbursementService::buildDvFieldMap() / fillDvTemplate().
 *
 * Two papers (payload `paper`):
 *   plain      (default) — _shared/template/dv_form.pdf (dv.pdf's fields moved onto
 *              the boxes of the scanned form) filled, flattened, laid over
 *              _shared/template/dv_background.pdf page 1 (the cleaned scan of the
 *              official form), plus page 2 of it — the back: Instructions +
 *              Checklist (`back_page`, default true).
 *   preprinted — _shared/template/dv.pdf (REGIONAL's field layout for the
 *              physical green forms) filled and flattened, text only, to print
 *              onto pre-printed DV stock. No back page.
 * The background and the aligned field layout were built from the scan in
 * ACCOUNTING/app/pdf-forms/templates/DV-10-9-2026.pdf — see
 * system_docs/modules/PRINT-FORMS.md "DV".
 *
 * Printed through PrintService::print('DV', …, 'pdf') — see PrintService::DOCS.
 */
class DvPrintService
{
    private const FORM_PLAIN      = 'dv_form.pdf';
    private const FORM_PREPRINTED = 'dv.pdf';
    private const BACKGROUND      = 'dv_background.pdf';   // page 1 = front, page 2 = back

    // How many rows the form has.
    private const ENTRY_ROWS     = 11;
    private const DEDUCTION_ROWS = 12;
    // What the Particulars box holds (≈ 3 lines above the deductions list).
    private const PARTICULARS_MAX = 270;

    /**
     * DV model. Payload (all optional):
     *   voucher_number, route ('MAIN' default | 'REGIONAL' — who signs which box,
     *   and the paying agency's "- MAIN OFFICE" / "- REGIONAL OFFICE"),
     *   paying_agency (overrides the agency line), payee_name, payee_address,
     *   payee_tin, particulars, gross_amount (or amount), deductions [{ description,
     *   amount }] (up to 12 lines + the total), total_deductions (default their sum),
     *   accounting_entries [{ obligation, fpa, object_code, account_code, debit,
     *   credit }] (up to 11 rows),
     *   Box A: cert_officer1_name / _title (one signer), + cert_officer2_name /
     *          _title (a second co-signer, e.g. the Regional Manager),
     *   Box B (accountant's certification): MAIN — fmd_officer_name / _title
     *          (blank → the current FMD Department Manager) + accountant_name /
     *          _title; REGIONAL — accountant_name / _title alone,
     *   Box C: approving_officer / approver_title (REGIONAL route; MAIN leaves it blank),
     *   paper ('plain' | 'preprinted'), back_page (plain paper only; default true).
     *
     * More than the form holds (the voucher module allows any number of lines and any length): the box keeps what fits
     * and says "see attached", and an attachment page (after the front) lists everything —
     *   accounting entries > 11: rows 1–10, then row 11 "SEE ATTACHED" carrying the sum of the remaining debits / credits
     *     (the columns still add up);
     *   deductions > 12: rows 1–11, then row 12 "See attached" with the rest's sum (the total stays right);
     *   particulars longer than the box: its start + "... (see attached)".
     */
    public function model(array $p): array
    {
        $t      = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $fmt    = static fn (float $n) => number_format($n, 2);
        $fmtOpt = static fn (float $n) => $n > 0 ? number_format($n, 2) : '';
        $isMain = strtoupper($t('route') ?: 'MAIN') !== 'REGIONAL';
        $gross  = PrintService::amount($p['gross_amount'] ?? $p['amount'] ?? null);
        $two    = $t('cert_officer2_name') !== '';   // Box A with two co-signers

        $payee = $t('payee_name') . ($t('payee_address') !== '' ? ', ' . $t('payee_address') : '');

        $fmdName = $t('fmd_officer_name');
        if ($isMain && $fmdName === '') {
            $fmdName = PrintService::resolveSignatoryName('FMD Department Manager') ?? '';
        }

        $fields = [
            'voucher_id'   => $t('voucher_number'),
            'AGENCY'       => $t('paying_agency') ?: 'NATIONAL HOUSING AUTHORITY - ' . ($isMain ? 'MAIN OFFICE' : 'REGIONAL OFFICE'),
            'PAYEE'        => $payee,
            'TIN'          => $t('payee_tin'),
            'PARTICULARS'  => self::fitParticulars(trim((string) ($p['particulars'] ?? ''))),
            'AMOUNT'       => $gross > 0 ? $fmt($gross) : '',
            'AMOUNT_WORDS' => $gross > 0 ? $this->amountInWords($gross) : '',
            // Box A — one signer centred (SIGN_1), or two side by side (SIGN_7 / SIGN_8)
            'SIGN_1' => $two ? '' : $t('cert_officer1_name'), 'POS_1' => $two ? '' : $t('cert_officer1_title'),
            'SIGN_7' => $two ? $t('cert_officer1_name') : '', 'POS_7' => $two ? $t('cert_officer1_title') : '',
            'SIGN_8' => $two ? $t('cert_officer2_name') : '', 'POS_8' => $two ? $t('cert_officer2_title') : '',
            // Box B certification — MAIN: FMD officer + accountant; REGIONAL: the accountant (wider SIGN_6)
            'SIGN_2' => $isMain ? $fmdName : '', 'POS_2' => $isMain ? ($t('fmd_officer_title') ?: ($fmdName !== '' ? 'Department Manager, FMD' : '')) : '',
            'SIGN_3' => $isMain ? $t('accountant_name') : '', 'POS_3' => $isMain ? $t('accountant_title') : '',
            'SIGN_6' => $isMain ? '' : $t('accountant_name'), 'POS_6' => $isMain ? '' : $t('accountant_title'),
            // Box C — approving officer (REGIONAL route)
            'SIGN_4' => $isMain ? '' : $t('approving_officer'), 'POS_4' => $isMain ? '' : $t('approver_title'),
        ];

        $entries = array_values(is_array($p['accounting_entries'] ?? null) ? $p['accounting_entries'] : []);
        $allEntries = $entries;
        if (count($entries) > self::ENTRY_ROWS) {   // the last row carries the rest — "SEE ATTACHED", their sums
            $rest = array_slice($entries, self::ENTRY_ROWS - 1);
            $sum  = static fn (string $k) => array_sum(array_map(static fn ($e) => PrintService::amount($e[$k] ?? $e[$k . '_amount'] ?? null), $rest));
            $entries = array_merge(array_slice($entries, 0, self::ENTRY_ROWS - 1), [['account_code' => 'SEE ATTACHED', 'debit' => $sum('debit'), 'credit' => $sum('credit')]]);
        }
        for ($i = 0; $i < self::ENTRY_ROWS; $i++) {
            $n = $i + 1;
            $e = $entries[$i] ?? [];
            $fields["OBLG_{$n}"]             = PrintService::text($e['obligation'] ?? '');
            $fields["OBLG_FPA_{$n}"]         = PrintService::text($e['fpa'] ?? '');
            $fields["OBJ_CODE_{$n}"]         = PrintService::text($e['object_code'] ?? '');
            $fields["acct_code_{$n}"]        = PrintService::text($e['account_code'] ?? '');
            $fields["acct_code_{$n}_debit"]  = $fmtOpt(PrintService::amount($e['debit'] ?? $e['debit_amount'] ?? null));
            $fields["acct_code_{$n}_credit"] = $fmtOpt(PrintService::amount($e['credit'] ?? $e['credit_amount'] ?? null));
        }

        $deductions = array_values(array_filter(array_map(static fn ($d) => [
            'label'  => PrintService::text($d['description'] ?? $d['label'] ?? '') ?: 'Other Deduction',
            'amount' => PrintService::amount($d['amount'] ?? null),
        ], is_array($p['deductions'] ?? null) ? $p['deductions'] : []), static fn ($d) => $d['amount'] > 0));
        $shown = $deductions;
        if (count($deductions) > self::DEDUCTION_ROWS) {   // the last row carries the rest — "Others (see attached)", their sum
            $rest  = array_slice($deductions, self::DEDUCTION_ROWS - 1);
            $shown = array_merge(array_slice($deductions, 0, self::DEDUCTION_ROWS - 1),
                [['label' => 'See attached', 'amount' => array_sum(array_column($rest, 'amount'))]]);   // short: the label column is narrow
        }
        foreach ($shown as $i => $d) {
            $fields['DEDUC_' . ($i + 1)]     = $d['label'];
            $fields['DEDUC_AMT_' . ($i + 1)] = $fmt($d['amount']);
        }
        if ($deductions) {
            $total = isset($p['total_deductions']) && $p['total_deductions'] !== ''
                ? PrintService::amount($p['total_deductions'])
                : array_sum(array_column($deductions, 'amount'));
            $fields['DEDUC_13']    = 'Total Deductions';
            $fields['DEDUC_TOTAL'] = $fmt($total);
        }

        $paper  = strtolower($t('paper')) === 'preprinted' ? 'preprinted' : 'plain';
        $number = $t('voucher_number');

        // the attachment page — only when something did not fit on the form
        $particulars = trim((string) ($p['particulars'] ?? ''));
        $annex = null;
        if (count($allEntries) > self::ENTRY_ROWS || count($deductions) > self::DEDUCTION_ROWS || $fields['PARTICULARS'] !== $particulars) {
            $annex = [
                'voucher_number' => $number,
                'payee'          => $payee,
                'particulars'    => $particulars,
                'entries'        => array_map(static fn ($e) => [
                    'obligation'   => PrintService::text($e['obligation'] ?? ''),
                    'object_code'  => PrintService::text($e['object_code'] ?? ''),
                    'account_code' => PrintService::text($e['account_code'] ?? ''),
                    'debit'        => PrintService::amount($e['debit'] ?? $e['debit_amount'] ?? null),
                    'credit'       => PrintService::amount($e['credit'] ?? $e['credit_amount'] ?? null),
                ], $allEntries),
                'deductions'     => $deductions,
                'gross'          => $gross,
            ];
        }

        return [
            'annex'     => $annex,
            'fields'    => $fields,
            'paper'     => $paper,
            'back_page' => $paper === 'plain' && !in_array($p['back_page'] ?? true, [false, 'false', 0, '0'], true),
            'file_base' => $number !== '' ? preg_replace('/[^A-Za-z0-9\-]/', '_', $number) : 'DV-DRAFT',
        ];
    }

    /** The filled DV as PDF bytes, or null (logged) when pdftk fails. */
    public function pdf(array $m): ?string
    {
        $dir  = PrintService::repoRoot() . '/_shared/template/';
        $form = $dir . ($m['paper'] === 'preprinted' ? self::FORM_PREPRINTED : self::FORM_PLAIN);
        if (!is_file($form) || ($m['paper'] === 'plain' && !is_file($dir . self::BACKGROUND))) {
            App\LogError('DvPrintService: template missing in ' . $dir);

            return null;
        }

        $tmp   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dv_' . bin2hex(random_bytes(8));
        $files = ['fdf' => $tmp . '.fdf', 'filled' => $tmp . '_filled.pdf', 'front' => $tmp . '_front.pdf', 'out' => $tmp . '_out.pdf',
                  'form' => $tmp . '_form.pdf', 'background' => $tmp . '_bg.pdf', 'annex' => $tmp . '_annex.pdf'];
        try {
            // pdftk reads its inputs in many small reads — painfully slow on a mounted
            // folder (20 s+ per call in the Docker dev setup); local temp copies take < 1 s.
            if (!copy($form, $files['form']) || ($m['paper'] === 'plain' && !copy($dir . self::BACKGROUND, $files['background']))) {
                App\LogError('DvPrintService: could not copy the templates to ' . sys_get_temp_dir());

                return null;
            }
            file_put_contents($files['fdf'], $this->fdf($m['fields']));
            if (!$this->pdftk([$files['form'], 'fill_form', $files['fdf'], 'output', $files['filled'], 'flatten'])) {
                return null;
            }
            $result = $files['filled'];

            if ($m['paper'] === 'plain') {
                // the official form behind the values
                if (!$this->pdftk([$files['filled'], 'multibackground', $files['background'], 'output', $files['front']])) {
                    return null;
                }
                $result = $files['front'];
            }

            // the pages: the front, the attachment (only when something did not fit), the back (plain paper, optional)
            $inputs = ['A=' . $result];
            $order  = ['A1'];
            if (!empty($m['annex'])) {
                $annex = $this->annexPdf($m['annex']);
                if ($annex === null || file_put_contents($files['annex'], $annex) === false) {
                    return null;
                }
                $inputs[] = 'C=' . $files['annex'];
                $order[]  = 'C';
            }
            if ($m['paper'] === 'plain' && $m['back_page']) {
                $inputs[] = 'B=' . $files['background'];
                $order[]  = 'B2';
            }
            if (count($order) > 1) {
                if (!$this->pdftk(array_merge($inputs, ['cat'], $order, ['output', $files['out']]))) {
                    return null;
                }
                $result = $files['out'];
            }

            $bytes = file_get_contents($result);

            return ($bytes === false || $bytes === '') ? null : $bytes;
        } finally {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }

    /** The Particulars box's text: as typed when it fits, else its start (cut at a word) + "... (see attached)". */
    private static function fitParticulars(string $text): string
    {
        if (mb_strlen($text) <= self::PARTICULARS_MAX) {
            return $text;
        }
        $cut = mb_substr($text, 0, self::PARTICULARS_MAX - 20);
        $cut = preg_replace('/\s+\S*$/u', '', $cut) ?: $cut;   // end on a whole word

        return rtrim($cut, " ,;:-") . '... (see attached)';   // ASCII dots: the form's font has no "…"
    }

    /**
     * The attachment page (legal, Dompdf): everything the form could not hold — the full Particulars, every accounting
     * entry with its totals, every deduction with the total — headed by the voucher number and payee.
     */
    private function annexPdf(array $a): ?string
    {
        $e     = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $money = static fn (float $v) => $v != 0 ? number_format($v, 2) : '';
        $html  = '<p class="t">DISBURSEMENT VOUCHER — ATTACHMENT</p>'
            . '<table class="h"><tr><td><b>Voucher No.:</b> ' . $e($a['voucher_number']) . '</td></tr>'
            . '<tr><td><b>Payee:</b> ' . $e($a['payee']) . '</td></tr></table>';
        if ($a['particulars'] !== '') {
            $html .= '<p class="s">PARTICULARS OF PAYMENT</p><p class="part">' . nl2br($e($a['particulars']), false) . '</p>';
        }
        if (count($a['entries']) > self::ENTRY_ROWS) {
            $rows = '';
            $dr = $cr = 0.0;
            foreach ($a['entries'] as $x) {
                $dr += $x['debit'];
                $cr += $x['credit'];
                $rows .= '<tr><td>' . $e($x['obligation']) . '</td><td>' . $e($x['object_code']) . '</td><td class="c">' . $e($x['account_code'])
                    . '</td><td class="r">' . $money($x['debit']) . '</td><td class="r">' . $money($x['credit']) . '</td></tr>';
            }
            $html .= '<p class="s">ACCOUNTING ENTRIES</p><table class="g"><tr><th style="width:30%;">Obligation</th><th style="width:16%;">Object Code</th>'
                . '<th style="width:18%;">Account Code</th><th style="width:18%;">Debit</th><th style="width:18%;">Credit</th></tr>' . $rows
                . '<tr class="tot"><td colspan="3" class="r">TOTAL</td><td class="r">' . number_format($dr, 2) . '</td><td class="r">' . number_format($cr, 2) . '</td></tr></table>';
        }
        if (count($a['deductions']) > self::DEDUCTION_ROWS) {
            $rows = '';
            foreach ($a['deductions'] as $d) {
                $rows .= '<tr><td>' . $e($d['label']) . '</td><td class="r">' . number_format($d['amount'], 2) . '</td></tr>';
            }
            $html .= '<p class="s">DEDUCTIONS</p><table class="g"><tr><th style="width:70%;">Deduction</th><th style="width:30%;">Amount</th></tr>' . $rows
                . '<tr class="tot"><td class="r">Total Deductions</td><td class="r">' . number_format(array_sum(array_column($a['deductions'], 'amount')), 2) . '</td></tr></table>';
        }
        $doc = '<!doctype html><html><head><meta charset="utf-8"><style>'
            . '@page{size:legal portrait;margin:54pt 54pt;}body{font-family:Helvetica,Arial,sans-serif;font-size:10pt;color:#000;}'
            . '.t{font-size:13pt;font-weight:bold;text-align:center;margin:0 0 12pt;}.s{font-weight:bold;margin:14pt 0 4pt;}'
            . '.h{border-collapse:collapse;width:100%;}.h td{padding:1pt 0;}.part{text-align:justify;line-height:1.35;margin:0;}'
            . '.g{border-collapse:collapse;width:100%;}.g th,.g td{border:.75pt solid #000;padding:3pt 5pt;vertical-align:top;}'
            . '.g th{background:#eee;}.c{text-align:center;}.r{text-align:right;white-space:nowrap;}.tot td{font-weight:bold;}'
            . '</style></head><body>' . $html . '</body></html>';
        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($doc, 'UTF-8');
            $dompdf->setPaper('legal', 'portrait');
            $dompdf->render();

            return $dompdf->output();
        } catch (\Throwable $ex) {
            App\LogError('DvPrintService::annexPdf ' . $ex->getMessage());

            return null;
        }
    }

    /** Runs pdftk with the given arguments; false (logged) on failure. */
    private function pdftk(array $args): bool
    {
        $bin = getenv('PDFTK_PATH') ?: (strncasecmp(PHP_OS, 'WIN', 3) === 0 ? 'C:/Program Files (x86)/PDFtk Server/bin/pdftk.exe' : 'pdftk');
        exec(escapeshellarg($bin) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            App\LogError('DvPrintService: pdftk ' . ($args[1] ?? '') . ' failed: ' . implode(' ', $out));

            return false;
        }

        return true;
    }

    /**
     * FDF for pdftk fill_form — plain "(…)" strings in PDFDocEncoding, as the old
     * REGIONAL / ACCOUNTING generators (UTF-16 hex strings leave pdftk-java's
     * multiline fields blank). Text is converted to Windows-1252; "₱" → "P".
     */
    private function fdf(array $fields): string
    {
        $lines = [];
        foreach ($fields as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $value = str_replace('₱', 'P', (string) $value);
            $value = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value) ?: $value;
            $esc   = static fn ($v) => str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $v);
            $lines[] = '<< /T (' . $esc($name) . ') /V (' . $esc($value) . ') >>';
        }

        return "%FDF-1.2\n1 0 obj\n<< /FDF << /Fields [\n" . implode("\n", $lines)
            . "\n] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    /** "EIGHTY THOUSAND PESOS ONLY." / "… PESOS AND TWENTY-FIVE/100 ONLY." — the old REGIONAL DV wording. */
    private function amountInWords(float $amount): string
    {
        $cents = (int) round(abs($amount) * 100);
        $pesos = intdiv($cents, 100);
        $words = preg_replace('/\s+Pesos$/', '', PrintService::amountInWords((float) $pesos));
        $c     = $cents % 100;
        $centWords = '';
        if ($c > 0) {
            $centWords = preg_replace('/\s+Pesos$/', '', PrintService::amountInWords((float) $c));
        }

        return strtoupper($words . ' Peso' . ($pesos !== 1 ? 's' : '') . ($c > 0 ? ' and ' . $centWords . '/100' : '') . ' Only.');
    }
}
