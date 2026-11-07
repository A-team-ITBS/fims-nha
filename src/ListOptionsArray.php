<?php

namespace PHPMaker2024\FIMS;

/**
 * ListOptionsArray class (Array of ListOptions)
 */
class ListOptionsArray extends \ArrayObject
{
    // Constructor
    public function __construct($array = [])
    {
        parent::__construct($array, \ArrayObject::ARRAY_AS_PROPS);
    }

    // Render
    public function render($part, $pos = "")
    {
        foreach ($this as $options) {
            $options->render($part, $pos);
        }
    }

    // Hide all options
    public function hideAllOptions()
    {
        foreach ($this as $options) {
            $options->hideAllOptions();
        }
    }

    // Visible
    public function visible()
    {
        return array_any($this->getArrayCopy(), fn($options) => $options->visible());
    }
}
