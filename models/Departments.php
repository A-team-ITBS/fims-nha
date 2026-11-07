<?php

namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Container\ContainerInterface;
use Slim\Routing\RouteCollectorProxy;
use Slim\App;
use Closure;

/**
 * Table class for departments
 */
class Departments extends DbTable
{
    protected $SqlFrom = "";
    protected $SqlSelect = null;
    protected $SqlSelectList = null;
    protected $SqlWhere = "";
    protected $SqlGroupBy = "";
    protected $SqlHaving = "";
    protected $SqlOrderBy = "";
    public $DbErrorMessage = "";
    public $UseSessionForListSql = true;

    // Column CSS classes
    public $LeftColumnClass = "col-sm-2 col-form-label ew-label";
    public $RightColumnClass = "col-sm-10";
    public $OffsetColumnClass = "col-sm-10 offset-sm-2";
    public $TableLeftColumnClass = "w-col-2";

    // Ajax / Modal
    public $UseAjaxActions = false;
    public $ModalSearch = false;
    public $ModalView = false;
    public $ModalAdd = false;
    public $ModalEdit = false;
    public $ModalUpdate = false;
    public $InlineDelete = false;
    public $ModalGridAdd = false;
    public $ModalGridEdit = false;
    public $ModalMultiEdit = false;

    // Fields
    public $department_id;
    public $department_name;
    public $description;
    public $parent_department_id;
    public $department_head_id;
    public $department_code;
    public $department_acronym;
    public $office_location;
    public $contact_number;
    public $_email;
    public $is_active;
    public $created_at;
    public $updated_at;
    public $rc_id;
    public $office_id;
    public $department_type;
    public $hierarchy_level;
    public $is_budget_unit;

    // Page ID
    public $PageID = ""; // To be overridden by subclass

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $CurrentLanguage, $CurrentLocale;

        // Language object
        $Language = Container("app.language");
        $this->TableVar = "departments";
        $this->TableName = 'departments';
        $this->TableType = "TABLE";
        $this->ImportUseTransaction = $this->supportsTransaction() && Config("IMPORT_USE_TRANSACTION");
        $this->UseTransaction = $this->supportsTransaction() && Config("USE_TRANSACTION");

        // Update Table
        $this->UpdateTable = "uac.departments";
        $this->Dbid = 'DB';
        $this->ExportAll = true;
        $this->ExportPageBreakCount = 0; // Page break per every n record (PDF only)

        // PDF
        $this->ExportPageOrientation = "portrait"; // Page orientation (PDF only)
        $this->ExportPageSize = "a4"; // Page size (PDF only)

        // PhpSpreadsheet
        $this->ExportExcelPageOrientation = null; // Page orientation (PhpSpreadsheet only)
        $this->ExportExcelPageSize = null; // Page size (PhpSpreadsheet only)

        // PHPWord
        $this->ExportWordPageOrientation = ""; // Page orientation (PHPWord only)
        $this->ExportWordPageSize = ""; // Page orientation (PHPWord only)
        $this->ExportWordColumnWidth = null; // Cell width (PHPWord only)
        $this->DetailAdd = false; // Allow detail add
        $this->DetailEdit = false; // Allow detail edit
        $this->DetailView = false; // Allow detail view
        $this->ShowMultipleDetails = false; // Show multiple details
        $this->GridAddRowCount = 5;
        $this->AllowAddDeleteRow = true; // Allow add/delete row
        $this->UseAjaxActions = $this->UseAjaxActions || Config("USE_AJAX_ACTIONS");
        $this->UserIDAllowSecurity = Config("DEFAULT_USER_ID_ALLOW_SECURITY"); // Default User ID allowed permissions
        $this->BasicSearch = new BasicSearch($this);

        // department_id
        $this->department_id = new DbField(
            $this, // Table
            'x_department_id', // Variable name
            'department_id', // Name
            '"department_id"', // Expression
            'CAST("department_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"department_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'NO' // Edit Tag
        );
        $this->department_id->InputTextType = "text";
        $this->department_id->Raw = true;
        $this->department_id->IsAutoIncrement = true; // Autoincrement field
        $this->department_id->IsPrimaryKey = true; // Primary key field
        $this->department_id->Nullable = false; // NOT NULL field
        $this->department_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->department_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['department_id'] = &$this->department_id;

        // department_name
        $this->department_name = new DbField(
            $this, // Table
            'x_department_name', // Variable name
            'department_name', // Name
            '"department_name"', // Expression
            '"department_name"', // Basic search expression
            200, // Type
            150, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"department_name"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->department_name->InputTextType = "text";
        $this->department_name->Nullable = false; // NOT NULL field
        $this->department_name->Required = true; // Required field
        $this->department_name->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['department_name'] = &$this->department_name;

        // description
        $this->description = new DbField(
            $this, // Table
            'x_description', // Variable name
            'description', // Name
            '"description"', // Expression
            '"description"', // Basic search expression
            201, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"description"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXTAREA' // Edit Tag
        );
        $this->description->InputTextType = "text";
        $this->description->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['description'] = &$this->description;

        // parent_department_id
        $this->parent_department_id = new DbField(
            $this, // Table
            'x_parent_department_id', // Variable name
            'parent_department_id', // Name
            '"parent_department_id"', // Expression
            'CAST("parent_department_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"parent_department_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'SELECT' // Edit Tag
        );
        $this->parent_department_id->addMethod("getSelectFilter", fn() => "parent_department_id is NULL");
        $this->parent_department_id->InputTextType = "text";
        $this->parent_department_id->Raw = true;
        $this->parent_department_id->setSelectMultiple(false); // Select one
        $this->parent_department_id->UsePleaseSelect = true; // Use PleaseSelect by default
        $this->parent_department_id->PleaseSelectText = $Language->phrase("PleaseSelect"); // "PleaseSelect" text
        $this->parent_department_id->Lookup = new Lookup($this->parent_department_id, 'departments', false, 'department_id', ["department_name","","",""], '', '', [], [], [], [], [], [], false, '"department_name"', '', "\"department_name\"");
        $this->parent_department_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->parent_department_id->SearchOperators = ["=", "<>", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['parent_department_id'] = &$this->parent_department_id;

        // department_head_id
        $this->department_head_id = new DbField(
            $this, // Table
            'x_department_head_id', // Variable name
            'department_head_id', // Name
            '"department_head_id"', // Expression
            'CAST("department_head_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"department_head_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->department_head_id->InputTextType = "text";
        $this->department_head_id->Raw = true;
        $this->department_head_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->department_head_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['department_head_id'] = &$this->department_head_id;

        // department_code
        $this->department_code = new DbField(
            $this, // Table
            'x_department_code', // Variable name
            'department_code', // Name
            '"department_code"', // Expression
            '"department_code"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"department_code"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->department_code->InputTextType = "text";
        $this->department_code->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['department_code'] = &$this->department_code;

        // department_acronym
        $this->department_acronym = new DbField(
            $this, // Table
            'x_department_acronym', // Variable name
            'department_acronym', // Name
            '"department_acronym"', // Expression
            '"department_acronym"', // Basic search expression
            200, // Type
            20, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"department_acronym"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->department_acronym->InputTextType = "text";
        $this->department_acronym->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['department_acronym'] = &$this->department_acronym;

        // office_location
        $this->office_location = new DbField(
            $this, // Table
            'x_office_location', // Variable name
            'office_location', // Name
            '"office_location"', // Expression
            '"office_location"', // Basic search expression
            200, // Type
            200, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"office_location"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->office_location->InputTextType = "text";
        $this->office_location->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['office_location'] = &$this->office_location;

        // contact_number
        $this->contact_number = new DbField(
            $this, // Table
            'x_contact_number', // Variable name
            'contact_number', // Name
            '"contact_number"', // Expression
            '"contact_number"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"contact_number"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->contact_number->InputTextType = "text";
        $this->contact_number->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['contact_number'] = &$this->contact_number;

        // email
        $this->_email = new DbField(
            $this, // Table
            'x__email', // Variable name
            'email', // Name
            '"email"', // Expression
            '"email"', // Basic search expression
            200, // Type
            100, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"email"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->_email->InputTextType = "text";
        $this->_email->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['email'] = &$this->_email;

        // is_active
        $this->is_active = new DbField(
            $this, // Table
            'x_is_active', // Variable name
            'is_active', // Name
            '"is_active"', // Expression
            'CAST("is_active" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"is_active"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->is_active->InputTextType = "text";
        $this->is_active->Raw = true;
        $this->is_active->setDataType(DataType::BOOLEAN);
        $this->is_active->Lookup = new Lookup($this->is_active, 'departments', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->is_active->OptionCount = 2;
        $this->is_active->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['is_active'] = &$this->is_active;

        // created_at
        $this->created_at = new DbField(
            $this, // Table
            'x_created_at', // Variable name
            'created_at', // Name
            '"created_at"', // Expression
            CastDateFieldForLike("\"created_at\"", 0, "DB"), // Basic search expression
            135, // Type
            0, // Size
            0, // Date/Time format
            false, // Is upload field
            '"created_at"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->created_at->InputTextType = "text";
        $this->created_at->Raw = true;
        $this->created_at->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->created_at->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['created_at'] = &$this->created_at;

        // updated_at
        $this->updated_at = new DbField(
            $this, // Table
            'x_updated_at', // Variable name
            'updated_at', // Name
            '"updated_at"', // Expression
            CastDateFieldForLike("\"updated_at\"", 0, "DB"), // Basic search expression
            135, // Type
            0, // Size
            0, // Date/Time format
            false, // Is upload field
            '"updated_at"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->updated_at->InputTextType = "text";
        $this->updated_at->Raw = true;
        $this->updated_at->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->updated_at->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['updated_at'] = &$this->updated_at;

        // rc_id
        $this->rc_id = new DbField(
            $this, // Table
            'x_rc_id', // Variable name
            'rc_id', // Name
            '"rc_id"', // Expression
            'CAST("rc_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"rc_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->rc_id->InputTextType = "text";
        $this->rc_id->Raw = true;
        $this->rc_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->rc_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['rc_id'] = &$this->rc_id;

        // office_id
        $this->office_id = new DbField(
            $this, // Table
            'x_office_id', // Variable name
            'office_id', // Name
            '"office_id"', // Expression
            'CAST("office_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"office_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->office_id->InputTextType = "text";
        $this->office_id->Raw = true;
        $this->office_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->office_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['office_id'] = &$this->office_id;

        // department_type
        $this->department_type = new DbField(
            $this, // Table
            'x_department_type', // Variable name
            'department_type', // Name
            '"department_type"', // Expression
            '"department_type"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"department_type"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->department_type->InputTextType = "text";
        $this->department_type->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['department_type'] = &$this->department_type;

        // hierarchy_level
        $this->hierarchy_level = new DbField(
            $this, // Table
            'x_hierarchy_level', // Variable name
            'hierarchy_level', // Name
            '"hierarchy_level"', // Expression
            'CAST("hierarchy_level" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"hierarchy_level"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->hierarchy_level->addMethod("getDefault", fn() => 1);
        $this->hierarchy_level->InputTextType = "text";
        $this->hierarchy_level->Raw = true;
        $this->hierarchy_level->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->hierarchy_level->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['hierarchy_level'] = &$this->hierarchy_level;

        // is_budget_unit
        $this->is_budget_unit = new DbField(
            $this, // Table
            'x_is_budget_unit', // Variable name
            'is_budget_unit', // Name
            '"is_budget_unit"', // Expression
            'CAST("is_budget_unit" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"is_budget_unit"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->is_budget_unit->InputTextType = "text";
        $this->is_budget_unit->Raw = true;
        $this->is_budget_unit->setDataType(DataType::BOOLEAN);
        $this->is_budget_unit->Lookup = new Lookup($this->is_budget_unit, 'departments', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->is_budget_unit->OptionCount = 2;
        $this->is_budget_unit->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['is_budget_unit'] = &$this->is_budget_unit;

        // Add Doctrine Cache
        $this->Cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $this->CacheProfile = new \Doctrine\DBAL\Cache\QueryCacheProfile(0, $this->TableVar);

        // Call Table Load event
        $this->tableLoad();
    }

    // Field Visibility
    public function getFieldVisibility($fldParm)
    {
        global $Security;
        return $this->$fldParm->Visible; // Returns original value
    }

    // Set left column class (must be predefined col-*-* classes of Bootstrap grid system)
    public function setLeftColumnClass($class)
    {
        if (preg_match('/^col\-(\w+)\-(\d+)$/', $class, $match)) {
            $this->LeftColumnClass = $class . " col-form-label ew-label";
            $this->RightColumnClass = "col-" . $match[1] . "-" . strval(12 - (int)$match[2]);
            $this->OffsetColumnClass = $this->RightColumnClass . " " . str_replace("col-", "offset-", $class);
            $this->TableLeftColumnClass = preg_replace('/^col-\w+-(\d+)$/', "w-col-$1", $class); // Change to w-col-*
        }
    }

    // Single column sort
    public function updateSort(&$fld)
    {
        if ($this->CurrentOrder == $fld->Name) {
            $sortField = $fld->Expression;
            $lastSort = $fld->getSort();
            if (in_array($this->CurrentOrderType, ["ASC", "DESC", "NO"])) {
                $curSort = $this->CurrentOrderType;
            } else {
                $curSort = $lastSort;
            }
            $orderBy = in_array($curSort, ["ASC", "DESC"]) ? $sortField . " " . $curSort : "";
            $this->setSessionOrderBy($orderBy); // Save to Session
        }
    }

    // Update field sort
    public function updateFieldSort()
    {
        $orderBy = $this->getSessionOrderBy(); // Get ORDER BY from Session
        $flds = GetSortFields($orderBy);
        foreach ($this->Fields as $field) {
            $fldSort = "";
            foreach ($flds as $fld) {
                if ($fld[0] == $field->Expression || $fld[0] == $field->VirtualExpression) {
                    $fldSort = $fld[1];
                }
            }
            $field->setSort($fldSort);
        }
    }

    // Render X Axis for chart
    public function renderChartXAxis($chartVar, $chartRow)
    {
        return $chartRow;
    }

    // Get FROM clause
    public function getSqlFrom()
    {
        return ($this->SqlFrom != "") ? $this->SqlFrom : "uac.departments";
    }

    // Get FROM clause (for backward compatibility)
    public function sqlFrom()
    {
        return $this->getSqlFrom();
    }

    // Set FROM clause
    public function setSqlFrom($v)
    {
        $this->SqlFrom = $v;
    }

    // Get SELECT clause
    public function getSqlSelect() // Select
    {
        return $this->SqlSelect ?? $this->getQueryBuilder()->select($this->sqlSelectFields());
    }

    // Get list of fields
    private function sqlSelectFields()
    {
        $useFieldNames = false;
        $fieldNames = [];
        $platform = $this->getConnection()->getDatabasePlatform();
        foreach ($this->Fields as $field) {
            $expr = $field->Expression;
            $customExpr = $field->CustomDataType?->convertToPHPValueSQL($expr, $platform) ?? $expr;
            if ($customExpr != $expr) {
                $fieldNames[] = $customExpr . " AS " . QuotedName($field->Name, $this->Dbid);
                $useFieldNames = true;
            } else {
                $fieldNames[] = $expr;
            }
        }
        return $useFieldNames ? implode(", ", $fieldNames) : "*";
    }

    // Get SELECT clause (for backward compatibility)
    public function sqlSelect()
    {
        return $this->getSqlSelect();
    }

    // Set SELECT clause
    public function setSqlSelect($v)
    {
        $this->SqlSelect = $v;
    }

    // Get WHERE clause
    public function getSqlWhere()
    {
        $where = ($this->SqlWhere != "") ? $this->SqlWhere : "";
        $this->DefaultFilter = "";
        AddFilter($where, $this->DefaultFilter);
        return $where;
    }

    // Get WHERE clause (for backward compatibility)
    public function sqlWhere()
    {
        return $this->getSqlWhere();
    }

    // Set WHERE clause
    public function setSqlWhere($v)
    {
        $this->SqlWhere = $v;
    }

    // Get GROUP BY clause
    public function getSqlGroupBy()
    {
        return $this->SqlGroupBy != "" ? $this->SqlGroupBy : "";
    }

    // Get GROUP BY clause (for backward compatibility)
    public function sqlGroupBy()
    {
        return $this->getSqlGroupBy();
    }

    // set GROUP BY clause
    public function setSqlGroupBy($v)
    {
        $this->SqlGroupBy = $v;
    }

    // Get HAVING clause
    public function getSqlHaving() // Having
    {
        return ($this->SqlHaving != "") ? $this->SqlHaving : "";
    }

    // Get HAVING clause (for backward compatibility)
    public function sqlHaving()
    {
        return $this->getSqlHaving();
    }

    // Set HAVING clause
    public function setSqlHaving($v)
    {
        $this->SqlHaving = $v;
    }

    // Get ORDER BY clause
    public function getSqlOrderBy()
    {
        return ($this->SqlOrderBy != "") ? $this->SqlOrderBy : "";
    }

    // Get ORDER BY clause (for backward compatibility)
    public function sqlOrderBy()
    {
        return $this->getSqlOrderBy();
    }

    // set ORDER BY clause
    public function setSqlOrderBy($v)
    {
        $this->SqlOrderBy = $v;
    }

    // Apply User ID filters
    public function applyUserIDFilters($filter, $id = "")
    {
        return $filter;
    }

    // Check if User ID security allows view all
    public function userIDAllow($id = "")
    {
        $allow = $this->UserIDAllowSecurity;
        switch ($id) {
            case "add":
            case "copy":
            case "gridadd":
            case "register":
            case "addopt":
                return ($allow & Allow::ADD->value) == Allow::ADD->value;
            case "edit":
            case "gridedit":
            case "update":
            case "changepassword":
            case "resetpassword":
                return ($allow & Allow::EDIT->value) == Allow::EDIT->value;
            case "delete":
                return ($allow & Allow::DELETE->value) == Allow::DELETE->value;
            case "view":
                return ($allow & Allow::VIEW->value) == Allow::VIEW->value;
            case "search":
                return ($allow & Allow::SEARCH->value) == Allow::SEARCH->value;
            case "lookup":
                return ($allow & Allow::LOOKUP->value) == Allow::LOOKUP->value;
            default:
                return ($allow & Allow::LIST->value) == Allow::LIST->value;
        }
    }

    /**
     * Get record count
     *
     * @param string|QueryBuilder $sql SQL or QueryBuilder
     * @param mixed $c Connection
     * @return int
     */
    public function getRecordCount($sql, $c = null)
    {
        $cnt = -1;
        $sqlwrk = $sql instanceof QueryBuilder // Query builder
            ? (clone $sql)->resetQueryPart("orderBy")->getSQL()
            : $sql;
        $pattern = '/^SELECT\s([\s\S]+?)\sFROM\s/i';
        // Skip Custom View / SubQuery / SELECT DISTINCT / ORDER BY
        if (
            in_array($this->TableType, ["TABLE", "VIEW", "LINKTABLE"]) &&
            preg_match($pattern, $sqlwrk) &&
            !preg_match('/\(\s*(SELECT[^)]+)\)/i', $sqlwrk) &&
            !preg_match('/^\s*SELECT\s+DISTINCT\s+/i', $sqlwrk) &&
            !preg_match('/\s+ORDER\s+BY\s+/i', $sqlwrk)
        ) {
            $sqlcnt = "SELECT COUNT(*) FROM " . preg_replace($pattern, "", $sqlwrk);
        } else {
            $sqlcnt = "SELECT COUNT(*) FROM (" . $sqlwrk . ") COUNT_TABLE";
        }
        $conn = $c ?? $this->getConnection();
        $cnt = $conn->fetchOne($sqlcnt);
        if ($cnt !== false) {
            return (int)$cnt;
        }
        // Unable to get count by SELECT COUNT(*), execute the SQL to get record count directly
        $result = $conn->executeQuery($sqlwrk);
        $cnt = $result->rowCount();
        if ($cnt == 0) { // Unable to get record count, count directly
            while ($result->fetch()) {
                $cnt++;
            }
        }
        return $cnt;
    }

    // Get SQL
    public function getSql($where, $orderBy = "")
    {
        return $this->getSqlAsQueryBuilder($where, $orderBy)->getSQL();
    }

    // Get QueryBuilder
    public function getSqlAsQueryBuilder($where, $orderBy = "")
    {
        return $this->buildSelectSql(
            $this->getSqlSelect(),
            $this->getSqlFrom(),
            $this->getSqlWhere(),
            $this->getSqlGroupBy(),
            $this->getSqlHaving(),
            $this->getSqlOrderBy(),
            $where,
            $orderBy
        );
    }

    // Table SQL
    public function getCurrentSql()
    {
        $filter = $this->CurrentFilter;
        $filter = $this->applyUserIDFilters($filter);
        $sort = $this->getSessionOrderBy();
        return $this->getSql($filter, $sort);
    }

    /**
     * Table SQL with List page filter
     *
     * @return QueryBuilder
     */
    public function getListSql()
    {
        $filter = $this->UseSessionForListSql ? $this->getSessionWhere() : "";
        AddFilter($filter, $this->CurrentFilter);
        $filter = $this->applyUserIDFilters($filter);
        $this->recordsetSelecting($filter);
        $select = $this->getSqlSelect();
        $from = $this->getSqlFrom();
        $sort = $this->UseSessionForListSql ? $this->getSessionOrderBy() : "";
        $this->Sort = $sort;
        return $this->buildSelectSql(
            $select,
            $from,
            $this->getSqlWhere(),
            $this->getSqlGroupBy(),
            $this->getSqlHaving(),
            $this->getSqlOrderBy(),
            $filter,
            $sort
        );
    }

    // Get ORDER BY clause
    public function getOrderBy()
    {
        $orderBy = $this->getSqlOrderBy();
        $sort = $this->getSessionOrderBy();
        if ($orderBy != "" && $sort != "") {
            $orderBy .= ", " . $sort;
        } elseif ($sort != "") {
            $orderBy = $sort;
        }
        return $orderBy;
    }

    // Get record count based on filter (for detail record count in master table pages)
    public function loadRecordCount($filter)
    {
        $origFilter = $this->CurrentFilter;
        $this->CurrentFilter = $filter;
        $this->recordsetSelecting($this->CurrentFilter);
        $isCustomView = $this->TableType == "CUSTOMVIEW";
        $select = $isCustomView ? $this->getSqlSelect() : $this->getQueryBuilder()->select("*");
        $groupBy = $isCustomView ? $this->getSqlGroupBy() : "";
        $having = $isCustomView ? $this->getSqlHaving() : "";
        $sql = $this->buildSelectSql($select, $this->getSqlFrom(), $this->getSqlWhere(), $groupBy, $having, "", $this->CurrentFilter, "");
        $cnt = $this->getRecordCount($sql);
        $this->CurrentFilter = $origFilter;
        return $cnt;
    }

    // Get record count (for current List page)
    public function listRecordCount()
    {
        $filter = $this->getSessionWhere();
        AddFilter($filter, $this->CurrentFilter);
        $filter = $this->applyUserIDFilters($filter);
        $this->recordsetSelecting($filter);
        $isCustomView = $this->TableType == "CUSTOMVIEW";
        $select = $isCustomView ? $this->getSqlSelect() : $this->getQueryBuilder()->select("*");
        $groupBy = $isCustomView ? $this->getSqlGroupBy() : "";
        $having = $isCustomView ? $this->getSqlHaving() : "";
        $sql = $this->buildSelectSql($select, $this->getSqlFrom(), $this->getSqlWhere(), $groupBy, $having, "", $filter, "");
        $cnt = $this->getRecordCount($sql);
        return $cnt;
    }

    /**
     * INSERT statement
     *
     * @param mixed $rs
     * @return QueryBuilder
     */
    public function insertSql(&$rs)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->insert($this->UpdateTable);
        $platform = $this->getConnection()->getDatabasePlatform();
        foreach ($rs as $name => $value) {
            if (!isset($this->Fields[$name]) || $this->Fields[$name]->IsCustom) {
                continue;
            }
            $field = $this->Fields[$name];
            $parm = $queryBuilder->createPositionalParameter($value, $field->getParameterType());
            $parm = $field->CustomDataType?->convertToDatabaseValueSQL($parm, $platform) ?? $parm; // Convert database SQL
            $queryBuilder->setValue($field->Expression, $parm);
        }
        return $queryBuilder;
    }

    // Insert
    public function insert(&$rs)
    {
        $conn = $this->getConnection();
        try {
            $queryBuilder = $this->insertSql($rs);
            $result = $conn->executeQuery(
                $queryBuilder->getSQL() . " RETURNING department_id",
                $queryBuilder->getParameters(),
                $queryBuilder->getParameterTypes()
            )->fetchOne();
            $this->DbErrorMessage = "";
        } catch (\Exception $e) {
            $result = false;
            $this->DbErrorMessage = $e->getMessage();
        }
        if ($result) {
            $this->department_id->setDbValue($result);
            $rs['department_id'] = $this->department_id->DbValue;
        }
        return $result !== false ? 1 : false;
    }

    /**
     * UPDATE statement
     *
     * @param array $rs Data to be updated
     * @param string|array $where WHERE clause
     * @param string $curfilter Filter
     * @return QueryBuilder
     */
    public function updateSql(&$rs, $where = "", $curfilter = true)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->update($this->UpdateTable);
        $platform = $this->getConnection()->getDatabasePlatform();
        foreach ($rs as $name => $value) {
            if (!isset($this->Fields[$name]) || $this->Fields[$name]->IsCustom || $this->Fields[$name]->IsAutoIncrement) {
                continue;
            }
            $field = $this->Fields[$name];
            $parm = $queryBuilder->createPositionalParameter($value, $field->getParameterType());
            $parm = $field->CustomDataType?->convertToDatabaseValueSQL($parm, $platform) ?? $parm; // Convert database SQL
            $queryBuilder->set($field->Expression, $parm);
        }
        $filter = $curfilter ? $this->CurrentFilter : "";
        if (is_array($where)) {
            $where = $this->arrayToFilter($where);
        }
        AddFilter($filter, $where);
        if ($filter != "") {
            $queryBuilder->where($filter);
        }
        return $queryBuilder;
    }

    // Update
    public function update(&$rs, $where = "", $rsold = null, $curfilter = true)
    {
        // If no field is updated, execute may return 0. Treat as success
        try {
            $success = $this->updateSql($rs, $where, $curfilter)->executeStatement();
            $success = $success > 0 ? $success : true;
            $this->DbErrorMessage = "";
        } catch (\Exception $e) {
            $success = false;
            $this->DbErrorMessage = $e->getMessage();
        }

        // Return auto increment field
        if ($success) {
            if (!isset($rs['department_id']) && !EmptyValue($this->department_id->CurrentValue)) {
                $rs['department_id'] = $this->department_id->CurrentValue;
            }
        }
        return $success;
    }

    /**
     * DELETE statement
     *
     * @param array $rs Key values
     * @param string|array $where WHERE clause
     * @param string $curfilter Filter
     * @return QueryBuilder
     */
    public function deleteSql(&$rs, $where = "", $curfilter = true)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->delete($this->UpdateTable);
        if (is_array($where)) {
            $where = $this->arrayToFilter($where);
        }
        if ($rs) {
            if (array_key_exists('department_id', $rs)) {
                AddFilter($where, QuotedName('department_id', $this->Dbid) . '=' . QuotedValue($rs['department_id'], $this->department_id->DataType, $this->Dbid));
            }
        }
        $filter = $curfilter ? $this->CurrentFilter : "";
        AddFilter($filter, $where);
        return $queryBuilder->where($filter != "" ? $filter : "0=1");
    }

    // Delete
    public function delete(&$rs, $where = "", $curfilter = false)
    {
        $success = true;
        if ($success) {
            try {
                $success = $this->deleteSql($rs, $where, $curfilter)->executeStatement();
                $this->DbErrorMessage = "";
            } catch (\Exception $e) {
                $success = false;
                $this->DbErrorMessage = $e->getMessage();
            }
        }
        return $success;
    }

    // Load DbValue from result set or array
    protected function loadDbValues($row)
    {
        if (!is_array($row)) {
            return;
        }
        $this->department_id->DbValue = $row['department_id'];
        $this->department_name->DbValue = $row['department_name'];
        $this->description->DbValue = $row['description'];
        $this->parent_department_id->DbValue = $row['parent_department_id'];
        $this->department_head_id->DbValue = $row['department_head_id'];
        $this->department_code->DbValue = $row['department_code'];
        $this->department_acronym->DbValue = $row['department_acronym'];
        $this->office_location->DbValue = $row['office_location'];
        $this->contact_number->DbValue = $row['contact_number'];
        $this->_email->DbValue = $row['email'];
        $this->is_active->DbValue = (ConvertToBool($row['is_active']) ? "1" : "0");
        $this->created_at->DbValue = $row['created_at'];
        $this->updated_at->DbValue = $row['updated_at'];
        $this->rc_id->DbValue = $row['rc_id'];
        $this->office_id->DbValue = $row['office_id'];
        $this->department_type->DbValue = $row['department_type'];
        $this->hierarchy_level->DbValue = $row['hierarchy_level'];
        $this->is_budget_unit->DbValue = (ConvertToBool($row['is_budget_unit']) ? "1" : "0");
    }

    // Delete uploaded files
    public function deleteUploadedFiles($row)
    {
        $this->loadDbValues($row);
    }

    // Record filter WHERE clause
    protected function sqlKeyFilter()
    {
        return "\"department_id\" = @department_id@";
    }

    // Get Key
    public function getKey($current = false, $keySeparator = null)
    {
        $keys = [];
        $val = $current ? $this->department_id->CurrentValue : $this->department_id->OldValue;
        if (EmptyValue($val)) {
            return "";
        } else {
            $keys[] = $val;
        }
        $keySeparator ??= Config("COMPOSITE_KEY_SEPARATOR");
        return implode($keySeparator, $keys);
    }

    // Set Key
    public function setKey($key, $current = false, $keySeparator = null)
    {
        $keySeparator ??= Config("COMPOSITE_KEY_SEPARATOR");
        $this->OldKey = strval($key);
        $keys = explode($keySeparator, $this->OldKey);
        if (count($keys) == 1) {
            if ($current) {
                $this->department_id->CurrentValue = $keys[0];
            } else {
                $this->department_id->OldValue = $keys[0];
            }
        }
    }

    // Get record filter
    public function getRecordFilter($row = null, $current = false)
    {
        $keyFilter = $this->sqlKeyFilter();
        if (is_array($row)) {
            $val = array_key_exists('department_id', $row) ? $row['department_id'] : null;
        } else {
            $val = !EmptyValue($this->department_id->OldValue) && !$current ? $this->department_id->OldValue : $this->department_id->CurrentValue;
        }
        if (!is_numeric($val)) {
            return "0=1"; // Invalid key
        }
        if ($val === null) {
            return "0=1"; // Invalid key
        } else {
            $keyFilter = str_replace("@department_id@", AdjustSql($val, $this->Dbid), $keyFilter); // Replace key value
        }
        return $keyFilter;
    }

    // Return page URL
    public function getReturnUrl()
    {
        $referUrl = ReferUrl();
        $referPageName = ReferPageName();
        $name = PROJECT_NAME . "_" . $this->TableVar . "_" . Config("TABLE_RETURN_URL");
        // Get referer URL automatically
        if ($referUrl != "" && $referPageName != CurrentPageName() && $referPageName != "login") { // Referer not same page or login page
            $_SESSION[$name] = $referUrl; // Save to Session
        }
        return $_SESSION[$name] ?? GetUrl("DepartmentsList");
    }

    // Set return page URL
    public function setReturnUrl($v)
    {
        $_SESSION[PROJECT_NAME . "_" . $this->TableVar . "_" . Config("TABLE_RETURN_URL")] = $v;
    }

    // Get modal caption
    public function getModalCaption($pageName)
    {
        global $Language;
        return match ($pageName) {
            "DepartmentsView" => $Language->phrase("View"),
            "DepartmentsEdit" => $Language->phrase("Edit"),
            "DepartmentsAdd" => $Language->phrase("Add"),
            default => ""
        };
    }

    // Default route URL
    public function getDefaultRouteUrl()
    {
        return "DepartmentsList";
    }

    // API page name
    public function getApiPageName($action)
    {
        return match (strtolower($action)) {
            Config("API_VIEW_ACTION") => "DepartmentsView",
            Config("API_ADD_ACTION") => "DepartmentsAdd",
            Config("API_EDIT_ACTION") => "DepartmentsEdit",
            Config("API_DELETE_ACTION") => "DepartmentsDelete",
            Config("API_LIST_ACTION") => "DepartmentsList",
            default => ""
        };
    }

    // Current URL
    public function getCurrentUrl($parm = "")
    {
        $url = CurrentPageUrl(false);
        if ($parm != "") {
            $url = $this->keyUrl($url, $parm);
        } else {
            $url = $this->keyUrl($url, Config("TABLE_SHOW_DETAIL") . "=");
        }
        return $this->addMasterUrl($url);
    }

    // List URL
    public function getListUrl()
    {
        return "DepartmentsList";
    }

    // View URL
    public function getViewUrl($parm = "")
    {
        if ($parm != "") {
            $url = $this->keyUrl("DepartmentsView", $parm);
        } else {
            $url = $this->keyUrl("DepartmentsView", Config("TABLE_SHOW_DETAIL") . "=");
        }
        return $this->addMasterUrl($url);
    }

    // Add URL
    public function getAddUrl($parm = "")
    {
        if ($parm != "") {
            $url = "DepartmentsAdd?" . $parm;
        } else {
            $url = "DepartmentsAdd";
        }
        return $this->addMasterUrl($url);
    }

    // Edit URL
    public function getEditUrl($parm = "")
    {
        $url = $this->keyUrl("DepartmentsEdit", $parm);
        return $this->addMasterUrl($url);
    }

    // Inline edit URL
    public function getInlineEditUrl()
    {
        $url = $this->keyUrl("DepartmentsList", "action=edit");
        return $this->addMasterUrl($url);
    }

    // Copy URL
    public function getCopyUrl($parm = "")
    {
        $url = $this->keyUrl("DepartmentsAdd", $parm);
        return $this->addMasterUrl($url);
    }

    // Inline copy URL
    public function getInlineCopyUrl()
    {
        $url = $this->keyUrl("DepartmentsList", "action=copy");
        return $this->addMasterUrl($url);
    }

    // Delete URL
    public function getDeleteUrl($parm = "")
    {
        if ($this->UseAjaxActions && ConvertToBool(Param("infinitescroll")) && CurrentPageID() == "list") {
            return $this->keyUrl(GetApiUrl(Config("API_DELETE_ACTION") . "/" . $this->TableVar));
        } else {
            return $this->keyUrl("DepartmentsDelete", $parm);
        }
    }

    // Add master url
    public function addMasterUrl($url)
    {
        return $url;
    }

    public function keyToJson($htmlEncode = false)
    {
        $json = "";
        $json .= "\"department_id\":" . VarToJson($this->department_id->CurrentValue, "number");
        $json = "{" . $json . "}";
        if ($htmlEncode) {
            $json = HtmlEncode($json);
        }
        return $json;
    }

    // Add key value to URL
    public function keyUrl($url, $parm = "")
    {
        if ($this->department_id->CurrentValue !== null) {
            $url .= "/" . $this->encodeKeyValue($this->department_id->CurrentValue);
        } else {
            return "javascript:ew.alert(ew.language.phrase('InvalidRecord'));";
        }
        if ($parm != "") {
            $url .= "?" . $parm;
        }
        return $url;
    }

    // Render sort
    public function renderFieldHeader($fld)
    {
        global $Security, $Language;
        $sortUrl = "";
        $attrs = "";
        if ($this->PageID != "grid" && $fld->Sortable) {
            $sortUrl = $this->sortUrl($fld);
            $attrs = ' role="button" data-ew-action="sort" data-ajax="' . ($this->UseAjaxActions ? "true" : "false") . '" data-sort-url="' . $sortUrl . '" data-sort-type="1"';
            if ($this->ContextClass) { // Add context
                $attrs .= ' data-context="' . HtmlEncode($this->ContextClass) . '"';
            }
        }
        $html = '<div class="ew-table-header-caption"' . $attrs . '>' . $fld->caption() . '</div>';
        if ($sortUrl) {
            $html .= '<div class="ew-table-header-sort">' . $fld->getSortIcon() . '</div>';
        }
        if ($this->PageID != "grid" && !$this->isExport() && $fld->UseFilter && $Security->canSearch()) {
            $html .= '<div class="ew-filter-dropdown-btn" data-ew-action="filter" data-table="' . $fld->TableVar . '" data-field="' . $fld->FieldVar .
                '"><div class="ew-table-header-filter" role="button" aria-haspopup="true">' . $Language->phrase("Filter") .
                (is_array($fld->EditValue) ? str_replace("%c", count($fld->EditValue), $Language->phrase("FilterCount")) : '') .
                '</div></div>';
        }
        $html = '<div class="ew-table-header-btn">' . $html . '</div>';
        if ($this->UseCustomTemplate) {
            $scriptId = str_replace("{id}", $fld->TableVar . "_" . $fld->Param, "tpc_{id}");
            $html = '<template id="' . $scriptId . '">' . $html . '</template>';
        }
        return $html;
    }

    // Sort URL
    public function sortUrl($fld)
    {
        global $DashboardReport;
        if (
            $this->CurrentAction || $this->isExport() ||
            in_array($fld->Type, [128, 204, 205])
        ) { // Unsortable data type
                return "";
        } elseif ($fld->Sortable) {
            $urlParm = "order=" . urlencode($fld->Name) . "&amp;ordertype=" . $fld->getNextSort();
            if ($DashboardReport) {
                $urlParm .= "&amp;" . Config("PAGE_DASHBOARD") . "=" . $DashboardReport;
            }
            return $this->addMasterUrl($this->CurrentPageName . "?" . $urlParm);
        } else {
            return "";
        }
    }

    // Get record keys from Post/Get/Session
    public function getRecordKeys()
    {
        $arKeys = [];
        $arKey = [];
        if (Param("key_m") !== null) {
            $arKeys = Param("key_m");
            $cnt = count($arKeys);
        } else {
            $isApi = IsApi();
            $keyValues = $isApi
                ? (Route(0) == "export"
                    ? array_map(fn ($i) => Route($i + 3), range(0, 0))  // Export API
                    : array_map(fn ($i) => Route($i + 2), range(0, 0))) // Other API
                : []; // Non-API
            if (($keyValue = Param("department_id") ?? Route("department_id")) !== null) {
                $arKeys[] = $keyValue;
            } elseif ($isApi && (($keyValue = Key(0) ?? $keyValues[0] ?? null) !== null)) {
                $arKeys[] = $keyValue;
            } else {
                $arKeys = null; // Do not setup
            }
        }
        // Check keys
        $ar = [];
        if (is_array($arKeys)) {
            foreach ($arKeys as $key) {
                if (!is_numeric($key)) {
                    continue;
                }
                $ar[] = $key;
            }
        }
        return $ar;
    }

    // Get filter from records
    public function getFilterFromRecords($rows)
    {
        return implode(" OR ", array_map(fn($row) => "(" . $this->getRecordFilter($row) . ")", $rows));
    }

    // Get filter from record keys
    public function getFilterFromRecordKeys($setCurrent = true)
    {
        $arKeys = $this->getRecordKeys();
        $keyFilter = "";
        foreach ($arKeys as $key) {
            if ($keyFilter != "") {
                $keyFilter .= " OR ";
            }
            if ($setCurrent) {
                $this->department_id->CurrentValue = $key;
            } else {
                $this->department_id->OldValue = $key;
            }
            $keyFilter .= "(" . $this->getRecordFilter() . ")";
        }
        return $keyFilter;
    }

    // Load result set based on filter/sort
    public function loadRs($filter, $sort = "")
    {
        $sql = $this->getSql($filter, $sort); // Set up filter (WHERE Clause) / sort (ORDER BY Clause)
        $conn = $this->getConnection();
        return $conn->executeQuery($sql);
    }

    // Load row values from record
    public function loadListRowValues(&$rs)
    {
        if (is_array($rs)) {
            $row = $rs;
        } elseif ($rs && property_exists($rs, "fields")) { // Recordset
            $row = $rs->fields;
        } else {
            return;
        }
        $this->department_id->setDbValue($row['department_id']);
        $this->department_name->setDbValue($row['department_name']);
        $this->description->setDbValue($row['description']);
        $this->parent_department_id->setDbValue($row['parent_department_id']);
        $this->department_head_id->setDbValue($row['department_head_id']);
        $this->department_code->setDbValue($row['department_code']);
        $this->department_acronym->setDbValue($row['department_acronym']);
        $this->office_location->setDbValue($row['office_location']);
        $this->contact_number->setDbValue($row['contact_number']);
        $this->_email->setDbValue($row['email']);
        $this->is_active->setDbValue(ConvertToBool($row['is_active']) ? "1" : "0");
        $this->created_at->setDbValue($row['created_at']);
        $this->updated_at->setDbValue($row['updated_at']);
        $this->rc_id->setDbValue($row['rc_id']);
        $this->office_id->setDbValue($row['office_id']);
        $this->department_type->setDbValue($row['department_type']);
        $this->hierarchy_level->setDbValue($row['hierarchy_level']);
        $this->is_budget_unit->setDbValue(ConvertToBool($row['is_budget_unit']) ? "1" : "0");
    }

    // Render list content
    public function renderListContent($filter)
    {
        global $Response;
        $listPage = "DepartmentsList";
        $listClass = PROJECT_NAMESPACE . $listPage;
        $page = new $listClass();
        $page->loadRecordsetFromFilter($filter);
        $view = Container("app.view");
        $template = $listPage . ".php"; // View
        $GLOBALS["Title"] ??= $page->Title; // Title
        try {
            $Response = $view->render($Response, $template, $GLOBALS);
        } finally {
            $page->terminate(); // Terminate page and clean up
        }
    }

    // Render list row values
    public function renderListRow()
    {
        global $Security, $CurrentLanguage, $Language;

        // Call Row Rendering event
        $this->rowRendering();

        // Common render codes

        // department_id

        // department_name

        // description

        // parent_department_id

        // department_head_id

        // department_code

        // department_acronym

        // office_location

        // contact_number

        // email

        // is_active

        // created_at

        // updated_at

        // rc_id

        // office_id

        // department_type

        // hierarchy_level

        // is_budget_unit

        // department_id
        $this->department_id->ViewValue = $this->department_id->CurrentValue;

        // department_name
        $this->department_name->ViewValue = $this->department_name->CurrentValue;

        // description
        $this->description->ViewValue = $this->description->CurrentValue;

        // parent_department_id
        $curVal = strval($this->parent_department_id->CurrentValue);
        if ($curVal != "") {
            $this->parent_department_id->ViewValue = $this->parent_department_id->lookupCacheOption($curVal);
            if ($this->parent_department_id->ViewValue === null) { // Lookup from database
                $filterWrk = SearchFilter($this->parent_department_id->Lookup->getTable()->Fields["department_id"]->searchExpression(), "=", $curVal, $this->parent_department_id->Lookup->getTable()->Fields["department_id"]->searchDataType(), "");
                $lookupFilter = $this->parent_department_id->getSelectFilter($this); // PHP
                $sqlWrk = $this->parent_department_id->Lookup->getSql(false, $filterWrk, $lookupFilter, $this, true, true);
                $conn = Conn();
                $config = $conn->getConfiguration();
                $config->setResultCache($this->Cache);
                $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                $ari = count($rswrk);
                if ($ari > 0) { // Lookup values found
                    $arwrk = $this->parent_department_id->Lookup->renderViewRow($rswrk[0]);
                    $this->parent_department_id->ViewValue = $this->parent_department_id->displayValue($arwrk);
                } else {
                    $this->parent_department_id->ViewValue = FormatNumber($this->parent_department_id->CurrentValue, $this->parent_department_id->formatPattern());
                }
            }
        } else {
            $this->parent_department_id->ViewValue = null;
        }

        // department_head_id
        $this->department_head_id->ViewValue = $this->department_head_id->CurrentValue;
        $this->department_head_id->ViewValue = FormatNumber($this->department_head_id->ViewValue, $this->department_head_id->formatPattern());

        // department_code
        $this->department_code->ViewValue = $this->department_code->CurrentValue;

        // department_acronym
        $this->department_acronym->ViewValue = $this->department_acronym->CurrentValue;

        // office_location
        $this->office_location->ViewValue = $this->office_location->CurrentValue;

        // contact_number
        $this->contact_number->ViewValue = $this->contact_number->CurrentValue;

        // email
        $this->_email->ViewValue = $this->_email->CurrentValue;

        // is_active
        if (ConvertToBool($this->is_active->CurrentValue)) {
            $this->is_active->ViewValue = $this->is_active->tagCaption(1) != "" ? $this->is_active->tagCaption(1) : "Yes";
        } else {
            $this->is_active->ViewValue = $this->is_active->tagCaption(2) != "" ? $this->is_active->tagCaption(2) : "No";
        }

        // created_at
        $this->created_at->ViewValue = $this->created_at->CurrentValue;
        $this->created_at->ViewValue = FormatDateTime($this->created_at->ViewValue, $this->created_at->formatPattern());

        // updated_at
        $this->updated_at->ViewValue = $this->updated_at->CurrentValue;
        $this->updated_at->ViewValue = FormatDateTime($this->updated_at->ViewValue, $this->updated_at->formatPattern());

        // rc_id
        $this->rc_id->ViewValue = $this->rc_id->CurrentValue;
        $this->rc_id->ViewValue = FormatNumber($this->rc_id->ViewValue, $this->rc_id->formatPattern());

        // office_id
        $this->office_id->ViewValue = $this->office_id->CurrentValue;
        $this->office_id->ViewValue = FormatNumber($this->office_id->ViewValue, $this->office_id->formatPattern());

        // department_type
        $this->department_type->ViewValue = $this->department_type->CurrentValue;

        // hierarchy_level
        $this->hierarchy_level->ViewValue = $this->hierarchy_level->CurrentValue;
        $this->hierarchy_level->ViewValue = FormatNumber($this->hierarchy_level->ViewValue, $this->hierarchy_level->formatPattern());

        // is_budget_unit
        if (ConvertToBool($this->is_budget_unit->CurrentValue)) {
            $this->is_budget_unit->ViewValue = $this->is_budget_unit->tagCaption(1) != "" ? $this->is_budget_unit->tagCaption(1) : "Yes";
        } else {
            $this->is_budget_unit->ViewValue = $this->is_budget_unit->tagCaption(2) != "" ? $this->is_budget_unit->tagCaption(2) : "No";
        }

        // department_id
        $this->department_id->HrefValue = "";
        $this->department_id->TooltipValue = "";

        // department_name
        $this->department_name->HrefValue = "";
        $this->department_name->TooltipValue = "";

        // description
        $this->description->HrefValue = "";
        $this->description->TooltipValue = "";

        // parent_department_id
        $this->parent_department_id->HrefValue = "";
        $this->parent_department_id->TooltipValue = "";

        // department_head_id
        $this->department_head_id->HrefValue = "";
        $this->department_head_id->TooltipValue = "";

        // department_code
        $this->department_code->HrefValue = "";
        $this->department_code->TooltipValue = "";

        // department_acronym
        $this->department_acronym->HrefValue = "";
        $this->department_acronym->TooltipValue = "";

        // office_location
        $this->office_location->HrefValue = "";
        $this->office_location->TooltipValue = "";

        // contact_number
        $this->contact_number->HrefValue = "";
        $this->contact_number->TooltipValue = "";

        // email
        $this->_email->HrefValue = "";
        $this->_email->TooltipValue = "";

        // is_active
        $this->is_active->HrefValue = "";
        $this->is_active->TooltipValue = "";

        // created_at
        $this->created_at->HrefValue = "";
        $this->created_at->TooltipValue = "";

        // updated_at
        $this->updated_at->HrefValue = "";
        $this->updated_at->TooltipValue = "";

        // rc_id
        $this->rc_id->HrefValue = "";
        $this->rc_id->TooltipValue = "";

        // office_id
        $this->office_id->HrefValue = "";
        $this->office_id->TooltipValue = "";

        // department_type
        $this->department_type->HrefValue = "";
        $this->department_type->TooltipValue = "";

        // hierarchy_level
        $this->hierarchy_level->HrefValue = "";
        $this->hierarchy_level->TooltipValue = "";

        // is_budget_unit
        $this->is_budget_unit->HrefValue = "";
        $this->is_budget_unit->TooltipValue = "";

        // Call Row Rendered event
        $this->rowRendered();

        // Save data for Custom Template
        $this->Rows[] = $this->customTemplateFieldValues();
    }

    // Render edit row values
    public function renderEditRow()
    {
        global $Security, $CurrentLanguage, $Language;

        // Call Row Rendering event
        $this->rowRendering();

        // department_id
        $this->department_id->setupEditAttributes();
        $this->department_id->EditValue = $this->department_id->CurrentValue;

        // department_name
        $this->department_name->setupEditAttributes();
        if (!$this->department_name->Raw) {
            $this->department_name->CurrentValue = HtmlDecode($this->department_name->CurrentValue);
        }
        $this->department_name->EditValue = $this->department_name->CurrentValue;
        $this->department_name->PlaceHolder = RemoveHtml($this->department_name->caption());

        // description
        $this->description->setupEditAttributes();
        $this->description->EditValue = $this->description->CurrentValue;
        $this->description->PlaceHolder = RemoveHtml($this->description->caption());

        // parent_department_id
        $this->parent_department_id->setupEditAttributes();
        $this->parent_department_id->PlaceHolder = RemoveHtml($this->parent_department_id->caption());

        // department_head_id
        $this->department_head_id->setupEditAttributes();
        $this->department_head_id->EditValue = $this->department_head_id->CurrentValue;
        $this->department_head_id->PlaceHolder = RemoveHtml($this->department_head_id->caption());
        if (strval($this->department_head_id->EditValue) != "" && is_numeric($this->department_head_id->EditValue)) {
            $this->department_head_id->EditValue = FormatNumber($this->department_head_id->EditValue, null);
        }

        // department_code
        $this->department_code->setupEditAttributes();
        if (!$this->department_code->Raw) {
            $this->department_code->CurrentValue = HtmlDecode($this->department_code->CurrentValue);
        }
        $this->department_code->EditValue = $this->department_code->CurrentValue;
        $this->department_code->PlaceHolder = RemoveHtml($this->department_code->caption());

        // department_acronym
        $this->department_acronym->setupEditAttributes();
        if (!$this->department_acronym->Raw) {
            $this->department_acronym->CurrentValue = HtmlDecode($this->department_acronym->CurrentValue);
        }
        $this->department_acronym->EditValue = $this->department_acronym->CurrentValue;
        $this->department_acronym->PlaceHolder = RemoveHtml($this->department_acronym->caption());

        // office_location
        $this->office_location->setupEditAttributes();
        if (!$this->office_location->Raw) {
            $this->office_location->CurrentValue = HtmlDecode($this->office_location->CurrentValue);
        }
        $this->office_location->EditValue = $this->office_location->CurrentValue;
        $this->office_location->PlaceHolder = RemoveHtml($this->office_location->caption());

        // contact_number
        $this->contact_number->setupEditAttributes();
        if (!$this->contact_number->Raw) {
            $this->contact_number->CurrentValue = HtmlDecode($this->contact_number->CurrentValue);
        }
        $this->contact_number->EditValue = $this->contact_number->CurrentValue;
        $this->contact_number->PlaceHolder = RemoveHtml($this->contact_number->caption());

        // email
        $this->_email->setupEditAttributes();
        if (!$this->_email->Raw) {
            $this->_email->CurrentValue = HtmlDecode($this->_email->CurrentValue);
        }
        $this->_email->EditValue = $this->_email->CurrentValue;
        $this->_email->PlaceHolder = RemoveHtml($this->_email->caption());

        // is_active
        $this->is_active->EditValue = $this->is_active->options(false);
        $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

        // created_at
        $this->created_at->setupEditAttributes();
        $this->created_at->EditValue = FormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        $this->created_at->PlaceHolder = RemoveHtml($this->created_at->caption());

        // updated_at
        $this->updated_at->setupEditAttributes();
        $this->updated_at->EditValue = FormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern());
        $this->updated_at->PlaceHolder = RemoveHtml($this->updated_at->caption());

        // rc_id
        $this->rc_id->setupEditAttributes();
        $this->rc_id->EditValue = $this->rc_id->CurrentValue;
        $this->rc_id->PlaceHolder = RemoveHtml($this->rc_id->caption());
        if (strval($this->rc_id->EditValue) != "" && is_numeric($this->rc_id->EditValue)) {
            $this->rc_id->EditValue = FormatNumber($this->rc_id->EditValue, null);
        }

        // office_id
        $this->office_id->setupEditAttributes();
        $this->office_id->EditValue = $this->office_id->CurrentValue;
        $this->office_id->PlaceHolder = RemoveHtml($this->office_id->caption());
        if (strval($this->office_id->EditValue) != "" && is_numeric($this->office_id->EditValue)) {
            $this->office_id->EditValue = FormatNumber($this->office_id->EditValue, null);
        }

        // department_type
        $this->department_type->setupEditAttributes();
        if (!$this->department_type->Raw) {
            $this->department_type->CurrentValue = HtmlDecode($this->department_type->CurrentValue);
        }
        $this->department_type->EditValue = $this->department_type->CurrentValue;
        $this->department_type->PlaceHolder = RemoveHtml($this->department_type->caption());

        // hierarchy_level
        $this->hierarchy_level->setupEditAttributes();
        $this->hierarchy_level->EditValue = $this->hierarchy_level->CurrentValue;
        $this->hierarchy_level->PlaceHolder = RemoveHtml($this->hierarchy_level->caption());
        if (strval($this->hierarchy_level->EditValue) != "" && is_numeric($this->hierarchy_level->EditValue)) {
            $this->hierarchy_level->EditValue = FormatNumber($this->hierarchy_level->EditValue, null);
        }

        // is_budget_unit
        $this->is_budget_unit->EditValue = $this->is_budget_unit->options(false);
        $this->is_budget_unit->PlaceHolder = RemoveHtml($this->is_budget_unit->caption());

        // Call Row Rendered event
        $this->rowRendered();
    }

    // Aggregate list row values
    public function aggregateListRowValues()
    {
    }

    // Aggregate list row (for rendering)
    public function aggregateListRow()
    {
        // Call Row Rendered event
        $this->rowRendered();
    }

    // Export data in HTML/CSV/Word/Excel/Email/PDF format
    public function exportDocument($doc, $result, $startRec = 1, $stopRec = 1, $exportPageType = "")
    {
        if (!$result || !$doc) {
            return;
        }
        if (!$doc->ExportCustom) {
            // Write header
            $doc->exportTableHeader();
            if ($doc->Horizontal) { // Horizontal format, write header
                $doc->beginExportRow();
                if ($exportPageType == "view") {
                    $doc->exportCaption($this->department_id);
                    $doc->exportCaption($this->department_name);
                    $doc->exportCaption($this->description);
                    $doc->exportCaption($this->parent_department_id);
                    $doc->exportCaption($this->department_head_id);
                    $doc->exportCaption($this->department_code);
                    $doc->exportCaption($this->department_acronym);
                    $doc->exportCaption($this->office_location);
                    $doc->exportCaption($this->contact_number);
                    $doc->exportCaption($this->_email);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->created_at);
                    $doc->exportCaption($this->updated_at);
                    $doc->exportCaption($this->rc_id);
                    $doc->exportCaption($this->office_id);
                    $doc->exportCaption($this->department_type);
                    $doc->exportCaption($this->hierarchy_level);
                    $doc->exportCaption($this->is_budget_unit);
                } else {
                    $doc->exportCaption($this->department_id);
                    $doc->exportCaption($this->department_name);
                    $doc->exportCaption($this->parent_department_id);
                    $doc->exportCaption($this->department_head_id);
                    $doc->exportCaption($this->department_code);
                    $doc->exportCaption($this->department_acronym);
                    $doc->exportCaption($this->office_location);
                    $doc->exportCaption($this->contact_number);
                    $doc->exportCaption($this->_email);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->created_at);
                    $doc->exportCaption($this->updated_at);
                    $doc->exportCaption($this->rc_id);
                    $doc->exportCaption($this->office_id);
                    $doc->exportCaption($this->department_type);
                    $doc->exportCaption($this->hierarchy_level);
                    $doc->exportCaption($this->is_budget_unit);
                }
                $doc->endExportRow();
            }
        }
        $recCnt = $startRec - 1;
        $stopRec = $stopRec > 0 ? $stopRec : PHP_INT_MAX;
        while (($row = $result->fetch()) && $recCnt < $stopRec) {
            $recCnt++;
            if ($recCnt >= $startRec) {
                $rowCnt = $recCnt - $startRec + 1;

                // Page break
                if ($this->ExportPageBreakCount > 0) {
                    if ($rowCnt > 1 && ($rowCnt - 1) % $this->ExportPageBreakCount == 0) {
                        $doc->exportPageBreak();
                    }
                }
                $this->loadListRowValues($row);

                // Render row
                $this->RowType = RowType::VIEW; // Render view
                $this->resetAttributes();
                $this->renderListRow();
                if (!$doc->ExportCustom) {
                    $doc->beginExportRow($rowCnt); // Allow CSS styles if enabled
                    if ($exportPageType == "view") {
                        $doc->exportField($this->department_id);
                        $doc->exportField($this->department_name);
                        $doc->exportField($this->description);
                        $doc->exportField($this->parent_department_id);
                        $doc->exportField($this->department_head_id);
                        $doc->exportField($this->department_code);
                        $doc->exportField($this->department_acronym);
                        $doc->exportField($this->office_location);
                        $doc->exportField($this->contact_number);
                        $doc->exportField($this->_email);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->created_at);
                        $doc->exportField($this->updated_at);
                        $doc->exportField($this->rc_id);
                        $doc->exportField($this->office_id);
                        $doc->exportField($this->department_type);
                        $doc->exportField($this->hierarchy_level);
                        $doc->exportField($this->is_budget_unit);
                    } else {
                        $doc->exportField($this->department_id);
                        $doc->exportField($this->department_name);
                        $doc->exportField($this->parent_department_id);
                        $doc->exportField($this->department_head_id);
                        $doc->exportField($this->department_code);
                        $doc->exportField($this->department_acronym);
                        $doc->exportField($this->office_location);
                        $doc->exportField($this->contact_number);
                        $doc->exportField($this->_email);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->created_at);
                        $doc->exportField($this->updated_at);
                        $doc->exportField($this->rc_id);
                        $doc->exportField($this->office_id);
                        $doc->exportField($this->department_type);
                        $doc->exportField($this->hierarchy_level);
                        $doc->exportField($this->is_budget_unit);
                    }
                    $doc->endExportRow($rowCnt);
                }
            }

            // Call Row Export server event
            if ($doc->ExportCustom) {
                $this->rowExport($doc, $row);
            }
        }
        if (!$doc->ExportCustom) {
            $doc->exportTableFooter();
        }
    }

    // Get file data
    public function getFileData($fldparm, $key, $resize, $width = 0, $height = 0, $plugins = [])
    {
        global $DownloadFileName;

        // No binary fields
        return false;
    }

    // Table level events

    // Table Load event
    public function tableLoad()
    {
        // Enter your code here
    }

    // Recordset Selecting event
    public function recordsetSelecting(&$filter)
    {
        // Enter your code here
    }

    // Recordset Selected event
    public function recordsetSelected($rs)
    {
        //Log("Recordset Selected");
    }

    // Recordset Search Validated event
    public function recordsetSearchValidated()
    {
        // Example:
        //$this->MyField1->AdvancedSearch->SearchValue = "your search criteria"; // Search value
    }

    // Recordset Searching event
    public function recordsetSearching(&$filter)
    {
        // Enter your code here
    }

    // Row_Selecting event
    public function rowSelecting(&$filter)
    {
        // Enter your code here
    }

    // Row Selected event
    public function rowSelected(&$rs)
    {
        //Log("Row Selected");
    }

    // Row Inserting event
    public function rowInserting($rsold, &$rsnew)
    {
        // Enter your code here
        // To cancel, set return value to false
        Log("Row Inserting" . var_dump($rsold) . var_dump($rsnew));
        return true;
    }
    // Row Inserted event
    public function rowInserted($rsold, $rsnew)
    {
        Log("Row Inserted" . var_dump($rsold) . var_dump($rsnew));
    }
    // Row Updating event
    public function rowUpdating($rsold, &$rsnew)
    {
        // Enter your code here
        // To cancel, set return value to false
        Log("Row Updating" . var_dump($rsold) . var_dump($rsnew));
        return true;
    }
    // Row Updated event
    public function rowUpdated($rsold, $rsnew)
    {
        Log("Row Updated" . var_dump($rsold) . var_dump($rsnew));

        //Log("Row Updated");
    }

    // Row Update Conflict event
    public function rowUpdateConflict($rsold, &$rsnew)
    {
        // Enter your code here
        // To ignore conflict, set return value to false
        return true;
    }

    // Grid Inserting event
    public function gridInserting()
    {
        // Enter your code here
        // To reject grid insert, set return value to false
        return true;
    }

    // Grid Inserted event
    public function gridInserted($rsnew)
    {
        //Log("Grid Inserted");
    }

    // Grid Updating event
    public function gridUpdating($rsold)
    {
        // Enter your code here
        // To reject grid update, set return value to false
        return true;
    }

    // Grid Updated event
    public function gridUpdated($rsold, $rsnew)
    {
        //Log("Grid Updated");
    }

    // Row Deleting event
    public function rowDeleting(&$rs)
    {
        // Enter your code here
        // To cancel, set return value to False
        Log("Row Deleting" . var_dump($rs));
        return true;
    }
    // Row Deleted event
    public function rowDeleted($rs)
    {
        Log("Row Deleted" . var_dump($rs));
    }

    // Email Sending event
    public function emailSending($email, $args)
    {
        //var_dump($email, $args); exit();
        return true;
    }

    // Lookup Selecting event
    public function lookupSelecting($fld, &$filter)
    {
        //var_dump($fld->Name, $fld->Lookup, $filter); // Uncomment to view the filter
        // Enter your code here
    }

    // Row Rendering event
    public function rowRendering()
    {
        // Enter your code here
    }

    // Row Rendered event
    public function rowRendered()
    {
        // To view properties of field class, use:
        //var_dump($this-><FieldName>);
    }

    // User ID Filtering event
    public function userIdFiltering(&$filter)
    {
        // Enter your code here
    }
}
