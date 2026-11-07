<?php

namespace PHPMaker2024\FIMS;

use Slim\App;

// Handle Routes
return function (App $app) {
    // Dispatch API action event
    DispatchEvent(new ApiActionEvent($app), ApiActionEvent::NAME);

    // Other API actions
    $app->any('/[{params:.*}]', ApiController::class)->add(ApiPermissionMiddleware::class)->setName("catchall");
};
