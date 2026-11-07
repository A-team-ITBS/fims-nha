<?php

/**
 * PagePermission — Global permission helper for custom pages.
 *
 * Works across FMD, ACCOUNTING, TREASURY, UAC and any PHPMaker-based system.
 * Reads permissions from uac.user_level_permissions using the same bitwise
 * flags set up in the Security Management page.
 *
 * ----------------------------------------------------------------------------
 * SETUP (once per system, in app/api.php or app/global.php):
 * ----------------------------------------------------------------------------
 *   require_once __DIR__ . '/../../_shared/PagePermission.php';
 *   PagePermission::boot('PHPMaker2024\\FMD');
 *
 * ----------------------------------------------------------------------------
 * USAGE IN CUSTOM PAGE PHP (e.g. app/pages/journal-entry.php):
 * ----------------------------------------------------------------------------
 *   $perm = new PagePermission('FMD', 'journal-entry');
 *   if (!$perm->canView()) {
 *       http_response_code(403);
 *       echo json_encode(['success' => false, 'message' => 'Access denied.']);
 *       exit;
 *   }
 *   // Expose to JavaScript:
 *   $permJson = json_encode($perm->toArray());
 *
 * ----------------------------------------------------------------------------
 * USAGE AS SLIM MIDDLEWARE (in app/routes/*.php):
 * ----------------------------------------------------------------------------
 *   $app->get('/journal-entry/list', [JournalEntryService::class, 'list'])
 *       ->add(PagePermission::middleware('FMD', 'journal-entry', PagePermission::ALLOW_LIST))
 *       ->add($jwtMiddleware);
 *
 * ----------------------------------------------------------------------------
 * REGISTERING CUSTOM PAGES IN SECURITY MANAGEMENT:
 * ----------------------------------------------------------------------------
 * Add each custom page to the systems.level_permissions JSON field for its
 * system. Each entry follows the PHPMaker format:
 *   ["page-name", "page_var", "Page Caption", true, "SYSTEM_CODE", "url"]
 *
 * The table_name stored in user_level_permissions will be:
 *   {SYSTEM_CODE}page-name   e.g. {FMD}journal-entry
 */

// This file intentionally has no namespace declaration so the class lands in
// the global namespace and is safely shareable across all PHPMaker systems.

class PagePermission
{
    // --- Permission bit flags (mirrors UAC definitions.php) ---
    const ALLOW_ADD    = 1;
    const ALLOW_DELETE = 2;
    const ALLOW_EDIT   = 4;
    const ALLOW_LIST   = 8;
    const ALLOW_ADMIN  = 16;
    const ALLOW_VIEW   = 32;
    const ALLOW_SEARCH = 64;
    const ALLOW_IMPORT = 128;
    const ALLOW_LOOKUP = 256;
    const ALLOW_PUSH   = 512;
    const ALLOW_EXPORT = 1024;

    // --- Static backend wired up once per system via boot() ---
    /** @var callable|null */
    private static $fnExecuteScalar = null;
    /** @var callable|null */
    private static $fnDecodeJwt     = null;
    /** @var callable|null */
    private static $fnGetJwtToken   = null;

    // --- Instance state ---
    private $permission   = 0;
    private $adminBypass  = false;

    // -------------------------------------------------------------------------
    // Boot — call once in api.php / global.php for each system
    // -------------------------------------------------------------------------

    /**
     * Wire up PHPMaker helper functions from the given namespace.
     *
     * @param string $namespace  e.g. 'PHPMaker2024\\FMD'
     */
    public static function boot(string $namespace): void
    {
        self::$fnExecuteScalar = function (string $sql) use ($namespace) {
            return call_user_func("\\{$namespace}\\ExecuteScalar", $sql, 'DB');
        };
        self::$fnDecodeJwt = function (string $jwt) use ($namespace) {
            return call_user_func("\\{$namespace}\\DecodeJwt", $jwt) ?? [];
        };
        self::$fnGetJwtToken = function () use ($namespace) {
            return call_user_func("\\{$namespace}\\GetJwtToken");
        };
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param string     $systemCode  System code as stored in UAC (e.g. 'FMD', 'ACCOUNTING')
     * @param string     $pageName    Page/module name matching the security management entry
     * @param array|null $jwtPayload  Pre-decoded JWT payload; null = auto-resolve from request
     */
    public function __construct(string $systemCode, string $pageName, ?array $jwtPayload = null)
    {
        $payload    = $jwtPayload ?? $this->resolvePayload();
        $userLevel  = $payload['userlevel'] ?? '-2';

        // Admin (user level -1) bypasses all permission checks
        if ((string)$userLevel === '-1' || (int)$userLevel === -1) {
            $this->adminBypass = true;
            $this->permission  = PHP_INT_MAX;
            return;
        }

        $tableName = '{' . $systemCode . '}' . $pageName;
        $this->permission = $this->queryPermission($tableName, (string)$userLevel);
    }

    // -------------------------------------------------------------------------
    // Permission checks
    // -------------------------------------------------------------------------

    public function isAdmin():   bool { return $this->adminBypass; }
    public function canAdd():    bool { return $this->check(self::ALLOW_ADD); }
    public function canDelete(): bool { return $this->check(self::ALLOW_DELETE); }
    public function canEdit():   bool { return $this->check(self::ALLOW_EDIT); }
    public function canList():   bool { return $this->check(self::ALLOW_LIST); }
    public function canView():   bool { return $this->check(self::ALLOW_VIEW); }
    public function canSearch(): bool { return $this->check(self::ALLOW_SEARCH); }
    public function canImport(): bool { return $this->check(self::ALLOW_IMPORT); }
    public function canExport(): bool { return $this->check(self::ALLOW_EXPORT); }
    public function canLookup(): bool { return $this->check(self::ALLOW_LOOKUP); }
    public function canPush():   bool { return $this->check(self::ALLOW_PUSH); }

    /** Check any custom or combined flag. */
    public function has(int $flag): bool { return $this->check($flag); }

    /** Raw integer permission value. */
    public function getPermission(): int { return $this->permission; }

    /**
     * Serialize to array — pass to json_encode() and expose to JavaScript.
     *
     * Usage: const PERM = <?= json_encode($perm->toArray()) ?>;
     * Then:  if (PERM.canEdit) { ... }
     */
    public function toArray(): array
    {
        return [
            'isAdmin'    => $this->isAdmin(),
            'canAdd'     => $this->canAdd(),
            'canDelete'  => $this->canDelete(),
            'canEdit'    => $this->canEdit(),
            'canList'    => $this->canList(),
            'canView'    => $this->canView(),
            'canSearch'  => $this->canSearch(),
            'canImport'  => $this->canImport(),
            'canExport'  => $this->canExport(),
            'canLookup'  => $this->canLookup(),
            'canPush'    => $this->canPush(),
            'permission' => $this->permission,
        ];
    }

    // -------------------------------------------------------------------------
    // Slim middleware factory
    // -------------------------------------------------------------------------

    /**
     * Dynamic middleware — resolves the owning system(s) at request time by
     * scanning uac.systems.level_permissions for entries matching $pageName.
     * Grants access if ANY registering system has the required flag ticked
     * for the caller's user level. Lets a page be gated purely by JSON
     * registration — no hardcoded system code in the API file.
     *
     * @param string $pageName     e.g. 'JevListing.php'
     * @param int    $requiredFlag Default: ALLOW_VIEW (32)
     */
    public static function dynamicMiddleware(
        string $pageName,
        int $requiredFlag = self::ALLOW_VIEW
    ): \Closure {
        return function (
            \Psr\Http\Message\ServerRequestInterface  $request,
            \Psr\Http\Server\RequestHandlerInterface  $handler
        ) use ($pageName, $requiredFlag): \Psr\Http\Message\ResponseInterface {

            $authHeader = $request->getHeaderLine('Authorization')
                       ?: $request->getHeaderLine('X-Authorization');

            if (!$authHeader && self::$fnGetJwtToken) {
                $authHeader = (self::$fnGetJwtToken)();
            }

            $jwt     = ltrim(str_replace('Bearer ', '', $authHeader));
            $payload = ($jwt && self::$fnDecodeJwt)
                ? (self::$fnDecodeJwt)($jwt)
                : [];

            $userLevel = $payload['userlevel'] ?? '-2';

            // Admin bypass — user level -1 short-circuits all checks.
            if ((string)$userLevel === '-1' || (int)$userLevel === -1) {
                return $handler->handle($request);
            }

            $permission = self::queryDynamicPermission($pageName, (string)$userLevel);

            if (($permission & $requiredFlag) === 0) {
                $response = new \Nyholm\Psr7\Response();
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to access this resource.',
                ]));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(403);
            }

            return $handler->handle($request);
        };
    }

    /**
     * Returns a PSR-15–compatible Slim middleware closure.
     *
     * The middleware:
     *   1. Decodes the JWT from the Authorization header (or cookie fallback).
     *   2. Checks the required permission flag against uac.user_level_permissions.
     *   3. Returns 403 JSON on failure; attaches the PagePermission instance to
     *      the request as the 'pagePermission' attribute on success.
     *
     * @param string $systemCode   e.g. 'FMD'
     * @param string $pageName     e.g. 'journal-entry'
     * @param int    $requiredFlag Default: ALLOW_VIEW (32)
     */
    public static function middleware(
        string $systemCode,
        string $pageName,
        int $requiredFlag = self::ALLOW_VIEW
    ): \Closure {
        return function (
            \Psr\Http\Message\ServerRequestInterface  $request,
            \Psr\Http\Server\RequestHandlerInterface  $handler
        ) use ($systemCode, $pageName, $requiredFlag): \Psr\Http\Message\ResponseInterface {

            $authHeader = $request->getHeaderLine('Authorization')
                       ?: $request->getHeaderLine('X-Authorization');

            if (!$authHeader && self::$fnGetJwtToken) {
                $authHeader = (self::$fnGetJwtToken)();
            }

            $jwt     = ltrim(str_replace('Bearer ', '', $authHeader));
            $payload = ($jwt && self::$fnDecodeJwt)
                ? (self::$fnDecodeJwt)($jwt)
                : [];

            $perm = new self($systemCode, $pageName, $payload ?: []);

            if (!$perm->has($requiredFlag)) {
                $response = new \Nyholm\Psr7\Response();
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'You do not have permission to access this resource.',
                ]));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(403);
            }

            return $handler->handle(
                $request->withAttribute('pagePermission', $perm)
            );
        };
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function check(int $flag): bool
    {
        return $this->adminBypass || ($this->permission & $flag) !== 0;
    }

    private function resolvePayload(): array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
                   ?? $_SERVER['HTTP_X_AUTHORIZATION']
                   ?? '';

        if (!$authHeader && self::$fnGetJwtToken) {
            $authHeader = (self::$fnGetJwtToken)();
        }

        // Cookie fallback (EW_TOKEN is PHPMaker's default JWT cookie name)
        if (!$authHeader) {
            $cookieName = 'EW_TOKEN';
            if (isset($_COOKIE[$cookieName])) {
                $authHeader = 'Bearer ' . $_COOKIE[$cookieName];
            }
        }

        $jwt = ltrim(str_replace('Bearer ', '', $authHeader ?? ''));

        if (empty($jwt) || !self::$fnDecodeJwt) {
            return [];
        }

        return (self::$fnDecodeJwt)($jwt) ?: [];
    }

    /**
     * Aggregated permission across every system that registers $pageName in
     * its level_permissions JSON. Returns the bitwise-OR MAX so any tick in
     * any registering system contributes. One SQL round-trip.
     */
    private static function queryDynamicPermission(string $pageName, string $userLevelStr): int
    {
        if (!self::$fnExecuteScalar) {
            return 0;
        }

        // Sanitize inputs — pageName is developer-controlled, but defense-in-depth.
        $safePage  = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $pageName);
        $safeLevel = preg_replace('/[^0-9,\-]/', '', $userLevelStr);

        if (empty($safeLevel)) {
            $safeLevel = '-2'; // anonymous
        }

        $sql = "SELECT COALESCE(MAX(ulp.permission), 0)
                FROM uac.systems s
                JOIN uac.user_level_permissions ulp
                     ON ulp.table_name = '{' || s.system_code || '}' || '{$safePage}'
                WHERE s.is_active = TRUE
                  AND s.level_permissions IS NOT NULL
                  AND EXISTS (
                    SELECT 1
                    FROM jsonb_array_elements(s.level_permissions) AS elem
                    WHERE elem->>0 = '{$safePage}'
                      AND (elem->>3)::boolean = TRUE
                  )
                  AND ulp.user_level_id = ANY(STRING_TO_ARRAY('{$safeLevel}', ',')::integer[])";

        $GLOBALS['_PagePermission_DynamicDebug'] = [
            'page_input'     => $pageName,
            'page_sanitized' => $safePage,
            'level_input'    => $userLevelStr,
            'level_sanitized'=> $safeLevel,
            'sql'            => $sql,
        ];

        try {
            $result = (int)(self::$fnExecuteScalar)($sql);
            $GLOBALS['_PagePermission_DynamicDebug']['result'] = $result;
            $GLOBALS['_PagePermission_DynamicDebug']['error']  = null;
            return $result;
        } catch (\Exception $e) {
            $GLOBALS['_PagePermission_DynamicDebug']['result'] = 0;
            $GLOBALS['_PagePermission_DynamicDebug']['error']  = $e->getMessage();
            return 0;
        }
    }

    private function queryPermission(string $tableName, string $userLevelStr): int
    {
        if (!self::$fnExecuteScalar) {
            return 0;
        }

        // Sanitize inputs — these are developer-controlled, but defense-in-depth:
        // tableName: allow {UPPERCASE-CODE}Name.php form (letters, digits,
        // underscore, hyphen, dot, curly braces). Dot is required for custom
        // pages whose DB key includes the .php extension (PHPMaker convention).
        $safeTable = preg_replace('/[^a-zA-Z0-9_\-\.\{\}]/', '', $tableName);
        // userLevelStr: comma-separated integers (e.g. "3,5,12")
        $safeLevel = preg_replace('/[^0-9,\-]/', '', $userLevelStr);

        if (empty($safeLevel)) {
            $safeLevel = '-2'; // anonymous
        }

        // Aggregate MAX so multi-role users get their highest permission
        $sql = "SELECT COALESCE(MAX(permission), 0)
                FROM uac.user_level_permissions
                WHERE table_name = '{$safeTable}'
                AND user_level_id = ANY(STRING_TO_ARRAY('{$safeLevel}', ',')::integer[])";

        // DEBUG
        $GLOBALS['_PagePermission_Debug'] = [
            'table_input' => $tableName,
            'table_sanitized' => $safeTable,
            'level_input' => $userLevelStr,
            'level_sanitized' => $safeLevel,
            'sql' => $sql,
        ];
        
        try {
            $result = (int)(self::$fnExecuteScalar)($sql);
            $GLOBALS['_PagePermission_Debug']['result'] = $result;
            $GLOBALS['_PagePermission_Debug']['error'] = null;
        } catch (\Exception $e) {
            $GLOBALS['_PagePermission_Debug']['result'] = 0;
            $GLOBALS['_PagePermission_Debug']['error'] = $e->getMessage();
            return 0;
        }
        
        return $result;
    }
}
