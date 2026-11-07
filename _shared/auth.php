<?php
// _shared/auth.php - Authentication helper functions

/**
 * Get system configuration array safely
 * @return array
 */

function getSystemConfig() {
    global $SYSTEM;
    
    // If global is already set, use it
    if (isset($SYSTEM) && is_array($SYSTEM)) {
        return $SYSTEM;
    }
    
    // Otherwise, load it from file
    $systemFile = __DIR__ . "/system.php";
    if (file_exists($systemFile)) {
        require($systemFile);
        return $SYSTEM;
    }
    
    return [];
}

/**
 * Check if user is logged in to any subsystem
 * @return bool
 */
function isAnyUserLoggedIn() {
    $SYSTEM = getSystemConfig();
    $subsystems = array_keys($SYSTEM);
    
    foreach ($subsystems as $subsystem) {
        $userNameKey = "{$subsystem}_Status_UserName";
        $userIdKey = "{$subsystem}_Status_UserID";
        
        if (!empty($_SESSION[$userNameKey]) && !empty($_SESSION[$userIdKey])) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get the currently logged in user's username from any subsystem
 * @return string|null
 */
function getLoggedInUsername() {
    $SYSTEM = getSystemConfig();
    $subsystems = array_keys($SYSTEM);
    
    foreach ($subsystems as $subsystem) {
        $userNameKey = "{$subsystem}_Status_UserName";
        $userIdKey = "{$subsystem}_Status_UserID";
        
        if (!empty($_SESSION[$userNameKey]) && !empty($_SESSION[$userIdKey])) {
            return $_SESSION[$userNameKey];
        }
    }
    
    return null;
}

/**
 * Get the system where user is currently logged in
 * @return string|null
 */
function getLoggedInSystem() {
    $SYSTEM = getSystemConfig();
    $subsystems = array_keys($SYSTEM);
    
    foreach ($subsystems as $subsystem) {
        $userNameKey = "{$subsystem}_Status_UserName";
        $userIdKey = "{$subsystem}_Status_UserID";
        
        if (!empty($_SESSION[$userNameKey]) && !empty($_SESSION[$userIdKey])) {
            return $subsystem;
        }
    }
    
    return null;
}

/**
 * Detect whether the given username belongs to a LIBIS-only account (i.e. a
 * service account created for the LIBIS integration surface). Such accounts
 * MUST NOT be allowed to sign in through the FIMS UI login page — they are
 * intended only for programmatic use via `POST /LIBIS/api/login`, which has
 * its own direct DB path and bypasses this check.
 *
 * Matching rule: an account is considered LIBIS-only when its
 * `uac.users.profile` JSON contains:
 *   - "system" = "LIBIS"
 *
 * Populated by `_assets/sql/05 - schema_sample_data_structure/01 - UAC/
 * 05 -sample_data/05 - libis_integration_service_account.sql`.
 *
 * Fail-open behaviour: on any DB error, malformed JSON, or missing profile,
 * this returns `false` (i.e. allows the login attempt to continue to the
 * regular password check). A DB blip must NEVER lock human users out of FIMS.
 *
 * @param string $username Username as submitted on the login form.
 * @return bool            True when the account is LIBIS-only and MUST be rejected.
 */
function isLibisOnlyAccount(string $username): bool
{
    $username = trim($username);
    if ($username === '') {
        return false;
    }

    // Reuse the centralised .env loader + env() helper.
    require_once __DIR__ . '/load-env.php';

    try {
        $pdo = new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                (string)env('DB_HOST', 'localhost'),
                (string)env('DB_PORT', '5432'),
                (string)env('DB_NAME', 'govflow360_nha')
            ),
            (string)env('DB_USER', 'postgres'),
            (string)env('DB_PASSWORD', ''),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (Throwable $e) {
        error_log('[isLibisOnlyAccount] DB connect failed: ' . $e->getMessage());
        return false; // fail open
    }

    try {
        $stmt = $pdo->prepare('SELECT profile FROM uac.users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('[isLibisOnlyAccount] user lookup failed: ' . $e->getMessage());
        return false; // fail open
    }

    if (!$row || empty($row['profile'])) {
        return false;
    }

    $profile = json_decode((string)$row['profile'], true);
    if (!is_array($profile)) {
        return false; // malformed JSON — treat as non-LIBIS
    }

    $system = strtoupper((string)($profile['system'] ?? ''));

    return $system === 'LIBIS';
}

/**
 * Get greeting based on time of day
 * @return string
 */
function getTimeBasedGreeting($username = 'User') {
    date_default_timezone_set('Asia/Manila');

    $hour = date('G');
    if ($hour >= 5 && $hour < 12) {
        return "Good morning, $username!";
    } elseif ($hour >= 12 && $hour < 17) {
        return "Good afternoon, $username!";
    } elseif ($hour >= 17 && $hour < 21) {
        return "Good evening, $username!";
    } else {
        return "Good night, $username!";
    }
}

