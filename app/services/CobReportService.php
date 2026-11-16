<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * The COB "Generate Report" — a PDF or Excel file of one of the COB's tree tabs (Sources and Uses of Funds / Per Component / Per Region),
 * as deep as the user chooses, for the filters the page is showing. Built on the SAME tree the page shows (CobService::rollup(), so only
 * Approved requests, and only what the caller's RC may see), flattened to rows.
 *
 *   build()    the report model: title, meta (fiscal year, filters, unit, date), the rows, the grand total, and the two preview looks
 *   render()   the file: 'pdf' (Dompdf, landscape A4, "Page X of Y") or 'xlsx' (XlsxWriter) -> { filename, mime, data: base64 }
 *
 * The PDF is rendered from the very HTML the on-screen preview shows (pdfHtml()), so the two cannot drift; the Excel preview is an HTML imitation
 * of the sheet, from the same rows and palette. Amounts follow the page's Amount Unit; Excel cells keep the full peso value and only the number
 * format scales them (a trailing comma divides the shown number by 1,000).
 *
 * Columns, as on the page: Total | Fund Balance | Adjustment | Carry Over Obligation — Total = Fund Balance + Carry Over Obligation (Adjustment is already
 * inside Fund Balance), "Beginning Balance" groups the last three. There is no New Funds column (see system_docs/modules/COB-MONITORING.md).
 */
class CobReportService
{
    /** Deepest level a report can go to: 0 = the first level only … 4 = down to the accounts. */
    public const MAX_DEPTH = 4;
    public const FORMATS = ['json', 'pdf', 'xlsx'];
    public const UNITS = [
        'full' => ['label' => 'Full Amount', 'divisor' => 1, 'commas' => 0],
        'thousand' => ['label' => 'Thousands (K)', 'divisor' => 1e3, 'commas' => 1],
        'million' => ['label' => 'Millions (M)', 'divisor' => 1e6, 'commas' => 2],
        'billion' => ['label' => 'Billions (B)', 'divisor' => 1e9, 'commas' => 3],
    ];
    /**
     * A PDF past this many rows is refused; Excel has no such limit. Dompdf lays the whole table out in memory and slows down faster than the row count
     * grows — measured here: 500 rows ~4 s / 120 MB, 1,000 rows ~11 s / 280 MB (Xdebug off) — so 3,000 would exhaust the memory limit or the request time.
     */
    public const PDF_MAX_ROWS = 800;
    /** The on-screen preview shows this many rows at most (the file always has them all). */
    public const PREVIEW_MAX_ROWS = 400;

    private const LAYOUTS = [
        'fund_source' => [
            'title' => 'SOURCES AND USES OF FUNDS', 'heading' => 'Sources and Uses of Funds', 'slug' => 'Sources_and_Uses_of_Funds',
            'first' => 'TYPE OF FUND', 'grand' => 'TOTAL SOURCES OF FUNDS',
            'levels' => [
                ['Type of Fund', 'Type_of_Fund'],
                ['Type of Fund / Component', 'Fund_Component'],
                ['Type of Fund / Component / Program', 'Fund_Component_Program'],
                ['Type of Fund / Component / Program / Project', 'Fund_Component_Program_Project'],
                ['Type of Fund / Component / Program / Project / Account', 'Fund_Component_Program_Project_Account'],
            ],
        ],
        'component' => [
            'title' => 'USES OF FUNDS PER COMPONENT', 'heading' => 'Uses of Funds Per Component', 'slug' => 'Uses_of_Funds_Per_Component',
            'first' => 'COMPONENT', 'grand' => 'TOTAL USES OF FUNDS',
            'levels' => [
                ['Component', 'Component'],
                ['Component / Type of Fund', 'Component_Fund'],
                ['Component / Type of Fund / Program', 'Component_Fund_Program'],
                ['Component / Type of Fund / Program / Project', 'Component_Fund_Program_Project'],
                ['Component / Type of Fund / Program / Project / Account', 'Component_Fund_Program_Project_Account'],
            ],
        ],
        'region' => [
            'title' => 'USES OF FUNDS PER REGION', 'heading' => 'Uses of Funds Per Region', 'slug' => 'Uses_of_Funds_Per_Region',
            'first' => 'REGION / OFFICE', 'grand' => 'TOTAL USES OF FUNDS',
            'levels' => [
                ['Region', 'Region'],
                ['Region / Office', 'Region_Office'],
                ['Region / Office / Program', 'Region_Office_Program'],
                ['Region / Office / Program / Project', 'Region_Office_Program_Project'],
                ['Region / Office / Program / Project / Account', 'Region_Office_Program_Project_Account'],
            ],
        ],
    ];

    /** PDF palette (the old COB report's navy / light-blue) and the look of each depth. */
    private const PDF = ['head' => '#0f2045', 'total' => '#d0dff0', 'line' => '#cbd5e1', 'totalText' => '#0f172a', 'gold' => '#f59e0b'];
    private const PDF_DEPTH = [
        ['marker' => '', 'indent' => 0, 'fill' => '#dbe4f0', 'bold' => true, 'color' => '#0f172a'],
        ['marker' => '> ', 'indent' => 10, 'fill' => '#f8fafc', 'bold' => false, 'color' => '#334155'],
        ['marker' => '- ', 'indent' => 20, 'fill' => '#fdfdfe', 'bold' => false, 'color' => '#475569'],
        ['marker' => '· ', 'indent' => 30, 'fill' => '#ffffff', 'bold' => false, 'color' => '#64748b'],
        ['marker' => '+ ', 'indent' => 40, 'fill' => '#ffffff', 'bold' => false, 'color' => '#7c5cbf'],
    ];
    /** Excel palette (the Sources/Uses template): navy header, blue total, green first level (RGB, no '#'). */
    private const XL = ['head' => '1F497D', 'total' => '538DD5', 'fund' => 'C4D79B', 'comp' => 'EBF1DE', 'cat' => 'F6F9EF', 'projLine' => 'D9D9D9', 'projText' => '404040', 'ftText' => '595959'];

    private CobService $cob;

    public function __construct(?CobService $cob = null)
    {
        $this->cob = $cob ?? new CobService();
    }

    // -------------------------------------------------------------------------
    // Model
    // -------------------------------------------------------------------------

    /**
     * @return array{success: bool, data?: array, httpCode?: int, message?: string}
     */
    public function build(string $layoutBy, int $depth, array $filters, string $scopeRcId = '', string $unit = 'full'): array
    {
        if (!isset(self::LAYOUTS[$layoutBy])) {
            return ['success' => false, 'httpCode' => 400, 'message' => 'Unknown report layout: '.$layoutBy];
        }
        $depth = max(0, min(self::MAX_DEPTH, $depth));
        $unit = isset(self::UNITS[$unit]) ? $unit : 'full';
        $layout = self::LAYOUTS[$layoutBy];

        $tree = $this->cob->rollup($layoutBy, $filters, $scopeRcId);
        if (!$tree['success']) {
            return ['success' => false, 'httpCode' => 500, 'message' => 'Failed to load the report data.'];
        }

        $f = $this->cob->normalizeFilters($filters);
        $rows = [];
        $this->flatten($tree['data'], $depth, $f['fiscal_yr'] === null, $rows);

        $grand = $tree['totals'];

        return ['success' => true, 'data' => [
            'layout_by' => $layoutBy,
            'depth' => $depth,
            'title' => $layout['title'],
            'heading' => $layout['heading'],
            'first_column' => $layout['first'],
            'grand_label' => $layout['grand'],
            'include' => $layout['levels'][$depth][0],
            'slug' => $layout['slug'].'_'.$layout['levels'][$depth][1],
            'unit' => $unit,
            'unit_label' => self::UNITS[$unit]['label'],
            'fiscal_year' => $f['fiscal_yr'] !== null ? 'FY '.$f['fiscal_yr'] : 'All fiscal years',
            'filters' => $this->describeFilters($f),
            'generated' => date('F j, Y'),
            'row_count' => count($rows),
            'rows' => $rows,
            'grand' => [
                'total' => $grand['total'], 'fund_balance' => $grand['fund_balance'], 'adjustment' => $grand['adjustment'],
                'carry_over_obligation' => $grand['carry_over_obligation'],
            ],
        ]];
    }

    /** Tree -> flat, ordered rows { depth, type, label, total, fund_balance, adjustment, carry_over_obligation }, down to $maxDepth. */
    private function flatten(array $nodes, int $maxDepth, bool $showYear, array &$rows, int $depth = 0): void
    {
        foreach ($nodes as $n) {
            $rows[] = [
                'depth' => $depth,
                'type' => $n['type'],
                'label' => $this->labelOf($n, $showYear),
                'total' => $n['total'],
                'fund_balance' => $n['fund_balance'],
                'adjustment' => $n['adjustment'],
                'carry_over_obligation' => $n['carry_over_obligation'],
            ];
            if ($depth < $maxDepth && !empty($n['children'])) {
                $this->flatten($n['children'], $maxDepth, $showYear, $rows, $depth + 1);
            }
        }
    }

    private function labelOf(array $n, bool $showYear): string
    {
        $label = (string) $n['label'];
        switch ($n['type']) {
            case 'program':
                return $label.(!empty($n['sub']) ? ' — '.$n['sub'] : '');
            case 'project':
                // a project is budgeted once per request: with every fiscal year in the report the year tells the copies apart
                return $label.(!empty($n['code']) ? ' ['.$n['code'].']' : '').($showYear && !empty($n['fiscal_yr']) ? ' · FY '.$n['fiscal_yr'] : '');
            case 'account':
                return $label.(!empty($n['code']) ? ' ['.$n['code'].']' : '');
            default:
                return $label;
        }
    }

    /** The filters in force, as readable lines — names, not ids. */
    private function describeFilters(array $f): array
    {
        $lines = [];
        $name = static function (string $sql): ?string {
            $v = App\ExecuteScalar($sql, 'DB');

            return $v !== null && $v !== false && trim((string) $v) !== '' ? trim((string) $v) : null;
        };
        $str = static fn (string $v): string => App\QuotedValue($v, DataType::STRING, 'DB');

        try {
            if ($f['type_of_fund_id'] !== null) {
                $lines[] = 'Type of Fund: '.($name('SELECT name FROM fims.type_of_fund WHERE type_of_fund_id = '.$f['type_of_fund_id']) ?? '#'.$f['type_of_fund_id']);
            }
            if ($f['component_id'] !== null) {
                $lines[] = 'Component: '.($name('SELECT name FROM fims.component_type WHERE component_type_id = '.$f['component_id']) ?? '#'.$f['component_id']);
            }
            if ($f['region_id'] !== '') {
                $lines[] = 'Region: '.($f['region_id'] === 'central' ? 'Central Office'
                    : ($name('SELECT rc_name FROM fims.responsibility_code WHERE rc_id = '.$str($f['region_id'])) ?? $f['region_id']));
            }
            if ($f['program_code'] !== '') {
                $p = $name('SELECT program_name FROM fims.program WHERE program_code = '.$str($f['program_code']));
                $lines[] = 'Program: '.($p !== null ? $p.' ('.$f['program_code'].')' : $f['program_code']);
            }
            if ($f['project_code'] !== '') {
                $lines[] = 'Project: '.$f['project_code'];
            }
            if ($f['account_id'] !== '') {
                $a = $name('SELECT account_code FROM fims.chart_of_accounts WHERE account_id = '.$str($f['account_id']));
                $lines[] = 'Account: '.($a ?? $f['account_id']);
            }
        } catch (\Throwable $e) {
            App\LogError('CobReportService::describeFilters '.$e->getMessage());   // the report is still right, just a name short
        }
        if ($f['search'] !== '') {
            $lines[] = 'Search: "'.$f['search'].'"';
        }

        return $lines;
    }

    // -------------------------------------------------------------------------
    // Formatting
    // -------------------------------------------------------------------------

    private static function decimals(string $unit): int
    {
        return $unit === 'full' ? 2 : 3;   // scaled units read like 34.716
    }

    private static function fmt(float $v, string $unit): string
    {
        $d = self::decimals($unit);
        $x = round($v / self::UNITS[$unit]['divisor'], $d);

        return number_format(abs($x) < 0.5 * 10 ** -$d ? 0.0 : $x, $d);   // never "-0.00"
    }

    private static function h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // -------------------------------------------------------------------------
    // The two looks (HTML, inline styles only: the same markup is shown in the page and laid out by Dompdf)
    // -------------------------------------------------------------------------

    /**
     * The PDF look, as an HTML fragment: navy cover, two-row header, light-blue total row, shaded tree rows.
     * $limit caps the body rows (the preview); null = all of them (the PDF).
     */
    public function pdfHtml(array $m, ?int $limit = null): string
    {
        $P = self::PDF;
        $u = $m['unit'];
        $cell = static fn (string $extra): string => 'padding:3px 5px;border:1px solid '.self::PDF['line'].';font-size:7.5pt;'.$extra;
        $num = function (float $v, string $style) use ($u, $cell): string {
            return '<td style="'.$cell('text-align:right;white-space:nowrap;'.$style).'">'.self::h(self::fmt($v, $u)).'</td>';
        };
        $th = static fn (string $text, string $attrs = '', string $style = ''): string => '<th '.$attrs.' style="background:'.self::PDF['head'].';color:#ffffff;font-weight:bold;font-size:7.5pt;'
            .'text-align:center;padding:5px 4px;border:1px solid #ffffff;'.$style.'">'.self::h($text).'</th>';

        $g = $m['grand'];
        $totalStyle = 'background:'.$P['total'].';color:'.$P['totalText'].';font-weight:bold;';
        $body = '<tr><td style="'.$cell($totalStyle).'">'.self::h($m['grand_label']).'</td>'
            .$num($g['total'], $totalStyle).$num($g['fund_balance'], $totalStyle).$num($g['adjustment'], $totalStyle).$num($g['carry_over_obligation'], $totalStyle).'</tr>';

        $shown = 0;
        foreach ($m['rows'] as $r) {
            if ($limit !== null && $shown >= $limit) {
                break;
            }
            $shown++;
            $st = self::PDF_DEPTH[min($r['depth'], count(self::PDF_DEPTH) - 1)];
            $style = 'background:'.$st['fill'].';color:'.$st['color'].';font-weight:'.($st['bold'] ? 'bold' : 'normal').';';
            $body .= '<tr><td style="'.$cell($style.'padding-left:'.(5 + $st['indent']).'px;').'">'.self::h($st['marker'].$r['label']).'</td>'
                .$num($r['total'], $style).$num($r['fund_balance'], $style).$num($r['adjustment'], $style).$num($r['carry_over_obligation'], $style).'</tr>';
        }
        if ($limit !== null && $m['row_count'] > $shown) {
            $body .= '<tr><td colspan="5" style="'.$cell('text-align:center;color:#64748b;font-style:italic;').'">… '.($m['row_count'] - $shown)
                .' more row(s) — the file has all '.$m['row_count'].' rows.</td></tr>';
        }

        $meta = '<b style="color:#94a3b8;">Fiscal Year:</b> '.self::h($m['fiscal_year']).' &nbsp;|&nbsp; <b style="color:#94a3b8;">Amounts:</b> '.self::h($m['unit_label'])
            .' &nbsp;|&nbsp; <b style="color:#94a3b8;">Generated:</b> '.self::h($m['generated']);

        return '<div class="cobr-paper" style="font-family:\'DejaVu Sans\',Arial,sans-serif;color:#1a1b21;">'
            .'<table style="width:100%;border-collapse:collapse;margin-bottom:8px;"><tr><td style="background:'.$P['head'].';padding:14px 16px;">'
            .'<div style="font-size:8pt;font-weight:bold;color:'.$P['gold'].';letter-spacing:1px;">NATIONAL HOUSING AUTHORITY</div>'
            .'<div style="font-size:17pt;font-weight:bold;color:#ffffff;margin:4px 0;">'.self::h($m['title']).'</div>'
            .'<div style="font-size:9.5pt;color:#cbd5e1;margin-bottom:8px;">Corporate Operating Budget — '.self::h($m['include']).'</div>'
            .'<div style="font-size:8pt;color:#e2e8f0;">'.$meta.'</div></td></tr></table>'
            .($m['filters'] ? '<div style="font-size:7.5pt;color:#6b7280;font-style:italic;margin:0 0 8px;">Filters: '.self::h(implode('  |  ', $m['filters'])).'</div>' : '')
            .'<table style="width:100%;border-collapse:collapse;"><thead>'
            .'<tr>'.$th($m['first_column'], 'rowspan="2"', 'width:40%;').$th('Total', 'rowspan="2"').$th('Beginning Balance', 'colspan="3"').'</tr>'
            .'<tr>'.$th('Fund Balance').$th('Adjustment').$th('Carry Over Obligation').'</tr>'
            .'</thead><tbody>'.$body.'</tbody></table>'
            .'<div style="font-size:7pt;color:#94a3b8;text-align:center;margin-top:12px;">NHA Financial Information Management System (FIMS) | Generated: '.self::h($m['generated']).'</div>'
            .'</div>';
    }

    /** The Excel look, as an HTML fragment — plain title lines, navy header, blue total row, green first-level rows. */
    public function xlsxHtml(array $m, ?int $limit = null): string
    {
        $X = self::XL;
        $u = $m['unit'];
        $line = static fn (string $c): string => 'border:1px solid '.$c.';padding:3px 6px;font-size:8pt;font-family:Arial,sans-serif;';
        $th = static fn (string $text, string $attrs = '', string $style = ''): string => '<th '.$attrs.' style="background:#'.self::XL['head'].';color:#ffffff;font-weight:bold;font-size:8pt;'
            .'font-family:Arial,sans-serif;text-align:center;padding:5px 4px;border:1px solid #ffffff;'.$style.'">'.self::h($text).'</th>';
        $row = function (string $label, array $o, string $bg, string $color, bool $bold, string $border, int $indent) use ($u, $line): string {
            $css = $line($border).'background:'.$bg.';color:'.$color.';font-weight:'.($bold ? 'bold' : 'normal').';';
            $cells = '<td style="'.$css.'padding-left:'.(6 + $indent).'px;">'.self::h($label).'</td>';
            foreach (['total', 'fund_balance', 'adjustment', 'carry_over_obligation'] as $k) {
                $cells .= '<td style="'.$css.'text-align:right;white-space:nowrap;">'.self::h(self::fmt((float) $o[$k], $u)).'</td>';
            }

            return '<tr>'.$cells.'</tr>';
        };

        $body = $row($m['grand_label'], $m['grand'], '#'.$X['total'], '#000000', true, '#ffffff', 0);
        $indents = [10, 30, 50, 70, 90];   // Excel indent levels 1 / 3 / 5 / 7 / 9, at ~10px each
        $shown = 0;
        foreach ($m['rows'] as $r) {
            if ($limit !== null && $shown >= $limit) {
                break;
            }
            $shown++;
            $d = min($r['depth'], 4);
            if ($d === 0) {
                $body .= '<tr><td colspan="5" style="height:5px;padding:0;"></td></tr>';
            }
            [$bg, $color, $bold, $border] = match ($d) {
                0 => ['#'.$X['fund'], '#000000', true, '#ffffff'],
                1 => ['#'.$X['comp'], '#000000', false, '#ffffff'],
                2 => ['#'.$X['cat'], '#000000', false, '#ffffff'],
                3 => ['#ffffff', '#'.$X['projText'], false, '#'.$X['projLine']],
                default => ['#ffffff', '#'.$X['ftText'], false, '#'.$X['projLine']],
            };
            $body .= $row($r['label'], $r, $bg, $color, $bold, $border, $indents[$d]);
        }
        if ($limit !== null && $m['row_count'] > $shown) {
            $body .= '<tr><td colspan="5" style="'.$line('#ffffff').'text-align:center;color:#64748b;font-style:italic;">… '.($m['row_count'] - $shown)
                .' more row(s) — the file has all '.$m['row_count'].' rows.</td></tr>';
        }

        $sub = 'font-size:8.5pt;color:#595959;font-family:Arial,sans-serif;margin:2px 0;';

        return '<div class="cobr-paper" style="font-family:Arial,sans-serif;">'
            .'<div style="font-size:9pt;font-weight:bold;color:#'.$X['head'].';font-family:Arial,sans-serif;">NATIONAL HOUSING AUTHORITY</div>'
            .'<div style="font-size:15pt;font-weight:bold;color:#'.$X['head'].';font-family:Arial,sans-serif;margin:2px 0;">'.self::h($m['title']).'</div>'
            .'<div style="'.$sub.'">Corporate Operating Budget — '.self::h($m['include']).'</div>'
            .'<div style="'.$sub.'">Fiscal Year: '.self::h($m['fiscal_year']).'   |   Amounts: '.self::h($m['unit_label']).'   |   Generated: '.self::h($m['generated']).'</div>'
            .($m['filters'] ? '<div style="'.$sub.'">Filters: '.self::h(implode('   |   ', $m['filters'])).'</div>' : '')
            .'<table style="width:100%;border-collapse:collapse;margin-top:8px;"><thead>'
            .'<tr>'.$th($m['first_column'], 'rowspan="2"', 'width:40%;').$th('Total', 'rowspan="2"').$th('Beginning Balance', 'colspan="3"').'</tr>'
            .'<tr>'.$th('Fund Balance').$th('Adjustment').$th('Carry Over Obligation').'</tr>'
            .'</thead><tbody>'.$body.'</tbody></table></div>';
    }

    // -------------------------------------------------------------------------
    // Files
    // -------------------------------------------------------------------------

    /**
     * @return array{success: bool, filename?: string, mime?: string, data?: string, httpCode?: int, message?: string}
     */
    public function render(array $m, string $format): array
    {
        if (!in_array($format, ['pdf', 'xlsx'], true)) {
            return ['success' => false, 'httpCode' => 400, 'message' => 'Unsupported format: '.$format];
        }
        $stamp = date('Ymd');
        $name = $m['slug'].'_'.$stamp.'.'.$format;

        try {
            if ($format === 'pdf') {
                if ($m['row_count'] > self::PDF_MAX_ROWS) {
                    return ['success' => false, 'httpCode' => 413, 'message' => 'This report has '.$m['row_count'].' rows — too many for a PDF (the limit is '
                        .self::PDF_MAX_ROWS.'). Choose a shallower level or narrow the filters; Excel has no such limit.'];
                }
                $bytes = $this->pdf($m);

                return ['success' => true, 'filename' => $name, 'mime' => 'application/pdf', 'data' => base64_encode($bytes)];
            }

            return ['success' => true, 'filename' => $name, 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'data' => base64_encode($this->xlsx($m))];
        } catch (\Throwable $e) {
            App\LogError('CobReportService::render '.$format.' '.$e->getMessage());

            return ['success' => false, 'httpCode' => 500, 'message' => 'The '.strtoupper($format).' could not be generated.'];
        }
    }

    /** The PDF: the preview's own HTML, A4 landscape, with "Page X of Y" on every page. */
    public function pdf(array $m): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page { margin: 30pt 32pt 34pt 32pt; } body { margin: 0; font-family: "DejaVu Sans", sans-serif; }'
            .' thead { display: table-header-group; } tr { page-break-inside: avoid; }</style></head><body>'.$this->pdfHtml($m).'</body></html>';

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);   // a long report is slow to lay out; the default 30 s would cut it off
        }
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultMediaType', 'print');
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->getCanvas()->page_script(static function (int $page, int $pages, $canvas, $fontMetrics) {
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $text = 'Page '.$page.' of '.$pages;
            $canvas->text($canvas->get_width() - 32 - $fontMetrics->getTextWidth($text, $font, 7), $canvas->get_height() - 22, $text, $font, 7, [0.58, 0.64, 0.72]);
        });

        return $dompdf->output();
    }

    /** The workbook: a title block, the two-row header, the total row, then the rows (a thin gap before each first-level row). */
    public function xlsx(array $m): string
    {
        $X = self::XL;
        $u = $m['unit'];
        $x = new XlsxWriter($m['heading']);
        $ncol = 5;

        $font = static fn (array $o = []): array => array_merge(['name' => 'Arial', 'sz' => 10], $o);
        $sTitle = $x->style(['font' => $font(['bold' => true, 'color' => $X['head']])]);
        $sH1 = $x->style(['font' => $font(['sz' => 16, 'bold' => true, 'color' => $X['head']])]);
        $sSub = $x->style(['font' => $font(['sz' => 9, 'color' => '595959'])]);
        $sTh = $x->style(['font' => $font(['bold' => true, 'color' => 'FFFFFF']), 'fill' => $X['head'], 'border' => 'FFFFFF', 'align' => ['h' => 'center', 'v' => 'center', 'wrap' => true]]);

        // Cells keep the full peso value; each trailing comma in the format divides the SHOWN number by 1,000 (K = 1, M = 2, B = 3)
        $numFmt = (self::decimals($u) === 2 ? '#,##0.00' : '#,##0.000').str_repeat(',', self::UNITS[$u]['commas']);

        $level = [
            0 => ['font' => $font(['bold' => true]), 'fill' => $X['fund'], 'border' => 'FFFFFF', 'indent' => 1],
            1 => ['font' => $font(), 'fill' => $X['comp'], 'border' => 'FFFFFF', 'indent' => 3],
            2 => ['font' => $font(), 'fill' => $X['cat'], 'border' => 'FFFFFF', 'indent' => 5],
            3 => ['font' => $font(['color' => $X['projText']]), 'fill' => null, 'border' => $X['projLine'], 'indent' => 7],
            4 => ['font' => $font(['color' => $X['ftText']]), 'fill' => null, 'border' => $X['projLine'], 'indent' => 9],
        ];
        $rowStyles = [];
        foreach ($level as $d => $s) {
            $base = array_filter(['font' => $s['font'], 'fill' => $s['fill'], 'border' => $s['border']]);
            $rowStyles[$d] = [
                'label' => $x->style($base + ['align' => ['h' => 'left', 'v' => 'center', 'indent' => $s['indent'], 'wrap' => true]]),
                'num' => $x->style($base + ['align' => ['h' => 'right', 'v' => 'center'], 'numFmt' => $numFmt]),
            ];
        }
        $totalBase = ['font' => $font(['bold' => true]), 'fill' => $X['total'], 'border' => 'FFFFFF'];
        $totalLabel = $x->style($totalBase + ['align' => ['h' => 'left', 'v' => 'center']]);
        $totalNum = $x->style($totalBase + ['align' => ['h' => 'right', 'v' => 'center'], 'numFmt' => $numFmt]);

        $r = 0;
        $block = function (string $text, int $style, ?float $height = null) use ($x, &$r, $ncol): void {
            $x->set($r, 0, $text, $style);
            $x->merge($r, 0, $r, $ncol - 1);
            if ($height !== null) {
                $x->rowHeight($r, $height);
            }
            $r++;
        };
        $block('NATIONAL HOUSING AUTHORITY', $sTitle);
        $block($m['title'], $sH1, 24);
        $block('Corporate Operating Budget — '.$m['include'], $sSub);
        $block('Fiscal Year: '.$m['fiscal_year'].'   |   Amounts: '.$m['unit_label'].'   |   Generated: '.$m['generated'], $sSub);
        if ($m['filters']) {
            $block('Filters: '.implode('   |   ', $m['filters']), $sSub);
        }
        $x->rowHeight($r++, 8);   // breathing room above the table

        // Two-row header: label | Total | Beginning Balance (Fund Balance, Adjustment, Carry Over Obligation)
        $h0 = $r;
        $h1 = $r + 1;
        for ($c = 0; $c < $ncol; $c++) {
            $x->set($h0, $c, '', $sTh);
            $x->set($h1, $c, '', $sTh);
        }
        $x->set($h0, 0, $m['first_column'], $sTh);
        $x->merge($h0, 0, $h1, 0);
        $x->set($h0, 1, 'Total', $sTh);
        $x->merge($h0, 1, $h1, 1);
        $x->set($h0, 2, 'Beginning Balance', $sTh);
        $x->merge($h0, 2, $h0, 4);
        $x->set($h1, 2, 'Fund Balance', $sTh);
        $x->set($h1, 3, 'Adjustment', $sTh);
        $x->set($h1, 4, 'Carry Over Obligation', $sTh);
        $x->rowHeight($h0, 20);
        $x->rowHeight($h1, 32);
        $r = $h1 + 1;

        $put = function (string $label, array $o, int $labelStyle, int $numStyle) use ($x, &$r): void {
            $x->set($r, 0, $label, $labelStyle);
            foreach (['total', 'fund_balance', 'adjustment', 'carry_over_obligation'] as $i => $k) {
                $x->set($r, $i + 1, (float) $o[$k], $numStyle);
            }
        };
        $put($m['grand_label'], $m['grand'], $totalLabel, $totalNum);
        $x->rowHeight($r++, 20);

        foreach ($m['rows'] as $row) {
            if ($row['depth'] === 0) {
                $x->rowHeight($r++, 6);   // a thin gap between first-level groups, as in the template
            }
            $d = min($row['depth'], 4);
            $put($row['label'], $row, $rowStyles[$d]['label'], $rowStyles[$d]['num']);
            $x->rowHeight($r++, $d === 0 ? 20 : 16);
        }
        $x->rowHeight($r++, 8);
        $block('NHA Financial Information Management System (FIMS) | Generated: '.$m['generated'], $sSub);

        foreach ([0 => 56, 1 => 18, 2 => 18, 3 => 16, 4 => 22] as $c => $w) {
            $x->colWidth($c, $w);
        }

        return $x->render();
    }

    // -------------------------------------------------------------------------
    // One call for the route
    // -------------------------------------------------------------------------

    /**
     * 'json' -> the model plus both previews (capped); 'pdf' / 'xlsx' -> the file. A failure is { success: false, httpCode, message }.
     */
    public function report(string $layoutBy, int $depth, array $filters, string $scopeRcId, string $unit, string $format): array
    {
        if (!in_array($format, self::FORMATS, true)) {
            return ['success' => false, 'httpCode' => 400, 'message' => 'format must be one of: '.implode(', ', self::FORMATS)];
        }
        $built = $this->build($layoutBy, $depth, $filters, $scopeRcId, $unit);
        if (!$built['success']) {
            return $built;
        }
        $m = $built['data'];

        if ($format === 'json') {
            $m['preview_pdf'] = $this->pdfHtml($m, self::PREVIEW_MAX_ROWS);
            $m['preview_xlsx'] = $this->xlsxHtml($m, self::PREVIEW_MAX_ROWS);
            $m['rows'] = array_slice($m['rows'], 0, self::PREVIEW_MAX_ROWS);   // the page has no use for more; the file is built from the full model

            return ['success' => true, 'data' => $m];
        }

        return $this->render($m, $format);
    }
}
