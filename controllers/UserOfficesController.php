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

class UserOfficesController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/UserOfficesList[/{user_office_id}]", [PermissionMiddleware::class], "list.user_offices")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserOfficesList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/UserOfficesAdd[/{user_office_id}]", [PermissionMiddleware::class], "add.user_offices")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserOfficesAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/UserOfficesView[/{user_office_id}]", [PermissionMiddleware::class], "view.user_offices")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserOfficesView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/UserOfficesEdit[/{user_office_id}]", [PermissionMiddleware::class], "edit.user_offices")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserOfficesEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/UserOfficesDelete[/{user_office_id}]", [PermissionMiddleware::class], "delete.user_offices")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserOfficesDelete");
    }
}
