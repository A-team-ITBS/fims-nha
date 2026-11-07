<?php

namespace PHPMaker2024\FIMS\Attributes;

use Attribute;
use JsonSerializable;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Map implements JsonSerializable
{
    private array $methods;
    private string $pattern;
    private string $handler;
    private array $middleware;
    private ?string $name;
    private array $options;

    /**
     * Constructor
     */
    public function __construct(
        string|array $method,
        string $pattern,
        string|array $middleware = [],
        ?string $name = null,
        array $options = []
    ) {
        $this->methods = is_array($method) ? $method : ($method != "" ? [$method] : []);
        $this->pattern = $pattern;
        $this->middleware = is_array($middleware) ? $middleware : ($middleware != "" ? [$middleware] : []);
        $this->name = $name;
        $this->options = $options;
    }

    /**
     * @return string
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return string
     */
    public function getHandler(): string
    {
        return $this->handler;
    }

    /**
     * Set handler
     *
     * @param string $handler Handler
     * @return void
     */
    public function setHandler(string $handler): void
    {
        $this->handler = $handler;
    }

    /**
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get option
     *
     * @param string $name Name
     * @return mixed
     */
    public function get(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Set option
     *
     * @param string|array $arg1 Name or name/value pairs
     * @param mixed $arg2 Value
     * @return void
     */
    public function set(string $arg1, mixed $arg2 = null): void
    {
        if (is_string($arg1)) {
            $this->options[$arg1] = $arg2;
        } elseif (is_array($arg1)) {
            $this->options = array_merge_recursive($this->options, $arg1);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'methods' => $this->methods,
            'pattern' => $this->pattern,
            'handler' => $this->handler,
            'middleware' => $this->middleware,
            'name' => $this->name,
            'options' => $this->options,
        ];
    }
}
