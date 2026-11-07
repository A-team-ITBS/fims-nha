<?php

namespace PHPMaker2024\FIMS;

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Routing\RouteContext;

/**
 * CORS middleware
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Constructor
     */
    public function __construct(public array $Config = [])
    {
    }

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
        // Set up request
        $GLOBALS["Request"] = $request;
        $response = $handler->handle($request);
        $headers = array_keys($this->Config);

        // Access-Control-Allow-Origin
        if (in_array("Access-Control-Allow-Origin", $headers)) {
            $response = $response->withHeader("Access-Control-Allow-Origin", $this->Config["Access-Control-Allow-Origin"] ?: "*");
        }

        // Access-Control-Allow-Methods
        if (in_array("Access-Control-Allow-Methods", $headers)) {
            if ($this->Config["Access-Control-Allow-Methods"]) {
                $response = $response->withHeader("Access-Control-Allow-Methods", $this->Config["Access-Control-Allow-Methods"]);
            } else { // Default
                $methods = RoutingResults($request)?->getAllowedMethods() ?? [];
                $response = $response->withHeader("Access-Control-Allow-Methods", implode(", ", array_unique($methods)));
            }
        }

        // Access-Control-Allow-Headers
        if (in_array("Access-Control-Allow-Headers", $headers)) {
            if ($this->Config["Access-Control-Allow-Headers"]) {
                $response = $response->withHeader("Access-Control-Allow-Headers", $this->Config["Access-Control-Allow-Headers"]);
            } else { // Default
                $requestHeaders = $request->getHeaderLine("Access-Control-Request-Headers");
                $response = $response->withHeader("Access-Control-Allow-Headers", $requestHeaders);
            }
        }

        // Access-Control-Allow-Credentials
        if (in_array("Access-Control-Allow-Credentials", $headers)) {
            if ($this->Config["Access-Control-Allow-Credentials"] === true) {
                $response = $response->withHeader("Access-Control-Allow-Credentials", "true"); // The only valid value for this header is true (case-sensitive)
            }
        }
        return $response;
    }
}
