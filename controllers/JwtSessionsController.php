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

class JwtSessionsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/JwtSessionsList[/{session_id}]", [PermissionMiddleware::class], "list.jwt_sessions")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "JwtSessionsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/JwtSessionsAdd[/{session_id}]", [PermissionMiddleware::class], "add.jwt_sessions")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "JwtSessionsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/JwtSessionsView[/{session_id}]", [PermissionMiddleware::class], "view.jwt_sessions")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "JwtSessionsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/JwtSessionsEdit[/{session_id}]", [PermissionMiddleware::class], "edit.jwt_sessions")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "JwtSessionsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/JwtSessionsDelete[/{session_id}]", [PermissionMiddleware::class], "delete.jwt_sessions")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "JwtSessionsDelete");
    }
}
