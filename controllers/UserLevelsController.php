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

class UserLevelsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/UserLevelsList[/{user_level_id}]", [PermissionMiddleware::class], "list._user_levels")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserLevelsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/UserLevelsAdd[/{user_level_id}]", [PermissionMiddleware::class], "add._user_levels")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserLevelsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/UserLevelsView[/{user_level_id}]", [PermissionMiddleware::class], "view._user_levels")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserLevelsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/UserLevelsEdit[/{user_level_id}]", [PermissionMiddleware::class], "edit._user_levels")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserLevelsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/UserLevelsDelete[/{user_level_id}]", [PermissionMiddleware::class], "delete._user_levels")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserLevelsDelete");
    }
}
