<?php
/*
 * COB — "Generate Report" dialog. prefix: cobr_
 *
 * Opened by the Generate Report button of each tree tab of the COB page (app/pages/cob-page.php): window.cobReport.open(tab), tab =
 * region | fund_source | component. The user picks how deep the report goes (the first level only … down to the projects), checks a PREVIEW
 * (switchable PDF / Excel look), then exports to PDF or Excel.
 *
 * Everything is built by the server (GET /cob/report, CobReportService): the preview is the very HTML the PDF is laid out from, the Excel
 * preview an imitation of the sheet from the same rows and palette — so a file always matches what was previewed. The report covers every record
 * matching the page's filters (not just the rows expanded on screen) in the page's Amount Unit, and only what the caller's RC may see.
 * Export buttons stay off while the preview loads or is empty, and the export asks for exactly the parameters the preview was drawn from.
 *
 * Included INSIDE the page's .nha-root (the overlay / modal rules of _shared/css/nha-fims.css are scoped to it).
 *
 * LOAD ORDER: this file's <script> runs BEFORE the page's own <script>, so nothing here may touch the page's globals at load. COB_API, cob_params()
 * and cob_state are read when open() runs, never earlier.
 */
?>
<style>
#cobr-overlay .cobr-sub { margin: 0 0 14px; font-size: 12.5px; color: var(--afims-text-muted, #62657E); }
#cobr-overlay .cobr-lbl { margin: 0 0 6px; font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--afims-text-muted, #62657E); }
#cobr-overlay .cobr-opts { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); gap: 8px; margin-bottom: 16px; }
#cobr-overlay .cobr-opt { display: flex; align-items: flex-start; gap: 9px; padding: 9px 11px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 9px; cursor: pointer; }
#cobr-overlay .cobr-opt:hover { background: var(--afims-surface-subtle, #F4F7FA); }
#cobr-overlay .cobr-opt.on { border-color: var(--afims-heading-fill, #000E3E); background: var(--afims-surface-subtle, #F4F7FA); box-shadow: 0 0 0 1px var(--afims-heading-fill, #000E3E) inset; }
#cobr-overlay .cobr-opt input { margin-top: 3px; accent-color: var(--afims-heading-fill, #000E3E); }
#cobr-overlay .cobr-opt strong { display: block; font-size: 12.5px; color: var(--afims-heading, #000E3E); }
#cobr-overlay .cobr-opt small { display: block; margin-top: 1px; font-size: 11.5px; color: var(--afims-text-muted, #62657E); }
#cobr-overlay .cobr-pvbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
#cobr-overlay .cobr-pvbar .cobr-lbl { margin: 0; }
#cobr-overlay .cobr-seg { display: inline-flex; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; overflow: hidden; }
#cobr-overlay .cobr-seg button { padding: 5px 14px; font-size: 12px; font-weight: 600; border: 0; background: var(--afims-surface, #fff); color: var(--afims-text-muted, #62657E); cursor: pointer; font-family: inherit; }
#cobr-overlay .cobr-seg button.active { background: var(--afims-heading-fill, #000E3E); color: #fff; }
#cobr-overlay .cobr-stage { max-height: 44vh; overflow: auto; padding: 14px; background: #E8ECF2; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; }
#cobr-overlay .cobr-paper-wrap { min-width: 1000px; padding: 14px 16px; background: #fff; box-shadow: 0 1px 6px rgba(15, 32, 69, .18); }
#cobr-overlay .cobr-empty { padding: 28px 8px; text-align: center; font-size: 13px; color: var(--afims-text-muted, #62657E); }
#cobr-overlay .cobr-note { margin: 10px 0 0; font-size: 11.5px; line-height: 1.5; color: var(--afims-text-muted, #62657E); }
#cobr-overlay .cobr-msg { min-height: 16px; margin: 6px 0 0; font-size: 12.5px; font-weight: 600; color: #b42318; }
</style>

<div id="cobr-overlay" class="nha-overlay no-print">
    <div class="nha-modal u-modal-xl" role="dialog" aria-modal="true" aria-labelledby="cobr-title">
        <div class="nha-modal-hdr">
            <h3 id="cobr-title">Generate Report</h3>
            <button type="button" class="nha-modal-close" id="cobr-x" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body">
            <p class="cobr-sub">Choose what to include, check the preview, then export to PDF or Excel.</p>

            <p class="cobr-lbl" id="cobr-opts-lbl">What to include</p>
            <div class="cobr-opts" id="cobr-opts" role="radiogroup" aria-labelledby="cobr-opts-lbl"></div>

            <div class="cobr-pvbar">
                <p class="cobr-lbl">Preview <span id="cobr-count"></span></p>
                <div class="cobr-seg" role="group" aria-label="Preview as">
                    <button type="button" class="active" id="cobr-seg-pdf" aria-pressed="true">PDF</button>
                    <button type="button" id="cobr-seg-xlsx" aria-pressed="false">Excel</button>
                </div>
            </div>
            <div class="cobr-stage"><div id="cobr-preview" aria-live="polite"></div></div>

            <p class="cobr-note">Covers every record matching the page's filters, not only the rows opened on screen. Amounts in <strong id="cobr-unit">—</strong>.
                The file matches the preview.</p>
            <p class="cobr-msg" id="cobr-msg" role="alert"></p>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn nha-btn-ghost" id="cobr-cancel">Cancel</button>
            <button type="button" class="nha-btn nha-btn-outline" id="cobr-pdf" disabled>Export to PDF</button>
            <button type="button" class="nha-btn nha-btn-primary" id="cobr-xlsx" disabled>Export to Excel</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   COB GENERATE REPORT   prefix: cobr_   (see the notes at the top of this file)
═══════════════════════════════════════════════════════════════ */
(function () {
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // What each tab can include, shallowest first (the server's CobReportService::LAYOUTS says the same, longhand). A level means "down to it":
    // a program with no Sub-Program simply has no Sub-Program row.
    const TABS = {
        region: { title: 'Per Region', levels: [
            ['Region', 'Only the region rows'],
            ['Region + Program', 'Regions split by program'],
            ['Region + Program + Sub-Program', 'Programs split by sub-program'],
            ['Region + Program + Sub-Program + Projects', 'Down to the projects'],
        ] },
        fund_source: { title: 'Per Type of Fund', levels: [
            ['Type of Fund', 'Only the type of fund rows'],
            ['Type of Fund + Component', 'Fund types and their components'],
            ['Type of Fund + Component + Program', 'Components split by program'],
            ['Type of Fund + Component + Program + Sub-Program', 'Programs split by sub-program'],
            ['Type of Fund + Component + Program + Sub-Program + Projects', 'Down to the projects'],
        ] },
        component: { title: 'Per Component', levels: [
            ['Component', 'Only the component rows'],
            ['Component + Type of Fund', 'Components and the funds paying for them'],
            ['Component + Type of Fund + Program', 'Funds split by program'],
            ['Component + Type of Fund + Program + Sub-Program', 'Programs split by sub-program'],
            ['Component + Type of Fund + Program + Sub-Program + Projects', 'Down to the projects'],
        ] },
    };

    const state = {
        tab: null, depth: 0, mode: 'pdf',
        seq: 0,            // bumped on every load / close: a slow answer for an older request is dropped
        ready: false,      // there is a loaded, non-empty preview
        busy: false,       // an export is running
        busyFormat: null,  // which one: 'pdf' | 'xlsx'
        model: null,       // the loaded report model (both looks) — switching PDF / Excel redraws it without a refetch
        query: null,       // the exact parameters the shown preview was drawn from — the export asks for the same
        lastFocus: null,
    };

    const setMsg = t => { $('cobr-msg').textContent = t || ''; };
    function syncButtons() {
        const off = state.busy || !state.ready;
        $('cobr-pdf').disabled = $('cobr-xlsx').disabled = off;
        $('cobr-pdf').textContent = state.busy && state.busyFormat === 'pdf' ? 'Preparing PDF…' : 'Export to PDF';
        $('cobr-xlsx').textContent = state.busy && state.busyFormat === 'xlsx' ? 'Preparing Excel…' : 'Export to Excel';
    }
    const preview = html => { $('cobr-preview').innerHTML = html; };
    const previewMessage = t => { preview(`<div class="cobr-empty">${esc(t)}</div>`); $('cobr-count').textContent = ''; };

    // The page's filters (and central=1) as the page itself would send them, plus the Amount Unit — read now, not at load.
    function pageQuery() {
        if (typeof cob_params !== 'function' || typeof cob_state === 'undefined') throw new Error('The COB page is not ready.');
        return Object.assign({}, cob_params(), { unit: cob_state.unit });
    }

    async function call(query) {
        if (typeof COB_API === 'undefined') throw new Error('The COB page is not ready.');
        const res = await fetch(`${COB_API}/report?${new URLSearchParams(query)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        let json = null; try { json = await res.json(); } catch (e) { /* not JSON */ }
        if (!res.ok || !json || json.success === false) throw new Error((json && json.message) || ('HTTP ' + res.status));
        return json;
    }

    function renderOptions() {
        const tab = TABS[state.tab];
        $('cobr-opts').innerHTML = tab.levels.map(([label, hint], i) => `<label class="cobr-opt${i === state.depth ? ' on' : ''}">
            <input type="radio" name="cobr-depth" value="${i}"${i === state.depth ? ' checked' : ''}>
            <span><strong>${esc(label)}</strong><small>${esc(hint)}</small></span></label>`).join('');
        $('cobr-title').textContent = 'Generate Report — ' + tab.title;
    }

    function renderPreview(model) {
        $('cobr-unit').textContent = model.unit_label;
        if (!model.row_count) {
            state.ready = false;
            previewMessage('No approved records match the page\'s filters — there is nothing to export.');
        } else {
            preview(`<div class="cobr-paper-wrap">${state.mode === 'xlsx' ? model.preview_xlsx : model.preview_pdf}</div>`);
            $('cobr-count').textContent = `${model.row_count} row${model.row_count === 1 ? '' : 's'}`;
            state.ready = true;
        }
        syncButtons();
    }

    async function load() {
        const my = ++state.seq;
        state.ready = false; state.model = null;
        syncButtons(); setMsg('');
        previewMessage('Loading preview…');
        try {
            const query = Object.assign(pageQuery(), { layout_by: state.tab, depth: state.depth, format: 'json' });
            const json = await call(query);
            if (my !== state.seq) return;                 // closed, or another depth chosen, while loading
            state.query = query; state.model = json.data;
            renderPreview(json.data);
        } catch (e) {
            if (my !== state.seq) return;
            previewMessage('Could not load the preview: ' + e.message);
        }
    }

    function open(tab) {
        if (!TABS[tab]) return;
        const overlay = $('cobr-overlay');
        if (!overlay.classList.contains('open')) state.lastFocus = document.activeElement;
        state.tab = tab; state.depth = 0; state.busy = false;
        renderOptions();
        $('cobr-unit').textContent = '—';
        overlay.classList.add('open');
        const first = $('cobr-opts').querySelector('input:checked'); if (first) first.focus();
        load();
    }

    function close() {
        const overlay = $('cobr-overlay');
        if (state.busy || !overlay.classList.contains('open')) return;   // let a running export finish first
        state.seq++; state.ready = false; state.query = null; state.model = null;
        preview('');
        overlay.classList.remove('open');
        const back = state.lastFocus; state.lastFocus = null;
        if (back && back.focus && document.contains(back)) back.focus();
    }

    function setMode(mode) {
        state.mode = mode === 'xlsx' ? 'xlsx' : 'pdf';
        for (const m of ['pdf', 'xlsx']) { const b = $('cobr-seg-' + m); b.classList.toggle('active', m === state.mode); b.setAttribute('aria-pressed', m === state.mode ? 'true' : 'false'); }
        if (state.model) renderPreview(state.model);   // the same loaded model in the other look — nothing to refetch
    }

    function download(base64, mime, filename) {
        const bin = atob(base64), bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const url = URL.createObjectURL(new Blob([bytes], { type: mime }));
        const a = document.createElement('a');
        a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    async function run(format) {
        if (state.busy || !state.ready || !state.query) return;
        state.busy = true; state.busyFormat = format; syncButtons(); setMsg('');
        try {
            const json = await call(Object.assign({}, state.query, { format }));     // exactly what the preview was drawn from
            download(json.data, json.mime, json.filename);
            state.busy = false;
            close();
        } catch (e) {
            state.busy = false;
            setMsg('Export failed: ' + e.message);
        } finally {
            state.busy = false; syncButtons();
        }
    }

    function bind() {
        $('cobr-x').addEventListener('click', close);
        $('cobr-cancel').addEventListener('click', close);
        $('cobr-overlay').addEventListener('click', e => { if (e.target === $('cobr-overlay')) close(); });
        $('cobr-opts').addEventListener('change', e => {
            if (e.target.name !== 'cobr-depth') return;
            state.depth = parseInt(e.target.value, 10) || 0;
            $('cobr-opts').querySelectorAll('.cobr-opt').forEach(l => l.classList.toggle('on', l.contains(e.target)));
            load();
        });
        $('cobr-seg-pdf').addEventListener('click', () => setMode('pdf'));
        $('cobr-seg-xlsx').addEventListener('click', () => setMode('xlsx'));
        $('cobr-pdf').addEventListener('click', () => run('pdf'));
        $('cobr-xlsx').addEventListener('click', () => run('xlsx'));
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && $('cobr-overlay').classList.contains('open')) { e.preventDefault(); close(); }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();

    window.cobReport = { open, close };
})();
</script>
