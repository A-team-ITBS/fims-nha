<?php

namespace PHPMaker2024\FIMS;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PHPMaker2024\FIMS\Attributes\Delete;
use PHPMaker2024\FIMS\Attributes\Get;
use PHPMaker2024\FIMS\Attributes\Map;
use PHPMaker2024\FIMS\Attributes\Options;
use PHPMaker2024\FIMS\Attributes\Patch;
use PHPMaker2024\FIMS\Attributes\Post;
use PHPMaker2024\FIMS\Attributes\Put;

class NotificationsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/NotificationsList[/{id:.*}]", [PermissionMiddleware::class], "list.notifications")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "NotificationsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/NotificationsAdd[/{id:.*}]", [PermissionMiddleware::class], "add.notifications")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "NotificationsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/NotificationsView[/{id:.*}]", [PermissionMiddleware::class], "view.notifications")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "NotificationsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/NotificationsEdit[/{id:.*}]", [PermissionMiddleware::class], "edit.notifications")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "NotificationsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/NotificationsDelete[/{id:.*}]", [PermissionMiddleware::class], "delete.notifications")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "NotificationsDelete");
    }
}
