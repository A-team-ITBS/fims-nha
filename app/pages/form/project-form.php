<!-- ── ADD / EDIT MODAL ─────────────────────────────────── -->
<div id="proj-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-sm">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="proj-modal-title">Add Project</h3>
            <button type="button" class="nha-modal-close" id="proj-modal-close">&times;</button>
        </div>
        <form id="proj-form">
            <div class="nha-modal-body">
                <input type="hidden" id="proj-mode" value="add">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Project Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="proj-project-code" required maxlength="20">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Project Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="proj-project-name" required maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Program <span class="text-danger">*</span></label>
                        <select class="form-select" id="proj-program-code" required>
                            <option value="">— Select program —</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn-ghost" id="proj-cancel">Cancel</button>
                <button type="submit" class="nha-btn-primary" id="proj-save">Save</button>
            </div>
        </form>
    </div>
</div>
