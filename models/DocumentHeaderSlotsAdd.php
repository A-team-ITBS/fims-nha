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
class DocumentHeaderSlotsAdd extends DocumentHeaderSlots
{
    use MessagesTrait;

    // Page ID
    public $PageID = "add";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "DocumentHeaderSlotsAdd";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "DocumentHeaderSlotsAdd";

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
        $this->header_slot_id->Visible = false;
        $this->slot_code->setVisibility();
        $this->slot_name->setVisibility();
        $this->part_kind->setVisibility();
        $this->display_order->setVisibility();
        $this->default_column_pct->setVisibility();
        $this->default_render_width->setVisibility();
        $this->default_render_height->setVisibility();
        $this->default_align->setVisibility();
        $this->is_active->setVisibility();
        $this->created_at->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'document_header_slots';
        $this->TableName = 'document_header_slots';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-add-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (document_header_slots)
        if (!isset($GLOBALS["document_header_slots"]) || $GLOBALS["document_header_slots"]::class == PROJECT_NAMESPACE . "document_header_slots") {
            $GLOBALS["document_header_slots"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'document_header_slots');
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
                        $result["view"] = SameString($pageName, "DocumentHeaderSlotsView"); // If View page, no primary button
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
            $key .= @$ar['header_slot_id'];
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
            $this->header_slot_id->Visible = false;
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
    public $FormClassName = "ew-form ew-add-form";
    public $IsModal = false;
    public $IsMobileOrModal = false;
    public $DbMasterFilter = "";
    public $DbDetailFilter = "";
    public $StartRecord;
    public $Priv = 0;
    public $CopyRecord;

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

        // Load default values for add
        $this->loadDefaultValues();

        // Check modal
        if ($this->IsModal) {
            $SkipHeaderFooter = true;
        }
        $this->IsMobileOrModal = IsMobile() || $this->IsModal;
        $postBack = false;

        // Set up current action
        if (IsApi()) {
            $this->CurrentAction = "insert"; // Add record directly
            $postBack = true;
        } elseif (Post("action", "") !== "") {
            $this->CurrentAction = Post("action"); // Get form action
            $this->setKey(Post($this->OldKeyName));
            $postBack = true;
        } else {
            // Load key values from QueryString
            if (($keyValue = Get("header_slot_id") ?? Route("header_slot_id")) !== null) {
                $this->header_slot_id->setQueryStringValue($keyValue);
            }
            $this->OldKey = $this->getKey(true); // Get from CurrentValue
            $this->CopyRecord = !EmptyValue($this->OldKey);
            if ($this->CopyRecord) {
                $this->CurrentAction = "copy"; // Copy record
                $this->setKey($this->OldKey); // Set up record key
            } else {
                $this->CurrentAction = "show"; // Display blank record
            }
        }

        // Load old record or default values
        $rsold = $this->loadOldRecord();

        // Load form values
        if ($postBack) {
            $this->loadFormValues(); // Load form values
        }

        // Validate form if post back
        if ($postBack) {
            if (!$this->validateForm()) {
                $this->EventCancelled = true; // Event cancelled
                $this->restoreFormValues(); // Restore form values
                if (IsApi()) {
                    $this->terminate();
                    return;
                } else {
                    $this->CurrentAction = "show"; // Form error, reset action
                }
            }
        }

        // Perform current action
        switch ($this->CurrentAction) {
            case "copy": // Copy an existing record
                if (!$rsold) { // Record not loaded
                    if ($this->getFailureMessage() == "") {
                        $this->setFailureMessage($Language->phrase("NoRecord")); // No record found
                    }
                    $this->terminate("DocumentHeaderSlotsList"); // No matching record, return to list
                    return;
                }
                break;
            case "insert": // Add new record
                $this->SendEmail = true; // Send email on add success
                if ($this->addRow($rsold)) { // Add successful
                    if ($this->getSuccessMessage() == "" && Post("addopt") != "1") { // Skip success message for addopt (done in JavaScript)
                        $this->setSuccessMessage($Language->phrase("AddSuccess")); // Set up success message
                    }
                    $returnUrl = $this->getReturnUrl();
                    if (GetPageName($returnUrl) == "DocumentHeaderSlotsList") {
                        $returnUrl = $this->addMasterUrl($returnUrl); // List page, return to List page with correct master key if necessary
                    } elseif (GetPageName($returnUrl) == "DocumentHeaderSlotsView") {
                        $returnUrl = $this->getViewUrl(); // View page, return to View page with keyurl directly
                    }

                    // Handle UseAjaxActions with return page
                    if ($this->IsModal && $this->UseAjaxActions) {
                        $this->IsModal = false;
                        if (GetPageName($returnUrl) != "DocumentHeaderSlotsList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "DocumentHeaderSlotsList"; // Return list page content
                        }
                    }
                    if (IsJsonResponse()) { // Return to caller
                        $this->terminate(true);
                        return;
                    } else {
                        $this->terminate($returnUrl);
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
                } else {
                    $this->EventCancelled = true; // Event cancelled
                    $this->restoreFormValues(); // Add failed, restore form values
                }
        }

        // Set up Breadcrumb
        $this->setupBreadcrumb();

        // Render row based on row type
        $this->RowType = RowType::ADD; // Render add type

        // Render row
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

    // Load default values
    protected function loadDefaultValues()
    {
        $this->default_align->DefaultValue = $this->default_align->getDefault(); // PHP
        $this->default_align->OldValue = $this->default_align->DefaultValue;
    }

    // Load form values
    protected function loadFormValues()
    {
        // Load from form
        global $CurrentForm;
        $validate = !Config("SERVER_VALIDATE");

        // Check field name 'slot_code' first before field var 'x_slot_code'
        $val = $CurrentForm->hasValue("slot_code") ? $CurrentForm->getValue("slot_code") : $CurrentForm->getValue("x_slot_code");
        if (!$this->slot_code->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->slot_code->Visible = false; // Disable update for API request
            } else {
                $this->slot_code->setFormValue($val);
            }
        }

        // Check field name 'slot_name' first before field var 'x_slot_name'
        $val = $CurrentForm->hasValue("slot_name") ? $CurrentForm->getValue("slot_name") : $CurrentForm->getValue("x_slot_name");
        if (!$this->slot_name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->slot_name->Visible = false; // Disable update for API request
            } else {
                $this->slot_name->setFormValue($val);
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

        // Check field name 'display_order' first before field var 'x_display_order'
        $val = $CurrentForm->hasValue("display_order") ? $CurrentForm->getValue("display_order") : $CurrentForm->getValue("x_display_order");
        if (!$this->display_order->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->display_order->Visible = false; // Disable update for API request
            } else {
                $this->display_order->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'default_column_pct' first before field var 'x_default_column_pct'
        $val = $CurrentForm->hasValue("default_column_pct") ? $CurrentForm->getValue("default_column_pct") : $CurrentForm->getValue("x_default_column_pct");
        if (!$this->default_column_pct->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->default_column_pct->Visible = false; // Disable update for API request
            } else {
                $this->default_column_pct->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'default_render_width' first before field var 'x_default_render_width'
        $val = $CurrentForm->hasValue("default_render_width") ? $CurrentForm->getValue("default_render_width") : $CurrentForm->getValue("x_default_render_width");
        if (!$this->default_render_width->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->default_render_width->Visible = false; // Disable update for API request
            } else {
                $this->default_render_width->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'default_render_height' first before field var 'x_default_render_height'
        $val = $CurrentForm->hasValue("default_render_height") ? $CurrentForm->getValue("default_render_height") : $CurrentForm->getValue("x_default_render_height");
        if (!$this->default_render_height->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->default_render_height->Visible = false; // Disable update for API request
            } else {
                $this->default_render_height->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'default_align' first before field var 'x_default_align'
        $val = $CurrentForm->hasValue("default_align") ? $CurrentForm->getValue("default_align") : $CurrentForm->getValue("x_default_align");
        if (!$this->default_align->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->default_align->Visible = false; // Disable update for API request
            } else {
                $this->default_align->setFormValue($val);
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

        // Check field name 'header_slot_id' first before field var 'x_header_slot_id'
        $val = $CurrentForm->hasValue("header_slot_id") ? $CurrentForm->getValue("header_slot_id") : $CurrentForm->getValue("x_header_slot_id");
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->slot_code->CurrentValue = $this->slot_code->FormValue;
        $this->slot_name->CurrentValue = $this->slot_name->FormValue;
        $this->part_kind->CurrentValue = $this->part_kind->FormValue;
        $this->display_order->CurrentValue = $this->display_order->FormValue;
        $this->default_column_pct->CurrentValue = $this->default_column_pct->FormValue;
        $this->default_render_width->CurrentValue = $this->default_render_width->FormValue;
        $this->default_render_height->CurrentValue = $this->default_render_height->FormValue;
        $this->default_align->CurrentValue = $this->default_align->FormValue;
        $this->is_active->CurrentValue = $this->is_active->FormValue;
        $this->created_at->CurrentValue = $this->created_at->FormValue;
        $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
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
        $this->header_slot_id->setDbValue($row['header_slot_id']);
        $this->slot_code->setDbValue($row['slot_code']);
        $this->slot_name->setDbValue($row['slot_name']);
        $this->part_kind->setDbValue($row['part_kind']);
        $this->display_order->setDbValue($row['display_order']);
        $this->default_column_pct->setDbValue($row['default_column_pct']);
        $this->default_render_width->setDbValue($row['default_render_width']);
        $this->default_render_height->setDbValue($row['default_render_height']);
        $this->default_align->setDbValue($row['default_align']);
        $this->is_active->setDbValue((ConvertToBool($row['is_active']) ? "1" : "0"));
        $this->created_at->setDbValue($row['created_at']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['header_slot_id'] = $this->header_slot_id->DefaultValue;
        $row['slot_code'] = $this->slot_code->DefaultValue;
        $row['slot_name'] = $this->slot_name->DefaultValue;
        $row['part_kind'] = $this->part_kind->DefaultValue;
        $row['display_order'] = $this->display_order->DefaultValue;
        $row['default_column_pct'] = $this->default_column_pct->DefaultValue;
        $row['default_render_width'] = $this->default_render_width->DefaultValue;
        $row['default_render_height'] = $this->default_render_height->DefaultValue;
        $row['default_align'] = $this->default_align->DefaultValue;
        $row['is_active'] = $this->is_active->DefaultValue;
        $row['created_at'] = $this->created_at->DefaultValue;
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

        // header_slot_id
        $this->header_slot_id->RowCssClass = "row";

        // slot_code
        $this->slot_code->RowCssClass = "row";

        // slot_name
        $this->slot_name->RowCssClass = "row";

        // part_kind
        $this->part_kind->RowCssClass = "row";

        // display_order
        $this->display_order->RowCssClass = "row";

        // default_column_pct
        $this->default_column_pct->RowCssClass = "row";

        // default_render_width
        $this->default_render_width->RowCssClass = "row";

        // default_render_height
        $this->default_render_height->RowCssClass = "row";

        // default_align
        $this->default_align->RowCssClass = "row";

        // is_active
        $this->is_active->RowCssClass = "row";

        // created_at
        $this->created_at->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // header_slot_id
            $this->header_slot_id->ViewValue = $this->header_slot_id->CurrentValue;

            // slot_code
            $this->slot_code->ViewValue = $this->slot_code->CurrentValue;

            // slot_name
            $this->slot_name->ViewValue = $this->slot_name->CurrentValue;

            // part_kind
            $this->part_kind->ViewValue = $this->part_kind->CurrentValue;

            // display_order
            $this->display_order->ViewValue = $this->display_order->CurrentValue;
            $this->display_order->ViewValue = FormatNumber($this->display_order->ViewValue, $this->display_order->formatPattern());

            // default_column_pct
            $this->default_column_pct->ViewValue = $this->default_column_pct->CurrentValue;
            $this->default_column_pct->ViewValue = FormatNumber($this->default_column_pct->ViewValue, $this->default_column_pct->formatPattern());

            // default_render_width
            $this->default_render_width->ViewValue = $this->default_render_width->CurrentValue;
            $this->default_render_width->ViewValue = FormatNumber($this->default_render_width->ViewValue, $this->default_render_width->formatPattern());

            // default_render_height
            $this->default_render_height->ViewValue = $this->default_render_height->CurrentValue;
            $this->default_render_height->ViewValue = FormatNumber($this->default_render_height->ViewValue, $this->default_render_height->formatPattern());

            // default_align
            $this->default_align->ViewValue = $this->default_align->CurrentValue;

            // is_active
            if (ConvertToBool($this->is_active->CurrentValue)) {
                $this->is_active->ViewValue = $this->is_active->tagCaption(1) != "" ? $this->is_active->tagCaption(1) : "Yes";
            } else {
                $this->is_active->ViewValue = $this->is_active->tagCaption(2) != "" ? $this->is_active->tagCaption(2) : "No";
            }

            // created_at
            $this->created_at->ViewValue = $this->created_at->CurrentValue;
            $this->created_at->ViewValue = FormatDateTime($this->created_at->ViewValue, $this->created_at->formatPattern());

            // slot_code
            $this->slot_code->HrefValue = "";

            // slot_name
            $this->slot_name->HrefValue = "";

            // part_kind
            $this->part_kind->HrefValue = "";

            // display_order
            $this->display_order->HrefValue = "";

            // default_column_pct
            $this->default_column_pct->HrefValue = "";

            // default_render_width
            $this->default_render_width->HrefValue = "";

            // default_render_height
            $this->default_render_height->HrefValue = "";

            // default_align
            $this->default_align->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";
        } elseif ($this->RowType == RowType::ADD) {
            // slot_code
            $this->slot_code->setupEditAttributes();
            if (!$this->slot_code->Raw) {
                $this->slot_code->CurrentValue = HtmlDecode($this->slot_code->CurrentValue);
            }
            $this->slot_code->EditValue = HtmlEncode($this->slot_code->CurrentValue);
            $this->slot_code->PlaceHolder = RemoveHtml($this->slot_code->caption());

            // slot_name
            $this->slot_name->setupEditAttributes();
            if (!$this->slot_name->Raw) {
                $this->slot_name->CurrentValue = HtmlDecode($this->slot_name->CurrentValue);
            }
            $this->slot_name->EditValue = HtmlEncode($this->slot_name->CurrentValue);
            $this->slot_name->PlaceHolder = RemoveHtml($this->slot_name->caption());

            // part_kind
            $this->part_kind->setupEditAttributes();
            if (!$this->part_kind->Raw) {
                $this->part_kind->CurrentValue = HtmlDecode($this->part_kind->CurrentValue);
            }
            $this->part_kind->EditValue = HtmlEncode($this->part_kind->CurrentValue);
            $this->part_kind->PlaceHolder = RemoveHtml($this->part_kind->caption());

            // display_order
            $this->display_order->setupEditAttributes();
            $this->display_order->EditValue = $this->display_order->CurrentValue;
            $this->display_order->PlaceHolder = RemoveHtml($this->display_order->caption());
            if (strval($this->display_order->EditValue) != "" && is_numeric($this->display_order->EditValue)) {
                $this->display_order->EditValue = FormatNumber($this->display_order->EditValue, null);
            }

            // default_column_pct
            $this->default_column_pct->setupEditAttributes();
            $this->default_column_pct->EditValue = $this->default_column_pct->CurrentValue;
            $this->default_column_pct->PlaceHolder = RemoveHtml($this->default_column_pct->caption());
            if (strval($this->default_column_pct->EditValue) != "" && is_numeric($this->default_column_pct->EditValue)) {
                $this->default_column_pct->EditValue = FormatNumber($this->default_column_pct->EditValue, null);
            }

            // default_render_width
            $this->default_render_width->setupEditAttributes();
            $this->default_render_width->EditValue = $this->default_render_width->CurrentValue;
            $this->default_render_width->PlaceHolder = RemoveHtml($this->default_render_width->caption());
            if (strval($this->default_render_width->EditValue) != "" && is_numeric($this->default_render_width->EditValue)) {
                $this->default_render_width->EditValue = FormatNumber($this->default_render_width->EditValue, null);
            }

            // default_render_height
            $this->default_render_height->setupEditAttributes();
            $this->default_render_height->EditValue = $this->default_render_height->CurrentValue;
            $this->default_render_height->PlaceHolder = RemoveHtml($this->default_render_height->caption());
            if (strval($this->default_render_height->EditValue) != "" && is_numeric($this->default_render_height->EditValue)) {
                $this->default_render_height->EditValue = FormatNumber($this->default_render_height->EditValue, null);
            }

            // default_align
            $this->default_align->setupEditAttributes();
            if (!$this->default_align->Raw) {
                $this->default_align->CurrentValue = HtmlDecode($this->default_align->CurrentValue);
            }
            $this->default_align->EditValue = HtmlEncode($this->default_align->CurrentValue);
            $this->default_align->PlaceHolder = RemoveHtml($this->default_align->caption());

            // is_active
            $this->is_active->EditValue = $this->is_active->options(false);
            $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

            // created_at
            $this->created_at->setupEditAttributes();
            $this->created_at->EditValue = HtmlEncode(FormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()));
            $this->created_at->PlaceHolder = RemoveHtml($this->created_at->caption());

            // Add refer script

            // slot_code
            $this->slot_code->HrefValue = "";

            // slot_name
            $this->slot_name->HrefValue = "";

            // part_kind
            $this->part_kind->HrefValue = "";

            // display_order
            $this->display_order->HrefValue = "";

            // default_column_pct
            $this->default_column_pct->HrefValue = "";

            // default_render_width
            $this->default_render_width->HrefValue = "";

            // default_render_height
            $this->default_render_height->HrefValue = "";

            // default_align
            $this->default_align->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";
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
            if ($this->slot_code->Visible && $this->slot_code->Required) {
                if (!$this->slot_code->IsDetailKey && EmptyValue($this->slot_code->FormValue)) {
                    $this->slot_code->addErrorMessage(str_replace("%s", $this->slot_code->caption(), $this->slot_code->RequiredErrorMessage));
                }
            }
            if ($this->slot_name->Visible && $this->slot_name->Required) {
                if (!$this->slot_name->IsDetailKey && EmptyValue($this->slot_name->FormValue)) {
                    $this->slot_name->addErrorMessage(str_replace("%s", $this->slot_name->caption(), $this->slot_name->RequiredErrorMessage));
                }
            }
            if ($this->part_kind->Visible && $this->part_kind->Required) {
                if (!$this->part_kind->IsDetailKey && EmptyValue($this->part_kind->FormValue)) {
                    $this->part_kind->addErrorMessage(str_replace("%s", $this->part_kind->caption(), $this->part_kind->RequiredErrorMessage));
                }
            }
            if ($this->display_order->Visible && $this->display_order->Required) {
                if (!$this->display_order->IsDetailKey && EmptyValue($this->display_order->FormValue)) {
                    $this->display_order->addErrorMessage(str_replace("%s", $this->display_order->caption(), $this->display_order->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->display_order->FormValue)) {
                $this->display_order->addErrorMessage($this->display_order->getErrorMessage(false));
            }
            if ($this->default_column_pct->Visible && $this->default_column_pct->Required) {
                if (!$this->default_column_pct->IsDetailKey && EmptyValue($this->default_column_pct->FormValue)) {
                    $this->default_column_pct->addErrorMessage(str_replace("%s", $this->default_column_pct->caption(), $this->default_column_pct->RequiredErrorMessage));
                }
            }
            if (!CheckNumber($this->default_column_pct->FormValue)) {
                $this->default_column_pct->addErrorMessage($this->default_column_pct->getErrorMessage(false));
            }
            if ($this->default_render_width->Visible && $this->default_render_width->Required) {
                if (!$this->default_render_width->IsDetailKey && EmptyValue($this->default_render_width->FormValue)) {
                    $this->default_render_width->addErrorMessage(str_replace("%s", $this->default_render_width->caption(), $this->default_render_width->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->default_render_width->FormValue)) {
                $this->default_render_width->addErrorMessage($this->default_render_width->getErrorMessage(false));
            }
            if ($this->default_render_height->Visible && $this->default_render_height->Required) {
                if (!$this->default_render_height->IsDetailKey && EmptyValue($this->default_render_height->FormValue)) {
                    $this->default_render_height->addErrorMessage(str_replace("%s", $this->default_render_height->caption(), $this->default_render_height->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->default_render_height->FormValue)) {
                $this->default_render_height->addErrorMessage($this->default_render_height->getErrorMessage(false));
            }
            if ($this->default_align->Visible && $this->default_align->Required) {
                if (!$this->default_align->IsDetailKey && EmptyValue($this->default_align->FormValue)) {
                    $this->default_align->addErrorMessage(str_replace("%s", $this->default_align->caption(), $this->default_align->RequiredErrorMessage));
                }
            }
            if ($this->is_active->Visible && $this->is_active->Required) {
                if ($this->is_active->FormValue == "") {
                    $this->is_active->addErrorMessage(str_replace("%s", $this->is_active->caption(), $this->is_active->RequiredErrorMessage));
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

    // Add record
    protected function addRow($rsold = null)
    {
        global $Language, $Security;

        // Get new row
        $rsnew = $this->getAddRow();

        // Update current values
        $this->setCurrentValues($rsnew);
        if ($this->slot_code->CurrentValue != "") { // Check field with unique index
            $filter = "(\"slot_code\" = '" . AdjustSql($this->slot_code->CurrentValue, $this->Dbid) . "')";
            $rsChk = $this->loadRs($filter)->fetch();
            if ($rsChk !== false) {
                $idxErrMsg = str_replace("%f", $this->slot_code->caption(), $Language->phrase("DupIndex"));
                $idxErrMsg = str_replace("%v", $this->slot_code->CurrentValue, $idxErrMsg);
                $this->setFailureMessage($idxErrMsg);
                return false;
            }
        }
        $conn = $this->getConnection();

        // Load db values from old row
        $this->loadDbValues($rsold);

        // Call Row Inserting event
        $insertRow = $this->rowInserting($rsold, $rsnew);
        if ($insertRow) {
            $addRow = $this->insert($rsnew);
            if ($addRow) {
            } elseif (!EmptyValue($this->DbErrorMessage)) { // Show database error
                $this->setFailureMessage($this->DbErrorMessage);
            }
        } else {
            if ($this->getSuccessMessage() != "" || $this->getFailureMessage() != "") {
                // Use the message, do nothing
            } elseif ($this->CancelMessage != "") {
                $this->setFailureMessage($this->CancelMessage);
                $this->CancelMessage = "";
            } else {
                $this->setFailureMessage($Language->phrase("InsertCancelled"));
            }
            $addRow = false;
        }
        if ($addRow) {
            // Call Row Inserted event
            $this->rowInserted($rsold, $rsnew);
        }

        // Write JSON response
        if (IsJsonResponse() && $addRow) {
            $row = $this->getRecordsFromRecordset([$rsnew], true);
            $table = $this->TableVar;
            WriteJson(["success" => true, "action" => Config("API_ADD_ACTION"), $table => $row]);
        }
        return $addRow;
    }

    /**
     * Get add row
     *
     * @return array
     */
    protected function getAddRow()
    {
        global $Security;
        $rsnew = [];

        // slot_code
        $this->slot_code->setDbValueDef($rsnew, $this->slot_code->CurrentValue, false);

        // slot_name
        $this->slot_name->setDbValueDef($rsnew, $this->slot_name->CurrentValue, false);

        // part_kind
        $this->part_kind->setDbValueDef($rsnew, $this->part_kind->CurrentValue, false);

        // display_order
        $this->display_order->setDbValueDef($rsnew, $this->display_order->CurrentValue, false);

        // default_column_pct
        $this->default_column_pct->setDbValueDef($rsnew, $this->default_column_pct->CurrentValue, false);

        // default_render_width
        $this->default_render_width->setDbValueDef($rsnew, $this->default_render_width->CurrentValue, false);

        // default_render_height
        $this->default_render_height->setDbValueDef($rsnew, $this->default_render_height->CurrentValue, false);

        // default_align
        $this->default_align->setDbValueDef($rsnew, $this->default_align->CurrentValue, strval($this->default_align->CurrentValue) == "");

        // is_active
        $tmpBool = $this->is_active->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_active->setDbValueDef($rsnew, $tmpBool, strval($this->is_active->CurrentValue) == "");

        // created_at
        $this->created_at->setDbValueDef($rsnew, UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()), false);
        return $rsnew;
    }

    /**
     * Restore add form from row
     * @param array $row Row
     */
    protected function restoreAddFormFromRow($row)
    {
        if (isset($row['slot_code'])) { // slot_code
            $this->slot_code->setFormValue($row['slot_code']);
        }
        if (isset($row['slot_name'])) { // slot_name
            $this->slot_name->setFormValue($row['slot_name']);
        }
        if (isset($row['part_kind'])) { // part_kind
            $this->part_kind->setFormValue($row['part_kind']);
        }
        if (isset($row['display_order'])) { // display_order
            $this->display_order->setFormValue($row['display_order']);
        }
        if (isset($row['default_column_pct'])) { // default_column_pct
            $this->default_column_pct->setFormValue($row['default_column_pct']);
        }
        if (isset($row['default_render_width'])) { // default_render_width
            $this->default_render_width->setFormValue($row['default_render_width']);
        }
        if (isset($row['default_render_height'])) { // default_render_height
            $this->default_render_height->setFormValue($row['default_render_height']);
        }
        if (isset($row['default_align'])) { // default_align
            $this->default_align->setFormValue($row['default_align']);
        }
        if (isset($row['is_active'])) { // is_active
            $this->is_active->setFormValue($row['is_active']);
        }
        if (isset($row['created_at'])) { // created_at
            $this->created_at->setFormValue($row['created_at']);
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("DocumentHeaderSlotsList"), "", $this->TableVar, true);
        $pageId = ($this->isCopy()) ? "Copy" : "Add";
        $Breadcrumb->add("add", $pageId, $url);
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

    // Form Custom Validate event
    public function formCustomValidate(&$customError)
    {
        // Return error message in $customError
        return true;
    }
}
