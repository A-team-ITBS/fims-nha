<?php
namespace PHPMaker2024\FIMS; // Change this to your name space
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

$jwtMiddleware = function (Request $request, RequestHandler $handler): Response {
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader) {
        $authHeader = $request->getHeaderLine('X-Authorization');
    }
    if (!$authHeader) {
        $authHeader = GetJwtToken();
    }
    $jwt = str_replace('Bearer ', '', $authHeader);

    // Decode the JWT
    $payload = DecodeJwt($jwt);

    // If the JWT is invalid, return a 401 Unauthorized response
    if (isset($payload['failureMessage']) || !isset($jwt) || empty($jwt)) {
        $response = new \Nyholm\Psr7\Response();
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401);
    }

    // Attach user_id to the request
    $user_id = $payload['userid'] ?? null;
    $request = $request->withAttribute('user_id', $user_id);

    // If the JWT is valid, pass the request to the next middleware
    $response = $handler->handle($request);
    return $response;
};

// Access middleware for permission checking
$accessMiddleware = function (Request $request, RequestHandler $handler): Response {
    // Add your access control logic here
    // Example: Check user permissions based on endpoint and method
    
    $response = $handler->handle($request);
    return $response;
};

// Include Service Classes (if any)
// include(dirname(__DIR__, 1) . "/app/services/YourService.php");
include(dirname(__DIR__, 1) . "/app/services/ProgramService.php");
include(dirname(__DIR__, 1) . "/app/services/ProjectService.php");
include(dirname(__DIR__, 1) . "/app/services/BudgetLineItemService.php");
include(dirname(__DIR__, 1) . "/app/services/BudgetReqService.php");
include(dirname(__DIR__, 1) . "/app/services/FiscalYearService.php");
include(dirname(__DIR__, 1) . "/app/services/ExpenseClassService.php");
include(dirname(__DIR__, 1) . "/app/services/ChartOfAccountsService.php");

// Include Route Files
include(dirname(__DIR__, 1) . "/app/routes/notifications.php");
include(dirname(__DIR__, 1) . "/app/routes/service.php");
include(dirname(__DIR__, 1) . "/app/routes/user-access.php");
include(dirname(__DIR__, 1) . "/app/routes/program.php");
include(dirname(__DIR__, 1) . "/app/routes/project.php");
include(dirname(__DIR__, 1) . "/app/routes/budget_req.php");
include(dirname(__DIR__, 1) . "/app/routes/budget_line_item.php");
include(dirname(__DIR__, 1) . "/app/routes/fiscal_year.php");
include(dirname(__DIR__, 1) . "/app/routes/expense_class.php");
include(dirname(__DIR__, 1) . "/app/routes/chart_of_accounts.php");

// $appBase = dirname(__DIR__, 1) . '/app/';

// foreach (glob($appBase . 'services/[!_]*.php') ?: [] as $f) {
//     require_once $f;
// }

// foreach (glob($appBase . 'routes/[!_]*.php') ?: [] as $f) {
//     require_once $f;
// }
