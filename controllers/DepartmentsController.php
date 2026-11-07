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

class DepartmentsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/DepartmentsList[/{department_id}]", [PermissionMiddleware::class], "list.departments")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DepartmentsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/DepartmentsAdd[/{department_id}]", [PermissionMiddleware::class], "add.departments")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DepartmentsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/DepartmentsView[/{department_id}]", [PermissionMiddleware::class], "view.departments")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DepartmentsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/DepartmentsEdit[/{department_id}]", [PermissionMiddleware::class], "edit.departments")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DepartmentsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/DepartmentsDelete[/{department_id}]", [PermissionMiddleware::class], "delete.departments")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DepartmentsDelete");
    }
}
