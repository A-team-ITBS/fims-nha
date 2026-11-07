<?php

namespace PHPMaker2024\FIMS;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Abstract controller class
 */
abstract class AbstractController
{
    /**
     * Constructor
     */
    public function __construct(protected ContainerInterface $container)
    {
    }

    /**
     * Set up
     */
    protected function setup(Request $request, Response &$response)
    {
        $GLOBALS["Request"] = $request;
        $GLOBALS["Response"] = &$response; // Note: global $Response does not work
    }
}
