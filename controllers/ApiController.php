<?php

namespace PHPMaker2024\FIMS;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Support\Collection;
use PHPMaker2024\FIMS\Attributes\Delete;
use PHPMaker2024\FIMS\Attributes\Get;
use PHPMaker2024\FIMS\Attributes\Map;
use PHPMaker2024\FIMS\Attributes\Options;
use PHPMaker2024\FIMS\Attributes\Patch;
use PHPMaker2024\FIMS\Attributes\Post;
use PHPMaker2024\FIMS\Attributes\Put;

/**
 * API controller
 */
class ApiController extends AbstractController
{
    protected ?string $pageName;

    /**
     * Process page
     */
    public function processPage(Request $request, Response $response, array $args)
    {
        $this->setup($request, $response);
        if ($this->pageName) {
            $pageClass = PROJECT_NAMESPACE . $this->pageName;
            if (class_exists($pageClass)) {
                $page = new $pageClass();
                $page->run();
                // Render page if not terminated
                if (!$page->isTerminated()) {
                    $view = $this->container->get("app.view");
                    $page->RenderingView = true;
                    $layout = property_exists($page, "MultiColumnLayout") && $page->MultiColumnLayout == "cards" ? "Cards" : "Table";
                    $template = $page->TableVar . $layout . ".php"; // View
                    $GLOBALS["Title"] ??= $page->Title; // Title
                    try {
                        $response = $view->render($response, $template, $GLOBALS);
                    } finally {
                        $page->RenderingView = false;
                        $page->terminate(true); // Terminate page and clean up
                    }
                }
            }
        }
        return $response;
    }

    /**
     * login
     */
    #[Map(["POST", "OPTIONS"], "/login", [JwtMiddleware::class], "login")]
    public function login(Request $request, Response $response, array $args): Response
    {
        global $Language, $Security;
        $this->setup($request, $response);
        if ($request->isGet()) {
            $username = $request->getQueryParam(Config("API_LOGIN_USERNAME"));
            $password = $request->getQueryParam(Config("API_LOGIN_PASSWORD"));
            $code = $request->getQueryParam(Config("API_LOGIN_SECURITY_CODE"));
            $expire = $request->getQueryParam(Config("API_LOGIN_EXPIRE"));
            $permission = $request->getQueryParam(Config("API_LOGIN_PERMISSION"));
        } else {
            $username = $request->getParsedBodyParam(Config("API_LOGIN_USERNAME"));
            $password = $request->getParsedBodyParam(Config("API_LOGIN_PASSWORD"));
            $code = $request->getParsedBodyParam(Config("API_LOGIN_SECURITY_CODE"));
            $expire = $request->getParsedBodyParam(Config("API_LOGIN_EXPIRE"));
            $permission = $request->getParsedBodyParam(Config("API_LOGIN_PERMISSION"));
        }
        $Security = $this->container->get("app.security");
        $Language = $this->container->get("app.language");
        // Valdiate expire
        if ($expire && (!is_numeric($expire) || ParseInteger($expire) <= 0)) {
            return $response->withJson(["error" => $Language->phrase("IncorrectInteger") . ": " . Config("API_LOGIN_EXPIRE")]); // Incorrect expire
        }
        // Valdiate permission
        if ($permission && (!is_numeric($permission) || ParseInteger($permission) <= 0 || ParseInteger($permission) > Allow::ALL->value)) {
            return $response->withJson(["error" => $Language->phrase("IncorrectInteger") . ": " . Config("API_LOGIN_PERMISSION")]); // Incorrect expire
        }
        $validPwd = $Security->validateUser($username, $password, securityCode: $code);
        return $validPwd
            ? $response
            : $response->withStatus(401); // Not authorized
    }

    /**
     * list
     */
    #[Map(["GET", "OPTIONS"], "/list/{table}[/{params:.*}]", [ApiPermissionMiddleware::class, JwtMiddleware::class], "list")]
    public function list(Request $request, Response $response, array $args): Response
    {
        $table = $args["table"] ?? Get(Config("API_OBJECT_NAME"));
        if ($table) {
            $this->pageName = $this->container->get($table)?->getApiPageName("list");
        }
        return $this->processPage($request, $response, $args);
    }

    /**
     * view
     */
    #[Map(["GET", "OPTIONS"], "/view/{table}[/{params:.*}]", [ApiPermissionMiddleware::class, JwtMiddleware::class], "view")]
    public function view(Request $request, Response $response, array $args): Response
    {
        $table = $args["table"] ?? Get(Config("API_OBJECT_NAME"));
        if ($table) {
            $this->pageName = $this->container->get($table)?->getApiPageName("view");
        }
        return $this->processPage($request, $response, $args);
    }

    /**
     * add
     */
    #[Map(["POST", "OPTIONS"], "/add/{table}[/{params:.*}]", [ApiPermissionMiddleware::class, JwtMiddleware::class], "add")]
    public function add(Request $request, Response $response, array $args): Response
    {
        $table = $args["table"] ?? Post(Config("API_OBJECT_NAME"));
        if ($table) {
            $this->pageName = $this->container->get($table)?->getApiPageName("add");
        }
        return $this->processPage($request, $response, $args);
    }

    /**
     * edit
     */
    #[Map(["POST", "OPTIONS"], "/edit/{table}[/{params:.*}]", [ApiPermissionMiddleware::class, JwtMiddleware::class], "edit")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        $table = $args["table"] ?? Post(Config("API_OBJECT_NAME"));
        if ($table) {
            $this->pageName = $this->container->get($table)?->getApiPageName("edit");
        }
        return $this->processPage($request, $response, $args);
    }

    /**
     * delete
     */
    #[Map(["GET", "POST", "DELETE", "OPTIONS"], "/delete/{table}[/{params:.*}]", [ApiPermissionMiddleware::class, JwtMiddleware::class], "delete")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        $table = $args["table"] ?? Param(Config("API_OBJECT_NAME"));
        if ($table) {
            $this->pageName = $this->container->get($table)?->getApiPageName("delete");
        }
        return $this->processPage($request, $response, $args);
    }

    /**
     * register
     */
    #[Map(["POST", "OPTIONS"], "/register", [ApiPermissionMiddleware::class], "register")]
    public function register(Request $request, Response $response, array $args): Response
    {
        $this->pageName = "Register";
        return $this->processPage($request, $response, $args);
    }

    /**
     * file
     * /api/file/{table}/{field}/{key}
     * /api/file/{table}/{path}
     * $args["param"] can be {field} or {path}
     */
    #[Map(["GET", "OPTIONS"], "/file/{table}/{param}[/{key:.*}]", [ApiPermissionMiddleware::class, JwtMiddleware::class], "file")]
    public function file(Request $request, Response $response, array $args): Response
    {
        $this->setup($request, $response);
        (new FileViewer)();
        return $response;
    }

    /**
     * export
     * /api/export/{type}/{table}/{key}
     * /api/export/{id}
     * /api/export/search
     * $args["param"] can be {type} or {id} or "search"
     */
    #[Map(["GET", "POST", "OPTIONS"], "/export[/{param}[/{table}[/{key:.*}]]]", [ApiPermissionMiddleware::class, JwtMiddleware::class], "export")]
    public function export(Request $request, Response $response, array $args): Response
    {
        $this->setup($request, $response);
        (new ExportHandler())->export($request, $response);
        return $response;
    }

    /**
     * upload
     */
    #[Map(["POST", "OPTIONS"], "/upload", [ApiPermissionMiddleware::class, JwtMiddleware::class], "upload")]
    public function upload(Request $request, Response $response, array $args): Response
    {
        $this->setup($request, $response);
        HttpUpload::create()->getUploadedFiles();
        return $response;
    }

    /**
     * jupload
     */
    #[Map(["GET", "POST", "OPTIONS"], "/jupload", [ApiPermissionMiddleware::class], "jupload")]
    public function jupload(Request $request, Response $response, array $args): Response
    {
        $this->setup($request, $response);
        (new FileUploadHandler)();
        return $response;
    }

    /**
     * session
     */
    #[Map(["GET", "OPTIONS"], "/session", [ApiPermissionMiddleware::class], "session")]
    public function session(Request $request, Response $response, array $args): Response
    {
        $this->setup($request, $response);
        (new SessionHandler)();
        return $response;
    }

    /**
     * lookup
     */
    #[Map(["GET", "POST", "OPTIONS"], "/lookup[/{params:.*}]", [ApiPermissionMiddleware::class, JwtMiddleware::class], "lookup")]
    public function lookup(Request $request, Response $response, array $args): Response
    {
        global $Security, $Language;
        $this->setup($request, $response);
        if ($request->getContentType() == "application/json") { // Multiple requests
            $req = $request->getParsedBody();
            if (is_array($req)) { // Multiple requests
                $out = [];
                foreach ($req as $ar) {
                    if (is_string($ar)) { // Request is QueryString
                        parse_str($ar, $ar);
                    }
                    $object = $ar[Config("API_LOOKUP_PAGE")];
                    $fieldName = $ar[Config("API_FIELD_NAME")];
                    $res = [Config("API_LOOKUP_PAGE") => $object, Config("API_FIELD_NAME") => $fieldName];
                    $page = Container($object); // Don't use $this->container
                    $lookupField = $page?->Fields[$fieldName] ?? null;
                    if ($lookupField) {
                        $lookup = $lookupField->Lookup;
                        if ($lookup) {
                            $tbl = $lookup->getTable();
                            if ($tbl) {
                                $Security->loadTablePermissions($tbl->TableVar);
                                if ($Security->canLookup()) {
                                    $res = array_merge($res, $page->lookup($ar, false));
                                } else {
                                    $res = array_merge($res, ["result" => $Language->phrase("401")]);
                                }
                            }
                        }
                    }
                    if ($fieldName) {
                        $out[] = $res;
                    }
                }
                $response = $response->withJson(ConvertToUtf8($out));
            }
        } else { // Single request
            $page = $request->getParam(Config("API_LOOKUP_PAGE"));
            Container($page)?->lookup($request->getParams()); // Don't use $this->container
        }
        return $response;
    }

    /**
     * chart
     */
    #[Map(["GET", "OPTIONS"], "/chart[/{params:.*}]", [ApiPermissionMiddleware::class], "chart")]
    public function exportchart(Request $request, Response $response, array $args): Response
    {
        $this->setup($request, $response);
        (new ChartExporter)();
        return $response;
    }

    /**
     * permissions
     */
    #[Map(["GET", "POST", "OPTIONS"], "/permissions/{level}", [ApiPermissionMiddleware::class, JwtMiddleware::class], "permissions")]
    public function permissions(Request $request, Response $response, array $args): Response
    {
        global $Security, $USER_LEVELS, $USER_LEVEL_TABLES;
        $this->setup($request, $response);
        $userLevel = $args["level"] ?? null;
        if ($userLevel === null) {
            return $response;
        }

        // Set up security
        $Security = $this->container->get("app.security");
        $Security->setupUserLevel(); // Get all User Level info
        $ar = $USER_LEVEL_TABLES;

        // Get permissions
        if (IsGet()) {
            // Check user level
            $userLevels = [-2]; // Default anonymous
            if ($Security->isLoggedIn()) {
                if ($Security->isSysAdmin() && is_numeric($userLevel) && !SameString($userLevel, "-1")) { // Get permissions for user level
                    if ($Security->userLevelIDExists($userLevel)) {
                        $userLevels = [$userLevel];
                    }
                } else {
                    $userLevel = $Security->CurrentUserLevelID;
                    $userLevels = $Security->UserLevelID;
                }
            }
            $userLevel = $userLevels[0];
            $privs = [];
            $cnt = count($ar);
            for ($i = 0; $i < $cnt; $i++) {
                $projectId = $ar[$i][4];
                $tableVar = $ar[$i][1];
                $tableName = $ar[$i][0];
                $allowed = $ar[$i][3];
                if ($allowed) {
                    $priv = 0;
                    foreach ($userLevels as $level) {
                        $priv |= $Security->getUserLevelPrivEx($projectId . $tableName, $level);
                    }
                    $privs[$tableVar] = $priv;
                }
            }
            $res = ["userlevel" => $userLevel, "permissions" => $privs];
            $response = $response->withJson($res);

        // Update permissions
        } elseif (IsPost() && $Security->isSysAdmin()) { // System admin only
            $json = $request->getContentType() == "application/json" ? $request->getParsedBody() : [];

            // Validate user level
            if (!is_numeric($userLevel) || SameString($userLevel, "-1") || !Collection::make($USER_LEVELS)->first(fn ($level) => SameString($level[0], $userLevel))) {
                $res = ["userlevel" => $userLevel, "permissions" => $json, "success" => false];
                $response = $response->withJson($res);
            }

            // Validate table names / permissions
            $newPrivs = [];
            $outPrivs = [];
            foreach ($json as $tableName => $permission) {
                $table = Collection::make($ar)->first(fn ($privs) => $privs[0] == $tableName || $privs[1] == $tableName);
                if (!$table || !is_numeric($permission) || intval($permission) < 0 || intval($permission) > Allow::ALL->value) {
                    $res = ["userlevel" => $userLevel, "permissions" => $json, "success" => false];
                    $response = $response->withJson($res);
                }
                $permission = intval($permission) & Allow::ALL->value;
                $newPrivs[$table[4] . $table[1]] = $permission;
                $outPrivs[$table[1]] = $permission;
            }

            // Update permissions for user level
            if (method_exists($Security, "updatePermissions")) {
                $Security->updatePermissions($userLevel, $newPrivs);
                $res = ["userlevel" => $userLevel, "permissions" => $outPrivs, "success" => true];
                $response = $response->withJson($res);
            } else {
                $res = ["userlevel" => $userLevel, "permissions" => $json, "success" => false];
                $response = $response->withJson($res);
            }
        }
        return $response;
    }

    /**
     * push
     */
    #[Map(["GET", "POST", "OPTIONS"], "/push/{action}", [ApiPermissionMiddleware::class], "push")]
    public function push(Request $request, Response $response, array $args): Response
    {
        $this->setup($request, $response);
        $action = $args["action"] ?? null;
        $push = new PushNotification();
        if ($action == Config("API_PUSH_NOTIFICATION_SUBSCRIBE")) {
            $push->subscribe();
        } elseif ($action == Config("API_PUSH_NOTIFICATION_SEND")) {
            $push->send();
        } elseif ($action == Config("API_PUSH_NOTIFICATION_DELETE")) {
            $push->delete();
        }
        return $response;
    }

    /**
     * twofa
     */
    #[Map(["GET", "POST", "OPTIONS"], "/twofa/{action}[/{parm}]", [ApiPermissionMiddleware::class], "twofa")]
    public function twofa(Request $request, Response $response, array $args): Response
    {
        $this->setup($request, $response);
        $action = $args["action"] ?? null;
        $parm = $args["parm"] ?? null;
        $className = TwoFactorAuthenticationClass();
        $auth = new $className();
        if ($action == Config("API_2FA_SHOW")) {
            $auth->show();
        } elseif ($action == Config("API_2FA_VERIFY")) {
            $auth->verify($parm);
        } elseif ($action == Config("API_2FA_RESET")) {
            $auth->reset($parm);
        } elseif ($action == Config("API_2FA_BACKUP_CODES")) {
            $auth->getBackupCodes();
        } elseif ($action == Config("API_2FA_NEW_BACKUP_CODES")) {
            $auth->getNewBackupCodes();
        } elseif ($action == Config("API_2FA_SEND_OTP")) {
            $usr = $_SESSION[SESSION_USER_PROFILE_USER_NAME] ?? CurrentUserName(); // Send OTP to logging in or current user
            $res = $className::sendOneTimePassword($usr, $parm);
            if ($res === true) { // Send successful
                $response = $response->withJson(["success" => true]);
            } else {
                $response = $response->withJson(ConvertToUtf8(["success" => false, "error" => ["description" => $res]]));
            }
        }
        return $response;
    }

    /**
     * metadata
     */
    #[Get("/metadata", [ApiPermissionMiddleware::class], "metadata")]
    public function metadata(Request $request, Response $response, array $args): Response
    {
        return $response;
    }

    /**
     * chat
     */
    #[Get("/chat/{value:[01]}", [ApiPermissionMiddleware::class, JwtMiddleware::class], "chat")]
    public function chat(Request $request, Response $response, array $args): Response
    {
        if (IsLoggedIn() && !IsSysAdmin()) {
            if ((new UserProfile(CurrentUserName()))->setChatEnabled(ConvertToBool($args["value"]))->saveToStorage()) {
                return $response->withJson(["success" => true]);
            }
        }
        return $response->withJson(["success" => false]);
    }

    /**
     * Other API actions
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $this->setup($request, $response);
        if (count(Route()) == 0) {
            return $response;
        }

        // Handle custom actions (deprecated)
        $action = Route(0);
        if ($action && is_callable($GLOBALS["API_ACTIONS"][$action] ?? null)) {
            $func = $GLOBALS["API_ACTIONS"][$action];
            return $func($request, $response, $args);
        }
        return $response;
    }
}
