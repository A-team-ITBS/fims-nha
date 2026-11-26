<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * A small .xlsx writer: ONE worksheet, with the formatting a report needs — fonts, fills, thin borders, number formats, alignment (indent,
 * wrap), merged cells, column widths, row heights, landscape fit-to-width. Built on ZipArchive (no PhpSpreadsheet in this project; see also the
 * pure-PHP reader in CashPositionSoaService). Strings are written inline (no shared-strings part), numbers as numbers — a cell keeps its full
 * value, the number format only changes how it is shown.
 *
 *   $x = new XlsxWriter('Sources and Uses');
 *   $head = $x->style(['font' => ['bold' => true, 'color' => 'FFFFFF'], 'fill' => '1F497D', 'border' => 'FFFFFF', 'align' => ['h' => 'center']]);
 *   $x->set(0, 0, 'Total', $head);                  // row, column — both 0-based
 *   $x->set(1, 0, 1234567.5, $x->style(['numFmt' => '#,##0.00']));
 *   $x->merge(0, 0, 1, 0); $x->colWidth(0, 40); $x->rowHeight(0, 24);
 *   file_put_contents('r.xlsx', $x->render());
 *
 * Style definition keys (all optional): font {name, sz, bold, italic, u (underline), color 'RRGGBB'}, fill 'RRGGBB', border 'RRGGBB' (thin, all sides),
 * align {h: left|center|right, v: top|center|bottom, wrap: bool, indent: int}, numFmt 'format code'. style() returns a style id for set() and
 * de-duplicates identical definitions.
 */
class XlsxWriter
{
    private string $sheetName;
    /** @var array<int, array<int, array{v: mixed, s: int}>> */
    private array $cells = [];
    /** @var array<int, array{0: int, 1: int, 2: int, 3: int}> */
    private array $merges = [];
    /** @var array<int, float> */
    private array $colWidths = [];
    /** @var array<int, float> */
    private array $rowHeights = [];
    /** @var array<string, int> serialized definition => style id */
    private array $styleIds = [];
    /** @var array<int, array> */
    private array $styleDefs = [[]];   // style 0 = the default (Arial 10, no fill / border)
    private int $maxRow = 0;
    private int $maxCol = 0;

    public function __construct(string $sheetName = 'Sheet1')
    {
        $clean = trim(preg_replace('/[\[\]:*?\/\\\\]/', ' ', $sheetName) ?? '');
        $this->sheetName = mb_substr($clean !== '' ? $clean : 'Sheet1', 0, 31);
        $this->styleIds[serialize([])] = 0;
    }

    public function style(array $def): int
    {
        $key = serialize($def);
        if (!isset($this->styleIds[$key])) {
            $this->styleIds[$key] = count($this->styleDefs);
            $this->styleDefs[] = $def;
        }

        return $this->styleIds[$key];
    }

    /** A number is written as a number; anything else as text; null / '' as an empty (but styled) cell. */
    public function set(int $row, int $col, mixed $value, int $style = 0): void
    {
        $this->cells[$row][$col] = ['v' => $value, 's' => $style];
        $this->maxRow = max($this->maxRow, $row);
        $this->maxCol = max($this->maxCol, $col);
    }

    public function merge(int $row1, int $col1, int $row2, int $col2): void
    {
        $this->merges[] = [min($row1, $row2), min($col1, $col2), max($row1, $row2), max($col1, $col2)];
        $this->maxRow = max($this->maxRow, $row1, $row2);
        $this->maxCol = max($this->maxCol, $col1, $col2);
    }

    public function colWidth(int $col, float $chars): void
    {
        $this->colWidths[$col] = $chars;
        $this->maxCol = max($this->maxCol, $col);
    }

    public function rowHeight(int $row, float $points): void
    {
        $this->rowHeights[$row] = $points;
        $this->maxRow = max($this->maxRow, $row);
    }

    private int $freezeRow = 0;
    private string $autoFilter = '';
    private bool $gridLines = false;

    /** Freeze the rows above $row (0-based) — e.g. freeze(4) keeps rows 1–4 in view (Excel's "freeze at A5"). */
    public function freeze(int $row): void
    {
        $this->freezeRow = max(0, $row);
    }

    /** An auto-filter on a header row, columns $col1 … $col2 (0-based). */
    public function autoFilter(int $row, int $col1, int $col2): void
    {
        $this->autoFilter = self::ref($row, $col1).':'.self::ref($row, $col2);
    }

    /** Show Excel's grid lines (hidden by default). */
    public function gridLines(bool $show = true): void
    {
        $this->gridLines = $show;
    }

    /** "A", "B", … "Z", "AA" for a 0-based column index. */
    public static function colName(int $col): string
    {
        $name = '';
        for ($n = $col + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26).$name;
        }

        return $name;
    }

    public static function ref(int $row, int $col): string
    {
        return self::colName($col).($row + 1);
    }

    /** Text safe inside XML: control characters Excel rejects are dropped, markup characters escaped. */
    private static function xml(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function rgb(?string $c): string
    {
        $c = strtoupper(ltrim((string) $c, '#'));

        return 'FF'.(preg_match('/^[0-9A-F]{6}$/', $c) ? $c : '000000');
    }

    /** @return string the .xlsx file's bytes */
    public function render(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        if ($tmp === false || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create the .xlsx file.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="'.self::xml($this->sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>');
            $zip->addFromString('xl/styles.xml', $this->stylesXml());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());
            $zip->close();

            $bytes = file_get_contents($tmp);
            if ($bytes === false || $bytes === '') {
                throw new \RuntimeException('Cannot read back the .xlsx file.');
            }

            return $bytes;
        } finally {
            @unlink($tmp);
        }
    }

    private function stylesXml(): string
    {
        $fonts = ['<font><sz val="10"/><color rgb="FF000000"/><name val="Arial"/></font>'];
        $fills = ['<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>'];
        $borders = ['<border><left/><right/><top/><bottom/><diagonal/></border>'];
        $numFmts = [];                     // format code => id (custom ids start at 164)
        $xfs = ['<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'];
        $fontIds = [$fonts[0] => 0];
        $fillIds = [];
        $borderIds = [];

        foreach (array_slice($this->styleDefs, 1, null, true) as $def) {
            $f = $def['font'] ?? [];
            $font = '<font>'.(!empty($f['bold']) ? '<b/>' : '').(!empty($f['italic']) ? '<i/>' : '').(!empty($f['u']) ? '<u/>' : '')
                .'<sz val="'.(float) ($f['sz'] ?? 10).'"/><color rgb="'.self::rgb($f['color'] ?? '000000').'"/>'
                .'<name val="'.self::xml((string) ($f['name'] ?? 'Arial')).'"/></font>';
            $fontIds[$font] ??= array_push($fonts, $font) - 1;

            $fillId = 0;
            if (!empty($def['fill'])) {
                $fill = '<fill><patternFill patternType="solid"><fgColor rgb="'.self::rgb($def['fill']).'"/><bgColor indexed="64"/></patternFill></fill>';
                $fillIds[$fill] ??= array_push($fills, $fill) - 1;
                $fillId = $fillIds[$fill];
            }

            $borderId = 0;
            if (!empty($def['border'])) {
                $side = '<color rgb="'.self::rgb($def['border']).'"/>';
                $border = '<border><left style="thin">'.$side.'</left><right style="thin">'.$side.'</right><top style="thin">'.$side.'</top>'
                    .'<bottom style="thin">'.$side.'</bottom><diagonal/></border>';
                $borderIds[$border] ??= array_push($borders, $border) - 1;
                $borderId = $borderIds[$border];
            }

            $numFmtId = 0;
            if (!empty($def['numFmt'])) {
                $numFmts[$def['numFmt']] ??= 164 + count($numFmts);
                $numFmtId = $numFmts[$def['numFmt']];
            }

            $a = $def['align'] ?? [];
            $align = '';
            if ($a) {
                $align = '<alignment'.(!empty($a['h']) ? ' horizontal="'.self::xml((string) $a['h']).'"' : '')
                    .(!empty($a['v']) ? ' vertical="'.self::xml((string) $a['v']).'"' : '')
                    .(!empty($a['wrap']) ? ' wrapText="1"' : '')
                    .(!empty($a['indent']) ? ' indent="'.(int) $a['indent'].'"' : '').'/>';
            }

            $xfs[] = '<xf numFmtId="'.$numFmtId.'" fontId="'.$fontIds[$font].'" fillId="'.$fillId.'" borderId="'.$borderId.'" xfId="0"'
                .($numFmtId ? ' applyNumberFormat="1"' : '').' applyFont="1"'.($fillId ? ' applyFill="1"' : '').($borderId ? ' applyBorder="1"' : '')
                .($align !== '' ? ' applyAlignment="1">'.$align.'</xf>' : '/>');
        }

        $fmtXml = '';
        foreach ($numFmts as $code => $id) {
            $fmtXml .= '<numFmt numFmtId="'.$id.'" formatCode="'.self::xml($code).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .($fmtXml !== '' ? '<numFmts count="'.count($numFmts).'">'.$fmtXml.'</numFmts>' : '')
            .'<fonts count="'.count($fonts).'">'.implode('', $fonts).'</fonts>'
            .'<fills count="'.count($fills).'">'.implode('', $fills).'</fills>'
            .'<borders count="'.count($borders).'">'.implode('', $borders).'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($xfs).'">'.implode('', $xfs).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function sheetXml(): string
    {
        $rows = array_unique(array_merge(array_keys($this->cells), array_keys($this->rowHeights)));
        sort($rows);

        $data = '';
        foreach ($rows as $r) {
            $cells = $this->cells[$r] ?? [];
            ksort($cells);
            $attrs = isset($this->rowHeights[$r]) ? ' ht="'.(float) $this->rowHeights[$r].'" customHeight="1"' : '';
            $data .= '<row r="'.($r + 1).'"'.$attrs.'>';
            foreach ($cells as $c => $cell) {
                $ref = self::ref($r, $c);
                $s = $cell['s'] ? ' s="'.$cell['s'].'"' : '';
                $v = $cell['v'];
                if ($v === null || $v === '') {
                    $data .= '<c r="'.$ref.'"'.$s.'/>';
                } elseif (is_int($v) || is_float($v)) {
                    $data .= '<c r="'.$ref.'"'.$s.'><v>'.(is_float($v) ? rtrim(rtrim(sprintf('%.10F', $v), '0'), '.') : $v).'</v></c>';
                } else {
                    $data .= '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::xml((string) $v).'</t></is></c>';
                }
            }
            $data .= '</row>';
        }

        $cols = '';
        ksort($this->colWidths);
        foreach ($this->colWidths as $c => $w) {
            $cols .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.(float) $w.'" customWidth="1"/>';
        }

        $merge = '';
        foreach ($this->merges as [$r1, $c1, $r2, $c2]) {
            $merge .= '<mergeCell ref="'.self::ref($r1, $c1).':'.self::ref($r2, $c2).'"/>';
        }

        $pane = $this->freezeRow > 0
            ? '<pane ySplit="'.$this->freezeRow.'" topLeftCell="'.self::ref($this->freezeRow, 0).'" activePane="bottomLeft" state="frozen"/>'
              .'<selection pane="bottomLeft" activeCell="'.self::ref($this->freezeRow, 0).'" sqref="'.self::ref($this->freezeRow, 0).'"/>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:'.self::ref($this->maxRow, $this->maxCol).'"/>'
            .'<sheetViews><sheetView workbookViewId="0"'.($this->gridLines ? '' : ' showGridLines="0"').'>'.$pane.'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$data.'</sheetData>'
            .($this->autoFilter ? '<autoFilter ref="'.$this->autoFilter.'"/>' : '')
            .($merge !== '' ? '<mergeCells count="'.count($this->merges).'">'.$merge.'</mergeCells>' : '')
            .'<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }
}
