/**
 * Subsidiary Ledger — DOCX generator.
 *
 *   node scripts/gen_sl_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model built by
 * SubsidiaryLedgerPrintService::model() — sheets, lines and totals are already resolved there; this script only
 * lays them out, the same as SubsidiaryLedgerPrintService::html().
 *
 * Letter portrait, 0.5in margins, Arial 9pt. One section (new page) per sheet in m.sheets (one SL account), each
 * one table whose header rows (repeated on every page) are: "<BOOK> - <GL TITLE> <YEAR>" | Fund, GL code;
 * SUBSIDIARY LEDGER | bank + account no. (or the SL code); the account name; the column headings
 * (DATE · PARTICULARS · FOLIO · JEV# · DEBITS · CREDITS · BALANCE) — then the year row, "JAN Beginning balance",
 * one row per posting, blank filler rows and "DEC Ending balance". No letterhead.
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, TableLayoutType, HeightRule,
} = docx;
const fs = require('fs');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_sl_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Page ──────────────────────────────────────────────────────────────────────
const MARGIN = 720;                        // 0.5in
const W = 12240 - MARGIN * 2;              // 10800 twips
const pct = (shares) => {
    const ws = shares.map(s => Math.round(W * s / 100));
    ws[ws.length - 1] += W - ws.reduce((a, b) => a + b, 0);
    return ws;
};
const CW = pct([7, 36, 7, 13, 12.5, 12.5, 12]);   // date | particulars | folio | jev# | debits | credits | balance
const span = (from, n) => CW.slice(from, from + n).reduce((a, b) => a + b, 0);

// ── Helpers ───────────────────────────────────────────────────────────────────
const FONT = 'Arial';
const SZ = 18, SZ_TITLE = 22;              // 9 / 11pt
const C = AlignmentType.CENTER, R = AlignmentType.RIGHT, L = AlignmentType.LEFT;
const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const LINE = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
const THICK = { style: BorderStyle.SINGLE, size: 10, color: '000000' };
const HAIR = { style: BorderStyle.SINGLE, size: 2, color: '999999' };
const NO_BORDERS = { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE };

const run = (text, o) => new TextRun({ text: String(text == null ? '' : text), font: FONT, size: (o && o.size) || SZ, bold: !!(o && o.bold) });
const p = (children, o) => new Paragraph({ children, alignment: (o && o.align) || L, spacing: { before: 0, after: (o && o.after) || 0 } });

function cell(children, width, o) {
    o = o || {};
    return new TableCell({
        children: children.length ? children : [p([])],
        width: { size: width, type: WidthType.DXA },
        columnSpan: o.span,
        borders: Object.assign({ top: NONE, bottom: NONE, left: NONE, right: NONE }, o.borders || {}),
        verticalAlign: o.valign || VerticalAlign.CENTER,
        margins: { top: o.tight ? 0 : 20, bottom: o.tight ? 0 : 20, left: 60, right: 60 },
    });
}
const row = (cells, o) => new TableRow({
    children: cells, cantSplit: true, tableHeader: !!(o && o.header),
    height: o && o.h ? { value: o.h, rule: HeightRule.ATLEAST } : undefined,
});
const box = { top: LINE, bottom: LINE, left: LINE, right: LINE };
const th = (t, width) => cell([p([run(t, { bold: true })], { align: C })], width, { borders: box });

// ── Body rows ─────────────────────────────────────────────────────────────────
const ALIGN = [C, L, C, C, R, R, R];
function line(vals, o) {
    o = o || {};
    return row(vals.map((v, i) => cell([p([run(v || '', { bold: o.bold })], { align: ALIGN[i] })], CW[i], {
        tight: true, valign: VerticalAlign.TOP,
        borders: { left: LINE, right: LINE, top: o.top || NONE, bottom: o.bottom || HAIR },
    })), { h: 230 });
}

// ── One sheet ─────────────────────────────────────────────────────────────────
function sheetTable(sh) {
    const head = [
        row([cell([p([run(m.title || '', { bold: true, size: SZ_TITLE })], { after: 80 })], span(0, 4), { span: 4 }), cell([], span(4, 3), { span: 3 })], { header: true }),
        row([cell([], span(0, 4), { span: 4 }), cell([p([run(m.fund || '')], { align: R })], CW[4]),
            cell([p([run(m.gl_code || '', { bold: true })], { align: R })], span(5, 2), { span: 2 })], { header: true }),
        row([cell([p([run('SUBSIDIARY LEDGER', { bold: true, size: SZ_TITLE })])], span(0, 4), { span: 4 }),
            cell([p([run(sh.id_line || ' ', { bold: true })], { align: R })], span(4, 3), { span: 3 })], { header: true }),
        row([cell([], span(0, 4), { span: 4 }), cell([p([run(sh.name || ' ')], { align: R, after: 100 })], span(4, 3), { span: 3 })], { header: true }),
        row([th('DATE', CW[0]), th('PARTICULARS', CW[1]), th('FOLIO', CW[2]), th('JEV#', CW[3]),
            th('DEBITS', CW[4]), th('CREDITS', CW[5]), th('BALANCE', CW[6])], { header: true }),
    ];
    const body = [line([m.year, '', '', '', '', '', ''], { bold: true }), line(['JAN', 'Beginning balance', '', '', '', '', sh.opening])];
    let count = 2;   // printed lines — a long Particulars wraps (~52 characters a line), as in the HTML
    (sh.rows || []).forEach(function (r) {
        body.push(line([r.mon, r.text, r.folio, r.jev, r.d, r.c, '']));
        count += Math.max(1, Math.ceil(String(r.text || '').length / 52));
    });
    for (; count < (m.min_rows || 0); count++) body.push(line(['', '', '', '', '', '', '']));
    const t = sh.totals || {};
    body.push(line(['DEC', 'Ending balance', '', '', t.d, t.c, t.bal], { bold: true, top: THICK, bottom: THICK }));

    return new Table({
        width: { size: W, type: WidthType.DXA }, columnWidths: CW, layout: TableLayoutType.FIXED,
        borders: NO_BORDERS, rows: head.concat(body),
    });
}

// ── Document ──────────────────────────────────────────────────────────────────
const sheets = (m.sheets && m.sheets.length) ? m.sheets : [{ id_line: '', name: '', opening: '0.00', rows: [], totals: {} }];
const doc = new Document({
    sections: sheets.map(sh => ({
        properties: {
            page: {
                size: { width: 12240, height: 15840 },   // letter
                margin: { top: MARGIN, right: MARGIN, bottom: MARGIN, left: MARGIN, header: 400, footer: 400 },
            },
        },
        children: [sheetTable(sh)],
    })),
});

Packer.toBuffer(doc).then(function (buf) {
    fs.writeFileSync(outPath, buf);
    console.log('OK:' + outPath);
}).catch(function (err) {
    process.stderr.write(err.message + '\n');
    process.exit(1);
});
