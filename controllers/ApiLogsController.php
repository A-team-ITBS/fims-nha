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

class ApiLogsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/ApiLogsList[/{log_id}]", [PermissionMiddleware::class], "list.api_logs")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiLogsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/ApiLogsAdd[/{log_id}]", [PermissionMiddleware::class], "add.api_logs")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiLogsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/ApiLogsView[/{log_id}]", [PermissionMiddleware::class], "view.api_logs")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiLogsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/ApiLogsEdit[/{log_id}]", [PermissionMiddleware::class], "edit.api_logs")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiLogsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/ApiLogsDelete[/{log_id}]", [PermissionMiddleware::class], "delete.api_logs")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ApiLogsDelete");
    }
}
