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

class DepartmentSystemsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/DepartmentSystemsList[/{department_system_id}]", [PermissionMiddleware::class], "list.department_systems")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DepartmentSystemsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/DepartmentSystemsAdd[/{department_system_id}]", [PermissionMiddleware::class], "add.department_systems")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DepartmentSystemsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/DepartmentSystemsView[/{department_system_id}]", [PermissionMiddleware::class], "view.department_systems")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DepartmentSystemsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/DepartmentSystemsEdit[/{department_system_id}]", [PermissionMiddleware::class], "edit.department_systems")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DepartmentSystemsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/DepartmentSystemsDelete[/{department_system_id}]", [PermissionMiddleware::class], "delete.department_systems")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DepartmentSystemsDelete");
    }
}
