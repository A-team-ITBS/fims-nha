<?php

namespace PHPMaker2024\FIMS;

/**
 * Form class
 */
class HttpForm
{
    public $Index = -1;
    public $FormName = "";

    // Reset index to -1
    public function resetIndex()
    {
        $this->Index = -1;
    }

    // Get form element name based on index
    protected function getIndexedName($name)
    {
        if ($this->Index < 0) {
            return $name;
        } else {
            return substr($name, 0, 1) . $this->Index . substr($name, 1);
        }
    }

    // Has value for form element
    public function hasValue($name)
    {
        $wrkname = $this->getIndexedName($name);
        if (preg_match('/^(fn_)?(x|o)\d*_/', $name) && $this->FormName != "") {
            if (Post($this->FormName . '$' . $wrkname) !== null) {
                return true;
            }
        }
        return Post($wrkname) !== null;
    }

    // Get value for form element
    public function getValue($name)
    {
        $wrkname = $this->getIndexedName($name);
        $value = Post($wrkname);
        if (preg_match('/^(fn_)?(x|o)\d*_/', $name) && $this->FormName != "") {
            $wrkname = $this->FormName . '$' . $wrkname;
            if (Post($wrkname) !== null) {
                $value = Post($wrkname);
            }
        }
        return $value;
    }

    // Get search value for form element
    public function getSearchValues($name)
    {
        return [
            "value" => $this->getValue("x_$name"),
            "operator" => $this->getValue("z_$name"),
            "condition" => $this->getValue("v_$name"),
            "value2" => $this->getValue("y_$name"),
            "operator2" => $this->getValue("w_$name"),
        ];
    }
}
