<?php

namespace PHPMaker2024\FIMS;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Routing\RouteContext;
use Slim\Exception\HttpUnauthorizedException;
use PHPMaker2024\FIMS\Attributes\Delete;
use PHPMaker2024\FIMS\Attributes\Get;
use PHPMaker2024\FIMS\Attributes\Map;
use PHPMaker2024\FIMS\Attributes\Options;
use PHPMaker2024\FIMS\Attributes\Patch;
use PHPMaker2024\FIMS\Attributes\Post;
use PHPMaker2024\FIMS\Attributes\Put;

/**
 * Class others controller
 */
class OthersController extends ControllerBase
{
    // personaldata
    #[Map(["GET","POST","OPTIONS"], "/personaldata", [PermissionMiddleware::class], "personaldata")]
    public function personaldata(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PersonalData");
    }

    // login
    #[Map(["GET","POST","OPTIONS"], "/login[/{provider}]", [PermissionMiddleware::class], "login")]
    public function login(Request $request, Response $response, array $args): Response
    {
        global $Error;
        $Error = $this->container->get("app.flash")->getFirstMessage("error");
        return $this->runPage($request, $response, $args, "Login");
    }

    // resetpassword
    #[Map(["GET","POST","OPTIONS"], "/resetpassword", [PermissionMiddleware::class], "resetpassword")]
    public function resetpassword(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ResetPassword");
    }

    // changepassword
    #[Map(["GET","POST","OPTIONS"], "/changepassword", [PermissionMiddleware::class], "changepassword")]
    public function changepassword(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ChangePassword");
    }

    // userpriv
    #[Map(["GET","POST","OPTIONS"], "/userpriv", [PermissionMiddleware::class], "userpriv")]
    public function userpriv(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "Userpriv");
    }

    // logout
    #[Map(["GET","POST","OPTIONS"], "/logout", [PermissionMiddleware::class], "logout")]
    public function logout(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "Logout");
    }

    // Swagger
    #[Get("/swagger/swagger", [], "swagger")]
    public function swagger(Request $request, Response $response, array $args): Response
    {
        $basePath = GetBasePath($request);
        $lang = $this->container->get("app.language");
        $title = $lang->phrase("ApiTitle");
        if (!$title || $title == "ApiTitle") {
            $title = "REST API"; // Default
        }
        $data = [
            "title" => $title,
            "version" => Config("API_VERSION"),
            "basePath" => $basePath
        ];
        $view = $this->container->get("app.view");
        return $view->render($response, "swagger.php", $data);
    }

    // Index
    #[Get("/[index]", [PermissionMiddleware::class], "index")]
    public function index(Request $request, Response $response, array $args): Response
    {
        global $Security, $USER_LEVEL_TABLES;
        $url = "";
        foreach ($USER_LEVEL_TABLES as $t) {
            if ($t[0] == "MainDashboard.php") { // Check default table
                if ($Security->allowList($t[4] . $t[0])) {
                    $url = $t[5];
                    break;
                }
            } elseif ($url == "") {
                if ($t[5] && $Security->allowList($t[4] . $t[0])) {
                    $url = $t[5];
                }
            }
        }
        if ($url === "" && !$Security->isLoggedIn()) {
            $url = "login";
        }

        // Logged in but no accessible UAC page: send the user to the first
        // system they are assigned to (same lookup as Login::pageLoad) instead
        // of showing "Unauthorized".
        if ($url === "" && $Security->isLoggedIn() && !empty($_SESSION[SESSION_USER_ID])) {
            try {
                $sql = "SELECT DISTINCT s.system_code, s.url
                        FROM " . QuotedName("user_level_assignments", "DB") . " ula
                        INNER JOIN " . QuotedName("user_levels", "DB") . " ul ON ula.user_level_id = ul.user_level_id
                        INNER JOIN " . QuotedName("systems", "DB") . " s ON ul.system_id = s.system_id
                        WHERE ula.user_id = ? AND s.is_active = TRUE
                        ORDER BY s.system_code";
                $systems = Conn("DB")->executeQuery($sql, [$_SESSION[SESSION_USER_ID]])->fetchAllAssociative();
                if ($systems) {
                    $first = $systems[0];
                    $url = $first["system_code"] === "UAC"
                        ? "MainDashboard"
                        : ($first["url"] ?? "/" . $first["system_code"] . "/");
                }
            } catch (\Throwable $e) {
                // Fall through to the unauthorized response below
            }
        }
        if ($url == "") {
            throw new HttpUnauthorizedException($request, DeniedMessage());
        }
        return $response->withHeader("Location", $url)->withStatus(302);
    }
}
