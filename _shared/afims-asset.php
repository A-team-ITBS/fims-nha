<?php
/**
 * AFIMS stylesheet versioning.
 *
 * Redirects to a stylesheet with its file time as ?v=, so browsers download it
 * again only after the file changes (the web server caches .css for a year).
 * Used by _shared/js/custom.js for custom.css and each module's afims-ui.css.
 *
 *   /_shared/afims-asset.php?f=_shared/css/custom.css
 *   /_shared/afims-asset.php?f=TREASURY/app/css/afims-ui.css
 */
$file = $_GET['f'] ?? '';
$path = dirname(__DIR__) . '/' . $file;

if (!preg_match('#^(_shared/css/custom\.css|[A-Z][A-Z-]*/app/css/afims-ui\.css)$#', $file) || !is_file($path)) {
    http_response_code(404);
    exit;
}

// no-store (not just no-cache) so the browser never reuses a cached copy of this
// 302 redirect — otherwise it keeps following the old ?v= and serves the stale,
// immutable-cached stylesheet even after the file changes. The redirect is tiny;
// re-requesting it each load is cheap, and the target CSS still caches per ?v=.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: /' . $file . '?v=' . filemtime($path), true, 302);
