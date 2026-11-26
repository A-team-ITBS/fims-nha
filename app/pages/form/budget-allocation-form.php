<?php

// Must stay: a file included from views/BudgetAllocationForm.php does NOT inherit that
// file's namespace, so without this line Config(), CurrentUserID() and GetUrl() below
// resolve in the global namespace and fatal with "Call to undefined function Config()".
namespace PHPMaker2024\FIMS;

// Initialize variables
$allocationId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$page_title = $allocationId ? 'Edit Budget Allocation Form' : 'Certificate of Budget Allocation (CBA) Form';
$current_date = date('Y-m-d');
$user_id = CurrentUserID();

// The requesting office of a memo request is the logged-in user's own Responsibility Center: uac.users.rc_id
// first (that column only), then the RC itself in fims.responsibility_code (its name and office type are
// what the RFA / LAA print preview shows). The API stores the same rc_id on the record, from the verified
// user (BudgetAllocationService::create), so the form never sends it.
$requestingRc = ['rc_id' => '', 'rc_code' => '', 'rc_name' => '', 'office_type' => ''];
try {
    $userRcId = trim((string) ExecuteScalar('SELECT rc_id FROM uac.users WHERE user_id = ' . (int) $user_id, 'DB'));
    if ($userRcId !== '') {
        $rcRow = ExecuteRow(
            'SELECT rc_id, rc_code, rc_name, office_type FROM fims.responsibility_code WHERE rc_id = ' . QuotedValue($userRcId, DataType::STRING, 'DB'),
            'DB'
        );
        if ($rcRow) {
            $requestingRc = [
                'rc_id'       => (string) $rcRow['rc_id'],
                'rc_code'     => (string) ($rcRow['rc_code'] ?? ''),
                'rc_name'     => (string) ($rcRow['rc_name'] ?? ''),
                'office_type' => (string) ($rcRow['office_type'] ?? ''),
            ];
        }
    }
} catch (\Throwable $e) {
    // can't tell which RC this user belongs to: the print preview just has no requesting office, never an error page
}

// The CBA, RFA and LAA forms are printed by the Financial Management Department and by administrators: the logged-in user's RC
// (uac.users.rc_id joined to fims.responsibility_code, $requestingRc above) must be FMD — recognised by its rc_code, NOT its
// rc_id (the id's format differs between datasets: "013" vs "RC-013"), mirrors GlobalService::CENTRAL_REVIEW_RC_CODE — or
// the user is a superadmin (level -1), the same people as memo-req-page.php's $_mrq_isAdmin: the built-in administrator
// (user_id -1, no uac.users row) or a uac.users row whose user_level_id lists -1 ("-1,3"). No RC / a failed lookup is "not FMD".
// Hiding the Print Preview button of a CBA / RFA / LAA is cosmetic: POST /print/{cba,rfa,laa} refuse everyone else (routes/print.php).
$cbaIsAdmin = (int) $user_id === -1;
try {
    foreach (explode(',', (string) ExecuteScalar('SELECT user_level_id FROM uac.users WHERE user_id = ' . (int) $user_id, 'DB')) as $cbaLevel) {
        if (trim($cbaLevel) === '-1') {
            $cbaIsAdmin = true;
        }
    }
} catch (\Throwable $e) {
    // can't read the user's level: not an administrator, never an error page
}
$cbaCanPrintForm = $cbaIsAdmin || $requestingRc['rc_code'] === '35-074-0000000-032';

// Generate CBA Number (placeholder — regenerated client-side unless editing)
$cba_number = 'CBA#'.date('Y-m-d').'-'.str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

// FIMS's own API (/api → app/routes/budget_allocation.php). API.FMD_URL is the separate
// FMD application's API and is not a FIMS config key.
$apiBase = Config('API.FIMS_URL');

// Rendering mode. The including page sets $cbaAsModal = true before include() (see
// app/pages/memo-req-page.php) to get the form inside an overlay modal that it opens with
// window.cbaModal.open() (a new request) or window.cbaModal.open(id) (edit that record). Left unset, the form renders as a normal page, which is what
// views/BudgetAllocationForm.php (the "Memo Request Form" menu item) relies on.
$cbaAsModal = !empty($cbaAsModal);

// Which Allocation Types this form may create / edit: the including page sets $cbaAllowedTypes (memo-req-page.php: ['CBA'] or
// ['RFA', 'LAA'], from the $alloc_type of its wrapper). One type -> it is preselected and locked; several -> only those are
// offered. Left unset (the stand-alone "Memo Request Form" page) every type stays available, as before.
$cbaAllowedTypes = array_values(array_intersect(['CBA', 'RFA', 'LAA', 'CFA'], array_map('strval', (array) ($cbaAllowedTypes ?? []))));

?>


<?php if ($cbaAsModal): ?>
<style>
	/* SAFETY NET — the essential overlay / modal rules of _shared/css/nha-fims.css, repeated here on purpose.
	   What makes this form a modal (hidden until opened, fixed over the page, centred) used to live ONLY in that
	   shared stylesheet. On a computer where it is blocked, 404s (the app in a sub-folder) or is an older copy
	   cached by the browser, the whole form was drawn INLINE in the page — about 2,500px of it — and never became
	   a modal. These rules are deliberately LOWER in specificity than the shared ones (no .nha-root prefix), so
	   whenever the shared stylesheet is present it still wins and nothing looks different. */
	.nha-overlay {
		display: none;
		position: fixed;
		inset: 0;
		z-index: 9050;
		box-sizing: border-box;
		padding: 16px;
		align-items: center;
		justify-content: center;
		background: rgba(15, 32, 69, .55);
	}

	.nha-overlay.open {
		display: flex;
	}

	.nha-modal {
		display: flex;
		flex-direction: column;
		max-height: 90vh;
		overflow: hidden;
		background: var(--afims-surface, #FFFFFF);
		border-radius: 14px;
		box-shadow: 0 24px 64px rgba(0, 0, 0, .22);
	}


	.nha-modal-hdr {
		display: flex;
		flex-shrink: 0;
		align-items: center;
		justify-content: space-between;
		padding: 16px 20px;
		background: var(--afims-heading-fill, #000E3E);
	}

	.nha-modal-title {
		margin: 0;
		font-size: 14px;
		font-weight: 700;
		color: var(--afims-pure-white, #FFFFFF);
	}

	.nha-modal-close {
		width: 28px;
		height: 28px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 18px;
		line-height: 1;
		color: var(--afims-pure-white, #FFFFFF);
		cursor: pointer;
		background: rgba(255, 255, 255, .15);
		border: 1px solid rgba(255, 255, 255, .25);
		border-radius: 6px;
	}

	.nha-modal-body {
		flex: 1;
		padding: 20px;
		overflow-y: auto;
	}

	/* Modal mode - the form lives inside .nha-modal-body. The base layer below was written
	   for a full page, so drop its page-level width/padding. */
	.nha-root .cba-modal { position: relative; }
	.nha-root .cba-modal .nha-modal-body { padding: 18px 22px; }
	.nha-root .cba-modal .cba-form-container { max-width: none; margin: 0; padding: 0; }
</style>

<!-- The wrapper makes the .nha-overlay / .nha-modal rules apply wherever this form is included
     (they are scoped to .nha-root); padding/background are neutralised so it takes no space. -->
<div class="nha-root" style="padding:0;background:none;">
<div id="cba-overlay" class="nha-overlay">
	<div class="nha-modal cba-modal u-modal-xl">
		<div class="nha-modal-hdr">
			<h3 class="nha-modal-title" id="cba-modal-title">New Memo Request</h3>
			<button type="button" class="nha-modal-close" id="cba-modal-close" onclick="cbaCancel()" aria-label="Close">&times;</button>
		</div>
		<div class="nha-modal-body" id="cba-modal-body">
<?php endif; ?>
<div class="cba-form-container">

	<style>
		/* ══════════════════════════════════════════════════════════════════════════
		   BASE LAYER — layout, fields, buttons, cards
		   This form was written for the FMD app's stylesheet, which FIMS does not
		   load, so none of these classes (.form-row, .form-section, .form-input,
		   .error-box …) had any rules. Everything is scoped under .cba-form-container
		   so it cannot leak into the rest of the app, and uses the shared --afims-*
		   tokens so it follows the light / dark theme like the other FIMS pages.
		   The widget-specific rules (combobox list, budget lines, document rows,
		   Print button) live in the <style> blocks further down.
		══════════════════════════════════════════════════════════════════════════ */
		.cba-form-container {
			font-family: 'IBM Plex Sans', sans-serif;
			/* The app forces input / select / textarea / button text to
			   calc(1em * var(--input-scale)) !important, i.e. "whatever the parent
			   is, times the user's scale". So the size of every control in this form
			   is set HERE, once — a font-size on the controls themselves is ignored. */
			font-size: 14px;
			max-width: 1040px;
			margin: 0 auto;
			padding: 4px 0 40px;
			color: var(--afims-text, #1A1B21);
		}

		.cba-form-container *,
		.cba-form-container *::before,
		.cba-form-container *::after {
			box-sizing: border-box;
		}

		/* ── Number card ─────────────────────────────────────────────────────── */
		.cba-form-container .form-header {
			margin-bottom: 18px;
		}

		.cba-form-container .cba-number-card {
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 16px;
			padding: 16px 22px;
			background: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-left: 4px solid var(--afims-primary, #0035A9);
			border-radius: 12px;
			box-shadow: 0 1px 2px rgba(15, 32, 69, .05);
		}

		.cba-form-container .cba-number-label {
			margin: 0 0 4px;
			font-size: 10.5px;
			font-weight: 700;
			letter-spacing: .08em;
			text-transform: uppercase;
			color: var(--afims-text-muted, #62657E);
		}

		.cba-form-container .cba-number-value {
			margin: 0;
			font-family: 'IBM Plex Mono', monospace;
			font-size: 22px;
			font-weight: 600;
			letter-spacing: -.01em;
			color: var(--afims-heading, #000E3E);
		}

		/* ── Section cards ───────────────────────────────────────────────────── */
		.cba-form-container .form-section {
			margin-bottom: 18px;
			padding: 22px 24px 24px;
			background: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 12px;
			box-shadow: 0 1px 2px rgba(15, 32, 69, .05);
		}

		.cba-form-container .section-header {
			display: flex;
			align-items: center;
			gap: 10px;
			margin: 0 0 18px;
			padding-bottom: 12px;
			border-bottom: 1px solid var(--afims-border, #E4ECF2);
			font-family: inherit;
			font-size: 15px;
			font-weight: 700;
			line-height: 1.3;
			color: var(--afims-heading, #000E3E);
		}

		.cba-form-container .section-header::before {
			content: '';
			flex-shrink: 0;
			width: 4px;
			height: 16px;
			border-radius: 2px;
			background: var(--afims-primary, #0035A9);
		}

		/* ── Grid ────────────────────────────────────────────────────────────── */
		.cba-form-container .form-row {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 18px 20px;
			align-items: start;
			margin-bottom: 18px;
		}

		.cba-form-container .form-row.full-width {
			grid-template-columns: minmax(0, 1fr);
		}

		/* three comboboxes side by side; auto-fit drops to two / one column as the room shrinks */
		.cba-form-container .form-row.cols-3 {
			grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
		}

		/* vendor row: Vendor Name | Vendor Code | Vendor Type, then Address (and the hint) on rows of their own */
		.cba-form-container .form-row.cols-vendor {
			grid-template-columns: repeat(3, minmax(0, 1fr));
		}

		@media (max-width: 960px) {
			.cba-form-container .form-row.cols-vendor {
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}
		}

		@media (max-width: 760px) {
			.cba-form-container .form-row.cols-vendor {
				grid-template-columns: minmax(0, 1fr);
			}
		}

		/* Budget Allocation Amount line: COB | Requested Amount | Cash Requirements | Proposed Amount — COB gets
		   the most room; Proposed Amount is read-only (filled from the picked COB), so it doesn't need as much */
		.cba-form-container .form-row.cols-baa {
			grid-template-columns: 2fr 1fr 1fr 1fr;
		}

		/* Cash Requirements hidden (CBA, or a COE document) — COB | Requested Amount | Proposed Amount left */
		.cba-form-container .form-row.cols-baa.baa-no-cash {
			grid-template-columns: 2fr 1fr 1fr;
		}

		@media (max-width: 760px) {
			.cba-form-container .form-row.cols-baa,
			.cba-form-container .form-row.cols-baa.baa-no-cash {
				grid-template-columns: minmax(0, 1fr);
			}
		}

		/* a field that takes the whole row of a multi-column .form-row (the vendor's Address) */
		.cba-form-container .form-row > .form-group-wide {
			grid-column: 1 / -1;
		}

		/* option list of the Program / Sub-Program / Project comboboxes (JS toggles display) */
		.cba-form-container .pc-dropdown {
			display: none;
			position: absolute;
			top: 100%;
			left: 0;
			right: 0;
			z-index: 9999;
			max-height: 220px;
			overflow-y: auto;
			background: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-top: none;
			border-radius: 0 0 6px 6px;
			box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
		}

		.cba-form-container .form-section > .form-row:last-child {
			margin-bottom: 0;
		}

		.cba-form-container .form-group {
			display: flex;
			flex-direction: column;
			gap: 6px;
			min-width: 0;
			position: relative;
		}

		/* ── Labels, hints, errors ───────────────────────────────────────────── */
		/* custom.css sets `body.afims-ui .content-wrapper .form-label` (14px / 500 / its own
		   colour); match its specificity so the label hierarchy below actually applies. */
		body.afims-ui .content-wrapper .cba-form-container .form-label {
			display: block;
			margin: 0;
			font-size: 12px;
			font-weight: 600;
			letter-spacing: .04em;
			text-transform: uppercase;
			color: var(--afims-text, #1A1B21);
		}

		body.afims-ui .content-wrapper .cba-form-container .has-error > .form-label {
			color: var(--afims-danger, #DC3545);
		}

		.cba-form-container .required {
			margin-left: 2px;
			color: var(--afims-danger, #DC3545);
		}

		.cba-form-container .input-hint {
			margin: 0;
			font-size: 11.5px;
			line-height: 1.5;
			color: var(--afims-text-muted, #62657E);
		}

		/* Program / Sub-Program / Project: text typed instead of picked is a NEW one, created when the form is saved */
		.cba-form-container .input-hint.pc-hint-new {
			color: var(--afims-primary, #0035A9);
			font-weight: 600;
		}

		.cba-form-container .pc-dropdown .pc-new {
			padding: 10px 14px;
			font-size: 13px;
			color: var(--afims-primary, #0035A9);
			background: var(--afims-surface-subtle, #F4F7FA);
		}

		/* Hidden until JS adds .visible — the text is static markup, so without
		   this rule every "Failed to load…" message showed on a healthy page. */
		.cba-form-container .error-box {
			display: none;
			margin: 0;
			padding: 8px 12px;
			font-size: 12px;
			line-height: 1.5;
			color: var(--afims-danger, #DC3545);
			background: var(--afims-danger-soft, #FAE1E3);
			border: 1px solid color-mix(in srgb, var(--afims-danger, #DC3545) 25%, transparent);
			border-radius: 8px;
		}

		.cba-form-container .error-box.visible {
			display: block;
		}

		.cba-form-container .form-section > .error-box.visible {
			margin-bottom: 12px;
		}

		/* ── Fields ──────────────────────────────────────────────────────────── */
		.cba-form-container .form-input,
		.cba-form-container .form-select,
		.cba-form-container .form-textarea {
			display: block;
			width: 100%;
			min-height: 40px;
			padding: 9px 12px;
			font-family: 'IBM Plex Sans', sans-serif;
			font-weight: 400;
			line-height: 1.4;
			color: var(--afims-text, #1A1B21);
			background-color: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 8px;
			outline: none;
			transition: border-color .15s, box-shadow .15s, background-color .15s;
		}

		.cba-form-container .form-select {
			appearance: none;
			-webkit-appearance: none;
			padding-right: 34px;
			cursor: pointer;
			background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%237a7d92' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
			background-repeat: no-repeat;
			background-position: right 12px center;
			background-size: 14px;
		}

		.cba-form-container .form-textarea {
			min-height: 104px;
			line-height: 1.55;
			resize: vertical;
		}

		.cba-form-container .form-input::placeholder,
		.cba-form-container .form-textarea::placeholder {
			color: var(--afims-text-disabled, #A9AAB8);
		}

		.cba-form-container .form-input:hover:not(:disabled):not(:focus),
		.cba-form-container .form-select:hover:not(:disabled):not(:focus),
		.cba-form-container .form-textarea:hover:not(:disabled):not(:focus) {
			border-color: color-mix(in srgb, var(--afims-heading-fill, #000E3E) 35%, var(--afims-border, #E4ECF2));
		}

		.cba-form-container .form-input:focus,
		.cba-form-container .form-select:focus,
		.cba-form-container .form-textarea:focus {
			border-color: var(--afims-heading-fill, #000E3E);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-heading-fill, #000E3E) 12%, transparent);
		}

		.cba-form-container .form-input:disabled,
		.cba-form-container .form-select:disabled,
		.cba-form-container .form-textarea:disabled {
			color: var(--afims-text-disabled, #A9AAB8);
			background-color: var(--afims-surface-subtle, #F4F7FA);
			cursor: not-allowed;
		}

		/* read-only fields (the vendor's address): shown, filled in by script, not typed */
		.cba-form-container .form-input[readonly],
		.cba-form-container .form-textarea[readonly] {
			background-color: var(--afims-surface-subtle, #F4F7FA);
			cursor: default;
		}

		.cba-form-container .form-textarea.vendor-address {
			min-height: 64px;
			resize: none;
		}

		/* validation state — classes toggled by validateField() */
		.cba-form-container .form-input.error,
		.cba-form-container .form-select.error,
		.cba-form-container .form-textarea.error {
			border-color: var(--afims-danger, #DC3545);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-danger, #DC3545) 12%, transparent);
		}

		.cba-form-container .form-input.success,
		.cba-form-container .form-select.success,
		.cba-form-container .form-textarea.success {
			border-color: color-mix(in srgb, var(--afims-success, #11A752) 60%, var(--afims-border, #E4ECF2));
		}

		/* custom.css restyles every .form-select under .content-wrapper with higher
		   specificity (36px tall, stronger border, blue focus ring), which left the
		   selects shorter than the text inputs beside them. This block matches that
		   specificity so selects and inputs look identical. The compact .baa-cf-sel
		   filter selects keep their own styling. */
		body.afims-ui .content-wrapper .cba-form-container .form-select:not(.baa-cf-sel) {
			min-height: 40px;
			padding: 9px 34px 9px 12px;
			border-color: var(--afims-border, #E4ECF2);
			border-radius: 8px;
			box-shadow: none;
		}

		/* the dark theme resets the select background, which removes the chevron */
		html.afims-dark[data-bs-theme="dark"] .cba-form-container .form-select:not(.baa-cf-sel) {
			background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23b5b8cc' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
			background-repeat: no-repeat;
			background-position: right 12px center;
			background-size: 14px;
		}

		body.afims-ui .content-wrapper .cba-form-container .form-select:not(.baa-cf-sel):hover:not(:disabled):not(:focus) {
			border-color: color-mix(in srgb, var(--afims-heading-fill, #000E3E) 35%, var(--afims-border, #E4ECF2));
		}

		body.afims-ui .content-wrapper .cba-form-container .form-select:not(.baa-cf-sel):focus {
			border-color: var(--afims-heading-fill, #000E3E);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-heading-fill, #000E3E) 12%, transparent);
		}

		body.afims-ui .content-wrapper .cba-form-container .form-select:not(.baa-cf-sel):disabled {
			color: var(--afims-text-disabled, #A9AAB8);
			background-color: var(--afims-surface-subtle, #F4F7FA);
		}

		body.afims-ui .content-wrapper .cba-form-container .form-select.error:not(.baa-cf-sel) {
			border-color: var(--afims-danger, #DC3545);
			box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-danger, #DC3545) 12%, transparent);
		}

		body.afims-ui .content-wrapper .cba-form-container .form-select.success:not(.baa-cf-sel) {
			border-color: color-mix(in srgb, var(--afims-success, #11A752) 60%, var(--afims-border, #E4ECF2));
		}

		/* peso prefix inside amount inputs (budget lines) */
		.cba-form-container .form-group.has-prefix .input-prefix {
			position: absolute;
			left: 13px;
			bottom: 0;
			z-index: 1;
			display: flex;
			align-items: center;
			height: 40px;
			font-size: 13px;
			font-weight: 600;
			color: var(--afims-text-muted, #62657E);
			pointer-events: none;
		}

		.cba-form-container .form-group.has-prefix .form-input {
			padding-left: 30px;
		}

		.cba-form-container .amt-input {
			font-weight: 500;
			font-variant-numeric: tabular-nums;
			text-align: right;
		}

		/* async-loaded lookups: spinner while .is-loading (toggled by setLoading()) */
		.cba-form-container .select-loading-wrapper {
			position: relative;
		}

		.cba-form-container .select-loading-wrapper.is-loading::after {
			content: '';
			position: absolute;
			top: 50%;
			right: 34px;
			z-index: 2;
			width: 15px;
			height: 15px;
			margin-top: -7.5px;
			border: 2px solid var(--afims-border, #E4ECF2);
			border-top-color: var(--afims-heading-fill, #000E3E);
			border-radius: 50%;
			animation: cba-spin .75s linear infinite;
			pointer-events: none;
		}

		.cba-form-container .select-loading-wrapper:not(:has(select)).is-loading::after {
			right: 12px;
		}

		/* checkbox rows ("Is this for bidding?") */
		.cba-form-container input[type="checkbox"] {
			accent-color: var(--afims-heading-fill, #000E3E);
		}

		body.afims-ui .content-wrapper .cba-form-container .form-label:has(> input[type="checkbox"]) {
			font-size: 14px;
			font-weight: 600;
			letter-spacing: 0;
			text-transform: none;
		}

		.cba-form-container .form-group:has(#is_bidding) {
			padding: 12px 14px;
			background: var(--afims-surface-subtle, #F4F7FA);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 10px;
		}

		/* ── Budget allocation lines: each line becomes a tinted card ────────── */
		.cba-form-container .baa-line {
			margin-bottom: 14px;
			padding: 16px 48px 16px 16px;
			background: var(--afims-surface-subtle, #F4F7FA);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 10px;
		}

		.cba-form-container .baa-remove-line {
			top: 12px;
			right: 12px;
		}

		/* ── Buttons ─────────────────────────────────────────────────────────── */
		/* Size / padding / radius come from custom.css (`body.afims-ui .content-wrapper .btn`:
		   36px tall) so these buttons match every other FIMS button; only the font and
		   the 1px border are set here. */
		.cba-form-container .btn {
			font-family: 'IBM Plex Sans', sans-serif;
			font-weight: 600;
			cursor: pointer;
			border: 1px solid transparent;
		}

		.cba-form-container .btn:disabled {
			opacity: .55;
			cursor: not-allowed;
		}

		.cba-form-container .btn-icon {
			flex-shrink: 0;
			width: 16px;
			height: 16px;
		}

		.cba-form-container .btn-primary {
			color: var(--afims-pure-white, #FFFFFF);
			background: var(--afims-heading-fill, #000E3E);
			border-color: var(--afims-heading-fill, #000E3E);
		}

		.cba-form-container .btn-primary:hover:not(:disabled) {
			opacity: .9;
		}

		/* Dark mode: custom.css paints every .btn-primary with `var(--navy) !important`, and inside the
		   .nha-root modal nha-fims.css redefines --navy as the (light) heading TEXT colour — so the
		   Submit button became white text on a near-white fill. Keep the navy fill used in light mode. */
		html.afims-dark[data-bs-theme="dark"] .cba-form-container button.btn.btn-primary,
		html.afims-dark[data-bs-theme="dark"] .cba-form-container button.btn.btn-primary:disabled {
			color: var(--afims-pure-white, #FFFFFF) !important;
			background-color: var(--afims-heading-fill, #22305E) !important;
			border-color: var(--afims-heading-fill, #22305E) !important;
		}

		html.afims-dark[data-bs-theme="dark"] .cba-form-container button.btn.btn-primary:hover:not(:disabled),
		html.afims-dark[data-bs-theme="dark"] .cba-form-container button.btn.btn-primary:focus:not(:disabled) {
			color: var(--afims-pure-white, #FFFFFF) !important;
			background-color: var(--afims-primary, #3861BC) !important;
			border-color: var(--afims-primary, #3861BC) !important;
		}

		.cba-form-container .btn-secondary {
			color: var(--afims-text, #1A1B21);
			background: var(--afims-surface, #FFFFFF);
			border-color: var(--afims-border, #E4ECF2);
		}

		.cba-form-container .btn-secondary:hover:not(:disabled) {
			background: var(--afims-surface-subtle, #F4F7FA);
		}

		.cba-form-container .form-actions {
			display: flex;
			align-items: center;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 12px;
			margin-bottom: 18px;
			padding: 16px 20px;
			background: var(--afims-surface, #FFFFFF);
			border: 1px solid var(--afims-border, #E4ECF2);
			border-radius: 12px;
			box-shadow: 0 1px 2px rgba(15, 32, 69, .05);
		}

		.cba-form-container .btn-group {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 10px;
		}

		/* custom.css forces a 6px radius on .btn-group children with !important, which
		   made Save / Print / Submit differ from Cancel. Use the app's own radius. */
		.cba-form-container .btn-group > .btn:not(#afims-btn) {
			border-radius: var(--ui-radius, 8px) !important;
		}

		/* ── Reminders card ──────────────────────────────────────────────────── */
		.cba-form-container .info-card {
			padding: 18px 22px;
			background: color-mix(in srgb, var(--afims-primary, #0035A9) 6%, var(--afims-surface, #FFFFFF));
			border: 1px solid color-mix(in srgb, var(--afims-primary, #0035A9) 18%, var(--afims-border, #E4ECF2));
			border-radius: 12px;
		}

		.cba-form-container .info-card-header {
			display: flex;
			align-items: flex-start;
			gap: 14px;
		}

		.cba-form-container .info-card-icon {
			flex-shrink: 0;
			width: 22px;
			height: 22px;
			margin-top: 1px;
			color: var(--afims-primary, #0035A9);
		}

		.cba-form-container .info-card-title {
			margin: 0 0 6px;
			font-size: 13px;
			font-weight: 700;
			color: var(--afims-heading, #000E3E);
		}

		.cba-form-container .info-list {
			margin: 0;
			padding-left: 18px;
			font-size: 12.5px;
			line-height: 1.75;
			color: var(--afims-text-muted, #62657E);
		}

		/* ── Narrow screens ──────────────────────────────────────────────────── */
		@media (max-width: 760px) {
			.cba-form-container .form-row {
				grid-template-columns: minmax(0, 1fr);
			}

			.cba-form-container .form-section {
				padding: 18px 16px 20px;
			}

			.cba-form-container .baa-line {
				padding-right: 44px;
			}

			.cba-form-container .form-actions,
			.cba-form-container .btn-group {
				flex-direction: column;
				align-items: stretch;
			}
		}
	</style>

	<style>
		/* Spin animation of the small per-field lookup spinners (.select-loading-wrapper.is-loading above).
		   The full-page "loading" is the app's shared PagePreloader (app/preloader.php), not drawn here. */
		@keyframes cba-spin {
			to {
				transform: rotate(360deg);
			}
		}
	</style>

	<!-- Form Header: the CBA number / date card. Hidden while creating — numbering is not in use yet, so
	     nothing is shown or saved for it (see buildPayload). Still shown when editing a saved record. -->
	<div class="form-header"<?php echo $allocationId ? '' : ' style="display:none;"'; ?>>
		<div class="cba-number-card">
			<div>
				<p class="cba-number-label" id="cba_number_label">CBA Number</p>
				<p class="cba-number-value" id="cba_number_display">
					<?php echo htmlspecialchars($cba_number); ?>
				</p>
			</div>
			<div style="text-align: right;">
				<p class="cba-number-label">Date Created</p>
				<p style="font-size: 14px; font-weight: 600; color: var(--afims-heading, #000E3E); margin: 0;">
					<?php echo date('F d, Y'); ?>
				</p>
			</div>
		</div>
	</div>

	<form id="cba_form" method="POST">

		<!-- ── Allocation Type (moved to the top: it decides how much of the rest of the form shows — see
		     cbaApplyAllocationTypeVisibility()) ──────────────────────── -->
		<div class="form-section">
			<h2 class="section-header">Allocation Type</h2>

			<div class="form-row">
				<!-- Always full-width: Parent CBA selection now lives inside each of Funding Allocations' own
				     blocks (#project_blocks_section) instead of sharing this row — see cbaUsesBlockProjectUI()
				     and blockCbaSetupSearch(). -->
				<div class="form-group form-group-wide" id="allocation_type_group">
					<label for="allocation_type" class="form-label">
						Allocation Type <span class="required">*</span>
					</label>
					<select id="allocation_type" name="allocation_type" class="form-select" required>
						<option value="">Select allocation type</option>
						<option value="CBA">CBA - Certificate of Budget Allocation</option>
						<option value="RFA">RFA - Request for Fund Allotment (&lt;&#8369;50M)</option>
						<option value="LAA">LAA - Letter of Advice of Allotment (&ge;&#8369;50M)</option>
						<option value="CFA">CFA - Certified Funds Available</option>
					</select>
				</div>
			</div>

			<!-- Category — hidden for CBA / RFA / LAA (cbaApplyAllocationTypeVisibility() toggles #category_row
			     and clears any stale pick); still shown for CFA. To bring it back everywhere, drop that toggle
			     and restore `required` on #cat_search below. -->
			<div class="form-row" id="category_row">
				<!-- Category → GET /budget_category/all (searchable combobox) -->
				<div class="form-group form-group-wide">
					<label for="cat_search" class="form-label">Category <span class="required">*</span></label>
					<div class="select-loading-wrapper is-loading" id="category_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="cat_search" class="form-input" placeholder="Search category&hellip;"
								autocomplete="off" disabled>
							<div id="cat_dropdown" class="pc-dropdown"></div>
						</div>
						<input type="hidden" id="category_id" name="category_id" value="">
					</div>
					<div class="error-box" id="category_error">Failed to load categories. Please refresh the page.</div>
				</div>
			</div>
		</div>

		<!-- ── Purpose / Description — its own card, right after Allocation Type (moved out of the
		     Program/Project card below: it applies to the whole request, not to any one project block). ── -->
		<div class="form-section">
			<h2 class="section-header">Purpose / Description</h2>

			<div class="form-row full-width">
				<div class="form-group">
					<textarea id="project_description" name="project_description" class="form-textarea"
						placeholder="Enter the purpose or description of this budget allocation..."></textarea>
					<p class="input-hint">Provide details about the purpose and scope of this budget allocation</p>
				</div>
			</div>
		</div>

		<!-- ── Program and Project ────────────────────────────────────── -->
		<div class="form-section" id="program_project_section">
			<h2 class="section-header" id="program_project_section_title">Program, Sub-Program and Project Details</h2>

			<!-- CFA (and "no type chosen yet") — the single Program / Sub-Program / Project chain, unchanged.
			     Hidden for CBA / RFA / LAA, which use #project_blocks_section below instead — see
			     cbaApplyAllocationTypeVisibility(). -->
			<div id="single_project_block">
			<div class="form-row cols-3" id="program_project_row">
				<!-- Program → GET /program/all (searchable combobox). Hidden for CBA — see
				     cbaApplyAllocationTypeVisibility(): the chosen Project carries its own Program / Sub-Program. -->
				<div class="form-group" id="program_group">
					<label for="prog_search" class="form-label">Program</label>
					<div class="select-loading-wrapper is-loading" id="program_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="prog_search" class="form-input" placeholder="Search program&hellip;"
								autocomplete="off" disabled>
							<div id="prog_dropdown" class="pc-dropdown"></div>
						</div>
						<input type="hidden" id="program_code" name="program_code" value="">
					</div>
					<div class="error-box" id="program_error">Failed to load programs. Please refresh the page.</div>
					<p class="input-hint" id="program_hint">Pick an existing program, or type a new name to create one.</p>
				</div>

				<!-- Sub-Program → GET /sub_program/all?program_code=… (searchable combobox, needs a Program). Hidden for CBA, same as Program above. -->
				<div class="form-group" id="sub_program_group">
					<label for="subprog_search" class="form-label">Sub-Program</label>
					<div class="select-loading-wrapper" id="sub_program_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="subprog_search" class="form-input" placeholder="Select a program first"
								autocomplete="off" disabled>
							<div id="subprog_dropdown" class="pc-dropdown"></div>
						</div>
						<input type="hidden" id="sub_program_code" name="sub_program_code" value="">
					</div>
					<div class="error-box" id="sub_program_error">Failed to load sub-programs. Please select the program again.</div>
					<p class="input-hint" id="sub_program_hint">Select a program first.</p>
				</div>

				<!-- Project → GET /project/all?program_code=…&sub_program_code=… (searchable combobox, needs a Sub-Program).
				     CFA (and "no type chosen yet") only — CBA / RFA / LAA use #project_blocks_section instead. -->
				<div class="form-group" id="project_group">
					<label for="proj_search" class="form-label">Project <span class="required">*</span></label>
					<div class="select-loading-wrapper" id="project_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<!-- required is toggled off in applyProgramProjectVisibilityForDocType() whenever this whole
							     row is hidden (non-CBA + non-Infrastructure category) — a hidden field must never block
							     submit. -->
							<input type="text" id="proj_search" class="form-input" placeholder="Select a sub-program first"
								autocomplete="off" disabled required>
							<div id="proj_dropdown" class="pc-dropdown"></div>
						</div>
						<input type="hidden" id="project_code" name="project_code" value="">
					</div>
					<div class="error-box" id="project_error">Failed to load projects. Please select the sub-program again.</div>
					<p class="input-hint" id="project_hint">Select a sub-program first.</p>
				</div>
			</div>
			</div><!-- /#single_project_block -->

			<!-- CBA / RFA / LAA — one or more project blocks, each with its own COB / Available Amount (or CBA
			     Available Balance, for RFA/LAA) / Requested Amount line(s). Shown instead of
			     #single_project_block, which only CFA (and "no type chosen yet") still uses — see
			     cbaApplyAllocationTypeVisibility(). What each block's own search field picks differs by type
			     (blockFieldNoun() / blockSetupSearch()): CBA searches PROJECTS directly (no Program/Sub-Program
			     to pick first); RFA/LAA instead searches approved CBAs — picking one seeds that block's own
			     Project + COB/account-code lines from that CBA (cbaAutofillBlockFromParent()) and locks them.
			     CBA always gets exactly one block (auto-seeded, "+ Add New CBA" hidden, remove button hidden —
			     see cbaBlocksAreParentDriven()); RFA/LAA start with one block too but may add more, one per
			     additional Parent CBA. -->
			<div id="project_blocks_section" style="display:none;">
				<div id="project_blocks_container"></div>
				<button type="button" class="btn btn-secondary" id="add_project_block_btn" style="margin:4px 0 16px;display:none;"
					onclick="addProjectBlock()">+ Add New CBA</button>
				<!-- RFA/LAA only (cbaBlocksAreParentDriven(), same as the button above) — a COE allocation has no
				     Project/CBA to anchor against, so this block skips the Parent CBA pick entirely and draws
				     directly against a COE account code — see blockIsDirectDraw(). -->
				<button type="button" class="btn btn-secondary" id="add_direct_cob_block_btn" style="margin:4px 0 16px;display:none;"
					onclick="addProjectBlock(true)">+ Add Direct COE Account</button>
			</div>

			<!-- —— Budget Allocation Amount (same card as the Project details above) —————————— -->
			<!-- Hidden for CBA while there's only the one (merged, next to Project) line — see
			     baaApplyHeaderVisibility(): nothing left here needs its own heading. Reappears if an existing
			     record being edited turns out to have more than one saved line. Always hidden in project-blocks
			     mode (CBA / RFA / LAA): each block renders its own COB heading instead — see
			     cbaApplyAllocationTypeVisibility(). -->
			<div id="baa_header">
				<h2 class="section-header">Budget Allocation Amount</h2>
				<p class="input-hint" style="margin-top:-4px;margin-bottom:12px;">
					Choose the COB (its account code) this allocation is drawn from. Only approved budget requests are
					listed, and the list follows the Program, Sub-Program and Project chosen above.
				</p>

				<div class="error-box" id="cob_error">Failed to load COBs. Please refresh the page.</div>
			</div>

			<div id="baa_lines_container"></div>

			<!-- CFA's single chain has no project-block id of its own, so this one static button (instead of
			     a per-block one) covers it — see baaRestoreRemovedLine(null) / updateRestoreButtonVisibility(). -->
			<button type="button" class="btn btn-secondary" id="baa_restore_btn" style="margin:4px 0 16px;display:none;"
				onclick="baaRestoreRemovedLine(null)">&#8634; Restore Removed Account Code</button>

			<div id="baa_totals" class="baa-totals">
				<div class="baa-totals-row">
					<span class="baa-totals-label">Total Requested Amount</span>
					<span class="baa-totals-value" id="baa_total_requested">&#8369;0.00</span>
				</div>
				<div class="baa-totals-row" id="baa_total_allocated_row">
					<span class="baa-totals-label">Total Cash Requirements</span>
					<span class="baa-totals-value" id="baa_total_allocated">&#8369;0.00</span>
				</div>
			</div>

			<!-- Summed totals of the lines (requested_amount / allocated_amount). The itemized lines, each with its COB,
			     are submitted separately as `line_transaction` (jsonb) — see collectBaaLines() / buildPayload(). -->
			<input type="hidden" id="requested_amount_raw" name="requested_amount" value="">
			<input type="hidden" id="allocated_amount_raw" name="allocated_amount" value="">

			<style>
				.baa-line {
					position: relative;
					padding-right: 34px;
					margin-bottom: 16px;
				}

				.baa-remove-line {
					position: absolute;
					top: 4px;
					right: 0;
					width: 24px;
					height: 24px;
					border: 1px solid var(--afims-border, #E4ECF2);
					background: var(--afims-surface, #FFFFFF);
					color: var(--afims-text-disabled, #A9AAB8);
					border-radius: 6px;
					font-size: 16px;
					line-height: 1;
					cursor: pointer;
					transition: background .15s, color .15s, border-color .15s;
				}

				.baa-remove-line:hover {
					background: var(--afims-danger-soft, #FAE1E3);
					color: var(--afims-danger, #DC3545);
					border-color: var(--afims-danger-soft, #FAE1E3);
				}

				/* One independent project block (#project_blocks_container's children) — Project on its own
				   row, then that block's own COB / Available / Requested Amount line(s) below it. */
				.project-block {
					position: relative;
					border: 1px solid var(--afims-border, #E4ECF2);
					border-radius: 8px;
					padding: 16px 44px 4px 16px;
					margin-bottom: 16px;
				}

				.project-block-remove {
					position: absolute;
					top: 12px;
					right: 12px;
					width: 24px;
					height: 24px;
					border: 1px solid var(--afims-border, #E4ECF2);
					background: var(--afims-surface, #FFFFFF);
					color: var(--afims-text-disabled, #A9AAB8);
					border-radius: 6px;
					font-size: 16px;
					line-height: 1;
					cursor: pointer;
					transition: background .15s, color .15s, border-color .15s;
				}

				.project-block-remove:hover {
					background: var(--afims-danger-soft, #FAE1E3);
					color: var(--afims-danger, #DC3545);
					border-color: var(--afims-danger-soft, #FAE1E3);
				}

				.project-block .baa-line {
					margin-bottom: 0;
				}

				.baa-proposed-amount {
					color: var(--afims-text-muted, #62657E);
				}

				/* "Where this COB's balance is held" — a collapsed-by-default drill-down into
				   fims.budget_line_item_earmark for the picked COB (see baaToggleEarmarks()). Spans the full
				   row (same grid-column trick as the old .baa-bv-info), under the COB/Amount fields and
				   above Purpose / Description. */
				.baa-earmarks {
					grid-column: 1 / -1;
					margin-top: 4px;
				}

				.baa-earmarks-toggle {
					display: inline-flex;
					align-items: center;
					gap: 6px;
					padding: 4px 0;
					border: none;
					background: none;
					cursor: pointer;
					font-size: 12px;
					font-weight: 600;
					color: var(--afims-primary, #0035A9);
				}

				.baa-earmarks-toggle:hover {
					text-decoration: underline;
				}

				.baa-earmarks-caret {
					flex-shrink: 0;
					transition: transform .15s;
				}

				.baa-earmarks.open .baa-earmarks-caret {
					transform: rotate(90deg);
				}

				.baa-earmarks-body {
					margin-top: 6px;
					padding: 10px 12px;
					background: var(--afims-surface-subtle, #F4F7FA);
					border: 1px solid var(--afims-border, #E4ECF2);
					border-radius: 8px;
					font-size: 12px;
				}

				.baa-earmarks-row {
					display: flex;
					align-items: baseline;
					justify-content: space-between;
					gap: 10px;
					padding: 4px 0;
				}

				.baa-earmarks-row + .baa-earmarks-row {
					border-top: 1px solid var(--afims-border, #E4ECF2);
				}

				.baa-earmarks-ref {
					font-weight: 600;
					color: var(--afims-heading, #000E3E);
				}

				.baa-earmarks-amt {
					font-family: 'IBM Plex Mono', monospace;
					font-weight: 600;
					white-space: nowrap;
				}

				.baa-earmarks-tag {
					display: inline-block;
					margin-left: 6px;
					padding: 1px 6px;
					border-radius: 4px;
					font-size: 10px;
					font-weight: 700;
					text-transform: uppercase;
				}

				.baa-earmarks-tag.active { background: #FFF8DB; color: #92400E; }
				.baa-earmarks-tag.converted { background: #DCFCE7; color: #166534; }
				.baa-earmarks-tag.released { background: var(--afims-border, #E4ECF2); color: var(--afims-text-muted, #62657E); }

				.baa-totals {
					margin-top: 16px;
					padding: 12px 16px;
					background: var(--afims-surface-subtle, #F4F7FA);
					border: 1px solid var(--afims-border, #E4ECF2);
					border-radius: 8px;
				}

				.baa-totals-row {
					display: flex;
					justify-content: space-between;
					align-items: center;
					padding: 3px 0;
					font-size: 13px;
				}

				.baa-totals-label {
					color: var(--afims-text-muted, #62657E);
					font-weight: 500;
				}

				.baa-totals-value {
					color: var(--afims-heading, #000E3E);
					font-weight: 700;
					font-family: 'IBM Plex Mono', monospace;
				}
			</style>
		</div>

		<!-- ── Vendor ─────────────────────────────────────────────────── -->
		<div class="form-section" id="vendor_section">
			<h2 class="section-header">Vendor Details</h2>

			<div class="form-row cols-vendor" id="vendor_row">
				<!-- Vendor Name → GET /vendors/all?active=1 (dropdown pick only — see vendSetupSearch()); saved as
				     vendor_id. Picking one shows its Code / Type / Address, read-only, below.
				     The group itself is hidden in cbaApplyAllocationTypeVisibility() for CBA (which has no
				     vendor to pick — only its #pr_number_group sibling below applies there) — a hidden field
				     must never block submit, same reasoning as #proj_search, so `required` is toggled off right
				     alongside it. Also toggled off (group kept visible) whenever every Funding Allocation block
				     is a Direct COE Account (no Parent CBA, no project-backed procurement to name a vendor for)
				     — see cbaApplyVendorRequirement(). The "*"/"(Optional)" markers next to the label follow
				     that same toggle. -->
				<div class="form-group" id="vendor_name_group">
					<label for="vendor_search" class="form-label">Vendor Name
						<span class="required" id="vendor_required_mark">*</span>
						<span class="input-hint" id="vendor_optional_hint" style="display:none;">(Optional)</span>
					</label>
					<div class="select-loading-wrapper is-loading" id="vendor_wrapper" style="overflow:visible;">
						<div style="position:relative;overflow:visible;">
							<input type="text" id="vendor_search" class="form-input" placeholder="Search vendor&hellip;"
								maxlength="500" autocomplete="off" disabled required>
							<div id="vendor_dropdown" class="pc-dropdown"></div>
						</div>
						<input type="hidden" id="vendor_id" name="vendor_id" value="">
					</div>
					<div class="error-box" id="vendor_error">Failed to load vendors. Please refresh the page.</div>
				</div>

				<!-- Purchase Request / Purchase Order Number — CBA (and CFA / no type chosen yet) → Purchase
				     Request; RFA / LAA → Purchase Order (see pcrApplyMode()). Only one of the two groups below
				     shows at a time; their Amount counterparts (pr_amount / po_amount) stay in the DOM, hidden,
				     so pcrPayloadFields() / pcrPopulate() keep working unchanged. Neither is ever required —
				     #pr_number_group is #vendor_section's one field CBA still shows (#vendor_name_group hides
				     for it instead, just above). -->
				<div class="form-group" id="pr_number_group">
					<label for="pr_number" class="form-label">Purchase Request Number <span class="input-hint" style="display:inline;">(Optional)</span></label>
					<input type="text" id="pr_number" class="form-input" maxlength="50" autocomplete="off"
						placeholder="Enter the purchase request number">
				</div>
				<div class="form-group" id="po_number_group" style="display:none;">
					<label for="po_number" class="form-label">Purchase Order Number <span class="input-hint" style="display:inline;">(Optional)</span></label>
					<input type="text" id="po_number" class="form-input" maxlength="50" autocomplete="off"
						placeholder="Enter the purchase order number">
				</div>

				<!-- Vendor Code / Type / Address: read-only details of whichever vendor is picked above. -->
				<div class="form-group" id="vendor_code_group" style="display:none;">
					<label for="vendor_code" class="form-label">Vendor Code</label>
					<input type="text" id="vendor_code" class="form-input" readonly tabindex="-1">
				</div>

				<div class="form-group" id="vendor_type_group" style="display:none;">
					<label for="vendor_type" class="form-label">Vendor Type</label>
					<input type="text" id="vendor_type_view" class="form-input" readonly tabindex="-1">
				</div>

				<div class="form-group form-group-wide" id="vendor_address_group" style="display:none;">
					<label for="vendor_address" class="form-label">Address</label>
					<textarea id="vendor_address" class="form-textarea vendor-address" rows="2" readonly
						tabindex="-1"></textarea>
				</div>

				<!-- Purchase Request / Order Amount removed from the UI — pr_amount / po_amount (+ their _raw
				     hidden twins) stay in the DOM, hidden, so pcrPayloadFields() / pcrPopulate() keep working
				     unchanged for existing records. -->
				<div class="form-group has-prefix" style="display:none;">
					<label for="pr_amount" class="form-label">Purchase Request Amount</label>
					<span class="input-prefix">&#8369;</span>
					<input type="text" id="pr_amount" class="form-input amt-input" placeholder="0.00" autocomplete="off">
					<input type="hidden" id="pr_amount_raw" value="">
				</div>
				<div class="form-group has-prefix" style="display:none;">
					<label for="po_amount" class="form-label">Purchase Order Amount</label>
					<span class="input-prefix">&#8369;</span>
					<input type="text" id="po_amount" class="form-input amt-input" placeholder="0.00" autocomplete="off">
					<input type="hidden" id="po_amount_raw" value="">
				</div>
			</div>
		</div>

		<!-- ── Basic Information ──────────────────────────────────────── -->
		<!-- Hidden for CBA — see cbaApplyAllocationTypeVisibility(): Fiscal Year and Document Type are now hidden
		     fields derived elsewhere, and Is this for bidding? / Parent CBA don't apply either, leaving nothing
		     in this section to show. -->
		<div class="form-section" id="basic_info_section">
			<h2 class="section-header">Basic Information</h2>

			<!-- Fiscal Year is no longer a separate pick: an existing Project's code already starts with the
			     year it was created under (e.g. "2026-R03-001-0001-0001"), so this is derived from the chosen
			     Project instead of asking for it twice (see pcDeriveFiscalYearFromProject()). A typed/new
			     Project (nothing picked yet) falls back to CURRENT_YEAR, same default the old dropdown used. -->
			<input type="hidden" id="fiscal_year" name="fiscal_year" value="">

			<!-- ── Document Type ──────────────────────────────────────────── -->
			<!-- No longer picked by hand — derived from the chosen COB's account code (1xxx = CO, 5xxx = COE) once
			     it's picked below; see cobDeriveDocumentType(). Kept as a real <select> (just permanently hidden)
			     so every existing document.getElementById('document_type').value read/write still works as-is. -->
			<div class="form-row" id="document_type_row" style="display:none;">
				<div class="form-group">
					<label for="document_type" class="form-label">
						Document Type
					</label>
					<select id="document_type" name="document_type" class="form-select">
						<option value="">Select document type</option>
						<option value="CO">CO &mdash; Capital Outlay</option>
						<option value="COE">COE &mdash; Corporate Operating Expenditure</option>
					</select>
					<p class="input-hint">Budget document classification. Required for CBA allocations.</p>
				</div>
			</div>

			<!-- ── Is this for bidding? ────────────────────────────────────── (hidden for CBA — see cbaApplyAllocationTypeVisibility()) -->
			<div class="form-row" id="is_bidding_row">
				<div class="form-group">
					<label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
						<input type="checkbox" id="is_bidding" name="is_bidding"
							style="width:16px;height:16px;cursor:pointer;">
						<span>Is this for bidding?</span>
					</label>
					<p class="input-hint">Check if this allocation is being certified for bidding purposes. Affects the
						wording of the printed CO certificate.</p>
				</div>
			</div>

			<style>
				/* ── Budget Version search dropdown ──────────────── */
				.bv-item {
					padding: 9px 14px;
					cursor: pointer;
					border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA);
				}

				.bv-item:hover {
					background: var(--afims-heading-fill, #000E3E);
				}

				.bv-item:hover .bv-item-name,
				.bv-item:hover .bv-item-sub,
				.bv-item:hover .bv-item-balance {
					color: var(--afims-pure-white, #FFFFFF);
				}

				.bv-item-name {
					font-size: 13px;
					font-weight: 600;
					color: var(--afims-heading, #000E3E);
				}

				.bv-item-sub {
					font-size: 11px;
					color: var(--afims-text-muted, #62657E);
					margin-top: 2px;
				}

				.bv-item-balance {
					font-size: 11px;
					font-weight: 600;
					color: var(--afims-success, #11A752);
					margin-top: 2px;
				}

				/* ── Print Preview button ─────────────────────────── */
				.btn-print {
					display: inline-flex;
					align-items: center;
					gap: 6px;
					padding: 8px 16px;
					background: var(--teal, #0d9488);
					color: var(--afims-pure-white, #FFFFFF);
					border: none;
					border-radius: 6px;
					font-size: 13px;
					font-weight: 600;
					cursor: pointer;
					transition: background .15s;
				}

				.btn-print:hover:not(:disabled) {
					background: var(--afims-success-hover, #0C7539);
				}

				.btn-print:disabled {
					opacity: .6;
					cursor: not-allowed;
				}

				/* ── Program search loading state ────────────────── */
				#prog_dropdown .prog-searching {
					padding: 10px 14px;
					color: var(--afims-text-muted, #62657E);
					font-size: 13px;
					font-style: italic;
				}
			</style>
		</div>


		<!-- ── Certified Funds Available (allocation type CFA only) ──────────
		     The CFA memo's per-document values (CfaPrintService) — read by Print Preview from this form,
		     not saved. Its labels (FOR / THRU / SUBJECT, Main Office / Regional Offices, …) are fixed on the
		     print; every value here prints only as entered. The body is Purpose / Description above. -->
		<div class="form-section" id="cfa_section" style="display:none;">
			<h2 class="section-header">Certified Funds Available</h2>
			<div class="form-row">
				<div class="form-group">
					<label for="cfa_for_name" class="form-label">For</label>
					<input type="text" id="cfa_for_name" class="form-input" autocomplete="off" placeholder="Name">
				</div>
				<div class="form-group">
					<label for="cfa_for_title" class="form-label">For — Title</label>
					<input type="text" id="cfa_for_title" class="form-input" autocomplete="off" placeholder="Title / position">
				</div>
			</div>
			<div class="form-row">
				<div class="form-group">
					<label for="cfa_thru_name" class="form-label">Thru</label>
					<input type="text" id="cfa_thru_name" class="form-input" autocomplete="off" placeholder="Name">
				</div>
				<div class="form-group">
					<label for="cfa_thru_title" class="form-label">Thru — Title</label>
					<input type="text" id="cfa_thru_title" class="form-input" autocomplete="off" placeholder="Title / position">
				</div>
			</div>
			<div class="form-row full-width">
				<div class="form-group">
					<label for="cfa_subject" class="form-label">Subject</label>
					<input type="text" id="cfa_subject" class="form-input" autocomplete="off" placeholder="Subject of the memorandum">
				</div>
			</div>
			<div class="form-row">
				<div class="form-group">
					<label for="cfa_main_office" class="form-label">Main Office</label>
					<input type="text" id="cfa_main_office" class="form-input" autocomplete="off" placeholder="Who prepares / processes / releases it at the Main Office">
				</div>
				<div class="form-group">
					<label for="cfa_regional_offices" class="form-label">Regional Offices</label>
					<input type="text" id="cfa_regional_offices" class="form-input" autocomplete="off" placeholder="… at the Regional Offices">
				</div>
			</div>
			<div class="form-row full-width">
				<div class="form-group">
					<label for="cfa_body_after" class="form-label">Paragraphs after the Main Office / Regional Offices list</label>
					<textarea id="cfa_body_after" class="form-textarea" placeholder="e.g. the estimated cost and the closing paragraph"></textarea>
				</div>
			</div>
			<div class="form-row">
				<div class="form-group">
					<label for="cfa_fund_source" class="form-label">Fund Source (under "Certified Funds Available")</label>
					<input type="text" id="cfa_fund_source" class="form-input" autocomplete="off" placeholder="e.g. Corp. Receipts">
				</div>
				<div class="form-group">
					<label for="cfa_running_title" class="form-label">Page 2 Header Title</label>
					<textarea id="cfa_running_title" class="form-input" rows="2" autocomplete="off" placeholder="Short title printed at the top of page 2 on (one line each)"></textarea>
				</div>
			</div>
			<div class="form-row">
				<div class="form-group">
					<label for="cfa_requested_by_name" class="form-label">Requesting Officer</label>
					<input type="text" id="cfa_requested_by_name" class="form-input" autocomplete="off" placeholder="Name">
				</div>
				<div class="form-group">
					<label for="cfa_requested_by_title" class="form-label">Requesting Officer — Title</label>
					<input type="text" id="cfa_requested_by_title" class="form-input" autocomplete="off" placeholder="Title / position">
				</div>
			</div>
			<div class="form-row">
				<div class="form-group">
					<label for="cfa_certified_by_name" class="form-label">Certified by (FMD)</label>
					<input type="text" id="cfa_certified_by_name" class="form-input" autocomplete="off" placeholder="Name">
				</div>
				<div class="form-group">
					<label for="cfa_certified_by_title" class="form-label">Certified by — Title</label>
					<input type="text" id="cfa_certified_by_title" class="form-input" autocomplete="off" placeholder="Title / position">
				</div>
			</div>
			<label class="form-label">Recommending Approval</label>
			<div id="cfa_recommending"></div>
			<button type="button" class="btn btn-secondary" style="margin:4px 0 16px;" onclick="cfaAddRecommending()">+ Add Recommending Officer</button>
			<div class="form-row" style="margin-bottom:0;">
				<div class="form-group">
					<label for="cfa_approved_by_name" class="form-label">Approved by</label>
					<input type="text" id="cfa_approved_by_name" class="form-input" autocomplete="off" placeholder="Name">
				</div>
				<div class="form-group">
					<label for="cfa_approved_by_title" class="form-label">Approved by — Title</label>
					<input type="text" id="cfa_approved_by_title" class="form-input" autocomplete="off" placeholder="Title / position">
				</div>
			</div>
		</div>

		<!-- ── Supporting Documents ───────────────────────────────────── -->
		<div class="form-section">
			<h2 class="section-header">Supporting Documents</h2>
			<p class="input-hint" style="margin-bottom:12px;">
				Drag &amp; drop a file (max 25MB) for each document, or add a link/reference instead.
			</p>

			<div id="supporting_docs_list">
				<!-- Rows injected by JS -->
			</div>

			<button type="button" class="btn btn-secondary" id="add_doc_btn"
				style="margin-top:10px;display:inline-flex;align-items:center;gap:6px;font-size:13px;"
				onclick="addSupportingDocRow()">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16"
					height="16">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
				</svg>
				Add Document
			</button>

			<style>
				.sdoc-row {
					display: grid;
					grid-template-columns: 1fr auto;
					gap: 10px;
					align-items: flex-end;
					margin-bottom: 10px;
				}

				.sdoc-row .form-input {
					font-size: 13px;
				}

				.sdoc-remove-btn {
					background: none;
					border: 1px solid var(--afims-danger-soft, #FAE1E3);
					border-radius: 8px;
					width: 40px;
					height: 40px;
					box-sizing: border-box;
					padding: 0;
					cursor: pointer;
					color: var(--afims-danger, #DC3545);
					display: flex;
					align-items: center;
					justify-content: center;
					flex-shrink: 0;
					transition: background .15s;
				}

				.sdoc-remove-btn:hover {
					background: var(--afims-danger-soft, #FAE1E3);
				}

				.sdoc-label {
					font-size: 12px;
					font-weight: 500;
					color: var(--afims-text, #1A1B21);
					margin-bottom: 4px;
					display: block;
				}

				.sdoc-dropzone {
					position: relative;
					border: 2px dashed var(--afims-border, #E4ECF2);
					border-radius: 8px;
					background: var(--afims-surface-subtle, #F4F7FA);
					padding: 12px;
					text-align: center;
					cursor: pointer;
					transition: border-color .15s, background .15s;
				}

				.sdoc-dropzone.drag-over {
					border-color: var(--afims-primary, #0035A9);
					background: var(--afims-primary-soft, #E6EBF6);
				}

				.sdoc-dropzone.uploading {
					pointer-events: none;
					opacity: .6;
				}

				.sdoc-dropzone input[type="file"] {
					position: absolute;
					inset: 0;
					opacity: 0;
					width: 100%;
					height: 100%;
					cursor: pointer;
				}

				.sdoc-dz-icon {
					width: 18px;
					height: 18px;
					color: var(--afims-text-disabled, #A9AAB8);
					margin: 0 auto 4px;
					display: block;
				}

				.sdoc-dz-title {
					font-size: 12.5px;
					color: var(--afims-text-muted, #62657E);
					margin: 0;
				}

				.sdoc-dz-hint {
					font-size: 11px;
					color: var(--afims-text-disabled, #A9AAB8);
				}

				.sdoc-file-chip {
					display: flex;
					align-items: center;
					gap: 10px;
					box-sizing: border-box;
					min-height: 40px;
					border: 1px solid var(--afims-border, #E4ECF2);
					border-radius: 8px;
					padding: 6px 8px 6px 10px;
					background: var(--afims-surface, #FFFFFF);
					font-size: 12.5px;
					transition: border-color .15s, background .15s;
				}

				.sdoc-file-chip:hover {
					background: var(--afims-surface-subtle, #F4F7FA);
				}

				.sdoc-file-ico {
					display: inline-flex;
					align-items: center;
					justify-content: center;
					width: 26px;
					height: 26px;
					border-radius: 6px;
					flex-shrink: 0;
					background: var(--afims-primary-soft, #E6EBF6);
					color: var(--afims-primary, #0035A9);
				}

				.sdoc-file-ico svg {
					width: 13px;
					height: 13px;
				}

				.sdoc-file-name {
					flex: 0 1 auto;
					min-width: 0;
					font-weight: 500;
					color: var(--afims-heading, #000E3E);
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: nowrap;
					text-decoration: none;
				}

				/* Only a real link (http/https or a signed storage URL) is clickable. */
				a.sdoc-file-name[href] {
					color: var(--afims-primary, #0035A9);
					cursor: pointer;
				}

				a.sdoc-file-name[href]:hover {
					text-decoration: underline;
				}

				.sdoc-file-size {
					flex-shrink: 0;
					padding: 2px 8px;
					border-radius: 999px;
					background: var(--afims-surface-subtle, #F4F7FA);
					border: 1px solid var(--afims-border, #E4ECF2);
					color: var(--afims-text-muted, #62657E);
					font-size: 11px;
					white-space: nowrap;
				}

				.sdoc-file-size:empty {
					display: none;
				}

				.sdoc-file-actions {
					margin-left: auto;
					display: flex;
					align-items: center;
					gap: 6px;
					flex-shrink: 0;
				}

				.sdoc-file-open {
					display: inline-flex;
					align-items: center;
					gap: 5px;
					padding: 5px 12px;
					border-radius: 6px;
					border: 1px solid var(--afims-primary-soft, #E6EBF6);
					background: var(--afims-primary-soft, #E6EBF6);
					color: var(--afims-primary, #0035A9);
					font-size: 12px;
					font-weight: 600;
					text-decoration: none;
					cursor: pointer;
					transition: background .15s, color .15s;
				}

				.sdoc-file-open:hover {
					background: var(--afims-primary, #0035A9);
					border-color: var(--afims-primary, #0035A9);
					color: var(--afims-pure-white, #FFFFFF);
				}

				.sdoc-file-remove {
					background: none;
					border: 1px solid var(--afims-border, #E4ECF2);
					border-radius: 6px;
					padding: 5px 12px;
					color: var(--afims-text-muted, #62657E);
					cursor: pointer;
					font-size: 12px;
					font-weight: 500;
					font-family: inherit;
					transition: background .15s, color .15s;
				}

				.sdoc-file-remove:hover {
					background: var(--afims-surface-subtle, #F4F7FA);
					color: var(--afims-heading, #000E3E);
				}

				.sdoc-upload-note {
					font-size: 11.5px;
					color: var(--afims-warning-text, #7E6908);
					margin: 6px 0 0;
				}

				.sdoc-upload-note.error {
					color: var(--afims-danger, #DC3545);
				}
			</style>
		</div>

		<!-- ── Remarks ────────────────────────────────────────────────── -->
		<div class="form-section">
			<h2 class="section-header">Remarks / Notes</h2>
			<div class="form-row full-width">
				<div class="form-group">
					<label for="remarks" class="form-label">Additional Remarks or Notes</label>
					<textarea id="remarks" name="remarks" class="form-textarea"
						placeholder="Enter any additional remarks, notes, or special instructions..."></textarea>
				</div>
			</div>
		</div>

		<!-- Hidden Fields -->
		<input type="hidden" id="allocation_number" name="allocation_number"
			value="<?php echo htmlspecialchars($cba_number); ?>">
		<input type="hidden" id="created_by" name="created_by"
			value="<?php echo (int) $user_id; ?>">
		<input type="hidden" id="multiple_node_id" name="multiple_node_id" value="">
		<input type="hidden" id="allocation_id" name="allocation_id"
			value="<?php echo $allocationId ? (int) $allocationId : ''; ?>">

		<!-- Form Actions -->
		<div class="form-actions">
			<button type="button" class="btn btn-secondary" onclick="cbaCancel()">
				<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
					stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
						d="M10 19l-7-7m0 0l7-7m-7 7h18" />
				</svg>
				Cancel
			</button>

			<div class="btn-group">
				<button type="button" class="btn btn-print" onclick="printCBA()">
					<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
						stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
							d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
					</svg>
					Print Preview
				</button>

				<button type="submit" class="btn btn-primary" id="submit_cba_btn">
					<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
						stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
					</svg>
					<span id="submit_btn_label">Submit Request</span>
				</button>
			</div>
		</div>
	</form>

	<!-- Information Card -->
	<div class="info-card">
		<div class="info-card-header">
			<svg class="info-card-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
				stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
					d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
			</svg>
			<div class="info-card-content">
				<p class="info-card-title">Important Reminders</p>
				<ul class="info-list">
					<li>CBA requests above &#8369;50 Million require General Manager approval (RFA process)</li>
					<li>CBA requests below &#8369;50 Million follow streamlined LAA process</li>
					<li>Ensure all supporting documents are attached before submission</li>
					<li>Budget allocations must be validated against approved COB</li>
					<li>This certification is valid for the current fiscal year only</li>
				</ul>
			</div>
		</div>
	</div>
</div>
<?php if ($cbaAsModal): ?>
		</div><!-- /.nha-modal-body -->
	</div><!-- /.nha-modal -->
</div><!-- /#cba-overlay -->
</div><!-- /.nha-root (modal wrapper) -->
<?php endif; ?>

<?php
// Print Preview → the shared print forms (PrintService: CBA / RFA / LAA / CAF) in the preview modal
// (prp_open(payload, { type })) — HTML preview, PDF, DOCX and the letterhead designer.
// include_once: the page around this form may already include it.
include_once __DIR__ . '/../readone/print-preview-readone.php';
?>
<script>
	const API_BASE = '<?php echo $apiBase; ?>';
	const CBA_TOKEN = window.ew?.API_JWT_TOKEN ?? '';

	// ── Edit mode ────────────────────────────────────────────────────────────────
	const CBA_CONFIG = {
		allocationId: <?php echo $allocationId ? json_encode($allocationId) : 'null'; ?> ,
		// the logged-in user's own RC (see the PHP header): { rc_id, rc_code, rc_name, office_type }
		requestingRc: <?php echo json_encode($requestingRc, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?> ,
	};
	// `var`, not `const`: in modal mode the host page chooses at open time — window.cbaModal.open() creates, open(id) edits that
	// record (see the controller at the end of this script) — instead of it being fixed by ?id= when the page was rendered.
	// Every reader below checks it when it runs, never at load.
	var CBA_EDIT_MODE = !!CBA_CONFIG.allocationId;
	// true when the including page rendered this form inside the overlay modal (see the PHP header)
	const CBA_AS_MODAL = <?php echo $cbaAsModal ? 'true' : 'false'; ?>;
	// the Allocation Types the including page limits this form to ([] = no limit) — see the PHP header
	const CBA_ALLOWED_TYPES = <?php echo json_encode($cbaAllowedTypes); ?>;
	// true when the logged-in user is of the Financial Management Department or an administrator — the only ones who print the
	// CBA / RFA / LAA forms (the allocation types in CBA_PRINT_RESTRICTED_TYPES; a CFA is not restricted)
	const CBA_CAN_PRINT_FORM = <?php echo $cbaCanPrintForm ? 'true' : 'false'; ?>;
	const CBA_PRINT_RESTRICTED_TYPES = ['CBA', 'RFA', 'LAA'];
	// created_at of the allocation being edited (set by populateEditForm). Stays
	// null in add mode — nothing is saved yet, so the RFA print date is today's.
	var CBA_CREATED_AT = null;

	// ── Requesting office = a Responsibility Center, not a field on the form ───────
	// Add mode: the logged-in user's own RC (CBA_CONFIG.requestingRc, resolved in PHP from uac.users.rc_id ->
	// fims.responsibility_code). Edit mode: the RC saved on the record (populateEditForm). The API sets
	// budget_allocation.requesting_rc_id itself on create, so this is only used for the RFA / LAA print preview.
	var CBA_REQ_RC = Object.assign({ rc_id: '', rc_code: '', rc_name: '', office_type: '' }, CBA_CONFIG.requestingRc || {});

	// fims.responsibility_code.office_type -> the department_type the RFA template switch uses:
	// Regional / District offices print the REGIONAL OFFICE form, every central RC (Agency / Group / Department) the STAFF form (MAIN).
	function cbaRcDeptType(officeType) {
		var t = String(officeType || '');
		if (t === 'Regional') return 'REGIONAL';
		if (t === 'District') return 'DISTRICT';
		return t ? 'MAIN' : '';
	}

	/* ── API fetch helper (GET) ─────────────────────────────────────────────── */
	async function cba_apiFetch(url) {
		const res = await fetch(url, {
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				...(CBA_TOKEN ? {
					'X-Authorization': 'Bearer ' + CBA_TOKEN
				} : {}),
			},
		});
		if (!res.ok) throw new Error('HTTP ' + res.status);
		return res.json();
	}

	/* ── API fetch helper (POST) ────────────────────────────────────────────── */
	async function cba_apiFetchPost(url, body) {
		const res = await fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				...(CBA_TOKEN ? {
					'X-Authorization': 'Bearer ' + CBA_TOKEN
				} : {}),
			},
			body: JSON.stringify(body),
		});
		if (!res.ok) {
			const err = await res.json().catch(() => ({}));
			throw new Error(err.message || 'Server error ' + res.status);
		}
		return res.json();
	}

	/* ── API fetch helper (PATCH — used for updates in edit mode) ──────────── */
	async function cba_apiFetchPatch(url, body) {
		const res = await fetch(url, {
			method: 'PATCH',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				...(CBA_TOKEN ? {
					'X-Authorization': 'Bearer ' + CBA_TOKEN
				} : {}),
			},
			body: JSON.stringify(body),
		});
		if (!res.ok) {
			const err = await res.json().catch(() => ({}));
			throw new Error(err.message || 'Server error ' + res.status);
		}
		return res.json();
	}

	// ── Shared state ─────────────────────────────────────────────────────────────
	const CURRENT_YEAR = <?php echo (int) date('Y'); ?> ;

	// ── Per-type series counters (populated by refreshAllocationCount) ────────────
	var CBA_SERIES = {
		CBA: 1,
		RFA: 1,
		LAA: 1
	};

	// ── Boot ─────────────────────────────────────────────────────────────────────
	// The whole page set-up. Page mode runs it once at load; modal mode runs it on every open
	// (see the Boot block at the end of this script).
	// The app's shared preloader (app/preloader.php) — cbaBoot() shows it while the lookups load.
	function cbaHideLoader() {
		window.PagePreloader?.hide();
	}

	function cbaBoot() {
		// Listeners on the <form> element itself. They are attached here, not at script load,
		// because in modal mode the form's markup is rebuilt on every open and that discards
		// whatever was attached to the previous element.
		var cbaFormEl = document.getElementById('cba_form');
		cbaFormEl.addEventListener('submit', cbaOnSubmit);
		cbaFormEl.addEventListener('keydown', cbaBlockEnter);

		pcInit(); // Program → Sub-Program → Project comboboxes
		toggleCbaIdRow(); // starting state for the Allocation Type / Parent CBA row — a preset or edit load below may call it again

		generateCBANumber();
		addBudgetAllocationLine(); // render the first Budget Allocation Amount row

		document.getElementById('allocation_type').addEventListener('change', generateCBANumber);
		document.getElementById('allocation_type').addEventListener('change', toggleCbaIdRow);
		document.getElementById('allocation_type').addEventListener('change', updateSubmitLabel);
		document.getElementById('allocation_type').addEventListener('change', pcrApplyMode); // PR ↔ PO fields
		// RFA / LAA printing is paused (PRP_PAUSED in readone/print-preview-readone.php): the Print button is disabled
		// while the type is RFA or LAA. Also re-checked when the button is pointed at / focused, since edit mode sets the
		// type without a change event.
		document.getElementById('allocation_type').addEventListener('change', cbaSyncPrintPaused);
		(function() {
			var pb = document.querySelector('.btn-print');
			if (pb) { pb.addEventListener('pointerenter', cbaSyncPrintPaused); pb.addEventListener('focus', cbaSyncPrintPaused); }
			cbaSyncPrintPaused();
		})();
		document.getElementById('allocation_type').addEventListener('change', cfaApplyMode); // the CFA section
		wireAmtInput(document.getElementById('pr_amount'));
		wireAmtInput(document.getElementById('po_amount'));
		document.getElementById('allocation_type').addEventListener('change',
			applyBudgetVersionVisibilityForDocType);
		document.getElementById('allocation_type').addEventListener('change', cbaApplyAllocationTypeVisibility);
		document.getElementById('document_type').addEventListener('change', applyAllocationTypeOptionsForDocType);
		document.getElementById('document_type').addEventListener('change', generateCBANumber); // RFA code depends on document type
		document.getElementById('document_type').addEventListener('change', dropInfrastructureCategoryForCoe);
		document.getElementById('document_type').addEventListener('change',
			applyProgramProjectVisibilityForDocType);
		// after applyProgramProjectVisibilityForDocType — a doc-type switch can re-lock Project via
		// cbaClearProgramChain(); this re-opens it for CBA's independent Project search if still applicable.
		document.getElementById('document_type').addEventListener('change', cbaApplyAllocationTypeVisibility);
		document.getElementById('document_type').addEventListener('change',
			applyBudgetVersionVisibilityForDocType);

		// ── Allocation Type limited by the page that hosts the form (CBA_ALLOWED_TYPES) ──
		// The CBA page allows only CBA, the RFA/LAA page only those two: every other <option> is taken out, so a request
		// can never be created on the wrong page — including the blank "Select allocation type" placeholder itself,
		// since a scoped page always has a real type to fall back on (single-type: locked to it; RFA/LAA: defaulted
		// to RFA below) and never needs to let the field sit unselected. (Re-run on every open — the markup is
		// rebuilt each time.)
		if (CBA_ALLOWED_TYPES.length) {
			Array.prototype.slice.call(document.getElementById('allocation_type').options).forEach(function(opt) {
				if (!opt.value || CBA_ALLOWED_TYPES.indexOf(opt.value) === -1) opt.remove();
			});
		}

		// ── Pre-populate from URL params (?type=CBA|RFA|LAA&parent_cba_id=N) ──
		// A page that limits the form to ONE type (the CBA page) decides it; otherwise ?type= on the URL still presets it
		// (e.g. the "Generate RFA/LAA" link from a CBA). Skipped entirely in edit mode — editing an existing record should
		// never trigger the "generate RFA/LAA from parent CBA" autofill flow.
		const _urlParams = new URLSearchParams(window.location.search);
		const _presetType = CBA_EDIT_MODE ? '' :
			(CBA_ALLOWED_TYPES.length === 1 ? CBA_ALLOWED_TYPES[0] : (_urlParams.get('type') || '').toUpperCase());
		const _presetCbaId = CBA_EDIT_MODE ? '' : (_urlParams.get('parent_cba_id') || '').trim();

		if ((_presetType === 'CBA' || _presetType === 'RFA' || _presetType === 'LAA') &&
			(!CBA_ALLOWED_TYPES.length || CBA_ALLOWED_TYPES.indexOf(_presetType) !== -1)) {
			var allocTypeEl = document.getElementById('allocation_type');
			allocTypeEl.value = _presetType;
			// Locked: the page it was opened from already decided this — changing it here would disagree with where the
			// user clicked "New ... Memo Request" from. (On the RFA/LAA page a ?type= preset locks it to that one.)
			allocTypeEl.disabled = true;
			allocTypeEl.title = 'Determined by the page this was opened from.';
			generateCBANumber();
			toggleCbaIdRow();
		} else if (!_presetType && !CBA_EDIT_MODE &&
			CBA_ALLOWED_TYPES.indexOf('RFA') !== -1 && CBA_ALLOWED_TYPES.indexOf('LAA') !== -1) {
			// The RFA/LAA page (both types allowed, neither locked via a single-type page or a ?type= preset) —
			// default the selection to RFA rather than leaving it blank, since that's the far more common of the
			// two (LAA is only for the ≥₱50M case). Left enabled, unlike the lock above: the user can still
			// switch it to LAA themselves.
			document.getElementById('allocation_type').value = 'RFA';
			generateCBANumber();
			toggleCbaIdRow();
		}

		// ── Pre-populate Document Type from URL param (?document_type=CO|COE) ──
		// Used by the "Create" button on allocation-variance.php (CO) and
		// coe-allocation-variance.php (COE) so the form opens with the correct
		// document type already selected instead of defaulting to blank.
		const _presetDocType = CBA_EDIT_MODE ? '' : (_urlParams.get('document_type') || '').toUpperCase();
		if (_presetDocType === 'CO' || _presetDocType === 'COE') {
			document.getElementById('document_type').value = _presetDocType;
			generateCBANumber(); // RFA code depends on document type (COE → 57)
			applyBudgetVersionVisibilityForDocType();
		}

		// document_type is final here (URL preset applied) — hide CFA if it is CO.
		applyAllocationTypeOptionsForDocType();
		pcrApplyMode(); // so is the allocation type (a preset RFA / LAA shows the Purchase Order fields)
		cfaApplyMode(); // … and the CFA section for a CFA

		applyProgramProjectVisibilityForDocType();
		if (_presetDocType === 'CO' || _presetDocType === 'COE') {
			dropInfrastructureCategoryForCoe();
		}
		cbaApplyAllocationTypeVisibility(); // after the doc-type rules above, in case a preset type is CBA

		// Run all dropdown loaders; after they settle, apply CBA autofill if requested.
		// The app's shared preloader covers the screen meanwhile (cbaHideLoader() takes it down).
		// NOTE: the COB lists are not loaded here — each line loads its own, from the Program / Sub-Program /
		// Project chosen above (see cobLoad).
		window.PagePreloader?.show();
		var _dropdownLoaders = Promise.allSettled([
			loadPrograms(),
			refreshAllocationCount(),
			loadCategories(),
			loadVendors(),
		]);

		// Take the preloader down once all dropdowns have settled. In edit mode the saved record is still to be
		// filled in (loadExistingAllocation, below), so it stays up until that has finished too.
		_dropdownLoaders.then(function() {
			if (!CBA_EDIT_MODE) cbaHideLoader();
		});

		if ((_presetType === 'RFA' || _presetType === 'LAA') && _presetCbaId) {
			// cbaApplyAllocationTypeVisibility() above left block mode empty (RFA/LAA now waits for the user to
			// pick "+ Add New CBA" or "+ Add Direct COE Account" — see cbaUpdateAddBlockButtonsVisibility()), but
			// a preset Parent CBA means the choice is already made for them: seed one parent-driven block here,
			// then load the CBA list and the other dropdowns in parallel, and pre-select this one parent on it
			// (the user can still add more afterward via "+ Add New CBA" — this preset just seeds the first).
			if (!PROJECT_BLOCKS.length) addProjectBlock();
			Promise.all([
				ensureCbaDataLoaded(),
				_dropdownLoaders,
			]).then(async function() {
				var cba = CBA_DATA.find(function(c) {
					return String(c.allocation_id) === String(_presetCbaId);
				});
				if (!cba || !PROJECT_BLOCKS.length) return;

				await blockCbaSelect(PROJECT_BLOCKS[0].id, cba);
			});
		}
		// ─────────────────────────────────────────────────────────────────

		updateSubmitLabel();
		setupFormValidation();

		if (CBA_EDIT_MODE) {
			// Load the existing allocation once dropdowns have settled, then
			// populate every field from it (see populateEditForm below).
			_dropdownLoaders.then(function() {
				loadExistingAllocation(CBA_CONFIG.allocationId);
			});
		} else {
			// Default supporting document rows (create mode only)
			addSupportingDocRow('Memo Proposal');
		}
	}

	// ── Edit mode: load + populate an existing allocation ─────────────────────────
	// GET /budget_allocation/read/{id} → same nested response shape used
	// throughout this file (program{}, project{}, category_type{}, file_attachments[]).
	async function loadExistingAllocation(id) {
		try {
			var json = await cba_apiFetch(`${API_BASE}/budget_allocation/read/${id}`);
			if (!json.success || !json.data) throw new Error(json.message || 'Failed to load allocation.');
			await populateEditForm(json.data);
		} catch (err) {
			console.error('[loadExistingAllocation]', err);
			cbaNotify('Error', 'Failed to load allocation #' + id + ': ' + err.message, 'error');
		} finally {
			cbaHideLoader();
		}
	}

	async function populateEditForm(a) {
		var g = function(id) {
			return document.getElementById(id);
		};

		CBA_CREATED_AT = a.created_at || null;
		// the record's own requesting office (its creator's RC, joined by the API), not the editing user's
		CBA_REQ_RC = {
			rc_id: a.requesting_rc_id || '',
			rc_code: a.requesting_rc_code || '',
			rc_name: a.requesting_rc_name || '',
			office_type: a.requesting_rc_office_type || '',
		};

		// 1. Allocation type — set first since it drives visibility of several
		//    other fields (CBA parent row, category, doc type).
		g('allocation_type').value = a.allocation_type || 'CBA';
		cbaSyncPrintButton();
		toggleCbaIdRow();
		updateSubmitLabel();
		pcrApplyMode();
		cfaApplyMode();

		// 2. Allocation number — locked to the value loaded from the server
		//    (generateCBANumber() is a no-op in edit mode). If the existing
		//    record has no allocation_number (null/empty — e.g. an old or
		//    incomplete record), generate one now using the same per-type
		//    CBA/RFA/LAA format as create mode, so it's never left blank and
		//    gets carried into the update payload (buildPayload() reads
		//    straight from this hidden field).
		var allocNumber = a.allocation_number || buildAllocationNumber(a.allocation_type || 'CBA', a.document_type);
		g('allocation_number').value = allocNumber;
		g('cba_number_display').textContent = allocNumber;

		// 4. Fiscal year
		if (a.fiscal_year) cbaSetFiscalYear(a.fiscal_year);

		// 6. Document type
		if (a.document_type) {
			var docTypeEl = g('document_type');
			docTypeEl.value = a.document_type;
			applyAllocationTypeOptionsForDocType();
			dropInfrastructureCategoryForCoe();
			applyBudgetVersionVisibilityForDocType();
		}

		// 9. CFA (and "no type chosen yet"): select the single Program → Sub-Program → Project chain first,
		//    so every line's COB list is loaded once, for that chain — CBA / RFA / LAA instead get one or
		//    more project blocks, restored below (step 10).
		if (!cbaUsesBlockProjectUI()) {
			await cbaSelectProgramChain(a.program_code, a.sub_program_code, a.project_code);
		}

		// 9b. CBA / RFA / LAA hide Vendor / Is this for bidding? / Program / Sub-Program, and switch into
		//     project-blocks mode — applied now that, for CFA, the chain above already holds Project's real
		//     value, so this won't trigger the "load every project" step too.
		cbaApplyAllocationTypeVisibility();

		// 10. Budget Allocation Amount lines.
		if (cbaUsesBlockProjectUI()) {
			EDIT_SINGLE_BLOCK_ID = null;
			await restoreProjectBlocksFromSaved(a.projects);
			// RFA / LAA only (CBA's one block has no parent at all) — shows each block's own Parent CBA label.
			if (isRfaOrLaa()) await restoreBlockParentLabels(a.parents);
		} else {
			EDIT_SINGLE_BLOCK_ID = (a.projects && a.projects[0] && a.projects[0].budget_allocation_project_id != null) ?
				parseInt(a.projects[0].budget_allocation_project_id, 10) : null;
			// one per saved line_transaction entry (row 0 already exists from boot), each with the COB it drew from
			var savedLines = Array.isArray(a.line_transaction) ? a.line_transaction : [];
			if (savedLines.length) {
				while (BAA_ROWS.length < savedLines.length) addBudgetAllocationLine();
				for (var li = 0; li < savedLines.length; li++) {
					var line = savedLines[li];
					var idx = BAA_ROWS[li];
					if (idx === undefined) continue;

					baaSetAmount(idx, 'requested', line.requested_amount);
					baaSetAmount(idx, 'allocated', line.allocated_amount);
					await cobRestoreLine(idx, line);
				}
			} else {
				// no itemized lines saved — the flat totals go into row 0
				baaSetAmount(0, 'requested', a.requested_amount);
				baaSetAmount(0, 'allocated', a.allocated_amount);
			}
		}
		recomputeBaaTotals();

		// 13. Purpose / description / remarks — the API's own field is "description" (see buildPayload()'s
		// description: v('project_description') for why), not "project_description".
		if (a.description) g('project_description').value = a.description;
		if (a.remarks) g('remarks').value = a.remarks;
		if (g('is_bidding')) g('is_bidding').checked = !!a.is_bidding;

		// 14. Category (by id, from the loaded /budget_category/all list)
		catSelectById(a.category_id);

		// 14b. Vendor (by id; the address comes with it)
		await vendSelectById(a.vendor_id);

		// 14c. Purchase Request / Purchase Order (number + amount)
		pcrPopulate(a);

		// 17. Existing supporting documents — replace the blank default row
		var list = g('supporting_docs_list');
		if (list) list.innerHTML = '';
		var attachments = a.file_attachments || [];
		var activeAttachments = attachments.filter(function(att) {
			return !att.deleted_at && att.file_meta && !att.file_meta.deleted_at;
		});
		if (activeAttachments.length) {
			activeAttachments.forEach(function(att) {
				addExistingSupportingDocRow(att.file_meta.document_name, att.file_meta.link,
					att.file_meta.view_url);
			});
		} else {
			addSupportingDocRow('Memo Proposal');
		}
		if (a.multiple_node_id && g('multiple_node_id')) {
			g('multiple_node_id').value = a.multiple_node_id;
		}

		// 19. Apply COE visibility rules last, now that program/project/amount
		//     autofill above has already run.
		applyProgramProjectVisibilityForDocType();
	}

	// ── Loader helpers ────────────────────────────────────────────────────────────
	function setLoading(wrapperId, loading) {
		document.getElementById(wrapperId).classList.toggle('is-loading', loading);
	}

	function showError(errorId) {
		document.getElementById(errorId).classList.add('visible');
	}

	// ── 1. Fiscal Year ────────────────────────────────────────────────────────────
	// No longer picked from a dropdown — defaults to CURRENT_YEAR (same default the old
	// dropdown preselected) until a Project is chosen, at which point
	// pcDeriveFiscalYearFromProject() below takes over.
	document.getElementById('fiscal_year').value = CURRENT_YEAR;

	// Sets the (hidden) fiscal_year field directly from a saved record — used when loading an
	// existing allocation for edit, and when autofilling an RFA/LAA from its parent CBA. Runs
	// before cbaSelectProgramChain() in both call sites, and is not overridden by it: that only
	// derives a year from a Project the *user* picks (pcSelect), not one filled in by code.
	function cbaSetFiscalYear(year) {
		document.getElementById('fiscal_year').value = String(year);
	}

	// Parses the leading "YYYY" an existing Project's code was generated with (see
	// GlobalService::generateProjectCode) and uses it as this allocation's fiscal year — the
	// Project already fixes the year, so there is nothing left for the user to pick. Only applies
	// to a Project actually picked from the list (has a real code); a typed/new Project keeps
	// whatever the field already holds (CURRENT_YEAR, unless a saved record set it otherwise).
	function pcDeriveFiscalYearFromProject(code) {
		var m = /^(\d{4})-/.exec(code || '');
		if (m) document.getElementById('fiscal_year').value = m[1];
	}

	// ── Drop a stale "Infrastructure" Category pick when Document Type = COE ──────
	// COE has no "Infrastructure" Category, so a pick made under another document
	// type is cleared (the dropdown list itself is filtered by catVisibleData()).
	// Runs on every Document Type change and after programmatic changes to it.
	function dropInfrastructureCategoryForCoe() {
		var docType = document.getElementById('document_type').value;
		if (docType === 'COE' && isInfrastructureCategorySelected()) {
			var catHidden = document.getElementById('category_id');
			catHidden.value = '';
			catHidden.dataset.label = '';
			var catSearch = document.getElementById('cat_search');
			if (catSearch) catSearch.value = '';
		}
	}

	// True when the selected Category (fims.budget_category) is
	// "Infrastructure" — looked up by id in CAT_ALL_DATA rather than trusting
	// the hidden field's dataset.label, since catClearSelection() only clears
	// .value (not dataset.label) while the user is typing a new search query.
	function isInfrastructureCategorySelected() {
		var hidden = document.getElementById('category_id');
		var id = hidden ? hidden.value : '';
		if (!id) return false;
		var cat = (CAT_ALL_DATA || []).find(function(c) {
			return String(c.budget_category_id) === String(id);
		});
		return !!cat && catIsInfrastructure(cat);
	}

	// ── Hide Program/Project (not Purpose/Description) for non-Infrastructure categories ──────────────────
	// Program/Project only shows when Category = Infrastructure (hidden for every other category) — this used to
	// also depend on Document Type (always hidden for COE), but Document Type is no longer picked up front: it's
	// derived from the COB once it's chosen (cobDeriveDocumentType()), which for most allocations happens well
	// after this point, so it can no longer gate anything shown this early. CBA is the one exception: its Project
	// is independent of Category entirely (see cbaApplyAllocationTypeVisibility()), so it's never hidden here —
	// only Program / Sub-Program are, and unconditionally so, regardless of Category.
	// Purpose / Description ("particulars") field stays visible either way — only the Program/Project row itself
	// is hidden. Neither the program/sub-program/project codes nor allocated_amount carry a `required` attribute,
	// so hiding them just clears out any stale values so they aren't sent.
	function applyProgramProjectVisibilityForDocType() {
		var docType = document.getElementById('document_type').value;
		var singleBlock = document.getElementById('single_project_block');
		var sectionTitle = document.getElementById('program_project_section_title');
		var usesBlocks = cbaUsesBlockProjectUI(); // CBA / RFA / LAA

		// CBA / RFA / LAA never show #single_project_block at all — they use #project_blocks_section instead
		// (cbaApplyAllocationTypeVisibility() owns that toggle). CFA (and "no type chosen yet") is the only
		// case that's conditional: hidden for a COE document, or a CO document whose Category isn't
		// Infrastructure.
		var hideSingleBlock = usesBlocks || docType === 'COE' || (docType === 'CO' && !isInfrastructureCategorySelected());

		if (singleBlock) singleBlock.style.display = hideSingleBlock ? 'none' : '';
		if (sectionTitle) {
			sectionTitle.textContent = usesBlocks ? 'Funding Allocations' :
				(hideSingleBlock ? 'Purpose Details' : 'Program, Sub-Program and Project Details');
		}

		if (hideSingleBlock && !usesBlocks) cbaClearProgramChain();

		// Project is required — but never while this whole row is hidden, or the [required] sweep in
		// cbaOnSubmit would block submission over a field the user can't even see.
		var projSearch = document.getElementById('proj_search');
		if (projSearch) projSearch.required = !hideSingleBlock;

		baaApplyCashRequirementsVisibility();
	}

	// Cash Requirements (allocated_amount) is hidden for COE documents (it has none) and for CBA / RFA / LAA
	// allocations (not used there — see cbaUsesIndependentProject()) — on every row, plus its Total Cash
	// Requirements summary. Wired to both document_type's and allocation_type's 'change', and called wherever
	// either is set programmatically. Clears out any stale allocated_amount value while hidden, so it isn't sent.
	function baaApplyCashRequirementsVisibility() {
		var docType = document.getElementById('document_type').value;
		var hideCash = cbaUsesIndependentProject() || docType === 'COE';

		document.querySelectorAll('.baa-line').forEach(function(row) {
			row.classList.toggle('baa-no-cash', hideCash);
			var cashGroup = row.querySelector('.baa-cash-group');
			if (!cashGroup) return;
			cashGroup.style.display = hideCash ? 'none' : '';
			if (hideCash) {
				var allocDisplay = row.querySelector('.baa-allocated-amount');
				if (allocDisplay) {
					allocDisplay.value = '';
					baaSyncRaw(allocDisplay);
				}
			}
		});

		var totalsRow = document.getElementById('baa_total_allocated_row');
		if (totalsRow) totalsRow.style.display = hideCash ? 'none' : '';

		recomputeBaaTotals();
		baaRelocateFirstLine();
		baaApplyHeaderVisibility();
	}

	// Moves the first Budget Allocation Amount line (COB + Requested Amount) in next to Project — CFA (and "no
	// type chosen yet") only, and only while Program / Sub-Program / Project are actually showing (not a COE
	// document, where that whole row is hidden). Moves it back down to its usual place in baa_lines_container
	// otherwise. CBA / RFA / LAA no longer use this splice at all: each of their project blocks already renders
	// its Project and its own line(s) together natively (#project_blocks_container) — see
	// cbaApplyAllocationTypeVisibility().
	function baaRelocateFirstLine() {
		var firstIdx = BAA_ROWS[0];
		if (firstIdx === undefined || BAA_ROW_BLOCK[firstIdx] != null) return;
		var firstRow = document.querySelector('.baa-line[data-idx="' + firstIdx + '"]');
		if (!firstRow) return;

		var container = document.getElementById('baa_lines_container');
		if (container && firstRow.parentNode !== container) {
			container.insertBefore(firstRow, container.firstChild);
		}
	}

	// The "Budget Allocation Amount" heading, its hint text and the totals box only earn their keep for CFA
	// (and "no type chosen yet") once there's something below them that needs introducing — with just the
	// one line, there's nothing left to show. CBA / RFA / LAA always hide the heading (each project block
	// renders its own COB area instead) but keep the totals box (the combined total across every block).
	function baaApplyHeaderVisibility() {
		var blockMode = cbaUsesBlockProjectUI();
		var hide = blockMode ? false : BAA_ROWS.length <= 1;

		var header = document.getElementById('baa_header');
		if (header) header.style.display = (blockMode || hide) ? 'none' : '';
		var totals = document.getElementById('baa_totals');
		if (totals) totals.style.display = (blockMode ? BAA_ROWS.length === 0 : hide) ? 'none' : '';
	}

	// ── 3. Program → Sub-Program → Project (cascading searchable comboboxes) ──────
	// Each level lists only what belongs to the level above it:
	//   Program      GET /program/all
	//   Sub-Program  GET /sub_program/all?program_code=<program>
	//   Project      GET /project/all?program_code=<program>&sub_program_code=<sub-program>
	// Choosing, editing or clearing a level clears every level below it and loads the
	// next one. A program with no sub-programs would otherwise be a dead end
	// (projects.sub_program_code is optional), so its projects are listed directly.
	// The value of each level is its CODE — these tables have no integer id. It lives
	// in the hidden input and is sent as program_code / sub_program_code / project_code.
	//
	// A level can also be NEW: text typed into its box that was not picked from the list. Nothing is created while
	// typing; buildPayload() sends the typed text as program_name / sub_program_name / project_name and the server
	// (BudgetAllocationService::resolveTypedEntities) creates what does not exist yet — reusing an existing one
	// with the same name — in the same transaction as the memo request. Under a NEW level there is nothing to
	// pick, so the levels below it become type-only (pcNewOnly).
	var PCHAIN = {
		prog: {
			search: 'prog_search', dd: 'prog_dropdown', hidden: 'program_code', wrapper: 'program_wrapper',
			error: 'program_error', hint: 'program_hint', noun: 'program', parent: null, child: 'subprog',
			codeKey: 'program_code', nameKey: 'program_name', data: [], seq: 0,
			hintBase: 'Pick an existing program, or type a new name to create one.'
		},
		subprog: {
			search: 'subprog_search', dd: 'subprog_dropdown', hidden: 'sub_program_code', wrapper: 'sub_program_wrapper',
			error: 'sub_program_error', hint: 'sub_program_hint', noun: 'sub-program', parent: 'prog', child: 'proj',
			codeKey: 'sub_program_code', nameKey: 'sub_program_name', data: [], seq: 0, hintBase: ''
		},
		proj: {
			search: 'proj_search', dd: 'proj_dropdown', hidden: 'project_code', wrapper: 'project_wrapper',
			error: 'project_error', hint: 'project_hint', noun: 'project', parent: 'subprog', child: null,
			codeKey: 'project_code', nameKey: 'project_name', data: [], seq: 0, hintBase: ''
		},
	};

	function pcEl(key, part) {
		return document.getElementById(PCHAIN[key][part]);
	}

	function pcValue(key) {
		var hidden = pcEl(key, 'hidden');
		return hidden ? hidden.value : '';
	}

	function pcEsc(s) {
		return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function pcShowError(key) {
		showError(PCHAIN[key].error);
	}

	function pcHideError(key) {
		var box = document.getElementById(PCHAIN[key].error);
		if (box) box.classList.remove('visible');
	}

	// The text typed into a level's box when it was NOT picked from the list — i.e. a NEW program / sub-program /
	// project to create on save ('' when an existing one is picked, or the box is empty).
	function pcTyped(key) {
		if (pcValue(key)) return '';
		return (pcEl(key, 'search').value || '').replace(/\s+/g, ' ').trim();
	}

	// Sets a level's hint. A normal hint is remembered (hintBase); a "new" one (isNew) is shown on top of it, in colour,
	// and pcRefreshStates() puts the remembered one back as soon as the level stops being new.
	function pcSetHint(key, text, isNew) {
		var c = PCHAIN[key];
		if (!c.hint) return;
		var el = pcEl(key, 'hint');
		if (!isNew) c.hintBase = text;
		el.textContent = text;
		el.classList.toggle('pc-hint-new', !!isNew);
	}

	function pcRender(key, rows) {
		var c = PCHAIN[key];
		var dd = pcEl(key, 'dd');
		if (!rows.length) {
			var q = (pcEl(key, 'search').value || '').replace(/\s+/g, ' ').trim();
			dd.innerHTML = q ?
				'<div class="pc-new">No match \u2014 \u201c' + pcEsc(q) + '\u201d will be created as a new ' + c.noun + ' when you save.</div>' :
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No ' + c.noun +
				's yet \u2014 type a name to create one.</div>';
			dd.style.display = 'block';
			return;
		}
		dd.innerHTML = rows.map(function(r) {
			// A project's own row (from /project/all) carries its parent program_code / sub_program_code —
			// kept on the item so cbaSyncProjectParents() can back-fill them when Program / Sub-Program are
			// hidden (CBA; see cbaApplyAllocationTypeVisibility()) and shown here so the picker still reads
			// clearly without them on screen.
			var extra = key === 'proj' ?
				' data-program-code="' + pcEsc(r.program_code) + '" data-program-name="' + pcEsc(r.program_name) + '"' +
				' data-sub-program-code="' + pcEsc(r.sub_program_code) + '" data-sub-program-name="' + pcEsc(r.sub_program_name) + '"' : '';
			var sub = key === 'proj' ?
				pcEsc(r[c.codeKey]) + (r.program_name ? ' · ' + pcEsc(r.program_name) : '') +
				(r.sub_program_name ? ' / ' + pcEsc(r.sub_program_name) : '') :
				pcEsc(r[c.codeKey]);
			return '<div class="bv-item" data-code="' + pcEsc(r[c.codeKey]) + '" data-name="' + pcEsc(r[c.nameKey]) + '"' + extra + '>' +
				'<div class="bv-item-name">' + pcEsc(r[c.nameKey]) + '</div>' +
				'<div class="bv-item-sub">' + sub + '</div>' +
				'</div>';
		}).join('');
		dd.style.display = 'block';
		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				pcSelect(key, this.dataset.code, this.dataset.name);
				if (key === 'proj') {
					cbaSyncProjectParents(this.dataset.programCode, this.dataset.programName,
						this.dataset.subProgramCode, this.dataset.subProgramName);
				}
			});
		});
	}

	// Pick a level's value, then clear the levels below it and load the next one. The returned
	// promise resolves once that next level has its options, so edit mode can await it and
	// select the following level straight away.
	function pcSelect(key, code, name) {
		var c = PCHAIN[key];
		var hidden = pcEl(key, 'hidden');
		hidden.value = code;
		hidden.dataset.label = name + ' (' + code + ')';
		pcEl(key, 'search').value = hidden.dataset.label;
		pcEl(key, 'dd').style.display = 'none';
		pcClearBelow(key);
		if (key === 'proj') pcDeriveFiscalYearFromProject(code);
		cobChainChanged(); // the COB lists follow the chain
		pcRefreshStates();
		return c.child ? pcLoad(c.child) : Promise.resolve();
	}

	// Forget one level's value (and optionally the text in its box).
	function pcClear(key, clearText) {
		var hidden = pcEl(key, 'hidden');
		if (!hidden) return;
		hidden.value = '';
		hidden.dataset.label = '';
		if (clearText) pcEl(key, 'search').value = '';
		cobChainChanged();
	}

	// Lock a level until the one above it has a value.
	function pcDisable(key) {
		var c = PCHAIN[key];
		var parentNoun = PCHAIN[c.parent].noun;
		var search = pcEl(key, 'search');
		search.disabled = true;
		search.placeholder = 'Select a ' + parentNoun + ' first';
		pcSetHint(key, 'Select a ' + parentNoun + ' first.');
		pcEl(key, 'dd').style.display = 'none';
		pcHideError(key);
		setLoading(c.wrapper, false);
	}

	// Clear and lock every level below `key`. The seq bump makes any request still in flight for
	// those levels discard its answer instead of filling a list the user has already moved past.
	function pcClearBelow(key) {
		for (var k = PCHAIN[key].child; k; k = PCHAIN[k].child) {
			PCHAIN[k].seq++;
			PCHAIN[k].data = [];
			pcClear(k, true);
			pcDisable(k);
		}
	}

	// Open a level for typing only: its parent is NEW (typed, not picked), so there is nothing to pick from yet.
	function pcNewOnly(key) {
		var c = PCHAIN[key];
		c.seq++; // a list still loading for this level is discarded
		c.data = [];
		var search = pcEl(key, 'search');
		search.disabled = false;
		search.placeholder = 'Type a new ' + c.noun + (key === 'proj' ? '' : ' (optional)') + '\u2026';
		setLoading(c.wrapper, false);
		pcHideError(key);
	}

	// Re-derives what the typed text means after any edit or pick. A level whose box holds text that was not picked is NEW;
	// under a NEW level the levels below are type-only. Hints show which levels are new and revert when one stops being.
	function pcRefreshStates() {
		var progNew = !!pcTyped('prog');
		var subNew = !!pcTyped('subprog');

		if (progNew) {
			// a new program has no sub-programs or projects yet — a project may even sit directly under it
			pcNewOnly('subprog');
			pcNewOnly('proj');
			pcSetHint('subprog', 'This is a new program, so its sub-programs are new too \u2014 type one, or leave it blank.');
			pcSetHint('proj', 'Type a new project, or leave it blank.');
		} else if (subNew) {
			pcNewOnly('proj');
			pcSetHint('proj', 'Type a new project, or leave it blank.');
		}

		['prog', 'subprog', 'proj'].forEach(function(k) {
			var typed = pcTyped(k);
			var c = PCHAIN[k];
			if (typed) {
				pcSetHint(k, 'New ' + c.noun + ' \u201c' + typed + '\u201d \u2014 it will be created when you save.', true);
			} else if (c.hint) {
				pcSetHint(k, c.hintBase);
			}
		});
	}

	// Load a child level's options from its parent's current value.
	async function pcLoad(key) {
		var c = PCHAIN[key];
		var search = pcEl(key, 'search');
		var progCode = pcValue('prog');
		var subCode = pcValue('subprog');
		var url = key === 'subprog' ?
			API_BASE + '/sub_program/all?program_code=' + encodeURIComponent(progCode) :
			API_BASE + '/project/all?program_code=' + encodeURIComponent(progCode) +
			(subCode ? '&sub_program_code=' + encodeURIComponent(subCode) : '');

		var seq = ++c.seq;
		var loaded = false;
		pcHideError(key);
		setLoading(c.wrapper, true);
		search.disabled = true;
		search.placeholder = 'Loading\u2026';
		try {
			var json = await cba_apiFetch(url);
			if (seq !== c.seq) return; // a level above changed while this was loading
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message || 'Unexpected response format');

			c.data = json.data;
			loaded = true;
			var n = c.data.length;
			if (!n) {
				// nothing to pick: it can only be typed — a new one, created when you save
				search.disabled = false;
				search.placeholder = 'No ' + c.noun + 's yet \u2014 type to add one';
				pcSetHint(key, key === 'subprog' ?
					'This program has no sub-programs yet \u2014 type one to add it, or leave it blank (its projects are listed directly).' :
					'No projects under this ' + (subCode ? 'sub-program' : 'program') + ' yet \u2014 type one to add it.');
			} else {
				search.disabled = false;
				search.placeholder = 'Search or type a new ' + c.noun + '\u2026';
				pcSetHint(key, n + ' ' + c.noun + '(s) under this ' + (key === 'subprog' ? 'program' : (subCode ? 'sub-program' : 'program')) + '.');
			}
			// A successful load re-enables the box unconditionally above \u2014 re-assert the RFA / LAA Parent CBA
			// lock (cbaApplyParentCbaLock()) in case this landed after it, so Project doesn't silently become
			// editable again once this async load catches up.
			if (key === 'proj') cbaApplyParentCbaLock();
		} catch (err) {
			if (seq !== c.seq) return;
			console.error('[pcLoad ' + key + ']', err);
			c.data = [];
			search.placeholder = 'Could not load';
			pcShowError(key);
		} finally {
			if (seq === c.seq) setLoading(c.wrapper, false);
		}
		// no sub-programs under the chosen program: skip straight to its projects
		if (loaded && key === 'subprog' && !c.data.length && seq === c.seq) return pcLoad('proj');
	}

	// Typing in a box filters that level's already-loaded options (client-side).
	function pcWire(key) {
		var c = PCHAIN[key];
		var search = pcEl(key, 'search');
		var dd = pcEl(key, 'dd');

		function matches(q) {
			q = q.toLowerCase();
			return c.data.filter(function(r) {
				return !q || String(r[c.nameKey] || '').toLowerCase().includes(q) ||
					String(r[c.codeKey] || '').toLowerCase().includes(q);
			}).slice(0, 50);
		}

		search.addEventListener('input', function() {
			// editing the text withdraws the earlier pick — it and everything below it are cleared
			if (pcValue(key)) {
				pcClear(key);
				pcClearBelow(key);
			} else if (!pcTyped(key)) {
				pcClearBelow(key); // the box was emptied: the levels below lock again
			}
			pcRender(key, matches(this.value.trim()));
			pcRefreshStates(); // text that was typed (not picked) is a NEW one
		});
		search.addEventListener('focus', function() {
			if (!pcValue(key)) pcRender(key, matches(this.value.trim()));
		});
		search.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// Boot: wire the three boxes and lock the two child levels until a parent is chosen.
	function pcInit() {
		['prog', 'subprog', 'proj'].forEach(pcWire);
		pcDisable('subprog');
		pcDisable('proj');
	}

	async function loadPrograms() {
		var c = PCHAIN.prog;
		var search = pcEl('prog', 'search');
		try {
			var json = await cba_apiFetch(API_BASE + '/program/all');
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message || 'Unexpected response format');

			c.data = json.data;
			search.disabled = false;
			search.placeholder = 'Search or type a new program\u2026';
		} catch (err) {
			console.error('[loadPrograms]', err);
			pcShowError('prog');
		} finally {
			setLoading(c.wrapper, false);
		}
	}

	// Select program → sub-program → project from their codes (edit mode, and "generate RFA/LAA
	// from a parent CBA"). Each level is only selectable once the one above has loaded its options.
	async function cbaSelectProgramChain(progCode, subCode, projCode) {
		// The levels are filled in by code here, not chosen by the user: the COBs already picked belong to this
		// chain, so the COB reloads it triggers must not clear them.
		COB_KEEP_SELECTION++;
		try {
			var prog = progCode && PCHAIN.prog.data.find(function(r) {
				return r.program_code === progCode;
			});
			if (!prog) return;
			await pcSelect('prog', prog.program_code, prog.program_name);

			var sub = subCode && PCHAIN.subprog.data.find(function(r) {
				return r.sub_program_code === subCode;
			});
			if (sub) await pcSelect('subprog', sub.sub_program_code, sub.sub_program_name);

			var proj = projCode && PCHAIN.proj.data.find(function(r) {
				return r.project_code === projCode;
			});
			if (proj) await pcSelect('proj', proj.project_code, proj.project_name);
		} finally {
			COB_KEEP_SELECTION--;
		}
	}

	// Empty all three (used when the document type hides this row).
	function cbaClearProgramChain() {
		pcClear('prog', true);
		pcClearBelow('prog');
		pcRefreshStates();
	}

	// ── CBA: a simpler form ──────────────────────────────────────────────────
	// A Certificate of Budget Allocation doesn't buy anything from a vendor and is never for bidding, and it is
	// filed by Project rather than by picking a Program then a Sub-Program: the chosen Project's own
	// program_code / sub_program_code (carried on every /project/all row — see cbaSyncProjectParents()) are used
	// instead, so Program and Sub-Program stay hidden and the Project box is searchable on its own. RFA / LAA /
	// CFA keep the full form — only CBA simplifies it this way. Wired to allocation_type's 'change' (and
	// document_type's, since a doc-type switch can re-lock Project via cbaClearProgramChain() above), and called
	// directly wherever allocation_type is set programmatically (no 'change' event fires then): cbaBoot()'s
	// preset and populateEditForm().
	// CBA, RFA and LAA all use the simplified "pick a Project, then a COB" flow (independent of Program /
	// Sub-Program) — see the usesIndependentProject checks below. Only CBA additionally drops Vendor / Is
	// this for bidding? / Basic Information, since RFA and LAA still need those.
	function cbaUsesIndependentProject() {
		var type = document.getElementById('allocation_type').value;
		return type === 'CBA' || type === 'RFA' || type === 'LAA';
	}

	// Whether THIS type uses the repeatable project-BLOCKS UI/data-shape (#project_blocks_section,
	// PROJECT_BLOCKS[], the `projects` array built by collectProjectBlocks()) rather than the single,
	// CASCADING Program → Sub-Program → Project chain (#single_project_block, CFA only). CBA / RFA / LAA all
	// use it — same membership as cbaUsesIndependentProject() above — because a project block's own search
	// field is a flat, direct pick with no Program/Sub-Program to choose first (blockFieldNoun() /
	// blockSetupSearch(): a Project directly for CBA, an approved Parent CBA for RFA/LAA — see
	// blockCbaSetupSearch()); the single chain, by contrast, locks Project until a Program and a Sub-Program
	// are chosen (pcDisable()'s cascade) — wrong for either case. CBA always gets exactly one block (never
	// add/removable by hand); RFA/LAA start with one too but may add more, one per additional Parent CBA
	// ("+ Add New CBA" — see cbaBlocksAreParentDriven()).
	function cbaUsesBlockProjectUI() {
		return cbaUsesIndependentProject();
	}

	// RFA / LAA only: each block IS a Parent CBA pick, so more than one is meaningful — they start with one
	// block (same as CBA) but may add more via "+ Add New CBA" (switchToBlockMode() shows that button only
	// for these two types). CBA has exactly one project in total, so it never gets that button and its one
	// block's remove button never shows either (renumberProjectBlocks()'s own onlyOne check already covers
	// that — CBA simply never grows past one block to begin with).
	function cbaBlocksAreParentDriven() {
		var type = document.getElementById('allocation_type').value;
		return type === 'RFA' || type === 'LAA';
	}

	// Whether every current Funding Allocation block is a Direct COE Account draw (directCob — no Parent CBA,
	// no Project). False when there are no blocks at all (nothing to call "all direct" yet) or when at least
	// one block still needs a Parent CBA — see cbaApplyVendorRequirement(), the one thing this gates.
	function cbaAllProjectBlocksAreDirect() {
		return PROJECT_BLOCKS.length > 0 && PROJECT_BLOCKS.every(function(b) { return !!b.directCob; });
	}

	// Vendor Name is required for every RFA/LAA/CFA EXCEPT when the whole request draws directly from COE
	// account(s) with no Parent CBA / Project behind it — there is no procurement to name a vendor for. Hidden
	// outright for CBA already (cbaApplyAllocationTypeVisibility()); here only the required-ness and the
	// "*"/"(Optional)" marker change, so re-run this whenever the set of blocks changes (addProjectBlock() /
	// removeProjectBlock()), not just on allocation-type change.
	function cbaApplyVendorRequirement() {
		var isCBA = document.getElementById('allocation_type').value === 'CBA';
		var optional = isCBA || cbaAllProjectBlocksAreDirect();

		var vendSearchEl = document.getElementById('vendor_search');
		if (vendSearchEl) vendSearchEl.required = !optional;

		var mark = document.getElementById('vendor_required_mark');
		var hint = document.getElementById('vendor_optional_hint');
		if (mark) mark.style.display = optional ? 'none' : '';
		if (hint) hint.style.display = optional ? 'inline' : 'none';
	}

	// RFA/LAA only: with no blocks yet, the user must choose which kind to add first — both buttons show as a
	// choice. Once a block of one kind exists, its own button stays (so more of the same kind can still be
	// added) but the OTHER kind's button hides, so the form doesn't look like you can freely mix the two from
	// the start. Both reappear once every block of a kind has been removed. A record edited back in that
	// already mixes both kinds (restoreProjectBlocksFromSaved(), old data saved before this rule) shows both
	// buttons too — nothing here forces it back to one kind.
	function cbaUpdateAddBlockButtonsVisibility() {
		var addBtn = document.getElementById('add_project_block_btn');
		var addDirectBtn = document.getElementById('add_direct_cob_block_btn');
		if (!addBtn || !addDirectBtn) return;
		if (!cbaBlocksAreParentDriven()) {
			addBtn.style.display = 'none';
			addDirectBtn.style.display = 'none';
			return;
		}
		var hasParentDriven = PROJECT_BLOCKS.some(function(b) { return !b.directCob; });
		var hasDirect = PROJECT_BLOCKS.some(function(b) { return b.directCob; });
		addBtn.style.display = (!PROJECT_BLOCKS.length || hasParentDriven) ? '' : 'none';
		addDirectBtn.style.display = (!PROJECT_BLOCKS.length || hasDirect) ? '' : 'none';
	}

	// The CBA / RFA / LAA forms are printed by FMD users and administrators only (CBA_CAN_PRINT_FORM): on those types the Print
	// Preview button is hidden for anyone else. CFA keeps it. Runs whenever the allocation type is set or changes.
	function cbaSyncPrintButton() {
		var printBtn = document.querySelector('.btn-print');
		var typeEl = document.getElementById('allocation_type');
		if (!printBtn || !typeEl) return;
		printBtn.style.display = (CBA_PRINT_RESTRICTED_TYPES.indexOf(typeEl.value) >= 0 && !CBA_CAN_PRINT_FORM) ? 'none' : '';
	}

	function cbaApplyAllocationTypeVisibility() {
		cbaSyncPrintButton();
		var isCBA = document.getElementById('allocation_type').value === 'CBA';
		var usesIndependentProject = cbaUsesIndependentProject();

		// #vendor_section itself stays visible for every type — CBA keeps it open for its #pr_number_group
		// field (Purchase Request Number, optional); only #vendor_name_group (and the vendor_id it carries)
		// is CBA-specific and hides. Vendor Code/Type/Address stay hidden regardless — they only ever show once
		// a vendor is actually picked (vendSetMode()), which cbaClearVendor() below prevents for CBA.
		var vendorNameGroup = document.getElementById('vendor_name_group');
		if (vendorNameGroup) vendorNameGroup.style.display = isCBA ? 'none' : '';
		cbaApplyVendorRequirement();
		if (isCBA) cbaClearVendor();

		var biddingRow = document.getElementById('is_bidding_row');
		if (biddingRow) biddingRow.style.display = usesIndependentProject ? 'none' : '';
		if (usesIndependentProject) {
			var biddingEl = document.getElementById('is_bidding');
			if (biddingEl) biddingEl.checked = false;
		}

		// Category — hidden for CBA / RFA / LAA; only CFA (and "no type chosen yet") still shows it.
		var categoryRow = document.getElementById('category_row');
		if (categoryRow) categoryRow.style.display = usesIndependentProject ? 'none' : '';
		var catSearchEl = document.getElementById('cat_search');
		if (catSearchEl) catSearchEl.required = !usesIndependentProject;
		if (usesIndependentProject) catClearSelection();

		// Basic Information has nothing left in it for CBA / RFA / LAA (Fiscal Year / Document Type are hidden
		// fields derived elsewhere, Parent CBA now lives in the Allocation Type row instead, and Is this for
		// bidding? is hidden here too) — hide the section itself rather than leave an empty card with just a
		// heading.
		var basicInfoSection = document.getElementById('basic_info_section');
		if (basicInfoSection) basicInfoSection.style.display = usesIndependentProject ? 'none' : '';

		var progGroup = document.getElementById('program_group');
		var subGroup = document.getElementById('sub_program_group');
		if (progGroup) progGroup.style.display = usesIndependentProject ? 'none' : '';
		if (subGroup) subGroup.style.display = usesIndependentProject ? 'none' : '';

		baaApplyCashRequirementsVisibility(); // Cash Requirements isn't used for CBA / RFA / LAA either
		baaApplyCbaAmountVisibilityAll(); // CBA Available Balance (RFA / LAA) vs Available Amount (CBA / CFA)
		baaRefreshAllCbaAmounts();
		baaApplyEarmarksVisibilityAll(); // "Where this COB's balance is held" is a CBA / CFA concept only

		// Project — CBA / RFA / LAA get one-or-more project block(s) (#project_blocks_section, each with its
		// own flat, direct Project search + its own COB/Amount line(s), no Program/Sub-Program to pick
		// first); CFA (and "no type chosen yet") keeps the single, cascading Program → Sub-Program → Project
		// chain (#single_project_block) — see cbaUsesBlockProjectUI().
		var usesBlockUI = cbaUsesBlockProjectUI();
		var singleBlock = document.getElementById('single_project_block');
		var blocksSection = document.getElementById('project_blocks_section');
		if (usesBlockUI) {
			if (singleBlock) singleBlock.style.display = 'none';
			if (blocksSection) blocksSection.style.display = '';
			switchToBlockMode();
		} else {
			if (blocksSection) blocksSection.style.display = 'none';
			switchToSingleMode();

			if (!pcValue('proj')) {
				// Leaving CBA / RFA / LAA with no project picked: back to the normal locked cascade.
				if (!pcValue('prog')) {
					pcDisable('subprog');
					pcDisable('proj');
				} else if (!pcValue('subprog')) {
					pcDisable('proj');
				}
			}
		}

		// Re-check whether the first line's COB should be locked until Project is picked (cobRequiresProjectFirst)
		// — switching allocation type changes that regardless of whether Project itself just changed.
		if (BAA_ROWS[0] !== undefined) cobLoad(BAA_ROWS[0]);

		cbaApplyParentCbaLock();
	}

	// Tears down every legacy (non-block) BAA line and rebuilds as one fresh project block — a no-op once
	// already in block mode (PROJECT_BLOCKS already has at least one). Called whenever Allocation Type
	// becomes CBA / RFA / LAA. The "+ Add New CBA" button only ever shows for RFA/LAA — CBA always keeps
	// exactly its one auto-seeded block (see cbaBlocksAreParentDriven()).
	function switchToBlockMode() {
		BAA_ROWS.slice().forEach(function(idx) {
			if (BAA_ROW_BLOCK[idx] == null) baaHardRemoveLine(idx);
		});
		// CBA has no "+ Add" buttons to choose from (cbaBlocksAreParentDriven() is false for it) — it must
		// always auto-seed its one block itself. RFA/LAA instead starts empty and waits for the user to pick
		// "+ Add New CBA" or "+ Add Direct COE Account" — see cbaUpdateAddBlockButtonsVisibility().
		if (!PROJECT_BLOCKS.length && !cbaBlocksAreParentDriven()) addProjectBlock();
		cbaUpdateAddBlockButtonsVisibility();
		updateBaaRemoveButtons();
		recomputeBaaTotals();
		baaApplyHeaderVisibility();
	}

	// Tears down every project block and rebuilds as one fresh legacy (non-block) BAA line — a no-op once
	// already in single-chain mode (PROJECT_BLOCKS is empty). Called whenever Allocation Type becomes CFA or
	// is cleared.
	function switchToSingleMode() {
		if (!PROJECT_BLOCKS.length) return;
		PROJECT_BLOCKS.slice().forEach(function(b) {
			removeProjectBlock(b.id, true);
		});
		if (!BAA_ROWS.length) addBudgetAllocationLine();
		updateBaaRemoveButtons();
		recomputeBaaTotals();
		baaRelocateFirstLine();
		baaApplyHeaderVisibility();
	}

	function cbaClearVendor() {
		var idEl = document.getElementById('vendor_id');
		var searchEl = document.getElementById('vendor_search');
		if (idEl) idEl.value = '';
		if (searchEl) searchEl.value = '';
		vendSetMode('none');
	}

	// A project picked while Program / Sub-Program are hidden (CBA) carries its own program_code /
	// sub_program_code (every /project/all row is joined to its program and sub-program) — copy them into the
	// two hidden fields so the payload still has them, and into the search boxes' text so they read correctly
	// if the type is switched away from CBA afterwards.
	function cbaSyncProjectParents(progCode, progName, subCode, subName) {
		var progHidden = pcEl('prog', 'hidden');
		var progSearch = pcEl('prog', 'search');
		if (progHidden && progCode) {
			progHidden.value = progCode;
			progHidden.dataset.label = (progName || progCode) + ' (' + progCode + ')';
			if (progSearch) {
				progSearch.value = progHidden.dataset.label;
				progSearch.disabled = false;
			}
		}

		var subHidden = pcEl('subprog', 'hidden');
		var subSearch = pcEl('subprog', 'search');
		if (subHidden) {
			if (subCode) {
				subHidden.value = subCode;
				subHidden.dataset.label = (subName || subCode) + ' (' + subCode + ')';
				if (subSearch) subSearch.value = subHidden.dataset.label;
			} else {
				subHidden.value = '';
				subHidden.dataset.label = '';
				if (subSearch) subSearch.value = '';
			}
			if (subSearch && progCode) subSearch.disabled = false; // this project's program is known either way
		}
	}

	// Drop every loaded list and invalidate in-flight requests (modal reopen).
	function pcResetState() {
		['prog', 'subprog', 'proj'].forEach(function(k) {
			PCHAIN[k].data = [];
			PCHAIN[k].seq++;
		});
	}

	// ── Category (searchable combobox) ───────────────────────────────────────────
	// GET /budget_category/all  →  [{ budget_category_id, name, … }]. The picked id lives in the
	// hidden #category_id and is saved as budget_allocation.category_id.
	var CAT_ALL_DATA = [];
	var CAT_DEBOUNCE = null;

	function catIsInfrastructure(c) {
		return String(c.name || '').trim().toLowerCase() === 'infrastructure';
	}

	async function loadCategories() {
		var searchEl = document.getElementById('cat_search');
		var wrapper = document.getElementById('category_wrapper');
		try {
			var json = await cba_apiFetch(API_BASE + '/budget_category/all');
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			CAT_ALL_DATA = json.data;
			searchEl.disabled = false;
			searchEl.placeholder = 'Search category…';
			catSetupSearch();
			// A successful load re-enables the box unconditionally above — re-assert the RFA / LAA Parent CBA
			// lock in case this landed after it (cbaApplyParentCbaLock()).
			cbaApplyParentCbaLock();
		} catch (err) {
			console.error('[loadCategories]', err);
			showError('category_error');
		} finally {
			if (wrapper) wrapper.classList.remove('is-loading');
		}
	}

	// Categories offered in the dropdown — Document Type = COE never uses
	// "Infrastructure", so it is dropped from the list for COE docs. CAT_ALL_DATA
	// itself stays complete (isInfrastructureCategorySelected() and the edit/
	// autofill lookups resolve ids against it).
	function catVisibleData() {
		if (document.getElementById('document_type').value !== 'COE') return CAT_ALL_DATA;
		return CAT_ALL_DATA.filter(function(c) {
			return !catIsInfrastructure(c);
		});
	}

	function catRenderDropdown(data) {
		var dd = document.getElementById('cat_dropdown');
		if (!data.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No results found</div>';
			dd.style.display = 'block';
			return;
		}
		dd.innerHTML = data.map(function(cat) {
			return '<div class="bv-item" data-id="' + pcEsc(cat.budget_category_id) + '" data-name="' + pcEsc(cat.name) + '">' +
				'<div class="bv-item-name">' + pcEsc(cat.name) + '</div>' +
				'</div>';
		}).join('');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				catSelect(parseInt(this.dataset.id, 10), this.dataset.name);
			});
		});
	}

	function catSelect(id, name) {
		var hidden = document.getElementById('category_id');
		hidden.value = id;
		hidden.dataset.label = name;

		document.getElementById('cat_search').value = name;
		document.getElementById('cat_dropdown').style.display = 'none';

		applyProgramProjectVisibilityForDocType();
	}

	// Select a saved category by id (edit mode, "generate from a parent CBA"). Skipped when the id is
	// not in the list (e.g. the category was deleted since), in which case the field stays empty.
	function catSelectById(id) {
		if (id == null || id === '') return;
		var cat = CAT_ALL_DATA.find(function(c) {
			return String(c.budget_category_id) === String(id);
		});
		if (cat) catSelect(cat.budget_category_id, cat.name);
	}

	function catClearSelection() {
		document.getElementById('category_id').value = '';
		applyProgramProjectVisibilityForDocType();
	}

	function catSetupSearch() {
		var searchEl = document.getElementById('cat_search');
		var dd = document.getElementById('cat_dropdown');

		searchEl.addEventListener('input', function() {
			var q = this.value.trim().toLowerCase();
			catClearSelection();
			clearTimeout(CAT_DEBOUNCE);

			if (!q) {
				catRenderDropdown(catVisibleData());
				return;
			}

			CAT_DEBOUNCE = setTimeout(function() {
				var filtered = catVisibleData().filter(function(c) {
					return String(c.name || '').toLowerCase().includes(q);
				});
				catRenderDropdown(filtered);
			}, 150);
		});

		searchEl.addEventListener('focus', function() {
			if (!document.getElementById('category_id').value) {
				catRenderDropdown(catVisibleData());
			}
		});

		searchEl.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// ── Vendor (searchable combobox; a vendor that does not exist can be added) ───
	// GET /vendors/all?active=1  →  [{ vendor_id, vendor_code, vendor_name, vendor_type, address, … }]. The picked
	// id lives in the hidden #vendor_id and is saved as budget_allocation.vendor_id.
	//
	// VEND_MODE:
	//   'none'      nothing picked — only Vendor Name shows.
	//   'existing'  a vendor from the list is picked — its code, type and address show, read-only.
	var VEND_ALL_DATA = [];
	var VEND_DEBOUNCE = null;
	var VEND_SEQ = 0;          // invalidates an in-flight edit-mode lookup (modal reopen)
	var VEND_LIST_MAX = 50;    // rows rendered at once; typing narrows the rest
	var VEND_MODE = 'none';

	function vendEl(id) {
		return document.getElementById(id);
	}

	// 'SERVICE_PROVIDER' → 'Service Provider'
	function vendTypeLabel(code) {
		return String(code == null ? '' : code).toLowerCase().split('_').filter(Boolean).map(function(w) {
			return w.charAt(0).toUpperCase() + w.slice(1);
		}).join(' ');
	}

	async function loadVendors() {
		var searchEl = vendEl('vendor_search');
		var wrapper = vendEl('vendor_wrapper');
		try {
			var json = await cba_apiFetch(API_BASE + '/vendors/all?active=1');
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
				'Unexpected response format');

			VEND_ALL_DATA = json.data;
			searchEl.disabled = false;
			searchEl.placeholder = 'Search vendor…';
			vendSetupSearch();
		} catch (err) {
			console.error('[loadVendors]', err);
			showError('vendor_error');
		} finally {
			if (wrapper) wrapper.classList.remove('is-loading');
		}
	}

	// Shows / hides the read-only Vendor Code, Type and Address fields for whichever vendor (if any) is picked.
	function vendSetMode(mode) {
		VEND_MODE = mode;
		var show = mode === 'existing';
		var code = vendEl('vendor_code');
		var addr = vendEl('vendor_address');
		var typeView = vendEl('vendor_type_view');

		vendEl('vendor_code_group').style.display = show ? '' : 'none';
		vendEl('vendor_type_group').style.display = show ? '' : 'none';
		vendEl('vendor_address_group').style.display = show ? '' : 'none';

		if (!show) {
			code.value = '';
			addr.value = '';
			typeView.value = '';
		}
	}

	function vendFilter(q) {
		if (!q) return VEND_ALL_DATA;
		return VEND_ALL_DATA.filter(function(v) {
			return String(v.vendor_name || '').toLowerCase().includes(q) ||
				String(v.vendor_code || '').toLowerCase().includes(q) ||
				String(v.tin || '').toLowerCase().includes(q);
		});
	}

	// A vendor whose name equals the typed text (case-insensitive) — so typing a name in full counts as
	// picking it, and the same vendor is never added twice.
	function vendFindExact(name) {
		var n = String(name || '').trim().toLowerCase();
		if (!n) return null;
		return VEND_ALL_DATA.find(function(v) {
			return String(v.vendor_name || '').trim().toLowerCase() === n;
		}) || null;
	}

	function vendRenderDropdown(rows) {
		var dd = vendEl('vendor_dropdown');
		if (!rows.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-muted, #62657E);font-size:13px;">No matching vendor</div>';
			dd.style.display = 'block';
			return;
		}
		var shown = rows.slice(0, VEND_LIST_MAX);
		dd.innerHTML = shown.map(function(v) {
			var sub = [v.vendor_code, vendTypeLabel(v.vendor_type)].filter(Boolean).join(' \u00b7 ');
			return '<div class="bv-item" data-id="' + pcEsc(v.vendor_id) + '">' +
				'<div class="bv-item-name">' + pcEsc(v.vendor_name) + '</div>' +
				(sub ? '<div class="bv-item-sub">' + pcEsc(sub) + '</div>' : '') +
				'</div>';
		}).join('') + (rows.length > shown.length ?
			'<div style="padding:8px 14px;color:var(--afims-text-muted, #62657E);font-size:11px;">Showing the first ' +
			shown.length + ' of ' + rows.length + ' \u2014 type to narrow the list</div>' : '');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				var v = VEND_ALL_DATA.find(function(x) {
					return String(x.vendor_id) === item.dataset.id;
				});
				if (v) vendSelectRecord(v);
			});
		});
	}

	function vendSelect(id, name, address, code, type) {
		var hidden = vendEl('vendor_id');
		hidden.value = id;
		hidden.dataset.label = name || '';

		vendEl('vendor_search').value = name || '';
		vendEl('vendor_dropdown').style.display = 'none';
		vendEl('vendor_code').value = code == null ? '' : String(code);
		vendEl('vendor_address').value = address == null ? '' : String(address);
		vendEl('vendor_type_view').value = vendTypeLabel(type);
		vendSetMode('existing');
	}

	function vendSelectRecord(v) {
		vendSelect(v.vendor_id, v.vendor_name, v.address, v.vendor_code, v.vendor_type);
	}

	// Select a saved vendor by id (edit mode, "generate from a parent CBA"). A vendor that is no longer
	// offered in the list (it has since been made inactive) is fetched on its own so the saved record
	// still shows it; if that fails too, the field simply stays empty.
	async function vendSelectById(id) {
		if (id == null || id === '') return;
		var v = VEND_ALL_DATA.find(function(x) {
			return String(x.vendor_id) === String(id);
		});
		if (!v) {
			var seq = ++VEND_SEQ;
			try {
				var json = await cba_apiFetch(API_BASE + '/vendors/read/' + encodeURIComponent(id));
				if (seq !== VEND_SEQ || !json.success || !json.data) return;
				v = json.data;
			} catch (err) {
				console.warn('[vendSelectById]', err);
				return;
			}
		}
		vendSelectRecord(v);
	}

	// The text in Vendor Name was edited: any picked vendor (and the code / type / address that came with it)
	// is withdrawn — Vendor Name is pick-only, so nothing is kept from what's typed until it matches a real one.
	function vendOnType() {
		vendEl('vendor_id').value = '';
		vendSetMode('none');
	}

	// Settle what is typed in Vendor Name when the user is done with it (blur, or at save time): an exact
	// match of an existing vendor (by name) selects it; anything else is left as unpicked typed text (no
	// vendor_id), the same as picking no vendor at all.
	function vendResolveTyped() {
		var name = vendEl('vendor_search').value.trim();
		if (!name || VEND_MODE === 'existing') return;
		var exact = vendFindExact(name);
		if (exact) vendSelectRecord(exact);
	}

	// Called by postToApi before the allocation is saved — settles a typed exact name match into a real pick
	// (see vendResolveTyped()) so clicking straight to Save after typing a full vendor name still works.
	function vendEnsure() {
		vendResolveTyped();
	}

	function vendSetupSearch() {
		var searchEl = vendEl('vendor_search');
		var dd = vendEl('vendor_dropdown');

		searchEl.addEventListener('input', function() {
			var q = this.value.trim().toLowerCase();
			vendOnType();
			clearTimeout(VEND_DEBOUNCE);

			if (!q) {
				vendRenderDropdown(vendFilter(''));
				return;
			}

			VEND_DEBOUNCE = setTimeout(function() {
				if (VEND_MODE === 'existing') return;
				vendRenderDropdown(vendFilter(q));
			}, 150);
		});

		searchEl.addEventListener('focus', function() {
			if (!vendEl('vendor_id').value && !this.value.trim()) {
				vendRenderDropdown(vendFilter(''));
			}
		});

		searchEl.addEventListener('blur', function() {
			clearTimeout(VEND_DEBOUNCE); // a pending search must not reopen the list after the user has left
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
			vendResolveTyped();
		});
	}

	// ── Purchase Request / Purchase Order ────────────────────────────────────────
	// Its Number field lives in the Vendor Details row (vendor_row) — RFA / LAA → Purchase Order Number
	// (po_number); anything else (CBA, CFA, or no type chosen yet — the same default getTypeLabel() uses) →
	// Purchase Request Number (pr_number). Both stay in the form, so switching the type back restores what
	// was typed, but only the one showing is saved. Amount (pr_amount / po_amount) is kept in the DOM, hidden
	// — removed from the UI — see the markup comment above pr_amount.
	function pcrMode() {
		var t = document.getElementById('allocation_type').value;
		return (t === 'RFA' || t === 'LAA') ? 'po' : 'pr';
	}

	function pcrApplyMode() {
		var m = pcrMode();
		document.getElementById('pr_number_group').style.display = m === 'pr' ? '' : 'none';
		document.getElementById('po_number_group').style.display = m === 'po' ? '' : 'none';
	}

	// ── Certified Funds Available (CFA) section ───────────────────────────
	// Shown only for allocation type CFA. Its values feed the CFA print (printCBA → CfaPrintService);
	// they are not saved with the memo request.
	function cfaApplyMode() {
		document.getElementById('cfa_section').style.display =
			document.getElementById('allocation_type').value === 'CFA' ? '' : 'none';
	}

	// One "Recommending Approval" officer row: name + title + remove.
	function cfaAddRecommending() {
		var row = document.createElement('div');
		row.className = 'form-row cfa-rec-row';
		row.innerHTML =
			'<div class="form-group"><input type="text" class="form-input cfa-rec-name" autocomplete="off" placeholder="Name"></div>' +
			'<div class="form-group" style="display:flex;gap:8px;align-items:center;">' +
			'<input type="text" class="form-input cfa-rec-title" autocomplete="off" placeholder="Title / position">' +
			'<button type="button" class="btn btn-secondary" title="Remove" onclick="this.closest(\'.cfa-rec-row\').remove()">&times;</button></div>';
		document.getElementById('cfa_recommending').appendChild(row);
		row.querySelector('.cfa-rec-name').focus();
	}

	// The CFA section's values for the print payload (CfaPrintService field names).
	function cfaPayloadFields() {
		var v = function(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; };
		return {
			for_name: v('cfa_for_name'), for_title: v('cfa_for_title'),
			thru_name: v('cfa_thru_name'), thru_title: v('cfa_thru_title'),
			subject: v('cfa_subject'),
			main_office: v('cfa_main_office'), regional_offices: v('cfa_regional_offices'),
			body_after: v('cfa_body_after'),
			fund_source_name: v('cfa_fund_source'),
			running_title: v('cfa_running_title'),
			requested_by_name: v('cfa_requested_by_name'), requested_by_title: v('cfa_requested_by_title'),
			certified_by_name: v('cfa_certified_by_name'), certified_by_title: v('cfa_certified_by_title'),
			recommending: Array.prototype.map.call(document.querySelectorAll('#cfa_recommending .cfa-rec-row'), function(r) {
				return { name: r.querySelector('.cfa-rec-name').value.trim(), title: r.querySelector('.cfa-rec-title').value.trim() };
			}),
			approved_by_name: v('cfa_approved_by_name'), approved_by_title: v('cfa_approved_by_title'),
		};
	}

	// Request fields for the pair that is showing. A blank value is sent as '' (the service stores NULL), so
	// emptying a field on an existing record clears it.
	function pcrPayloadFields() {
		var m = pcrMode();
		var out = {};
		out[m + '_number'] = document.getElementById(m + '_number').value.trim();
		out[m + '_amount'] = document.getElementById(m + '_amount_raw').value;
		return out;
	}

	// Edit mode: fill both pairs from the saved record (only the one for the allocation type is visible).
	function pcrPopulate(a) {
		document.getElementById('pr_number').value = a.pr_number || '';
		document.getElementById('po_number').value = a.po_number || '';
		['pr', 'po'].forEach(function(k) {
			var n = parseFloat(a[k + '_amount']) || 0;
			document.getElementById(k + '_amount').value = n > 0 ? baaFormatAmt(n) : '';
			document.getElementById(k + '_amount_raw').value = n > 0 ? n.toFixed(2) : '';
		});
	}

	// ── 5. Budget Allocation Amount lines (COB + Requested Amount + Cash Requirements) ──────────────────────
	// One line = a COB and the amounts drawn from it. A COB is an approved budget line item, listed by its
	// Account Code; its Type of Fund isn't chosen separately — picking a COB carries its type of fund along with
	// it (see collectBaaLines()).
	//   GET /budget_line_item/approved[?program_code=&sub_program_code=&project_code=]
	// returns the line items of budget requests whose status is Approved, narrowed to whatever is attached to the
	// Program / Sub-Program / Project chosen above (none chosen → every approved line item). Every line loads its own
	// list and reloads whenever that chain changes (see cobChainChanged). The itemized lines are sent as
	// `line_transaction` (jsonb) — see collectBaaLines() / buildPayload(); their totals feed
	// requested_amount_raw / allocated_amount_raw.
	var BAA_ROWS = []; // ordered array of live line idx values
	var BAA_ROW_SEQ = -1;
	var BAA_ROW_BLOCK = {}; // idx -> the project block (PROJECT_BLOCKS[].id) it belongs to; absent/null for CFA's own single chain
	var COB_DATA = {}; // idx -> approved line items last loaded for that line
	var COB_SIG = {}; // idx -> the program|sub-program|project that list was loaded for
	var COB_SEQ = {}; // idx -> token of the newest request, so an answer that was overtaken is dropped
	var COB_LOAD = {}; // idx -> promise of that line's newest load
	var COB_SEL = {}; // idx -> the picked line item (kept even when it is not in the current list)
	var COB_WIRED = {}; // idx -> search listeners attached
	var BAA_EARMARKS = {}; // idx -> { budgetLineItemId, data } once GET /budget_line_item/{id}/earmarks has loaded
	var COB_EARMARKS_CACHE = {}; // budget_line_item_id -> Promise<earmark[]>, for CBA Available Balance (RFA / LAA)
	var BAA_CBA_AMT_SEQ = {}; // idx -> token of the newest CBA Available Balance resolution, so an answer that was overtaken is dropped
	var COB_EPOCH = 0; // bumped when the modal form is reset, so requests from the old DOM are dropped
	var COB_KEEP_SELECTION = 0; // >0 while a saved record's own chain is being filled in: a reload must not clear its picks
	var COB_SYNC_QUEUED = false;
	var COB_TOKEN_SEQ = 0;
	var COB_LIST_MAX = 50; // rows rendered at once; typing narrows the rest
	// A stack of account-code rows removed by mistake, per scope — a project block's own id, or BAA_CFA_KEY
	// for CFA's single chain (which has no block id) — so "Restore Removed Account Code" (baaRestoreRemovedLine)
	// can bring the most recent one back with its own COB and amounts. See baaStashRemovedLine().
	var BAA_REMOVED = {};
	var BAA_CFA_KEY = '__cfa__';

	// ── 4b. Funding Allocations (CBA / RFA / LAA only) — one or more independent project blocks, each with its
	// own Project pick (searchable across every project, same "load once, filter client-side" shape as the
	// Category / Vendor comboboxes elsewhere in this file) plus its own COB / Amount line(s)
	// (addBudgetAllocationLine(blockId), reused as-is below). CFA (and "no type chosen yet") keeps the single
	// Program → Sub-Program → Project chain above (PCHAIN) untouched — see switchToBlockMode() /
	// switchToSingleMode() (cbaApplyAllocationTypeVisibility()).
	var PROJECT_BLOCKS = []; // ordered array of { id, projectCode, projectName, programCode, programName, subProgramCode, subProgramName, budgetAllocationProjectId }
	var PROJECT_BLOCK_SEQ = -1;
	// CFA (and "no type chosen yet") in edit mode: the stored record's one project block's own
	// budget_allocation_project_id, echoed back by buildPayload() so update() edits that block in place
	// instead of soft-deleting it and inserting a new one. Null for a brand-new record.
	var EDIT_SINGLE_BLOCK_ID = null;
	var PROJ_ALL_DATA = [];
	var PROJ_LOADED = false;
	var PROJ_LOAD_PROMISE = null;
	// CBA only — the logged-in user's own-RC projects (GET /project/all?scope=mine, filtered server-side
	// against fims.project.rc_id — the creator's RC, set in ProjectService::create()). Drives ONLY the live
	// "Search project…" dropdown (blockProjAvailableData()) for picking a NEW project. Resolving an
	// already-saved project_code (edit-mode restore, or an RFA/LAA block autofilling from a Parent CBA that
	// may have been created under a different RC) still goes through the unscoped PROJ_ALL_DATA below —
	// otherwise a cross-RC record would silently come up with an empty Project field.
	var PROJ_MINE_DATA = [];
	var PROJ_MINE_LOADED = false;
	var PROJ_MINE_LOAD_PROMISE = null;

	async function loadAllProjects() {
		if (PROJ_LOADED) return;
		if (PROJ_LOAD_PROMISE) return PROJ_LOAD_PROMISE;
		PROJ_LOAD_PROMISE = (async function() {
			try {
				var json = await cba_apiFetch(API_BASE + '/project/all');
				PROJ_ALL_DATA = (json && json.success && Array.isArray(json.data)) ? json.data : [];
				PROJ_LOADED = true;
			} catch (err) {
				console.error('[loadAllProjects]', err);
				PROJ_ALL_DATA = [];
			}
		})();
		return PROJ_LOAD_PROMISE;
	}

	async function loadMyProjects() {
		if (PROJ_MINE_LOADED) return;
		if (PROJ_MINE_LOAD_PROMISE) return PROJ_MINE_LOAD_PROMISE;
		PROJ_MINE_LOAD_PROMISE = (async function() {
			try {
				var json = await cba_apiFetch(API_BASE + '/project/all?scope=mine');
				PROJ_MINE_DATA = (json && json.success && Array.isArray(json.data)) ? json.data : [];
				PROJ_MINE_LOADED = true;
			} catch (err) {
				console.error('[loadMyProjects]', err);
				PROJ_MINE_DATA = [];
			}
		})();
		return PROJ_MINE_LOAD_PROMISE;
	}

	function projectBlockById(id) {
		return PROJECT_BLOCKS.find(function(b) { return b.id === id; }) || null;
	}

	// What a project block's own search field picks, by type — CBA searches a Project directly; RFA/LAA
	// searches an approved CBA instead (which then resolves to ITS project) — see blockSetupSearch(). A
	// direct COE draw (RFA/LAA only — see blockIsDirectDraw()) has no search field of its own at all, so
	// this is never consulted for one (projectBlockTemplate()/blockSetupSearch() branch on directCob first).
	function blockFieldNoun() {
		return isRfaOrLaa() ? 'Parent CBA' : 'Project';
	}

	function blockFieldPlaceholder() {
		return isRfaOrLaa() ? 'Search approved CBA by ref no. or program…' : 'Search project…';
	}

	// Whether one project block is a direct COE draw (no Parent CBA, no Project — see
	// BudgetLineItemService::createDirectEarmarkForRfaApproval()'s own doc comment).
	function blockIsDirectDraw(blockId) {
		var b = projectBlockById(blockId);
		return !!(b && b.directCob);
	}

	// Whether one Budget Allocation Amount line belongs to a direct-draw block — used wherever a line
	// would otherwise assume it has a Parent CBA (its ceiling, ancestor's lock, error text).
	function lineIsDirectDraw(idx) {
		var blockId = BAA_ROW_BLOCK[idx];
		return blockId != null && blockIsDirectDraw(blockId);
	}

	function projectBlockTemplate(blockId, label, placeholder, directCob) {
		if (directCob) {
			return '' +
				'<div class="project-block" data-block-id="' + blockId + '" data-direct="1">' +
				'<button type="button" class="project-block-remove" title="Remove this project">&times;</button>' +
				'<div class="form-group">' +
				'<label class="form-label">' + pcEsc(label) + '</label>' +
				'<p class="input-hint">A COE account code picked directly below — no Parent CBA, no Project.</p>' +
				'</div>' +
				'<input type="hidden" id="project_code_' + blockId + '" value="">' +
				'<div id="baa_lines_' + blockId + '"></div>' +
				'<button type="button" class="btn btn-secondary baa-restore-line-btn" id="restore_line_btn_' + blockId +
				'" style="margin:4px 0 16px;display:none;">&#8634; Restore Removed Account Code</button>' +
				'</div>';
		}

		var parentCbaField = '' +
			'<div class="form-group">' +
			'<label for="proj_search_' + blockId + '" class="form-label">' + pcEsc(label) + ' <span class="required">*</span></label>' +
			'<div style="position:relative;">' +
			'<input type="text" id="proj_search_' + blockId + '" class="form-input" placeholder="' + pcEsc(placeholder) + '" autocomplete="off" required>' +
			'<div id="proj_dropdown_' + blockId + '" class="pc-dropdown"></div>' +
			'</div>' +
			'<input type="hidden" id="project_code_' + blockId + '" value="">' +
			'</div>';

		// RFA/LAA only: which of the Parent CBA's own project(s) this block draws against — usually a single,
		// disabled option (a CBA has exactly one project, going forward); an enabled, real choice only for a
		// CBA saved before that restriction existed — see populateBlockProjectChoice(). Not rendered for CBA,
		// whose own search field already IS the project.
		var projectChoiceField = isRfaOrLaa() ? '' +
			'<div class="form-group">' +
			'<label for="project_choice_' + blockId + '" class="form-label">Project <span class="required">*</span></label>' +
			'<select id="project_choice_' + blockId + '" class="form-select" disabled required></select>' +
			'<p class="input-hint" id="project_choice_hint_' + blockId + '">Pick a Parent CBA first.</p>' +
			'</div>' : '';

		return '' +
			'<div class="project-block" data-block-id="' + blockId + '">' +
			'<button type="button" class="project-block-remove" title="Remove this project">&times;</button>' +
			'<div class="' + (isRfaOrLaa() ? 'form-row' : 'form-row full-width') + '">' +
			parentCbaField +
			projectChoiceField +
			'</div>' +
			'<div id="baa_lines_' + blockId + '"></div>' +
			// Lets the office bring back an account-code row it removed from THIS block by mistake — same
			// COB, same amounts. Hidden whenever there's nothing removed to restore — see
			// updateRestoreButtonVisibility() / baaStashRemovedLine().
			'<button type="button" class="btn btn-secondary baa-restore-line-btn" id="restore_line_btn_' + blockId +
			'" style="margin:4px 0 16px;display:none;">&#8634; Restore Removed Account Code</button>' +
			'</div>';
	}

	// Re-labels every block "Project 1" / "Parent CBA 1", … by its current position (removing one in the
	// middle renumbers the rest) and shows/hides each block's own remove button. CBA always keeps exactly
	// one block (cbaBlocksAreParentDriven() is false for it) — its button never shows. RFA/LAA blocks
	// (Parent-CBA-driven or direct — see blockIsDirectDraw()) may be freely removed, even down to the last
	// one: there's no "always keep at least one" invariant for them the way there is for CBA — an empty
	// result is simply caught by cbaOnSubmit()'s own "Parent CBA Required" check at submit time.
	function renumberProjectBlocks() {
		var hideRemove = !cbaBlocksAreParentDriven() && PROJECT_BLOCKS.length <= 1;
		var noun = blockFieldNoun();
		PROJECT_BLOCKS.forEach(function(b, i) {
			var blockEl = document.querySelector('.project-block[data-block-id="' + b.id + '"]');
			if (!blockEl) return;
			var label = blockEl.querySelector('.form-label');
			if (label) {
				label.innerHTML = b.directCob ?
					('Direct COE Account ' + (i + 1)) :
					(noun + ' ' + (i + 1) + ' <span class="required">*</span>');
			}
			var removeBtn = blockEl.querySelector('.project-block-remove');
			if (removeBtn) removeBtn.style.visibility = hideRemove ? 'hidden' : 'visible';
		});
	}

	// Dispatches a block's search field wiring by type — see blockFieldNoun()'s own comment. A direct draw
	// has no search field at all (projectBlockTemplate()) — nothing to wire.
	function blockSetupSearch(blockId) {
		if (blockIsDirectDraw(blockId)) return;
		if (isRfaOrLaa()) blockCbaSetupSearch(blockId); else blockProjSetupSearch(blockId);
	}

	// $directCob (RFA/LAA only — see blockIsDirectDraw()): a direct COE draw block, with no Parent CBA /
	// Project search at all — just straight to its own COB line(s) (addBudgetAllocationLine() below).
	async function addProjectBlock(directCob) {
		directCob = !!directCob;
		var blockId = ++PROJECT_BLOCK_SEQ;
		PROJECT_BLOCKS.push({
			id: blockId, projectCode: null, projectName: null,
			programCode: null, programName: null, subProgramCode: null, subProgramName: null,
			budgetAllocationProjectId: null, parentAllocationId: null, directCob: directCob
		});

		var container = document.getElementById('project_blocks_container');
		var holder = document.createElement('div');
		holder.innerHTML = projectBlockTemplate(blockId,
			directCob ? ('Direct COE Account ' + PROJECT_BLOCKS.length) : (blockFieldNoun() + ' ' + PROJECT_BLOCKS.length),
			blockFieldPlaceholder(), directCob);
		var blockEl = holder.firstElementChild;
		container.appendChild(blockEl);

		blockEl.querySelector('.project-block-remove').addEventListener('click', function() {
			removeProjectBlock(blockId);
		});
		blockEl.querySelector('.baa-restore-line-btn').addEventListener('click', function() {
			baaRestoreRemovedLine(blockId);
		});

		if (directCob) {
			// Nothing to load — no Parent CBA / Project list for a direct draw.
		} else if (isRfaOrLaa()) {
			await ensureCbaDataLoaded();
		} else {
			await loadMyProjects();
		}
		blockSetupSearch(blockId);
		addBudgetAllocationLine(blockId);
		renumberProjectBlocks();
		cbaUpdateAddBlockButtonsVisibility();
		cbaApplyVendorRequirement();
		return blockId;
	}

	// $force bypasses the "CBA always keeps exactly one project" guard — used only when switching the whole
	// form out of block mode (switchToSingleMode()), which removes every block deliberately. RFA/LAA has no
	// such guard at all (see renumberProjectBlocks()) — its visible "×" button is never hidden just for
	// being the last one, so this check never even applies to it in practice (cbaBlocksAreParentDriven()
	// is false only for CBA, the one type that must always keep its one block).
	function removeProjectBlock(blockId, force) {
		if (!force && !cbaBlocksAreParentDriven() && PROJECT_BLOCKS.length <= 1) return;
		BAA_ROWS.filter(function(idx) { return BAA_ROW_BLOCK[idx] === blockId; }).slice().forEach(function(idx) {
			baaHardRemoveLine(idx);
		});
		var blockEl = document.querySelector('.project-block[data-block-id="' + blockId + '"]');
		if (blockEl) blockEl.remove();
		PROJECT_BLOCKS = PROJECT_BLOCKS.filter(function(b) { return b.id !== blockId; });
		delete BAA_REMOVED[blockId]; // nothing left to restore into a block that no longer exists
		renumberProjectBlocks();
		recomputeBaaTotals();
		cbaUpdateAddBlockButtonsVisibility();
		cbaApplyVendorRequirement();
	}

	function blockProjRenderDropdown(blockId, data) {
		var dd = document.getElementById('proj_dropdown_' + blockId);
		if (!dd) return;
		if (!data.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No results found</div>';
			dd.style.display = 'block';
			return;
		}
		var shown = data.slice(0, COB_LIST_MAX);
		dd.innerHTML = shown.map(function(p) {
			return '<div class="bv-item" data-id="' + pcEsc(p.project_code) + '">' +
				'<div class="bv-item-name">' + pcEsc(p.project_name || p.project_code) + '</div>' +
				(p.program_name ? '<div class="bv-item-sub">' + pcEsc(p.program_name) +
					(p.sub_program_name ? ' / ' + pcEsc(p.sub_program_name) : '') + '</div>' : '') +
				'</div>';
		}).join('') + (data.length > shown.length ?
			'<div style="padding:8px 14px;color:var(--afims-text-muted, #62657E);font-size:11px;">Showing the first ' +
			shown.length + ' of ' + data.length + ' — type to narrow the list</div>' : '');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				var proj = data.find(function(p) { return String(p.project_code) === item.dataset.id; });
				if (proj) blockProjSelect(blockId, proj);
			});
		});
	}

	function blockProjSelect(blockId, proj) {
		var b = projectBlockById(blockId);
		if (!b) return;
		b.projectCode = proj.project_code;
		b.projectName = proj.project_name || proj.project_code;
		b.programCode = proj.program_code || null;
		b.programName = proj.program_name || null;
		b.subProgramCode = proj.sub_program_code || null;
		b.subProgramName = proj.sub_program_name || null;

		var hidden = document.getElementById('project_code_' + blockId);
		if (hidden) hidden.value = proj.project_code;
		var search = document.getElementById('proj_search_' + blockId);
		if (search) search.value = b.projectName;
		var dd = document.getElementById('proj_dropdown_' + blockId);
		if (dd) dd.style.display = 'none';

		// Fiscal year is still one record-level field — derived from the first block's project, same as the
		// single-chain flow already did for CBA / RFA / LAA before this.
		if (PROJECT_BLOCKS[0] && PROJECT_BLOCKS[0].id === blockId) pcDeriveFiscalYearFromProject(proj.project_code);

		cobChainChangedForBlock(blockId).then(function() {
			return autoExpandBlockCobLines(blockId);
		});
	}

	function blockProjClearSelection(blockId) {
		var b = projectBlockById(blockId);
		if (b) {
			b.projectCode = null; b.projectName = null;
			b.programCode = null; b.programName = null;
			b.subProgramCode = null; b.subProgramName = null;
		}
		var hidden = document.getElementById('project_code_' + blockId);
		if (hidden) hidden.value = '';
	}

	// PROJ_MINE_DATA (the logged-in user's own-RC projects — see loadMyProjects()) minus whatever project
	// every OTHER block already picked — the same project can only be allocated to one block at a time.
	// (This block's own current pick, if any, stays offered — so reopening the dropdown to change it still
	// shows it.)
	function blockProjAvailableData(blockId) {
		var usedCodes = PROJECT_BLOCKS.filter(function(b) {
			return b.id !== blockId && b.projectCode;
		}).map(function(b) {
			return b.projectCode;
		});
		if (!usedCodes.length) return PROJ_MINE_DATA;

		return PROJ_MINE_DATA.filter(function(p) {
			return usedCodes.indexOf(p.project_code) === -1;
		});
	}

	function blockProjSetupSearch(blockId) {
		var searchEl = document.getElementById('proj_search_' + blockId);
		var dd = document.getElementById('proj_dropdown_' + blockId);
		if (!searchEl || !dd) return;

		searchEl.addEventListener('input', function() {
			var q = this.value.trim().toLowerCase();
			blockProjClearSelection(blockId);
			cobChainChangedForBlock(blockId);

			var available = blockProjAvailableData(blockId);
			var filtered = !q ? available : available.filter(function(p) {
				return String(p.project_name || '').toLowerCase().includes(q) ||
					String(p.project_code || '').toLowerCase().includes(q);
			});
			blockProjRenderDropdown(blockId, filtered);
		});

		searchEl.addEventListener('focus', function() {
			var hidden = document.getElementById('project_code_' + blockId);
			if (!hidden || !hidden.value) blockProjRenderDropdown(blockId, blockProjAvailableData(blockId));
		});

		searchEl.addEventListener('blur', function() {
			setTimeout(function() { dd.style.display = 'none'; }, 150);
		});
	}

	// Select a saved project by code (edit mode). Skipped when the code is not in the list (e.g. the project
	// was deleted since), in which case the field stays empty.
	function blockProjSelectById(blockId, code) {
		if (code == null || code === '') return;
		var proj = PROJ_ALL_DATA.find(function(p) { return String(p.project_code) === String(code); });
		if (proj) blockProjSelect(blockId, proj);
	}

	// Reloads only the COB lists of the lines belonging to this one block — a project pick in one block must
	// never reload every other block's (or CFA's) lines. Returns a promise resolving once every one of them has
	// (so callers can auto-expand once the real list is in, see autoExpandBlockCobLines()).
	function cobChainChangedForBlock(blockId) {
		var keep = COB_KEEP_SELECTION > 0;
		return Promise.all(BAA_ROWS.filter(function(idx) {
			return BAA_ROW_BLOCK[idx] === blockId;
		}).map(function(idx) {
			return cobLoad(idx, keep);
		}));
	}

	// A freshly-added project block starts with exactly one (empty) COB line. The moment its Project is picked
	// and that line's COB list comes back, this expands it to one line per approved COB available for that
	// project — pre-selected — instead of leaving the office to search/add them one at a time: a project with 4
	// approved COBs shows 4 rows right away. Never runs during edit-mode restore (COB_KEEP_SELECTION > 0 —
	// restoreProjectBlocksFromSaved() builds the exact saved set of lines itself) or once the block already has
	// more than its one starting line (the user has since added/removed lines by hand — leave their work alone).
	async function autoExpandBlockCobLines(blockId) {
		if (COB_KEEP_SELECTION > 0) return;
		var idxs = BAA_ROWS.filter(function(i) { return BAA_ROW_BLOCK[i] === blockId; });
		if (idxs.length !== 1) return;
		var idx = idxs[0];
		if (COB_SEL[idx]) return;
		var data = COB_DATA[idx] || [];
		if (data.length <= 1) return;

		cobSelect(idx, data[0]);
		for (var i = 1; i < data.length; i++) {
			var newIdx = addBudgetAllocationLine(blockId);
			await cobLoad(newIdx);
			cobSelect(newIdx, data[i]);
		}
		updateBaaRemoveButtons();
		recomputeBaaTotals();
	}

	// Edit mode (populateEditForm()): rebuilds PROJECT_BLOCKS from a saved record's `projects` — adding or
	// removing blocks (addProjectBlock() / removeProjectBlock()) until there's exactly one per saved block,
	// then filling each one in (its Project pick, its own COB lines, and — RFA/LAA — its own
	// parentAllocationId, just below) in order. For RFA/LAA, each block's search field still shows the
	// PROJECT's name at this point — restoreBlockParentLabels(), called right after this returns, overwrites
	// it with that block's own Parent CBA label instead (and shows the resolved project as a small note).
	async function restoreProjectBlocksFromSaved(savedBlocks) {
		savedBlocks = Array.isArray(savedBlocks) ? savedBlocks : [];
		if (!savedBlocks.length) return;

		// The levels are filled in by code here, not chosen by the user: the COBs already picked belong to this
		// block, so the reloads it triggers must not clear them (same precedent as cbaSelectProgramChain()).
		COB_KEEP_SELECTION++;
		try {
			// A direct-draw block's template has no Parent CBA / Project search field at all (see
			// projectBlockTemplate()) — rebuilding from scratch, rather than reusing whatever
			// switchToBlockMode() auto-seeded (always a non-direct block) or a stale prior block, guarantees
			// every index gets the template that matches its saved is_direct_draw, with no mismatch to patch up.
			PROJECT_BLOCKS.slice().forEach(function(b) { removeProjectBlock(b.id, true); });
			for (var bi = 0; bi < savedBlocks.length; bi++) {
				await addProjectBlock(!!savedBlocks[bi].is_direct_draw);
			}

			for (var i = 0; i < savedBlocks.length; i++) {
				var saved = savedBlocks[i];
				var blockId = PROJECT_BLOCKS[i].id;
				PROJECT_BLOCKS[i].budgetAllocationProjectId = saved.budget_allocation_project_id != null ?
					parseInt(saved.budget_allocation_project_id, 10) : null;
				PROJECT_BLOCKS[i].directCob = !!saved.is_direct_draw;
				// RFA / LAA only — which Parent CBA this block draws from (restoreBlockParentLabels(), called
				// right after this function returns, uses it plus the record's own `parents` array to show
				// each block's own Parent CBA label in its search field).
				PROJECT_BLOCKS[i].parentAllocationId = saved.parent_allocation_id != null ?
					parseInt(saved.parent_allocation_id, 10) : null;

				await loadAllProjects();
				blockProjSelectById(blockId, saved.project_code);

				var savedLines = Array.isArray(saved.line_transaction) ? saved.line_transaction : [];
				var blockLineIdxs = BAA_ROWS.filter(function(idx) { return BAA_ROW_BLOCK[idx] === blockId; });
				while (blockLineIdxs.length < savedLines.length) {
					blockLineIdxs.push(addBudgetAllocationLine(blockId));
				}
				for (var li = 0; li < savedLines.length; li++) {
					var line = savedLines[li];
					var idx = blockLineIdxs[li];
					if (idx === undefined) continue;

					baaSetAmount(idx, 'requested', line.requested_amount);
					baaSetAmount(idx, 'allocated', line.allocated_amount);
					await cobRestoreLine(idx, line);
				}
			}
		} finally {
			COB_KEEP_SELECTION--;
		}

		renumberProjectBlocks();
		updateBaaRemoveButtons();
		recomputeBaaTotals();
	}

	// RFA / LAA edit-mode restore: shows each block's own Parent CBA label in its search field
	// (restoreProjectBlocksFromSaved() left it showing the project's name instead) and populates/pre-selects
	// its Project choice select from that same CBA's own resolved project blocks — restoreProjectBlocksFromSaved()
	// has already set each PROJECT_BLOCKS entry's own parentAllocationId (and projectCode) by the time this
	// runs. Never re-runs any autofill: every field is already exactly as saved.
	async function restoreBlockParentLabels(parents) {
		var byId = {};
		(Array.isArray(parents) ? parents : []).forEach(function(p) { byId[p.allocation_id] = p; });

		await ensureCbaDataLoaded();

		for (var i = 0; i < PROJECT_BLOCKS.length; i++) {
			var b = PROJECT_BLOCKS[i];
			if (b.parentAllocationId == null) continue;
			var p = byId[b.parentAllocationId];
			var label = p ? (p.allocation_number || p.request_referrence_no || ('#' + b.parentAllocationId)) : ('#' + b.parentAllocationId);
			var search = document.getElementById('proj_search_' + b.id);
			if (search) search.value = label;

			// Populate the Project choice too, matched back to the SAVED project_code — so an existing record
			// linked to a now-multi-project-looking CBA still shows which one it actually drew from, without
			// requiring the user to re-pick it.
			var cba = CBA_DATA.find(function(c) { return c.allocation_id === b.parentAllocationId; });
			if (!cba) continue;
			var parentBlocks = await cbaResolveAllParentBlocks(cba);
			populateBlockProjectChoice(b.id, parentBlocks);
			var savedIdx = parentBlocks.findIndex(function(pb) { return pb.project_code === b.projectCode; });
			var sel = document.getElementById('project_choice_' + b.id);
			if (sel && savedIdx >= 0) sel.value = String(savedIdx);
		}
	}

	function cobEl(part, idx) {
		return document.getElementById(part + '_' + idx);
	}

	// The Program / Sub-Program / Project chosen above (CFA's single shared chain).
	function cobChainSignature() {
		return [pcValue('prog'), pcValue('subprog'), pcValue('proj')].join('|');
	}

	// What a line's COB list depends on. A block-scoped line (BAA_ROW_BLOCK[idx] set — CBA / RFA / LAA) depends
	// only on ITS OWN block's Project; every other line shares the single chain above (CFA), there being nothing
	// else left to narrow it by.
	function cobSignature(idx) {
		var blockId = BAA_ROW_BLOCK[idx];
		if (blockId != null) {
			var b = projectBlockById(blockId);
			return 'block:' + blockId + ':' + (b ? b.projectCode : null);
		}

		return cobChainSignature();
	}

	// The account code and title — what the search box shows once a COB is picked; its Type of Fund travels with it
	// (see collectBaaLines()) rather than being chosen separately.
	function cobLabel(r) {
		if (!r.account_code) return 'Line item #' + r.budget_line_item_id;
		return r.account_title ? r.account_code + ' - ' + r.account_title : r.account_code;
	}

	function cobAmount(v) {
		return (v == null || v === '') ? '—' : '₱' + baaFormatAmt(parseFloat(v) || 0);
	}

	// A COB's Available Amount is the derived ceiling GET /budget_line_item/approved sends as `available_amount`
	// (BudgetLineBalanceSql::availableExpr() — suggested_amount + adjustment, less what is still reserved and
	// already drawn) — never proposed_amount (only what the office first asked for), and no longer suggested_amount
	// itself (that column is now a stable, never-depleted figure — see BudgetLineItemService's Earmarks section
	// header comment). Falls back to suggested_amount for a snapshot saved before this field existed, where
	// suggested_amount WAS the live ceiling — a historical value, not a live one, but the best available.
	// '' when the record has neither (a saved line whose COB has since left the approved list).
	function cobAvailable(rec) {
		if (!rec) return '';
		if (rec.available_amount != null && rec.available_amount !== '') return rec.available_amount;
		return rec.suggested_amount != null && rec.suggested_amount !== '' ? rec.suggested_amount : '';
	}

	function baaRowTemplate(idx) {
		return '' +
			'<div class="baa-line form-row cols-baa" data-idx="' + idx + '">' +
			'<div class="form-group baa-bv-group">' +
			'<label for="bv_search_' + idx + '" class="form-label">' +
			'<span id="bv_field_label_' + idx + '">Account Code</span>' +
			' <span class="required baa-bv-required" id="bv_required_mark_' + idx + '" style="display:none;">*</span>' +
			'</label>' +
			'<div style="position:relative;">' +
			'<input type="text" id="bv_search_' + idx + '" class="form-input baa-bv-search" data-idx="' + idx + '" ' +
			'placeholder="Loading COBs&hellip;" autocomplete="off" disabled>' +
			'<div id="bv_dropdown_' + idx + '" class="baa-bv-dropdown" ' +
			'style="display:none;position:absolute;top:100%;left:0;right:0;z-index:200;' +
			'background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-top:none;' +
			'border-radius:0 0 6px 6px;max-height:220px;overflow-y:auto;' +
			'box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>' +
			'</div>' +
			'<input type="hidden" id="budget_line_item_id_' + idx + '" value="">' +
			'<p class="input-hint" id="cob_hint_' + idx + '"></p>' +
			'</div>' +
			'<div class="form-group has-prefix baa-proposed-group" id="proposed_amount_group_' + idx + '">' +
			'<label for="proposed_amount_' + idx + '" class="form-label">Available Amount</label>' +
			'<span class="input-prefix">&#8369;</span>' +
			'<input type="text" id="proposed_amount_' + idx +
			'" class="form-input amt-input baa-proposed-amount" data-idx="' + idx +
			'" placeholder="0.00" disabled readonly autocomplete="off">' +
			'</div>' +
			// RFA / LAA only (see baaApplyCbaAmountVisibility()) — the parent CBA's own requested amount for
			// whichever COB is picked on this line, not the COB's general available_amount: an RFA/LAA draws
			// against what its specific parent CBA set aside, not the COB's wider leftover balance.
			'<div class="form-group has-prefix baa-cba-amount-group" id="cba_amount_group_' + idx + '" style="display:none;">' +
			'<label for="cba_amount_' + idx + '" class="form-label">CBA Available Balance</label>' +
			'<span class="input-prefix">&#8369;</span>' +
			'<input type="text" id="cba_amount_' + idx +
			'" class="form-input amt-input baa-cba-amount" data-idx="' + idx +
			'" placeholder="0.00" disabled readonly autocomplete="off">' +
			'</div>' +
			'<div class="form-group has-prefix baa-req-group">' +
			'<label for="requested_amount_' + idx +
			'" class="form-label">Requested Amount <span class="required">*</span></label>' +
			// .input-prefix is bottom-anchored (position:absolute;bottom:0) against its nearest positioned
			// ancestor — wrapping just the prefix + input here keeps that anchor at the input's own bottom edge,
			// so the error message below (a sibling, outside this wrapper) doesn't pull it down when shown.
			'<div style="position:relative;">' +
			'<span class="input-prefix">&#8369;</span>' +
			'<input type="text" id="requested_amount_' + idx +
			'" class="form-input amt-input baa-requested-amount" data-idx="' + idx +
			'" placeholder="0.00" required autocomplete="off">' +
			'<input type="hidden" id="requested_amount_' + idx + '_raw" value="">' +
			'</div>' +
			'<p class="error-box" id="requested_amount_' + idx +
			'_error">Requested Amount must not exceed the Available Amount.</p>' +
			'</div>' +
			'<div class="form-group has-prefix baa-cash-group">' +
			'<label for="allocated_amount_' + idx + '" class="form-label">Cash Requirements</label>' +
			'<span class="input-prefix">&#8369;</span>' +
			'<input type="text" id="allocated_amount_' + idx +
			'" class="form-input amt-input baa-allocated-amount" data-idx="' + idx +
			'" placeholder="0.00" autocomplete="off">' +
			'<input type="hidden" id="allocated_amount_' + idx + '_raw" value="">' +
			'</div>' +
			'<button type="button" class="baa-remove-line" data-idx="' + idx +
			'" title="Remove this budget allocation">&times;</button>' +
			'<div class="baa-earmarks" id="baa_earmarks_' + idx + '" style="display:none;">' +
			'<button type="button" class="baa-earmarks-toggle" id="baa_earmarks_toggle_' + idx + '">' +
			'<svg class="baa-earmarks-caret" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">' +
			'<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>' +
			'<span>CBA Issued</span>' +
			'</button>' +
			'<div class="baa-earmarks-body" id="baa_earmarks_body_' + idx + '" style="display:none;"></div>' +
			'</div>' +
			'</div>';
	}

	// $blockId: the project block (PROJECT_BLOCKS[].id) this line belongs to — appended into that block's own
	// #baa_lines_<id> container instead of the shared #baa_lines_container, and tagged in BAA_ROW_BLOCK so
	// cobSignature() / cobQuery() / cobRequiresProjectFirst() resolve its own project instead of the single
	// shared chain. Omitted (undefined) for CFA's own single-chain lines — unchanged.
	function addBudgetAllocationLine(blockId) {
		var idx = ++BAA_ROW_SEQ;
		BAA_ROWS.push(idx);
		if (blockId != null) BAA_ROW_BLOCK[idx] = blockId;

		var container = blockId != null ? document.getElementById('baa_lines_' + blockId) : document.getElementById('baa_lines_container');
		var holder = document.createElement('div');
		holder.innerHTML = baaRowTemplate(idx);
		var rowEl = holder.firstElementChild;
		container.appendChild(rowEl);

		wireAmtInput(document.getElementById('requested_amount_' + idx));
		wireAmtInput(document.getElementById('allocated_amount_' + idx));
		var reqAmtEl = document.getElementById('requested_amount_' + idx);
		if (reqAmtEl) {
			reqAmtEl.addEventListener('input', function() {
				baaValidateRequestedAmount(idx);
			});
			reqAmtEl.addEventListener('blur', function() {
				baaValidateRequestedAmount(idx);
			});
		}
		rowEl.querySelector('.baa-remove-line').addEventListener('click', function() {
			removeBudgetAllocationLine(idx);
		});
		var earmarksToggle = document.getElementById('baa_earmarks_toggle_' + idx);
		if (earmarksToggle) {
			earmarksToggle.addEventListener('click', function() {
				baaToggleEarmarks(idx);
			});
		}

		// Keep a newly-added row consistent with the current doc/allocation type.
		baaApplyCashRequirementsVisibility();
		baaApplyCbaAmountVisibility(idx);
		applyBudgetVersionVisibilityForDocType();
		updateBaaRemoveButtons();
		recomputeBaaTotals();
		cobLoad(idx);
		return idx;
	}

	// The actual teardown, no "keep at least one" guard — removeBudgetAllocationLine() below applies that guard
	// (per-block when the line belongs to one); switchToBlockMode() / removeProjectBlock() call this directly
	// when they are deliberately clearing every line of a block (or every legacy line) to rebuild from scratch.
	function baaHardRemoveLine(idx) {
		var rowEl = document.querySelector('.baa-line[data-idx="' + idx + '"]');
		if (rowEl) rowEl.remove();
		BAA_ROWS = BAA_ROWS.filter(function(i) {
			return i !== idx;
		});
		delete COB_DATA[idx];
		delete COB_SIG[idx];
		delete COB_SEQ[idx];
		delete COB_LOAD[idx];
		delete COB_SEL[idx];
		delete COB_WIRED[idx];
		delete BAA_ROW_BLOCK[idx];
	}

	function removeBudgetAllocationLine(idx) {
		var blockId = BAA_ROW_BLOCK[idx];
		if (blockId != null) {
			var siblings = BAA_ROWS.filter(function(i) { return BAA_ROW_BLOCK[i] === blockId; });
			if (siblings.length <= 1) return; // always keep at least one line per project block
		} else if (BAA_ROWS.length <= 1) {
			return; // CFA's single chain: always keep at least one line
		}
		baaStashRemovedLine(idx, blockId);
		baaHardRemoveLine(idx);
		updateBaaRemoveButtons();
		recomputeBaaTotals();
		baaRelocateFirstLine(); // the line removed may have been the one merged in beside Project
		baaApplyHeaderVisibility();
	}

	// Snapshots a line's COB pick and amounts right before removeBudgetAllocationLine() tears it down, so
	// "Restore Removed Account Code" (baaRestoreRemovedLine) can bring it back later. A blank row — nothing
	// picked yet — isn't tracked: there'd be nothing worth restoring.
	function baaStashRemovedLine(idx, blockId) {
		var rec = COB_SEL[idx];
		if (!rec) return;
		var key = blockId == null ? BAA_CFA_KEY : blockId;
		var reqEl = document.getElementById('requested_amount_' + idx + '_raw');
		var allocEl = document.getElementById('allocated_amount_' + idx + '_raw');
		(BAA_REMOVED[key] = BAA_REMOVED[key] || []).push({
			cobId: rec.budget_line_item_id,
			cobSnapshot: {
				type_of_fund_id: rec.type_of_fund_id,
				type_of_fund: rec.type_of_fund,
				account_code: rec.account_code,
				account_title: rec.account_title,
				fiscal_yr: rec.fiscal_yr,
				proposed_amount: rec.proposed_amount,
				suggested_amount: rec.suggested_amount,
				available_amount: rec.available_amount
			},
			requested: parseFloat(reqEl ? reqEl.value : '') || 0,
			allocated: parseFloat(allocEl ? allocEl.value : '') || 0
		});
		updateRestoreButtonVisibility(key);
	}

	// Brings back the most recently removed account code for this scope (a project block's id, or null for
	// CFA's single chain) as a new line — same COB re-selected (cobSelectById falls back to the snapshot if
	// that COB has since left the approved list), same Requested/Cash amounts. Does nothing once the stack is
	// empty — the button is hidden then anyway (updateRestoreButtonVisibility).
	async function baaRestoreRemovedLine(blockId) {
		var key = blockId == null ? BAA_CFA_KEY : blockId;
		var stack = BAA_REMOVED[key];
		if (!stack || !stack.length) return;
		var snapshot = stack.pop();
		var idx = addBudgetAllocationLine(blockId);
		await cobSelectById(idx, snapshot.cobId, snapshot.cobSnapshot);
		baaSetAmount(idx, 'requested', snapshot.requested);
		baaSetAmount(idx, 'allocated', snapshot.allocated);
		recomputeBaaTotals();
		updateRestoreButtonVisibility(key);
	}

	// Shows "Restore Removed Account Code" only while there is something removed left to bring back for this
	// scope — hidden the rest of the time, including once the stack is drained.
	function updateRestoreButtonVisibility(key) {
		var btn = key === BAA_CFA_KEY ? document.getElementById('baa_restore_btn') :
			document.getElementById('restore_line_btn_' + key);
		if (!btn) return;
		var stack = BAA_REMOVED[key];
		btn.style.display = (stack && stack.length) ? '' : 'none';
	}

	// Each line's own remove ("×") button is hidden only while it is the LAST line of its own scope — its
	// project block if it belongs to one, else the whole (CFA) form.
	function updateBaaRemoveButtons() {
		document.querySelectorAll('.baa-line').forEach(function(rowEl) {
			var idx = parseInt(rowEl.getAttribute('data-idx'), 10);
			var btn = rowEl.querySelector('.baa-remove-line');
			if (!btn) return;
			var blockId = BAA_ROW_BLOCK[idx];
			var count = blockId != null ?
				BAA_ROWS.filter(function(i) { return BAA_ROW_BLOCK[i] === blockId; }).length :
				BAA_ROWS.length;
			btn.style.visibility = count <= 1 ? 'hidden' : 'visible';
		});
	}

	// ── COB  →  GET /budget_line_item/approved ───────────────────────────────────
	// Filtered by the Program / Sub-Program / Project above: every level that has a value narrows the list.
	// The Program / Sub-Program / Project this idx's COB list is narrowed by: its own project block's, when it
	// belongs to one (CBA / RFA / LAA), else the single shared chain above (CFA).
	function cobChainOf(idx) {
		var blockId = BAA_ROW_BLOCK[idx];
		if (blockId != null) {
			var b = projectBlockById(blockId);
			return { program: b ? b.programCode : null, subProgram: b ? b.subProgramCode : null, project: b ? b.projectCode : null };
		}

		return { program: pcValue('prog'), subProgram: pcValue('subprog'), project: pcValue('proj') };
	}

	function cobQuery(idx) {
		var chain = cobChainOf(idx);
		var parts = [];
		[['program_code', chain.program], ['sub_program_code', chain.subProgram], ['project_code', chain.project]].forEach(function(p) {
			if (p[1]) parts.push(p[0] + '=' + encodeURIComponent(p[1]));
		});
		return parts.length ? '?' + parts.join('&') : '';
	}

	function cobUpdateHint(idx) {
		var hint = cobEl('cob_hint', idx);
		if (!hint) return;
		// A project block's lines already show one row per approved COB (autoExpandBlockCobLines()) — the
		// "N approved COB(s) available" count is redundant there (and noisy, repeated on every row); CFA's
		// single-chain lines keep it, since there it's the only hint of how many COBs match the chain.
		if (BAA_ROW_BLOCK[idx] != null) {
			hint.textContent = '';
			return;
		}
		var chain = cobChainOf(idx);
		var n = (COB_DATA[idx] || []).length;
		var scope = chain.project ? ' for this project' : chain.subProgram ? ' for this sub-program' :
			chain.program ? ' for this program' : '';
		hint.textContent = n ?
			n + ' approved COB(s) available' + scope + '.' + (scope ? '' :
				' Choose a program, sub-program or project to narrow the list.') :
			'No approved COBs found' + scope + '.';
	}

	// A project block's line has no Program / Sub-Program of its own to narrow the COB list by — Project is the
	// only thing that does. So its FIRST line must have Project picked before COB is: picking a COB first would
	// leave it possibly belonging to a different project than the one chosen afterwards. Every other line of the
	// same block (a saved record can have more than one) is free to load immediately — the block's project
	// doesn't change once it does.
	function cobRequiresProjectFirst(idx) {
		var blockId = BAA_ROW_BLOCK[idx];
		if (blockId != null) {
			if (blockIsDirectDraw(blockId)) return false; // no Project to wait on — picks straight from the full COE list
			var firstOfBlock = BAA_ROWS.find(function(i) { return BAA_ROW_BLOCK[i] === blockId; });
			return idx === firstOfBlock;
		}

		return cbaUsesIndependentProject() && idx === BAA_ROWS[0];
	}

	// Loads (or reloads) one line's list for the chain that is chosen right now. Resolves when that list is
	// in place; never rejects. A line that already has the list for this chain does nothing.
	function cobLoad(idx, keepPick) {
		var searchEl = cobEl('bv_search', idx);
		if (!searchEl) return Promise.resolve(); // the line was removed

		if (cobRequiresProjectFirst(idx) && !cobChainOf(idx).project) {
			// Invalidate any load already in flight (e.g. the unfiltered one from before CBA was chosen) so its
			// success handler can't land afterwards and silently re-enable this box — see the COB_SEQ check
			// inside the async function below.
			COB_SEQ[idx] = COB_EPOCH + ':' + (++COB_TOKEN_SEQ);
			COB_SIG[idx] = undefined;
			COB_LOAD[idx] = undefined;

			cobClearSelection(idx, true);
			COB_DATA[idx] = [];
			searchEl.disabled = true;
			searchEl.placeholder = 'Select a project first';
			var lockedDd = cobEl('bv_dropdown', idx);
			if (lockedDd) lockedDd.style.display = 'none';
			var lockedHint = cobEl('cob_hint', idx);
			if (lockedHint) lockedHint.textContent = 'Select a project above first.';
			return Promise.resolve();
		}

		var sig = cobSignature(idx);
		if (COB_SIG[idx] === sig && COB_LOAD[idx]) return COB_LOAD[idx];

		COB_SIG[idx] = sig;
		var token = COB_EPOCH + ':' + (++COB_TOKEN_SEQ);
		COB_SEQ[idx] = token;
		var keep = keepPick === true || COB_KEEP_SELECTION > 0;

		searchEl.disabled = true;
		searchEl.placeholder = 'Loading COBs…';
		var hint = cobEl('cob_hint', idx);
		if (hint) hint.textContent = 'Loading approved COBs…';
		var url = API_BASE + '/budget_line_item/approved' + cobQuery(idx);

		COB_LOAD[idx] = (async function() {
			try {
				var json = await cba_apiFetch(url);
				if (COB_SEQ[idx] !== token) return; // overtaken by a newer load (or the line / form was reset)
				if (!json.success || !Array.isArray(json.data)) throw new Error(json.message ||
					'Unexpected response format');

				COB_DATA[idx] = json.data;
				var el = cobEl('bv_search', idx);
				if (!el) return;

				// A pick that this chain no longer offers goes — unless a saved record is being filled in,
				// whose COBs belong to its own chain.
				var sel = COB_SEL[idx];
				if (sel && !keep && !json.data.some(function(r) {
						return String(r.budget_line_item_id) === String(sel.budget_line_item_id);
					})) {
					cobClearSelection(idx);
					el.value = '';
				}
				el.disabled = false;
				el.placeholder = 'Search account code or title…';
				cobUpdateHint(idx);
				cobSetupSearch(idx);
				// A successful load re-enables the box unconditionally above — re-assert the RFA / LAA Parent
				// CBA lock in case this landed after it (cbaApplyParentCbaLock()).
				cbaApplyParentCbaLock();
			} catch (err) {
				if (COB_SEQ[idx] !== token) return;
				console.error('[cobLoad]', err);
				COB_DATA[idx] = [];
				var failed = cobEl('bv_search', idx);
				if (failed) failed.placeholder = 'Could not load';
				var failedHint = cobEl('cob_hint', idx);
				if (failedHint) failedHint.textContent = '';
				showError('cob_error');
			}
		})();
		return COB_LOAD[idx];
	}

	// Called whenever a level of the Program / Sub-Program / Project chain changes. Several levels can change
	// in one go (choosing a program clears the two below it), so the reload is done once, after they have —
	// every line's COB list.
	function cobChainChanged() {
		if (COB_SYNC_QUEUED) return;
		COB_SYNC_QUEUED = true;
		Promise.resolve().then(function() {
			COB_SYNC_QUEUED = false;
			var keep = COB_KEEP_SELECTION > 0; // read now: the flag is gone by the time the requests come back
			BAA_ROWS.forEach(function(idx) {
				cobLoad(idx, keep);
			});
		});
	}

	function cobMatches(idx, q) {
		var directOnly = lineIsDirectDraw(idx); // a direct draw is COE-only — see createDirectEarmarkForRfaApproval()
		return (COB_DATA[idx] || []).filter(function(r) {
			if (directOnly && String(r.account_code || '').trim().charAt(0) === '1') return false;
			return !q || String(r.account_code || '').toLowerCase().includes(q) ||
				String(r.account_title || '').toLowerCase().includes(q) ||
				String(r.particulars || '').toLowerCase().includes(q);
		});
	}

	function cobRenderDropdown(idx, rows) {
		var dd = cobEl('bv_dropdown', idx);
		if (!dd) return;
		if (!rows.length) {
			dd.innerHTML =
				'<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">No results found</div>';
			dd.style.display = 'block';
			return;
		}
		var shown = rows.slice(0, COB_LIST_MAX);
		dd.innerHTML = shown.map(function(r) {
			return '<div class="bv-item" data-id="' + pcEsc(r.budget_line_item_id) + '">' +
				'<div class="bv-item-name">' + pcEsc(r.account_code || 'No account code') + '</div>' +
				(r.account_title ? '<div class="bv-item-sub">' + pcEsc(r.account_title) + '</div>' : '') +
				'<div class="bv-item-sub">FY ' + pcEsc(r.fiscal_yr) + ' · Available ' +
			pcEsc(cobAmount(cobAvailable(r))) +
				(r.project_name ? ' · ' + pcEsc(r.project_name) : '') + '</div>' +
				'</div>';
		}).join('') + (rows.length > shown.length ?
			'<div style="padding:8px 14px;color:var(--afims-text-muted, #62657E);font-size:11px;">Showing the first ' +
			shown.length + ' of ' + rows.length + ' — type to narrow the list</div>' : '');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				var rec = (COB_DATA[idx] || []).find(function(r) {
					return String(r.budget_line_item_id) === item.dataset.id;
				});
				if (rec) cobSelect(idx, rec);
			});
		});
	}

	function cobSelect(idx, rec) {
		var idEl = cobEl('budget_line_item_id', idx);
		var searchEl = cobEl('bv_search', idx);
		if (!idEl || !searchEl) return;
		COB_SEL[idx] = rec;
		idEl.value = rec.budget_line_item_id;
		searchEl.value = cobLabel(rec);
		var dd = cobEl('bv_dropdown', idx);
		if (dd) dd.style.display = 'none';
		baaBvClearError(idx);

		// The Available Amount is the derived ceiling BudgetLineItemService::fetchApproved() sends as `available_amount`
		// (BudgetLineBalanceSql::availableExpr()) — the same figure the server checks the Requested Amount against
		// (lockAndGetAvailable()): suggested_amount + adjustment, less what is still reserved and already drawn. It is
		// the real ceiling — what's actually still free to request against this COB right now. There is no fallback to
		// proposed_amount. Only the real ceiling for CBA / CFA — RFA / LAA use CBA Available Balance instead (see baaRefreshCbaAmount()).
		var proposedEl = cobEl('proposed_amount', idx);
		if (proposedEl) {
			var availableAmt = cobAvailable(rec);
			proposedEl.value = (availableAmt != null && availableAmt !== '') ?
				baaFormatAmt(parseFloat(availableAmt) || 0) : '';
		}
		baaRefreshCbaAmount(idx); // RFA / LAA: the parent CBA's own requested amount for this COB
		baaValidateRequestedAmount(idx); // this COB's ceiling may make an already-typed Requested Amount invalid (or valid again)

		baaApplyEarmarksVisibility(idx);

		cobDeriveDocumentType(rec);
	}

	// Document Type is no longer picked by hand (see document_type_row, permanently hidden) — it's derived from
	// whichever COB is picked, by the first digit of its account code: 1xxx = CO (Capital Outlay), 5xxx = COE
	// (Corporate Operating Expenditure). Skipped in edit mode (the loaded record's own document_type, set in
	// populateEditForm(), is authoritative — a line's account code shouldn't second-guess it) and effectively
	// moot in the "generate RFA/LAA from a parent CBA" autofill (it sets its own value right after restoring
	// lines, overriding whatever this derives in the meantime).
	function cobDeriveDocumentType(rec) {
		if (CBA_EDIT_MODE) return;
		var prefix = String(rec.account_code || '').trim().charAt(0);
		var derived = prefix === '1' ? 'CO' : prefix === '5' ? 'COE' : '';
		if (!derived) return;

		var docTypeEl = document.getElementById('document_type');
		if (!docTypeEl || docTypeEl.value === derived) return;
		docTypeEl.value = derived;

		generateCBANumber(); // RFA's middle code depends on document type (COE → 57)
		dropInfrastructureCategoryForCoe();
		applyProgramProjectVisibilityForDocType(); // Cash Requirements visibility still depends on document type
		applyBudgetVersionVisibilityForDocType();
	}

	// Forget a line's pick (and optionally the text in its box).
	function cobClearSelection(idx, clearText) {
		delete COB_SEL[idx];
		var idEl = cobEl('budget_line_item_id', idx);
		if (idEl) idEl.value = '';
		var proposedEl = cobEl('proposed_amount', idx);
		if (proposedEl) proposedEl.value = '';
		var cbaAmtEl = cobEl('cba_amount', idx);
		if (cbaAmtEl) cbaAmtEl.value = '';
		baaValidateRequestedAmount(idx); // no COB any more -> no ceiling -> clears a stale error
		baaHideEarmarksToggle(idx);
		if (clearText) {
			var searchEl = cobEl('bv_search', idx);
			if (searchEl) searchEl.value = '';
		}
	}

	// ── "Where this COB's balance is held" (fims.budget_line_item_earmark) ──────────────────────
	// Collapsed by default under the picked COB — GET /budget_line_item/{id}/earmarks on first expand, listing
	// every Approved CBA/CFA reservation (active/released) and RFA/LAA conversion (converted) against it, so a
	// user can see WHY the Available Amount is lower than the line's suggested amount without leaving this form.

	// Shown once a COB is picked (cobSelect()); a different COB drops any cached list so it reloads.
	function baaShowEarmarksToggle(idx, budgetLineItemId) {
		var panel = document.getElementById('baa_earmarks_' + idx);
		if (!panel) return;
		var cached = BAA_EARMARKS[idx];
		if (!cached || String(cached.budgetLineItemId) !== String(budgetLineItemId)) {
			BAA_EARMARKS[idx] = { budgetLineItemId: budgetLineItemId, data: null };
			panel.classList.remove('open');
			var body = document.getElementById('baa_earmarks_body_' + idx);
			if (body) body.style.display = 'none';
		}
		panel.style.display = '';
	}

	// Hidden when the line's COB is cleared — nothing to drill into any more.
	function baaHideEarmarksToggle(idx) {
		var panel = document.getElementById('baa_earmarks_' + idx);
		if (panel) {
			panel.style.display = 'none';
			panel.classList.remove('open');
		}
		var body = document.getElementById('baa_earmarks_body_' + idx);
		if (body) body.style.display = 'none';
		delete BAA_EARMARKS[idx];
	}

	// RFA / LAA draw against their parent CBA's own earmark (see CBA Available Balance, above) — "Where this COB's balance
	// is held" is a CBA / CFA concept only (it explains a COB-wide Available Amount they don't show at all).
	// Call instead of baaShowEarmarksToggle() directly so switching Allocation Type hides/restores it correctly.
	function baaApplyEarmarksVisibility(idx) {
		var rec = COB_SEL[idx];
		if (isRfaOrLaa() || !rec) {
			baaHideEarmarksToggle(idx);
		} else {
			baaShowEarmarksToggle(idx, rec.budget_line_item_id);
		}
	}

	function baaApplyEarmarksVisibilityAll() {
		BAA_ROWS.forEach(baaApplyEarmarksVisibility);
	}

	async function baaToggleEarmarks(idx) {
		var panel = document.getElementById('baa_earmarks_' + idx);
		var body = document.getElementById('baa_earmarks_body_' + idx);
		var entry = BAA_EARMARKS[idx];
		if (!panel || !body || !entry) return;

		var open = panel.classList.toggle('open');
		body.style.display = open ? '' : 'none';
		if (!open || entry.data) return; // already loaded, or just collapsed — nothing more to do

		body.innerHTML = '<div class="baa-earmarks-row">Loading…</div>';
		try {
			var json = await cba_apiFetch(API_BASE + '/budget_line_item/' + encodeURIComponent(entry.budgetLineItemId) + '/earmarks');
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message || 'Unexpected response format');
			entry.data = json.data;
			baaRenderEarmarks(body, entry.data);
		} catch (err) {
			console.error('[baaToggleEarmarks]', err);
			body.innerHTML = '<div class="baa-earmarks-row">Could not load. Try again.</div>';
		}
	}

	// ── CBA Available Balance (RFA / LAA only) ────────────────────────────────────────────────────
	// For RFA / LAA, the Requested Amount ceiling isn't the COB's general Available Amount — it's the specific
	// parent CBA's own remaining (active) earmark against that COB, which shrinks as earlier RFA/LAA draws
	// convert part of it into an obligation. Available Amount stays the CBA / CFA ceiling.
	function isRfaOrLaa() {
		var type = (document.getElementById('allocation_type') || {}).value;
		return type === 'RFA' || type === 'LAA';
	}

	// element id -> its own disabled state from just before cbaLockField() force-locked it, so unlocking
	// hands control back to whatever would otherwise apply (its own loading/cascade state) instead of guessing it.
	var CBA_PARENT_LOCK_PREV = {};

	function cbaLockField(id, locked) {
		var el = document.getElementById(id);
		if (!el) return;
		if (locked) {
			if (!(id in CBA_PARENT_LOCK_PREV)) CBA_PARENT_LOCK_PREV[id] = el.disabled;
			el.disabled = true;
		} else if (id in CBA_PARENT_LOCK_PREV) {
			el.disabled = CBA_PARENT_LOCK_PREV[id];
			delete CBA_PARENT_LOCK_PREV[id];
		}
	}

	// A line's own parent CBA — the parentAllocationId of the project block it belongs to (null for a
	// non-block line, or a block with no parent chosen yet). An RFA/LAA can now have several blocks, each
	// drawing from its OWN parent, so this is resolved per line rather than off one global Parent CBA field.
	function cbaParentIdForLine(idx) {
		var blockId = BAA_ROW_BLOCK[idx];
		if (blockId == null) return null;
		var b = projectBlockById(blockId);
		return b ? b.parentAllocationId : null;
	}

	// RFA / LAA with a Parent CBA picked must match that CBA's Category / COB exactly — those are what its
	// earmark (and CBA Available Balance) is actually keyed to, so a mismatched line could never be approved.
	// Locks those fields read-only while a parent is selected; Requested Amount stays editable (partial draws
	// are still allowed). A block's own search field is deliberately NOT locked — it's how the user corrects
	// a wrong pick (typing in it clears the block's parentAllocationId, see blockCbaSetupSearch()'s own
	// 'input' handler) — re-locking it would make that impossible. Category is record-level (locked once ANY
	// block has a parent); each line's own COB field is locked individually, against ITS OWN block's parent.
	// Call after picking/clearing a Parent CBA, after an Allocation Type change, and after adding a new
	// Budget Allocation Amount line.
	function cbaApplyParentCbaLock() {
		var isRL = isRfaOrLaa();
		var anyParentPicked = PROJECT_BLOCKS.some(function(b) { return !!b.parentAllocationId; });
		cbaLockField('cat_search', isRL && anyParentPicked);

		BAA_ROWS.forEach(function(idx) {
			cbaLockField('bv_search_' + idx, isRL && !!cbaParentIdForLine(idx));
		});
	}

	// Shows CBA Available Balance / hides Available Amount for RFA / LAA (and back for CBA / CFA), on every current line —
	// call whenever Allocation Type changes, and once for a freshly added line.
	function baaApplyCbaAmountVisibility(idx) {
		// A direct draw has no Parent CBA, so no CBA Available Balance to show — it uses the COB's own
		// Available Amount instead, same as CBA/CFA (see baaRequestedExceedsProposed()).
		var showCba = isRfaOrLaa() && !lineIsDirectDraw(idx);
		var proposedGroup = document.getElementById('proposed_amount_group_' + idx);
		var cbaGroup = document.getElementById('cba_amount_group_' + idx);
		if (proposedGroup) proposedGroup.style.display = showCba ? 'none' : '';
		if (cbaGroup) cbaGroup.style.display = showCba ? '' : 'none';
	}

	function baaApplyCbaAmountVisibilityAll() {
		BAA_ROWS.forEach(baaApplyCbaAmountVisibility);
	}

	// Every earmark held against one COB (fims.budget_line_item_earmark, via the same endpoint
	// baaToggleEarmarks() uses for the "Where this COB's balance is held" drilldown) — cached per COB so
	// switching between parent CBAs / lines on the same COB doesn't re-fetch it each time.
	function cbaFetchCobEarmarks(budgetLineItemId) {
		if (!budgetLineItemId) return Promise.resolve([]);
		if (COB_EARMARKS_CACHE[budgetLineItemId]) return COB_EARMARKS_CACHE[budgetLineItemId];
		var promise = cba_apiFetch(API_BASE + '/budget_line_item/' + encodeURIComponent(budgetLineItemId) + '/earmarks')
			.then(function(json) {
				return (json.success && Array.isArray(json.data)) ? json.data : [];
			})
			.catch(function(err) {
				console.error('[cbaFetchCobEarmarks]', err);
				return [];
			});
		COB_EARMARKS_CACHE[budgetLineItemId] = promise;
		return promise;
	}

	// Resolves and displays this line's CBA Available Balance: the parent CBA's current ACTIVE earmark
	// against whichever COB is currently picked here — not its original requested amount, which doesn't
	// shrink as earlier RFA/LAA draws convert part of it into an obligation (see
	// BudgetLineItemService::convertEarmarkForRfaApproval(), the same figure this mirrors). Async (the COB's
	// earmarks may need a fetch) — guarded against a stale answer landing after a newer COB / parent CBA
	// pick, the same way cobLoad() guards COB_SEQ.
	function baaRefreshCbaAmount(idx) {
		var cbaAmtEl = cobEl('cba_amount', idx);
		if (!cbaAmtEl) return;

		var token = (BAA_CBA_AMT_SEQ[idx] = (BAA_CBA_AMT_SEQ[idx] || 0) + 1);
		var rec = COB_SEL[idx];
		var parentId = cbaParentIdForLine(idx);

		if (!rec || !parentId) {
			cbaAmtEl.value = '';
			if (isRfaOrLaa()) baaValidateRequestedAmount(idx);
			return;
		}

		cbaFetchCobEarmarks(rec.budget_line_item_id).then(function(rows) {
			if (BAA_CBA_AMT_SEQ[idx] !== token) return; // overtaken by a newer COB / parent CBA pick
			var row = rows.find(function(r) {
				return String(r.allocation_id) === String(parentId) && r.earmark_status === 'active';
			});
			var amt = row ? (parseFloat(row.amount) || 0) : 0;
			cbaAmtEl.value = baaFormatAmt(amt);
			if (isRfaOrLaa()) {
				// Requested Amount always follows the CBA Available Balance for a NEW RFA / LAA — still
				// editable afterward (e.g. for a partial draw), just not left defaulted to something that's
				// already known to exceed what's actually available. Skipped in edit mode: an existing saved
				// record keeps its own historical Requested Amount instead of being overwritten by today's balance.
				if (!CBA_EDIT_MODE) {
					baaSetAmount(idx, 'requested', amt);
					recomputeBaaTotals();
				}
				baaValidateRequestedAmount(idx);
			}
		});
	}

	function baaRefreshAllCbaAmounts() {
		BAA_ROWS.forEach(baaRefreshCbaAmount);
	}

	function baaRenderEarmarks(body, rows) {
		if (!rows.length) {
			body.innerHTML = '<div class="baa-earmarks-row">Nothing is currently held against this COB.</div>';
			return;
		}
		body.innerHTML = rows.map(function(row) {
			var ref = row.allocation_number || ('#' + row.allocation_id);
			var type = row.allocation_type ? ' (' + pcEsc(row.allocation_type) + ')' : '';
			return '<div class="baa-earmarks-row">' +
				'<span><span class="baa-earmarks-ref">' + pcEsc(ref) + '</span>' + type +
				'<span class="baa-earmarks-tag ' + pcEsc(row.earmark_status) + '">' + pcEsc(row.earmark_status) + '</span></span>' +
				'<span class="baa-earmarks-amt">' + pcEsc(cobAmount(row.original_amount)) + '</span>' +
				'</div>';
		}).join('');
	}

	// A saved line (edit mode, parent CBA): its COB. The Type of Fund isn't chosen separately any more — it
	// travels with whichever COB is picked (see collectBaaLines()).
	async function cobRestoreLine(idx, line) {
		await cobSelectById(idx, line.budget_line_item_id, line);
	}

	// Select a saved line's COB by id (edit mode, "generate from a parent CBA"). It is taken from the line's list
	// when it is there; otherwise from the details saved with the line (snapshot), so a COB that has since left
	// the approved list — or belongs to another chain — still shows on the record that used it.
	async function cobSelectById(idx, id, snapshot) {
		if (id == null || id === '' || !cobEl('bv_search', idx)) return;
		await cobLoad(idx);
		var rec = (COB_DATA[idx] || []).find(function(r) {
			return String(r.budget_line_item_id) === String(id);
		});
		if (!rec && snapshot) {
			rec = {
				budget_line_item_id: id,
				type_of_fund_id: snapshot.type_of_fund_id,
				type_of_fund: snapshot.type_of_fund || '',
				account_code: snapshot.account_code || '',
				account_title: snapshot.account_title || '',
				fiscal_yr: snapshot.fiscal_yr || '',
				proposed_amount: snapshot.proposed_amount,
				suggested_amount: snapshot.suggested_amount,
				available_amount: snapshot.available_amount
			};
		}
		if (rec) cobSelect(idx, rec);
	}

	// Wired once per line, when its first list arrives.
	function cobSetupSearch(idx) {
		var searchEl = cobEl('bv_search', idx);
		var dd = cobEl('bv_dropdown', idx);
		if (!searchEl || !dd || COB_WIRED[idx]) return;
		COB_WIRED[idx] = true;

		searchEl.addEventListener('input', function() {
			cobClearSelection(idx); // typing withdraws the pick
			cobRenderDropdown(idx, cobMatches(idx, this.value.trim().toLowerCase()));
		});
		searchEl.addEventListener('focus', function() {
			if (!cobEl('budget_line_item_id', idx).value) {
				cobRenderDropdown(idx, cobMatches(idx, this.value.trim().toLowerCase()));
			}
		});
		searchEl.addEventListener('blur', function() {
			setTimeout(function() {
				dd.style.display = 'none';
			}, 150);
		});
	}

	// ── COB required ────────────────────────────────────────────────────────────
	// Every Budget Allocation Amount line must be tied to a COB, regardless of allocation type. Enforced on
	// submit by validateBudgetVersionsRequired() — isBudgetVersionRequired() also drives the "*" marker.
	function isBudgetVersionRequired() {
		return true;
	}

	// Every project block (CBA/CFA/RFA/LAA block-mode) must have at least one line with a real account code
	// AND a positive Requested Amount — a block saved with nothing actually picked (e.g. every row removed
	// down to one that was never filled in) would reach Approved with nothing earmarked against it. Mirrors
	// BudgetAllocationService::validateReferences()'s own universal check (parseLineTransaction()'s same
	// "blank entries don't count" rule), so client and server can never disagree on what counts as empty.
	// A no-op outside block mode (CFA's single chain has its own line(s), never project blocks).
	function validateProjectBlocksHaveAccountCodes() {
		if (!cbaUsesBlockProjectUI()) return true;
		return PROJECT_BLOCKS.every(function(b) {
			return BAA_ROWS.some(function(idx) {
				if (BAA_ROW_BLOCK[idx] !== b.id || !COB_SEL[idx]) return false;
				var reqEl = document.getElementById('requested_amount_' + idx + '_raw');
				return baaParseAmt(reqEl ? reqEl.value : '') > 0;
			});
		});
	}

	// Clears the red error state set by validateBudgetVersionsRequired() on a row.
	function baaBvClearError(idx) {
		var searchEl = document.getElementById('bv_search_' + idx);
		if (!searchEl) return;
		var group = searchEl.closest('.form-group');
		if (group) group.classList.remove('has-error');
		searchEl.classList.remove('error');
	}

	// Submit-time check: every line must have a COB when the allocation type is RFA / LAA. Checks the hidden
	// budget_line_item_id_{idx} (the actual pick), not the search box text, so half-typed text that was never
	// picked from the dropdown doesn't pass. Returns true when valid.
	function validateBudgetVersionsRequired() {
		if (!isBudgetVersionRequired()) return true;

		var firstInvalid = null;
		BAA_ROWS.forEach(function(idx) {
			var idEl = document.getElementById('budget_line_item_id_' + idx);
			var searchEl = document.getElementById('bv_search_' + idx);
			if (!idEl || !searchEl) return;

			var missing = idEl.value.trim() === '';
			var group = searchEl.closest('.form-group');
			if (group) group.classList.toggle('has-error', missing);
			searchEl.classList.toggle('error', missing);
			if (missing && !firstInvalid) firstInvalid = searchEl;
		});

		if (firstInvalid) {
			firstInvalid.scrollIntoView({
				behavior: 'smooth',
				block: 'center'
			});
			return false;
		}
		return true;
	}

	// ── Requested Amount must not exceed its ceiling ─────────────────────────────
	// CBA / CFA: Available Amount (read-only, filled from the picked COB's earmark-adjusted balance).
	// RFA / LAA: CBA Available Balance instead — the specific parent CBA's own remaining active earmark for
	// that COB, not the COB's wider leftover balance (see baaRefreshCbaAmount()). "Nothing to enforce yet"
	// means no COB picked for this line (or, for RFA / LAA, no Parent CBA picked either) — NOT a ceiling that
	// has genuinely resolved to 0 (a COB fully drawn by its parent must still block any positive Requested
	// Amount, not be read as "unknown, let it through").
	function baaRequestedExceedsProposed(idx) {
		if (!COB_SEL[idx]) return false; // no COB picked for this line yet
		var direct = lineIsDirectDraw(idx);
		if (isRfaOrLaa() && !direct && !cbaParentIdForLine(idx)) return false; // this line's own block has no Parent CBA picked yet

		var ceilEl = document.getElementById((isRfaOrLaa() && !direct ? 'cba_amount_' : 'proposed_amount_') + idx);
		var ceiling = ceilEl ? baaParseAmt(ceilEl.value) : 0;
		var requested = baaParseAmt((document.getElementById('requested_amount_' + idx) || {}).value);
		return requested > ceiling;
	}

	// Applies (or clears) the red error state on one line's Requested Amount field. Called live as the user types
	// or picks a COB (a new ceiling can turn an already-typed Requested Amount invalid, or valid again).
	function baaValidateRequestedAmount(idx) {
		var reqEl = document.getElementById('requested_amount_' + idx);
		if (!reqEl) return true;
		var exceeds = baaRequestedExceedsProposed(idx);
		var group = reqEl.closest('.form-group');
		if (group) group.classList.toggle('has-error', exceeds);
		reqEl.classList.toggle('error', exceeds);
		var errorEl = document.getElementById('requested_amount_' + idx + '_error');
		if (errorEl) {
			errorEl.textContent = (isRfaOrLaa() && !lineIsDirectDraw(idx)) ?
				'Requested Amount must not exceed the CBA Available Balance.' :
				'Requested Amount must not exceed the Available Amount.';
			errorEl.classList.toggle('visible', exceeds);
		}
		return !exceeds;
	}

	// Submit-time check across every line. Returns true when every line's Requested Amount is within its
	// Proposed Amount (or has no Proposed Amount to be bound by).
	function validateRequestedAmountsAgainstProposed() {
		var firstInvalid = null;
		BAA_ROWS.forEach(function(idx) {
			var valid = baaValidateRequestedAmount(idx);
			if (!valid && !firstInvalid) firstInvalid = document.getElementById('requested_amount_' + idx);
		});

		if (firstInvalid) {
			firstInvalid.scrollIntoView({
				behavior: 'smooth',
				block: 'center'
			});
			return false;
		}
		return true;
	}

	// ── Hide CFA for document_type = CO ────────────────────────────────────────
	// CFA (Certified Funds Available) isn't offered for a CO document, so
	// its <option> is taken out of Allocation Type while document_type = CO and put
	// back for anything else. It is removed rather than hidden: <option hidden> is
	// ignored by some browsers. Wired to #document_type's 'change' event and called
	// wherever document_type.value is set programmatically (no 'change' fires then).
	// - CFA already selected when CO is chosen → Allocation Type goes back to blank
	//   (not silently to CBA) and 'change' fires so the number, labels and Budget
	//   Version fields follow; the required field makes the user pick again.
	// - Edit mode on an existing CFA record keeps the option, so the saved type
	//   isn't wiped out by merely opening the record.
	var ALLOC_CFA_OPTION = null;

	function applyAllocationTypeOptionsForDocType() {
		var sel = document.getElementById('allocation_type');
		var docType = document.getElementById('document_type').value;
		if (!ALLOC_CFA_OPTION) ALLOC_CFA_OPTION = sel.querySelector('option[value="CFA"]');
		if (!ALLOC_CFA_OPTION) return;

		var present = ALLOC_CFA_OPTION.parentNode === sel;
		var cfaAllowed = !CBA_ALLOWED_TYPES.length || CBA_ALLOWED_TYPES.indexOf('CFA') !== -1; // a CBA / RFA / LAA page never offers CFA
		var hide = !cfaAllowed || (docType === 'CO' && !(CBA_EDIT_MODE && sel.value === 'CFA'));

		if (hide && present) {
			var wasCfa = sel.value === 'CFA';
			ALLOC_CFA_OPTION.remove();
			if (wasCfa) {
				sel.value = '';
				sel.dispatchEvent(new Event('change'));
			}
		} else if (!hide && !present) {
			sel.appendChild(ALLOC_CFA_OPTION);
		}
	}

	// ── COB field: always shown ─────────────────────────────────────────────────
	// The COB field is shown for every document type / allocation type (it used to be hidden for CBA + CO).
	// It is only *required* (the "*" marker + submit check) for RFA / LAA — see isBudgetVersionRequired().
	function applyBudgetVersionVisibilityForDocType() {
		var bvRequired = isBudgetVersionRequired();

		document.querySelectorAll('.baa-line').forEach(function(rowEl) {
			var bvGroup = rowEl.querySelector('.baa-bv-group');
			if (bvGroup) bvGroup.style.display = '';

			var mark = rowEl.querySelector('.baa-bv-required');
			if (mark) mark.style.display = bvRequired ? '' : 'none';
			if (!bvRequired) baaBvClearError(rowEl.dataset.idx); // isBudgetVersionRequired() always true today; kept in case that changes again
		});
	}

	// ── Amount inputs: comma-formatted display, raw value in hidden field ────────
	function baaParseAmt(str) {
		return parseFloat((str || '').replace(/[^0-9.]/g, '')) || 0;
	}

	function baaFormatAmt(num) {
		if (!num && num !== 0) return '';
		return num.toLocaleString('en-PH', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}

	function baaSyncRaw(input) {
		var raw = baaParseAmt(input.value);
		var hidden = document.getElementById(input.id + '_raw');
		if (hidden) hidden.value = raw > 0 ? raw.toFixed(2) : '';
	}

	// Adds thousands commas to an amount input as the user types, keeping the caret in the same place relative
	// to the digits around it (not just at a fixed offset — inserting a digit near the start shouldn't jump the
	// caret to the end once a new comma appears further along).
	function baaLiveFormat(input) {
		var raw = input.value;
		var cursor = input.selectionStart;
		var digitsBeforeCursor = raw.slice(0, cursor).replace(/[^0-9]/g, '').length;

		var cleaned = raw.replace(/[^0-9.]/g, '');
		var firstDot = cleaned.indexOf('.');
		if (firstDot !== -1) {
			cleaned = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
		}
		var hasDot = cleaned.indexOf('.') !== -1;
		var parts = cleaned.split('.');
		var intPart = parts[0].replace(/^0+(?=\d)/, '');
		var decPart = parts[1] !== undefined ? parts[1].slice(0, 2) : '';

		var formatted = (intPart ? Number(intPart).toLocaleString('en-PH') : '') + (hasDot ? '.' + decPart : '');
		input.value = formatted;

		var seen = 0,
			pos = 0;
		for (; pos < formatted.length && seen < digitsBeforeCursor; pos++) {
			if (/[0-9]/.test(formatted[pos])) seen++;
		}
		input.setSelectionRange(pos, pos);
	}

	function wireAmtInput(input) {
		if (!input) return;
		input.addEventListener('blur', function() {
			var raw = baaParseAmt(this.value);
			this.value = raw > 0 ? baaFormatAmt(raw) : '';
			baaSyncRaw(this);
		});
		input.addEventListener('keydown', function(e) {
			var allowed = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home',
				'End'
			];
			if (allowed.includes(e.key)) return;
			if (e.key === '.' && !this.value.includes('.')) return;
			if (e.key >= '0' && e.key <= '9') return;
			e.preventDefault();
		});
		input.addEventListener('input', function() {
			baaLiveFormat(this);
			baaSyncRaw(this);
			recomputeBaaTotals();
		});
	}

	function baaSetAmount(idx, kind, val) {
		var num = parseFloat(val) || 0;
		var display = document.getElementById(kind + '_amount_' + idx);
		var raw = document.getElementById(kind + '_amount_' + idx + '_raw');
		if (!display) return;
		if (num > 0) {
			display.value = baaFormatAmt(num);
			if (raw) raw.value = num.toFixed(2);
		} else {
			display.value = '';
			if (raw) raw.value = '';
		}
	}

	// Sums every row's Requested/Cash amounts, updates the totals footer, mirrors
	// the totals into the legacy singular hidden fields (requested_amount_raw /
	// allocated_amount_raw — read by buildPayload, printCBA, the alloc>req submit
	// check, etc.).
	function recomputeBaaTotals() {
		var totalReq = 0,
			totalAlloc = 0;
		BAA_ROWS.forEach(function(idx) {
			var reqRaw = document.getElementById('requested_amount_' + idx + '_raw');
			var allocRaw = document.getElementById('allocated_amount_' + idx + '_raw');
			totalReq += reqRaw ? (parseFloat(reqRaw.value) || 0) : 0;
			totalAlloc += allocRaw ? (parseFloat(allocRaw.value) || 0) : 0;
		});

		var totalReqEl = document.getElementById('baa_total_requested');
		var totalAllocEl = document.getElementById('baa_total_allocated');
		if (totalReqEl) totalReqEl.textContent = '₱' + baaFormatAmt(totalReq);
		if (totalAllocEl) totalAllocEl.textContent = '₱' + baaFormatAmt(totalAlloc);

		var reqMirror = document.getElementById('requested_amount_raw');
		var allocMirror = document.getElementById('allocated_amount_raw');
		if (reqMirror) reqMirror.value = totalReq > 0 ? totalReq.toFixed(2) : '';
		if (allocMirror) allocMirror.value = totalAlloc > 0 ? totalAlloc.toFixed(2) : '';
	}

	// Collects every row into the itemized array sent as `line_transaction`. A line keeps the Type of Fund and
	// Account Code of its COB next to the id, so the record still reads correctly if that line item changes later.
	function baaLineObject(idx) {
		var sel = COB_SEL[idx] || null;
		return {
			budget_line_item_id: sel ? String(sel.budget_line_item_id) : '',
			type_of_fund_id: sel && sel.type_of_fund_id != null ? String(sel.type_of_fund_id) : '',
			type_of_fund: sel ? (sel.type_of_fund || '') : '',
			account_code: sel ? (sel.account_code || '') : '',
			account_title: sel ? (sel.account_title || '') : '',
			fiscal_yr: sel && sel.fiscal_yr != null ? String(sel.fiscal_yr) : '',
			proposed_amount: sel && sel.proposed_amount != null ? String(sel.proposed_amount) : '',
			suggested_amount: sel && sel.suggested_amount != null ? String(sel.suggested_amount) : '',
			available_amount: sel && sel.available_amount != null ? String(sel.available_amount) : '',
			requested_amount: (document.getElementById('requested_amount_' + idx + '_raw') || {}).value || '',
			allocated_amount: (document.getElementById('allocated_amount_' + idx + '_raw') || {}).value || '',
		};
	}

	// Drop a fully-empty trailing row (e.g. one added then left untouched).
	function baaLineIsMeaningful(line) {
		return !!(line.budget_line_item_id || line.type_of_fund_id || line.requested_amount);
	}

	// CFA's own single chain — every line in BAA_ROWS that doesn't belong to a project block.
	function collectBaaLines() {
		return BAA_ROWS.filter(function(idx) { return BAA_ROW_BLOCK[idx] == null; })
			.map(baaLineObject).filter(baaLineIsMeaningful);
	}

	// RFA / LAA — one entry per project block (one per selected Parent CBA): its own parent_allocation_id,
	// Program / Sub-Program / Project (picked code, or a typed new name) and its own itemized COB lines, in
	// the shape buildPayload() sends as `projects`.
	function collectProjectBlocks() {
		return PROJECT_BLOCKS.map(function(b) {
			var lines = BAA_ROWS.filter(function(idx) { return BAA_ROW_BLOCK[idx] === b.id; })
				.map(baaLineObject).filter(baaLineIsMeaningful);
			var block = { line_transaction: JSON.stringify(lines) };
			if (b.budgetAllocationProjectId != null) block.budget_allocation_project_id = b.budgetAllocationProjectId;
			if (b.directCob) {
				// No Parent CBA, no Project — see BudgetAllocationService::validateReferences()'s own
				// direct-draw branch, which rejects this block carrying any of those fields.
				block.is_direct_draw = true;
				return block;
			}
			if (b.parentAllocationId != null) block.parent_allocation_id = b.parentAllocationId;
			if (b.projectCode) block.project_code = b.projectCode; else if (b.projectName) block.project_name = b.projectName;
			if (b.programCode) block.program_code = b.programCode; else if (b.programName) block.program_name = b.programName;
			if (b.subProgramCode) block.sub_program_code = b.subProgramCode; else if (b.subProgramName) block.sub_program_name = b.subProgramName;

			return block;
		});
	}

	// ── 8. Project blocks' own search field: a Project (CBA) or a Parent CBA (RFA/LAA) ──────────────────
	// Each project block's search field (proj_search_<blockId>) picks different things depending on type —
	// see blockFieldNoun() / blockSetupSearch(): for CBA it searches PROJECTS directly (blockProjSetupSearch(),
	// unchanged); for RFA/LAA it searches APPROVED CBAs instead (blockCbaSetupSearch(), below) — picking one
	// seeds that block's own Project + COB/account-code lines from that CBA's own project
	// (cbaAutofillBlockFromParent()) and is what the backend's parent_allocation_id (collectProjectBlocks())
	// ultimately comes from. One CBA funds one block, same "already claimed by another block" exclusion
	// blockProjAvailableData() already applies to projects (see blockCbaAvailableData()).
	var CBA_DATA = [];
	var CBA_LOADED = false;

	// Only CBAs with this status are offered as a parent (a status NAME of the memo-request statuses; the API
	// resolves it, so no install-specific id is needed here). A name that matches no status offers nothing.
	var CBA_PARENT_STATUS = 'Approved';

	// What a CBA is called in the list: its allocation number, else the request reference the database gave it
	// (RR-001, …) — every row has one, while a new CBA carries no allocation number — then program and fiscal year.
	function cbaLabelOf(c) {
		return (c.allocation_number || c.request_referrence_no || ('#' + c.allocation_id)) +
			(c.program_name ? ' — ' + c.program_name : '') +
			(c.fiscal_year ? ' · FY' + c.fiscal_year : '');
	}

	function cbaSearchText(c) {
		return [c.request_referrence_no, c.allocation_number, c.program_name, c.sub_program_name, c.project_name, c.description]
			.map(function(s) { return (s || '').toLowerCase(); }).join(' ');
	}

	// Lazily loads every Approved CBA once — shared by every block's own Parent CBA search (RFA/LAA only).
	async function ensureCbaDataLoaded() {
		if (CBA_LOADED) return CBA_DATA;
		try {
			var json = await cba_apiFetch(`${API_BASE}/budget_allocation/all?allocation_type=CBA&status=${encodeURIComponent(CBA_PARENT_STATUS)}`);
			if (!json.success || !Array.isArray(json.data)) throw new Error(json.message || 'Bad response');
			CBA_DATA = json.data;
			CBA_LOADED = true;
		} catch (err) {
			console.error('[ensureCbaDataLoaded]', err);
		}
		return CBA_DATA;
	}

	// This CBA's own project block(s), re-read from /budget_allocation/read/{id} when the /all list's own
	// `projects` is somehow empty, falling back to its legacy flat project_code / line_transaction fields
	// for a CBA saved before multi-project support existed at all. CBA creation is now restricted to exactly
	// one project (cbaUsesBlockProjectUI()), so this almost always returns a single-entry array — but a CBA
	// saved BEFORE that restriction existed can still have several, which is exactly why this returns every
	// one of them rather than always assuming the first: populateBlockProjectChoice() below is what decides,
	// per block, whether there's a real choice to make.
	async function cbaResolveAllParentBlocks(cba) {
		var parentBlocks = Array.isArray(cba.projects) ? cba.projects : [];
		if (!parentBlocks.length) {
			try {
				var parentJson = await cba_apiFetch(`${API_BASE}/budget_allocation/read/${cba.allocation_id}`);
				if (parentJson.success && parentJson.data && Array.isArray(parentJson.data.projects)) {
					parentBlocks = parentJson.data.projects;
				}
			} catch (err) {
				console.error('[cba autofill] Failed to read the parent record:', err);
			}
		}
		if (!parentBlocks.length) {
			parentBlocks = [{
				project_code: cba.project_code, program_code: cba.program_code, sub_program_code: cba.sub_program_code,
				line_transaction: Array.isArray(cba.line_transaction) && cba.line_transaction.length ? cba.line_transaction :
					[{ requested_amount: cba.requested_amount, allocated_amount: cba.allocated_amount }],
			}];
		}
		return parentBlocks;
	}

	// blockId -> the full array cbaResolveAllParentBlocks() last resolved for it — the Project choice
	// select's own 'change' handler (blockWireProjectChoice()) reads this to know what each of its own
	// <option>s (indexed 0, 1, 2, …) actually refers to.
	var BLOCK_PARENT_PROJECT_BLOCKS = {};

	// Populates (and enables only when there's a real choice) one block's own "Project" <select> from its
	// Parent CBA's resolved project blocks. One project (the common, going-forward case): a single, disabled
	// option — nothing to choose, so nothing is left for the user to get wrong. More than one (a CBA saved
	// before CBA was restricted to exactly one project): an enabled select defaulted to NO selection — the
	// user must explicitly pick one rather than this code silently guessing the first, which project an
	// "ambiguous" legacy CBA actually means for THIS particular draw.
	function populateBlockProjectChoice(blockId, parentBlocks) {
		var sel = document.getElementById('project_choice_' + blockId);
		var hint = document.getElementById('project_choice_hint_' + blockId);
		if (!sel) return;
		BLOCK_PARENT_PROJECT_BLOCKS[blockId] = parentBlocks;

		if (parentBlocks.length <= 1) {
			var only = parentBlocks[0] || null;
			sel.innerHTML = only ? '<option value="0">' + pcEsc(only.project_name || only.project_code || 'Project') + '</option>' : '';
			sel.value = '0';
			sel.disabled = true;
			sel.required = false;
			if (hint) hint.textContent = 'This Parent CBA has one project.';
			return;
		}

		sel.innerHTML = '<option value="" disabled selected>— Select project —</option>' +
			parentBlocks.map(function(pb, i) {
				return '<option value="' + i + '">' + pcEsc(pb.project_name || pb.project_code || ('Project ' + (i + 1))) + '</option>';
			}).join('');
		sel.value = '';
		sel.disabled = false;
		sel.required = true;
		if (hint) hint.textContent = 'This Parent CBA has more than one project — pick which one this draw is against.';
	}

	// Removes every BAA line currently in one project block — call before re-seeding it from a (possibly
	// different) resolved project, so a stale line from a previous Parent CBA / project pick never lingers
	// alongside the new ones.
	function blockClearLines(blockId) {
		BAA_ROWS.filter(function(idx) { return BAA_ROW_BLOCK[idx] === blockId; }).slice().forEach(function(idx) {
			baaHardRemoveLine(idx);
		});
	}

	// Wires the Project choice select's own 'change' — picking a different option re-seeds the block from
	// THAT project block instead (clearing whatever lines the previous one left behind first).
	function blockWireProjectChoice(blockId) {
		var sel = document.getElementById('project_choice_' + blockId);
		if (!sel) return;
		sel.addEventListener('change', async function() {
			var idx = parseInt(this.value, 10);
			var blocks = BLOCK_PARENT_PROJECT_BLOCKS[blockId];
			if (!blocks || isNaN(idx) || !blocks[idx]) return;
			blockClearLines(blockId);
			await cbaAutofillBlockFromParent(blockId, blocks[idx]);
		});
	}

	// Seeds ONE already-created block (blockId) from one parent CBA's own project block — same per-block
	// restore logic restoreProjectBlocksFromSaved() uses, applied to a single block instead of rebuilding the
	// whole PROJECT_BLOCKS array.
	async function cbaAutofillBlockFromParent(blockId, parentBlock) {
		if (!parentBlock) return;
		COB_KEEP_SELECTION++;
		try {
			await loadAllProjects();
			if (parentBlock.project_code) blockProjSelectById(blockId, parentBlock.project_code);

			var savedLines = Array.isArray(parentBlock.line_transaction) ? parentBlock.line_transaction :
				(parentBlock.line_transaction ? [parentBlock.line_transaction] : []);
			var blockLineIdxs = BAA_ROWS.filter(function(idx) { return BAA_ROW_BLOCK[idx] === blockId; });
			while (blockLineIdxs.length < savedLines.length) {
				blockLineIdxs.push(addBudgetAllocationLine(blockId));
			}
			for (var li = 0; li < savedLines.length; li++) {
				var line = savedLines[li];
				var idx = blockLineIdxs[li];
				if (idx === undefined) continue;

				baaSetAmount(idx, 'requested', line.requested_amount);
				baaSetAmount(idx, 'allocated', line.allocated_amount);
				await cobRestoreLine(idx, line);
			}
		} finally {
			COB_KEEP_SELECTION--;
		}
		recomputeBaaTotals();
	}

	// Record-level autofill from a parent CBA — Fiscal Year, Document Type, Purpose/Description, Remarks, Is
	// this for bidding?, Category, Vendor and Supporting Documents. Runs only for the FIRST block that ever
	// gets a parent (blockCbaSelect()) — later ones contribute a project block but never touch these shared
	// fields.
	async function cbaAutofillHeaderFromParent(cba) {
		if (cba.fiscal_year) {
			cbaSetFiscalYear(cba.fiscal_year);
			generateCBANumber();
		}

		if (cba.document_type) {
			var docTypeEl = document.getElementById('document_type');
			docTypeEl.value = cba.document_type;
			generateCBANumber(); // RFA code depends on document type (COE → 57)
			// document_type was set programmatically (no 'change' event fired),
			// so re-apply the CO auto-select/lock behavior manually.
			applyAllocationTypeOptionsForDocType();
			dropInfrastructureCategoryForCoe();
			applyBudgetVersionVisibilityForDocType();
		}

		if (cba.description) {
			document.getElementById('project_description').value = cba.description;
		}
		if (cba.remarks) {
			document.getElementById('remarks').value = cba.remarks;
		}
		var isBiddingEl = document.getElementById('is_bidding');
		if (isBiddingEl) isBiddingEl.checked = !!cba.is_bidding;

		catSelectById(cba.category_id);
		await vendSelectById(cba.vendor_id);

		if (cba.multiple_node_id) {
			try {
				var cbaReadJson = await cba_apiFetch(
					`${API_BASE}/budget_allocation/read/${cba.allocation_id}`);
				var attachments = cbaReadJson.success ? (cbaReadJson.data?.file_attachments ||
					[]) : [];
				attachments
					.filter(function(a) {
						return !a.deleted_at && a.file_meta && !a.file_meta.deleted_at;
					})
					.forEach(function(a) {
						addExistingSupportingDocRow(a.file_meta.document_name, a.file_meta.link,
							a.file_meta.view_url, 'Carried over from parent CBA');
					});
			} catch (err) {
				console.error('[cba autofill] Failed to load parent supporting documents:',
					err);
			}
		}

		applyProgramProjectVisibilityForDocType();
	}

	// CBAs already picked by OTHER blocks are excluded — one CBA funds exactly one block (same exclusion
	// blockProjAvailableData() applies to projects for CBA's own block type).
	function blockCbaAvailableData(blockId) {
		var usedIds = PROJECT_BLOCKS.filter(function(b) {
			return b.id !== blockId && b.parentAllocationId != null;
		}).map(function(b) { return b.parentAllocationId; });
		if (!usedIds.length) return CBA_DATA;

		return CBA_DATA.filter(function(c) { return usedIds.indexOf(c.allocation_id) === -1; });
	}

	function blockCbaRenderDropdown(blockId, data) {
		var dd = document.getElementById('proj_dropdown_' + blockId);
		if (!dd) return;
		if (!data.length) {
			dd.innerHTML = '<div style="padding:10px 14px;color:var(--afims-text-disabled, #A9AAB8);font-size:13px;">' +
				(CBA_LOADED ? 'No approved CBA found.' : 'Loading…') + '</div>';
			dd.style.display = 'block';
			return;
		}
		var shown = data.slice(0, COB_LIST_MAX);
		dd.innerHTML = shown.map(function(c) {
			return '<div class="bv-item" data-id="' + pcEsc(String(c.allocation_id)) + '">' +
				'<div class="bv-item-name">' + pcEsc(cbaLabelOf(c)) + '</div>' +
				'</div>';
		}).join('') + (data.length > shown.length ?
			'<div style="padding:8px 14px;color:var(--afims-text-muted, #62657E);font-size:11px;">Showing the first ' +
			shown.length + ' of ' + data.length + ' — type to narrow the list</div>' : '');
		dd.style.display = 'block';

		dd.querySelectorAll('.bv-item').forEach(function(item) {
			item.addEventListener('mousedown', function(e) {
				e.preventDefault();
				var c = data.find(function(x) { return String(x.allocation_id) === item.dataset.id; });
				if (c) blockCbaSelect(blockId, c);
			});
		});
	}

	// Picking a Parent CBA for one block: tags the block with it, resolves its project block(s), and
	// populates the Project choice select (populateBlockProjectChoice()) — enabled only when there's a real
	// choice. Then, only while creating a NEW record, never while editing one already saved (CBA_EDIT_MODE):
	// with exactly one project (the common case), seeds it immediately, same as before; with several (a
	// legacy CBA), waits for the user's own pick instead (blockWireProjectChoice()'s 'change' handler) rather
	// than silently guessing the first. Either way the record-level autofill runs too, but ONLY for the very
	// first block (across the whole form) that ever gets a parent. The search field's displayed value is set
	// to the CBA's own label LAST, deliberately AFTER cbaAutofillBlockFromParent() — that call runs
	// blockProjSelectById() internally (it seeds the Project the normal way), which overwrites this same
	// field with the PROJECT's name; re-setting it afterward is what makes the field show the Parent CBA, not
	// the project it resolved to.
	async function blockCbaSelect(blockId, cba) {
		var b = projectBlockById(blockId);
		if (!b) return;
		b.parentAllocationId = cba.allocation_id;

		var dd = document.getElementById('proj_dropdown_' + blockId);
		if (dd) dd.style.display = 'none';

		var isFirstParentOverall = !PROJECT_BLOCKS.some(function(x) { return x.id !== blockId && x.parentAllocationId != null; });

		if (!CBA_EDIT_MODE) {
			if (isFirstParentOverall) await cbaAutofillHeaderFromParent(cba);

			var parentBlocks = await cbaResolveAllParentBlocks(cba);
			populateBlockProjectChoice(blockId, parentBlocks);
			if (parentBlocks.length <= 1) {
				blockClearLines(blockId);
				await cbaAutofillBlockFromParent(blockId, parentBlocks[0] || null);
			}
		}

		var search = document.getElementById('proj_search_' + blockId);
		if (search) search.value = cbaLabelOf(cba);

		baaRefreshAllCbaAmounts(); // this block's own CBA Available Balance ceiling may have changed
		cbaApplyParentCbaLock();
	}

	function blockCbaClearSelection(blockId) {
		var b = projectBlockById(blockId);
		if (b) b.parentAllocationId = null;
		var sel = document.getElementById('project_choice_' + blockId);
		if (sel) {
			sel.innerHTML = '';
			sel.disabled = true;
			sel.required = false;
		}
		var hint = document.getElementById('project_choice_hint_' + blockId);
		if (hint) hint.textContent = 'Pick a Parent CBA first.';
	}

	function blockCbaSetupSearch(blockId) {
		var searchEl = document.getElementById('proj_search_' + blockId);
		var dd = document.getElementById('proj_dropdown_' + blockId);
		if (!searchEl || !dd) return;
		blockWireProjectChoice(blockId);

		searchEl.addEventListener('input', function() {
			var q = this.value.trim().toLowerCase();
			blockCbaClearSelection(blockId);

			var available = blockCbaAvailableData(blockId);
			var filtered = !q ? available : available.filter(function(c) { return cbaSearchText(c).includes(q); });
			blockCbaRenderDropdown(blockId, filtered);
		});

		searchEl.addEventListener('focus', async function() {
			await ensureCbaDataLoaded();
			var b = projectBlockById(blockId);
			if (!b || !b.parentAllocationId) blockCbaRenderDropdown(blockId, blockCbaAvailableData(blockId));
		});

		searchEl.addEventListener('blur', function() {
			setTimeout(function() { dd.style.display = 'none'; }, 150);
		});
	}

	// Historically toggled a "Parent CBA" row that shared its line with Allocation Type; that picker has
	// moved into each project block itself (blockCbaSetupSearch(), above) — nothing left to show/hide here.
	// Kept as a thin stub purely for its one remaining side effect and its several existing call sites
	// (wired to allocation_type's 'change', called at boot and from populateEditForm()).
	function toggleCbaIdRow() {
		applyBudgetVersionVisibilityForDocType();
	}

	// ── Allocation count  →  GET /budget_allocation/counts ───────────────────────
	async function refreshAllocationCount() {
		try {
			var json = await cba_apiFetch(`${API_BASE}/budget_allocation/counts`);
			if (json.success && json.data) {
				var d = json.data;
				CBA_SERIES.CBA = parseInt(d.next_cba_series, 10) || 1;
				CBA_SERIES.RFA = parseInt(d.next_rfa_series, 10) || 1;
				CBA_SERIES.LAA = parseInt(d.next_laa_series, 10) || 1;
				generateCBANumber();
			}
		} catch (err) {
			console.warn('[refreshAllocationCount]', err);
		}
	}

	// (the 2-minute refresh of this is started by cbaStartTimers() - see the Boot block at the end)

	// ── Allocation Number generator ───────────────────────────────────────────────
	// Shared by create mode (generateCBANumber) and edit mode (populateEditForm,
	// when the loaded record has no allocation_number yet) so both paths always
	// produce the same per-type format.
	function buildAllocationNumber(type, docType) {
		type = type || 'CBA';

		var d = new Date();
		var yr = d.getFullYear();
		var yy = String(yr).slice(-2);
		var mo = d.getMonth() + 1;
		var quarter = Math.ceil(mo / 3);
		var moInQuarter = mo - (quarter - 1) * 3;

		var qq = String(quarter).padStart(1, '0');
		var mm = String(moInQuarter).padStart(1, '0');
		var series = String(CBA_SERIES[type] || 1).padStart(2, '0');

		if (type === 'CBA') return 'CBA ' + yr + '-' + qq + mm + '-86-' + series;
		// RFA's middle code: 57 for document type COE, 87 for everything else (CO)
		var rfaCode = (docType === 'COE') ? '57' : '87';
		if (type === 'RFA') return yy + '' + qq + mm + '-' + rfaCode + '-' + series;
		if (type === 'LAA') return 'LAA ' + yr + '-' + qq + mm + '-' + series;
		return type + ' ' + yr + '-' + qq + mm + '-' + series;
	}

	function generateCBANumber() {
		// In edit mode the allocation_number is locked to the value loaded from
		// the server — never overwrite it with a freshly generated series number.
		if (CBA_EDIT_MODE) return;

		var type = document.getElementById('allocation_type').value || 'CBA';
		var num = buildAllocationNumber(type, document.getElementById('document_type').value);

		document.getElementById('allocation_number').value = num;
		document.getElementById('cba_number_display').textContent = num;
	}

	// ── Validation ────────────────────────────────────────────────────────────────
	function setupFormValidation() {
		document.querySelectorAll('[required]').forEach(function(field) {
			field.addEventListener('blur', function() {
				validateField(field);
			});
			field.addEventListener('input', function() {
				if (field.classList.contains('error')) validateField(field);
			});
		});
	}

	function validateField(field) {
		var group = field.closest('.form-group');
		if (!group) return;
		var isEmpty = field.value.trim() === '';
		var invalid = isEmpty;
		// A Requested Amount that is filled in but exceeds its line's Proposed Amount is just as invalid as an
		// empty one — otherwise this generic required-field check (wired to every [required] field, Requested
		// Amount included) would clobber baaValidateRequestedAmount()'s own error state back to "success" on
		// every blur / keystroke, since it only ever looked at emptiness.
		if (!invalid && field.classList.contains('baa-requested-amount')) {
			invalid = baaRequestedExceedsProposed(field.dataset.idx);
		}
		// Vendor Name is pick-only (vendSetupSearch / vendOnType): typed text that never resolved to a real
		// vendor_id is just as invalid as an empty box, even though the box itself isn't empty. Resolve an
		// exact-name match first (vendResolveTyped) so finishing typing the full name still counts as a pick.
		if (!invalid && field.id === 'vendor_search') {
			vendResolveTyped();
			invalid = !document.getElementById('vendor_id').value;
		}
		group.classList.toggle('has-error', invalid);
		field.classList.toggle('error', invalid);
		field.classList.toggle('success', !invalid);
	}

	// ── Supporting Documents ──────────────────────────────────────────────────────
	var _sdocCount = 0;
	var SDOC_MAX_BYTES = 25 * 1024 * 1024; // 25MB

	function addSupportingDocRow(docLabel) {
		_sdocCount++;
		var idx = _sdocCount;
		var list = document.getElementById('supporting_docs_list');
		var row = document.createElement('div');
		row.className = 'sdoc-row';
		row.dataset.idx = idx;
		row.innerHTML =
			'<div class="form-group">' +
			'<label class="sdoc-label">' + (docLabel ? escSdoc(docLabel) : 'Attach File') +
			' <span class="required">*</span></label>' +
			'<div class="sdoc-dropzone" id="sdoc_dz_' + idx + '"' +
			' ondragover="sdocDragOver(event,' + idx + ')"' +
			' ondragleave="sdocDragLeave(event,' + idx + ')"' +
			' ondrop="sdocDrop(event,' + idx + ')">' +
			'<input type="file" id="sdoc_file_' + idx + '"' +
			' accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"' +
			' onchange="sdocFileChange(event,' + idx + ')">' +
			'<svg class="sdoc-dz-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6H16a5 5 0 011 9.9M12 12v9m0-9l-3 3m3-3l3 3"/>' +
			'</svg>' +
			'<p class="sdoc-dz-title">Drag &amp; drop a file, or click to browse</p>' +
			'<span class="sdoc-dz-hint">Max 25MB &middot; PDF, DOC, XLS, JPG, PNG &mdash; the document name is taken from the file</span>' +
			'</div>' +
			'<div class="sdoc-file-chip" id="sdoc_chip_' + idx + '" style="display:none;">' +
			'<span class="sdoc-file-ico"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' +
			'</svg></span>' +
			'<a class="sdoc-file-name" id="sdoc_fname_' + idx + '" target="_blank" rel="noopener noreferrer"></a>' +
			'<span class="sdoc-file-size" id="sdoc_fsize_' + idx + '"></span>' +
			'<span class="sdoc-file-actions">' +
			'<a class="sdoc-file-open" id="sdoc_open_' + idx + '" target="_blank" rel="noopener noreferrer" style="display:none;">' +
			'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="12" height="12">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>' +
			'</svg>Open</a>' +
			'<button type="button" class="sdoc-file-remove" title="Remove this file and choose another" onclick="sdocClearFile(' + idx + ')">Replace</button>' +
			'</span>' +
			'</div>' +
			'<input type="text" class="form-input sdoc-link" id="sdoc_link_' + idx + '"' +
			' placeholder="e.g. https://drive.google.com/…"' +
			' style="display:none;margin-top:8px;">' +
			'<p class="sdoc-upload-note" id="sdoc_note_' + idx + '" style="display:none;"></p>' +
			'<input type="hidden" class="sdoc-name" id="sdoc_name_' + idx + '" value="">' +
			'</div>' +
			'<button type="button" class="sdoc-remove-btn" title="Remove row" aria-label="Remove row" onclick="removeSupportingDocRow(this)">' +
			'<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15">' +
			'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>' +
			'</svg>' +
			'</button>';
		list.appendChild(row);
		return idx;
	}

	// Only these are made clickable: absolute http(s) URLs and root-relative paths.
	// An uploaded file's stored "link" is a storage key, not a URL, and a typed
	// "reference" may be free text — neither is opened (see sdocSetOpen).
	function sdocSafeUrl(u) {
		u = String(u || '').trim();
		return (/^https?:\/\//i.test(u) || /^\/(?!\/)/.test(u)) ? u : '';
	}

	// Wires the chip's file name and Open button to the document's URL, or hides
	// Open (and leaves the name as plain text) when there is nothing to open.
	function sdocSetOpen(idx, rawUrl) {
		var url = sdocSafeUrl(rawUrl);
		var open = document.getElementById('sdoc_open_' + idx);
		var name = document.getElementById('sdoc_fname_' + idx);
		if (url) {
			open.href = url;
			name.href = url;
			open.style.display = 'inline-flex';
		} else {
			open.removeAttribute('href');
			name.removeAttribute('href');
			open.style.display = 'none';
		}
	}

	// Swaps the dropzone for the attached-file chip.
	//   note    — small pill after the name (a file size, or e.g. "Carried over
	//             from parent CBA"); hidden when empty
	//   openUrl — where the name / Open button go (see sdocSetOpen)
	function sdocShowChip(idx, name, note, openUrl) {
		var fname = document.getElementById('sdoc_fname_' + idx);
		fname.textContent = name;
		fname.title = name;
		document.getElementById('sdoc_fsize_' + idx).textContent = note || '';
		sdocSetOpen(idx, openUrl);
		document.getElementById('sdoc_dz_' + idx).style.display = 'none';
		document.getElementById('sdoc_chip_' + idx).style.display = 'flex';
	}

	// Pre-fills a supporting-doc row with a document that is already attached —
	// carried over from the parent CBA when generating an RFA/LAA (see the
	// ?parent_cba_id autofill in the DOMContentLoaded handler), or this
	// allocation's own documents in edit mode. There is no local File object to
	// re-select, so the dropzone is skipped and the row goes straight to the chip
	// state with the document_name/link, which is exactly what getSupportingDocs()
	// reads on submit.
	//   viewUrl — the read endpoint's presigned URL for an uploaded file
	//             (file_meta.view_url); falls back to the link itself when that is
	//             already a URL (a typed Google Drive link, etc.)
	//   note    — optional pill text, e.g. 'Carried over from parent CBA'
	function addExistingSupportingDocRow(name, link, viewUrl, note) {
		// The label is generic: the file name is shown on the chip right below, so
		// repeating it as the label too just doubled it up.
		var idx = addSupportingDocRow('Supporting Document');
		document.getElementById('sdoc_name_' + idx).value = name || '';
		document.getElementById('sdoc_link_' + idx).value = link || '';
		sdocShowChip(idx, name || 'Supporting Document', note, viewUrl || link);
	}

	function removeSupportingDocRow(btn) {
		btn.closest('.sdoc-row').remove();
	}

	function sdocFormatSize(bytes) {
		if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
		return Math.ceil(bytes / 1024) + ' KB';
	}

	function sdocSetNote(idx, message, isError) {
		var note = document.getElementById('sdoc_note_' + idx);
		if (!message) {
			note.style.display = 'none';
			note.textContent = '';
			return;
		}
		note.textContent = message;
		note.className = 'sdoc-upload-note' + (isError ? ' error' : '');
		note.style.display = 'block';
	}

	// Reverts this row to the original manual Document Name / Link fields —
	// used when a dropped file is over the 25MB limit.
	function sdocShowManualMode(idx, message) {
		document.getElementById('sdoc_dz_' + idx).style.display = 'none';
		document.getElementById('sdoc_chip_' + idx).style.display = 'none';
		var link = document.getElementById('sdoc_link_' + idx);
		link.style.display = 'block';
		link.focus();
		sdocSetNote(idx, message, true);
	}

	function sdocDragOver(e, idx) {
		e.preventDefault();
		document.getElementById('sdoc_dz_' + idx).classList.add('drag-over');
	}

	function sdocDragLeave(e, idx) {
		document.getElementById('sdoc_dz_' + idx).classList.remove('drag-over');
	}

	function sdocDrop(e, idx) {
		e.preventDefault();
		document.getElementById('sdoc_dz_' + idx).classList.remove('drag-over');
		if (e.dataTransfer.files && e.dataTransfer.files[0]) {
			sdocProcessFile(idx, e.dataTransfer.files[0]);
		}
	}

	function sdocFileChange(e, idx) {
		if (e.target.files && e.target.files[0]) {
			sdocProcessFile(idx, e.target.files[0]);
		}
	}

	function sdocDeriveName(fileName) {
		return fileName.replace(/\.[^./]+$/, '');
	}

	function sdocProcessFile(idx, file) {
		sdocSetNote(idx, '', false);

		// Document name always comes from the file itself — set it up front so
		// it's captured even for oversized files that fall back to a manual link.
		document.getElementById('sdoc_name_' + idx).value = sdocDeriveName(file.name);

		if (file.size > SDOC_MAX_BYTES) {
			sdocShowManualMode(
				idx,
				'That file is ' + sdocFormatSize(file.size) +
				', which is over the 25MB limit. Please provide a link instead.'
			);
			return;
		}

		var dz = document.getElementById('sdoc_dz_' + idx);
		dz.classList.add('uploading');
		sdocSetNote(idx, 'Uploading…', false);

		var formData = new FormData();
		formData.append('file', file);

		fetch(`${API_BASE}/upload/file`, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					...(CBA_TOKEN ? {
						'X-Authorization': 'Bearer ' + CBA_TOKEN
					} : {})
				},
				body: formData,
			})
			.then(function(res) {
				return res.json().then(function(json) {
					if (!res.ok || !json.success) throw new Error(json.message || 'Upload failed.');
					return json;
				});
			})
			.then(function(json) {
				dz.classList.remove('uploading');
				sdocSetNote(idx, '', false);

				document.getElementById('sdoc_link_' + idx).value = json.link;
				sdocShowChip(idx, file.name, sdocFormatSize(file.size), json.preview_url);
			})
			.catch(function(err) {
				dz.classList.remove('uploading');
				sdocSetNote(idx, err.message || 'Upload failed. Please try again or use a link instead.', true);
			});
	}

	function sdocClearFile(idx) {
		document.getElementById('sdoc_name_' + idx).value = '';
		document.getElementById('sdoc_link_' + idx).value = '';
		document.getElementById('sdoc_file_' + idx).value = '';
		sdocSetOpen(idx, '');
		document.getElementById('sdoc_chip_' + idx).style.display = 'none';
		document.getElementById('sdoc_dz_' + idx).style.display = 'block';
		sdocSetNote(idx, '', false);
	}

	function getSupportingDocs() {
		var rows = document.querySelectorAll('#supporting_docs_list .sdoc-row');
		var result = [];
		rows.forEach(function(row) {
			var name = row.querySelector('.sdoc-name').value.trim();
			var link = row.querySelector('.sdoc-link').value.trim();
			if (name || link) {
				result.push({
					document_name: name,
					link: link
				});
			}
		});
		return result;
	}

	function escSdoc(str) {
		return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	// ── Build payload ─────────────────────────────────────────────────────────────
	// statusId: the id of the memo-request status to give the record (see cbaStatusIdFor), sent as `status_id` — the only
	// field the API reads for it. Leave it out and create gives the new request 'Draft' while update leaves the status
	// alone. (The `status: 'DRAFT' / 'FOR_REVIEW'` text this used to send was never read by the API, so submitting
	// never actually changed a status.)
	function buildPayload(statusId) {
		var g = function(id) {
			return document.getElementById(id);
		};
		var v = function(id) {
			return g(id) ? g(id).value : '';
		};

		var payload = {
			allocation_type: v('allocation_type'),
			// #cba_id is this form's own field id for the "Parent CBA" picker, but the column (and the API's
			// BudgetAllocationService::normalize()/INT_FIELDS) is named parent_id — sent under that name here,
			// and read back under that name in populateEditForm() below, so the link actually persists (it
			// silently didn't before: the backend only ever recognized "parent_id", never "cba_id").
			// parent_id itself is NOT sent any more — an RFA/LAA can now draw from several parent CBAs at
			// once, one per project block (block.parent_allocation_id, set by collectProjectBlocks() below);
			// the server derives its own parent_id as a display-only mirror of the first one.
			// There is no memo_ref_no any more (the column was dropped, alter_table/20): a request is identified by the
			// request_referrence_no (RR-001, ...) that the DATABASE generates when the row is created — nothing to send.
			// The generated allocation number (#allocation_number, e.g. "CBA 2026-111-86-01") is deliberately NOT sent
			// either, and not copied into any other column: numbering is not saved to the database yet. It is still
			// generated into #allocation_number for display / print preview, and on edit the record keeps whatever
			// number it already has (PATCH only changes the fields sent).
			fiscal_year: v('fiscal_year'),
			document_type: v('document_type'),
			// CBA / RFA / LAA send PROJECT_BLOCKS as collected by collectProjectBlocks(): CBA always has
			// exactly one block (no parent_allocation_id); RFA/LAA have one block per selected Parent CBA,
			// each with its own parent_allocation_id — either way, each with its own Program / Sub-Program /
			// Project and its own itemized COB lines. CFA (and "no type chosen yet") has just the single
			// Program → Sub-Program → Project chain above, sent here as that chain's own one-entry equivalent —
			// the server always expects `projects`, never the old flat program_code / line_transaction fields.
			projects: cbaUsesBlockProjectUI() ? collectProjectBlocks() : [(function() {
				var block = { line_transaction: JSON.stringify(collectBaaLines()) };
				if (EDIT_SINGLE_BLOCK_ID != null) block.budget_allocation_project_id = EDIT_SINGLE_BLOCK_ID;
				if (v('program_code')) block.program_code = v('program_code'); else if (pcTyped('prog')) block.program_name = pcTyped('prog');
				if (v('sub_program_code')) block.sub_program_code = v('sub_program_code'); else if (pcTyped('subprog')) block.sub_program_name = pcTyped('subprog');
				if (v('project_code')) block.project_code = v('project_code'); else if (pcTyped('proj')) block.project_name = pcTyped('proj');

				return block;
			})()],
			requested_amount: v('requested_amount_raw'),
			allocated_amount: v('allocated_amount_raw'),
			documents_complete: '1',
			created_by: v('created_by'),
			multiple_node_id: v('multiple_node_id'),
			funding_status: 'PENDING',
			notification_sent: '0',
			// #project_description is this form's own field id for Purpose / Description, but the column (and
			// the API's BudgetAllocationService::normalize()) is named description — sent under that name
			// here (same fix as cba_id -> parent_id above: the API only ever recognized "description", never
			// "project_description", so Purpose / Description silently never saved before this).
			description: v('project_description'),
			remarks: v('remarks'),
			is_bidding: g('is_bidding') ? (g('is_bidding').checked ? '1' : '0') : '0',
			category_id: v('category_id'),
			vendor_id: v('vendor_id'),
			...pcrPayloadFields(), // pr_number + pr_amount, or po_number + po_amount (RFA / LAA)
			file_data: getSupportingDocs(),
		};
		if (statusId != null) payload.status_id = statusId;
		return payload;
	}

	// ── POST to API ───────────────────────────────────────────────────────────────
	async function postToApi(payload) {
		// Settles a typed-but-not-yet-picked exact vendor name match before reading vendor_id below.
		await vendEnsure();
		payload.vendor_id = vendEl('vendor_id').value;

		var nullableFKFields = [
			'category_id',
			'vendor_id',
			'multiple_node_id',
		];
		nullableFKFields.forEach(function(key) {
			if (payload[key] === '' || payload[key] == null) delete payload[key];
		});

		// payload no longer carries a top-level parent_id at all — buildPayload() stopped sending it; each
		// RFA/LAA project block carries its own parent_allocation_id instead (collectProjectBlocks()).

		var fileDocs = Array.isArray(payload.file_data) ? payload.file_data.filter(function(d) {
			return d.document_name || d.link;
		}) : [];
		delete payload.file_data;

		// ── Step 1: Save the main record first, WITHOUT touching multiple_node_id ─
		// Supporting documents are only uploaded after this succeeds — uploading
		// first (the old order) created the file rows / multiple_node_id group
		// up front, so a validation failure on the main record still left those
		// orphaned in the DB, and every retry produced another orphaned group.
		var mainJson;
		if (CBA_EDIT_MODE) {
			// create-only fields the update endpoint doesn't accept / shouldn't
			// be allowed to silently change on an existing record
			delete payload.created_by;
			mainJson = await cba_apiFetchPatch(`${API_BASE}/budget_allocation/update/${CBA_CONFIG.allocationId}`,
				payload);
		} else {
			mainJson = await cba_apiFetchPost(`${API_BASE}/budget_allocation/add`, payload);
		}

		if (!mainJson.success || fileDocs.length === 0) return mainJson;

		// ── Step 2: Upload supporting documents & attach multiple_node_id ─────────
		var allocationId = CBA_EDIT_MODE ? CBA_CONFIG.allocationId : mainJson.allocation_id;
		if (allocationId) {
			var createdBy = parseInt(document.getElementById('created_by').value, 10) || 1;
			try {
				var uploadJson = await cba_apiFetchPost(`${API_BASE}/upload/multi-upload`, {
					created_by: createdBy,
					file_data: fileDocs,
				});
				if (uploadJson.success && uploadJson.multiple_node_id) {
					await cba_apiFetchPatch(`${API_BASE}/budget_allocation/update/${allocationId}`, {
						multiple_node_id: uploadJson.multiple_node_id,
					});
					mainJson.multiple_node_id = uploadJson.multiple_node_id;
				}
			} catch (uploadErr) {
				// Non-fatal: the main record is already saved; log and continue
				// without attaching supporting documents.
				console.warn('[postToApi] Supporting documents upload failed:', uploadErr);
			}
		}

		return mainJson;
	}

	// ── Submit label helpers ──────────────────────────────────────────────────────
	function getTypeLabel() {
		var type = document.getElementById('allocation_type').value || 'CBA';
		if (type === 'RFA') return {
			short: 'RFA',
			full: 'Request for Fund Allotment'
		};
		if (type === 'LAA') return {
			short: 'LAA',
			full: 'Letter of Advice of Allotment'
		};
		return {
			short: 'CBA',
			full: 'Certificate of Budget Allocation'
		};
	}

	// Text of the main button: "Submit Request" when creating (whatever the allocation type), "Update <type>" when
	// editing a saved record.
	function submitButtonLabel() {
		return CBA_EDIT_MODE ? ('Update ' + getTypeLabel().short) : 'Submit Request';
	}

	function updateSubmitLabel() {
		var label = getTypeLabel();
		var span = document.getElementById('submit_btn_label');
		if (span) span.textContent = submitButtonLabel();

		// "CBA Number" header card label — follows the selected allocation type
		// (CBA / RFA / LAA) instead of always reading "CBA Number".
		var numLabel = document.getElementById('cba_number_label');
		if (numLabel) numLabel.textContent = label.short + ' Number';
	}

	// Swal replacement for the browser's alert() (the plain "localhost says" dialog) — same
	// title / text / icon / confirmButtonColor convention as the confirm()-replacing Swal.fire() calls
	// elsewhere in the app (budget-req-page.php, memo-process-readone.php). Swal is loaded globally by
	// views/layout.php. Returns the Swal.fire() promise so a caller that needs to wait for it to be
	// dismissed (e.g. before redirecting) can await it; others can fire-and-forget.
	function cbaNotify(title, text, icon) {
		return Swal.fire({
			title: title,
			text: text,
			icon: icon || 'info',
			confirmButtonColor: '#0f2045',
		});
	}

	// ── Submit decision ────────────────────────────────────────────────────────────
	// What "Submit Request" / "Update" turns a request into — always 'For Review'; there is no Draft option on
	// this form. `status` is the NAME of a memo-request status (fims.settings_status, status_for =
	// 'budget_allocation'); its id comes from the API (cbaStatusIdFor), so no install-specific id lives here.
	// Ships with sample_data/06 - fims_settings_status_budget_allocation.sql.
	var CBA_SUBMIT_DECISION = {
		status: 'For Review',
		done: 'submitted for review',
		busy: 'Submitting'
	};

	// Resolves with the id of the memo-request status called `name`. Throws — before anything is saved — when the
	// statuses cannot be read or that one is not set up. Read on every use, so a status added while the page was open
	// is found.
	async function cbaStatusIdFor(name) {
		var json = await cba_apiFetch(`${API_BASE}/budget_allocation/statuses`);
		if (!json.success || !Array.isArray(json.data)) {
			throw new Error(json.message || 'Could not load the request statuses.');
		}
		var hit = json.data.find(function(s) {
			return String(s.name || '').trim().toLowerCase() === name.toLowerCase();
		});
		if (!hit) {
			throw new Error('The status "' + name + '" is not set up in the database yet, so nothing was saved. ' +
				'Ask an administrator to run the SQL installer (node install_system.js --sql-only).');
		}
		return parseInt(hit.id, 10);
	}

	// Asks for confirmation before submitting: resolves true when confirmed, false when cancelled. A plain
	// SweetAlert2 confirm — there is no status choice any more, every submission goes to 'For Review'.
	async function cbaAskDecision(typeLabel) {
		var result = await Swal.fire({
			title: CBA_EDIT_MODE ? 'Save changes?' : 'Submit this request?',
			text: CBA_EDIT_MODE ?
				('Your changes to this ' + typeLabel.full + ' will be saved and submitted for review.') :
				('This ' + typeLabel.full + ' will be submitted for review.'),
			icon: 'question',
			showCancelButton: true,
			confirmButtonText: CBA_EDIT_MODE ? 'Yes, save' : 'Yes, submit',
			cancelButtonText: 'Cancel',
			confirmButtonColor: '#0f2045',
		});
		return result.isConfirmed;
	}

	// ── Submit ────────────────────────────────────────────────────────────────────
	async function cbaOnSubmit(e) {
		e.preventDefault();

		var valid = true;
		this.querySelectorAll('[required]').forEach(function(field) {
			if (field.value.trim() === '') {
				validateField(field);
				valid = false;
			}
		});
		if (!valid) {
			await cbaNotify('Missing Information', 'Please fill in all required fields marked with *', 'warning');
			return;
		}

		// Vendor Name is pick-only — a real vendor from the dropdown, typed to search. The generic required-
		// fields loop above only rejects an EMPTY box, so a name typed but never actually picked (no exact
		// match either) would otherwise sail through: vendor_id stays blank and postToApi() silently drops it
		// from the payload, saving the record with no vendor at all. Caught here instead.
		var vendorSearchEl = document.getElementById('vendor_search');
		if (vendorSearchEl && vendorSearchEl.required) vendResolveTyped(); // settle an exact-name match before checking
		if (vendorSearchEl && vendorSearchEl.required && !document.getElementById('vendor_id').value) {
			validateField(vendorSearchEl);
			await cbaNotify('Invalid Vendor', 'Type to search, then pick a vendor from the list — "' +
				vendorSearchEl.value.trim() + '" does not match any vendor on file.', 'warning');
			vendorSearchEl.focus();
			return;
		}

		// Every project block must have EITHER a Parent CBA picked, OR be a direct COE draw (directCob — no
		// Parent CBA, no Project) for RFA / LAA — the backend refuses to save otherwise
		// (BudgetAllocationService::validateReferences()), but checked here too so the user sees a clear
		// message instead of a generic save error.
		if (isRfaOrLaa() && (PROJECT_BLOCKS.length === 0 ||
				PROJECT_BLOCKS.some(function(b) { return !b.parentAllocationId && !b.directCob; }))) {
			await cbaNotify('Parent CBA Required',
				'Select an approved Parent CBA, or add a Direct COE Account, to fund every Funding Allocation in this ' +
				getTypeLabel().short + '.', 'warning');
			return;
		}

		// Every project block (CBA/CFA/RFA/LAA block-mode) needs at least one account code with a positive
		// Requested Amount — mirrors BudgetAllocationService::validateReferences()'s own universal check, so
		// a block saved with nothing actually picked can never slip through on either side.
		if (!validateProjectBlocksHaveAccountCodes()) {
			await cbaNotify('Account Code Required',
				'Every Funding Allocation must have at least one account code with a Requested Amount.', 'warning');
			return;
		}

		// Budget Version is required on every line for RFA / LAA.
		if (!validateBudgetVersionsRequired()) {
			await cbaNotify('COB Required', 'COB is required for ' + getTypeLabel().short +
				'. Please select one for every Budget Allocation line.', 'warning');
			return;
		}

		// Requested Amount must not exceed the picked COB's Available Amount, on every line.
		if (!validateRequestedAmountsAgainstProposed()) {
			await cbaNotify('Amount Exceeded',
				'Requested Amount cannot exceed the Available Amount for one or more Budget Allocation lines.', 'warning');
			return;
		}

		// Supporting documents are required on create only — an existing
		// allocation being edited may already have its documents attached
		// (or intentionally removed via workflow), so don't block updates.
		if (!CBA_EDIT_MODE && getSupportingDocs().length === 0) {
			await cbaNotify('Supporting Document Required',
				'Please attach at least one supporting document before submitting.', 'warning');
			return;
		}

		var req = parseFloat(document.getElementById('requested_amount_raw').value) || 0;
		var alloc = parseFloat(document.getElementById('allocated_amount_raw').value) || 0;
		if (alloc > req && alloc > 0) {
			var allocWarning = await Swal.fire({
				title: 'Allocated Amount Exceeds Requested',
				text: 'Allocated amount exceeds requested amount. Continue?',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Yes, continue',
				cancelButtonText: 'Cancel',
				confirmButtonColor: '#0f2045',
			});
			if (!allocWarning.isConfirmed) return;
		}
		var typeLabel = getTypeLabel();

		// Confirm, then submit for review. Cancelled -> nothing is sent.
		var confirmed = await cbaAskDecision(typeLabel);
		if (!confirmed) return;
		var decision = CBA_SUBMIT_DECISION;

		var submitBtn = this.querySelector('button[type="submit"]');
		submitBtn.disabled = true;
		submitBtn.innerHTML = '<span>' + decision.busy + '&hellip;</span>';

		try {
			// The id of the chosen status, looked up first: when it cannot be found this throws and NOTHING has been saved.
			var statusId = await cbaStatusIdFor(decision.status);
			var json = await postToApi(buildPayload(statusId));
			if (!json.success) throw new Error(json.message || (CBA_EDIT_MODE ? 'Update failed.' :
				'Submission failed.'));
			await cbaNotify('Success', typeLabel.full + ' ' + (CBA_EDIT_MODE ? 'updated and ' : '') + decision.done + '.', 'success');
			if (cbaAfterSave()) return; // modal mode: closed + list refreshed, nothing to redirect to
			var docType = document.getElementById('document_type').value;
			window.location.href = docType === 'COE' ? '/FMD/CoeBudgetAllocationDashboard' :
				'/FMD/BudgetAllocationDashboard';
		} catch (err) {
			console.error('[submitCBA]', err);
			cbaNotify('Error', (CBA_EDIT_MODE ? 'Update error: ' : 'Submission error: ') + err.message, 'error');
			submitBtn.disabled = false;
			var restoredLabel = submitButtonLabel();
			submitBtn.innerHTML =
				'<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> <span id="submit_btn_label">' +
				restoredLabel + '</span>';
		}
	}

	// ── Block Enter outside textarea ──────────────────────────────────────────────
	function cbaBlockEnter(e) {
		if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') e.preventDefault();
	}

	// ── Print Preview ─────────────────────────────────────────────────────────────
	// Builds the payload from the form and opens the shared print preview
	// (app/pages/readone/print-preview-readone.php → POST /api/print/{doc}), picking
	// the print form from allocation_type:
	//   CBA → CBA (CO / COE from document_type)   RFA → RFA   LAA → LAA
	//   CFA → CFA (Certified Funds Available — the funding-request memo FMD certifies)
	// A Regional / District requesting office prints the regional CBA.
	// Field names: system_docs/modules/PRINT-FORMS.md.
	// Print paused for this type (RFA / LAA — see PRP_PAUSED in readone/print-preview-readone.php): button disabled.
	function cbaSyncPrintPaused() {
		var pb = document.querySelector('.btn-print');
		if (!pb || pb.dataset.generating) return;
		var t = (document.getElementById('allocation_type') || {}).value || '';
		var paused = typeof prp_isPaused === 'function' && prp_isPaused(t);
		pb.disabled = paused;
		pb.style.opacity = paused ? '.45' : '';
		pb.style.cursor = paused ? 'not-allowed' : '';
		pb.title = paused ? 'Printing the ' + t + ' is paused for now' : '';
	}

	async function printCBA() {
		var btn = document.querySelector('.btn-print');
		if (!btn) return;
		// The CBA / RFA / LAA forms are for FMD users and administrators to print (the button is hidden for anyone else; this and
		// the API are the real checks).
		var printGateType = (document.getElementById('allocation_type') || {}).value || 'CBA';
		if (CBA_PRINT_RESTRICTED_TYPES.indexOf(printGateType) >= 0 && !CBA_CAN_PRINT_FORM) {
			cbaNotify('Not allowed', 'Only the Financial Management Department or an administrator can print the ' + printGateType + '.', 'error');
			return;
		}
		btn.disabled = true;
		btn.innerHTML = '<span>Generating&hellip;</span>';

		try {
			var g = function(id) {
				return document.getElementById(id);
			};
			var v = function(id) {
				return g(id) ? g(id).value : '';
			};

			var allocType = v('allocation_type') || 'CBA';

			// ── Print form (PrintService DOCS key) ────────────────────────────────
			var printType = {
				CBA: 'CBA',
				RFA: 'RFA',
				LAA: 'LAA',
				CFA: 'CFA'
			}[allocType] || 'CBA';
			// The requesting office (the creator's RC): a Regional / District office prints the regional CBA / CAF.
			var isRegionalOffice = ['REGIONAL', 'DISTRICT'].indexOf(cbaRcDeptType(CBA_REQ_RC.office_type)) >= 0;
			// Every line currently entered — CBA / RFA / LAA's project blocks (collectBaaLines() alone only
			// sees CFA's own single chain, since block-scoped lines are tagged in BAA_ROW_BLOCK).
			var allLines = cbaUsesBlockProjectUI() ?
				PROJECT_BLOCKS.reduce(function(acc, b) {
					return acc.concat(BAA_ROWS.filter(function(idx) { return BAA_ROW_BLOCK[idx] === b.id; }).map(baaLineObject));
				}, []).filter(baaLineIsMeaningful) :
				collectBaaLines();
			// "Sources of Fund" on the CO certificate: the Type of Fund of the first budget line that has one (each line's
			// Type of Fund comes with its picked COB line item).
			var fundSourceName = (function() {
				var line = allLines.find(function(l) { return l.type_of_fund; });
				return line ? String(line.type_of_fund).trim() : '';
			})();
			// Program / Sub-Program / Project printed on the memo — CBA / RFA / LAA's project blocks have no
			// program_code / project_code hidden field at all (those only exist for CFA's single chain):
			// the FIRST block's own picks are shown, same "first block represents the whole print" convention
			// BudgetAllocationService::attachProjects() already uses server-side.
			var firstBlock = cbaUsesBlockProjectUI() ? PROJECT_BLOCKS[0] : null;
			var printProgramCode = firstBlock ? (firstBlock.programCode || '') : v('program_code');
			var printProgramName = firstBlock ? (firstBlock.programName || '') : ((g('program_code') && g('program_code').dataset.label) || pcTyped('prog'));
			var printSubCode = firstBlock ? (firstBlock.subProgramCode || '') : v('sub_program_code');
			var printSubName = firstBlock ? (firstBlock.subProgramName || '') : ((g('sub_program_code') && g('sub_program_code').dataset.label) || pcTyped('subprog'));
			var printProjectCode = firstBlock ? (firstBlock.projectCode || '') : v('project_code');
			var printProjectName = firstBlock ? (firstBlock.projectName || '') : ((g('project_code') && g('project_code').dataset.label) || pcTyped('proj'));

			// ── Base payload (fields common to all three types) ───────────────────
			var payload = {
				allocation_number: v('allocation_number'),
				allocation_type: allocType,
				fiscal_year: v('fiscal_year'),
				fiscal_year_label: v('fiscal_year'), // the value IS the label — just the year
				document_type: v('document_type'),
				// Budget Allocation Category (e.g. "Goods and Services") — printed
				// below "Sources of Fund" on CO documents (see gen_cba_docx.js).
				category_name: g('category_id') ? (g('category_id').dataset
					.label || '') : '',
				program_code: printProgramCode,
				program_name: printProgramName,
				sub_program_code: printSubCode,
				sub_program_name: printSubName,
				project_code: printProjectCode,
				project_name: printProjectName,
				project_description: v('project_description'),
				requested_amount: v('requested_amount_raw'),
				allocated_amount: v('allocated_amount_raw'),
				remarks: v('remarks'),
				// Controls the CO certificate's validity-clause wording (CbaPrintService):
				// checked = "for bidding purposes", unchecked = "for whatever legal purpose it may serve".
				is_bidding: g('is_bidding') ? g('is_bidding').checked : false,
				fund_source_name: fundSourceName,
				// Edit mode: the record's created_at; add mode: null -> today's date.
				date: CBA_CREATED_AT ? new Date(CBA_CREATED_AT).toLocaleDateString('en-US', { month: 'long', day: allocType === 'LAA' ? 'numeric' : '2-digit', year: 'numeric' }) : null,
			};
			if (isRegionalOffice) payload.issuing_office = 'REGIONAL';
			// A CBA prints ONE amount: the Total Requested Amount (requested_amount_raw mirrors it), else the allocated one.
			// The regional CBA reads `amount ?? allocated_amount ?? requested_amount`, and an EMPTY allocated_amount ('') would
			// win over the requested one and print P0.00 — so say the amount outright, as PrintPayloadService does for a saved CBA.
			if (allocType === 'CBA') payload.amount = v('requested_amount_raw') || v('allocated_amount_raw');

			// ── CFA → the CAF print form (Certificate as to Availability of Funds) ─
			// ── CFA → the CFA funding-request memo (CfaPrintService) ──────────────
			if (printType === 'CFA') {
				payload.cfa_number = v('allocation_number');
				// The memo's request: the Purpose / Description text as typed (each line a paragraph;
				// "Label : value" lines print as an indented list).
				payload.body = v('project_description');
				// FMD's "Certified Funds Available: Php …" + "RFA# <linked RFA> / <fund source>".
				payload.amount = v('requested_amount_raw');
				payload.rfa_number = v('cba_id') && g('cba_search') ? g('cba_search').value.trim() : '';
				// The CFA section: FOR / THRU / Subject, Main Office / Regional Offices, the paragraphs after
				// that list, fund source, page-2 title and the signatories — printed only as entered.
				Object.assign(payload, cfaPayloadFields());
			}

			// ── RFA-specific fields ───────────────────────────────────────────────
			// gen_rfa_docx.js reads: rfa_no, payee_name, particulars, amount,
			// rfa_type, cert_correct_name/title/office, signatory_name_2..4,
			// project_name, total_estimated_cost, total_funds_available,
			// total_funded_to_date, balance, payments_outstanding, this_request,
			// total_payments, total_contract_cost, adjustment_additive/deductive,
			// total_allotment, funding_source (object with saro_no, saro_date,
			// or_no, or_date, corporate_receipts, ng_support, subsidy, equity,
			// agency_name), certFundsName/Title, recommendingName/Title,
			// approvedName/Title.
			if (allocType === 'RFA') {
				payload.rfa_no = v('allocation_number');
				payload.amount = v('requested_amount_raw');
				payload.particulars = v('project_description');
				// Payee = the requesting office: the Responsibility Center of the user who created the memo request
				// (CBA_REQ_RC: uac.users.rc_id -> fims.responsibility_code).
				payload.payee_name = CBA_REQ_RC.rc_name || '';
				// Picks the RFA template: a MAIN (central) office prints the STAFF form ("(STAFF)", filled out by
				// FMD), a Regional / District office the REGIONAL OFFICE form — from the RC's office_type.
				payload.requesting_department_type = cbaRcDeptType(CBA_REQ_RC.office_type);
				// RFA type line = "<CATEGORY> – Php 30 Million and below (<OFFICE>)", using
				// the selected Budget Allocation Category's name. RfaPrintService (preview / PDF) and
				// gen_rfa_docx.js both use payload.rfa_type ahead of their default
				// ("GOODS AND SERVICES – …"), so setting it here changes the preview and
				// the DOCX together. No category selected -> left unset (default applies).
				(function() {
					var cat = String(payload.category_name || '').trim();
					if (!cat) return;
					var isStaff = payload.requesting_department_type === 'MAIN';
					payload.rfa_type = cat.toUpperCase() + ' – Php 30 Million and below (' +
						(isStaff ? 'STAFF' : 'REGIONAL OFFICE') + ')';
				})();
				// Pass through any pre-filled signatory fields if present on the form;
				// fall back to empty strings so the JS blanks them cleanly.
				payload.cert_correct_name = v('cert_correct_name') || '';
				payload.cert_correct_title = v('cert_correct_title') || '';
				payload.cert_correct_office = v('cert_correct_office') || '';
				payload.signatory_name_2 = v('signatory_name_2') || '';
				payload.signatory_position_2 = v('signatory_position_2') || '';
				payload.signatory_name_3 = v('signatory_name_3') || '';
				payload.signatory_position_3 = v('signatory_position_3') || '';
				payload.signatory_name_4 = v('signatory_name_4') || '';
				payload.signatory_position_4 = v('signatory_position_4') || '';
				// A COB is an approved budget line item: it has no fund balance or SARO reference of its own, so those
				// print blank (total funds available falls back to the amounts below).
				var rfaBvFundBalance = 0;
				var rfaSaroRef = '';
				var rfaSaroDate = '';

				// Financial reference fields — use raw amounts where applicable
				payload.total_estimated_cost = v('requested_amount_raw') || '0';
				// "Total funds made available for the year" is the fund balance of
				// the requested Budget Version, not the requested/allocated amount.
				payload.total_funds_available = rfaBvFundBalance > 0 ?
					rfaBvFundBalance :
					(v('allocated_amount_raw') || v('requested_amount_raw') || '0');
				payload.this_request = v('requested_amount_raw') || '0';
				payload.total_allotment = v('requested_amount_raw') || '0';
				payload.total_contract_cost = v('requested_amount_raw') || '0';
				// Funding source sub-object (SARO/OR details)
				payload.funding_source = {
					saro_no: rfaSaroRef,
					saro_date: rfaSaroDate,
					or_no: v('or_no') || '',
					or_date: v('or_date') || '',
					corporate_receipts: g('fs_corporate') ? g('fs_corporate').checked : false,
					ng_support: g('fs_ng_support') ? g('fs_ng_support').checked : false,
					subsidy: g('fs_subsidy') ? g('fs_subsidy').checked : false,
					equity: g('fs_equity') ? g('fs_equity').checked : false,
					agency_name: v('agency_name') || '',
				};
			}

			// ── LAA-specific fields ───────────────────────────────────────────────
			// gen_laa_docx.js reads: date (ISO), for_name, for_title, thru_name,
			// thru_title, subject, description, amount, sig_name, sig_title,
			// laa_number.
			if (allocType === 'LAA') {
				payload.laa_number = v('allocation_number');
				payload.amount = v('requested_amount_raw');
				payload.description = v('project_description');
				// Signatory fields — use dedicated hidden inputs if present, else blank
				payload.for_name = v('laa_for_name') || '';
				payload.for_title = v('laa_for_title') || '';
				payload.thru_name = v('laa_thru_name') || '';
				payload.thru_title = v('laa_thru_title') || '';
				payload.sig_name = v('laa_sig_name') || '';
				payload.sig_title = v('laa_sig_title') || '';
				// subject has a sensible default inside gen_laa_docx.js; only
				// override if there is a dedicated field on this form.
				if (v('laa_subject')) payload.subject = v('laa_subject');

				// office_name = the requesting office (the creator's Responsibility Center name) — the same label
				// RFA uses for its payee. The COE LAA reads "To transfer obligation made to <office> to cover the
				// payment for the <description>"; the CO LAA prints it as the addressee and in the transfer text
				// (LaaPrintService).
				payload.office_name = CBA_REQ_RC.rc_name || '';
			}

			// ── The shared print preview: HTML preview, Download PDF / DOCX, letterhead.
			// The signatory (FMD Department Manager) is filled in server-side when blank.
			await prp_open(payload, { type: printType });

		} catch (err) {
			console.error('[printCBA]', err);
			cbaNotify('Error', 'Print error: ' + err.message, 'error');
		} finally {
			btn.disabled = false;
			btn.innerHTML =
				'<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg> Print Preview';
		}
	}

	// ==============================================================================
	// BOOT + MODAL CONTROLLER
	//
	// Page mode  (views/BudgetAllocationForm.php): boots once, exactly as it always did.
	// Modal mode (memo-req-page.php sets $cbaAsModal): nothing runs at page load. The
	//   lookups, the 2-minute counter refresh and the loading overlay all start when the user
	//   opens the modal, and every open starts from the untouched markup, so a form that was
	//   cancelled or saved never leaks into the next one.
	// This block must stay LAST: boot reads variables declared throughout the script above.
	// ==============================================================================
	var CBA_COUNT_TIMER = null;
	var CBA_PRISTINE_HTML = null; // the form markup exactly as the server rendered it

	function cbaStartTimers() {
		if (!CBA_COUNT_TIMER) CBA_COUNT_TIMER = setInterval(refreshAllocationCount, 2 * 60 * 1000);
	}

	function cbaStopTimers() {
		clearInterval(CBA_COUNT_TIMER);
		CBA_COUNT_TIMER = null;
	}

	// Everything the form remembers between loads. Besides the data caches this includes
	// "wire once" flags and a cached <option> node that belong to one specific copy of the DOM:
	// left over from a previous open they would point at removed elements, so the combobox
	// search boxes would never get their listeners again on the second open.
	function cbaResetState() {
		CBA_CREATED_AT = null;
		CBA_REQ_RC = Object.assign({ rc_id: '', rc_code: '', rc_name: '', office_type: '' }, CBA_CONFIG.requestingRc || {});   // back to the user's own RC
		pcResetState();
		clearTimeout(CAT_DEBOUNCE); CAT_DEBOUNCE = null;
		CAT_ALL_DATA = [];
		clearTimeout(VEND_DEBOUNCE); VEND_DEBOUNCE = null; VEND_SEQ++;
		VEND_ALL_DATA = [];
		VEND_MODE = 'none';
		BAA_ROWS = []; BAA_ROW_SEQ = -1; BAA_ROW_BLOCK = {};
		BAA_REMOVED = {};
		var cfaRestoreBtn = document.getElementById('baa_restore_btn');
		if (cfaRestoreBtn) cfaRestoreBtn.style.display = 'none';
		PROJECT_BLOCKS = []; PROJECT_BLOCK_SEQ = -1; EDIT_SINGLE_BLOCK_ID = null;
		PROJ_ALL_DATA = []; PROJ_LOADED = false; PROJ_LOAD_PROMISE = null; // same "refetch every open" precedent as CAT_ALL_DATA / VEND_ALL_DATA below
		PROJ_MINE_DATA = []; PROJ_MINE_LOADED = false; PROJ_MINE_LOAD_PROMISE = null;
		COB_EPOCH++; COB_DATA = {}; COB_SIG = {}; COB_SEQ = {}; COB_LOAD = {}; COB_SEL = {}; COB_WIRED = {};
		COB_SYNC_QUEUED = false; COB_KEEP_SELECTION = 0;
		// Earmarks against a COB can change between modal opens (an RFA/LAA approved elsewhere converts part
		// of a CBA's reservation) — a stale cache here would keep showing an outdated CBA Available Balance /
		// "Where this COB's balance is held" drilldown for the same COB in a fresh request.
		BAA_EARMARKS = {}; COB_EARMARKS_CACHE = {}; BAA_CBA_AMT_SEQ = {};
		CBA_PARENT_LOCK_PREV = {}; // a fresh modal open starts from pristine (unlocked) markup
		ALLOC_CFA_OPTION = null;
		CBA_DATA = []; CBA_LOADED = false;
		_sdocCount = 0;
	}

	// After a successful save. Modal mode: close and refresh the host page's list (returns true).
	// Page mode: does nothing and returns false, so the caller keeps its own behaviour.
	function cbaAfterSave() {
		if (!CBA_AS_MODAL) return false;
		window.cbaModal.close();
		if (typeof window.mrq_load === 'function') window.mrq_load();
		return true;
	}

	// The Cancel button and the modal's close button.
	function cbaCancel() {
		if (CBA_AS_MODAL) window.cbaModal.close();
		else window.history.back();
	}

	if (CBA_AS_MODAL) {
		window.cbaModal = {
			// open()    — a new memo request.
			// open(id)  — edit that record (fims.budget_allocation.allocation_id): the same form, filled in from
			//             GET /budget_allocation/read/{id} and saved with PATCH /budget_allocation/update/{id}. Used by the
			//             list's Edit action instead of the full-page BudgetAllocationForm, which has its own page permission.
			open: function(id) {
				var container = document.querySelector('.cba-form-container');
				if (CBA_PRISTINE_HTML === null) CBA_PRISTINE_HTML = container.innerHTML; // first open: still untouched
				else container.innerHTML = CBA_PRISTINE_HTML;                            // later opens: start over
				cbaResetState();

				// create or edit — decided here, before cbaBoot() reads it. The markup was just reset to the server's
				// "create" rendering, so the edit-only parts are switched on (or left off) now.
				var editId = Number(id) > 0 ? Number(id) : null;
				CBA_CONFIG.allocationId = editId;
				CBA_EDIT_MODE = !!editId;
				document.getElementById('cba-modal-title').textContent = editId ? 'Edit Memo Request' : 'New Memo Request';
				var numberCard = container.querySelector('.form-header');   // the number / date card: shown for a saved record only
				if (numberCard) numberCard.style.display = editId ? '' : 'none';
				var idField = document.getElementById('allocation_id');
				if (idField) idField.value = editId ? String(editId) : '';
				document.getElementById('cba-overlay').classList.add('open');
				document.getElementById('cba-modal-body').scrollTop = 0;
				cbaBoot();
				cbaStartTimers();
			},
			close: function() {
				document.getElementById('cba-overlay').classList.remove('open');
				cbaStopTimers();
			},
		};
	} else if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			cbaBoot();
			cbaStartTimers();
		});
	} else {
		cbaBoot();
		cbaStartTimers();
	}
</script>
