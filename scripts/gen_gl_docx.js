/**
 * General Ledger (NHA Form No. 11) — DOCX generator.
 *
 *   node scripts/gen_gl_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model built by
 * GeneralLedgerPrintService::model() — the monthly summary lines, amounts and the Ending Balance are
 * already resolved there; this script only lays them out, the same as GeneralLedgerPrintService::html().
 *
 * Letter portrait, 0.5in margins, Arial 9pt. One table: NHA Form No. 11, GENERAL LEDGER, NATIONAL HOUSING
 * AUTHORITY (Agency), Fund / Account Code, Account Title / Sheet No., the column headings (Date · Particulars ·
 * Reference · Debit · Credit · Balance) — all header rows, so they repeat on every page — then the year row,
 * the lines, blank filler rows and the "<year> Dec 31 Ending Balance" row. No letterhead.
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, TableLayoutType, HeightRule,
} = docx;
const fs = require('fs');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_gl_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Page ──────────────────────────────────────────────────────────────────────
const MARGIN = 720;                        // 0.5in
const W = 12240 - MARGIN * 2;              // 10800 twips
const pct = (...shares) => {
    const ws = shares.map(s => Math.round(W * s / 100));
    ws[ws.length - 1] += W - ws.reduce((a, b) => a + b, 0);
    return ws;
};
const CW = pct(7, 4, 31, 10, 16, 16, 16);   // month | day | particulars | reference | debit | credit | balance

// ── Helpers ───────────────────────────────────────────────────────────────────
const FONT = 'Arial';
const SZ = 18, SZ_SMALL = 16, SZ_TITLE = 26;   // 9 / 8 / 13pt
const C = AlignmentType.CENTER, R = AlignmentType.RIGHT, L = AlignmentType.LEFT;
const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const LINE = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
const THICK = { style: BorderStyle.SINGLE, size: 10, color: '000000' };
const HAIR = { style: BorderStyle.SINGLE, size: 2, color: '999999' };
const NO_BORDERS = { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE };

const run = (text, o) => new TextRun({
    text: String(text == null ? '' : text), font: FONT, size: (o && o.size) || SZ, bold: !!(o && o.bold), italics: !!(o && o.italic),
});
const p = (children, o) => new Paragraph({ children, alignment: (o && o.align) || L, spacing: { before: 0, after: (o && o.after) || 0 } });
const span = (from, n) => CW.slice(from, from + n).reduce((a, b) => a + b, 0);

function cell(children, width, o) {
    o = o || {};
    return new TableCell({
        children: children.length ? children : [p([])],
        width: { size: width, type: WidthType.DXA },
        columnSpan: o.span,
        rowSpan: o.rowSpan,
        borders: Object.assign({ top: NONE, bottom: NONE, left: NONE, right: NONE }, o.borders || {}),
        verticalAlign: o.valign || VerticalAlign.CENTER,
        margins: { top: o.tight ? 0 : 20, bottom: o.tight ? 0 : 20, left: 60, right: 60 },
    });
}
const row = (cells, o) => new TableRow({
    children: cells, cantSplit: true, tableHeader: !!(o && o.header),
    height: o && o.h ? { value: o.h, rule: HeightRule.ATLEAST } : undefined,
});

// ── Header rows (repeat on every page) ────────────────────────────────────────
// the agency: the name underlined, "Agency" below — a small centred table inside one full-width cell
const AGW = Math.round(W * 0.5);
const agency = new Table({
    width: { size: AGW, type: WidthType.DXA }, columnWidths: [AGW], layout: TableLayoutType.FIXED, borders: NO_BORDERS,
    alignment: AlignmentType.CENTER,
    rows: [
        row([cell([p([run(m.agency || '', { bold: true })], { align: C })], AGW, { borders: { bottom: LINE } })]),
        row([cell([p([run('Agency', { size: SZ_SMALL })], { align: C })], AGW, { tight: true })]),
    ],
});
// the account block: Label | underlined value | gap | Label | underlined value
const AW = (() => { const inner = W - 120; const ws = [15, 41, 4, 15, 25].map(s => Math.round(inner * s / 100)); ws[4] += inner - ws.reduce((a, b) => a + b, 0); return ws; })();
const acctRow = (l1, v1, l2, v2) => row([
    cell([p([run(l1, { bold: true })])], AW[0], { valign: VerticalAlign.BOTTOM }),
    cell([p([run(v1 || ' ', { bold: true })])], AW[1], { borders: { bottom: LINE }, valign: VerticalAlign.BOTTOM }),
    cell([], AW[2]),
    cell([p([run(l2, { bold: true })])], AW[3], { valign: VerticalAlign.BOTTOM }),
    cell([p([run(v2 || ' ', { bold: true })])], AW[4], { borders: { bottom: LINE }, valign: VerticalAlign.BOTTOM }),
], { h: 300 });
const acct = new Table({
    width: { size: W - 120, type: WidthType.DXA }, columnWidths: AW, layout: TableLayoutType.FIXED, borders: NO_BORDERS,
    rows: [acctRow('Fund:', m.fund, 'Account Code:', m.code), acctRow('Account Title:', m.title, 'Sheet No.', m.sheet)],
});
const head = [
    row([cell([p([run(m.form_no || 'NHA Form No. 11', { size: SZ_SMALL, italic: true })])], W, { span: 7 })], { header: true }),
    row([cell([p([run('GENERAL LEDGER', { bold: true, size: SZ_TITLE })], { align: C, after: 100 })], W, { span: 7 })], { header: true }),
    row([cell([agency, p([run('', { size: 4 })])], W, { span: 7 })], { header: true }),
    row([cell([acct, p([run('', { size: 4 })])], W, { span: 7 })], { header: true }),
];
const box = { top: LINE, bottom: LINE, left: LINE, right: LINE };
const th = (t, width, o) => cell([p([run(t, { bold: true })], { align: C })], width, Object.assign({ borders: box }, o));
head.push(row([
    th('Date', span(0, 2), { span: 2 }), th('Particulars', CW[2]), th('Reference', CW[3]),
    th('Debit', CW[4]), th('Credit', CW[5]), th('Balance', CW[6]),
], { header: true }));

// ── Body rows ─────────────────────────────────────────────────────────────────
const ALIGN = [L, R, L, C, R, R, R];
function line(vals, o) {
    o = o || {};
    return row(vals.map((v, i) => cell([p([run(v || '', { bold: o.bold })], { align: ALIGN[i] })], CW[i], {
        tight: true,
        borders: {
            left: i === 1 ? NONE : LINE, right: i === 0 ? NONE : LINE,
            top: o.top || NONE, bottom: o.bottom || HAIR,
        },
    })), { h: 230 });
}
const lineOf = (r) => line([r.mon, r.day, r.text, r.ref, r.d, r.c, r.bal]);
const body = [];
body.push(line([m.year, '', '', '', '', '', ''], { bold: true }));
let count = 1;
(m.rows || []).forEach(function (r) {
    body.push(r.kind === 'gap' ? line(['', '', '', '', '', '', '']) : lineOf(r));
    count++;
});
for (; count < (m.min_rows || 0); count++) body.push(line(['', '', '', '', '', '', '']));
const en = m.ending || {};
body.push(line([en.mon, en.day, en.text || 'Ending Balance', '', en.d, en.c, en.bal], { bold: true, top: THICK, bottom: THICK }));

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
