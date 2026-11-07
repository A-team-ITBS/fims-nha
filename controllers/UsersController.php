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

class UsersController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/UsersList[/{user_id}]", [PermissionMiddleware::class], "list.users")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UsersList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/UsersAdd[/{user_id}]", [PermissionMiddleware::class], "add.users")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UsersAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/UsersView[/{user_id}]", [PermissionMiddleware::class], "view.users")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UsersView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/UsersEdit[/{user_id}]", [PermissionMiddleware::class], "edit.users")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UsersEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/UsersDelete[/{user_id}]", [PermissionMiddleware::class], "delete.users")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UsersDelete");
    }
}
