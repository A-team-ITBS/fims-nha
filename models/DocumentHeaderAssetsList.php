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
class DocumentHeaderAssetsList extends DocumentHeaderAssets
{
    use MessagesTrait;

    // Page ID
    public $PageID = "list";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "DocumentHeaderAssetsList";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // Grid form hidden field names
    public $FormName = "fdocument_header_assetslist";
    public $FormActionName = "";
    public $FormBlankRowName = "";
    public $FormKeyCountName = "";

    // CSS class/style
    public $CurrentPageName = "DocumentHeaderAssetsList";

    // Page URLs
    public $AddUrl;
    public $EditUrl;
    public $DeleteUrl;
    public $ViewUrl;
    public $CopyUrl;
    public $ListUrl;

    // Update URLs
    public $InlineAddUrl;
    public $InlineCopyUrl;
    public $InlineEditUrl;
    public $GridAddUrl;
    public $GridEditUrl;
    public $MultiEditUrl;
    public $MultiDeleteUrl;
    public $MultiUpdateUrl;

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
        $this->FormActionName = Config("FORM_ROW_ACTION_NAME");
        $this->FormBlankRowName = Config("FORM_BLANK_ROW_NAME");
        $this->FormKeyCountName = Config("FORM_KEY_COUNT_NAME");
        $this->TableVar = 'document_header_assets';
        $this->TableName = 'document_header_assets';

        // Table CSS class
        $this->TableClass = "table table-bordered table-hover table-sm ew-table";

        // CSS class name as context
        $this->ContextClass = CheckClassName($this->TableVar);
        AppendClass($this->TableGridClass, $this->ContextClass);

        // Fixed header table
        if (!$this->UseCustomTemplate) {
            $this->setFixedHeaderTable(Config("USE_FIXED_HEADER_TABLE"), Config("FIXED_HEADER_TABLE_HEIGHT"));
        }

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (document_header_assets)
        if (!isset($GLOBALS["document_header_assets"]) || $GLOBALS["document_header_assets"]::class == PROJECT_NAMESPACE . "document_header_assets") {
            $GLOBALS["document_header_assets"] = &$this;
        }

        // Page URL
        $pageUrl = $this->pageUrl(false);

        // Initialize URLs
        $this->AddUrl = "DocumentHeaderAssetsAdd";
        $this->InlineAddUrl = $pageUrl . "action=add";
        $this->GridAddUrl = $pageUrl . "action=gridadd";
        $this->GridEditUrl = $pageUrl . "action=gridedit";
        $this->MultiEditUrl = $pageUrl . "action=multiedit";
        $this->MultiDeleteUrl = "DocumentHeaderAssetsDelete";
        $this->MultiUpdateUrl = "DocumentHeaderAssetsUpdate";

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

        // List options
        $this->ListOptions = new ListOptions(Tag: "td", TableVar: $this->TableVar);

        // Export options
        $this->ExportOptions = new ListOptions(TagClassName: "ew-export-option");

        // Import options
        $this->ImportOptions = new ListOptions(TagClassName: "ew-import-option");

        // Other options
        $this->OtherOptions = new ListOptionsArray();

        // Grid-Add/Edit
        $this->OtherOptions["addedit"] = new ListOptions(
            TagClassName: "ew-add-edit-option",
            UseDropDownButton: false,
            DropDownButtonPhrase: $Language->phrase("ButtonAddEdit"),
            UseButtonGroup: true
        );

        // Detail tables
        $this->OtherOptions["detail"] = new ListOptions(TagClassName: "ew-detail-option");
        // Actions
        $this->OtherOptions["action"] = new ListOptions(TagClassName: "ew-action-option");

        // Column visibility
        $this->OtherOptions["column"] = new ListOptions(
            TableVar: $this->TableVar,
            TagClassName: "ew-column-option",
            ButtonGroupClass: "ew-column-dropdown",
            UseDropDownButton: true,
            DropDownButtonPhrase: $Language->phrase("Columns"),
            DropDownAutoClose: "outside",
            UseButtonGroup: false
        );

        // Filter options
        $this->FilterOptions = new ListOptions(TagClassName: "ew-filter-option");

        // List actions
        $this->ListActions = new ListActions();
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

            // Handle modal response
            if ($this->IsModal) { // Show as modal
                $pageName = GetPageName($url);
                $result = ["url" => GetUrl($url), "modal" => "1"];  // Assume return to modal for simplicity
                if (!SameString($pageName, GetPageName($this->getListUrl()))) { // Not List page
                    $result["caption"] = $this->getModalCaption($pageName);
                    $result["view"] = SameString($pageName, "DocumentHeaderAssetsView"); // If View page, no primary button
                } else { // List page
                    $result["error"] = $this->getFailureMessage(); // List page should not be shown as modal => error
                    $this->clearFailureMessage();
                }
                WriteJson($result);
            } else {
                SaveDebugMessage();
                Redirect(GetUrl($url));
            }
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
                        if ($fld->DataType == DataType::MEMO && $fld->MemoMaxLength > 0) {
                            $val = TruncateMemo($val, $fld->MemoMaxLength, $fld->TruncateMemoRemoveHtml);
                        }
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

    // Lookup data
    public function lookup(array $req = [], bool $response = true)
    {
        global $Language, $Security;

        // Get lookup object
        $fieldName = $req["field"] ?? null;
        if (!$fieldName) {
            return [];
        }
        $fld = $this->Fields[$fieldName];
        $lookup = $fld->Lookup;
        $name = $req["name"] ?? "";
        if (ContainsString($name, "query_builder_rule")) {
            $lookup->FilterFields = []; // Skip parent fields if any
        }

        // Get lookup parameters
        $lookupType = $req["ajax"] ?? "unknown";
        $pageSize = -1;
        $offset = -1;
        $searchValue = "";
        if (SameText($lookupType, "modal") || SameText($lookupType, "filter")) {
            $searchValue = $req["q"] ?? $req["sv"] ?? "";
            $pageSize = $req["n"] ?? $req["recperpage"] ?? 10;
        } elseif (SameText($lookupType, "autosuggest")) {
            $searchValue = $req["q"] ?? "";
            $pageSize = $req["n"] ?? -1;
            $pageSize = is_numeric($pageSize) ? (int)$pageSize : -1;
            if ($pageSize <= 0) {
                $pageSize = Config("AUTO_SUGGEST_MAX_ENTRIES");
            }
        }
        $start = $req["start"] ?? -1;
        $start = is_numeric($start) ? (int)$start : -1;
        $page = $req["page"] ?? -1;
        $page = is_numeric($page) ? (int)$page : -1;
        $offset = $start >= 0 ? $start : ($page > 0 && $pageSize > 0 ? ($page - 1) * $pageSize : 0);
        $userSelect = Decrypt($req["s"] ?? "");
        $userFilter = Decrypt($req["f"] ?? "");
        $userOrderBy = Decrypt($req["o"] ?? "");
        $keys = $req["keys"] ?? null;
        $lookup->LookupType = $lookupType; // Lookup type
        $lookup->FilterValues = []; // Clear filter values first
        if ($keys !== null) { // Selected records from modal
            if (is_array($keys)) {
                $keys = implode(Config("MULTIPLE_OPTION_SEPARATOR"), $keys);
            }
            $lookup->FilterFields = []; // Skip parent fields if any
            $lookup->FilterValues[] = $keys; // Lookup values
            $pageSize = -1; // Show all records
        } else { // Lookup values
            $lookup->FilterValues[] = $req["v0"] ?? $req["lookupValue"] ?? "";
        }
        $cnt = is_array($lookup->FilterFields) ? count($lookup->FilterFields) : 0;
        for ($i = 1; $i <= $cnt; $i++) {
            $lookup->FilterValues[] = $req["v" . $i] ?? "";
        }
        $lookup->SearchValue = $searchValue;
        $lookup->PageSize = $pageSize;
        $lookup->Offset = $offset;
        if ($userSelect != "") {
            $lookup->UserSelect = $userSelect;
        }
        if ($userFilter != "") {
            $lookup->UserFilter = $userFilter;
        }
        if ($userOrderBy != "") {
            $lookup->UserOrderBy = $userOrderBy;
        }
        return $lookup->toJson($this, $response); // Use settings from current page
    }

    // Class variables
    public $ListOptions; // List options
    public $ExportOptions; // Export options
    public $SearchOptions; // Search options
    public $OtherOptions; // Other options
    public $HeaderOptions; // Header options
    public $FooterOptions; // Footer options
    public $FilterOptions; // Filter options
    public $ImportOptions; // Import options
    public $ListActions; // List actions
    public $SelectedCount = 0;
    public $SelectedIndex = 0;
    public $DisplayRecords = 20;
    public $StartRecord;
    public $StopRecord;
    public $TotalRecords = 0;
    public $RecordRange = 10;
    public $PageSizes = "10,20,50,-1"; // Page sizes (comma separated)
    public $DefaultSearchWhere = ""; // Default search WHERE clause
    public $SearchWhere = ""; // Search WHERE clause
    public $SearchPanelClass = "ew-search-panel collapse show"; // Search Panel class
    public $SearchColumnCount = 0; // For extended search
    public $SearchFieldsPerRow = 1; // For extended search
    public $RecordCount = 0; // Record count
    public $InlineRowCount = 0;
    public $StartRowCount = 1;
    public $Attrs = []; // Row attributes and cell attributes
    public $RowIndex = 0; // Row index
    public $KeyCount = 0; // Key count
    public $MultiColumnGridClass = "row-cols-md";
    public $MultiColumnEditClass = "col-12 w-100";
    public $MultiColumnCardClass = "card h-100 ew-card";
    public $MultiColumnListOptionsPosition = "bottom-start";
    public $DbMasterFilter = ""; // Master filter
    public $DbDetailFilter = ""; // Detail filter
    public $MasterRecordExists;
    public $MultiSelectKey;
    public $Command;
    public $UserAction; // User action
    public $RestoreSearch = false;
    public $HashValue; // Hash value
    public $DetailPages;
    public $TopContentClass = "ew-top";
    public $MiddleContentClass = "ew-middle";
    public $BottomContentClass = "ew-bottom";
    public $PageAction;
    public $RecKeys = [];
    public $IsModal = false;
    protected $FilterForModalActions = "";
    private $UseInfiniteScroll = false;

    /**
     * Load result set from filter
     *
     * @return void
     */
    public function loadRecordsetFromFilter($filter)
    {
        // Set up list options
        $this->setupListOptions();

        // Search options
        $this->setupSearchOptions();

        // Other options
        $this->setupOtherOptions();

        // Set visibility
        $this->setVisibility();

        // Load result set
        $this->TotalRecords = $this->loadRecordCount($filter);
        $this->StartRecord = 1;
        $this->StopRecord = $this->DisplayRecords;
        $this->CurrentFilter = $filter;
        $this->Recordset = $this->loadRecordset();

        // Set up pager
        $this->Pager = new PrevNextPager($this, $this->StartRecord, $this->DisplayRecords, $this->TotalRecords, $this->PageSizes, $this->RecordRange, $this->AutoHidePager, $this->AutoHidePageSizeSelector);
    }

    /**
     * Page run
     *
     * @return void
     */
    public function run()
    {
        global $ExportType, $Language, $Security, $CurrentForm, $DashboardReport;

        // Multi column button position
        $this->MultiColumnListOptionsPosition = Config("MULTI_COLUMN_LIST_OPTIONS_POSITION");
        $DashboardReport ??= Param(Config("PAGE_DASHBOARD"));

        // Is modal
        $this->IsModal = ConvertToBool(Param("modal"));

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

        // Get export parameters
        $custom = "";
        if (Param("export") !== null) {
            $this->Export = Param("export");
            $custom = Param("custom", "");
        } else {
            $this->setExportReturnUrl(CurrentUrl());
        }
        $ExportType = $this->Export; // Get export parameter, used in header
        if ($ExportType != "") {
            global $SkipHeaderFooter;
            $SkipHeaderFooter = true;
        }
        $this->CurrentAction = Param("action"); // Set up current action

        // Get grid add count
        $gridaddcnt = Get(Config("TABLE_GRID_ADD_ROW_COUNT"), "");
        if (is_numeric($gridaddcnt) && $gridaddcnt > 0) {
            $this->GridAddRowCount = $gridaddcnt;
        }

        // Set up list options
        $this->setupListOptions();
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

        // Setup other options
        $this->setupOtherOptions();

        // Set up lookup cache
        $this->setupLookupOptions($this->is_active);
        $this->setupLookupOptions($this->line1_bold);
        $this->setupLookupOptions($this->line2_bold);

        // Update form name to avoid conflict
        if ($this->IsModal) {
            $this->FormName = "fdocument_header_assetsgrid";
        }

        // Set up page action
        $this->PageAction = CurrentPageUrl(false);

        // Set up infinite scroll
        $this->UseInfiniteScroll = ConvertToBool(Param("infinitescroll"));

        // Search filters
        $srchAdvanced = ""; // Advanced search filter
        $srchBasic = ""; // Basic search filter
        $query = ""; // Query builder

        // Set up Dashboard Filter
        if ($DashboardReport) {
            AddFilter($this->Filter, $this->getDashboardFilter($DashboardReport, $this->TableVar));
        }

        // Get command
        $this->Command = strtolower(Get("cmd", ""));

        // Process list action first
        if ($this->processListAction()) { // Ajax request
            $this->terminate();
            return;
        }

        // Set up records per page
        $this->setupDisplayRecords();

        // Handle reset command
        $this->resetCmd();

        // Set up Breadcrumb
        if (!$this->isExport()) {
            $this->setupBreadcrumb();
        }

        // Hide list options
        if ($this->isExport()) {
            $this->ListOptions->hideAllOptions(["sequence"]);
            $this->ListOptions->UseDropDownButton = false; // Disable drop down button
            $this->ListOptions->UseButtonGroup = false; // Disable button group
        } elseif ($this->isGridAdd() || $this->isGridEdit() || $this->isMultiEdit() || $this->isConfirm()) {
            $this->ListOptions->hideAllOptions();
            $this->ListOptions->UseDropDownButton = false; // Disable drop down button
            $this->ListOptions->UseButtonGroup = false; // Disable button group
        }

        // Hide options
        if ($this->isExport() || !(EmptyValue($this->CurrentAction) || $this->isSearch())) {
            $this->ExportOptions->hideAllOptions();
            $this->FilterOptions->hideAllOptions();
            $this->ImportOptions->hideAllOptions();
        }

        // Hide other options
        if ($this->isExport()) {
            $this->OtherOptions->hideAllOptions();
        }

        // Get default search criteria
        AddFilter($this->DefaultSearchWhere, $this->basicSearchWhere(true));

        // Get basic search values
        $this->loadBasicSearchValues();

        // Process filter list
        if ($this->processFilterList()) {
            $this->terminate();
            return;
        }

        // Restore search parms from Session if not searching / reset / export
        if (($this->isExport() || $this->Command != "search" && $this->Command != "reset" && $this->Command != "resetall") && $this->Command != "json" && $this->checkSearchParms()) {
            $this->restoreSearchParms();
        }

        // Call Recordset SearchValidated event
        $this->recordsetSearchValidated();

        // Set up sorting order
        $this->setupSortOrder();

        // Get basic search criteria
        if (!$this->hasInvalidFields()) {
            $srchBasic = $this->basicSearchWhere();
        }

        // Restore display records
        if ($this->Command != "json" && $this->getRecordsPerPage() != "") {
            $this->DisplayRecords = $this->getRecordsPerPage(); // Restore from Session
        } else {
            $this->DisplayRecords = 20; // Load default
            $this->setRecordsPerPage($this->DisplayRecords); // Save default to Session
        }

        // Load search default if no existing search criteria
        if (!$this->checkSearchParms() && !$query) {
            // Load basic search from default
            $this->BasicSearch->loadDefault();
            if ($this->BasicSearch->Keyword != "") {
                $srchBasic = $this->basicSearchWhere(); // Save to session
            }
        }

        // Build search criteria
        if ($query) {
            AddFilter($this->SearchWhere, $query);
        } else {
            AddFilter($this->SearchWhere, $srchAdvanced);
            AddFilter($this->SearchWhere, $srchBasic);
        }

        // Call Recordset_Searching event
        $this->recordsetSearching($this->SearchWhere);

        // Save search criteria
        if ($this->Command == "search" && !$this->RestoreSearch) {
            $this->setSearchWhere($this->SearchWhere); // Save to Session
            $this->StartRecord = 1; // Reset start record counter
            $this->setStartRecordNumber($this->StartRecord);
        } elseif ($this->Command != "json" && !$query) {
            $this->SearchWhere = $this->getSearchWhere();
        }

        // Build filter
        if (!$Security->canList()) {
            $this->Filter = "(0=1)"; // Filter all records
        }
        AddFilter($this->Filter, $this->DbDetailFilter);
        AddFilter($this->Filter, $this->SearchWhere);

        // Set up filter
        if ($this->Command == "json") {
            $this->UseSessionForListSql = false; // Do not use session for ListSQL
            $this->CurrentFilter = $this->Filter;
        } else {
            $this->setSessionWhere($this->Filter);
            $this->CurrentFilter = "";
        }
        $this->Filter = $this->applyUserIDFilters($this->Filter);
        if ($this->isGridAdd()) {
            $this->CurrentFilter = "0=1";
            $this->StartRecord = 1;
            $this->DisplayRecords = $this->GridAddRowCount;
            $this->TotalRecords = $this->DisplayRecords;
            $this->StopRecord = $this->DisplayRecords;
        } elseif (($this->isEdit() || $this->isCopy() || $this->isInlineInserted() || $this->isInlineUpdated()) && $this->UseInfiniteScroll) { // Get current record only
            $this->CurrentFilter = $this->isInlineUpdated() ? $this->getRecordFilter() : $this->getFilterFromRecordKeys();
            $this->TotalRecords = $this->listRecordCount();
            $this->StartRecord = 1;
            $this->StopRecord = $this->DisplayRecords;
            $this->Recordset = $this->loadRecordset();
        } elseif (
            $this->UseInfiniteScroll && $this->isGridInserted() ||
            $this->UseInfiniteScroll && ($this->isGridEdit() || $this->isGridUpdated()) ||
            $this->isMultiEdit() ||
            $this->UseInfiniteScroll && $this->isMultiUpdated()
        ) { // Get current records only
            $this->CurrentFilter = $this->FilterForModalActions; // Restore filter
            $this->TotalRecords = $this->listRecordCount();
            $this->StartRecord = 1;
            $this->StopRecord = $this->DisplayRecords;
            $this->Recordset = $this->loadRecordset();
        } else {
            $this->TotalRecords = $this->listRecordCount();
            $this->StartRecord = 1;
            if ($this->DisplayRecords <= 0 || ($this->isExport() && $this->ExportAll)) { // Display all records
                $this->DisplayRecords = $this->TotalRecords;
            }
            if (!($this->isExport() && $this->ExportAll)) { // Set up start record position
                $this->setupStartRecord();
            }
            $this->Recordset = $this->loadRecordset($this->StartRecord - 1, $this->DisplayRecords);

            // Set no record found message
            if ((EmptyValue($this->CurrentAction) || $this->isSearch()) && $this->TotalRecords == 0) {
                if (!$Security->canList()) {
                    $this->setWarningMessage(DeniedMessage());
                }
                if ($this->SearchWhere == "0=101") {
                    $this->setWarningMessage($Language->phrase("EnterSearchCriteria"));
                } else {
                    $this->setWarningMessage($Language->phrase("NoRecord"));
                }
            }
        }

        // Set up list action columns
        foreach ($this->ListActions as $listAction) {
            if ($listAction->Allowed) {
                if ($listAction->Select == ACTION_MULTIPLE) { // Show checkbox column if multiple action
                    $this->ListOptions["checkbox"]->Visible = true;
                } elseif ($listAction->Select == ACTION_SINGLE) { // Show list action column
                        $this->ListOptions["listactions"]->Visible = true; // Set visible if any list action is allowed
                }
            }
        }

        // Search options
        $this->setupSearchOptions();

        // Set up search panel class
        if ($this->SearchWhere != "") {
            if ($query) { // Hide search panel if using QueryBuilder
                RemoveClass($this->SearchPanelClass, "show");
            } else {
                AppendClass($this->SearchPanelClass, "show");
            }
        }

        // API list action
        if (IsApi()) {
            if (Route(0) == Config("API_LIST_ACTION")) {
                if (!$this->isExport()) {
                    $rows = $this->getRecordsFromRecordset($this->Recordset);
                    $this->Recordset?->free();
                    WriteJson([
                        "success" => true,
                        "action" => Config("API_LIST_ACTION"),
                        $this->TableVar => $rows,
                        "totalRecordCount" => $this->TotalRecords
                    ]);
                    $this->terminate(true);
                }
                return;
            } elseif ($this->getFailureMessage() != "") {
                WriteJson(["error" => $this->getFailureMessage()]);
                $this->clearFailureMessage();
                $this->terminate(true);
                return;
            }
        }

        // Render other options
        $this->renderOtherOptions();

        // Set up pager
        $this->Pager = new PrevNextPager($this, $this->StartRecord, $this->DisplayRecords, $this->TotalRecords, $this->PageSizes, $this->RecordRange, $this->AutoHidePager, $this->AutoHidePageSizeSelector);

        // Set ReturnUrl in header if necessary
        if ($returnUrl = Container("app.flash")->getFirstMessage("Return-Url")) {
            AddHeader("Return-Url", GetUrl($returnUrl));
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

    // Get page number
    public function getPageNumber()
    {
        return ($this->DisplayRecords > 0 && $this->StartRecord > 0) ? ceil($this->StartRecord / $this->DisplayRecords) : 1;
    }

    // Set up number of records displayed per page
    protected function setupDisplayRecords()
    {
        $wrk = Get(Config("TABLE_REC_PER_PAGE"), "");
        if ($wrk != "") {
            if (is_numeric($wrk)) {
                $this->DisplayRecords = (int)$wrk;
            } else {
                if (SameText($wrk, "all")) { // Display all records
                    $this->DisplayRecords = -1;
                } else {
                    $this->DisplayRecords = 20; // Non-numeric, load default
                }
            }
            $this->setRecordsPerPage($this->DisplayRecords); // Save to Session
            // Reset start position
            $this->StartRecord = 1;
            $this->setStartRecordNumber($this->StartRecord);
        }
    }

    // Build filter for all keys
    protected function buildKeyFilter()
    {
        global $CurrentForm;
        $wrkFilter = "";

        // Update row index and get row key
        $rowindex = 1;
        $CurrentForm->Index = $rowindex;
        $thisKey = strval($CurrentForm->getValue($this->OldKeyName));
        while ($thisKey != "") {
            $this->setKey($thisKey);
            if ($this->OldKey != "") {
                $filter = $this->getRecordFilter();
                if ($wrkFilter != "") {
                    $wrkFilter .= " OR ";
                }
                $wrkFilter .= $filter;
            } else {
                $wrkFilter = "0=1";
                break;
            }

            // Update row index and get row key
            $rowindex++; // Next row
            $CurrentForm->Index = $rowindex;
            $thisKey = strval($CurrentForm->getValue($this->OldKeyName));
        }
        return $wrkFilter;
    }

    // Get list of filters
    public function getFilterList()
    {
        // Initialize
        $filterList = "";
        $savedFilterList = "";

        // Load server side filters
        if (Config("SEARCH_FILTER_OPTION") == "Server") {
            $savedFilterList = Profile()->getSearchFilters("fdocument_header_assetssrch");
        }
        $filterList = Concat($filterList, $this->header_asset_id->AdvancedSearch->toJson(), ","); // Field header_asset_id
        $filterList = Concat($filterList, $this->asset_code->AdvancedSearch->toJson(), ","); // Field asset_code
        $filterList = Concat($filterList, $this->asset_name->AdvancedSearch->toJson(), ","); // Field asset_name
        $filterList = Concat($filterList, $this->document_asset_type_id->AdvancedSearch->toJson(), ","); // Field document_asset_type_id
        $filterList = Concat($filterList, $this->scope_system_code->AdvancedSearch->toJson(), ","); // Field scope_system_code
        $filterList = Concat($filterList, $this->scope_document_code->AdvancedSearch->toJson(), ","); // Field scope_document_code
        $filterList = Concat($filterList, $this->file_name->AdvancedSearch->toJson(), ","); // Field file_name
        $filterList = Concat($filterList, $this->mime_type->AdvancedSearch->toJson(), ","); // Field mime_type
        $filterList = Concat($filterList, $this->file_size->AdvancedSearch->toJson(), ","); // Field file_size
        $filterList = Concat($filterList, $this->checksum_sha256->AdvancedSearch->toJson(), ","); // Field checksum_sha256
        $filterList = Concat($filterList, $this->image_width->AdvancedSearch->toJson(), ","); // Field image_width
        $filterList = Concat($filterList, $this->image_height->AdvancedSearch->toJson(), ","); // Field image_height
        $filterList = Concat($filterList, $this->render_width->AdvancedSearch->toJson(), ","); // Field render_width
        $filterList = Concat($filterList, $this->render_height->AdvancedSearch->toJson(), ","); // Field render_height
        $filterList = Concat($filterList, $this->image_data->AdvancedSearch->toJson(), ","); // Field image_data
        $filterList = Concat($filterList, $this->is_active->AdvancedSearch->toJson(), ","); // Field is_active
        $filterList = Concat($filterList, $this->notes->AdvancedSearch->toJson(), ","); // Field notes
        $filterList = Concat($filterList, $this->created_at->AdvancedSearch->toJson(), ","); // Field created_at
        $filterList = Concat($filterList, $this->updated_at->AdvancedSearch->toJson(), ","); // Field updated_at
        $filterList = Concat($filterList, $this->created_by->AdvancedSearch->toJson(), ","); // Field created_by
        $filterList = Concat($filterList, $this->updated_by->AdvancedSearch->toJson(), ","); // Field updated_by
        $filterList = Concat($filterList, $this->header_slot_id->AdvancedSearch->toJson(), ","); // Field header_slot_id
        $filterList = Concat($filterList, $this->part_kind->AdvancedSearch->toJson(), ","); // Field part_kind
        $filterList = Concat($filterList, $this->column_pct->AdvancedSearch->toJson(), ","); // Field column_pct
        $filterList = Concat($filterList, $this->align->AdvancedSearch->toJson(), ","); // Field align
        $filterList = Concat($filterList, $this->text_line1->AdvancedSearch->toJson(), ","); // Field text_line1
        $filterList = Concat($filterList, $this->text_line2->AdvancedSearch->toJson(), ","); // Field text_line2
        $filterList = Concat($filterList, $this->font_name->AdvancedSearch->toJson(), ","); // Field font_name
        $filterList = Concat($filterList, $this->line1_size_pt->AdvancedSearch->toJson(), ","); // Field line1_size_pt
        $filterList = Concat($filterList, $this->line2_size_pt->AdvancedSearch->toJson(), ","); // Field line2_size_pt
        $filterList = Concat($filterList, $this->line1_bold->AdvancedSearch->toJson(), ","); // Field line1_bold
        $filterList = Concat($filterList, $this->line2_bold->AdvancedSearch->toJson(), ","); // Field line2_bold
        $filterList = Concat($filterList, $this->line1_color->AdvancedSearch->toJson(), ","); // Field line1_color
        $filterList = Concat($filterList, $this->line2_color->AdvancedSearch->toJson(), ","); // Field line2_color
        if ($this->BasicSearch->Keyword != "") {
            $wrk = "\"" . Config("TABLE_BASIC_SEARCH") . "\":\"" . JsEncode($this->BasicSearch->Keyword) . "\",\"" . Config("TABLE_BASIC_SEARCH_TYPE") . "\":\"" . JsEncode($this->BasicSearch->Type) . "\"";
            $filterList = Concat($filterList, $wrk, ",");
        }

        // Return filter list in JSON
        if ($filterList != "") {
            $filterList = "\"data\":{" . $filterList . "}";
        }
        if ($savedFilterList != "") {
            $filterList = Concat($filterList, "\"filters\":" . $savedFilterList, ",");
        }
        return ($filterList != "") ? "{" . $filterList . "}" : "null";
    }

    // Process filter list
    protected function processFilterList()
    {
        if (Post("ajax") == "savefilters") { // Save filter request (Ajax)
            $filters = Post("filters");
            Profile()->setSearchFilters("fdocument_header_assetssrch", $filters);
            WriteJson([["success" => true]]); // Success
            return true;
        } elseif (Post("cmd") == "resetfilter") {
            $this->restoreFilterList();
        }
        return false;
    }

    // Restore list of filters
    protected function restoreFilterList()
    {
        // Return if not reset filter
        if (Post("cmd") !== "resetfilter") {
            return false;
        }
        $filter = json_decode(Post("filter"), true);
        $this->Command = "search";

        // Field header_asset_id
        $this->header_asset_id->AdvancedSearch->SearchValue = @$filter["x_header_asset_id"];
        $this->header_asset_id->AdvancedSearch->SearchOperator = @$filter["z_header_asset_id"];
        $this->header_asset_id->AdvancedSearch->SearchCondition = @$filter["v_header_asset_id"];
        $this->header_asset_id->AdvancedSearch->SearchValue2 = @$filter["y_header_asset_id"];
        $this->header_asset_id->AdvancedSearch->SearchOperator2 = @$filter["w_header_asset_id"];
        $this->header_asset_id->AdvancedSearch->save();

        // Field asset_code
        $this->asset_code->AdvancedSearch->SearchValue = @$filter["x_asset_code"];
        $this->asset_code->AdvancedSearch->SearchOperator = @$filter["z_asset_code"];
        $this->asset_code->AdvancedSearch->SearchCondition = @$filter["v_asset_code"];
        $this->asset_code->AdvancedSearch->SearchValue2 = @$filter["y_asset_code"];
        $this->asset_code->AdvancedSearch->SearchOperator2 = @$filter["w_asset_code"];
        $this->asset_code->AdvancedSearch->save();

        // Field asset_name
        $this->asset_name->AdvancedSearch->SearchValue = @$filter["x_asset_name"];
        $this->asset_name->AdvancedSearch->SearchOperator = @$filter["z_asset_name"];
        $this->asset_name->AdvancedSearch->SearchCondition = @$filter["v_asset_name"];
        $this->asset_name->AdvancedSearch->SearchValue2 = @$filter["y_asset_name"];
        $this->asset_name->AdvancedSearch->SearchOperator2 = @$filter["w_asset_name"];
        $this->asset_name->AdvancedSearch->save();

        // Field document_asset_type_id
        $this->document_asset_type_id->AdvancedSearch->SearchValue = @$filter["x_document_asset_type_id"];
        $this->document_asset_type_id->AdvancedSearch->SearchOperator = @$filter["z_document_asset_type_id"];
        $this->document_asset_type_id->AdvancedSearch->SearchCondition = @$filter["v_document_asset_type_id"];
        $this->document_asset_type_id->AdvancedSearch->SearchValue2 = @$filter["y_document_asset_type_id"];
        $this->document_asset_type_id->AdvancedSearch->SearchOperator2 = @$filter["w_document_asset_type_id"];
        $this->document_asset_type_id->AdvancedSearch->save();

        // Field scope_system_code
        $this->scope_system_code->AdvancedSearch->SearchValue = @$filter["x_scope_system_code"];
        $this->scope_system_code->AdvancedSearch->SearchOperator = @$filter["z_scope_system_code"];
        $this->scope_system_code->AdvancedSearch->SearchCondition = @$filter["v_scope_system_code"];
        $this->scope_system_code->AdvancedSearch->SearchValue2 = @$filter["y_scope_system_code"];
        $this->scope_system_code->AdvancedSearch->SearchOperator2 = @$filter["w_scope_system_code"];
        $this->scope_system_code->AdvancedSearch->save();

        // Field scope_document_code
        $this->scope_document_code->AdvancedSearch->SearchValue = @$filter["x_scope_document_code"];
        $this->scope_document_code->AdvancedSearch->SearchOperator = @$filter["z_scope_document_code"];
        $this->scope_document_code->AdvancedSearch->SearchCondition = @$filter["v_scope_document_code"];
        $this->scope_document_code->AdvancedSearch->SearchValue2 = @$filter["y_scope_document_code"];
        $this->scope_document_code->AdvancedSearch->SearchOperator2 = @$filter["w_scope_document_code"];
        $this->scope_document_code->AdvancedSearch->save();

        // Field file_name
        $this->file_name->AdvancedSearch->SearchValue = @$filter["x_file_name"];
        $this->file_name->AdvancedSearch->SearchOperator = @$filter["z_file_name"];
        $this->file_name->AdvancedSearch->SearchCondition = @$filter["v_file_name"];
        $this->file_name->AdvancedSearch->SearchValue2 = @$filter["y_file_name"];
        $this->file_name->AdvancedSearch->SearchOperator2 = @$filter["w_file_name"];
        $this->file_name->AdvancedSearch->save();

        // Field mime_type
        $this->mime_type->AdvancedSearch->SearchValue = @$filter["x_mime_type"];
        $this->mime_type->AdvancedSearch->SearchOperator = @$filter["z_mime_type"];
        $this->mime_type->AdvancedSearch->SearchCondition = @$filter["v_mime_type"];
        $this->mime_type->AdvancedSearch->SearchValue2 = @$filter["y_mime_type"];
        $this->mime_type->AdvancedSearch->SearchOperator2 = @$filter["w_mime_type"];
        $this->mime_type->AdvancedSearch->save();

        // Field file_size
        $this->file_size->AdvancedSearch->SearchValue = @$filter["x_file_size"];
        $this->file_size->AdvancedSearch->SearchOperator = @$filter["z_file_size"];
        $this->file_size->AdvancedSearch->SearchCondition = @$filter["v_file_size"];
        $this->file_size->AdvancedSearch->SearchValue2 = @$filter["y_file_size"];
        $this->file_size->AdvancedSearch->SearchOperator2 = @$filter["w_file_size"];
        $this->file_size->AdvancedSearch->save();

        // Field checksum_sha256
        $this->checksum_sha256->AdvancedSearch->SearchValue = @$filter["x_checksum_sha256"];
        $this->checksum_sha256->AdvancedSearch->SearchOperator = @$filter["z_checksum_sha256"];
        $this->checksum_sha256->AdvancedSearch->SearchCondition = @$filter["v_checksum_sha256"];
        $this->checksum_sha256->AdvancedSearch->SearchValue2 = @$filter["y_checksum_sha256"];
        $this->checksum_sha256->AdvancedSearch->SearchOperator2 = @$filter["w_checksum_sha256"];
        $this->checksum_sha256->AdvancedSearch->save();

        // Field image_width
        $this->image_width->AdvancedSearch->SearchValue = @$filter["x_image_width"];
        $this->image_width->AdvancedSearch->SearchOperator = @$filter["z_image_width"];
        $this->image_width->AdvancedSearch->SearchCondition = @$filter["v_image_width"];
        $this->image_width->AdvancedSearch->SearchValue2 = @$filter["y_image_width"];
        $this->image_width->AdvancedSearch->SearchOperator2 = @$filter["w_image_width"];
        $this->image_width->AdvancedSearch->save();

        // Field image_height
        $this->image_height->AdvancedSearch->SearchValue = @$filter["x_image_height"];
        $this->image_height->AdvancedSearch->SearchOperator = @$filter["z_image_height"];
        $this->image_height->AdvancedSearch->SearchCondition = @$filter["v_image_height"];
        $this->image_height->AdvancedSearch->SearchValue2 = @$filter["y_image_height"];
        $this->image_height->AdvancedSearch->SearchOperator2 = @$filter["w_image_height"];
        $this->image_height->AdvancedSearch->save();

        // Field render_width
        $this->render_width->AdvancedSearch->SearchValue = @$filter["x_render_width"];
        $this->render_width->AdvancedSearch->SearchOperator = @$filter["z_render_width"];
        $this->render_width->AdvancedSearch->SearchCondition = @$filter["v_render_width"];
        $this->render_width->AdvancedSearch->SearchValue2 = @$filter["y_render_width"];
        $this->render_width->AdvancedSearch->SearchOperator2 = @$filter["w_render_width"];
        $this->render_width->AdvancedSearch->save();

        // Field render_height
        $this->render_height->AdvancedSearch->SearchValue = @$filter["x_render_height"];
        $this->render_height->AdvancedSearch->SearchOperator = @$filter["z_render_height"];
        $this->render_height->AdvancedSearch->SearchCondition = @$filter["v_render_height"];
        $this->render_height->AdvancedSearch->SearchValue2 = @$filter["y_render_height"];
        $this->render_height->AdvancedSearch->SearchOperator2 = @$filter["w_render_height"];
        $this->render_height->AdvancedSearch->save();

        // Field image_data
        $this->image_data->AdvancedSearch->SearchValue = @$filter["x_image_data"];
        $this->image_data->AdvancedSearch->SearchOperator = @$filter["z_image_data"];
        $this->image_data->AdvancedSearch->SearchCondition = @$filter["v_image_data"];
        $this->image_data->AdvancedSearch->SearchValue2 = @$filter["y_image_data"];
        $this->image_data->AdvancedSearch->SearchOperator2 = @$filter["w_image_data"];
        $this->image_data->AdvancedSearch->save();

        // Field is_active
        $this->is_active->AdvancedSearch->SearchValue = @$filter["x_is_active"];
        $this->is_active->AdvancedSearch->SearchOperator = @$filter["z_is_active"];
        $this->is_active->AdvancedSearch->SearchCondition = @$filter["v_is_active"];
        $this->is_active->AdvancedSearch->SearchValue2 = @$filter["y_is_active"];
        $this->is_active->AdvancedSearch->SearchOperator2 = @$filter["w_is_active"];
        $this->is_active->AdvancedSearch->save();

        // Field notes
        $this->notes->AdvancedSearch->SearchValue = @$filter["x_notes"];
        $this->notes->AdvancedSearch->SearchOperator = @$filter["z_notes"];
        $this->notes->AdvancedSearch->SearchCondition = @$filter["v_notes"];
        $this->notes->AdvancedSearch->SearchValue2 = @$filter["y_notes"];
        $this->notes->AdvancedSearch->SearchOperator2 = @$filter["w_notes"];
        $this->notes->AdvancedSearch->save();

        // Field created_at
        $this->created_at->AdvancedSearch->SearchValue = @$filter["x_created_at"];
        $this->created_at->AdvancedSearch->SearchOperator = @$filter["z_created_at"];
        $this->created_at->AdvancedSearch->SearchCondition = @$filter["v_created_at"];
        $this->created_at->AdvancedSearch->SearchValue2 = @$filter["y_created_at"];
        $this->created_at->AdvancedSearch->SearchOperator2 = @$filter["w_created_at"];
        $this->created_at->AdvancedSearch->save();

        // Field updated_at
        $this->updated_at->AdvancedSearch->SearchValue = @$filter["x_updated_at"];
        $this->updated_at->AdvancedSearch->SearchOperator = @$filter["z_updated_at"];
        $this->updated_at->AdvancedSearch->SearchCondition = @$filter["v_updated_at"];
        $this->updated_at->AdvancedSearch->SearchValue2 = @$filter["y_updated_at"];
        $this->updated_at->AdvancedSearch->SearchOperator2 = @$filter["w_updated_at"];
        $this->updated_at->AdvancedSearch->save();

        // Field created_by
        $this->created_by->AdvancedSearch->SearchValue = @$filter["x_created_by"];
        $this->created_by->AdvancedSearch->SearchOperator = @$filter["z_created_by"];
        $this->created_by->AdvancedSearch->SearchCondition = @$filter["v_created_by"];
        $this->created_by->AdvancedSearch->SearchValue2 = @$filter["y_created_by"];
        $this->created_by->AdvancedSearch->SearchOperator2 = @$filter["w_created_by"];
        $this->created_by->AdvancedSearch->save();

        // Field updated_by
        $this->updated_by->AdvancedSearch->SearchValue = @$filter["x_updated_by"];
        $this->updated_by->AdvancedSearch->SearchOperator = @$filter["z_updated_by"];
        $this->updated_by->AdvancedSearch->SearchCondition = @$filter["v_updated_by"];
        $this->updated_by->AdvancedSearch->SearchValue2 = @$filter["y_updated_by"];
        $this->updated_by->AdvancedSearch->SearchOperator2 = @$filter["w_updated_by"];
        $this->updated_by->AdvancedSearch->save();

        // Field header_slot_id
        $this->header_slot_id->AdvancedSearch->SearchValue = @$filter["x_header_slot_id"];
        $this->header_slot_id->AdvancedSearch->SearchOperator = @$filter["z_header_slot_id"];
        $this->header_slot_id->AdvancedSearch->SearchCondition = @$filter["v_header_slot_id"];
        $this->header_slot_id->AdvancedSearch->SearchValue2 = @$filter["y_header_slot_id"];
        $this->header_slot_id->AdvancedSearch->SearchOperator2 = @$filter["w_header_slot_id"];
        $this->header_slot_id->AdvancedSearch->save();

        // Field part_kind
        $this->part_kind->AdvancedSearch->SearchValue = @$filter["x_part_kind"];
        $this->part_kind->AdvancedSearch->SearchOperator = @$filter["z_part_kind"];
        $this->part_kind->AdvancedSearch->SearchCondition = @$filter["v_part_kind"];
        $this->part_kind->AdvancedSearch->SearchValue2 = @$filter["y_part_kind"];
        $this->part_kind->AdvancedSearch->SearchOperator2 = @$filter["w_part_kind"];
        $this->part_kind->AdvancedSearch->save();

        // Field column_pct
        $this->column_pct->AdvancedSearch->SearchValue = @$filter["x_column_pct"];
        $this->column_pct->AdvancedSearch->SearchOperator = @$filter["z_column_pct"];
        $this->column_pct->AdvancedSearch->SearchCondition = @$filter["v_column_pct"];
        $this->column_pct->AdvancedSearch->SearchValue2 = @$filter["y_column_pct"];
        $this->column_pct->AdvancedSearch->SearchOperator2 = @$filter["w_column_pct"];
        $this->column_pct->AdvancedSearch->save();

        // Field align
        $this->align->AdvancedSearch->SearchValue = @$filter["x_align"];
        $this->align->AdvancedSearch->SearchOperator = @$filter["z_align"];
        $this->align->AdvancedSearch->SearchCondition = @$filter["v_align"];
        $this->align->AdvancedSearch->SearchValue2 = @$filter["y_align"];
        $this->align->AdvancedSearch->SearchOperator2 = @$filter["w_align"];
        $this->align->AdvancedSearch->save();

        // Field text_line1
        $this->text_line1->AdvancedSearch->SearchValue = @$filter["x_text_line1"];
        $this->text_line1->AdvancedSearch->SearchOperator = @$filter["z_text_line1"];
        $this->text_line1->AdvancedSearch->SearchCondition = @$filter["v_text_line1"];
        $this->text_line1->AdvancedSearch->SearchValue2 = @$filter["y_text_line1"];
        $this->text_line1->AdvancedSearch->SearchOperator2 = @$filter["w_text_line1"];
        $this->text_line1->AdvancedSearch->save();

        // Field text_line2
        $this->text_line2->AdvancedSearch->SearchValue = @$filter["x_text_line2"];
        $this->text_line2->AdvancedSearch->SearchOperator = @$filter["z_text_line2"];
        $this->text_line2->AdvancedSearch->SearchCondition = @$filter["v_text_line2"];
        $this->text_line2->AdvancedSearch->SearchValue2 = @$filter["y_text_line2"];
        $this->text_line2->AdvancedSearch->SearchOperator2 = @$filter["w_text_line2"];
        $this->text_line2->AdvancedSearch->save();

        // Field font_name
        $this->font_name->AdvancedSearch->SearchValue = @$filter["x_font_name"];
        $this->font_name->AdvancedSearch->SearchOperator = @$filter["z_font_name"];
        $this->font_name->AdvancedSearch->SearchCondition = @$filter["v_font_name"];
        $this->font_name->AdvancedSearch->SearchValue2 = @$filter["y_font_name"];
        $this->font_name->AdvancedSearch->SearchOperator2 = @$filter["w_font_name"];
        $this->font_name->AdvancedSearch->save();

        // Field line1_size_pt
        $this->line1_size_pt->AdvancedSearch->SearchValue = @$filter["x_line1_size_pt"];
        $this->line1_size_pt->AdvancedSearch->SearchOperator = @$filter["z_line1_size_pt"];
        $this->line1_size_pt->AdvancedSearch->SearchCondition = @$filter["v_line1_size_pt"];
        $this->line1_size_pt->AdvancedSearch->SearchValue2 = @$filter["y_line1_size_pt"];
        $this->line1_size_pt->AdvancedSearch->SearchOperator2 = @$filter["w_line1_size_pt"];
        $this->line1_size_pt->AdvancedSearch->save();

        // Field line2_size_pt
        $this->line2_size_pt->AdvancedSearch->SearchValue = @$filter["x_line2_size_pt"];
        $this->line2_size_pt->AdvancedSearch->SearchOperator = @$filter["z_line2_size_pt"];
        $this->line2_size_pt->AdvancedSearch->SearchCondition = @$filter["v_line2_size_pt"];
        $this->line2_size_pt->AdvancedSearch->SearchValue2 = @$filter["y_line2_size_pt"];
        $this->line2_size_pt->AdvancedSearch->SearchOperator2 = @$filter["w_line2_size_pt"];
        $this->line2_size_pt->AdvancedSearch->save();

        // Field line1_bold
        $this->line1_bold->AdvancedSearch->SearchValue = @$filter["x_line1_bold"];
        $this->line1_bold->AdvancedSearch->SearchOperator = @$filter["z_line1_bold"];
        $this->line1_bold->AdvancedSearch->SearchCondition = @$filter["v_line1_bold"];
        $this->line1_bold->AdvancedSearch->SearchValue2 = @$filter["y_line1_bold"];
        $this->line1_bold->AdvancedSearch->SearchOperator2 = @$filter["w_line1_bold"];
        $this->line1_bold->AdvancedSearch->save();

        // Field line2_bold
        $this->line2_bold->AdvancedSearch->SearchValue = @$filter["x_line2_bold"];
        $this->line2_bold->AdvancedSearch->SearchOperator = @$filter["z_line2_bold"];
        $this->line2_bold->AdvancedSearch->SearchCondition = @$filter["v_line2_bold"];
        $this->line2_bold->AdvancedSearch->SearchValue2 = @$filter["y_line2_bold"];
        $this->line2_bold->AdvancedSearch->SearchOperator2 = @$filter["w_line2_bold"];
        $this->line2_bold->AdvancedSearch->save();

        // Field line1_color
        $this->line1_color->AdvancedSearch->SearchValue = @$filter["x_line1_color"];
        $this->line1_color->AdvancedSearch->SearchOperator = @$filter["z_line1_color"];
        $this->line1_color->AdvancedSearch->SearchCondition = @$filter["v_line1_color"];
        $this->line1_color->AdvancedSearch->SearchValue2 = @$filter["y_line1_color"];
        $this->line1_color->AdvancedSearch->SearchOperator2 = @$filter["w_line1_color"];
        $this->line1_color->AdvancedSearch->save();

        // Field line2_color
        $this->line2_color->AdvancedSearch->SearchValue = @$filter["x_line2_color"];
        $this->line2_color->AdvancedSearch->SearchOperator = @$filter["z_line2_color"];
        $this->line2_color->AdvancedSearch->SearchCondition = @$filter["v_line2_color"];
        $this->line2_color->AdvancedSearch->SearchValue2 = @$filter["y_line2_color"];
        $this->line2_color->AdvancedSearch->SearchOperator2 = @$filter["w_line2_color"];
        $this->line2_color->AdvancedSearch->save();
        $this->BasicSearch->setKeyword(@$filter[Config("TABLE_BASIC_SEARCH")]);
        $this->BasicSearch->setType(@$filter[Config("TABLE_BASIC_SEARCH_TYPE")]);
    }

    // Show list of filters
    public function showFilterList()
    {
        global $Language;

        // Initialize
        $filterList = "";
        $captionClass = $this->isExport("email") ? "ew-filter-caption-email" : "ew-filter-caption";
        $captionSuffix = $this->isExport("email") ? ": " : "";
        if ($this->BasicSearch->Keyword != "") {
            $filterList .= "<div><span class=\"" . $captionClass . "\">" . $Language->phrase("BasicSearchKeyword") . "</span>" . $captionSuffix . $this->BasicSearch->Keyword . "</div>";
        }

        // Show Filters
        if ($filterList != "") {
            $message = "<div id=\"ew-filter-list\" class=\"callout callout-info d-table\"><div id=\"ew-current-filters\">" .
                $Language->phrase("CurrentFilters") . "</div>" . $filterList . "</div>";
            $this->messageShowing($message, "");
            Write($message);
        } else { // Output empty tag
            Write("<div id=\"ew-filter-list\"></div>");
        }
    }

    // Return basic search WHERE clause based on search keyword and type
    public function basicSearchWhere($default = false)
    {
        global $Security;
        $searchStr = "";
        if (!$Security->canSearch()) {
            return "";
        }

        // Fields to search
        $searchFlds = [];
        $searchFlds[] = &$this->asset_code;
        $searchFlds[] = &$this->asset_name;
        $searchFlds[] = &$this->scope_system_code;
        $searchFlds[] = &$this->scope_document_code;
        $searchFlds[] = &$this->file_name;
        $searchFlds[] = &$this->mime_type;
        $searchFlds[] = &$this->checksum_sha256;
        $searchFlds[] = &$this->image_data;
        $searchFlds[] = &$this->notes;
        $searchFlds[] = &$this->part_kind;
        $searchFlds[] = &$this->align;
        $searchFlds[] = &$this->text_line1;
        $searchFlds[] = &$this->text_line2;
        $searchFlds[] = &$this->font_name;
        $searchFlds[] = &$this->line1_color;
        $searchFlds[] = &$this->line2_color;
        $searchKeyword = $default ? $this->BasicSearch->KeywordDefault : $this->BasicSearch->Keyword;
        $searchType = $default ? $this->BasicSearch->TypeDefault : $this->BasicSearch->Type;

        // Get search SQL
        if ($searchKeyword != "") {
            $ar = $this->BasicSearch->keywordList($default);
            $searchStr = GetQuickSearchFilter($searchFlds, $ar, $searchType, Config("BASIC_SEARCH_ANY_FIELDS"), $this->Dbid);
            if (!$default && in_array($this->Command, ["", "reset", "resetall"])) {
                $this->Command = "search";
            }
        }
        if (!$default && $this->Command == "search") {
            $this->BasicSearch->setKeyword($searchKeyword);
            $this->BasicSearch->setType($searchType);

            // Clear rules for QueryBuilder
            $this->setSessionRules("");
        }
        return $searchStr;
    }

    // Check if search parm exists
    protected function checkSearchParms()
    {
        // Check basic search
        if ($this->BasicSearch->issetSession()) {
            return true;
        }
        return false;
    }

    // Clear all search parameters
    protected function resetSearchParms()
    {
        // Clear search WHERE clause
        $this->SearchWhere = "";
        $this->setSearchWhere($this->SearchWhere);

        // Clear basic search parameters
        $this->resetBasicSearchParms();
    }

    // Load advanced search default values
    protected function loadAdvancedSearchDefault()
    {
        return false;
    }

    // Clear all basic search parameters
    protected function resetBasicSearchParms()
    {
        $this->BasicSearch->unsetSession();
    }

    // Restore all search parameters
    protected function restoreSearchParms()
    {
        $this->RestoreSearch = true;

        // Restore basic search values
        $this->BasicSearch->load();
    }

    // Set up sort parameters
    protected function setupSortOrder()
    {
        // Load default Sorting Order
        if ($this->Command != "json") {
            $defaultSort = ""; // Set up default sort
            if ($this->getSessionOrderBy() == "" && $defaultSort != "") {
                $this->setSessionOrderBy($defaultSort);
            }
        }

        // Check for "order" parameter
        if (Get("order") !== null) {
            $this->CurrentOrder = Get("order");
            $this->CurrentOrderType = Get("ordertype", "");
            $this->updateSort($this->header_asset_id); // header_asset_id
            $this->updateSort($this->asset_code); // asset_code
            $this->updateSort($this->asset_name); // asset_name
            $this->updateSort($this->document_asset_type_id); // document_asset_type_id
            $this->updateSort($this->scope_system_code); // scope_system_code
            $this->updateSort($this->scope_document_code); // scope_document_code
            $this->updateSort($this->file_name); // file_name
            $this->updateSort($this->mime_type); // mime_type
            $this->updateSort($this->file_size); // file_size
            $this->updateSort($this->checksum_sha256); // checksum_sha256
            $this->updateSort($this->image_width); // image_width
            $this->updateSort($this->image_height); // image_height
            $this->updateSort($this->render_width); // render_width
            $this->updateSort($this->render_height); // render_height
            $this->updateSort($this->is_active); // is_active
            $this->updateSort($this->created_at); // created_at
            $this->updateSort($this->updated_at); // updated_at
            $this->updateSort($this->created_by); // created_by
            $this->updateSort($this->updated_by); // updated_by
            $this->updateSort($this->header_slot_id); // header_slot_id
            $this->updateSort($this->part_kind); // part_kind
            $this->updateSort($this->column_pct); // column_pct
            $this->updateSort($this->align); // align
            $this->updateSort($this->font_name); // font_name
            $this->updateSort($this->line1_size_pt); // line1_size_pt
            $this->updateSort($this->line2_size_pt); // line2_size_pt
            $this->updateSort($this->line1_bold); // line1_bold
            $this->updateSort($this->line2_bold); // line2_bold
            $this->updateSort($this->line1_color); // line1_color
            $this->updateSort($this->line2_color); // line2_color
            $this->setStartRecordNumber(1); // Reset start position
        }

        // Update field sort
        $this->updateFieldSort();
    }

    // Reset command
    // - cmd=reset (Reset search parameters)
    // - cmd=resetall (Reset search and master/detail parameters)
    // - cmd=resetsort (Reset sort parameters)
    protected function resetCmd()
    {
        // Check if reset command
        if (StartsString("reset", $this->Command)) {
            // Reset search criteria
            if ($this->Command == "reset" || $this->Command == "resetall") {
                $this->resetSearchParms();
            }

            // Reset (clear) sorting order
            if ($this->Command == "resetsort") {
                $orderBy = "";
                $this->setSessionOrderBy($orderBy);
                $this->header_asset_id->setSort("");
                $this->asset_code->setSort("");
                $this->asset_name->setSort("");
                $this->document_asset_type_id->setSort("");
                $this->scope_system_code->setSort("");
                $this->scope_document_code->setSort("");
                $this->file_name->setSort("");
                $this->mime_type->setSort("");
                $this->file_size->setSort("");
                $this->checksum_sha256->setSort("");
                $this->image_width->setSort("");
                $this->image_height->setSort("");
                $this->render_width->setSort("");
                $this->render_height->setSort("");
                $this->image_data->setSort("");
                $this->is_active->setSort("");
                $this->notes->setSort("");
                $this->created_at->setSort("");
                $this->updated_at->setSort("");
                $this->created_by->setSort("");
                $this->updated_by->setSort("");
                $this->header_slot_id->setSort("");
                $this->part_kind->setSort("");
                $this->column_pct->setSort("");
                $this->align->setSort("");
                $this->text_line1->setSort("");
                $this->text_line2->setSort("");
                $this->font_name->setSort("");
                $this->line1_size_pt->setSort("");
                $this->line2_size_pt->setSort("");
                $this->line1_bold->setSort("");
                $this->line2_bold->setSort("");
                $this->line1_color->setSort("");
                $this->line2_color->setSort("");
            }

            // Reset start position
            $this->StartRecord = 1;
            $this->setStartRecordNumber($this->StartRecord);
        }
    }

    // Set up list options
    protected function setupListOptions()
    {
        global $Security, $Language;

        // Add group option item ("button")
        $item = &$this->ListOptions->addGroupOption();
        $item->Body = "";
        $item->OnLeft = false;
        $item->Visible = false;

        // "view"
        $item = &$this->ListOptions->add("view");
        $item->CssClass = "text-nowrap";
        $item->Visible = $Security->canView();
        $item->OnLeft = false;

        // "edit"
        $item = &$this->ListOptions->add("edit");
        $item->CssClass = "text-nowrap";
        $item->Visible = $Security->canEdit();
        $item->OnLeft = false;

        // "copy"
        $item = &$this->ListOptions->add("copy");
        $item->CssClass = "text-nowrap";
        $item->Visible = $Security->canAdd();
        $item->OnLeft = false;

        // "delete"
        $item = &$this->ListOptions->add("delete");
        $item->CssClass = "text-nowrap";
        $item->Visible = $Security->canDelete();
        $item->OnLeft = false;

        // List actions
        $item = &$this->ListOptions->add("listactions");
        $item->CssClass = "text-nowrap";
        $item->OnLeft = false;
        $item->Visible = false;
        $item->ShowInButtonGroup = false;
        $item->ShowInDropDown = false;

        // "checkbox"
        $item = &$this->ListOptions->add("checkbox");
        $item->Visible = false;
        $item->OnLeft = false;
        $item->Header = "<div class=\"form-check\"><input type=\"checkbox\" name=\"key\" id=\"key\" class=\"form-check-input\" data-ew-action=\"select-all-keys\"></div>";
        if ($item->OnLeft) {
            $item->moveTo(0);
        }
        $item->ShowInDropDown = false;
        $item->ShowInButtonGroup = false;

        // Drop down button for ListOptions
        $this->ListOptions->UseDropDownButton = false;
        $this->ListOptions->DropDownButtonPhrase = $Language->phrase("ButtonListOptions");
        $this->ListOptions->UseButtonGroup = false;
        if ($this->ListOptions->UseButtonGroup && IsMobile()) {
            $this->ListOptions->UseDropDownButton = true;
        }

        //$this->ListOptions->ButtonClass = ""; // Class for button group

        // Call ListOptions_Load event
        $this->listOptionsLoad();
        $this->setupListOptionsExt();
        $item = $this->ListOptions[$this->ListOptions->GroupOptionName];
        $item->Visible = $this->ListOptions->groupOptionVisible();
    }

    // Set up list options (extensions)
    protected function setupListOptionsExt()
    {
            // Set up list options (to be implemented by extensions)
    }

    // Add "hash" parameter to URL
    public function urlAddHash($url, $hash)
    {
        return $this->UseAjaxActions ? $url : UrlAddQuery($url, "hash=" . $hash);
    }

    // Render list options
    public function renderListOptions()
    {
        global $Security, $Language, $CurrentForm;
        $this->ListOptions->loadDefault();

        // Call ListOptions_Rendering event
        $this->listOptionsRendering();
        $pageUrl = $this->pageUrl(false);
        if ($this->CurrentMode == "view") {
            // "view"
            $opt = $this->ListOptions["view"];
            $viewcaption = HtmlTitle($Language->phrase("ViewLink"));
            if ($Security->canView()) {
                if ($this->ModalView && !IsMobile()) {
                    $opt->Body = "<a class=\"ew-row-link ew-view\" title=\"" . $viewcaption . "\" data-table=\"document_header_assets\" data-caption=\"" . $viewcaption . "\" data-ew-action=\"modal\" data-action=\"view\" data-ajax=\"" . ($this->UseAjaxActions ? "true" : "false") . "\" data-url=\"" . HtmlEncode(GetUrl($this->ViewUrl)) . "\" data-btn=\"null\">" . $Language->phrase("ViewLink") . "</a>";
                } else {
                    $opt->Body = "<a class=\"ew-row-link ew-view\" title=\"" . $viewcaption . "\" data-caption=\"" . $viewcaption . "\" href=\"" . HtmlEncode(GetUrl($this->ViewUrl)) . "\">" . $Language->phrase("ViewLink") . "</a>";
                }
            } else {
                $opt->Body = "";
            }

            // "edit"
            $opt = $this->ListOptions["edit"];
            $editcaption = HtmlTitle($Language->phrase("EditLink"));
            if ($Security->canEdit()) {
                if ($this->ModalEdit && !IsMobile()) {
                    $opt->Body = "<a class=\"ew-row-link ew-edit\" title=\"" . $editcaption . "\" data-table=\"document_header_assets\" data-caption=\"" . $editcaption . "\" data-ew-action=\"modal\" data-action=\"edit\" data-ajax=\"" . ($this->UseAjaxActions ? "true" : "false") . "\" data-url=\"" . HtmlEncode(GetUrl($this->EditUrl)) . "\" data-btn=\"SaveBtn\">" . $Language->phrase("EditLink") . "</a>";
                } else {
                    $opt->Body = "<a class=\"ew-row-link ew-edit\" title=\"" . $editcaption . "\" data-caption=\"" . $editcaption . "\" href=\"" . HtmlEncode(GetUrl($this->EditUrl)) . "\">" . $Language->phrase("EditLink") . "</a>";
                }
            } else {
                $opt->Body = "";
            }

            // "copy"
            $opt = $this->ListOptions["copy"];
            $copycaption = HtmlTitle($Language->phrase("CopyLink"));
            if ($Security->canAdd()) {
                if ($this->ModalAdd && !IsMobile()) {
                    $opt->Body = "<a class=\"ew-row-link ew-copy\" title=\"" . $copycaption . "\" data-table=\"document_header_assets\" data-caption=\"" . $copycaption . "\" data-ew-action=\"modal\" data-action=\"add\" data-ajax=\"" . ($this->UseAjaxActions ? "true" : "false") . "\" data-url=\"" . HtmlEncode(GetUrl($this->CopyUrl)) . "\" data-btn=\"AddBtn\">" . $Language->phrase("CopyLink") . "</a>";
                } else {
                    $opt->Body = "<a class=\"ew-row-link ew-copy\" title=\"" . $copycaption . "\" data-caption=\"" . $copycaption . "\" href=\"" . HtmlEncode(GetUrl($this->CopyUrl)) . "\">" . $Language->phrase("CopyLink") . "</a>";
                }
            } else {
                $opt->Body = "";
            }

            // "delete"
            $opt = $this->ListOptions["delete"];
            if ($Security->canDelete()) {
                $deleteCaption = $Language->phrase("DeleteLink");
                $deleteTitle = HtmlTitle($deleteCaption);
                if ($this->UseAjaxActions) {
                    $opt->Body = "<a class=\"ew-row-link ew-delete\" data-ew-action=\"inline\" data-action=\"delete\" title=\"" . $deleteTitle . "\" data-caption=\"" . $deleteTitle . "\" data-key= \"" . HtmlEncode($this->getKey(true)) . "\" data-url=\"" . HtmlEncode(GetUrl($this->DeleteUrl)) . "\">" . $deleteCaption . "</a>";
                } else {
                    $opt->Body = "<a class=\"ew-row-link ew-delete\"" .
                        ($this->InlineDelete ? " data-ew-action=\"inline-delete\"" : "") .
                        " title=\"" . $deleteTitle . "\" data-caption=\"" . $deleteTitle . "\" href=\"" . HtmlEncode(GetUrl($this->DeleteUrl)) . "\">" . $deleteCaption . "</a>";
                }
            } else {
                $opt->Body = "";
            }
        } // End View mode

        // Set up list action buttons
        $opt = $this->ListOptions["listactions"];
        if ($opt && !$this->isExport() && !$this->CurrentAction) {
            $body = "";
            $links = [];
            foreach ($this->ListActions as $listAction) {
                $action = $listAction->Action;
                $allowed = $listAction->Allowed;
                $disabled = false;
                if ($listAction->Select == ACTION_SINGLE && $allowed) {
                    $caption = $listAction->Caption;
                    $title = HtmlTitle($caption);
                    if ($action != "") {
                        $icon = ($listAction->Icon != "") ? "<i class=\"" . HtmlEncode(str_replace(" ew-icon", "", $listAction->Icon)) . "\" data-caption=\"" . $title . "\"></i> " : "";
                        $link = $disabled
                            ? "<li><div class=\"alert alert-light\">" . $icon . " " . $caption . "</div></li>"
                            : "<li><button type=\"button\" class=\"dropdown-item ew-action ew-list-action\" data-caption=\"" . $title . "\" data-ew-action=\"submit\" form=\"fdocument_header_assetslist\" data-key=\"" . $this->keyToJson(true) . "\"" . $listAction->toDataAttributes() . ">" . $icon . " " . $caption . "</button></li>";
                        $links[] = $link;
                        if ($body == "") { // Setup first button
                            $body = $disabled
                            ? "<div class=\"alert alert-light\">" . $icon . " " . $caption . "</div>"
                            : "<button type=\"button\" class=\"btn btn-default ew-action ew-list-action\" title=\"" . $title . "\" data-caption=\"" . $title . "\" data-ew-action=\"submit\" form=\"fdocument_header_assetslist\" data-key=\"" . $this->keyToJson(true) . "\"" . $listAction->toDataAttributes() . ">" . $icon . " " . $caption . "</button>";
                        }
                    }
                }
            }
            if (count($links) > 1) { // More than one buttons, use dropdown
                $body = "<button type=\"button\" class=\"dropdown-toggle btn btn-default ew-actions\" title=\"" . HtmlTitle($Language->phrase("ListActionButton")) . "\" data-bs-toggle=\"dropdown\">" . $Language->phrase("ListActionButton") . "</button>";
                $content = implode(array_map(fn($link) => "<li>" . $link . "</li>", $links));
                $body .= "<ul class=\"dropdown-menu" . ($opt->OnLeft ? "" : " dropdown-menu-right") . "\">" . $content . "</ul>";
                $body = "<div class=\"btn-group btn-group-sm\">" . $body . "</div>";
            }
            if (count($links) > 0) {
                $opt->Body = $body;
            }
        }

        // "checkbox"
        $opt = $this->ListOptions["checkbox"];
        $opt->Body = "<div class=\"form-check\"><input type=\"checkbox\" id=\"key_m_" . $this->RowCount . "\" name=\"key_m[]\" class=\"form-check-input ew-multi-select\" value=\"" . HtmlEncode($this->header_asset_id->CurrentValue) . "\" data-ew-action=\"select-key\"></div>";
        $this->renderListOptionsExt();

        // Call ListOptions_Rendered event
        $this->listOptionsRendered();
    }

    // Render list options (extensions)
    protected function renderListOptionsExt()
    {
        // Render list options (to be implemented by extensions)
        global $Security, $Language;
    }

    // Set up other options
    protected function setupOtherOptions()
    {
        global $Language, $Security;
        $options = &$this->OtherOptions;
        $option = $options["addedit"];

        // Add
        $item = &$option->add("add");
        $addcaption = HtmlTitle($Language->phrase("AddLink"));
        if ($this->ModalAdd && !IsMobile()) {
            $item->Body = "<a class=\"ew-add-edit ew-add\" title=\"" . $addcaption . "\" data-table=\"document_header_assets\" data-caption=\"" . $addcaption . "\" data-ew-action=\"modal\" data-action=\"add\" data-ajax=\"" . ($this->UseAjaxActions ? "true" : "false") . "\" data-url=\"" . HtmlEncode(GetUrl($this->AddUrl)) . "\" data-btn=\"AddBtn\">" . $Language->phrase("AddLink") . "</a>";
        } else {
            $item->Body = "<a class=\"ew-add-edit ew-add\" title=\"" . $addcaption . "\" data-caption=\"" . $addcaption . "\" href=\"" . HtmlEncode(GetUrl($this->AddUrl)) . "\">" . $Language->phrase("AddLink") . "</a>";
        }
        $item->Visible = $this->AddUrl != "" && $Security->canAdd();
        $option = $options["action"];

        // Show column list for column visibility
        if ($this->UseColumnVisibility) {
            $option = $this->OtherOptions["column"];
            $item = &$option->addGroupOption();
            $item->Body = "";
            $item->Visible = $this->UseColumnVisibility;
            $this->createColumnOption($option, "header_asset_id");
            $this->createColumnOption($option, "asset_code");
            $this->createColumnOption($option, "asset_name");
            $this->createColumnOption($option, "document_asset_type_id");
            $this->createColumnOption($option, "scope_system_code");
            $this->createColumnOption($option, "scope_document_code");
            $this->createColumnOption($option, "file_name");
            $this->createColumnOption($option, "mime_type");
            $this->createColumnOption($option, "file_size");
            $this->createColumnOption($option, "checksum_sha256");
            $this->createColumnOption($option, "image_width");
            $this->createColumnOption($option, "image_height");
            $this->createColumnOption($option, "render_width");
            $this->createColumnOption($option, "render_height");
            $this->createColumnOption($option, "is_active");
            $this->createColumnOption($option, "created_at");
            $this->createColumnOption($option, "updated_at");
            $this->createColumnOption($option, "created_by");
            $this->createColumnOption($option, "updated_by");
            $this->createColumnOption($option, "header_slot_id");
            $this->createColumnOption($option, "part_kind");
            $this->createColumnOption($option, "column_pct");
            $this->createColumnOption($option, "align");
            $this->createColumnOption($option, "font_name");
            $this->createColumnOption($option, "line1_size_pt");
            $this->createColumnOption($option, "line2_size_pt");
            $this->createColumnOption($option, "line1_bold");
            $this->createColumnOption($option, "line2_bold");
            $this->createColumnOption($option, "line1_color");
            $this->createColumnOption($option, "line2_color");
        }

        // Set up custom actions
        foreach ($this->CustomActions as $name => $action) {
            $this->ListActions[$name] = $action;
        }

        // Set up options default
        foreach ($options as $name => $option) {
            if ($name != "column") { // Always use dropdown for column
                $option->UseDropDownButton = false;
                $option->UseButtonGroup = true;
            }
            //$option->ButtonClass = ""; // Class for button group
            $item = &$option->addGroupOption();
            $item->Body = "";
            $item->Visible = false;
        }
        $options["addedit"]->DropDownButtonPhrase = $Language->phrase("ButtonAddEdit");
        $options["detail"]->DropDownButtonPhrase = $Language->phrase("ButtonDetails");
        $options["action"]->DropDownButtonPhrase = $Language->phrase("ButtonActions");

        // Filter button
        $item = &$this->FilterOptions->add("savecurrentfilter");
        $item->Body = "<a class=\"ew-save-filter\" data-form=\"fdocument_header_assetssrch\" data-ew-action=\"none\">" . $Language->phrase("SaveCurrentFilter") . "</a>";
        $item->Visible = true;
        $item = &$this->FilterOptions->add("deletefilter");
        $item->Body = "<a class=\"ew-delete-filter\" data-form=\"fdocument_header_assetssrch\" data-ew-action=\"none\">" . $Language->phrase("DeleteFilter") . "</a>";
        $item->Visible = true;
        $this->FilterOptions->UseDropDownButton = true;
        $this->FilterOptions->UseButtonGroup = !$this->FilterOptions->UseDropDownButton;
        $this->FilterOptions->DropDownButtonPhrase = $Language->phrase("Filters");

        // Add group option item
        $item = &$this->FilterOptions->addGroupOption();
        $item->Body = "";
        $item->Visible = false;

        // Page header/footer options
        $this->HeaderOptions = new ListOptions(TagClassName: "ew-header-option", UseDropDownButton: false, UseButtonGroup: false);
        $item = &$this->HeaderOptions->addGroupOption();
        $item->Body = "";
        $item->Visible = false;
        $this->FooterOptions = new ListOptions(TagClassName: "ew-footer-option", UseDropDownButton: false, UseButtonGroup: false);
        $item = &$this->FooterOptions->addGroupOption();
        $item->Body = "";
        $item->Visible = false;

        // Show active user count from SQL
    }

    // Active user filter
    // - Get active users by SQL (SELECT COUNT(*) FROM UserTable WHERE ProfileField LIKE '%"SessionID":%')
    protected function activeUserFilter()
    {
        if (UserProfile::$FORCE_LOGOUT_USER) {
            $userProfileField = $this->Fields[Config("USER_PROFILE_FIELD_NAME")];
            return $userProfileField->Expression . " LIKE '%\"" . UserProfile::$SESSION_ID . "\":%'";
        }
        return "0=1"; // No active users
    }

    // Create new column option
    protected function createColumnOption($option, $name)
    {
        $field = $this->Fields[$name] ?? null;
        if ($field?->Visible) {
            $item = $option->add($field->Name);
            $item->Body = '<button class="dropdown-item">' .
                '<div class="form-check ew-dropdown-checkbox">' .
                '<div class="form-check-input ew-dropdown-check-input" data-field="' . $field->Param . '"></div>' .
                '<label class="form-check-label ew-dropdown-check-label">' . $field->caption() . '</label></div></button>';
        }
    }

    // Render other options
    public function renderOtherOptions()
    {
        global $Language, $Security;
        $options = &$this->OtherOptions;
        $option = $options["action"];
        // Set up list action buttons
        foreach ($this->ListActions as $listAction) {
            if ($listAction->Select == ACTION_MULTIPLE) {
                $item = &$option->add("custom_" . $listAction->Action);
                $caption = $listAction->Caption;
                $icon = ($listAction->Icon != "") ? '<i class="' . HtmlEncode($listAction->Icon) . '" data-caption="' . HtmlEncode($caption) . '"></i>' . $caption : $caption;
                $item->Body = '<button type="button" class="btn btn-default ew-action ew-list-action" title="' . HtmlEncode($caption) . '" data-caption="' . HtmlEncode($caption) . '" data-ew-action="submit" form="fdocument_header_assetslist"' . $listAction->toDataAttributes() . '>' . $icon . '</button>';
                $item->Visible = $listAction->Allowed;
            }
        }

        // Hide multi edit, grid edit and other options
        if ($this->TotalRecords <= 0) {
            $option = $options["addedit"];
            $item = $option["gridedit"];
            if ($item) {
                $item->Visible = false;
            }
            $option = $options["action"];
            $option->hideAllOptions();
        }
    }

    // Process list action
    protected function processListAction()
    {
        global $Language, $Security, $Response;
        $users = [];
        $user = "";
        $filter = $this->getFilterFromRecordKeys();
        $userAction = Post("action", "");
        if ($filter != "" && $userAction != "") {
            $conn = $this->getConnection();
            // Clear current action
            $this->CurrentAction = "";
            // Check permission first
            $actionCaption = $userAction;
            $listAction = $this->ListActions[$userAction] ?? null;
            if ($listAction) {
                $this->UserAction = $userAction;
                $actionCaption = $listAction->Caption ?: $listAction->Action;
                if (!$listAction->Allowed) {
                    $errmsg = str_replace('%s', $actionCaption, $Language->phrase("CustomActionNotAllowed"));
                    if (Post("ajax") == $userAction) { // Ajax
                        echo "<p class=\"text-danger\">" . $errmsg . "</p>";
                        return true;
                    } else {
                        $this->setFailureMessage($errmsg);
                        return false;
                    }
                }
            } else {
                // Skip checking, handle by Row_CustomAction
            }
            $rows = $this->loadRs($filter)->fetchAllAssociative();
            $this->SelectedCount = count($rows);
            $this->ActionValue = Post("actionvalue");

            // Call row action event
            if ($this->SelectedCount > 0) {
                if ($this->UseTransaction) {
                    $conn->beginTransaction();
                }
                $this->SelectedIndex = 0;
                foreach ($rows as $row) {
                    $this->SelectedIndex++;
                    if ($listAction) {
                        $processed = $listAction->handle($row, $this);
                        if (!$processed) {
                            break;
                        }
                    }
                    $processed = $this->rowCustomAction($userAction, $row);
                    if (!$processed) {
                        break;
                    }
                }
                if ($processed) {
                    if ($this->UseTransaction) { // Commit transaction
                        if ($conn->isTransactionActive()) {
                            $conn->commit();
                        }
                    }
                    if ($this->getSuccessMessage() == "") {
                        $this->setSuccessMessage($listAction->SuccessMessage);
                    }
                    if ($this->getSuccessMessage() == "") {
                        $this->setSuccessMessage(str_replace("%s", $actionCaption, $Language->phrase("CustomActionCompleted"))); // Set up success message
                    }
                } else {
                    if ($this->UseTransaction) { // Rollback transaction
                        if ($conn->isTransactionActive()) {
                            $conn->rollback();
                        }
                    }
                    if ($this->getFailureMessage() == "") {
                        $this->setFailureMessage($listAction->FailureMessage);
                    }

                    // Set up error message
                    if ($this->getSuccessMessage() != "" || $this->getFailureMessage() != "") {
                        // Use the message, do nothing
                    } elseif ($this->CancelMessage != "") {
                        $this->setFailureMessage($this->CancelMessage);
                        $this->CancelMessage = "";
                    } else {
                        $this->setFailureMessage(str_replace('%s', $actionCaption, $Language->phrase("CustomActionFailed")));
                    }
                }
            }
            if (Post("ajax") == $userAction) { // Ajax
                if (WithJsonResponse()) { // List action returns JSON
                    $this->clearSuccessMessage(); // Clear success message
                    $this->clearFailureMessage(); // Clear failure message
                } else {
                    if ($this->getSuccessMessage() != "") {
                        echo "<p class=\"text-success\">" . $this->getSuccessMessage() . "</p>";
                        $this->clearSuccessMessage(); // Clear success message
                    }
                    if ($this->getFailureMessage() != "") {
                        echo "<p class=\"text-danger\">" . $this->getFailureMessage() . "</p>";
                        $this->clearFailureMessage(); // Clear failure message
                    }
                }
                return true;
            }
        }
        return false; // Not ajax request
    }

    // Set up Grid
    public function setupGrid()
    {
        global $CurrentForm;
        if ($this->ExportAll && $this->isExport()) {
            $this->StopRecord = $this->TotalRecords;
        } else {
            // Set the last record to display
            if ($this->TotalRecords > $this->StartRecord + $this->DisplayRecords - 1) {
                $this->StopRecord = $this->StartRecord + $this->DisplayRecords - 1;
            } else {
                $this->StopRecord = $this->TotalRecords;
            }
        }
        $this->RecordCount = $this->StartRecord - 1;
        if ($this->CurrentRow !== false) {
            // Nothing to do
        } elseif ($this->isGridAdd() && !$this->AllowAddDeleteRow && $this->StopRecord == 0) { // Grid-Add with no records
            $this->StopRecord = $this->GridAddRowCount;
        } elseif ($this->isAdd() && $this->TotalRecords == 0) { // Inline-Add with no records
            $this->StopRecord = 1;
        }

        // Initialize aggregate
        $this->RowType = RowType::AGGREGATEINIT;
        $this->resetAttributes();
        $this->renderRow();
        if (($this->isGridAdd() || $this->isGridEdit())) { // Render template row first
            $this->RowIndex = '$rowindex$';
        }
    }

    // Set up Row
    public function setupRow()
    {
        global $CurrentForm;
        if ($this->isGridAdd() || $this->isGridEdit()) {
            if ($this->RowIndex === '$rowindex$') { // Render template row first
                $this->loadRowValues();

                // Set row properties
                $this->resetAttributes();
                $this->RowAttrs->merge(["data-rowindex" => $this->RowIndex, "id" => "r0_document_header_assets", "data-rowtype" => RowType::ADD]);
                $this->RowAttrs->appendClass("ew-template");
                // Render row
                $this->RowType = RowType::ADD;
                $this->renderRow();

                // Render list options
                $this->renderListOptions();

                // Reset record count for template row
                $this->RecordCount--;
                return;
            }
        }

        // Set up key count
        $this->KeyCount = $this->RowIndex;

        // Init row class and style
        $this->resetAttributes();
        $this->CssClass = "";
        if ($this->isCopy() && $this->InlineRowCount == 0 && !$this->loadRow()) { // Inline copy
            $this->CurrentAction = "add";
        }
        if ($this->isAdd() && $this->InlineRowCount == 0 || $this->isGridAdd()) {
            $this->loadRowValues(); // Load default values
            $this->OldKey = "";
            $this->setKey($this->OldKey);
        } elseif ($this->isInlineInserted() && $this->UseInfiniteScroll) {
            // Nothing to do, just use current values
        } elseif (!($this->isCopy() && $this->InlineRowCount == 0)) {
            $this->loadRowValues($this->CurrentRow); // Load row values
            if ($this->isGridEdit() || $this->isMultiEdit()) {
                $this->OldKey = $this->getKey(true); // Get from CurrentValue
                $this->setKey($this->OldKey);
            }
        }
        $this->RowType = RowType::VIEW; // Render view
        if (($this->isAdd() || $this->isCopy()) && $this->InlineRowCount == 0 || $this->isGridAdd()) { // Add
            $this->RowType = RowType::ADD; // Render add
        }

        // Inline Add/Copy row (row 0)
        if ($this->RowType == RowType::ADD && ($this->isAdd() || $this->isCopy())) {
            $this->InlineRowCount++;
            $this->RecordCount--; // Reset record count for inline add/copy row
            if ($this->TotalRecords == 0) { // Reset stop record if no records
                $this->StopRecord = 0;
            }
        } else {
            // Inline Edit row
            if ($this->RowType == RowType::EDIT && $this->isEdit()) {
                $this->InlineRowCount++;
            }
            $this->RowCount++; // Increment row count
        }

        // Set up row attributes
        $this->RowAttrs->merge([
            "data-rowindex" => $this->RowCount,
            "data-key" => $this->getKey(true),
            "id" => "r" . $this->RowCount . "_document_header_assets",
            "data-rowtype" => $this->RowType,
            "data-inline" => ($this->isAdd() || $this->isCopy() || $this->isEdit()) ? "true" : "false", // Inline-Add/Copy/Edit
            "class" => ($this->RowCount % 2 != 1) ? "ew-table-alt-row" : "",
        ]);
        if ($this->isAdd() && $this->RowType == RowType::ADD || $this->isEdit() && $this->RowType == RowType::EDIT) { // Inline-Add/Edit row
            $this->RowAttrs->appendClass("table-active");
        }

        // Render row
        $this->renderRow();

        // Render list options
        $this->renderListOptions();
    }

    // Load basic search values
    protected function loadBasicSearchValues()
    {
        $this->BasicSearch->setKeyword(Get(Config("TABLE_BASIC_SEARCH"), ""), false);
        if ($this->BasicSearch->Keyword != "" && $this->Command == "") {
            $this->Command = "search";
        }
        $this->BasicSearch->setType(Get(Config("TABLE_BASIC_SEARCH_TYPE"), ""), false);
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

    // Load old record
    protected function loadOldRecord()
    {
        // Load old record
        if ($this->OldKey != "") {
            $this->setKey($this->OldKey);
            $this->CurrentFilter = $this->getRecordFilter();
            $sql = $this->getCurrentSql();
            $conn = $this->getConnection();
            $rs = ExecuteQuery($sql, $conn);
            if ($row = $rs->fetch()) {
                $this->loadRowValues($row); // Load row values
                return $row;
            }
        }
        $this->loadRowValues(); // Load default row values
        return null;
    }

    // Render row values based on field settings
    public function renderRow()
    {
        global $Security, $Language, $CurrentLanguage;

        // Initialize URLs
        $this->ViewUrl = $this->getViewUrl();
        $this->EditUrl = $this->getEditUrl();
        $this->InlineEditUrl = $this->getInlineEditUrl();
        $this->CopyUrl = $this->getCopyUrl();
        $this->InlineCopyUrl = $this->getInlineCopyUrl();
        $this->DeleteUrl = $this->getDeleteUrl();

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

    // Set up search options
    protected function setupSearchOptions()
    {
        global $Language, $Security;
        $pageUrl = $this->pageUrl(false);
        $this->SearchOptions = new ListOptions(TagClassName: "ew-search-option");

        // Search button
        $item = &$this->SearchOptions->add("searchtoggle");
        $searchToggleClass = ($this->SearchWhere != "") ? " active" : " active";
        $item->Body = "<a class=\"btn btn-default ew-search-toggle" . $searchToggleClass . "\" role=\"button\" title=\"" . $Language->phrase("SearchPanel") . "\" data-caption=\"" . $Language->phrase("SearchPanel") . "\" data-ew-action=\"search-toggle\" data-form=\"fdocument_header_assetssrch\" aria-pressed=\"" . ($searchToggleClass == " active" ? "true" : "false") . "\">" . $Language->phrase("SearchLink") . "</a>";
        $item->Visible = true;

        // Show all button
        $item = &$this->SearchOptions->add("showall");
        if ($this->UseCustomTemplate || !$this->UseAjaxActions) {
            $item->Body = "<a class=\"btn btn-default ew-show-all\" role=\"button\" title=\"" . $Language->phrase("ShowAll") . "\" data-caption=\"" . $Language->phrase("ShowAll") . "\" href=\"" . $pageUrl . "cmd=reset\">" . $Language->phrase("ShowAllBtn") . "</a>";
        } else {
            $item->Body = "<a class=\"btn btn-default ew-show-all\" role=\"button\" title=\"" . $Language->phrase("ShowAll") . "\" data-caption=\"" . $Language->phrase("ShowAll") . "\" data-ew-action=\"refresh\" data-url=\"" . $pageUrl . "cmd=reset\">" . $Language->phrase("ShowAllBtn") . "</a>";
        }
        $item->Visible = ($this->SearchWhere != $this->DefaultSearchWhere && $this->SearchWhere != "0=101");

        // Button group for search
        $this->SearchOptions->UseDropDownButton = false;
        $this->SearchOptions->UseButtonGroup = true;
        $this->SearchOptions->DropDownButtonPhrase = $Language->phrase("ButtonSearch");

        // Add group option item
        $item = &$this->SearchOptions->addGroupOption();
        $item->Body = "";
        $item->Visible = false;

        // Hide search options
        if ($this->isExport() || $this->CurrentAction && $this->CurrentAction != "search") {
            $this->SearchOptions->hideAllOptions();
        }
        if (!$Security->canSearch()) {
            $this->SearchOptions->hideAllOptions();
            $this->FilterOptions->hideAllOptions();
        }
    }

    // Check if any search fields
    public function hasSearchFields()
    {
        return true;
    }

    // Render search options
    protected function renderSearchOptions()
    {
        if (!$this->hasSearchFields() && $this->SearchOptions["searchtoggle"]) {
            $this->SearchOptions["searchtoggle"]->Visible = false;
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $url = preg_replace('/\?cmd=reset(all){0,1}$/i', '', $url); // Remove cmd=reset(all)
        $Breadcrumb->add("list", $this->TableVar, $url, "", $this->TableVar, true);
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

    // Set up starting record parameters
    public function setupStartRecord()
    {
        if ($this->DisplayRecords == 0) {
            return;
        }
        $pageNo = Get(Config("TABLE_PAGE_NUMBER"));
        $startRec = Get(Config("TABLE_START_REC"));
        $infiniteScroll = ConvertToBool(Param("infinitescroll"));
        if ($pageNo !== null) { // Check for "pageno" parameter first
            $pageNo = ParseInteger($pageNo);
            if (is_numeric($pageNo)) {
                $this->StartRecord = ($pageNo - 1) * $this->DisplayRecords + 1;
                if ($this->StartRecord <= 0) {
                    $this->StartRecord = 1;
                } elseif ($this->StartRecord >= (int)(($this->TotalRecords - 1) / $this->DisplayRecords) * $this->DisplayRecords + 1) {
                    $this->StartRecord = (int)(($this->TotalRecords - 1) / $this->DisplayRecords) * $this->DisplayRecords + 1;
                }
            }
        } elseif ($startRec !== null && is_numeric($startRec)) { // Check for "start" parameter
            $this->StartRecord = $startRec;
        } elseif (!$infiniteScroll) {
            $this->StartRecord = $this->getStartRecordNumber();
        }

        // Check if correct start record counter
        if (!is_numeric($this->StartRecord) || intval($this->StartRecord) <= 0) { // Avoid invalid start record counter
            $this->StartRecord = 1; // Reset start record counter
        } elseif ($this->StartRecord > $this->TotalRecords) { // Avoid starting record > total records
            $this->StartRecord = (int)(($this->TotalRecords - 1) / $this->DisplayRecords) * $this->DisplayRecords + 1; // Point to last page first record
        } elseif (($this->StartRecord - 1) % $this->DisplayRecords != 0) {
            $this->StartRecord = (int)(($this->StartRecord - 1) / $this->DisplayRecords) * $this->DisplayRecords + 1; // Point to page boundary
        }
        if (!$infiniteScroll) {
            $this->setStartRecordNumber($this->StartRecord);
        }
    }

    // Get page count
    public function pageCount() {
        return ceil($this->TotalRecords / $this->DisplayRecords);
    }

    // Parse query builder rule
    protected function parseRules($group, $fieldName = "", $itemName = "") {
        $group["condition"] ??= "AND";
        if (!in_array($group["condition"], ["AND", "OR"])) {
            throw new \Exception("Unable to build SQL query with condition '" . $group["condition"] . "'");
        }
        if (!is_array($group["rules"] ?? null)) {
            return "";
        }
        $parts = [];
        foreach ($group["rules"] as $rule) {
            if (is_array($rule["rules"] ?? null) && count($rule["rules"]) > 0) {
                $part = $this->parseRules($rule, $fieldName, $itemName);
                if ($part) {
                    $parts[] = "(" . " " . $part . " " . ")" . " ";
                }
            } else {
                $field = $rule["field"];
                $fld = $this->fieldByParam($field);
                $dbid = $this->Dbid;
                if ($fld instanceof ReportField && is_array($fld->DashboardSearchSourceFields)) {
                    $item = $fld->DashboardSearchSourceFields[$itemName] ?? null;
                    if ($item) {
                        $tbl = Container($item["table"]);
                        $dbid = $tbl->Dbid;
                        $fld = $tbl->Fields[$item["field"]];
                    } else {
                        $fld = null;
                    }
                }
                if ($fld && ($fieldName == "" || $fld->Name == $fieldName)) { // Field name not specified or matched field name
                    $fldOpr = array_search($rule["operator"], Config("CLIENT_SEARCH_OPERATORS"));
                    $ope = Config("QUERY_BUILDER_OPERATORS")[$rule["operator"]] ?? null;
                    if (!$ope || !$fldOpr) {
                        throw new \Exception("Unknown SQL operation for operator '" . $rule["operator"] . "'");
                    }
                    if ($ope["nb_inputs"] > 0 && isset($rule["value"]) && !EmptyValue($rule["value"]) || IsNullOrEmptyOperator($fldOpr)) {
                        $fldVal = $rule["value"];
                        if (is_array($fldVal)) {
                            $fldVal = $fld->isMultiSelect() ? implode(Config("MULTIPLE_OPTION_SEPARATOR"), $fldVal) : $fldVal[0];
                        }
                        $useFilter = $fld->UseFilter; // Query builder does not use filter
                        try {
                            if ($fld instanceof ReportField) { // Search report fields
                                if ($fld->SearchType == "dropdown") {
                                    if (is_array($fldVal)) {
                                        $sql = "";
                                        foreach ($fldVal as $val) {
                                            AddFilter($sql, DropDownFilter($fld, $val, $fldOpr, $dbid), "OR");
                                        }
                                        $parts[] = $sql;
                                    } else {
                                        $parts[] = DropDownFilter($fld, $fldVal, $fldOpr, $dbid);
                                    }
                                } else {
                                    $fld->AdvancedSearch->SearchOperator = $fldOpr;
                                    $fld->AdvancedSearch->SearchValue = $fldVal;
                                    $parts[] = GetReportFilter($fld, false, $dbid);
                                }
                            } else { // Search normal fields
                                if ($fld->isMultiSelect()) {
                                    $parts[] = $fldVal != "" ? GetMultiSearchSql($fld, $fldOpr, ConvertSearchValue($fldVal, $fldOpr, $fld), $this->Dbid) : "";
                                } else {
                                    $fldVal2 = ContainsString($fldOpr, "BETWEEN") ? $rule["value"][1] : ""; // BETWEEN
                                    if (is_array($fldVal2)) {
                                        $fldVal2 = implode(Config("MULTIPLE_OPTION_SEPARATOR"), $fldVal2);
                                    }
                                    $fld->AdvancedSearch->SearchValue = ConvertSearchValue($fldVal, $fldOpr, $fld);
                                    $fld->AdvancedSearch->SearchValue2 = ConvertSearchValue($fldVal2, $fldOpr, $fld);
                                    $parts[] = GetSearchSql(
                                        $fld,
                                        $fld->AdvancedSearch->SearchValue, // SearchValue
                                        $fldOpr,
                                        "", // $fldCond not used
                                        $fld->AdvancedSearch->SearchValue2, // SearchValue2
                                        "", // $fldOpr2 not used
                                        $this->Dbid
                                    );
                                }
                            }
                        } finally {
                            $fld->UseFilter = $useFilter;
                        }
                    }
                }
            }
        }
        $where = "";
        foreach ($parts as $part) {
            AddFilter($where, $part, $group["condition"]);
        }
        if ($where && ($group["not"] ?? false)) {
            $where = "NOT (" . $where . ")";
        }
        return $where;
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

    // Form Custom Validate event
    public function formCustomValidate(&$customError)
    {
        // Return error message in $customError
        return true;
    }

    // ListOptions Load event
    public function listOptionsLoad()
    {
        // Example:
        //$opt = &$this->ListOptions->add("new");
        //$opt->Header = "xxx";
        //$opt->OnLeft = true; // Link on left
        //$opt->moveTo(0); // Move to first column
    }

    // ListOptions Rendering event
    public function listOptionsRendering()
    {
        //Container("DetailTableGrid")->DetailAdd = (...condition...); // Set to true or false conditionally
        //Container("DetailTableGrid")->DetailEdit = (...condition...); // Set to true or false conditionally
        //Container("DetailTableGrid")->DetailView = (...condition...); // Set to true or false conditionally
    }

    // ListOptions Rendered event
    public function listOptionsRendered()
    {
        // Example:
        //$this->ListOptions["new"]->Body = "xxx";
    }

    // Row Custom Action event
    public function rowCustomAction($action, $row)
    {
        // Return false to abort
        return true;
    }

    // Page Exporting event
    // $doc = export object
    public function pageExporting(&$doc)
    {
        //$doc->Text = "my header"; // Export header
        //return false; // Return false to skip default export and use Row_Export event
        return true; // Return true to use default export and skip Row_Export event
    }

    // Row Export event
    // $doc = export document object
    public function rowExport($doc, $rs)
    {
        //$doc->Text .= "my content"; // Build HTML with field value: $rs["MyField"] or $this->MyField->ViewValue
    }

    // Page Exported event
    // $doc = export document object
    public function pageExported($doc)
    {
        //$doc->Text .= "my footer"; // Export footer
        //Log($doc->Text);
    }

    // Page Importing event
    public function pageImporting(&$builder, &$options)
    {
        //var_dump($options); // Show all options for importing
        //$builder = fn($workflow) => $workflow->addStep($myStep);
        //return false; // Return false to skip import
        return true;
    }

    // Row Import event
    public function rowImport(&$row, $cnt)
    {
        //Log($cnt); // Import record count
        //var_dump($row); // Import row
        //return false; // Return false to skip import
        return true;
    }

    // Page Imported event
    public function pageImported($obj, $results)
    {
        //var_dump($obj); // Workflow result object
        //var_dump($results); // Import results
    }
}
