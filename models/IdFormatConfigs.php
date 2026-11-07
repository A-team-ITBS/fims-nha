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
 * Table class for id_format_configs
 */
class IdFormatConfigs extends DbTable
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
    public $config_id;
    public $module_code;
    public $document_type;
    public $config_name;
    public $description;
    public $use_prefix;
    public $prefix_value;
    public $use_year;
    public $year_format;
    public $use_fiscal_year;
    public $use_month;
    public $month_format;
    public $use_department;
    public $department_field;
    public $sequence_length;
    public $sequence_start;
    public $use_suffix;
    public $suffix_value;
    public $separator;
    public $reset_strategy;
    public $validation_regex;
    public $example_output;
    public $is_active;
    public $effective_date;
    public $obsolete_date;
    public $created_by;
    public $created_at;
    public $updated_at;
    public $partition_by_month;

    // Page ID
    public $PageID = ""; // To be overridden by subclass

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $CurrentLanguage, $CurrentLocale;

        // Language object
        $Language = Container("app.language");
        $this->TableVar = "id_format_configs";
        $this->TableName = 'id_format_configs';
        $this->TableType = "TABLE";
        $this->ImportUseTransaction = $this->supportsTransaction() && Config("IMPORT_USE_TRANSACTION");
        $this->UseTransaction = $this->supportsTransaction() && Config("USE_TRANSACTION");

        // Update Table
        $this->UpdateTable = "uac.id_format_configs";
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

        // config_id
        $this->config_id = new DbField(
            $this, // Table
            'x_config_id', // Variable name
            'config_id', // Name
            '"config_id"', // Expression
            'CAST("config_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"config_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'NO' // Edit Tag
        );
        $this->config_id->InputTextType = "text";
        $this->config_id->Raw = true;
        $this->config_id->IsAutoIncrement = true; // Autoincrement field
        $this->config_id->IsPrimaryKey = true; // Primary key field
        $this->config_id->Nullable = false; // NOT NULL field
        $this->config_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->config_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['config_id'] = &$this->config_id;

        // module_code
        $this->module_code = new DbField(
            $this, // Table
            'x_module_code', // Variable name
            'module_code', // Name
            '"module_code"', // Expression
            '"module_code"', // Basic search expression
            200, // Type
            20, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"module_code"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->module_code->InputTextType = "text";
        $this->module_code->Nullable = false; // NOT NULL field
        $this->module_code->Required = true; // Required field
        $this->module_code->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['module_code'] = &$this->module_code;

        // document_type
        $this->document_type = new DbField(
            $this, // Table
            'x_document_type', // Variable name
            'document_type', // Name
            '"document_type"', // Expression
            '"document_type"', // Basic search expression
            200, // Type
            100, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"document_type"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->document_type->InputTextType = "text";
        $this->document_type->Nullable = false; // NOT NULL field
        $this->document_type->Required = true; // Required field
        $this->document_type->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['document_type'] = &$this->document_type;

        // config_name
        $this->config_name = new DbField(
            $this, // Table
            'x_config_name', // Variable name
            'config_name', // Name
            '"config_name"', // Expression
            '"config_name"', // Basic search expression
            200, // Type
            200, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"config_name"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->config_name->InputTextType = "text";
        $this->config_name->Nullable = false; // NOT NULL field
        $this->config_name->Required = true; // Required field
        $this->config_name->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['config_name'] = &$this->config_name;

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

        // use_prefix
        $this->use_prefix = new DbField(
            $this, // Table
            'x_use_prefix', // Variable name
            'use_prefix', // Name
            '"use_prefix"', // Expression
            'CAST("use_prefix" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"use_prefix"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->use_prefix->InputTextType = "text";
        $this->use_prefix->Raw = true;
        $this->use_prefix->setDataType(DataType::BOOLEAN);
        $this->use_prefix->Lookup = new Lookup($this->use_prefix, 'id_format_configs', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->use_prefix->OptionCount = 2;
        $this->use_prefix->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['use_prefix'] = &$this->use_prefix;

        // prefix_value
        $this->prefix_value = new DbField(
            $this, // Table
            'x_prefix_value', // Variable name
            'prefix_value', // Name
            '"prefix_value"', // Expression
            '"prefix_value"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"prefix_value"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->prefix_value->InputTextType = "text";
        $this->prefix_value->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['prefix_value'] = &$this->prefix_value;

        // use_year
        $this->use_year = new DbField(
            $this, // Table
            'x_use_year', // Variable name
            'use_year', // Name
            '"use_year"', // Expression
            'CAST("use_year" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"use_year"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->use_year->InputTextType = "text";
        $this->use_year->Raw = true;
        $this->use_year->setDataType(DataType::BOOLEAN);
        $this->use_year->Lookup = new Lookup($this->use_year, 'id_format_configs', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->use_year->OptionCount = 2;
        $this->use_year->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['use_year'] = &$this->use_year;

        // year_format
        $this->year_format = new DbField(
            $this, // Table
            'x_year_format', // Variable name
            'year_format', // Name
            '"year_format"', // Expression
            '"year_format"', // Basic search expression
            200, // Type
            20, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"year_format"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->year_format->InputTextType = "text";
        $this->year_format->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['year_format'] = &$this->year_format;

        // use_fiscal_year
        $this->use_fiscal_year = new DbField(
            $this, // Table
            'x_use_fiscal_year', // Variable name
            'use_fiscal_year', // Name
            '"use_fiscal_year"', // Expression
            'CAST("use_fiscal_year" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"use_fiscal_year"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->use_fiscal_year->InputTextType = "text";
        $this->use_fiscal_year->Raw = true;
        $this->use_fiscal_year->setDataType(DataType::BOOLEAN);
        $this->use_fiscal_year->Lookup = new Lookup($this->use_fiscal_year, 'id_format_configs', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->use_fiscal_year->OptionCount = 2;
        $this->use_fiscal_year->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['use_fiscal_year'] = &$this->use_fiscal_year;

        // use_month
        $this->use_month = new DbField(
            $this, // Table
            'x_use_month', // Variable name
            'use_month', // Name
            '"use_month"', // Expression
            'CAST("use_month" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"use_month"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->use_month->InputTextType = "text";
        $this->use_month->Raw = true;
        $this->use_month->setDataType(DataType::BOOLEAN);
        $this->use_month->Lookup = new Lookup($this->use_month, 'id_format_configs', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->use_month->OptionCount = 2;
        $this->use_month->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['use_month'] = &$this->use_month;

        // month_format
        $this->month_format = new DbField(
            $this, // Table
            'x_month_format', // Variable name
            'month_format', // Name
            '"month_format"', // Expression
            '"month_format"', // Basic search expression
            200, // Type
            20, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"month_format"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->month_format->InputTextType = "text";
        $this->month_format->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['month_format'] = &$this->month_format;

        // use_department
        $this->use_department = new DbField(
            $this, // Table
            'x_use_department', // Variable name
            'use_department', // Name
            '"use_department"', // Expression
            'CAST("use_department" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"use_department"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->use_department->InputTextType = "text";
        $this->use_department->Raw = true;
        $this->use_department->setDataType(DataType::BOOLEAN);
        $this->use_department->Lookup = new Lookup($this->use_department, 'id_format_configs', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->use_department->OptionCount = 2;
        $this->use_department->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['use_department'] = &$this->use_department;

        // department_field
        $this->department_field = new DbField(
            $this, // Table
            'x_department_field', // Variable name
            'department_field', // Name
            '"department_field"', // Expression
            '"department_field"', // Basic search expression
            200, // Type
            100, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"department_field"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->department_field->InputTextType = "text";
        $this->department_field->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['department_field'] = &$this->department_field;

        // sequence_length
        $this->sequence_length = new DbField(
            $this, // Table
            'x_sequence_length', // Variable name
            'sequence_length', // Name
            '"sequence_length"', // Expression
            'CAST("sequence_length" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"sequence_length"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->sequence_length->addMethod("getDefault", fn() => 4);
        $this->sequence_length->InputTextType = "text";
        $this->sequence_length->Raw = true;
        $this->sequence_length->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->sequence_length->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['sequence_length'] = &$this->sequence_length;

        // sequence_start
        $this->sequence_start = new DbField(
            $this, // Table
            'x_sequence_start', // Variable name
            'sequence_start', // Name
            '"sequence_start"', // Expression
            'CAST("sequence_start" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"sequence_start"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->sequence_start->addMethod("getDefault", fn() => 1);
        $this->sequence_start->InputTextType = "text";
        $this->sequence_start->Raw = true;
        $this->sequence_start->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->sequence_start->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['sequence_start'] = &$this->sequence_start;

        // use_suffix
        $this->use_suffix = new DbField(
            $this, // Table
            'x_use_suffix', // Variable name
            'use_suffix', // Name
            '"use_suffix"', // Expression
            'CAST("use_suffix" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"use_suffix"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->use_suffix->InputTextType = "text";
        $this->use_suffix->Raw = true;
        $this->use_suffix->setDataType(DataType::BOOLEAN);
        $this->use_suffix->Lookup = new Lookup($this->use_suffix, 'id_format_configs', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->use_suffix->OptionCount = 2;
        $this->use_suffix->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['use_suffix'] = &$this->use_suffix;

        // suffix_value
        $this->suffix_value = new DbField(
            $this, // Table
            'x_suffix_value', // Variable name
            'suffix_value', // Name
            '"suffix_value"', // Expression
            '"suffix_value"', // Basic search expression
            200, // Type
            50, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"suffix_value"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->suffix_value->InputTextType = "text";
        $this->suffix_value->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['suffix_value'] = &$this->suffix_value;

        // separator
        $this->separator = new DbField(
            $this, // Table
            'x_separator', // Variable name
            'separator', // Name
            '"separator"', // Expression
            '"separator"', // Basic search expression
            200, // Type
            10, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"separator"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->separator->addMethod("getDefault", fn() => "-");
        $this->separator->InputTextType = "text";
        $this->separator->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['separator'] = &$this->separator;

        // reset_strategy
        $this->reset_strategy = new DbField(
            $this, // Table
            'x_reset_strategy', // Variable name
            'reset_strategy', // Name
            '"reset_strategy"', // Expression
            '"reset_strategy"', // Basic search expression
            200, // Type
            20, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"reset_strategy"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->reset_strategy->addMethod("getDefault", fn() => "yearly");
        $this->reset_strategy->InputTextType = "text";
        $this->reset_strategy->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['reset_strategy'] = &$this->reset_strategy;

        // validation_regex
        $this->validation_regex = new DbField(
            $this, // Table
            'x_validation_regex', // Variable name
            'validation_regex', // Name
            '"validation_regex"', // Expression
            '"validation_regex"', // Basic search expression
            200, // Type
            500, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"validation_regex"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->validation_regex->InputTextType = "text";
        $this->validation_regex->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['validation_regex'] = &$this->validation_regex;

        // example_output
        $this->example_output = new DbField(
            $this, // Table
            'x_example_output', // Variable name
            'example_output', // Name
            '"example_output"', // Expression
            '"example_output"', // Basic search expression
            200, // Type
            200, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"example_output"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->example_output->InputTextType = "text";
        $this->example_output->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['example_output'] = &$this->example_output;

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
        $this->is_active->Lookup = new Lookup($this->is_active, 'id_format_configs', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
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
        $this->effective_date->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->effective_date->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['effective_date'] = &$this->effective_date;

        // obsolete_date
        $this->obsolete_date = new DbField(
            $this, // Table
            'x_obsolete_date', // Variable name
            'obsolete_date', // Name
            '"obsolete_date"', // Expression
            CastDateFieldForLike("\"obsolete_date\"", 0, "DB"), // Basic search expression
            133, // Type
            0, // Size
            0, // Date/Time format
            false, // Is upload field
            '"obsolete_date"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->obsolete_date->InputTextType = "text";
        $this->obsolete_date->Raw = true;
        $this->obsolete_date->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->obsolete_date->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['obsolete_date'] = &$this->obsolete_date;

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

        // partition_by_month
        $this->partition_by_month = new DbField(
            $this, // Table
            'x_partition_by_month', // Variable name
            'partition_by_month', // Name
            '"partition_by_month"', // Expression
            'CAST("partition_by_month" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"partition_by_month"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->partition_by_month->InputTextType = "text";
        $this->partition_by_month->Raw = true;
        $this->partition_by_month->Nullable = false; // NOT NULL field
        $this->partition_by_month->setDataType(DataType::BOOLEAN);
        $this->partition_by_month->Lookup = new Lookup($this->partition_by_month, 'id_format_configs', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->partition_by_month->OptionCount = 2;
        $this->partition_by_month->SearchOperators = ["=", "<>"];
        $this->Fields['partition_by_month'] = &$this->partition_by_month;

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
        return ($this->SqlFrom != "") ? $this->SqlFrom : "uac.id_format_configs";
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
                $queryBuilder->getSQL() . " RETURNING config_id",
                $queryBuilder->getParameters(),
                $queryBuilder->getParameterTypes()
            )->fetchOne();
            $this->DbErrorMessage = "";
        } catch (\Exception $e) {
            $result = false;
            $this->DbErrorMessage = $e->getMessage();
        }
        if ($result) {
            $this->config_id->setDbValue($result);
            $rs['config_id'] = $this->config_id->DbValue;
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
            if (!isset($rs['config_id']) && !EmptyValue($this->config_id->CurrentValue)) {
                $rs['config_id'] = $this->config_id->CurrentValue;
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
            if (array_key_exists('config_id', $rs)) {
                AddFilter($where, QuotedName('config_id', $this->Dbid) . '=' . QuotedValue($rs['config_id'], $this->config_id->DataType, $this->Dbid));
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
        $this->config_id->DbValue = $row['config_id'];
        $this->module_code->DbValue = $row['module_code'];
        $this->document_type->DbValue = $row['document_type'];
        $this->config_name->DbValue = $row['config_name'];
        $this->description->DbValue = $row['description'];
        $this->use_prefix->DbValue = (ConvertToBool($row['use_prefix']) ? "1" : "0");
        $this->prefix_value->DbValue = $row['prefix_value'];
        $this->use_year->DbValue = (ConvertToBool($row['use_year']) ? "1" : "0");
        $this->year_format->DbValue = $row['year_format'];
        $this->use_fiscal_year->DbValue = (ConvertToBool($row['use_fiscal_year']) ? "1" : "0");
        $this->use_month->DbValue = (ConvertToBool($row['use_month']) ? "1" : "0");
        $this->month_format->DbValue = $row['month_format'];
        $this->use_department->DbValue = (ConvertToBool($row['use_department']) ? "1" : "0");
        $this->department_field->DbValue = $row['department_field'];
        $this->sequence_length->DbValue = $row['sequence_length'];
        $this->sequence_start->DbValue = $row['sequence_start'];
        $this->use_suffix->DbValue = (ConvertToBool($row['use_suffix']) ? "1" : "0");
        $this->suffix_value->DbValue = $row['suffix_value'];
        $this->separator->DbValue = $row['separator'];
        $this->reset_strategy->DbValue = $row['reset_strategy'];
        $this->validation_regex->DbValue = $row['validation_regex'];
        $this->example_output->DbValue = $row['example_output'];
        $this->is_active->DbValue = (ConvertToBool($row['is_active']) ? "1" : "0");
        $this->effective_date->DbValue = $row['effective_date'];
        $this->obsolete_date->DbValue = $row['obsolete_date'];
        $this->created_by->DbValue = $row['created_by'];
        $this->created_at->DbValue = $row['created_at'];
        $this->updated_at->DbValue = $row['updated_at'];
        $this->partition_by_month->DbValue = (ConvertToBool($row['partition_by_month']) ? "1" : "0");
    }

    // Delete uploaded files
    public function deleteUploadedFiles($row)
    {
        $this->loadDbValues($row);
    }

    // Record filter WHERE clause
    protected function sqlKeyFilter()
    {
        return "\"config_id\" = @config_id@";
    }

    // Get Key
    public function getKey($current = false, $keySeparator = null)
    {
        $keys = [];
        $val = $current ? $this->config_id->CurrentValue : $this->config_id->OldValue;
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
                $this->config_id->CurrentValue = $keys[0];
            } else {
                $this->config_id->OldValue = $keys[0];
            }
        }
    }

    // Get record filter
    public function getRecordFilter($row = null, $current = false)
    {
        $keyFilter = $this->sqlKeyFilter();
        if (is_array($row)) {
            $val = array_key_exists('config_id', $row) ? $row['config_id'] : null;
        } else {
            $val = !EmptyValue($this->config_id->OldValue) && !$current ? $this->config_id->OldValue : $this->config_id->CurrentValue;
        }
        if (!is_numeric($val)) {
            return "0=1"; // Invalid key
        }
        if ($val === null) {
            return "0=1"; // Invalid key
        } else {
            $keyFilter = str_replace("@config_id@", AdjustSql($val, $this->Dbid), $keyFilter); // Replace key value
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
        return $_SESSION[$name] ?? GetUrl("IdFormatConfigsList");
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
            "IdFormatConfigsView" => $Language->phrase("View"),
            "IdFormatConfigsEdit" => $Language->phrase("Edit"),
            "IdFormatConfigsAdd" => $Language->phrase("Add"),
            default => ""
        };
    }

    // Default route URL
    public function getDefaultRouteUrl()
    {
        return "IdFormatConfigsList";
    }

    // API page name
    public function getApiPageName($action)
    {
        return match (strtolower($action)) {
            Config("API_VIEW_ACTION") => "IdFormatConfigsView",
            Config("API_ADD_ACTION") => "IdFormatConfigsAdd",
            Config("API_EDIT_ACTION") => "IdFormatConfigsEdit",
            Config("API_DELETE_ACTION") => "IdFormatConfigsDelete",
            Config("API_LIST_ACTION") => "IdFormatConfigsList",
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
        return "IdFormatConfigsList";
    }

    // View URL
    public function getViewUrl($parm = "")
    {
        if ($parm != "") {
            $url = $this->keyUrl("IdFormatConfigsView", $parm);
        } else {
            $url = $this->keyUrl("IdFormatConfigsView", Config("TABLE_SHOW_DETAIL") . "=");
        }
        return $this->addMasterUrl($url);
    }

    // Add URL
    public function getAddUrl($parm = "")
    {
        if ($parm != "") {
            $url = "IdFormatConfigsAdd?" . $parm;
        } else {
            $url = "IdFormatConfigsAdd";
        }
        return $this->addMasterUrl($url);
    }

    // Edit URL
    public function getEditUrl($parm = "")
    {
        $url = $this->keyUrl("IdFormatConfigsEdit", $parm);
        return $this->addMasterUrl($url);
    }

    // Inline edit URL
    public function getInlineEditUrl()
    {
        $url = $this->keyUrl("IdFormatConfigsList", "action=edit");
        return $this->addMasterUrl($url);
    }

    // Copy URL
    public function getCopyUrl($parm = "")
    {
        $url = $this->keyUrl("IdFormatConfigsAdd", $parm);
        return $this->addMasterUrl($url);
    }

    // Inline copy URL
    public function getInlineCopyUrl()
    {
        $url = $this->keyUrl("IdFormatConfigsList", "action=copy");
        return $this->addMasterUrl($url);
    }

    // Delete URL
    public function getDeleteUrl($parm = "")
    {
        if ($this->UseAjaxActions && ConvertToBool(Param("infinitescroll")) && CurrentPageID() == "list") {
            return $this->keyUrl(GetApiUrl(Config("API_DELETE_ACTION") . "/" . $this->TableVar));
        } else {
            return $this->keyUrl("IdFormatConfigsDelete", $parm);
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
        $json .= "\"config_id\":" . VarToJson($this->config_id->CurrentValue, "number");
        $json = "{" . $json . "}";
        if ($htmlEncode) {
            $json = HtmlEncode($json);
        }
        return $json;
    }

    // Add key value to URL
    public function keyUrl($url, $parm = "")
    {
        if ($this->config_id->CurrentValue !== null) {
            $url .= "/" . $this->encodeKeyValue($this->config_id->CurrentValue);
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
            if (($keyValue = Param("config_id") ?? Route("config_id")) !== null) {
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
                $this->config_id->CurrentValue = $key;
            } else {
                $this->config_id->OldValue = $key;
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
        $this->config_id->setDbValue($row['config_id']);
        $this->module_code->setDbValue($row['module_code']);
        $this->document_type->setDbValue($row['document_type']);
        $this->config_name->setDbValue($row['config_name']);
        $this->description->setDbValue($row['description']);
        $this->use_prefix->setDbValue(ConvertToBool($row['use_prefix']) ? "1" : "0");
        $this->prefix_value->setDbValue($row['prefix_value']);
        $this->use_year->setDbValue(ConvertToBool($row['use_year']) ? "1" : "0");
        $this->year_format->setDbValue($row['year_format']);
        $this->use_fiscal_year->setDbValue(ConvertToBool($row['use_fiscal_year']) ? "1" : "0");
        $this->use_month->setDbValue(ConvertToBool($row['use_month']) ? "1" : "0");
        $this->month_format->setDbValue($row['month_format']);
        $this->use_department->setDbValue(ConvertToBool($row['use_department']) ? "1" : "0");
        $this->department_field->setDbValue($row['department_field']);
        $this->sequence_length->setDbValue($row['sequence_length']);
        $this->sequence_start->setDbValue($row['sequence_start']);
        $this->use_suffix->setDbValue(ConvertToBool($row['use_suffix']) ? "1" : "0");
        $this->suffix_value->setDbValue($row['suffix_value']);
        $this->separator->setDbValue($row['separator']);
        $this->reset_strategy->setDbValue($row['reset_strategy']);
        $this->validation_regex->setDbValue($row['validation_regex']);
        $this->example_output->setDbValue($row['example_output']);
        $this->is_active->setDbValue(ConvertToBool($row['is_active']) ? "1" : "0");
        $this->effective_date->setDbValue($row['effective_date']);
        $this->obsolete_date->setDbValue($row['obsolete_date']);
        $this->created_by->setDbValue($row['created_by']);
        $this->created_at->setDbValue($row['created_at']);
        $this->updated_at->setDbValue($row['updated_at']);
        $this->partition_by_month->setDbValue(ConvertToBool($row['partition_by_month']) ? "1" : "0");
    }

    // Render list content
    public function renderListContent($filter)
    {
        global $Response;
        $listPage = "IdFormatConfigsList";
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

        // config_id

        // module_code

        // document_type

        // config_name

        // description

        // use_prefix

        // prefix_value

        // use_year

        // year_format

        // use_fiscal_year

        // use_month

        // month_format

        // use_department

        // department_field

        // sequence_length

        // sequence_start

        // use_suffix

        // suffix_value

        // separator

        // reset_strategy

        // validation_regex

        // example_output

        // is_active

        // effective_date

        // obsolete_date

        // created_by

        // created_at

        // updated_at

        // partition_by_month

        // config_id
        $this->config_id->ViewValue = $this->config_id->CurrentValue;

        // module_code
        $this->module_code->ViewValue = $this->module_code->CurrentValue;

        // document_type
        $this->document_type->ViewValue = $this->document_type->CurrentValue;

        // config_name
        $this->config_name->ViewValue = $this->config_name->CurrentValue;

        // description
        $this->description->ViewValue = $this->description->CurrentValue;

        // use_prefix
        if (ConvertToBool($this->use_prefix->CurrentValue)) {
            $this->use_prefix->ViewValue = $this->use_prefix->tagCaption(1) != "" ? $this->use_prefix->tagCaption(1) : "Yes";
        } else {
            $this->use_prefix->ViewValue = $this->use_prefix->tagCaption(2) != "" ? $this->use_prefix->tagCaption(2) : "No";
        }

        // prefix_value
        $this->prefix_value->ViewValue = $this->prefix_value->CurrentValue;

        // use_year
        if (ConvertToBool($this->use_year->CurrentValue)) {
            $this->use_year->ViewValue = $this->use_year->tagCaption(1) != "" ? $this->use_year->tagCaption(1) : "Yes";
        } else {
            $this->use_year->ViewValue = $this->use_year->tagCaption(2) != "" ? $this->use_year->tagCaption(2) : "No";
        }

        // year_format
        $this->year_format->ViewValue = $this->year_format->CurrentValue;

        // use_fiscal_year
        if (ConvertToBool($this->use_fiscal_year->CurrentValue)) {
            $this->use_fiscal_year->ViewValue = $this->use_fiscal_year->tagCaption(1) != "" ? $this->use_fiscal_year->tagCaption(1) : "Yes";
        } else {
            $this->use_fiscal_year->ViewValue = $this->use_fiscal_year->tagCaption(2) != "" ? $this->use_fiscal_year->tagCaption(2) : "No";
        }

        // use_month
        if (ConvertToBool($this->use_month->CurrentValue)) {
            $this->use_month->ViewValue = $this->use_month->tagCaption(1) != "" ? $this->use_month->tagCaption(1) : "Yes";
        } else {
            $this->use_month->ViewValue = $this->use_month->tagCaption(2) != "" ? $this->use_month->tagCaption(2) : "No";
        }

        // month_format
        $this->month_format->ViewValue = $this->month_format->CurrentValue;

        // use_department
        if (ConvertToBool($this->use_department->CurrentValue)) {
            $this->use_department->ViewValue = $this->use_department->tagCaption(1) != "" ? $this->use_department->tagCaption(1) : "Yes";
        } else {
            $this->use_department->ViewValue = $this->use_department->tagCaption(2) != "" ? $this->use_department->tagCaption(2) : "No";
        }

        // department_field
        $this->department_field->ViewValue = $this->department_field->CurrentValue;

        // sequence_length
        $this->sequence_length->ViewValue = $this->sequence_length->CurrentValue;
        $this->sequence_length->ViewValue = FormatNumber($this->sequence_length->ViewValue, $this->sequence_length->formatPattern());

        // sequence_start
        $this->sequence_start->ViewValue = $this->sequence_start->CurrentValue;
        $this->sequence_start->ViewValue = FormatNumber($this->sequence_start->ViewValue, $this->sequence_start->formatPattern());

        // use_suffix
        if (ConvertToBool($this->use_suffix->CurrentValue)) {
            $this->use_suffix->ViewValue = $this->use_suffix->tagCaption(1) != "" ? $this->use_suffix->tagCaption(1) : "Yes";
        } else {
            $this->use_suffix->ViewValue = $this->use_suffix->tagCaption(2) != "" ? $this->use_suffix->tagCaption(2) : "No";
        }

        // suffix_value
        $this->suffix_value->ViewValue = $this->suffix_value->CurrentValue;

        // separator
        $this->separator->ViewValue = $this->separator->CurrentValue;

        // reset_strategy
        $this->reset_strategy->ViewValue = $this->reset_strategy->CurrentValue;

        // validation_regex
        $this->validation_regex->ViewValue = $this->validation_regex->CurrentValue;

        // example_output
        $this->example_output->ViewValue = $this->example_output->CurrentValue;

        // is_active
        if (ConvertToBool($this->is_active->CurrentValue)) {
            $this->is_active->ViewValue = $this->is_active->tagCaption(1) != "" ? $this->is_active->tagCaption(1) : "Yes";
        } else {
            $this->is_active->ViewValue = $this->is_active->tagCaption(2) != "" ? $this->is_active->tagCaption(2) : "No";
        }

        // effective_date
        $this->effective_date->ViewValue = $this->effective_date->CurrentValue;
        $this->effective_date->ViewValue = FormatDateTime($this->effective_date->ViewValue, $this->effective_date->formatPattern());

        // obsolete_date
        $this->obsolete_date->ViewValue = $this->obsolete_date->CurrentValue;
        $this->obsolete_date->ViewValue = FormatDateTime($this->obsolete_date->ViewValue, $this->obsolete_date->formatPattern());

        // created_by
        $this->created_by->ViewValue = $this->created_by->CurrentValue;
        $this->created_by->ViewValue = FormatNumber($this->created_by->ViewValue, $this->created_by->formatPattern());

        // created_at
        $this->created_at->ViewValue = $this->created_at->CurrentValue;
        $this->created_at->ViewValue = FormatDateTime($this->created_at->ViewValue, $this->created_at->formatPattern());

        // updated_at
        $this->updated_at->ViewValue = $this->updated_at->CurrentValue;
        $this->updated_at->ViewValue = FormatDateTime($this->updated_at->ViewValue, $this->updated_at->formatPattern());

        // partition_by_month
        if (ConvertToBool($this->partition_by_month->CurrentValue)) {
            $this->partition_by_month->ViewValue = $this->partition_by_month->tagCaption(1) != "" ? $this->partition_by_month->tagCaption(1) : "Yes";
        } else {
            $this->partition_by_month->ViewValue = $this->partition_by_month->tagCaption(2) != "" ? $this->partition_by_month->tagCaption(2) : "No";
        }

        // config_id
        $this->config_id->HrefValue = "";
        $this->config_id->TooltipValue = "";

        // module_code
        $this->module_code->HrefValue = "";
        $this->module_code->TooltipValue = "";

        // document_type
        $this->document_type->HrefValue = "";
        $this->document_type->TooltipValue = "";

        // config_name
        $this->config_name->HrefValue = "";
        $this->config_name->TooltipValue = "";

        // description
        $this->description->HrefValue = "";
        $this->description->TooltipValue = "";

        // use_prefix
        $this->use_prefix->HrefValue = "";
        $this->use_prefix->TooltipValue = "";

        // prefix_value
        $this->prefix_value->HrefValue = "";
        $this->prefix_value->TooltipValue = "";

        // use_year
        $this->use_year->HrefValue = "";
        $this->use_year->TooltipValue = "";

        // year_format
        $this->year_format->HrefValue = "";
        $this->year_format->TooltipValue = "";

        // use_fiscal_year
        $this->use_fiscal_year->HrefValue = "";
        $this->use_fiscal_year->TooltipValue = "";

        // use_month
        $this->use_month->HrefValue = "";
        $this->use_month->TooltipValue = "";

        // month_format
        $this->month_format->HrefValue = "";
        $this->month_format->TooltipValue = "";

        // use_department
        $this->use_department->HrefValue = "";
        $this->use_department->TooltipValue = "";

        // department_field
        $this->department_field->HrefValue = "";
        $this->department_field->TooltipValue = "";

        // sequence_length
        $this->sequence_length->HrefValue = "";
        $this->sequence_length->TooltipValue = "";

        // sequence_start
        $this->sequence_start->HrefValue = "";
        $this->sequence_start->TooltipValue = "";

        // use_suffix
        $this->use_suffix->HrefValue = "";
        $this->use_suffix->TooltipValue = "";

        // suffix_value
        $this->suffix_value->HrefValue = "";
        $this->suffix_value->TooltipValue = "";

        // separator
        $this->separator->HrefValue = "";
        $this->separator->TooltipValue = "";

        // reset_strategy
        $this->reset_strategy->HrefValue = "";
        $this->reset_strategy->TooltipValue = "";

        // validation_regex
        $this->validation_regex->HrefValue = "";
        $this->validation_regex->TooltipValue = "";

        // example_output
        $this->example_output->HrefValue = "";
        $this->example_output->TooltipValue = "";

        // is_active
        $this->is_active->HrefValue = "";
        $this->is_active->TooltipValue = "";

        // effective_date
        $this->effective_date->HrefValue = "";
        $this->effective_date->TooltipValue = "";

        // obsolete_date
        $this->obsolete_date->HrefValue = "";
        $this->obsolete_date->TooltipValue = "";

        // created_by
        $this->created_by->HrefValue = "";
        $this->created_by->TooltipValue = "";

        // created_at
        $this->created_at->HrefValue = "";
        $this->created_at->TooltipValue = "";

        // updated_at
        $this->updated_at->HrefValue = "";
        $this->updated_at->TooltipValue = "";

        // partition_by_month
        $this->partition_by_month->HrefValue = "";
        $this->partition_by_month->TooltipValue = "";

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

        // config_id
        $this->config_id->setupEditAttributes();
        $this->config_id->EditValue = $this->config_id->CurrentValue;

        // module_code
        $this->module_code->setupEditAttributes();
        if (!$this->module_code->Raw) {
            $this->module_code->CurrentValue = HtmlDecode($this->module_code->CurrentValue);
        }
        $this->module_code->EditValue = $this->module_code->CurrentValue;
        $this->module_code->PlaceHolder = RemoveHtml($this->module_code->caption());

        // document_type
        $this->document_type->setupEditAttributes();
        if (!$this->document_type->Raw) {
            $this->document_type->CurrentValue = HtmlDecode($this->document_type->CurrentValue);
        }
        $this->document_type->EditValue = $this->document_type->CurrentValue;
        $this->document_type->PlaceHolder = RemoveHtml($this->document_type->caption());

        // config_name
        $this->config_name->setupEditAttributes();
        if (!$this->config_name->Raw) {
            $this->config_name->CurrentValue = HtmlDecode($this->config_name->CurrentValue);
        }
        $this->config_name->EditValue = $this->config_name->CurrentValue;
        $this->config_name->PlaceHolder = RemoveHtml($this->config_name->caption());

        // description
        $this->description->setupEditAttributes();
        $this->description->EditValue = $this->description->CurrentValue;
        $this->description->PlaceHolder = RemoveHtml($this->description->caption());

        // use_prefix
        $this->use_prefix->EditValue = $this->use_prefix->options(false);
        $this->use_prefix->PlaceHolder = RemoveHtml($this->use_prefix->caption());

        // prefix_value
        $this->prefix_value->setupEditAttributes();
        if (!$this->prefix_value->Raw) {
            $this->prefix_value->CurrentValue = HtmlDecode($this->prefix_value->CurrentValue);
        }
        $this->prefix_value->EditValue = $this->prefix_value->CurrentValue;
        $this->prefix_value->PlaceHolder = RemoveHtml($this->prefix_value->caption());

        // use_year
        $this->use_year->EditValue = $this->use_year->options(false);
        $this->use_year->PlaceHolder = RemoveHtml($this->use_year->caption());

        // year_format
        $this->year_format->setupEditAttributes();
        if (!$this->year_format->Raw) {
            $this->year_format->CurrentValue = HtmlDecode($this->year_format->CurrentValue);
        }
        $this->year_format->EditValue = $this->year_format->CurrentValue;
        $this->year_format->PlaceHolder = RemoveHtml($this->year_format->caption());

        // use_fiscal_year
        $this->use_fiscal_year->EditValue = $this->use_fiscal_year->options(false);
        $this->use_fiscal_year->PlaceHolder = RemoveHtml($this->use_fiscal_year->caption());

        // use_month
        $this->use_month->EditValue = $this->use_month->options(false);
        $this->use_month->PlaceHolder = RemoveHtml($this->use_month->caption());

        // month_format
        $this->month_format->setupEditAttributes();
        if (!$this->month_format->Raw) {
            $this->month_format->CurrentValue = HtmlDecode($this->month_format->CurrentValue);
        }
        $this->month_format->EditValue = $this->month_format->CurrentValue;
        $this->month_format->PlaceHolder = RemoveHtml($this->month_format->caption());

        // use_department
        $this->use_department->EditValue = $this->use_department->options(false);
        $this->use_department->PlaceHolder = RemoveHtml($this->use_department->caption());

        // department_field
        $this->department_field->setupEditAttributes();
        if (!$this->department_field->Raw) {
            $this->department_field->CurrentValue = HtmlDecode($this->department_field->CurrentValue);
        }
        $this->department_field->EditValue = $this->department_field->CurrentValue;
        $this->department_field->PlaceHolder = RemoveHtml($this->department_field->caption());

        // sequence_length
        $this->sequence_length->setupEditAttributes();
        $this->sequence_length->EditValue = $this->sequence_length->CurrentValue;
        $this->sequence_length->PlaceHolder = RemoveHtml($this->sequence_length->caption());
        if (strval($this->sequence_length->EditValue) != "" && is_numeric($this->sequence_length->EditValue)) {
            $this->sequence_length->EditValue = FormatNumber($this->sequence_length->EditValue, null);
        }

        // sequence_start
        $this->sequence_start->setupEditAttributes();
        $this->sequence_start->EditValue = $this->sequence_start->CurrentValue;
        $this->sequence_start->PlaceHolder = RemoveHtml($this->sequence_start->caption());
        if (strval($this->sequence_start->EditValue) != "" && is_numeric($this->sequence_start->EditValue)) {
            $this->sequence_start->EditValue = FormatNumber($this->sequence_start->EditValue, null);
        }

        // use_suffix
        $this->use_suffix->EditValue = $this->use_suffix->options(false);
        $this->use_suffix->PlaceHolder = RemoveHtml($this->use_suffix->caption());

        // suffix_value
        $this->suffix_value->setupEditAttributes();
        if (!$this->suffix_value->Raw) {
            $this->suffix_value->CurrentValue = HtmlDecode($this->suffix_value->CurrentValue);
        }
        $this->suffix_value->EditValue = $this->suffix_value->CurrentValue;
        $this->suffix_value->PlaceHolder = RemoveHtml($this->suffix_value->caption());

        // separator
        $this->separator->setupEditAttributes();
        if (!$this->separator->Raw) {
            $this->separator->CurrentValue = HtmlDecode($this->separator->CurrentValue);
        }
        $this->separator->EditValue = $this->separator->CurrentValue;
        $this->separator->PlaceHolder = RemoveHtml($this->separator->caption());

        // reset_strategy
        $this->reset_strategy->setupEditAttributes();
        if (!$this->reset_strategy->Raw) {
            $this->reset_strategy->CurrentValue = HtmlDecode($this->reset_strategy->CurrentValue);
        }
        $this->reset_strategy->EditValue = $this->reset_strategy->CurrentValue;
        $this->reset_strategy->PlaceHolder = RemoveHtml($this->reset_strategy->caption());

        // validation_regex
        $this->validation_regex->setupEditAttributes();
        if (!$this->validation_regex->Raw) {
            $this->validation_regex->CurrentValue = HtmlDecode($this->validation_regex->CurrentValue);
        }
        $this->validation_regex->EditValue = $this->validation_regex->CurrentValue;
        $this->validation_regex->PlaceHolder = RemoveHtml($this->validation_regex->caption());

        // example_output
        $this->example_output->setupEditAttributes();
        if (!$this->example_output->Raw) {
            $this->example_output->CurrentValue = HtmlDecode($this->example_output->CurrentValue);
        }
        $this->example_output->EditValue = $this->example_output->CurrentValue;
        $this->example_output->PlaceHolder = RemoveHtml($this->example_output->caption());

        // is_active
        $this->is_active->EditValue = $this->is_active->options(false);
        $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

        // effective_date
        $this->effective_date->setupEditAttributes();
        $this->effective_date->EditValue = FormatDateTime($this->effective_date->CurrentValue, $this->effective_date->formatPattern());
        $this->effective_date->PlaceHolder = RemoveHtml($this->effective_date->caption());

        // obsolete_date
        $this->obsolete_date->setupEditAttributes();
        $this->obsolete_date->EditValue = FormatDateTime($this->obsolete_date->CurrentValue, $this->obsolete_date->formatPattern());
        $this->obsolete_date->PlaceHolder = RemoveHtml($this->obsolete_date->caption());

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

        // updated_at
        $this->updated_at->setupEditAttributes();
        $this->updated_at->EditValue = FormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern());
        $this->updated_at->PlaceHolder = RemoveHtml($this->updated_at->caption());

        // partition_by_month
        $this->partition_by_month->EditValue = $this->partition_by_month->options(false);
        $this->partition_by_month->PlaceHolder = RemoveHtml($this->partition_by_month->caption());

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
                    $doc->exportCaption($this->config_id);
                    $doc->exportCaption($this->module_code);
                    $doc->exportCaption($this->document_type);
                    $doc->exportCaption($this->config_name);
                    $doc->exportCaption($this->description);
                    $doc->exportCaption($this->use_prefix);
                    $doc->exportCaption($this->prefix_value);
                    $doc->exportCaption($this->use_year);
                    $doc->exportCaption($this->year_format);
                    $doc->exportCaption($this->use_fiscal_year);
                    $doc->exportCaption($this->use_month);
                    $doc->exportCaption($this->month_format);
                    $doc->exportCaption($this->use_department);
                    $doc->exportCaption($this->department_field);
                    $doc->exportCaption($this->sequence_length);
                    $doc->exportCaption($this->sequence_start);
                    $doc->exportCaption($this->use_suffix);
                    $doc->exportCaption($this->suffix_value);
                    $doc->exportCaption($this->separator);
                    $doc->exportCaption($this->reset_strategy);
                    $doc->exportCaption($this->validation_regex);
                    $doc->exportCaption($this->example_output);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->effective_date);
                    $doc->exportCaption($this->obsolete_date);
                    $doc->exportCaption($this->created_by);
                    $doc->exportCaption($this->created_at);
                    $doc->exportCaption($this->updated_at);
                    $doc->exportCaption($this->partition_by_month);
                } else {
                    $doc->exportCaption($this->config_id);
                    $doc->exportCaption($this->module_code);
                    $doc->exportCaption($this->document_type);
                    $doc->exportCaption($this->config_name);
                    $doc->exportCaption($this->use_prefix);
                    $doc->exportCaption($this->prefix_value);
                    $doc->exportCaption($this->use_year);
                    $doc->exportCaption($this->year_format);
                    $doc->exportCaption($this->use_fiscal_year);
                    $doc->exportCaption($this->use_month);
                    $doc->exportCaption($this->month_format);
                    $doc->exportCaption($this->use_department);
                    $doc->exportCaption($this->department_field);
                    $doc->exportCaption($this->sequence_length);
                    $doc->exportCaption($this->sequence_start);
                    $doc->exportCaption($this->use_suffix);
                    $doc->exportCaption($this->suffix_value);
                    $doc->exportCaption($this->separator);
                    $doc->exportCaption($this->reset_strategy);
                    $doc->exportCaption($this->validation_regex);
                    $doc->exportCaption($this->example_output);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->effective_date);
                    $doc->exportCaption($this->obsolete_date);
                    $doc->exportCaption($this->created_by);
                    $doc->exportCaption($this->created_at);
                    $doc->exportCaption($this->updated_at);
                    $doc->exportCaption($this->partition_by_month);
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
                        $doc->exportField($this->config_id);
                        $doc->exportField($this->module_code);
                        $doc->exportField($this->document_type);
                        $doc->exportField($this->config_name);
                        $doc->exportField($this->description);
                        $doc->exportField($this->use_prefix);
                        $doc->exportField($this->prefix_value);
                        $doc->exportField($this->use_year);
                        $doc->exportField($this->year_format);
                        $doc->exportField($this->use_fiscal_year);
                        $doc->exportField($this->use_month);
                        $doc->exportField($this->month_format);
                        $doc->exportField($this->use_department);
                        $doc->exportField($this->department_field);
                        $doc->exportField($this->sequence_length);
                        $doc->exportField($this->sequence_start);
                        $doc->exportField($this->use_suffix);
                        $doc->exportField($this->suffix_value);
                        $doc->exportField($this->separator);
                        $doc->exportField($this->reset_strategy);
                        $doc->exportField($this->validation_regex);
                        $doc->exportField($this->example_output);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->effective_date);
                        $doc->exportField($this->obsolete_date);
                        $doc->exportField($this->created_by);
                        $doc->exportField($this->created_at);
                        $doc->exportField($this->updated_at);
                        $doc->exportField($this->partition_by_month);
                    } else {
                        $doc->exportField($this->config_id);
                        $doc->exportField($this->module_code);
                        $doc->exportField($this->document_type);
                        $doc->exportField($this->config_name);
                        $doc->exportField($this->use_prefix);
                        $doc->exportField($this->prefix_value);
                        $doc->exportField($this->use_year);
                        $doc->exportField($this->year_format);
                        $doc->exportField($this->use_fiscal_year);
                        $doc->exportField($this->use_month);
                        $doc->exportField($this->month_format);
                        $doc->exportField($this->use_department);
                        $doc->exportField($this->department_field);
                        $doc->exportField($this->sequence_length);
                        $doc->exportField($this->sequence_start);
                        $doc->exportField($this->use_suffix);
                        $doc->exportField($this->suffix_value);
                        $doc->exportField($this->separator);
                        $doc->exportField($this->reset_strategy);
                        $doc->exportField($this->validation_regex);
                        $doc->exportField($this->example_output);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->effective_date);
                        $doc->exportField($this->obsolete_date);
                        $doc->exportField($this->created_by);
                        $doc->exportField($this->created_at);
                        $doc->exportField($this->updated_at);
                        $doc->exportField($this->partition_by_month);
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
