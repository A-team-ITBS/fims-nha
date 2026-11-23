<?php

namespace PHPMaker2024\FIMS;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PHPMaker2024\FIMS\Attributes\Delete;
use PHPMaker2024\FIMS\Attributes\Get;
use PHPMaker2024\FIMS\Attributes\Map;
use PHPMaker2024\FIMS\Attributes\Options;
use PHPMaker2024\FIMS\Attributes\Patch;
use PHPMaker2024\FIMS\Attributes\Post;
use PHPMaker2024\FIMS\Attributes\Put;

/**
 * merchandise_inventory_page controller
 */
class MerchandiseInventoryPageController extends ControllerBase
{
    // custom
    #[Map(["GET", "POST", "OPTIONS"], "/MerchandiseInventoryPage[/{params:.*}]", [PermissionMiddleware::class], "custom.merchandise_inventory_page")]
    public function custom(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "MerchandiseInventoryPage");
    }
}
