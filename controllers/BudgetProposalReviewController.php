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
 * budget_proposal_review controller
 */
class BudgetProposalReviewController extends ControllerBase
{
    // custom
    #[Map(["GET", "POST", "OPTIONS"], "/BudgetProposalReview[/{params:.*}]", [PermissionMiddleware::class], "custom.budget_proposal_review")]
    public function custom(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "BudgetProposalReview");
    }
}
