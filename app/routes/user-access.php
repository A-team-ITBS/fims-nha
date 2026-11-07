<?php
// __BASE__/app/routes/user-access.php
namespace PHPMaker2024\FIMS;

// Create a user directly from the UserAccess page (no redirect to UsersAdd)
$app->post("/user-access/users", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\UserAccessService();
    $result = $service->createUser($data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Save a user level's module permissions from the UserAccess page
// (no redirect to UserLevelPermissionsList)
$app->post("/user-access/permissions", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\UserAccessService();
    $result = $service->savePermissions($data['user_level_id'] ?? null, $data['permissions'] ?? []);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});
