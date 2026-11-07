<?php

namespace PHPMaker2024\FIMS;

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Routing\RouteContext;
use Illuminate\Session\Store;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Filesystem\Filesystem;

/**
 * Laravel session middleware
 */
class LaravelSessionMiddleware implements MiddlewareInterface
{
    /**
     * Invoke middleware
     *
     * @param Request $request Request
     * @param RequestHandler $handler Handler
     *
     * @return Response
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $store = null;
        if (session_status() == PHP_SESSION_ACTIVE && class_exists(\Illuminate\Session\Store::class)) {
            $sessionId = session_id();
            $store = new Store(
                $sessionId,
                new FileSessionHandler(
                    new Filesystem(),
                    Config("LARAVEL.SESSION_PATH"),
                    Config("LARAVEL.SESSION_LIFETIME")
                ),
                sha1($sessionId)
            );
            $store->start();
            $store->put("BasePath", BasePath());
            $request = $request->withAttribute("SESSION_STORE", $store);
        }
        $response = $handler->handle($request);

        // Save user primary key to Laravel session
        $store?->put("UserId", IsSysAdmin() ? -1 : CurrentUserPrimaryKey());
        $store?->save();
        return $response;
    }
}
