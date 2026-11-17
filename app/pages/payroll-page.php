<?php
namespace PHPMaker2024\FIMS;

/**
 * payroll-page.php — Payroll setup (Accounting Records).
 *
 * PREVIEW ONLY — there is no payroll database or API behind this page yet, and the item list has been
 * removed for now: the page shows a header with an "Add Payroll Item" button that opens the form modal
 * (form/payroll-form.php). Saving validates the fields and stores nothing.
 * The Account Code picker is real: it loads the Chart of Accounts from GET /chart_of_accounts/all.
 */
$_pyr_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    .pyr-note { display:flex;align-items:flex-start;gap:10px;margin-bottom:16px;padding:10px 14px;
        background:var(--afims-surface-subtle, #F4F7FA);border:1px solid var(--afims-border, #E4ECF2);
        border-left:4px solid var(--afims-warning, #D97706);border-radius:8px;
        font-size:12px;line-height:1.5;color:var(--afims-text-muted, #62657E); }
    .pyr-note svg { flex-shrink:0;margin-top:1px;color:var(--afims-warning, #D97706); }
    .pyr-note strong { color:var(--afims-text, #1A1B21); }
</style>

<div id="pyr-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Payroll</div>
                <div class="nha-hdr-sub">Payroll items &amp; chart of accounts setup</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="pyr-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add a payroll item">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Payroll Item
            </button>
        </div>
    </div>

    <!-- ── PREVIEW NOTE ───────────────────────────────────── -->
    <div class="pyr-note no-print">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <strong>Preview — no payroll database yet.</strong>
            Account codes are loaded live from the Chart of Accounts. Saving only checks the fields; nothing is stored.
        </div>
    </div>

    <!-- ── EMPTY STATE (the item list is not built yet) ───── -->
    <div class="nha-table-wrap">
        <div class="nha-empty nha-empty-md">
            <span class="nha-empty-title">No payroll items yet</span>
            <span class="nha-empty-text">The item list is not built yet. Use “Add Payroll Item” to open the form.</span>
        </div>
    </div>

<?php include __DIR__ . '/form/payroll-form.php'; ?>

</div><!-- /#pyr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   PAYROLL ITEM FORM   prefix: pyr_
   PREVIEW ONLY — validates and closes; nothing is saved.
   When the payroll API exists, replace the body of pyr_save() with a fetch() like vnd_save() in vendors-page.php.
═══════════════════════════════════════════════════════════════ */

const PYR_COA_API = '<?php echo $_pyr_api; ?>' + '/chart_of_accounts/all';   // all Active accounts
const PYR_SL_API  = '<?php echo $_pyr_api; ?>' + '/voucher/sl-codes';         // SL masters (sl_code, sl_name, account_code); /{sl_code}/details for the details

const $pyr = id => document.getElementById(id);

const PYR_FIELDS = {               // payload key → input id
    item_code    : 'pyr-item-code',
    item_name    : 'pyr-item-name',
    category     : 'pyr-category',
    account_code : 'pyr-account-code',
    sl_code      : 'pyr-sl-code',
    sl_detail_id : 'pyr-sl-detail',
    status       : 'pyr-status',
    remarks      : 'pyr-remarks',
};

/* The Debit / Credit side is not entered: it follows from the category. */
const PYR_SIDE = { EARNING: 'Debit', REFUND: 'Debit', EMPLOYER_SHARE: 'Debit', DEDUCTION: 'Credit' };

let pyr_accounts = new Map();      // account_code → title  (postable accounts only)
let pyr_coaState = 'idle';         // idle | loading | ready | error
let pyr_coaError = '';

let pyr_slMasters = [];            // all active SL masters; the SL Code picker shows those of the chosen account
let pyr_slState   = 'idle';        // idle | loading | ready | error
let pyr_slError   = '';
let pyr_slFor     = null;          // the account the SL Code options were last built for
let pyr_slReq     = 0;             // guards against out-of-order SL Detail responses

function pyr_bind() {
    $pyr('pyr-add')?.addEventListener('click', pyr_openModal);
    $pyr('pyr-modal-close')?.addEventListener('click', pyr_closeModal);
    $pyr('pyr-cancel')?.addEventListener('click', pyr_closeModal);
    $pyr('pyr-form')?.addEventListener('submit', pyr_save);
    $pyr('pyr-account-code')?.addEventListener('input', () => { pyr_syncAccount(); pyr_fillSl(); });
    $pyr('pyr-category')?.addEventListener('change', pyr_syncSide);
    $pyr('pyr-sl-code')?.addEventListener('change', pyr_onSlChange);
    pyr_loadAccounts();
    pyr_loadSl();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', pyr_bind);
} else {
    pyr_bind();
}

/* ── Chart of Accounts ─────────────────────────────────────── */
function pyr_truthy(v) { return v === true || v === 1 || v === '1' || v === 't' || v === 'true'; }

/* Only postable accounts can carry a payroll amount. */
async function pyr_loadAccounts() {
    if (pyr_coaState === 'loading') return;
    pyr_coaState = 'loading'; pyr_coaError = '';
    pyr_syncAccount();
    try {
        const res  = await fetch(PYR_COA_API, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the chart of accounts.');

        pyr_accounts = new Map();
        const list = $pyr('pyr-account-list');
        list.textContent = '';
        for (const a of (json.data ?? [])) {
            if (!pyr_truthy(a.posting_allowed) || !a.account_code) continue;
            pyr_accounts.set(a.account_code, a.account_title || '');
            const opt = document.createElement('option');
            opt.value = a.account_code;
            opt.label = a.account_title || '';
            list.appendChild(opt);
        }
        pyr_coaState = 'ready';
    } catch (e) {
        pyr_coaState = 'error'; pyr_coaError = e.message;
        pyr_toast('Chart of accounts could not be loaded: ' + e.message, 'error');
    }
    pyr_syncAccount();
    pyr_fillSl(true);
}

/* Shows the picked account's title under the field. */
function pyr_syncAccount() {
    const hint = $pyr('pyr-account-hint');
    if (!hint) return;
    const code  = $pyr('pyr-account-code').value.trim();
    const title = pyr_accounts.get(code);

    hint.className = 'pyr-hint';
    if (pyr_coaState === 'loading') { hint.textContent = 'Loading chart of accounts…'; return; }
    if (pyr_coaState === 'error')   { hint.textContent = 'Chart of accounts unavailable: ' + pyr_coaError; hint.classList.add('bad'); return; }
    if (!code)                      { hint.textContent = `Postable accounts from the Chart of Accounts (${pyr_accounts.size}). Leave blank if none assigned yet.`; return; }
    if (title !== undefined)        { hint.textContent = title; hint.classList.add('ok'); return; }
    hint.textContent = 'No postable account with this code.';
    hint.classList.add('bad');
}

/* ── Subsidiary ledger (SL Code → SL Detail) ───────────────── */
async function pyr_loadSl() {
    if (pyr_slState === 'loading') return;
    pyr_slState = 'loading'; pyr_slError = '';
    pyr_fillSl(true);
    try {
        const res  = await fetch(PYR_SL_API, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load subsidiary ledgers.');
        pyr_slMasters = json.data ?? [];
        pyr_slState = 'ready';
    } catch (e) {
        pyr_slState = 'error'; pyr_slError = e.message;
        pyr_toast('Subsidiary ledgers could not be loaded: ' + e.message, 'error');
    }
    pyr_fillSl(true);
}

function pyr_option(value, label) {
    const o = document.createElement('option');
    o.value = value; o.textContent = label;
    return o;
}

/* Rebuilds the SL Code options for the chosen account; leaves them alone while the account is unchanged. */
function pyr_fillSl(force = false) {
    const code = $pyr('pyr-account-code').value.trim();
    const acct = pyr_accounts.has(code) ? code : '';
    if (!force && acct === pyr_slFor) return;
    pyr_slFor = acct;

    const sel = $pyr('pyr-sl-code'), hint = $pyr('pyr-sl-hint');
    const keep = sel.value;
    sel.textContent = '';
    sel.appendChild(pyr_option('', '— None —'));
    hint.className = 'pyr-hint';

    let mine = [];
    if (pyr_slState === 'loading')      hint.textContent = 'Loading subsidiary ledgers…';
    else if (pyr_slState === 'error')   { hint.textContent = 'Subsidiary ledgers unavailable: ' + pyr_slError; hint.classList.add('bad'); }
    else if (!acct)                     hint.textContent = 'Pick an account first.';
    else {
        mine = pyr_slMasters.filter(s => s.account_code === acct);
        hint.textContent = mine.length
            ? `Optional. ${mine.length} subsidiary ledger${mine.length > 1 ? 's' : ''} for this account.`
            : 'This account has no subsidiary ledger.';
    }
    mine.forEach(s => sel.appendChild(pyr_option(s.sl_code, `${s.sl_code} — ${s.sl_name}`)));
    sel.value = mine.some(s => s.sl_code === keep) ? keep : '';
    sel.disabled = !mine.length;
    pyr_onSlChange();
}

/* SL Detail options belong to the chosen SL Code. */
async function pyr_onSlChange() {
    const sl = $pyr('pyr-sl-code').value;
    const sel = $pyr('pyr-sl-detail'), hint = $pyr('pyr-sl-detail-hint');
    const req = ++pyr_slReq;
    sel.textContent = '';
    sel.appendChild(pyr_option('', '— None —'));
    sel.disabled = true;
    hint.className = 'pyr-hint';
    if (!sl) { hint.textContent = 'Optional. Choose an SL Code first.'; return; }

    hint.textContent = 'Loading details…';
    try {
        const res  = await fetch(`${PYR_SL_API}/${encodeURIComponent(sl)}/details`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load SL details.');
        if (req !== pyr_slReq) return;                       // the user picked another SL meanwhile
        const rows = json.data ?? [];
        rows.forEach(d => sel.appendChild(pyr_option(String(d.sl_detail_id), `${d.detail_code} — ${d.detail_name}`)));
        sel.disabled = !rows.length;
        hint.textContent = rows.length ? 'Optional. A detail within this SL Code.' : 'This SL Code has no details.';
    } catch (e) {
        if (req !== pyr_slReq) return;
        hint.textContent = 'SL details unavailable: ' + e.message;
        hint.classList.add('bad');
    }
}

/* Tells the user which side the chosen category posts to. */
function pyr_syncSide() {
    const side = PYR_SIDE[$pyr('pyr-category').value];
    $pyr('pyr-side-hint').textContent = side
        ? `Posts to its account as a ${side}.`
        : 'Decides how the item posts to its account.';
}

/* ── Modal ─────────────────────────────────────────────────── */
function pyr_openModal() {
    pyr_hideErr();
    $pyr('pyr-form').reset();
    $pyr('pyr-status').value = 'ACTIVE';
    if (pyr_coaState === 'error') pyr_loadAccounts();      // retry a failed load
    if (pyr_slState === 'error') pyr_loadSl();
    pyr_syncAccount();
    pyr_syncSide();
    pyr_fillSl(true);
    $pyr('pyr-overlay').classList.add('open');
}

function pyr_closeModal() {
    $pyr('pyr-overlay').classList.remove('open');
    $pyr('pyr-form').reset();
    pyr_syncAccount();
    pyr_syncSide();
    pyr_fillSl(true);
    pyr_hideErr();
}

function pyr_showErr(msg) {
    const el = $pyr('pyr-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function pyr_hideErr() {
    $pyr('pyr-err').style.display = 'none';
}

function pyr_save(ev) {
    ev.preventDefault();
    pyr_hideErr();

    const v = {};
    for (const [key, inputId] of Object.entries(PYR_FIELDS)) {
        v[key] = $pyr(inputId).value.trim();
    }
    v.item_code = v.item_code.toUpperCase();

    if (!v.item_code) { pyr_showErr('Item code is required.'); return; }
    if (!v.item_name) { pyr_showErr('Item name is required.'); return; }
    if (!v.category)  { pyr_showErr('Category is required.'); return; }
    if (v.account_code) {
        if (pyr_coaState !== 'ready') { pyr_showErr('The chart of accounts is not loaded, so the account code cannot be checked.'); return; }
        if (!pyr_accounts.has(v.account_code)) { pyr_showErr('Account code is not a postable account in the Chart of Accounts.'); return; }
    }
    if (v.sl_code) {
        if (!v.account_code) { pyr_showErr('Pick an account before choosing an SL Code.'); return; }
        if (!pyr_slMasters.some(s => s.sl_code === v.sl_code && s.account_code === v.account_code)) {
            pyr_showErr('SL Code does not belong to this account.'); return;
        }
    }
    if (v.sl_detail_id && !v.sl_code) { pyr_showErr('Choose an SL Code before an SL Detail.'); return; }

    pyr_toast('Item looks good — preview only, not saved (no payroll database yet).', 'info');
    pyr_closeModal();
}

/* ── Helpers ───────────────────────────────────────────────── */
function pyr_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
    let wrap = document.getElementById('nha-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'nha-toast-wrap';
        wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(wrap);
    }
    const color = colors[type] || colors.info;
    const t = document.createElement('div');
    t.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fff;border-left:4px solid ${color};border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);font-size:13px;color:#1e293b;min-width:260px;max-width:340px;pointer-events:auto;`;
    const span = document.createElement('span');
    span.style.flex = '1';
    span.textContent = msg;
    const close = document.createElement('button');
    close.innerHTML = '&times;';
    close.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
    close.onclick = () => t.remove();
    t.append(span, close);
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
