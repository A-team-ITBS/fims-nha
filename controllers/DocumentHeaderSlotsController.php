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

class DocumentHeaderSlotsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/DocumentHeaderSlotsList[/{header_slot_id}]", [PermissionMiddleware::class], "list.document_header_slots")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentHeaderSlotsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/DocumentHeaderSlotsAdd[/{header_slot_id}]", [PermissionMiddleware::class], "add.document_header_slots")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentHeaderSlotsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/DocumentHeaderSlotsView[/{header_slot_id}]", [PermissionMiddleware::class], "view.document_header_slots")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentHeaderSlotsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/DocumentHeaderSlotsEdit[/{header_slot_id}]", [PermissionMiddleware::class], "edit.document_header_slots")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentHeaderSlotsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/DocumentHeaderSlotsDelete[/{header_slot_id}]", [PermissionMiddleware::class], "delete.document_header_slots")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "DocumentHeaderSlotsDelete");
    }
}
