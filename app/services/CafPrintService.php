<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * CAF (Certificate as to Availability of Funds) print form — the model (every
 * printed word). The CAF is the same one-page certificate as the CBA, so html()
 * reuses CbaPrintService::html() and the DOCX is scripts/gen_cba_docx.js; the
 * model's kind = CAF picks the CAF's styles there.
 *
 * Printed through PrintService::print('CAF', …) — see PrintService::DOCS.
 *
 * EVERY CAF — main office or regional — is laid out like the CAF template
 * (CAF-2641-87-01.docx, signed by the Department Manager): see templateStyle().
 * What differs between the two is only the wording that names the payee (main
 * office: "to be released to …"; regional: "entered into with …" / "in favor of …",
 * the old REGIONAL gen_caf_docx.js wordings). The regional office no longer has a
 * layout of its own (it used to print "Certified:" + a blank name).
 * Wording ported from the old FMD gen_caf_docx.js / buildCafHtml()
 * ("CAF_if_for_LGU" template).
 */
class CafPrintService
{
    // Regional CAF body wordings (old REGIONAL gen_caf_docx.js / cafServices::detectCafBodyVariant()).
    public const REGIONAL_BODY_VARIANTS = ['entered_into_with', 'in_favor_of', 'in_favor_of_petty'];

    /**
     * CAF model. Payload (all optional):
     *   caf_number, allocation_number (or rfa_number / laa_number — the linked RFA / LAA,
     *   printed "per RFA# …"; ref_type 'LAA' prints "LAA#"), caf_amount (or amount),
     *   purpose (or description — printed as typed: "necessary to cover <purpose>"),
     *   payee_name ("name (CODE)" → name on the main-office form), payee_title (e.g.
     *   "represented by its Mayor, Hon. …"), payee_office_address,
     *   fund_source_name (printed as given, code included), date (Y-m-d or text;
     *   blank → today), approved_by_name (blank → the FMD Department Manager).
     *
     * issuing_office 'REGIONAL' (a Regional / District requesting office) only changes the
     * payee wording — body_variant:
     *     entered_into_with (default) "… necessary to cover <purpose> entered into with
     *                       <PAYEE> with principal office at <address> is available from
     *                       <fund> per RFA# <no>."
     *     in_favor_of        "… in favor of <PAYEE> necessary to cover <purpose> is
     *                       available from <fund> per RFA# <no>." (e.g. EHAP)
     *     in_favor_of_petty  "… in favor of <PAYEE>, <purpose> is available from <fund>."
     *                       — the title then reads "… AVAILABILITY OF FUND".
     * The old module guessed the variant from the purpose text; here the caller sends it.
     * A regional payload that names its certifier (certified_name, certified_title —
     * default "Chief Corporate Accountant A", certified_office) signs under that name;
     * otherwise, like the main office, the Department Manager signs.
     */
    public function model(array $p): array
    {
        $regional  = strtoupper(trim((string) ($p['issuing_office'] ?? ''))) === 'REGIONAL';
        $cafNumber = PrintService::text($p['caf_number'] ?? '');
        $refType   = strtoupper(PrintService::text($p['ref_type'] ?? '')) === 'LAA' ? 'LAA' : 'RFA';
        $refNo     = PrintService::text($p['allocation_number'] ?? $p['rfa_number'] ?? $p['laa_number'] ?? '');
        $amount    = PrintService::amount($p['caf_amount'] ?? $p['amount'] ?? null);
        $purpose   = PrintService::text($p['purpose'] ?? $p['description'] ?? '');
        // The fund source is printed as given, code and all ("NG Support - GAA (NG-GAA)"), as in the CAF template.
        $fund      = PrintService::text($p['fund_source_name'] ?? '');
        $address   = PrintService::text($p['payee_office_address'] ?? '');

        // "ONE MILLION & 50/100 PESOS (1,000,000.50)" — the CAF writes centavos with "&".
        $words = $amount > 0
            ? strtoupper(preg_replace('/ and (\d+\/100)/', ' & $1', PrintService::amountInWords($amount))) . ' (' . PrintService::money($amount) . ')'
            : '';

        $body = [['text' => 'This is to certify that the amount of ', 'bold' => false]];
        if ($words !== '') {
            $body[] = ['text' => $words, 'bold' => true];
        }

        // "necessary to cover <purpose>": the purpose is the RFA's description exactly as typed (the CAF template prints
        // "necessary to cover test") — no "the" is added. A blank purpose leaves the phrase out.
        $cover = $purpose !== '' ? $this->ordinalRuns(' necessary to cover ' . $purpose) : [];
        // a CAF whose lines carry no Type of Fund still reads properly, never "available from ."
        $available = ' is available from ' . ($fund !== '' ? $fund : 'the appropriate fund source')
            . ($refNo !== '' ? ' per ' . $refType . '# ' . $refNo : '') . '.';

        $title   = 'CERTIFICATE AS TO AVAILABILITY OF FUNDS';
        $variant = 'main';
        if ($regional) {
            $variant = in_array($p['body_variant'] ?? '', self::REGIONAL_BODY_VARIANTS, true) ? $p['body_variant'] : 'entered_into_with';
            $payee   = mb_strtoupper(PrintService::text($p['payee_name'] ?? ''));
            if ($variant === 'in_favor_of_petty') {
                $title  = 'CERTIFICATE AS TO AVAILABILITY OF FUND';
                $body[] = ['text' => ' in favor of ', 'bold' => false];
                $body[] = ['text' => $payee, 'bold' => true];
                $body[] = ['text' => ', ' . $purpose . ' is available from ' . ($fund !== '' ? $fund : 'the appropriate fund source') . '.', 'bold' => false];
            } elseif ($variant === 'in_favor_of') {
                $body[] = ['text' => ' in favor of ', 'bold' => false];
                $body[] = ['text' => $payee, 'bold' => true];
                $body = array_merge($body, $cover, [['text' => $available, 'bold' => false]]);
            } else {
                $body = array_merge($body, $cover);
                if ($payee !== '') {
                    $body[] = ['text' => ' entered into with ', 'bold' => false];
                    $body[] = ['text' => $payee, 'bold' => true];
                    if ($address !== '') {
                        $body[] = ['text' => ' with principal office at ' . $address, 'bold' => false];
                    }
                }
                $body[] = ['text' => $available, 'bold' => false];
            }
        } else {
            // The payee's dropdown label arrives as "name (CODE)" — print the plain name.
            $payee    = preg_replace('/\s*\([A-Z0-9\-]{1,12}\)\s*$/', '', PrintService::text($p['payee_name'] ?? ''));
            $payeeRep = PrintService::text($p['payee_title'] ?? '');           // e.g. "represented by its Mayor, Hon. …"
            $body     = array_merge($body, $cover);
            if ($payee !== '') {
                $body[] = ['text' => ' to be released to ', 'bold' => false];
                $body[] = ['text' => $payee . ($payeeRep !== '' ? ' ' . $payeeRep : ''), 'bold' => true];
                if ($address !== '') {
                    $body[] = ['text' => ', with principal office at ' . $address, 'bold' => false];
                }
            }
            $body[] = ['text' => $available, 'bold' => false];
        }

        // Signed by the Department Manager (the template), unless a regional payload names its certifier.
        $certName  = $regional ? PrintService::text($p['certified_name'] ?? '') : '';
        $signatory = $certName !== ''
            ? ['name' => $certName, 'title_lines' => array_values(array_filter([
                PrintService::text($p['certified_title'] ?? '') ?: 'Chief Corporate Accountant A',
                PrintService::text($p['certified_office'] ?? ''),
            ], static fn (string $l): bool => $l !== ''))]
            : PrintService::signatory($p['approved_by_name'] ?? '');

        $model = [
            'kind'              => 'CAF',
            'brand_document'    => 'CAF',
            'allocation_number' => $refNo !== '' ? $refType . '# ' . $refNo : '',   // footnote line 1
            'doc_title'         => $cafNumber !== '' ? $cafNumber : 'CAF',
            'date'              => PrintService::longDate($p['date'] ?? ''),
            'title'             => $title,
            'style'             => self::templateStyle(),
            'body'              => $body,
            'validity'          => '',
            'signatory'         => $signatory,
            'footnote'          => $fund !== '' ? [['label' => 'Source of Fund: ', 'value' => $fund]] : [],   // footnote line 2
            'reminders'         => ['heading' => '', 'items' => []],
            'footer_text'       => LetterheadLayoutService::DEFAULT_FOOTER_TEXT,
            'header_parts'      => null,
            'file_base'         => preg_match('/^CAF/i', $cafNumber)
                ? preg_replace('/[^A-Za-z0-9\-]/', '_', $cafNumber)
                : 'CAF-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', $cafNumber) ?: 'DRAFT'),
        ];
        if ($regional) {
            $model['issuing_office'] = 'REGIONAL';
        }

        return $model;
    }

    /**
     * The CAF template (CAF-2641-87-01.docx — signed by the Department Manager): 12pt, title 14pt bold italic, date
     * upright and centred over the right-hand part of the page, two empty lines above it and an empty line after
     * the date and after the title, a 0.5in tab, 1.5 lines, left-aligned, name bold italic, footnote 12pt italic
     * "Source of Fund: <bold>…". Used by every CAF, main office or regional.
     */
    private static function templateStyle(): array
    {
        return CbaPrintService::style([
            'size' => 12, 'title_size' => 14, 'title_italic' => true, 'date_italic' => false,
            'indent_pt' => 36, 'line_height' => 1.5, 'name_italic' => true,
            'foot_size' => 12, 'foot_italic' => true, 'foot_bold' => 'value',
            'date_align' => 'block', 'blank_top' => 2, 'blank_date' => 26, 'title_after' => 16, 'blank_title' => 14,
            'cert_align' => 'left', 'allocnum_after' => 4,
        ]);
    }

    /** The CBA's certificate layout, in the CAF's styles (model kind = CAF). */
    public function html(array $m, string $media = 'screen'): string
    {
        return (new CbaPrintService())->html($m, $media);
    }

    /** Plain text → body runs, with "1st" / "2nd" / … suffixes as superscript (the CAF template). */
    private function ordinalRuns(string $text): array
    {
        $runs = [];
        $parts = preg_split('/\b(\d+)(st|nd|rd|th)\b/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 0; $i < count($parts); $i += 3) {
            if ($parts[$i] !== '') {
                $runs[] = ['text' => $parts[$i], 'bold' => false];
            }
            if (isset($parts[$i + 1])) {
                $runs[] = ['text' => $parts[$i + 1], 'bold' => false];
                $runs[] = ['text' => $parts[$i + 2], 'bold' => false, 'sup' => true];
            }
        }

        return $runs;
    }
}
