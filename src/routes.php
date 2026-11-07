<?php

namespace PHPMaker2024\FIMS;

use Slim\App;
use Slim\Exception\HttpNotFoundException;

// Handle Routes
return function (App $app) {
    // Dispatch route action event
    $event = new RouteActionEvent($app);
    DispatchEvent($event, RouteActionEvent::NAME);

    /**
     * Catch-all route to serve a 404 Not Found page if none of the routes match
     * NOTE: Make sure this route is defined last.
     */
    if (!$event->isPropagationStopped()) {
        $app->map(
            ["GET", "POST", "PUT", "DELETE", "PATCH"],
            '/{routes:.+}',
            fn($request, $response, $params) => throw new HttpNotFoundException($request, str_replace("%p", $params["routes"], Container("app.language")->phrase("PageNotFound")))
        );
    }
};
