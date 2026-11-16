<?php
/*
 * COB — one approved budget request, read-only. prefix: cobv_
 *
 * Opened from the COB page (app/pages/cob-page.php) by a project row's "View": window.cobModal.open(budgetReferenceNumber,
 * projectCode?) — projectCode, when given, scrolls to and highlights that project. It shows what the request is made of, straight
 * from GET /cob/read/{budget_reference_number} (CobService::fetchById): the request, then every Program block, every Project of
 * it, each Project's chart-of-accounts line items with their figures, and its supporting documents (fims.budget_req_project_attachment).
 *
 * Included INSIDE the page's .nha-root: every .nha-overlay / .nha-modal rule of _shared/css/nha-fims.css is scoped to it.
 *
 * LOAD ORDER: this file's <script> runs BEFORE the page's own <script> (the include sits in the markup, above it), so nothing here may
 * touch the page's globals at load. COB_API is read when open() runs, never earlier.
 */
?>
<style>
#cobv-overlay .cobv-info { display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px 20px;margin-bottom:16px; }
#cobv-overlay .cobv-k { display:block;font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--afims-text-muted, #62657E); }
#cobv-overlay .cobv-v { display:block;margin-top:2px;font-size:13.5px;font-weight:500;color:var(--afims-text, #1A1B21);overflow-wrap:anywhere; }
#cobv-overlay .cobv-sub { display:block;font-size:11.5px;color:var(--afims-text-muted, #62657E);font-weight:400; }
#cobv-overlay .cobv-tiles { display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:18px; }
#cobv-overlay .cobv-tile { padding:10px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;background:var(--afims-surface-subtle, #F4F7FA); }
#cobv-overlay .cobv-tile .cobv-k { font-size:10px; }
#cobv-overlay .cobv-tile b { display:block;margin-top:3px;font-family:'IBM Plex Mono',monospace;font-size:14px;color:var(--afims-heading, #000E3E); }
#cobv-overlay .cobv-tile.cobv-total { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E); }
#cobv-overlay .cobv-tile.cobv-total .cobv-k,
#cobv-overlay .cobv-tile.cobv-total b { color:#fff; }
#cobv-overlay .cobv-prog { margin-bottom:18px; }
#cobv-overlay .cobv-prog-hdr { display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:6px 14px;padding:8px 12px;margin-bottom:8px;
    background:var(--afims-surface-subtle, #F4F7FA);border-radius:8px;font-weight:700;font-size:13.5px;color:var(--afims-heading, #000E3E); }
#cobv-overlay .cobv-prog-hdr small { font-weight:500;font-size:12px;color:var(--afims-text-muted, #62657E); }
#cobv-overlay .cobv-proj { margin:0 0 12px 8px;padding:12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:10px; }
#cobv-overlay .cobv-proj.cobv-focus { border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 2px rgba(0,14,62,.12); }
#cobv-overlay .cobv-proj-hdr { display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:4px 14px;margin-bottom:8px;font-weight:600;font-size:13px; }
#cobv-overlay .cobv-proj-hdr small { font-weight:500;font-size:12px;color:var(--afims-text-muted, #62657E); }
#cobv-overlay .cobv-scroll { overflow-x:auto; }
#cobv-overlay table.cobv-tbl { width:100%;min-width:900px;border-collapse:collapse;font-size:12.5px; }
#cobv-overlay .cobv-tbl th:first-child, #cobv-overlay .cobv-tbl td:first-child { min-width:180px; }
#cobv-overlay .cobv-tbl th:nth-child(2), #cobv-overlay .cobv-tbl td:nth-child(2) { min-width:100px; }
#cobv-overlay .cobv-tbl th:nth-child(3), #cobv-overlay .cobv-tbl td:nth-child(3) { min-width:90px; }
#cobv-overlay .cobv-tbl th { padding:7px 7px;text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;
    color:var(--afims-text-muted, #62657E);background:var(--afims-surface-subtle, #F4F7FA);border-bottom:1.5px solid var(--afims-border, #E4ECF2); }
#cobv-overlay .cobv-tbl td { padding:7px;border-bottom:1px solid var(--afims-border, #E4ECF2);vertical-align:top; }
#cobv-overlay .cobv-tbl .cobv-num { text-align:right;white-space:nowrap;font-family:'IBM Plex Mono',monospace;font-size:11.5px; }
#cobv-overlay .cobv-tbl th.cobv-num { font-family:inherit;font-size:10.5px;white-space:normal; }
#cobv-overlay .cobv-tbl tfoot td { font-weight:700;border-bottom:none;background:var(--afims-surface-subtle, #F4F7FA); }
#cobv-overlay .cobv-code { font-family:'IBM Plex Mono',monospace;font-size:11px;padding:1px 6px;border-radius:5px;background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-muted, #62657E); }
#cobv-overlay .cobv-docs { margin-top:10px;padding-top:8px;border-top:1px dashed var(--afims-border, #E4ECF2); }
#cobv-overlay .cobv-docs-title { margin:0 0 6px;font-size:10.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--afims-text-muted, #62657E); }
#cobv-overlay .cobv-doc { display:flex;align-items:center;gap:8px;padding:4px 0;font-size:12.5px; }
#cobv-overlay .cobv-doc a { color:var(--afims-primary-text, #0035A9);font-weight:600;text-decoration:none; }
#cobv-overlay .cobv-doc a:hover { text-decoration:underline; }
#cobv-overlay .cobv-doc .cobv-na { color:var(--afims-text-disabled, #A9AAB8);font-size:12px; }
#cobv-overlay .cobv-muted { color:var(--afims-text-muted, #62657E);font-size:12.5px; }
#cobv-overlay .cobv-adj-pos { color:#16a34a; }
#cobv-overlay .cobv-adj-neg { color:#dc2626; }
</style>

<div id="cobv-overlay" class="nha-overlay no-print">
    <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="cobv-title">
        <div class="nha-modal-hdr">
            <h3 id="cobv-title">Approved Budget Request</h3>
            <button type="button" class="nha-modal-close" id="cobv-close" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body" id="cobv-body"></div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn nha-btn-ghost" id="cobv-cancel">Close</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   COB REQUEST DETAILS MODAL   prefix: cobv_   (see the notes at the top of this file)
═══════════════════════════════════════════════════════════════ */
(function () {
    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = v => '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    // A pasted document link is user text: only a plain http(s) address may ever become an href (never javascript: / data:).
    const safeUrl = u => (typeof u === 'string' && /^https?:\/\//i.test(u.trim())) ? u.trim() : null;
    const chip = t => t ? `<span class="cobv-code">${esc(t)}</span>` : '';

    // "2026-03-01 08:00:00+08" is not an ISO string every browser parses — normalise it, and fall back to its date part.
    function fmtDate(s) {
        if (!s) return '—';
        const show = d => isNaN(d) ? String(s).slice(0, 10) : d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        const m = String(s).match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(:\d{2})?(?:\.\d+)?(Z|[+-]\d{2}(?::?\d{2})?)?$/);
        if (!m) return show(new Date(s));
        let tz = m[4] || '';                                   // "+08" / "+0800" / "+08:00" / "Z" / none
        if (/^[+-]\d{2}$/.test(tz)) tz += ':00';
        else if (/^[+-]\d{4}$/.test(tz)) tz = tz.slice(0, 3) + ':' + tz.slice(3);
        return show(new Date(`${m[1]}T${m[2]}${m[3] || ':00'}${tz}`));
    }
    const adj = v => {
        const n = Number(v || 0);
        return n === 0 ? '—' : `<span class="${n > 0 ? 'cobv-adj-pos' : 'cobv-adj-neg'}">${n > 0 ? '+' : '−'} ${money(Math.abs(n))}</span>`;
    };

    const state = { seq: 0, lastFocus: null };

    const tile = (label, value, cls) => `<div class="cobv-tile ${cls || ''}"><span class="cobv-k">${esc(label)}</span><b>${value}</b></div>`;
    const tiles = t => `<div class="cobv-tiles">`
        + tile('Total', money(t.total), 'cobv-total') + tile('Fund Balance', money(t.fund_balance))
        + tile('Adjustment', adj(t.adjustment)) + tile('Carry Over Obligation', money(t.carry_over_obligation))
        + tile('Obligation', money(t.obligation)) + tile('Disbursement', money(t.disbursement)) + `</div>`;

    function lineTable(lines, totals) {
        if (!lines.length) return `<p class="cobv-muted">No line items.</p>`;
        const rows = lines.map(l => `<tr>
            <td>${esc(l.account_title || '—')}${l.account_code ? ` ${chip(l.account_code)}` : ''}${l.particulars ? `<span class="cobv-sub" style="display:block;font-size:11.5px;color:var(--afims-text-muted,#62657E);">${esc(l.particulars)}</span>` : ''}</td>
            <td>${esc(l.type_of_fund_name || '—')}</td><td>${esc(l.component_name || '—')}</td>
            <td class="cobv-num">${money(l.proposed_amount)}</td><td class="cobv-num">${adj(l.adjustment)}</td>
            <td class="cobv-num">${money(l.fund_balance)}</td><td class="cobv-num">${money(l.carry_over_obligation)}</td>
            <td class="cobv-num">${money(l.obligation)}</td><td class="cobv-num">${money(l.disbursement)}</td></tr>`).join('');
        return `<div class="cobv-scroll"><table class="cobv-tbl"><thead><tr>
            <th>Account</th><th>Type of Fund</th><th>Component</th><th class="cobv-num">Proposed</th><th class="cobv-num">Adjustment</th>
            <th class="cobv-num">Fund Balance</th><th class="cobv-num">Carry Over Obligation</th><th class="cobv-num">Obligation</th><th class="cobv-num">Disbursement</th>
            </tr></thead><tbody>${rows}</tbody><tfoot><tr><td colspan="3">Total</td>
            <td class="cobv-num">${money(totals.proposed_amount)}</td><td class="cobv-num">${adj(totals.adjustment)}</td><td class="cobv-num">${money(totals.fund_balance)}</td>
            <td class="cobv-num">${money(totals.carry_over_obligation)}</td><td class="cobv-num">${money(totals.obligation)}</td><td class="cobv-num">${money(totals.disbursement)}</td>
            </tr></tfoot></table></div>`;
    }

    function docsHtml(docs) {
        const items = (docs || []).map(d => {
            const url = safeUrl(d.view_url);
            return `<div class="cobv-doc"><span class="nha-bdg nha-bdg-xs ${d.is_link ? 'nha-bdg-blue' : 'nha-bdg-gray'}">${d.is_link ? 'Link' : 'File'}</span>
                <span>${esc(d.document_name)}</span>
                ${url ? `<a href="${esc(url)}" target="_blank" rel="noopener noreferrer">Open</a>` : `<span class="cobv-na">not available</span>`}</div>`;
        }).join('');
        return `<div class="cobv-docs"><h5 class="cobv-docs-title">Supporting documents (${(docs || []).length})</h5>${items || '<span class="cobv-muted">None attached.</span>'}</div>`;
    }

    const projectCard = (p, focusCode) => `<div class="cobv-proj${focusCode && p.project_code === focusCode ? ' cobv-focus' : ''}" data-project="${esc(p.project_code)}">
        <div class="cobv-proj-hdr"><span>${esc(p.project_name || p.project_code || 'No project')} ${chip(p.project_code)}</span>
            <small>${p.location ? esc(p.location) + ' · ' : ''}Fund Balance ${money(p.totals.fund_balance)}</small></div>
        ${lineTable(p.line_items, p.totals)}${docsHtml(p.attachments)}</div>`;

    function render(r, focusCode) {
        $('cobv-title').innerHTML = `Approved Budget Request <span class="cobv-code" style="margin-left:8px;">${esc(r.budget_reference_number)}</span>`;
        const office = r.rc_name ? `${esc(r.rc_name)}${r.rc_code ? ` ${chip(r.rc_code)}` : ''}` : '—';
        const status = r.status_name ? `<span class="nha-bdg nha-bdg-green" ${/^#[0-9a-f]{6}$/i.test(r.status_color || '') ? `style="background:${r.status_color}22;color:${r.status_color};"` : ''}>${esc(r.status_name)}</span>` : '—';
        let html = `<div class="cobv-info">
            <div><span class="cobv-k">Reference No.</span><span class="cobv-v">${esc(r.budget_reference_number)}</span></div>
            <div><span class="cobv-k">Fiscal Year</span><span class="cobv-v">${esc(r.fiscal_yr)}</span></div>
            <div><span class="cobv-k">Requesting Office</span><span class="cobv-v">${office}${r.rc_parent_name ? `<span class="cobv-sub">${esc(r.rc_parent_name)}</span>` : ''}${r.rc_office_type ? `<span class="cobv-sub">${esc(r.rc_office_type)}</span>` : ''}</span></div>
            <div><span class="cobv-k">Status</span><span class="cobv-v">${status}</span></div>
            <div><span class="cobv-k">Date Submitted</span><span class="cobv-v">${esc(fmtDate(r.submitted_at))}</span></div></div>`;
        html += tiles(r.totals);

        const programs = r.programs || [];
        html += programs.map(g => `<div class="cobv-prog">
            <div class="cobv-prog-hdr"><span>${esc(g.program_name || g.program_code)} ${chip(g.program_code)}${g.sub_program_name ? ` <small>· ${esc(g.sub_program_name)}</small>` : ''}</span>
                <small>Fund Balance ${money(g.totals.fund_balance)}</small></div>
            ${(g.projects || []).length ? g.projects.map(p => projectCard(p, focusCode)).join('') : '<p class="cobv-muted" style="margin-left:12px;">No projects under this program.</p>'}</div>`).join('');

        const loose = r.unassigned_line_items || [];
        if (loose.length) {
            const t = loose.reduce((a, l) => { for (const k of ['proposed_amount', 'adjustment', 'fund_balance', 'carry_over_obligation', 'obligation', 'disbursement']) a[k] += Number(l[k] || 0); return a; },
                { proposed_amount: 0, adjustment: 0, fund_balance: 0, carry_over_obligation: 0, obligation: 0, disbursement: 0 });
            html += `<div class="cobv-prog"><div class="cobv-prog-hdr"><span>No program / project</span><small>Line items of an older request with no program block</small></div>
                <div class="cobv-proj">${lineTable(loose, t)}</div></div>`;
        }
        if (!programs.length && !loose.length) html += '<p class="cobv-muted">This request has no line items.</p>';
        $('cobv-body').innerHTML = html;

        if (focusCode) {
            const el = [...$('cobv-body').querySelectorAll('.cobv-proj[data-project]')].find(n => n.dataset.project === focusCode);
            if (el && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });   // only as far as needed: the request's own header stays in view when the project is near the top
        }
    }

    async function open(budgetReferenceNumber, projectCode) {
        const overlay = $('cobv-overlay');
        if (!overlay) return;
        const my = ++state.seq;
        if (!overlay.classList.contains('open')) state.lastFocus = document.activeElement;
        $('cobv-title').textContent = 'Approved Budget Request';
        $('cobv-body').innerHTML = '<div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div>';
        overlay.classList.add('open');
        const closeBtn = $('cobv-close'); if (closeBtn) closeBtn.focus();
        try {
            // COB_API belongs to the page, which declares it AFTER this file ran — read it now, not at load.
            if (typeof COB_API === 'undefined') throw new Error('The COB page is not ready.');
            const res = await fetch(`${COB_API}/read/${encodeURIComponent(budgetReferenceNumber)}?central=1`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            let json = null; try { json = await res.json(); } catch (e) { /* not JSON */ }
            if (!res.ok || !json || json.success === false) throw new Error((json && json.message) || ('HTTP ' + res.status));
            if (my !== state.seq) return;              // a newer open() superseded this one
            render(json.data, projectCode || '');
        } catch (e) {
            if (my !== state.seq) return;
            $('cobv-body').innerHTML = `<div class="nha-error">${esc(e.message || 'The request could not be loaded.')}</div>`;
        }
    }

    function close() {
        const overlay = $('cobv-overlay');
        if (!overlay || !overlay.classList.contains('open')) return;
        state.seq++;                                   // a response still on its way must not repaint a closed modal
        overlay.classList.remove('open');
        const back = state.lastFocus; state.lastFocus = null;
        if (back && back.focus && document.contains(back)) back.focus();
    }

    function bind() {
        $('cobv-close').addEventListener('click', close);
        $('cobv-cancel').addEventListener('click', close);
        $('cobv-overlay').addEventListener('click', e => { if (e.target === $('cobv-overlay')) close(); });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && $('cobv-overlay').classList.contains('open')) { e.preventDefault(); close(); }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind); else bind();

    window.cobModal = { open, close };
})();
</script>
