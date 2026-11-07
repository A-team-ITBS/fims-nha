<!-- ── ADD / EDIT MODAL ─────────────────────────────────── -->
<div id="bgr-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-sm">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="bgr-modal-title">Add Budget Request</h3>
            <button type="button" class="nha-modal-close" id="bgr-modal-close">&times;</button>
        </div>
        <form id="bgr-form">
            <div class="nha-modal-body">
                <input type="hidden" id="bgr-mode" value="add">
                <input type="hidden" id="bgr-id" value="">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Fiscal Year <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="bgr-fiscal-yr" required min="2000" max="2100" placeholder="e.g. 2026">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Allocate To <span class="text-danger">*</span></label>
                        <select class="form-select" id="bgr-type" required>
                            <option value="PROGRAM">Program</option>
                            <option value="PROJECT">Project</option>
                        </select>
                    </div>
                    <div class="col-12" id="bgr-program-wrap">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" id="bgr-program-code">
                            <option value="">— Select program —</option>
                        </select>
                    </div>
                    <div class="col-12" id="bgr-project-wrap" hidden>
                        <label class="form-label">Project <span class="text-danger">*</span></label>
                        <select class="form-select" id="bgr-project-code">
                            <option value="">— Select project —</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">&#8369;</span>
                            <input type="number" class="form-control text-end" id="bgr-amount" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                </div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn-ghost" id="bgr-cancel">Cancel</button>
                <button type="submit" class="nha-btn-primary" id="bgr-save">Save</button>
            </div>
        </form>
    </div>
</div>
