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
 * Table class for offices
 */
class Offices extends DbTable
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
    public $office_id;
    public $office_name;
    public $office_code;
    public $office_type;
    public $location;
    public $address;
    public $region;
    public $province;
    public $city_municipality;
    public $office_contact_number;
    public $office_email;
    public $office_telephone;
    public $office_fax;
    public $parent_office_id;
    public $is_active;
    public $date_opened;
    public $date_closed;
    public $created_by;
    public $created_at;
    public $modified_by;
    public $modified_at;
    public $remarks;
    public $metadata;
    public $rc_id;
    public $region_code;

    // Page ID
    public $PageID = ""; // To be overridden by subclass

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $CurrentLanguage, $CurrentLocale;

        // Language object
        $Language = Container("app.language");
        $this->TableVar = "offices";
        $this->TableName = 'offices';
        $this->TableType = "TABLE";
        $this->ImportUseTransaction = $this->supportsTransaction() && Config("IMPORT_USE_TRANSACTION");
        $this->UseTransaction = $this->supportsTransaction() && Config("USE_TRANSACTION");

        // Update Table
        $this->UpdateTable = "uac.offices";
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
            'NO' // Edit Tag
        );
        $this->office_id->InputTextType = "text";
        $this->office_id->Raw = true;
        $this->office_id->IsAutoIncrement = true; // Autoincrement field
        $this->office_id->IsPrimaryKey = true; // Primary key field
        $this->office_id->Nullable = false; // NOT NULL field
        $this->office_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->office_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['office_id'] = &$this->office_id;

        // office_name
        $this->office_name = new DbField(
            $this, // Table
            'x_office_name', // Variable name
            'office_name', // Name
            '"office_name"', // Expression
            '"office_name"', // Basic search expression
            200, // Type
            150, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"office_name"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->office_name->InputTextType = "text";
        $this->office_name->Nullable = false; // NOT NULL field
        $this->office_name->Required = true; // Required field
        $this->office_name->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['office_name'] = &$this->office_name;

        // office_code
        $this->office_code = new DbField(
            $this, // Table
            'x_office_code', // Variable name
            'office_code', // Name
            '"office_code"', // Expression
            '"office_code"', // Basic search expression
            200, // Type
            20, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"office_code"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->office_code->InputTextType = "text";
        $this->office_code->Nullable = false; // NOT NULL field
        $this->office_code->Required = true; // Required field
        $this->office_code->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['office_code'] = &$this->office_code;

        // office_type
        $this->office_type = new DbField(
            $this, // Table
            'x_office_type', // Variable name
            'office_type', // Name
            '"office_type"', // Expression
            '"office_type"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"office_type"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->office_type->InputTextType = "text";
        $this->office_type->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['office_type'] = &$this->office_type;

        // location
        $this->location = new DbField(
            $this, // Table
            'x_location', // Variable name
            'location', // Name
            '"location"', // Expression
            '"location"', // Basic search expression
            201, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"location"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXTAREA' // Edit Tag
        );
        $this->location->InputTextType = "text";
        $this->location->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['location'] = &$this->location;

        // address
        $this->address = new DbField(
            $this, // Table
            'x_address', // Variable name
            'address', // Name
            '"address"', // Expression
            '"address"', // Basic search expression
            201, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"address"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXTAREA' // Edit Tag
        );
        $this->address->InputTextType = "text";
        $this->address->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['address'] = &$this->address;

        // region
        $this->region = new DbField(
            $this, // Table
            'x_region', // Variable name
            'region', // Name
            '"region"', // Expression
            '"region"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"region"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->region->InputTextType = "text";
        $this->region->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['region'] = &$this->region;

        // province
        $this->province = new DbField(
            $this, // Table
            'x_province', // Variable name
            'province', // Name
            '"province"', // Expression
            '"province"', // Basic search expression
            200, // Type
            100, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"province"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->province->InputTextType = "text";
        $this->province->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['province'] = &$this->province;

        // city_municipality
        $this->city_municipality = new DbField(
            $this, // Table
            'x_city_municipality', // Variable name
            'city_municipality', // Name
            '"city_municipality"', // Expression
            '"city_municipality"', // Basic search expression
            200, // Type
            100, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"city_municipality"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->city_municipality->InputTextType = "text";
        $this->city_municipality->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['city_municipality'] = &$this->city_municipality;

        // office_contact_number
        $this->office_contact_number = new DbField(
            $this, // Table
            'x_office_contact_number', // Variable name
            'office_contact_number', // Name
            '"office_contact_number"', // Expression
            '"office_contact_number"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"office_contact_number"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->office_contact_number->InputTextType = "text";
        $this->office_contact_number->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['office_contact_number'] = &$this->office_contact_number;

        // office_email
        $this->office_email = new DbField(
            $this, // Table
            'x_office_email', // Variable name
            'office_email', // Name
            '"office_email"', // Expression
            '"office_email"', // Basic search expression
            200, // Type
            100, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"office_email"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->office_email->InputTextType = "text";
        $this->office_email->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['office_email'] = &$this->office_email;

        // office_telephone
        $this->office_telephone = new DbField(
            $this, // Table
            'x_office_telephone', // Variable name
            'office_telephone', // Name
            '"office_telephone"', // Expression
            '"office_telephone"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"office_telephone"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->office_telephone->InputTextType = "text";
        $this->office_telephone->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['office_telephone'] = &$this->office_telephone;

        // office_fax
        $this->office_fax = new DbField(
            $this, // Table
            'x_office_fax', // Variable name
            'office_fax', // Name
            '"office_fax"', // Expression
            '"office_fax"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"office_fax"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->office_fax->InputTextType = "text";
        $this->office_fax->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['office_fax'] = &$this->office_fax;

        // parent_office_id
        $this->parent_office_id = new DbField(
            $this, // Table
            'x_parent_office_id', // Variable name
            'parent_office_id', // Name
            '"parent_office_id"', // Expression
            'CAST("parent_office_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"parent_office_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->parent_office_id->InputTextType = "text";
        $this->parent_office_id->Raw = true;
        $this->parent_office_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->parent_office_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['parent_office_id'] = &$this->parent_office_id;

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
        $this->is_active->Lookup = new Lookup($this->is_active, 'offices', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->is_active->OptionCount = 2;
        $this->is_active->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['is_active'] = &$this->is_active;

        // date_opened
        $this->date_opened = new DbField(
            $this, // Table
            'x_date_opened', // Variable name
            'date_opened', // Name
            '"date_opened"', // Expression
            CastDateFieldForLike("\"date_opened\"", 0, "DB"), // Basic search expression
            133, // Type
            0, // Size
            0, // Date/Time format
            false, // Is upload field
            '"date_opened"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->date_opened->InputTextType = "text";
        $this->date_opened->Raw = true;
        $this->date_opened->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->date_opened->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['date_opened'] = &$this->date_opened;

        // date_closed
        $this->date_closed = new DbField(
            $this, // Table
            'x_date_closed', // Variable name
            'date_closed', // Name
            '"date_closed"', // Expression
            CastDateFieldForLike("\"date_closed\"", 0, "DB"), // Basic search expression
            133, // Type
            0, // Size
            0, // Date/Time format
            false, // Is upload field
            '"date_closed"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->date_closed->InputTextType = "text";
        $this->date_closed->Raw = true;
        $this->date_closed->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->date_closed->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['date_closed'] = &$this->date_closed;

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

        // modified_by
        $this->modified_by = new DbField(
            $this, // Table
            'x_modified_by', // Variable name
            'modified_by', // Name
            '"modified_by"', // Expression
            'CAST("modified_by" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"modified_by"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->modified_by->InputTextType = "text";
        $this->modified_by->Raw = true;
        $this->modified_by->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->modified_by->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['modified_by'] = &$this->modified_by;

        // modified_at
        $this->modified_at = new DbField(
            $this, // Table
            'x_modified_at', // Variable name
            'modified_at', // Name
            '"modified_at"', // Expression
            CastDateFieldForLike("\"modified_at\"", 0, "DB"), // Basic search expression
            135, // Type
            0, // Size
            0, // Date/Time format
            false, // Is upload field
            '"modified_at"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->modified_at->InputTextType = "text";
        $this->modified_at->Raw = true;
        $this->modified_at->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->modified_at->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['modified_at'] = &$this->modified_at;

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

        // metadata
        $this->metadata = new DbField(
            $this, // Table
            'x_metadata', // Variable name
            'metadata', // Name
            '"metadata"', // Expression
            'CAST("metadata" AS varchar(255))', // Basic search expression
            201, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"metadata"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXTAREA' // Edit Tag
        );
        $this->metadata->InputTextType = "text";
        $this->metadata->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['metadata'] = &$this->metadata;

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

        // region_code
        $this->region_code = new DbField(
            $this, // Table
            'x_region_code', // Variable name
            'region_code', // Name
            '"region_code"', // Expression
            '"region_code"', // Basic search expression
            200, // Type
            10, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"region_code"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->region_code->InputTextType = "text";
        $this->region_code->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['region_code'] = &$this->region_code;

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
        return ($this->SqlFrom != "") ? $this->SqlFrom : "uac.offices";
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
                $queryBuilder->getSQL() . " RETURNING office_id",
                $queryBuilder->getParameters(),
                $queryBuilder->getParameterTypes()
            )->fetchOne();
            $this->DbErrorMessage = "";
        } catch (\Exception $e) {
            $result = false;
            $this->DbErrorMessage = $e->getMessage();
        }
        if ($result) {
            $this->office_id->setDbValue($result);
            $rs['office_id'] = $this->office_id->DbValue;
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
            if (!isset($rs['office_id']) && !EmptyValue($this->office_id->CurrentValue)) {
                $rs['office_id'] = $this->office_id->CurrentValue;
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
            if (array_key_exists('office_id', $rs)) {
                AddFilter($where, QuotedName('office_id', $this->Dbid) . '=' . QuotedValue($rs['office_id'], $this->office_id->DataType, $this->Dbid));
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
        $this->office_id->DbValue = $row['office_id'];
        $this->office_name->DbValue = $row['office_name'];
        $this->office_code->DbValue = $row['office_code'];
        $this->office_type->DbValue = $row['office_type'];
        $this->location->DbValue = $row['location'];
        $this->address->DbValue = $row['address'];
        $this->region->DbValue = $row['region'];
        $this->province->DbValue = $row['province'];
        $this->city_municipality->DbValue = $row['city_municipality'];
        $this->office_contact_number->DbValue = $row['office_contact_number'];
        $this->office_email->DbValue = $row['office_email'];
        $this->office_telephone->DbValue = $row['office_telephone'];
        $this->office_fax->DbValue = $row['office_fax'];
        $this->parent_office_id->DbValue = $row['parent_office_id'];
        $this->is_active->DbValue = (ConvertToBool($row['is_active']) ? "1" : "0");
        $this->date_opened->DbValue = $row['date_opened'];
        $this->date_closed->DbValue = $row['date_closed'];
        $this->created_by->DbValue = $row['created_by'];
        $this->created_at->DbValue = $row['created_at'];
        $this->modified_by->DbValue = $row['modified_by'];
        $this->modified_at->DbValue = $row['modified_at'];
        $this->remarks->DbValue = $row['remarks'];
        $this->metadata->DbValue = $row['metadata'];
        $this->rc_id->DbValue = $row['rc_id'];
        $this->region_code->DbValue = $row['region_code'];
    }

    // Delete uploaded files
    public function deleteUploadedFiles($row)
    {
        $this->loadDbValues($row);
    }

    // Record filter WHERE clause
    protected function sqlKeyFilter()
    {
        return "\"office_id\" = @office_id@";
    }

    // Get Key
    public function getKey($current = false, $keySeparator = null)
    {
        $keys = [];
        $val = $current ? $this->office_id->CurrentValue : $this->office_id->OldValue;
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
                $this->office_id->CurrentValue = $keys[0];
            } else {
                $this->office_id->OldValue = $keys[0];
            }
        }
    }

    // Get record filter
    public function getRecordFilter($row = null, $current = false)
    {
        $keyFilter = $this->sqlKeyFilter();
        if (is_array($row)) {
            $val = array_key_exists('office_id', $row) ? $row['office_id'] : null;
        } else {
            $val = !EmptyValue($this->office_id->OldValue) && !$current ? $this->office_id->OldValue : $this->office_id->CurrentValue;
        }
        if (!is_numeric($val)) {
            return "0=1"; // Invalid key
        }
        if ($val === null) {
            return "0=1"; // Invalid key
        } else {
            $keyFilter = str_replace("@office_id@", AdjustSql($val, $this->Dbid), $keyFilter); // Replace key value
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
        return $_SESSION[$name] ?? GetUrl("OfficesList");
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
            "OfficesView" => $Language->phrase("View"),
            "OfficesEdit" => $Language->phrase("Edit"),
            "OfficesAdd" => $Language->phrase("Add"),
            default => ""
        };
    }

    // Default route URL
    public function getDefaultRouteUrl()
    {
        return "OfficesList";
    }

    // API page name
    public function getApiPageName($action)
    {
        return match (strtolower($action)) {
            Config("API_VIEW_ACTION") => "OfficesView",
            Config("API_ADD_ACTION") => "OfficesAdd",
            Config("API_EDIT_ACTION") => "OfficesEdit",
            Config("API_DELETE_ACTION") => "OfficesDelete",
            Config("API_LIST_ACTION") => "OfficesList",
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
        return "OfficesList";
    }

    // View URL
    public function getViewUrl($parm = "")
    {
        if ($parm != "") {
            $url = $this->keyUrl("OfficesView", $parm);
        } else {
            $url = $this->keyUrl("OfficesView", Config("TABLE_SHOW_DETAIL") . "=");
        }
        return $this->addMasterUrl($url);
    }

    // Add URL
    public function getAddUrl($parm = "")
    {
        if ($parm != "") {
            $url = "OfficesAdd?" . $parm;
        } else {
            $url = "OfficesAdd";
        }
        return $this->addMasterUrl($url);
    }

    // Edit URL
    public function getEditUrl($parm = "")
    {
        $url = $this->keyUrl("OfficesEdit", $parm);
        return $this->addMasterUrl($url);
    }

    // Inline edit URL
    public function getInlineEditUrl()
    {
        $url = $this->keyUrl("OfficesList", "action=edit");
        return $this->addMasterUrl($url);
    }

    // Copy URL
    public function getCopyUrl($parm = "")
    {
        $url = $this->keyUrl("OfficesAdd", $parm);
        return $this->addMasterUrl($url);
    }

    // Inline copy URL
    public function getInlineCopyUrl()
    {
        $url = $this->keyUrl("OfficesList", "action=copy");
        return $this->addMasterUrl($url);
    }

    // Delete URL
    public function getDeleteUrl($parm = "")
    {
        if ($this->UseAjaxActions && ConvertToBool(Param("infinitescroll")) && CurrentPageID() == "list") {
            return $this->keyUrl(GetApiUrl(Config("API_DELETE_ACTION") . "/" . $this->TableVar));
        } else {
            return $this->keyUrl("OfficesDelete", $parm);
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
        $json .= "\"office_id\":" . VarToJson($this->office_id->CurrentValue, "number");
        $json = "{" . $json . "}";
        if ($htmlEncode) {
            $json = HtmlEncode($json);
        }
        return $json;
    }

    // Add key value to URL
    public function keyUrl($url, $parm = "")
    {
        if ($this->office_id->CurrentValue !== null) {
            $url .= "/" . $this->encodeKeyValue($this->office_id->CurrentValue);
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
            if (($keyValue = Param("office_id") ?? Route("office_id")) !== null) {
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
                $this->office_id->CurrentValue = $key;
            } else {
                $this->office_id->OldValue = $key;
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
        $this->office_id->setDbValue($row['office_id']);
        $this->office_name->setDbValue($row['office_name']);
        $this->office_code->setDbValue($row['office_code']);
        $this->office_type->setDbValue($row['office_type']);
        $this->location->setDbValue($row['location']);
        $this->address->setDbValue($row['address']);
        $this->region->setDbValue($row['region']);
        $this->province->setDbValue($row['province']);
        $this->city_municipality->setDbValue($row['city_municipality']);
        $this->office_contact_number->setDbValue($row['office_contact_number']);
        $this->office_email->setDbValue($row['office_email']);
        $this->office_telephone->setDbValue($row['office_telephone']);
        $this->office_fax->setDbValue($row['office_fax']);
        $this->parent_office_id->setDbValue($row['parent_office_id']);
        $this->is_active->setDbValue(ConvertToBool($row['is_active']) ? "1" : "0");
        $this->date_opened->setDbValue($row['date_opened']);
        $this->date_closed->setDbValue($row['date_closed']);
        $this->created_by->setDbValue($row['created_by']);
        $this->created_at->setDbValue($row['created_at']);
        $this->modified_by->setDbValue($row['modified_by']);
        $this->modified_at->setDbValue($row['modified_at']);
        $this->remarks->setDbValue($row['remarks']);
        $this->metadata->setDbValue($row['metadata']);
        $this->rc_id->setDbValue($row['rc_id']);
        $this->region_code->setDbValue($row['region_code']);
    }

    // Render list content
    public function renderListContent($filter)
    {
        global $Response;
        $listPage = "OfficesList";
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

        // office_id

        // office_name

        // office_code

        // office_type

        // location

        // address

        // region

        // province

        // city_municipality

        // office_contact_number

        // office_email

        // office_telephone

        // office_fax

        // parent_office_id

        // is_active

        // date_opened

        // date_closed

        // created_by

        // created_at

        // modified_by

        // modified_at

        // remarks

        // metadata

        // rc_id

        // region_code

        // office_id
        $this->office_id->ViewValue = $this->office_id->CurrentValue;

        // office_name
        $this->office_name->ViewValue = $this->office_name->CurrentValue;

        // office_code
        $this->office_code->ViewValue = $this->office_code->CurrentValue;

        // office_type
        $this->office_type->ViewValue = $this->office_type->CurrentValue;

        // location
        $this->location->ViewValue = $this->location->CurrentValue;

        // address
        $this->address->ViewValue = $this->address->CurrentValue;

        // region
        $this->region->ViewValue = $this->region->CurrentValue;

        // province
        $this->province->ViewValue = $this->province->CurrentValue;

        // city_municipality
        $this->city_municipality->ViewValue = $this->city_municipality->CurrentValue;

        // office_contact_number
        $this->office_contact_number->ViewValue = $this->office_contact_number->CurrentValue;

        // office_email
        $this->office_email->ViewValue = $this->office_email->CurrentValue;

        // office_telephone
        $this->office_telephone->ViewValue = $this->office_telephone->CurrentValue;

        // office_fax
        $this->office_fax->ViewValue = $this->office_fax->CurrentValue;

        // parent_office_id
        $this->parent_office_id->ViewValue = $this->parent_office_id->CurrentValue;
        $this->parent_office_id->ViewValue = FormatNumber($this->parent_office_id->ViewValue, $this->parent_office_id->formatPattern());

        // is_active
        if (ConvertToBool($this->is_active->CurrentValue)) {
            $this->is_active->ViewValue = $this->is_active->tagCaption(1) != "" ? $this->is_active->tagCaption(1) : "Yes";
        } else {
            $this->is_active->ViewValue = $this->is_active->tagCaption(2) != "" ? $this->is_active->tagCaption(2) : "No";
        }

        // date_opened
        $this->date_opened->ViewValue = $this->date_opened->CurrentValue;
        $this->date_opened->ViewValue = FormatDateTime($this->date_opened->ViewValue, $this->date_opened->formatPattern());

        // date_closed
        $this->date_closed->ViewValue = $this->date_closed->CurrentValue;
        $this->date_closed->ViewValue = FormatDateTime($this->date_closed->ViewValue, $this->date_closed->formatPattern());

        // created_by
        $this->created_by->ViewValue = $this->created_by->CurrentValue;
        $this->created_by->ViewValue = FormatNumber($this->created_by->ViewValue, $this->created_by->formatPattern());

        // created_at
        $this->created_at->ViewValue = $this->created_at->CurrentValue;
        $this->created_at->ViewValue = FormatDateTime($this->created_at->ViewValue, $this->created_at->formatPattern());

        // modified_by
        $this->modified_by->ViewValue = $this->modified_by->CurrentValue;
        $this->modified_by->ViewValue = FormatNumber($this->modified_by->ViewValue, $this->modified_by->formatPattern());

        // modified_at
        $this->modified_at->ViewValue = $this->modified_at->CurrentValue;
        $this->modified_at->ViewValue = FormatDateTime($this->modified_at->ViewValue, $this->modified_at->formatPattern());

        // remarks
        $this->remarks->ViewValue = $this->remarks->CurrentValue;

        // metadata
        $this->metadata->ViewValue = $this->metadata->CurrentValue;

        // rc_id
        $this->rc_id->ViewValue = $this->rc_id->CurrentValue;
        $this->rc_id->ViewValue = FormatNumber($this->rc_id->ViewValue, $this->rc_id->formatPattern());

        // region_code
        $this->region_code->ViewValue = $this->region_code->CurrentValue;

        // office_id
        $this->office_id->HrefValue = "";
        $this->office_id->TooltipValue = "";

        // office_name
        $this->office_name->HrefValue = "";
        $this->office_name->TooltipValue = "";

        // office_code
        $this->office_code->HrefValue = "";
        $this->office_code->TooltipValue = "";

        // office_type
        $this->office_type->HrefValue = "";
        $this->office_type->TooltipValue = "";

        // location
        $this->location->HrefValue = "";
        $this->location->TooltipValue = "";

        // address
        $this->address->HrefValue = "";
        $this->address->TooltipValue = "";

        // region
        $this->region->HrefValue = "";
        $this->region->TooltipValue = "";

        // province
        $this->province->HrefValue = "";
        $this->province->TooltipValue = "";

        // city_municipality
        $this->city_municipality->HrefValue = "";
        $this->city_municipality->TooltipValue = "";

        // office_contact_number
        $this->office_contact_number->HrefValue = "";
        $this->office_contact_number->TooltipValue = "";

        // office_email
        $this->office_email->HrefValue = "";
        $this->office_email->TooltipValue = "";

        // office_telephone
        $this->office_telephone->HrefValue = "";
        $this->office_telephone->TooltipValue = "";

        // office_fax
        $this->office_fax->HrefValue = "";
        $this->office_fax->TooltipValue = "";

        // parent_office_id
        $this->parent_office_id->HrefValue = "";
        $this->parent_office_id->TooltipValue = "";

        // is_active
        $this->is_active->HrefValue = "";
        $this->is_active->TooltipValue = "";

        // date_opened
        $this->date_opened->HrefValue = "";
        $this->date_opened->TooltipValue = "";

        // date_closed
        $this->date_closed->HrefValue = "";
        $this->date_closed->TooltipValue = "";

        // created_by
        $this->created_by->HrefValue = "";
        $this->created_by->TooltipValue = "";

        // created_at
        $this->created_at->HrefValue = "";
        $this->created_at->TooltipValue = "";

        // modified_by
        $this->modified_by->HrefValue = "";
        $this->modified_by->TooltipValue = "";

        // modified_at
        $this->modified_at->HrefValue = "";
        $this->modified_at->TooltipValue = "";

        // remarks
        $this->remarks->HrefValue = "";
        $this->remarks->TooltipValue = "";

        // metadata
        $this->metadata->HrefValue = "";
        $this->metadata->TooltipValue = "";

        // rc_id
        $this->rc_id->HrefValue = "";
        $this->rc_id->TooltipValue = "";

        // region_code
        $this->region_code->HrefValue = "";
        $this->region_code->TooltipValue = "";

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

        // office_id
        $this->office_id->setupEditAttributes();
        $this->office_id->EditValue = $this->office_id->CurrentValue;

        // office_name
        $this->office_name->setupEditAttributes();
        if (!$this->office_name->Raw) {
            $this->office_name->CurrentValue = HtmlDecode($this->office_name->CurrentValue);
        }
        $this->office_name->EditValue = $this->office_name->CurrentValue;
        $this->office_name->PlaceHolder = RemoveHtml($this->office_name->caption());

        // office_code
        $this->office_code->setupEditAttributes();
        if (!$this->office_code->Raw) {
            $this->office_code->CurrentValue = HtmlDecode($this->office_code->CurrentValue);
        }
        $this->office_code->EditValue = $this->office_code->CurrentValue;
        $this->office_code->PlaceHolder = RemoveHtml($this->office_code->caption());

        // office_type
        $this->office_type->setupEditAttributes();
        if (!$this->office_type->Raw) {
            $this->office_type->CurrentValue = HtmlDecode($this->office_type->CurrentValue);
        }
        $this->office_type->EditValue = $this->office_type->CurrentValue;
        $this->office_type->PlaceHolder = RemoveHtml($this->office_type->caption());

        // location
        $this->location->setupEditAttributes();
        $this->location->EditValue = $this->location->CurrentValue;
        $this->location->PlaceHolder = RemoveHtml($this->location->caption());

        // address
        $this->address->setupEditAttributes();
        $this->address->EditValue = $this->address->CurrentValue;
        $this->address->PlaceHolder = RemoveHtml($this->address->caption());

        // region
        $this->region->setupEditAttributes();
        if (!$this->region->Raw) {
            $this->region->CurrentValue = HtmlDecode($this->region->CurrentValue);
        }
        $this->region->EditValue = $this->region->CurrentValue;
        $this->region->PlaceHolder = RemoveHtml($this->region->caption());

        // province
        $this->province->setupEditAttributes();
        if (!$this->province->Raw) {
            $this->province->CurrentValue = HtmlDecode($this->province->CurrentValue);
        }
        $this->province->EditValue = $this->province->CurrentValue;
        $this->province->PlaceHolder = RemoveHtml($this->province->caption());

        // city_municipality
        $this->city_municipality->setupEditAttributes();
        if (!$this->city_municipality->Raw) {
            $this->city_municipality->CurrentValue = HtmlDecode($this->city_municipality->CurrentValue);
        }
        $this->city_municipality->EditValue = $this->city_municipality->CurrentValue;
        $this->city_municipality->PlaceHolder = RemoveHtml($this->city_municipality->caption());

        // office_contact_number
        $this->office_contact_number->setupEditAttributes();
        if (!$this->office_contact_number->Raw) {
            $this->office_contact_number->CurrentValue = HtmlDecode($this->office_contact_number->CurrentValue);
        }
        $this->office_contact_number->EditValue = $this->office_contact_number->CurrentValue;
        $this->office_contact_number->PlaceHolder = RemoveHtml($this->office_contact_number->caption());

        // office_email
        $this->office_email->setupEditAttributes();
        if (!$this->office_email->Raw) {
            $this->office_email->CurrentValue = HtmlDecode($this->office_email->CurrentValue);
        }
        $this->office_email->EditValue = $this->office_email->CurrentValue;
        $this->office_email->PlaceHolder = RemoveHtml($this->office_email->caption());

        // office_telephone
        $this->office_telephone->setupEditAttributes();
        if (!$this->office_telephone->Raw) {
            $this->office_telephone->CurrentValue = HtmlDecode($this->office_telephone->CurrentValue);
        }
        $this->office_telephone->EditValue = $this->office_telephone->CurrentValue;
        $this->office_telephone->PlaceHolder = RemoveHtml($this->office_telephone->caption());

        // office_fax
        $this->office_fax->setupEditAttributes();
        if (!$this->office_fax->Raw) {
            $this->office_fax->CurrentValue = HtmlDecode($this->office_fax->CurrentValue);
        }
        $this->office_fax->EditValue = $this->office_fax->CurrentValue;
        $this->office_fax->PlaceHolder = RemoveHtml($this->office_fax->caption());

        // parent_office_id
        $this->parent_office_id->setupEditAttributes();
        $this->parent_office_id->EditValue = $this->parent_office_id->CurrentValue;
        $this->parent_office_id->PlaceHolder = RemoveHtml($this->parent_office_id->caption());
        if (strval($this->parent_office_id->EditValue) != "" && is_numeric($this->parent_office_id->EditValue)) {
            $this->parent_office_id->EditValue = FormatNumber($this->parent_office_id->EditValue, null);
        }

        // is_active
        $this->is_active->EditValue = $this->is_active->options(false);
        $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

        // date_opened
        $this->date_opened->setupEditAttributes();
        $this->date_opened->EditValue = FormatDateTime($this->date_opened->CurrentValue, $this->date_opened->formatPattern());
        $this->date_opened->PlaceHolder = RemoveHtml($this->date_opened->caption());

        // date_closed
        $this->date_closed->setupEditAttributes();
        $this->date_closed->EditValue = FormatDateTime($this->date_closed->CurrentValue, $this->date_closed->formatPattern());
        $this->date_closed->PlaceHolder = RemoveHtml($this->date_closed->caption());

        // created_by
        $this->created_by->setupEditAttributes();
        $this->created_by->EditValue = $this->created_by->CurrentValue;
        $this->created_by->PlaceHolder = RemoveHtml($this->created_by->caption());
        if (strval($this->created_by->EditValue) != "" && is_numeric($this->created_by->EditValue)) {
            $this->created_by->EditValue = FormatNumber($this->created_by->EditValue, null);
        }

        // created_at
        $this->created_at->setupEditAttributes();
        $this->created_at->EditValue = FormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        $this->created_at->PlaceHolder = RemoveHtml($this->created_at->caption());

        // modified_by
        $this->modified_by->setupEditAttributes();
        $this->modified_by->EditValue = $this->modified_by->CurrentValue;
        $this->modified_by->PlaceHolder = RemoveHtml($this->modified_by->caption());
        if (strval($this->modified_by->EditValue) != "" && is_numeric($this->modified_by->EditValue)) {
            $this->modified_by->EditValue = FormatNumber($this->modified_by->EditValue, null);
        }

        // modified_at
        $this->modified_at->setupEditAttributes();
        $this->modified_at->EditValue = FormatDateTime($this->modified_at->CurrentValue, $this->modified_at->formatPattern());
        $this->modified_at->PlaceHolder = RemoveHtml($this->modified_at->caption());

        // remarks
        $this->remarks->setupEditAttributes();
        $this->remarks->EditValue = $this->remarks->CurrentValue;
        $this->remarks->PlaceHolder = RemoveHtml($this->remarks->caption());

        // metadata
        $this->metadata->setupEditAttributes();
        $this->metadata->EditValue = $this->metadata->CurrentValue;
        $this->metadata->PlaceHolder = RemoveHtml($this->metadata->caption());

        // rc_id
        $this->rc_id->setupEditAttributes();
        $this->rc_id->EditValue = $this->rc_id->CurrentValue;
        $this->rc_id->PlaceHolder = RemoveHtml($this->rc_id->caption());
        if (strval($this->rc_id->EditValue) != "" && is_numeric($this->rc_id->EditValue)) {
            $this->rc_id->EditValue = FormatNumber($this->rc_id->EditValue, null);
        }

        // region_code
        $this->region_code->setupEditAttributes();
        if (!$this->region_code->Raw) {
            $this->region_code->CurrentValue = HtmlDecode($this->region_code->CurrentValue);
        }
        $this->region_code->EditValue = $this->region_code->CurrentValue;
        $this->region_code->PlaceHolder = RemoveHtml($this->region_code->caption());

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
                    $doc->exportCaption($this->office_id);
                    $doc->exportCaption($this->office_name);
                    $doc->exportCaption($this->office_code);
                    $doc->exportCaption($this->office_type);
                    $doc->exportCaption($this->location);
                    $doc->exportCaption($this->address);
                    $doc->exportCaption($this->region);
                    $doc->exportCaption($this->province);
                    $doc->exportCaption($this->city_municipality);
                    $doc->exportCaption($this->office_contact_number);
                    $doc->exportCaption($this->office_email);
                    $doc->exportCaption($this->office_telephone);
                    $doc->exportCaption($this->office_fax);
                    $doc->exportCaption($this->parent_office_id);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->date_opened);
                    $doc->exportCaption($this->date_closed);
                    $doc->exportCaption($this->created_by);
                    $doc->exportCaption($this->created_at);
                    $doc->exportCaption($this->modified_by);
                    $doc->exportCaption($this->modified_at);
                    $doc->exportCaption($this->remarks);
                    $doc->exportCaption($this->metadata);
                    $doc->exportCaption($this->rc_id);
                    $doc->exportCaption($this->region_code);
                } else {
                    $doc->exportCaption($this->office_id);
                    $doc->exportCaption($this->office_name);
                    $doc->exportCaption($this->office_code);
                    $doc->exportCaption($this->office_type);
                    $doc->exportCaption($this->region);
                    $doc->exportCaption($this->province);
                    $doc->exportCaption($this->city_municipality);
                    $doc->exportCaption($this->office_contact_number);
                    $doc->exportCaption($this->office_email);
                    $doc->exportCaption($this->office_telephone);
                    $doc->exportCaption($this->office_fax);
                    $doc->exportCaption($this->parent_office_id);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->date_opened);
                    $doc->exportCaption($this->date_closed);
                    $doc->exportCaption($this->created_by);
                    $doc->exportCaption($this->created_at);
                    $doc->exportCaption($this->modified_by);
                    $doc->exportCaption($this->modified_at);
                    $doc->exportCaption($this->rc_id);
                    $doc->exportCaption($this->region_code);
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
                        $doc->exportField($this->office_id);
                        $doc->exportField($this->office_name);
                        $doc->exportField($this->office_code);
                        $doc->exportField($this->office_type);
                        $doc->exportField($this->location);
                        $doc->exportField($this->address);
                        $doc->exportField($this->region);
                        $doc->exportField($this->province);
                        $doc->exportField($this->city_municipality);
                        $doc->exportField($this->office_contact_number);
                        $doc->exportField($this->office_email);
                        $doc->exportField($this->office_telephone);
                        $doc->exportField($this->office_fax);
                        $doc->exportField($this->parent_office_id);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->date_opened);
                        $doc->exportField($this->date_closed);
                        $doc->exportField($this->created_by);
                        $doc->exportField($this->created_at);
                        $doc->exportField($this->modified_by);
                        $doc->exportField($this->modified_at);
                        $doc->exportField($this->remarks);
                        $doc->exportField($this->metadata);
                        $doc->exportField($this->rc_id);
                        $doc->exportField($this->region_code);
                    } else {
                        $doc->exportField($this->office_id);
                        $doc->exportField($this->office_name);
                        $doc->exportField($this->office_code);
                        $doc->exportField($this->office_type);
                        $doc->exportField($this->region);
                        $doc->exportField($this->province);
                        $doc->exportField($this->city_municipality);
                        $doc->exportField($this->office_contact_number);
                        $doc->exportField($this->office_email);
                        $doc->exportField($this->office_telephone);
                        $doc->exportField($this->office_fax);
                        $doc->exportField($this->parent_office_id);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->date_opened);
                        $doc->exportField($this->date_closed);
                        $doc->exportField($this->created_by);
                        $doc->exportField($this->created_at);
                        $doc->exportField($this->modified_by);
                        $doc->exportField($this->modified_at);
                        $doc->exportField($this->rc_id);
                        $doc->exportField($this->region_code);
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
