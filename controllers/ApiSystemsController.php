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

class ApiSystemsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/ApiSystemsList[/{system_id}]", [PermissionMiddleware::class], "list.api_systems")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiSystemsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/ApiSystemsAdd[/{system_id}]", [PermissionMiddleware::class], "add.api_systems")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiSystemsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/ApiSystemsView[/{system_id}]", [PermissionMiddleware::class], "view.api_systems")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiSystemsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/ApiSystemsEdit[/{system_id}]", [PermissionMiddleware::class], "edit.api_systems")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiSystemsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/ApiSystemsDelete[/{system_id}]", [PermissionMiddleware::class], "delete.api_systems")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiSystemsDelete");
    }
}
