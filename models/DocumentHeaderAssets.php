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
 * Table class for document_header_assets
 */
class DocumentHeaderAssets extends DbTable
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
    public $header_asset_id;
    public $asset_code;
    public $asset_name;
    public $document_asset_type_id;
    public $scope_system_code;
    public $scope_document_code;
    public $file_name;
    public $mime_type;
    public $file_size;
    public $checksum_sha256;
    public $image_width;
    public $image_height;
    public $render_width;
    public $render_height;
    public $image_data;
    public $is_active;
    public $notes;
    public $created_at;
    public $updated_at;
    public $created_by;
    public $updated_by;
    public $header_slot_id;
    public $part_kind;
    public $column_pct;
    public $align;
    public $text_line1;
    public $text_line2;
    public $font_name;
    public $line1_size_pt;
    public $line2_size_pt;
    public $line1_bold;
    public $line2_bold;
    public $line1_color;
    public $line2_color;

    // Page ID
    public $PageID = ""; // To be overridden by subclass

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $CurrentLanguage, $CurrentLocale;

        // Language object
        $Language = Container("app.language");
        $this->TableVar = "document_header_assets";
        $this->TableName = 'document_header_assets';
        $this->TableType = "TABLE";
        $this->ImportUseTransaction = $this->supportsTransaction() && Config("IMPORT_USE_TRANSACTION");
        $this->UseTransaction = $this->supportsTransaction() && Config("USE_TRANSACTION");

        // Update Table
        $this->UpdateTable = "uac.document_header_assets";
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

        // header_asset_id
        $this->header_asset_id = new DbField(
            $this, // Table
            'x_header_asset_id', // Variable name
            'header_asset_id', // Name
            '"header_asset_id"', // Expression
            'CAST("header_asset_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"header_asset_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'NO' // Edit Tag
        );
        $this->header_asset_id->InputTextType = "text";
        $this->header_asset_id->Raw = true;
        $this->header_asset_id->IsAutoIncrement = true; // Autoincrement field
        $this->header_asset_id->IsPrimaryKey = true; // Primary key field
        $this->header_asset_id->Nullable = false; // NOT NULL field
        $this->header_asset_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->header_asset_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['header_asset_id'] = &$this->header_asset_id;

        // asset_code
        $this->asset_code = new DbField(
            $this, // Table
            'x_asset_code', // Variable name
            'asset_code', // Name
            '"asset_code"', // Expression
            '"asset_code"', // Basic search expression
            200, // Type
            40, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"asset_code"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->asset_code->InputTextType = "text";
        $this->asset_code->Nullable = false; // NOT NULL field
        $this->asset_code->Required = true; // Required field
        $this->asset_code->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['asset_code'] = &$this->asset_code;

        // asset_name
        $this->asset_name = new DbField(
            $this, // Table
            'x_asset_name', // Variable name
            'asset_name', // Name
            '"asset_name"', // Expression
            '"asset_name"', // Basic search expression
            200, // Type
            150, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"asset_name"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->asset_name->InputTextType = "text";
        $this->asset_name->Nullable = false; // NOT NULL field
        $this->asset_name->Required = true; // Required field
        $this->asset_name->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['asset_name'] = &$this->asset_name;

        // document_asset_type_id
        $this->document_asset_type_id = new DbField(
            $this, // Table
            'x_document_asset_type_id', // Variable name
            'document_asset_type_id', // Name
            '"document_asset_type_id"', // Expression
            'CAST("document_asset_type_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"document_asset_type_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->document_asset_type_id->InputTextType = "text";
        $this->document_asset_type_id->Raw = true;
        $this->document_asset_type_id->Nullable = false; // NOT NULL field
        $this->document_asset_type_id->Required = true; // Required field
        $this->document_asset_type_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->document_asset_type_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['document_asset_type_id'] = &$this->document_asset_type_id;

        // scope_system_code
        $this->scope_system_code = new DbField(
            $this, // Table
            'x_scope_system_code', // Variable name
            'scope_system_code', // Name
            '"scope_system_code"', // Expression
            '"scope_system_code"', // Basic search expression
            200, // Type
            20, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"scope_system_code"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->scope_system_code->InputTextType = "text";
        $this->scope_system_code->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['scope_system_code'] = &$this->scope_system_code;

        // scope_document_code
        $this->scope_document_code = new DbField(
            $this, // Table
            'x_scope_document_code', // Variable name
            'scope_document_code', // Name
            '"scope_document_code"', // Expression
            '"scope_document_code"', // Basic search expression
            200, // Type
            30, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"scope_document_code"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->scope_document_code->InputTextType = "text";
        $this->scope_document_code->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['scope_document_code'] = &$this->scope_document_code;

        // file_name
        $this->file_name = new DbField(
            $this, // Table
            'x_file_name', // Variable name
            'file_name', // Name
            '"file_name"', // Expression
            '"file_name"', // Basic search expression
            200, // Type
            255, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"file_name"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->file_name->InputTextType = "text";
        $this->file_name->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['file_name'] = &$this->file_name;

        // mime_type
        $this->mime_type = new DbField(
            $this, // Table
            'x_mime_type', // Variable name
            'mime_type', // Name
            '"mime_type"', // Expression
            '"mime_type"', // Basic search expression
            200, // Type
            60, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"mime_type"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->mime_type->InputTextType = "text";
        $this->mime_type->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['mime_type'] = &$this->mime_type;

        // file_size
        $this->file_size = new DbField(
            $this, // Table
            'x_file_size', // Variable name
            'file_size', // Name
            '"file_size"', // Expression
            'CAST("file_size" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"file_size"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->file_size->InputTextType = "text";
        $this->file_size->Raw = true;
        $this->file_size->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->file_size->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['file_size'] = &$this->file_size;

        // checksum_sha256
        $this->checksum_sha256 = new DbField(
            $this, // Table
            'x_checksum_sha256', // Variable name
            'checksum_sha256', // Name
            '"checksum_sha256"', // Expression
            '"checksum_sha256"', // Basic search expression
            200, // Type
            64, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"checksum_sha256"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->checksum_sha256->InputTextType = "text";
        $this->checksum_sha256->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['checksum_sha256'] = &$this->checksum_sha256;

        // image_width
        $this->image_width = new DbField(
            $this, // Table
            'x_image_width', // Variable name
            'image_width', // Name
            '"image_width"', // Expression
            'CAST("image_width" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"image_width"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->image_width->InputTextType = "text";
        $this->image_width->Raw = true;
        $this->image_width->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->image_width->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['image_width'] = &$this->image_width;

        // image_height
        $this->image_height = new DbField(
            $this, // Table
            'x_image_height', // Variable name
            'image_height', // Name
            '"image_height"', // Expression
            'CAST("image_height" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"image_height"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->image_height->InputTextType = "text";
        $this->image_height->Raw = true;
        $this->image_height->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->image_height->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['image_height'] = &$this->image_height;

        // render_width
        $this->render_width = new DbField(
            $this, // Table
            'x_render_width', // Variable name
            'render_width', // Name
            '"render_width"', // Expression
            'CAST("render_width" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"render_width"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->render_width->InputTextType = "text";
        $this->render_width->Raw = true;
        $this->render_width->Nullable = false; // NOT NULL field
        $this->render_width->Required = true; // Required field
        $this->render_width->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->render_width->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['render_width'] = &$this->render_width;

        // render_height
        $this->render_height = new DbField(
            $this, // Table
            'x_render_height', // Variable name
            'render_height', // Name
            '"render_height"', // Expression
            'CAST("render_height" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"render_height"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->render_height->InputTextType = "text";
        $this->render_height->Raw = true;
        $this->render_height->Nullable = false; // NOT NULL field
        $this->render_height->Required = true; // Required field
        $this->render_height->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->render_height->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['render_height'] = &$this->render_height;

        // image_data
        $this->image_data = new DbField(
            $this, // Table
            'x_image_data', // Variable name
            'image_data', // Name
            '"image_data"', // Expression
            '"image_data"', // Basic search expression
            201, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"image_data"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXTAREA' // Edit Tag
        );
        $this->image_data->InputTextType = "text";
        $this->image_data->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['image_data'] = &$this->image_data;

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
        $this->is_active->Nullable = false; // NOT NULL field
        $this->is_active->setDataType(DataType::BOOLEAN);
        $this->is_active->Lookup = new Lookup($this->is_active, 'document_header_assets', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->is_active->OptionCount = 2;
        $this->is_active->SearchOperators = ["=", "<>"];
        $this->Fields['is_active'] = &$this->is_active;

        // notes
        $this->notes = new DbField(
            $this, // Table
            'x_notes', // Variable name
            'notes', // Name
            '"notes"', // Expression
            '"notes"', // Basic search expression
            201, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"notes"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXTAREA' // Edit Tag
        );
        $this->notes->InputTextType = "text";
        $this->notes->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['notes'] = &$this->notes;

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
        $this->created_at->Nullable = false; // NOT NULL field
        $this->created_at->Required = true; // Required field
        $this->created_at->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->created_at->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
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
        $this->updated_at->Nullable = false; // NOT NULL field
        $this->updated_at->Required = true; // Required field
        $this->updated_at->DefaultErrorMessage = str_replace("%s", $GLOBALS["DATE_FORMAT"], $Language->phrase("IncorrectDate"));
        $this->updated_at->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN"];
        $this->Fields['updated_at'] = &$this->updated_at;

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

        // updated_by
        $this->updated_by = new DbField(
            $this, // Table
            'x_updated_by', // Variable name
            'updated_by', // Name
            '"updated_by"', // Expression
            'CAST("updated_by" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"updated_by"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->updated_by->InputTextType = "text";
        $this->updated_by->Raw = true;
        $this->updated_by->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->updated_by->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['updated_by'] = &$this->updated_by;

        // header_slot_id
        $this->header_slot_id = new DbField(
            $this, // Table
            'x_header_slot_id', // Variable name
            'header_slot_id', // Name
            '"header_slot_id"', // Expression
            'CAST("header_slot_id" AS varchar(255))', // Basic search expression
            3, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"header_slot_id"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->header_slot_id->InputTextType = "text";
        $this->header_slot_id->Raw = true;
        $this->header_slot_id->DefaultErrorMessage = $Language->phrase("IncorrectInteger");
        $this->header_slot_id->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['header_slot_id'] = &$this->header_slot_id;

        // part_kind
        $this->part_kind = new DbField(
            $this, // Table
            'x_part_kind', // Variable name
            'part_kind', // Name
            '"part_kind"', // Expression
            '"part_kind"', // Basic search expression
            200, // Type
            10, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"part_kind"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->part_kind->addMethod("getDefault", fn() => "image");
        $this->part_kind->InputTextType = "text";
        $this->part_kind->Nullable = false; // NOT NULL field
        $this->part_kind->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY"];
        $this->Fields['part_kind'] = &$this->part_kind;

        // column_pct
        $this->column_pct = new DbField(
            $this, // Table
            'x_column_pct', // Variable name
            'column_pct', // Name
            '"column_pct"', // Expression
            'CAST("column_pct" AS varchar(255))', // Basic search expression
            14, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"column_pct"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->column_pct->InputTextType = "text";
        $this->column_pct->Raw = true;
        $this->column_pct->DefaultErrorMessage = $Language->phrase("IncorrectFloat");
        $this->column_pct->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['column_pct'] = &$this->column_pct;

        // align
        $this->align = new DbField(
            $this, // Table
            'x_align', // Variable name
            'align', // Name
            '"align"', // Expression
            '"align"', // Basic search expression
            200, // Type
            10, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"align"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->align->InputTextType = "text";
        $this->align->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['align'] = &$this->align;

        // text_line1
        $this->text_line1 = new DbField(
            $this, // Table
            'x_text_line1', // Variable name
            'text_line1', // Name
            '"text_line1"', // Expression
            '"text_line1"', // Basic search expression
            201, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"text_line1"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXTAREA' // Edit Tag
        );
        $this->text_line1->InputTextType = "text";
        $this->text_line1->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['text_line1'] = &$this->text_line1;

        // text_line2
        $this->text_line2 = new DbField(
            $this, // Table
            'x_text_line2', // Variable name
            'text_line2', // Name
            '"text_line2"', // Expression
            '"text_line2"', // Basic search expression
            201, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"text_line2"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXTAREA' // Edit Tag
        );
        $this->text_line2->InputTextType = "text";
        $this->text_line2->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['text_line2'] = &$this->text_line2;

        // font_name
        $this->font_name = new DbField(
            $this, // Table
            'x_font_name', // Variable name
            'font_name', // Name
            '"font_name"', // Expression
            '"font_name"', // Basic search expression
            200, // Type
            60, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"font_name"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->font_name->InputTextType = "text";
        $this->font_name->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['font_name'] = &$this->font_name;

        // line1_size_pt
        $this->line1_size_pt = new DbField(
            $this, // Table
            'x_line1_size_pt', // Variable name
            'line1_size_pt', // Name
            '"line1_size_pt"', // Expression
            'CAST("line1_size_pt" AS varchar(255))', // Basic search expression
            14, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"line1_size_pt"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->line1_size_pt->InputTextType = "text";
        $this->line1_size_pt->Raw = true;
        $this->line1_size_pt->DefaultErrorMessage = $Language->phrase("IncorrectFloat");
        $this->line1_size_pt->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['line1_size_pt'] = &$this->line1_size_pt;

        // line2_size_pt
        $this->line2_size_pt = new DbField(
            $this, // Table
            'x_line2_size_pt', // Variable name
            'line2_size_pt', // Name
            '"line2_size_pt"', // Expression
            'CAST("line2_size_pt" AS varchar(255))', // Basic search expression
            14, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"line2_size_pt"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->line2_size_pt->InputTextType = "text";
        $this->line2_size_pt->Raw = true;
        $this->line2_size_pt->DefaultErrorMessage = $Language->phrase("IncorrectFloat");
        $this->line2_size_pt->SearchOperators = ["=", "<>", "IN", "NOT IN", "<", "<=", ">", ">=", "BETWEEN", "NOT BETWEEN", "IS NULL", "IS NOT NULL"];
        $this->Fields['line2_size_pt'] = &$this->line2_size_pt;

        // line1_bold
        $this->line1_bold = new DbField(
            $this, // Table
            'x_line1_bold', // Variable name
            'line1_bold', // Name
            '"line1_bold"', // Expression
            'CAST("line1_bold" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"line1_bold"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->line1_bold->InputTextType = "text";
        $this->line1_bold->Raw = true;
        $this->line1_bold->setDataType(DataType::BOOLEAN);
        $this->line1_bold->Lookup = new Lookup($this->line1_bold, 'document_header_assets', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->line1_bold->OptionCount = 2;
        $this->line1_bold->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['line1_bold'] = &$this->line1_bold;

        // line2_bold
        $this->line2_bold = new DbField(
            $this, // Table
            'x_line2_bold', // Variable name
            'line2_bold', // Name
            '"line2_bold"', // Expression
            'CAST("line2_bold" AS varchar(255))', // Basic search expression
            11, // Type
            0, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"line2_bold"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'CHECKBOX' // Edit Tag
        );
        $this->line2_bold->InputTextType = "text";
        $this->line2_bold->Raw = true;
        $this->line2_bold->setDataType(DataType::BOOLEAN);
        $this->line2_bold->Lookup = new Lookup($this->line2_bold, 'document_header_assets', false, '', ["","","",""], '', '', [], [], [], [], [], [], false, '', '', "");
        $this->line2_bold->OptionCount = 2;
        $this->line2_bold->SearchOperators = ["=", "<>", "IS NULL", "IS NOT NULL"];
        $this->Fields['line2_bold'] = &$this->line2_bold;

        // line1_color
        $this->line1_color = new DbField(
            $this, // Table
            'x_line1_color', // Variable name
            'line1_color', // Name
            '"line1_color"', // Expression
            '"line1_color"', // Basic search expression
            200, // Type
            8, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"line1_color"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->line1_color->InputTextType = "text";
        $this->line1_color->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['line1_color'] = &$this->line1_color;

        // line2_color
        $this->line2_color = new DbField(
            $this, // Table
            'x_line2_color', // Variable name
            'line2_color', // Name
            '"line2_color"', // Expression
            '"line2_color"', // Basic search expression
            200, // Type
            8, // Size
            -1, // Date/Time format
            false, // Is upload field
            '"line2_color"', // Virtual expression
            false, // Is virtual
            false, // Force selection
            false, // Is Virtual search
            'FORMATTED TEXT', // View Tag
            'TEXT' // Edit Tag
        );
        $this->line2_color->InputTextType = "text";
        $this->line2_color->SearchOperators = ["=", "<>", "IN", "NOT IN", "STARTS WITH", "NOT STARTS WITH", "LIKE", "NOT LIKE", "ENDS WITH", "NOT ENDS WITH", "IS EMPTY", "IS NOT EMPTY", "IS NULL", "IS NOT NULL"];
        $this->Fields['line2_color'] = &$this->line2_color;

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
        return ($this->SqlFrom != "") ? $this->SqlFrom : "uac.document_header_assets";
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
                $queryBuilder->getSQL() . " RETURNING header_asset_id",
                $queryBuilder->getParameters(),
                $queryBuilder->getParameterTypes()
            )->fetchOne();
            $this->DbErrorMessage = "";
        } catch (\Exception $e) {
            $result = false;
            $this->DbErrorMessage = $e->getMessage();
        }
        if ($result) {
            $this->header_asset_id->setDbValue($result);
            $rs['header_asset_id'] = $this->header_asset_id->DbValue;
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
            if (!isset($rs['header_asset_id']) && !EmptyValue($this->header_asset_id->CurrentValue)) {
                $rs['header_asset_id'] = $this->header_asset_id->CurrentValue;
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
            if (array_key_exists('header_asset_id', $rs)) {
                AddFilter($where, QuotedName('header_asset_id', $this->Dbid) . '=' . QuotedValue($rs['header_asset_id'], $this->header_asset_id->DataType, $this->Dbid));
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
        $this->header_asset_id->DbValue = $row['header_asset_id'];
        $this->asset_code->DbValue = $row['asset_code'];
        $this->asset_name->DbValue = $row['asset_name'];
        $this->document_asset_type_id->DbValue = $row['document_asset_type_id'];
        $this->scope_system_code->DbValue = $row['scope_system_code'];
        $this->scope_document_code->DbValue = $row['scope_document_code'];
        $this->file_name->DbValue = $row['file_name'];
        $this->mime_type->DbValue = $row['mime_type'];
        $this->file_size->DbValue = $row['file_size'];
        $this->checksum_sha256->DbValue = $row['checksum_sha256'];
        $this->image_width->DbValue = $row['image_width'];
        $this->image_height->DbValue = $row['image_height'];
        $this->render_width->DbValue = $row['render_width'];
        $this->render_height->DbValue = $row['render_height'];
        $this->image_data->DbValue = $row['image_data'];
        $this->is_active->DbValue = (ConvertToBool($row['is_active']) ? "1" : "0");
        $this->notes->DbValue = $row['notes'];
        $this->created_at->DbValue = $row['created_at'];
        $this->updated_at->DbValue = $row['updated_at'];
        $this->created_by->DbValue = $row['created_by'];
        $this->updated_by->DbValue = $row['updated_by'];
        $this->header_slot_id->DbValue = $row['header_slot_id'];
        $this->part_kind->DbValue = $row['part_kind'];
        $this->column_pct->DbValue = $row['column_pct'];
        $this->align->DbValue = $row['align'];
        $this->text_line1->DbValue = $row['text_line1'];
        $this->text_line2->DbValue = $row['text_line2'];
        $this->font_name->DbValue = $row['font_name'];
        $this->line1_size_pt->DbValue = $row['line1_size_pt'];
        $this->line2_size_pt->DbValue = $row['line2_size_pt'];
        $this->line1_bold->DbValue = (ConvertToBool($row['line1_bold']) ? "1" : "0");
        $this->line2_bold->DbValue = (ConvertToBool($row['line2_bold']) ? "1" : "0");
        $this->line1_color->DbValue = $row['line1_color'];
        $this->line2_color->DbValue = $row['line2_color'];
    }

    // Delete uploaded files
    public function deleteUploadedFiles($row)
    {
        $this->loadDbValues($row);
    }

    // Record filter WHERE clause
    protected function sqlKeyFilter()
    {
        return "\"header_asset_id\" = @header_asset_id@";
    }

    // Get Key
    public function getKey($current = false, $keySeparator = null)
    {
        $keys = [];
        $val = $current ? $this->header_asset_id->CurrentValue : $this->header_asset_id->OldValue;
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
                $this->header_asset_id->CurrentValue = $keys[0];
            } else {
                $this->header_asset_id->OldValue = $keys[0];
            }
        }
    }

    // Get record filter
    public function getRecordFilter($row = null, $current = false)
    {
        $keyFilter = $this->sqlKeyFilter();
        if (is_array($row)) {
            $val = array_key_exists('header_asset_id', $row) ? $row['header_asset_id'] : null;
        } else {
            $val = !EmptyValue($this->header_asset_id->OldValue) && !$current ? $this->header_asset_id->OldValue : $this->header_asset_id->CurrentValue;
        }
        if (!is_numeric($val)) {
            return "0=1"; // Invalid key
        }
        if ($val === null) {
            return "0=1"; // Invalid key
        } else {
            $keyFilter = str_replace("@header_asset_id@", AdjustSql($val, $this->Dbid), $keyFilter); // Replace key value
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
        return $_SESSION[$name] ?? GetUrl("DocumentHeaderAssetsList");
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
            "DocumentHeaderAssetsView" => $Language->phrase("View"),
            "DocumentHeaderAssetsEdit" => $Language->phrase("Edit"),
            "DocumentHeaderAssetsAdd" => $Language->phrase("Add"),
            default => ""
        };
    }

    // Default route URL
    public function getDefaultRouteUrl()
    {
        return "DocumentHeaderAssetsList";
    }

    // API page name
    public function getApiPageName($action)
    {
        return match (strtolower($action)) {
            Config("API_VIEW_ACTION") => "DocumentHeaderAssetsView",
            Config("API_ADD_ACTION") => "DocumentHeaderAssetsAdd",
            Config("API_EDIT_ACTION") => "DocumentHeaderAssetsEdit",
            Config("API_DELETE_ACTION") => "DocumentHeaderAssetsDelete",
            Config("API_LIST_ACTION") => "DocumentHeaderAssetsList",
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
        return "DocumentHeaderAssetsList";
    }

    // View URL
    public function getViewUrl($parm = "")
    {
        if ($parm != "") {
            $url = $this->keyUrl("DocumentHeaderAssetsView", $parm);
        } else {
            $url = $this->keyUrl("DocumentHeaderAssetsView", Config("TABLE_SHOW_DETAIL") . "=");
        }
        return $this->addMasterUrl($url);
    }

    // Add URL
    public function getAddUrl($parm = "")
    {
        if ($parm != "") {
            $url = "DocumentHeaderAssetsAdd?" . $parm;
        } else {
            $url = "DocumentHeaderAssetsAdd";
        }
        return $this->addMasterUrl($url);
    }

    // Edit URL
    public function getEditUrl($parm = "")
    {
        $url = $this->keyUrl("DocumentHeaderAssetsEdit", $parm);
        return $this->addMasterUrl($url);
    }

    // Inline edit URL
    public function getInlineEditUrl()
    {
        $url = $this->keyUrl("DocumentHeaderAssetsList", "action=edit");
        return $this->addMasterUrl($url);
    }

    // Copy URL
    public function getCopyUrl($parm = "")
    {
        $url = $this->keyUrl("DocumentHeaderAssetsAdd", $parm);
        return $this->addMasterUrl($url);
    }

    // Inline copy URL
    public function getInlineCopyUrl()
    {
        $url = $this->keyUrl("DocumentHeaderAssetsList", "action=copy");
        return $this->addMasterUrl($url);
    }

    // Delete URL
    public function getDeleteUrl($parm = "")
    {
        if ($this->UseAjaxActions && ConvertToBool(Param("infinitescroll")) && CurrentPageID() == "list") {
            return $this->keyUrl(GetApiUrl(Config("API_DELETE_ACTION") . "/" . $this->TableVar));
        } else {
            return $this->keyUrl("DocumentHeaderAssetsDelete", $parm);
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
        $json .= "\"header_asset_id\":" . VarToJson($this->header_asset_id->CurrentValue, "number");
        $json = "{" . $json . "}";
        if ($htmlEncode) {
            $json = HtmlEncode($json);
        }
        return $json;
    }

    // Add key value to URL
    public function keyUrl($url, $parm = "")
    {
        if ($this->header_asset_id->CurrentValue !== null) {
            $url .= "/" . $this->encodeKeyValue($this->header_asset_id->CurrentValue);
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
            if (($keyValue = Param("header_asset_id") ?? Route("header_asset_id")) !== null) {
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
                $this->header_asset_id->CurrentValue = $key;
            } else {
                $this->header_asset_id->OldValue = $key;
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
        $this->header_asset_id->setDbValue($row['header_asset_id']);
        $this->asset_code->setDbValue($row['asset_code']);
        $this->asset_name->setDbValue($row['asset_name']);
        $this->document_asset_type_id->setDbValue($row['document_asset_type_id']);
        $this->scope_system_code->setDbValue($row['scope_system_code']);
        $this->scope_document_code->setDbValue($row['scope_document_code']);
        $this->file_name->setDbValue($row['file_name']);
        $this->mime_type->setDbValue($row['mime_type']);
        $this->file_size->setDbValue($row['file_size']);
        $this->checksum_sha256->setDbValue($row['checksum_sha256']);
        $this->image_width->setDbValue($row['image_width']);
        $this->image_height->setDbValue($row['image_height']);
        $this->render_width->setDbValue($row['render_width']);
        $this->render_height->setDbValue($row['render_height']);
        $this->image_data->setDbValue($row['image_data']);
        $this->is_active->setDbValue(ConvertToBool($row['is_active']) ? "1" : "0");
        $this->notes->setDbValue($row['notes']);
        $this->created_at->setDbValue($row['created_at']);
        $this->updated_at->setDbValue($row['updated_at']);
        $this->created_by->setDbValue($row['created_by']);
        $this->updated_by->setDbValue($row['updated_by']);
        $this->header_slot_id->setDbValue($row['header_slot_id']);
        $this->part_kind->setDbValue($row['part_kind']);
        $this->column_pct->setDbValue($row['column_pct']);
        $this->align->setDbValue($row['align']);
        $this->text_line1->setDbValue($row['text_line1']);
        $this->text_line2->setDbValue($row['text_line2']);
        $this->font_name->setDbValue($row['font_name']);
        $this->line1_size_pt->setDbValue($row['line1_size_pt']);
        $this->line2_size_pt->setDbValue($row['line2_size_pt']);
        $this->line1_bold->setDbValue(ConvertToBool($row['line1_bold']) ? "1" : "0");
        $this->line2_bold->setDbValue(ConvertToBool($row['line2_bold']) ? "1" : "0");
        $this->line1_color->setDbValue($row['line1_color']);
        $this->line2_color->setDbValue($row['line2_color']);
    }

    // Render list content
    public function renderListContent($filter)
    {
        global $Response;
        $listPage = "DocumentHeaderAssetsList";
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

        // header_asset_id

        // asset_code

        // asset_name

        // document_asset_type_id

        // scope_system_code

        // scope_document_code

        // file_name

        // mime_type

        // file_size

        // checksum_sha256

        // image_width

        // image_height

        // render_width

        // render_height

        // image_data

        // is_active

        // notes

        // created_at

        // updated_at

        // created_by

        // updated_by

        // header_slot_id

        // part_kind

        // column_pct

        // align

        // text_line1

        // text_line2

        // font_name

        // line1_size_pt

        // line2_size_pt

        // line1_bold

        // line2_bold

        // line1_color

        // line2_color

        // header_asset_id
        $this->header_asset_id->ViewValue = $this->header_asset_id->CurrentValue;

        // asset_code
        $this->asset_code->ViewValue = $this->asset_code->CurrentValue;

        // asset_name
        $this->asset_name->ViewValue = $this->asset_name->CurrentValue;

        // document_asset_type_id
        $this->document_asset_type_id->ViewValue = $this->document_asset_type_id->CurrentValue;
        $this->document_asset_type_id->ViewValue = FormatNumber($this->document_asset_type_id->ViewValue, $this->document_asset_type_id->formatPattern());

        // scope_system_code
        $this->scope_system_code->ViewValue = $this->scope_system_code->CurrentValue;

        // scope_document_code
        $this->scope_document_code->ViewValue = $this->scope_document_code->CurrentValue;

        // file_name
        $this->file_name->ViewValue = $this->file_name->CurrentValue;

        // mime_type
        $this->mime_type->ViewValue = $this->mime_type->CurrentValue;

        // file_size
        $this->file_size->ViewValue = $this->file_size->CurrentValue;
        $this->file_size->ViewValue = FormatNumber($this->file_size->ViewValue, $this->file_size->formatPattern());

        // checksum_sha256
        $this->checksum_sha256->ViewValue = $this->checksum_sha256->CurrentValue;

        // image_width
        $this->image_width->ViewValue = $this->image_width->CurrentValue;
        $this->image_width->ViewValue = FormatNumber($this->image_width->ViewValue, $this->image_width->formatPattern());

        // image_height
        $this->image_height->ViewValue = $this->image_height->CurrentValue;
        $this->image_height->ViewValue = FormatNumber($this->image_height->ViewValue, $this->image_height->formatPattern());

        // render_width
        $this->render_width->ViewValue = $this->render_width->CurrentValue;
        $this->render_width->ViewValue = FormatNumber($this->render_width->ViewValue, $this->render_width->formatPattern());

        // render_height
        $this->render_height->ViewValue = $this->render_height->CurrentValue;
        $this->render_height->ViewValue = FormatNumber($this->render_height->ViewValue, $this->render_height->formatPattern());

        // image_data
        $this->image_data->ViewValue = $this->image_data->CurrentValue;

        // is_active
        if (ConvertToBool($this->is_active->CurrentValue)) {
            $this->is_active->ViewValue = $this->is_active->tagCaption(1) != "" ? $this->is_active->tagCaption(1) : "Yes";
        } else {
            $this->is_active->ViewValue = $this->is_active->tagCaption(2) != "" ? $this->is_active->tagCaption(2) : "No";
        }

        // notes
        $this->notes->ViewValue = $this->notes->CurrentValue;

        // created_at
        $this->created_at->ViewValue = $this->created_at->CurrentValue;
        $this->created_at->ViewValue = FormatDateTime($this->created_at->ViewValue, $this->created_at->formatPattern());

        // updated_at
        $this->updated_at->ViewValue = $this->updated_at->CurrentValue;
        $this->updated_at->ViewValue = FormatDateTime($this->updated_at->ViewValue, $this->updated_at->formatPattern());

        // created_by
        $this->created_by->ViewValue = $this->created_by->CurrentValue;
        $this->created_by->ViewValue = FormatNumber($this->created_by->ViewValue, $this->created_by->formatPattern());

        // updated_by
        $this->updated_by->ViewValue = $this->updated_by->CurrentValue;
        $this->updated_by->ViewValue = FormatNumber($this->updated_by->ViewValue, $this->updated_by->formatPattern());

        // header_slot_id
        $this->header_slot_id->ViewValue = $this->header_slot_id->CurrentValue;
        $this->header_slot_id->ViewValue = FormatNumber($this->header_slot_id->ViewValue, $this->header_slot_id->formatPattern());

        // part_kind
        $this->part_kind->ViewValue = $this->part_kind->CurrentValue;

        // column_pct
        $this->column_pct->ViewValue = $this->column_pct->CurrentValue;
        $this->column_pct->ViewValue = FormatNumber($this->column_pct->ViewValue, $this->column_pct->formatPattern());

        // align
        $this->align->ViewValue = $this->align->CurrentValue;

        // text_line1
        $this->text_line1->ViewValue = $this->text_line1->CurrentValue;

        // text_line2
        $this->text_line2->ViewValue = $this->text_line2->CurrentValue;

        // font_name
        $this->font_name->ViewValue = $this->font_name->CurrentValue;

        // line1_size_pt
        $this->line1_size_pt->ViewValue = $this->line1_size_pt->CurrentValue;
        $this->line1_size_pt->ViewValue = FormatNumber($this->line1_size_pt->ViewValue, $this->line1_size_pt->formatPattern());

        // line2_size_pt
        $this->line2_size_pt->ViewValue = $this->line2_size_pt->CurrentValue;
        $this->line2_size_pt->ViewValue = FormatNumber($this->line2_size_pt->ViewValue, $this->line2_size_pt->formatPattern());

        // line1_bold
        if (ConvertToBool($this->line1_bold->CurrentValue)) {
            $this->line1_bold->ViewValue = $this->line1_bold->tagCaption(1) != "" ? $this->line1_bold->tagCaption(1) : "Yes";
        } else {
            $this->line1_bold->ViewValue = $this->line1_bold->tagCaption(2) != "" ? $this->line1_bold->tagCaption(2) : "No";
        }

        // line2_bold
        if (ConvertToBool($this->line2_bold->CurrentValue)) {
            $this->line2_bold->ViewValue = $this->line2_bold->tagCaption(1) != "" ? $this->line2_bold->tagCaption(1) : "Yes";
        } else {
            $this->line2_bold->ViewValue = $this->line2_bold->tagCaption(2) != "" ? $this->line2_bold->tagCaption(2) : "No";
        }

        // line1_color
        $this->line1_color->ViewValue = $this->line1_color->CurrentValue;

        // line2_color
        $this->line2_color->ViewValue = $this->line2_color->CurrentValue;

        // header_asset_id
        $this->header_asset_id->HrefValue = "";
        $this->header_asset_id->TooltipValue = "";

        // asset_code
        $this->asset_code->HrefValue = "";
        $this->asset_code->TooltipValue = "";

        // asset_name
        $this->asset_name->HrefValue = "";
        $this->asset_name->TooltipValue = "";

        // document_asset_type_id
        $this->document_asset_type_id->HrefValue = "";
        $this->document_asset_type_id->TooltipValue = "";

        // scope_system_code
        $this->scope_system_code->HrefValue = "";
        $this->scope_system_code->TooltipValue = "";

        // scope_document_code
        $this->scope_document_code->HrefValue = "";
        $this->scope_document_code->TooltipValue = "";

        // file_name
        $this->file_name->HrefValue = "";
        $this->file_name->TooltipValue = "";

        // mime_type
        $this->mime_type->HrefValue = "";
        $this->mime_type->TooltipValue = "";

        // file_size
        $this->file_size->HrefValue = "";
        $this->file_size->TooltipValue = "";

        // checksum_sha256
        $this->checksum_sha256->HrefValue = "";
        $this->checksum_sha256->TooltipValue = "";

        // image_width
        $this->image_width->HrefValue = "";
        $this->image_width->TooltipValue = "";

        // image_height
        $this->image_height->HrefValue = "";
        $this->image_height->TooltipValue = "";

        // render_width
        $this->render_width->HrefValue = "";
        $this->render_width->TooltipValue = "";

        // render_height
        $this->render_height->HrefValue = "";
        $this->render_height->TooltipValue = "";

        // image_data
        $this->image_data->HrefValue = "";
        $this->image_data->TooltipValue = "";

        // is_active
        $this->is_active->HrefValue = "";
        $this->is_active->TooltipValue = "";

        // notes
        $this->notes->HrefValue = "";
        $this->notes->TooltipValue = "";

        // created_at
        $this->created_at->HrefValue = "";
        $this->created_at->TooltipValue = "";

        // updated_at
        $this->updated_at->HrefValue = "";
        $this->updated_at->TooltipValue = "";

        // created_by
        $this->created_by->HrefValue = "";
        $this->created_by->TooltipValue = "";

        // updated_by
        $this->updated_by->HrefValue = "";
        $this->updated_by->TooltipValue = "";

        // header_slot_id
        $this->header_slot_id->HrefValue = "";
        $this->header_slot_id->TooltipValue = "";

        // part_kind
        $this->part_kind->HrefValue = "";
        $this->part_kind->TooltipValue = "";

        // column_pct
        $this->column_pct->HrefValue = "";
        $this->column_pct->TooltipValue = "";

        // align
        $this->align->HrefValue = "";
        $this->align->TooltipValue = "";

        // text_line1
        $this->text_line1->HrefValue = "";
        $this->text_line1->TooltipValue = "";

        // text_line2
        $this->text_line2->HrefValue = "";
        $this->text_line2->TooltipValue = "";

        // font_name
        $this->font_name->HrefValue = "";
        $this->font_name->TooltipValue = "";

        // line1_size_pt
        $this->line1_size_pt->HrefValue = "";
        $this->line1_size_pt->TooltipValue = "";

        // line2_size_pt
        $this->line2_size_pt->HrefValue = "";
        $this->line2_size_pt->TooltipValue = "";

        // line1_bold
        $this->line1_bold->HrefValue = "";
        $this->line1_bold->TooltipValue = "";

        // line2_bold
        $this->line2_bold->HrefValue = "";
        $this->line2_bold->TooltipValue = "";

        // line1_color
        $this->line1_color->HrefValue = "";
        $this->line1_color->TooltipValue = "";

        // line2_color
        $this->line2_color->HrefValue = "";
        $this->line2_color->TooltipValue = "";

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

        // header_asset_id
        $this->header_asset_id->setupEditAttributes();
        $this->header_asset_id->EditValue = $this->header_asset_id->CurrentValue;

        // asset_code
        $this->asset_code->setupEditAttributes();
        if (!$this->asset_code->Raw) {
            $this->asset_code->CurrentValue = HtmlDecode($this->asset_code->CurrentValue);
        }
        $this->asset_code->EditValue = $this->asset_code->CurrentValue;
        $this->asset_code->PlaceHolder = RemoveHtml($this->asset_code->caption());

        // asset_name
        $this->asset_name->setupEditAttributes();
        if (!$this->asset_name->Raw) {
            $this->asset_name->CurrentValue = HtmlDecode($this->asset_name->CurrentValue);
        }
        $this->asset_name->EditValue = $this->asset_name->CurrentValue;
        $this->asset_name->PlaceHolder = RemoveHtml($this->asset_name->caption());

        // document_asset_type_id
        $this->document_asset_type_id->setupEditAttributes();
        $this->document_asset_type_id->EditValue = $this->document_asset_type_id->CurrentValue;
        $this->document_asset_type_id->PlaceHolder = RemoveHtml($this->document_asset_type_id->caption());
        if (strval($this->document_asset_type_id->EditValue) != "" && is_numeric($this->document_asset_type_id->EditValue)) {
            $this->document_asset_type_id->EditValue = FormatNumber($this->document_asset_type_id->EditValue, null);
        }

        // scope_system_code
        $this->scope_system_code->setupEditAttributes();
        if (!$this->scope_system_code->Raw) {
            $this->scope_system_code->CurrentValue = HtmlDecode($this->scope_system_code->CurrentValue);
        }
        $this->scope_system_code->EditValue = $this->scope_system_code->CurrentValue;
        $this->scope_system_code->PlaceHolder = RemoveHtml($this->scope_system_code->caption());

        // scope_document_code
        $this->scope_document_code->setupEditAttributes();
        if (!$this->scope_document_code->Raw) {
            $this->scope_document_code->CurrentValue = HtmlDecode($this->scope_document_code->CurrentValue);
        }
        $this->scope_document_code->EditValue = $this->scope_document_code->CurrentValue;
        $this->scope_document_code->PlaceHolder = RemoveHtml($this->scope_document_code->caption());

        // file_name
        $this->file_name->setupEditAttributes();
        if (!$this->file_name->Raw) {
            $this->file_name->CurrentValue = HtmlDecode($this->file_name->CurrentValue);
        }
        $this->file_name->EditValue = $this->file_name->CurrentValue;
        $this->file_name->PlaceHolder = RemoveHtml($this->file_name->caption());

        // mime_type
        $this->mime_type->setupEditAttributes();
        if (!$this->mime_type->Raw) {
            $this->mime_type->CurrentValue = HtmlDecode($this->mime_type->CurrentValue);
        }
        $this->mime_type->EditValue = $this->mime_type->CurrentValue;
        $this->mime_type->PlaceHolder = RemoveHtml($this->mime_type->caption());

        // file_size
        $this->file_size->setupEditAttributes();
        $this->file_size->EditValue = $this->file_size->CurrentValue;
        $this->file_size->PlaceHolder = RemoveHtml($this->file_size->caption());
        if (strval($this->file_size->EditValue) != "" && is_numeric($this->file_size->EditValue)) {
            $this->file_size->EditValue = FormatNumber($this->file_size->EditValue, null);
        }

        // checksum_sha256
        $this->checksum_sha256->setupEditAttributes();
        if (!$this->checksum_sha256->Raw) {
            $this->checksum_sha256->CurrentValue = HtmlDecode($this->checksum_sha256->CurrentValue);
        }
        $this->checksum_sha256->EditValue = $this->checksum_sha256->CurrentValue;
        $this->checksum_sha256->PlaceHolder = RemoveHtml($this->checksum_sha256->caption());

        // image_width
        $this->image_width->setupEditAttributes();
        $this->image_width->EditValue = $this->image_width->CurrentValue;
        $this->image_width->PlaceHolder = RemoveHtml($this->image_width->caption());
        if (strval($this->image_width->EditValue) != "" && is_numeric($this->image_width->EditValue)) {
            $this->image_width->EditValue = FormatNumber($this->image_width->EditValue, null);
        }

        // image_height
        $this->image_height->setupEditAttributes();
        $this->image_height->EditValue = $this->image_height->CurrentValue;
        $this->image_height->PlaceHolder = RemoveHtml($this->image_height->caption());
        if (strval($this->image_height->EditValue) != "" && is_numeric($this->image_height->EditValue)) {
            $this->image_height->EditValue = FormatNumber($this->image_height->EditValue, null);
        }

        // render_width
        $this->render_width->setupEditAttributes();
        $this->render_width->EditValue = $this->render_width->CurrentValue;
        $this->render_width->PlaceHolder = RemoveHtml($this->render_width->caption());
        if (strval($this->render_width->EditValue) != "" && is_numeric($this->render_width->EditValue)) {
            $this->render_width->EditValue = FormatNumber($this->render_width->EditValue, null);
        }

        // render_height
        $this->render_height->setupEditAttributes();
        $this->render_height->EditValue = $this->render_height->CurrentValue;
        $this->render_height->PlaceHolder = RemoveHtml($this->render_height->caption());
        if (strval($this->render_height->EditValue) != "" && is_numeric($this->render_height->EditValue)) {
            $this->render_height->EditValue = FormatNumber($this->render_height->EditValue, null);
        }

        // image_data
        $this->image_data->setupEditAttributes();
        $this->image_data->EditValue = $this->image_data->CurrentValue;
        $this->image_data->PlaceHolder = RemoveHtml($this->image_data->caption());

        // is_active
        $this->is_active->EditValue = $this->is_active->options(false);
        $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

        // notes
        $this->notes->setupEditAttributes();
        $this->notes->EditValue = $this->notes->CurrentValue;
        $this->notes->PlaceHolder = RemoveHtml($this->notes->caption());

        // created_at
        $this->created_at->setupEditAttributes();
        $this->created_at->EditValue = FormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        $this->created_at->PlaceHolder = RemoveHtml($this->created_at->caption());

        // updated_at
        $this->updated_at->setupEditAttributes();
        $this->updated_at->EditValue = FormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern());
        $this->updated_at->PlaceHolder = RemoveHtml($this->updated_at->caption());

        // created_by
        $this->created_by->setupEditAttributes();
        $this->created_by->EditValue = $this->created_by->CurrentValue;
        $this->created_by->PlaceHolder = RemoveHtml($this->created_by->caption());
        if (strval($this->created_by->EditValue) != "" && is_numeric($this->created_by->EditValue)) {
            $this->created_by->EditValue = FormatNumber($this->created_by->EditValue, null);
        }

        // updated_by
        $this->updated_by->setupEditAttributes();
        $this->updated_by->EditValue = $this->updated_by->CurrentValue;
        $this->updated_by->PlaceHolder = RemoveHtml($this->updated_by->caption());
        if (strval($this->updated_by->EditValue) != "" && is_numeric($this->updated_by->EditValue)) {
            $this->updated_by->EditValue = FormatNumber($this->updated_by->EditValue, null);
        }

        // header_slot_id
        $this->header_slot_id->setupEditAttributes();
        $this->header_slot_id->EditValue = $this->header_slot_id->CurrentValue;
        $this->header_slot_id->PlaceHolder = RemoveHtml($this->header_slot_id->caption());
        if (strval($this->header_slot_id->EditValue) != "" && is_numeric($this->header_slot_id->EditValue)) {
            $this->header_slot_id->EditValue = FormatNumber($this->header_slot_id->EditValue, null);
        }

        // part_kind
        $this->part_kind->setupEditAttributes();
        if (!$this->part_kind->Raw) {
            $this->part_kind->CurrentValue = HtmlDecode($this->part_kind->CurrentValue);
        }
        $this->part_kind->EditValue = $this->part_kind->CurrentValue;
        $this->part_kind->PlaceHolder = RemoveHtml($this->part_kind->caption());

        // column_pct
        $this->column_pct->setupEditAttributes();
        $this->column_pct->EditValue = $this->column_pct->CurrentValue;
        $this->column_pct->PlaceHolder = RemoveHtml($this->column_pct->caption());
        if (strval($this->column_pct->EditValue) != "" && is_numeric($this->column_pct->EditValue)) {
            $this->column_pct->EditValue = FormatNumber($this->column_pct->EditValue, null);
        }

        // align
        $this->align->setupEditAttributes();
        if (!$this->align->Raw) {
            $this->align->CurrentValue = HtmlDecode($this->align->CurrentValue);
        }
        $this->align->EditValue = $this->align->CurrentValue;
        $this->align->PlaceHolder = RemoveHtml($this->align->caption());

        // text_line1
        $this->text_line1->setupEditAttributes();
        $this->text_line1->EditValue = $this->text_line1->CurrentValue;
        $this->text_line1->PlaceHolder = RemoveHtml($this->text_line1->caption());

        // text_line2
        $this->text_line2->setupEditAttributes();
        $this->text_line2->EditValue = $this->text_line2->CurrentValue;
        $this->text_line2->PlaceHolder = RemoveHtml($this->text_line2->caption());

        // font_name
        $this->font_name->setupEditAttributes();
        if (!$this->font_name->Raw) {
            $this->font_name->CurrentValue = HtmlDecode($this->font_name->CurrentValue);
        }
        $this->font_name->EditValue = $this->font_name->CurrentValue;
        $this->font_name->PlaceHolder = RemoveHtml($this->font_name->caption());

        // line1_size_pt
        $this->line1_size_pt->setupEditAttributes();
        $this->line1_size_pt->EditValue = $this->line1_size_pt->CurrentValue;
        $this->line1_size_pt->PlaceHolder = RemoveHtml($this->line1_size_pt->caption());
        if (strval($this->line1_size_pt->EditValue) != "" && is_numeric($this->line1_size_pt->EditValue)) {
            $this->line1_size_pt->EditValue = FormatNumber($this->line1_size_pt->EditValue, null);
        }

        // line2_size_pt
        $this->line2_size_pt->setupEditAttributes();
        $this->line2_size_pt->EditValue = $this->line2_size_pt->CurrentValue;
        $this->line2_size_pt->PlaceHolder = RemoveHtml($this->line2_size_pt->caption());
        if (strval($this->line2_size_pt->EditValue) != "" && is_numeric($this->line2_size_pt->EditValue)) {
            $this->line2_size_pt->EditValue = FormatNumber($this->line2_size_pt->EditValue, null);
        }

        // line1_bold
        $this->line1_bold->EditValue = $this->line1_bold->options(false);
        $this->line1_bold->PlaceHolder = RemoveHtml($this->line1_bold->caption());

        // line2_bold
        $this->line2_bold->EditValue = $this->line2_bold->options(false);
        $this->line2_bold->PlaceHolder = RemoveHtml($this->line2_bold->caption());

        // line1_color
        $this->line1_color->setupEditAttributes();
        if (!$this->line1_color->Raw) {
            $this->line1_color->CurrentValue = HtmlDecode($this->line1_color->CurrentValue);
        }
        $this->line1_color->EditValue = $this->line1_color->CurrentValue;
        $this->line1_color->PlaceHolder = RemoveHtml($this->line1_color->caption());

        // line2_color
        $this->line2_color->setupEditAttributes();
        if (!$this->line2_color->Raw) {
            $this->line2_color->CurrentValue = HtmlDecode($this->line2_color->CurrentValue);
        }
        $this->line2_color->EditValue = $this->line2_color->CurrentValue;
        $this->line2_color->PlaceHolder = RemoveHtml($this->line2_color->caption());

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
                    $doc->exportCaption($this->header_asset_id);
                    $doc->exportCaption($this->asset_code);
                    $doc->exportCaption($this->asset_name);
                    $doc->exportCaption($this->document_asset_type_id);
                    $doc->exportCaption($this->scope_system_code);
                    $doc->exportCaption($this->scope_document_code);
                    $doc->exportCaption($this->file_name);
                    $doc->exportCaption($this->mime_type);
                    $doc->exportCaption($this->file_size);
                    $doc->exportCaption($this->checksum_sha256);
                    $doc->exportCaption($this->image_width);
                    $doc->exportCaption($this->image_height);
                    $doc->exportCaption($this->render_width);
                    $doc->exportCaption($this->render_height);
                    $doc->exportCaption($this->image_data);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->notes);
                    $doc->exportCaption($this->created_at);
                    $doc->exportCaption($this->updated_at);
                    $doc->exportCaption($this->created_by);
                    $doc->exportCaption($this->updated_by);
                    $doc->exportCaption($this->header_slot_id);
                    $doc->exportCaption($this->part_kind);
                    $doc->exportCaption($this->column_pct);
                    $doc->exportCaption($this->align);
                    $doc->exportCaption($this->text_line1);
                    $doc->exportCaption($this->text_line2);
                    $doc->exportCaption($this->font_name);
                    $doc->exportCaption($this->line1_size_pt);
                    $doc->exportCaption($this->line2_size_pt);
                    $doc->exportCaption($this->line1_bold);
                    $doc->exportCaption($this->line2_bold);
                    $doc->exportCaption($this->line1_color);
                    $doc->exportCaption($this->line2_color);
                } else {
                    $doc->exportCaption($this->header_asset_id);
                    $doc->exportCaption($this->asset_code);
                    $doc->exportCaption($this->asset_name);
                    $doc->exportCaption($this->document_asset_type_id);
                    $doc->exportCaption($this->scope_system_code);
                    $doc->exportCaption($this->scope_document_code);
                    $doc->exportCaption($this->file_name);
                    $doc->exportCaption($this->mime_type);
                    $doc->exportCaption($this->file_size);
                    $doc->exportCaption($this->checksum_sha256);
                    $doc->exportCaption($this->image_width);
                    $doc->exportCaption($this->image_height);
                    $doc->exportCaption($this->render_width);
                    $doc->exportCaption($this->render_height);
                    $doc->exportCaption($this->is_active);
                    $doc->exportCaption($this->created_at);
                    $doc->exportCaption($this->updated_at);
                    $doc->exportCaption($this->created_by);
                    $doc->exportCaption($this->updated_by);
                    $doc->exportCaption($this->header_slot_id);
                    $doc->exportCaption($this->part_kind);
                    $doc->exportCaption($this->column_pct);
                    $doc->exportCaption($this->align);
                    $doc->exportCaption($this->font_name);
                    $doc->exportCaption($this->line1_size_pt);
                    $doc->exportCaption($this->line2_size_pt);
                    $doc->exportCaption($this->line1_bold);
                    $doc->exportCaption($this->line2_bold);
                    $doc->exportCaption($this->line1_color);
                    $doc->exportCaption($this->line2_color);
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
                        $doc->exportField($this->header_asset_id);
                        $doc->exportField($this->asset_code);
                        $doc->exportField($this->asset_name);
                        $doc->exportField($this->document_asset_type_id);
                        $doc->exportField($this->scope_system_code);
                        $doc->exportField($this->scope_document_code);
                        $doc->exportField($this->file_name);
                        $doc->exportField($this->mime_type);
                        $doc->exportField($this->file_size);
                        $doc->exportField($this->checksum_sha256);
                        $doc->exportField($this->image_width);
                        $doc->exportField($this->image_height);
                        $doc->exportField($this->render_width);
                        $doc->exportField($this->render_height);
                        $doc->exportField($this->image_data);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->notes);
                        $doc->exportField($this->created_at);
                        $doc->exportField($this->updated_at);
                        $doc->exportField($this->created_by);
                        $doc->exportField($this->updated_by);
                        $doc->exportField($this->header_slot_id);
                        $doc->exportField($this->part_kind);
                        $doc->exportField($this->column_pct);
                        $doc->exportField($this->align);
                        $doc->exportField($this->text_line1);
                        $doc->exportField($this->text_line2);
                        $doc->exportField($this->font_name);
                        $doc->exportField($this->line1_size_pt);
                        $doc->exportField($this->line2_size_pt);
                        $doc->exportField($this->line1_bold);
                        $doc->exportField($this->line2_bold);
                        $doc->exportField($this->line1_color);
                        $doc->exportField($this->line2_color);
                    } else {
                        $doc->exportField($this->header_asset_id);
                        $doc->exportField($this->asset_code);
                        $doc->exportField($this->asset_name);
                        $doc->exportField($this->document_asset_type_id);
                        $doc->exportField($this->scope_system_code);
                        $doc->exportField($this->scope_document_code);
                        $doc->exportField($this->file_name);
                        $doc->exportField($this->mime_type);
                        $doc->exportField($this->file_size);
                        $doc->exportField($this->checksum_sha256);
                        $doc->exportField($this->image_width);
                        $doc->exportField($this->image_height);
                        $doc->exportField($this->render_width);
                        $doc->exportField($this->render_height);
                        $doc->exportField($this->is_active);
                        $doc->exportField($this->created_at);
                        $doc->exportField($this->updated_at);
                        $doc->exportField($this->created_by);
                        $doc->exportField($this->updated_by);
                        $doc->exportField($this->header_slot_id);
                        $doc->exportField($this->part_kind);
                        $doc->exportField($this->column_pct);
                        $doc->exportField($this->align);
                        $doc->exportField($this->font_name);
                        $doc->exportField($this->line1_size_pt);
                        $doc->exportField($this->line2_size_pt);
                        $doc->exportField($this->line1_bold);
                        $doc->exportField($this->line2_bold);
                        $doc->exportField($this->line1_color);
                        $doc->exportField($this->line2_color);
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
