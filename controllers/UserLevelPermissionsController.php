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

class UserLevelPermissionsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/UserLevelPermissionsList[/{keys:.*}]", [PermissionMiddleware::class], "list.user_level_permissions")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $this->getKeyParams($args), "UserLevelPermissionsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/UserLevelPermissionsAdd[/{keys:.*}]", [PermissionMiddleware::class], "add.user_level_permissions")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $this->getKeyParams($args), "UserLevelPermissionsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/UserLevelPermissionsView[/{keys:.*}]", [PermissionMiddleware::class], "view.user_level_permissions")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $this->getKeyParams($args), "UserLevelPermissionsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/UserLevelPermissionsEdit[/{keys:.*}]", [PermissionMiddleware::class], "edit.user_level_permissions")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $this->getKeyParams($args), "UserLevelPermissionsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/UserLevelPermissionsDelete[/{keys:.*}]", [PermissionMiddleware::class], "delete.user_level_permissions")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $this->getKeyParams($args), "UserLevelPermissionsDelete");
    }

    // Get keys as associative array
    protected function getKeyParams($args)
    {
        global $RouteValues;
        if (array_key_exists("keys", $args)) {
            $sep = Container("user_level_permissions")->RouteCompositeKeySeparator;
            $keys = explode($sep, $args["keys"]);
            if (count($keys) == 2) {
                $keyArgs = array_combine(["user_level_id","table_name"], $keys);
                $RouteValues = array_merge(Route(), $keyArgs);
                $args = array_merge($args, $keyArgs);
            }
        }
        return $args;
    }
}
