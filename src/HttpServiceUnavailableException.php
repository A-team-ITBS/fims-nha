<?php

namespace PHPMaker2024\FIMS;

use Slim\Exception\HttpSpecializedException;

class HttpServiceUnavailableException extends HttpSpecializedException
{
    /**
     * @var int
     */
    protected $code = 503;

    /**
     * @var string
     */
    protected $message = 'Service Unavailable.';
    protected string $title = '503 Service Unavailable';
    protected string $description = 'The server is not ready to handle the request.';
}
