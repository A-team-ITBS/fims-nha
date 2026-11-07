<?php

namespace PHPMaker2024\FIMS;

/**
 * Attributes class
 */
class Attributes implements \ArrayAccess, \IteratorAggregate
{
    // Constructor
    public function __construct(private array $attrs = [])
    {
    }

    // offsetSet
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->attrs[] = $value;
        } else {
            $this->attrs[$offset] = $value;
        }
    }

    // offsetExists
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->attrs[$offset]);
    }

    // offsetUnset
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->attrs[$offset]);
    }

    // offsetGet
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->attrs[$offset] ?? ""; // No undefined index
    }

    // getIterator
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->attrs);
    }

    // Append class
    public function appendClass($value)
    {
        $cls = $this->offsetGet("class");
        AppendClass($cls, $value);
        $this->attrs["class"] = trim($cls);
        return $this->attrs["class"];
    }

    // Prepend class
    public function prependClass($value)
    {
        $cls = $this->offsetGet("class");
        PrependClass($cls, $value);
        $this->attrs["class"] = trim($cls);
        return $this->attrs["class"];
    }

    // Remove class
    public function removeClass($value)
    {
        $cls = $this->offsetGet("class");
        RemoveClass($cls, $value);
        $this->attrs["class"] = trim($cls);
        return $this->attrs["class"];
    }

    // Append
    public function append($offset, $value, $sep = "")
    {
        if (SameText($offset, "class")) {
            return $this->appendClass($value);
        }
        $ar = array_unique(array_filter([$this->offsetGet($offset), $value], fn($v) => !EmptyString($v)));
        $this->attrs[$offset] = implode($sep, $ar);
        return $this->attrs[$offset];
    }

    // Prepend
    public function prepend($offset, $value, $sep = "")
    {
        if (SameText($offset, "class")) {
            return $this->prependClass($value);
        }
        $ar = array_unique(array_filter([$value, $this->offsetGet($offset)], fn($v) => !EmptyString($v)));
        $this->attrs[$offset] = implode($sep, $ar);
        return $this->attrs[$offset];
    }

    // Merge attributes
    public function merge($attrs)
    {
        if ($attrs instanceof Attributes) {
            $attrs = $attrs->toArray();
        }
        if (is_array($attrs)) {
            if (isset($attrs["class"])) {
                $this->appendClass($attrs["class"]);
                unset($attrs["class"]);
            }
            $this->attrs = array_replace_recursive($this->attrs, $attrs);
        }
    }

    // Check attributes for hyperlink
    public function checkLinkAttributes()
    {
        $onclick = $this->attrs["onclick"] ?? "";
        $href = $this->attrs["href"] ?? "";
        if ($onclick) {
            if (!$href) {
                $this->attrs["href"] = "#";
            }
            if (!StartsString("return ", $onclick) && !EndsString("return false;", $onclick)) {
                $this->append("onclick", "return false;", ";");
            }
        }
    }

    // To array
    public function toArray()
    {
        return array_filter($this->attrs, fn($v) => $v !== null);
    }

    /**
     * To string
     *
     * @param array $exclude Keys to exclude
     * @return string
     */
    public function toString($exclude = [])
    {
        $att = "";
        foreach ($this->attrs as $k => $v) {
            $key = trim($k);
            if (in_array($key, $exclude)) {
                continue;
            }
            $v = $v instanceof \UnitEnum ? $v->value : $v; // Convert enum to string
            if (is_array($v)) {
                $v = ArrayToJsonAttribute($v); // Convert array to JSON
            }
            $value = trim($v ?? "");
            if (IsBooleanAttribute($key) && $value !== false) { // Allow boolean attributes, e.g. "disabled"
                $att .= ' ' . $key . (($value != "" && $value !== true) ? '="' . $value . '"' : '');
            } elseif ($key != "" && $value != "") {
                $att .= ' ' . $key . '="' . $value . '"';
            } elseif ($key == "alt" && $value == "") { // Allow alt="" since it is a required attribute
                $att .= ' alt=""';
            }
        }
        return $att;
    }
}
