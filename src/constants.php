<?php

/**
 * PHPMaker 2024 constants
 */

namespace PHPMaker2024\FIMS;

/**
 * Constants
 */
define(__NAMESPACE__ . "\PROJECT_NAMESPACE", __NAMESPACE__ . "\\");

// PHP >= 8.1.0
define(PROJECT_NAMESPACE . "IS_PHP81", true);

// System
define(PROJECT_NAMESPACE . "IS_WINDOWS", strtolower(substr(PHP_OS, 0, 3)) === "win"); // Is Windows OS
define(PROJECT_NAMESPACE . "PATH_DELIMITER", IS_WINDOWS ? "\\" : "/"); // Physical path delimiter

// List actions
define(PROJECT_NAMESPACE . "ACTION_POSTBACK", "P"); // Post back
define(PROJECT_NAMESPACE . "ACTION_AJAX", "A"); // Ajax
define(PROJECT_NAMESPACE . "ACTION_MULTIPLE", "M"); // Multiple records
define(PROJECT_NAMESPACE . "ACTION_SINGLE", "S"); // Single record
define(PROJECT_NAMESPACE . "ACTION_CUSTOM", "C"); // Custom HTML

// User level constants
define(PROJECT_NAMESPACE . "PRIVILEGES", [
    "add",
    "delete",
    "edit",
    "list",
    "view",
    "search",
    "import",
    "lookup",
    "export",
    "push",
    "admin" // Put "admin" at last for userpriv page
]); // User permissions

// Product version
define(PROJECT_NAMESPACE . "PRODUCT_VERSION", "24.16.0");

// Project
define(PROJECT_NAMESPACE . "PROJECT_NAME", "FIMS"); // Project name
define(PROJECT_NAMESPACE . "PROJECT_ID", "{FIMS}"); // Project ID

/**
 * Character encoding
 * Note: If you use non English languages, you need to set character encoding.
 * Make sure multibyte string functions are enabled and your encoding is supported.
 */
define(PROJECT_NAMESPACE . "PROJECT_CHARSET", "utf-8"); // Charset (deprecated)
define(PROJECT_NAMESPACE . "IS_UTF8", true); // UTF-8 (deprecated)
define(PROJECT_NAMESPACE . "PROJECT_ENCODING", "UTF-8"); // Character encoding (deprecated)
define(PROJECT_NAMESPACE . "IS_DOUBLE_BYTE", in_array(PROJECT_ENCODING, ["GBK", "BIG5", "SHIFT_JIS"])); // Double-byte character encoding (deprecated)
define(PROJECT_NAMESPACE . "FILE_SYSTEM_ENCODING", ""); // File system encoding

// Session
define(PROJECT_NAMESPACE . "SESSION_STATUS", PROJECT_NAME . "_Status"); // Login status
define(PROJECT_NAMESPACE . "SESSION_USER_PROFILE", SESSION_STATUS . "_UserProfile"); // User profile
define(PROJECT_NAMESPACE . "SESSION_USER_NAME", SESSION_STATUS . "_UserName"); // User name
define(PROJECT_NAMESPACE . "SESSION_USER_LOGIN_TYPE", SESSION_STATUS . "_UserLoginType"); // User login type
define(PROJECT_NAMESPACE . "SESSION_USER_ID", SESSION_STATUS . "_UserID"); // User ID
define(PROJECT_NAMESPACE . "SESSION_USER_PRIMARY_KEY", SESSION_STATUS . "_UserPrimaryKey"); // User primary key
define(PROJECT_NAMESPACE . "SESSION_USER_PROFILE_USER_NAME", SESSION_USER_PROFILE . "_UserName");
define(PROJECT_NAMESPACE . "SESSION_USER_PROFILE_PASSWORD", SESSION_USER_PROFILE . "_Password");
define(PROJECT_NAMESPACE . "SESSION_USER_PROFILE_LOGIN_TYPE", SESSION_USER_PROFILE . "_LoginType");
define(PROJECT_NAMESPACE . "SESSION_USER_PROFILE_SECRET", SESSION_USER_PROFILE . "_Secret");
define(PROJECT_NAMESPACE . "SESSION_USER_PROFILE_RECORD", SESSION_USER_PROFILE . "_Record");
define(PROJECT_NAMESPACE . "SESSION_USER_PROFILE_SECURITY_CODE", SESSION_USER_PROFILE . "_SecurityCode");
define(PROJECT_NAMESPACE . "SYS_ADMIN_USER_PROFILE", "UserProfile");
define(PROJECT_NAMESPACE . "SYS_ADMIN_EMAIL_ADDRESS", "EmailAddress");
define(PROJECT_NAMESPACE . "SYS_ADMIN_PHONE_NUMBER", "PhoneNumber");
define(PROJECT_NAMESPACE . "SESSION_USER_LEVEL_ID", SESSION_STATUS . "_UserLevel"); // User Level ID
define(PROJECT_NAMESPACE . "SESSION_USER_LEVEL_LIST", SESSION_STATUS . "_UserLevelList"); // User Level List
define(PROJECT_NAMESPACE . "SESSION_USER_LEVEL_LIST_LOADED", SESSION_STATUS . "_UserLevelListLoaded"); // User Level List Loaded
define(PROJECT_NAMESPACE . "SESSION_USER_LEVEL", SESSION_STATUS . "_UserLevelValue"); // User Level
define(PROJECT_NAMESPACE . "SESSION_PARENT_USER_ID", SESSION_STATUS . "_ParentUserId"); // Parent User ID
define(PROJECT_NAMESPACE . "SESSION_SYS_ADMIN", PROJECT_NAME . "_SysAdmin"); // System admin
define(PROJECT_NAMESPACE . "SESSION_PROJECT_ID", PROJECT_NAME . "_ProjectId"); // User Level project ID
define(PROJECT_NAMESPACE . "SESSION_USER_LEVELS", PROJECT_NAME . "_UserLevels"); // User Levels (array)
define(PROJECT_NAMESPACE . "SESSION_USER_LEVEL_PRIVS", PROJECT_NAME . "_UserLevelPrivs"); // User Level privileges (array)
define(PROJECT_NAMESPACE . "SESSION_USER_LEVEL_MSG", PROJECT_NAME . "_UserLevelMessage"); // User Level Message
define(PROJECT_NAMESPACE . "SESSION_MESSAGE", PROJECT_NAME . "_Message"); // System message
define(PROJECT_NAMESPACE . "SESSION_FAILURE_MESSAGE", PROJECT_NAME . "_FailureMessage"); // Failure message
define(PROJECT_NAMESPACE . "SESSION_SUCCESS_MESSAGE", PROJECT_NAME . "_SuccessMessage"); // Success message
define(PROJECT_NAMESPACE . "SESSION_WARNING_MESSAGE", PROJECT_NAME . "_WarningMessage"); // Warning message
define(PROJECT_NAMESPACE . "SESSION_MESSAGE_HEADING", PROJECT_NAME . "_MessageHeading"); // Message heading
define(PROJECT_NAMESPACE . "SESSION_INLINE_MODE", PROJECT_NAME . "_InlineMode"); // Inline mode
define(PROJECT_NAMESPACE . "SESSION_BREADCRUMB", PROJECT_NAME . "_Breadcrumb"); // Breadcrumb
define(PROJECT_NAMESPACE . "SESSION_HISTORY", PROJECT_NAME . "_History"); // History (Breadcrumb)
define(PROJECT_NAMESPACE . "SESSION_TEMP_IMAGES", PROJECT_NAME . "_TempImages"); // Temp images
define(PROJECT_NAMESPACE . "SESSION_CAPTCHA_CODE", PROJECT_NAME . "_Captcha"); // Captcha code
define(PROJECT_NAMESPACE . "SESSION_LANGUAGE_ID", PROJECT_NAME . "_LanguageId"); // Language ID
define(PROJECT_NAMESPACE . "SESSION_LOGOUT_PARAMS", PROJECT_NAME . "_LanguageId"); // Logout paramters
define(PROJECT_NAMESPACE . "SESSION_MYSQL_ENGINES", PROJECT_NAME . "_MySqlEngines"); // MySQL table engines
define(PROJECT_NAMESPACE . "SESSION_ACTIVE_USERS", PROJECT_NAME . "_ActiveUsers"); // Active users
