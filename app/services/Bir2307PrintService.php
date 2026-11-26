<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * BIR Form 2307 — Certificate of Creditable Tax Withheld at Source (January 2018 ENCS), for ONE Disbursement Voucher
 * with withholding tax (ATC) lines. PDF only: _shared/template/bir_2307.pdf (the old ACCOUNTING template, its AcroForm
 * fields named by hand) filled with pdftk (PrintService::fillPdfForm()), page 1 kept.
 *
 * Like the old ACCOUNTING "BIR Form 2307" window (dv-processing.php #bir2307Modal), the print preview first shows the
 * form's details, prefilled by prefill() (GET /print/prefill/bir2307/{dvId}), to check / change before printing:
 *   editable — 1 quarter + period from / to; Part I payee TIN, name, address, ZIP; each Part III row's description;
 *              the signatory (picked from a list — NHA's default signatory first, then the DV's own Accounting
 *              signatories — or typed), title, TIN, date signed;
 *   locked   — Part II payor (NHA: TIN, nature of business, name, address, ZIP) and each row's ATC / income payment /
 *              tax withheld (locked to the DV's tax lines).
 * The edits come back in the payload (EDITABLE keys) and are not saved.
 *
 * Payload: { dv_id, …EDITABLE }. Defaults:
 *   - period: the quarter of the DV date; each row's whole tax base goes in the column of the DV date's month, counted
 *     within the printed period's quarter (a DV month outside it → the 1st month);
 *   - payee: the DV's vendor (name, TIN, address), else the DV's claimant / claimant address / TIN;
 *   - Part III: the DV's ATC lines with tax withheld (fims.disbursement_voucher_atc_lines + fims.atc_codes) — income-tax
 *     codes (WI / WC …) in the EWT table (rows 1–10), percentage-tax codes (WB…) in the business-tax table (rows
 *     13–22); withholding VAT (WV…) is not on a 2307; over 10 rows per table are left off (the form has 10);
 *   - payor + default signatory: fims.app_settings (bir_payor_* / bir_signatory_*), else the old module's values
 *     (FALLBACK — NATIONAL HOUSING AUTHORITY, CRISALYN T. HUGO …).
 * A DV without an EWT / percentage-tax line has no 2307: prefill() / model() throw (→ HTTP 400 with the reason).
 */
class Bir2307PrintService
{
    private const TEMPLATE = 'bir_2307.pdf';
    private const ROWS     = 10;   // rows per Part III table
    private const DESC_MAX = 96;   // a description cell holds two lines of 6 pt text

    // fims.app_settings keys → the old ACCOUNTING module's hard-coded values, used while a key isn't set
    private const FALLBACK = [
        'bir_payor_tin'       => '000-916-384-000',
        'bir_payor_name'      => 'NATIONAL HOUSING AUTHORITY',
        'bir_payor_address'   => 'Elliptical Rd., Diliman, Quezon City',
        'bir_payor_zip'       => '1101',
        'bir_payor_nature'    => 'Government Agency',
        'bir_signatory_name'  => 'CRISALYN T. HUGO',
        'bir_signatory_title' => 'Officer-In-Charge, Acctg. Dept.',
        'bir_signatory_tin'   => '416-806-839',
    ];

    // What the preview's details panel may change (sent back in the payload); 'descriptions' is a list by row.
    public const EDITABLE = ['period_from', 'period_to', 'payee_name', 'payee_tin', 'payee_address', 'payee_zip',
                             'signatory_name', 'signatory_title', 'signatory_tin', 'signatory_date', 'descriptions'];

    /**
     * The details panel's data: { editable: {…EDITABLE, quarter}, locked: {dv_no, dv_date, gross, payor_*, rows
     * [{atc_code, rate, tax_base, withholding_tax}]}, signatories: [{name, title, tin}] }.
     */
    public function prefill(int $dvId): array
    {
        [$dv, $set, $rows, $period] = $this->load($dvId);

        return [
            'editable'    => self::editableDefaults($dv, $set, $rows, $period) + ['quarter' => $period['q']],
            'locked'      => [
                'dv_no'         => $dv['dv_no'],
                'dv_date'       => $dv['dv_date'],
                'fiscal_year'   => $period['year'],
                'gross'         => round((float) $dv['gross_amount'], 2),
                'payor_tin'     => $set['bir_payor_tin'],
                'payor_nature'  => $set['bir_payor_nature'],
                'payor_name'    => $set['bir_payor_name'],
                'payor_address' => $set['bir_payor_address'],
                'payor_zip'     => $set['bir_payor_zip'],
                'rows'          => array_map(static fn ($l) => [
                    'atc_code' => $l['atc_code'], 'rate' => $l['rate'] !== null ? (float) $l['rate'] : null,
                    'tax_base' => round((float) $l['tax_base'], 2), 'withholding_tax' => round((float) $l['withholding_tax'], 2),
                ], $rows),
            ],
            'signatories' => self::signatories($dv, $set),
        ];
    }

    /** The form's field values. Payload: { dv_id } + optionally the EDITABLE values from the preview's details panel. */
    public function model(array $p): array
    {
        [$dv, $set, $rows, $period] = $this->load((int) ($p['dv_id'] ?? 0));
        $def = self::editableDefaults($dv, $set, $rows, $period);
        $v   = [];
        foreach (self::EDITABLE as $k) {   // an edited value (blank included) wins over the prefilled one
            if ($k === 'descriptions') {
                $sent = is_array($p[$k] ?? null) ? array_values($p[$k]) : [];
                $v[$k] = array_map(static fn ($d, $i) => array_key_exists($i, $sent) && $sent[$i] !== null ? PrintService::text($sent[$i]) : $d,
                    $def[$k], array_keys($def[$k]));
                continue;
            }
            $v[$k] = array_key_exists($k, $p) && $p[$k] !== null ? PrintService::text($p[$k]) : $def[$k];
        }

        // the printed period (default: the DV date's quarter); the DV's month is one of its quarter's three columns
        $from   = strtotime($v['period_from']) ?: $period['from'];
        $to     = strtotime($v['period_to']) ?: $period['to'];
        $qStart = intdiv((int) date('n', $from) - 1, 3) * 3 + 1;
        $col    = (int) date('n', strtotime((string) $dv['dv_date']) ?: $from) - $qStart;
        $col    = $col >= 0 && $col <= 2 ? $col : 0;

        $digits = static fn ($x) => preg_replace('/\D/', '', (string) $x);
        $branch = static fn (string $tin) => strlen($tin) > 9 ? str_pad(substr($tin, 9, 5), 5, '0') : '';
        $money  = static fn (float $x) => $x != 0.0 ? number_format($x, 2) : '';
        $sum    = static fn (array $r, string $k) => round(array_sum(array_map(static fn ($x) => (float) $x[$k], $r)), 2);

        $payeeTin = $digits($v['payee_tin']);
        $payorTin = $digits($set['bir_payor_tin']);

        $fields = [
            'Period_From_1' => date('mdY', $from),   // MMDDYYYY combs
            'Period_To_1'   => date('mdY', $to),
            // Part I — payee (the template has no TIN_Payee_4: 3-3-3 + the 5-box branch)
            'TIN_Payee_1' => substr($payeeTin, 0, 3), 'TIN_Payee_2' => substr($payeeTin, 3, 3), 'TIN_Payee_3' => substr($payeeTin, 6, 3),
            'TIN_Payee_5' => $branch($payeeTin),
            'Payee_Name'  => $v['payee_name'],
            'Registered_Address' => $v['payee_address'],   // item 4 (payee)
            '4A ZIP Code' => $v['payee_zip'], 'Foreign Address if applicable' => '',
            // Part II — payor (NHA)
            'Payor_TIN_1' => substr($payorTin, 0, 3), 'Payor_TIN_2' => substr($payorTin, 3, 3), 'Payor_TIN_3' => substr($payorTin, 6, 3),
            'Payor_TIN_4' => $branch($payorTin),
            'Payor_Name'  => $set['bir_payor_name'],
            'Registered Address_2' => $set['bir_payor_address'],   // item 8 (payor)
            '8A ZIP Code' => $set['bir_payor_zip'],
            // signatures — the payor's (NHA's signatory) and the payee's conforme. The form has no box for the date the
            // payor signs (Date_issue / Date_expiry are a TAX AGENT's accreditation dates), so signatory_date isn't printed.
            'Signature_1' => $v['signatory_name'], 'Title_1' => $v['signatory_title'], 'TIN_1' => $v['signatory_tin'],
            'Signature_2' => $v['payee_name'], 'TIN_2' => $v['payee_tin'], 'Title_2' => '',
            'Tax_agent_1' => '', 'Tax_agent_2' => '', 'Date_issue_1' => '', 'Date_issue_2' => '', 'Date_expiry_1' => '', 'Date_expiry_2' => '',
            'ATCTotal' => '', 'ATCTotal_2' => '',
        ];

        // Part III — the two tables (rows keep their prefill order: EWT rows first, then WB rows)
        foreach ($rows as $i => &$r) {
            $r['description'] = $v['descriptions'][$i] ?? '';
        }
        unset($r);
        $isBiz = static fn ($r) => str_starts_with($r['atc_code'], 'WB');
        $ewt   = array_values(array_filter($rows, static fn ($r) => !$isBiz($r)));
        $biz   = array_values(array_filter($rows, $isBiz));
        foreach ([[$ewt, 'EWT_', 1, ''], [$biz, 'WBT_', 13, '_2']] as [$tbl, $descPrefix, $firstRow, $totSuffix]) {
            $shown = array_slice($tbl, 0, self::ROWS);
            foreach ($shown as $i => $l) {
                $n    = $firstRow + $i;
                $base = round((float) $l['tax_base'], 2);
                $fields[$descPrefix . ($i + 1)] = self::fitDescription((string) $l['description']);
                $fields['ATCRow' . $n]          = $l['atc_code'];
                foreach (['First', 'Second', 'Third'] as $c => $word) {
                    $fields["{$word}_Quarter_{$n}"] = $c === $col ? $money($base) : '';
                }
                $fields['TotalRow' . $n] = $money($base);
                $fields['TWQ_' . $n]     = $money(round((float) $l['withholding_tax'], 2));
            }
            $base = $sum($shown, 'tax_base');
            foreach (['First', 'Second', 'Third'] as $c => $word) {
                $fields["{$word}_Total{$totSuffix}"] = $c === $col ? $money($base) : '';
            }
            $fields['TotalTotal' . $totSuffix] = $money($base);
            $fields['TWQ_Total' . $totSuffix]  = $money($sum($shown, 'withholding_tax'));
        }

        return [
            'fields'    => $fields,
            'file_base' => 'BIR2307-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', (string) $dv['dv_no']) ?: (int) $p['dv_id']),
        ];
    }

    /** The filled 2307 (page 1) as PDF bytes, or null (logged). */
    public function pdf(array $m): ?string
    {
        return PrintService::fillPdfForm(self::TEMPLATE, $m['fields'], '1');
    }

    /**
     * The DV, NHA's settings (with FALLBACK), its 2307 rows — EWT rows first, then business-tax (WB) rows, each
     * {atc_code, rate, tax_base, withholding_tax, description} — and its period (the DV date's quarter: year, q, from,
     * to). Throws when there's no DV or nothing to certify.
     */
    private function load(int $dvId): array
    {
        if ($dvId <= 0) {
            throw new \InvalidArgumentException('dv_id is required.');
        }
        $dv = App\ExecuteRow(
            "SELECT dv.dv_no, dv.dv_date, dv.gross_amount, dv.particulars, dv.claimant_name, dv.claimant_address, dv.employee_no_tin,
                    dv.accounting_processed_by, dv.division_reviewed_by, dv.department_approved_by,
                    v.vendor_name, v.tin AS vendor_tin, v.address AS vendor_address
             FROM fims.disbursement_vouchers dv
             LEFT JOIN fims.vendors v ON v.vendor_id = dv.vendor_id
             WHERE dv.dv_id = {$dvId}",
            'DB'
        );
        if (!$dv) {
            throw new \InvalidArgumentException('Disbursement Voucher not found.');
        }
        $lines = App\ExecuteRows(
            "SELECT UPPER(TRIM(l.atc_code)) AS atc_code, COALESCE(l.rate, a.tax_rate) AS rate, l.tax_base, l.withholding_tax, a.description
             FROM fims.disbursement_voucher_atc_lines l
             LEFT JOIN fims.atc_codes a ON a.atc_code = l.atc_code
             WHERE l.dv_id = {$dvId} AND COALESCE(l.withholding_tax, 0) > 0
             ORDER BY l.line_number, l.dv_atc_line_id",
            'DB'
        ) ?: [];
        $lines = array_values(array_filter($lines, static fn ($l) => !str_starts_with($l['atc_code'], 'WV')));   // VAT withheld: not a 2307
        $isBiz = static fn ($l) => str_starts_with($l['atc_code'], 'WB');
        $rows  = array_merge(array_values(array_filter($lines, static fn ($l) => !$isBiz($l))), array_values(array_filter($lines, $isBiz)));
        if (!$rows) {
            throw new \InvalidArgumentException("DV {$dv['dv_no']} has no withholding tax (EWT / percentage tax) to certify on a BIR 2307.");
        }

        $set = self::agencySettings();

        $date   = strtotime((string) $dv['dv_date']) ?: time();
        $year   = (int) date('Y', $date);
        $qStart = intdiv((int) date('n', $date) - 1, 3) * 3 + 1;
        $period = ['year' => $year, 'q' => intdiv($qStart - 1, 3) + 1,
                   'from' => mktime(0, 0, 0, $qStart, 1, $year), 'to' => mktime(0, 0, 0, $qStart + 3, 0, $year)];

        return [$dv, $set, $rows, $period];
    }

    /**
     * NHA as the withholding agent on the BIR forms (2307, 2316): fims.app_settings' bir_payor_* / bir_signatory_* keys,
     * each falling back to the old module's value (FALLBACK) while it isn't set.
     */
    public static function agencySettings(): array
    {
        $set  = self::FALLBACK;
        $keys = implode(', ', array_map(static fn ($k) => "'{$k}'", array_keys(self::FALLBACK)));
        foreach (App\ExecuteRows("SELECT setting_key, setting_value FROM fims.app_settings WHERE setting_key IN ({$keys})", 'DB') ?: [] as $r) {
            if (trim((string) $r['setting_value']) !== '') {
                $set[$r['setting_key']] = PrintService::text($r['setting_value']);
            }
        }

        return $set;
    }

    /** The EDITABLE values before any edit (the old window's prefill). */
    private static function editableDefaults(array $dv, array $set, array $rows, array $period): array
    {
        return [
            'period_from'     => date('Y-m-d', $period['from']),
            'period_to'       => date('Y-m-d', $period['to']),
            'payee_name'      => PrintService::text($dv['vendor_name'] ?: $dv['claimant_name']),
            'payee_tin'       => PrintService::text($dv['vendor_tin'] ?: $dv['employee_no_tin']),
            'payee_address'   => PrintService::text($dv['vendor_address'] ?: $dv['claimant_address']),
            'payee_zip'       => '',
            'signatory_name'  => $set['bir_signatory_name'],
            'signatory_title' => $set['bir_signatory_title'],
            'signatory_tin'   => $set['bir_signatory_tin'],
            'signatory_date'  => '',
            // a row's description: its ATC's own wording, else the DV's particulars (as the old window)
            'descriptions'    => array_map(static fn ($r) => PrintService::text($r['description'] ?: $dv['particulars']), $rows),
        ];
    }

    /**
     * Who can sign for NHA (the old window's signatory list): NHA's default signatory (settings, CRISALYN T. HUGO) first,
     * then the DV's own Accounting signatories (Section Chief, Division Manager, Department Manager), without repeats.
     */
    private static function signatories(array $dv, array $set): array
    {
        $out = [['name' => $set['bir_signatory_name'], 'title' => $set['bir_signatory_title'], 'tin' => $set['bir_signatory_tin']]];
        $roles = ['accounting_processed_by' => 'Accounting Section Chief', 'division_reviewed_by' => 'Accounting Division Manager',
                  'department_approved_by' => 'Accounting Department Manager'];
        foreach ($roles as $col => $title) {
            $uid = (int) ($dv[$col] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $u = App\ExecuteRow("SELECT first_name, middle_name, last_name, suffix FROM uac.users WHERE user_id = {$uid}", 'DB');
            if (!$u) {
                continue;
            }
            $mid  = trim((string) $u['middle_name']);
            $name = strtoupper(trim(implode(' ', array_filter([trim((string) $u['first_name']), $mid !== '' ? mb_substr($mid, 0, 1) . '.' : '',
                trim((string) $u['last_name'])])) . (trim((string) $u['suffix']) !== '' ? ', ' . trim($u['suffix']) : '')));
            if ($name !== '' && !in_array($name, array_column($out, 'name'), true)) {
                $out[] = ['name' => $name, 'title' => $title, 'tin' => ''];
            }
        }

        return $out;
    }

    /** A description cut at a word to what its cell holds ("…" as "..." — the form's font has no ellipsis). */
    private static function fitDescription(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= self::DESC_MAX) {
            return $text;
        }
        $cut   = mb_substr($text, 0, self::DESC_MAX - 3);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false ? mb_substr($cut, 0, $space) : $cut, ' ,;-') . '...';
    }
}
