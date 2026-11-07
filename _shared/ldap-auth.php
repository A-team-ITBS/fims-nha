<?php
/**
 * ============================================================
 *  Active Directory / LDAP authentication helper
 * ============================================================
 * Single source of truth for validating an NHA username +
 * password pair against the corporate AD server. Isolated here
 * so it can be reused by the UAC login flow, by API endpoints,
 * or by future systems (BCS, PIMS bridges, etc.).
 *
 * Reads all config from .env via _shared/load-env.php:
 *   LDAP_ENABLED           on/off master switch
 *   LDAP_SERVER            ldap:// or ldaps:// URL
 *   LDAP_PORT              389 for ldap, 636 for ldaps
 *   LDAP_DOMAIN            appended to bare usernames as UPN
 *   LDAP_TIMEOUT           seconds — prevents hung logins
 *   LDAP_TLS_INSECURE      "true" only for dev with self-signed
 *   LDAP_LOCAL_ACCOUNTS    csv of usernames that skip LDAP
 *
 * Return values from ldapAuthenticate():
 *   'ok'         → bind succeeded, allow login
 *   'bad_creds'  → server responded, credentials rejected
 *   'unreachable'→ could not connect; caller may fall back
 *   'disabled'   → LDAP_ENABLED=false; caller must use local
 *   'local_only' → account is on the local-only allowlist
 *   'no_ext'     → PHP ldap extension missing
 * ============================================================
 */

require_once __DIR__ . '/load-env.php';

/**
 * Returns true if the ldap PHP extension is loaded.
 */
function ldapExtensionAvailable(): bool
{
    return function_exists('ldap_connect') && function_exists('ldap_bind');
}

/**
 * Returns true when a given username is in LDAP_LOCAL_ACCOUNTS.
 * Match is case-insensitive and ignores surrounding whitespace.
 */
function isLdapLocalAccount(string $username): bool
{
    $csv = (string) env('LDAP_LOCAL_ACCOUNTS', '');
    if ($csv === '') {
        return false;
    }
    $needle = strtolower(trim($username));
    foreach (explode(',', $csv) as $entry) {
        if ($needle !== '' && $needle === strtolower(trim($entry))) {
            return true;
        }
    }
    return false;
}

/**
 * Normalise the supplied username into an AD-friendly UPN.
 * If the user typed a full UPN ("juan@nha.gov.ph") we keep it;
 * otherwise we append LDAP_DOMAIN.
 */
function buildLdapUpn(string $username): string
{
    $u = trim($username);
    if ($u === '') {
        return $u;
    }
    if (strpos($u, '@') !== false) {
        return $u; // caller supplied a UPN
    }
    $domain = trim((string) env('LDAP_DOMAIN', ''));
    return $domain === '' ? $u : ($u . '@' . $domain);
}

/**
 * Authenticate a username + password pair against AD.
 * Never throws — always returns one of the documented status codes.
 *
 * @param string $username  Plain username or UPN
 * @param string $password  Cleartext password (used only for bind)
 * @return string           Status code (see file header)
 */
function ldapAuthenticate(string $username, string $password): string
{
    // ── Preflight ────────────────────────────────────────────
    // env() coerces "true"/"false" in .env into real booleans, so compare
    // against boolean true rather than a stringified value.
    if (env('LDAP_ENABLED', false) !== true) {
        return 'disabled';
    }
    if (!ldapExtensionAvailable()) {
        error_log('[LDAP] php_ldap extension is not loaded — cannot authenticate against AD.');
        return 'no_ext';
    }
    if ($username === '' || $password === '') {
        return 'bad_creds';
    }
    if (isLdapLocalAccount($username)) {
        return 'local_only';
    }

    // ── Connect ──────────────────────────────────────────────
    $server = (string) env('LDAP_SERVER', 'ldap://localhost');
    $port   = (int)    env('LDAP_PORT', 389);
    $timeout = max(1, (int) env('LDAP_TIMEOUT', 5));

    // Suppress warnings on connect — we surface failures via return code.
    $ldap = @ldap_connect($server, $port);
    if (!$ldap) {
        error_log("[LDAP] ldap_connect() to {$server}:{$port} failed to initialise.");
        return 'unreachable';
    }

    @ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    @ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);        // required for AD
    @ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, $timeout);
    @ldap_set_option($ldap, LDAP_OPT_TIMELIMIT, $timeout);

    // Development-only: allow self-signed / mismatched TLS certs.
    if (env('LDAP_TLS_INSECURE', false) === true) {
        @putenv('LDAPTLS_REQCERT=never');
    }

    // ── Bind ─────────────────────────────────────────────────
    $upn = buildLdapUpn($username);

    // Silence PHP warnings on invalid credentials — we inspect ldap_errno instead.
    $bound = @ldap_bind($ldap, $upn, $password);
    $errNo = ldap_errno($ldap);

    // Close the handle whichever branch we take.
    @ldap_unbind($ldap);

    if ($bound) {
        return 'ok';
    }

    // AD returns 49 (LDAP_INVALID_CREDENTIALS) on wrong username/password.
    // Anything else (network, TLS, server error) is treated as unreachable
    // so the caller can decide whether to allow local fallback.
    if ($errNo === 49) {
        return 'bad_creds';
    }
    error_log("[LDAP] bind() failed for {$upn}: errno={$errNo} " . ldap_err2str($errNo));
    return 'unreachable';
}
