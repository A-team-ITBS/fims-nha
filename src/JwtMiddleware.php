<?php

namespace PHPMaker2024\FIMS;

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Routing\RouteContext;
use Slim\Exception\HttpNotFoundException;

/**
 * JWT middleware
 */
class JwtMiddleware implements MiddlewareInterface
{
    // Validate JWT token
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Set up request
        $GLOBALS["Request"] = $request;
        $route = GetRoute($request);

        // Return Not Found for non-existent route
        if (empty($route)) {
            throw new HttpNotFoundException($request);
        }

        // Handle login
        if ($route->getName() == "login") {
            return $this->create($request, $handler);
        }

        // Login user against default expiry time
        return $this->login($request, $handler);
    }

    // Create JWT token
    public function create(Request $request, RequestHandler $handler): Response
    {
        global $Security, $ResponseFactory;

        // Get response
        $response = $handler->handle($request);

        // Authorize
        $Security = Container("app.security");
        if ($Security->isLoggedIn()) {
            if ($request->isGet()) {
                $expire = $request->getQueryParam(Config("API_LOGIN_EXPIRE"));
                $permission = $request->getQueryParam(Config("API_LOGIN_PERMISSION"));
            } else {
                $expire = $request->getParsedBodyParam(Config("API_LOGIN_EXPIRE"));
                $permission = $request->getParsedBodyParam(Config("API_LOGIN_PERMISSION"));
            }
            $expire = ParseInteger($expire ?? 0); // Get expire time in hours
            $permission = ParseInteger($permission ?? 0); // Get allowed permission
            $minExpiry = $expire ? time() + $expire * 60 * 60 : 0;
            $jwt = $Security->createJwt($minExpiry, $permission);
            $response = $ResponseFactory->createResponse();
            return $response->withJson(["JWT" => $jwt]); // Return JWT token
        } elseif (StartsString("application/json", $response->getHeaderLine("Content-type") ?? "")) { // JSON error response
            return $response;
        } else {
            return $response->withStatus(401); // Not authorized
        }
    }

    // Login user
    private function login(Request $request, RequestHandler $handler): Response
    {
        global $Security, $ResponseFactory;

        // Set up security from HTTP header or cookie
        $Security = Container("app.security");
        $token = preg_replace('/^Bearer\s+/', "", $request->getHeaderLine(Config("JWT.AUTH_HEADER"))); // Get bearer token from HTTP header
        if ($token) {
            $jwt = DecodeJwt($token);
            if (is_array($jwt) && count($jwt) > 0) {
                if ((int)($jwt["userlevel"] ?? PHP_INT_MIN) >= AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID) { // Valid JWT token
                    $request->withAttribute("JWT", $jwt); // Add JWT to request attribute
                    $Security->loginUser(
                        $jwt["username"] ?? "",
                        $jwt["userid"] ?? null,
                        $jwt["parentuserid"] ?? null,
                        $jwt["userlevel"] ?? AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID,
                        $jwt["userprimarykey"] ?? null,
                        $jwt["permission"] ?? 0
                    ); // Login user
                } else { // JWT error
                    $response = $ResponseFactory->createResponse();
                    $json = array_merge($jwt, ["success" => false, "version" => PRODUCT_VERSION]);
                    return $response->withJson($json);
                }
            }
        }

        // Process request
        return $handler->handle($request);
    }
}
