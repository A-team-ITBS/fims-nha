/**
 * Subsidiary Ledger — DOCX generator.
 *
 *   node scripts/gen_sl_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model built by
 * SubsidiaryLedgerPrintService::model() — lines, running balances and totals are already resolved there;
 * this script only lays them out, the same as SubsidiaryLedgerPrintService::html().
 *
 * Letter portrait, 0.5in margins, Times New Roman 10pt. One table whose header rows (repeated on every page)
 * are the SUBSIDIARY LEDGER title, Entity Name / Fund, the SL Account Name box beside GL Account Name /
 * Account Code / Bank Account No., and the column headings (Date · Particulars · Ref · Amount: Debit · Credit ·
 * Balance); then one row per posting, blank filler rows and the Totals row. No letterhead.
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
const pct = (shares, total) => {
    const ws = shares.map(s => Math.round(total * s / 100));
    ws[ws.length - 1] += total - ws.reduce((a, b) => a + b, 0);
    return ws;
};
const CW = pct([11, 30, 13, 15.3, 15.3, 15.4], W);   // date | particulars | ref | debit | credit | balance
const span = (from, n) => CW.slice(from, from + n).reduce((a, b) => a + b, 0);

// ── Helpers ───────────────────────────────────────────────────────────────────
const FONT = 'Times New Roman';
const SZ = 20, SZ_TITLE = 26;              // 10 / 13pt
const C = AlignmentType.CENTER, R = AlignmentType.RIGHT, L = AlignmentType.LEFT;
const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const LINE = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
const BOLD = { style: BorderStyle.SINGLE, size: 10, color: '000000' };
const NO_BORDERS = { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE };

const run = (text, o) => new TextRun({ text: String(text == null ? '' : text), font: FONT, size: (o && o.size) || SZ, bold: !!(o && o.bold) });
const p = (children, o) => new Paragraph({ children, alignment: (o && o.align) || L, spacing: { before: (o && o.before) || 0, after: (o && o.after) || 0 } });

function cell(children, width, o) {
    o = o || {};
    return new TableCell({
        children: children.length ? children : [p([])],
        width: { size: width, type: WidthType.DXA },
        columnSpan: o.span,
        rowSpan: o.rowSpan,
        borders: Object.assign({ top: NONE, bottom: NONE, left: NONE, right: NONE }, o.borders || {}),
        verticalAlign: o.valign || VerticalAlign.CENTER,
        margins: { top: o.pad != null ? o.pad : 20, bottom: o.pad != null ? o.pad : 20, left: 60, right: 60 },
    });
}
const row = (cells, o) => new TableRow({
    children: cells, cantSplit: true, tableHeader: !!(o && o.header),
    height: o && o.h ? { value: o.h, rule: HeightRule.ATLEAST } : undefined,
});
const inner = (widths, rows) => new Table({
    width: { size: widths.reduce((a, b) => a + b, 0), type: WidthType.DXA }, columnWidths: widths,
    layout: TableLayoutType.FIXED, borders: NO_BORDERS, rows,
});

// ── Header rows (repeat on every page) ────────────────────────────────────────
const TW = pct([13, 47, 8, 32], W - 120);
const top = inner(TW, [row([
    cell([p([run('Entity Name :', { bold: true })])], TW[0], { valign: VerticalAlign.BOTTOM }),
    cell([p([run(m.entity || ' ', { bold: true })])], TW[1], { valign: VerticalAlign.BOTTOM }),
    cell([p([run('Fund :', { bold: true })], { align: R })], TW[2], { valign: VerticalAlign.BOTTOM }),
    cell([p([run(m.fund || ' ', { bold: true })])], TW[3], { valign: VerticalAlign.BOTTOM, borders: { bottom: LINE } }),
])]);

const GW = pct([38, 62], span(3, 3) - 120);
const glBox = inner(GW, [['GL Account Name :', m.gl_title], ['Account Code :', m.gl_code], ['Bank Account No. :', m.bank]].map(([k, val]) => row([
    cell([p([run(k, { bold: true })])], GW[0], { pad: 0, valign: VerticalAlign.TOP }),
    cell([p([run(val || ' ', { bold: true })])], GW[1], { pad: 0, valign: VerticalAlign.TOP }),
])));

const box = { top: BOLD, bottom: BOLD, left: BOLD, right: BOLD };
const th = (t, width, o) => cell([p([run(t, { bold: !(o && o.normal) })], { align: C })], width, Object.assign({ borders: box }, o));
const head = [
    row([cell([p([run('SUBSIDIARY LEDGER', { bold: true, size: SZ_TITLE })], { align: C, after: 200 })], W, { span: 6 })], { header: true }),
    row([cell([top, p([run('', { size: 4 })])], W, { span: 6 })], { header: true }),
    row([
        cell([p([run(m.sl_name || ' ', { bold: true })], { align: C }),
              p([run('SL Account Name' + (m.sl_code ? '  (' + m.sl_code + ')' : ''), { bold: true })], { align: C, before: 40 })], span(0, 3), { span: 3, borders: box }),
        cell([glBox], span(3, 3), { span: 3, borders: box }),
    ], { header: true, h: 1040 }),
    row([
        th('Date', CW[0], { rowSpan: 2 }), th('Particulars', CW[1], { rowSpan: 2 }), th('Ref', CW[2], { rowSpan: 2 }),
        th('Amount', span(3, 3), { span: 3 }),
    ], { header: true }),
    row([th('Debit', CW[3], { normal: true }), th('Credit', CW[4], { normal: true }), th('Balance', CW[5], { normal: true })], { header: true }),
];

// ── Body rows ─────────────────────────────────────────────────────────────────
const ALIGN = [C, L, C, R, R, R];
function line(vals, o) {
    o = o || {};
    return row(vals.map((v, i) => cell([p([run(v || '', { bold: o.bold })], { align: o.align && o.align[i] || ALIGN[i] })], CW[i], {
        pad: 10, valign: VerticalAlign.TOP,
        borders: { left: BOLD, right: BOLD, top: o.top || NONE, bottom: o.bottom || LINE },
    })), { h: 270 });
}
const body = [];
let count = 0;   // printed lines — a long Particulars wraps (~36 characters a line), as in the HTML
(m.rows || []).forEach(function (r) {
    body.push(line([r.date, r.text, r.ref, r.d, r.c, r.bal]));
    count += Math.max(1, Math.ceil(String(r.text || '').length / 36));
});
for (; count < (m.min_rows || 0); count++) body.push(line(['', '', '', '', '', '']));
const t = m.totals || {};
body.push(line(['', 'Totals', '', t.d, t.c, t.bal], { bold: true, top: BOLD, bottom: BOLD, align: [C, C, C, R, R, R] }));

// ── Document ──────────────────────────────────────────────────────────────────
const doc = new Document({
    sections: [{
        properties: {
            page: {
                size: { width: 12240, height: 15840 },   // letter
                margin: { top: MARGIN, right: MARGIN, bottom: MARGIN, left: MARGIN, header: 400, footer: 400 },
            },
        },
        children: [new Table({
            width: { size: W, type: WidthType.DXA }, columnWidths: CW, layout: TableLayoutType.FIXED,
            borders: NO_BORDERS, rows: head.concat(body),
        })],
    }],
});

Packer.toBuffer(doc).then(function (buf) {
    fs.writeFileSync(outPath, buf);
    console.log('OK:' + outPath);
}).catch(function (err) {
    process.stderr.write(err.message + '\n');
    process.exit(1);
});
