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

class DocumentHeaderAssetsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/DocumentHeaderAssetsList[/{header_asset_id}]", [PermissionMiddleware::class], "list.document_header_assets")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentHeaderAssetsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/DocumentHeaderAssetsAdd[/{header_asset_id}]", [PermissionMiddleware::class], "add.document_header_assets")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentHeaderAssetsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/DocumentHeaderAssetsView[/{header_asset_id}]", [PermissionMiddleware::class], "view.document_header_assets")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentHeaderAssetsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/DocumentHeaderAssetsEdit[/{header_asset_id}]", [PermissionMiddleware::class], "edit.document_header_assets")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentHeaderAssetsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/DocumentHeaderAssetsDelete[/{header_asset_id}]", [PermissionMiddleware::class], "delete.document_header_assets")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentHeaderAssetsDelete");
    }
}
