<?php

namespace PHPMaker2024\FIMS;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Composer\Script\Event;
use Symfony\Component\Finder\Finder;
use Symfony\Component\VarExporter\VarExporter;
use Slim\App;
use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\Node\Stmt\Namespace_;
use PHPMaker2024\FIMS\Attributes\Delete;
use PHPMaker2024\FIMS\Attributes\Get;
use PHPMaker2024\FIMS\Attributes\Map;
use PHPMaker2024\FIMS\Attributes\Options;
use PHPMaker2024\FIMS\Attributes\Patch;
use PHPMaker2024\FIMS\Attributes\Post;
use PHPMaker2024\FIMS\Attributes\Put;

class RouteAttributes
{
    public static string $CONTROLLERS_FOLDER = __DIR__ . "/../controllers";
    public static string $API_CONTROLLERS = "*ApiController.php"; // Note: API controller file names must end with "ApiController"
    public static string $CACHE_FOLDER = "log/cache"; // Cache folder
    public static string $ROUTE_ATTRIBUTES_FILE = "RouteAttributes.php"; // Route attributes file under CACHE_FOLDER
    public static string $API_ROUTE_ATTRIBUTES_FILE = "ApiRouteAttributes.php"; // API Route attributes file under CACHE_FOLDER
    public static $Logger;

    /**
     * Write debug message to log file
     */
    protected static function debug(string $msg): void
    {
        echo $msg . "\n";
    }

    /**
     * Get cache folder
     *
     * @return string
     */
    protected static function getCacheFolder(): string
    {
        return __DIR__ . "/../" . self::$CACHE_FOLDER . "/";
    }

    /**
     * Is remote path
     *
     * @param string $path Path
     * @return bool
     */
    protected static function isRemote($path): bool
    {
        return str_contains($path, '://');
    }

    /**
     * Create folder
     *
     * @param string $dir Directory
     * @param int $mode Permissions
     * @return bool
     */
    public static function createFolder($dir, $mode = 0)
    {
        return is_dir($dir) || ($mode ? @mkdir($dir, $mode, true) : (@mkdir($dir, 0777, true) || @mkdir($dir, 0666, true) || @mkdir($dir, 0444, true)));
    }

    /**
     * Get route attributes from controllers folder
     *
     * @param bool $api For API or not
     * @return array
     */
    public static function get(bool $api = false): array
    {
        try {
            $routes = [];
            $finder = Finder::create()->files()->in(self::$CONTROLLERS_FOLDER);
            if ($api) {
                $finder->name(self::$API_CONTROLLERS);
            } else {
                $finder->name("*.php")->notName(self::$API_CONTROLLERS);
            }
            $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
            foreach ($finder as $file) {
                $namespace = __NAMESPACE__;
                try {
                    $stmts = $parser->parse($file->getContents());
                    if ($stmts[0] instanceof Namespace_) {
                        $namespace = implode("\\", $stmts[0]->name->parts);
                    }
                } catch (Error $e) {
                    self::debug("Warning: " . $e->getMessage());
                }
                try {
                    $className = $file->getFilenameWithoutExtension();
                    $reflectionClass = new ReflectionClass($namespace . "\\" . $className);
                    foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                        foreach ($method->getAttributes() as $attribute) {
                            $inst = $attribute->newInstance();
                            $inst->setHandler($method->class . ":" . $method->getName());
                            if ($inst instanceof Map) {
                                $routes[] = $inst;
                            }
                        }
                    }
                } catch (ReflectionException $e) {
                    self::debug("Warning: " . $e->getMessage());
                }
            }
            $cacheFile = $api ? self::$API_ROUTE_ATTRIBUTES_FILE : self::$ROUTE_ATTRIBUTES_FILE;
            if (!RouteAttributes::isRemote(self::getCacheFolder()) && self::createFolder(self::getCacheFolder())) {
                file_put_contents(self::getCacheFolder() . $cacheFile, "<?php return " . VarExporter::export($routes) . ";");
            }
            return $routes;
        } catch (\Exception $e) {
            self::debug($e->getMessage());
        }
    }

    /**
     * Register routes
     *
     * @param App $app Slim app
     * @return void
     */
    public static function registerRoutes(App $app): void
    {
        $cacheFile = self::getCacheFolder() . self::$ROUTE_ATTRIBUTES_FILE;
        if (!RouteAttributes::isRemote($cacheFile) && file_exists($cacheFile)) {
            $routeAttributes = require $cacheFile;
        } else {
            $routeAttributes = self::get();
        }
        foreach ($routeAttributes as $attr) {
            $route = $app->map($attr->getMethods(), $attr->getPattern(), $attr->getHandler());
            foreach ($attr->getMiddleware() as $middleware) {
                $route->add($app->getContainer()->get($middleware));
            }
            $name = $attr->getName();
            if ($name) {
                $route->setName($name);
            }
        }
    }

    /**
     * Register API routes
     *
     * @param App $app Slim app
     * @return void
     */
    public static function registerApiRoutes(App $app): void
    {
        $cacheFile = self::getCacheFolder() . self::$API_ROUTE_ATTRIBUTES_FILE;
        if (!RouteAttributes::isRemote($cacheFile) && file_exists($cacheFile)) {
            $routeAttributes = require $cacheFile;
        } else {
            $routeAttributes = self::get(true);
        }
        foreach ($routeAttributes as $attr) {
            $route = $app->map($attr->getMethods(), $attr->getPattern(), $attr->getHandler());
            foreach ($attr->getMiddleware() as $middleware) {
                $route->add($app->getContainer()->get($middleware));
            }
            $name = $attr->getName();
            if ($name) {
                $route->setName($name);
            }
        }
    }

    /**
     * Dispatch route attributes to cache file
     * Note: Do NOT dispatch FastRoute cache, or routes without attributes will not be included in cache.
     *
     * @param $event Composer event
     * @return void
     */
    public static function dispatch(Event $event): void
    {
        self::get(); // Non-API routes
        if (file_exists(self::getCacheFolder() . self::$ROUTE_ATTRIBUTES_FILE)) {
            echo self::$ROUTE_ATTRIBUTES_FILE . " generated\n";
        }
        self::get(true); // API routes
        if (file_exists(self::getCacheFolder() . self::$API_ROUTE_ATTRIBUTES_FILE)) {
            echo self::$API_ROUTE_ATTRIBUTES_FILE . " generated\n";
        }
    }
}
