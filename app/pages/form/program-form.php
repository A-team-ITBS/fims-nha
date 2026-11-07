<!-- ── ADD / EDIT MODAL ─────────────────────────────────── -->
<div id="prg-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-sm">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="prg-modal-title">Add Program</h3>
            <button type="button" class="nha-modal-close" id="prg-modal-close">&times;</button>
        </div>
        <form id="prg-form">
            <div class="nha-modal-body">
                <input type="hidden" id="prg-mode" value="add">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Program Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="prg-program-code" required maxlength="20">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Group</label>
                        <input type="text" class="form-control" id="prg-group" maxlength="100">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Program Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="prg-program-name" required maxlength="255">
                    </div>
                </div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn-ghost" id="prg-cancel">Cancel</button>
                <button type="submit" class="nha-btn-primary" id="prg-save">Save</button>
            </div>
        </form>
    </div>
</div>
