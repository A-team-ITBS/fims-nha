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
 * remittance_list_page controller
 */
class RemittanceListPageController extends ControllerBase
{
    // custom
    #[Map(["GET", "POST", "OPTIONS"], "/RemittanceListPage[/{params:.*}]", [PermissionMiddleware::class], "custom.remittance_list_page")]
    public function custom(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "RemittanceListPage");
    }
}
