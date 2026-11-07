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

class PositionCategoriesController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/PositionCategoriesList[/{position_category_id}]", [PermissionMiddleware::class], "list.position_categories")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PositionCategoriesList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/PositionCategoriesAdd[/{position_category_id}]", [PermissionMiddleware::class], "add.position_categories")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PositionCategoriesAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/PositionCategoriesView[/{position_category_id}]", [PermissionMiddleware::class], "view.position_categories")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PositionCategoriesView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/PositionCategoriesEdit[/{position_category_id}]", [PermissionMiddleware::class], "edit.position_categories")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PositionCategoriesEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/PositionCategoriesDelete[/{position_category_id}]", [PermissionMiddleware::class], "delete.position_categories")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PositionCategoriesDelete");
    }
}
