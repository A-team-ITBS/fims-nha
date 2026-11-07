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
class UserOfficesEdit extends UserOffices
{
    use MessagesTrait;

    // Page ID
    public $PageID = "edit";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "UserOfficesEdit";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "UserOfficesEdit";

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
        $this->user_office_id->setVisibility();
        $this->user_id->setVisibility();
        $this->office_id->setVisibility();
        $this->is_primary->setVisibility();
        $this->access_start_date->setVisibility();
        $this->access_end_date->setVisibility();
        $this->is_active->setVisibility();
        $this->assigned_by->setVisibility();
        $this->assigned_at->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'user_offices';
        $this->TableName = 'user_offices';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-edit-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (user_offices)
        if (!isset($GLOBALS["user_offices"]) || $GLOBALS["user_offices"]::class == PROJECT_NAMESPACE . "user_offices") {
            $GLOBALS["user_offices"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'user_offices');
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
                        $result["view"] = SameString($pageName, "UserOfficesView"); // If View page, no primary button
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
            $key .= @$ar['user_office_id'];
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
            $this->user_office_id->Visible = false;
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
        $this->setupLookupOptions($this->is_primary);
        $this->setupLookupOptions($this->is_active);

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
            if (($keyValue = Get("user_office_id") ?? Key(0) ?? Route(2)) !== null) {
                $this->user_office_id->setQueryStringValue($keyValue);
                $this->user_office_id->setOldValue($this->user_office_id->QueryStringValue);
            } elseif (Post("user_office_id") !== null) {
                $this->user_office_id->setFormValue(Post("user_office_id"));
                $this->user_office_id->setOldValue($this->user_office_id->FormValue);
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
                if (($keyValue = Get("user_office_id") ?? Route("user_office_id")) !== null) {
                    $this->user_office_id->setQueryStringValue($keyValue);
                    $loadByQuery = true;
                } else {
                    $this->user_office_id->CurrentValue = null;
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
                        $this->terminate("UserOfficesList"); // No matching record, return to list
                        return;
                    }
                break;
            case "update": // Update
                $returnUrl = $this->getReturnUrl();
                if (GetPageName($returnUrl) == "UserOfficesList") {
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
                        if (GetPageName($returnUrl) != "UserOfficesList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "UserOfficesList"; // Return list page content
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

        // Check field name 'user_office_id' first before field var 'x_user_office_id'
        $val = $CurrentForm->hasValue("user_office_id") ? $CurrentForm->getValue("user_office_id") : $CurrentForm->getValue("x_user_office_id");
        if (!$this->user_office_id->IsDetailKey) {
            $this->user_office_id->setFormValue($val);
        }

        // Check field name 'user_id' first before field var 'x_user_id'
        $val = $CurrentForm->hasValue("user_id") ? $CurrentForm->getValue("user_id") : $CurrentForm->getValue("x_user_id");
        if (!$this->user_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->user_id->Visible = false; // Disable update for API request
            } else {
                $this->user_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'office_id' first before field var 'x_office_id'
        $val = $CurrentForm->hasValue("office_id") ? $CurrentForm->getValue("office_id") : $CurrentForm->getValue("x_office_id");
        if (!$this->office_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->office_id->Visible = false; // Disable update for API request
            } else {
                $this->office_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'is_primary' first before field var 'x_is_primary'
        $val = $CurrentForm->hasValue("is_primary") ? $CurrentForm->getValue("is_primary") : $CurrentForm->getValue("x_is_primary");
        if (!$this->is_primary->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->is_primary->Visible = false; // Disable update for API request
            } else {
                $this->is_primary->setFormValue($val);
            }
        }

        // Check field name 'access_start_date' first before field var 'x_access_start_date'
        $val = $CurrentForm->hasValue("access_start_date") ? $CurrentForm->getValue("access_start_date") : $CurrentForm->getValue("x_access_start_date");
        if (!$this->access_start_date->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->access_start_date->Visible = false; // Disable update for API request
            } else {
                $this->access_start_date->setFormValue($val, true, $validate);
            }
            $this->access_start_date->CurrentValue = UnFormatDateTime($this->access_start_date->CurrentValue, $this->access_start_date->formatPattern());
        }

        // Check field name 'access_end_date' first before field var 'x_access_end_date'
        $val = $CurrentForm->hasValue("access_end_date") ? $CurrentForm->getValue("access_end_date") : $CurrentForm->getValue("x_access_end_date");
        if (!$this->access_end_date->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->access_end_date->Visible = false; // Disable update for API request
            } else {
                $this->access_end_date->setFormValue($val, true, $validate);
            }
            $this->access_end_date->CurrentValue = UnFormatDateTime($this->access_end_date->CurrentValue, $this->access_end_date->formatPattern());
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

        // Check field name 'assigned_by' first before field var 'x_assigned_by'
        $val = $CurrentForm->hasValue("assigned_by") ? $CurrentForm->getValue("assigned_by") : $CurrentForm->getValue("x_assigned_by");
        if (!$this->assigned_by->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->assigned_by->Visible = false; // Disable update for API request
            } else {
                $this->assigned_by->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'assigned_at' first before field var 'x_assigned_at'
        $val = $CurrentForm->hasValue("assigned_at") ? $CurrentForm->getValue("assigned_at") : $CurrentForm->getValue("x_assigned_at");
        if (!$this->assigned_at->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->assigned_at->Visible = false; // Disable update for API request
            } else {
                $this->assigned_at->setFormValue($val, true, $validate);
            }
            $this->assigned_at->CurrentValue = UnFormatDateTime($this->assigned_at->CurrentValue, $this->assigned_at->formatPattern());
        }
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->user_office_id->CurrentValue = $this->user_office_id->FormValue;
        $this->user_id->CurrentValue = $this->user_id->FormValue;
        $this->office_id->CurrentValue = $this->office_id->FormValue;
        $this->is_primary->CurrentValue = $this->is_primary->FormValue;
        $this->access_start_date->CurrentValue = $this->access_start_date->FormValue;
        $this->access_start_date->CurrentValue = UnFormatDateTime($this->access_start_date->CurrentValue, $this->access_start_date->formatPattern());
        $this->access_end_date->CurrentValue = $this->access_end_date->FormValue;
        $this->access_end_date->CurrentValue = UnFormatDateTime($this->access_end_date->CurrentValue, $this->access_end_date->formatPattern());
        $this->is_active->CurrentValue = $this->is_active->FormValue;
        $this->assigned_by->CurrentValue = $this->assigned_by->FormValue;
        $this->assigned_at->CurrentValue = $this->assigned_at->FormValue;
        $this->assigned_at->CurrentValue = UnFormatDateTime($this->assigned_at->CurrentValue, $this->assigned_at->formatPattern());
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
        $this->user_office_id->setDbValue($row['user_office_id']);
        $this->user_id->setDbValue($row['user_id']);
        $this->office_id->setDbValue($row['office_id']);
        $this->is_primary->setDbValue((ConvertToBool($row['is_primary']) ? "1" : "0"));
        $this->access_start_date->setDbValue($row['access_start_date']);
        $this->access_end_date->setDbValue($row['access_end_date']);
        $this->is_active->setDbValue((ConvertToBool($row['is_active']) ? "1" : "0"));
        $this->assigned_by->setDbValue($row['assigned_by']);
        $this->assigned_at->setDbValue($row['assigned_at']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['user_office_id'] = $this->user_office_id->DefaultValue;
        $row['user_id'] = $this->user_id->DefaultValue;
        $row['office_id'] = $this->office_id->DefaultValue;
        $row['is_primary'] = $this->is_primary->DefaultValue;
        $row['access_start_date'] = $this->access_start_date->DefaultValue;
        $row['access_end_date'] = $this->access_end_date->DefaultValue;
        $row['is_active'] = $this->is_active->DefaultValue;
        $row['assigned_by'] = $this->assigned_by->DefaultValue;
        $row['assigned_at'] = $this->assigned_at->DefaultValue;
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

        // user_office_id
        $this->user_office_id->RowCssClass = "row";

        // user_id
        $this->user_id->RowCssClass = "row";

        // office_id
        $this->office_id->RowCssClass = "row";

        // is_primary
        $this->is_primary->RowCssClass = "row";

        // access_start_date
        $this->access_start_date->RowCssClass = "row";

        // access_end_date
        $this->access_end_date->RowCssClass = "row";

        // is_active
        $this->is_active->RowCssClass = "row";

        // assigned_by
        $this->assigned_by->RowCssClass = "row";

        // assigned_at
        $this->assigned_at->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // user_office_id
            $this->user_office_id->ViewValue = $this->user_office_id->CurrentValue;

            // user_id
            $this->user_id->ViewValue = $this->user_id->CurrentValue;
            $this->user_id->ViewValue = FormatNumber($this->user_id->ViewValue, $this->user_id->formatPattern());

            // office_id
            $this->office_id->ViewValue = $this->office_id->CurrentValue;
            $this->office_id->ViewValue = FormatNumber($this->office_id->ViewValue, $this->office_id->formatPattern());

            // is_primary
            if (ConvertToBool($this->is_primary->CurrentValue)) {
                $this->is_primary->ViewValue = $this->is_primary->tagCaption(1) != "" ? $this->is_primary->tagCaption(1) : "Yes";
            } else {
                $this->is_primary->ViewValue = $this->is_primary->tagCaption(2) != "" ? $this->is_primary->tagCaption(2) : "No";
            }

            // access_start_date
            $this->access_start_date->ViewValue = $this->access_start_date->CurrentValue;
            $this->access_start_date->ViewValue = FormatDateTime($this->access_start_date->ViewValue, $this->access_start_date->formatPattern());

            // access_end_date
            $this->access_end_date->ViewValue = $this->access_end_date->CurrentValue;
            $this->access_end_date->ViewValue = FormatDateTime($this->access_end_date->ViewValue, $this->access_end_date->formatPattern());

            // is_active
            if (ConvertToBool($this->is_active->CurrentValue)) {
                $this->is_active->ViewValue = $this->is_active->tagCaption(1) != "" ? $this->is_active->tagCaption(1) : "Yes";
            } else {
                $this->is_active->ViewValue = $this->is_active->tagCaption(2) != "" ? $this->is_active->tagCaption(2) : "No";
            }

            // assigned_by
            $this->assigned_by->ViewValue = $this->assigned_by->CurrentValue;
            $this->assigned_by->ViewValue = FormatNumber($this->assigned_by->ViewValue, $this->assigned_by->formatPattern());

            // assigned_at
            $this->assigned_at->ViewValue = $this->assigned_at->CurrentValue;
            $this->assigned_at->ViewValue = FormatDateTime($this->assigned_at->ViewValue, $this->assigned_at->formatPattern());

            // user_office_id
            $this->user_office_id->HrefValue = "";

            // user_id
            $this->user_id->HrefValue = "";

            // office_id
            $this->office_id->HrefValue = "";

            // is_primary
            $this->is_primary->HrefValue = "";

            // access_start_date
            $this->access_start_date->HrefValue = "";

            // access_end_date
            $this->access_end_date->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // assigned_by
            $this->assigned_by->HrefValue = "";

            // assigned_at
            $this->assigned_at->HrefValue = "";
        } elseif ($this->RowType == RowType::EDIT) {
            // user_office_id
            $this->user_office_id->setupEditAttributes();
            $this->user_office_id->EditValue = $this->user_office_id->CurrentValue;

            // user_id
            $this->user_id->setupEditAttributes();
            $this->user_id->EditValue = $this->user_id->CurrentValue;
            $this->user_id->PlaceHolder = RemoveHtml($this->user_id->caption());
            if (strval($this->user_id->EditValue) != "" && is_numeric($this->user_id->EditValue)) {
                $this->user_id->EditValue = FormatNumber($this->user_id->EditValue, null);
            }

            // office_id
            $this->office_id->setupEditAttributes();
            $this->office_id->EditValue = $this->office_id->CurrentValue;
            $this->office_id->PlaceHolder = RemoveHtml($this->office_id->caption());
            if (strval($this->office_id->EditValue) != "" && is_numeric($this->office_id->EditValue)) {
                $this->office_id->EditValue = FormatNumber($this->office_id->EditValue, null);
            }

            // is_primary
            $this->is_primary->EditValue = $this->is_primary->options(false);
            $this->is_primary->PlaceHolder = RemoveHtml($this->is_primary->caption());

            // access_start_date
            $this->access_start_date->setupEditAttributes();
            $this->access_start_date->EditValue = HtmlEncode(FormatDateTime($this->access_start_date->CurrentValue, $this->access_start_date->formatPattern()));
            $this->access_start_date->PlaceHolder = RemoveHtml($this->access_start_date->caption());

            // access_end_date
            $this->access_end_date->setupEditAttributes();
            $this->access_end_date->EditValue = HtmlEncode(FormatDateTime($this->access_end_date->CurrentValue, $this->access_end_date->formatPattern()));
            $this->access_end_date->PlaceHolder = RemoveHtml($this->access_end_date->caption());

            // is_active
            $this->is_active->EditValue = $this->is_active->options(false);
            $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

            // assigned_by
            $this->assigned_by->setupEditAttributes();
            $this->assigned_by->EditValue = $this->assigned_by->CurrentValue;
            $this->assigned_by->PlaceHolder = RemoveHtml($this->assigned_by->caption());
            if (strval($this->assigned_by->EditValue) != "" && is_numeric($this->assigned_by->EditValue)) {
                $this->assigned_by->EditValue = FormatNumber($this->assigned_by->EditValue, null);
            }

            // assigned_at
            $this->assigned_at->setupEditAttributes();
            $this->assigned_at->EditValue = HtmlEncode(FormatDateTime($this->assigned_at->CurrentValue, $this->assigned_at->formatPattern()));
            $this->assigned_at->PlaceHolder = RemoveHtml($this->assigned_at->caption());

            // Edit refer script

            // user_office_id
            $this->user_office_id->HrefValue = "";

            // user_id
            $this->user_id->HrefValue = "";

            // office_id
            $this->office_id->HrefValue = "";

            // is_primary
            $this->is_primary->HrefValue = "";

            // access_start_date
            $this->access_start_date->HrefValue = "";

            // access_end_date
            $this->access_end_date->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // assigned_by
            $this->assigned_by->HrefValue = "";

            // assigned_at
            $this->assigned_at->HrefValue = "";
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
            if ($this->user_office_id->Visible && $this->user_office_id->Required) {
                if (!$this->user_office_id->IsDetailKey && EmptyValue($this->user_office_id->FormValue)) {
                    $this->user_office_id->addErrorMessage(str_replace("%s", $this->user_office_id->caption(), $this->user_office_id->RequiredErrorMessage));
                }
            }
            if ($this->user_id->Visible && $this->user_id->Required) {
                if (!$this->user_id->IsDetailKey && EmptyValue($this->user_id->FormValue)) {
                    $this->user_id->addErrorMessage(str_replace("%s", $this->user_id->caption(), $this->user_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->user_id->FormValue)) {
                $this->user_id->addErrorMessage($this->user_id->getErrorMessage(false));
            }
            if ($this->office_id->Visible && $this->office_id->Required) {
                if (!$this->office_id->IsDetailKey && EmptyValue($this->office_id->FormValue)) {
                    $this->office_id->addErrorMessage(str_replace("%s", $this->office_id->caption(), $this->office_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->office_id->FormValue)) {
                $this->office_id->addErrorMessage($this->office_id->getErrorMessage(false));
            }
            if ($this->is_primary->Visible && $this->is_primary->Required) {
                if ($this->is_primary->FormValue == "") {
                    $this->is_primary->addErrorMessage(str_replace("%s", $this->is_primary->caption(), $this->is_primary->RequiredErrorMessage));
                }
            }
            if ($this->access_start_date->Visible && $this->access_start_date->Required) {
                if (!$this->access_start_date->IsDetailKey && EmptyValue($this->access_start_date->FormValue)) {
                    $this->access_start_date->addErrorMessage(str_replace("%s", $this->access_start_date->caption(), $this->access_start_date->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->access_start_date->FormValue, $this->access_start_date->formatPattern())) {
                $this->access_start_date->addErrorMessage($this->access_start_date->getErrorMessage(false));
            }
            if ($this->access_end_date->Visible && $this->access_end_date->Required) {
                if (!$this->access_end_date->IsDetailKey && EmptyValue($this->access_end_date->FormValue)) {
                    $this->access_end_date->addErrorMessage(str_replace("%s", $this->access_end_date->caption(), $this->access_end_date->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->access_end_date->FormValue, $this->access_end_date->formatPattern())) {
                $this->access_end_date->addErrorMessage($this->access_end_date->getErrorMessage(false));
            }
            if ($this->is_active->Visible && $this->is_active->Required) {
                if ($this->is_active->FormValue == "") {
                    $this->is_active->addErrorMessage(str_replace("%s", $this->is_active->caption(), $this->is_active->RequiredErrorMessage));
                }
            }
            if ($this->assigned_by->Visible && $this->assigned_by->Required) {
                if (!$this->assigned_by->IsDetailKey && EmptyValue($this->assigned_by->FormValue)) {
                    $this->assigned_by->addErrorMessage(str_replace("%s", $this->assigned_by->caption(), $this->assigned_by->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->assigned_by->FormValue)) {
                $this->assigned_by->addErrorMessage($this->assigned_by->getErrorMessage(false));
            }
            if ($this->assigned_at->Visible && $this->assigned_at->Required) {
                if (!$this->assigned_at->IsDetailKey && EmptyValue($this->assigned_at->FormValue)) {
                    $this->assigned_at->addErrorMessage(str_replace("%s", $this->assigned_at->caption(), $this->assigned_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->assigned_at->FormValue, $this->assigned_at->formatPattern())) {
                $this->assigned_at->addErrorMessage($this->assigned_at->getErrorMessage(false));
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

        // user_id
        $this->user_id->setDbValueDef($rsnew, $this->user_id->CurrentValue, $this->user_id->ReadOnly);

        // office_id
        $this->office_id->setDbValueDef($rsnew, $this->office_id->CurrentValue, $this->office_id->ReadOnly);

        // is_primary
        $tmpBool = $this->is_primary->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_primary->setDbValueDef($rsnew, $tmpBool, $this->is_primary->ReadOnly);

        // access_start_date
        $this->access_start_date->setDbValueDef($rsnew, UnFormatDateTime($this->access_start_date->CurrentValue, $this->access_start_date->formatPattern()), $this->access_start_date->ReadOnly);

        // access_end_date
        $this->access_end_date->setDbValueDef($rsnew, UnFormatDateTime($this->access_end_date->CurrentValue, $this->access_end_date->formatPattern()), $this->access_end_date->ReadOnly);

        // is_active
        $tmpBool = $this->is_active->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_active->setDbValueDef($rsnew, $tmpBool, $this->is_active->ReadOnly);

        // assigned_by
        $this->assigned_by->setDbValueDef($rsnew, $this->assigned_by->CurrentValue, $this->assigned_by->ReadOnly);

        // assigned_at
        $this->assigned_at->setDbValueDef($rsnew, UnFormatDateTime($this->assigned_at->CurrentValue, $this->assigned_at->formatPattern()), $this->assigned_at->ReadOnly);
        return $rsnew;
    }

    /**
     * Restore edit form from row
     * @param array $row Row
     */
    protected function restoreEditFormFromRow($row)
    {
        if (isset($row['user_id'])) { // user_id
            $this->user_id->CurrentValue = $row['user_id'];
        }
        if (isset($row['office_id'])) { // office_id
            $this->office_id->CurrentValue = $row['office_id'];
        }
        if (isset($row['is_primary'])) { // is_primary
            $this->is_primary->CurrentValue = $row['is_primary'];
        }
        if (isset($row['access_start_date'])) { // access_start_date
            $this->access_start_date->CurrentValue = $row['access_start_date'];
        }
        if (isset($row['access_end_date'])) { // access_end_date
            $this->access_end_date->CurrentValue = $row['access_end_date'];
        }
        if (isset($row['is_active'])) { // is_active
            $this->is_active->CurrentValue = $row['is_active'];
        }
        if (isset($row['assigned_by'])) { // assigned_by
            $this->assigned_by->CurrentValue = $row['assigned_by'];
        }
        if (isset($row['assigned_at'])) { // assigned_at
            $this->assigned_at->CurrentValue = $row['assigned_at'];
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("UserOfficesList"), "", $this->TableVar, true);
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
                case "x_is_primary":
                    break;
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
