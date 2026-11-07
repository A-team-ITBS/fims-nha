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
class OfficesAdd extends Offices
{
    use MessagesTrait;

    // Page ID
    public $PageID = "add";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "OfficesAdd";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "OfficesAdd";

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
        $this->office_id->Visible = false;
        $this->office_name->setVisibility();
        $this->office_code->setVisibility();
        $this->office_type->setVisibility();
        $this->location->setVisibility();
        $this->address->setVisibility();
        $this->region->setVisibility();
        $this->province->setVisibility();
        $this->city_municipality->setVisibility();
        $this->office_contact_number->setVisibility();
        $this->office_email->setVisibility();
        $this->office_telephone->setVisibility();
        $this->office_fax->setVisibility();
        $this->parent_office_id->setVisibility();
        $this->is_active->setVisibility();
        $this->date_opened->setVisibility();
        $this->date_closed->setVisibility();
        $this->created_by->setVisibility();
        $this->created_at->setVisibility();
        $this->modified_by->setVisibility();
        $this->modified_at->setVisibility();
        $this->remarks->setVisibility();
        $this->metadata->setVisibility();
        $this->rc_id->setVisibility();
        $this->region_code->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'offices';
        $this->TableName = 'offices';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-add-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (offices)
        if (!isset($GLOBALS["offices"]) || $GLOBALS["offices"]::class == PROJECT_NAMESPACE . "offices") {
            $GLOBALS["offices"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'offices');
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
                        $result["view"] = SameString($pageName, "OfficesView"); // If View page, no primary button
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
            $key .= @$ar['office_id'];
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
            $this->office_id->Visible = false;
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
            if (($keyValue = Get("office_id") ?? Route("office_id")) !== null) {
                $this->office_id->setQueryStringValue($keyValue);
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
                    $this->terminate("OfficesList"); // No matching record, return to list
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
                    if (GetPageName($returnUrl) == "OfficesList") {
                        $returnUrl = $this->addMasterUrl($returnUrl); // List page, return to List page with correct master key if necessary
                    } elseif (GetPageName($returnUrl) == "OfficesView") {
                        $returnUrl = $this->getViewUrl(); // View page, return to View page with keyurl directly
                    }

                    // Handle UseAjaxActions with return page
                    if ($this->IsModal && $this->UseAjaxActions) {
                        $this->IsModal = false;
                        if (GetPageName($returnUrl) != "OfficesList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "OfficesList"; // Return list page content
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
    }

    // Load form values
    protected function loadFormValues()
    {
        // Load from form
        global $CurrentForm;
        $validate = !Config("SERVER_VALIDATE");

        // Check field name 'office_name' first before field var 'x_office_name'
        $val = $CurrentForm->hasValue("office_name") ? $CurrentForm->getValue("office_name") : $CurrentForm->getValue("x_office_name");
        if (!$this->office_name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->office_name->Visible = false; // Disable update for API request
            } else {
                $this->office_name->setFormValue($val);
            }
        }

        // Check field name 'office_code' first before field var 'x_office_code'
        $val = $CurrentForm->hasValue("office_code") ? $CurrentForm->getValue("office_code") : $CurrentForm->getValue("x_office_code");
        if (!$this->office_code->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->office_code->Visible = false; // Disable update for API request
            } else {
                $this->office_code->setFormValue($val);
            }
        }

        // Check field name 'office_type' first before field var 'x_office_type'
        $val = $CurrentForm->hasValue("office_type") ? $CurrentForm->getValue("office_type") : $CurrentForm->getValue("x_office_type");
        if (!$this->office_type->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->office_type->Visible = false; // Disable update for API request
            } else {
                $this->office_type->setFormValue($val);
            }
        }

        // Check field name 'location' first before field var 'x_location'
        $val = $CurrentForm->hasValue("location") ? $CurrentForm->getValue("location") : $CurrentForm->getValue("x_location");
        if (!$this->location->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->location->Visible = false; // Disable update for API request
            } else {
                $this->location->setFormValue($val);
            }
        }

        // Check field name 'address' first before field var 'x_address'
        $val = $CurrentForm->hasValue("address") ? $CurrentForm->getValue("address") : $CurrentForm->getValue("x_address");
        if (!$this->address->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->address->Visible = false; // Disable update for API request
            } else {
                $this->address->setFormValue($val);
            }
        }

        // Check field name 'region' first before field var 'x_region'
        $val = $CurrentForm->hasValue("region") ? $CurrentForm->getValue("region") : $CurrentForm->getValue("x_region");
        if (!$this->region->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->region->Visible = false; // Disable update for API request
            } else {
                $this->region->setFormValue($val);
            }
        }

        // Check field name 'province' first before field var 'x_province'
        $val = $CurrentForm->hasValue("province") ? $CurrentForm->getValue("province") : $CurrentForm->getValue("x_province");
        if (!$this->province->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->province->Visible = false; // Disable update for API request
            } else {
                $this->province->setFormValue($val);
            }
        }

        // Check field name 'city_municipality' first before field var 'x_city_municipality'
        $val = $CurrentForm->hasValue("city_municipality") ? $CurrentForm->getValue("city_municipality") : $CurrentForm->getValue("x_city_municipality");
        if (!$this->city_municipality->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->city_municipality->Visible = false; // Disable update for API request
            } else {
                $this->city_municipality->setFormValue($val);
            }
        }

        // Check field name 'office_contact_number' first before field var 'x_office_contact_number'
        $val = $CurrentForm->hasValue("office_contact_number") ? $CurrentForm->getValue("office_contact_number") : $CurrentForm->getValue("x_office_contact_number");
        if (!$this->office_contact_number->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->office_contact_number->Visible = false; // Disable update for API request
            } else {
                $this->office_contact_number->setFormValue($val);
            }
        }

        // Check field name 'office_email' first before field var 'x_office_email'
        $val = $CurrentForm->hasValue("office_email") ? $CurrentForm->getValue("office_email") : $CurrentForm->getValue("x_office_email");
        if (!$this->office_email->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->office_email->Visible = false; // Disable update for API request
            } else {
                $this->office_email->setFormValue($val);
            }
        }

        // Check field name 'office_telephone' first before field var 'x_office_telephone'
        $val = $CurrentForm->hasValue("office_telephone") ? $CurrentForm->getValue("office_telephone") : $CurrentForm->getValue("x_office_telephone");
        if (!$this->office_telephone->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->office_telephone->Visible = false; // Disable update for API request
            } else {
                $this->office_telephone->setFormValue($val);
            }
        }

        // Check field name 'office_fax' first before field var 'x_office_fax'
        $val = $CurrentForm->hasValue("office_fax") ? $CurrentForm->getValue("office_fax") : $CurrentForm->getValue("x_office_fax");
        if (!$this->office_fax->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->office_fax->Visible = false; // Disable update for API request
            } else {
                $this->office_fax->setFormValue($val);
            }
        }

        // Check field name 'parent_office_id' first before field var 'x_parent_office_id'
        $val = $CurrentForm->hasValue("parent_office_id") ? $CurrentForm->getValue("parent_office_id") : $CurrentForm->getValue("x_parent_office_id");
        if (!$this->parent_office_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->parent_office_id->Visible = false; // Disable update for API request
            } else {
                $this->parent_office_id->setFormValue($val, true, $validate);
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

        // Check field name 'date_opened' first before field var 'x_date_opened'
        $val = $CurrentForm->hasValue("date_opened") ? $CurrentForm->getValue("date_opened") : $CurrentForm->getValue("x_date_opened");
        if (!$this->date_opened->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->date_opened->Visible = false; // Disable update for API request
            } else {
                $this->date_opened->setFormValue($val, true, $validate);
            }
            $this->date_opened->CurrentValue = UnFormatDateTime($this->date_opened->CurrentValue, $this->date_opened->formatPattern());
        }

        // Check field name 'date_closed' first before field var 'x_date_closed'
        $val = $CurrentForm->hasValue("date_closed") ? $CurrentForm->getValue("date_closed") : $CurrentForm->getValue("x_date_closed");
        if (!$this->date_closed->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->date_closed->Visible = false; // Disable update for API request
            } else {
                $this->date_closed->setFormValue($val, true, $validate);
            }
            $this->date_closed->CurrentValue = UnFormatDateTime($this->date_closed->CurrentValue, $this->date_closed->formatPattern());
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

        // Check field name 'modified_by' first before field var 'x_modified_by'
        $val = $CurrentForm->hasValue("modified_by") ? $CurrentForm->getValue("modified_by") : $CurrentForm->getValue("x_modified_by");
        if (!$this->modified_by->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->modified_by->Visible = false; // Disable update for API request
            } else {
                $this->modified_by->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'modified_at' first before field var 'x_modified_at'
        $val = $CurrentForm->hasValue("modified_at") ? $CurrentForm->getValue("modified_at") : $CurrentForm->getValue("x_modified_at");
        if (!$this->modified_at->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->modified_at->Visible = false; // Disable update for API request
            } else {
                $this->modified_at->setFormValue($val, true, $validate);
            }
            $this->modified_at->CurrentValue = UnFormatDateTime($this->modified_at->CurrentValue, $this->modified_at->formatPattern());
        }

        // Check field name 'remarks' first before field var 'x_remarks'
        $val = $CurrentForm->hasValue("remarks") ? $CurrentForm->getValue("remarks") : $CurrentForm->getValue("x_remarks");
        if (!$this->remarks->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->remarks->Visible = false; // Disable update for API request
            } else {
                $this->remarks->setFormValue($val);
            }
        }

        // Check field name 'metadata' first before field var 'x_metadata'
        $val = $CurrentForm->hasValue("metadata") ? $CurrentForm->getValue("metadata") : $CurrentForm->getValue("x_metadata");
        if (!$this->metadata->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->metadata->Visible = false; // Disable update for API request
            } else {
                $this->metadata->setFormValue($val);
            }
        }

        // Check field name 'rc_id' first before field var 'x_rc_id'
        $val = $CurrentForm->hasValue("rc_id") ? $CurrentForm->getValue("rc_id") : $CurrentForm->getValue("x_rc_id");
        if (!$this->rc_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->rc_id->Visible = false; // Disable update for API request
            } else {
                $this->rc_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'region_code' first before field var 'x_region_code'
        $val = $CurrentForm->hasValue("region_code") ? $CurrentForm->getValue("region_code") : $CurrentForm->getValue("x_region_code");
        if (!$this->region_code->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->region_code->Visible = false; // Disable update for API request
            } else {
                $this->region_code->setFormValue($val);
            }
        }

        // Check field name 'office_id' first before field var 'x_office_id'
        $val = $CurrentForm->hasValue("office_id") ? $CurrentForm->getValue("office_id") : $CurrentForm->getValue("x_office_id");
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->office_name->CurrentValue = $this->office_name->FormValue;
        $this->office_code->CurrentValue = $this->office_code->FormValue;
        $this->office_type->CurrentValue = $this->office_type->FormValue;
        $this->location->CurrentValue = $this->location->FormValue;
        $this->address->CurrentValue = $this->address->FormValue;
        $this->region->CurrentValue = $this->region->FormValue;
        $this->province->CurrentValue = $this->province->FormValue;
        $this->city_municipality->CurrentValue = $this->city_municipality->FormValue;
        $this->office_contact_number->CurrentValue = $this->office_contact_number->FormValue;
        $this->office_email->CurrentValue = $this->office_email->FormValue;
        $this->office_telephone->CurrentValue = $this->office_telephone->FormValue;
        $this->office_fax->CurrentValue = $this->office_fax->FormValue;
        $this->parent_office_id->CurrentValue = $this->parent_office_id->FormValue;
        $this->is_active->CurrentValue = $this->is_active->FormValue;
        $this->date_opened->CurrentValue = $this->date_opened->FormValue;
        $this->date_opened->CurrentValue = UnFormatDateTime($this->date_opened->CurrentValue, $this->date_opened->formatPattern());
        $this->date_closed->CurrentValue = $this->date_closed->FormValue;
        $this->date_closed->CurrentValue = UnFormatDateTime($this->date_closed->CurrentValue, $this->date_closed->formatPattern());
        $this->created_by->CurrentValue = $this->created_by->FormValue;
        $this->created_at->CurrentValue = $this->created_at->FormValue;
        $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        $this->modified_by->CurrentValue = $this->modified_by->FormValue;
        $this->modified_at->CurrentValue = $this->modified_at->FormValue;
        $this->modified_at->CurrentValue = UnFormatDateTime($this->modified_at->CurrentValue, $this->modified_at->formatPattern());
        $this->remarks->CurrentValue = $this->remarks->FormValue;
        $this->metadata->CurrentValue = $this->metadata->FormValue;
        $this->rc_id->CurrentValue = $this->rc_id->FormValue;
        $this->region_code->CurrentValue = $this->region_code->FormValue;
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
        $this->is_active->setDbValue((ConvertToBool($row['is_active']) ? "1" : "0"));
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

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['office_id'] = $this->office_id->DefaultValue;
        $row['office_name'] = $this->office_name->DefaultValue;
        $row['office_code'] = $this->office_code->DefaultValue;
        $row['office_type'] = $this->office_type->DefaultValue;
        $row['location'] = $this->location->DefaultValue;
        $row['address'] = $this->address->DefaultValue;
        $row['region'] = $this->region->DefaultValue;
        $row['province'] = $this->province->DefaultValue;
        $row['city_municipality'] = $this->city_municipality->DefaultValue;
        $row['office_contact_number'] = $this->office_contact_number->DefaultValue;
        $row['office_email'] = $this->office_email->DefaultValue;
        $row['office_telephone'] = $this->office_telephone->DefaultValue;
        $row['office_fax'] = $this->office_fax->DefaultValue;
        $row['parent_office_id'] = $this->parent_office_id->DefaultValue;
        $row['is_active'] = $this->is_active->DefaultValue;
        $row['date_opened'] = $this->date_opened->DefaultValue;
        $row['date_closed'] = $this->date_closed->DefaultValue;
        $row['created_by'] = $this->created_by->DefaultValue;
        $row['created_at'] = $this->created_at->DefaultValue;
        $row['modified_by'] = $this->modified_by->DefaultValue;
        $row['modified_at'] = $this->modified_at->DefaultValue;
        $row['remarks'] = $this->remarks->DefaultValue;
        $row['metadata'] = $this->metadata->DefaultValue;
        $row['rc_id'] = $this->rc_id->DefaultValue;
        $row['region_code'] = $this->region_code->DefaultValue;
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

        // office_id
        $this->office_id->RowCssClass = "row";

        // office_name
        $this->office_name->RowCssClass = "row";

        // office_code
        $this->office_code->RowCssClass = "row";

        // office_type
        $this->office_type->RowCssClass = "row";

        // location
        $this->location->RowCssClass = "row";

        // address
        $this->address->RowCssClass = "row";

        // region
        $this->region->RowCssClass = "row";

        // province
        $this->province->RowCssClass = "row";

        // city_municipality
        $this->city_municipality->RowCssClass = "row";

        // office_contact_number
        $this->office_contact_number->RowCssClass = "row";

        // office_email
        $this->office_email->RowCssClass = "row";

        // office_telephone
        $this->office_telephone->RowCssClass = "row";

        // office_fax
        $this->office_fax->RowCssClass = "row";

        // parent_office_id
        $this->parent_office_id->RowCssClass = "row";

        // is_active
        $this->is_active->RowCssClass = "row";

        // date_opened
        $this->date_opened->RowCssClass = "row";

        // date_closed
        $this->date_closed->RowCssClass = "row";

        // created_by
        $this->created_by->RowCssClass = "row";

        // created_at
        $this->created_at->RowCssClass = "row";

        // modified_by
        $this->modified_by->RowCssClass = "row";

        // modified_at
        $this->modified_at->RowCssClass = "row";

        // remarks
        $this->remarks->RowCssClass = "row";

        // metadata
        $this->metadata->RowCssClass = "row";

        // rc_id
        $this->rc_id->RowCssClass = "row";

        // region_code
        $this->region_code->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
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

            // office_name
            $this->office_name->HrefValue = "";

            // office_code
            $this->office_code->HrefValue = "";

            // office_type
            $this->office_type->HrefValue = "";

            // location
            $this->location->HrefValue = "";

            // address
            $this->address->HrefValue = "";

            // region
            $this->region->HrefValue = "";

            // province
            $this->province->HrefValue = "";

            // city_municipality
            $this->city_municipality->HrefValue = "";

            // office_contact_number
            $this->office_contact_number->HrefValue = "";

            // office_email
            $this->office_email->HrefValue = "";

            // office_telephone
            $this->office_telephone->HrefValue = "";

            // office_fax
            $this->office_fax->HrefValue = "";

            // parent_office_id
            $this->parent_office_id->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // date_opened
            $this->date_opened->HrefValue = "";

            // date_closed
            $this->date_closed->HrefValue = "";

            // created_by
            $this->created_by->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // modified_by
            $this->modified_by->HrefValue = "";

            // modified_at
            $this->modified_at->HrefValue = "";

            // remarks
            $this->remarks->HrefValue = "";

            // metadata
            $this->metadata->HrefValue = "";

            // rc_id
            $this->rc_id->HrefValue = "";

            // region_code
            $this->region_code->HrefValue = "";
        } elseif ($this->RowType == RowType::ADD) {
            // office_name
            $this->office_name->setupEditAttributes();
            if (!$this->office_name->Raw) {
                $this->office_name->CurrentValue = HtmlDecode($this->office_name->CurrentValue);
            }
            $this->office_name->EditValue = HtmlEncode($this->office_name->CurrentValue);
            $this->office_name->PlaceHolder = RemoveHtml($this->office_name->caption());

            // office_code
            $this->office_code->setupEditAttributes();
            if (!$this->office_code->Raw) {
                $this->office_code->CurrentValue = HtmlDecode($this->office_code->CurrentValue);
            }
            $this->office_code->EditValue = HtmlEncode($this->office_code->CurrentValue);
            $this->office_code->PlaceHolder = RemoveHtml($this->office_code->caption());

            // office_type
            $this->office_type->setupEditAttributes();
            if (!$this->office_type->Raw) {
                $this->office_type->CurrentValue = HtmlDecode($this->office_type->CurrentValue);
            }
            $this->office_type->EditValue = HtmlEncode($this->office_type->CurrentValue);
            $this->office_type->PlaceHolder = RemoveHtml($this->office_type->caption());

            // location
            $this->location->setupEditAttributes();
            $this->location->EditValue = HtmlEncode($this->location->CurrentValue);
            $this->location->PlaceHolder = RemoveHtml($this->location->caption());

            // address
            $this->address->setupEditAttributes();
            $this->address->EditValue = HtmlEncode($this->address->CurrentValue);
            $this->address->PlaceHolder = RemoveHtml($this->address->caption());

            // region
            $this->region->setupEditAttributes();
            if (!$this->region->Raw) {
                $this->region->CurrentValue = HtmlDecode($this->region->CurrentValue);
            }
            $this->region->EditValue = HtmlEncode($this->region->CurrentValue);
            $this->region->PlaceHolder = RemoveHtml($this->region->caption());

            // province
            $this->province->setupEditAttributes();
            if (!$this->province->Raw) {
                $this->province->CurrentValue = HtmlDecode($this->province->CurrentValue);
            }
            $this->province->EditValue = HtmlEncode($this->province->CurrentValue);
            $this->province->PlaceHolder = RemoveHtml($this->province->caption());

            // city_municipality
            $this->city_municipality->setupEditAttributes();
            if (!$this->city_municipality->Raw) {
                $this->city_municipality->CurrentValue = HtmlDecode($this->city_municipality->CurrentValue);
            }
            $this->city_municipality->EditValue = HtmlEncode($this->city_municipality->CurrentValue);
            $this->city_municipality->PlaceHolder = RemoveHtml($this->city_municipality->caption());

            // office_contact_number
            $this->office_contact_number->setupEditAttributes();
            if (!$this->office_contact_number->Raw) {
                $this->office_contact_number->CurrentValue = HtmlDecode($this->office_contact_number->CurrentValue);
            }
            $this->office_contact_number->EditValue = HtmlEncode($this->office_contact_number->CurrentValue);
            $this->office_contact_number->PlaceHolder = RemoveHtml($this->office_contact_number->caption());

            // office_email
            $this->office_email->setupEditAttributes();
            if (!$this->office_email->Raw) {
                $this->office_email->CurrentValue = HtmlDecode($this->office_email->CurrentValue);
            }
            $this->office_email->EditValue = HtmlEncode($this->office_email->CurrentValue);
            $this->office_email->PlaceHolder = RemoveHtml($this->office_email->caption());

            // office_telephone
            $this->office_telephone->setupEditAttributes();
            if (!$this->office_telephone->Raw) {
                $this->office_telephone->CurrentValue = HtmlDecode($this->office_telephone->CurrentValue);
            }
            $this->office_telephone->EditValue = HtmlEncode($this->office_telephone->CurrentValue);
            $this->office_telephone->PlaceHolder = RemoveHtml($this->office_telephone->caption());

            // office_fax
            $this->office_fax->setupEditAttributes();
            if (!$this->office_fax->Raw) {
                $this->office_fax->CurrentValue = HtmlDecode($this->office_fax->CurrentValue);
            }
            $this->office_fax->EditValue = HtmlEncode($this->office_fax->CurrentValue);
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
            $this->date_opened->EditValue = HtmlEncode(FormatDateTime($this->date_opened->CurrentValue, $this->date_opened->formatPattern()));
            $this->date_opened->PlaceHolder = RemoveHtml($this->date_opened->caption());

            // date_closed
            $this->date_closed->setupEditAttributes();
            $this->date_closed->EditValue = HtmlEncode(FormatDateTime($this->date_closed->CurrentValue, $this->date_closed->formatPattern()));
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
            $this->created_at->EditValue = HtmlEncode(FormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()));
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
            $this->modified_at->EditValue = HtmlEncode(FormatDateTime($this->modified_at->CurrentValue, $this->modified_at->formatPattern()));
            $this->modified_at->PlaceHolder = RemoveHtml($this->modified_at->caption());

            // remarks
            $this->remarks->setupEditAttributes();
            $this->remarks->EditValue = HtmlEncode($this->remarks->CurrentValue);
            $this->remarks->PlaceHolder = RemoveHtml($this->remarks->caption());

            // metadata
            $this->metadata->setupEditAttributes();
            $this->metadata->EditValue = HtmlEncode($this->metadata->CurrentValue);
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
            $this->region_code->EditValue = HtmlEncode($this->region_code->CurrentValue);
            $this->region_code->PlaceHolder = RemoveHtml($this->region_code->caption());

            // Add refer script

            // office_name
            $this->office_name->HrefValue = "";

            // office_code
            $this->office_code->HrefValue = "";

            // office_type
            $this->office_type->HrefValue = "";

            // location
            $this->location->HrefValue = "";

            // address
            $this->address->HrefValue = "";

            // region
            $this->region->HrefValue = "";

            // province
            $this->province->HrefValue = "";

            // city_municipality
            $this->city_municipality->HrefValue = "";

            // office_contact_number
            $this->office_contact_number->HrefValue = "";

            // office_email
            $this->office_email->HrefValue = "";

            // office_telephone
            $this->office_telephone->HrefValue = "";

            // office_fax
            $this->office_fax->HrefValue = "";

            // parent_office_id
            $this->parent_office_id->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // date_opened
            $this->date_opened->HrefValue = "";

            // date_closed
            $this->date_closed->HrefValue = "";

            // created_by
            $this->created_by->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // modified_by
            $this->modified_by->HrefValue = "";

            // modified_at
            $this->modified_at->HrefValue = "";

            // remarks
            $this->remarks->HrefValue = "";

            // metadata
            $this->metadata->HrefValue = "";

            // rc_id
            $this->rc_id->HrefValue = "";

            // region_code
            $this->region_code->HrefValue = "";
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
            if ($this->office_name->Visible && $this->office_name->Required) {
                if (!$this->office_name->IsDetailKey && EmptyValue($this->office_name->FormValue)) {
                    $this->office_name->addErrorMessage(str_replace("%s", $this->office_name->caption(), $this->office_name->RequiredErrorMessage));
                }
            }
            if ($this->office_code->Visible && $this->office_code->Required) {
                if (!$this->office_code->IsDetailKey && EmptyValue($this->office_code->FormValue)) {
                    $this->office_code->addErrorMessage(str_replace("%s", $this->office_code->caption(), $this->office_code->RequiredErrorMessage));
                }
            }
            if ($this->office_type->Visible && $this->office_type->Required) {
                if (!$this->office_type->IsDetailKey && EmptyValue($this->office_type->FormValue)) {
                    $this->office_type->addErrorMessage(str_replace("%s", $this->office_type->caption(), $this->office_type->RequiredErrorMessage));
                }
            }
            if ($this->location->Visible && $this->location->Required) {
                if (!$this->location->IsDetailKey && EmptyValue($this->location->FormValue)) {
                    $this->location->addErrorMessage(str_replace("%s", $this->location->caption(), $this->location->RequiredErrorMessage));
                }
            }
            if ($this->address->Visible && $this->address->Required) {
                if (!$this->address->IsDetailKey && EmptyValue($this->address->FormValue)) {
                    $this->address->addErrorMessage(str_replace("%s", $this->address->caption(), $this->address->RequiredErrorMessage));
                }
            }
            if ($this->region->Visible && $this->region->Required) {
                if (!$this->region->IsDetailKey && EmptyValue($this->region->FormValue)) {
                    $this->region->addErrorMessage(str_replace("%s", $this->region->caption(), $this->region->RequiredErrorMessage));
                }
            }
            if ($this->province->Visible && $this->province->Required) {
                if (!$this->province->IsDetailKey && EmptyValue($this->province->FormValue)) {
                    $this->province->addErrorMessage(str_replace("%s", $this->province->caption(), $this->province->RequiredErrorMessage));
                }
            }
            if ($this->city_municipality->Visible && $this->city_municipality->Required) {
                if (!$this->city_municipality->IsDetailKey && EmptyValue($this->city_municipality->FormValue)) {
                    $this->city_municipality->addErrorMessage(str_replace("%s", $this->city_municipality->caption(), $this->city_municipality->RequiredErrorMessage));
                }
            }
            if ($this->office_contact_number->Visible && $this->office_contact_number->Required) {
                if (!$this->office_contact_number->IsDetailKey && EmptyValue($this->office_contact_number->FormValue)) {
                    $this->office_contact_number->addErrorMessage(str_replace("%s", $this->office_contact_number->caption(), $this->office_contact_number->RequiredErrorMessage));
                }
            }
            if ($this->office_email->Visible && $this->office_email->Required) {
                if (!$this->office_email->IsDetailKey && EmptyValue($this->office_email->FormValue)) {
                    $this->office_email->addErrorMessage(str_replace("%s", $this->office_email->caption(), $this->office_email->RequiredErrorMessage));
                }
            }
            if ($this->office_telephone->Visible && $this->office_telephone->Required) {
                if (!$this->office_telephone->IsDetailKey && EmptyValue($this->office_telephone->FormValue)) {
                    $this->office_telephone->addErrorMessage(str_replace("%s", $this->office_telephone->caption(), $this->office_telephone->RequiredErrorMessage));
                }
            }
            if ($this->office_fax->Visible && $this->office_fax->Required) {
                if (!$this->office_fax->IsDetailKey && EmptyValue($this->office_fax->FormValue)) {
                    $this->office_fax->addErrorMessage(str_replace("%s", $this->office_fax->caption(), $this->office_fax->RequiredErrorMessage));
                }
            }
            if ($this->parent_office_id->Visible && $this->parent_office_id->Required) {
                if (!$this->parent_office_id->IsDetailKey && EmptyValue($this->parent_office_id->FormValue)) {
                    $this->parent_office_id->addErrorMessage(str_replace("%s", $this->parent_office_id->caption(), $this->parent_office_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->parent_office_id->FormValue)) {
                $this->parent_office_id->addErrorMessage($this->parent_office_id->getErrorMessage(false));
            }
            if ($this->is_active->Visible && $this->is_active->Required) {
                if ($this->is_active->FormValue == "") {
                    $this->is_active->addErrorMessage(str_replace("%s", $this->is_active->caption(), $this->is_active->RequiredErrorMessage));
                }
            }
            if ($this->date_opened->Visible && $this->date_opened->Required) {
                if (!$this->date_opened->IsDetailKey && EmptyValue($this->date_opened->FormValue)) {
                    $this->date_opened->addErrorMessage(str_replace("%s", $this->date_opened->caption(), $this->date_opened->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->date_opened->FormValue, $this->date_opened->formatPattern())) {
                $this->date_opened->addErrorMessage($this->date_opened->getErrorMessage(false));
            }
            if ($this->date_closed->Visible && $this->date_closed->Required) {
                if (!$this->date_closed->IsDetailKey && EmptyValue($this->date_closed->FormValue)) {
                    $this->date_closed->addErrorMessage(str_replace("%s", $this->date_closed->caption(), $this->date_closed->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->date_closed->FormValue, $this->date_closed->formatPattern())) {
                $this->date_closed->addErrorMessage($this->date_closed->getErrorMessage(false));
            }
            if ($this->created_by->Visible && $this->created_by->Required) {
                if (!$this->created_by->IsDetailKey && EmptyValue($this->created_by->FormValue)) {
                    $this->created_by->addErrorMessage(str_replace("%s", $this->created_by->caption(), $this->created_by->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->created_by->FormValue)) {
                $this->created_by->addErrorMessage($this->created_by->getErrorMessage(false));
            }
            if ($this->created_at->Visible && $this->created_at->Required) {
                if (!$this->created_at->IsDetailKey && EmptyValue($this->created_at->FormValue)) {
                    $this->created_at->addErrorMessage(str_replace("%s", $this->created_at->caption(), $this->created_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->created_at->FormValue, $this->created_at->formatPattern())) {
                $this->created_at->addErrorMessage($this->created_at->getErrorMessage(false));
            }
            if ($this->modified_by->Visible && $this->modified_by->Required) {
                if (!$this->modified_by->IsDetailKey && EmptyValue($this->modified_by->FormValue)) {
                    $this->modified_by->addErrorMessage(str_replace("%s", $this->modified_by->caption(), $this->modified_by->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->modified_by->FormValue)) {
                $this->modified_by->addErrorMessage($this->modified_by->getErrorMessage(false));
            }
            if ($this->modified_at->Visible && $this->modified_at->Required) {
                if (!$this->modified_at->IsDetailKey && EmptyValue($this->modified_at->FormValue)) {
                    $this->modified_at->addErrorMessage(str_replace("%s", $this->modified_at->caption(), $this->modified_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->modified_at->FormValue, $this->modified_at->formatPattern())) {
                $this->modified_at->addErrorMessage($this->modified_at->getErrorMessage(false));
            }
            if ($this->remarks->Visible && $this->remarks->Required) {
                if (!$this->remarks->IsDetailKey && EmptyValue($this->remarks->FormValue)) {
                    $this->remarks->addErrorMessage(str_replace("%s", $this->remarks->caption(), $this->remarks->RequiredErrorMessage));
                }
            }
            if ($this->metadata->Visible && $this->metadata->Required) {
                if (!$this->metadata->IsDetailKey && EmptyValue($this->metadata->FormValue)) {
                    $this->metadata->addErrorMessage(str_replace("%s", $this->metadata->caption(), $this->metadata->RequiredErrorMessage));
                }
            }
            if ($this->rc_id->Visible && $this->rc_id->Required) {
                if (!$this->rc_id->IsDetailKey && EmptyValue($this->rc_id->FormValue)) {
                    $this->rc_id->addErrorMessage(str_replace("%s", $this->rc_id->caption(), $this->rc_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->rc_id->FormValue)) {
                $this->rc_id->addErrorMessage($this->rc_id->getErrorMessage(false));
            }
            if ($this->region_code->Visible && $this->region_code->Required) {
                if (!$this->region_code->IsDetailKey && EmptyValue($this->region_code->FormValue)) {
                    $this->region_code->addErrorMessage(str_replace("%s", $this->region_code->caption(), $this->region_code->RequiredErrorMessage));
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

    // Add record
    protected function addRow($rsold = null)
    {
        global $Language, $Security;

        // Get new row
        $rsnew = $this->getAddRow();

        // Update current values
        $this->setCurrentValues($rsnew);
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

        // office_name
        $this->office_name->setDbValueDef($rsnew, $this->office_name->CurrentValue, false);

        // office_code
        $this->office_code->setDbValueDef($rsnew, $this->office_code->CurrentValue, false);

        // office_type
        $this->office_type->setDbValueDef($rsnew, $this->office_type->CurrentValue, false);

        // location
        $this->location->setDbValueDef($rsnew, $this->location->CurrentValue, false);

        // address
        $this->address->setDbValueDef($rsnew, $this->address->CurrentValue, false);

        // region
        $this->region->setDbValueDef($rsnew, $this->region->CurrentValue, false);

        // province
        $this->province->setDbValueDef($rsnew, $this->province->CurrentValue, false);

        // city_municipality
        $this->city_municipality->setDbValueDef($rsnew, $this->city_municipality->CurrentValue, false);

        // office_contact_number
        $this->office_contact_number->setDbValueDef($rsnew, $this->office_contact_number->CurrentValue, false);

        // office_email
        $this->office_email->setDbValueDef($rsnew, $this->office_email->CurrentValue, false);

        // office_telephone
        $this->office_telephone->setDbValueDef($rsnew, $this->office_telephone->CurrentValue, false);

        // office_fax
        $this->office_fax->setDbValueDef($rsnew, $this->office_fax->CurrentValue, false);

        // parent_office_id
        $this->parent_office_id->setDbValueDef($rsnew, $this->parent_office_id->CurrentValue, false);

        // is_active
        $tmpBool = $this->is_active->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_active->setDbValueDef($rsnew, $tmpBool, strval($this->is_active->CurrentValue) == "");

        // date_opened
        $this->date_opened->setDbValueDef($rsnew, UnFormatDateTime($this->date_opened->CurrentValue, $this->date_opened->formatPattern()), false);

        // date_closed
        $this->date_closed->setDbValueDef($rsnew, UnFormatDateTime($this->date_closed->CurrentValue, $this->date_closed->formatPattern()), false);

        // created_by
        $this->created_by->setDbValueDef($rsnew, $this->created_by->CurrentValue, false);

        // created_at
        $this->created_at->setDbValueDef($rsnew, UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()), strval($this->created_at->CurrentValue) == "");

        // modified_by
        $this->modified_by->setDbValueDef($rsnew, $this->modified_by->CurrentValue, false);

        // modified_at
        $this->modified_at->setDbValueDef($rsnew, UnFormatDateTime($this->modified_at->CurrentValue, $this->modified_at->formatPattern()), strval($this->modified_at->CurrentValue) == "");

        // remarks
        $this->remarks->setDbValueDef($rsnew, $this->remarks->CurrentValue, false);

        // metadata
        $this->metadata->setDbValueDef($rsnew, $this->metadata->CurrentValue, false);

        // rc_id
        $this->rc_id->setDbValueDef($rsnew, $this->rc_id->CurrentValue, false);

        // region_code
        $this->region_code->setDbValueDef($rsnew, $this->region_code->CurrentValue, false);
        return $rsnew;
    }

    /**
     * Restore add form from row
     * @param array $row Row
     */
    protected function restoreAddFormFromRow($row)
    {
        if (isset($row['office_name'])) { // office_name
            $this->office_name->setFormValue($row['office_name']);
        }
        if (isset($row['office_code'])) { // office_code
            $this->office_code->setFormValue($row['office_code']);
        }
        if (isset($row['office_type'])) { // office_type
            $this->office_type->setFormValue($row['office_type']);
        }
        if (isset($row['location'])) { // location
            $this->location->setFormValue($row['location']);
        }
        if (isset($row['address'])) { // address
            $this->address->setFormValue($row['address']);
        }
        if (isset($row['region'])) { // region
            $this->region->setFormValue($row['region']);
        }
        if (isset($row['province'])) { // province
            $this->province->setFormValue($row['province']);
        }
        if (isset($row['city_municipality'])) { // city_municipality
            $this->city_municipality->setFormValue($row['city_municipality']);
        }
        if (isset($row['office_contact_number'])) { // office_contact_number
            $this->office_contact_number->setFormValue($row['office_contact_number']);
        }
        if (isset($row['office_email'])) { // office_email
            $this->office_email->setFormValue($row['office_email']);
        }
        if (isset($row['office_telephone'])) { // office_telephone
            $this->office_telephone->setFormValue($row['office_telephone']);
        }
        if (isset($row['office_fax'])) { // office_fax
            $this->office_fax->setFormValue($row['office_fax']);
        }
        if (isset($row['parent_office_id'])) { // parent_office_id
            $this->parent_office_id->setFormValue($row['parent_office_id']);
        }
        if (isset($row['is_active'])) { // is_active
            $this->is_active->setFormValue($row['is_active']);
        }
        if (isset($row['date_opened'])) { // date_opened
            $this->date_opened->setFormValue($row['date_opened']);
        }
        if (isset($row['date_closed'])) { // date_closed
            $this->date_closed->setFormValue($row['date_closed']);
        }
        if (isset($row['created_by'])) { // created_by
            $this->created_by->setFormValue($row['created_by']);
        }
        if (isset($row['created_at'])) { // created_at
            $this->created_at->setFormValue($row['created_at']);
        }
        if (isset($row['modified_by'])) { // modified_by
            $this->modified_by->setFormValue($row['modified_by']);
        }
        if (isset($row['modified_at'])) { // modified_at
            $this->modified_at->setFormValue($row['modified_at']);
        }
        if (isset($row['remarks'])) { // remarks
            $this->remarks->setFormValue($row['remarks']);
        }
        if (isset($row['metadata'])) { // metadata
            $this->metadata->setFormValue($row['metadata']);
        }
        if (isset($row['rc_id'])) { // rc_id
            $this->rc_id->setFormValue($row['rc_id']);
        }
        if (isset($row['region_code'])) { // region_code
            $this->region_code->setFormValue($row['region_code']);
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("OfficesList"), "", $this->TableVar, true);
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
