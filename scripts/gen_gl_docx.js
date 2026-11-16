/**
 * General Ledger — DOCX generator.
 *
 *   node scripts/gen_gl_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model built by
 * GeneralLedgerPrintService::model() — the monthly summary lines, amounts and closing totals are
 * already resolved there; this script only lays them out, the same as GeneralLedgerPrintService::html().
 *
 * Letter portrait, 0.5in margins, Arial 9pt. One table: GENERAL LEDGER title, Entity Name / Account Code,
 * Account Title / Sheet No., the column headings (Date · Particulars · Ref. · Amount: Debit · Credit · Balance)
 * — all header rows, so they repeat on every page — then the year row, the lines, blank filler rows and the
 * closing "Dec 31 Balance" row. No letterhead (the sheet has its own title).
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
const CW = pct(6, 5, 33, 9, 15.5, 15.5, 16);   // month | day | particulars | ref | debit | credit | balance

// ── Helpers ───────────────────────────────────────────────────────────────────
const FONT = 'Arial';
const SZ = 18, SZ_TITLE = 26;              // 9 / 13pt
const C = AlignmentType.CENTER, R = AlignmentType.RIGHT, L = AlignmentType.LEFT;
const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const LINE = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
const THICK = { style: BorderStyle.SINGLE, size: 10, color: '000000' };
const HAIR = { style: BorderStyle.SINGLE, size: 2, color: '999999' };

const run = (text, o) => new TextRun({ text: String(text == null ? '' : text), font: FONT, size: (o && o.size) || SZ, bold: !!(o && o.bold) });
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
// the account block: Label | underlined value | gap | Label | underlined value (a table inside one full-width cell)
const AW = (() => { const inner = W - 120; const ws = [14, 42, 4, 14, 26].map(s => Math.round(inner * s / 100)); ws[4] += inner - ws.reduce((a, b) => a + b, 0); return ws; })();
const acctRow = (l1, v1, l2, v2) => row([
    cell([p([run(l1, { bold: true })])], AW[0], { valign: VerticalAlign.BOTTOM }),
    cell([p([run(v1 || ' ', { bold: true })])], AW[1], { borders: { bottom: LINE }, valign: VerticalAlign.BOTTOM }),
    cell([], AW[2]),
    cell([p([run(l2, { bold: true })])], AW[3], { valign: VerticalAlign.BOTTOM }),
    cell([p([run(v2 || ' ', { bold: true })])], AW[4], { borders: { bottom: LINE }, valign: VerticalAlign.BOTTOM }),
], { h: 300 });
const acct = new Table({
    width: { size: W - 120, type: WidthType.DXA }, columnWidths: AW, layout: TableLayoutType.FIXED,
    borders: { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE },
    rows: [acctRow('Entity Name:', m.entity, 'Account Code:', m.code), acctRow('Account Title:', m.title, 'Sheet No.:', m.sheet)],
});
const head = [
    row([cell([p([run('GENERAL LEDGER', { bold: true, size: SZ_TITLE })], { align: C, after: 160 })], W, { span: 7 })], { header: true }),
    row([cell([acct, p([run('', { size: 4 })])], W, { span: 7 })], { header: true }),
];
const box = { top: LINE, bottom: LINE, left: LINE, right: LINE };
const th = (t, width, o) => cell([p([run(t, { bold: true })], { align: C })], width, Object.assign({ borders: box }, o));
head.push(row([
    th('Date', span(0, 2), { span: 2, rowSpan: 2 }), th('Particulars', CW[2], { rowSpan: 2 }), th('Ref.', CW[3], { rowSpan: 2 }),
    th('Amount', span(4, 3), { span: 3 }),
], { header: true }));
head.push(row([th('Debit', CW[4]), th('Credit', CW[5]), th('Balance', CW[6])], { header: true }));

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
    })), { h: 230, body: true });
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
const cl = m.closing || {};
body.push(line([cl.mon, cl.day, 'Balance', '', cl.d, cl.c, cl.bal], { bold: true, top: THICK, bottom: THICK }));

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
            width: { size: W, type: WidthType.DXA },
            columnWidths: CW,
            layout: TableLayoutType.FIXED,
            borders: { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE },
            rows: head.concat(body),
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
