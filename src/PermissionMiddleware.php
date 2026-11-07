<?php

namespace PHPMaker2024\FIMS;

use Slim\Routing\RouteContext;
use Slim\Exception\HttpBadRequestException;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Permission middleware
 */
class PermissionMiddleware
{
    // Invoke
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        global $Language, $Security;

        // Request
        $GLOBALS["Request"] = $request;
        $routeName = RouteName();
        $ar = explode(".", $routeName);
        $pageAction = $ar[0] ?? ""; // Page action
        $table = $ar[1] ?? ""; // Table

        // Page ID
        if (!defined(PROJECT_NAMESPACE . "PAGE_ID")) {
            define(PROJECT_NAMESPACE . "PAGE_ID", $pageAction);
        }

        // Language
        $Language = Container("app.language");

        // Security
        $Security = Container("app.security");

        // Current table
        if ($table != "") {
            $GLOBALS["Table"] = Container($table);
        }

        // Auto login
        if (
            !$Security->isLoggedIn() &&
            !IsPasswordReset() &&
            !IsPasswordExpired() &&
            !IsLoggingIn2FA() &&
            !IsRegistering() &&
            !IsRegistering2FA()
        ) {
            $Security->autoLogin();
        }

        // Check permission
        if ($table != "") { // Table level
            $Security->loadTablePermissions($table);
            if (
                $pageAction == Config("VIEW_ACTION") && !$Security->canView() ||
                in_array($pageAction, [Config("EDIT_ACTION"), Config("UPDATE_ACTION")]) && !$Security->canEdit() ||
                $pageAction == Config("ADD_ACTION") && !$Security->canAdd() ||
                $pageAction == Config("DELETE_ACTION") && !$Security->canDelete() ||
                in_array($pageAction, [Config("SEARCH_ACTION"), Config("QUERY_ACTION")]) && !$Security->canSearch()
            ) {
                $_SESSION[SESSION_FAILURE_MESSAGE] = DeniedMessage(); // Set no permission
                if ($Security->canList()) { // Back to list
                    $pageAction = Config("LIST_ACTION");
                    $routeUrl = $GLOBALS["Table"]->getListUrl();
                    return $this->redirect($table . "." . $pageAction);
                } else {
                    return $this->redirect();
                }
            } elseif (
                $pageAction == Config("LIST_ACTION") && !$Security->canList() || // List Permission
                in_array($pageAction, [
                    Config("CUSTOM_REPORT_ACTION"),
                    Config("SUMMARY_REPORT_ACTION"),
                    Config("CROSSTAB_REPORT_ACTION"),
                    Config("DASHBOARD_REPORT_ACTION"),
                    Config("CALENDAR_REPORT_ACTION")
                ]) && !$Security->canList()
            ) { // No permission
                $_SESSION[SESSION_FAILURE_MESSAGE] = DeniedMessage(); // Set no permission
                return $this->redirect();
            }
        } else { // Others
            if ($pageAction == "changepassword") { // Change password
                if (!IsPasswordReset() && !IsPasswordExpired()) {
                    if (!$Security->isLoggedIn() || $Security->isSysAdmin()) {
                        return $this->redirect();
                    }
                }
            } elseif ($pageAction == "personaldata") { // Personal data
                if (!$Security->isLoggedIn() || $Security->isSysAdmin()) {
                    $_SESSION[SESSION_FAILURE_MESSAGE] = DeniedMessage(); // Set no permission
                    return $this->redirect();
                }
            } elseif ($pageAction == "userpriv") { // User priv
                $table = "_user_levels";
                $pageAction = Config("LIST_ACTION");
                $routeUrl = Container($table)->getListUrl();
                $Security->loadTablePermissions($table);
                if (!$Security->isLoggedIn() || !$Security->isAdmin()) {
                    $_SESSION[SESSION_FAILURE_MESSAGE] = DeniedMessage(); // Set no permission
                    return $this->redirect($table . "." . $pageAction);
                }
            }
        }

        // Validate CSRF
        if (Config("CHECK_TOKEN") && !IsSamlResponse() && !ValidateCsrf($request)) {
            throw new HttpBadRequestException($request, $Language->phrase("InvalidPostRequest"));
        }

        // Handle request
        return $handler->handle($request);
    }

    // Redirect
    public function redirect(string $routeName = "login")
    {
        global $Request, $ResponseFactory, $Security;
        $response = $ResponseFactory->createResponse(); // Create response
        $GLOBALS["Response"] = &$response; // Note: global $Response does not work
        if ($routeName == "login") {
            $Security->saveLastUrl(); // Save last URL for redirection after login
        }
        if (
            $Request->getQueryParam("modal") == "1" && // Modal
            !($routeName == "login" && Config("USE_MODAL_LOGIN")) // Not modal login
        ) {
            return $response->withJson(["url" => UrlFor($routeName)]);
        }
        return $response->withHeader("Location", UrlFor($routeName))->withStatus(302);
    }
}
