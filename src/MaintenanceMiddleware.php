<?php

namespace PHPMaker2024\FIMS;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Maintenance middleware
 */
class MaintenanceMiddleware implements MiddlewareInterface
{
    const RETRY_AFTER = "Retry-After";

    /**
     * @var DateTimeInterface|int|null
     */
    protected $retryAfter;

    /**
     * @var string
     */
    protected $template = "";
    protected ResponseFactoryInterface $responseFactory;

    /**
     * Constructor
     *
     * @param ResponseFactoryInterface $responseFactory
     *
     * @return MaintenanceMiddleware
     */
    public function __construct(ResponseFactoryInterface $responseFactory = null)
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * Estimated time when the downtime will be complete
     *
     * @param DateTimeInterface|int $retryAfter DateTimeInterface or integer (seconds)
     */
    public function setRetryAfter($retryAfter): self
    {
        $this->retryAfter = $retryAfter;
        return $this;
    }

    /**
     * Get template
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set template
     *
     * @param string $template Template file name
     * @return void
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Invoke middleware
     *
     * @param Request $request Request
     * @param RequestHandler $handler Handler
     *
     * @return Response
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        global $Language, $Error, $Title;
        $statusCode = 503;
        $response = $this->responseFactory->createResponse($statusCode);
        $basePath = BasePath(true);
        $Language = Container("app.language");
        $Title = $Language->phrase($statusCode);
        $Error = [
            "statusCode" => $statusCode,
            "error" => [
                "class" => "text-danger",
                "type" => $Language->phrase($statusCode),
                "description" => $Language->phrase($statusCode . "Desc"),
            ],
        ];
        $view = Container("app.view");
        $html = '<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>' . $Title . '</title>
        <link rel="stylesheet" href="' . $basePath . 'adminlte3/css/' . CssFile("adminlte.css") . '">
        <link rel="stylesheet" href="' . $basePath . 'plugins/fontawesome-free/css/all.css">
        <link rel="stylesheet" href="' . $basePath . CssFile(Config("PROJECT_STYLESHEET_FILENAME")) . '">
    </head>
    <body class="container-fluid">
        <div class="d-flex justify-content-center align-items-center h-100">
            ' . $view->fetch($this->template, $GLOBALS) . '
        </div>
    </body>
</html>';
        $response->getBody()->write($html);
        if (is_int($this->retryAfter)) {
            return $response->withHeader(self::RETRY_AFTER, (string) $this->retryAfter);
        }
        if ($this->retryAfter instanceof DateTimeInterface) {
            return $response->withHeader(self::RETRY_AFTER, $this->retryAfter->format('D, d M Y H:i:s \G\M\T'));
        }
        return $response;
    }
}
