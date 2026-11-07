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

class SystemsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/SystemsList[/{system_id}]", [PermissionMiddleware::class], "list.systems")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "SystemsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/SystemsAdd[/{system_id}]", [PermissionMiddleware::class], "add.systems")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "SystemsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/SystemsView[/{system_id}]", [PermissionMiddleware::class], "view.systems")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "SystemsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/SystemsEdit[/{system_id}]", [PermissionMiddleware::class], "edit.systems")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "SystemsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/SystemsDelete[/{system_id}]", [PermissionMiddleware::class], "delete.systems")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "SystemsDelete");
    }
}
