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

class UserLevelAssignmentsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/UserLevelAssignmentsList[/{assignment_id}]", [PermissionMiddleware::class], "list.user_level_assignments")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserLevelAssignmentsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/UserLevelAssignmentsAdd[/{assignment_id}]", [PermissionMiddleware::class], "add.user_level_assignments")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserLevelAssignmentsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/UserLevelAssignmentsView[/{assignment_id}]", [PermissionMiddleware::class], "view.user_level_assignments")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserLevelAssignmentsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/UserLevelAssignmentsEdit[/{assignment_id}]", [PermissionMiddleware::class], "edit.user_level_assignments")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserLevelAssignmentsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/UserLevelAssignmentsDelete[/{assignment_id}]", [PermissionMiddleware::class], "delete.user_level_assignments")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "UserLevelAssignmentsDelete");
    }
}
