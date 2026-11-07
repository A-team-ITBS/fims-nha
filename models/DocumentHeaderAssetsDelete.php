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
 * Page class
 */
class DocumentHeaderAssetsDelete extends DocumentHeaderAssets
{
    use MessagesTrait;

    // Page ID
    public $PageID = "delete";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "DocumentHeaderAssetsDelete";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "DocumentHeaderAssetsDelete";

    // Page headings
    public $Heading = "";
    public $Subheading = "";
    public $PageHeader;
    public $PageFooter;

    // Page layout
    public $UseLayout = true;

    // Page terminated
    private $terminated = false;

    // Page heading
    public function pageHeading()
    {
        global $Language;
        if ($this->Heading != "") {
            return $this->Heading;
        }
        if (method_exists($this, "tableCaption")) {
            return $this->tableCaption();
        }
        return "";
    }

    // Page subheading
    public function pageSubheading()
    {
        global $Language;
        if ($this->Subheading != "") {
            return $this->Subheading;
        }
        if ($this->TableName) {
            return $Language->phrase($this->PageID);
        }
        return "";
    }

    // Page name
    public function pageName()
    {
        return CurrentPageName();
    }

    // Page URL
    public function pageUrl($withArgs = true)
    {
        $route = GetRoute();
        $args = RemoveXss($route->getArguments());
        if (!$withArgs) {
            foreach ($args as $key => &$val) {
                $val = "";
            }
            unset($val);
        }
        return rtrim(UrlFor($route->getName(), $args), "/") . "?";
    }

    // Show Page Header
    public function showPageHeader()
    {
        $header = $this->PageHeader;
        $this->pageDataRendering($header);
        if ($header != "") { // Header exists, display
            echo '<div id="ew-page-header">' . $header . '</div>';
        }
    }

    // Show Page Footer
    public function showPageFooter()
    {
        $footer = $this->PageFooter;
        $this->pageDataRendered($footer);
        if ($footer != "") { // Footer exists, display
            echo '<div id="ew-page-footer">' . $footer . '</div>';
        }
    }

    // Set field visibility
    public function setVisibility()
    {
        $this->header_asset_id->setVisibility();
        $this->asset_code->setVisibility();
        $this->asset_name->setVisibility();
        $this->document_asset_type_id->setVisibility();
        $this->scope_system_code->setVisibility();
        $this->scope_document_code->setVisibility();
        $this->file_name->setVisibility();
        $this->mime_type->setVisibility();
        $this->file_size->setVisibility();
        $this->checksum_sha256->setVisibility();
        $this->image_width->setVisibility();
        $this->image_height->setVisibility();
        $this->render_width->setVisibility();
        $this->render_height->setVisibility();
        $this->image_data->Visible = false;
        $this->is_active->setVisibility();
        $this->notes->Visible = false;
        $this->created_at->setVisibility();
        $this->updated_at->setVisibility();
        $this->created_by->setVisibility();
        $this->updated_by->setVisibility();
        $this->header_slot_id->setVisibility();
        $this->part_kind->setVisibility();
        $this->column_pct->setVisibility();
        $this->align->setVisibility();
        $this->text_line1->Visible = false;
        $this->text_line2->Visible = false;
        $this->font_name->setVisibility();
        $this->line1_size_pt->setVisibility();
        $this->line2_size_pt->setVisibility();
        $this->line1_bold->setVisibility();
        $this->line2_bold->setVisibility();
        $this->line1_color->setVisibility();
        $this->line2_color->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'document_header_assets';
        $this->TableName = 'document_header_assets';

        // Table CSS class
        $this->TableClass = "table table-bordered table-hover table-sm ew-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (document_header_assets)
        if (!isset($GLOBALS["document_header_assets"]) || $GLOBALS["document_header_assets"]::class == PROJECT_NAMESPACE . "document_header_assets") {
            $GLOBALS["document_header_assets"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'document_header_assets');
        }

        // Start timer
        $DebugTimer = Container("debug.timer");

        // Debug message
        LoadDebugMessage();

        // Open connection
        $GLOBALS["Conn"] ??= $this->getConnection();

        // User table object
        $UserTable = Container("usertable");
    }

    // Get content from stream
    public function getContents(): string
    {
        global $Response;
        return $Response?->getBody() ?? ob_get_clean();
    }

    // Is lookup
    public function isLookup()
    {
        return SameText(Route(0), Config("API_LOOKUP_ACTION"));
    }

    // Is AutoFill
    public function isAutoFill()
    {
        return $this->isLookup() && SameText(Post("ajax"), "autofill");
    }

    // Is AutoSuggest
    public function isAutoSuggest()
    {
        return $this->isLookup() && SameText(Post("ajax"), "autosuggest");
    }

    // Is modal lookup
    public function isModalLookup()
    {
        return $this->isLookup() && SameText(Post("ajax"), "modal");
    }

    // Is terminated
    public function isTerminated()
    {
        return $this->terminated;
    }

    /**
     * Terminate page
     *
     * @param string $url URL for direction
     * @return void
     */
    public function terminate($url = "")
    {
        if ($this->terminated) {
            return;
        }
        global $TempImages, $DashboardReport, $Response;

        // Page is terminated
        $this->terminated = true;

        // Page Unload event
        if (method_exists($this, "pageUnload")) {
            $this->pageUnload();
        }
        DispatchEvent(new PageUnloadedEvent($this), PageUnloadedEvent::NAME);
        if (!IsApi() && method_exists($this, "pageRedirecting")) {
            $this->pageRedirecting($url);
        }

        // Close connection
        CloseConnections();

        // Return for API
        if (IsApi()) {
            $res = $url === true;
            if (!$res) { // Show response for API
                $ar = array_merge($this->getMessages(), $url ? ["url" => GetUrl($url)] : []);
                WriteJson($ar);
            }
            $this->clearMessages(); // Clear messages for API request
            return;
        } else { // Check if response is JSON
            if (WithJsonResponse()) { // With JSON response
                $this->clearMessages();
                return;
            }
        }

        // Go to URL if specified
        if ($url != "") {
            if (!Config("DEBUG") && ob_get_length()) {
                ob_end_clean();
            }
            SaveDebugMessage();
            Redirect(GetUrl($url));
        }
        return; // Return to controller
    }

    // Get records from result set
    protected function getRecordsFromRecordset($rs, $current = false)
    {
        $rows = [];
        if (is_object($rs)) { // Result set
            while ($row = $rs->fetch()) {
                $this->loadRowValues($row); // Set up DbValue/CurrentValue
                $row = $this->getRecordFromArray($row);
                if ($current) {
                    return $row;
                } else {
                    $rows[] = $row;
                }
            }
        } elseif (is_array($rs)) {
            foreach ($rs as $ar) {
                $row = $this->getRecordFromArray($ar);
                if ($current) {
                    return $row;
                } else {
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }

    // Get record from array
    protected function getRecordFromArray($ar)
    {
        $row = [];
        if (is_array($ar)) {
            foreach ($ar as $fldname => $val) {
                if (array_key_exists($fldname, $this->Fields) && ($this->Fields[$fldname]->Visible || $this->Fields[$fldname]->IsPrimaryKey)) { // Primary key or Visible
                    $fld = &$this->Fields[$fldname];
                    if ($fld->HtmlTag == "FILE") { // Upload field
                        if (EmptyValue($val)) {
                            $row[$fldname] = null;
                        } else {
                            if ($fld->DataType == DataType::BLOB) {
                                $url = FullUrl(GetApiUrl(Config("API_FILE_ACTION") .
                                    "/" . $fld->TableVar . "/" . $fld->Param . "/" . rawurlencode($this->getRecordKeyValue($ar))));
                                $row[$fldname] = ["type" => ContentType($val), "url" => $url, "name" => $fld->Param . ContentExtension($val)];
                            } elseif (!$fld->UploadMultiple || !ContainsString($val, Config("MULTIPLE_UPLOAD_SEPARATOR"))) { // Single file
                                $url = FullUrl(GetApiUrl(Config("API_FILE_ACTION") .
                                    "/" . $fld->TableVar . "/" . Encrypt($fld->physicalUploadPath() . $val)));
                                $row[$fldname] = ["type" => MimeContentType($val), "url" => $url, "name" => $val];
                            } else { // Multiple files
                                $files = explode(Config("MULTIPLE_UPLOAD_SEPARATOR"), $val);
                                $ar = [];
                                foreach ($files as $file) {
                                    $url = FullUrl(GetApiUrl(Config("API_FILE_ACTION") .
                                        "/" . $fld->TableVar . "/" . Encrypt($fld->physicalUploadPath() . $file)));
                                    if (!EmptyValue($file)) {
                                        $ar[] = ["type" => MimeContentType($file), "url" => $url, "name" => $file];
                                    }
                                }
                                $row[$fldname] = $ar;
                            }
                        }
                    } else {
                        $row[$fldname] = $val;
                    }
                }
            }
        }
        return $row;
    }

    // Get record key value from array
    protected function getRecordKeyValue($ar)
    {
        $key = "";
        if (is_array($ar)) {
            $key .= @$ar['header_asset_id'];
        }
        return $key;
    }

    /**
     * Hide fields for add/edit
     *
     * @return void
     */
    protected function hideFieldsForAddEdit()
    {
        if ($this->isAdd() || $this->isCopy() || $this->isGridAdd()) {
            $this->header_asset_id->Visible = false;
        }
    }
    public $DbMasterFilter = "";
    public $DbDetailFilter = "";
    public $StartRecord;
    public $TotalRecords = 0;
    public $RecordCount;
    public $RecKeys = [];
    public $StartRowCount = 1;

    /**
     * Page run
     *
     * @return void
     */
    public function run()
    {
        global $ExportType, $Language, $Security, $CurrentForm;

        // Use layout
        $this->UseLayout = $this->UseLayout && ConvertToBool(Param(Config("PAGE_LAYOUT"), true));

        // View
        $this->View = Get(Config("VIEW"));

        // Load user profile
        if (IsLoggedIn()) {
            Profile()->setUserName(CurrentUserName())->loadFromStorage();

            // Force logout user
            if (!IsSysAdmin() && Profile()->isForceLogout(session_id())) {
                $this->terminate("logout");
                return;
            }

            // Check if valid user and update last accessed time
            if (!IsSysAdmin() && !IsPasswordExpired() && !Profile()->isValidUser(session_id(), false)) {
                $this->terminate("logout"); // Handle as session expired
                return;
            }
        }
        $this->CurrentAction = Param("action"); // Set up current action
        $this->setVisibility();

        // Set lookup cache
        if (!in_array($this->PageID, Config("LOOKUP_CACHE_PAGE_IDS"))) {
            $this->setUseLookupCache(false);
        }

        // Global Page Loading event (in userfn*.php)
        DispatchEvent(new PageLoadingEvent($this), PageLoadingEvent::NAME);

        // Page Load event
        if (method_exists($this, "pageLoad")) {
            $this->pageLoad();
        }

        // Hide fields for add/edit
        if (!$this->UseAjaxActions) {
            $this->hideFieldsForAddEdit();
        }
        // Use inline delete
        if ($this->UseAjaxActions) {
            $this->InlineDelete = true;
        }

        // Set up lookup cache
        $this->setupLookupOptions($this->is_active);
        $this->setupLookupOptions($this->line1_bold);
        $this->setupLookupOptions($this->line2_bold);

        // Set up Breadcrumb
        $this->setupBreadcrumb();

        // Load key parameters
        $this->RecKeys = $this->getRecordKeys(); // Load record keys
        $filter = $this->getFilterFromRecordKeys();
        if ($filter == "") {
            $this->terminate("DocumentHeaderAssetsList"); // Prevent SQL injection, return to list
            return;
        }

        // Set up filter (WHERE Clause)
        $this->CurrentFilter = $filter;

        // Get action
        if (IsApi()) {
            $this->CurrentAction = "delete"; // Delete record directly
        } elseif (Param("action") !== null) {
            $this->CurrentAction = Param("action") == "delete" ? "delete" : "show";
        } else {
            $this->CurrentAction = $this->InlineDelete ?
                "delete" : // Delete record directly
                "show"; // Display record
        }
        if ($this->isDelete()) {
            $this->SendEmail = true; // Send email on delete success
            if ($this->deleteRows()) { // Delete rows
                if ($this->getSuccessMessage() == "") {
                    $this->setSuccessMessage($Language->phrase("DeleteSuccess")); // Set up success message
                }
                if (IsJsonResponse()) {
                    $this->terminate(true);
                    return;
                } else {
                    $this->terminate($this->getReturnUrl()); // Return to caller
                    return;
                }
            } else { // Delete failed
                if (IsJsonResponse()) {
                    $this->terminate();
                    return;
                }
                // Return JSON error message if UseAjaxActions
                if ($this->UseAjaxActions) {
                    WriteJson(["success" => false, "error" => $this->getFailureMessage()]);
                    $this->clearFailureMessage();
                    $this->terminate();
                    return;
                }
                if ($this->InlineDelete) {
                    $this->terminate($this->getReturnUrl()); // Return to caller
                    return;
                } else {
                    $this->CurrentAction = "show"; // Display record
                }
            }
        }
        if ($this->isShow()) { // Load records for display
            $this->Recordset = $this->loadRecordset();
            if ($this->TotalRecords <= 0) { // No record found, exit
                $this->Recordset?->free();
                $this->terminate("DocumentHeaderAssetsList"); // Return to list
                return;
            }
        }

        // Set LoginStatus / Page_Rendering / Page_Render
        if (!IsApi() && !$this->isTerminated()) {
            // Setup login status
            SetupLoginStatus();

            // Pass login status to client side
            SetClientVar("login", LoginStatus());

            // Global Page Rendering event (in userfn*.php)
            DispatchEvent(new PageRenderingEvent($this), PageRenderingEvent::NAME);

            // Page Render event
            if (method_exists($this, "pageRender")) {
                $this->pageRender();
            }

            // Render search option
            if (method_exists($this, "renderSearchOptions")) {
                $this->renderSearchOptions();
            }
        }
    }

    /**
     * Load result set
     *
     * @param int $offset Offset
     * @param int $rowcnt Maximum number of rows
     * @return Doctrine\DBAL\Result Result
     */
    public function loadRecordset($offset = -1, $rowcnt = -1)
    {
        // Load List page SQL (QueryBuilder)
        $sql = $this->getListSql();

        // Load result set
        if ($offset > -1) {
            $sql->setFirstResult($offset);
        }
        if ($rowcnt > 0) {
            $sql->setMaxResults($rowcnt);
        }
        $result = $sql->executeQuery();
        if (property_exists($this, "TotalRecords") && $rowcnt < 0) {
            $this->TotalRecords = $result->rowCount();
            if ($this->TotalRecords <= 0) { // Handle database drivers that does not return rowCount()
                $this->TotalRecords = $this->getRecordCount($this->getListSql());
            }
        }

        // Call Recordset Selected event
        $this->recordsetSelected($result);
        return $result;
    }

    /**
     * Load records as associative array
     *
     * @param int $offset Offset
     * @param int $rowcnt Maximum number of rows
     * @return void
     */
    public function loadRows($offset = -1, $rowcnt = -1)
    {
        // Load List page SQL (QueryBuilder)
        $sql = $this->getListSql();

        // Load result set
        if ($offset > -1) {
            $sql->setFirstResult($offset);
        }
        if ($rowcnt > 0) {
            $sql->setMaxResults($rowcnt);
        }
        $result = $sql->executeQuery();
        return $result->fetchAllAssociative();
    }

    /**
     * Load row based on key values
     *
     * @return void
     */
    public function loadRow()
    {
        global $Security, $Language;
        $filter = $this->getRecordFilter();

        // Call Row Selecting event
        $this->rowSelecting($filter);

        // Load SQL based on filter
        $this->CurrentFilter = $filter;
        $sql = $this->getCurrentSql();
        $conn = $this->getConnection();
        $res = false;
        $row = $conn->fetchAssociative($sql);
        if ($row) {
            $res = true;
            $this->loadRowValues($row); // Load row values
        }
        return $res;
    }

    /**
     * Load row values from result set or record
     *
     * @param array $row Record
     * @return void
     */
    public function loadRowValues($row = null)
    {
        $row = is_array($row) ? $row : $this->newRow();

        // Call Row Selected event
        $this->rowSelected($row);
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
        $this->is_active->setDbValue((ConvertToBool($row['is_active']) ? "1" : "0"));
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
        $this->line1_bold->setDbValue((ConvertToBool($row['line1_bold']) ? "1" : "0"));
        $this->line2_bold->setDbValue((ConvertToBool($row['line2_bold']) ? "1" : "0"));
        $this->line1_color->setDbValue($row['line1_color']);
        $this->line2_color->setDbValue($row['line2_color']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['header_asset_id'] = $this->header_asset_id->DefaultValue;
        $row['asset_code'] = $this->asset_code->DefaultValue;
        $row['asset_name'] = $this->asset_name->DefaultValue;
        $row['document_asset_type_id'] = $this->document_asset_type_id->DefaultValue;
        $row['scope_system_code'] = $this->scope_system_code->DefaultValue;
        $row['scope_document_code'] = $this->scope_document_code->DefaultValue;
        $row['file_name'] = $this->file_name->DefaultValue;
        $row['mime_type'] = $this->mime_type->DefaultValue;
        $row['file_size'] = $this->file_size->DefaultValue;
        $row['checksum_sha256'] = $this->checksum_sha256->DefaultValue;
        $row['image_width'] = $this->image_width->DefaultValue;
        $row['image_height'] = $this->image_height->DefaultValue;
        $row['render_width'] = $this->render_width->DefaultValue;
        $row['render_height'] = $this->render_height->DefaultValue;
        $row['image_data'] = $this->image_data->DefaultValue;
        $row['is_active'] = $this->is_active->DefaultValue;
        $row['notes'] = $this->notes->DefaultValue;
        $row['created_at'] = $this->created_at->DefaultValue;
        $row['updated_at'] = $this->updated_at->DefaultValue;
        $row['created_by'] = $this->created_by->DefaultValue;
        $row['updated_by'] = $this->updated_by->DefaultValue;
        $row['header_slot_id'] = $this->header_slot_id->DefaultValue;
        $row['part_kind'] = $this->part_kind->DefaultValue;
        $row['column_pct'] = $this->column_pct->DefaultValue;
        $row['align'] = $this->align->DefaultValue;
        $row['text_line1'] = $this->text_line1->DefaultValue;
        $row['text_line2'] = $this->text_line2->DefaultValue;
        $row['font_name'] = $this->font_name->DefaultValue;
        $row['line1_size_pt'] = $this->line1_size_pt->DefaultValue;
        $row['line2_size_pt'] = $this->line2_size_pt->DefaultValue;
        $row['line1_bold'] = $this->line1_bold->DefaultValue;
        $row['line2_bold'] = $this->line2_bold->DefaultValue;
        $row['line1_color'] = $this->line1_color->DefaultValue;
        $row['line2_color'] = $this->line2_color->DefaultValue;
        return $row;
    }

    // Render row values based on field settings
    public function renderRow()
    {
        global $Security, $Language, $CurrentLanguage;

        // Initialize URLs

        // Call Row_Rendering event
        $this->rowRendering();

        // Common render codes for all row types

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

        // View row
        if ($this->RowType == RowType::VIEW) {
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

            // is_active
            $this->is_active->HrefValue = "";
            $this->is_active->TooltipValue = "";

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
        }

        // Call Row Rendered event
        if ($this->RowType != RowType::AGGREGATEINIT) {
            $this->rowRendered();
        }
    }

    // Delete records based on current filter
    protected function deleteRows()
    {
        global $Language, $Security;
        if (!$Security->canDelete()) {
            $this->setFailureMessage($Language->phrase("NoDeletePermission")); // No delete permission
            return false;
        }
        $sql = $this->getCurrentSql();
        $conn = $this->getConnection();
        $rows = $conn->fetchAllAssociative($sql);
        if (count($rows) == 0) {
            $this->setFailureMessage($Language->phrase("NoRecord")); // No record found
            return false;
        }
        if ($this->UseTransaction) {
            $conn->beginTransaction();
        }

        // Clone old rows
        $rsold = $rows;
        $successKeys = [];
        $failKeys = [];
        foreach ($rsold as $row) {
            $thisKey = "";
            if ($thisKey != "") {
                $thisKey .= Config("COMPOSITE_KEY_SEPARATOR");
            }
            $thisKey .= $row['header_asset_id'];

            // Call row deleting event
            $deleteRow = $this->rowDeleting($row);
            if ($deleteRow) { // Delete
                $deleteRow = $this->delete($row);
                if (!$deleteRow && !EmptyValue($this->DbErrorMessage)) { // Show database error
                    $this->setFailureMessage($this->DbErrorMessage);
                }
            }
            if ($deleteRow === false) {
                if ($this->UseTransaction) {
                    $successKeys = []; // Reset success keys
                    break;
                }
                $failKeys[] = $thisKey;
            } else {
                if (Config("DELETE_UPLOADED_FILES")) { // Delete old files
                    $this->deleteUploadedFiles($row);
                }

                // Call Row Deleted event
                $this->rowDeleted($row);
                $successKeys[] = $thisKey;
            }
        }

        // Any records deleted
        $deleteRows = count($successKeys) > 0;
        if (!$deleteRows) {
            // Set up error message
            if ($this->getSuccessMessage() != "" || $this->getFailureMessage() != "") {
                // Use the message, do nothing
            } elseif ($this->CancelMessage != "") {
                $this->setFailureMessage($this->CancelMessage);
                $this->CancelMessage = "";
            } else {
                $this->setFailureMessage($Language->phrase("DeleteCancelled"));
            }
        }
        if ($deleteRows) {
            if ($this->UseTransaction) { // Commit transaction
                if ($conn->isTransactionActive()) {
                    $conn->commit();
                }
            }

            // Set warning message if delete some records failed
            if (count($failKeys) > 0) {
                $this->setWarningMessage(str_replace("%k", explode(", ", $failKeys), $Language->phrase("DeleteRecordsFailed")));
            }
        } else {
            if ($this->UseTransaction) { // Rollback transaction
                if ($conn->isTransactionActive()) {
                    $conn->rollback();
                }
            }
        }

        // Write JSON response
        if ((IsJsonResponse() || ConvertToBool(Param("infinitescroll"))) && $deleteRows) {
            $rows = $this->getRecordsFromRecordset($rsold);
            $table = $this->TableVar;
            if (Param("key_m") === null) { // Single delete
                $rows = $rows[0]; // Return object
            }
            WriteJson(["success" => true, "action" => Config("API_DELETE_ACTION"), $table => $rows]);
        }
        return $deleteRows;
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("DocumentHeaderAssetsList"), "", $this->TableVar, true);
        $pageId = "delete";
        $Breadcrumb->add("delete", $pageId, $url);
    }

    // Setup lookup options
    public function setupLookupOptions($fld)
    {
        if ($fld->Lookup && $fld->Lookup->Options === null) {
            // Get default connection and filter
            $conn = $this->getConnection();
            $lookupFilter = "";

            // No need to check any more
            $fld->Lookup->Options = [];

            // Set up lookup SQL and connection
            switch ($fld->FieldVar) {
                case "x_is_active":
                    break;
                case "x_line1_bold":
                    break;
                case "x_line2_bold":
                    break;
                default:
                    $lookupFilter = "";
                    break;
            }

            // Always call to Lookup->getSql so that user can setup Lookup->Options in Lookup_Selecting server event
            $sql = $fld->Lookup->getSql(false, "", $lookupFilter, $this);

            // Set up lookup cache
            if (!$fld->hasLookupOptions() && $fld->UseLookupCache && $sql != "" && count($fld->Lookup->Options) == 0 && count($fld->Lookup->FilterFields) == 0) {
                $totalCnt = $this->getRecordCount($sql, $conn);
                if ($totalCnt > $fld->LookupCacheCount) { // Total count > cache count, do not cache
                    return;
                }
                $rows = $conn->executeQuery($sql)->fetchAll();
                $ar = [];
                foreach ($rows as $row) {
                    $row = $fld->Lookup->renderViewRow($row, Container($fld->Lookup->LinkTable));
                    $key = $row["lf"];
                    if (IsFloatType($fld->Type)) { // Handle float field
                        $key = (float)$key;
                    }
                    $ar[strval($key)] = $row;
                }
                $fld->Lookup->Options = $ar;
            }
        }
    }

    // Page Load event
    public function pageLoad()
    {
        //Log("Page Load");
    }

    // Page Unload event
    public function pageUnload()
    {
        //Log("Page Unload");
    }

    // Page Redirecting event
    public function pageRedirecting(&$url)
    {
        // Example:
        //$url = "your URL";
    }

    // Message Showing event
    // $type = ''|'success'|'failure'|'warning'
    public function messageShowing(&$msg, $type)
    {
        if ($type == "success") {
            //$msg = "your success message";
        } elseif ($type == "failure") {
            //$msg = "your failure message";
        } elseif ($type == "warning") {
            //$msg = "your warning message";
        } else {
            //$msg = "your message";
        }
    }

    // Page Render event
    public function pageRender()
    {
        //Log("Page Render");
    }

    // Page Data Rendering event
    public function pageDataRendering(&$header)
    {
        // Example:
        //$header = "your header";
    }

    // Page Data Rendered event
    public function pageDataRendered(&$footer)
    {
        // Example:
        //$footer = "your footer";
    }

    // Page Breaking event
    public function pageBreaking(&$break, &$content)
    {
        // Example:
        //$break = false; // Skip page break, or
        //$content = "<div style=\"break-after:page;\"></div>"; // Modify page break content
    }
}
