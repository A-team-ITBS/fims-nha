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

class DocumentAssetTypesController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/DocumentAssetTypesList[/{document_asset_type_id}]", [PermissionMiddleware::class], "list.document_asset_types")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentAssetTypesList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/DocumentAssetTypesAdd[/{document_asset_type_id}]", [PermissionMiddleware::class], "add.document_asset_types")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentAssetTypesAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/DocumentAssetTypesView[/{document_asset_type_id}]", [PermissionMiddleware::class], "view.document_asset_types")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentAssetTypesView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/DocumentAssetTypesEdit[/{document_asset_type_id}]", [PermissionMiddleware::class], "edit.document_asset_types")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentAssetTypesEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/DocumentAssetTypesDelete[/{document_asset_type_id}]", [PermissionMiddleware::class], "delete.document_asset_types")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentAssetTypesDelete");
    }
}
