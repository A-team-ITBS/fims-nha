/**
 * Create Date/Time Picker (for PHPMaker 2024)
 * @license Copyright (c) e.World Technology Limited. All rights reserved.
 */

tempusDominus.Namespace.css.toggleMeridiem = "toggleMeridiem,btn,btn-primary";

// Global options
ew.dateTimePickerOptions = {
    keepInvalid: true,
    localization: {
        dayViewHeaderFormat: { month: "long", year: "numeric" }
    }
};

// Create date/time picker
ew.createDateTimePicker = function(formid, id, options) {
    if (id.includes("$rowindex$"))
        return;
    let $ = jQuery,
        el = ew.getElement(id, formid),
        sv = ew.getElement("sv_" + id, formid), // AutoSuggest
        $input = $(sv || el),
        namespace = tempusDominus.Namespace.NAME,
        dataKey = tempusDominus.Namespace.dataKey;
    if (!el || $input.data(dataKey) || $input.parent().data(dataKey))
        return;
    options = ew.deepAssign({}, ew.dateTimePickerOptions, options);
    let inputGroup = $.isBoolean(options.inputGroup) ? options.inputGroup : true;
    delete(options.inputGroup);
    // options.debug = options.debug || ew.DEBUG;
    let args = {"id": id, "form": formid, "enabled": true, "inputGroup": inputGroup, "options": options};
    $(document).trigger("datetimepicker", [args]);
    if (!args.enabled)
        return;
    if (args.inputGroup !== false) {
        // <div class="input-group date" id="{id}" data-td-target-input="nearest" data-td-target-toggle="nearest">
        // 	<input type="text" class="form-control td-input" data-target="#{id}"/>
        // 	<button class="btn btn-default" type="button" data-target="#{id}" data-td-toggle="datetimepicker"><i class="fa-regular fa-calendar"></i></button>
        // </div>
        let $textbox = $input,
            isInvalid = $input.hasClass("is-invalid"),
            id = "datetimepicker_" + formid + "_" + $input.attr("id");
            $btn = $('<button class="btn btn-default" type="button"><i class="fa-regular fa-calendar"></i></button>')
                .on("click." + dataKey, function() {
                    $textbox.removeClass("is-invalid");
                });
        $input.addClass(namespace + "-input").attr("data-target", "#" + id)
            .wrap(`<div class="input-group${isInvalid ? " is-invalid" : ""}" id="${id}" data-target-input="nearest" data-td-target-toggle="nearest"></div>`)
            .after($btn.attr("data-target", "#" + id).attr(`data-td-toggle`, "datetimepicker"))
            .on("focus." + dataKey, function() {
                $textbox.tooltip("hide").tooltip("disable");
            }).on("blur." + dataKey, function() {
                $textbox.tooltip("enable");
            });
        $input = $input.parent().on("change." + dataKey, function(e) {
            if (e.date)
                $textbox.trigger("change");
        });
    } else {
        // <input type="text" class="form-control td-input" id="{id}" data-td-toggle="datetimepicker" data-target="#{id}"/>
        $input.addClass(dataKey + "-input").attr(`data-td-toggle`, "datetimepicker").attr("data-target", "#" + $input.attr("id"))
            .on("change." + dataKey, function(e) {
                if (e.date && $input[0] !== el)
                    $(el).val($input.val()).trigger("change");
            }).on("focus." + dataKey, function() {
                $input.tooltip("hide").tooltip("disable");
            }).on("blur." + dataKey, function() {
                $input.tooltip("enable");
            });
    }
    $input.tempusDominus(args.options);
    const td = $input.data(dataKey);
    document.addEventListener("changetheme", e => td.updateOptions({ display: { theme: e.detail } }));
    return td;
}
