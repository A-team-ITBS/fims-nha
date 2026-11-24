<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * The COB "Generate Report" — a PDF or Excel file of one of the COB's tree tabs (Per Region / Per Type of Fund / Per Component), as deep as the user
 * chooses, for the filters the page is showing. Built on the SAME tree the page shows (CobService::rollup(), so only Approved requests, and only what
 * the caller's RC may see), flattened to rows.
 *
 *   build()    the report model: title, meta (fiscal year, filters, unit, date), the rows, the grand total, and the two preview looks
 *   render()   the file: 'pdf' (Dompdf, landscape A4, "Page X of Y") or 'xlsx' (XlsxWriter) -> { filename, mime, data: base64 }
 *
 * The PDF is rendered from the very HTML the on-screen preview shows (pdfHtml()), so the two cannot drift; the Excel preview is an HTML imitation
 * of the sheet, from the same rows and palette. Amounts follow the page's Amount Unit; Excel cells keep the full peso value and only the number
 * format scales them (a trailing comma divides the shown number by 1,000).
 *
 * The layout is the COB Monitoring sheet's: Code | Name | Approved Amount | Amendment / Realignment | Revised Amount | CBA Requested |
 * Available for Earmarking | RFA Approved | LAA Issued | Remaining Balance — see CobService for what each column is. A zero prints as "-", a negative in
 * brackets. There is no colour coding of rows or columns: the hierarchy shows as indentation, and every row above a project is bold.
 */
class CobReportService
{
    /** The deepest level index any report can go to (0 = the first level only). */
    public const MAX_DEPTH = 4;
    public const FORMATS = ['json', 'pdf', 'xlsx'];
    public const UNITS = [
        'full' => ['label' => 'Full Amount', 'divisor' => 1, 'commas' => 0],
        'thousand' => ['label' => 'Thousands (K)', 'divisor' => 1e3, 'commas' => 1],
        'million' => ['label' => 'Millions (M)', 'divisor' => 1e6, 'commas' => 2],
        'billion' => ['label' => 'Billions (B)', 'divisor' => 1e9, 'commas' => 3],
    ];
    /** The amount columns, in sheet order (the keys are CobService::AMOUNT_KEYS). */
    public const COLUMNS = [
        'approved_amount' => 'Approved Amount',
        'revision_amount' => 'Amendment / Realignment',
        'revised_amount' => 'Revised Amount',
        'cba_requested' => 'CBA Requested',
        'available_amount' => 'Available for Earmarking',
        'rfa_approved' => 'RFA Approved',
        'laa_issued' => 'LAA Issued',
        'remaining_balance' => 'Remaining Balance',
    ];
    /**
     * A PDF past this many rows is refused; Excel has no such limit. Dompdf lays the whole table out in memory — about 45 KB per cell, and it slows down
     * faster than the row count grows. Measured here with the ten columns (Xdebug off): 250 rows ~2 s / 114 MB, 400 rows ~6 s / 184 MB, 600 rows ~11 s / 312 MB.
     * pdf() raises the memory limit to the app's own PDF figure (512 MB, as src/ExportPdf.php does), so 600 rows fit with room to spare; much more would not.
     */
    public const PDF_MAX_ROWS = 600;
    /** What pdf() raises memory_limit to when it is lower — the same figure the app's own PDF export uses (Config PDF_MEMORY_LIMIT). */
    private const PDF_MEMORY_BYTES = 512 * 1024 * 1024;
    /** The on-screen preview shows this many rows at most (the file always has them all). */
    public const PREVIEW_MAX_ROWS = 400;

    /**
     * What each tab reports. 'types' is the level order of the tab's tree — a report at depth N includes every row whose type is among the first N + 1
     * (a program with no Sub-Program simply has no row of that type), so "down to Sub-Program" means the same for every program.
     */
    private const LAYOUTS = [
        'region' => [
            'view' => 'Per Region', 'sheet' => 'COB Per Region', 'slug' => 'COB_Per_Region',
            'types' => ['region', 'program', 'subprogram', 'project'],
            'levels' => [
                ['Region', 'Region'],
                ['Region / Program', 'Region_Program'],
                ['Region / Program / Sub-Program', 'Region_Program_SubProgram'],
                ['Region / Program / Sub-Program / Project', 'Region_Program_SubProgram_Project'],
            ],
        ],
        'fund_source' => [
            'view' => 'Per Type of Fund', 'sheet' => 'COB Per Type of Fund', 'slug' => 'COB_Per_Type_of_Fund',
            'types' => ['fund', 'component', 'program', 'subprogram', 'project'],
            'levels' => [
                ['Type of Fund', 'Type_of_Fund'],
                ['Type of Fund / Component', 'Fund_Component'],
                ['Type of Fund / Component / Program', 'Fund_Component_Program'],
                ['Type of Fund / Component / Program / Sub-Program', 'Fund_Component_Program_SubProgram'],
                ['Type of Fund / Component / Program / Sub-Program / Project', 'Fund_Component_Program_SubProgram_Project'],
            ],
        ],
        'component' => [
            'view' => 'Per Component', 'sheet' => 'COB Per Component', 'slug' => 'COB_Per_Component',
            'types' => ['component', 'fund', 'program', 'subprogram', 'project'],
            'levels' => [
                ['Component', 'Component'],
                ['Component / Type of Fund', 'Component_Fund'],
                ['Component / Type of Fund / Program', 'Component_Fund_Program'],
                ['Component / Type of Fund / Program / Sub-Program', 'Component_Fund_Program_SubProgram'],
                ['Component / Type of Fund / Program / Sub-Program / Project', 'Component_Fund_Program_SubProgram_Project'],
            ],
        ],
    ];

    /** Header fill (RGB, no '#'). There is no other colour coding: rows are told apart by indentation and bold, not by tint. */
    private const HEAD = '0F2045';
    private const PDF = ['total' => '#e5e7eb', 'line' => '#cbd5e1', 'totalText' => '#0f172a', 'gold' => '#f59e0b'];
    /** Excel palette (RGB, no '#'): navy header, grey total row, light-grey cell lines. */
    private const XL = ['head' => '1F497D', 'total' => 'D9D9D9', 'line' => 'D9D9D9'];

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
        $layout = self::LAYOUTS[$layoutBy];
        $depth = max(0, min(count($layout['levels']) - 1, $depth));   // a tab with fewer levels than MAX_DEPTH + 1 stops at its own last one
        $unit = isset(self::UNITS[$unit]) ? $unit : 'full';

        $tree = $this->cob->rollup($layoutBy, $filters, $scopeRcId);
        if (!$tree['success']) {
            return ['success' => false, 'httpCode' => 500, 'message' => 'Failed to load the report data.'];
        }

        $f = $this->cob->normalizeFilters($filters);
        $rows = [];
        $this->flatten($tree['data'], array_slice($layout['types'], 0, $depth + 1), $f['fiscal_yr'] === null, $rows);

        return ['success' => true, 'data' => [
            'layout_by' => $layoutBy,
            'depth' => $depth,
            'title' => 'CORPORATE OPERATING BUDGET MONITORING',
            'view' => $layout['view'],
            'heading' => $layout['sheet'],
            'grand_label' => 'TOTAL',
            'include' => $layout['levels'][$depth][0],
            'slug' => $layout['slug'].'_'.$layout['levels'][$depth][1],
            'unit' => $unit,
            'unit_label' => self::UNITS[$unit]['label'],
            'fiscal_year' => $f['fiscal_yr'] !== null ? 'FY '.$f['fiscal_yr'] : 'All fiscal years',
            'filters' => $this->describeFilters($f),
            'generated' => date('F j, Y'),
            'row_count' => count($rows),
            'rows' => $rows,
            'grand' => array_intersect_key($tree['totals'], self::COLUMNS),
        ]];
    }

    /**
     * Tree -> flat, ordered rows { depth, type, code, label, ...the eight amounts }. A node is kept when its type is one of $types, the levels the report
     * goes down to; $depth is how far down the tree the row really sits (a project directly under a program is one step in, not two).
     */
    private function flatten(array $nodes, array $types, bool $showYear, array &$rows, int $depth = 0): void
    {
        foreach ($nodes as $n) {
            if (!in_array($n['type'], $types, true)) {
                continue;   // a deeper level than the report asks for — and so is everything below it
            }
            $row = [
                'depth' => $depth,
                'type' => $n['type'],
                'code' => (string) ($n['code'] ?? ''),
                // a project is budgeted once per request: with every fiscal year in the report the year tells the copies apart
                'label' => (string) $n['label'].($n['type'] === 'project' && $showYear && !empty($n['fiscal_yr']) ? ' · FY '.$n['fiscal_yr'] : ''),
            ];
            foreach (array_keys(self::COLUMNS) as $k) {
                $row[$k] = (float) ($n[$k] ?? 0);
            }
            $rows[] = $row;
            if (!empty($n['children'])) {
                $this->flatten($n['children'], $types, $showYear, $rows, $depth + 1);
            }
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

    /** As on the sheet: a dash for zero (also for anything that rounds to zero), a negative in brackets. */
    private static function fmt(float $v, string $unit): string
    {
        $d = self::decimals($unit);
        $x = round($v / self::UNITS[$unit]['divisor'], $d);
        if (abs($x) < 0.5 * 10 ** -$d) {
            return '-';
        }

        return $x < 0 ? '('.number_format(-$x, $d).')' : number_format($x, $d);
    }

    private static function h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Every row above a project (region, fund, component, program, sub-program) is bold; a project is plain. */
    private static function isBold(string $type): bool
    {
        return $type !== 'project';
    }

    // -------------------------------------------------------------------------
    // The two looks (HTML, inline styles only: the same markup is shown in the page and laid out by Dompdf)
    // -------------------------------------------------------------------------

    /**
     * The PDF look, as an HTML fragment: navy cover, one header row, a grey total row, plain rows (bold above a project, indented by level).
     * $limit caps the body rows (the preview); null = all of them (the PDF).
     */
    public function pdfHtml(array $m, ?int $limit = null): string
    {
        $P = self::PDF;
        $u = $m['unit'];
        $cell = static fn (string $extra): string => 'padding:3px 4px;border:1px solid '.self::PDF['line'].';font-size:6.8pt;'.$extra;
        $num = function (float $v, string $style) use ($u, $cell): string {
            return '<td style="'.$cell('text-align:right;white-space:nowrap;'.$style).'">'.self::h(self::fmt($v, $u)).'</td>';
        };
        $th = static fn (string $text, string $style = ''): string => '<th style="background:#'.self::HEAD.';color:#ffffff;font-weight:bold;font-size:6.8pt;'
            .'text-align:center;padding:5px 3px;border:1px solid #ffffff;'.$style.'">'.self::h($text).'</th>';

        $head = $th('Code', 'width:50pt;').$th('Name');
        foreach (self::COLUMNS as $label) {
            $head .= $th($label);
        }

        $totalStyle = 'background:'.$P['total'].';color:'.$P['totalText'].';font-weight:bold;';
        $body = '<tr><td colspan="2" style="'.$cell($totalStyle).'">'.self::h($m['grand_label']).'</td>';
        foreach (array_keys(self::COLUMNS) as $k) {
            $body .= $num($m['grand'][$k], $totalStyle);
        }
        $body .= '</tr>';

        $shown = 0;
        foreach ($m['rows'] as $r) {
            if ($limit !== null && $shown >= $limit) {
                break;
            }
            $shown++;
            $style = 'color:#1a1b21;font-weight:'.(self::isBold($r['type']) ? 'bold' : 'normal').';';
            $body .= '<tr><td style="'.$cell($style.'white-space:nowrap;').'">'.self::h($r['code']).'</td>'
                .'<td style="'.$cell($style.'padding-left:'.(4 + 9 * $r['depth']).'px;').'">'.self::h($r['label']).'</td>';
            foreach (array_keys(self::COLUMNS) as $k) {
                $body .= $num($r[$k], $style);
            }
            $body .= '</tr>';
        }
        if ($limit !== null && $m['row_count'] > $shown) {
            $body .= '<tr><td colspan="10" style="'.$cell('text-align:center;color:#64748b;font-style:italic;').'">… '.($m['row_count'] - $shown)
                .' more row(s) — the file has all '.$m['row_count'].' rows.</td></tr>';
        }

        $meta = '<b style="color:#94a3b8;">Fiscal Year:</b> '.self::h($m['fiscal_year']).' &nbsp;|&nbsp; <b style="color:#94a3b8;">Amounts:</b> '.self::h($m['unit_label'])
            .' &nbsp;|&nbsp; <b style="color:#94a3b8;">Generated:</b> '.self::h($m['generated']);

        return '<div class="cobr-paper" style="font-family:\'DejaVu Sans\',Arial,sans-serif;color:#1a1b21;">'
            .'<table style="width:100%;border-collapse:collapse;margin-bottom:8px;"><tr><td style="background:#'.self::HEAD.';padding:14px 16px;">'
            .'<div style="font-size:8pt;font-weight:bold;color:'.$P['gold'].';letter-spacing:1px;">NATIONAL HOUSING AUTHORITY</div>'
            .'<div style="font-size:16pt;font-weight:bold;color:#ffffff;margin:4px 0;">'.self::h($m['title']).'</div>'
            .'<div style="font-size:9.5pt;color:#cbd5e1;margin-bottom:8px;">'.self::h($m['view']).' — '.self::h($m['include']).'</div>'
            .'<div style="font-size:8pt;color:#e2e8f0;">'.$meta.'</div></td></tr></table>'
            .($m['filters'] ? '<div style="font-size:7.5pt;color:#6b7280;font-style:italic;margin:0 0 8px;">Filters: '.self::h(implode('  |  ', $m['filters'])).'</div>' : '')
            .'<table style="width:100%;border-collapse:collapse;"><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table>'
            .'<div style="font-size:7pt;color:#94a3b8;text-align:center;margin-top:12px;">NHA Financial Information Management System (FIMS) | Generated: '.self::h($m['generated']).'</div>'
            .'</div>';
    }

    /** The Excel look, as an HTML fragment — plain title lines, a navy header, a grey total row, plain rows. */
    public function xlsxHtml(array $m, ?int $limit = null): string
    {
        $X = self::XL;
        $u = $m['unit'];
        $line = static fn (string $c): string => 'border:1px solid '.$c.';padding:3px 6px;font-size:8pt;font-family:Arial,sans-serif;';
        $th = static fn (string $text): string => '<th style="background:#'.self::XL['head'].';color:#ffffff;font-weight:bold;font-size:8pt;'
            .'font-family:Arial,sans-serif;text-align:center;padding:5px 4px;border:1px solid #ffffff;">'.self::h($text).'</th>';

        $head = $th('Code').$th('Name');
        foreach (self::COLUMNS as $label) {
            $head .= $th($label);
        }

        $totalCss = $line('#'.$X['line']).'background:#'.$X['total'].';color:#000000;font-weight:bold;';
        $body = '<tr><td colspan="2" style="'.$totalCss.'">'.self::h($m['grand_label']).'</td>';
        foreach (array_keys(self::COLUMNS) as $k) {
            $body .= '<td style="'.$totalCss.'text-align:right;white-space:nowrap;">'.self::h(self::fmt((float) $m['grand'][$k], $u)).'</td>';
        }
        $body .= '</tr>';

        $shown = 0;
        foreach ($m['rows'] as $r) {
            if ($limit !== null && $shown >= $limit) {
                break;
            }
            $shown++;
            if ($r['depth'] === 0) {
                $body .= '<tr><td colspan="10" style="height:5px;padding:0;"></td></tr>';
            }
            $css = $line('#'.$X['line']).'color:#000000;font-weight:'.(self::isBold($r['type']) ? 'bold' : 'normal').';';
            $body .= '<tr><td style="'.$css.'white-space:nowrap;">'.self::h($r['code']).'</td>'
                .'<td style="'.$css.'padding-left:'.(6 + 10 * $r['depth']).'px;">'.self::h($r['label']).'</td>';
            foreach (array_keys(self::COLUMNS) as $k) {
                $body .= '<td style="'.$css.'text-align:right;white-space:nowrap;">'.self::h(self::fmt($r[$k], $u)).'</td>';
            }
            $body .= '</tr>';
        }
        if ($limit !== null && $m['row_count'] > $shown) {
            $body .= '<tr><td colspan="10" style="'.$line('#'.$X['line']).'text-align:center;color:#64748b;font-style:italic;">… '.($m['row_count'] - $shown)
                .' more row(s) — the file has all '.$m['row_count'].' rows.</td></tr>';
        }

        $sub = 'font-size:8.5pt;color:#595959;font-family:Arial,sans-serif;margin:2px 0;';

        return '<div class="cobr-paper" style="font-family:Arial,sans-serif;">'
            .'<div style="font-size:9pt;font-weight:bold;color:#'.$X['head'].';font-family:Arial,sans-serif;">NATIONAL HOUSING AUTHORITY</div>'
            .'<div style="font-size:15pt;font-weight:bold;color:#'.$X['head'].';font-family:Arial,sans-serif;margin:2px 0;">'.self::h($m['title']).'</div>'
            .'<div style="'.$sub.'">'.self::h($m['view']).' — '.self::h($m['include']).'</div>'
            .'<div style="'.$sub.'">Fiscal Year: '.self::h($m['fiscal_year']).'   |   Amounts: '.self::h($m['unit_label']).'   |   Generated: '.self::h($m['generated']).'</div>'
            .($m['filters'] ? '<div style="'.$sub.'">Filters: '.self::h(implode('   |   ', $m['filters'])).'</div>' : '')
            .'<table style="width:100%;border-collapse:collapse;margin-top:8px;"><thead><tr>'.$head.'</tr></thead><tbody>'.$body.'</tbody></table></div>';
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
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page { margin: 28pt 24pt 32pt 24pt; } body { margin: 0; font-family: "DejaVu Sans", sans-serif; }'
            .' thead { display: table-header-group; } tr { page-break-inside: avoid; }</style></head><body>'.$this->pdfHtml($m).'</body></html>';

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);   // a long report is slow to lay out; the default 30 s would cut it off
        }
        self::raiseMemoryLimit(self::PDF_MEMORY_BYTES);
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
            $canvas->text($canvas->get_width() - 24 - $fontMetrics->getTextWidth($text, $font, 7), $canvas->get_height() - 22, $text, $font, 7, [0.58, 0.64, 0.72]);
        });

        return $dompdf->output();
    }

    /** Raises memory_limit to $bytes when it is lower (never lowers it, never touches "no limit"). */
    private static function raiseMemoryLimit(int $bytes): void
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '' || $limit === '-1') {
            return;
        }
        $current = (int) $limit * (['g' => 1 << 30, 'm' => 1 << 20, 'k' => 1 << 10][strtolower(substr($limit, -1))] ?? 1);
        if ($current < $bytes) {
            @ini_set('memory_limit', (string) $bytes);
        }
    }

    /** The workbook: a title block, the header row, the total row, then the rows (a thin gap before each first-level row). */
    public function xlsx(array $m): string
    {
        $X = self::XL;
        $u = $m['unit'];
        $x = new XlsxWriter($m['heading']);
        $ncol = 2 + count(self::COLUMNS);

        $font = static fn (array $o = []): array => array_merge(['name' => 'Arial', 'sz' => 10], $o);
        $sTitle = $x->style(['font' => $font(['bold' => true, 'color' => $X['head']])]);
        $sH1 = $x->style(['font' => $font(['sz' => 16, 'bold' => true, 'color' => $X['head']])]);
        $sSub = $x->style(['font' => $font(['sz' => 9, 'color' => '595959'])]);
        $thStyle = $x->style(['font' => $font(['bold' => true, 'color' => 'FFFFFF']), 'fill' => $X['head'], 'border' => 'FFFFFF',
            'align' => ['h' => 'center', 'v' => 'center', 'wrap' => true]]);

        // Cells keep the full peso value; each trailing comma in the format divides the SHOWN number by 1,000 (K = 1, M = 2, B = 3).
        // Three sections — positive; negative in brackets; zero as a dash — each scaled the same way.
        $scaled = (self::decimals($u) === 2 ? '#,##0.00' : '#,##0.000').str_repeat(',', self::UNITS[$u]['commas']);
        $numFmt = $scaled.';('.$scaled.');"-"';

        // one pair of styles per (type, depth-class) combination, made on first use
        $styles = [];
        $styleFor = function (string $type, int $depth, string $kind) use ($x, $font, $X, $numFmt, &$styles): int {
            $key = $type.'|'.$depth.'|'.$kind;
            if (!isset($styles[$key])) {
                $base = ['font' => $font(['bold' => self::isBold($type)]), 'border' => $X['line']];
                $styles[$key] = $kind === 'label'
                    ? $x->style($base + ['align' => ['h' => 'left', 'v' => 'center', 'indent' => 1 + $depth, 'wrap' => true]])
                    : ($kind === 'code'
                        ? $x->style($base + ['align' => ['h' => 'left', 'v' => 'center']])
                        : $x->style($base + ['align' => ['h' => 'right', 'v' => 'center'], 'numFmt' => $numFmt]));
            }

            return $styles[$key];
        };
        $totalBase = ['font' => $font(['bold' => true]), 'fill' => $X['total'], 'border' => $X['line']];
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
        $block($m['view'].' — '.$m['include'], $sSub);
        $block('Fiscal Year: '.$m['fiscal_year'].'   |   Amounts: '.$m['unit_label'].'   |   Generated: '.$m['generated'], $sSub);
        if ($m['filters']) {
            $block('Filters: '.implode('   |   ', $m['filters']), $sSub);
        }
        $x->rowHeight($r++, 8);   // breathing room above the table

        // Header: Code | Name | the eight columns
        $x->set($r, 0, 'Code', $thStyle);
        $x->set($r, 1, 'Name', $thStyle);
        $c = 2;
        foreach (self::COLUMNS as $label) {
            $x->set($r, $c++, $label, $thStyle);
        }
        $x->rowHeight($r++, 32);

        $x->set($r, 0, $m['grand_label'], $totalLabel);
        $x->set($r, 1, '', $totalLabel);
        $x->merge($r, 0, $r, 1);
        $c = 2;
        foreach (array_keys(self::COLUMNS) as $k) {
            $x->set($r, $c++, (float) $m['grand'][$k], $totalNum);
        }
        $x->rowHeight($r++, 20);

        foreach ($m['rows'] as $row) {
            if ($row['depth'] === 0) {
                $x->rowHeight($r++, 6);   // a thin gap between first-level groups, as in the template
            }
            $x->set($r, 0, $row['code'], $styleFor($row['type'], $row['depth'], 'code'));
            $x->set($r, 1, $row['label'], $styleFor($row['type'], $row['depth'], 'label'));
            $c = 2;
            foreach (array_keys(self::COLUMNS) as $k) {
                $x->set($r, $c++, (float) $row[$k], $styleFor($row['type'], $row['depth'], $k));
            }
            $x->rowHeight($r++, $row['depth'] === 0 ? 20 : 16);
        }
        $x->rowHeight($r++, 8);
        $block('NHA Financial Information Management System (FIMS) | Generated: '.$m['generated'], $sSub);

        $x->colWidth(0, 16);
        $x->colWidth(1, 52);
        for ($c = 2; $c < $ncol; $c++) {
            $x->colWidth($c, 19);
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
