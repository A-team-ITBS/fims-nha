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
 * Table class for plantilla_items
 */
class PlantillaItems extends DbTable
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
    public $plantilla_item_id;
    public $department_id;
    public $position_id;
    public $item_number;
    public $item_description;
    public $authorized_count;
    public $is_active;
    public $effective_date;
    public $abolition_date;
    public $annual_budget_allocation;
    public $monthly_salary_allocation;
    public $remarks;
    public $created_by;
    public $approved_by;
    public $created_at;
    public $updated_at;

    // Page ID
    public $PageID = ""; // To be overridden by subclass

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $CurrentLanguage, $CurrentLocale;

        // Language object
        $Language = Container("app.language");
        $this->TableVar = "plantilla_items";
        $this->TableName = 'plantilla_items';
        $this->TableType = "TABLE";
        $this->ImportUseTransaction = $this->supportsTransaction() && Config("IMPORT_USE_TRANSACTION");
        $this->UseTransaction = $this->supportsTransaction() && Config("USE_TRANSACTION");

        // Update Table
        $this->UpdateTable = "uac.plantilla_items";
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

        // plantilla_item_id
        $this->plantilla_item_id = new DbField(
            $this, // Table
            'x_plantilla_item_id', // Variable name
            'plantilla_item_id', // Name
            '"plantilla_item_id"', // Expression
            'CAST("plantilla_item_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"plantilla_item_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'NO' // Edit Tag
        );
        $this->plantilla_item_id->InputTextType = "text";
        $this->plantilla_item_id->Raw = true;
        $this->plantilla_item_id->IsAutoIncrement = true; // Autoincrement field
        $this->plantilla_item_id->IsPrimaryKey = true; // Primary key field
        $this->plantilla_item_id->Nullable = false; // NOT NULL field
        $this->plantilla_item_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->plantilla_item_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['plantilla_item_id'] = &$this->plantilla_item_id;

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
            'TEXT' // Edit Tag
        );
        $this->department_id->InputTextType = "text";
        $this->department_id->Raw = true;
        $this->department_id->Nullable = false; // NOT NULL field
        $this->department_id->Required = true; // Required field
        $this->department_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->department_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['department_id'] = &$this->department_id;

        // position_id
        $this->position_id = new DbField(
            $this, // Table
            'x_position_id', // Variable name
            'position_id', // Name
            '"position_id"', // Expression
            'CAST("position_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"position_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->position_id->InputTextType = "text";
        $this->position_id->Raw = true;
        $this->position_id->Nullable = false; // NOT NULL field
        $this->position_id->Required = true; // Required field
        $this->position_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->position_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['position_id'] = &$this->position_id;

        // item_number
        $this->item_number = new DbField(
            $this, // Table
            'x_item_number', // Variable name
            'item_number', // Name
            '"item_number"', // Expression
            '"item_number"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"item_number"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->item_number->InputTextType = "text";
        $this->item_number->Nullable = false; // NOT NULL field
        $this->item_number->Required = true; // Required field
        $this->item_number->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['item_number'] = &$this->item_number;

        // item_description
        $this->item_description = new DbField(
            $this, // Table
            'x_item_description', // Variable name
            'item_description', // Name
            '"item_description"', // Expression
            '"item_description"', // Basic search expression
            200, // Type
            200, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"item_description"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->item_description->InputTextType = "text";
        $this->item_description->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['item_description'] = &$this->item_description;

        // authorized_count
        $this->authorized_count = new DbField(
            $this, // Table
            'x_authorized_count', // Variable name
            'authorized_count', // Name
            '"authorized_count"', // Expression
            'CAST("authorized_count" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"authorized_count"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->authorized_count->addMethod("getDefault", fn() => 1);
        $this->authorized_count->InputTextType = "text";
        $this->authorized_count->Raw = true;
        $this->authorized_count->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->authorized_count->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['authorized_count'] = &$this->authorized_count;

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
        $this->is_active->Lookup = new Lookup($this->is_active, 'plantilla_items', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->is_active->OptionCount = 2;
        $this->is_active->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['is_active'] = &$this->is_active;

        // effective_date
        $this->effective_date = new DbField(
            $this, // Table
            'x_effective_date', // Variable name
            'effective_date', // Name
            '"effective_date"', // Expression
            CastDateFieldForLike("\"effective_date\"", 0, "DB"), // Basic search expression
            133, // Type
            0, // Size
            0, // Date/Time format
            false, // Is upload field
            '"effective_date"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->effective_date->InputTextType = "text";
        $this->effective_date->Raw = true;
        $this->effective_date->Nullable = false; // NOT NULL field
        $this->effective_date->Required = true; // Required field
        $this->effective_date->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->effective_date->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['effective_date'] = &$this->effective_date;

        // abolition_date
        $this->abolition_date = new DbField(
            $this, // Table
            'x_abolition_date', // Variable name
            'abolition_date', // Name
            '"abolition_date"', // Expression
            CastDateFieldForLike("\"abolition_date\"", 0, "DB"), // Basic search expression
            133, // Type
            0, // Size
            0, // Date/Time format
            false, // Is upload field
            '"abolition_date"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->abolition_date->InputTextType = "text";
        $this->abolition_date->Raw = true;
        $this->abolition_date->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->abolition_date->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['abolition_date'] = &$this->abolition_date;

        // annual_budget_allocation
        $this->annual_budget_allocation = new DbField(
            $this, // Table
            'x_annual_budget_allocation', // Variable name
            'annual_budget_allocation', // Name
            '"annual_budget_allocation"', // Expression
            'CAST("annual_budget_allocation" AS varchar(255))', // Basic search expression
            14, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"annual_budget_allocation"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->annual_budget_allocation->InputTextType = "text";
        $this->annual_budget_allocation->Raw = true;
        $this->annual_budget_allocation->DefaultErrorMessage = $Language->phrase("IncorrectFloat");
        $this->annual_budget_allocation->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['annual_budget_allocation'] = &$this->annual_budget_allocation;

        // monthly_salary_allocation
        $this->monthly_salary_allocation = new DbField(
            $this, // Table
            'x_monthly_salary_allocation', // Variable name
            'monthly_salary_allocation', // Name
            '"monthly_salary_allocation"', // Expression
            'CAST("monthly_salary_allocation" AS varchar(255))', // Basic search expression
            14, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"monthly_salary_allocation"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->monthly_salary_allocation->InputTextType = "text";
        $this->monthly_salary_allocation->Raw = true;
        $this->monthly_salary_allocation->DefaultErrorMessage = $Language->phrase("IncorrectFloat");
        $this->monthly_salary_allocation->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['monthly_salary_allocation'] = &$this->monthly_salary_allocation;

        // remarks
        $this->remarks = new DbField(
            $this, // Table
            'x_remarks', // Variable name
            'remarks', // Name
            '"remarks"', // Expression
            '"remarks"', // Basic search expression
            201, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"remarks"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXTAREA' // Edit Tag
        );
        $this->remarks->InputTextType = "text";
        $this->remarks->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['remarks'] = &$this->remarks;

        // created_by
        $this->created_by = new DbField(
            $this, // Table
            'x_created_by', // Variable name
            'created_by', // Name
            '"created_by"', // Expression
            'CAST("created_by" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"created_by"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->created_by->InputTextType = "text";
        $this->created_by->Raw = true;
        $this->created_by->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->created_by->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['created_by'] = &$this->created_by;

        // approved_by
        $this->approved_by = new DbField(
            $this, // Table
            'x_approved_by', // Variable name
            'approved_by', // Name
            '"approved_by"', // Expression
            'CAST("approved_by" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"approved_by"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->approved_by->InputTextType = "text";
        $this->approved_by->Raw = true;
        $this->approved_by->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->approved_by->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['approved_by'] = &$this->approved_by;

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
        return ($this->SqlFrom != "") ? $this->SqlFrom : "uac.plantilla_items";
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
                $queryBuilder->getSQL() . " RETURNING plantilla_item_id",
                $queryBuilder->getParameters(),
                $queryBuilder->getParameterTypes()
            )->fetchOne();
            $this->DbErrorMessage = "";
        } catch (\Exception $e) {
            $result = false;
            $this->DbErrorMessage = $e->getMessage();
        }
        if ($result) {
            $this->plantilla_item_id->setDbValue($result);
            $rs['plantilla_item_id'] = $this->plantilla_item_id->DbValue;
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
            if (!isset($rs['plantilla_item_id']) && !EmptyValue($this->plantilla_item_id->CurrentValue)) {
                $rs['plantilla_item_id'] = $this->plantilla_item_id->CurrentValue;
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
            if (array_key_exists('plantilla_item_id', $rs)) {
                AddFilter($where, QuotedName('plantilla_item_id', $this->Dbid) . '=' . QuotedValue($rs['plantilla_item_id'], $this->plantilla_item_id->DataType, $this->Dbid));
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
        $this->plantilla_item_id->DbValue = $row['plantilla_item_id'];
        $this->department_id->DbValue = $row['department_id'];
        $this->position_id->DbValue = $row['position_id'];
        $this->item_number->DbValue = $row['item_number'];
        $this->item_description->DbValue = $row['item_description'];
        $this->authorized_count->DbValue = $row['authorized_count'];
        $this->is_active->DbValue = (ConvertToBool($row['is_active']) ? "1" : "0");
        $this->effective_date->DbValue = $row['effective_date'];
        $this->abolition_date->DbValue = $row['abolition_date'];
        $this->annual_budget_allocation->DbValue = $row['annual_budget_allocation'];
        $this->monthly_salary_allocation->DbValue = $row['monthly_salary_allocation'];
        $this->remarks->DbValue = $row['remarks'];
        $this->created_by->DbValue = $row['created_by'];
        $this->approved_by->DbValue = $row['approved_by'];
        $this->created_at->DbValue = $row['created_at'];
        $this->updated_at->DbValue = $row['updated_at'];
    }

    // Delete uploaded files
    public function deleteUploadedFiles($row)
    {
        $this->loadDbValues($row);
    }

    // Record filter WHERE clause
    protected function sqlKeyFilter()
    {
        return "\"plantilla_item_id\" = @plantilla_item_id@";
    }

    // Get Key
    public function getKey($current = false, $keySeparator = null)
    {
        $keys = [];
        $val = $current ? $this->plantilla_item_id->CurrentValue : $this->plantilla_item_id->OldValue;
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
                $this->plantilla_item_id->CurrentValue = $keys[0];
            } else {
                $this->plantilla_item_id->OldValue = $keys[0];
            }
        }
    }

    // Get record filter
    public function getRecordFilter($row = null, $current = false)
    {
        $keyFilter = $this->sqlKeyFilter();
        if (is_array($row)) {
            $val = array_key_exists('plantilla_item_id', $row) ? $row['plantilla_item_id'] : null;
        } else {
            $val = !EmptyValue($this->plantilla_item_id->OldValue) && !$current ? $this->plantilla_item_id->OldValue : $this->plantilla_item_id->CurrentValue;
        }
        if (!is_numeric($val)) {
            return "0=1"; // Invalid key
        }
        if ($val === null) {
            return "0=1"; // Invalid key
        } else {
            $keyFilter = str_replace("@plantilla_item_id@", AdjustSql($val, $this->Dbid), $keyFilter); // Replace key value
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
        return $_SESSION[$name] ?? GetUrl("PlantillaItemsList");
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
            "PlantillaItemsView" => $Language->phrase("View"),
            "PlantillaItemsEdit" => $Language->phrase("Edit"),
            "PlantillaItemsAdd" => $Language->phrase("Add"),
            default => ""
        };
    }

    // Default route URL
    public function getDefaultRouteUrl()
    {
        return "PlantillaItemsList";
    }

    // API page name
    public function getApiPageName($action)
    {
        return match (strtolower($action)) {
            Config("API_VIEW_ACTION") => "PlantillaItemsView",
            Config("API_ADD_ACTION") => "PlantillaItemsAdd",
            Config("API_EDIT_ACTION") => "PlantillaItemsEdit",
            Config("API_DELETE_ACTION") => "PlantillaItemsDelete",
            Config("API_LIST_ACTION") => "PlantillaItemsList",
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
        return "PlantillaItemsList";
    }

    // View URL
    public function getViewUrl($parm = "")
    {
        if ($parm != "") {
            $url = $this->keyUrl("PlantillaItemsView", $parm);
        } else {
            $url = $this->keyUrl("PlantillaItemsView", Config("TABLE_SHOW_DETAIL") . "=");
        }
        return $this->addMasterUrl($url);
    }

    // Add URL
    public function getAddUrl($parm = "")
    {
        if ($parm != "") {
            $url = "PlantillaItemsAdd?" . $parm;
        } else {
            $url = "PlantillaItemsAdd";
        }
        return $this->addMasterUrl($url);
    }

    // Edit URL
    public function getEditUrl($parm = "")
    {
        $url = $this->keyUrl("PlantillaItemsEdit", $parm);
        return $this->addMasterUrl($url);
    }

    // Inline edit URL
    public function getInlineEditUrl()
    {
        $url = $this->keyUrl("PlantillaItemsList", "action=edit");
        return $this->addMasterUrl($url);
    }

    // Copy URL
    public function getCopyUrl($parm = "")
    {
        $url = $this->keyUrl("PlantillaItemsAdd", $parm);
        return $this->addMasterUrl($url);
    }

    // Inline copy URL
    public function getInlineCopyUrl()
    {
        $url = $this->keyUrl("PlantillaItemsList", "action=copy");
        return $this->addMasterUrl($url);
    }

    // Delete URL
    public function getDeleteUrl($parm = "")
    {
        if ($this->UseAjaxActions && ConvertToBool(Param("infinitescroll")) && CurrentPageID() == "list") {
            return $this->keyUrl(GetApiUrl(Config("API_DELETE_ACTION") . "/" . $this->TableVar));
        } else {
            return $this->keyUrl("PlantillaItemsDelete", $parm);
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
        $json .= "\"plantilla_item_id\":" . VarToJson($this->plantilla_item_id->CurrentValue, "number");
        $json = "{" . $json . "}";
        if ($htmlEncode) {
            $json = HtmlEncode($json);
        }
        return $json;
    }

    // Add key value to URL
    public function keyUrl($url, $parm = "")
    {
        if ($this->plantilla_item_id->CurrentValue !== null) {
            $url .= "/" . $this->encodeKeyValue($this->plantilla_item_id->CurrentValue);
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
            if (($keyValue = Param("plantilla_item_id") ?? Route("plantilla_item_id")) !== null) {
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
                $this->plantilla_item_id->CurrentValue = $key;
            } else {
                $this->plantilla_item_id->OldValue = $key;
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
        $this->plantilla_item_id->setDbValue($row['plantilla_item_id']);
        $this->department_id->setDbValue($row['department_id']);
        $this->position_id->setDbValue($row['position_id']);
        $this->item_number->setDbValue($row['item_number']);
        $this->item_description->setDbValue($row['item_description']);
        $this->authorized_count->setDbValue($row['authorized_count']);
        $this->is_active->setDbValue(ConvertToBool($row['is_active']) ? "1" : "0");
        $this->effective_date->setDbValue($row['effective_date']);
        $this->abolition_date->setDbValue($row['abolition_date']);
        $this->annual_budget_allocation->setDbValue($row['annual_budget_allocation']);
        $this->monthly_salary_allocation->setDbValue($row['monthly_salary_allocation']);
        $this->remarks->setDbValue($row['remarks']);
        $this->created_by->setDbValue($row['created_by']);
        $this->approved_by->setDbValue($row['approved_by']);
        $this->created_at->setDbValue($row['created_at']);
        $this->updated_at->setDbValue($row['updated_at']);
    }

    // Render list content
    public function renderListContent($filter)
    {
        global $Response;
        $listPage = "PlantillaItemsList";
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

        // plantilla_item_id

        // department_id

        // position_id

        // item_number

        // item_description

        // authorized_count

        // is_active

        // effective_date

        // abolition_date

        // annual_budget_allocation

        // monthly_salary_allocation

        // remarks

        // created_by

        // approved_by

        // created_at

        // updated_at

        // plantilla_item_id
        $this->plantilla_item_id->ViewValue = $this->plantilla_item_id->CurrentValue;

        // department_id
        $this->department_id->ViewValue = $this->department_id->CurrentValue;
        $this->department_id->ViewValue = FormatNumber($this->department_id->ViewValue, $this->department_id->formatPattern());

        // position_id
        $this->position_id->ViewValue = $this->position_id->CurrentValue;
        $this->position_id->ViewValue = FormatNumber($this->position_id->ViewValue, $this->position_id->formatPattern());

        // item_number
        $this->item_number->ViewValue = $this->item_number->CurrentValue;

        // item_description
        $this->item_description->ViewValue = $this->item_description->CurrentValue;

        // authorized_count
        $this->authorized_count->ViewValue = $this->authorized_count->CurrentValue;
        $this->authorized_count->ViewValue = FormatNumber($this->authorized_count->ViewValue, $this->authorized_count->formatPattern());

        // is_active
        if (ConvertToBool($this->is_active->CurrentValue)) {
            $this->is_active->ViewValue = $this->is_active->tagCaption(1) != "" ? $this->is_active->tagCaption(1) : "Yes";
        } else {
            $this->is_active->ViewValue = $this->is_active->tagCaption(2) != "" ? $this->is_active->tagCaption(2) : "No";
        }

        // effective_date
        $this->effective_date->ViewValue = $this->effective_date->CurrentValue;
        $this->effective_date->ViewValue = FormatDateTime($this->effective_date->ViewValue, $this->effective_date->formatPattern());

        // abolition_date
        $this->abolition_date->ViewValue = $this->abolition_date->CurrentValue;
        $this->abolition_date->ViewValue = FormatDateTime($this->abolition_date->ViewValue, $this->abolition_date->formatPattern());

        // annual_budget_allocation
        $this->annual_budget_allocation->ViewValue = $this->annual_budget_allocation->CurrentValue;
        $this->annual_budget_allocation->ViewValue = FormatNumber($this->annual_budget_allocation->ViewValue, $this->annual_budget_allocation->formatPattern());

        // monthly_salary_allocation
        $this->monthly_salary_allocation->ViewValue = $this->monthly_salary_allocation->CurrentValue;
        $this->monthly_salary_allocation->ViewValue = FormatNumber($this->monthly_salary_allocation->ViewValue, $this->monthly_salary_allocation->formatPattern());

        // remarks
        $this->remarks->ViewValue = $this->remarks->CurrentValue;

        // created_by
        $this->created_by->ViewValue = $this->created_by->CurrentValue;
        $this->created_by->ViewValue = FormatNumber($this->created_by->ViewValue, $this->created_by->formatPattern());

        // approved_by
        $this->approved_by->ViewValue = $this->approved_by->CurrentValue;
        $this->approved_by->ViewValue = FormatNumber($this->approved_by->ViewValue, $this->approved_by->formatPattern());

        // created_at
        $this->created_at->ViewValue = $this->created_at->CurrentValue;
        $this->created_at->ViewValue = FormatDateTime($this->created_at->ViewValue, $this->created_at->formatPattern());

        // updated_at
        $this->updated_at->ViewValue = $this->updated_at->CurrentValue;
        $this->updated_at->ViewValue = FormatDateTime($this->updated_at->ViewValue, $this->updated_at->formatPattern());

        // plantilla_item_id
        $this->plantilla_item_id->HrefValue = "";
        $this->plantilla_item_id->TooltipValue = "";

        // department_id
        $this->department_id->HrefValue = "";
        $this->department_id->TooltipValue = "";

        // position_id
        $this->position_id->HrefValue = "";
        $this->position_id->TooltipValue = "";

        // item_number
        $this->item_number->HrefValue = "";
        $this->item_number->TooltipValue = "";

        // item_description
        $this->item_description->HrefValue = "";
        $this->item_description->TooltipValue = "";

        // authorized_count
        $this->authorized_count->HrefValue = "";
        $this->authorized_count->TooltipValue = "";

        // is_active
        $this->is_active->HrefValue = "";
        $this->is_active->TooltipValue = "";

        // effective_date
        $this->effective_date->HrefValue = "";
        $this->effective_date->TooltipValue = "";

        // abolition_date
        $this->abolition_date->HrefValue = "";
        $this->abolition_date->TooltipValue = "";

        // annual_budget_allocation
        $this->annual_budget_allocation->HrefValue = "";
        $this->annual_budget_allocation->TooltipValue = "";

        // monthly_salary_allocation
        $this->monthly_salary_allocation->HrefValue = "";
        $this->monthly_salary_allocation->TooltipValue = "";

        // remarks
        $this->remarks->HrefValue = "";
        $this->remarks->TooltipValue = "";

        // created_by
        $this->created_by->HrefValue = "";
        $this->created_by->TooltipValue = "";

        // approved_by
        $this->approved_by->HrefValue = "";
        $this->approved_by->TooltipValue = "";

        // created_at
        $this->created_at->HrefValue = "";
        $this->created_at->TooltipValue = "";

        // updated_at
        $this->updated_at->HrefValue = "";
        $this->updated_at->TooltipValue = "";

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

        // plantilla_item_id
        $this->plantilla_item_id->setupEditAttributes();
        $this->plantilla_item_id->EditValue = $this->plantilla_item_id->CurrentValue;

        // department_id
        $this->department_id->setupEditAttributes();
        $this->department_id->EditValue = $this->department_id->CurrentValue;
        $this->department_id->PlaceHolder = RemoveHtml($this->department_id->caption());
        if (strval($this->department_id->EditValue) != "" && is_numeric($this->department_id->EditValue)) {
            $this->department_id->EditValue = FormatNumber($this->department_id->EditValue, null);
        }

        // position_id
        $this->position_id->setupEditAttributes();
        $this->position_id->EditValue = $this->position_id->CurrentValue;
        $this->position_id->PlaceHolder = RemoveHtml($this->position_id->caption());
        if (strval($this->position_id->EditValue) != "" && is_numeric($this->position_id->EditValue)) {
            $this->position_id->EditValue = FormatNumber($this->position_id->EditValue, null);
        }

        // item_number
        $this->item_number->setupEditAttributes();
        if (!$this->item_number->Raw) {
            $this->item_number->CurrentValue = HtmlDecode($this->item_number->CurrentValue);
        }
        $this->item_number->EditValue = $this->item_number->CurrentValue;
        $this->item_number->PlaceHolder = RemoveHtml($this->item_number->caption());

        // item_description
        $this->item_description->setupEditAttributes();
        if (!$this->item_description->Raw) {
            $this->item_description->CurrentValue = HtmlDecode($this->item_description->CurrentValue);
        }
        $this->item_description->EditValue = $this->item_description->CurrentValue;
        $this->item_description->PlaceHolder = RemoveHtml($this->item_description->caption());

        // authorized_count
        $this->authorized_count->setupEditAttributes();
        $this->authorized_count->EditValue = $this->authorized_count->CurrentValue;
        $this->authorized_count->PlaceHolder = RemoveHtml($this->authorized_count->caption());
        if (strval($this->authorized_count->EditValue) != "" && is_numeric($this->authorized_count->EditValue)) {
            $this->authorized_count->EditValue = FormatNumber($this->authorized_count->EditValue, null);
        }

        // is_active
        $this->is_active->EditValue = $this->is_active->options(false);
        $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

        // effective_date
        $this->effective_date->setupEditAttributes();
        $this->effective_date->EditValue = FormatDateTime($this->effective_date->CurrentValue, $this->effective_date->formatPattern());
        $this->effective_date->PlaceHolder = RemoveHtml($this->effective_date->caption());

        // abolition_date
        $this->abolition_date->setupEditAttributes();
        $this->abolition_date->EditValue = FormatDateTime($this->abolition_date->CurrentValue, $this->abolition_date->formatPattern());
        $this->abolition_date->PlaceHolder = RemoveHtml($this->abolition_date->caption());

        // annual_budget_allocation
        $this->annual_budget_allocation->setupEditAttributes();
        $this->annual_budget_allocation->EditValue = $this->annual_budget_allocation->CurrentValue;
        $this->annual_budget_allocation->PlaceHolder = RemoveHtml($this->annual_budget_allocation->caption());
        if (strval($this->annual_budget_allocation->EditValue) != "" && is_numeric($this->annual_budget_allocation->EditValue)) {
            $this->annual_budget_allocation->EditValue = FormatNumber($this->annual_budget_allocation->EditValue, null);
        }

        // monthly_salary_allocation
        $this->monthly_salary_allocation->setupEditAttributes();
        $this->monthly_salary_allocation->EditValue = $this->monthly_salary_allocation->CurrentValue;
        $this->monthly_salary_allocation->PlaceHolder = RemoveHtml($this->monthly_salary_allocation->caption());
        if (strval($this->monthly_salary_allocation->EditValue) != "" && is_numeric($this->monthly_salary_allocation->EditValue)) {
            $this->monthly_salary_allocation->EditValue = FormatNumber($this->monthly_salary_allocation->EditValue, null);
        }

        // remarks
        $this->remarks->setupEditAttributes();
        $this->remarks->EditValue = $this->remarks->CurrentValue;
        $this->remarks->PlaceHolder = RemoveHtml($this->remarks->caption());

        // created_by
        $this->created_by->setupEditAttributes();
        $this->created_by->EditValue = $this->created_by->CurrentValue;
        $this->created_by->PlaceHolder = RemoveHtml($this->created_by->caption());
        if (strval($this->created_by->EditValue) != "" && is_numeric($this->created_by->EditValue)) {
            $this->created_by->EditValue = FormatNumber($this->created_by->EditValue, null);
        }

        // approved_by
        $this->approved_by->setupEditAttributes();
        $this->approved_by->EditValue = $this->approved_by->CurrentValue;
        $this->approved_by->PlaceHolder = RemoveHtml($this->approved_by->caption());
        if (strval($this->approved_by->EditValue) != "" && is_numeric($this->approved_by->EditValue)) {
            $this->approved_by->EditValue = FormatNumber($this->approved_by->EditValue, null);
        }

        // created_at
        $this->created_at->setupEditAttributes();
        $this->created_at->EditValue = FormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        $this->created_at->PlaceHolder = RemoveHtml($this->created_at->caption());

        // updated_at
        $this->updated_at->setupEditAttributes();
        $this->updated_at->EditValue = FormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern());
        $this->updated_at->PlaceHolder = RemoveHtml($this->updated_at->caption());

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
                    $doc->exportCaption($this->plantilla_item_id);
                    $doc->exportCaption($this->department_id);
                    $doc->exportCaption($this->position_id);
                    $doc->exportCaption($this->item_number);
                    $doc->exportCaption($this->item_description);
                    $doc->exportCaption($this->authorized_count);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->effective_date);
                    $doc->exportCaption($this->abolition_date);
                    $doc->exportCaption($this->annual_budget_allocation);
                    $doc->exportCaption($this->monthly_salary_allocation);
                    $doc->exportCaption($this->remarks);
                    $doc->exportCaption($this->created_by);
                    $doc->exportCaption($this->approved_by);
                    $doc->exportCaption($this->created_at);
                    $doc->exportCaption($this->updated_at);
                } else {
                    $doc->exportCaption($this->plantilla_item_id);
                    $doc->exportCaption($this->department_id);
                    $doc->exportCaption($this->position_id);
                    $doc->exportCaption($this->item_number);
                    $doc->exportCaption($this->item_description);
                    $doc->exportCaption($this->authorized_count);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->effective_date);
                    $doc->exportCaption($this->abolition_date);
                    $doc->exportCaption($this->annual_budget_allocation);
                    $doc->exportCaption($this->monthly_salary_allocation);
                    $doc->exportCaption($this->created_by);
                    $doc->exportCaption($this->approved_by);
                    $doc->exportCaption($this->created_at);
                    $doc->exportCaption($this->updated_at);
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
                        $doc->exportField($this->plantilla_item_id);
                        $doc->exportField($this->department_id);
                        $doc->exportField($this->position_id);
                        $doc->exportField($this->item_number);
                        $doc->exportField($this->item_description);
                        $doc->exportField($this->authorized_count);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->effective_date);
                        $doc->exportField($this->abolition_date);
                        $doc->exportField($this->annual_budget_allocation);
                        $doc->exportField($this->monthly_salary_allocation);
                        $doc->exportField($this->remarks);
                        $doc->exportField($this->created_by);
                        $doc->exportField($this->approved_by);
                        $doc->exportField($this->created_at);
                        $doc->exportField($this->updated_at);
                    } else {
                        $doc->exportField($this->plantilla_item_id);
                        $doc->exportField($this->department_id);
                        $doc->exportField($this->position_id);
                        $doc->exportField($this->item_number);
                        $doc->exportField($this->item_description);
                        $doc->exportField($this->authorized_count);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->effective_date);
                        $doc->exportField($this->abolition_date);
                        $doc->exportField($this->annual_budget_allocation);
                        $doc->exportField($this->monthly_salary_allocation);
                        $doc->exportField($this->created_by);
                        $doc->exportField($this->approved_by);
                        $doc->exportField($this->created_at);
                        $doc->exportField($this->updated_at);
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
        return true;
    }

    // Row Inserted event
    public function rowInserted($rsold, $rsnew)
    {
        //Log("Row Inserted");
    }

    // Row Updating event
    public function rowUpdating($rsold, &$rsnew)
    {
        // Enter your code here
        // To cancel, set return value to false
        return true;
    }

    // Row Updated event
    public function rowUpdated($rsold, $rsnew)
    {
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
        return true;
    }

    // Row Deleted event
    public function rowDeleted($rs)
    {
        //Log("Row Deleted");
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
