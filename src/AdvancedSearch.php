<?php

namespace PHPMaker2024\FIMS;

/**
 * Advanced Search class
 */
class AdvancedSearch
{
    public $SearchValue; // Search value
    public $ViewValue = ""; // View value
    public $SearchOperator; // Search operator
    public $SearchCondition; // Search condition
    public $SearchValue2; // Search value 2
    public $ViewValue2 = ""; // View value 2
    public $SearchOperator2; // Search operator 2
    public $SearchValueDefault = ""; // Search value default
    public $SearchOperatorDefault = ""; // Search operator default
    public $SearchConditionDefault = ""; // Search condition default
    public $SearchValue2Default = ""; // Search value 2 default
    public $SearchOperator2Default = ""; // Search operator 2 default
    public $Raw = false;
    protected $Prefix = "";
    protected $Suffix = "";
    protected $HasValue = false;

    // Constructor
    public function __construct(public $Field)
    {
        $this->Prefix = PROJECT_NAME . "_" . $this->Field->TableVar . "_" . Config("TABLE_ADVANCED_SEARCH") . "_";
        $this->Suffix = "_" . $this->Field->Param;
        $this->Raw = !Config("REMOVE_XSS");
    }

    // Set SearchValue
    public function setSearchValue($v)
    {
        $this->SearchValue = $this->Raw ? $v : RemoveXss($v);
        $this->HasValue = true;
    }

    // Set SearchOperator
    public function setSearchOperator($v)
    {
        if (IsValidOperator($v)) {
            $this->SearchOperator = $v;
            $this->HasValue = true;
        }
    }

    // Set SearchCondition
    public function setSearchCondition($v)
    {
        $this->SearchCondition = Config("REMOVE_XSS") ? RemoveXss($v) : $v;
        $this->HasValue = true;
    }

    // Set SearchValue2
    public function setSearchValue2($v)
    {
        $this->SearchValue2 = $this->Raw ? $v : RemoveXss($v);
        $this->HasValue = true;
    }

    // Set SearchOperator2
    public function setSearchOperator2($v)
    {
        if (IsValidOperator($v)) {
            $this->SearchOperator2 = $v;
            $this->HasValue = true;
        }
    }

    // Unset session
    public function unsetSession()
    {
        Session()->delete($this->getSessionName("x"))
            ->delete($this->getSessionName("z"))
            ->delete($this->getSessionName("v"))
            ->delete($this->getSessionName("y"))
            ->delete($this->getSessionName("w"));
    }

    // Isset session
    public function issetSession()
    {
        return isset($_SESSION[$this->getSessionName("x")]) || isset($_SESSION[$this->getSessionName("y")]);
    }

    // Get values from array
    public function get(array $ar = null)
    {
        $ar ??= IsPost() ? $_POST : $_GET;
        $parm = $this->Field->Param;
        if (array_key_exists("x_" . $parm, $ar)) {
            $this->setSearchValue($ar["x_" . $parm]);
        } elseif (array_key_exists($parm, $ar)) { // Support SearchValue without "x_"
            $v = $ar[$parm];
            if (!in_array($this->Field->DataType, [DataType::STRING, DataType::MEMO]) && !$this->Field->IsVirtual && !is_array($v)) {
                $this->parseSearchValue($v); // Support search format field=<opr><value><cond><value2> (e.g. Field=greater_or_equal1)
            } else {
                $this->setSearchValue($v);
            }
        }
        if (array_key_exists("z_" . $parm, $ar)) {
            $this->setSearchOperator($ar["z_" . $parm]);
        }
        if (array_key_exists("v_" . $parm, $ar)) {
            $this->setSearchCondition($ar["v_" . $parm]);
        }
        if (array_key_exists("y_" . $parm, $ar)) {
            $this->setSearchValue2($ar["y_" . $parm]);
        }
        if (array_key_exists("w_" . $parm, $ar)) {
            $this->setSearchOperator2($ar["w_" . $parm]);
        }
        return $this->HasValue;
    }

    /**
     * Parse search value
     *
     * @param string $value Search value
     * - supported format
     * - <opr><val> (e.g. >=3)
     * - <between_opr><val>|<val2> (e.g. between1|4 => BETWEEN 1 AND 4)
     * - <opr><val>|<opr2><val2> (e.g. greater1|less4 => > 1 AND < 4)
     * - <opr><val>||<opr2><val2> (e.g. less1||greater4 => < 1 OR > 4)
     */
    public function parseSearchValue($value)
    {
        if (EmptyValue($value)) {
            return;
        }
        $arOprs = $this->Field->SearchOperators;
        rsort($arOprs);
        $arClientOprs = array_map(fn($opr) => Config("CLIENT_SEARCH_OPERATORS")[$opr], $this->Field->SearchOperators);
        rsort($arClientOprs);
        $pattern = '/^(' . implode('|', $arOprs) . ')/';
        $clientPattern = '/^(' . implode('|', $arClientOprs) . ')/';
        $parse = function ($pattern, $clientPattern, $val) {
            if (preg_match($pattern, $val, $m)) { // Match operators
                $opr = $m[1];
                $parsedValue = substr($val, strlen($m[1]));
            } elseif (preg_match($clientPattern, $val, $m)) { // Match client operators
                $opr = array_search($m[1], Config("CLIENT_SEARCH_OPERATORS"));
                $parsedValue = substr($val, strlen($m[1]));
            } else {
                $opr = "";
                $parsedValue = $val;
            }
            return ["opr" => $opr, "val" => $parsedValue];
        };
        ["opr" => $opr, "val" => $val] = $parse($pattern, $clientPattern, $value);
        if ($opr && $val) {
            $this->setSearchOperator($opr);
            if (in_array($opr, ["BETWEEN", "NOT BETWEEN"]) && ContainsString($val, Config("BETWEEN_OPERATOR_VALUE_SEPARATOR"))) { // Handle BETWEEN operator
                $arValues = explode(Config("BETWEEN_OPERATOR_VALUE_SEPARATOR"), $val);
                $this->setSearchValue($arValues[0]);
                $this->setSearchValue2($arValues[1]);
            } elseif (ContainsString($val, Config("OR_OPERATOR_VALUE_SEPARATOR"))) { // Handle OR
                $arValues = explode(Config("OR_OPERATOR_VALUE_SEPARATOR"), $val);
                $this->setSearchValue($arValues[0]);
                $this->setSearchCondition("OR");
                ["opr" => $opr, "val" => $val] = $parse($pattern, $clientPattern, $arValues[1]);
                $this->setSearchOperator2($opr ?: "=");
                $this->setSearchValue2($val);
            } elseif (ContainsString($val, Config("BETWEEN_OPERATOR_VALUE_SEPARATOR"))) { // Handle AND
                $arValues = explode(Config("BETWEEN_OPERATOR_VALUE_SEPARATOR"), $val);
                $this->setSearchValue($arValues[0]);
                $this->setSearchCondition("AND");
                ["opr" => $opr, "val" => $val] = $parse($pattern, $clientPattern, $arValues[1]);
                $this->setSearchOperator2($opr ?: "=");
                $this->setSearchValue2($val);
            } else {
                $this->setSearchValue($val);
            }
        } else {
            $this->setSearchValue($val);
        }
    }

    // Save to session
    public function save()
    {
        $fldVal = $this->SearchValue;
        $sep = $this->Field->UseFilter ? Config("FILTER_OPTION_SEPARATOR") : Config("MULTIPLE_OPTION_SEPARATOR");
        if (is_array($fldVal)) {
            $fldVal = implode($sep, $fldVal);
        }
        $fldVal2 = $this->SearchValue2;
        if (is_array($fldVal2)) {
            $fldVal2 = implode($sep, $fldVal2);
        }
        $_SESSION[$this->getSessionName("x")] = $fldVal;
        $_SESSION[$this->getSessionName("y")] = $fldVal2;
        $_SESSION[$this->getSessionName("z")] = $this->SearchOperator;
        $_SESSION[$this->getSessionName("v")] = $this->SearchCondition;
        $_SESSION[$this->getSessionName("w")] = $this->SearchOperator2;
    }

    // Load from session
    public function load()
    {
        $this->SearchValue = Session($this->getSessionName("x"));
        $this->SearchOperator = Session($this->getSessionName("z"));
        $this->SearchCondition = Session($this->getSessionName("v"));
        $this->SearchValue2 = Session($this->getSessionName("y"));
        $this->SearchOperator2 = Session($this->getSessionName("w"));
    }

    // Get value
    public function getValue($infix)
    {
        return Session($this->getSessionName($infix));
    }

    // Load default values
    public function loadDefault()
    {
        if ($this->SearchValueDefault != "") {
            $this->SearchValue = $this->SearchValueDefault;
        }
        if ($this->SearchOperatorDefault != "") {
            $this->SearchOperator = $this->SearchOperatorDefault;
        }
        if ($this->SearchConditionDefault != "") {
            $this->SearchCondition = $this->SearchConditionDefault;
        }
        if ($this->SearchValue2Default != "") {
            $this->SearchValue2 = $this->SearchValue2Default;
        }
        if ($this->SearchOperator2Default != "") {
            $this->SearchOperator2 = $this->SearchOperator2Default;
        }
    }

    // Convert to JSON
    public function toJson()
    {
        if (
            $this->SearchValue != "" ||
            $this->SearchValue2 != "" ||
            in_array($this->SearchOperator, ["IS NULL", "IS NOT NULL", "IS EMPTY", "IS NOT EMPTY"]) ||
            in_array($this->SearchOperator2, ["IS NULL", "IS NOT NULL", "IS EMPTY", "IS NOT EMPTY"])
        ) {
            return '"x' . $this->Suffix . '":"' . JsEncode($this->SearchValue) . '",' .
                '"z' . $this->Suffix . '":"' . JsEncode($this->SearchOperator) . '",' .
                '"v' . $this->Suffix . '":"' . JsEncode($this->SearchCondition) . '",' .
                '"y' . $this->Suffix . '":"' . JsEncode($this->SearchValue2) . '",' .
                '"w' . $this->Suffix . '":"' . JsEncode($this->SearchOperator2) . '"';
        }
        return "";
    }

    // Session variable name
    protected function getSessionName($infix)
    {
        return $this->Prefix . $infix . $this->Suffix;
    }
}
