<?php

namespace PHPMaker2024\FIMS;

/**
 * List actions class
 */
class ListActions implements \ArrayAccess, \IteratorAggregate
{
    public $Items = [];

    // Implements offsetSet
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->Items[] = &$value;
        } else {
            $this->Items[$offset] = &$value;
        }
    }

    // Implements offsetExists
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->Items[$offset]);
    }

    // Implements offsetUnset
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->Items[$offset]);
    }

    // Implements offsetGet
    #[\ReturnTypeWillChange]
    public function &offsetGet($offset)
    {
        $item = $this->Items[$offset] ?? null;
        return $item;
    }

    // Implements IteratorAggregate
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->Items);
    }

    // Add and return a new action
    public function &add(
        string|array|ListAction $action, // Name
        string $caption = "", // Caption
        bool $allowed = true,
        string $method = ACTION_POSTBACK,
        string $select = ACTION_MULTIPLE,
        string $confirmMessage = "",
        string $icon = "fa-solid fa-star ew-icon",
        string $success = "",
        mixed $handler = null,
        string $successMessage = "",
        string $failureMessage = "",
    ) {
        if (is_array($action)) {
            foreach ($action as $item) {
                if ($item instanceof ListAction) {
                    $this->Items[$item->Action] = $item;
                }
            }
            return;
        } elseif ($action instanceof ListAction) {
            $this->Items[$action->Action] = $action;
            return $action;
        }
        $item = new ListAction($action, $caption, $allowed, $method, $select, $confirmMessage, $icon, $success, $handler, $successMessage, $failureMessage);
        $this->Items[$action] = $item;
        return $item;
    }
}
