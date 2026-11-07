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

class PlantillaItemsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/PlantillaItemsList[/{plantilla_item_id}]", [PermissionMiddleware::class], "list.plantilla_items")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PlantillaItemsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/PlantillaItemsAdd[/{plantilla_item_id}]", [PermissionMiddleware::class], "add.plantilla_items")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PlantillaItemsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/PlantillaItemsView[/{plantilla_item_id}]", [PermissionMiddleware::class], "view.plantilla_items")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PlantillaItemsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/PlantillaItemsEdit[/{plantilla_item_id}]", [PermissionMiddleware::class], "edit.plantilla_items")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PlantillaItemsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/PlantillaItemsDelete[/{plantilla_item_id}]", [PermissionMiddleware::class], "delete.plantilla_items")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PlantillaItemsDelete");
    }
}
