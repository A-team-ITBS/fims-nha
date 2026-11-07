<?php

namespace PHPMaker2024\FIMS;

/**
 * Basic Search class
 */
class BasicSearch
{
    public $BasicSearchAnyFields;
    public $Keyword = "";
    public $KeywordDefault = "";
    public $Type = "";
    public $TypeDefault = "";
    public $Raw = false;
    protected $Prefix = "";

    // Constructor
    public function __construct(public $Table)
    {
        $this->BasicSearchAnyFields = Config("BASIC_SEARCH_ANY_FIELDS");
        $this->Prefix = PROJECT_NAME . "_" . $this->Table->TableVar . "_";
        $this->Raw = !Config("REMOVE_XSS");
    }

    // Session variable name
    protected function getSessionName($suffix)
    {
        return $this->Prefix . $suffix;
    }

    // Load default
    public function loadDefault()
    {
        $this->Keyword = $this->KeywordDefault;
        $this->Type = $this->TypeDefault;
        if (!isset($_SESSION[$this->getSessionName(Config("TABLE_BASIC_SEARCH_TYPE"))]) && $this->TypeDefault != "") { // Save default to session
            $this->setType($this->TypeDefault);
        }
    }

    // Unset session
    public function unsetSession()
    {
        Session()->delete($this->getSessionName(Config("TABLE_BASIC_SEARCH_TYPE")))
            ->delete($this->getSessionName(Config("TABLE_BASIC_SEARCH")));
    }

    // Isset session
    public function issetSession()
    {
        return isset($_SESSION[$this->getSessionName(Config("TABLE_BASIC_SEARCH"))]);
    }

    // Set keyword
    public function setKeyword($v, $save = true)
    {
        $v = $this->Raw ? $v : RemoveXss($v);
        $this->Keyword = $v;
        if ($save) {
            $_SESSION[$this->getSessionName(Config("TABLE_BASIC_SEARCH"))] = $v;
        }
    }

    // Set type
    public function setType($v, $save = true)
    {
        $this->Type = $v;
        if ($save) {
            $_SESSION[$this->getSessionName(Config("TABLE_BASIC_SEARCH_TYPE"))] = $v;
        }
    }

    // Save
    public function save()
    {
        $_SESSION[$this->getSessionName(Config("TABLE_BASIC_SEARCH"))] = $this->Keyword;
        $_SESSION[$this->getSessionName(Config("TABLE_BASIC_SEARCH_TYPE"))] = $this->Type;
    }

    // Get keyword
    public function getKeyword()
    {
        return Session($this->getSessionName(Config("TABLE_BASIC_SEARCH")));
    }

    // Get type
    public function getType()
    {
        return Session($this->getSessionName(Config("TABLE_BASIC_SEARCH_TYPE")));
    }

    // Get type name
    public function getTypeName()
    {
        global $Language;
        $typ = $this->getType();
        return match ($typ) {
            "=" => $Language->phrase("QuickSearchExact"),
            "AND" => $Language->phrase("QuickSearchAll"),
            "OR" => $Language->phrase("QuickSearchAny"),
            default => $Language->phrase("QuickSearchAuto")
        };
    }

    // Get short type name
    public function getTypeNameShort()
    {
        global $Language;
        $typ = $this->getType();
        $typname = match ($typ) {
            "=" => $Language->phrase("QuickSearchExactShort"),
            "AND" => $Language->phrase("QuickSearchAllShort"),
            "OR" => $Language->phrase("QuickSearchAnyShort"),
            default => $Language->phrase("QuickSearchAutoShort")
        };
        if ($typname != "") {
            $typname .= "&nbsp;";
        }
        return $typname;
    }

    // Get keyword list
    public function keywordList($default = false)
    {
        $searchKeyword = $default ? $this->KeywordDefault : $this->Keyword;
        $searchType = $default ? $this->TypeDefault : $this->Type;
        if ($searchKeyword != "") {
            $search = trim($searchKeyword);
            $ar = GetQuickSearchKeywords($search, $searchType);
            return $ar;
        }
        return [];
    }

    // Load
    public function load()
    {
        $this->Keyword = $this->getKeyword();
        $this->Type = $this->getType();
    }
}
