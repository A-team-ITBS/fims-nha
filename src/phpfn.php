<?php

/**
 * PHPMaker 2024 functions
 * Copyright (c) e.World Technology Limited. All rights reserved.
*/

namespace PHPMaker2024\FIMS;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use FastRoute\RouteParser\Std;
use Slim\Csrf\Guard;
use Slim\Routing\RouteContext;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\ParameterType;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use DiDom\Document;
use DiDom\Element;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Collection;
use Illuminate\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Contracts\Encryption\DecryptException;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Hautelook\Phpass\PasswordHash;
use PHPMailer\PHPMailer\PHPMailer;

use Detection\MobileDetect;

// Custom types
Type::addType("timetz", "Doctrine\\DBAL\\Types\\VarDateTimeType"); // "timetz" type
Type::addType("geometry", "PHPMaker2024\\FIMS\\GeometryType"); // "geometry" type
Type::addType("geography", "PHPMaker2024\\FIMS\\GeographyType"); // "geography" type
Type::addType("hierarchyid", "PHPMaker2024\\FIMS\\HierarchyIdType"); // "hierarchyid" type
Type::addType("bytes", "PHPMaker2024\\FIMS\\BytesType"); // "bytes" type

/**
 * Get environment variable
 *
 * @param string $name Name
 * @param mixed $def Default value
 * @return mixed
 */
function Env(string $name, $def = null)
{
    return $_ENV[$name] ?? $def;
}

/**
 * Get/Set Configuration
 *
 * @return mixed
 */
function Config(...$args)
{
    global $ConfigData;
    $numargs = count($args);
    if ($numargs == 1) { // Get
        $name = $args[0];
        // Check global variables
        if (isset($GLOBALS[$name])) { // Allow overriding by global variable
            return $GLOBALS[$name];
        }
        // Check environment variables
        if (isset($_ENV[$name])) { // Allow overriding by environment variables
            return $_ENV[$name];
        }
        // Check config data
        if ($ConfigData->has($name)) {
            return $ConfigData->get($name);
        }
        // Check constants
        if (defined(PROJECT_NAMESPACE . $name)) {
            return constant(PROJECT_NAMESPACE . $name);
        }
        throw new \Exception("Undefined index: " . $name . " in configuration.");
    } elseif ($numargs == 2) { // Set
        list($name, $newValue) = $args;
        $oldValue = $ConfigData->get($name);
        if (is_array($oldValue) && is_array($newValue)) {
            $ConfigData->set($name, array_replace_recursive($oldValue, $newValue));
        } else {
            $ConfigData->set($name, $newValue);
        }
    }
    return $ConfigData;
}

/**
 * Event dispatcher
 *
 * @return void
 */
function EventDispatcher()
{
    return $GLOBALS["EventDispatcher"];
}

/**
 * Add event listener
 *
 * @param string $eventName Event name
 * @param callable|array $listener Listener
 * @param int $priority Priority
 * @return void
 */
function AddListener(string $eventName, callable|array $listener, int $priority = 0)
{
    $GLOBALS["EventDispatcher"]->addListener($eventName, $listener, $priority);
}

/**
 * Dispatch event
 *
 * @param Event $event Event
 * @param string $eventName Event name
 * @return Event
 */
function DispatchEvent(object $event, string $eventName = null): object
{
    return $GLOBALS["EventDispatcher"]->dispatch($event, $eventName);
}

/**
 * Is development
 *
 * @return bool
 */
function IsDevelopment()
{
    return Config("ENVIRONMENT") == "development";
}

/**
 * Is production
 *
 * @return bool
 */
function IsProduction()
{
    return Config("ENVIRONMENT") == "production";
}

/**
 * Is debug mode
 *
 * @return bool
 */
function IsDebug()
{
    return Config("DEBUG");
}

/**
 * Get request object
 *
 * @return \Slim\Http\ServerRequest
 */
function Request()
{
    return $GLOBALS["Request"];
}

/**
 * Get response object (for API only)
 *
 * @return \Slim\Http\Response
 */
function Response()
{
    return $GLOBALS["Response"];
}

/**
 * Get Container
 *
 * @return Psr\Container\ContainerInterface
 */
function Container(...$args)
{
    global $container;
    if (!$container) {
        return null;
    }
    $numargs = count($args);
    if ($numargs == 1) { // Get
        if (is_string($args[0])) {
            $name = $args[0];
            if ($container->has($name)) {
                return $container->get($name);
            } else {
                $class = PROJECT_NAMESPACE . $name;
                if (class_exists($class)) {
                    $obj = new $class();
                    $container->set($name, $obj);
                    return $obj;
                }
            }
        } elseif (is_array($args[0])) {
            foreach ($args[0] as $key => $value) {
                $container->set($key, $value);
            }
        }
        return null;
    } elseif ($numargs == 2) { // Set
        $container->set($args[0], $args[1]);
    }
    return $container;
}

/**
 * Slim app
 *
 * @return Slim\App
 */
function App()
{
    return $GLOBALS["app"];
}

/**
 * Route collector
 *
 * @return Slim\Routing\RouteCollector
 */
function RouteCollector()
{
    return App()->getRouteCollector();
}

/**
 * Route context
 *
 * @param Request $request Request
 * @return ?RouteContext
 */
function RouteContext(Request $request = null): ?RouteContext
{
    try {
        return RouteContext::fromRequest($request ?? Request());
    } catch (\RuntimeException $e) { // Cannot create RouteContext before routing has been completed
        return null;
    }
}

/**
 * Route parser
 *
 * @param Request $request Request
 * @return ?RouteParserInterface
 */
function RouteParser(Request $request = null): ?RouteParserInterface
{
    return RouteContext($request)?->getRouteParser();
}

/**
 * Routing result
 *
 * @param Request $request Request
 * @return ?RoutingResults
 */
function RoutingResults(Request $request = null): ?RoutingResults
{
    return RouteContext($request)?->getRoutingResults();
}

/**
 * Get route
 *
 * @param Request $request Request
 * @return ?RouteInterface
 */
function GetRoute(Request $request = null): ?RouteInterface
{
    return RouteContext($request)?->getRoute();
}

/**
 * Get base path
 *
 * @param Request $request Request
 * @return ?string
 */
function GetBasePath(Request $request = null): ?string
{
    return RouteContext($request)?->getBasePath();
}

/**
 * Route name
 *
 * @param Request $request Request
 * @return ?string
 */
function RouteName(Request $request = null): ?string
{
    return GetRoute($request)?->getName();
}

/**
 * Get URL from route name
 *
 * @param ?string $routeName Route name
 * @param array $data Route data
 * @param array $queryParams Query parameters
 * @return string URL
 */
function UrlFor(?string $routeName, array $data = [], array $queryParams = []): string
{
    return $routeName ? RouteParser()->urlFor($routeName, $data, $queryParams) : "";
}

/**
 * Get relative URL from route name
 *
 * @param ?string $routeName Route name
 * @param array $data Route data
 * @param array $queryParams Query parameters
 * @return string URL
 */
function RelativeUrlFor(?string $routeName, array $data = [], array $queryParams = []): string
{
    return $routeName ? RouteParser()->relativeUrlFor($routeName, $data, $queryParams) : "";
}

/**
 * Get full URL from route name
 *
 * @param ?string $routeName Route name
 * @param array $data Route data
 * @param array $queryParams Query parameters
 * @return string URL
 */
function FullUrlFor(?string $routeName, array $data = [], array $queryParams = []): string
{
    global $Request;
    return $routeName ? RouteParser()->fullUrlFor($Request->getUri(), $routeName, $data, $queryParams) : "";
}

/**
 * Get path (no query parameters) from route name
 *
 * @param string $routeName Route name
 * @param array $data Route data
 * @param bool $withOptionalParameters Whether with optional parameters
 * @return string Path
 */
function PathFor(?string $routeName, array $data = [], $withOptionalParameters = true)
{
    if (!$routeName) {
        return "";
    }
    $route = RouteCollector()->getNamedRoute($routeName);
    if (!$route) {
        return "";
    }
    if ($withOptionalParameters) { // With optional parameters
        return rtrim(UrlFor($routeName, $data), "/"); // No trailing "/"
    } else { // Without optional parameters
        $pattern = $route->getPattern();
        $expressions = Container(Std::class)->parse($pattern);
        $expression = $expressions[0]; // The least specific expression (no optional parameters)
        $segments = [];
        $segmentName = "";
        foreach ($expression as $segment) {
            // Each $segment is either a string or an array (representing a placeholder)
            if (is_string($segment)) {
                $segments[] = $segment;
                continue;
            }
            // If no data element for this segment in the provided $data
            if (!array_key_exists($segment[0], $data)) {
                throw new \InvalidArgumentException("Missing data for URL segment: " . $segment[0]);
            }
            $segments[] = $data[$segment[0]];
        }
        return rtrim(implode("", $segments), "/"); // No trailing "/"
    }
}

/**
 * Get base path
 *
 * @return string
 */
function BasePath($withTrailingDelimiter = false)
{
    $scriptName = ServerVar("SCRIPT_NAME");
    $basePath = str_replace("\\", "/", dirname($scriptName));
    if (strlen($basePath) > 1) {
        return $withTrailingDelimiter ? IncludeTrailingDelimiter($basePath, false) : $basePath;
    }
    return $withTrailingDelimiter ? IncludeTrailingDelimiter($basePath, false) : ""; // Root folder "/"
}

/**
 * Get app URL (domain URL + base path)
 *
 * @return string
 */
function AppUrl($withTrailingDelimiter = false)
{
    return DomainUrl() . BasePath($withTrailingDelimiter);
}

/**
 * Redirect to URL
 *
 * @param string $url URL
 * @return \Slim\Http\Response
 */
function Redirect($url)
{
    global $Response, $ResponseFactory;
    $Response ??= $ResponseFactory->createResponse();
    return $Response = $Response->withHeader("Location", $url)->withStatus(Config("REDIRECT_STATUS_CODE"));
}

/**
 * Is API request
 *
 * @return bool
 */
function IsApi()
{
    return $GLOBALS["IsApi"] === true;
}

/**
 * Is JSON response (request accepts JSON)
 *
 * @return bool
 */
function IsJsonResponse()
{
    return IsApi() ||
        ConvertToBool(Param("json")) ||
        preg_match('/\bapplication\/json\b/', $GLOBALS["Request"]?->getHeaderLine("Accept") ?? "");
}

/**
 * Check if response is JSON
 *
 * @return bool
 */
function WithJsonResponse()
{
    global $Response;
    return StartsString("application/json", $Response?->getHeaderLine("Content-type") ?? "") && $Response?->getBody()->getSize();
}

/**
 * Create JWT token
 *
 * @param array $values Values to be encoded
 * @param int $expiry Expiry time (seconds)
 * @return string JWT token
 */
function CreateJwt(array $values, int $expiry = 0)
{
    $tokenId = base64_encode(NewGuid());
    $issuedAt = time();
    $notBefore = $issuedAt + Config("JWT.NOT_BEFORE_TIME"); // Adding not before time (seconds)
    $defExpiry = $notBefore + Config("JWT.EXPIRE_TIME"); // Adding expire time (seconds), default expiry time
    $serverName = ServerVar("SERVER_NAME");

    // Override default expiry time
    if ($expiry > 0) {
        $notBefore = $issuedAt;
        $exp = $notBefore + $expiry;
    } else {
        $exp = $defExpiry;
    }

    // Create the token as an array
    return \Firebase\JWT\JWT::encode(
        [
            "iat" => $issuedAt, // Issued at: time when the token was generated
            "jti" => $tokenId, // Json Token Id: a unique identifier for the token
            "iss" => $serverName, // Issuer
            "nbf" => $notBefore, // Not before
            "exp" => $exp, // Expire
            "values" => $values
        ], // Data to be encoded in the JWT
        Config("JWT.SECRET_KEY"), // The signing key
        Config("JWT.ALGORITHM") // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
    );
}

/**
 * Decode JWT token
 *
 * @param string $token JWT token
 * @return array
 */
function DecodeJwt(string $token)
{
    try {
        $payload = (array)\Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(Config("JWT.SECRET_KEY"), Config("JWT.ALGORITHM")));
        return (array)$payload["values"];
    } catch (\Exception $e) {
        if (Config("DEBUG")) {
            return ["failureMessage" => $e->getMessage()];
        }
    }
}

/**
 * Get JWT token
 *
 * @return string JWT token
 */
function GetJwtToken()
{
    global $Security;
    $expiry = time() + max(Config("SESSION_TIMEOUT") * 60, Config("JWT.EXPIRE_TIME"), ini_get("session.gc_maxlifetime"));
    return $Security?->createJwt($expiry)
        ?? CreateJwt([
            "username" => "",
            "userid" => null,
            "parentuserid" => null,
            "userlevel" => AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID,
            "userprimarykey" => null,
            "permission" => 0
        ], $expiry);
}

/**
 * Use Session
 *
 * @param Request $request Request
 * @return bool
 */
function UseSession($request)
{
    $prefix = Config("CSRF_PREFIX");
    if (
        !Collection::make($request->getParsedBody() ?? [])->keys()->contains(fn ($v) => StartsString($prefix, $v)) &&
        !Collection::make($request->getQueryParams() ?? [])->keys()->contains(fn ($v) => StartsString($prefix, $v)) &&
        !Collection::make($request->getHeaders() ?? [])->keys()->contains(fn ($v) => StartsString(HeaderCase($prefix), HeaderCase($v))) &&
        !$request->hasHeader(Config("JWT.AUTH_HEADER"))
    ) {
        return false;
    }
    $action = Route(0);
    return !in_array($action, Config("SESSIONLESS_API_ACTIONS")) || $request->getQueryParam("session") !== null; // For file URLs
}

/**
 * Get request method
 *
 * @return string Request method
 */
function RequestMethod()
{
    return $GLOBALS["Request"]?->getMethod() ?? ServerVar("REQUEST_METHOD");
}

/**
 * Is GET request
 *
 * @return bool
 */
function IsGet()
{
    return SameText(RequestMethod(), "GET");
}

/**
 * Is POST request
 *
 * @return bool
 */
function IsPost()
{
    return SameText(RequestMethod(), "POST");
}

/**
 * Is OPTIONS request
 *
 * @return bool
 */
function IsOptions()
{
    return SameText(RequestMethod(), "OPTIONS");
}

/**
 * Get querystring data
 *
 * @param string $name Name of parameter
 * @param mixed $default Default value if name not found
 * @return string
*/
function Get($name, $default = null)
{
    return $GLOBALS["Request"]?->getQueryParam($name, $default) ?? $_GET[$name] ?? $default;
}

/**
 * Get post data
 *
 * @param string $name Name of paramter
 * @param mixed $default Default value if name not found
 * @return string
*/
function Post($name, $default = null)
{
    return $GLOBALS["Request"]?->getParsedBodyParam($name, $default) ?? $_POST[$name] ?? $default;
}

/**
 * Get post/querystring data
 *
 * @param string $name Name of paramter
 * @param mixed $default Default value if name not found
 * @return string
*/
function Param($name, $default = null)
{
    return $GLOBALS["Request"]?->getParam($name, $default) ?? $_POST[$name] ?? $_GET[$name] ?? $default;
}

/**
 * Get key data from Param("key")
 *
 * @param int $i The nth (0-based) key
 * @return string|null
 */
function Key($i = 0)
{
    $key = Param(Config("API_KEY_NAME"));
    if ($key !== null) {
        $keys = explode(Config("COMPOSITE_KEY_SEPARATOR"), $key);
        return $keys[$i] ?? null;
    }
    return null;
}

/**
 * Is SAML response
 *
 * @return bool
 */
function IsSamlResponse()
{
    return Param("SAMLResponse") !== null;
}

/**
 * Get route data
 *
 * @param int|string $idx The nth (0-based) route value or argument name
 * @return string|string[]|null
 */
function Route($idx = null)
{
    global $RouteValues;
    if ($RouteValues === null) { // Set up route values
        $routeContext = RouteContext();
        if ($routeContext != null) { // Routing has been completed
            $basePath = $routeContext?->getBasePath() ?? "";
            $routingResults = $routeContext?->getRoutingResults();
            $args = $routingResults?->getRouteArguments() ?? [];
            $uri = preg_replace("/^" . preg_quote($basePath, "/") . "/", "", $routingResults?->getUri() ?? "");
            $RouteValues = array_merge(explode("/", ltrim($uri, "/")), $args);
        } else { // Cannot create RouteContext before routing has been completed
            if (is_int($idx)) {
                $uri = ServerVar("REQUEST_URI"); // e.g. /basepath/api/file
                $basePath = BasePath(true); // e.g. /basepath/api/
                $uri = preg_replace("/^" . preg_quote($basePath, "/")  . "/", "", $uri);
                return explode("/", ltrim($uri, "/"))[$idx] ?? null;
            }
            return null;
        }
    }
    if ($idx === null) { // Get all route values as array
        return $RouteValues;
    } else { // Get route value by name or index
        $value = $RouteValues[$idx] ?? null;
        if (IsApi()) { // Special handling for API
            // Get record key separated by key separator (for API /file/{table}/{param}[/{key:.*}])
            if (
                $idx == "key"
                && ($RouteValues[0] ?? "") == "file"
                && ContainsString($value, "/")
            ) {
                $value = implode(Config("COMPOSITE_KEY_SEPARATOR"), explode("/", $value));
            // Composite key (for API /(view|edit|delete)/{table}[/{params:.*}])
            } elseif (
                is_int($idx) && $idx >= 2
                && in_array($RouteValues[0] ?? "", ["view", "edit", "delete"])
                && ContainsString($RouteValues["params"], Config("COMPOSITE_KEY_SEPARATOR"))
            ) {
                $keys = explode(Config("COMPOSITE_KEY_SEPARATOR"), $RouteValues["params"]);
                return $keys[$idx - 2] ?? null; // For Route(i + 2) where i is 0-based index of keyFields
            // Composite key (for API /export/{param}[/{table}[/{key:.*}]])
            } elseif (
                is_int($idx) && $idx >= 3
                && ($RouteValues[0] ?? "") == "export"
                && ContainsString($RouteValues["key"] ?? "", Config("COMPOSITE_KEY_SEPARATOR"))
            ) {
                $keys = explode(Config("COMPOSITE_KEY_SEPARATOR"), $RouteValues["key"]);
                return $keys[$idx - 3] ?? null; // For Route(i + 3) where i is 0-based index of keyFields
            }
        }
        return $value;
    }
}

/**
 * Write data to response
 *
 * @param mixed $data Data being outputted
 * @return void
 */
function Write($data)
{
    global $Page, $Response;
    if (is_object($Response) && !($Page?->RenderingView)) {
        $Response->getBody()->write($data);
    } else {
        echo $data;
    }
}

/**
 * Set HTTP response status code
 *
 * @param int $code Response status code
 * @return void
 */
function SetStatus($code)
{
    global $Response;
    if (is_object($Response)) {
        $Response = $Response->withStatus($code);
    } else {
        http_response_code($code);
    }
}

/**
 * Output JSON data (UTF-8)
 *
 * @param mixed $data Data to be encoded and outputted (non UTF-8)
 * @param int $encodingOptions optional JSON encoding options (same as that of json_encode())
 * @return void
 */
function WriteJson($data, $encodingOptions = 0)
{
    global $Response;
    $ar = IsApi() ? ["version" => PRODUCT_VERSION] : []; // If API, output as object
    if (is_array($data) && !array_is_list($data) && count($data) > 0) { // Associative array
        $data = array_merge($data, $ar);
    }
    $json = json_encode(ConvertToUtf8($data), $encodingOptions);
    if ($json === false) {
        $json = json_encode(["json_encode_error" => json_last_error()], $encodingOptions);
    }
    if (is_object($Response)) {
        $Response->getBody()->write($json);
        $Response = $Response->withHeader("Content-Type", "application/json; charset=utf-8");
    } else {
        if (!Config("DEBUG") && ob_get_length()) {
            ob_end_clean();
        }
        header("Content-Type: application/json; charset=utf-8");
        echo $json;
    }
}

/**
 * Output XML
 *
 * @param mixed $data XML to be outputted
 * @return void
 */
function WriteXml($data)
{
    global $Response;
    if (is_object($Response)) {
        $Response->getBody()->write($data);
        $Response = $Response->withHeader("Content-Type", "application/xml");
    } else {
        if (!Config("DEBUG") && ob_get_length()) {
            ob_end_clean();
        }
        header("Content-Type: application/xml");
        echo $data;
    }
}

/**
 * Add header
 *
 * @param string $name Header name
 * @param string $value Header value
 * @param bool $replace optional Replace a previous similar header, or add a second header of the same type. Default is true.
 * @return void
 */
function AddHeader($name, $value, $replace = true)
{
    global $Response;
    if (is_object($Response)) {
        if ($replace) { // Replace
            $Response = $Response->withHeader($name, $value);
        } else { // Append
            $Response = $Response->withAddedHeader($name, $value);
        }
    } else {
        header($name . ": " . $value, $replace);
    }
}

/**
 * Remove header
 *
 * @param string $name Header name to be removed
 * @return void
 */
function RemoveHeader($name)
{
    global $Response;
    if (is_object($Response)) {
        $Response = $Response->withoutHeader($name);
    } else {
        header_remove($name);
    }
}

/**
 * Read cookie from request
 *
 * @param string $name Cookie name
 * @return string
 */
function ReadCookie($name)
{
    global $Request;
    return FigRequestCookies::get($Request, PROJECT_NAME . "_" . $name)->getValue();
}

/**
 * User has given consent to track cookie
 *
 * @return bool
 */
function CanTrackCookie()
{
    return ReadCookie(Config("COOKIE_CONSENT_NAME")) == "1";
}

/**
 * Set cookie to response
 *
 * @param string $name Cookie name
 * @param string $value Cookie value
 * @param int $expiry optional Cookie expiry time
 * @param bool $httpOnly optional HTTP only
 * @return Cookie
 */
function SetCookie($name, $value, $expiry = -1, $httpOnly = null)
{
    global $Response;
    $expiry = ($expiry > -1) ? $expiry : Config("COOKIE_EXPIRY_TIME");
    $setCookie = SetCookie::create(PROJECT_NAME . "_" . $name, $value)
        ->withPath(Config("COOKIE_PATH"))
        ->withExpires(gmdate("D, d-M-Y H:i:s T", $expiry))
        ->withSameSite(SameSite::fromString(Config("COOKIE_SAMESITE")))
        ->withHttpOnly($httpOnly ?? Config("COOKIE_HTTP_ONLY"))
        ->withSecure(Config("COOKIE_SAMESITE") == "None" || IsHttps() && Config("COOKIE_SECURE"));
    $Response = FigResponseCookies::set($Response, $setCookie);
    return $setCookie;
}

/**
 * Remove/Expire response cookie
 *
 * @param string $name Cookie name
 * @return string
 */
function RemoveCookie($name)
{
    global $Response;
    $Response = FigResponseCookies::set($Response, SetCookie::create(PROJECT_NAME . "_" . $name, "")->withPath(Config("COOKIE_PATH"))->expire());
}

/**
 * Create consent cookie
 *
 * @return string
 */
function CreateConsentCookie()
{
    return (string) SetCookie::create(PROJECT_NAME . "_" . Config("COOKIE_CONSENT_NAME"), 1)
        ->withPath(Config("COOKIE_PATH"))
        ->withExpires(gmdate("D, d-M-Y H:i:s T", Config("COOKIE_EXPIRY_TIME")))
        ->withSameSite(SameSite::fromString(Config("COOKIE_SAMESITE")))
        ->withHttpOnly(false) // httpOnly must be false
        ->withSecure(Config("COOKIE_SAMESITE") == "None" || IsHttps() && Config("COOKIE_SECURE"));
}

/**
 * Write cookie to response
 *
 * @param string $name Cookie name
 * @param string $value Cookie value
 * @param int $expiry optional Cookie expiry time. Default is Config("COOKIE_EXPIRY_TIME")
 * @param bool $essential optional Essential cookie, set even without user consent. Default is true.
 * @param bool $httpOnly optional HTTP only. Default is false.
 * @return void
 */
function WriteCookie($name, $value, $expiry = -1, $essential = true, $httpOnly = false)
{
    if ($essential || CanTrackCookie()) {
        SetCookie($name, $value, $expiry, $httpOnly);
    }
}

/**
 * Send event
 *
 * @param string $type Type of event
 * @param string $data Data of event
 * @return void
 */
function SendEvent($data, $type = "message")
{
    echo "event: " . $type . "\n",
        "data: " . (is_array($data) ? json_encode($data) : $data),
        "\n\n";

    // Flush the output buffer and send echoed messages to the browser
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

/**
 * Get page object
 *
 * @param string $name Page name or table name
 * @return object
 */
function &Page($name = "")
{
    $res = $name ? $GLOBALS[$name] ?? null : $GLOBALS["Page"] ?? null;
    $res = is_object($res) ? $res : null;
    return $res;
}

/**
 * Get current language ID
 *
 * @return string
 */
function CurrentLanguageID()
{
    return $GLOBALS["CurrentLanguage"] ?? "";
}

/**
 * Is RTL language
 *
 * @return bool
 */
function IsRTL()
{
    global $CurrentLanguage;
    $lang = explode("-", str_replace("_", "-", $CurrentLanguage))[0];
    return in_array($lang, Config("RTL_LANGUAGES"));
}

// Get current project ID
function CurrentProjectID()
{
    return $GLOBALS["Page"]?->ProjectID ?? PROJECT_ID;
}

/**
 * Get current page object
 *
 * @return object
 */
function &CurrentPage()
{
    return $GLOBALS["Page"];
}

/**
 * Get user table object
 *
 * @return object
 */
function &UserTable()
{
    return $GLOBALS["UserTable"];
}

/**
 * Get current main table object
 *
 * @return object
 */
function &CurrentTable()
{
    return $GLOBALS["Table"];
}

/**
 * Get current main table name
 *
 * @return string
 */
function CurrentTableName()
{
    $tbl = &CurrentTable();
    return $tbl?->TableName ?? "";
}

/**
 * Get user table object
 *
 * @param string $tblVar Table Var
 * @return string
 */
function GetTableName($tblVar)
{
    global $USER_LEVEL_TABLES;
    $table = Collection::make($USER_LEVEL_TABLES)->first(fn ($tbl) => $tbl[1] == $tblVar);
    return $table[0] ?? $tblVar; // Return table name if found
}

/**
 * Get current master table object
 *
 * @return object
 */
function &CurrentMasterTable()
{
    $tbl = &CurrentTable();
    if ($tbl != null && method_exists($tbl, "getCurrentMasterTable") && $masterTbl = $tbl->getCurrentMasterTable()) {
        $GLOBALS[$masterTbl] ??= Container($masterTbl);
        return $GLOBALS[$masterTbl];
    }
    return $GLOBALS["NullValue"];
}

/**
 * Get current detail table object
 *
 * @return object
 */
function &CurrentDetailTable()
{
    return $GLOBALS["Grid"];
}

/**
 * Get foreign key URL
 *
 * @param string $name Key name
 * @param string $val Key value
 * @param mixed $dateFormat Date format
 * @return string
 */
function GetForeignKeyUrl($name, $val, $dateFormat = null)
{
    $url = $name . "=";
    if ($val === null) {
        $val = Config("NULL_VALUE");
    } elseif ($val === "") {
        $val = Config("EMPTY_VALUE");
    } elseif (is_numeric($dateFormat)) {
        $val = UnFormatDateTime($val, $dateFormat);
    }
    return $url . urlencode($val);
}

/**
 * Get filter for a primary/foreign key field
 *
 * @param DbField $fld Field object
 * @param string $val Value
 * @param string $dataType DATATYPE_* of value
 * @param string $dbid Database ID
 * @return string Filter (<Field> <Opr> <Value>)
 */
function GetKeyFilter($fld, $val, $dataType, $dbid)
{
    $expression = $fld->Expression;
    if ($val == Config("NULL_VALUE")) {
        return $expression . " IS NULL";
    } elseif ($val == Config("EMPTY_VALUE")) {
        $val = "";
    }
    $dbtype = GetConnectionType($dbid);
    if ($fld->DataType == DataType::NUMBER && ($dataType == DataType::STRING || $dataType == DataType::MEMO)) { // Find field value (number) in input value (string)
        if ($dbtype == "MYSQL") { // MySQL, use FIND_IN_SET(expr, val)
            $fldOpr = "FIND_IN_SET";
        } else { // Other database type, use expr IN (val)
            $fldOpr = "IN";
            $val = str_replace(Config("MULTIPLE_OPTION_SEPARATOR"), Config("IN_OPERATOR_VALUE_SEPARATOR"), $val);
        }
        return SearchFilter($expression, $fldOpr, $val, $dataType, $dbid);
    } elseif (($fld->DataType == DataType::STRING || $fld->DataType == DataType::MEMO) && $dataType == DataType::NUMBER) { // Find input value (number) in field value (string)
        return GetMultiValueFilter($expression, $val, $dbid);
    } else { // Assume same data type
        return SearchFilter($expression, "=", $val, $dataType, $dbid);
    }
}

/**
 * Search field for multi-value
 *
 * @param string $expression Search expression
 * @param string $val Value
 * @param string $dbid Database ID
 * @param string $opr Operator
 * @return string Filter (<Field> <Opr> <Value>)
 */
function GetMultiValueFilter($expression, $val, $dbid, $opr = "=")
{
    $dbtype = GetConnectionType($dbid);
    $ar = is_array($val) ? $val : [$val];
    $parts = array_map(
        fn($v) => $dbtype == "MYSQL"
            ? ($opr == "=" ? "" : "NOT ") . "FIND_IN_SET('" . AdjustSql($v, $dbid) . "', " . $expression . ")" // MySQL, use FIND_IN_SET(val, expr)
            : GetMultiSearchSqlFilter($expression, $opr, $v, $dbid, Config("MULTIPLE_OPTION_SEPARATOR")), // Other database type, use (expr = 'val' OR expr LIKE 'val,%' OR expr LIKE '%,val,%' OR expr LIKE '%,val')
        $ar
    );
    return implode($opr == "=" ? " OR " : " AND ", $parts);
}

/**
 * Get foreign key value
 *
 * @param string $val Key value
 * @return string
 */
function GetForeignKeyValue($val)
{
    if ($val == Config("NULL_VALUE")) {
        return null;
    } elseif ($val == Config("EMPTY_VALUE")) {
        return "";
    }
    return $val;
}

/**
 * Validate CSRF Token
 * Also see https://github.com/slimphp/Slim-Csrf/blob/1.x/src/Guard.php
 *
 * @param object $request Request
 * @return bool
 */
function ValidateCsrf($request)
{
    global $TokenNameKey, $TokenName, $TokenValueKey, $TokenValue;
    $csrf = Container("app.csrf");
    $TokenNameKey = $csrf->getTokenNameKey();
    $TokenValueKey = $csrf->getTokenValueKey();
    $TokenName = Param($TokenNameKey) ?? $request->getHeader($TokenNameKey)[0] ?? $request->getHeader(HeaderCase($TokenNameKey))[0] ?? null;
    $TokenValue = Param($TokenValueKey) ?? $request->getHeader($TokenValueKey)[0] ?? $request->getHeader(HeaderCase($TokenValueKey))[0] ?? null;
    if (in_array($request->getMethod(), ["POST", "PUT", "DELETE", "PATCH"])) {
        return !empty($TokenName) && !empty($TokenValue) ? $csrf->validateToken($TokenName, $TokenValue) : false;
    } else { // GET/OPTIONS/HEAD/etc, do not accept token in the body of request
        if (Post($TokenNameKey) !== null) {
            return false;
        }
    }
    return true;
}

/**
 * Generate CSRF Token
 *
 * @return string
 */
function GenerateCsrf()
{
    global $csrf, $TokenNameKey, $TokenName, $TokenValueKey, $TokenValue;
    $csrf = Container("app.csrf");
    $token = $csrf->generateToken();
    $TokenNameKey = $csrf->getTokenNameKey();
    $TokenValueKey = $csrf->getTokenValueKey();
    $TokenName = $csrf->getTokenName();
    $TokenValue = $csrf->getTokenValue();
    return $token;
}

// Get file IMG tag (for export to email/PDF/HTML only)
function GetFileImgTag($fn, $class = "")
{
    if (!is_array($fn)) {
        $fn = $fn ? [$fn] : [];
    }
    $files = array_filter($fn);
    $files = array_map(fn($file) => ContainsString($file, ":\\") ? str_replace("\\", "/", $file) : $file, $files); // Replace '\' by '/' to avoid encoding issue
    $tags = array_map(fn($file) => '<img class="ew-image' . ($class ? ' ' . $class : '') . '" src="' . $file . '" alt="">', $files);
    return implode("<br>", $tags);
}

// Get file A tag
function GetFileATag($fld, $fn)
{
    $wrkfiles = [];
    $wrkpath = "";
    $html = "";
    if ($fld->DataType == DataType::BLOB) {
        if (!EmptyValue($fld->Upload->DbValue)) {
            $wrkfiles = [$fn];
        }
    } elseif ($fld->UploadMultiple) {
        $wrkfiles = explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $fn);
        $pos = strrpos($wrkfiles[0], '/');
        if ($pos !== false) {
            $wrkpath = substr($wrkfiles[0], 0, $pos + 1); // Get path from first file name
            $wrkfiles[0] = substr($wrkfiles[0], $pos + 1);
        }
    } else {
        if (!EmptyValue($fld->Upload->DbValue)) {
            $wrkfiles = [$fn];
        }
    }
    $elements = array_map(
        fn($wrkfile) => Element::create("a", attributes: ["href" => FullUrl($wrkpath . $wrkfile, "href")])->setInnerHtml($fld->caption()),
        array_filter($wrkfiles)
    );
    return implode("<br>", array_map(fn($el) => $el->toDocument()->format()->html(), $elements));
}

// Get file temp image
function GetFileTempImage($fld, $val)
{
    if ($fld->DataType == DataType::BLOB) {
        if (!EmptyValue($fld->Upload->DbValue)) {
            $tmpimage = $fld->Upload->DbValue;
            if ($fld->ImageResize) {
                ResizeBinary($tmpimage, $fld->ImageWidth, $fld->ImageHeight);
            }
            return TempImage($tmpimage);
        }
        return "";
    } else {
        $tmpimage = file_get_contents($fld->physicalUploadPath() . $val);
        if ($fld->ImageResize) {
            ResizeBinary($tmpimage, $fld->ImageWidth, $fld->ImageHeight);
        }
        return TempImage($tmpimage);
    }
}

// Get file image
function GetFileImage($fld, $val, $width = 0, $height = 0, $crop = false)
{
    $image = "";
    $file = "";
    if ($fld->DataType == DataType::BLOB) {
        $image = is_resource($val) ? stream_get_contents($val) : $val;
    } elseif ($fld->UploadMultiple) {
        $file = $fld->physicalUploadPath() . (explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $val ?? "")[0]);
    } else {
        $file = $fld->physicalUploadPath() . $val;
    }
    if (is_file($file)) {
        $image = file_get_contents($file);
    }
    if (!EmptyValue($image) && $width > 0) {
        $func = fn($phpthumb) => $phpthumb->adaptiveResize($width, $height);
        $plugins = $crop ? [$func] : [];
        ResizeBinary($image, $width, $height, $plugins);
        return $image;
    }
    return "";
}

// Get API action URL // PHP
function GetApiUrl($action, $query = "")
{
    return GetUrl(Config("API_URL") . $action) . ($query ? "?" : "") . $query;
}

/**
 * Get file upload URL
 *
 * @param DbField $fld Field object
 * @param mixed $val Field value
 * @param array $options optional {
 *  @var bool "resize" Resize the image
 *  @var bool "crop" Crop image
 *  @var bool "encrypt" Encrypt file path
 *  @var bool "urlencode" URL-encode file path
 * }
 * @return string URL
 */
function GetFileUploadUrl($fld, $val, $options = [])
{
    $opts = [
        "resize" => false,
        "crop" => false,
        "encrypt" => null,
        "urlencode" => true
    ];
    if (is_bool($options)) {
        $opts["resize"] = $options;
    } elseif (is_array($options)) {
        $opts = array_merge($opts, $options);
    }
    extract($opts);
    if (!EmptyString($val)) {
        $sessionId = session_id();
        $fileUrl = GetApiUrl(Config("API_FILE_ACTION")) . "/";
        if ($fld->DataType == DataType::BLOB) {
            $tableVar = !EmptyString($fld->SourceTableVar) ? $fld->SourceTableVar : $fld->TableVar;
            $fn = $fileUrl . rawurlencode($tableVar) . "/" . rawurlencode($fld->Param) . "/" . rawurlencode($val);
            if ($resize) {
                $fn .= "?resize=1&width=" . $fld->ImageWidth . "&height=" . $fld->ImageHeight . ($crop ? "&crop=1" : "");
            }
        } else {
            $encrypt ??= Config("ENCRYPT_FILE_PATH") || !EmptyString($fld->getS3Bucket());
            $path = ($encrypt || $resize) ? $fld->physicalUploadPath() : $fld->hrefPath();
            $key = $sessionId . Config("ENCRYPTION_KEY");
            if ($encrypt) {
                $fn = $fileUrl . $fld->TableVar . "/" . Encrypt($path . $val, $key);
                if ($resize) {
                    $fn .= "?width=" . $fld->ImageWidth . "&height=" . $fld->ImageHeight . ($crop ? "&crop=1" : "");
                }
            } elseif ($resize) {
                $fn = $fileUrl . $fld->TableVar . "/" . Encrypt($path . $val, $key) .
                    "?width=" . $fld->ImageWidth . "&height=" . $fld->ImageHeight . ($crop ? "&crop=1" : ""); // Encrypt the physical path
            } else {
                $fn = IsRemote($path) ? $path : UrlEncodeFilePath($path);
                $fn .= UrlEncodeFilePath($val, !IsRemote($path)); // S3 expects "+" in file name
                $fn = GetUrl($fn);
            }
        }
        $fn .= ContainsString($fn, "?") ? "&" : "?";
        $fn .= "session=" . Encrypt($sessionId) . "&" . $GLOBALS["TokenNameKey"] . "=" . $GLOBALS["TokenName"] . "&" . $GLOBALS["TokenValueKey"] . "=" . $GLOBALS["TokenValue"];
        return $fn;
    }
    return "";
}

/**
 * URL encode file path
 *
 * @param string $path File path
 * @param bool $raw Use rawurlencode() or else urlencode()
 * @return string
 */
function UrlEncodeFilePath($path, $raw = true)
{
    $ar = explode("/", $path);
    $scheme = parse_url($path, PHP_URL_SCHEME);
    foreach ($ar as &$c) {
        if ($c != $scheme . ":") {
            $c = $raw ? rawurlencode($c) : urlencode($c);
        }
    }
    return implode("/", $ar);
}

// Get file view tag
function GetFileViewTag(&$fld, $val, $tooltip = false)
{
    global $Page;
    if (!EmptyString($val)) {
        $val = $fld->htmlDecode($val);
        if ($fld->DataType == DataType::BLOB) {
            $wrknames = [$val];
            $wrkfiles = [$val];
        } elseif ($fld->UploadMultiple) {
            $wrknames = explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $val);
            $wrkfiles = explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $fld->htmlDecode($fld->Upload->DbValue));
        } else {
            $wrknames = [$val];
            $wrkfiles = [$fld->htmlDecode($fld->Upload->DbValue)];
        }
        $wrkfiles = array_filter($wrkfiles);
        $multiple = count($wrkfiles) > 1;
        $href = $tooltip ? "" : $fld->HrefValue;
        $isLazy = $tooltip ? false : IsLazy();
        $tags = [];
        $wrkcnt = 0;
        $showBase64Image = $Page?->isExport("html");
        $skipImage = $Page && ($Page->isExport("excel") && !Config("USE_PHPEXCEL") || $Page->isExport("word") && !Config("USE_PHPWORD"));
        $showTempImage = $Page && ($Page->TableType == "REPORT"
            && ($Page->isExport("excel") && Config("USE_PHPEXCEL")
            || $Page->isExport("word") && Config("USE_PHPWORD"))
            || $Page->TableType != "REPORT" && ($Page->Export == "pdf" || $Page->Export == "email"));
        foreach ($wrkfiles as $wrkfile) {
            $tag = "";
            if ($showTempImage) {
                $fn = GetFileTempImage($fld, $wrkfile);
            } elseif ($skipImage) {
                $fn = "";
            } else {
                $fn = GetFileUploadUrl($fld, $wrkfile, ["resize" => $fld->ImageResize]);
            }
            if ($fld->ViewTag == "IMAGE" && ($fld->IsBlobImage || IsImageFile($wrkfile))) { // Image
                $fld->ViewAttrs->appendClass($fld->ImageCssClass);
                if ($showBase64Image) {
                    $tag = GetFileImgTag(ImageFileToBase64Url(GetFileTempImage($fld, $wrkfile)));
                } else {
                    if ($isLazy) {
                        $fld->ViewAttrs->appendClass("ew-lazy");
                    }
                    if ($href == "" && !$fld->UseColorbox) {
                        if ($fn != "") {
                            if ($isLazy) {
                                $tag = '<img loading="lazy" alt="" src="data:image/png;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" data-src="' . $fn . '"' . $fld->viewAttributes() . '>';
                            } else {
                                $tag = '<img alt="" src="' . $fn . '"' . $fld->viewAttributes() . '>';
                            }
                        }
                    } else {
                        if ($fld->UploadMultiple && ContainsString($href, '%u')) {
                            $fld->HrefValue = str_replace('%u', GetFileUploadUrl($fld, $wrkfile), $href);
                        }
                        if ($fn != "") {
                            if ($isLazy) {
                                $tag = '<a' . $fld->linkAttributes() . '><img loading="lazy" alt="" src="data:image/png;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" data-src="' . $fn . '"' . $fld->viewAttributes() . '></a>';
                            } else {
                                $tag = '<a' . $fld->linkAttributes() . '><img alt="" src="' . $fn . '"' . $fld->viewAttributes() . '></a>';
                            }
                        }
                    }
                }
            } else { // Non image
                if ($fld->DataType == DataType::BLOB) {
                    $url = $href;
                    $name = ($fld->Upload->FileName != "") ? $fld->Upload->FileName : $fld->caption();
                    $ext = str_replace(".", "", ContentExtension($fld->Upload->DbValue));
                } else {
                    $url = GetFileUploadUrl($fld, $wrkfile);
                    $cnt = count($wrknames);
                    $name = $wrknames[$wrkcnt] ?? $wrknames[$cnt - 1];
                    $pathinfo = pathinfo($wrkfile);
                    $ext = strtolower($pathinfo["extension"] ?? "");
                }
                $isPdf = SameText($ext, "pdf");
                if ($url != "") {
                    $fld->LinkAttrs->removeClass("ew-lightbox"); // Remove colorbox class
                    unset($fld->LinkAttrs["title"]); // Remove title
                    if ($fld->UploadMultiple && ContainsString($href, "%u")) {
                        $fld->HrefValue = str_replace("%u", $url, $href);
                    }
                    $isEmbedPdf = $isPdf && Config("EMBED_PDF") && !($Page && $Page->isExport() && !$Page->isExport("print")); // Skip Embed PDF for export
                    if ($isEmbedPdf) {
                        $pdfFile = $fld->physicalUploadPath() . $wrkfile;
                        $tag = "<a" . $fld->linkAttributes() . ">" . $name . "</a>";
                        if ($fld->DataType == DataType::BLOB || IsRemote($pdfFile) || file_exists($pdfFile)) {
                            $tag = '<div class="ew-pdfobject" data-url="' . $url . '">' . $tag . '</div>';
                        }
                    } else {
                        if ($ext) {
                            $fld->LinkAttrs["data-extension"] = $ext;
                        }
                        $tag = "<a" . $fld->linkAttributes() . ">" . $name . "</a>";
                    }
                }
            }
            if ($tag != "") {
                $tags[] = $tag;
            }
            $wrkcnt += 1;
        }
        if ($multiple && count($tags) > 1) {
            return '<div class="d-flex flex-row ew-images">' . implode('', $tags) . '</div>';
        }
        return implode('', $tags);
    }
    return "";
}

// Get image view tag
function GetImageViewTag(&$fld, $val)
{
    if (!EmptyString($val)) {
        $href = $fld->HrefValue;
        $image = $val;
        if ($val && !ContainsString($val, "://") && !ContainsString($val, "\\") && !ContainsText($val, "javascript:")) {
            $fn = GetImageUrl($fld, $val, ["resize" => $fld->ImageResize]);
        } else {
            $fn = $val;
        }
        if (IsImageFile($val)) { // Image
            $fld->ViewAttrs->appendClass($fld->ImageCssClass);
            if (IsLazy()) {
                $fld->ViewAttrs->appendClass("ew-lazy");
            }
            if ($href == "" && !$fld->UseColorbox) {
                if ($fn != "") {
                    if (IsLazy()) {
                        $image = '<img loading="lazy" alt="" src="data:image/png;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" data-src="' . $fn . '"' . $fld->viewAttributes() . '>';
                    } else {
                        $image = '<img alt="" src="' . $fn . '"' . $fld->viewAttributes() . '>';
                    }
                }
            } else {
                if ($fn != "") {
                    if (IsLazy()) {
                        $image = '<a' . $fld->linkAttributes() . '><img loading="lazy" alt="" src="data:image/png;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" data-src="' . $fn . '"' . $fld->viewAttributes() . '></a>';
                    } else {
                        $image = '<a' . $fld->linkAttributes() . '><img alt="" src="' . $fn . '"' . $fld->viewAttributes() . '></a>';
                    }
                }
            }
        } else {
            $name = $val;
            if ($href != "") {
                $image = "<a" . $fld->linkAttributes() . ">" . $name . "</a>";
            } else {
                $image = $name;
            }
        }
        return $image;
    }
    return "";
}

/**
 * Get image URL
 *
 * @param DbField $fld Field object
 * @param mixed $val Field value
 * @param array $options optional {
 *  @var bool "resize" Resize the image
 *  @var bool "crop" Crop image
 *  @var bool "encrypt" Encrypt file path
 *  @var bool "urlencode" URL-encode file path
 * }
 * @return string URL
 */
function GetImageUrl($fld, $val, $options = [])
{
    $opts = [
        "resize" => false,
        "crop" => false,
        "encrypt" => null,
        "urlencode" => true
    ];
    if (is_bool($options)) {
        $opts["resize"] = $options;
    } elseif (is_array($options)) {
        $opts = array_merge($opts, $options);
    }
    extract($opts);
    if (!EmptyString($val)) {
        $sessionId = session_id();
        $key = $sessionId . Config("ENCRYPTION_KEY");
        $sessionQry = "session=" . Encrypt($sessionId) . "&" . $GLOBALS["TokenNameKey"] . "=" . $GLOBALS["TokenName"] . "&" . $GLOBALS["TokenValueKey"] . "=" . $GLOBALS["TokenValue"];
        $fileUrl = GetApiUrl(Config("API_FILE_ACTION")) . "/";
        $encrypt = ($encrypt === null) ? Config("ENCRYPT_FILE_PATH") : $encrypt;
        $path = ($encrypt || $resize) ? $fld->physicalUploadPath() : $fld->hrefPath();
        if ($encrypt) {
            $fn = $fileUrl . $fld->TableVar . "/" . Encrypt($path . $val, $key) . "?" . $sessionQry;
            if ($resize) {
                $fn .= "&width=" . $fld->ImageWidth . "&height=" . $fld->ImageHeight . ($crop ? "&crop=1" : "");
            }
        } elseif ($resize) {
            $fn = $fileUrl . $fld->TableVar . "/" . Encrypt($path . $val, $key) . "?" . $sessionQry .
                "&width=" . $fld->ImageWidth . "&height=" . $fld->ImageHeight . ($crop ? "&crop=1" : "");
        } else {
            $fn = $val;
            if ($urlencode) {
                $fn = UrlEncodeFilePath($fn);
            }
            $fn = GetUrl($fn);
        }
        return $fn;
    }
    return "";
}

// Check if image file
function IsImageFile($fn)
{
    if ($fn != "") {
        $ar = parse_url($fn);
        if ($ar && array_key_exists("query", $ar)) { // Thumbnail URL
            if ($q = parse_str($ar["query"])) {
                $fn = $q["fn"];
            }
        }
        $pathinfo = pathinfo($fn);
        $ext = strtolower($pathinfo["extension"] ?? "");
        return in_array($ext, explode(",", Config("IMAGE_ALLOWED_FILE_EXT")));
    }
    return false;
}

// Check if lazy loading images
function IsLazy()
{
    global $ExportType;
    return Config("LAZY_LOAD") && ($ExportType == "" || $ExportType == "print");
}

// Write HTTP header
function WriteHeader($cache)
{
    global $Response;
    $export = Get("export");
    $cacheProvider = Container("app.cache");
    if ($cache || IsHttps() && $export && !SameText($export, "print")) { // Allow cache
        $Response = $cacheProvider->allowCache($Response, "private", 86400, true);
    } else { // No cache
        $Response = $cacheProvider->denyCache($Response);
        $Response = $cacheProvider->withExpires($Response, "Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
        $Response = $cacheProvider->withLastModified($Response, gmdate("D, d M Y H:i:s") . " GMT"); // Always modified
    }
    $Response = $Response->withHeader("X-UA-Compatible", "IE=edge");
    if (!$export || SameText($export, "print")) {
        $ct = "text/html";
        $charset = PROJECT_CHARSET;
        if ($charset != "") {
            $ct .= "; charset=" . $charset;
        }
        $Response = $Response->withHeader("Content-Type", $ct); // Charset
    }
}

/**
 *  Get content file extension
 *
 * @param string $data Data
 * @param bool $dot Extension with dot
 * @return string
 */
function ContentExtension(&$data, $dot = true)
{
    $ct = ContentType($data);
    if ($ct) {
        $ext = MimeTypes()->getExtensions($ct)[0] ?? null;
        if ($ext) {
            return $dot ? "." . $ext : $ext;
        }
    }
    return ""; // Unknown extension
}

/**
 * Get content type
 * http://en.wikipedia.org/wiki/List_of_file_signatures
 * https://www.garykessler.net/library/file_sigs.html (mp3 / aac / flac / mp4 / m4v / mov)
 *
 * @param string $data Data of file
 * @param string $fn File path
 * @return string Content type
 */
function ContentType(&$data, $fn = "")
{
    $mp4Sig = strlen($data) >= 12 ? substr($data, 4, 8) : "";
    if (StartsString("\x47\x49\x46\x38\x37\x61", $data) || StartsString("\x47\x49\x46\x38\x39\x61", $data)) { // Check if gif
        return "image/gif";
    } elseif (StartsString("\xFF\xD8\xFF\xE0", $data) || StartsString("\xFF\xD8\xFF\xDB", $data) || StartsString("\xFF\xD8\xFF\xEE", $data) || StartsString("\xFF\xD8\xFF\xE1", $data)) { // Check if jpg
        return "image/jpeg";
    } elseif (StartsString("\x89\x50\x4E\x47\x0D\x0A\x1A\x0A", $data)) { // Check if png
        return "image/png";
    } elseif (StartsString("\x42\x4D", $data)) { // Check if bmp
        return "image/bmp";
    } elseif (StartsString("\x25\x50\x44\x46", $data)) { // Check if pdf
        return "application/pdf";
    } elseif (StartsString("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", $data)) { // xls/doc/ppt
        if (ContainsString($data, "\x77\x6F\x72\x6B\x62\x6F\x6F\x6B")) { // xls, find pattern "workbook"
            return MimeTypes()->getMimeTypes("xls")[0];
        } elseif (ContainsString($data, "\x57\x6F\x72\x64\x2E\x44\x6F\x63\x75\x6D\x65\x6E\x74")) { // doc, find pattern "Word.Document"
            return MimeTypes()->getMimeTypes("doc")[0];
        }
    } elseif (StartsString("\x50\x4B\x03\x04", $data)) { // docx/xlsx/pptx/zip
        if ($fn != "") { // Use file extension to get mime type first in case other files types with the same bytes (e.g. dotx)
            return MimeContentType($fn);
        } elseif (ContainsString($data, "\x78\x6C\x2F\x77\x6F\x72\x6B\x62\x6F\x6F\x6B")) { // xlsx, find pattern "x1/workbook"
            return MimeTypes()->getMimeTypes("xlsx")[0];
        } elseif (ContainsString($data, "\x77\x6F\x72\x64\x2F\x5F\x72\x65\x6C")) { // docx, find pattern "word/_rel"
            return MimeTypes()->getMimeTypes("docx")[0];
        }
    } elseif (StartsString("\x49\x44\x33", $data)) { // mp3
        return MimeTypes()->getMimeTypes("mp3")[0];
    } elseif (StartsString("\xFF\xF1", $data) || StartsString("\xFF\xF9", $data)) { // aac
        return MimeTypes()->getMimeTypes("aac")[0];
    } elseif (StartsString("\x66\x4C\x61\x43\x00\x00\x00\x22", $data)) { // flac
        return MimeTypes()->getMimeTypes("flac")[0];
    } elseif (SameString("\x66\x74\x79\x70\x4D\x53\x4E\x56", $mp4Sig) || SameString("\x66\x74\x79\x70\x69\x73\x6F\x6D", $mp4Sig)) { // mp4
        return MimeTypes()->getMimeTypes("mp4")[0];
    } elseif (SameString("\x66\x74\x79\x70\x6D\x70\x34\x32", $mp4Sig)) { // m4v
        return MimeTypes()->getMimeTypes("mp4v")[0];
    } elseif (SameString("\x66\x74\x79\x70\x71\x74\x20\x20", $mp4Sig)) { // mov
        return MimeTypes()->getMimeTypes("mov")[0];
    } elseif ($fn != "") { // Use file extension to get mime type
        return MimeContentType($fn);
    }
    return Config("DEFAULT_MIME_TYPE");
}

/**
 * Get MimeTypes
 *
 * @return Symfony\Component\Mime\MimeTypes
 */
function MimeTypes()
{
    return Container("mime.types");
}

/**
 * Get content type for a file
 *
 * @param string $fn File path
 * @return string Content type
 */
function MimeContentType($fn)
{
    $ext = pathinfo($fn, PATHINFO_EXTENSION);
    $mt = MimeTypes();
    $ct = $mt->getMimeTypes($ext)[0] ?? "";
    if (!$ct && (file_exists($fn) || is_readable($fn))) { // Check the file content if possible
        if ($mt->isGuesserSupported()) {
            $ct = $mt->guessMimeType($fn);
        } elseif (function_exists("mime_content_type")) {
            $ct = mime_content_type($fn);
        } else {
            $size = @getimagesize($filepath);
            if (!empty($size["mime"])) {
                $ct = $size["mime"];
            }
        }
    }
    return $ct ?: Config("DEFAULT_MIME_TYPE");
}

/**
 * Get file extension for a file
 *
 * @param string $fn File path
 * @param bool $dot Extension with dot
 * @return string
 */
// Get content file extension
function MimeContentExtension($fn, $dot = true)
{
    $ext = pathinfo($fn, PATHINFO_EXTENSION);
    $ct = MimeContentType($fn);
    if ($ct) {
        $ext = MimeTypes()->getExtensions($ct)[0] ?? null;
        if ($ext) {
            return $dot ? "." . $ext : $ext;
        }
    }
    return ""; // Unknown extension
}

/**
 * Get entity manager
 *
 * @param string $dbid Database ID
 * @return EntityManager
 */
function EntityManager($dbid = "")
{
    return Container("entitymanager." . ($dbid ?: "DB"));
}

/**
 * Get event manager
 *
 * @param string $dbid Database ID
 * @return EventManager
 */
function EventManager($dbid = "")
{
    return Conn($dbid)->getEventManager();
}

/**
 * Get user entity manager
 *
 * @return EntityManager
 */
function GetUserEntityManager()
{
    return EntityManager(Config("USER_TABLE_DBID"));
}

/**
 * Get user repository
 *
 * @return ObjectRepository
 */
function GetUserRepository()
{
    return GetUserEntityManager()->getRepository(Config("USER_TABLE_ENTITY_CLASS"));
}

/**
 * Get entity class name by table name
 *
 * @param string $tablename Table name
 * @return string Entity class name
 */
function GetEntityClass($tablename)
{
    $finder = Finder::create()->files()->in(__DIR__ . "/Entity");
    foreach ($finder as $file) { // Delete files
        $name = $file->getBaseName(".php");
        $class = new \ReflectionClass(PROJECT_NAMESPACE . "Entity\\" . $name);
        $attributes = $class->getAttributes();
        foreach ($attributes as $attribute) {
            if ($attribute->getName() == "Doctrine\\ORM\\Mapping\\Table" && $attribute->newInstance()->name == $tablename) {
                return PROJECT_NAMESPACE . "Entity\\" . $name;
            }
        }
    }
    return null;
}

/**
 * Get an user entity by user name
 *
 * @param string $username User name
 * @param array $criteria Other criteria
 * @return User entity or null
 */
function FindUserByUserName(?string $username, array $criteria = [])
{
    $criteria = array_merge(["username" => $username], $criteria);
    return !EmptyValue($username)
        ? GetUserRepository()->findOneBy($criteria)
        : null; // Note: Use property name, not column name.
}

/**
 * Get privilege
 *
 * @param Allow|string|int $name Allow (enum) or enum name or value
 * @return int
 */
function GetPrivilege(Allow|string|int $name): int
{
    if ($name instanceof Allow) { // Enum
        return $name->value;
    } elseif (is_int($name)) { // Integer
        return $name;
    }
    $name = strtoupper($name);
    if (IS_PHP81) { // PHP >= 8.1
        global $container;
        if (!$container->has("reflection.enum.allow")) {
            $container->set("reflection.enum.allow", new \ReflectionEnum(Allow::class));
        }
        $re = $container->get("reflection.enum.allow");
        return $re->hasCase($name) ? $re->getCase($name)->getBackingValue() : 0;
    } else { // PHP 8.0
        return Allow::coerce($name)->value ?? 0;
    }
}

// Get connection object
function Conn($dbid = "")
{
    global $container;
    $name = "connection." . ($dbid ?: "DB");
    return $container?->has($name) ? $container->get($name) : ConnectDb(Db($dbid));
}

// Get connection object (alias of Conn())
function GetConnection($dbid = "")
{
    return Conn($dbid);
}

// Get connection resource handle
function GetConnectionId($dbid = "")
{
    $conn = Conn($dbid);
    $c = $conn->getWrappedConnection();
    return method_exists($c, "getWrappedResourceHandle") ? $c->getWrappedResourceHandle() : $c;
}

// Get connection info
function Db($dbid = "")
{
    return Config("Databases." . ($dbid ?: "DB"));
}

// Get connection type
function GetConnectionType($dbid = "")
{
    $db = Db($dbid);
    return $db ? $db["type"] : false;
}

// Connect to database
function ConnectDb($info)
{
    $event = new DatabaseConnectingEvent(arguments: $info);
    DispatchEvent($event, DatabaseConnectingEvent::NAME);
    $info = $event->getArguments();
    $dbid = $info["id"] ?? "DB";
    $dbtype = $info["type"] ?? "";
    if ($dbtype == "MYSQL") {
        $info["driver"] ??= "pdo_mysql";
        if (Config("MYSQL_CHARSET") != "" && !array_key_exists("charset", $info)) {
            $info["charset"] = Config("MYSQL_CHARSET");
        }
        if ($info["driver"] == "pdo_mysql") {
            $keys = [
                \PDO::MYSQL_ATTR_SSL_CA,
                \PDO::MYSQL_ATTR_SSL_CAPATH,
                \PDO::MYSQL_ATTR_SSL_CERT,
                \PDO::MYSQL_ATTR_SSL_CIPHER,
                \PDO::MYSQL_ATTR_SSL_KEY
            ];
            if (
                defined("PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT") &&
                Collection::make($info["driverOptions"] ?? [])->keys()->contains(fn ($v) => in_array($v, $keys))
            ) { // SSL
                $info["driverOptions"][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] ??= false;
            }
        } elseif ($info["driver"] == "mysqli") {
            if (Collection::make($info)->keys()->contains(fn ($v) => StartsString("ssl_", $v))) { // SSL
                $info["driverOptions"]["flags"] = ($info["driverOptions"]["flags"] ?? 0) | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
            }
        }
    } elseif ($dbtype == "POSTGRESQL") {
        $info["driver"] = "pdo_pgsql";
        if (Config("POSTGRESQL_CHARSET") != "" && !array_key_exists("charset", $info)) {
            $info["charset"] = Config("POSTGRESQL_CHARSET");
        }
    } elseif ($dbtype == "MSSQL") {
        $info["driver"] ??= "sqlsrv";
        $info["driverOptions"] ??= []; // See https://docs.microsoft.com/en-us/sql/connect/php/connection-options?view=sql-server-ver16
        // Use TransactionIsolation = SQLSRV_TXN_READ_UNCOMMITTED to avoid record locking
        // https://docs.microsoft.com/en-us/sql/t-sql/statements/set-transaction-isolation-level-transact-sql?view=sql-server-ver15
        $info["driverOptions"]["TrustServerCertificate"] = 1;
        if ($info["driver"] == "sqlsrv") {
            $info["driverOptions"]["TransactionIsolation"] = 1; // SQLSRV_TXN_READ_UNCOMMITTED
            if (IS_UTF8) {
                $info["driverOptions"]["CharacterSet"] = "UTF-8";
            }
        } elseif ($info["driver"] == "pdo_sqlsrv") {
            $info["driverOptions"]["TransactionIsolation"] = "READ_UNCOMMITTED"; // PDO::SQLSRV_TXN_READ_UNCOMMITTED
            //if (IS_UTF8) { // Not supported for pdo_sqlsrv
                //$info["driverOptions"]["CharacterSet"] = "UTF-8";
            //}
        }
    } elseif ($dbtype == "SQLITE") {
        $info["driver"] = "pdo_sqlite";
    } elseif ($dbtype == "ORACLE") {
        $info["driver"] = "oci8";
        if (Config("ORACLE_CHARSET") != "" && !array_key_exists("charset", $info)) {
            $info["charset"] = Config("ORACLE_CHARSET");
        }
    }

    // Decrypt user name and password
    if (array_key_exists("user", $info) && Config("ENCRYPT_USER_NAME_AND_PASSWORD")) {
        $info["user"] = PhpDecrypt($info["user"]);
    }
    if (array_key_exists("password", $info) && Config("ENCRYPT_USER_NAME_AND_PASSWORD")) {
        $info["password"] = PhpDecrypt($info["password"]);
    }

    // Configuration
    $config = new Configuration();
    $sqlLogger = Container("sql.logger");
    if ($sqlLogger) {
        $config->setSQLLogger($sqlLogger);
    }

    // Connect
    $evm = new EventManager();
    if ($dbtype == "MYSQL" || $dbtype == "POSTGRESQL" || $dbtype == "ORACLE") {
        $dbtimezone = @$info["timezone"] ?: Config("DB_TIME_ZONE");
        if ($dbtype == "ORACLE") {
            $oraVars = ["CURRENT_SCHEMA" => QuotedName(@$info["schema"], $dbid)];
            if ($dbtimezone != "") {
                $oraVars["TIME_ZONE"] = $dbtimezone;
            }
            $evm->addEventSubscriber(new OracleSessionInit($oraVars));
        }
        $conn = DriverManager::getConnection($info, $config, $evm);
        if ($dbtype == "MYSQL") {
            if ($dbtimezone != "") {
                $conn->executeStatement("SET time_zone = '" . $dbtimezone . "'");
            }
        }
        if ($dbtype == "POSTGRESQL") {
            if ($dbtimezone != "") {
                $conn->executeStatement("SET TIME ZONE '" . $dbtimezone . "'");
            }
        }
        if ($dbtype == "POSTGRESQL") {
            // Set schema
            if (@$info["schema"] != "public" && @$info["schema"] != "") {
                $conn->executeStatement("SET search_path TO " . QuotedName($info["schema"], $dbid));
            }
        }
    } elseif ($dbtype == "SQLITE") {
        $relpath = @$info["relpath"];
        $dbname = @$info["dbname"];
        if ($relpath == "") {
            $info["path"] = realpath($GLOBALS["RELATIVE_PATH"] . $dbname);
        } elseif (StartsString("\\\\", $relpath) || ContainsString($relpath, ":")) { // Physical path
            $info["path"] = $relpath . $dbname;
        } else { // Relative to app root
            $info["path"] = ServerMapPath($relpath) . $dbname;
        }
        $conn = DriverManager::getConnection($info, $config, $evm);
    } elseif ($dbtype == "MSSQL") {
        $conn = DriverManager::getConnection($info, $config, $evm);
        // $conn->executeStatement("SET DATEFORMAT ymd"); // Set date format
    }
    $platform = $conn->getDatabasePlatform();
    if ($platform instanceof Platforms\MySQLPlatform) { // MySQL
        $platform->registerDoctrineTypeMapping("enum", "string"); // Map enum to string
        $platform->registerDoctrineTypeMapping("bytes", "bytes"); // Map bytes to bytes
        $platform->registerDoctrineTypeMapping("geometry", "geometry"); // Map geometry to geometry
    } else if ($platform instanceof Platforms\PostgreSQLPlatform) { // PostgreSQL
        $platform->registerDoctrineTypeMapping("timetz", "timetz"); // Map timetz to timetz
        $platform->registerDoctrineTypeMapping("geometry", "geometry"); // Map geometry to geometry
        $platform->registerDoctrineTypeMapping("geography", "geography"); // Map geography to geography
    } else if ($platform instanceof Platforms\SQLServerPlatform) { // Microsoft SQL Server
        $platform->registerDoctrineTypeMapping("geometry", "geometry"); // Map geometry to geometry
        $platform->registerDoctrineTypeMapping("geography", "geography"); // Map geography to geography
        $platform->registerDoctrineTypeMapping("hierarchyid", "hierarchyid"); // Map hierarchyid to hierarchyid
    }
    $event = new DatabaseConnectedEvent($conn);
    DispatchEvent($event, DatabaseConnectedEvent::NAME);
    return $conn;
}

// Close database connections
function CloseConnections()
{
    $dbids = array_keys(Config("Databases"));
    foreach ($dbids as $dbid) {
        Container("connection." . $dbid)?->close();
    }
    $GLOBALS["Conn"] = null;
}

/**
 * Cast date/time field for LIKE
 *
 * @param string $fld Field expression
 * @param int $namedformat Date format
 * @param string $dbid Database ID
 * @return string SQL expression formatting the field to 'y-MM-dd HH:mm:ss'
 */
function CastDateFieldForLike($fld, $namedformat, $dbid = "")
{
    global $DATE_FORMAT, $DATE_SEPARATOR, $TIME_SEPARATOR;
    $dbtype = GetConnectionType($dbid);
    $dateFormat = DbDateFormat($namedformat, $dbtype);
    if ($dateFormat) {
        if ($dbtype == "MYSQL") {
            return "DATE_FORMAT(" . $fld . ", '" . $dateFormat . "')";
        } elseif ($dbtype == "MSSQL") {
            return "FORMAT(" . $fld . ", '" . $dateFormat . "')";
        } elseif ($dbtype == "ORACLE" || $dbtype == "POSTGRESQL") {
            return "TO_CHAR(" . $fld . ", '" . $dateFormat . "')";
        } elseif ($dbtype == "SQLITE") {
            return "STRFTIME('" . $dateFormat . "', " . $fld . ")";
        }
    }
    return $fld;
}

// Append LIKE operator
function Like($pat, $dbid = "")
{
    return LikeOrNotLike("LIKE", $pat, $dbid);
}

// Append NOT LIKE operator
function NotLike($pat, $dbid = "")
{
    return LikeOrNotLike("NOT LIKE", $pat, $dbid);
}

// Append LIKE or NOT LIKE operator
function LikeOrNotLike($opr, $pat, $dbid = "")
{
    $dbtype = GetConnectionType($dbid);
    $opr = " " . $opr . " "; // " LIKE " or " NOT LIKE "
    $pat = "'" . $pat . "'"; // Note: $pat already adjusted for "'" in AdjustSqlForLike
    if ($dbtype == "POSTGRESQL" && Config("USE_ILIKE_FOR_POSTGRESQL")) {
        return str_replace(" LIKE ", " ILIKE ", $opr) . $pat;
    } elseif ($dbtype == "MYSQL" && Config("LIKE_COLLATION_FOR_MYSQL") != "") {
        return $opr . $pat . " COLLATE " . Config("LIKE_COLLATION_FOR_MYSQL");
    } elseif ($dbtype == "MSSQL" && Config("LIKE_COLLATION_FOR_MSSQL") != "") {
        return " COLLATE " . Config("LIKE_COLLATION_FOR_MSSQL") . $opr . $pat;
    } elseif ($dbtype == "SQLITE" || $dbtype == "ORACLE") {
        $pat .= " ESCAPE '\'"; // Add ESCAPE character
    }
    return $opr . $pat;
}

/**
 * Get multi-value search SQL
 *
 * @param DbField $fld Field object
 * @param string $fldOpr Search operator
 * @param string $fldVal Converted search value
 * @param string $dbid Database ID
 * @return string WHERE clause
 */
function GetMultiSearchSql($fld, $fldOpr, $fldVal, $dbid)
{
    $fldDataType = $fld->DataType;
    $fldOpr = ConvertSearchOperator($fldOpr, $fld, $fldVal);
    if (in_array($fldOpr, ["IS NULL", "IS NOT NULL", "IS EMPTY", "IS NOT EMPTY"])) {
        return SearchFilter($fld->Expression, $fldOpr, $fldVal, $fldDataType, $dbid);
    } else {
        if ($fld->UseFilter) { // Use filter
            $sep = Config("FILTER_OPTION_SEPARATOR");
            $arVal = explode($sep, $fldVal);
            $ar = [];
            foreach ($arVal as $val) {
                $val = trim($val);
                $ar[] = SearchFilter($fld->Expression, $fldOpr, $val, $fldDataType, $dbid);
            }
            return implode(" OR ", $ar);
        } else {
            $sep = Config("MULTIPLE_OPTION_SEPARATOR");
            $arVal = explode($sep, $fldVal);
            $wrk = "";
            $dbtype = GetConnectionType($dbid);
            $searchOption = Config("SEARCH_MULTI_VALUE_OPTION");
            if ($searchOption == 1 || !IsMultiSearchOperator($fldOpr)) { // No multiple value search
                $wrk = SearchFilter($fld->Expression, $fldOpr, $fldVal, DataType::STRING, $dbid);
            } else { // Handle multiple search operator
                $searchCond = $searchOption == 3 ? "OR" : "AND"; // Search condition
                if (StartsString("NOT ", $fldOpr) || $fldOpr == "<>") { // Negate for NOT search
                    $searchCond = $searchCond == "AND" ? "OR" : "AND";
                }
                foreach ($arVal as $val) {
                    $val = trim($val);
                    if (!IsMultiSearchOperator($fldOpr)) {
                        $sql = SearchFilter($fld->Expression, $fldOpr, $val, $fldDataType, $dbid);
                    } elseif ($dbtype == "MYSQL" && in_array($fldOpr, ["=", "<>"])) { // Use FIND_IN_SET() for MySQL
                        $sql = GetMultiValueFilter($fld->Expression, $val, $dbid, $fldOpr);
                    } else { // Build multi search SQL
                        $sql = GetMultiSearchSqlFilter($fld->Expression, $fldOpr, $val, $dbid, $sep);
                    }
                    AddFilter($wrk, $sql, $searchCond);
                }
            }
        }
        return $wrk;
    }
}

// Multi value search operator
function IsMultiSearchOperator($opr)
{
    return in_array($opr, ["=", "<>"]); // Supports "=", "<>" only for multi value search
}

/**
 * Get multi search SQL filter
 *
 * @param string $fldExpression Field expression
 * @param string $fldOpr Search operator
 * @param string $fldVal Converted search value
 * @param string $dbid Database ID
 * @param string $sep Separator, e.g. Config("MULTIPLE_OPTION_SEPARATOR")
 * @return string WHERE clause (fld = val OR fld LIKE val,% OR fld LIKE %,val,% OR fld LIKE %,val)
 */
function GetMultiSearchSqlFilter($fldExpression, $fldOpr, $fldVal, $dbid, $sep)
{
    $opr = "=";
    $cond = "OR";
    $likeOpr = "LIKE";
    if (StartsString("NOT ", $fldOpr) || $fldOpr == "<>") {
        $opr = "<>";
        $cond = "AND";
        $likeOpr = "NOT LIKE";
    }
    $sql = $fldExpression . " " . $opr . " '" . AdjustSql($fldVal, $dbid) . "' " . $cond . " ";
    $sql .= $fldExpression . LikeOrNotLike($likeOpr, Wildcard($fldVal . $sep, "STARTS WITH", $dbid), $dbid) . " " . $cond . " " .
        $fldExpression . LikeOrNotLike($likeOpr, Wildcard($sep . $fldVal . $sep, "LIKE", $dbid), $dbid) . " " . $cond . " " .
        $fldExpression . LikeOrNotLike($likeOpr, Wildcard($sep . $fldVal, "ENDS WITH", $dbid), $dbid);
    return $sql;
}

// Check if float type
function IsFloatType($fldType)
{
    return in_array($fldType, [4, 5, 6, 131, 139]);
}

// Check if is numeric
function IsNumeric($value)
{
    return is_numeric($value) || ParseNumber($value) !== false;
}

/**
 * Get dropdown filter
 *
 * @param ReportField $fld Report field object
 * @param string $fldVal Filter value
 * @param string $fldOpr Filter operator
 * @param string $dbid Database ID
 * @param string $fldVal2 Filter value 2
 * @return string WHERE clause
 */
function DropDownFilter($fld, $fldVal, $fldOpr, $dbid = "", $fldVal2 = "")
{
    $fldName = $fld->Name;
    $fldExpression = $fld->searchExpression();
    $fldDataType = $fld->searchDataType();
    $fldOpr = $fldOpr ?: "=";
    $fldVal = ConvertSearchValue($fldVal, $fldOpr, $fld);
    $wrk = "";
    if (SameString($fldVal, Config("NULL_VALUE"))) {
        $wrk = $fld->Expression . " IS NULL";
    } elseif (SameString($fldVal, Config("NOT_NULL_VALUE"))) {
        $wrk = $fld->Expression . " IS NOT NULL";
    } elseif (SameString($fldVal, Config("EMPTY_VALUE"))) {
        $wrk = $fld->Expression . " = ''";
    } elseif (SameString($fldVal, Config("ALL_VALUE"))) {
        $wrk = "1 = 1";
    } else {
        if (StartsString("@@", $fldVal)) {
            $wrk = CustomFilter($fld, $fldVal, $dbid);
        } elseif (($fld->isMultiSelect() || $fld->UseFilter) && IsMultiSearchOperator($fldOpr) && !EmptyValue($fldVal)) {
            $wrk = GetMultiSearchSql($fld, $fldOpr, trim($fldVal), $dbid);
        } elseif ($fldOpr == "BETWEEN" && !EmptyValue($fldVal) && !EmptyValue($fldVal2)) {
            $wrk = $fldExpression . " " . $fldOpr . " " . QuotedValue($fldVal, $fldDataType, $dbid) . " AND " . QuotedValue($fldVal2, $fldDataType, $dbid);
        } else {
            if (!EmptyValue($fldVal)) {
                if ($fldDataType == DataType::DATE && $fldOpr != "") {
                    $wrk = GetDateFilterSql($fld->Expression, $fldOpr, $fldVal, $fldDataType, $dbid);
                } else {
                    $wrk = SearchFilter($fldExpression, $fldOpr, $fldVal, $fldDataType, $dbid);
                }
            }
        }
    }
    return $wrk;
}

/**
 * Get custom filter
 *
 * @param ReportField $fld Report field object
 * @param string $fldVal Filter value
 * @param string $dbid Database ID
 * @return string WHERE clause
 */
function CustomFilter($fld, $fldVal, $dbid = "")
{
    $wrk = "";
    if (is_array($fld->AdvancedFilters)) {
        foreach ($fld->AdvancedFilters as $filter) {
            if ($filter->ID == $fldVal && $filter->Enabled) {
                $fldExpr = $fld->Expression;
                $fn = $filter->FunctionName;
                $wrkid = StartsString("@@", $filter->ID) ? substr($filter->ID, 2) : $filter->ID;
                $fn = $fn != "" && !function_exists($fn) ? PROJECT_NAMESPACE . $fn : $fn;
                if (function_exists($fn)) {
                    $wrk = $fn($fldExpr, $dbid);
                } else {
                    $wrk = "";
                }
                break;
            }
        }
    }
    return $wrk;
}

/**
 * Get search SQL
 *
 * @param DbField $fld Field object
 * @param string $fldVal Converted search value
 * @param string $fldOpr Converted search operator
 * @param string $fldCond Search condition
 * @param string $fldVal2 Converted search value 2
 * @param string $fldOpr2 Converted search operator 2
 * @param string $dbid Database ID
 * @return string WHERE clause
 */
function GetSearchSql($fld, $fldVal, $fldOpr, $fldCond, $fldVal2, $fldOpr2, $dbid)
{
    // Build search SQL
    $sql = "";
    $virtual = $fld->VirtualSearch;
    $fldExpression = $virtual ? $fld->VirtualExpression : $fld->Expression;
    $fldDataType = $virtual ? DataType::STRING : $fld->DataType;
    if (in_array($fldOpr, ["BETWEEN", "NOT BETWEEN"])) {
        $isValidValue = $fldDataType != DataType::NUMBER || is_numeric($fldVal) && is_numeric($fldVal2);
        if ($fldVal != "" && $fldVal2 != "" && $isValidValue) {
            $sql = $fldExpression . " " . $fldOpr . " " . QuotedValue($fldVal, $fldDataType, $dbid) .
                " AND " . QuotedValue($fldVal2, $fldDataType, $dbid);
        }
    } else {
        // Handle first value
        if ($fldVal != "" && IsValidOperator($fldOpr) || IsNullOrEmptyOperator($fldOpr)) {
            $sql = SearchFilter($fldExpression, $fldOpr, $fldVal, $fldDataType, $dbid);
            if ($fld->isBoolean() && $fldVal == $fld->FalseValue && $fldOpr == "=") {
                $sql = "(" . $sql . " OR " . $fldExpression . " IS NULL)";
            }
        }
        // Handle second value
        $sql2 = "";
        if ($fldVal2 != "" && !EmptyValue($fldOpr2) && IsValidOperator($fldOpr2) || IsNullOrEmptyOperator($fldOpr2)) {
            $sql2 = SearchFilter($fldExpression, $fldOpr2, $fldVal2, $fldDataType, $dbid);
            if ($fld->isBoolean() && $fldVal2 == $fld->FalseValue && $fldOpr2 == "=") {
                $sql2 = "(" . $sql2 . " OR " . $fldExpression . " IS NULL)";
            }
        }
        // Combine SQL
        AddFilter($sql, $sql2, $fldCond == "OR" ? "OR" : "AND");
    }
    return $sql;
}

/**
 * Get search filter
 *
 * @param string $fldExpression Field expression
 * @param string $fldOpr Search operator
 * @param string $fldVal Converted search value
 * @param string $fldType Field type
 * @param string $dbid Database ID
 * @return string WHERE clause
 */
function SearchFilter($fldExpression, $fldOpr, $fldVal, $fldType, $dbid)
{
    $filter = $fldExpression;
    if (!$filter) {
        return "";
    }
    if (EmptyValue($fldOpr)) {
        $fldOpr = "=";
    }
    if (in_array($fldOpr, ["=", "<>", "<", "<=", ">", ">="])) {
        $filter .= " " . $fldOpr . " " . QuotedValue($fldVal, $fldType, $dbid);
    } elseif ($fldOpr == "IS NULL" || $fldOpr == "IS NOT NULL") {
        $filter .= " " . $fldOpr;
    } elseif ($fldOpr == "IS EMPTY") {
        $filter .= " = ''";
    } elseif ($fldOpr == "IS NOT EMPTY") {
        $filter .= " <> ''";
    } elseif ($fldOpr == "FIND_IN_SET" || $fldOpr == "NOT FIND_IN_SET") { // MYSQL only
        $filter = $fldOpr . "(" . $fldExpression . ", '" . AdjustSql($fldVal, $dbid) . "')";
    } elseif ($fldOpr == "IN" || $fldOpr == "NOT IN") {
        $filter .= " " . $fldOpr . " (" . implode(", ", array_map(fn($v) => QuotedValue($v, $fldType, $dbid), explode(Config("IN_OPERATOR_VALUE_SEPARATOR"), $fldVal))) . ")";
    } elseif (in_array($fldOpr, ["STARTS WITH", "LIKE", "ENDS WITH"])) {
        $filter .= Like(Wildcard($fldVal, $fldOpr, $dbid), $dbid);
    } elseif (in_array($fldOpr, ["NOT STARTS WITH", "NOT LIKE", "NOT ENDS WITH"])) {
        $filter .= NotLike(Wildcard($fldVal, $fldOpr, $dbid), $dbid);
    } else { // Default is equal
        $filter .= " = " . QuotedValue($fldVal, $fldType, $dbid);
    }
    return $filter;
}

/**
 * Convert search operator
 *
 * @param string $fldOpr Search operator
 * @param DbField $fld Field object
 * @param array|string $fldVal Converted field value(s) (single, delimited or array)
 * @return string|false Converted search operator (false if invalid operator)
 */
function ConvertSearchOperator($fldOpr, $fld, $fldVal)
{
    if ($fld->UseFilter) {
        $fldOpr = "="; // Use "equal"
    }
    $fldOpr = array_search($fldOpr, Config("CLIENT_SEARCH_OPERATORS")) ?: $fldOpr;
    if (!IsValidOperator($fldOpr)) {
        return false;
    }
    if ($fldVal == Config("NULL_VALUE")) { // Null value
        return "IS NULL";
    } elseif ($fldVal == Config("NOT_NULL_VALUE")) { // Not Null value
        return "IS NOT NULL";
    } elseif (EmptyValue($fldOpr)) { // Not specified, ignore
        return $fldOpr;
    } elseif ($fld->DataType == DataType::NUMBER && !$fld->VirtualSearch) { // Numeric value(s)
        if (!IsNumericSearchValue($fldVal, $fldOpr, $fld) || in_array($fldOpr, ["IS EMPTY", "IS NOT EMPTY"])) {
            return false; // Invalid
        } elseif (in_array($fldOpr, ["STARTS WITH", "LIKE", "ENDS WITH"])) {
            return "=";
        } elseif (in_array($fldOpr, ["NOT STARTS WITH", "NOT LIKE", "NOT ENDS WITH"])) {
            return "<>";
        }
    } elseif (
        in_array($fldOpr, ["LIKE", "NOT LIKE", "STARTS WITH", "NOT STARTS WITH", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"]) &&
        !in_array($fld->DataType, [DataType::STRING, DataType::MEMO, DataType::XML]) &&
        !$fld->VirtualSearch
    ) { // String type
        return false; // Invalid
    }
    return $fldOpr;
}

/**
 * Check if search value is numeric
 *
 * @param string $fldVal Converted search value
 * @param string $fldOpr Search oeperator
 * @param DbField $fld Field object
 * @return bool
 */
function IsNumericSearchValue($fldVal, $fldOpr, $fld)
{
    if ($fld->UseFilter && is_string($fldVal) && ContainsString($fldVal, Config("FILTER_OPTION_SEPARATOR"))) {
        return implode(Config("FILTER_OPTION_SEPARATOR"), array_map("is_numeric", explode(Config("FILTER_OPTION_SEPARATOR"), $fldVal)));
    } elseif ($fld->isMultiSelect() && is_string($fldVal) && ContainsString($fldVal, Config("MULTIPLE_OPTION_SEPARATOR"))) {
        return implode(Config("MULTIPLE_OPTION_SEPARATOR"), array_map("is_numeric", explode(Config("MULTIPLE_OPTION_SEPARATOR"), $fldVal)));
    } elseif (($fldOpr == "IN" || $fldOpr == "NOT IN") && ContainsString($fldVal, Config("IN_OPERATOR_VALUE_SEPARATOR"))) {
        return implode(Config("IN_OPERATOR_VALUE_SEPARATOR"), array_map("is_numeric", explode(Config("IN_OPERATOR_VALUE_SEPARATOR"), $fldVal)));
    } elseif (is_array($fldVal)) {
        return array_map("is_numeric", $fldVal);
    }
    return is_numeric($fldVal);
}

/**
 * Check if valid search operator
 *
 * @param string $fldOpr Search operator
 * @return bool
 */
function IsValidOperator($fldOpr)
{
    return EmptyValue($fldOpr) || in_array($fldOpr, array_keys(Config("CLIENT_SEARCH_OPERATORS")));
}

/**
 * Check if NULL or EMPTY search operator
 *
 * @param string $fldOpr Search operator
 * @return bool
 */
function IsNullOrEmptyOperator($fldOpr)
{
    return in_array($fldOpr, ["IS NULL", "IS NOT NULL", "IS EMPTY", "IS NOT EMPTY"]);
}

/**
 * Convert search value(s)
 *
 * @param array|string $fldVal Search value(s) (single, delimited or array)
 * @param DbField $fld Field object
 * @return array|string Converted search values
 */
function ConvertSearchValue($fldVal, $fldOpr, $fld)
{
    $convert = function ($val) use ($fld) {
        if ($val == Config("NULL_VALUE") || $val == Config("NOT_NULL_VALUE")) {
            return $val;
        } elseif (IsFloatType($fld->Type)) {
            return ConvertToFloatString($val);
        } elseif ($fld->isBoolean()) {
            return !EmptyValue($val) ? (ConvertToBool($val) ? $fld->TrueValue : $fld->FalseValue) : $val;
        } elseif ($fld->DataType == DataType::DATE || $fld->DataType == DataType::TIME) {
            return !EmptyValue($val) ? UnFormatDateTime($val, $fld->formatPattern()) : $val;
        }
        return $val;
    };
    if ($fld->UseFilter && is_string($fldVal) && ContainsString($fldVal, Config("FILTER_OPTION_SEPARATOR"))) {
        return implode(Config("FILTER_OPTION_SEPARATOR"), array_map($convert, explode(Config("FILTER_OPTION_SEPARATOR"), $fldVal)));
    } elseif ($fld->isMultiSelect() && is_string($fldVal) && ContainsString($fldVal, Config("MULTIPLE_OPTION_SEPARATOR"))) {
        return implode(Config("MULTIPLE_OPTION_SEPARATOR"), array_map($convert, explode(Config("MULTIPLE_OPTION_SEPARATOR"), $fldVal)));
    } elseif (($fldOpr == "IN" || $fldOpr == "NOT IN") && ContainsString($fldVal, Config("IN_OPERATOR_VALUE_SEPARATOR"))) {
        return implode(Config("IN_OPERATOR_VALUE_SEPARATOR"), array_map($convert, explode(Config("IN_OPERATOR_VALUE_SEPARATOR"), $fldVal)));
    } elseif (is_array($fldVal)) {
        return array_map($convert, $fldVal);
    }
    return $convert($fldVal);
}

/**
 * Quote table/field name based on dbid
 *
 * @param string $name Name
 * @param string $dbid Database ID
 * @return string
 */
function QuotedName($name, $dbid = "")
{
    $db = Config("Databases." . ($dbid ?: "DB"));
    if ($db) {
        $qs = $db["qs"];
        $qe = $db["qe"];
        return $qs . str_replace($qe, $qe . $qe, $name) . $qe;
    }
    return $name;
}

/**
 * Quote field value based on dbid
 *
 * @param mixed $value Value
 * @param int|DbField $fldType Field type or DbField
 * @param string $dbid Database ID
 * @return mixed
 */
function QuotedValue($value, $fldType, $dbid = "")
{
    if ($value === null) {
        return "NULL";
    }
    $dbtype = GetConnectionType($dbid);
    $raw = false;
    if ($fldType instanceof DbField) {
        $dataType = $fldType->DataType;
        $removeXss = !$fldType->Raw;
    } else {
        $dataType = $fldType;
        $removeXss = Config("REMOVE_XSS");
    }
    switch ($dataType) {
        case DataType::STRING:
        case DataType::MEMO:
            $val = "'" . AdjustSql($value, $dbid) . "'";
            return $dbtype == "MSSQL" ? "N" . $val : $val;
        case DataType::TIME:
            return "'" . AdjustSql($value, $dbid) . "'";
        case DataType::XML:
            return "'" . AdjustSql($value, $dbid) . "'";
        case DataType::BLOB:
            if ($dbtype == "MYSQL") {
                return "'" . addslashes($value) . "'";
            }
            return $value;
        case DataType::DATE:
            return "'" . AdjustSql($value, $dbid) . "'";
        case DataType::GUID:
            return "'" . $value . "'";
        case DataType::BOOLEAN:
            if ($dbtype == "MYSQL" || $dbtype == "POSTGRESQL") {
                return "'" . $value . "'"; // 'Y'|'N' or 'y'|'n' or '1'|'0' or 't'|'f'
            }
            return $value;
        case DataType::BIT: // $dbtype == "MYSQL" || $dbtype == "POSTGRESQL"
            return "b'" . $value . "'";
        case DataType::NUMBER:
            if (IsNumeric($value)) {
                return $value;
            }
            return "NULL"; // Treat as null
        default:
            return $value;
    }
}

/**
 * Add wildcard (%) to value for LIKE operator
 *
 * @param mixed $value Value
 * @param string $likeOpr LIKE operator
 * @param string $dbid Database ID
 * @return string
 */
function Wildcard($value, $likeOpr = "", $dbid = "")
{
    if (EndsText("STARTS WITH", $likeOpr)) {
        return AdjustSqlForLike($value, $dbid) . "%";
    } elseif (EndsText("ENDS WITH", $likeOpr)) {
        return "%" . AdjustSqlForLike($value, $dbid);
    } elseif (EndsText("LIKE", $likeOpr)) {
        return "%" . AdjustSqlForLike($value, $dbid) . "%";
    }
    return $value;
}

// Concat string
function Concat($str1, $str2, $sep)
{
    $str1 = trim($str1 ?? "");
    $str2 = trim($str2 ?? "");
    if ($str1 != "" && $sep != "" && !EndsString($sep, $str1)) {
        $str1 .= $sep;
    }
    return $str1 . $str2;
}

// Write message to debug file
function Trace($msg)
{
    $filename = "debug.txt";
    if (!$handle = fopen($filename, 'a')) {
        exit;
    }
    if (is_writable($filename)) {
        fwrite($handle, $msg . "\n");
    }
    fclose($handle);
}

// Compare values with special handling for null values
function CompareValue($v1, $v2)
{
    if ($v1 === null && $v2 === null) {
        return true;
    } elseif ($v1 === null || $v2 === null) {
        return false;
    } else {
        return ($v1 == $v2);
    }
}

// Check if boolean value is true
function ConvertToBool($value)
{
    return $value === true || in_array(strtolower($value ?? ""), ["1", "true", "on", "y", "yes", "t"]);
}

// Add message
function AddMessage(&$msg, $newmsg, $sep = "<br>")
{
    if (strval($newmsg) != "") {
        if (strval($msg) != "") {
            $msg .= $sep;
        }
        $msg .= $newmsg;
    }
}

/**
 * Add filter
 *
 * @param string $filter Filter
 * @param string|callable $newfilter New filter
 * @param string $cond Condition (AND / OR)
 * @return void
 */
function AddFilter(&$filter, $newfilter, $cond = "AND")
{
    if (is_callable($newfilter)) {
        $newfilter = $newfilter();
    }
    if (trim($newfilter ?? "") == "") {
        return;
    }
    if (trim($filter ?? "") != "") {
        $filter = AddBracketsForFilter($filter, $cond) . " " . $cond . " " . AddBracketsForFilter($newfilter, $cond);
    } else {
        $filter = $newfilter;
    }
}

/**
 * Add brackets to filter if necessary
 *
 * @param string $filter Filter
 * @param string $cond Condition (AND / OR)
 * @return string
 */
function AddBracketsForFilter($filter, $cond = "AND")
{
    if (trim($filter ?? "") != "") {
        $filterWrk = $filter;
        $pattern = '/\([^()]+?\)/';
        while (preg_match($pattern, $filterWrk)) { // Remove nested brackets (...)
            $filterWrk = preg_replace($pattern, "", $filterWrk);
        }
        if (preg_match('/\sOR\s/i', $filterWrk) && SameText($cond, "AND")) { // Check for any OR without brackets
            $filter = "(" . $filter . ")";
        }
    }
    return $filter;
}

// Adjust value (as string) for SQL based on dbid
function AdjustSql($val, $dbid = "")
{
    // $dbtype = GetConnectionType($dbid);
    $replacementMap = [
        "\0" => "\\0",
        "\n" => "\\n",
        "\r" => "\\r",
        "\t" => "\\t",
        chr(26) => "\\Z", // Substitute
        chr(8) => "\\b", // Backspace
        // '"' => '\"',
        "'" => "''",
        '\\' => '\\\\'
    ];
    return strtr(trim($val ?? ""), $replacementMap);
}

// Adjust value for SQL LIKE operator
function AdjustSqlForLike($val, $dbid = "")
{
    $replacementMap = [
        "'" => "''",
        "_" => "\_",
        "%" => "\%"
    ];
    $dbtype = GetConnectionType($dbid);
    if ($dbtype == "MSSQL") {
        $replacementMap["_"] = "[_]";
    }
    return strtr($val ?? "", $replacementMap);
}

/**
 * Write audit trail
 *
 * @param string $pfx Optional log file prefix (for backward compatibility only, not used)
 * @param string $dt Optional DateTime (for backward compatibility)
 * @param string $script Optional script name (for backward compatibility)
 * @param string $usr User ID or user name
 * @param string $action Action
 * @param string $table Table
 * @param string $field Field
 * @param string $keyvalue Key value
 * @param string $oldvalue Old value
 * @param string $newvalue New value
 * @return void
 */
function WriteAuditTrail($pfx, $dt, $script, $usr, $action, $table, $field, $keyvalue, $oldvalue, $newvalue)
{
    global $Language;
    if ($table === Config("AUDIT_TRAIL_TABLE_NAME")) {
        return;
    }
    $usrwrk = $usr;
    if ($usrwrk == "") { // Assume Administrator (logged in) / Anonymous user (not logged in) if no user
        $usrwrk = IsLoggedIn() ? $Language->phrase("UserAdministrator") : $Language->phrase("UserAnonymous");
    }
    if (Config("AUDIT_TRAIL_TO_DATABASE")) {
        $rsnew = [
            Config("AUDIT_TRAIL_FIELD_NAME_DATETIME") => $dt,
            Config("AUDIT_TRAIL_FIELD_NAME_SCRIPT") => $script,
            Config("AUDIT_TRAIL_FIELD_NAME_USER") => $usrwrk,
            Config("AUDIT_TRAIL_FIELD_NAME_ACTION") => $action,
            Config("AUDIT_TRAIL_FIELD_NAME_TABLE") => $table,
            Config("AUDIT_TRAIL_FIELD_NAME_FIELD") => $field,
            Config("AUDIT_TRAIL_FIELD_NAME_KEYVALUE") => $keyvalue,
            Config("AUDIT_TRAIL_FIELD_NAME_OLDVALUE") => $oldvalue,
            Config("AUDIT_TRAIL_FIELD_NAME_NEWVALUE") => $newvalue
        ];
    } else {
        $rsnew = [
            "datetime" => $dt,
            "script" => $script,
            "user" => $usrwrk,
            "ew-action" => $action,
            "table" => $table,
            "field" => $field,
            "keyvalue" => $keyvalue,
            "oldvalue" => $oldvalue,
            "newvalue" => $newvalue
        ];
    }

    // Call AuditTrail Inserting event
    $writeAuditTrail = AuditTrail_Inserting($rsnew);
    if ($writeAuditTrail) {
        if (Config("AUDIT_TRAIL_TO_DATABASE")) {
            $tbl = Container(Config("AUDIT_TRAIL_TABLE_VAR"));
            if ($tbl && (!method_exists($tbl, "rowInserting") || $tbl->rowInserting(null, $rsnew))) {
                if ($tbl->insert($rsnew)) {
                    if (method_exists($tbl, "rowInserted")) {
                        $tbl->rowInserted(null, $rsnew);
                    }
                }
            }
        } else {
            $logger = Container("app.audit");
            $logger->info(__FUNCTION__, $rsnew);
        }
    }
}

/**
 * Write audit trail
 *
 * @param string $usr User ID or user name
 * @param string $action Action
 * @param string $table Table
 * @param string $field Field
 * @param string $keyvalue Key value
 * @param string $oldvalue Old value
 * @param string $newvalue New value
 * @return void
 */
function WriteAuditLog($usr, $action, $table, $field = "", $keyvalue = "", $oldvalue = "", $newvalue = "")
{
    WriteAuditTrail("log", DbCurrentDateTime(), ScriptName(), $usr, $action, $table, $field, $keyvalue, $oldvalue, $newvalue);
}

/**
 * Write export log
 *
 * @param string $fileId File ID
 * @param string $dt DateTime
 * @param string $usr User ID or user name
 * @param string $exportType Export type
 * @param string $table Table
 * @param string $keyValue Key value
 * @param string $fileName File name
 * @param string $req Request
 * @return void
 */
function WriteExportLog($fileId, $dt, $usr, $exportType, $table, $keyValue, $fileName, $req)
{
    if (EmptyValue(Config("EXPORT_LOG_TABLE_VAR"))) {
        return;
    }
    $rsnew = [
        Config("EXPORT_LOG_FIELD_NAME_FILE_ID") => $fileId,
        Config("EXPORT_LOG_FIELD_NAME_DATETIME") => $dt,
        Config("EXPORT_LOG_FIELD_NAME_USER") => $usr,
        Config("EXPORT_LOG_FIELD_NAME_EXPORT_TYPE") => $exportType,
        Config("EXPORT_LOG_FIELD_NAME_TABLE") => $table,
        Config("EXPORT_LOG_FIELD_NAME_KEY_VALUE") => $keyValue,
        Config("EXPORT_LOG_FIELD_NAME_FILENAME") => $fileName,
        Config("EXPORT_LOG_FIELD_NAME_REQUEST") => $req
    ];
    if (Config("DEBUG")) {
        Log("Export: " . json_encode($rsnew));
    }
    $tbl = Container(Config("EXPORT_LOG_TABLE_VAR"));
    if ($tbl && (!method_exists($tbl, "rowInserting") || $tbl->rowInserting(null, $rsnew))) {
        if ($tbl->insert($rsnew)) {
            if (method_exists($tbl, "rowInserted")) {
                $tbl->rowInserted(null, $rsnew);
            }
        }
    }
}

/**
 * Export path
 *
 * @param bool $phyPath Physical path
 * @return string
 */
function ExportPath($phyPath = false)
{
    return $phyPath
        ? IncludeTrailingDelimiter(UploadPath(true) . Config("EXPORT_PATH"), true)
        : IncludeTrailingDelimiter(FullUrl(UploadPath(false) . Config("EXPORT_PATH")), false); // Full URL
}

/**
 * New GUID
 *
 * @return string
 */
function NewGuid()
{
    return \Ramsey\Uuid\Uuid::uuid4()->toString();
}

/**
 * Unformat date time
 *
 * @param string $dt Date/Time string
 * @param int|string $dateFormat Formatter pattern
 * @return string
 */
function UnFormatDateTime($dt, $dateFormat = "")
{
    global $CurrentLocale, $TIME_ZONE;
    $dt = trim($dt ?? "");
    if (
        EmptyValue($dt) ||
        preg_match('/^([0-9]{4})-([0][1-9]|[1][0-2])-([0][1-9]|[1|2][0-9]|[3][0|1])( (0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?)?$/', $dt) || // Date/Time
        preg_match('/^(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/', $dt)
    ) { // Time
        return $dt;
    }
    $dateFormat = DateFormat($dateFormat);
    $fmt = new \IntlDateFormatter($CurrentLocale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $TIME_ZONE, null, $dateFormat);
    $ts = $fmt->parse($dt); // Parse by $dateFormat
    if ($ts !== false) {
        if (ContainsText($dateFormat, "y") && ContainsText($dateFormat, "h")) { // Date/Time
            return date("Y-m-d H:i:s", $ts);
        } elseif (ContainsText($dateFormat, "y")) { // Date
            return date("Y-m-d", $ts);
        } elseif (ContainsText($dateFormat, "h")) { // Time
            return date("H:i:s", $ts);
        }
    }
    return $dt;
}

/**
 * Format a timestamp, datetime, date or time field
 *
 * @param int|string|DateTimeInterface $ts Timestamp or Date/Time string
 * @param int|string $dateformat Formatter pattern
 * @return string
 */
function FormatDateTime($ts, $dateFormat = "")
{
    global $CurrentLocale, $TIME_ZONE;
    $dt = false;
    if (is_numeric($ts)) { // Timestamp
        $dt = (new \DateTimeImmutable())->setTimestamp((int)$ts);
    } elseif (is_string($ts) && !EmptyValue($ts)) {
        $dt = new \DateTimeImmutable(trim($ts));
    } elseif ($ts instanceof \DateTimeInterface) {
        $dt = $ts;
    }
    if ($dt !== false) {
        if ($dateFormat == 8) { // Handle edit format (show time part only if exists)
            $dateFormat = intval($dt->format('His')) == 0 ? DateFormat(0) : DateFormat(1);
        } else {
            $dateFormat = DateFormat($dateFormat);
        }
        $fmt = new \IntlDateFormatter($CurrentLocale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $TIME_ZONE, null, $dateFormat);
        $res = $fmt->format($dt);
        return $res !== false ? ConvertDigits($res) : $ts;
    }
    return $ts;
}

/**
 * Is formatted
 *
 * @param string $value Value
 */
function IsFormatted($value)
{
    if (is_float($value) || is_int($value) || $value === null || $value === "") { // Number or empty, not formatted
        return false;
    }
    if (!is_numeric($value)) { // Contains non-numeric characters, assume formatted
        return true;
    }
    global $GROUPING_SEPARATOR;
    $value = strval($value);
    if ($GROUPING_SEPARATOR == "." && ContainsString($value, ".")) { // Contains one ".", e.g. 123.456
        if (ParseInteger($value) == str_replace(".", "", $value)) { // Can be parsed, "." is grouping separator
            return true;
        }
    }
    return false;
}

/**
 * Convert digits from intl numbering system to latn
 */
function ConvertDigits($value)
{
    global $CurrentLocale, $NUMBERING_SYSTEM;
    if ($NUMBERING_SYSTEM == "latn") {
        $nu = Config("INTL_NUMBERING_SYSTEMS")[$CurrentLocale] ?? "";
        if ($nu) {
            $digits = Config("NUMBERING_SYSTEMS")[$nu];
            return str_replace(mb_str_split($digits), str_split("0123456789"), $value);
        }
    }
    return $value;
}

/**
 * Format currency
 *
 * @param float $value Value
 * @param string $pattern Formatter pattern
 * @return string
 */
function FormatCurrency($value, $pattern = "")
{
    if ($value === null) {
        return null;
    }
    if (IsFormatted($value)) {
        $value = ParseNumber($value);
    }
    global $CurrentLocale, $CURRENCY_FORMAT, $CURRENCY_SYMBOL, $DECIMAL_SEPARATOR, $GROUPING_SEPARATOR;
    $fmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::CURRENCY);
    $fmt->setPattern($pattern ?: $CURRENCY_FORMAT);
    $fmt->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, $CURRENCY_SYMBOL);
    $fmt->setSymbol(\NumberFormatter::MONETARY_SEPARATOR_SYMBOL, $DECIMAL_SEPARATOR);
    $fmt->setSymbol(\NumberFormatter::MONETARY_GROUPING_SEPARATOR_SYMBOL, $GROUPING_SEPARATOR);
    $res = $fmt->format((float)$value);
    return $res !== false ? ConvertDigits($res) : $value;
}

/**
 * Parse currency
 *
 * @param string $value Value (Must match the locale pattern, e.g. "100\xc2\xa€")
 * @param string $pattern Formatter pattern
 * @return float
 */
function ParseCurrency($value, $pattern = "")
{
    global $CurrentLocale, $CURRENCY_FORMAT, $CURRENCY_SYMBOL, $DECIMAL_SEPARATOR, $GROUPING_SEPARATOR;
    $fmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::CURRENCY);
    $fmt->setPattern($pattern ?: $CURRENCY_FORMAT);
    $fmt->setSymbol(\NumberFormatter::CURRENCY_SYMBOL, $CURRENCY_SYMBOL);
    $fmt->setSymbol(\NumberFormatter::MONETARY_SEPARATOR_SYMBOL, $DECIMAL_SEPARATOR);
    $fmt->setSymbol(\NumberFormatter::MONETARY_GROUPING_SEPARATOR_SYMBOL, $GROUPING_SEPARATOR);
    return $fmt->parse($value);
}

/**
 * Format number
 *
 * @param float $value Value
 * @param string $pattern Formatter pattern. If null, keep number of decimal digits.
 * @return string
 */
function FormatNumber($value, $pattern = "")
{
    if (IsFormatted($value) || $value === null) {
        return $value;
    }
    global $CurrentLocale, $NUMBER_FORMAT, $DECIMAL_SEPARATOR, $GROUPING_SEPARATOR;
    $fmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::PATTERN_DECIMAL, $pattern ?: $NUMBER_FORMAT);
    if ($pattern === null) {
        $fmt->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 1000);
    }
    $fmt->setSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, $DECIMAL_SEPARATOR);
    $fmt->setSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL, $GROUPING_SEPARATOR);
    $res = $fmt->format((float)$value);
    return $res !== false ? ConvertDigits($res) : $value;
}

/**
 * Format integer
 *
 * @param int $value Value
 * @return string
 */
function FormatInteger($value)
{
    if (IsFormatted($value) || $value === null) {
        return $value;
    }
    global $CurrentLocale, $DECIMAL_SEPARATOR, $GROUPING_SEPARATOR;
    $fmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::TYPE_INT32); // TYPE_INT64 does not work
    $fmt->setSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, $DECIMAL_SEPARATOR);
    $fmt->setSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL, $GROUPING_SEPARATOR);
    $res = $fmt->format((int)$value);
    return $res !== false ? $res : $value;
}

/**
 * Parse number
 *
 * @param string $value Value
 * @param string $pattern Formatter pattern
 * @return float|false
 */
function ParseNumber($value, $pattern = "")
{
    global $CurrentLocale, $NUMBER_FORMAT, $PERCENT_SYMBOL, $DECIMAL_SEPARATOR, $GROUPING_SEPARATOR;
    if (EmptyValue($value)) {
        return false;
    } elseif (ContainsString($value, $PERCENT_SYMBOL)) {
        return ParsePercent($value, $pattern);
    }
    $fmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::PATTERN_DECIMAL, $pattern ?: $NUMBER_FORMAT);
    $fmt->setSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, $DECIMAL_SEPARATOR);
    $fmt->setSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL, $GROUPING_SEPARATOR);
    return $fmt->parse($value);
}

/**
 * Parse integer
 *
 * @param string $value Value
 * @param string $pattern Formatter pattern
 * @param int $type Integer type (\NumberFormatter::TYPE_INT64 = 2 or \NumberFormatter::TYPE_INT32 = 1)
 * @return int|false
 */
function ParseInteger($value, $pattern = "", $type = 0)
{
    global $CurrentLocale, $NUMBER_FORMAT, $DECIMAL_SEPARATOR, $GROUPING_SEPARATOR;
    $fmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::PATTERN_DECIMAL, $pattern ?: $NUMBER_FORMAT);
    $type = in_array($type, [\NumberFormatter::TYPE_INT64, \NumberFormatter::TYPE_INT32])
        ? $type
        : (PHP_INT_SIZE == 8 ? \NumberFormatter::TYPE_INT64 : \NumberFormatter::TYPE_INT32);
    $fmt->setSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, $DECIMAL_SEPARATOR);
    $fmt->setSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL, $GROUPING_SEPARATOR);
    return $fmt->parse($value, $type);
}

/**
 * Convert string to float (as string)
 *
 * @param string $value Value
 * @param string $pattern Formatter pattern
 * @return string|false
 */
function ConvertToFloatString($v, $pattern = "")
{
    if (EmptyValue($v)) {
        return $v;
    }
    $result = ParseNumber($v, $pattern);
    return $result !== false ? strval($result) : false;
}

/**
 * Format percent
 *
 * @param float $value Value
 * @param string $pattern Formatter pattern
 * @return string
 */
function FormatPercent($value, $pattern = "")
{
    if (EmptyValue($value)) {
        return $value;
    } elseif (IsFormatted($value)) {
        $value = ParseNumber($value);
    }
    global $CurrentLocale, $PERCENT_FORMAT, $PERCENT_SYMBOL, $DECIMAL_SEPARATOR, $GROUPING_SEPARATOR;
    $fmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::PERCENT);
    $fmt->setPattern($pattern ?: $PERCENT_FORMAT);
    $fmt->setSymbol(\NumberFormatter::PERCENT_SYMBOL, $PERCENT_SYMBOL);
    $fmt->setSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, $DECIMAL_SEPARATOR);
    $fmt->setSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL, $GROUPING_SEPARATOR);
    $res = $fmt->format((float)$value);
    return $res !== false ? ConvertDigits($res) : $value;
}

/**
 * Parse percent
 *
 * @param string $value Value (Must match the locale pattern, e.g. "100\xc2\xa0%")
 * @param string $pattern Formatter pattern
 * @return float
 */
function ParsePercent($value, $pattern = "")
{
    global $CurrentLocale, $PERCENT_FORMAT, $PERCENT_SYMBOL, $DECIMAL_SEPARATOR, $GROUPING_SEPARATOR;
    $fmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::PERCENT);
    $fmt->setPattern($pattern ?: $PERCENT_FORMAT);
    $fmt->setSymbol(\NumberFormatter::PERCENT_SYMBOL, $PERCENT_SYMBOL);
    $fmt->setSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, $DECIMAL_SEPARATOR);
    $fmt->setSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL, $GROUPING_SEPARATOR);
    return $fmt->parse($value);
}

// Format sequence number
function FormatSequenceNumber($seq)
{
    global $Language;
    return str_replace("%s", $seq, $Language->phrase("SequenceNumber"));
}

/**
 * Format phone number (https://github.com/giggsey/libphonenumber-for-php/blob/master/docs/PhoneNumberUtil.md)
 *
 * @param string $phoneNumber Phone number (e.g. US mobile: "(415)555-2671")
 * @param bool|string $region Region code (e.g. "US" / "GB" / "FR"), if false, skip formatting
 * @param string $format Phone number format (PhoneNumberFormat::E164/INTERNATIONAL/NATIONAL/RFC3966) (0/1/2/3)
 * @return string
 */
function FormatPhoneNumber($phoneNumber, $region = null, $format = 0)
{
    return $phoneNumber;
}

/**
 * Display field value separator
 *
 * @param int $idx Display field index (1|2|3)
 * @param DbField $fld field object
 * @return string
 */
function ValueSeparator($idx, $fld)
{
    $sep = $fld?->DisplayValueSeparator ?? ", ";
    return is_array($sep) ? @$sep[$idx - 1] : $sep;
}

/**
 * Get temp upload path root
 *
 * @param bool $physical Whether path is physical
 *  If true, return physical path of the temp upload folder root.
 *  If false, return href path of the temp upload folder root.
 * @return string
 */
function UploadTempPathRoot($physical = true)
{
    if ($physical) {
        return (Config("UPLOAD_TEMP_PATH") && Config("UPLOAD_TEMP_HREF_PATH")) ? IncludeTrailingDelimiter(Config("UPLOAD_TEMP_PATH"), true) : UploadPath(true);
    } else { // Href path
        return (Config("UPLOAD_TEMP_PATH") && Config("UPLOAD_TEMP_HREF_PATH")) ? IncludeTrailingDelimiter(Config("UPLOAD_TEMP_HREF_PATH"), false) : UploadPath(false);
    }
}

/**
 * Get temp upload path
 *
 * @param mixed $option Option
 *  If false, return href path of the temp upload folder.
 *  If NULL, return physical path of the temp upload folder.
 *  If string, return physical path of the temp upload folder with the parameter as part of the subpath.
 *  If object (DbField), return physical path of the temp upload folder with tblvar/fldvar as part of the subpath.
 * @param int $idx Index of the field
 * @param bool $tableLevel Table level or field level
 * @return string
 */
function UploadTempPath($option = null, $idx = -1, $tableLevel = false)
{
    global $ExportId;
    if ($option !== false) { // Physical path
        $path = UploadTempPathRoot();
        if (is_string($option)) { // API upload ($option as token)
            $path = IncludeTrailingDelimiter($path . Config("UPLOAD_TEMP_FOLDER_PREFIX") . $option, true);
        } else {
            // Create session id temp folder
            $sessionId = session_id() ?? $ExportId;
            $path = IncludeTrailingDelimiter($path . Config("UPLOAD_TEMP_FOLDER_PREFIX") . $sessionId, true);
            if (!file_exists($path)) {
                if (!CreateFolder($path)) {
                    throw new \Exception("Cannot create folder: " . $path); //** side effect
                }
                if (Config("DEBUG")) {
                    Log("Temp folder '" . $path . "' created"); // Log temp folder create for debug
                }
            }
            if (is_object($fld = $option)) { // Normal upload
                $fldvar = ($idx < 0) ? $fld->FieldVar : substr($fld->FieldVar, 0, 1) . $idx . substr($fld->FieldVar, 1);
                $tblvar = $fld->TableVar;
                $path = IncludeTrailingDelimiter($path . $tblvar, true);
                if (!$tableLevel) {
                    $path = IncludeTrailingDelimiter($path . $fldvar, true);
                }
                // Create field temp folder
                if (!file_exists($path)) {
                    if (!CreateFolder($path)) {
                        throw new \Exception("Cannot create folder: " . $path); //** side effect
                    }
                    if (Config("DEBUG")) {
                        Log("Temp folder '" . $path . "' created for '" . $fld->TableName . "' Field '" . $fld->Name . "'"); // Log temp folder create for debug
                    }
                }
            }
        }
    } else { // Href path
        $path = UploadTempPathRoot(false);
        $path = IncludeTrailingDelimiter($path . Config("UPLOAD_TEMP_FOLDER_PREFIX") . session_id(), false);
    }
    return $path;
}

// Render upload field to temp path
function RenderUploadField(&$fld, $idx = -1)
{
    global $Language, $Table;
    if ($Table?->EventCancelled) { // Skip render if insert/update cancelled
        return;
    }
    global $Language;
    $folder = UploadTempPath($fld, $idx);
    CleanPath($folder); // Clean the upload folder
    $physical = !IsRemote($folder);
    $thumbnailfolder = PathCombine($folder, Config("UPLOAD_THUMBNAIL_FOLDER"), $physical);
    if (!file_exists($thumbnailfolder)) {
        if (!CreateFolder($thumbnailfolder)) {
            throw new \Exception("Cannot create folder: " . $thumbnailfolder); //** side effect
        }
    }
    $imageFileTypes = explode(",", Config("IMAGE_ALLOWED_FILE_EXT"));
    if ($fld->DataType == DataType::BLOB) { // Blob field
        $data = $fld->Upload->DbValue;
        if (!EmptyValue($data)) {
            // Create upload file
            $filename = ($fld->Upload->FileName != "") ? $fld->Upload->FileName : $fld->Param;
            $f = IncludeTrailingDelimiter($folder, $physical) . $filename;
            CreateUploadFile($f, $data);
            // Create thumbnail file
            $f = IncludeTrailingDelimiter($thumbnailfolder, $physical) . $filename;
            $ext = ContentExtension($data);
            if ($ext != "" && in_array(substr($ext, 1), $imageFileTypes)) {
                $width = Config("UPLOAD_THUMBNAIL_WIDTH");
                $height = Config("UPLOAD_THUMBNAIL_HEIGHT");
                ResizeBinary($data, $width, $height);
                CreateUploadFile($f, $data);
            }
            $fld->Upload->FileName = basename($f); // Update file name
        }
    } else { // Upload to folder
        $fld->Upload->FileName = $fld->htmlDecode($fld->Upload->DbValue); // Update file name
        if (!EmptyValue($fld->Upload->FileName)) {
            // Create upload file
            if ($fld->UploadMultiple) {
                $files = explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $fld->Upload->FileName);
            } else {
                $files = [$fld->Upload->FileName];
            }
            foreach ($files as $filename) {
                if ($filename != "") {
                    $pathinfo = pathinfo($filename);
                    $filename = $pathinfo["basename"];
                    $dirname = $pathinfo["dirname"] ?? "";
                    $ext = strtolower($pathinfo["extension"] ?? "");
                    $filepath = ($dirname != "" && $dirname != ".") ? PathCombine($fld->UploadPath, $dirname, !IsRemote($fld->UploadPath)) : $fld->UploadPath;
                    $srcfile = ServerMapPath($filepath) . $filename;
                    $f = IncludeTrailingDelimiter($folder, $physical) . $filename;
                    $tf = IncludeTrailingDelimiter($thumbnailfolder, $physical) . $filename; // Thumbnail
                    if (!is_dir($srcfile) && file_exists($srcfile)) { // File found
                        $data = file_get_contents($srcfile);
                        CreateUploadFile($f, $data);
                        if (in_array($ext, $imageFileTypes)) {
                            $w = Config("UPLOAD_THUMBNAIL_WIDTH");
                            $h = Config("UPLOAD_THUMBNAIL_HEIGHT");
                            ResizeBinary($data, $w, $h); // Resize as thumbnail
                            CreateUploadFile($tf, $data); // Create thumbnail
                        }
                    } else { // File not found
                        $data = Config("FILE_NOT_FOUND");
                        file_put_contents($f, base64_decode($data));
                    }
                }
            }
        }
    }
}

// Write uploaded file
function CreateUploadFile(&$f, $data)
{
    $handle = fopen($f, "w");
    fwrite($handle, $data);
    fclose($handle);
    $pathinfo = pathinfo($f);
    $extension = $pathinfo["extension"] ?? "";
    if ($extension == "") { // No file extension
        $extension = ContentExtension($data);
        if ($extension) {
            rename($f, $f .= $extension);
        }
    }
}

/**
 * Get uploaded file name(s) (as comma separated value) by file token
 *
 * @param string $filetoken File token returned by API
 * @param bool $fullPath Includes full path or not
 * @return string
 */
function GetUploadedFileName($filetoken, $fullPath = true)
{
    return HttpUpload::create()->getUploadedFileName($filetoken, $fullPath);
}

/**
 * Get uploaded file names (as array) by file token
 *
 * @param string $filetoken File token returned by API
 * @param bool $fullPath Includes full path or not
 * @return array
 */
function GetUploadedFileNames($filetoken, $fullPath = true)
{
    return HttpUpload::create()->getUploadedFileNames($filetoken, $fullPath);
}

// Clean temp upload folders
function CleanUploadTempPaths($sessionid = "")
{
    $folder = (Config("UPLOAD_TEMP_PATH")) ? IncludeTrailingDelimiter(Config("UPLOAD_TEMP_PATH"), true) : UploadPath(true);
    $finder = Finder::create()->directories()->in($folder)->name('/^' . preg_quote(Config("UPLOAD_TEMP_FOLDER_PREFIX"), '/') . '/') // Find upload temp folders
        ->sortByName()->reverseSorting();
    foreach ($finder as $dir) {
        $entry = $dir->getFileName(); // Folder name
        if (Config("UPLOAD_TEMP_FOLDER_PREFIX") . $sessionid == $entry) { // Clean session folder
            CleanPath($dir->getRealPath(), true);
        } else {
            if (Config("UPLOAD_TEMP_FOLDER_PREFIX") . session_id() != $entry) {
                $temp = $dir->getRealPath();
                if (IsEmptyPath($temp)) { // Empty folder
                    CleanPath($temp, true);
                } else { // Old folder
                    CleanPath($temp, true, "< now - " . Config("UPLOAD_TEMP_FOLDER_TIME_LIMIT") . " minutes");
                }
            }
        }
    }
}

// Clean temp upload folder
function CleanUploadTempPath($fld, $idx = -1)
{
    // Clean the upload folder
    $path = UploadTempPath($fld, $idx);
    CleanPath($path, true);
    // Remove table temp folder if empty
    $path = UploadTempPath($fld, $idx, true);
    if (IsEmptyPath($path)) {
        CleanPath($path, true);
    }
}

/**
 * Clean folder path
 *
 * @param string $path Folder path
 * @param bool $delete Delete folder path or not
 * @param string $lastModifiedTime Last modified time (e.g. "< now - 10 minutes")
 * @return void
 */
function CleanPath($path, $delete = false, string|array $lastModifiedTime = [])
{
    if (!is_dir($path)) {
        return;
    }
    try {
        $finder = Finder::create()->files()->in($path)->date($lastModifiedTime);
        foreach ($finder as $file) { // Delete files
            $realpath = $file->getRealPath();
            try {
                unlink($realpath);
                if (Config("DEBUG")) {
                    if (file_exists($realpath)) {
                        Log("Failed to delete file '" . $realpath . "'");
                    } else {
                        Log("File '" . $realpath . "' deleted");
                    }
                }
            } catch (\Throwable $e) {
                if (Config("DEBUG")) {
                    Log("Failed to delete file '" . $realpath . "'. Exception: " . $e->getMessage());
                }
            }
        }
        if ($delete) {
            $finder->directories()->in($path)->sortByName()->reverseSorting();
            foreach ($finder as $dir) { // Delete subdirectories
                DeletePath($dir->getRealPath());
            }
            DeletePath($path); // Delete this directory
        }
    } catch (\Throwable $e) {
        if (Config("DEBUG")) {
            throw $e;
        }
    } finally {
        @gc_collect_cycles(); // Forces garbase collection (for S3)
    }
}

/**
 * Delete folder path
 *
 * @param string $path Folder path
 * @return void
 */
function DeletePath($path)
{
    try {
        if (IsEmptyPath($path)) { // Delete directory
            @rmdir($path); // Suppress "Directory not empty" warning
            if (Config("DEBUG")) {
                if (file_exists($path)) {
                    Log("Failed to delete folder '" . $path . "'");
                } else {
                    Log("Folder '" . $path . "' deleted");
                }
            }
        }
    } catch (\Throwable $e) {
        if (Config("DEBUG")) {
            Log("Failed to delete folder '" . $path . "'. Exception: " . $e->getMessage());
        }
    }
}

/**
 * Check if empty folder path
 *
 * @param string $path Folder path
 * @return bool
 */
function IsEmptyPath($path)
{
    return @is_dir($path) && !Finder::create()->files()->in($path)->hasResults();
}

/**
 * Truncate memo field based on specified length, string truncated to nearest whitespace
 *
 * @param string $memostr String to be truncated
 * @param int $maxlen Max. length
 * @param bool $removehtml Remove HTML or not
 * @return string
 */
function TruncateMemo($memostr, $maxlen, $removehtml = false)
{
    $str = $removehtml ? RemoveHtml($memostr) : $memostr;
    $str = preg_replace('/\s+/', " ", $str ?? "");
    $len = strlen($str);
    if ($len > 0 && $len > $maxlen) {
        $i = 0;
        while ($i >= 0 && $i < $len) {
            $j = strpos($str, " ", $i);
            if ($j === false) { // No whitespaces
                return substr($str, 0, $maxlen) . "..."; // Return the first part only
            } else {
                // Get nearest whitespace
                if ($j > 0) {
                    $i = $j;
                }
                // Get truncated text
                if ($i >= $maxlen) {
                    return substr($str, 0, $i) . "...";
                } else {
                    $i++;
                }
            }
        }
    }
    return $str;
}

// Remove HTML tags from text
function RemoveHtml($str)
{
    return preg_replace('/<[^>]*>/', '', strval($str));
}

/**
 * Send SMS message
 *
 * @param string $phoneNumber Phone Number (e.g. US mobile: "(415)555-2671")
 * @param string $content SMS content
 * @param bool|string $region Region code (e.g. "US" / "GB" / "FR"), if false, skip formatting
 * @param string $format Phone number format (PhoneNumberFormat::E164/INTERNATIONAL/NATIONAL/RFC3966) (0/1/2/3)
 * @return bool|string success or error description
 */
function SendSms($phoneNumber, $content, $region = null, $format = 0)
{
    $smsClass = Config("SMS_CLASS");
    $rc = new \ReflectionClass($smsClass);
    if ($rc->isAbstract()) {
        throw new \Exception("Make sure you have enabled an extension for sending SMS messages.");
    }
    $sms = new $smsClass();
    $sms->Content = $content;
    $sms->Recipient = FormatPhoneNumber($phoneNumber, $region, $format);
    $res = $sms->send();
    if ($res !== true && Config("DEBUG")) { // Error
        SetDebugMessage($sms->SendErrDescription);
        Log($sms->SendErrDescription);
    }
    return $res ?: $sms->SendErrDescription; // Return success or error description
}

// Function to send email
function SendEmail($fromEmail, $toEmail, $ccEmail, $bccEmail, $subject, $mailContent, $format, $charset, $smtpSecure = "", $attachments = [], $images = [], $members = null)
{
    global $Language;
    $mail = new PHPMailer();

    // Set up mailer
    $mailer = Config("SMTP.PHPMAILER_MAILER");
    $methods = ["smtp" => "isSMTP", "mail" => "isMail", "sendmail" => "isSendmail", "qmail" => "isQmail"];
    $method = $methods[$mailer] ?? "isSMTP";
    $mail->$method();

    // Set up server settings
    $smtpServerUsername = Config("ENCRYPT_USER_NAME_AND_PASSWORD") ? PhpDecrypt(Config("SMTP.SERVER_USERNAME")) : Config("SMTP.SERVER_USERNAME");
    $smtpServerPassword = Config("ENCRYPT_USER_NAME_AND_PASSWORD") ? PhpDecrypt(Config("SMTP.SERVER_PASSWORD")) : Config("SMTP.SERVER_PASSWORD");
    $mail->Host = Config("SMTP.SERVER");
    $mail->SMTPAuth = $smtpServerUsername != "" && $smtpServerPassword != "";
    $mail->Username = $smtpServerUsername;
    $mail->Password = $smtpServerPassword;
    $mail->Port = Config("SMTP.SERVER_PORT");
    if (Config("DEBUG")) {
        $mail->SMTPDebug = 2; // DEBUG_SERVER
        $mail->Debugoutput = Logger();
    }
    if ($smtpSecure != "") {
        $mail->SMTPSecure = $smtpSecure;
        $mail->SMTPOptions = ["ssl" => ["verify_peer" => false, "verify_peer_name" => false, "allow_self_signed" => true]];
    }
    if (preg_match('/^(.+)<([\w.%+-]+@[\w.-]+\.[A-Z]{2,6})>$/i', trim($fromEmail), $m)) {
        $mail->From = $m[2];
        $mail->FromName = trim($m[1]);
    } else {
        $mail->From = $fromEmail;
        $mail->FromName = $fromEmail;
    }
    $mail->Subject = $subject;
    if (SameText($format, "html")) {
        $mail->isHTML(true);
        $mail->Body = $mailContent;
    } else {
        $mail->isHTML(false);
        if (strip_tags($mailContent) != $mailContent) { // Contains HTML tags
            $mail->Body = HtmlToText($mailContent);
        } else {
            $mail->Body = $mailContent;
        }
    }
    if ($charset && !SameText($charset, "iso-8859-1")) {
        $mail->CharSet = $charset;
    }
    $toEmail = str_replace(";", ",", $toEmail);
    $arTo = explode(",", $toEmail);
    foreach ($arTo as $to) {
        if (preg_match('/^(.+)<([\w.%+-]+@[\w.-]+\.[A-Z]{2,6})>$/i', trim($to), $m)) {
            $mail->addAddress($m[2], trim($m[1]));
        } else {
            $mail->addAddress(trim($to));
        }
    }
    if ($ccEmail != "") {
        $ccEmail = str_replace(";", ",", $ccEmail);
        $arCc = explode(",", $ccEmail);
        foreach ($arCc as $cc) {
            if (preg_match('/^(.+)<([\w.%+-]+@[\w.-]+\.[A-Z]{2,6})>$/i', trim($cc), $m)) {
                $mail->addCC($m[2], trim($m[1]));
            } else {
                $mail->addCC(trim($cc));
            }
        }
    }
    if ($bccEmail != "") {
        $bccEmail = str_replace(";", ",", $bccEmail);
        $arBcc = explode(",", $bccEmail);
        foreach ($arBcc as $bcc) {
            if (preg_match('/^(.+)<([\w.%+-]+@[\w.-]+\.[A-Z]{2,6})>$/i', trim($bcc), $m)) {
                $mail->addBCC($m[2], trim($m[1]));
            } else {
                $mail->addBCC(trim($bcc));
            }
        }
    }
    if (is_array($attachments)) {
        foreach ($attachments as $attachment) {
            $filename = $attachment["filename"] ?? "";
            $content = $attachment["content"] ?? "";
            if ($content != "" && $filename != "") {
                $mail->addStringAttachment($content, $filename);
            } elseif ($filename != "") {
                $mail->addAttachment($filename);
            }
        }
    }
    if (is_array($images)) {
        foreach ($images as $tmpImage) {
            $file = UploadTempPath() . $tmpImage;
            $cid = pathinfo($tmpImage, PATHINFO_FILENAME); // Remove extension (filename as cid)
            $mail->addEmbeddedImage($file, $cid, $tmpImage);
        }
    }
    if (is_array($members)) {
        foreach ($members as $name => $value) {
            if (property_exists($mail, $name)) {
                $mail->set($name, $value);
            } elseif (method_exists($mail, $name)) {
                if (!$value) {
                    $value = [];
                } elseif (!is_array($value)) {
                    $value = [$value];
                }
                $mail->$name(...$value);
            }
        }
    }
    $res = $mail->send() ?: $mail->ErrorInfo;
    if ($res !== true) { // Error
        // Always log send failures (not just under DEBUG) — ResetPassword.php
        // and friends intentionally show the user a generic success message
        // regardless of real delivery outcome (anti-enumeration), so without
        // this the actual SMTP error (auth failure, wrong port, connection
        // refused, etc.) was never recorded anywhere, making "email never
        // arrives" impossible to diagnose. See log/log.log.
        LogError("SendEmail failed: " . $res);
        if (Config("DEBUG")) {
            SetDebugMessage($res, $mail->SMTPDebug);
            Log($res);
        }
    }
    return $res; // True on success, error info on error
}

/**
 * Field data type
 *
 * @param int $fldtype Field type
 * @return DataType
 */
function FieldDataType($fldtype)
{
    switch ($fldtype) {
        case 20: // BigInt
        case 3: // Integer
        case 2:  // SmallInt
        case 16: // TinyInt
        case 4: // Single
        case 5: // Double
        case 131: // Numeric
        case 139: // VarNumeric
        case 6: // Currency
        case 17: // UnsignedTinyInt
        case 18: // UnsignedSmallInt
        case 19: // UnsignedInt
        case 21: // UnsignedBigInt
            return DataType::NUMBER;
        case 7:
        case 133:
        case 135: // Date
        case 146: // DateTimeOffset
            return DataType::DATE;
        case 134: // Time
        case 145: // Time
            return DataType::TIME;
        case 201:
        case 203: // Memo
            return DataType::MEMO;
        case 129:
        case 130:
        case 200:
        case 202: // String
            return DataType::STRING;
        case 11: // Boolean
            return DataType::BOOLEAN;
        case 72: // GUID
            return DataType::GUID;
        case 128:
        case 204:
        case 205: // Binary
            return DataType::BLOB;
        case 141: // XML
            return DataType::XML;
        default:
            return DataType::OTHER;
    }
}

// Field query builder data type
function FieldQueryBuilderDataType($fldtype)
{
    switch ($fldtype) {
        case 20:
        case 3:
        case 2:
        case 16:
        case 17:
        case 18:
        case 19:
        case 21: // Integer
            return "intger";
        case 4:
        case 5:
        case 131:
        case 6:
        case 139: // Double
            return "double";
        case 7:
        case 133:
        case 135: // Date
        case 146: // DateTimeOffset
        case 134: // Time
        case 145: // Time
            return "datetime";
        default:
            return "string";
    }
}

/**
 * Root relative path
 *
 * @return string Root relative path
 */
function RootRelativePath()
{
    global $RELATIVE_PATH;
    return $RELATIVE_PATH;
}

/**
 * Application root
 *
 * @param bool $phyPath
 * @return string Path of the application root
 */
function AppRoot($phyPath)
{
    $root = RootRelativePath(); // Use root relative path
    if ($phyPath) {
        $path = realpath($root ?: ".");
        $path = preg_replace('/(?<!^)\\\\\\\\/', PATH_DELIMITER, $path); // Replace '\\' (not at the start of path) by path delimiter
    } else {
        $path = $root;
    }
    return IncludeTrailingDelimiter($path, $phyPath);
}

/**
 * Upload path
 *
 * @param bool $phyPath Physical path or not
 * @param string $destPath Destination path
 * @return string If $phyPath is true, return physical path on the server. If $phyPath is false, return relative URL.
 */
function UploadPath($phyPath, $destPath = "")
{
    $destPath = $destPath ?: Config("UPLOAD_DEST_PATH");
    if (IsRemote($destPath)) { // Remote
        $path = $destPath;
        $phyPath = false;
    } elseif ($phyPath) { // Physical
        $destPath = str_replace("/", PATH_DELIMITER, $destPath);
        $path = PathCombine(AppRoot(true), $destPath, true);
    } else { // Relative
        $path = PathCombine(AppRoot(false), $destPath, false);
    }
    return IncludeTrailingDelimiter($path, $phyPath);
}

// Get physical path relative to application root
function ServerMapPath($path, $isFile = false)
{
    $pathinfo = IsRemote($path) ? [] : pathinfo($path);
    if ($isFile && @$pathinfo["basename"] != "" || @$pathinfo["extension"] != "") { // File
        return UploadPath(true, $pathinfo["dirname"]) . $pathinfo["basename"];
    } else { // Folder
        return UploadPath(true, $path);
    }
}

/**
 * Log path (physical)
 *
 * @return string
 */
function LogPath()
{
    return UploadPath(true, Config("LOG_PATH"));
}

// Write info for config/debug only
function Info()
{
    echo "UPLOAD_DEST_PATH = " . Config("UPLOAD_DEST_PATH") . "<br>";
    echo "AppRoot(true) = " . AppRoot(true) . "<br>";
    echo "AppRoot(false) = " . AppRoot(false) . "<br>";
    echo "realpath('.') = " . realpath(".") . "<br>";
    echo "DOCUMENT_ROOT = " . ServerVar("DOCUMENT_ROOT") . "<br>";
    echo "__FILE__ = " . __FILE__ . "<br>";
    echo "CurrentUserName() = " . CurrentUserName() . "<br>";
    echo "CurrentUserID() = " . CurrentUserID() . "<br>";
    echo "CurrentParentUserID() = " . CurrentParentUserID() . "<br>";
    echo "IsLoggedIn() = " . (IsLoggedIn() ? "true" : "false") . "<br>";
    echo "IsAdmin() = " . (IsAdmin() ? "true" : "false") . "<br>";
    echo "IsSysAdmin() = " . (IsSysAdmin() ? "true" : "false") . "<br>";
    Security()->showUserLevelInfo();
}

/**
 * Generate a unique file name for a folder (filename(n).ext)
 *
 * @param string|string[] $folders Output folder(s)
 * @param string $orifn Original file name
 * @param bool $indexed Index starts from '(n)' at the end of the original file name
 * @return string
 */
function UniqueFilename($folders, $orifn, $indexed = false)
{
    if ($orifn == "") {
        $orifn = date("YmdHis") . ".bin";
    }
    $fn = $orifn;
    $info = pathinfo($fn);
    $filename = $info["filename"];
    $ext = $info["extension"];
    $i = 1;
    if ($indexed && preg_match('/\((\d+)\)$/', $filename, $matches)) { // Match '(n)' at the end of the file name
        $i = (int)$matches[1];
        $filename = preg_replace('/\(\d+\)$/', '', $filename); // Remove "(n)" at the end of the file name
    }
    $folders = is_array($folders) ? $folders : [$folders];
    foreach ($folders as $folder) {
        $destpath = $folder . $fn;
        if (!file_exists($folder) && !CreateFolder($folder)) {
            throw new \Exception("Folder does not exist: " . $folder); //** side effect
        }
        while (file_exists(Convert(PROJECT_ENCODING, FILE_SYSTEM_ENCODING, $destpath))) {
            $i++;
            $fn = $filename . "(" . $i . ")" . ($ext ? "." . $ext : "");
            $destpath = $folder . $fn;
        }
    }
    return $fn;
}

/**
 * Fix upload temp file names (avoid duplicate)
 *
 * @param DbField $fld Field object
 */
function FixUploadTempFileNames($fld)
{
    if ($fld->UploadMultiple) {
        $newFiles = explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $fld->Upload->FileName);
        $tempPath = UploadTempPath($fld, $fld->Upload->Index);
        $newFileCount = count($newFiles);
        for ($i = $newFileCount - 1; $i >= 0; $i--) {
            $newFile = $newFiles[$i];
            if (!EmptyValue($newFile)) {
                $tempFile = $newFile;
                for ($j = $i - 1; $j >= 0; $j--) { // Temp files with same names
                    if ($newFiles[$j] == $tempFile) {
                        $tempFile = UniqueFilename($tempPath, $newFile, true);
                    }
                }
                if ($tempFile != $newFile) { // Create a copy
                    CopyFile($tempPath, $tempFile, $tempPath . $newFile);
                }
                $newFiles[$i] = $tempFile;
            }
        }
        $fld->Upload->FileName = implode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $newFiles);
    }
}

/**
 * Fix upload file names (avoid duplicate files on upload folder)
 *
 * @param DbField $fld Field object
 */
function FixUploadFileNames($fld)
{
    $newFiles = $fld->UploadMultiple
        ? explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $fld->Upload->FileName)
        : [ $fld->Upload->FileName ];
    $oldFiles = EmptyValue($fld->Upload->DbValue) ? [] : ($fld->UploadMultiple
        ? explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $fld->htmlDecode($fld->Upload->DbValue))
        : [ $fld->htmlDecode($fld->Upload->DbValue) ]);
    $tempPath = UploadTempPath($fld, $fld->Upload->Index);
    $workPath = IncludeTrailingDelimiter($tempPath . "__work", true);
    if (!CreateFolder($workPath)) {
        throw new \Exception("Cannot create folder: " . $workPath); //** side effect
    }
    $newFileCount = count($newFiles);
    for ($i = 0; $i < $newFileCount; $i++) {
        if (!EmptyValue($newFiles[$i])) {
            $file = $newFiles[$i];
            if (file_exists($tempPath . $file)) {
                $oldFileFound = false;
                $oldFileCount = count($oldFiles);
                for ($j = 0; $j < $oldFileCount; $j++) {
                    $oldFile = $oldFiles[$j];
                    if ($oldFile == $file) { // Old file found, no need to delete anymore
                        array_splice($oldFiles, $j, 1);
                        $oldFileFound = true;
                        break;
                    }
                }
                if ($oldFileFound) { // No need to check if file exists further
                    continue;
                }
                rename($tempPath . $file, $workPath . $file); // Move to work folder before checking
                $file1 = UniqueFilename([$fld->physicalUploadPath(), $tempPath], $file, true); // Get new file name
                if ($file1 != $file) { // Rename temp file
                    rename($workPath . $file, $tempPath . $file1);
                    $newFiles[$i] = $file1;
                } else { // Move back
                    rename($workPath . $file, $tempPath . $file);
                }
            }
        }
    }
    //DeletePath($workPath);
    $fld->Upload->DbValue = implode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $oldFiles);
    $fld->Upload->FileName = implode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $newFiles);
}

/**
 * Save upload files to upload folder
 *
 * @param DbField $fld Field object
 * @param string $fileNames File names
 * @param bool $resize Resize file
 * @return bool
 */
function SaveUploadFiles($fld, $fileNames, $resize)
{
    $tempPath = UploadTempPath($fld, $fld->Upload->Index);
    $newFiles = $fld->UploadMultiple
        ? explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $fld->Upload->FileName)
        : [ $fld->Upload->FileName ];
    if (!EmptyValue($fld->Upload->FileName)) {
        if (SameString($fld->Upload->FileName, $fileNames)) { // Not changed in server event
            $fileNames = "";
        }
        $newFiles2 = EmptyValue($fileNames) ? [] : ($fld->UploadMultiple
            ? explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $fileNames)
            : [ $fileNames ]);
        $newFileCount = count($newFiles);
        for ($i = 0; $i < $newFileCount; $i++) {
            $newFile = $newFiles[$i];
            $newFile2 = count($newFiles2) > $i ? $newFiles2[$i] : "";
            if (!EmptyValue($newFile)) {
                $file = $tempPath . $newFile;
                if (file_exists($file)) {
                    if (!EmptyValue($newFile2)) { // Use correct file name
                        $newFile = $newFile2;
                    }
                    $res = $resize
                        ? $fld->Upload->resizeAndSaveToFile($fld->ImageWidth, $fld->ImageHeight, 100, $newFile, true, $i) // Resize
                        : $fld->Upload->saveToFile($newFile, true, $i); // Save
                    if (!$res) {
                        return false;
                    }
                }
            }
        }
    }
    if (Config("DELETE_UPLOADED_FILES")) {
        $oldFiles = EmptyValue($fld->Upload->DbValue) ? [] : ($fld->UploadMultiple
            ? explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $fld->htmlDecode($fld->Upload->DbValue))
            : [ $fld->htmlDecode($fld->Upload->DbValue) ]);
        foreach ($oldFiles as $oldFile) {
            if (!EmptyValue($oldFile) && !in_array($oldFile, $newFiles)) {
                @unlink($fld->oldPhysicalUploadPath() . $oldFile);
            }
        }
    }
    return true;
}

// Get refer URL
function ReferUrl()
{
    $url = ServerVar("HTTP_REFERER");
    $pattern = '/^' . preg_quote(DomainUrl(), '/') . '(?=\/)/';
    if (preg_match($pattern, $url)) {
        $url = preg_replace($pattern, "", $url);
    }
    return $url;
}

// Get refer page name
function ReferPageName()
{
    return GetPageName(ReferUrl());
}

// Get script physical folder
function ScriptFolder()
{
    $folder = "";
    $path = ServerVar("SCRIPT_FILENAME");
    $p = strrpos($path, PATH_DELIMITER);
    if ($p !== false) {
        $folder = substr($path, 0, $p);
    }
    return ($folder != "") ? $folder : realpath(".");
}

// Get a temp folder for temp file
function TempFolder()
{
    $folders = [];
    if (IS_WINDOWS) {
        $folders[] = ServerVar("TEMP");
        $folders[] = ServerVar("TMP");
    } else {
        if (Config("USER_UPLOAD_TEMP_PATH") != "") {
            $folders[] = ServerMapPath(Config("USER_UPLOAD_TEMP_PATH"));
        }
        $folders[] = '/tmp';
    }
    if (ini_get("upload_tmp_dir")) {
        $folders[] = ini_get("upload_tmp_dir");
    }
    if ($folder = array_find($folders, fn($folder) => is_dir($folder))) {
        return $folder;
    }
    return null;
}

/**
 * Create folder
 *
 * AWS SDK maps mode 7xx to ACL_PUBLIC, 6xx to ACL_AUTH_READ and others to ACL_PRIVATE.
 * mkdir() does not use the 3rd argument.
 * If bucket key not found, createBucket(), otherwise createSubfolder().
 * See https://github.com/aws/aws-sdk-php/blob/master/src/S3/StreamWrapper.php
 *
 * @param string $dir Directory
 * @param int $mode Permissions
 * @return bool
 */
function CreateFolder($dir, $mode = 0)
{
    return is_dir($dir) || ($mode ? @mkdir($dir, $mode, true) : (@mkdir($dir, 0777, true) || @mkdir($dir, 0666, true) || @mkdir($dir, 0444, true)));
}

// Save file
function SaveFile($folder, $fn, $filedata)
{
    $fn = Convert(PROJECT_ENCODING, FILE_SYSTEM_ENCODING, $fn);
    $res = false;
    if (CreateFolder($folder)) {
        $file = IncludeTrailingDelimiter($folder, true) . $fn;
        if (IsRemote($file)) { // Support S3 only
            $res = file_put_contents($file, $filedata);
        } else {
            $res = file_put_contents($file, $filedata, Config("SAVE_FILE_OPTIONS"));
        }
        if ($res !== false) {
            @chmod($file, Config("UPLOADED_FILE_MODE"));
        }
    }
    return $res;
}

// Copy file
function CopyFile($folder, $fn, $file)
{
    $fn = Convert(PROJECT_ENCODING, FILE_SYSTEM_ENCODING, $fn);
    if (file_exists($file)) {
        if (CreateFolder($folder)) {
            $newfile = IncludeTrailingDelimiter($folder, true) . $fn;
            return copy($file, $newfile);
        }
    }
    return false;
}

/**
 * Generate random number
 *
 * @param int $length Number of digits
 * @return int
 */
function Random($length = 8)
{
    $min = pow(10, $length - 1);
    $max = pow(10, $length) - 1;
    return mt_rand($min, $max);
}

// Calculate field hash
function GetFieldHash($value)
{
    return md5(GetFieldValueAsString($value));
}

// Get field value as string
function GetFieldValueAsString($value)
{
    if ($value === null || is_resource($value)) {
        return "";
    }
    if (strlen($value) > 65535) { // BLOB/TEXT
        if (Config("BLOB_FIELD_BYTE_COUNT") > 0) {
            return substr($value, 0, Config("BLOB_FIELD_BYTE_COUNT"));
        } else {
            return $value;
        }
    }
    return strval($value);
}

// Create file with unique file name
function TempFileName($folder, $prefix)
{
    if (IsRemote($folder)) {
        $file = $folder . $prefix . dechex(mt_rand(0, 65535)) . ".tmp";
        file_put_contents($file, ""); // Add a blank file
        return $file;
    }
    return @tempnam($folder, $prefix);
}

/**
 * Create temp image file from binary data and return cid or base64 URL
 *
 * @param string $filedata File data
 * @param string $cid Output as cid URL, otherwise as base64 URL
 * @return string cid or base64 URL
 */
function TempImage($filedata, bool $cid = false)
{
    global $TempImages;
    $folder = UploadTempPath();
    $file = TempFileName($folder, "tmp");
    $handle = fopen($file, "w");
    fwrite($handle, $filedata);
    fclose($handle);
    $ct = MimeContentType($file);
    if (!in_array($ct, ["image/gif", "image/jpeg", "image/png", "image/bmp"])) {
        return "";
    }
    $ext = "." . MimeTypes()->getExtensions($ct)[0];
    rename($file, $file .= $ext);
    $tmpimage = basename($file);
    $TempImages[] = $tmpimage;
    if ($cid) {
        return "cid:" . pathinfo($tmpimage, PATHINFO_FILENAME); // Temp image as cid URL
    } else {
        return ImageFileToBase64Url($file); // Temp image as base64 URL
    }
}

// Get image tag from base64 data URL (data:mime type;base64,image data)
function ImageFileToBase64Url($imageFile)
{
    if (!file_exists($imageFile)) { // File not found, ignore
        return $imageFile;
    }
    return "data:" . MimeContentType($imageFile) . ";base64," . base64_encode(file_get_contents($imageFile));
}

// Extract data from base64 data URL (data:mime type;base64,image data)
function DataFromBase64Url($dataUrl)
{
    return StartsString("data:", $dataUrl) && ContainsString($dataUrl, ";base64,")
        ? base64_decode(substr($dataUrl, strpos($dataUrl, ";base64,") + 8))
        : null;
}

// Get temp image from base64 data URL (data:mime type;base64,image data)
function TempImageFromBase64Url($dataUrl)
{
    $data = DataFromBase64Url($dataUrl);
    if ($data) {
        $fn = Random() . ContentExtension($data);
        SaveFile(UploadTempPath(), $fn, $data);
        $dataUrl = UploadTempPath() . $fn;
    }
    return $dataUrl;
}

// Add query string to URL
function UrlAddQuery($url, $qry)
{
    if (strval($qry) == "") {
        return $url;
    }
    return $url . (ContainsString($url, "?") ? "&" : "?") . $qry;
}

// Add "hash" parameter to URL
function UrlAddHash($url, $hash)
{
    return UrlAddQuery($url, "hash=" . $hash);
}

/**
 * Functions for image resize
 */

// Resize binary to thumbnail
function ResizeBinary(&$filedata, &$width, &$height, $quality = 100, $plugins = [], $resizeOptions = [])
{
    if ($width <= 0 && $height <= 0) {
        return false;
    }
    if (is_array($quality) && count($plugins) == 0) {
        $plugins = $quality;
    }
    $f = @tempnam(TempFolder(), "tmp");
    $handle = @fopen($f, 'wb');
    if ($handle) {
        fwrite($handle, $filedata);
        fclose($handle);
    }
    $format = "";
    if (file_exists($f) && filesize($f) > 0) { // Temp file created
        $info = @getimagesize($f);
        @gc_collect_cycles();
        @unlink($f);
        if (!$info || !in_array($info[2], [1, 2, 3])) { // Not gif/jpg/png
            return false;
        } elseif ($info[2] == 1) {
            $format = "GIF";
        } elseif ($info[2] == 2) {
            $format = "JPG";
        } elseif ($info[2] == 3) {
            $format = "PNG";
        }
    } else { // Temp file not created
        if (StartsString("\x47\x49\x46\x38\x37\x61", $filedata) || StartsString("\x47\x49\x46\x38\x39\x61", $filedata)) {
            $format = "GIF";
        } elseif (StartsString("\xFF\xD8\xFF\xE0", $filedata) && substr($filedata, 6, 5) == "\x4A\x46\x49\x46\x00") {
            $format = "JPG";
        } elseif (StartsString("\x89\x50\x4E\x47\x0D\x0A\x1A\x0A", $filedata)) {
            $format = "PNG";
        } else {
            return false;
        }
    }
    $cls = Config("THUMBNAIL_CLASS");
    $options = array_merge(Config("RESIZE_OPTIONS") + ["isDataStream" => true, "format" => $format], $resizeOptions);
    $thumb = new $cls($filedata, $options, $plugins);
    return $thumb->resizeEx($filedata, $width, $height);
}

// Resize file to thumbnail file
function ResizeFile($fn, $tn, &$width, &$height, $plugins = [], $resizeOptions = [])
{
    $info = @getimagesize($fn);
    if (!$info || !in_array($info[2], [1, 2, 3]) || $width <= 0 && $height <= 0) {
        if ($fn != $tn) {
            copy($fn, $tn);
        }
        return;
    }
    $cls = Config("THUMBNAIL_CLASS");
    $resizeOptions = array_merge(Config("RESIZE_OPTIONS"), $resizeOptions);
    $thumb = new $cls($fn, $resizeOptions, $plugins);
    $fdata = null;
    if (!$thumb->resizeEx($fdata, $width, $height, $tn)) {
        if ($fn != $tn) {
            copy($fn, $tn);
        }
    }
}

// Resize file to binary
function ResizeFileToBinary($fn, &$width, &$height, $plugins = [])
{
    $info = @getimagesize($fn);
    if (!$info) {
        return null;
    }
    if (!in_array($info[2], [1, 2, 3]) || $width <= 0 && $height <= 0) {
        $fdata = file_get_contents($fn);
    } else {
        $cls = Config("THUMBNAIL_CLASS");
        $thumb = new $cls($fn, Config("RESIZE_OPTIONS"), $plugins);
        $fdata = null;
        if (!$thumb->resizeEx($fdata, $width, $height)) {
            $fdata = file_get_contents($fn);
        }
    }
    return $fdata;
}

/**
 * Functions for Auto-Update fields
 */

// Get user IP
function CurrentUserIP()
{
    return ServerVar("HTTP_CLIENT_IP") ?: ServerVar("HTTP_X_FORWARDED_FOR") ?: ServerVar("HTTP_X_FORWARDED") ?:
        ServerVar("HTTP_FORWARDED_FOR") ?: ServerVar("HTTP_FORWARDED") ?: ServerVar("REMOTE_ADDR");
}

// Is local host
function IsLocal()
{
    return in_array(CurrentUserIP(), ["127.0.0.1", "::1"]);
}

// Get current host name, e.g. "www.mycompany.com"
function CurrentHost()
{
    return ServerVar("HTTP_HOST");
}

// Get current Windows user (for Windows Authentication)
function CurrentWindowsUser()
{
    return ServerVar("AUTH_USER"); // REMOTE_USER or LOGON_USER or AUTH_USER
}

/**
 * Get current date in default date format
 *
 * @param int $namedformat Format = -1|5|6|7 (see comment for FormatDateTime)
 * @return string
 */
function CurrentDate($namedformat = -1)
{
    if (in_array($namedformat, [5, 6, 7, 9, 10, 11, 12, 13, 14, 15, 16, 17])) {
        if ($namedformat == 5 || $namedformat == 9 || $namedformat == 12 || $namedformat == 15) {
            $dt = FormatDateTime(date('Y-m-d'), 5);
        } elseif ($namedformat == 6 || $namedformat == 10 || $namedformat == 13 || $namedformat == 16) {
            $dt = FormatDateTime(date('Y-m-d'), 6);
        } else {
            $dt = FormatDateTime(date('Y-m-d'), 7);
        }
        return $dt;
    } else {
        return date('Y-m-d');
    }
}

// Get current time in hh:mm:ss format
function CurrentTime()
{
    return date("H:i:s");
}

/**
 * Get current date in default date format with time in hh:mm:ss format
 *
 * @param int $namedformat Format = -1, 5-7, 9-11 (see comment for FormatDateTime)
 * @return string
 */
function CurrentDateTime($namedformat = -1)
{
    if (in_array($namedformat, [5, 6, 7, 9, 10, 11, 12, 13, 14, 15, 16, 17])) {
        if ($namedformat == 5 || $namedformat == 9 || $namedformat == 12 || $namedformat == 15) {
            $dt = FormatDateTime(date('Y-m-d H:i:s'), 9);
        } elseif ($namedformat == 6 || $namedformat == 10 || $namedformat == 13 || $namedformat == 16) {
            $dt = FormatDateTime(date('Y-m-d H:i:s'), 10);
        } else {
            $dt = FormatDateTime(date('Y-m-d H:i:s'), 11);
        }
        return $dt;
    } else {
        return date('Y-m-d H:i:s');
    }
}

// Get current date in standard format (yyyy/mm/dd)
function StdCurrentDate()
{
    return date('Y/m/d');
}

// Get date in standard format (yyyy/mm/dd)
function StdDate($ts)
{
    return date('Y/m/d', $ts);
}

// Get current date and time in standard format (yyyy/mm/dd hh:mm:ss)
function StdCurrentDateTime()
{
    return date('Y/m/d H:i:s');
}

// Get date/time in standard format (yyyy/mm/dd hh:mm:ss)
function StdDateTime($ts)
{
    return date('Y/m/d H:i:s', $ts);
}

// Get current date and time in database format (yyyy-mm-dd hh:mm:ss)
function DbCurrentDateTime()
{
    return date('Y-m-d H:i:s');
}

// Encrypt password
function EncryptPassword($input, $salt = "")
{
    if (Config("PASSWORD_HASH")) {
        return password_hash($input, PASSWORD_DEFAULT);
    } else {
        return strval($salt) != "" ? md5($input . $salt) . ":" . $salt : md5($input);
    }
}

/**
 * Compare password
 * Note: If salted, password must be stored in '<hashedstring>:<salt>'
 *
 * @param string $pwd Password to compare
 * @param string $input Input password
 * @return bool
 */
function ComparePassword($pwd, $input)
{
    if (preg_match('/^\$[HP]\$/', $pwd ?? "")) { // phpass
        $ar = Config("PHPASS_ITERATION_COUNT_LOG2");
        foreach ($ar as $i) {
            $hasher = new PasswordHash($i, true);
            if ($hasher->checkPassword($input, $pwd)) {
                return true;
            }
        }
        return false;
    } elseif (ContainsString($pwd, ":")) { // <hashedstring>:<salt>
        @list($crypt, $salt) = explode(":", $pwd, 2);
        if ($pwd == EncryptPassword($input, $salt)) {
            return true;
        }
    }
    if (Config("CASE_SENSITIVE_PASSWORD")) {
        if (Config("ENCRYPTED_PASSWORD")) {
            if (Config("PASSWORD_HASH")) {
                return password_verify($input, $pwd);
            } else {
                return ($pwd == EncryptPassword($input));
            }
        } else {
            return ($pwd == $input);
        }
    } else {
        if (Config("ENCRYPTED_PASSWORD")) {
            if (Config("PASSWORD_HASH")) {
                return password_verify(strtolower($input), $pwd);
            } else {
                return ($pwd == EncryptPassword(strtolower($input)));
            }
        } else {
            return SameText($pwd, $input);
        }
    }
}

// Get security object
function Security()
{
    global $Security;
    return $Security ??= Container("app.security") ?? new AdvancedSecurity();
}

/**
 * Session helper
 *
 * @return mixed Session value or HttpSession
 */
function Session(...$args)
{
    $numargs = count($args);
    if ($numargs == 0) { // Get HttpSession
        global $Session;
        return $Session ??= Container("app.session");
    } elseif ($numargs == 1) { // Get/Merge
        if (is_string($args[0])) { // Get
            return $_SESSION[$args[0]] ?? null;
        } elseif (is_array($args[0])) { // Merge
            $_SESSION = array_merge_recursive($_SESSION, $args[0]);
            return;
        }
    } elseif ($numargs == 2) { // Set
        $_SESSION[$args[0]] = $args[1];
        return;
    }
}

// Get SSO state store for SAML
function SsoStateStore()
{
    return Container("sso.state.store");
}

// Get session processor for SAML
function SessionProcessor()
{
    return Container("session.processor");
}

/**
 * Current user
 *
 * @return User entity
 */
function CurrentUser()
{
    $user = Container("app.user");
    if ($user) {
        return $user;
    }
    $pk = CurrentUserPrimaryKey();
    if (!EmptyValue($pk)) {
        $user = GetUserRepository()->find($pk);
        if ($user) {
            Container("app.user", $user);
            return $user;
        }
    }
    return null;
}

/**
 * Get/Set profile value
 *
 * @param array $args
 *   If no arguments, returns UserProfile instance.
 *   If $args[0] is true, returns user profile as associative array.
 *   If $args[0] is false, returns user profile as object.
 *   If count($args) is 2, set profile value and save to session.
 * @return mixed
 */
function Profile(...$args)
{
    $profile = Container("user.profile");
    if ($profile) {
        $numargs = count($args);
        if ($numargs == 1) { // Get value
            if (is_string($args[0])) { // $args[0] is $name(string)
                return $profile->get($args[0]); // Return mixed
            } elseif (is_bool($args[0])) { // $args[0] is $associative(bool)
                return $args[0]
                    ? $profile->toArray() // Return all values as associative array
                    : $profile->toObject(); // Return all values as object
            }
        } elseif ($numargs == 2) { // Set value
            $profile->set($args[0], $args[1])->saveToStorage(); // $args[0] is $name(string), $args[0] is $value(mixed)
        }
    }
    return $profile; // Return UserProfile instance
}

// Get language object
function Language()
{
    return Container("app.language");
}

// Get breadcrumb object
function Breadcrumb()
{
    return $GLOBALS["Breadcrumb"];
}

// Get logger
function Logger()
{
    return Container("app.logger");
}

/**
 * Adds a log record at the DEBUG level
 *
 * @param string $message The log message
 * @param array  $context The log context
 */
function Log($msg, array $context = [])
{
    Logger()->debug($msg, $context);
}

/**
 * Adds a log record at the ERROR level
 *
 * @param string $message The log message
 * @param array  $context The log context
 */
function LogError($msg, array $context = [])
{
    Logger()->error($msg, $context);
}

/**
 * Functions for backward compatibility
 */

// Get current user name
function CurrentUserName()
{
    return isset($_SESSION[SESSION_USER_NAME]) ? strval($_SESSION[SESSION_USER_NAME]) : Security()->currentUserName();
}

// Get current user ID
function CurrentUserID()
{
    return Security()->currentUserID();
}

// Get current user primary key
function CurrentUserPrimaryKey()
{
    return Security()->currentUserPrimaryKey();
}

// Get current user identifier (user ID or user name)
function CurrentUserIdentifier()
{
    global $Language;
    if (Config("LOG_USER_ID")) { // User ID
        $usr = CurrentUserID();
        if (!isset($usr) || EmptyValue($usr)) { // Assume Administrator or Anonymous user
            $usr = IsSysAdmin() ? AdvancedSecurity::ADMIN_USER_LEVEL_ID : AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID;
        }
    } else { // User name
        $usr = CurrentUserName();
        if (EmptyValue($usr)) { // Assume Administrator or Anonymous user
            $usr = IsSysAdmin() ? $Language->phrase("UserAdministrator") : $Language->phrase("UserAnonymous");
        }
    }
    return $usr;
}

// Get current parent user ID
function CurrentParentUserID()
{
    return Security()->currentParentUserID();
}

// Get current user level
function CurrentUserLevel()
{
    return Security()->currentUserLevelID();
}

// Get current user level name
function CurrentUserLevelName()
{
    return Security()->currentUserLevelName();
}

// Get current user level list
function CurrentUserLevelList()
{
    return Security()->userLevelList();
}

// Get Current user info
function CurrentUserInfo($fldname)
{
    return CurrentUser()?->get($fldname);
}

// Get current user email
function CurrentUserEmail()
{
    return Config("USER_EMAIL_FIELD_NAME") ? CurrentUserInfo(Config("USER_EMAIL_FIELD_NAME")) : null;
}

// Get current user image as base 64 string
function CurrentUserImageBase64()
{
    return Profile()->get(UserProfile::$IMAGE);
}

// Get current page ID
function CurrentPageID()
{
    if (isset($GLOBALS["Page"]->PageID)) {
        return $GLOBALS["Page"]->PageID;
    } elseif (defined(PROJECT_NAMESPACE . "PAGE_ID")) {
        return PAGE_ID;
    }
    return "";
}

// Get/Set current page title
function CurrentPageTitle($value = null)
{
    global $Page, $Title, $Language;
    if ($value !== null) { // Set
        if (isset($Page) && property_exists($Page, "Title")) {
            $Page->Title = $value;
        } else {
            $Title = $value;
        }
    } else { // Get
        if (isset($Page->Title)) {
            return $Page->Title;
        }
        return $Title ?? $Language->projectPhrase("BodyTitle");
    }
}

// Allow list
function AllowList($tableName)
{
    return Security()->allowList($tableName);
}

// Allow view
function AllowView($tableName)
{
    return Security()->allowView($tableName);
}

// Allow add
function AllowAdd($tableName)
{
    return Security()->allowAdd($tableName);
}

// Allow edit
function AllowEdit($tableName)
{
    return Security()->allowEdit($tableName);
}

// Allow delete
function AllowDelete($tableName)
{
    return Security()->allowDelete($tableName);
}

// Is password expired
function IsPasswordExpired()
{
    return Session(SESSION_STATUS) == "passwordexpired";
}

// Set session password expired
function SetSessionPasswordExpired()
{
    return Security()->setSessionPasswordExpired();
}

// Is password reset
function IsPasswordReset()
{
    return Session(SESSION_STATUS) == "passwordreset";
}

// Is logging in
function IsLoggingIn()
{
    return Session(SESSION_STATUS) == "loggingin";
}

// Is logging in (2FA)
function IsLoggingIn2FA()
{
    return Session(SESSION_STATUS) == "loggingin2fa";
}

// Is registering
function IsRegistering()
{
    return Session(SESSION_STATUS) == "registering";
}

// Is registering (2FA)
function IsRegistering2FA()
{
    return Session(SESSION_STATUS) == "registering2fa";
}

// Is logged in
function IsLoggedIn()
{
    return Session(SESSION_STATUS) == "login" || Security()->isLoggedIn();
}

// Is admin
function IsAdmin()
{
    return Session(SESSION_SYS_ADMIN) === 1 || Security()->isAdmin();
}

// Is system admin
function IsSysAdmin()
{
    return Session(SESSION_SYS_ADMIN) === 1 || Security()->isSysAdmin();
}

// Is Windows authenticated
function IsAuthenticated()
{
    return CurrentWindowsUser() != "";
}

// Is export
function IsExport($format = "")
{
    global $ExportType;
    $exportType = $ExportType ?: Param("export");
    return $format ? SameText($exportType, $format) : ($exportType != "");
}

// Encrypt with php-encryption
function PhpEncrypt($str, $password = "")
{
    if (!Config("ENCRYPTION_ENABLED") || EmptyValue($str)) {
        return $str;
    }
    try {
        return PhpEncryption::encryptWithPassword($str, $password ?: Config("ENCRYPTION_KEY"));
    } catch (\Throwable $e) {
        if (Config("DEBUG")) {
            Log("Failed to encrypt. " . $e->getMessage());
        }
        return $str;
    }
}

// Decrypt with php-encryption
function PhpDecrypt($str, $password = "")
{
    if (!Config("ENCRYPTION_ENABLED") || EmptyValue($str)) {
        return $str;
    }
    try {
        return PhpEncryption::decryptWithPassword($str, $password ?: Config("ENCRYPTION_KEY"));
    } catch (\Throwable $e) {
        if (Config("DEBUG")) {
            Log("Failed to decrypt. " . $e->getMessage());
        }
        return $str;
    }
}

// Return encryption key (16 or 32 characters)
function AesEncryptionKey($key)
{
    $size = str_contains(Config("AES_ENCRYPTION_CIPHER"), "256") ? 32 : 16;
    return strlen($key) == $size ? $key : (strlen($key) > $size ? substr($key, 0, $size) : str_pad($key, $size));
}

// Encrypt by AES
function Encrypt($str, $key = "")
{
    if (EmptyValue($str)) {
        return $str;
    }
    try {
        if ($key) {
            return (new Encrypter(AesEncryptionKey($key), Config("AES_ENCRYPTION_CIPHER")))->encryptString($str);
        } else {
            return Container(Encrypter::class)->encryptString($str);
        }
    } catch (EncryptException $e) {
        if (Config("DEBUG")) {
            Log("Failed to encrypt. " . $e->getMessage());
        }
        return $str;
    }
}

// Decrypt by AES
function Decrypt($str, $key = "")
{
    if (EmptyValue($str)) {
        return $str;
    }
    try {
        if ($key) {
            return (new Encrypter(AesEncryptionKey($key), Config("AES_ENCRYPTION_CIPHER")))->decryptString($str);
        } else {
            return Container(Encrypter::class)->decryptString($str);
        }
    } catch (DecryptException $e) {
        if (Config("DEBUG")) {
            Log("Failed to decrypt. " . $e->getMessage());
        }
        return $str;
    }
}

// URL-safe base64 encode
function UrlBase64Decode($input)
{
    return base64_decode(strtr($input, "-_", "+/"));
}

// URL-safe base64 decode
function UrlBase64Encode($input)
{
    return str_replace("=", "", strtr(base64_encode($input), "+/", "-_"));
}

/**
 * Remove XSS
 *
 * @param ?string $val String to be purified
 * @return ?string Purified string
 */
function RemoveXss($val)
{
    if (EmptyValue($val)) {
        return $val;
    } elseif (is_array($val)) {
        return array_map(fn($v) => RemoveXss($v), $val);
    }
    return Container("html.purifier")->purify($val);
}

/**
 * HTTP request by cURL
 * Note: cURL must be enabled in PHP
 *
 * @param string $url URL
 * @param string|array $data Data for the request
 * @param string $method Request method, "GET"(default) or "POST"
 * @param array $options Other options
 * @return mixed Returns true on success or false on failure
 *  If the CURLOPT_RETURNTRANSFER option is set, returns the result on success, false on failure.
 */
function ClientUrl($url, $data = "", $method = "GET", $options = [])
{
    if (!function_exists("curl_init")) {
        throw new \Exception("cURL not installed."); //** side effect
    }
    $ch = curl_init();
    $method = strtoupper($method);
    if ($method == "POST") {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    } elseif ($method == "GET") {
        if (is_array($data)) {
            $data = http_build_query($data);
        }
        curl_setopt($ch, CURLOPT_URL, $url . "?" . $data);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    foreach ($options as $option => $value) {
        curl_setopt($ch, $option, $value);
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

/**
 * Calculate date difference
 *
 * @param string $dateTimeBegin Begin date
 * @param string $dateTimeEnd End date
 * @param string $interval Interval: "s": Seconds, "n": Minutes, "h": Hours, "d": Days (default), "w": Weeks, "ww": Calendar weeks, "m": Months, or "yyyy": Years
 * @return int
 */
function DateDiff($dateTimeBegin, $dateTimeEnd, $interval = "d")
{
    $dateTimeBegin = strtotime($dateTimeBegin);
    if ($dateTimeBegin === -1 || $dateTimeBegin === false) {
        return false;
    }
    $dateTimeEnd = strtotime($dateTimeEnd);
    if ($dateTimeEnd === -1 || $dateTimeEnd === false) {
        return false;
    }
    $dif = $dateTimeEnd - $dateTimeBegin;
    $arBegin = getdate($dateTimeBegin);
    $dateBegin = mktime(0, 0, 0, $arBegin["mon"], $arBegin["mday"], $arBegin["year"]);
    $arEnd = getdate($dateTimeEnd);
    $dateEnd = mktime(0, 0, 0, $arEnd["mon"], $arEnd["mday"], $arEnd["year"]);
    $difDate = $dateEnd - $dateBegin;
    switch ($interval) {
        case "s": // Seconds
            return $dif;
        case "n": // Minutes
            return ($dif > 0) ? floor($dif / 60) : ceil($dif / 60);
        case "h": // Hours
            return ($dif > 0) ? floor($dif / 3600) : ceil($dif / 3600);
        case "d": // Days
            return ($difDate > 0) ? floor($difDate / 86400) : ceil($difDate / 86400);
        case "w": // Weeks
            return ($difDate > 0) ? floor($difDate / 604800) : ceil($difDate / 604800);
        case "ww": // Calendar weeks
            $difWeek = (($dateEnd - $arEnd["wday"] * 86400) - ($dateBegin - $arBegin["wday"] * 86400)) / 604800;
            return ($difWeek > 0) ? floor($difWeek) : ceil($difWeek);
        case "m": // Months
            return (($arEnd["year"] * 12 + $arEnd["mon"]) - ($arBegin["year"] * 12 + $arBegin["mon"]));
        case "yyyy": // Years
            return ($arEnd["year"] - $arBegin["year"]);
    }
}

// Get SQL log
function GetSqlLog()
{
    $sqlLogger = Container("debug.stack");
    $msg = "";
    foreach ($sqlLogger->queries as $query) {
        $values = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                if (count($value) > 0) {
                    $values[] = $key . ": " . print_r($value, true);
                }
            } elseif ($value && strlen($value) > 0) {
                $values[] = $key . ": " . $value;
            }
        }
        $msg .= "<p>" . implode(", ", $values) . "</p>";
    }
    $sqlLogger->queries = [];
    return $msg;
}

// Read global debug message
function GetDebugMessage()
{
    global $Page;
    if (!Config("DEBUG")) { // Skip if debug not enabled
        return "";
    }
    if ($Page && property_exists($Page, "Export") && $Page->Export == "pdf") { // Skip if export to PDF
        return "";
    }
    global $DebugMessage, $ExportType, $Language;
    $msg = $DebugMessage . GetSqlLog();
    $DebugMessage = "";
    return ($ExportType == "" && $msg != "") ? str_replace(["%t", "%s"], [$Language->phrase("Debug") ?: "Debug", $msg], CONFIG("DEBUG_MESSAGE_TEMPLATE")) : "";
}

// Set global debug message (2nd argument not used but required)
function SetDebugMessage($v, $level = 0)
{
    global $DebugMessage, $DebugTimer;
    $ar = preg_split('/<(hr|br)>/', trim($v));
    $ar = array_filter($ar, fn($s) => trim($s));
    $v = implode("; ", $ar);
    $txt = $DebugTimer?->getFormattedElapsedTime() ?? "";
    $DebugMessage .= "<p><samp>" . $txt . ($txt ? ": " : "") . $v . "</samp></p>";
}

// Save global debug message
function SaveDebugMessage()
{
    global $DebugMessage;
    if (Config("DEBUG")) {
        $_SESSION["DEBUG_MESSAGE"] = $DebugMessage . GetSqlLog();
    }
}

// Load global debug message
function LoadDebugMessage()
{
    global $DebugMessage;
    if (Config("DEBUG")) {
        $DebugMessage = Session("DEBUG_MESSAGE");
        $_SESSION["DEBUG_MESSAGE"] = "";
    }
}

// Permission denied message
function DeniedMessage()
{
    return str_replace("%s", ScriptName(), Container("app.language")->phrase("NoPermission"));
}

// Init array
function InitArray($len, $value)
{
    if ($len > 0) {
        return array_fill(0, $len, $value);
    }
    return [];
}

// Init 2D array
function Init2DArray($len1, $len2, $value)
{
    return InitArray($len1, InitArray($len2, $value));
}

/**
 * Validation functions
 */

/**
 * Check date
 *
 * @param string $value Value
 * @param int|string Formatter pattern
 * @return bool
 */
function CheckDate($value, $format = "")
{
    global $CurrentLocale, $TIME_ZONE;
    if (strval($value) == "") {
        return true;
    }
    $dt = trim($value);
    if (preg_match('/^([0-9]{4})-([0][1-9]|[1][0-2])-([0][1-9]|[1|2][0-9]|[3][0|1])( (0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?)?$/', $dt)) { // Date/Time
        return true;
    }
    $fmt = new \IntlDateFormatter($CurrentLocale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $TIME_ZONE, null, DateFormat($format));
    return $fmt->parse($dt) !== false; // Parse by $format
}

/**
 * Check time
 *
 * @param string $value Value
 * @param int|string Formatter pattern
 * @return bool
 */
function CheckTime($value, $format = "")
{
    global $CurrentLocale, $TIME_ZONE;
    if (strval($value) == "") {
        return true;
    }
    $dt = trim($value);
    if (preg_match('/^(0[0-9]|1[0-9]|2[0-3]):([0-5][0-9])(:([0-5][0-9]))?$/', $dt)) { // Date/Time
        return true;
    }
    $fmt = new \IntlDateFormatter($CurrentLocale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $TIME_ZONE, null, DateFormat($format));
    return $fmt->parse($dt) !== false; // Parse by $format
}

// Check integer
function CheckInteger($value)
{
    if (strval($value) == "") {
        return true;
    }
    return IsNumeric($value) && ParseInteger($value) !== false;
}

// Check number
function CheckNumber($value)
{
    if (strval($value) == "") {
        return true;
    }
    return IsNumeric($value) && ParseNumber($value) !== false;
}

/**
 * Check range (number)
 *
 * @param int|float $value Value
 * @param int|float $min Min value
 * @param int|float $max Max value
 * @return bool
 */
function CheckRange($value, $min, $max)
{
    if (strval($value) == "") {
        return true;
    }
    if (is_int($min) || is_float($min) || is_int($max) || is_float($max)) { // Number
        $value = ParseNumber($value);
        if ($value === false) { // Not number format
            return false;
        }
    }
    if ($min != null && $value < $min || $max != null && $value > $max) {
        return false;
    }
    return true;
}

// Check US phone number
function CheckPhone($value)
{
    if (strval($value) == "") {
        return true;
    }
    return preg_match('/^\(\d{3}\) ?\d{3}( |-)?\d{4}|^\d{3}( |-)?\d{3}( |-)?\d{4}$/', $value);
}

// Check US zip code
function CheckZip($value)
{
    if (strval($value) == "") {
        return true;
    }
    return preg_match('/^\d{5}$|^\d{5}-\d{4}$/', $value);
}

// Check credit card
function CheckCreditCard($value, $type = "")
{
    if (strval($value) == "") {
        return true;
    }
    $creditcard = [
        "visa" => "/^4\d{3}[ -]?\d{4}[ -]?\d{4}[ -]?\d{4}$/",
        "mastercard" => "/^5[1-5]\d{2}[ -]?\d{4}[ -]?\d{4}[ -]?\d{4}$/",
        "discover" => "/^6011[ -]?\d{4}[ -]?\d{4}[ -]?\d{4}$/",
        "amex" => "/^3[4,7]\d{13}$/",
        "diners" => "/^3[0,6,8]\d{12}$/",
        "bankcard" => "/^5610[ -]?\d{4}[ -]?\d{4}[ -]?\d{4}$/",
        "jcb" => "/^[3088|3096|3112|3158|3337|3528]\d{12}$/",
        "enroute" => "/^[2014|2149]\d{11}$/",
        "switch" => "/^[4903|4911|4936|5641|6333|6759|6334|6767]\d{12}$/"
    ];
    if (empty($type)) {
        $match = false;
        foreach ($creditcard as $type => $pattern) {
            if (@preg_match($pattern, $value) == 1) {
                $match = true;
                break;
            }
        }
        return ($match) ? CheckSum($value) : false;
    } else {
        if (!preg_match($creditcard[strtolower(trim($type))], $value)) {
            return false;
        }
        return CheckSum($value);
    }
}

// Check sum
function CheckSum($value)
{
    $value = str_replace(['-', ' '], ['', ''], $value);
    $checksum = 0;
    for ($i = (2 - (strlen($value) % 2)); $i <= strlen($value); $i += 2) {
        $checksum += (int)($value[$i - 1]);
    }
    for ($i = (strlen($value) % 2) + 1; $i < strlen($value); $i += 2) {
        $digit = (int)($value[$i - 1]) * 2;
        $checksum += ($digit < 10) ? $digit : ($digit - 9);
    }
    return ($checksum % 10 == 0);
}

// Check US social security number
function CheckSsn($value)
{
    if (strval($value) == "") {
        return true;
    }
    return preg_match('/^(?!000)([0-6]\d{2}|7([0-6]\d|7[012]))([ -]?)(?!00)\d\d\3(?!0000)\d{4}$/', $value);
}

// Check emails
function CheckEmails($value, $count)
{
    if (strval($value) == "") {
        return true;
    }
    $list = str_replace(",", ";", $value);
    $ar = explode(";", $list);
    $cnt = count($ar);
    if ($cnt > $count && $count > 0) {
        return false;
    }
    foreach ($ar as $email) {
        if (!CheckEmail($email)) {
            return false;
        }
    }
    return true;
}

// Check email
function CheckEmail($value)
{
    if (strval($value) == "") {
        return true;
    }
    return preg_match('/^[\w.%+-]+@[\w.-]+\.[A-Z]{2,18}$/i', trim($value));
}

// Check GUID
function CheckGuid($value)
{
    if (strval($value) == "") {
        return true;
    }
    return preg_match('/^(\{\w{8}-\w{4}-\w{4}-\w{4}-\w{12}\}|\w{8}-\w{4}-\w{4}-\w{4}-\w{12})$/', $value);
}

// Check file extension
function CheckFileType($value, $exts = "")
{
    if (strval($value) == "") {
        return true;
    }
    $extension = substr(strtolower(strrchr($value, ".")), 1);
    $exts = $exts ?: Config("UPLOAD_ALLOWED_FILE_EXT");
    $allowExt = explode(",", strtolower($exts));
    return (in_array($extension, $allowExt) || trim($exts) == "");
}

// Check empty string
function EmptyString($value)
{
    $str = strval($value);
    if (preg_match('/&[^;]+;/', $str)) { // Contains HTML entities
        $str = @html_entity_decode($str, ENT_COMPAT | ENT_HTML5, PROJECT_ENCODING);
    }
    $str = str_replace(SameText(PROJECT_ENCODING, "UTF-8") ? "\xC2\xA0" : "\xA0", " ", $str); // Remove utf-8 non-breaking space
    return trim($str) == "";
}

// Check empty value (is null or empty string) // PHP
function EmptyValue($value)
{
    return $value === null || is_string($value) && strlen($value) == 0;
}

// Partially hide a value
// - name@domain.com => n**e@domain.com
// - myname => m***me
function PartialHideValue($value)
{
    // Handle empty value
    if (EmptyValue($value)) {
        return $value;
    }

    // Handle email (split an email by "@")
    $name = $value;
    $domain = "";
    if (ContainsString($value, "@")) {
        list($name, $domain) = explode("@", $value);
    }

    // Get half the length of the first part
    $len = floor(strlen($name) / 2);
    $len2 = floor($len / 2);

    // Partially hide value by "*"
    return substr($name, 0, $len2) . str_repeat('*', $len) . substr($name, $len + $len2) . ($domain ? "@" . $domain : "");
}

// Check masked password
function IsMaskedPassword($value)
{
    return preg_match('/^\*+$/', strval($value));
}

// Check by preg
function CheckByRegEx($value, $pattern)
{
    if (strval($value) == "") {
        return true;
    }
    return preg_match($pattern, $value);
}

// Check URL
function CheckUrl($value)
{
    return CheckByRegEx($value, Config("URL_PATTERN"));
}

// Check special characters for user name
function CheckUsername($value)
{
    return preg_match('/[' . preg_quote(Config('INVALID_USERNAME_CHARACTERS'), '/') . ']/', strval($value));
}

// Check special characters for password
function CheckPassword($value)
{
    return preg_match('/[' . preg_quote(Config('INVALID_PASSWORD_CHARACTERS'), '/') . ']/', strval($value));
}

/**
 * Convert to UTF-8
 *
 * @param mixed $val Value being converted
 * @return mixed
 */
function ConvertToUtf8($val)
{
    if (IS_UTF8) {
        return $val;
    }
    if (is_string($val)) {
        return Convert(PROJECT_ENCODING, "UTF-8", $val);
    } elseif (is_array($val) || is_object($val)) {
        $isObject = is_object($val);
        if ($isObject) {
            $val = (array)$val;
        }
        $res = [];
        foreach ($val as $key => $value) {
            $res[ConvertToUtf8($key)] = ConvertToUtf8($value);
        }
        return $isObject ? (object)$res : $res;
    }
    return $val;
}

/**
 * Convert from UTF-8
 *
 * @param mixed $val Value being converted
 * @return mixed
 */
function ConvertFromUtf8($val)
{
    if (IS_UTF8) {
        return $val;
    }
    if (is_string($val)) {
        return Convert("UTF-8", PROJECT_ENCODING, $val);
    } elseif (is_array($val) || is_object($val)) {
        $isObject = is_object($val);
        if ($isObject) {
            $val = (array)$val;
        }
        $res = [];
        foreach ($val as $key => $value) {
            $res[ConvertFromUtf8($key)] = ConvertFromUtf8($value);
        }
        return $isObject ? (object)$res : $res;
    }
    return $val;
}

/**
 * Convert encoding
 *
 * @param string $from Encoding (from)
 * @param string $to Encoding (to)
 * @param string $str String being converted
 * @return string
 */
function Convert($from, $to, $str)
{
    return is_string($str) && $from != "" && $to != "" && !SameText($from, $to)
        ? mb_convert_encoding($str, $to, $from)
        : $str;
}

/**
 * Returns the JSON representation of a value
 *
 * @param mixed $val The value being encoded
 * @param string $type optional Specifies data type: "boolean", "string", "date" or "number"
 * @return string (No conversion to UTF-8)
 */
function VarToJson($val, $type = null)
{
    if ($val === null) { // null
        return "null";
    }
    $type = is_string($type) ? strtolower($type) : null;
    if ($type == "boolean" || is_bool($val)) { // bool
        return ConvertToBool($val) ? "true" : "false";
    } elseif ($type == "date" && (is_string($val) || is_int($val))) { // date
        return 'new Date("' . $val . '")';
    } elseif ($type == "number" && is_string($val)) { // number
        return (float)$val;
    } elseif ($type == "string" || is_string($val)) { // string
        if (ContainsString($val, "\0")) { // Contains null byte
            $val = "binary";
        }
        return '"' . JsEncode($val) . '"';
    }
    return $val; // int/float
}

/**
 * Convert array to JSON
 * If asscociative array, elements with integer key will not be outputted.
 *
 * @param array $ar The array being encoded
 * @return string (No conversion to UTF-8)
 */
function ArrayToJson(array $ar)
{
    $res = [];
    if (!array_is_list($ar)) { // Object
        foreach ($ar as $key => $val) {
            if (!is_int($key)) { // If object, skip element with integer key
                $res[] = VarToJson($key, "string") . ":" . JsonEncode($val);
            }
        }
        return IsDebug() ? "{\n" . implode(",\n", $res) . "\n}" : "{" . implode(",", $res) . "}";
    } else { // Array
        foreach ($ar as $val) {
            $res[] = JsonEncode($val);
        }
        return IsDebug() ? "[\n" . implode(",\n", $res) . "\n]" : "[" . implode(",", $res) . "]";
    }
}

/**
 * JSON encode
 *
 * @param mixed $val The value being encoded
 * @param int $flags Bitmask consisting of JSON constants (see https://www.php.net/manual/en/json.constants.php)
 * @param int $depth Set the maximum depth. Must be greater than zero.
 * @return string|false (non UTF-8)
 */
function JsonEncode(mixed $val, int $flags = 0, int $depth = 512)
{
    if ($val === null) {
        return "null";
    }
    if (!IS_UTF8) {
        $val = ConvertToUtf8($val); // Convert to UTF-8
    }
    $res = json_encode($val, $flags, $depth);
    if ($res !== false && !IS_UTF8) {
        $res = ConvertFromUtf8($res);
    }
    return $res;
}

/**
 * JSON decode
 *
 * @param ?string $val The JSON string being decoded (non UTF-8)
 * @param ?bool $associative When true, JSON objects will be returned as associative arrays.
 * @param int $depth Maximum nesting depth of the structure being decoded
 * @param int $flags Bitmask of JSON_BIGINT_AS_STRING, JSON_INVALID_UTF8_IGNORE, JSON_INVALID_UTF8_SUBSTITUTE, JSON_OBJECT_AS_ARRAY, JSON_THROW_ON_ERROR
 * @return mixed null is returned if the json cannot be decoded or if the encoded data is deeper than the nesting limit
 */
function JsonDecode(?string $val, ?bool $associative = null, int $depth = 512, int $flags = 0)
{
    if ($val === null) {
        return $val;
    }
    if (!IS_UTF8) {
        $val = ConvertToUtf8($val); // Convert to UTF-8
    }
    $res = json_decode($val, $associative, $depth, $flags);
    if ($res !== null && !IS_UTF8) {
        $res = ConvertFromUtf8($res);
    }
    return $res;
}

/**
 * Add <script> tag (async) by script
 *
 * @param string $src Path of script
 * @return void
 */
function AddClientScript($src, $id = "", $options = null)
{
    LoadJs($src, $id, $options);
}

/**
 * Add <link> tag by script
 *
 * @param string $src Path of stylesheet
 * @return void
 */
function AddStylesheet($src, $id = "")
{
    LoadJs("css!" . $src, $id);
}

/**
 * Load JavaScript or Stylesheet by loadjs
 *
 * @param string $src Path of script/stylesheet
 * @param string $id (optional) ID of the script
 * @param array $options (optional) Options (async and numRetries), see https://github.com/muicss/loadjs
 * @return void
 */
function LoadJs($src, $id = "", $options = null)
{
    $prefix = "";
    if (preg_match('/^css!/i', $src, $matches)) {
        $src = preg_replace('/^css!/i', '', $src);
        $prefix = "css!";
    }
    $basePath = BasePath(true);
    if (!IsRemote($src) && $basePath != "" && !StartsString($basePath, $src)) { // PHP
        $src = $basePath . $src;
    }
    echo '<script>loadjs("' . $prefix . $src . '"' . ($id ? ', "' . $id . '"' : '') . (is_array($options) ? ', ' . json_encode($options) : '') . ');</script>';
}

/**
 * Check boolean attribute
 *
 * @param string $attr Attribute name
 * @return bool
 */
function IsBooleanAttribute($attr)
{
    return in_array(strtolower($attr), Config("BOOLEAN_HTML_ATTRIBUTES"));
}

/**
 * Get HTML <a> tag
 *
 * @param string $phraseId Phrase ID for inner HTML
 * @param string|array|Attributes $attrs The href attribute, or array of attributes, or Attributes object
 * @return string HTML string
 */
function GetLinkHtml($attrs, $phraseId)
{
    global $Language;
    if (is_string($attrs)) {
        $attrs = new Attributes(["href" => $attrs]);
    } elseif (is_array($attrs)) {
        $attrs = new Attributes($attrs);
    } elseif (!$attrs instanceof Attributes) {
        $attrs = new Attributes();
    }
    $attrs->checkLinkAttributes();
    $phrase = $Language->phrase($phraseId);
    $title = $attrs["title"];
    if (!$title) {
        $title = HtmlTitle($phrase);
        $attrs["title"] = $title;
    }
    if ($title && !$attrs["data-caption"]) {
        $attrs["data-caption"] = $title;
    }
    return Element::create("a", attributes: $attrs->toArray())->setInnerHtml($phrase)->toDocument()->format()->html();
}

/**
 * Encode HTML
 *
 * @param ?string $str String to encode
 * @return ?string Encoded string
 */
function HtmlEncode($str)
{
    if (EmptyValue($str) || !is_string($str)) {
        return $str;
    }
    return @htmlspecialchars($str, ENT_COMPAT | ENT_HTML5, PROJECT_ENCODING);
}

/**
 * Decode HTML
 *
 * @param ?string $str String to decode
 * @return ?string Decoded string
 */
function HtmlDecode($str)
{
    if (EmptyValue($str) || !is_string($str)) {
        return $str;
    }
    return htmlspecialchars_decode($str, ENT_COMPAT | ENT_HTML5);
}

// Get title
function HtmlTitle($name)
{
    if (
        preg_match('/<span class=([\'"])visually-hidden\\1>([\s\S]*?)<\/span>/i', $name, $matches) || // Match span.visually-hidden
        preg_match('/\s+title\s*=\s*([\'"])([\s\S]*?)\\1/i', $name, $matches) || // Match title='title'
        preg_match('/\s+data-caption\s*=\s*([\'"])([\s\S]*?)\\1/i', $name, $matches) // Match data-caption='caption'
    ) {
        return $matches[2];
    }
    return $name;
}

/**
 * Get HTML for an option
 *
 * @param mixed $val Value of the option
 * @return string HTML
 */
function OptionHtml($val)
{
    return preg_replace('/\{value\}/', $val, Config("OPTION_HTML_TEMPLATE"));
}

/**
 * Get HTML for all option
 *
 * @param array $values Array of values
 * @return string HTML
 */
function OptionsHtml(array $values)
{
    return implode(array_map(fn($val) => OptionHtml($val), $values));
}

// Encode value for double-quoted Javascript string
function JsEncode($val)
{
    $val = strval($val);
    if (IS_DOUBLE_BYTE) {
        $val = ConvertToUtf8($val);
    }
    $val = str_replace("\\", "\\\\", $val);
    $val = str_replace("\"", "\\\"", $val);
    $val = str_replace("\t", "\\t", $val);
    $val = str_replace("\r", "\\r", $val);
    $val = str_replace("\n", "\\n", $val);
    if (IS_DOUBLE_BYTE) {
        $val = ConvertFromUtf8($val);
    }
    return $val;
}

// Encode value to single-quoted Javascript string for HTML attributes
function JsEncodeAttribute($val)
{
    $val = strval($val);
    if (IS_DOUBLE_BYTE) {
        $val = ConvertToUtf8($val);
    }
    $val = str_replace("&", "&amp;", $val);
    $val = str_replace("\"", "&quot;", $val);
    $val = str_replace("'", "&apos;", $val);
    $val = str_replace("<", "&lt;", $val);
    $val = str_replace(">", "&gt;", $val);
    if (IS_DOUBLE_BYTE) {
        $val = ConvertFromUtf8($val);
    }
    return $val;
}

// Convert array to JSON for single quoted HTML attributes
function ArrayToJsonAttribute($ar)
{
    $str = ArrayToJson($ar);
    return JsEncodeAttribute($str);
}

/**
 * Get current page URL
 *
 * @param bool $withOptionalParameters Whether with optional parameters
 * @return string URL
 */
function CurrentPageUrl($withOptionalParameters = true)
{
    $route = GetRoute();
    if (!$route) {
        return "";
    }
    $url = PathFor($route->getName(), $route->getArguments(), $withOptionalParameters);
    $basePath = BasePath(false);
    if ($basePath && !StartsString(IncludeTrailingDelimiter($basePath, false), $url)) {
        $url = $basePath . $url;
    }
    return $url;
}

// Get current page name (does not contain path)
function CurrentPageName()
{
    return GetPageName(CurrentPageUrl());
}

// Get page name
function GetPageName($url)
{
    $pageName = "";
    if ($url != "") {
        $pageName = $url;
        $p = strpos($pageName, "?");
        if ($p !== false) {
            $pageName = substr($pageName, 0, $p); // Remove QueryString
        }
        $host = ServerVar("HTTP_HOST");
        $p = strpos($pageName, $host);
        if ($p !== false) {
            $pageName = substr($pageName, $p + strlen($host)); // Remove host
        }
        $basePath = BasePath();
        if ($basePath != "" && StartsString($basePath, $pageName)) { // Remove base path
            $pageName = substr($pageName, strlen($basePath));
        }
        if (StartsString("/", $pageName)) { // Remove first "/"
            $pageName = substr($pageName, 1);
        }
        if (ContainsString($pageName, "/")) {
            $pageName = explode("/", $pageName)[0];
        }
    }
    return $pageName;
}

/**
 * Get dashboard report page URL (without arguments)
 * Note: Since there are more than one pages in dashboard report, the value of $Page changes in the View of dashboard report.
 *
 * @return string URL
 */
function CurrentDashboardPageUrl()
{
    global $DashboardReport, $Page;
    return $DashboardReport && $Page ? GetUrl($Page->CurrentPageName) : CurrentPageUrl(false);
}

// Get current user levels as array of user level IDs
function CurrentUserLevels()
{
    return Security()->UserLevelID;
}

// Check if menu item is allowed for current user level
function AllowListMenu($tableName)
{
    if (IsLoggedIn()) { // Get user level ID list as array
        $userlevels = CurrentUserLevels(); // Get user level ID list as array
    } else { // Get anonymous user level ID
        $userlevels = [AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID];
    }
    if (in_array(AdvancedSecurity::ADMIN_USER_LEVEL_ID, $userlevels)) {
        return true;
    } else {
        $priv = 0;
        $rows = Session(SESSION_USER_LEVEL_PRIVS);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (SameString($row[0], $tableName) && in_array($row[1], $userlevels)) {
                    $p = $row[2] ?? 0;
                    $p = (int)$p;
                    $priv = $priv | $p;
                }
            }
        }
        return ($priv & Allow::LIST->value);
    }
}

// Get script name
function ScriptName()
{
    $route = GetRoute();
    return $route
        ? UrlFor($route->getName(), $route->getArguments())
        : explode("?", ServerVar("REQUEST_URI"))[0];
}

// Get server variable by name
function ServerVar($name)
{
    return $_SERVER[$name] ?? $_ENV[$name] ?? "";
}

// Get CSS file
function CssFile($f, $rtl = null, $min = null)
{
    $rtl ??= IsRTL();
    $min ??= Config("USE_COMPRESSED_STYLESHEET");
    return $rtl
        ? ($min ? preg_replace('/(.css)$/i', ".rtl.min.css", $f) : preg_replace('/(.css)$/i', ".rtl.css", $f))
        : ($min ? preg_replace('/(.css)$/i', ".min.css", $f) : $f);
}

// Check if HTTPS
function IsHttps()
{
    return ServerVar("HTTPS") != "" && ServerVar("HTTPS") != "off" || ServerVar("SERVER_PORT") == 443 ||
        ServerVar("HTTP_X_FORWARDED_PROTO") != "" && ServerVar("HTTP_X_FORWARDED_PROTO") == "https";
}

// Get domain URL
function DomainUrl()
{
    $ssl = IsHttps();
    $port = strval(ServerVar("SERVER_PORT"));
    if (ServerVar("HTTP_X_FORWARDED_PROTO") != "" && strval(ServerVar("HTTP_X_FORWARDED_PORT")) != "") {
        $port = strval(ServerVar("HTTP_X_FORWARDED_PORT"));
    }
    $port = in_array($port, ["80", "443"]) ? "" : (":" . $port);
    return ($ssl ? "https" : "http") . "://" . ServerVar("SERVER_NAME") . $port;
}

// Get current URL
function CurrentUrl()
{
    $s = ScriptName();
    $q = ServerVar("QUERY_STRING");
    if ($q != "") {
        $s .= "?" . $q;
    }
    return $s;
}

// Get full URL (relative to the current script)
function FullUrl($url = "", $type = "")
{
    if (IsRemote($url)) { // Remote
        return $url;
    }
    if (StartsString("/", $url)) { // Absolute
        return DomainUrl() . $url;
    }
    $route = GetRoute();
    $fullUrl = FullUrlFor($route->getName());
    $baseUrl = substr($fullUrl, 0, strrpos($fullUrl, "/") + 1); // Get path of current script
    if ($url != "") {
        $fullUrl = RemoveTrailingDelimiter(PathCombine($baseUrl, $url, false), false); // Combine input URL
    }
    if ($type != "") {
        $protocol = Config("FULL_URL_PROTOCOLS." . $type);
        if ($protocol) {
            $fullUrl = preg_replace('/^\w+(?=:\/\/)/i', $protocol, $fullUrl);
        }
    }
    return $fullUrl;
}

// Get URL with base path
function GetUrl($url)
{
    global $RELATIVE_PATH;
    if ($url != "" && !StartsString("/", $url) && !ContainsString($url, "://") && !ContainsString($url, "\\") && !ContainsString($url, "javascript:")) {
        $basePath = BasePath(true);
        if ($RELATIVE_PATH != "") {
            $basePath = PathCombine($basePath, $RELATIVE_PATH, false);
        }
        return $basePath . $url;
    }
    return $url;
}

// Check if mobile device
function IsMobile()
{
    global $MobileDetect, $IsMobile;
    if (isset($IsMobile)) {
        return $IsMobile;
    }
    if (!isset($MobileDetect)) {
        $MobileDetect = new MobileDetect();
        $IsMobile = $MobileDetect->isMobile();
    }
    return $IsMobile;
}

/**
 * Execute query
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @param int $mode Fetch mode
 * @return Result The executed Result
 */
function ExecuteQuery($sql, $c = null)
{
    if (is_string($c)) { // $sql, $DbId
        $c = Conn($c);
    }
    $conn = $c ?? $GLOBALS["Conn"] ?? Conn();
    return $conn->executeQuery($sql);
}

/**
 * Execute UPDATE, INSERT, or DELETE statements
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @return int Rows affected
 */
function ExecuteStatement($sql, $c = null)
{
    if (is_string($c)) { // $sql, $DbId
        $c = Conn($c);
    }
    $conn = $c ?? $GLOBALS["Conn"] ?? Conn();
    return $conn->executeStatement($sql);
}

/**
 * Execute SELECT statement
 *
 * @param string $sql SQL to execute
 * @param mixed $fn Callback function to be called for each row
 * @param Connection|string $c optional Connection object or database ID
 * @return Result
 */
function Execute($sql, $fn = null, $c = null)
{
    if ($c === null && (is_string($fn) || $fn instanceof Connection)) {
        $c = $fn;
    }
    $sql = trim($sql);
    if (preg_match('/^(UPDATE|INSERT|DELETE)\s/i', $sql)) {
        return ExecuteStatement($sql, $c);
    }
    $result = ExecuteQuery($sql, $c);
    if (is_callable($fn)) {
        $rows = ExecuteRows($sql, $c);
        array_walk($rows, $fn);
    }
    return $result;
}

/**
 * Execute SELECT statment to get record count
 *
 * @param string|QueryBuilder $sql SQL or QueryBuilder
 * @param Connection $conn Connection
 * @return int Record count
 */
function ExecuteRecordCount($sql, $conn)
{
    $cnt = -1;
    if ($sql instanceof QueryBuilder) { // Query builder
        $queryBuilder = clone $sql;
        $sqlwrk = $queryBuilder->resetQueryPart("orderBy")->getSQL();
    } else {
        $conn ??= $GLOBALS["Conn"] ?? Conn();
        $sqlwrk = $sql;
    }
    if ($result = $conn->executeQuery($sqlwrk)) {
        $cnt = $result->rowCount();
        if ($cnt <= 0) { // Unable to get record count, count directly
            $cnt = 0;
            while ($result->fetch()) {
                $cnt++;
            }
        }
        return $cnt;
    }
    return $cnt;
}

/**
 * Get QueryBuilder
 *
 * @param Connection|string $c optional Connection object or database ID
 * @return QueryBuilder
 */
function QueryBuilder($c = null)
{
    if (is_string($c)) { // Database ID
        $c = Conn($c);
    }
    $conn = $c ?? $GLOBALS["Conn"] ?? Conn();
    return $conn->createQueryBuilder();
}

/**
 * Get QueryBuilder for UPDATE
 *
 * @param string $t Table
 * @param Connection|string $c optional Connection object or database ID
 * @return QueryBuilder
 */
function Update($t, $c = null)
{
    return QueryBuilder($c)->update($t);
}

/**
 * Get QueryBuilder for INSERT
 *
 * @param string $t Table
 * @param Connection|string $c optional Connection object or database ID
 * @return QueryBuilder
 */
function Insert($t, $c = null)
{
    return QueryBuilder($c)->insert($t);
}

/**
 * Get QueryBuilder for DELETE
 *
 * @param string $t Table
 * @param Connection|string $c optional Connection object or database ID
 * @return QueryBuilder
 */
function Delete($t, $c = null)
{
    return QueryBuilder($c)->delete($t);
}

/**
 * Get parameter type (for backward compatibility)
 *
 * @param DbField $fld Field Object
 * @return string|int
 */
function GetParameterType(DbField $fld)
{
    return $fld->getParameterType();
}

/**
 * Get field parameter type
 *
 * @param string $table Table name
 * @param string $field Field name
 * @return string|int
 */
function GetFieldParameterType(string $table, string $field)
{
    return Container($table)?->Fields[$field]?->getParameterType();
}

/**
 * Executes query and returns the first column of the first row
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @return mixed
 */
function ExecuteScalar($sql, $c = null)
{
    $result = ExecuteQuery($sql, $c);
    return $result->fetchOne();
}

/**
 * Executes the query, and returns the first row
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @param int $mode Fetch mode
 * @return mixed
 */
function ExecuteRow($sql, $c = null, $mode = FetchMode::ASSOCIATIVE)
{
    switch ($mode) {
        case FetchMode::ASSOCIATIVE:
            return ExecuteRowAssociative($sql, $c);
        case FetchMode::NUMERIC:
            return ExecuteRowNumeric($sql, $c);
        case FetchMode::COLUMN:
            return ExecuteScalar($sql, $c);
    }
    throw new LogicException('Only fetch modes declared on Doctrine\DBAL\FetchMode are supported.');
}

/**
 * Executes query and returns the first row (Associative)
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @return array<string,mixed>|false
 */
function ExecuteRowAssociative($sql, $c = null)
{
    $result = ExecuteQuery($sql, $c);
    return $result->fetchAssociative();
}

/**
 * Executes query and returns the first row (Numeric)
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @return list<mixed>|false
 */
function ExecuteRowNumeric($sql, $c = null)
{
    $result = ExecuteQuery($sql, $c);
    return $result->fetchNumeric();
}

/**
 * Executes query and returns all rows
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @param int $mode Fetch mode
 * @return list<mixed>
 */
function ExecuteRows($sql, $c = null, $mode = FetchMode::ASSOCIATIVE)
{
    switch ($mode) {
        case FetchMode::ASSOCIATIVE:
            return ExecuteRowsAssociative($sql, $c);
        case FetchMode::NUMERIC:
            return ExecuteRowsNumeric($sql, $c);
        case FetchMode::COLUMN:
            return ExecuteFirstColumn($sql, $c);
    }
    throw new LogicException('Only fetch modes declared on Doctrine\DBAL\FetchMode are supported.');
}

/**
 * Executes query and returns all rows (Associative)
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @return array<string,mixed>|false
 */
function ExecuteRowsAssociative($sql, $c = null)
{
    $result = ExecuteQuery($sql, $c);
    return $result->fetchAllAssociative();
}

/**
 * Executes query and returns all rows (Numeric)
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @return list<mixed>|false
 */
function ExecuteRowsNumeric($sql, $c = null)
{
    $result = ExecuteQuery($sql, $c);
    return $result->fetchAllNumeric();
}

/**
 * Executes query and returns an associative array with the keys mapped to the first column and the values being
 * an associative array representing the rest of the columns and their values
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @return array<mixed,array<string,mixed>>
 */
function ExecuteRowsAssociativeIndexed($sql, $c = null)
{
    $result = ExecuteQuery($sql, $c);
    return $result->fetchAllAssociativeIndexed();
}

/**
 * Executes query and an array containing the values of the first column of the result
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @return array<mixed,array<string,mixed>>
 */
function ExecuteRowsKeyValue($sql, $c = null)
{
    $result = ExecuteQuery($sql, $c);
    return $result->fetchAllKeyValue();
}

/**
 * Executes query and returns first column of all rows
 *
 * @param string $sql SQL to execute
 * @param Connection|string $c optional Connection object or database ID
 * @return list<mixed>
 */
function ExecuteFirstColumn($sql, $c = null)
{
    $result = ExecuteQuery($sql, $c);
    return $result->fetchFirstColumn();
}

/**
 * Executes query and returns all rows as JSON
 *
 * @param string $sql SQL to execute
 * @param array $options {
 *  @var bool "utf8" Convert to UTF-8, default: true
 *  @var bool "array" Output as array
 *  @var bool "firstonly" Output first row only
 *  @var bool "datatypes" Array of data types, key of array must be same as row(s)
 * }
 * @param Connection|string $c Connection object or DB ID
 * @return string
 */
function ExecuteJson($sql, $options = null, $c = null)
{
    $ar = is_array($options) ? $options : [];
    if (is_bool($options)) { // First only, backward compatibility
        $ar["firstonly"] = $options;
    }
    if ($c === null && is_object($options) && method_exists($options, "execute")) { // ExecuteJson($sql, $c)
        $c = $options;
    }
    $res = "false";
    $header = $ar["header"] ?? true; // Set header for JSON
    $utf8 = $header || ($ar["utf8"] ?? false); // Convert to utf-8
    $firstonly = $ar["firstonly"] ?? false;
    $datatypes = is_array($ar["datatypes"] ?? false) ? $ar["datatypes"] : [];
    $array = $ar["array"] ?? false;
    $mode = $array ? FetchMode::NUMERIC : FetchMode::ASSOCIATIVE;
    $rows = $firstonly ? [ExecuteRow($sql, $c, $mode)] : ExecuteRows($sql, $c, $mode);
    if (is_array($rows)) {
        $arOut = [];
        foreach ($rows as $row) {
            $arwrk = [];
            foreach ($row as $k => $v) {
                if ($array && is_string($k) || !$array && is_int($k)) {
                    continue;
                }
                $key = $array ? '' : '"' . JsEncode($k) . '":';
                $datatype = $datatypes[$k] ?? null;
                $val = VarToJson($v, $datatype);
                $arwrk[] = $key . $val;
            }
            if ($array) { // Array
                $arOut[] = "[" . implode(",", $arwrk) . "]";
            } else { // Object
                $arOut[] = "{" . implode(",", $arwrk) . "}";
            }
        }
        $res = $firstonly ? $arOut[0] : "[" . implode(",", $arOut) . "]";
        if ($utf8) {
            $res = ConvertToUtf8($res);
        }
    }
    return $res;
}

/**
 * Get query result in HTML table
 *
 * @param string $sql SQL to execute
 * @param array $options optional {
 *  @var bool|array "fieldcaption"
 *    true Use caption and use language object
 *    false Use field names directly
 *    array An associative array for looking up the field captions by field name
 *  @var bool "horizontal" Specifies if the table is horizontal, default: false
 *  @var string|array "tablename" Table name(s) for the language object
 *  @var string "tableclass" CSS class names of the table, default: "table table-bordered table-sm ew-db-table"
 *  @var Language "language" Language object, default: the global Language object
 * }
 * @param Connection|string $c optional Connection object or DB ID
 * @return string HTML string
 */
function ExecuteHtml($sql, $options = null, $c = null)
{
    // Internal function to get field caption
    $getFieldCaption = function ($key) use ($options) {
        $caption = "";
        if (!is_array($options)) {
            return $key;
        }
        $tableName = @$options["tablename"];
        $lang = $options["language"] ?? $GLOBALS["Language"];
        $useCaption = (array_key_exists("fieldcaption", $options) && $options["fieldcaption"]);
        if ($useCaption) {
            if (is_array($options["fieldcaption"])) {
                $caption = @$options["fieldcaption"][$key];
            } elseif (isset($lang)) {
                if (is_array($tableName)) {
                    foreach ($tableName as $tbl) {
                        $caption = @$lang->fieldPhrase($tbl, $key, "FldCaption");
                        if ($caption != "") {
                            break;
                        }
                    }
                } elseif ($tableName != "") {
                    $caption = @$lang->fieldPhrase($tableName, $key, "FldCaption");
                }
            }
        }
        return $caption ?: $key;
    };
    $options = is_array($options) ? $options : [];
    $horizontal = array_key_exists("horizontal", $options) && $options["horizontal"];
    $rs = ExecuteQuery($sql, $c);
    if ($rs?->columnCount() < 1) {
        return "";
    }
    $html = "";
    $class = $options["tableclass"] ?? "table table-sm ew-db-table"; // Table CSS class name
    $rowCount = ExecuteRecordCount($sql, $c);
    if ($rowCount > 1 || $horizontal) { // Horizontal table
        $rowcnt = 0;
        while ($row = $rs->fetch()) {
            if ($rowcnt == 0) {
                $html = "<table class=\"" . $class . "\">";
                $html .= "<thead><tr>";
                foreach (array_keys($row) as $key) {
                    $html .= "<th>" . $getFieldCaption($key) . "</th>";
                }
                $html .= "</tr></thead>";
                $html .= "<tbody>";
            }
            $rowcnt++;
            $html .= "<tr>";
            foreach ($row as $key => $value) {
                $html .= "<td>" . $value . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
    } else { // Single row, vertical table
        $html = "<table class=\"" . $class . "\"><tbody>";
        if ($row = $rs->fetch()) {
            foreach ($row as $key => $value) {
                $html .= "<tr><td>" . $getFieldCaption($key) . "</td><td>" . $value . "</td></tr>";
            }
        }
        $html .= "</tbody></table>";
    }
    return $html;
}

/**
 * Get class name(s) as array
 *
 * @param string $attr Class name(s)
 * @return string[] Class name(s)
 */
function ClassList($attr)
{
    return ($attr != "")
        ? array_unique(array_filter(explode(" ", $attr))) // Remove empty and duplicate values
        : [];
}

/**
 * Contains CSS class name
 *
 * @param string $attr Class name(s)
 * @param string $className Class name to search
 * @return bool
 */
function ContainsClass($attr, $className)
{
    return array_search($className, ClassList($attr)) !== false;
}

/**
 * Prepend CSS class name(s)
 *
 * @param string &$attr Class name(s)
 * @param string $className Class name(s) to prepend
 * @return string Class name(s)
 */
function PrependClass(&$attr, $className)
{
    if ($className) {
        $attr = $className . " " . $attr;
    }
    $attr = implode(" ", ClassList($attr));
    return $attr;
}

/**
 * Append CSS class name(s)
 *
 * @param string &$attr Class name(s)
 * @param string $className Class name(s) to append
 * @return string Class name(s)
 */
function AppendClass(&$attr, $className)
{
    if ($className) {
        $attr .= " " . $className;
    }
    $attr = implode(" ", ClassList($attr));
    return $attr;
}

/**
 * Remove CSS class name(s)
 *
 * @param string &$attr Class name(s)
 * @param string|callable $classNames Class name(s) to remove
 * @return string Class name(s)
 */
function RemoveClass(&$attr, $classNames)
{
    $ar = ClassList($attr);
    if (is_string($classNames)) { // String
        $ar = array_diff($ar, ClassList($classNames));
    } elseif (is_callable($classNames)) { // Callable to filter the class names
        $ar = array_filter($ar, $classNames);
    }
    $attr = implode(" ", $ar);
    return $attr;
}

/**
 * Check CSS class name and convert to lowercase with dashes between words
 *
 * @param string $name Class name
 * @return string Valid class name
 */
function CheckClassName($name)
{
    $prefix = Config("CLASS_PREFIX");
    if (preg_match('/^(\d+)(-*)([\-\w]+)/', $name, $m)) { // Cannot start with a digit
        return $prefix . $m[1] . $m[2] . ParamCase($m[3]);
    } elseif (preg_match('/^(-{2,}|-\d+)(-*)([\-\w]+)/', $name, $m)) { // Cannot start with two hyphens or a hyphen followed by a digit
        return $prefix . $m[1] . $m[2] . ParamCase($m[3]);
    } elseif (preg_match('/^(_+)?(-*)([\-\w]+)/', $name, $m)) { // Keep leading underscores
        return $m[1] . $m[2] . ParamCase($m[3]);
    }
    return ParamCase($name);
}

/**
 * Get locale information
 */
function LocaleConvert()
{
    global $DATE_SEPARATOR, $TIME_SEPARATOR, $CURRENCY_CODE, $TIME_ZONE, $CurrentLocale;
    $langid = CurrentLanguageID();
    $localefile = Config("LOCALE_FOLDER") . $langid . ".json";
    if (file_exists($localefile)) { // Load from locale file
        $locale = array_merge(["id" => $langid], json_decode(file_get_contents($localefile), true));
    } else { // Load from PHP intl extension
        $locales = array_map("strtolower", \ResourceBundle::getLocales(""));
        if (!in_array(strtolower(str_replace("-", "_", $langid)), $locales)) { // Locale not supported by server
            LogError("Locale " . $langid . " not supported by server.");
            $langid = "en-US"; // Fallback to "en-US"
        }
        $locale = [
            "id" => $langid,
            "desc" => \Locale::getDisplayName($langid),
        ];
    }
    $getSeparator = fn($str) => preg_match('/[^\w]+/', $str, $m) ? $m[0] : null;
    $CurrentLocale = $locale["id"];
    $fmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::DECIMAL);
    $currfmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::CURRENCY);
    $pctfmt = new \NumberFormatter($CurrentLocale, \NumberFormatter::PERCENT);
    $currcode = $locale["currency_code"] ?? $currfmt->getTextAttribute(\NumberFormatter::CURRENCY_CODE);
    $locale["currency_code"] = $currcode != "XXX" ? $currcode : $CURRENCY_CODE;
    $locale["number"] ??=  $fmt->getPattern();
    $locale["currency"] ??=  $currfmt->getPattern();
    $locale["percent"] ??=  $pctfmt->getPattern();
    $locale["percent_symbol"] ??=  $pctfmt->getSymbol(\NumberFormatter::PERCENT_SYMBOL);
    $locale["currency_symbol"] ??=  $currfmt->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
    $locale["numbering_system"] ??=  "";
    $locale["date"] ??=  (new \IntlDateFormatter($CurrentLocale, \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE))->getPattern();
    $locale["time"] ??=  (new \IntlDateFormatter($CurrentLocale, \IntlDateFormatter::NONE, \IntlDateFormatter::SHORT))->getPattern();
    $locale["decimal_separator"] ??=  $fmt->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    $locale["grouping_separator"] ??=  $fmt->getSymbol(\NumberFormatter::GROUPING_SEPARATOR_SYMBOL);
    $locale["date_separator"] ??=  $getSeparator($locale["date"]) ?? $DATE_SEPARATOR;
    $locale["time_separator"] ??=  $getSeparator($locale["time"]) ?? $TIME_SEPARATOR;
    $locale["time_zone"] = !empty($locale["time_zone"]) ? $locale["time_zone"] : $TIME_ZONE;
    return $locale;
}

/**
 * Get ICU date/time format pattern
 *
 * @param int|string $dateFormat Date format
 * @return string ICU date format
 */
function DateFormat($dateFormat)
{
    global $DATE_SEPARATOR, $TIME_SEPARATOR, $DATE_FORMAT, $TIME_FORMAT;
    if (is_numeric($dateFormat)) { // Predefined format
        $id = intval($dateFormat);
        if ($id == 1) {
            return $DATE_FORMAT . " " . $TIME_FORMAT; // DateTime
        } elseif ($id == 0 || $id == 2) {
            return $DATE_FORMAT; // Date
        } elseif ($id == 3) {
            return $TIME_FORMAT; // Time
        } else { // Predefined formats
            $formats = Config("DATE_FORMATS");
            if (array_key_exists($id, $formats)) {
                return str_replace(["/", ":"], [$DATE_SEPARATOR, $TIME_SEPARATOR], $formats[$id]);
            }
        }
    } elseif (is_string($dateFormat) && !EmptyValue($dateFormat)) { // User defined format
        return $dateFormat;
    }
    return ""; // Unknown
}

/**
 * Get database date/time format pattern
 *
 * @param int|string $dateFormat Date format
 * @param string $dbtype Database type
 * @return string Database date format
 */
function DbDateFormat($dateFormat, $dbtype)
{
    global $DATE_SEPARATOR, $TIME_SEPARATOR;
    $dateFormat = DateFormat($dateFormat);
    $tokens = array_reverse(preg_split('/[_\W]/', $dateFormat, -1, PREG_SPLIT_OFFSET_CAPTURE));
    $symbols = Config("DB_DATE_FORMATS." . $dbtype);
    foreach ($tokens as $token) {
        $t = $token[0];
        $dateFormat = substr_replace($dateFormat, $symbols[$t] ?? $t, $token[1], strlen($t));
    }
    return str_replace(["/", ":"], [$DATE_SEPARATOR, $TIME_SEPARATOR], $dateFormat);
}

// Get path relative to a base path
function PathCombine($basePath, $relPath, $phyPath)
{
    if (IsRemote($relPath)) { // Allow remote file
        return $relPath;
    }
    $phyPath = !IsRemote($basePath) && $phyPath;
    $delimiter = $phyPath ? PATH_DELIMITER : '/';
    if ($basePath != $delimiter) { // If BasePath = root, do not remove delimiter
        $basePath = RemoveTrailingDelimiter($basePath, $phyPath);
    }
    $relPath = $phyPath ? str_replace(['/', '\\'], PATH_DELIMITER, $relPath) : str_replace('\\', '/', $relPath);
    $relPath = IncludeTrailingDelimiter($relPath, $phyPath);
    if ($basePath == $delimiter && !$phyPath) { // If BasePath = root and not physical path, just return relative path(?)
        return $relPath;
    }
    $p1 = strpos($relPath, $delimiter);
    $path2 = "";
    while ($p1 !== false) {
        $path = substr($relPath, 0, $p1 + 1);
        if ($path == $delimiter || $path == '.' . $delimiter) {
            // Skip
        } elseif ($path == ".." . $delimiter) {
            $p2 = strrpos($basePath, $delimiter);
            if ($p2 === 0) { // BasePath = "/xxx", cannot move up
                $basePath = $delimiter;
            } elseif ($p2 !== false && !EndsString("..", $basePath)) {
                $basePath = substr($basePath, 0, $p2);
            } elseif ($basePath != "" && $basePath != "." && $basePath != "..") {
                $basePath = "";
            } else {
                $path2 .= ".." . $delimiter;
            }
        } else {
            $path2 .= $path;
        }
        $relPath = substr($relPath, $p1 + 1);
        if ($relPath === false) {
            $relPath = "";
        }
        $p1 = strpos($relPath, $delimiter);
    }
    return (($basePath === "" || $basePath === ".") ? "" : IncludeTrailingDelimiter($basePath, $phyPath)) . $path2 . $relPath;
}

// Remove the last delimiter for a path
function RemoveTrailingDelimiter($path, $phyPath)
{
    $delimiter = !IsRemote($path) && $phyPath ? PATH_DELIMITER : '/';
    while (substr($path, -1) == $delimiter) {
        $path = substr($path, 0, strlen($path) - 1);
    }
    return $path;
}

// Include the last delimiter for a path
function IncludeTrailingDelimiter($path, $phyPath)
{
    $path = RemoveTrailingDelimiter($path, $phyPath);
    $delimiter = !IsRemote($path) && $phyPath ? PATH_DELIMITER : '/';
    return $path . $delimiter;
}

// Get session timeout time (seconds)
function SessionTimeoutTime()
{
    if (Config("SESSION_TIMEOUT") > 0) { // User specified timeout time
        $mlt = Config("SESSION_TIMEOUT") * 60;
    } else { // Get max life time from php.ini
        $mlt = (int)ini_get("session.gc_maxlifetime"); // Defaults to 1440s = 24min
        if ($mlt > 0) {
            $mlt -= 30; // Add some safety margin
        }
    }
    if ($mlt <= 0) {
        $mlt = 1440; // PHP default (1440s = 24min)
    }
    return $mlt;
}

// Contains a substring (case-sensitive)
function ContainsString($haystack, $needle)
{
    return str_contains($haystack ?? "", $needle);
}

// Contains a substring (case-insensitive)
function ContainsText($haystack, $needle)
{
    return stripos($haystack ?? "", $needle) !== false;
}

// Starts with a substring (case-sensitive)
function StartsString($needle, $haystack)
{
    return str_starts_with($haystack ?? "", $needle);
}

// Starts with a substring (case-insensitive)
function StartsText($needle, $haystack)
{
    return stripos($haystack ?? "", $needle) === 0;
}

// Ends with a substring (case-sensitive)
function EndsString($needle, $haystack)
{
    return str_ends_with($haystack ?? "", $needle);
}

// Ends with a substring (case-insensitive)
function EndsText($needle, $haystack)
{
    return strripos($haystack ?? "", $needle) === strlen($haystack ?? "") - strlen($needle);
}

// Same trimmed strings (case-sensitive)
function SameString($str1, $str2)
{
    return strcmp(trim($str1 ?? ""), trim($str2 ?? "")) === 0;
}

// Same trimmed strings (case-insensitive)
function SameText($str1, $str2)
{
    return strcasecmp(trim($str1 ?? ""), trim($str2 ?? "")) === 0;
}

// Convert to constant case (e.g. FOO_BAR)
function ConstantCase($str)
{
    return (new \Jawira\CaseConverter\Convert($str))->toMacro();
}

// Convert to param case (e.g. foo-bar)
function ParamCase($str)
{
    return (new \Jawira\CaseConverter\Convert($str))->toKebab();
}

// Convert to header case (e.g. Foo-Bar)
function HeaderCase($str)
{
    return (new \Jawira\CaseConverter\Convert($str))->toTrain();
}

// Convert to Pascal case (e.g. FooBar)
function PascalCase($str)
{
    return (new \Jawira\CaseConverter\Convert($str))->toPascal();
}

// Convert to camle case (e.g. fooBar)
function CamelCase($str)
{
    return (new \Jawira\CaseConverter\Convert($str))->toCamel();
}

// Set client variable
function SetClientVar($key, $value)
{
    global $ClientVariables;
    $key = strval($key);
    if (is_array($value) && is_array($ClientVariables[$key] ?? null)) {
        $ClientVariables[$key] = array_replace_recursive($ClientVariables[$key], $value);
    } else {
        $ClientVariables[$key] = $value;
    }
}

// Get client variable
function GetClientVar($key = "", $subkey = "")
{
    global $ClientVariables;
    if (!$key) {
        return $ClientVariables;
    }
    $value = $ClientVariables[$key] ?? null;
    if ($subkey) {
        $value = $value[$subkey] ?? null;
    }
    return $value;
}

// Get config client variables
function ConfigClientVars()
{
    $values = [];
    $data = Config();
    $names = $data->get("CONFIG_CLIENT_VARS");
    foreach ($names as $name) {
        if ($data->has($name)) {
            $values[$name] = $data->get($name);
        }
    }
    // Update PROJECT_STYLESHEET_FILENAME
    $values["PROJECT_STYLESHEET_FILENAME"] = CssFile(Config("PROJECT_STYLESHEET_FILENAME"));
    return $values;
}

// Get global client variables
function GlobalClientVars()
{
    global $CURRENCY_FORMAT;
    $names = Config("GLOBAL_CLIENT_VARS");
    $values = [];
    foreach ($names as $name) {
        if (isset($GLOBALS[$name])) { // Global variable
            $values[ConstantCase($name)] = $GLOBALS[$name]; // Convert key to constant case
        } elseif (defined(PROJECT_NAMESPACE . $name)) { // Global constant
            $values[ConstantCase($name)] = constant(PROJECT_NAMESPACE . $name);
        } elseif (is_callable(PROJECT_NAMESPACE . $name, false, $func)) { // Global function
            $values[ConstantCase($name)] = $func();
        }
    }
    return array_merge([
        "ROWTYPE_VIEW" => RowType::VIEW, // 1
        "ROWTYPE_ADD" => RowType::ADD, // 2
        "ROWTYPE_EDIT" => RowType::EDIT, // 3
        "CURRENCY_FORMAT" => str_replace('¤', '$', $CURRENCY_FORMAT),
        "IS_RTL" => IsRTL(),
        "IS_LOGGEDIN" => IsLoggedIn(),
        "IS_AUTOLOGIN" => IsAutoLogin(),
        "LANGUAGE_ID" => str_replace("_", "-", CurrentLanguageID()),
        "PATH_BASE" => BasePath(true), // Path base // PHP
        "PROJECT_NAME" => PROJECT_NAME,
        "SESSION_ID" => Encrypt(session_id()), // Session ID // PHP
        "ANTIFORGERY_TOKEN_KEY" => $GLOBALS["TokenValueKey"], // "csrf_value" // PHP
        "ANTIFORGERY_TOKEN" => $GLOBALS["TokenValue"], // CSRF token // PHP
        "API_JWT_AUTHORIZATION_HEADER" => "X-Authorization", // API JWT authorization header
        "API_JWT_TOKEN" => GetJwtToken(), // API JWT token
        "IMAGE_FOLDER" => "images/", // Image folder
        "SESSION_TIMEOUT" => Config("SESSION_TIMEOUT") > 0 ? SessionTimeoutTime() : 0, // Session timeout time (seconds)
        "TIMEOUT_URL" => GetUrl("logout"), // Timeout URL // PHP
        "SERVER_SEARCH_FILTER" => Config("SEARCH_FILTER_OPTION") == "Server",
        "CLIENT_SEARCH_FILTER" => Config("SEARCH_FILTER_OPTION") == "Client",
    ], $values);
}

// Get/Set global login status array
function LoginStatus(...$args)
{
    global $LoginStatus;
    $numargs = count($args);
    if ($numargs == 1) { // Get
        return $LoginStatus[$args[0]] ?? null;
    } elseif ($numargs == 2) { // Set
        $LoginStatus[$args[0]] = $args[1];
        return;
    }
    return $LoginStatus->getArguments();
}

// Return Two Factor Authentication class
function TwoFactorAuthenticationClass()
{
    return match (Config("TWO_FACTOR_AUTHENTICATION_TYPE")) {
        "email" => EmailTwoFactorAuthentication::class,
        "sms" => SmsTwoFactorAuthentication::class,
        "google" => PragmaRxTwoFactorAuthentication::class,
        default => Config("TWO_FACTOR_AUTHENTICATION_CLASS"),
    };
}

// Set up login status
function SetupLoginStatus()
{
    global $LoginStatus, $Language, $EventDispatcher;
    $LoginStatus["isLoggedIn"] = IsLoggedIn();
    $LoginStatus["currentUserName"] = CurrentUserName();
    $currentPage = CurrentPageName();

    // Logout page
    $logoutPage = "logout";
    $logoutUrl = GetUrl($logoutPage);
    $LoginStatus["logout"] = [
        "ew-action" => "redirect",
        "url" => $logoutUrl
    ];
    $LoginStatus["logoutUrl"] = $logoutUrl;
    $LoginStatus["logoutText"] = $Language->phrase("Logout", null);
    $LoginStatus["canLogout"] = $logoutPage && IsLoggedIn();

    // Login page
    $loginPage = "login";
    $loginUrl = GetUrl($loginPage);
    $LoginStatus["login"] = [];
    if ($currentPage != $loginPage) {
        if (Config("USE_MODAL_LOGIN") && !IsMobile()) {
            $LoginStatus["login"] = [
                "ew-action" => "modal",
                "footer" => false,
                "caption" => $Language->phrase("Login", true),
                "size" => "modal-md",
                "url" => $loginUrl
            ];
        } else {
            $LoginStatus["login"] = [
                "ew-action" => "redirect",
                "url" => $loginUrl
            ];
        }
    } else {
        $LoginStatus["login"] = ["url" => $loginUrl];
    }
    $LoginStatus["loginTitle"] = $Language->phrase("Login", true);
    $LoginStatus["loginText"] = $Language->phrase("Login");
    $LoginStatus["canLogin"] = $currentPage != $loginPage && $loginUrl && !IsLoggedIn() && !IsLoggingIn2FA();

    // Reset password page
    $resetPasswordPage = "resetpassword";
    $resetPasswordUrl = GetUrl($resetPasswordPage);
    if ($currentPage != $resetPasswordPage) {
        if (Config("USE_MODAL_RESET_PASSWORD") && !IsMobile()) {
            $LoginStatus["resetPassword"] = [
                "ew-action" => "modal",
                "footer" => false,
                "caption" => $Language->phrase("ResetPassword"),
                "size" => "modal-md",
                "url" => $resetPasswordUrl
            ];
        } else {
            $LoginStatus["resetPassword"] = [
                "ew-action" => "redirect",
                "url" => $resetPasswordUrl
            ];
        }
    }
    $LoginStatus["resetPasswordUrl"] = $resetPasswordUrl;
    $LoginStatus["resetPasswordText"] = $Language->phrase("ResetPassword");
    $LoginStatus["canResetPassword"] = $resetPasswordUrl && !IsLoggedIn();

    // Change password page
    $changePasswordPage = "changepassword";
    $changePasswordUrl = GetUrl($changePasswordPage);
    if ($currentPage != $changePasswordPage) {
        if (Config("USE_MODAL_CHANGE_PASSWORD") && !IsMobile()) {
            $LoginStatus["changePassword"] = [
                "ew-action" => "modal",
                "footer" => false,
                "caption" => $Language->phrase("ChangePassword"),
                "size" => "modal-md",
                "url" => $changePasswordUrl
            ];
        } else {
            $LoginStatus["changePassword"] = [
                "ew-action" => "redirect",
                "url" => $changePasswordUrl
            ];
        }
    }
    $LoginStatus["changePasswordUrl"] = $changePasswordUrl;
    $LoginStatus["changePasswordText"] = $Language->phrase("ChangePassword");
    $LoginStatus["canChangePassword"] = $changePasswordUrl && IsLoggedIn() && !IsSysAdmin();

    // Personal data page
    $personalDataPage = "personaldata";
    $personalDataUrl = GetUrl($personalDataPage);
    if ($currentPage != $personalDataPage) {
        $LoginStatus["personalData"] = [
            "ew-action" => "redirect",
            "url" => $personalDataUrl
        ];
    }
    $LoginStatus["hasPersonalData"] = $personalDataUrl && IsLoggedIn() && !IsSysAdmin();
    $LoginStatus["personalDataUrl"] = $personalDataUrl;
    $LoginStatus["personalDataText"] = $Language->phrase("PersonalDataBtn");

    // Dispatch login status event and return the event
    return DispatchEvent($LoginStatus, LoginStatusEvent::NAME);
}

// Is absolute path (from symfony/filesystem)
function IsAbsolutePath(string $file): bool
{
    return "" !== $file && (strspn($file, "/\\", 0, 1)
        || (\strlen($file) > 3 && ctype_alpha($file[0])
            && ":" === $file[1]
            && strspn($file, "/\\", 2, 1)
        )
        || null !== parse_url($file, \PHP_URL_SCHEME)
    );
}

// Is remote path
function IsRemote($path)
{
    return str_contains($path, "://");
}

// Is auto login
function IsAutoLogin()
{
    return (Session(SESSION_USER_LOGIN_TYPE) == "a");
}

// Get current page heading
function CurrentPageHeading()
{
    global $Language, $Page;
    if (Config("PAGE_TITLE_STYLE") != "Title" && isset($Page) && method_exists($Page, "pageHeading")) {
        $heading = $Page->pageHeading();
        if ($heading != "") {
            return $heading;
        }
    }
    return $Language->projectPhrase("BodyTitle");
}

// Get current page subheading
function CurrentPageSubheading()
{
    global $Page;
    $heading = "";
    if (Config("PAGE_TITLE_STYLE") != "Title" && isset($Page) && method_exists($Page, "pageSubheading")) {
        $heading = $Page->pageSubheading();
    }
    return $heading;
}

// Convert HTML to text
function HtmlToText($html)
{
    return \Soundasleep\Html2Text::convert($html, true);
}

// Get captcha object
function Captcha()
{
    global $Captcha, $CaptchaClass, $Page;
    $class = PROJECT_NAMESPACE . $CaptchaClass;
    if (!isset($Captcha) || !($Captcha instanceof $class)) {
        $Captcha = new $class();
    }
    return $Captcha;
}

// Attributes for drill down
function DrillDownAttributes($url, $id, $hdr, $popover = true)
{
    if (trim($url) == "") {
        return [];
    } else {
        if ($popover) {
            return [
                "data-ew-action" => "drilldown",
                "data-url" => preg_replace('/&(?!amp;)/', '&amp;', $url), // Replace & to &amp;
                "data-id" => $id,
                "data-hdr" => $hdr
            ];
        } else {
            return [
                "data-ew-action" => "redirect",
                "data-url" => str_replace("?d=1&", "?d=2&", $url) // Change d parameter to 2
            ];
        }
    }
}

/**
 * Convert field value for dropdown
 *
 * @param string $t Date type
 * @param mixed $val Field value
 * @return string Converted value
 */
function ConvertDisplayValue($t, $val)
{
    if ($val === null) {
        return Config("NULL_VALUE");
    } elseif ($val === "") {
        return Config("EMPTY_VALUE");
    }
    if (is_float($val)) {
        $val = (float)$val;
    }
    if ($t == "") {
        return $val;
    }
    if ($ar = explode(" ", $val)) {
        $ar = explode("-", $ar[0]);
    } else {
        return $val;
    }
    if (!$ar || count($ar) != 3) {
        return $val;
    }
    list($year, $month, $day) = $ar;
    switch (strtolower($t)) {
        case "year":
            return $year;
        case "quarter":
            return "$year|" . ceil(intval($month) / 3);
        case "month":
            return "$year|$month";
        case "day":
            return "$year|$month|$day";
        case "date":
            return "$year-$month-$day";
    }
}

/**
 * Get dropdown display value
 *
 * @param mixed $v Field value
 * @param string $t Date type
 * @param int $fmt Date format
 * @return string Display value of the field value
 */
function GetDropDownDisplayValue($v, $t = "", $fmt = 0)
{
    global $Language;
    if (SameString($v, Config("NULL_VALUE"))) {
        return $Language->phrase("NullLabel");
    } elseif (SameString($v, Config("EMPTY_VALUE"))) {
        return $Language->phrase("EmptyLabel");
    } elseif (SameText($t, "boolean")) {
        return BooleanName($v);
    }
    if ($t == "") {
        return $v;
    }
    $ar = explode("|", strval($v));
    $t = strtolower($t);
    if (in_array($t, ["y", "year", "q", "quarter"])) {
        return (count($ar) >= 2) ? QuarterName($ar[1]) . " " . $ar[0] : $v;
    } elseif (in_array($t, ["m", "month"])) {
        return (count($ar) >= 2) ?  MonthName($ar[1]) . " " . $ar[0] : $v;
    } elseif (in_array($t, ["w", "week"])) {
        return (count($ar) >= 2) ? $Language->phrase("Week") . " " . $ar[1] . ", " . $ar[0] : $v;
    } elseif (in_array($t, ["d", "day"])) {
        return (count($ar) >= 3) ? FormatDateTime($ar[0] . "-" . $ar[1] . "-" . $ar[2], $fmt) : $v;
    } elseif (in_array($t, ["date"])) {
        return FormatDateTime($v, $fmt);
    }
    return $v;
}

/**
 * Get dropdown edit value
 *
 * @param object $fld Field object
 * @param mixed $v Field value
 */
function GetDropDownEditValue($fld, $v)
{
    global $Language;
    $val = trim(strval($v));
    $ar = [];
    if ($val != "") {
        $arwrk = $fld->isMultiSelect() ? explode(Config("MULTIPLE_OPTION_SEPARATOR"), $val) : [$val];
        foreach ($arwrk as $wrk) {
            $format = $fld->DateFilter ?: "date";
            $ar[] = ["lf" => $wrk, "df" => GetDropDownDisplayValue($wrk, $format, $fld->formatPattern())];
        }
    }
    return $ar;
}

/**
 * Get Boolean Name
 *
 * @param mixed $v Value, treat "T", "True", "Y", "Yes", "1" as true
 * @return string
 */
function BooleanName($v)
{
    global $Language;
    if ($v === null) {
        return $Language->phrase("NullLabel");
    } elseif (SameText($v, "T") || SameText($v, "true") || SameText($v, "Y") || SameText($v, "YES") || strval($v) == "1") {
        return $Language->phrase("BooleanYes");
    } else {
        return $Language->phrase("BooleanNo");
    }
}

// Quarter name
function QuarterName($q)
{
    $t = mktime(1, 0, 0, $q * 3);
    return FormatDateTime($t, Config("QUARTER_PATTERN"));
}

// Month name
function MonthName($m)
{
    $t = mktime(1, 0, 0, $m);
    return FormatDateTime($t, Config("MONTH_PATTERN"));
}

// Get current year
function CurrentYear()
{
    return intval(date('Y'));
}

// Get current quarter
function CurrentQuarter()
{
    return ceil(intval(date('n')) / 3);
}

// Get current month
function CurrentMonth()
{
    return intval(date('n'));
}

// Get current day
function CurrentDay()
{
    return intval(date('j'));
}

/**
 * Update sort fields
 *
 * @param string $orderBy Order By clause
 * @param string $sort Sort fields
 * @param int $opt Option (1: merge all fields, 2: merge $orderBy fields only)
 * @return string Order By clause
 */
function UpdateSortFields($orderBy, $sort, $opt)
{
    $arOrderBy = GetSortFields($orderBy);
    $cntOrderBy = count($arOrderBy);
    $arSort = GetSortFields($sort);
    $cntSort = count($arSort);
    $orderfld = "";
    for ($i = 0; $i < $cntSort; $i++) {
        $sortfld = $arSort[$i][0]; // Get sort field
        for ($j = 0; $j < $cntOrderBy; $j++) {
            $orderfld = $arOrderBy[$j][0]; // Get orderby field
            if ($orderfld == $sortfld) {
                $arOrderBy[$j][1] = $arSort[$i][1]; // Replace field
                break;
            }
        }
        if ($opt == 1) { // Append field
            if ($orderfld != $sortfld) {
                $arOrderBy[] = $arSort[$i];
            }
        }
    }
    return $arOrderBy;
}

// Get sort fields as array of [fieldName, sortDirection]
function GetSortFields($flds)
{
    $ar = [];
    if (is_array($flds)) {
        $ar = $flds;
    } elseif (is_string($flds)) {
        $temp = "";
        $tok = strtok($flds, ",");
        while ($tok !== false) {
            $temp .= $tok;
            if (substr_count($temp, "(") === substr_count($temp, ")")) { // Make sure not inside parentheses
                $ar[] = $temp;
                $temp = "";
            } else {
                $temp .= ",";
            }
            $tok = strtok(",");
        }
    }
    $ar = array_filter($ar, fn($fld) => is_array($fld) || is_string($fld) && trim($fld) !== "");
    return array_map(function ($fld) {
        if (is_array($fld)) {
            return $fld;
        }
        $fld = trim($fld);
        if (preg_match('/\s(ASC|DESC)$/i', $fld, $matches)) {
            return [trim(substr($fld, 0, -4)), $matches[1]];
        }
        return [trim($fld), ""];
    }, $ar);
}

// Get reverse sort
function ReverseSort($sorttype)
{
    return ($sorttype == "ASC") ? "DESC" : "ASC";
}

// Construct a crosstab field name
function CrosstabFieldExpression($smrytype, $smryfld, $colfld, $datetype, $val, $qc, $alias = "", $dbid = "")
{
    if (SameString($val, Config("NULL_VALUE"))) {
        $wrkval = "NULL";
        $wrkqc = "";
    } elseif (SameString($val, Config("EMPTY_VALUE"))) {
        $wrkval = "";
        $wrkqc = $qc;
    } else {
        $wrkval = $val;
        $wrkqc = $qc;
    }
    switch ($smrytype) {
        case "SUM":
            $fld = $smrytype . "(" . $smryfld . "*" . SqlDistinctFactor($colfld, $datetype, $wrkval, $wrkqc, $dbid) . ")";
            break;
        case "COUNT":
            $fld = "SUM(" . SqlDistinctFactor($colfld, $datetype, $wrkval, $wrkqc, $dbid) . ")";
            break;
        case "MIN":
        case "MAX":
            $dbtype = GetConnectionType($dbid);
            $aggwrk = SqlDistinctFactor($colfld, $datetype, $wrkval, $wrkqc, $dbid);
            $fld = $smrytype . "(IF(" . $aggwrk . "=0,NULL," . $smryfld . "))";
            if ($dbtype == "MSSQL" || $dbtype == "ORACLE" || $dbtype == "SQLITE") {
                $fld = $smrytype . "(CASE " . $aggwrk . " WHEN 0 THEN NULL ELSE " . $smryfld . " END)";
            } elseif ($dbtype == "MYSQL" || $dbtype == "POSTGRESQL") {
                $fld = $smrytype . "(IF(" . $aggwrk . "=0,NULL," . $smryfld . "))";
            }
            break;
        case "AVG":
            $sumwrk = "SUM(" . $smryfld . "*" . SqlDistinctFactor($colfld, $datetype, $wrkval, $wrkqc, $dbid) . ")";
            if ($alias != "") {
//          $sumwrk .= " AS SUM_" . $alias;
                $sumwrk .= " AS " . QuotedName("sum_" . $alias, $dbid);
            }
            $cntwrk = "SUM(" . SqlDistinctFactor($colfld, $datetype, $wrkval, $wrkqc, $dbid) . ")";
            if ($alias != "") {
//          $cntwrk .= " AS CNT_" . $alias;
                $cntwrk .= " AS " . QuotedName("cnt_" . $alias, $dbid);
            }
            return $sumwrk . ", " . $cntwrk;
    }
    if ($alias != "") {
        $fld .= " AS " . QuotedName($alias, $dbid);
    }
    return $fld;
}

/**
 * Construct SQL Distinct factor
 * - ACCESS
 * y: IIf(Year(FieldName)=1996,1,0)
 * q: IIf(DatePart(""q"",FieldName,1,0)=1,1,0))
 * m: (IIf(DatePart(""m"",FieldName,1,0)=1,1,0)))
 * others: (IIf(FieldName=val,1,0)))
 * - MS SQL
 * y: (1-ABS(SIGN(Year(FieldName)-1996)))
 * q: (1-ABS(SIGN(DatePart(q,FieldName)-1)))
 * m: (1-ABS(SIGN(DatePart(m,FieldName)-1)))
 * - MySQL
 * y: IF(YEAR(FieldName)=1996,1,0))
 * q: IF(QUARTER(FieldName)=1,1,0))
 * m: IF(MONTH(FieldName)=1,1,0))
 * - SQLITE
 * y: (CASE CAST(STRFTIME('%Y',FieldName) AS INTEGER) WHEN 1996 THEN 1 ELSE 0 END)
 * q: (CASE (CAST(STRFTIME('%m',FieldName) AS INTEGER)+2)/3 WHEN 1 THEN 1 ELSE 0 END)
 * m: (CASE CAST(STRFTIME('%m',FieldName) AS INTEGER) WHEN 1 THEN 1 ELSE 0 END)
 * - PostgreSQL
 * y: CASE WHEN TO_CHAR(FieldName,'YYYY')='1996' THEN 1 ELSE 0 END
 * q: CASE WHEN TO_CHAR(FieldName,'Q')='1' THEN 1 ELSE 0 END
 * m: CASE WHEN TO_CHAR(FieldName,'MM')=LPAD('1',2,'0') THEN 1 ELSE 0 END
 * - Oracle
 * y: DECODE(TO_CHAR(FieldName,'YYYY'),'1996',1,0)
 * q: DECODE(TO_CHAR(FieldName,'Q'),'1',1,0)
 * m: DECODE(TO_CHAR(FieldName,'MM'),LPAD('1',2,'0'),1,0)
 *
 * @param DbField $fld Field
 * @param int $dateType Date type
 * @param mixed $val Value
 * @param string $qc Quote character
 * @param int $dbid Database ID
 * @return string
 */
function SqlDistinctFactor($fld, $dateType, $val, $qc, $dbid = "")
{
    $dbtype = GetConnectionType($dbid);
    if ($dbtype == "MSSQL") {
        if ($dateType == "y" && is_numeric($val)) {
            return "(1-ABS(SIGN(Year(" . $fld . ")-" . $val . ")))";
        } elseif (($dateType == "q" || $dateType == "m") && is_numeric($val)) {
            return "(1-ABS(SIGN(DatePart(" . $dateType . "," . $fld . ")-" . $val . ")))";
        } elseif ($dateType == "d") {
            return "(CASE FORMAT(" . $fld . ",'yyyy-MM-dd') WHEN " . $qc . AdjustSql($val, $dbid) . $qc . " THEN 1 ELSE 0 END)";
        } elseif ($dateType == "dt") {
            return "(CASE FORMAT(" . $fld . ",'yyyy-MM-dd HH:mm:ss') WHEN " . $qc . AdjustSql($val, $dbid) . $qc . " THEN 1 ELSE 0 END)";
        } else {
            if ($val == "NULL") {
                return "(CASE WHEN " . $fld . " IS NULL THEN 1 ELSE 0 END)";
            } else {
                return "(CASE " . $fld . " WHEN " . $qc . AdjustSql($val, $dbid) . $qc . " THEN 1 ELSE 0 END)";
            }
        }
    } elseif ($dbtype == "MYSQL") {
        if ($dateType == "y" && is_numeric($val)) {
            return "IF(YEAR(" . $fld . ")=" . $val . ",1,0)";
        } elseif ($dateType == "q" && is_numeric($val)) {
            return "IF(QUARTER(" . $fld . ")=" . $val . ",1,0)";
        } elseif ($dateType == "m" && is_numeric($val)) {
            return "IF(MONTH(" . $fld . ")=" . $val . ",1,0)";
        } elseif ($dateType == "d") {
            return "(CASE DATE_FORMAT(" . $fld . ", '%Y-%m-%d') WHEN " . $qc . AdjustSql($val, $dbid) . $qc . " THEN 1 ELSE 0 END)";
        } elseif ($dateType == "dt") {
            return "(CASE DATE_FORMAT(" . $fld . ", '%Y-%m-%d %H:%i:%s') WHEN " . $qc . AdjustSql($val, $dbid) . $qc . " THEN 1 ELSE 0 END)";
        } else {
            if ($val == "NULL") {
                return "IF(" . $fld . " IS NULL,1,0)";
            } else {
                return "IF(" . $fld . "=" . $qc . AdjustSql($val, $dbid) . $qc . ",1,0)";
            }
        }
    } elseif ($dbtype == "SQLITE") {
        if ($dateType == "y" && is_numeric($val)) {
            return "(CASE CAST(STRFTIME('%Y', " . $fld . ") AS INTEGER) WHEN " . $val . " THEN 1 ELSE 0 END)";
        } elseif ($dateType == "q" && is_numeric($val)) {
            return "(CASE (CAST(STRFTIME('%m', " . $fld . ") AS INTEGER)+2)/3 WHEN " . $val . " THEN 1 ELSE 0 END)";
        } elseif ($dateType == "m" && is_numeric($val)) {
            return "(CASE CAST(STRFTIME('%m', " . $fld . ") AS INTEGER) WHEN " . $val . " THEN 1 ELSE 0 END)";
        } elseif ($dateType == "d") {
            return "(CASE STRFTIME('%Y-%m-%d', " . $fld . ") WHEN " . $qc . AdjustSql($val, $dbid) . $qc . " THEN 1 ELSE 0 END)";
        } elseif ($dateType == "dt") {
            return "(CASE STRFTIME('%Y-%m-%d %H:%M:%S', " . $fld . ") WHEN " . $qc . AdjustSql($val, $dbid) . $qc . " THEN 1 ELSE 0 END)";
        } else {
            if ($val == "NULL") {
                return "(CASE WHEN " . $fld . " IS NULL THEN 1 ELSE 0 END)";
            } else {
                return "(CASE " . $fld . " WHEN " . $qc . AdjustSql($val, $dbid) . $qc . " THEN 1 ELSE 0 END)";
            }
        }
    } elseif ($dbtype == "POSTGRESQL") {
        if ($dateType == "y" && is_numeric($val)) {
            return "CASE WHEN TO_CHAR(" . $fld . ",'YYYY')='" . $val . "' THEN 1 ELSE 0 END";
        } elseif ($dateType == "q" && is_numeric($val)) {
            return "CASE WHEN TO_CHAR(" . $fld . ",'Q')='" . $val . "' THEN 1 ELSE 0 END";
        } elseif ($dateType == "m" && is_numeric($val)) {
            return "CASE WHEN TO_CHAR(" . $fld . ",'MM')=LPAD('" . $val . "',2,'0') THEN 1 ELSE 0 END";
        } elseif ($dateType == "d") {
            return "CASE WHEN TO_CHAR(" . $fld . ",'YYYY') || '-' || LPAD(TO_CHAR(" . $fld . ",'MM'),2,'0') || '-' || LPAD(TO_CHAR(" . $fld . ",'DD'),2,'0')='" . $val . "' THEN 1 ELSE 0 END";
        } elseif ($dateType == "dt") {
            return "CASE WHEN TO_CHAR(" . $fld . ",'YYYY') || '-' || LPAD(TO_CHAR(" . $fld . ",'MM'),2,'0') || '-' || LPAD(TO_CHAR(" . $fld . ",'DD'),2,'0') || ' ' || LPAD(TO_CHAR(" . $fld . ",'HH24'),2,'0') || ':' || LPAD(TO_CHAR(" . $fld . ",'MI'),2,'0') || ':' || LPAD(TO_CHAR(" . $fld . ",'SS'),2,'0')='" . $val . "' THEN 1 ELSE 0 END";
        } else {
            if ($val == "NULL") {
                return "CASE WHEN " . $fld . " IS NULL THEN 1 ELSE 0 END";
            } else {
                return "CASE WHEN " . $fld . "=" . $qc . AdjustSql($val, $dbid) . $qc . " THEN 1 ELSE 0 END";
            }
        }
    } elseif ($dbtype == "ORACLE") {
        if ($dateType == "y" && is_numeric($val)) {
            return "DECODE(TO_CHAR(" . $fld . ",'YYYY'),'" . $val . "',1,0)";
        } elseif ($dateType == "q" && is_numeric($val)) {
            return "DECODE(TO_CHAR(" . $fld . ",'Q'),'" . $val . "',1,0)";
        } elseif ($dateType == "m" && is_numeric($val)) {
            return "DECODE(TO_CHAR(" . $fld . ",'MM'),LPAD('" . $val . "',2,'0'),1,0)";
        } elseif ($dateType == "d") {
            return "DECODE(" . $fld . ",TO_DATE(" . $qc . AdjustSql($val, $dbid) . $qc . ",'YYYY-MM-DD'),1,0)";
        } elseif ($dateType == "dt") {
            return "DECODE(" . $fld . ",TO_DATE(" . $qc . AdjustSql($val, $dbid) . $qc . ",'YYYY-MM-DD HH24:MI:SS'),1,0)";
        } else {
            if ($val == "NULL") {
                return "(CASE WHEN " . $fld . " IS NULL THEN 1 ELSE 0 END)";
            } else {
                return "DECODE(" . $fld . "," . $qc . AdjustSql($val, $dbid) . $qc . ",1,0)";
            }
        }
    }
}

// Evaluate summary value
function SummaryValue($val1, $val2, $ityp)
{
    if (in_array($ityp, ["SUM", "COUNT", "AVG"])) {
        if ($val2 === null || !is_numeric($val2)) {
            return $val1;
        } else {
            return ($val1 + $val2);
        }
    } elseif ($ityp == "MIN") {
        if ($val2 === null || !is_numeric($val2)) {
            return $val1; // Skip null and non-numeric
        } elseif ($val1 === null) {
            return $val2; // Initialize for first valid value
        } elseif ($val1 < $val2) {
            return $val1;
        } else {
            return $val2;
        }
    } elseif ($ityp == "MAX") {
        if ($val2 === null || !is_numeric($val2)) {
            return $val1; // Skip null and non-numeric
        } elseif ($val1 === null) {
            return $val2; // Initialize for first valid value
        } elseif ($val1 > $val2) {
            return $val1;
        } else {
            return $val2;
        }
    }
}

// Match filter value
function MatchedFilterValue($ar, $value)
{
    if (!is_array($ar)) {
        return (strval($ar) == strval($value));
    } else {
        foreach ($ar as $val) {
            if (strval($val) == strval($value)) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Render repeat column table
 *
 * @param int $totcnt Total count
 * @param int $rowcnt Zero based row count
 * @param int $repeatcnt Repeat count
 * @param int $rendertype Render type (1 or 2)
 * @return string HTML
 */
function RepeatColumnTable($totcnt, $rowcnt, $repeatcnt, $rendertype)
{
    $wrk = "";
    if ($rendertype == 1) { // Render control start
        if ($rowcnt == 0) {
            $wrk .= "<table class=\"ew-item-table\">";
        }
        if ($rowcnt % $repeatcnt == 0) {
            $wrk .= "<tr>";
        }
        $wrk .= "<td>";
    } elseif ($rendertype == 2) { // Render control end
        $wrk .= "</td>";
        if ($rowcnt % $repeatcnt == $repeatcnt - 1) {
            $wrk .= "</tr>";
        } elseif ($rowcnt == $totcnt - 1) {
            for ($i = ($rowcnt % $repeatcnt) + 1; $i < $repeatcnt; $i++) {
                $wrk .= "<td></td>";
            }
            $wrk .= "</tr>";
        }
        if ($rowcnt == $totcnt - 1) {
            $wrk .= "</table>";
        }
    }
    return $wrk;
}

// Check if the value is selected
function IsSelectedValue(&$ar, $value, $ft)
{
    if (!is_array($ar)) {
        return true;
    }
    $af = StartsString("@@", $value);
    foreach ($ar as $val) {
        if ($af || StartsString("@@", $val)) { // Advanced filters
            if ($val == $value) {
                return true;
            }
        } elseif (SameString($value, Config("NULL_VALUE")) && $value == $val) {
                return true;
        } else {
            if (CompareValueByFieldType($val, $value, $ft)) {
                return true;
            }
        }
    }
    return false;
}

// Check if advanced filter value
function IsAdvancedFilterValue($v)
{
    if (is_array($v) && count($v) > 0) {
        foreach ($v as $val) {
            if (!StartsString("@@", $val)) {
                return false;
            }
        }
        return true;
    } elseif (StartsString("@@", $v)) {
        return true;
    }
    return false;
}

// Compare values based on field type
function CompareValueByFieldType($v1, $v2, $ft)
{
    switch ($ft) {
    // Case adBigInt, adInteger, adSmallInt, adTinyInt, adUnsignedTinyInt, adUnsignedSmallInt, adUnsignedInt, adUnsignedBigInt
        case 20:
        case 3:
        case 2:
        case 16:
        case 17:
        case 18:
        case 19:
        case 21:
            if (is_numeric($v1) && is_numeric($v2)) {
                return (intval($v1) == intval($v2));
            }
            break;
    // Case adSingle, adDouble, adNumeric, adCurrency
        case 4:
        case 5:
        case 131:
        case 6:
            if (is_numeric($v1) && is_numeric($v2)) {
                return ((float)$v1 == (float)$v2);
            }
            break;
    //  Case adDate, adDBDate, adDBTime, adDBTimeStamp
        case 7:
        case 133:
        case 135:
        case 146:
        case 134:
        case 145:
            if (is_numeric(strtotime($v1)) && is_numeric(strtotime($v2))) {
                return (strtotime($v1) == strtotime($v2));
            }
            break;
        default:
            return (strcmp($v1, $v2) == 0); // Treat as string
    }
}

// Register filter group
function RegisterFilterGroup(&$fld, $groupName)
{
    global $Language;
    $filters = Config("REPORT_ADVANCED_FILTERS." . $groupName) ?: [];
    foreach ($filters as $id => $functionName) {
        RegisterFilter($fld, "@@" . $id, $Language->phrase($id), $functionName);
    }
}

// Register filter
function RegisterFilter(&$fld, $id, $name, $functionName = "")
{
    if (!is_array($fld->AdvancedFilters)) {
        $fld->AdvancedFilters = [];
    }
    $wrkid = StartsString("@@", $id) ? $id : "@@" . $id;
    $key = substr($wrkid, 2);
    $fld->AdvancedFilters[$key] = new AdvancedFilter($wrkid, $name, $functionName);
}

// Unregister filter
function UnregisterFilter(&$fld, $id)
{
    if (is_array($fld->AdvancedFilters)) {
        $wrkid = StartsString("@@", $id) ? $id : "@@" . $id;
        $key = substr($wrkid, 2);
        foreach ($fld->AdvancedFilters as $filter) {
            if ($filter->ID == $wrkid) {
                unset($fld->AdvancedFilters[$key]);
                break;
            }
        }
    }
}

// Return date value
function DateValue($fldOpr, $fldVal, $valType, $dbid = "")
{
    // Compose date string
    switch (strtolower($fldOpr)) {
        case "year":
            if ($valType == 1) {
                $wrkVal = "$fldVal-01-01";
            } elseif ($valType == 2) {
                $wrkVal = "$fldVal-12-31";
            }
            break;
        case "quarter":
            @list($y, $q) = explode("|", $fldVal);
            if (intval($y) == 0 || intval($q) == 0) {
                $wrkVal = "0000-00-00";
            } else {
                if ($valType == 1) {
                    $m = ($q - 1) * 3 + 1;
                    $m = str_pad($m, 2, "0", STR_PAD_LEFT);
                    $wrkVal = "$y-$m-01";
                } elseif ($valType == 2) {
                    $m = ($q - 1) * 3 + 3;
                    $m = str_pad($m, 2, "0", STR_PAD_LEFT);
                    $wrkVal = "$y-$m-" . DaysInMonth($y, $m);
                }
            }
            break;
        case "month":
            @list($y, $m) = explode("|", $fldVal);
            if (intval($y) == 0 || intval($m) == 0) {
                $wrkVal = "0000-00-00";
            } else {
                if ($valType == 1) {
                    $m = str_pad($m, 2, "0", STR_PAD_LEFT);
                    $wrkVal = "$y-$m-01";
                } elseif ($valType == 2) {
                    $m = str_pad($m, 2, "0", STR_PAD_LEFT);
                    $wrkVal = "$y-$m-" . DaysInMonth($y, $m);
                }
            }
            break;
        case "day":
        default:
            $wrkVal = str_replace("|", "-", $fldVal);
            $wrkVal = preg_replace('/\s+\d{2}\:\d{2}(\:\d{2})$/', "", $wrkVal); // Remove trailing time
    }

    // Add time if necessary
    if (preg_match('/(\d{4}|\d{2})-(\d{1,2})-(\d{1,2})/', $wrkVal)) { // Date without time
        if ($valType == 1) {
            $wrkVal .= " 00:00:00";
        } elseif ($valType == 2) {
            $wrkVal .= " 23:59:59";
        }
    }

    // Check if datetime
    if (preg_match('/(\d{4}|\d{2})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})/', $wrkVal)) { // DateTime
        $dateVal = $wrkVal;
    } else {
        $dateVal = "";
    }

    // Change date format if necessary
    $dbType = GetConnectionType($dbid);
    if (!SameText($dbType, "MYSQL") && !SameText($dbType, "SQLITE")) {
        $dateVal = str_replace("-", "/", $dateVal);
    }
    return $dateVal;
}

// Past
function IsPast($fldExpr, $dbid = "")
{
    $dt = date("Y-m-d H:i:s");
    $dbType = GetConnectionType($dbid);
    if (!SameText($dbType, "MYSQL") && !SameText($dbType, "SQLITE")) {
        $dt = str_replace("-", "/", $dt);
    }
    return "(" . $fldExpr . " < " . QuotedValue($dt, DataType::DATE, $dbid) . ")";
}

// Future;
function IsFuture($fldExpr, $dbid = "")
{
    $dt = date("Y-m-d H:i:s");
    $dbType = GetConnectionType($dbid);
    if (!SameText($dbType, "MYSQL") && !SameText($dbType, "SQLITE")) {
        $dt = str_replace("-", "/", $dt);
    }
    return "(" . $fldExpr . " > " . QuotedValue($dt, DataType::DATE, $dbid) . ")";
}

/**
 * WHERE class for between 2 dates
 *
 * @param string $fldExpr Field expression
 * @param string $dt1 Begin date (>=)
 * @param string $dt2 End date (<)
 * @return string
 */
function IsBetween($fldExpr, $dt1, $dt2, $dbid = "")
{
    $dbType = GetConnectionType($dbid);
    if (!SameText($dbType, "MYSQL") && !SameText($dbType, "SQLITE")) {
        $dt1 = str_replace("-", "/", $dt1);
        $dt2 = str_replace("-", "/", $dt2);
    }
    return "(" . $fldExpr . " >= " . QuotedValue($dt1, DataType::DATE, $dbid) . " AND " . $fldExpr . " < " . QuotedValue($dt2, DataType::DATE, $dbid) . ")";
}

// Last 30 days
function IsLast30Days($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m-d", strtotime("-29 days"));
    $dt2 = date("Y-m-d", strtotime("+1 days"));
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Last 14 days
function IsLast14Days($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m-d", strtotime("-13 days"));
    $dt2 = date("Y-m-d", strtotime("+1 days"));
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Last 7 days
function IsLast7Days($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m-d", strtotime("-6 days"));
    $dt2 = date("Y-m-d", strtotime("+1 days"));
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Next 30 days
function IsNext30Days($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m-d");
    $dt2 = date("Y-m-d", strtotime("+30 days"));
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Next 14 days
function IsNext14Days($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m-d");
    $dt2 = date("Y-m-d", strtotime("+14 days"));
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Next 7 days
function IsNext7Days($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m-d");
    $dt2 = date("Y-m-d", strtotime("+7 days"));
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Yesterday
function IsYesterday($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m-d", strtotime("-1 days"));
    $dt2 = date("Y-m-d");
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Today
function IsToday($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m-d");
    $dt2 = date("Y-m-d", strtotime("+1 days"));
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Tomorrow
function IsTomorrow($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m-d", strtotime("+1 days"));
    $dt2 = date("Y-m-d", strtotime("+2 days"));
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Last month
function IsLastMonth($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m", strtotime("-1 months")) . "-01";
    $dt2 = date("Y-m") . "-01";
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// This month
function IsThisMonth($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m") . "-01";
    $dt2 = date("Y-m", strtotime("+1 months")) . "-01";
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Next month
function IsNextMonth($fldExpr, $dbid = "")
{
    $dt1 = date("Y-m", strtotime("+1 months")) . "-01";
    $dt2 = date("Y-m", strtotime("+2 months")) . "-01";
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Last two weeks
function IsLast2Weeks($fldExpr, $dbid = "")
{
    if (strtotime("this Sunday") == strtotime("today")) {
        $dt1 = date("Y-m-d", strtotime("-14 days this Sunday"));
        $dt2 = date("Y-m-d", strtotime("this Sunday"));
    } else {
        $dt1 = date("Y-m-d", strtotime("-14 days last Sunday"));
        $dt2 = date("Y-m-d", strtotime("last Sunday"));
    }
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Last week
function IsLastWeek($fldExpr, $dbid = "")
{
    if (strtotime("this Sunday") == strtotime("today")) {
        $dt1 = date("Y-m-d", strtotime("-7 days this Sunday"));
        $dt2 = date("Y-m-d", strtotime("this Sunday"));
    } else {
        $dt1 = date("Y-m-d", strtotime("-7 days last Sunday"));
        $dt2 = date("Y-m-d", strtotime("last Sunday"));
    }
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// This week
function IsThisWeek($fldExpr, $dbid = "")
{
    if (strtotime("this Sunday") == strtotime("today")) {
        $dt1 = date("Y-m-d", strtotime("this Sunday"));
        $dt2 = date("Y-m-d", strtotime("+7 days this Sunday"));
    } else {
        $dt1 = date("Y-m-d", strtotime("last Sunday"));
        $dt2 = date("Y-m-d", strtotime("+7 days last Sunday"));
    }
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Next week
function IsNextWeek($fldExpr, $dbid = "")
{
    if (strtotime("this Sunday") == strtotime("today")) {
        $dt1 = date("Y-m-d", strtotime("+7 days this Sunday"));
        $dt2 = date("Y-m-d", strtotime("+14 days this Sunday"));
    } else {
        $dt1 = date("Y-m-d", strtotime("+7 days last Sunday"));
        $dt2 = date("Y-m-d", strtotime("+14 days last Sunday"));
    }
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Next two week
function IsNext2Weeks($fldExpr, $dbid = "")
{
    if (strtotime("this Sunday") == strtotime("today")) {
        $dt1 = date("Y-m-d", strtotime("+7 days this Sunday"));
        $dt2 = date("Y-m-d", strtotime("+21 days this Sunday"));
    } else {
        $dt1 = date("Y-m-d", strtotime("+7 days last Sunday"));
        $dt2 = date("Y-m-d", strtotime("+21 days last Sunday"));
    }
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Last year
function IsLastYear($fldExpr, $dbid = "")
{
    $dt1 = date("Y", strtotime("-1 years")) . "-01-01";
    $dt2 = date("Y") . "-01-01";
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// This year
function IsThisYear($fldExpr, $dbid = "")
{
    $dt1 = date("Y") . "-01-01";
    $dt2 = date("Y", strtotime("+1 years")) . "-01-01";
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Next year
function IsNextYear($fldExpr, $dbid = "")
{
    $dt1 = date("Y", strtotime("+1 years")) . "-01-01";
    $dt2 = date("Y", strtotime("+2 years")) . "-01-01";
    return IsBetween($fldExpr, $dt1, $dt2, $dbid);
}

// Days in month
function DaysInMonth($y, $m)
{
    if (in_array($m, [1, 3, 5, 7, 8, 10, 12])) {
        return 31;
    } elseif (in_array($m, [4, 6, 9, 11])) {
        return 30;
    } elseif ($m == 2) {
        return ($y % 4 == 0) ? 29 : 28;
    }
    return 0;
}

/**
 * Get group value
 * Field type:
 *  1: numeric, 2: date, 3: string
 * Group type:
 *  numeric: i = interval, n = normal
 *  date: d = Day, w = Week, m = Month, q = Quarter, y = Year
 *  string: f = first nth character, n = normal
 *
 * @param DbField $fld Field
 * @param mixed $val Value
 * @return mixed
 */
function GroupValue(&$fld, $val)
{
    $ft = $fld->Type;
    $grp = $fld->GroupByType;
    $intv = $fld->GroupInterval;
    if (in_array($ft, [20, 3, 2, 16, 4, 5, 131, 6, 17, 18, 19, 21])) { // adBigInt, adInteger, adSmallInt, adTinyInt, adSingle, adDouble, adNumeric, adCurrency, adUnsignedTinyInt, adUnsignedSmallInt, adUnsignedInt, adUnsignedBigInt (numeric)
        if (!is_numeric($val)) {
            return $val;
        }
        $wrkIntv = intval($intv);
        if ($wrkIntv <= 0) {
            $wrkIntv = 10;
        }
        return ($grp == "i") ? intval($val / $wrkIntv) : $val;
    } elseif (in_array($ft, [201, 203, 129, 130, 200, 202])) { // adLongVarChar, adLongVarWChar, adChar, adWChar, adVarChar, adVarWChar (string)
        $wrkIntv = intval($intv);
        if ($wrkIntv <= 0) {
            $wrkIntv = 1;
        }
        return ($grp == "f") ? substr(strval($val), 0, $wrkIntv) : $val;
    }
    return $val;
}

// Display group value
function DisplayGroupValue(&$fld, $val)
{
    global $Language;
    $ft = $fld->Type;
    $grp = $fld->GroupByType;
    $intv = $fld->GroupInterval;
    if ($val === null) {
        return $Language->phrase("NullLabel");
    }
    if ($val == "") {
        return $Language->phrase("EmptyLabel");
    }
    switch ($ft) {
        // Case adBigInt, adInteger, adSmallInt, adTinyInt, adSingle, adDouble, adNumeric, adCurrency, adUnsignedTinyInt, adUnsignedSmallInt, adUnsignedInt, adUnsignedBigInt (numeric)
        case 20:
        case 3:
        case 2:
        case 16:
        case 4:
        case 5:
        case 131:
        case 6:
        case 17:
        case 18:
        case 19:
        case 21:
            $wrkIntv = intval($intv);
            if ($wrkIntv <= 0) {
                $wrkIntv = 10;
            }
            switch ($grp) {
                case "i":
                    return strval($val * $wrkIntv) . " - " . strval(($val + 1) * $wrkIntv - 1);
                default:
                    return $val;
            }
            break;
        // Case adDate, adDBDate, adDBTime, adDBTimeStamp (date)
        case 7:
        case 133:
        case 135:
        case 146:
        case 134:
        case 145:
            $ar = explode("|", $val);
            switch ($grp) {
                case "y":
                    return $ar[0];
                case "q":
                    if (count($ar) < 2) {
                        return $val;
                    }
                    return FormatQuarter($ar[0], $ar[1]);
                case "m":
                    if (count($ar) < 2) {
                        return $val;
                    }
                    return FormatMonth($ar[0], $ar[1]);
                case "w":
                    if (count($ar) < 2) {
                        return $val;
                    }
                    return FormatWeek($ar[0], $ar[1]);
                case "d":
                    if (count($ar) < 3) {
                        return $val;
                    }
                    return FormatDay($ar[0], $ar[1], $ar[2]);
                case "h":
                    return FormatHour($ar[0]);
                case "min":
                    return FormatMinute($ar[0]);
                default:
                    return $val;
            }
            break;
        default: // String and others
            return $val; // Ignore
    }
}

// Format quarter
function FormatQuarter($y, $q)
{
    return "Q" . $q . "/" . $y;
}

// Format month
function FormatMonth($y, $m)
{
    return $m . "/" . $y;
}

// Format week
function FormatWeek($y, $w)
{
    return "WK" . $w . "/" . $y;
}

// Format day
function FormatDay($y, $m, $d)
{
    return $y . "-" . $m . "-" . $d;
}

// Format hour
function FormatHour($h)
{
    $h = intval($h);
    if ($h == 0) {
        return "12 AM";
    } elseif ($h < 12) {
        return $h . " AM";
    } elseif ($h == 12) {
        return "12 PM";
    }
    return ($h - 12) . " PM";
}

// Format minute
function FormatMinute($n)
{
    return $n . " MIN";
}

// Return detail filter SQL
function DetailFilterSql(&$fld, $fn, $val, $dbid = "")
{
    $ft = $fld->DataType;
    if ($fld->GroupSql != "") {
        $ft = DataType::STRING;
    }
    $ar = is_array($val) ? $val : [$val];
    $sqlwrk = "";
    foreach ($ar as $v) {
        if ($sqlwrk != "") {
            $sqlwrk .= " OR ";
        }
        $sqlwrk .= $fn;
        if ($v === null) {
            $sqlwrk .= " IS NULL";
        } else {
            $sqlwrk .= " = " . QuotedValue($v, $ft, $dbid);
        }
    }
    return $sqlwrk;
}

// Return Advanced Filter SQL
function AdvancedFilterSql(&$af, $fn, $val, $dbid = "")
{
    if (!is_array($af) || $val === null) {
        return null;
    } else {
        foreach ($af as $filter) {
            if (SameString($val, $filter->ID) && $filter->Enabled && ($func = $filter->FunctionName)) {
                $func = function_exists($func) ? $func : PROJECT_NAMESPACE . $func;
                if (function_exists($func)) {
                    return $func($fn, $dbid);
                } else {
                    return null;
                }
            }
        }
        return null;
    }
}

// Compare values by custom sequence
function CompareValueCustom($v1, $v2, $seq)
{
    if ($seq == "_number") { // Number
        if (is_numeric($v1) && is_numeric($v2)) {
            return ((float)$v1 > (float)$v2);
        }
    } elseif ($seq == "_date") { // Date
        if (is_numeric(strtotime($v1)) && is_numeric(strtotime($v2))) {
            return (strtotime($v1) > strtotime($v2));
        }
    } elseif ($seq != "") { // Custom sequence
        if (is_array($seq)) {
            $ar = $seq;
        } else {
            $ar = explode(",", $seq);
        }
        if (in_array($v1, $ar) && in_array($v2, $ar)) {
            return (array_search($v1, $ar) > array_search($v2, $ar));
        } else {
            return in_array($v2, $ar);
        }
    }
    return ($v1 > $v2);
}

// Escape chars for XML
function XmlEncode($val)
{
    return htmlspecialchars(strval($val));
}

// Load drop down list
function LoadDropDownList(&$list, $val)
{
    if (is_array($val)) {
        $ar = $val;
    } elseif ($val != Config("ALL_VALUE") && !EmptyValue($val)) {
        $ar = [$val];
    } else {
        $ar = [];
    }
    $list = [];
    foreach ($ar as $v) {
        if (!EmptyValue($v) && !StartsString("@@", $v)) {
            $list[] = $v;
        }
    }
}

// Get quick search keywords
function GetQuickSearchKeywords($search, $searchType)
{
    if ($searchType != "=") {
        $ar = [];
        // Match quoted keywords (i.e.: "...")
        if (preg_match_all('/"([^"]*)"/i', $search ?: "", $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $p = strpos($search, $match[0]);
                $str = substr($search, 0, $p);
                $search = substr($search, $p + strlen($match[0]));
                if (strlen(trim($str)) > 0) {
                    $ar = array_merge($ar, explode(" ", trim($str)));
                }
                $ar[] = $match[1]; // Save quoted keyword
            }
        }
        // Match individual keywords
        if (strlen(trim($search)) > 0) {
            $ar = array_merge($ar, explode(" ", trim($search)));
        }
    } else {
        $ar = [$search];
    }
    return $ar;
}

// Get quick search filter
function GetQuickSearchFilter($flds, $keywords, $searchType, $searchAnyFields, $dbid = "DB")
{
    // Search keyword in any fields
    if ((SameText($searchType, "OR") || SameText($searchType, "AND")) && $searchAnyFields) {
        $filter = "";
        foreach ($keywords as $keyword) {
            if ($keyword != "") {
                $ar = [$keyword];
                $thisFilter = array_reduce($flds, function ($res, $fld) use ($ar, $searchType, $dbid) {
                    AddFilter($res, GetQuickSearchFilterForField($fld, $ar, $searchType, $dbid), "OR");
                    return $res;
                }, "");
                AddFilter($filter, $thisFilter, $searchType);
            }
        }
    } else {
        $filter = array_reduce($flds, function ($res, $fld) use ($keywords, $searchType, $dbid) {
            AddFilter($res, GetQuickSearchFilterForField($fld, $keywords, $searchType, $dbid), "OR");
            return $res;
        }, "");
    }
    return $filter;
}

// Get quick search filter for field
function GetQuickSearchFilterForField($fld, $keywords, $searchType, $dbid)
{
    $defCond = SameText($searchType, "OR") ? "OR" : "AND";
    $arSql = []; // Array for SQL parts
    $arCond = []; // Array for search conditions
    $j = 0; // Number of SQL parts
    foreach ($keywords as $keyword) {
        $keyword = trim($keyword);
        if (Config("BASIC_SEARCH_IGNORE_PATTERN") != "") {
            $keyword = preg_replace(Config("BASIC_SEARCH_IGNORE_PATTERN"), "\\", $keyword);
            $ar = explode("\\", $keyword);
        } else {
            $ar = [$keyword];
        }
        foreach ($ar as $keyword) {
            if ($keyword != "") {
                $wrk = "";
                if ($keyword == "OR" && $searchType == "") {
                    if ($j > 0) {
                        $arCond[$j - 1] = "OR";
                    }
                } elseif ($keyword == Config("NULL_VALUE")) {
                    $wrk = $fld->Expression . " IS NULL";
                } elseif ($keyword == Config("NOT_NULL_VALUE")) {
                    $wrk = $fld->Expression . " IS NOT NULL";
                } elseif ($fld->IsVirtual && $fld->Visible) {
                    $wrk = $fld->VirtualExpression . Like(Wildcard($keyword, "LIKE", $dbid), $dbid);
                } elseif ($fld->DataType != DataType::NUMBER || is_numeric($keyword)) {
                    $wrk = $fld->BasicSearchExpression . Like(Wildcard($keyword, "LIKE", $dbid), $dbid);
                }
                if ($wrk != "") {
                    $arSql[$j] = $wrk;
                    $arCond[$j] = $defCond;
                    $j += 1;
                }
            }
        }
    }
    $cnt = count($arSql);
    $quoted = false;
    $sql = "";
    if ($cnt > 0) {
        for ($i = 0; $i < $cnt - 1; $i++) {
            if ($arCond[$i] == "OR") {
                if (!$quoted) {
                    $sql .= "(";
                }
                $quoted = true;
            }
            $sql .= $arSql[$i];
            if ($quoted && $arCond[$i] != "OR") {
                $sql .= ")";
                $quoted = false;
            }
            $sql .= " " . $arCond[$i] . " ";
        }
        $sql .= $arSql[$cnt - 1];
        if ($quoted) {
            $sql .= ")";
        }
    }
    return $sql;
}

// Get report filter
function GetReportFilter(&$fld, $default = false, $dbid = "")
{
    $dbtype = GetConnectionType($dbid);
    $fldName = $fld->Name;
    $fldExpression = $fld->searchExpression();
    $fldDataType = $fld->searchDataType();
    $fldDateTimeFormat = $fld->DateTimeFormat;
    $fldVal = $default ? $fld->AdvancedSearch->SearchValueDefault : $fld->AdvancedSearch->SearchValue;
    $fldOpr = $default ? $fld->AdvancedSearch->SearchOperatorDefault : $fld->AdvancedSearch->SearchOperator;
    $fldCond = $default ? $fld->AdvancedSearch->SearchConditionDefault : $fld->AdvancedSearch->SearchCondition;
    $fldVal2 = $default ? $fld->AdvancedSearch->SearchValue2Default : $fld->AdvancedSearch->SearchValue2;
    $fldOpr2 = $default ? $fld->AdvancedSearch->SearchOperator2Default : $fld->AdvancedSearch->SearchOperator2;
    $fldVal = ConvertSearchValue($fldVal, $fldOpr, $fld);
    $fldVal2 = ConvertSearchValue($fldVal2, $fldOpr2, $fld);
    $fldOpr = ConvertSearchOperator($fldOpr, $fld, $fldVal);
    $fldOpr2 = ConvertSearchOperator($fldOpr2, $fld, $fldVal2);
    $wrk = "";
    if (in_array($fldOpr, ["BETWEEN", "NOT BETWEEN"])) {
        $isValidValue = $fldDataType != DataType::NUMBER || $fld->VirtualSearch || IsNumericSearchValue($fldVal, $fldOpr, $fld) && IsNumericSearchValue($fldVal2, $fldOpr2, $fld);
        if ($fldVal != "" && $fldVal2 != "" && $isValidValue) {
            $wrk = $fldExpression . " " . $fldOpr . " " . QuotedValue($fldVal, $fldDataType, $dbid) .
                " AND " . QuotedValue($fldVal2, $fldDataType, $dbid);
        }
    } else {
        // Handle first value
        if ($fldVal != "" && IsValidOperator($fldOpr)) {
            $wrk = SearchFilter($fldExpression, $fldOpr, $fldVal, $fldDataType, $dbid);
        }
        // Handle second value
        $wrk2 = "";
        if ($fldVal2 != "" && !EmptyValue($fldOpr2) && IsValidOperator($fldOpr2)) {
            $wrk2 = SearchFilter($fldExpression, $fldOpr2, $fldVal2, $fldDataType, $dbid);
        }
        // Combine SQL
        AddFilter($wrk, $wrk2, $fldCond == "OR" ? "OR" : "AND");
    }
    return $wrk;
}

// Return date search string
function GetDateFilterSql($fldExpr, $fldOpr, $fldVal, $fldType, $dbid = "")
{
    $wrkVal1 = DateValue($fldOpr, $fldVal, 1, $dbid);
    $wrkVal2 = DateValue($fldOpr, $fldVal, 2, $dbid);
    if ($wrkVal1 != "" && $wrkVal2 != "") {
        return $fldExpr . " BETWEEN " . QuotedValue($wrkVal1, $fldType, $dbid) . " AND " . QuotedValue($wrkVal2, $fldType, $dbid);
    } else {
        return "";
    }
}

// Group filter
function GroupSql($fldExpr, $grpType, $grpInt = 0, $dbid = "")
{
    $dbtype = GetConnectionType($dbid);
    switch ($grpType) {
        case "f": // First n characters
            if ($dbtype == "MSSQL" || $dbtype == "MYSQL") { // MSSQL / MySQL
                return "SUBSTRING(" . $fldExpr . ",1," . $grpInt . ")";
            } else { // SQLite / PostgreSQL / Oracle
                return "SUBSTR(" . $fldExpr . ",1," . $grpInt . ")";
            }
            break;
        case "i": // Interval
            if ($dbtype == "MSSQL") { // MSSQL
                return "(" . $fldExpr . "/" . $grpInt . ")";
            } elseif ($dbtype == "MYSQL") { // MySQL
                return "(" . $fldExpr . " DIV " . $grpInt . ")";
            } elseif ($dbtype == "SQLITE") { // SQLite
                return "CAST(" . $fldExpr . "/" . $grpInt . " AS TEXT)";
            } elseif ($dbtype == "POSTGRESQL") { // PostgreSQL
                return "(" . $fldExpr . "/" . $grpInt . ")";
            } else { // Oracle
                return "FLOOR(" . $fldExpr . "/" . $grpInt . ")";
            }
            break;
        case "y": // Year
            if ($dbtype == "MSSQL" || $dbtype == "MYSQL") { // MSSQL / MySQL
                return "YEAR(" . $fldExpr . ")";
            } elseif ($dbtype == "SQLITE") { // SQLite
                return "CAST(STRFTIME('%Y'," . $fldExpr . ") AS INTEGER)";
            } else { // PostgreSQL / Oracle
                return "TO_CHAR(" . $fldExpr . ",'YYYY')";
            }
            break;
        case "xq": // Quarter
            if ($dbtype == "MSSQL") { // MSSQL
                return "DATEPART(QUARTER," . $fldExpr . ")";
            } elseif ($dbtype == "MYSQL") { // MySQL
                return "QUARTER(" . $fldExpr . ")";
            } elseif ($dbtype == "SQLITE") { // SQLite
                return "CAST(STRFTIME('%m'," . $fldExpr . ") AS INTEGER)+2)/3";
            } else { // PostgreSQL / Oracle
                return "TO_CHAR(" . $fldExpr . ",'Q')";
            }
            break;
        case "q": // Quarter (with year)
            if ($dbtype == "MSSQL") { // MSSQL
                return "(STR(YEAR(" . $fldExpr . "),4) + '|' + STR(DATEPART(QUARTER," . $fldExpr . "),1))";
            } elseif ($dbtype == "MYSQL") { // MySQL
                return "CONCAT(CAST(YEAR(" . $fldExpr . ") AS CHAR(4)), '|', CAST(QUARTER(" . $fldExpr . ") AS CHAR(1)))";
            } elseif ($dbtype == "SQLITE") { // SQLite
                return "(CAST(STRFTIME('%Y'," . $fldExpr . ") AS TEXT) || '|' || CAST((CAST(STRFTIME('%m'," . $fldExpr . ") AS INTEGER)+2)/3 AS TEXT))";
            } else { // PostgreSQL / Oracle
                return "(TO_CHAR(" . $fldExpr . ",'YYYY') || '|' || TO_CHAR(" . $fldExpr . ",'Q'))";
            }
            break;
        case "xm": // Month
            if ($dbtype == "MSSQL" || $dbtype == "MYSQL") { // MSSQL / MySQL
                return "MONTH(" . $fldExpr . ")";
            } elseif ($dbtype == "SQLITE") { // SQLite
                return "CAST(STRFTIME('%m'," . $fldExpr . ") AS INTEGER)";
            } else { // PostgreSQL / Oracle
                return "TO_CHAR(" . $fldExpr . ",'MM')";
            }
            break;
        case "m": // Month (with year)
            if ($dbtype == "MSSQL") { // MSSQL
                return "(STR(YEAR(" . $fldExpr . "),4) + '|' + REPLACE(STR(MONTH(" . $fldExpr . "),2,0),' ','0'))";
            } elseif ($dbtype == "MYSQL") { // MySQL
                return "CONCAT(CAST(YEAR(" . $fldExpr . ") AS CHAR(4)), '|', CAST(LPAD(MONTH(" . $fldExpr . "),2,'0') AS CHAR(2)))";
            } elseif ($dbtype == "SQLITE") { // SQLite
                return "CAST(STRFTIME('%Y|%m'," . $fldExpr . ") AS TEXT)";
            } else { // PostgreSQL / Oracle
                return "(TO_CHAR(" . $fldExpr . ",'YYYY') || '|' || TO_CHAR(" . $fldExpr . ",'MM'))";
            }
            break;
        case "w":
            if ($dbtype == "MSSQL") { // MSSQL
                return "(STR(YEAR(" . $fldExpr . "),4) + '|' + REPLACE(STR(DATEPART(WEEK," . $fldExpr . "),2,0),' ','0'))";
            } elseif ($dbtype == "MYSQL") { // MySQL
                //return "CONCAT(CAST(YEAR(" . $fldExpr . ") AS CHAR(4)), '|', CAST(LPAD(WEEKOFYEAR(" . $fldExpr . "),2,'0') AS CHAR(2)))";
                return "CONCAT(CAST(YEAR(" . $fldExpr . ") AS CHAR(4)), '|', CAST(LPAD(WEEK(" . $fldExpr . ",0),2,'0') AS CHAR(2)))";
            } elseif ($dbtype == "SQLITE") { // SQLite
                return "CAST(STRFTIME('%Y|%W'," . $fldExpr . ") AS TEXT)";
            } else {
                return "(TO_CHAR(" . $fldExpr . ",'YYYY') || '|' || TO_CHAR(" . $fldExpr . ",'WW'))";
            }
            break;
        case "d":
            if ($dbtype == "MSSQL") { // MSSQL
                return "(STR(YEAR(" . $fldExpr . "),4) + '|' + REPLACE(STR(MONTH(" . $fldExpr . "),2,0),' ','0') + '|' + REPLACE(STR(DAY(" . $fldExpr . "),2,0),' ','0'))";
            } elseif ($dbtype == "MYSQL") { // MySQL
                return "CONCAT(CAST(YEAR(" . $fldExpr . ") AS CHAR(4)), '|', CAST(LPAD(MONTH(" . $fldExpr . "),2,'0') AS CHAR(2)), '|', CAST(LPAD(DAY(" . $fldExpr . "),2,'0') AS CHAR(2)))";
            } elseif ($dbtype == "SQLITE") { // SQLite
                return "CAST(STRFTIME('%Y|%m|%d'," . $fldExpr . ") AS TEXT)";
            } else {
                return "(TO_CHAR(" . $fldExpr . ",'YYYY') || '|' || LPAD(TO_CHAR(" . $fldExpr . ",'MM'),2,'0') || '|' || LPAD(TO_CHAR(" . $fldExpr . ",'DD'),2,'0'))";
            }
            break;
        case "h":
            if ($dbtype == "MSSQL" || $dbtype == "MYSQL") { // Access / MSSQL / MySQL
                return "HOUR(" . $fldExpr . ")";
            } elseif ($dbtype == "SQLITE") { // SQLite
                return "CAST(STRFTIME('%H'," . $fldExpr . ") AS INTEGER)";
            } else {
                return "TO_CHAR(" . $fldExpr . ",'HH24')";
            }
            break;
        case "min":
            if ($dbtype == "MSSQL" || $dbtype == "MYSQL") { // Access / MSSQL / MySQL
                return "MINUTE(" . $fldExpr . ")";
            } elseif ($dbtype == "SQLITE") { // SQLite
                return "CAST(STRFTIME('%M'," . $fldExpr . ") AS INTEGER)";
            } else {
                return "TO_CHAR(" . $fldExpr . ",'MI')";
            }
            break;
    }
    return "";
}

// Get/Set Laravel session
function LaravelSession(...$args)
{
    $store = Request()?->getAttribute("SESSION_STORE");
    if ($store) {
        $numargs = count($args);
        if ($numargs == 1) {
            if (is_string($args[0])) { // Get
                return $store->get($args[0]);
            } elseif (is_array($args[0])) { // Put
                $store->put($args[0]);
            }
        } elseif ($numargs == 2) { // Put
            $store->put($args[0], $args[1]);
        }
    }
    return $store;
}
