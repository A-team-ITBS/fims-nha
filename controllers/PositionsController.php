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

class PositionsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/PositionsList[/{position_id}]", [PermissionMiddleware::class], "list.positions")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PositionsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/PositionsAdd[/{position_id}]", [PermissionMiddleware::class], "add.positions")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PositionsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/PositionsView[/{position_id}]", [PermissionMiddleware::class], "view.positions")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PositionsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/PositionsEdit[/{position_id}]", [PermissionMiddleware::class], "edit.positions")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PositionsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/PositionsDelete[/{position_id}]", [PermissionMiddleware::class], "delete.positions")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PositionsDelete");
    }
}
