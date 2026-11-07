<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\{UserProfile, Language, AdvancedSecurity, Timer, HttpErrorHandler, RouteAttributes};
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use DI\Container as Container;
use DI\ContainerBuilder;
use Selective\SameSiteCookie\SameSiteCookieConfiguration;
use Selective\SameSiteCookie\SameSiteCookieMiddleware;
use Selective\SameSiteCookie\SameSiteSessionMiddleware;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Exception\HttpInternalServerErrorException;
use Middlewares\Whoops;
use Dflydev\DotAccessData\Data;

// Relative path
$RELATIVE_PATH = "../";

// Autoload
require_once "../vendor/autoload.php";

// Require files
require_once "../src/constants.php";
require_once "../src/config.php";
require_once "../src/phpfn.php";
$ConfigData = new Data($CONFIG); // Ensure that $ConfigData is accessible by Global Codes
require_once "../src/userfn.php";

// Dispatch configuration event
DispatchEvent(new ConfigurationEvent($ConfigData), ConfigurationEvent::NAME);

// Check PHP extensions
$exts = array_filter(Config("PHP_EXTENSIONS"), fn($ext) => !extension_loaded($ext), ARRAY_FILTER_USE_KEY);
if (count($exts)) {
    $exts = array_map(fn($ext) => '<p><a href="https://www.php.net/manual/en/book.' . $exts[$ext] . '.php" target="_blank">' . $ext . '</a></p>', array_keys($exts));
    die("<p>Missing PHP extension(s)! Please install or enable the following required PHP extension(s) first:</p>" . implode("", $exts));
} elseif (!defined("LIBXML_HTML_NODEFDTD")) {
    die('<p>Missing PHP <a href="https://www.php.net/manual/en/book.libxml.php" target="_blank">Libxml</a> extension (>= 2.7.8). Please install or enable it first.</p>');
}

// Environment
$isProduction = IsProduction();
$isDebug = IsDebug();

// Set warnings and notices as errors
if ($isDebug && Config("REPORT_ALL_ERRORS")) {
    ini_set("display_errors", 1);
    ini_set("display_startup_errors", 1);
    error_reporting(E_ALL);
    set_error_handler(function ($severity, $message, $file, $line) {
        if (error_reporting() & $severity) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }
    });
}

// Instantiate PHP-DI container builder
$containerBuilder = new ContainerBuilder();
$containerBuilder->useAttributes(true);

// Enable container compilation
if ($isProduction && Config("COMPILE_CONTAINER") && !IsRemote(Config("UPLOAD_DEST_PATH"))) {
    $cacheFolder = Config("CACHE_FOLDER");
    if (CreateFolder($cacheFolder)) {
        $containerBuilder->enableCompilation($cacheFolder);
    }
}

// Add definitions
$containerBuilder->addDefinitions("../src/definitions.php");

// Dispatch container build event
DispatchEvent(new ContainerBuildEvent($containerBuilder), ContainerBuildEvent::NAME);

// Build PHP-DI container instance
$container = $containerBuilder->build();

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();
$callableResolver = $app->getCallableResolver();

// Display error details
$displayErrorDetails = $isDebug;
$logErrorToFile = Config("LOG_ERROR_TO_FILE");
$logErrors = $logErrorToFile || $isDebug;
$logErrorDetails = $logErrorToFile || $isDebug || Config("LOG_ERROR_DETAILS");

// Create request object
$serverRequestCreator = ServerRequestCreatorFactory::create();
$Request = $serverRequestCreator->createServerRequestFromGlobals();

// Create error handler
$ResponseFactory = $app->getResponseFactory();
$errorHandler = (new HttpErrorHandler($callableResolver, $ResponseFactory))
    ->setLayoutTemplate("layout.php")
    ->setErrorTemplate("Error.php")
    ->setShowSourceCode(false);

// Set base path
$basePath = BasePath();
$app->setBasePath($basePath);

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Add CORS middleware
$app->add(new CorsMiddleware([
    "Access-Control-Allow-Origin" => "*",
    "Access-Control-Allow-Headers" => "X-Requested-With, Origin, X-Authorization",
    "Access-Control-Allow-Methods" => "GET, POST, PUT, PATCH, DELETE, OPTIONS",
    "Access-Control-Allow-Credentials" => true
]));

// Add routing middleware (after CORS middleware so routing is performed first)
$app->addRoutingMiddleware();

// Is API
$IsApi = true;

// Set route cache file
if (Config("USE_ROUTE_CACHE") && !IsRemote(Config("LOG_PATH"))) {
    $routeCollector = $app->getRouteCollector();
    $cacheFolder = Config("CACHE_FOLDER");
    if (CreateFolder($cacheFolder)) {
        $routeCollector->setCacheFile($cacheFolder . "/" . Config("API_ROUTE_CACHE_FILE"));
    }
}

// Register routes (Add permission middleware)
RouteAttributes::registerApiRoutes($app);
(require_once "../src/apiroutes.php")($app);

// Add SameSite cookie/session middleware
$cookieConfiguration = new SameSiteCookieConfiguration([
    "start_session" => UseSession($Request),
    "same_site" => Config("COOKIE_SAMESITE"),
    "http_only" => Config("COOKIE_HTTP_ONLY"),
    "secure" => Config("COOKIE_SECURE")
]);
$app->add(new SameSiteCookieMiddleware($cookieConfiguration));
$app->add(new SameSiteSessionMiddleware(configuration: $cookieConfiguration));

// Add error handling middlewares
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails);
$errorMiddleware->setDefaultErrorHandler($errorHandler);
if (Config("MAINTENANCE_MODE")) {
    $app->add((new MaintenanceMiddleware($ResponseFactory))
        ->setRetryAfter(Config("MAINTENANCE_RETRY_AFTER"))
        ->setTemplate(Config("MAINTENANCE_TEMPLATE")));
}

// Run app
$app->run();
