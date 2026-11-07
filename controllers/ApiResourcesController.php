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

class ApiResourcesController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/ApiResourcesList[/{resource_id}]", [PermissionMiddleware::class], "list.api_resources")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiResourcesList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/ApiResourcesAdd[/{resource_id}]", [PermissionMiddleware::class], "add.api_resources")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiResourcesAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/ApiResourcesView[/{resource_id}]", [PermissionMiddleware::class], "view.api_resources")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiResourcesView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/ApiResourcesEdit[/{resource_id}]", [PermissionMiddleware::class], "edit.api_resources")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiResourcesEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/ApiResourcesDelete[/{resource_id}]", [PermissionMiddleware::class], "delete.api_resources")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiResourcesDelete");
    }
}
