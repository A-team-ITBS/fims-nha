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

class OfficesController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/OfficesList[/{office_id}]", [PermissionMiddleware::class], "list.offices")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "OfficesList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/OfficesAdd[/{office_id}]", [PermissionMiddleware::class], "add.offices")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "OfficesAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/OfficesView[/{office_id}]", [PermissionMiddleware::class], "view.offices")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "OfficesView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/OfficesEdit[/{office_id}]", [PermissionMiddleware::class], "edit.offices")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "OfficesEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/OfficesDelete[/{office_id}]", [PermissionMiddleware::class], "delete.offices")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "OfficesDelete");
    }
}
