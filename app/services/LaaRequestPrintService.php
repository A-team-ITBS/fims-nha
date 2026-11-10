<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * LAA release request — the regional office's memorandum asking for the release
 * of a Letter of Advice of Allotment (MEMORANDUM / FOR / THRU / SUBJECT, "May we
 * respectfully request …"). A separate document from the LAA itself
 * (LaaPrintService) — model + HTML (letter paper, Arial 11pt, no footer line).
 *
 * The memo layout (html() / scripts/gen_laareq_docx.js) is shared: the FTLA
 * request (FtlaRequestPrintService) builds the same model shape — date, rows,
 * subject, paragraphs (runs), closing, signatory — and prints through it.
 *
 * Printed through PrintService::print('LAAREQ', …); DOCX: scripts/gen_laareq_docx.js.
 * Ported from the old REGIONAL gen_laa_docx.js.
 */
class LaaRequestPrintService
{
    /**
     * Model. Payload (all optional): date (Y-m-d or text; blank → today),
     * for_name / for_title, thru_name / thru_title (each row prints only with a
     * name), subject (default "Request for the Release of Letter of Advice of
     * Allotment (LAA)"), description (or project_description — "for the
     * proposed budget for <description>"), amount, sig_name / sig_title /
     * sig_office (the requesting officer), laa_number (file name only).
     */
    public function model(array $p): array
    {
        $amount = PrintService::amount($p['amount'] ?? null);
        $desc   = PrintService::text($p['description'] ?? $p['project_description'] ?? '');
        $laaNo  = PrintService::text($p['laa_number'] ?? $p['allocation_number'] ?? '');

        return [
            'date'      => PrintService::longDate($p['date'] ?? ''),
            'rows'      => self::memoRows($p),
            'subject'   => PrintService::text($p['subject'] ?? '') ?: 'Request for the Release of Letter of Advice of Allotment (LAA)',
            // justified paragraphs, each a list of runs [text, bold]
            'paragraphs' => [
                [
                    ['text' => 'May we respectfully request for the release of Letter of Advice of Allotment (LAA) for the proposed budget for '
                        . ($desc !== '' ? $desc . ' ' : '') . 'amounting to ', 'bold' => false],
                    ['text' => PrintService::amountInWords($amount) . ' (Php ' . PrintService::money($amount) . ')', 'bold' => true],
                    ['text' => '.', 'bold' => false],
                ],
                [['text' => 'For your consideration and appropriate action please.', 'bold' => false]],
            ],
            'closing'   => 'Thank you.',
            'signatory' => [
                'name'   => PrintService::text($p['sig_name'] ?? ''),
                'title'  => PrintService::text($p['sig_title'] ?? ''),
                'office' => PrintService::text($p['sig_office'] ?? ''),
            ],
            'header_parts' => null,
            'footer_text'  => '',
            'file_base'    => 'LAA-REQUEST' . ($laaNo !== '' ? '-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $laaNo) : ''),
        ];
    }

    /** The memo's FOR / THRU rows from for_name / _title, thru_name / _title (each only with a name). */
    public static function memoRows(array $p): array
    {
        $rows = [];
        foreach (['for' => 'FOR', 'thru' => 'THRU'] as $key => $label) {
            $name = PrintService::text($p[$key . '_name'] ?? '');
            if ($name !== '') {
                $rows[] = ['label' => $label, 'name' => $name, 'title' => PrintService::text($p[$key . '_title'] ?? '')];
            }
        }

        return $rows;
    }

    /**
     * Letter paper (612 × 792pt), 1in sides (468pt text width), Arial 11pt.
     * Mirrors scripts/gen_laareq_docx.js. $media as CbaPrintService::html().
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($m['rows'] as $r) {
            $rows .= '<tr><td class="l">' . $e($r['label']) . '</td><td class="c">:</td><td><b>' . $e($r['name']) . '</b>'
                . '<div class="rt">' . ($e($r['title']) ?: '&nbsp;') . '</div></td></tr>';
        }
        $rows .= '<tr><td class="l">SUBJECT</td><td class="c">:</td><td><b>' . $e($m['subject']) . '</b></td></tr>';

        $paragraphs = '';
        foreach ($m['paragraphs'] as $runs) {
            $paragraphs .= '<p class="j">';
            foreach ($runs as $run) {
                $paragraphs .= $run['bold'] ? '<b>' . $e($run['text']) . '</b>' : $e($run['text']);
            }
            $paragraphs .= '</p>';
        }
        $sig = $m['signatory'];

        [$top, $hdrCss] = PrintService::repeatingHeaderCss($m, 468, 90, 36, 14);
        $pageCss = $media === 'print'
            ? '@page{size:letter portrait;margin:' . $top . 'pt 72pt 54pt 72pt;}body{margin:0;}' . $hdrCss
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{position:relative;width:612pt;min-height:792pt;margin:0 auto;padding:36pt 72pt 54pt;'
              . 'box-sizing:border-box;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:11pt;color:#000;line-height:1.2;}'
            . 'p{margin:0;}'
            . '.hdr{text-align:center;margin-bottom:14pt;}'
            . '.hdr>img{width:auto !important;height:auto;max-width:468pt;max-height:90pt;}'
            . '.date{margin:0 0 14pt;}.memo{font-weight:bold;margin:0 0 14pt;}'
            . 'table.rows{border-collapse:collapse;width:468pt;}table.rows td{padding:0;vertical-align:top;}'
            . 'table.rows td.l{width:86.4pt;font-weight:bold;}table.rows td.c{width:21.6pt;}'
            . '.rt{margin-bottom:10pt;}'
            . '.rule{border-bottom:1pt solid #000;margin:8pt 0 12pt;}'
            . '.j{text-align:justify;margin:0 0 14pt;}'
            . '.sig{margin-top:44pt;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '<div class="hdr">' . PrintService::headerHtml($m, 468, 'LAAREQ') . '</div>', '')
            . '<p class="date">' . $e($m['date']) . '</p>'
            . '<p class="memo">MEMORANDUM</p>'
            . '<table class="rows">' . $rows . '</table>'
            . '<div class="rule"></div>'
            . $paragraphs
            . '<p>' . $e($m['closing']) . '</p>'
            . '<div class="sig"><b>' . ($e($sig['name']) ?: '&nbsp;') . '</b><br>' . $e($sig['title'])
            . ($sig['office'] !== '' ? '<br>' . $e($sig['office']) : '') . '</div>'
            . PrintService::bodyClose($media, '');
    }
}
