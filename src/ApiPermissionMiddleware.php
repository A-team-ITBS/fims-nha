<?php

namespace PHPMaker2024\FIMS;

use Slim\Routing\RouteContext;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Illuminate\Support\Collection;

/**
 * Permission middleware
 */
class ApiPermissionMiddleware
{
    // Invoke
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        global $Security, $Language, $ResponseFactory;

        // Set up request
        $GLOBALS["Request"] = $request;

        // Create Response
        $response = $ResponseFactory->createResponse();
        $action = Route(0);
        $table = "";
        $checkToken = match ($action) {
            Config("API_SESSION_ACTION"), Config("API_EXPORT_CHART_ACTION"), Config("API_2FA_ACTION") => true,
            Config("API_JQUERY_UPLOAD_ACTION") => $request->isPost(),
            default => false,
        };

        // Validate JWT
        if ($checkToken) { // Check token
            $jwt = $request->getAttribute("JWT"); // Try get JWT from request attribute
            if ($jwt === null) {
                $token = preg_replace('/^Bearer\s+/', "", $request->getHeaderLine(Config("JWT.AUTH_HEADER"))); // Get bearer token from HTTP header
                if ($token) {
                    $jwt = DecodeJwt($token);
                }
            }
            if ((int)($jwt["userlevel"] ?? PHP_INT_MIN) < AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID) { // Invalid JWT token
                return $response->withStatus(401); // Not authorized
            }
        }

        // Actions for table
        $apiTableActions = [
            Config("API_EXPORT_ACTION"),
            Config("API_LIST_ACTION"),
            Config("API_VIEW_ACTION"),
            Config("API_ADD_ACTION"),
            Config("API_EDIT_ACTION"),
            Config("API_DELETE_ACTION"),
            Config("API_FILE_ACTION")
        ];
        if (in_array($action, $apiTableActions)) {
            $table = Route("table") ?? Param(Config("API_OBJECT_NAME")); // Get from route or Get/Post
        }

        // Language
        $Language = Container("app.language");

        // Security
        $Security = Container("app.security");

        // Default no permission
        $authorised = false;

        // Check permission
        if (
            $checkToken || // Token checked
            $action == Config("API_JQUERY_UPLOAD_ACTION") && $request->isGet() || // Get image during upload (GET)
            $action == Config("API_PERMISSIONS_ACTION") && $request->isGet() || // Permissions (GET)
            $action == Config("API_PERMISSIONS_ACTION") && $request->isPost() && $Security->isAdmin() || // Permissions (POST)
            $action == Config("API_UPLOAD_ACTION") && $Security->isLoggedIn() || // Upload
            $action == Config("API_REGISTER_ACTION") || // Register
            $action == Config("API_METADATA_ACTION") || // Metadata
            $action == Config("API_CHAT_ACTION") || // Chat
            in_array($action, array_keys($GLOBALS["API_ACTIONS"])) // Custom actions (deprecated)
        ) {
            $authorised = true;
        } elseif (in_array($action, $apiTableActions) && $table != "") { // Table actions
            $Security->loadTablePermissions($table);
            $authorised = $action == Config("API_LIST_ACTION") && $Security->canList() ||
                $action == Config("API_EXPORT_ACTION") && $Security->canExport() ||
                $action == Config("API_VIEW_ACTION") && $Security->canView() ||
                $action == Config("API_ADD_ACTION") && $Security->canAdd() ||
                $action == Config("API_EDIT_ACTION") && $Security->canEdit() ||
                $action == Config("API_DELETE_ACTION") && $Security->canDelete() ||
                $action == Config("API_FILE_ACTION") && ($Security->canList() || $Security->canView());
        } elseif ($action == Config("API_EXPORT_ACTION") && EmptyValue($table)) { // Get exported file
            $authorised = true; // Check table permission in ExportHandler.php
        } elseif ($action == Config("API_LOOKUP_ACTION")) { // Lookup
            $canLookup = function ($params) use ($Security) {
                $object = $params[Config("API_LOOKUP_PAGE")]; // Get lookup page
                $fieldName = $params[Config("API_FIELD_NAME")]; // Get field name
                $lookupField = Container($object)?->Fields[$fieldName] ?? null;
                if ($lookupField) {
                    $lookup = $lookupField->Lookup;
                    if ($lookup) {
                        $tbl = $lookup->getTable();
                        if ($tbl) {
                            $Security->loadTablePermissions($tbl->TableVar);
                            return $Security->canLookup();
                        }
                    }
                }
            };
            if ($request->getContentType() == "application/json") { // Multiple lookup requests in JSON
                $parsedBody = $request->getParsedBody();
                if (is_array($parsedBody)) {
                    $authorised = Collection::make($parsedBody)->contains($canLookup);
                }
            } else { // Single lookup request
                $authorised = $canLookup($request->getParams());
            }
        } elseif ($action == Config("API_PUSH_NOTIFICATION_ACTION")) { // Push notification
            $parm = Route("action");
            if ($parm == Config("API_PUSH_NOTIFICATION_SUBSCRIBE") || $parm == Config("API_PUSH_NOTIFICATION_DELETE")) {
                $authorised = Config("PUSH_ANONYMOUS") || $Security->isLoggedIn();
            } elseif ($parm == Config("API_PUSH_NOTIFICATION_SEND")) {
                $Security->loadTablePermissions(Config("SUBSCRIPTION_TABLE_NAME"));
                $authorised = $Security->canPush();
            }
        } elseif ($action == Config("API_2FA_ACTION")) { // Two factor authentication
            $parm = Route("action");
            if ($parm == Config("API_2FA_SHOW")) {
                $authorized = true;
            } elseif ($action == Config("API_2FA_VERIFY")) {
                $authorized = $Security->isLoggingIn2FA();
            } elseif ($action == Config("API_2FA_RESET")) {
                $authorized = $Security->isSysAdmin();
            } elseif ($action == Config("API_2FA_BACKUP_CODES") || $action == Config("API_2FA_NEW_BACKUP_CODES")) {
                $authorized = $Security->isLoggedIn() && !$Security->isSysAdmin();
            } elseif ($action == Config("API_2FA_SEND_OTP")) {
                $authorized = $Security->isLoggingIn2FA() && !$Security->isSysAdmin();
            }
        }
        if (!$authorised) {
            return $response->withStatus(401); // Not authorized
        }

        // Handle request
        return $handler->handle($request);
    }
}
