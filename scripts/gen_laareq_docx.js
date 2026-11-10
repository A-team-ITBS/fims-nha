/**
 * LAA release request (regional memorandum) — DOCX generator.
 *
 *   node scripts/gen_laareq_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by LaaRequestPrintService::model() — or FtlaRequestPrintService::model(),
 * the FTLA request memo, which shares this layout — every word is already
 * resolved there; this script only lays it out. The same model renders the HTML
 * preview / PDF (LaaRequestPrintService::html).
 *
 * Layout ported from the old REGIONAL gen_laa_docx.js: letter paper (8.5" × 11"),
 * 1in sides, Arial 11pt; MEMORANDUM, FOR / THRU / SUBJECT rows (label 1.2",
 * colon 0.3", value 5"), a rule, the request paragraph, closing and signatory.
 *
 * Header: the model's header_parts (letterhead layout — edited for this print
 * or the saved default), else the built-in banner via _shared/docx-branding.js
 * (UAC → Header Management, scope FMD / LAAREQ) →
 * _shared/images/letterhead/nha-letterhead-banner.png. No footer line.
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, Header,
    AlignmentType, BorderStyle, WidthType, VerticalAlign,
} = docx;
const fs = require('fs');
const path = require('path');
const branding = require('../_shared/docx-branding');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_laareq_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Header ────────────────────────────────────────────────────────────────────
const HEADER_W = 624;                // px — the 468pt (6.5") text width
const PART_SCALE = HEADER_W / 630;   // layout sizes are given for a 630 px letterhead

function defaultHeader() {
    try {
        const buf = fs.readFileSync(path.join(__dirname, '..', '_shared', 'images', 'letterhead', 'nha-letterhead-banner.png'));
        const isPng = buf.length > 24 && buf.toString('ascii', 1, 4) === 'PNG';
        const w = isPng ? buf.readUInt32BE(16) : 0;
        const h = isPng ? buf.readUInt32BE(20) : 0;
        const k = w && h ? Math.min(HEADER_W / w, 120 / h) : 0;
        return { base64: buf.toString('base64'), width: k ? Math.round(w * k) : HEADER_W, height: k ? Math.round(h * k) : 94 };
    } catch (e) {
        return { base64: null, width: HEADER_W, height: 94 };
    }
}

let headerChildren;
if (Array.isArray(m.header_parts)) {
    const parts = m.header_parts.map(function (p) {
        const q = Object.assign({}, p);
        if (p.base64) q.data = Buffer.from(p.base64, 'base64');
        if (p.render) q.render = { width: Math.round(p.render.width * PART_SCALE), height: Math.round(p.render.height * PART_SCALE) };
        ['x', 'y', 'box_width'].forEach(function (key) { if (p[key] != null) q[key] = p[key] * PART_SCALE; });
        return q;
    });
    headerChildren = parts.length
        // origin: left margin / header distance (twips) — see the page margins below
        ? branding.buildHeaderChildren(docx, { parts, totalWidth: HEADER_W, origin: { left: 1440, top: 708 } }).children
        : [new Paragraph({ children: [] })];
} else {
    const fb = defaultHeader();
    headerChildren = branding.buildHeaderChildren(docx, {
        system: 'FMD', document: 'LAAREQ', data: m,
        fallbackBase64: fb.base64, totalWidth: fb.width, totalHeight: fb.height,
    }).children;
}

// ── Text helpers ──────────────────────────────────────────────────────────────
const FONT = 'Arial';
const SZ = 22;   // 11pt

function run(text, o) {
    o = o || {};
    return new TextRun({ text: String(text == null ? '' : text), font: FONT, size: SZ, bold: !!o.bold });
}
function p(children, o) {
    o = o || {};
    return new Paragraph({
        children: Array.isArray(children) ? children : [children],
        alignment: o.align || AlignmentType.LEFT,
        spacing: { before: o.before || 0, after: o.after !== undefined ? o.after : 0 },
        border: o.border,
    });
}

const NONE = { style: BorderStyle.NONE, size: 0, color: 'auto' };
const noBorder = { top: NONE, bottom: NONE, left: NONE, right: NONE };
const COLS = [1728, 432, 7200];   // label 1.2" | colon 0.3" | value 5"

const cell = (paras, i) => new TableCell({
    borders: noBorder, width: { size: COLS[i], type: WidthType.DXA }, verticalAlign: VerticalAlign.TOP, children: paras,
});
const row = (label, valueParas) => new TableRow({ children: [
    cell([p([run(label, { bold: true })])], 0),
    cell([p([run(':')])], 1),
    cell(valueParas, 2),
] });

// ── Body ──────────────────────────────────────────────────────────────────────
const children = [];
children.push(p([run(m.date)], { after: 280 }));
children.push(p([run('MEMORANDUM', { bold: true })], { after: 280 }));

const rows = (m.rows || []).map(r => row(r.label, [p([run(r.name, { bold: true })]), p([run(r.title || '')], { after: 200 })]));
rows.push(row('SUBJECT', [p([run(m.subject, { bold: true })])]));
children.push(new Table({
    width: { size: COLS.reduce((s, w) => s + w, 0), type: WidthType.DXA },
    columnWidths: COLS,
    borders: Object.assign({}, noBorder, { insideHorizontal: NONE, insideVertical: NONE }),
    rows,
}));

children.push(p([], { before: 160, after: 240, border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: '000000', space: 1 } } }));
(m.paragraphs || []).forEach(runs =>
    children.push(p(runs.map(r => run(r.text, { bold: r.bold })), { align: AlignmentType.JUSTIFIED, after: 280 })));
children.push(p([run(m.closing)]));

// Space for the wet signature
for (let i = 0; i < 4; i++) children.push(p([run('')], { after: i === 3 ? 80 : 0 }));
const sig = m.signatory || {};
children.push(p([run(sig.name, { bold: true })]));
children.push(p([run(sig.title)]));
if (sig.office) children.push(p([run(sig.office)]));

// ── Document ──────────────────────────────────────────────────────────────────
const doc = new Document({
    sections: [{
        properties: {
            page: {
                size: { width: 12240, height: 15840 }, // letter
                margin: { top: 1440, right: 1440, bottom: 1080, left: 1440, header: 708, footer: 708 },
            },
        },
        headers: { default: new Header({ children: headerChildren }) },
        children,
    }],
});

Packer.toBuffer(doc).then(function (buf) {
    fs.writeFileSync(outPath, buf);
    console.log('OK:' + outPath);
}).catch(function (err) {
    process.stderr.write(err.message + '\n');
    process.exit(1);
});
