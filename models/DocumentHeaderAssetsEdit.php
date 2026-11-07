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
class DocumentHeaderAssetsEdit extends DocumentHeaderAssets
{
    use MessagesTrait;

    // Page ID
    public $PageID = "edit";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "DocumentHeaderAssetsEdit";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "DocumentHeaderAssetsEdit";

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
        $this->image_data->setVisibility();
        $this->is_active->setVisibility();
        $this->notes->setVisibility();
        $this->created_at->setVisibility();
        $this->updated_at->setVisibility();
        $this->created_by->setVisibility();
        $this->updated_by->setVisibility();
        $this->header_slot_id->setVisibility();
        $this->part_kind->setVisibility();
        $this->column_pct->setVisibility();
        $this->align->setVisibility();
        $this->text_line1->setVisibility();
        $this->text_line2->setVisibility();
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
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-edit-table";

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

            // Handle modal response
            if ($this->IsModal) { // Show as modal
                $pageName = GetPageName($url);
                $result = ["url" => GetUrl($url), "modal" => "1"];  // Assume return to modal for simplicity
                if (
                    SameString($pageName, GetPageName($this->getListUrl())) ||
                    SameString($pageName, GetPageName($this->getViewUrl())) ||
                    SameString($pageName, GetPageName(CurrentMasterTable()?->getViewUrl() ?? ""))
                ) { // List / View / Master View page
                    if (!SameString($pageName, GetPageName($this->getListUrl()))) { // Not List page
                        $result["caption"] = $this->getModalCaption($pageName);
                        $result["view"] = SameString($pageName, "DocumentHeaderAssetsView"); // If View page, no primary button
                    } else { // List page
                        $result["error"] = $this->getFailureMessage(); // List page should not be shown as modal => error
                        $this->clearFailureMessage();
                    }
                } else { // Other pages (add messages and then clear messages)
                    $result = array_merge($this->getMessages(), ["modal" => "1"]);
                    $this->clearMessages();
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

    // Properties
    public $FormClassName = "ew-form ew-edit-form overlay-wrapper";
    public $IsModal = false;
    public $IsMobileOrModal = false;
    public $DbMasterFilter;
    public $DbDetailFilter;
    public $HashValue; // Hash Value
    public $DisplayRecords = 1;
    public $StartRecord;
    public $StopRecord;
    public $TotalRecords = 0;
    public $RecordRange = 10;
    public $RecordCount;

    /**
     * Page run
     *
     * @return void
     */
    public function run()
    {
        global $ExportType, $Language, $Security, $CurrentForm, $SkipHeaderFooter;

        // Is modal
        $this->IsModal = ConvertToBool(Param("modal"));
        $this->UseLayout = $this->UseLayout && !$this->IsModal;

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

        // Create form object
        $CurrentForm = new HttpForm();
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

        // Check modal
        if ($this->IsModal) {
            $SkipHeaderFooter = true;
        }
        $this->IsMobileOrModal = IsMobile() || $this->IsModal;
        $loaded = false;
        $postBack = false;

        // Set up current action and primary key
        if (IsApi()) {
            // Load key values
            $loaded = true;
            if (($keyValue = Get("header_asset_id") ?? Key(0) ?? Route(2)) !== null) {
                $this->header_asset_id->setQueryStringValue($keyValue);
                $this->header_asset_id->setOldValue($this->header_asset_id->QueryStringValue);
            } elseif (Post("header_asset_id") !== null) {
                $this->header_asset_id->setFormValue(Post("header_asset_id"));
                $this->header_asset_id->setOldValue($this->header_asset_id->FormValue);
            } else {
                $loaded = false; // Unable to load key
            }

            // Load record
            if ($loaded) {
                $loaded = $this->loadRow();
            }
            if (!$loaded) {
                $this->setFailureMessage($Language->phrase("NoRecord")); // Set no record message
                $this->terminate();
                return;
            }
            $this->CurrentAction = "update"; // Update record directly
            $this->OldKey = $this->getKey(true); // Get from CurrentValue
            $postBack = true;
        } else {
            if (Post("action", "") !== "") {
                $this->CurrentAction = Post("action"); // Get action code
                if (!$this->isShow()) { // Not reload record, handle as postback
                    $postBack = true;
                }

                // Get key from Form
                $this->setKey(Post($this->OldKeyName), $this->isShow());
            } else {
                $this->CurrentAction = "show"; // Default action is display

                // Load key from QueryString
                $loadByQuery = false;
                if (($keyValue = Get("header_asset_id") ?? Route("header_asset_id")) !== null) {
                    $this->header_asset_id->setQueryStringValue($keyValue);
                    $loadByQuery = true;
                } else {
                    $this->header_asset_id->CurrentValue = null;
                }
            }

            // Load result set
            if ($this->isShow()) {
                    // Load current record
                    $loaded = $this->loadRow();
                $this->OldKey = $loaded ? $this->getKey(true) : ""; // Get from CurrentValue
            }
        }

        // Process form if post back
        if ($postBack) {
            $this->loadFormValues(); // Get form values
        }

        // Validate form if post back
        if ($postBack) {
            if (!$this->validateForm()) {
                $this->EventCancelled = true; // Event cancelled
                $this->restoreFormValues();
                if (IsApi()) {
                    $this->terminate();
                    return;
                } else {
                    $this->CurrentAction = ""; // Form error, reset action
                }
            }
        }

        // Perform current action
        switch ($this->CurrentAction) {
            case "show": // Get a record to display
                    if (!$loaded) { // Load record based on key
                        if ($this->getFailureMessage() == "") {
                            $this->setFailureMessage($Language->phrase("NoRecord")); // No record found
                        }
                        $this->terminate("DocumentHeaderAssetsList"); // No matching record, return to list
                        return;
                    }
                break;
            case "update": // Update
                $returnUrl = $this->getReturnUrl();
                if (GetPageName($returnUrl) == "DocumentHeaderAssetsList") {
                    $returnUrl = $this->addMasterUrl($returnUrl); // List page, return to List page with correct master key if necessary
                }
                $this->SendEmail = true; // Send email on update success
                if ($this->editRow()) { // Update record based on key
                    if ($this->getSuccessMessage() == "") {
                        $this->setSuccessMessage($Language->phrase("UpdateSuccess")); // Update success
                    }

                    // Handle UseAjaxActions with return page
                    if ($this->IsModal && $this->UseAjaxActions) {
                        $this->IsModal = false;
                        if (GetPageName($returnUrl) != "DocumentHeaderAssetsList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "DocumentHeaderAssetsList"; // Return list page content
                        }
                    }
                    if (IsJsonResponse()) {
                        $this->terminate(true);
                        return;
                    } else {
                        $this->terminate($returnUrl); // Return to caller
                        return;
                    }
                } elseif (IsApi()) { // API request, return
                    $this->terminate();
                    return;
                } elseif ($this->IsModal && $this->UseAjaxActions) { // Return JSON error message
                    WriteJson(["success" => false, "validation" => $this->getValidationErrors(), "error" => $this->getFailureMessage()]);
                    $this->clearFailureMessage();
                    $this->terminate();
                    return;
                } elseif ($this->getFailureMessage() == $Language->phrase("NoRecord")) {
                    $this->terminate($returnUrl); // Return to caller
                    return;
                } else {
                    $this->EventCancelled = true; // Event cancelled
                    $this->restoreFormValues(); // Restore form values if update failed
                }
        }

        // Set up Breadcrumb
        $this->setupBreadcrumb();

        // Render the record
        $this->RowType = RowType::EDIT; // Render as Edit
        $this->resetAttributes();
        $this->renderRow();

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

    // Get upload files
    protected function getUploadFiles()
    {
        global $CurrentForm, $Language;
    }

    // Load form values
    protected function loadFormValues()
    {
        // Load from form
        global $CurrentForm;
        $validate = !Config("SERVER_VALIDATE");

        // Check field name 'header_asset_id' first before field var 'x_header_asset_id'
        $val = $CurrentForm->hasValue("header_asset_id") ? $CurrentForm->getValue("header_asset_id") : $CurrentForm->getValue("x_header_asset_id");
        if (!$this->header_asset_id->IsDetailKey) {
            $this->header_asset_id->setFormValue($val);
        }

        // Check field name 'asset_code' first before field var 'x_asset_code'
        $val = $CurrentForm->hasValue("asset_code") ? $CurrentForm->getValue("asset_code") : $CurrentForm->getValue("x_asset_code");
        if (!$this->asset_code->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->asset_code->Visible = false; // Disable update for API request
            } else {
                $this->asset_code->setFormValue($val);
            }
        }

        // Check field name 'asset_name' first before field var 'x_asset_name'
        $val = $CurrentForm->hasValue("asset_name") ? $CurrentForm->getValue("asset_name") : $CurrentForm->getValue("x_asset_name");
        if (!$this->asset_name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->asset_name->Visible = false; // Disable update for API request
            } else {
                $this->asset_name->setFormValue($val);
            }
        }

        // Check field name 'document_asset_type_id' first before field var 'x_document_asset_type_id'
        $val = $CurrentForm->hasValue("document_asset_type_id") ? $CurrentForm->getValue("document_asset_type_id") : $CurrentForm->getValue("x_document_asset_type_id");
        if (!$this->document_asset_type_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->document_asset_type_id->Visible = false; // Disable update for API request
            } else {
                $this->document_asset_type_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'scope_system_code' first before field var 'x_scope_system_code'
        $val = $CurrentForm->hasValue("scope_system_code") ? $CurrentForm->getValue("scope_system_code") : $CurrentForm->getValue("x_scope_system_code");
        if (!$this->scope_system_code->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->scope_system_code->Visible = false; // Disable update for API request
            } else {
                $this->scope_system_code->setFormValue($val);
            }
        }

        // Check field name 'scope_document_code' first before field var 'x_scope_document_code'
        $val = $CurrentForm->hasValue("scope_document_code") ? $CurrentForm->getValue("scope_document_code") : $CurrentForm->getValue("x_scope_document_code");
        if (!$this->scope_document_code->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->scope_document_code->Visible = false; // Disable update for API request
            } else {
                $this->scope_document_code->setFormValue($val);
            }
        }

        // Check field name 'file_name' first before field var 'x_file_name'
        $val = $CurrentForm->hasValue("file_name") ? $CurrentForm->getValue("file_name") : $CurrentForm->getValue("x_file_name");
        if (!$this->file_name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->file_name->Visible = false; // Disable update for API request
            } else {
                $this->file_name->setFormValue($val);
            }
        }

        // Check field name 'mime_type' first before field var 'x_mime_type'
        $val = $CurrentForm->hasValue("mime_type") ? $CurrentForm->getValue("mime_type") : $CurrentForm->getValue("x_mime_type");
        if (!$this->mime_type->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->mime_type->Visible = false; // Disable update for API request
            } else {
                $this->mime_type->setFormValue($val);
            }
        }

        // Check field name 'file_size' first before field var 'x_file_size'
        $val = $CurrentForm->hasValue("file_size") ? $CurrentForm->getValue("file_size") : $CurrentForm->getValue("x_file_size");
        if (!$this->file_size->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->file_size->Visible = false; // Disable update for API request
            } else {
                $this->file_size->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'checksum_sha256' first before field var 'x_checksum_sha256'
        $val = $CurrentForm->hasValue("checksum_sha256") ? $CurrentForm->getValue("checksum_sha256") : $CurrentForm->getValue("x_checksum_sha256");
        if (!$this->checksum_sha256->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->checksum_sha256->Visible = false; // Disable update for API request
            } else {
                $this->checksum_sha256->setFormValue($val);
            }
        }

        // Check field name 'image_width' first before field var 'x_image_width'
        $val = $CurrentForm->hasValue("image_width") ? $CurrentForm->getValue("image_width") : $CurrentForm->getValue("x_image_width");
        if (!$this->image_width->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->image_width->Visible = false; // Disable update for API request
            } else {
                $this->image_width->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'image_height' first before field var 'x_image_height'
        $val = $CurrentForm->hasValue("image_height") ? $CurrentForm->getValue("image_height") : $CurrentForm->getValue("x_image_height");
        if (!$this->image_height->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->image_height->Visible = false; // Disable update for API request
            } else {
                $this->image_height->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'render_width' first before field var 'x_render_width'
        $val = $CurrentForm->hasValue("render_width") ? $CurrentForm->getValue("render_width") : $CurrentForm->getValue("x_render_width");
        if (!$this->render_width->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->render_width->Visible = false; // Disable update for API request
            } else {
                $this->render_width->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'render_height' first before field var 'x_render_height'
        $val = $CurrentForm->hasValue("render_height") ? $CurrentForm->getValue("render_height") : $CurrentForm->getValue("x_render_height");
        if (!$this->render_height->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->render_height->Visible = false; // Disable update for API request
            } else {
                $this->render_height->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'image_data' first before field var 'x_image_data'
        $val = $CurrentForm->hasValue("image_data") ? $CurrentForm->getValue("image_data") : $CurrentForm->getValue("x_image_data");
        if (!$this->image_data->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->image_data->Visible = false; // Disable update for API request
            } else {
                $this->image_data->setFormValue($val);
            }
        }

        // Check field name 'is_active' first before field var 'x_is_active'
        $val = $CurrentForm->hasValue("is_active") ? $CurrentForm->getValue("is_active") : $CurrentForm->getValue("x_is_active");
        if (!$this->is_active->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->is_active->Visible = false; // Disable update for API request
            } else {
                $this->is_active->setFormValue($val);
            }
        }

        // Check field name 'notes' first before field var 'x_notes'
        $val = $CurrentForm->hasValue("notes") ? $CurrentForm->getValue("notes") : $CurrentForm->getValue("x_notes");
        if (!$this->notes->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->notes->Visible = false; // Disable update for API request
            } else {
                $this->notes->setFormValue($val);
            }
        }

        // Check field name 'created_at' first before field var 'x_created_at'
        $val = $CurrentForm->hasValue("created_at") ? $CurrentForm->getValue("created_at") : $CurrentForm->getValue("x_created_at");
        if (!$this->created_at->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->created_at->Visible = false; // Disable update for API request
            } else {
                $this->created_at->setFormValue($val, true, $validate);
            }
            $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        }

        // Check field name 'updated_at' first before field var 'x_updated_at'
        $val = $CurrentForm->hasValue("updated_at") ? $CurrentForm->getValue("updated_at") : $CurrentForm->getValue("x_updated_at");
        if (!$this->updated_at->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->updated_at->Visible = false; // Disable update for API request
            } else {
                $this->updated_at->setFormValue($val, true, $validate);
            }
            $this->updated_at->CurrentValue = UnFormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern());
        }

        // Check field name 'created_by' first before field var 'x_created_by'
        $val = $CurrentForm->hasValue("created_by") ? $CurrentForm->getValue("created_by") : $CurrentForm->getValue("x_created_by");
        if (!$this->created_by->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->created_by->Visible = false; // Disable update for API request
            } else {
                $this->created_by->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'updated_by' first before field var 'x_updated_by'
        $val = $CurrentForm->hasValue("updated_by") ? $CurrentForm->getValue("updated_by") : $CurrentForm->getValue("x_updated_by");
        if (!$this->updated_by->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->updated_by->Visible = false; // Disable update for API request
            } else {
                $this->updated_by->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'header_slot_id' first before field var 'x_header_slot_id'
        $val = $CurrentForm->hasValue("header_slot_id") ? $CurrentForm->getValue("header_slot_id") : $CurrentForm->getValue("x_header_slot_id");
        if (!$this->header_slot_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->header_slot_id->Visible = false; // Disable update for API request
            } else {
                $this->header_slot_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'part_kind' first before field var 'x_part_kind'
        $val = $CurrentForm->hasValue("part_kind") ? $CurrentForm->getValue("part_kind") : $CurrentForm->getValue("x_part_kind");
        if (!$this->part_kind->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->part_kind->Visible = false; // Disable update for API request
            } else {
                $this->part_kind->setFormValue($val);
            }
        }

        // Check field name 'column_pct' first before field var 'x_column_pct'
        $val = $CurrentForm->hasValue("column_pct") ? $CurrentForm->getValue("column_pct") : $CurrentForm->getValue("x_column_pct");
        if (!$this->column_pct->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->column_pct->Visible = false; // Disable update for API request
            } else {
                $this->column_pct->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'align' first before field var 'x_align'
        $val = $CurrentForm->hasValue("align") ? $CurrentForm->getValue("align") : $CurrentForm->getValue("x_align");
        if (!$this->align->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->align->Visible = false; // Disable update for API request
            } else {
                $this->align->setFormValue($val);
            }
        }

        // Check field name 'text_line1' first before field var 'x_text_line1'
        $val = $CurrentForm->hasValue("text_line1") ? $CurrentForm->getValue("text_line1") : $CurrentForm->getValue("x_text_line1");
        if (!$this->text_line1->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->text_line1->Visible = false; // Disable update for API request
            } else {
                $this->text_line1->setFormValue($val);
            }
        }

        // Check field name 'text_line2' first before field var 'x_text_line2'
        $val = $CurrentForm->hasValue("text_line2") ? $CurrentForm->getValue("text_line2") : $CurrentForm->getValue("x_text_line2");
        if (!$this->text_line2->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->text_line2->Visible = false; // Disable update for API request
            } else {
                $this->text_line2->setFormValue($val);
            }
        }

        // Check field name 'font_name' first before field var 'x_font_name'
        $val = $CurrentForm->hasValue("font_name") ? $CurrentForm->getValue("font_name") : $CurrentForm->getValue("x_font_name");
        if (!$this->font_name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->font_name->Visible = false; // Disable update for API request
            } else {
                $this->font_name->setFormValue($val);
            }
        }

        // Check field name 'line1_size_pt' first before field var 'x_line1_size_pt'
        $val = $CurrentForm->hasValue("line1_size_pt") ? $CurrentForm->getValue("line1_size_pt") : $CurrentForm->getValue("x_line1_size_pt");
        if (!$this->line1_size_pt->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->line1_size_pt->Visible = false; // Disable update for API request
            } else {
                $this->line1_size_pt->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'line2_size_pt' first before field var 'x_line2_size_pt'
        $val = $CurrentForm->hasValue("line2_size_pt") ? $CurrentForm->getValue("line2_size_pt") : $CurrentForm->getValue("x_line2_size_pt");
        if (!$this->line2_size_pt->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->line2_size_pt->Visible = false; // Disable update for API request
            } else {
                $this->line2_size_pt->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'line1_bold' first before field var 'x_line1_bold'
        $val = $CurrentForm->hasValue("line1_bold") ? $CurrentForm->getValue("line1_bold") : $CurrentForm->getValue("x_line1_bold");
        if (!$this->line1_bold->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->line1_bold->Visible = false; // Disable update for API request
            } else {
                $this->line1_bold->setFormValue($val);
            }
        }

        // Check field name 'line2_bold' first before field var 'x_line2_bold'
        $val = $CurrentForm->hasValue("line2_bold") ? $CurrentForm->getValue("line2_bold") : $CurrentForm->getValue("x_line2_bold");
        if (!$this->line2_bold->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->line2_bold->Visible = false; // Disable update for API request
            } else {
                $this->line2_bold->setFormValue($val);
            }
        }

        // Check field name 'line1_color' first before field var 'x_line1_color'
        $val = $CurrentForm->hasValue("line1_color") ? $CurrentForm->getValue("line1_color") : $CurrentForm->getValue("x_line1_color");
        if (!$this->line1_color->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->line1_color->Visible = false; // Disable update for API request
            } else {
                $this->line1_color->setFormValue($val);
            }
        }

        // Check field name 'line2_color' first before field var 'x_line2_color'
        $val = $CurrentForm->hasValue("line2_color") ? $CurrentForm->getValue("line2_color") : $CurrentForm->getValue("x_line2_color");
        if (!$this->line2_color->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->line2_color->Visible = false; // Disable update for API request
            } else {
                $this->line2_color->setFormValue($val);
            }
        }
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->header_asset_id->CurrentValue = $this->header_asset_id->FormValue;
        $this->asset_code->CurrentValue = $this->asset_code->FormValue;
        $this->asset_name->CurrentValue = $this->asset_name->FormValue;
        $this->document_asset_type_id->CurrentValue = $this->document_asset_type_id->FormValue;
        $this->scope_system_code->CurrentValue = $this->scope_system_code->FormValue;
        $this->scope_document_code->CurrentValue = $this->scope_document_code->FormValue;
        $this->file_name->CurrentValue = $this->file_name->FormValue;
        $this->mime_type->CurrentValue = $this->mime_type->FormValue;
        $this->file_size->CurrentValue = $this->file_size->FormValue;
        $this->checksum_sha256->CurrentValue = $this->checksum_sha256->FormValue;
        $this->image_width->CurrentValue = $this->image_width->FormValue;
        $this->image_height->CurrentValue = $this->image_height->FormValue;
        $this->render_width->CurrentValue = $this->render_width->FormValue;
        $this->render_height->CurrentValue = $this->render_height->FormValue;
        $this->image_data->CurrentValue = $this->image_data->FormValue;
        $this->is_active->CurrentValue = $this->is_active->FormValue;
        $this->notes->CurrentValue = $this->notes->FormValue;
        $this->created_at->CurrentValue = $this->created_at->FormValue;
        $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        $this->updated_at->CurrentValue = $this->updated_at->FormValue;
        $this->updated_at->CurrentValue = UnFormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern());
        $this->created_by->CurrentValue = $this->created_by->FormValue;
        $this->updated_by->CurrentValue = $this->updated_by->FormValue;
        $this->header_slot_id->CurrentValue = $this->header_slot_id->FormValue;
        $this->part_kind->CurrentValue = $this->part_kind->FormValue;
        $this->column_pct->CurrentValue = $this->column_pct->FormValue;
        $this->align->CurrentValue = $this->align->FormValue;
        $this->text_line1->CurrentValue = $this->text_line1->FormValue;
        $this->text_line2->CurrentValue = $this->text_line2->FormValue;
        $this->font_name->CurrentValue = $this->font_name->FormValue;
        $this->line1_size_pt->CurrentValue = $this->line1_size_pt->FormValue;
        $this->line2_size_pt->CurrentValue = $this->line2_size_pt->FormValue;
        $this->line1_bold->CurrentValue = $this->line1_bold->FormValue;
        $this->line2_bold->CurrentValue = $this->line2_bold->FormValue;
        $this->line1_color->CurrentValue = $this->line1_color->FormValue;
        $this->line2_color->CurrentValue = $this->line2_color->FormValue;
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

        // Call Row_Rendering event
        $this->rowRendering();

        // Common render codes for all row types

        // header_asset_id
        $this->header_asset_id->RowCssClass = "row";

        // asset_code
        $this->asset_code->RowCssClass = "row";

        // asset_name
        $this->asset_name->RowCssClass = "row";

        // document_asset_type_id
        $this->document_asset_type_id->RowCssClass = "row";

        // scope_system_code
        $this->scope_system_code->RowCssClass = "row";

        // scope_document_code
        $this->scope_document_code->RowCssClass = "row";

        // file_name
        $this->file_name->RowCssClass = "row";

        // mime_type
        $this->mime_type->RowCssClass = "row";

        // file_size
        $this->file_size->RowCssClass = "row";

        // checksum_sha256
        $this->checksum_sha256->RowCssClass = "row";

        // image_width
        $this->image_width->RowCssClass = "row";

        // image_height
        $this->image_height->RowCssClass = "row";

        // render_width
        $this->render_width->RowCssClass = "row";

        // render_height
        $this->render_height->RowCssClass = "row";

        // image_data
        $this->image_data->RowCssClass = "row";

        // is_active
        $this->is_active->RowCssClass = "row";

        // notes
        $this->notes->RowCssClass = "row";

        // created_at
        $this->created_at->RowCssClass = "row";

        // updated_at
        $this->updated_at->RowCssClass = "row";

        // created_by
        $this->created_by->RowCssClass = "row";

        // updated_by
        $this->updated_by->RowCssClass = "row";

        // header_slot_id
        $this->header_slot_id->RowCssClass = "row";

        // part_kind
        $this->part_kind->RowCssClass = "row";

        // column_pct
        $this->column_pct->RowCssClass = "row";

        // align
        $this->align->RowCssClass = "row";

        // text_line1
        $this->text_line1->RowCssClass = "row";

        // text_line2
        $this->text_line2->RowCssClass = "row";

        // font_name
        $this->font_name->RowCssClass = "row";

        // line1_size_pt
        $this->line1_size_pt->RowCssClass = "row";

        // line2_size_pt
        $this->line2_size_pt->RowCssClass = "row";

        // line1_bold
        $this->line1_bold->RowCssClass = "row";

        // line2_bold
        $this->line2_bold->RowCssClass = "row";

        // line1_color
        $this->line1_color->RowCssClass = "row";

        // line2_color
        $this->line2_color->RowCssClass = "row";

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

            // asset_code
            $this->asset_code->HrefValue = "";

            // asset_name
            $this->asset_name->HrefValue = "";

            // document_asset_type_id
            $this->document_asset_type_id->HrefValue = "";

            // scope_system_code
            $this->scope_system_code->HrefValue = "";

            // scope_document_code
            $this->scope_document_code->HrefValue = "";

            // file_name
            $this->file_name->HrefValue = "";

            // mime_type
            $this->mime_type->HrefValue = "";

            // file_size
            $this->file_size->HrefValue = "";

            // checksum_sha256
            $this->checksum_sha256->HrefValue = "";

            // image_width
            $this->image_width->HrefValue = "";

            // image_height
            $this->image_height->HrefValue = "";

            // render_width
            $this->render_width->HrefValue = "";

            // render_height
            $this->render_height->HrefValue = "";

            // image_data
            $this->image_data->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // notes
            $this->notes->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // updated_at
            $this->updated_at->HrefValue = "";

            // created_by
            $this->created_by->HrefValue = "";

            // updated_by
            $this->updated_by->HrefValue = "";

            // header_slot_id
            $this->header_slot_id->HrefValue = "";

            // part_kind
            $this->part_kind->HrefValue = "";

            // column_pct
            $this->column_pct->HrefValue = "";

            // align
            $this->align->HrefValue = "";

            // text_line1
            $this->text_line1->HrefValue = "";

            // text_line2
            $this->text_line2->HrefValue = "";

            // font_name
            $this->font_name->HrefValue = "";

            // line1_size_pt
            $this->line1_size_pt->HrefValue = "";

            // line2_size_pt
            $this->line2_size_pt->HrefValue = "";

            // line1_bold
            $this->line1_bold->HrefValue = "";

            // line2_bold
            $this->line2_bold->HrefValue = "";

            // line1_color
            $this->line1_color->HrefValue = "";

            // line2_color
            $this->line2_color->HrefValue = "";
        } elseif ($this->RowType == RowType::EDIT) {
            // header_asset_id
            $this->header_asset_id->setupEditAttributes();
            $this->header_asset_id->EditValue = $this->header_asset_id->CurrentValue;

            // asset_code
            $this->asset_code->setupEditAttributes();
            if (!$this->asset_code->Raw) {
                $this->asset_code->CurrentValue = HtmlDecode($this->asset_code->CurrentValue);
            }
            $this->asset_code->EditValue = HtmlEncode($this->asset_code->CurrentValue);
            $this->asset_code->PlaceHolder = RemoveHtml($this->asset_code->caption());

            // asset_name
            $this->asset_name->setupEditAttributes();
            if (!$this->asset_name->Raw) {
                $this->asset_name->CurrentValue = HtmlDecode($this->asset_name->CurrentValue);
            }
            $this->asset_name->EditValue = HtmlEncode($this->asset_name->CurrentValue);
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
            $this->scope_system_code->EditValue = HtmlEncode($this->scope_system_code->CurrentValue);
            $this->scope_system_code->PlaceHolder = RemoveHtml($this->scope_system_code->caption());

            // scope_document_code
            $this->scope_document_code->setupEditAttributes();
            if (!$this->scope_document_code->Raw) {
                $this->scope_document_code->CurrentValue = HtmlDecode($this->scope_document_code->CurrentValue);
            }
            $this->scope_document_code->EditValue = HtmlEncode($this->scope_document_code->CurrentValue);
            $this->scope_document_code->PlaceHolder = RemoveHtml($this->scope_document_code->caption());

            // file_name
            $this->file_name->setupEditAttributes();
            if (!$this->file_name->Raw) {
                $this->file_name->CurrentValue = HtmlDecode($this->file_name->CurrentValue);
            }
            $this->file_name->EditValue = HtmlEncode($this->file_name->CurrentValue);
            $this->file_name->PlaceHolder = RemoveHtml($this->file_name->caption());

            // mime_type
            $this->mime_type->setupEditAttributes();
            if (!$this->mime_type->Raw) {
                $this->mime_type->CurrentValue = HtmlDecode($this->mime_type->CurrentValue);
            }
            $this->mime_type->EditValue = HtmlEncode($this->mime_type->CurrentValue);
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
            $this->checksum_sha256->EditValue = HtmlEncode($this->checksum_sha256->CurrentValue);
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
            $this->image_data->EditValue = HtmlEncode($this->image_data->CurrentValue);
            $this->image_data->PlaceHolder = RemoveHtml($this->image_data->caption());

            // is_active
            $this->is_active->EditValue = $this->is_active->options(false);
            $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

            // notes
            $this->notes->setupEditAttributes();
            $this->notes->EditValue = HtmlEncode($this->notes->CurrentValue);
            $this->notes->PlaceHolder = RemoveHtml($this->notes->caption());

            // created_at
            $this->created_at->setupEditAttributes();
            $this->created_at->EditValue = HtmlEncode(FormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()));
            $this->created_at->PlaceHolder = RemoveHtml($this->created_at->caption());

            // updated_at
            $this->updated_at->setupEditAttributes();
            $this->updated_at->EditValue = HtmlEncode(FormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern()));
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
            $this->part_kind->EditValue = HtmlEncode($this->part_kind->CurrentValue);
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
            $this->align->EditValue = HtmlEncode($this->align->CurrentValue);
            $this->align->PlaceHolder = RemoveHtml($this->align->caption());

            // text_line1
            $this->text_line1->setupEditAttributes();
            $this->text_line1->EditValue = HtmlEncode($this->text_line1->CurrentValue);
            $this->text_line1->PlaceHolder = RemoveHtml($this->text_line1->caption());

            // text_line2
            $this->text_line2->setupEditAttributes();
            $this->text_line2->EditValue = HtmlEncode($this->text_line2->CurrentValue);
            $this->text_line2->PlaceHolder = RemoveHtml($this->text_line2->caption());

            // font_name
            $this->font_name->setupEditAttributes();
            if (!$this->font_name->Raw) {
                $this->font_name->CurrentValue = HtmlDecode($this->font_name->CurrentValue);
            }
            $this->font_name->EditValue = HtmlEncode($this->font_name->CurrentValue);
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
            $this->line1_color->EditValue = HtmlEncode($this->line1_color->CurrentValue);
            $this->line1_color->PlaceHolder = RemoveHtml($this->line1_color->caption());

            // line2_color
            $this->line2_color->setupEditAttributes();
            if (!$this->line2_color->Raw) {
                $this->line2_color->CurrentValue = HtmlDecode($this->line2_color->CurrentValue);
            }
            $this->line2_color->EditValue = HtmlEncode($this->line2_color->CurrentValue);
            $this->line2_color->PlaceHolder = RemoveHtml($this->line2_color->caption());

            // Edit refer script

            // header_asset_id
            $this->header_asset_id->HrefValue = "";

            // asset_code
            $this->asset_code->HrefValue = "";

            // asset_name
            $this->asset_name->HrefValue = "";

            // document_asset_type_id
            $this->document_asset_type_id->HrefValue = "";

            // scope_system_code
            $this->scope_system_code->HrefValue = "";

            // scope_document_code
            $this->scope_document_code->HrefValue = "";

            // file_name
            $this->file_name->HrefValue = "";

            // mime_type
            $this->mime_type->HrefValue = "";

            // file_size
            $this->file_size->HrefValue = "";

            // checksum_sha256
            $this->checksum_sha256->HrefValue = "";

            // image_width
            $this->image_width->HrefValue = "";

            // image_height
            $this->image_height->HrefValue = "";

            // render_width
            $this->render_width->HrefValue = "";

            // render_height
            $this->render_height->HrefValue = "";

            // image_data
            $this->image_data->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // notes
            $this->notes->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // updated_at
            $this->updated_at->HrefValue = "";

            // created_by
            $this->created_by->HrefValue = "";

            // updated_by
            $this->updated_by->HrefValue = "";

            // header_slot_id
            $this->header_slot_id->HrefValue = "";

            // part_kind
            $this->part_kind->HrefValue = "";

            // column_pct
            $this->column_pct->HrefValue = "";

            // align
            $this->align->HrefValue = "";

            // text_line1
            $this->text_line1->HrefValue = "";

            // text_line2
            $this->text_line2->HrefValue = "";

            // font_name
            $this->font_name->HrefValue = "";

            // line1_size_pt
            $this->line1_size_pt->HrefValue = "";

            // line2_size_pt
            $this->line2_size_pt->HrefValue = "";

            // line1_bold
            $this->line1_bold->HrefValue = "";

            // line2_bold
            $this->line2_bold->HrefValue = "";

            // line1_color
            $this->line1_color->HrefValue = "";

            // line2_color
            $this->line2_color->HrefValue = "";
        }
        if ($this->RowType == RowType::ADD || $this->RowType == RowType::EDIT || $this->RowType == RowType::SEARCH) { // Add/Edit/Search row
            $this->setupFieldTitles();
        }

        // Call Row Rendered event
        if ($this->RowType != RowType::AGGREGATEINIT) {
            $this->rowRendered();
        }
    }

    // Validate form
    protected function validateForm()
    {
        global $Language, $Security;

        // Check if validation required
        if (!Config("SERVER_VALIDATE")) {
            return true;
        }
        $validateForm = true;
            if ($this->header_asset_id->Visible && $this->header_asset_id->Required) {
                if (!$this->header_asset_id->IsDetailKey && EmptyValue($this->header_asset_id->FormValue)) {
                    $this->header_asset_id->addErrorMessage(str_replace("%s", $this->header_asset_id->caption(), $this->header_asset_id->RequiredErrorMessage));
                }
            }
            if ($this->asset_code->Visible && $this->asset_code->Required) {
                if (!$this->asset_code->IsDetailKey && EmptyValue($this->asset_code->FormValue)) {
                    $this->asset_code->addErrorMessage(str_replace("%s", $this->asset_code->caption(), $this->asset_code->RequiredErrorMessage));
                }
            }
            if ($this->asset_name->Visible && $this->asset_name->Required) {
                if (!$this->asset_name->IsDetailKey && EmptyValue($this->asset_name->FormValue)) {
                    $this->asset_name->addErrorMessage(str_replace("%s", $this->asset_name->caption(), $this->asset_name->RequiredErrorMessage));
                }
            }
            if ($this->document_asset_type_id->Visible && $this->document_asset_type_id->Required) {
                if (!$this->document_asset_type_id->IsDetailKey && EmptyValue($this->document_asset_type_id->FormValue)) {
                    $this->document_asset_type_id->addErrorMessage(str_replace("%s", $this->document_asset_type_id->caption(), $this->document_asset_type_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->document_asset_type_id->FormValue)) {
                $this->document_asset_type_id->addErrorMessage($this->document_asset_type_id->getErrorMessage(false));
            }
            if ($this->scope_system_code->Visible && $this->scope_system_code->Required) {
                if (!$this->scope_system_code->IsDetailKey && EmptyValue($this->scope_system_code->FormValue)) {
                    $this->scope_system_code->addErrorMessage(str_replace("%s", $this->scope_system_code->caption(), $this->scope_system_code->RequiredErrorMessage));
                }
            }
            if ($this->scope_document_code->Visible && $this->scope_document_code->Required) {
                if (!$this->scope_document_code->IsDetailKey && EmptyValue($this->scope_document_code->FormValue)) {
                    $this->scope_document_code->addErrorMessage(str_replace("%s", $this->scope_document_code->caption(), $this->scope_document_code->RequiredErrorMessage));
                }
            }
            if ($this->file_name->Visible && $this->file_name->Required) {
                if (!$this->file_name->IsDetailKey && EmptyValue($this->file_name->FormValue)) {
                    $this->file_name->addErrorMessage(str_replace("%s", $this->file_name->caption(), $this->file_name->RequiredErrorMessage));
                }
            }
            if ($this->mime_type->Visible && $this->mime_type->Required) {
                if (!$this->mime_type->IsDetailKey && EmptyValue($this->mime_type->FormValue)) {
                    $this->mime_type->addErrorMessage(str_replace("%s", $this->mime_type->caption(), $this->mime_type->RequiredErrorMessage));
                }
            }
            if ($this->file_size->Visible && $this->file_size->Required) {
                if (!$this->file_size->IsDetailKey && EmptyValue($this->file_size->FormValue)) {
                    $this->file_size->addErrorMessage(str_replace("%s", $this->file_size->caption(), $this->file_size->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->file_size->FormValue)) {
                $this->file_size->addErrorMessage($this->file_size->getErrorMessage(false));
            }
            if ($this->checksum_sha256->Visible && $this->checksum_sha256->Required) {
                if (!$this->checksum_sha256->IsDetailKey && EmptyValue($this->checksum_sha256->FormValue)) {
                    $this->checksum_sha256->addErrorMessage(str_replace("%s", $this->checksum_sha256->caption(), $this->checksum_sha256->RequiredErrorMessage));
                }
            }
            if ($this->image_width->Visible && $this->image_width->Required) {
                if (!$this->image_width->IsDetailKey && EmptyValue($this->image_width->FormValue)) {
                    $this->image_width->addErrorMessage(str_replace("%s", $this->image_width->caption(), $this->image_width->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->image_width->FormValue)) {
                $this->image_width->addErrorMessage($this->image_width->getErrorMessage(false));
            }
            if ($this->image_height->Visible && $this->image_height->Required) {
                if (!$this->image_height->IsDetailKey && EmptyValue($this->image_height->FormValue)) {
                    $this->image_height->addErrorMessage(str_replace("%s", $this->image_height->caption(), $this->image_height->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->image_height->FormValue)) {
                $this->image_height->addErrorMessage($this->image_height->getErrorMessage(false));
            }
            if ($this->render_width->Visible && $this->render_width->Required) {
                if (!$this->render_width->IsDetailKey && EmptyValue($this->render_width->FormValue)) {
                    $this->render_width->addErrorMessage(str_replace("%s", $this->render_width->caption(), $this->render_width->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->render_width->FormValue)) {
                $this->render_width->addErrorMessage($this->render_width->getErrorMessage(false));
            }
            if ($this->render_height->Visible && $this->render_height->Required) {
                if (!$this->render_height->IsDetailKey && EmptyValue($this->render_height->FormValue)) {
                    $this->render_height->addErrorMessage(str_replace("%s", $this->render_height->caption(), $this->render_height->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->render_height->FormValue)) {
                $this->render_height->addErrorMessage($this->render_height->getErrorMessage(false));
            }
            if ($this->image_data->Visible && $this->image_data->Required) {
                if (!$this->image_data->IsDetailKey && EmptyValue($this->image_data->FormValue)) {
                    $this->image_data->addErrorMessage(str_replace("%s", $this->image_data->caption(), $this->image_data->RequiredErrorMessage));
                }
            }
            if ($this->is_active->Visible && $this->is_active->Required) {
                if ($this->is_active->FormValue == "") {
                    $this->is_active->addErrorMessage(str_replace("%s", $this->is_active->caption(), $this->is_active->RequiredErrorMessage));
                }
            }
            if ($this->notes->Visible && $this->notes->Required) {
                if (!$this->notes->IsDetailKey && EmptyValue($this->notes->FormValue)) {
                    $this->notes->addErrorMessage(str_replace("%s", $this->notes->caption(), $this->notes->RequiredErrorMessage));
                }
            }
            if ($this->created_at->Visible && $this->created_at->Required) {
                if (!$this->created_at->IsDetailKey && EmptyValue($this->created_at->FormValue)) {
                    $this->created_at->addErrorMessage(str_replace("%s", $this->created_at->caption(), $this->created_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->created_at->FormValue, $this->created_at->formatPattern())) {
                $this->created_at->addErrorMessage($this->created_at->getErrorMessage(false));
            }
            if ($this->updated_at->Visible && $this->updated_at->Required) {
                if (!$this->updated_at->IsDetailKey && EmptyValue($this->updated_at->FormValue)) {
                    $this->updated_at->addErrorMessage(str_replace("%s", $this->updated_at->caption(), $this->updated_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->updated_at->FormValue, $this->updated_at->formatPattern())) {
                $this->updated_at->addErrorMessage($this->updated_at->getErrorMessage(false));
            }
            if ($this->created_by->Visible && $this->created_by->Required) {
                if (!$this->created_by->IsDetailKey && EmptyValue($this->created_by->FormValue)) {
                    $this->created_by->addErrorMessage(str_replace("%s", $this->created_by->caption(), $this->created_by->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->created_by->FormValue)) {
                $this->created_by->addErrorMessage($this->created_by->getErrorMessage(false));
            }
            if ($this->updated_by->Visible && $this->updated_by->Required) {
                if (!$this->updated_by->IsDetailKey && EmptyValue($this->updated_by->FormValue)) {
                    $this->updated_by->addErrorMessage(str_replace("%s", $this->updated_by->caption(), $this->updated_by->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->updated_by->FormValue)) {
                $this->updated_by->addErrorMessage($this->updated_by->getErrorMessage(false));
            }
            if ($this->header_slot_id->Visible && $this->header_slot_id->Required) {
                if (!$this->header_slot_id->IsDetailKey && EmptyValue($this->header_slot_id->FormValue)) {
                    $this->header_slot_id->addErrorMessage(str_replace("%s", $this->header_slot_id->caption(), $this->header_slot_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->header_slot_id->FormValue)) {
                $this->header_slot_id->addErrorMessage($this->header_slot_id->getErrorMessage(false));
            }
            if ($this->part_kind->Visible && $this->part_kind->Required) {
                if (!$this->part_kind->IsDetailKey && EmptyValue($this->part_kind->FormValue)) {
                    $this->part_kind->addErrorMessage(str_replace("%s", $this->part_kind->caption(), $this->part_kind->RequiredErrorMessage));
                }
            }
            if ($this->column_pct->Visible && $this->column_pct->Required) {
                if (!$this->column_pct->IsDetailKey && EmptyValue($this->column_pct->FormValue)) {
                    $this->column_pct->addErrorMessage(str_replace("%s", $this->column_pct->caption(), $this->column_pct->RequiredErrorMessage));
                }
            }
            if (!CheckNumber($this->column_pct->FormValue)) {
                $this->column_pct->addErrorMessage($this->column_pct->getErrorMessage(false));
            }
            if ($this->align->Visible && $this->align->Required) {
                if (!$this->align->IsDetailKey && EmptyValue($this->align->FormValue)) {
                    $this->align->addErrorMessage(str_replace("%s", $this->align->caption(), $this->align->RequiredErrorMessage));
                }
            }
            if ($this->text_line1->Visible && $this->text_line1->Required) {
                if (!$this->text_line1->IsDetailKey && EmptyValue($this->text_line1->FormValue)) {
                    $this->text_line1->addErrorMessage(str_replace("%s", $this->text_line1->caption(), $this->text_line1->RequiredErrorMessage));
                }
            }
            if ($this->text_line2->Visible && $this->text_line2->Required) {
                if (!$this->text_line2->IsDetailKey && EmptyValue($this->text_line2->FormValue)) {
                    $this->text_line2->addErrorMessage(str_replace("%s", $this->text_line2->caption(), $this->text_line2->RequiredErrorMessage));
                }
            }
            if ($this->font_name->Visible && $this->font_name->Required) {
                if (!$this->font_name->IsDetailKey && EmptyValue($this->font_name->FormValue)) {
                    $this->font_name->addErrorMessage(str_replace("%s", $this->font_name->caption(), $this->font_name->RequiredErrorMessage));
                }
            }
            if ($this->line1_size_pt->Visible && $this->line1_size_pt->Required) {
                if (!$this->line1_size_pt->IsDetailKey && EmptyValue($this->line1_size_pt->FormValue)) {
                    $this->line1_size_pt->addErrorMessage(str_replace("%s", $this->line1_size_pt->caption(), $this->line1_size_pt->RequiredErrorMessage));
                }
            }
            if (!CheckNumber($this->line1_size_pt->FormValue)) {
                $this->line1_size_pt->addErrorMessage($this->line1_size_pt->getErrorMessage(false));
            }
            if ($this->line2_size_pt->Visible && $this->line2_size_pt->Required) {
                if (!$this->line2_size_pt->IsDetailKey && EmptyValue($this->line2_size_pt->FormValue)) {
                    $this->line2_size_pt->addErrorMessage(str_replace("%s", $this->line2_size_pt->caption(), $this->line2_size_pt->RequiredErrorMessage));
                }
            }
            if (!CheckNumber($this->line2_size_pt->FormValue)) {
                $this->line2_size_pt->addErrorMessage($this->line2_size_pt->getErrorMessage(false));
            }
            if ($this->line1_bold->Visible && $this->line1_bold->Required) {
                if ($this->line1_bold->FormValue == "") {
                    $this->line1_bold->addErrorMessage(str_replace("%s", $this->line1_bold->caption(), $this->line1_bold->RequiredErrorMessage));
                }
            }
            if ($this->line2_bold->Visible && $this->line2_bold->Required) {
                if ($this->line2_bold->FormValue == "") {
                    $this->line2_bold->addErrorMessage(str_replace("%s", $this->line2_bold->caption(), $this->line2_bold->RequiredErrorMessage));
                }
            }
            if ($this->line1_color->Visible && $this->line1_color->Required) {
                if (!$this->line1_color->IsDetailKey && EmptyValue($this->line1_color->FormValue)) {
                    $this->line1_color->addErrorMessage(str_replace("%s", $this->line1_color->caption(), $this->line1_color->RequiredErrorMessage));
                }
            }
            if ($this->line2_color->Visible && $this->line2_color->Required) {
                if (!$this->line2_color->IsDetailKey && EmptyValue($this->line2_color->FormValue)) {
                    $this->line2_color->addErrorMessage(str_replace("%s", $this->line2_color->caption(), $this->line2_color->RequiredErrorMessage));
                }
            }

        // Return validate result
        $validateForm = $validateForm && !$this->hasInvalidFields();

        // Call Form_CustomValidate event
        $formCustomError = "";
        $validateForm = $validateForm && $this->formCustomValidate($formCustomError);
        if ($formCustomError != "") {
            $this->setFailureMessage($formCustomError);
        }
        return $validateForm;
    }

    // Update record based on key values
    protected function editRow()
    {
        global $Security, $Language;
        $oldKeyFilter = $this->getRecordFilter();
        $filter = $this->applyUserIDFilters($oldKeyFilter);
        $conn = $this->getConnection();

        // Load old row
        $this->CurrentFilter = $filter;
        $sql = $this->getCurrentSql();
        $rsold = $conn->fetchAssociative($sql);
        if (!$rsold) {
            $this->setFailureMessage($Language->phrase("NoRecord")); // Set no record message
            return false; // Update Failed
        } else {
            // Load old values
            $this->loadDbValues($rsold);
        }

        // Get new row
        $rsnew = $this->getEditRow($rsold);

        // Update current values
        $this->setCurrentValues($rsnew);

        // Check field with unique index (asset_code)
        if ($this->asset_code->CurrentValue != "") {
            $filterChk = "(\"asset_code\" = '" . AdjustSql($this->asset_code->CurrentValue, $this->Dbid) . "')";
            $filterChk .= " AND NOT (" . $filter . ")";
            $this->CurrentFilter = $filterChk;
            $sqlChk = $this->getCurrentSql();
            $rsChk = $conn->executeQuery($sqlChk);
            if (!$rsChk) {
                return false;
            }
            if ($rsChk->fetch()) {
                $idxErrMsg = str_replace("%f", $this->asset_code->caption(), $Language->phrase("DupIndex"));
                $idxErrMsg = str_replace("%v", $this->asset_code->CurrentValue, $idxErrMsg);
                $this->setFailureMessage($idxErrMsg);
                return false;
            }
        }

        // Call Row Updating event
        $updateRow = $this->rowUpdating($rsold, $rsnew);
        if ($updateRow) {
            if (count($rsnew) > 0) {
                $this->CurrentFilter = $filter; // Set up current filter
                $editRow = $this->update($rsnew, "", $rsold);
                if (!$editRow && !EmptyValue($this->DbErrorMessage)) { // Show database error
                    $this->setFailureMessage($this->DbErrorMessage);
                }
            } else {
                $editRow = true; // No field to update
            }
            if ($editRow) {
            }
        } else {
            if ($this->getSuccessMessage() != "" || $this->getFailureMessage() != "") {
                // Use the message, do nothing
            } elseif ($this->CancelMessage != "") {
                $this->setFailureMessage($this->CancelMessage);
                $this->CancelMessage = "";
            } else {
                $this->setFailureMessage($Language->phrase("UpdateCancelled"));
            }
            $editRow = false;
        }

        // Call Row_Updated event
        if ($editRow) {
            $this->rowUpdated($rsold, $rsnew);
        }

        // Write JSON response
        if (IsJsonResponse() && $editRow) {
            $row = $this->getRecordsFromRecordset([$rsnew], true);
            $table = $this->TableVar;
            WriteJson(["success" => true, "action" => Config("API_EDIT_ACTION"), $table => $row]);
        }
        return $editRow;
    }

    /**
     * Get edit row
     *
     * @return array
     */
    protected function getEditRow($rsold)
    {
        global $Security;
        $rsnew = [];

        // asset_code
        $this->asset_code->setDbValueDef($rsnew, $this->asset_code->CurrentValue, $this->asset_code->ReadOnly);

        // asset_name
        $this->asset_name->setDbValueDef($rsnew, $this->asset_name->CurrentValue, $this->asset_name->ReadOnly);

        // document_asset_type_id
        $this->document_asset_type_id->setDbValueDef($rsnew, $this->document_asset_type_id->CurrentValue, $this->document_asset_type_id->ReadOnly);

        // scope_system_code
        $this->scope_system_code->setDbValueDef($rsnew, $this->scope_system_code->CurrentValue, $this->scope_system_code->ReadOnly);

        // scope_document_code
        $this->scope_document_code->setDbValueDef($rsnew, $this->scope_document_code->CurrentValue, $this->scope_document_code->ReadOnly);

        // file_name
        $this->file_name->setDbValueDef($rsnew, $this->file_name->CurrentValue, $this->file_name->ReadOnly);

        // mime_type
        $this->mime_type->setDbValueDef($rsnew, $this->mime_type->CurrentValue, $this->mime_type->ReadOnly);

        // file_size
        $this->file_size->setDbValueDef($rsnew, $this->file_size->CurrentValue, $this->file_size->ReadOnly);

        // checksum_sha256
        $this->checksum_sha256->setDbValueDef($rsnew, $this->checksum_sha256->CurrentValue, $this->checksum_sha256->ReadOnly);

        // image_width
        $this->image_width->setDbValueDef($rsnew, $this->image_width->CurrentValue, $this->image_width->ReadOnly);

        // image_height
        $this->image_height->setDbValueDef($rsnew, $this->image_height->CurrentValue, $this->image_height->ReadOnly);

        // render_width
        $this->render_width->setDbValueDef($rsnew, $this->render_width->CurrentValue, $this->render_width->ReadOnly);

        // render_height
        $this->render_height->setDbValueDef($rsnew, $this->render_height->CurrentValue, $this->render_height->ReadOnly);

        // image_data
        $this->image_data->setDbValueDef($rsnew, $this->image_data->CurrentValue, $this->image_data->ReadOnly);

        // is_active
        $tmpBool = $this->is_active->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_active->setDbValueDef($rsnew, $tmpBool, $this->is_active->ReadOnly);

        // notes
        $this->notes->setDbValueDef($rsnew, $this->notes->CurrentValue, $this->notes->ReadOnly);

        // created_at
        $this->created_at->setDbValueDef($rsnew, UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()), $this->created_at->ReadOnly);

        // updated_at
        $this->updated_at->setDbValueDef($rsnew, UnFormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern()), $this->updated_at->ReadOnly);

        // created_by
        $this->created_by->setDbValueDef($rsnew, $this->created_by->CurrentValue, $this->created_by->ReadOnly);

        // updated_by
        $this->updated_by->setDbValueDef($rsnew, $this->updated_by->CurrentValue, $this->updated_by->ReadOnly);

        // header_slot_id
        $this->header_slot_id->setDbValueDef($rsnew, $this->header_slot_id->CurrentValue, $this->header_slot_id->ReadOnly);

        // part_kind
        $this->part_kind->setDbValueDef($rsnew, $this->part_kind->CurrentValue, $this->part_kind->ReadOnly);

        // column_pct
        $this->column_pct->setDbValueDef($rsnew, $this->column_pct->CurrentValue, $this->column_pct->ReadOnly);

        // align
        $this->align->setDbValueDef($rsnew, $this->align->CurrentValue, $this->align->ReadOnly);

        // text_line1
        $this->text_line1->setDbValueDef($rsnew, $this->text_line1->CurrentValue, $this->text_line1->ReadOnly);

        // text_line2
        $this->text_line2->setDbValueDef($rsnew, $this->text_line2->CurrentValue, $this->text_line2->ReadOnly);

        // font_name
        $this->font_name->setDbValueDef($rsnew, $this->font_name->CurrentValue, $this->font_name->ReadOnly);

        // line1_size_pt
        $this->line1_size_pt->setDbValueDef($rsnew, $this->line1_size_pt->CurrentValue, $this->line1_size_pt->ReadOnly);

        // line2_size_pt
        $this->line2_size_pt->setDbValueDef($rsnew, $this->line2_size_pt->CurrentValue, $this->line2_size_pt->ReadOnly);

        // line1_bold
        $tmpBool = $this->line1_bold->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->line1_bold->setDbValueDef($rsnew, $tmpBool, $this->line1_bold->ReadOnly);

        // line2_bold
        $tmpBool = $this->line2_bold->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->line2_bold->setDbValueDef($rsnew, $tmpBool, $this->line2_bold->ReadOnly);

        // line1_color
        $this->line1_color->setDbValueDef($rsnew, $this->line1_color->CurrentValue, $this->line1_color->ReadOnly);

        // line2_color
        $this->line2_color->setDbValueDef($rsnew, $this->line2_color->CurrentValue, $this->line2_color->ReadOnly);
        return $rsnew;
    }

    /**
     * Restore edit form from row
     * @param array $row Row
     */
    protected function restoreEditFormFromRow($row)
    {
        if (isset($row['asset_code'])) { // asset_code
            $this->asset_code->CurrentValue = $row['asset_code'];
        }
        if (isset($row['asset_name'])) { // asset_name
            $this->asset_name->CurrentValue = $row['asset_name'];
        }
        if (isset($row['document_asset_type_id'])) { // document_asset_type_id
            $this->document_asset_type_id->CurrentValue = $row['document_asset_type_id'];
        }
        if (isset($row['scope_system_code'])) { // scope_system_code
            $this->scope_system_code->CurrentValue = $row['scope_system_code'];
        }
        if (isset($row['scope_document_code'])) { // scope_document_code
            $this->scope_document_code->CurrentValue = $row['scope_document_code'];
        }
        if (isset($row['file_name'])) { // file_name
            $this->file_name->CurrentValue = $row['file_name'];
        }
        if (isset($row['mime_type'])) { // mime_type
            $this->mime_type->CurrentValue = $row['mime_type'];
        }
        if (isset($row['file_size'])) { // file_size
            $this->file_size->CurrentValue = $row['file_size'];
        }
        if (isset($row['checksum_sha256'])) { // checksum_sha256
            $this->checksum_sha256->CurrentValue = $row['checksum_sha256'];
        }
        if (isset($row['image_width'])) { // image_width
            $this->image_width->CurrentValue = $row['image_width'];
        }
        if (isset($row['image_height'])) { // image_height
            $this->image_height->CurrentValue = $row['image_height'];
        }
        if (isset($row['render_width'])) { // render_width
            $this->render_width->CurrentValue = $row['render_width'];
        }
        if (isset($row['render_height'])) { // render_height
            $this->render_height->CurrentValue = $row['render_height'];
        }
        if (isset($row['image_data'])) { // image_data
            $this->image_data->CurrentValue = $row['image_data'];
        }
        if (isset($row['is_active'])) { // is_active
            $this->is_active->CurrentValue = $row['is_active'];
        }
        if (isset($row['notes'])) { // notes
            $this->notes->CurrentValue = $row['notes'];
        }
        if (isset($row['created_at'])) { // created_at
            $this->created_at->CurrentValue = $row['created_at'];
        }
        if (isset($row['updated_at'])) { // updated_at
            $this->updated_at->CurrentValue = $row['updated_at'];
        }
        if (isset($row['created_by'])) { // created_by
            $this->created_by->CurrentValue = $row['created_by'];
        }
        if (isset($row['updated_by'])) { // updated_by
            $this->updated_by->CurrentValue = $row['updated_by'];
        }
        if (isset($row['header_slot_id'])) { // header_slot_id
            $this->header_slot_id->CurrentValue = $row['header_slot_id'];
        }
        if (isset($row['part_kind'])) { // part_kind
            $this->part_kind->CurrentValue = $row['part_kind'];
        }
        if (isset($row['column_pct'])) { // column_pct
            $this->column_pct->CurrentValue = $row['column_pct'];
        }
        if (isset($row['align'])) { // align
            $this->align->CurrentValue = $row['align'];
        }
        if (isset($row['text_line1'])) { // text_line1
            $this->text_line1->CurrentValue = $row['text_line1'];
        }
        if (isset($row['text_line2'])) { // text_line2
            $this->text_line2->CurrentValue = $row['text_line2'];
        }
        if (isset($row['font_name'])) { // font_name
            $this->font_name->CurrentValue = $row['font_name'];
        }
        if (isset($row['line1_size_pt'])) { // line1_size_pt
            $this->line1_size_pt->CurrentValue = $row['line1_size_pt'];
        }
        if (isset($row['line2_size_pt'])) { // line2_size_pt
            $this->line2_size_pt->CurrentValue = $row['line2_size_pt'];
        }
        if (isset($row['line1_bold'])) { // line1_bold
            $this->line1_bold->CurrentValue = $row['line1_bold'];
        }
        if (isset($row['line2_bold'])) { // line2_bold
            $this->line2_bold->CurrentValue = $row['line2_bold'];
        }
        if (isset($row['line1_color'])) { // line1_color
            $this->line1_color->CurrentValue = $row['line1_color'];
        }
        if (isset($row['line2_color'])) { // line2_color
            $this->line2_color->CurrentValue = $row['line2_color'];
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("DocumentHeaderAssetsList"), "", $this->TableVar, true);
        $pageId = "edit";
        $Breadcrumb->add("edit", $pageId, $url);
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
        $infiniteScroll = false;
        $recordNo = $pageNo ?? $startRec; // Record number = page number or start record
        if ($recordNo !== null && is_numeric($recordNo)) {
            $this->StartRecord = $recordNo;
        } else {
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
}
