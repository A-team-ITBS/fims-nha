<?php

namespace PHPMaker2024\FIMS;

// Base path
$basePath = BasePath(true);
?>
<!DOCTYPE html>
<html<?= IsRTL() ? ' lang="' . CurrentLanguageID() . '" dir="rtl"' : '' ?> data-bs-theme="light">
<head>
<title><?= CurrentPageTitle() ?></title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= $basePath ?>css/select2.min.css?v=24.16.8">
<link rel="stylesheet" href="<?= $basePath ?>css/select2-bootstrap5.min.css?v=24.16.8">
<link rel="stylesheet" href="<?= $basePath ?><?= Config("FONT_AWESOME_STYLESHEET") ?>?v=24.16.8">
<link rel="stylesheet" href="<?= $basePath ?>adminlte3/css/<?= CssFile("adminlte.css") ?>?v=24.16.8">
<link rel="stylesheet" href="<?= $basePath ?><?= CssFile(Config("PROJECT_STYLESHEET_FILENAME")) ?>?v=24.16.8">
<script data-pace-options='<?= JsonEncode(Config("PACE_OPTIONS")) ?>' src="<?= $basePath ?>js/pace.js?v=24.16.8"></script><!-- Single quotes for data-pace-options -->
<script src="<?= $basePath ?>js/ewcore.min.js?v=24.16.8"></script>
<script>
var $rowindex$ = null;
Object.assign(ew, <?= JsonEncode(ConfigClientVars()) ?>, <?= JsonEncode(GlobalClientVars()) ?>);
loadjs(ew.PATH_BASE + "jquery/jquery.min.js?v=24.16.8", "jquery");
loadjs(ew.PATH_BASE + "js/popper.min.js?v=24.16.8", "popper");
loadjs(ew.PATH_BASE + "js/luxon.min.js?v=24.16.8", "luxon");
loadjs([
    ew.PATH_BASE + "js/mobile-detect.min.js?v=24.16.8",
    ew.PATH_BASE + "js/purify.min.js?v=24.16.8",
    ew.PATH_BASE + "js/cropper.min.js?v=24.16.8",
    ew.PATH_BASE + "jquery/load-image.all.min.js?v=24.16.8"
], "others");
loadjs(ew.PATH_BASE + "js/sweetalert2.all.min.js?v=24.16.8", "swal");
<?= $Language->toJson() ?>
ew.vars = <?= JsonEncode(GetClientVar()) ?>;
ew.ready(["wrapper", "jquery"], ew.PATH_BASE + "jquery/jsrender.min.js?v=24.16.8", "jsrender", ew.renderJsTemplates);
ew.ready("jquery", ew.PATH_BASE + "jquery/jquery-ui.min.js?v=24.16.8", "widget");
</script>
<?php include_once "views/menu.php"; ?>
<script>
var cssfiles = [
    ew.PATH_BASE + "css/jquery.fileupload.css?v=24.16.8",
    ew.PATH_BASE + "css/jquery.fileupload-ui.css?v=24.16.8",
    ew.PATH_BASE + "css/cropper.min.css?v=24.16.8"
];
cssfiles.push(ew.PATH_BASE + "colorbox/colorbox.css?v=24.16.8");
loadjs(cssfiles, "css");
var cssjs = [];
<?php foreach (array_merge(Config("STYLESHEET_FILES"), Config("JAVASCRIPT_FILES")) as $file) { // External Stylesheets and JavaScripts ?>
cssjs.push("<?= (IsRemote($file) ? "" : BasePath(true)) . $file ?>?v=24.16.8");
<?php } ?>
var jqueryjs = [
    ew.PATH_BASE + "jquery/select2.full.min.js?v=24.16.8",
    ew.PATH_BASE + "jquery/jqueryfileupload.min.js?v=24.16.8",
    ew.PATH_BASE + "jquery/typeahead.jquery.min.js?v=24.16.8"
];
jqueryjs.push(ew.PATH_BASE + "colorbox/jquery.colorbox.min.js?v=24.16.8");
jqueryjs.push(ew.PATH_BASE + "js/pdfobject.min.js?v=24.16.8");
ew.ready(["jquery", "dom", "popper"], ew.PATH_BASE + "bootstrap5/js/bootstrap.min.js?v=24.16.8", "bootstrap"); // Bootstrap
ew.ready("popper", ew.PATH_BASE + "js/tippy-bundle.umd.min.js?v=24.16.8", "tippy"); // Tippy
ew.ready("bootstrap", ew.PATH_BASE + "adminlte3/js/adminlte.min.js?v=24.16.8", "adminlte"); // AdminLTE (After Bootstrap)
ew.ready(["jquery", "widget"], [jqueryjs], "jqueryjs");
ew.ready(["bootstrap", "adminlte", "jqueryjs", "luxon", "others"], ew.PATH_BASE + "js/ew.min.js?v=24.16.8", "makerjs");
ew.ready("makerjs", [
    cssjs,
    ew.PATH_BASE + "js/userfn.js?v=24.16.8",
    ew.PATH_BASE + "js/userevent.js?v=24.16.8"
], "head");
</script>
<script>
loadjs(ew.PATH_BASE + "css/<?= CssFile("tempus-dominus.css") ?>?v=24.16.0");
ew.ready("head", [
    ew.PATH_BASE + "js/tempus-dominus.min.js?v=24.16.0",
    ew.PATH_BASE + "js/ewdatetimepicker.min.js?v=24.16.0"
], "datetimepicker");
</script>
<!-- Navbar -->
<script type="text/html" id="navbar-menu-items" class="ew-js-template" data-name="navbar" data-seq="10" data-data="navbar" data-method="appendTo" data-target="#ew-navbar">
{{if items}}
    {{for items}}
        <li id="{{:id}}" data-name="{{:name}}" class="{{if parentId == -1}}nav-item ew-navbar-item{{/if}}{{if isHeader && parentId > -1}}dropdown-header{{/if}}{{if items && parentId == -1}} dropdown{{/if}}{{if items && parentId != -1}} dropdown-submenu{{/if}}{{if items && level == 1}} dropdown-hover{{/if}} d-none d-sm-block">
            {{if isHeader && parentId > -1}}
                {{if icon}}<i class="{{:icon}}"></i>{{/if}}
                <span>{{:text}}</span>
            {{else}}
            <a href="{{:href}}"{{if target}} target="{{:target}}"{{/if}}{{if items}} role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"{{/if}}{{if attrs}}{{:attrs}}{{/if}}>
                {{if icon}}<i class="{{:icon}}"></i>{{/if}}
                <span>{{:text}}</span>
            </a>
            {{/if}}
            {{if items}}
            <ul class="dropdown-menu">
                {{include tmpl="#navbar-menu-items"/}}
            </ul>
            {{/if}}
        </li>
    {{/for}}
{{/if}}
</script>
<!-- Sidebar -->
<script type="text/html" class="ew-js-template" data-name="menu" data-seq="10" data-data="menu" data-target="#ew-menu">
{{if items}}
    <ul class="nav nav-pills nav-sidebar nav-child-indent flex-column{{if compact}} nav-compact{{/if}}" data-widget="treeview" role="menu" data-accordion="{{:accordion}}">
    {{include tmpl="#menu-items"/}}
    </ul>
{{/if}}
</script>
<script type="text/html" id="menu-items">
{{if items}}
    {{for items}}
        <li id="{{:id}}" data-name="{{:name}}" class="{{if isHeader}}nav-header{{else}}nav-item{{if items}} has-treeview{{/if}}{{if active}} active current{{/if}}{{if open}} menu-open{{/if}}{{/if}}{{if isNavbarItem}} d-block d-sm-none{{/if}}">
            {{if isHeader}}
                {{if icon}}<i class="{{:icon}}"></i>{{/if}}
                <span>{{:text}}</span>
                {{if label}}
                <span class="right">
                    {{:label}}
                </span>
                {{/if}}
            {{else}}
            <a href="{{:href}}"{{if target}} target="{{:target}}"{{/if}}{{if attrs}}{{:attrs}}{{/if}}>
                {{if icon}}<i class="nav-icon {{:icon}}"></i>{{/if}}
                <p>{{:text}}
                    {{if items}}
                        <i class="right fa-solid fa-angle-left"></i>
                        {{if label}}
                            <span class="right">
                                {{:label}}
                            </span>
                        {{/if}}
                    {{else}}
                        {{if label}}
                            <span class="right">
                                {{:label}}
                            </span>
                        {{/if}}
                    {{/if}}
                </p>
            </a>
            {{/if}}
            {{if items}}
            <ul class="nav nav-treeview"{{if open}} style="display: block;"{{/if}}>
                {{include tmpl="#menu-items"/}}
            </ul>
            {{/if}}
        </li>
    {{/for}}
{{/if}}
</script>
<script type="text/html" class="ew-js-template" data-name="languages" data-seq="10" data-data="languages" data-method="<?= $Language->Method ?>" data-target="<?= HtmlEncode($Language->Target) ?>">
<?= $Language->getTemplate() ?>
</script>
<script type="text/html" class="ew-js-template" data-name="colormodes" data-seq="10" data-method="appendTo" data-target="#ew-navbar-end">
<li class="nav-item dropdown">
    <button class="btn btn-link nav-link py-2 px-0 px-lg-2 dropdown-toggle d-flex align-items-center" id="bd-theme" type="button" aria-expanded="true" data-bs-toggle="dropdown" data-bs-display="static" aria-label="<?= HtmlEncode($Language->phrase("ToggleTheme")) ?> (<?= HtmlEncode($Language->phrase("ThemeLight")) ?>)">
        <i class="<?= $Language->phraseClass("ThemeLight") ?> my-1 theme-icon-active"></i>
        <span class="d-none ms-2" id="bd-theme-text"><?= $Language->phrase("ToggleTheme") ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="bd-theme-text" data-bs-popper="static">
        <li>
            <button type="button" class="dropdown-item d-flex align-items-center active" data-bs-theme-value="light" aria-pressed="true">
                <i class="<?= $Language->phraseClass("ThemeLight") ?> me-2 opacity-50 theme-icon"></i>
                <span class="fs-6"><?= $Language->phrase("ThemeLight", true) ?></span>
            </button>
        </li>
        <li>
            <button type="button" class="dropdown-item d-flex align-items-center" data-bs-theme-value="dark" aria-pressed="false">
                <i class="<?= $Language->phraseClass("ThemeDark") ?> me-2 opacity-50 theme-icon"></i>
                <span class="fs-6"><?= $Language->phrase("ThemeDark", true) ?></span>
            </button>
        </li>
    </ul>
</li>
</script>
<script type="text/html" class="ew-js-template" data-name="login" data-seq="10" data-data="login" data-method="appendTo" data-target="#ew-navbar-end">
{{if canSubscribe}}
<li class="nav-item"><a id="subscribe-notification" class="nav-link disabled">{{:subscribeText}}</a></li>
{{/if}}
{{if isLoggedIn}}
<li class="nav-item dropdown text-body">
    <a id="ew-nav-link-user" class="nav-link ew-user" data-bs-toggle="dropdown" href="#">
        <i class="fa-solid fa-user"></i>
    </a>
    <div class="dropdown-menu dropdown-menu-end ew-user-dropdown" aria-labelledby="ew-nav-link-user">
        <div class="dropdown-header">
            <i class="fa-solid fa-user me-2"></i>{{:currentUserName}}
        </div>
        <div class="dropdown-divider"></div>
        {{if hasPersonalData}}
        <a class="dropdown-item" id="personal-data"{{props personalData}} data-{{:key}}="{{>prop}}"{{/props}}>{{:personalDataText}}</a>
        {{/if}}
        {{if canChangePassword}}
        <a class="dropdown-item" id="change-password"{{props changePassword}} data-{{:key}}="{{>prop}}"{{/props}}>{{:changePasswordText}}</a>
        {{/if}}
        {{if enable2FAText}}
        <a class="dropdown-item{{if !enable2FA}} d-none{{/if}}" id="enable-2fa" data-ew-action="enable-2fa">{{:enable2FAText}}</a>
        {{/if}}
        {{if backupCodes}}
        <a class="dropdown-item{{if !showBackupCodes}} d-none{{/if}}" id="backup-codes" data-ew-action="backup-codes">{{:backupCodes}}</a>
        {{/if}}
        {{if disable2FAText}}
        <a class="dropdown-item{{if !disable2FA}} d-none{{/if}}" id="disable-2fa" data-ew-action="disable-2fa">{{:disable2FAText}}</a>
        {{/if}}
        {{if canChat}}
        <a class="dropdown-item{{if !enableChat}} d-none{{/if}}" id="enable-chat" data-ew-action="toggle-chat" data-value="1">{{:enableChatText}}</a>
        <a class="dropdown-item{{if !disableChat}} d-none{{/if}}" id="disable-chat" data-ew-action="toggle-chat" data-value="0">{{:disableChatText}}</a>
        {{/if}}
        {{if canLogout}}
        <div class="dropdown-divider"></div>
        <div class="dropdown-footer text-end py-0">
            <a class="btn btn-default"{{props logout}} data-{{:key}}="{{>prop}}"{{/props}}>{{:logoutText}}</a>
        </div>
        {{/if}}
    </div>
</li>
{{else}}
    {{if canLogin}}
<li class="nav-item"><a class="nav-link ew-tooltip" title="{{:loginTitle}}"{{props login}} data-{{:key}}="{{>prop}}"{{/props}}>{{:loginText}}</a></li>
    {{/if}}
    {{if canLogout}}
<li class="nav-item"><a class="nav-link ew-tooltip"{{props logout}} data-{{:key}}="{{>prop}}"{{/props}}>{{:logoutText}}</a></li>
    {{/if}}
{{/if}}
</script>
<link rel="shortcut icon" type="image/png" href="<?= BasePath() ?>/icon-uac-lbl.png">
<link rel="icon" type="image/png" href="<?= BasePath() ?>/icon-uac-lbl.png">
<meta name="generator" content="PHPMaker 2024.16.0">
</head>
<body class="<?= Config("BODY_CLASS") ?>" style="<?= Config("BODY_STYLE") ?>">
<?php if (@!$SkipHeaderFooter) { ?>
<div class="wrapper ew-layout">
    <!-- Main Header -->
    <!-- Navbar -->
    <nav class="<?= Config("NAVBAR_CLASS") ?>">
        <div class="container-fluid">
            <!-- Left navbar links -->
            <ul id="ew-navbar" class="navbar-nav">
                <li class="nav-item d-block">
                    <a class="nav-link" data-widget="pushmenu" data-enable-remember="true" data-ew-action="none"><i class="fa-solid fa-bars ew-icon"></i></a>
                </li>
                <a class="navbar-brand d-none" href="#" data-ew-action="none">
                    <img src="<?= GetUrl("images/icon-uac.png") ?>" alt="" class="brand-image ew-brand-image">
                </a>
            </ul>
            <!-- Right navbar links -->
            <ul id="ew-navbar-end" class="navbar-nav ms-auto"></ul>
        </div>
    </nav>
    <!-- /.navbar -->
    <!-- Main Sidebar Container -->
    <aside class="<?= Config("SIDEBAR_CLASS") ?>">
        <div class="brand-container">
            <!-- Brand Logo //** Note: Only licensed users are allowed to change the logo ** -->
            <a href="#" class="brand-link">
                <img src="<?= GetUrl("images/icon-uac.png") ?>" alt="" class="brand-image ew-brand-image">
            </a>
            <?php if (preg_match('/\bsidebar-mini\b/', Config("BODY_CLASS"))) { ?>
            <a class="pushmenu mx-1" data-pushmenu="mini" role="button"><i class="fa-solid fa-angle-double-left"></i></a>
            <?php } ?>
        </div>
        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel -->
            <?php if (IsLoggedIn()) { ?>
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <?php if (CurrentUserImageBase64()) { ?>
                <div class="image">
                    <img src="data:image/png;base64,<?= CurrentUserImageBase64() ?>" class="img-circle ew-user-image" alt="">
                </div>
                <?php } ?>
                <?php if (GetClientVar("login", "currentUserName")) { ?>
                <div class="info">
                    <a class="d-block"><?= GetClientVar("login", "currentUserName") ?></a>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
            <!-- Sidebar Menu -->
            <nav id="ew-menu" class="mt-2"></nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
    <?php if (Config("PAGE_TITLE_STYLE") != "None") { ?>
            <div class="container-fluid">
                <div class="row">
                <div class="col-sm-6">
                    <h1 class="m-0 text-dark"><?= CurrentPageHeading() ?> <small class="text-body-secondary"><?= CurrentPageSubheading() ?></small></h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                    <?= Breadcrumb()?->render() ?>
                </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->
    <?php } ?>
        </div>
        <!-- /.content-header -->
        <!-- Main content -->
        <section class="content">
        <div class="container-fluid">
<?php } ?>
<?= $content ?>
<?php if (@!$SkipHeaderFooter) { ?>
<?php
if (isset($DebugTimer)) {
    $DebugTimer->stop();
}
?>
        </div><!-- /.container-fluid -->
        </section>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
    <!-- Main Footer -->
    <footer class="main-footer">
        <!-- ** Note: Only licensed users are allowed to change the copyright statement. ** -->
        <div class="ew-footer-text"><?= $Language->projectPhrase("FooterText") ?></div>
        <div class="float-end d-none d-sm-inline"></div>
    </footer>
    <!-- Page Foot -->
    <?php 
    include(dirname(__DIR__, 2) . "/_shared/foot.php");
    ?>
</div>
<!-- ./wrapper -->
<?php } ?>
<script>
loadjs.done("wrapper");
</script>
<!-- template upload (for file upload) -->
<script id="template-upload" type="text/html">
{{for files}}
    <tr class="template-upload">
        <td>
            <span class="preview"></span>
        </td>
        <td>
            <p class="name">{{:name}}</p>
            <p class="error"></p>
        </td>
        <td>
            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar text-bg-success" style="width: 0%;"></div></div>
        </td>
        <td>
            {{if !#index && !~root.options.autoUpload}}
            <button type="button" class="btn btn-default btn-sm start" disabled><?= $Language->phrase("UploadStart") ?></button>
            {{/if}}
            {{if !#index}}
            <button type="button" class="btn btn-default btn-sm cancel"><?= $Language->phrase("UploadCancel") ?></button>
            {{/if}}
        </td>
    </tr>
{{/for}}
</script>
<!-- template download (for file upload) -->
<script id="template-download" type="text/html">
{{for files}}
    <tr class="template-download">
        <td>
            <span class="preview">
                {{if !exists}}
                <span class="error"><?= $Language->phrase("FileNotFound") ?></span>
                {{else url && extension == "pdf"}}
                <div class="ew-pdfobject" data-url="{{>url}}" style="width: <?= Config("UPLOAD_THUMBNAIL_WIDTH") ?>px;"></div>
                {{else url && extension == "mp3"}}
                <audio controls><source type="audio/mpeg" src="{{>url}}"></audio>
                {{else url && extension == "mp4"}}
                <video controls><source type="video/mp4" src="{{>url}}"></video>
                {{else thumbnailUrl}}
                <a href="{{>url}}" title="{{>name}}" download="{{>name}}" class="ew-lightbox"><img class="ew-lazy" loading="lazy" src="{{>thumbnailUrl}}"></a>
                {{/if}}
            </span>
        </td>
        <td>
            <p class="name">
                {{if !exists}}
                <span class="text-body-secondary">{{:name}}</span>
                {{else url && (extension == "pdf" || thumbnailUrl) && extension != "mp3" && extension != "mp4"}}
                <a href="{{>url}}" title="{{>name}}" data-extension="{{>extension}}" target="_blank">{{:name}}</a>
                {{else url}}
                <a href="{{>url}}" title="{{>name}}" data-extension="{{>extension}}" download="{{>name}}">{{:name}}</a>
                {{else}}
                <span>{{:name}}</span>
                {{/if}}
            </p>
            {{if error}}
            <div><span class="error">{{:error}}</span></div>
            {{/if}}
        </td>
        <td>
            <span class="size">{{:~root.formatFileSize(size)}}</span>
        </td>
        <td>
            {{if !~root.options.readonly && deleteUrl}}
            <button type="button" class="btn btn-default btn-sm delete" data-type="{{>deleteType}}" data-url="{{>deleteUrl}}"><?= $Language->phrase("UploadDelete") ?></button>
            {{else !~root.options.readonly}}
            <button type="button" class="btn btn-default btn-sm cancel"><?= $Language->phrase("UploadCancel") ?></button>
            {{/if}}
        </td>
    </tr>
{{/for}}
</script>
<!-- modal dialog -->
<div id="ew-modal-dialog" class="modal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="ew-modal-dialog-title" aria-hidden="true"><div class="modal-dialog modal-fullscreen-sm-down"><div class="modal-content"><div class="modal-header"><h5 id="ew-modal-dialog-title" class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $Language->phrase("CloseBtn") ?>"></button></div><div class="modal-body"></div><div class="modal-footer"></div></div></div></div>
<!-- modal lookup dialog -->
<div id="ew-modal-lookup-dialog" class="modal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="ew-modal-lookup-dialog-title" aria-hidden="true"><div class="modal-dialog modal-fullscreen-sm-down"><div class="modal-content"><div class="modal-header"><h5 id="ew-modal-lookup-dialog-title" class="modal-title"></h5></div><div class="modal-body p-0"></div><div class="modal-footer"><button type="button" class="btn btn-primary ew-btn" data-value="true" data-bs-dismiss="modal"><?= $Language->phrase("OKBtn") ?></button><button type="button" class="btn btn-default ew-btn" data-value="false" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button></div></div></div></div>
<!-- image cropper dialog -->
<div id="ew-cropper-dialog" class="modal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="ew-cropper-dialog-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="ew-cropper-dialog-title" class="modal-title"><?= $Language->phrase("Crop") ?></h5>
            </div>
            <div class="modal-body">
                <div id="ew-crop-image-container"><img id="ew-crop-image" src="data:image/png;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary ew-crop-btn"><?= $Language->phrase("Crop") ?></button>
                <button type="button" class="btn btn-default ew-skip-btn" data-bs-dismiss="modal"><?= $Language->phrase("Skip") ?></button>
            </div>
        </div>
    </div>
</div>
<!-- tooltip -->
<div id="ew-tooltip"></div>
<!-- drill down -->
<div id="ew-drilldown-panel"></div>
<script>
loadjs.done("wrapper");
loadjs.ready(ew.bundleIds, () => loadjs.isDefined("foot") || loadjs.done("foot"));
</script>
</body>
</html>
