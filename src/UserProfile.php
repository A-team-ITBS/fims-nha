<?php

namespace PHPMaker2024\FIMS;

/**
 * User Profile Class
 */
class UserProfile implements \Stringable
{
    public static $CONCURRENT_SESSION_COUNT = -1; // Maximum sessions allowed
    public static $FORCE_LOGOUT_USER = true; // Force logout user
    public static $FORCE_LOGOUT_CONCURRENT_USER = false; // Force logout concurrent user
    public static $SESSION_CLEANUP_TIME = 60 * 24; // Clean up unused sessions if idle more than 1 day
    public static $SESSION_TIMEOUT = -1;
    public static $MAX_RETRY = 3;
    public static $RETRY_LOCKOUT = 20;
    public static $PASSWORD_EXPIRE = 90;
    public static $CONCURRENT_SESSIONS = "Sessions";
    public static $SESSION_ID = "SessionID";
    public static $LAST_ACCESSED_DATE_TIME = "LastAccessedDateTime";
    public static $FORCE_LOGOUT = "ForceLogout";
    public static $LOGIN_RETRY_COUNT = "LoginRetryCount";
    public static $LAST_BAD_LOGIN_DATE_TIME = "LastBadLoginDateTime";
    public static $LAST_PASSWORD_CHANGED_DATE = "LastPasswordChangedDate";
    public static $LANGUAGE_ID = "LanguageId";
    public static $SEARCH_FILTERS = "SearchFilters";
    public static $IMAGE = "UserImage";
    public static $SECRET = "Secret";
    public static $SECRET_CREATE_DATE_TIME = "SecretCreateDateTime";
    public static $SECRET_VERIFY_DATE_TIME = "SecretVerifyDateTime";
    public static $SECRET_LAST_VERIFY_CODE = "SecretLastVerifyCode";
    public static $BACKUP_CODES = "BackupCodes";
    public static $ONE_TIME_PASSWORD = "OTP";
    public static $OTP_ACCOUNT = "OTPAccount";
    public static $OTP_CREATE_DATE_TIME = "OTPCreateDateTime";
    public static $OTP_VERIFY_DATE_TIME = "OTPVerifyDateTime";
    public static $CHAT_ENABLED = "ChatEnabled";
    private $userName = "";
    private $userId;
    private $userPrimaryKey;
    private $parentUserId;
    private $userLevel = AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID;
    private $profile = [];
    private $user;
    private $provider;
    public $TimeoutTime;
    public $MaxRetryCount;
    public $RetryLockoutTime;
    public $PasswordExpiryTime;

    // Constructor
    public function __construct(string $userName = "")
    {
        $this->TimeoutTime = self::$SESSION_TIMEOUT > 0 ? self::$SESSION_TIMEOUT : Config("SESSION_TIMEOUT");
        $this->MaxRetryCount = self::$MAX_RETRY;
        $this->RetryLockoutTime = self::$RETRY_LOCKOUT;
        $this->PasswordExpiryTime = self::$PASSWORD_EXPIRE;
        $this->setLoginRetryCount(0)->setLastBadLoginDateTime(""); // Max login retry
        $this->setLastPasswordChangedDate(""); // Password Expiry
        if ($userName) {
            $this->setUserName($userName)->loadFromStorage();
        }
    }

    // Create
    public static function create(): static
    {
        return new static();
    }

    // Get user name
    public function getUserName(): string
    {
        return $this->userName;
    }

    // Set user name
    public function setUserName(string $value)
    {
        $this->userName = $value;
        return $this;
    }

    // Get user ID
    public function getUserID()
    {
        return $this->userId;
    }

    // Set user ID
    public function setUserID($value)
    {
        $this->userId = $value;
        return $this;
    }

    // Get user primary key
    public function getUserPrimaryKey()
    {
        return $this->userPrimaryKey;
    }

    // Set user primary key
    public function setUserPrimaryKey($value)
    {
        $this->userPrimaryKey = $value;
        return $this;
    }

    // Get parent user ID
    public function getParentUserID()
    {
        return $this->parentUserId;
    }

    // Set parent user ID
    public function setParentUserID($value)
    {
        $this->parentUserId = $value;
        return $this;
    }

    // Get user level
    public function getUserLevel()
    {
        return $this->userLevel;
    }

    // Set user level
    public function setUserLevel($value)
    {
        $this->userLevel = $value;
        return $this;
    }

    // Get login arguments
    public function getLoginArguments(): array
    {
        return [
            "userName" => $this->getUserName(),
            "userId" => $this->getUserID(),
            "parentUserId" => $this->getParentUserID(),
            "userLevel" => $this->getUserLevel(),
            "userPrimaryKey" => $this->getUserPrimaryKey(),
        ];
    }

    // Get profile
    public function getProfile(): array
    {
        return $this->profile;
    }

    // Set profile
    public function setProfile(array $value)
    {
        $this->profile = $value;
        return $this;
    }

    // Get provider
    public function getProvider(): string
    {
        return $this->provider;
    }

    // Set provider
    public function setProvider(string $value)
    {
        $this->provider = $value;
        return $this;
    }

    // Has value in profile
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->profile);
    }

    // Get value
    public function get(string $name)
    {
        return $this->profile[$name] ?? null;
    }

    // Set value
    public function set(string $name, mixed $value): static
    {
        $this->profile[$name] = $value;
        return $this;
    }

    // Get profile as array
    public function toArray(): array
    {
        return $this->profile;
    }

    // Get profile as object
    public function toObject(): object
    {
        return (object)$this->toArray();
    }

    // Set property to profile // PHP
    public function __set(string $name, mixed $value): void
    {
        if ($value === null) {
            $this->delete($name);
        } else {
            $this->set($name, $value);
        }
    }

    // Get property from profile // PHP
    public function __get(string $name)
    {
        return $this->get($name);
    }

    // Delete property from profile
    public function delete(string $name)
    {
        unset($this->profile[$name]);
        return $this;
    }

    // Assign properties to profile
    public function assign(object|array $input)
    {
        if (is_object($input)) {
            $vars = get_object_vars($input);
            if (is_array($vars["data"])) {
                $data = $vars["data"];
                unset($vars["data"]);
                $vars = array_merge($vars, $data);
            }
            $this->assign($vars);
        } elseif (is_array($input)) {
            $input = array_filter($input, fn ($v, $k) => !is_int($k) && (is_bool($v) || is_float($v) || is_int($v) || $v === null || is_string($v)), ARRAY_FILTER_USE_BOTH);
            foreach ($input as $key => $value) {
                if (preg_match('/http:\/\/schemas\.[.\/\w]+\/claims\/(\w+)/', $key, $m)) { // e.g. http://schemas.microsoft.com/identity/claims/xxx, http://schemas.xmlsoap.org/ws/2005/05/identity/claims/xxx
                    $key = $m[1];
                }
                $this->set($key, $value);
            }
        }
    }

    // Check if System Admin
    public function isSysAdmin()
    {
        return Security()->isSysAdmin();
    }

    // Get language ID
    public function getLanguageId()
    {
        return $this->{self::$LANGUAGE_ID};
    }

    // Set language ID
    public function setLanguageId($value)
    {
        $this->{self::$LANGUAGE_ID} = $value;
        return $this;
    }

    // Get search filters
    public function getFilters()
    {
        return $this->{self::$SEARCH_FILTERS};
    }

    // Set search filters
    public function setFilters($value)
    {
        $this->{self::$SEARCH_FILTERS} = $value;
        return $this;
    }

    // Get search filters for a page
    public function getSearchFilters($pageid)
    {
        try {
            $allfilters = $this->getFilters();
            return $allfilters[$pageid] ?? "";
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return "";
    }

    // Set search filters for a page
    public function setSearchFilters($pageid, $filters)
    {
        try {
            $allfilters = $this->getFilters();
            if (!is_array($allfilters)) {
                $allfilters = [];
            }
            $allfilters[$pageid] = $filters;
            return $this->setFilters($allfilters)->saveToStorage();
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Get user
    public function getUser()
    {
        if (!EmptyValue(Config("USER_PROFILE_FIELD_NAME"))) { // Get user if profile field exists
            $userName = $this->getUserName();
            if (!$this->user && $userName != "" || $this->user->get(Config("LOGIN_USERNAME_FIELD_NAME")) != $userName) {
                $this->user = FindUserByUserName($userName);
            }
        }
        return $this->user;
    }

    // Set user
    public function setUser($user)
    {
        if ($user instanceof AbstractEntity) {
            $this->setUserName($user->get(Config("LOGIN_USERNAME_FIELD_NAME")));
            if (!EmptyValue(Config("USER_PROFILE_FIELD_NAME"))) { // Set user and load profile if profile field exists
                $this->user = $user;
                $this->load($user->get(Config("USER_PROFILE_FIELD_NAME")));
            }
        }
        return $this;
    }

    // Load profile from storage
    public function loadFromStorage()
    {
        if (is_array(Session(SESSION_USER_PROFILE_RECORD))) { // Load from session for register and system admin
            $row = Session(SESSION_USER_PROFILE_RECORD);
            $profile = $row[SYS_ADMIN_USER_PROFILE] ?? null;
            if ($profile !== null) {
                $this->load($profile);
                return true;
            }
        } else { // Load from database
            if ($this->getUserName() == "") { // Empty user name
                return false;
            }
            $user = $this->getUser();
            if ($user) { // Database user
                $this->load($user->get(Config("USER_PROFILE_FIELD_NAME")));
                return true;
            } else { // No database user
                $this->loadFromSession();
                return true;
            }
        }
        return false;
    }

    // Save profile to storage
    public function saveToStorage()
    {
        if (is_array(Session(SESSION_USER_PROFILE_RECORD))) { // Save to session for register and system admin
            $row = Session(SESSION_USER_PROFILE_RECORD);
            $row[SYS_ADMIN_USER_PROFILE] = (string)$this;
            $_SESSION[SESSION_USER_PROFILE_RECORD] = $row; // Save record
            return true;
        } else { // Save to database
            if (EmptyValue($this->getUserName())) { // Empty user name
                return false;
            }
            $user = $this->getUser();
            if ($user) { // Database user
                $user->set(Config("USER_PROFILE_FIELD_NAME"), (string)$this);
                GetUserEntityManager()->flush();
            } else { // No database user
                $this->saveToSession();
            }
            return true;
        }
        return false;
    }

    // Load profile from session
    public function loadFromSession()
    {
        if (isset($_SESSION[SESSION_USER_PROFILE])) {
            $this->load($_SESSION[SESSION_USER_PROFILE]);
        }
        return $this;
    }

    // Save profile to session
    public function saveToSession()
    {
        $_SESSION[SESSION_USER_PROFILE] = (string)$this;
        return $this;
    }

    // Load profile from string
    public function load($profile)
    {
        $profile = trim(strval($profile ?? ""));
        $ar = str_starts_with($profile, "a:") // Array by serialize()
            ? @unserialize($profile)
            : JsonDecode($profile, true);
        if (is_array($ar)) {
            $this->profile = array_merge($this->profile, $ar);
        }
        return $this;
    }

    // Clear profile
    public function clear()
    {
        $this->profile = [];
        return $this;
    }

    // Convert to string
    public function __toString(): string
    {
        return JsonEncode($this->profile);
    }

    // Get concurrent sessions
    public function getConcurrentSessions()
    {
        return $this->{self::$CONCURRENT_SESSIONS};
    }

    // Set concurrent sessions
    public function setConcurrentSessions($value)
    {
        $this->{self::$CONCURRENT_SESSIONS} = $value;
        return $this;
    }

    // Is valid user
    public function isValidUser($sessionId, $addSession = true)
    {
        if ($this->isSysAdmin() || IsApi()) { // Ignore system admin / API
            return true;
        }
        try {
            $sessions = $this->getConcurrentSessions();
            $sessions = is_array($sessions) ? $sessions : [];
            $valid = false;
            $cnt = 0;
            $logoutUser = self::$FORCE_LOGOUT_CONCURRENT_USER && self::$CONCURRENT_SESSION_COUNT == 1;
            foreach ($sessions as &$session) {
                $sessId = $session[self::$SESSION_ID];
                $dt = $session[self::$LAST_ACCESSED_DATE_TIME];
                $forceLogout = ConvertToBool($session[self::$FORCE_LOGOUT]);
                if (SameString($sessId, $sessionId)) {
                    $valid = true;
                    if (!$forceLogout && ($this->TimeoutTime < 0 || DateDiff($dt, StdCurrentDateTime(), "n") > $this->TimeoutTime)) { // Update accessed time
                        $session[self::$LAST_ACCESSED_DATE_TIME] = StdCurrentDateTime();
                    }
                    break;
                } elseif ($logoutUser) { // Logout concurrent user
                    $session[self::$FORCE_LOGOUT] = true;
                } else {
                    $cnt++;
                }
            }
            if (!$valid && $addSession && (self::$CONCURRENT_SESSION_COUNT < 0 || $cnt < self::$CONCURRENT_SESSION_COUNT || $logoutUser)) {
                $valid = true;
                $sessions[] = [
                    self::$SESSION_ID => $sessionId,
                    self::$LAST_ACCESSED_DATE_TIME => StdCurrentDateTime(),
                    self::$FORCE_LOGOUT => false,
                ];
            }
            // Remove unused sessions
            $sessions = $this->removeUnusedSessions($sessions);
            if ($valid) {
                $this->setConcurrentSessions($sessions)->saveToStorage();
            }
            return $valid;
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Remove unused sessions
    protected function removeUnusedSessions($sessions)
    {
        $cleanupTime = $this->TimeoutTime > 0 ? $this->TimeoutTime : self::$SESSION_CLEANUP_TIME; // Fallback to cleanup time if timeout not specified
        return array_filter($sessions, fn($session) => DateDiff($session[self::$LAST_ACCESSED_DATE_TIME], StdCurrentDateTime(), "n") <= $cleanupTime);
    }

    // Remove user
    public function removeUser($sessionId)
    {
        if ($this->isSysAdmin()) { // Ignore system admin
            return true;
        }
        try {
            $sessions = $this->getConcurrentSessions();
            $sessions = is_array($sessions) ? $sessions : [];
            $sessions = array_filter($sessions, fn($session) => $session[self::$SESSION_ID] != $sessionId);
            return $this->setConcurrentSessions($sessions)->saveToStorage();
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Reset concurrent user
    public function resetConcurrentUser()
    {
        try {
            return $this->setConcurrentSessions(null)->saveToStorage();
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Get active user session coount
    public function activeUserSessionCount($active = true)
    {
        try {
            $sessions = $this->getConcurrentSessions();
            $sessions = is_array($sessions) ? $sessions : [];
            if ($active) {
                $sessions = $this->removeUnusedSessions($sessions);
            }
            return count($sessions);
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return 0;
    }

    // Force logout user
    public function isForceLogout($sessionId = null)
    {
        if ($this->isSysAdmin() || IsApi()) { // Ignore system admin / API
            return false;
        }
        try {
            $isForceLogout = $sessionId === null ? true : false;
            $sessions = $this->getConcurrentSessions();
            $sessions = is_array($sessions) ? $sessions : [];
            foreach ($sessions as $session) {
                if ($sessionId === null) { // All session must be force logout
                    if (!ConvertToBool($session[self::$FORCE_LOGOUT])) {
                        return false;
                    }
                } elseif (SameText($session[self::$SESSION_ID], $sessionId)) {
                    return ConvertToBool($session[self::$FORCE_LOGOUT]);
                }
            }
            return $isForceLogout;
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Force logout user
    public function forceLogoutUser()
    {
        if (!self::$FORCE_LOGOUT_USER) {
            return false;
        }
        try {
            $sessions = $this->getConcurrentSessions();
            $sessions = is_array($sessions) ? $sessions : [];
            $sessions = $this->removeUnusedSessions($sessions);
            foreach ($sessions as &$session) {
                $session[self::$FORCE_LOGOUT] = true;
            }
            return $this->setConcurrentSessions($sessions)->saveToStorage();
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Exceed login retry
    public function exceedLoginRetry()
    {
        if ($this->isSysAdmin()) { // Ignore system admin
            return false;
        }
        try {
            $retrycount = $this->getLoginRetryCount();
            $dt = $this->getLastBadLoginDateTime();
            if ((int)$retrycount >= (int)$this->MaxRetryCount) {
                return DateDiff($dt, StdCurrentDateTime(), "n") >= $this->RetryLockoutTime
                    ? $this->resetLoginRetry()
                    : true;
            }
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Reset login retry
    public function resetLoginRetry()
    {
        try {
            return $this->setLoginRetryCount(0)->saveToStorage();
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Password expired
    public function passwordExpired()
    {
        if ($this->isSysAdmin()) { // Ignore system admin
            return false;
        }
        try {
            $dt = $this->getLastPasswordChangedDate();
            if (strval($dt) == "") {
                $dt = StdCurrentDate();
            }
            return DateDiff($dt, StdCurrentDate(), "d") >= $this->PasswordExpiryTime;
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Empty password changed date
    public function emptyPasswordChangedDate()
    {
        if ($this->isSysAdmin()) { // Ignore system admin
            return false;
        }
        try {
            $dt = $this->getLastPasswordChangedDate();
            return (strval($dt) == "");
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Set password expired
    public function setPasswordExpired()
    {
        try {
            return $this->setLastPasswordChangedDate(StdDate(strtotime("-" . ($this->PasswordExpiryTime + 1) . " days")))
                ->saveToStorage();
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Get login retry count
    public function getLoginRetryCount()
    {
        return $this->{self::$LOGIN_RETRY_COUNT};
    }

    // Set login retry count
    public function setLoginRetryCount($value)
    {
        $this->{self::$LOGIN_RETRY_COUNT} = $value;
        return $this;
    }

    // Get last bad login date time
    public function getLastBadLoginDateTime()
    {
        return $this->{self::$LAST_BAD_LOGIN_DATE_TIME};
    }

    // Set last bad login date time
    public function setLastBadLoginDateTime($value)
    {
        $this->{self::$LAST_BAD_LOGIN_DATE_TIME} = $value;
        return $this;
    }

    // Get last password changed date
    public function getLastPasswordChangedDate()
    {
        return $this->{self::$LAST_PASSWORD_CHANGED_DATE};
    }

    // Set last password changed date
    public function setLastPasswordChangedDate($value)
    {
        $this->{self::$LAST_PASSWORD_CHANGED_DATE} = $value;
        return $this;
    }

    // Get secret
    public function getSecret()
    {
        return $this->{self::$SECRET};
    }

    // Set secret
    public function setSecret($value)
    {
        $this->{self::$SECRET} = $value;
        return $this;
    }

    // Get secret create datetime
    public function getSecretCreateDateTime()
    {
        return $this->{self::$SECRET_CREATE_DATE_TIME};
    }

    // Set secret create datetime
    public function setSecretCreateDateTime($value)
    {
        $this->{self::$SECRET_CREATE_DATE_TIME} = $value;
        return $this;
    }

    // Get secret verify datetime
    public function getSecretVerifyDateTime()
    {
        return $this->{self::$SECRET_VERIFY_DATE_TIME};
    }

    // Set secret verify datetime
    public function setSecretVerifyDateTime($value)
    {
        $this->{self::$SECRET_VERIFY_DATE_TIME} = $value;
        return $this;
    }

    // Get secret last verify code
    public function getSecretLastVerifyCode()
    {
        return $this->{self::$SECRET_LAST_VERIFY_CODE};
    }

    // Set secret last verify code
    public function setSecretLastVerifyCode($value)
    {
        $this->{self::$SECRET_LAST_VERIFY_CODE} = $value;
        return $this;
    }

    // Get backup codes
    public function getCodes()
    {
        return $this->{self::$BACKUP_CODES};
    }

    // Set backup codes
    public function setCodes($value)
    {
        $this->{self::$BACKUP_CODES} = $value;
        return $this;
    }

    // Get one time password
    public function getPassword()
    {
        return $this->{self::$ONE_TIME_PASSWORD};
    }

    // Set one time password
    public function setPassword($value)
    {
        $this->{self::$ONE_TIME_PASSWORD} = $value;
        return $this;
    }

    // Get OTP account
    public function getOtpAccount()
    {
        return $this->{self::$OTP_ACCOUNT};
    }

    // Set OTP account
    public function setOtpAccount($value)
    {
        $this->{self::$OTP_ACCOUNT} = $value;
        return $this;
    }

    // Get OTP create datetime
    public function getOtpCreateDateTime()
    {
        return $this->{self::$OTP_CREATE_DATE_TIME};
    }

    // Set OTP create datetime
    public function setOtpCreateDateTime($value)
    {
        $this->{self::$OTP_CREATE_DATE_TIME} = $value;
        return $this;
    }

    // Get OTP verify datetime
    public function getOtpVerifyDateTime()
    {
        return $this->{self::$OTP_VERIFY_DATE_TIME};
    }

    // Set OTP verify datetime
    public function setOtpVerifyDateTime($value)
    {
        $this->{self::$OTP_VERIFY_DATE_TIME} = $value;
        return $this;
    }

    // Get chat enabled
    public function getChatEnabled()
    {
        return $this->{self::$CHAT_ENABLED};
    }

    // Set chat enabled
    public function setChatEnabled($value)
    {
        $this->{self::$CHAT_ENABLED} = $value;
        return $this;
    }

    // User has 2FA secret
    public function hasUserSecret($verified = false)
    {
        try {
            $secret = $this->getSecret();
            $valid = !EmptyValue($secret); // Secret is not empty
            if ($valid && $verified) {
                $verifyDateTime = $this->getSecretVerifyDateTime();
                $verifyCode = $this->getSecretLastVerifyCode();
                $valid = !empty($verifyDateTime) && !empty($verifyCode);
            }
            return $valid;
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Get user 2FA secret
    public function getUserSecret()
    {
        try {
            $secret = $this->getSecret();
            // Create new secret and save to profile
            if (EmptyValue($secret)) {
                $className = TwoFactorAuthenticationClass();
                $secret = $className::generateSecret();
                $backupCodes = $className::generateBackupCodes();
                $this->setSecret($secret)
                    ->setSecretCreateDateTime(DbCurrentDateTime())
                    ->setBackupCodes($backupCodes)
                    ->saveToStorage();
            }
            return $secret;
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return "";
    }

    // Set one time password (Email/SMS)
    public function setOneTimePassword($account, $otp)
    {
        try {
            return $this->setPassword($otp)
                ->setOtpAccount($account)
                ->setOtpCreateDateTime(DbCurrentDateTime())
                ->saveToStorage();
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Get decrypted backup codes
    public function getBackupCodes()
    {
        try {
            $codes = $this->getCodes();
            $decryptedCodes = is_array($codes)
                ? array_map(fn($code) => strlen($code) == Config("TWO_FACTOR_AUTHENTICATION_BACKUP_CODE_LENGTH") ? $code : PhpDecrypt(strval($code)), $codes) // Encrypt backup codes if necessary
                : [];
            return $decryptedCodes;
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
    }

    // Set encrypted backup codes
    public function setBackupCodes(array $codes)
    {
        try {
            $encryptedCodes = array_map(fn($code) => strlen($code) == Config("TWO_FACTOR_AUTHENTICATION_BACKUP_CODE_LENGTH") ? PhpEncrypt(strval($code)) : $code, $codes); // Encrypt backup codes if necessary
            $this->setCodes($encryptedCodes);
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        } finally {
            return $this;
        }
    }

    // Get new set of backup codes
    public function getNewBackupCodes(): array
    {
        try {
            $codes = TwoFactorAuthenticationClass()::generateBackupCodes();
            $this->setBackupCodes($codes)->saveToStorage();
            return $codes;
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return [];
    }

    // Verify 2FA code
    public function verify2FACode($code)
    {
        try {
            if (SameText(Config("TWO_FACTOR_AUTHENTICATION_TYPE"), "google")) { // Check against secret
                $storedCode = $this->getSecret();
            } else { // Check against encrypted one time password
                $secret = $this->getSecret();
                $storedCode = Decrypt($this->getPassword(), $secret);
            }
            if ($storedCode !== "") { // Stored code is not empty
                $valid = TwoFactorAuthenticationClass()::checkCode($storedCode, $code);
                if (!$valid && strlen($code) == Config("TWO_FACTOR_AUTHENTICATION_BACKUP_CODE_LENGTH")) { // Not valid, check if $code is backup code
                    $backupCodes = $this->getBackupCodes();
                    $valid = array_search($code, $backupCodes);
                    if ($valid !== false) {
                        array_splice($backupCodes, $valid, 1); // Remove used backup code
                        $this->setBackupCodes($backupCodes);
                        $valid = true;
                    }
                }
                if ($valid) { // Update verify date/time
                    $this->setSecretVerifyDateTime(DbCurrentDateTime())->setSecretLastVerifyCode($code);
                    if (!SameText(Config("TWO_FACTOR_AUTHENTICATION_TYPE"), "google")) {
                        $this->setOtpVerifyDateTime(DbCurrentDateTime()); // Set OTP verify date time
                    }
                    $this->saveToStorage();
                    // Update email address / mobile number if not verified
                    $account = $this->getOtpAccount();
                    if ($account && !$this->isSysAdmin()) {
                        $user = $this->getUser();
                        if (SameText(Config("TWO_FACTOR_AUTHENTICATION_TYPE"), "email")) {
                            $user->set(Config("USER_EMAIL_FIELD_NAME"), $account);
                        } elseif (SameText(Config("TWO_FACTOR_AUTHENTICATION_TYPE"), "sms")) {
                            $user->set(Config("USER_PHONE_FIELD_NAME"), $account);
                        }
                        GetUserEntityManager()->flush();
                    }
                }
                return $valid;
            }
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }

    // Reset user secret
    public function resetUserSecret()
    {
        try {
            return $this->setSecret(null)
                ->setSecretCreateDateTime(null)
                ->setSecretVerifyDateTime(null)
                ->setSecretLastVerifyCode(null)
                ->setCodes(null)
                ->saveToStorage();
        } catch (\Throwable $e) {
            if (Config("DEBUG")) {
                throw $e;
            }
        }
        return false;
    }
}
