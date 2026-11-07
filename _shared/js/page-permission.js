/* ============================================================
 * Shared client-side page-permission gating for FIMS custom pages.
 * ------------------------------------------------------------
 * Pairs with the server-side \PagePermission gate. THIS IS UX ONLY —
 * hiding a button does not secure anything; the API must still enforce
 * permissions server-side. It simply stops users from seeing/clicking
 * actions their user level is not allowed to perform.
 *
 * USAGE (near the top of an app/pages/*.php file, after $permJson is set):
 *   <script src="/_shared/js/page-permission.js"></script>
 *   <script>PagePerm.set(<?= $permJson ?>);</script>
 *
 * Then tag any action button/link with data-perm:
 *   <button data-perm="add"    onclick="showForm()">New</button>
 *   <button data-perm="edit"   onclick="edit(id)">Edit</button>
 *   <button data-perm="delete" onclick="del(id)">Delete</button>
 *   <button data-perm="deactivate" onclick="deactivate(id)">Deactivate</button>
 *   <button data-perm="activate"   onclick="activate(id)">Activate</button>
 *
 * A MutationObserver re-applies gating to buttons added later (e.g. rows
 * rendered via innerHTML), so dynamically-created buttons are covered too.
 *
 * Flag mapping:
 *   add/create/new      -> canAdd
 *   edit/update         -> canEdit
 *   delete/remove       -> canDelete
 *   deactivate          -> canDelete   (soft-delete)
 *   activate/reactivate -> canEdit     (status re-enable)
 *   view/list/search/export/import/lookup/push -> matching flag
 *
 * Default behaviour when not permitted: the element is HIDDEN.
 * Add data-perm-mode="disable" to grey it out (with a tooltip) instead.
 * ============================================================ */
(function (w) {
    "use strict";

    var FLAGS = {
        add: "canAdd", create: "canAdd", "new": "canAdd",
        edit: "canEdit", update: "canEdit",
        "delete": "canDelete", remove: "canDelete", destroy: "canDelete",
        deactivate: "canDelete",              // soft-delete
        activate: "canEdit", reactivate: "canEdit",  // status re-enable
        view: "canView", list: "canList", search: "canSearch",
        "export": "canExport", "import": "canImport",
        lookup: "canLookup", push: "canPush"
    };

    var PagePerm = {
        perm: { isAdmin: false },

        // Wire in the server-provided permission object and gate the page.
        set: function (p) {
            this.perm = p || { isAdmin: false };
            var self = this;
            if (document.body) {
                self.apply(document);
                self._observe();
            } else {
                document.addEventListener("DOMContentLoaded", function () {
                    self.apply(document);
                    self._observe();
                });
            }
            return this;
        },

        // True if the current user may perform the given action.
        can: function (action) {
            if (this.perm && this.perm.isAdmin) return true;
            var key = FLAGS[String(action).toLowerCase()] || action;
            return !!(this.perm && this.perm[key]);
        },

        // Hide or disable a single element based on its data-perm attribute.
        gate: function (el) {
            if (!el || el.__permChecked) return;
            var action = el.getAttribute && el.getAttribute("data-perm");
            if (!action) return;
            el.__permChecked = true;
            if (this.can(action)) return;

            var mode = el.getAttribute("data-perm-mode") || "hide";
            if (mode === "disable") {
                el.disabled = true;
                el.classList.add("disabled");
                el.setAttribute("aria-disabled", "true");
                el.title = "You do not have permission to " + action + ".";
                el.style.pointerEvents = "none";
                el.style.opacity = "0.5";
            } else {
                el.style.display = "none";
            }
        },

        // Gate every [data-perm] element under root (default: whole document).
        apply: function (root) {
            var self = this;
            (root || document).querySelectorAll("[data-perm]").forEach(function (el) {
                self.gate(el);
            });
        },

        // Watch for dynamically-inserted buttons and gate them on the fly.
        _observe: function () {
            if (this._observer || !w.MutationObserver || !document.body) return;
            var self = this;
            this._observer = new MutationObserver(function (muts) {
                muts.forEach(function (m) {
                    m.addedNodes.forEach(function (n) {
                        if (n.nodeType !== 1) return;
                        if (n.hasAttribute && n.hasAttribute("data-perm")) self.gate(n);
                        if (n.querySelectorAll) {
                            n.querySelectorAll("[data-perm]").forEach(function (el) { self.gate(el); });
                        }
                    });
                });
            });
            this._observer.observe(document.body, { childList: true, subtree: true });
        }
    };

    w.PagePerm = PagePerm;
})(window);
