<?php

namespace PHPMaker2024\FIMS;

/**
 * Class option values
 */
class OptionValues implements \Stringable
{
    // Constructor
    public function __construct(public array $Values = [])
    {
    }

    // Add value
    public function add($value)
    {
        $this->Values[] = $value;
    }

    // Convert to HTML
    public function toHtml(callable $fn = null): string
    {
        $fn ??= PROJECT_NAMESPACE . "OptionsHtml";
        if (is_callable($fn)) {
            return $fn($this->Values);
        }
        return $this->__toString();
    }

    // Convert to string (MUST return a string value)
    public function __toString(): string
    {
        return implode(Config("OPTION_SEPARATOR"), $this->Values);
    }
}
