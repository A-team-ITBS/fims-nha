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
class PsgcAdd extends Psgc
{
    use MessagesTrait;

    // Page ID
    public $PageID = "add";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "PsgcAdd";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "PsgcAdd";

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
        $this->code_10->setVisibility();
        $this->name->setVisibility();
        $this->psgc_code->setVisibility();
        $this->level->setVisibility();
        $this->od_name->setVisibility();
        $this->city_class->setVisibility();
        $this->income_class->setVisibility();
        $this->rural_urban->setVisibility();
        $this->population_2015->setVisibility();
        $this->population_2020->setVisibility();
        $this->status->setVisibility();
        $this->display->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'psgc';
        $this->TableName = 'psgc';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-add-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (psgc)
        if (!isset($GLOBALS["psgc"]) || $GLOBALS["psgc"]::class == PROJECT_NAMESPACE . "psgc") {
            $GLOBALS["psgc"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'psgc');
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
                        $result["view"] = SameString($pageName, "PsgcView"); // If View page, no primary button
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
            $key .= @$ar['code_10'];
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
            if (($keyValue = Get("code_10") ?? Route("code_10")) !== null) {
                $this->code_10->setQueryStringValue($keyValue);
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
                    $this->terminate("PsgcList"); // No matching record, return to list
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
                    if (GetPageName($returnUrl) == "PsgcList") {
                        $returnUrl = $this->addMasterUrl($returnUrl); // List page, return to List page with correct master key if necessary
                    } elseif (GetPageName($returnUrl) == "PsgcView") {
                        $returnUrl = $this->getViewUrl(); // View page, return to View page with keyurl directly
                    }

                    // Handle UseAjaxActions with return page
                    if ($this->IsModal && $this->UseAjaxActions) {
                        $this->IsModal = false;
                        if (GetPageName($returnUrl) != "PsgcList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "PsgcList"; // Return list page content
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

        // Check field name 'code_10' first before field var 'x_code_10'
        $val = $CurrentForm->hasValue("code_10") ? $CurrentForm->getValue("code_10") : $CurrentForm->getValue("x_code_10");
        if (!$this->code_10->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->code_10->Visible = false; // Disable update for API request
            } else {
                $this->code_10->setFormValue($val);
            }
        }

        // Check field name 'name' first before field var 'x_name'
        $val = $CurrentForm->hasValue("name") ? $CurrentForm->getValue("name") : $CurrentForm->getValue("x_name");
        if (!$this->name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->name->Visible = false; // Disable update for API request
            } else {
                $this->name->setFormValue($val);
            }
        }

        // Check field name 'psgc_code' first before field var 'x_psgc_code'
        $val = $CurrentForm->hasValue("psgc_code") ? $CurrentForm->getValue("psgc_code") : $CurrentForm->getValue("x_psgc_code");
        if (!$this->psgc_code->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->psgc_code->Visible = false; // Disable update for API request
            } else {
                $this->psgc_code->setFormValue($val);
            }
        }

        // Check field name 'level' first before field var 'x_level'
        $val = $CurrentForm->hasValue("level") ? $CurrentForm->getValue("level") : $CurrentForm->getValue("x_level");
        if (!$this->level->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->level->Visible = false; // Disable update for API request
            } else {
                $this->level->setFormValue($val);
            }
        }

        // Check field name 'od_name' first before field var 'x_od_name'
        $val = $CurrentForm->hasValue("od_name") ? $CurrentForm->getValue("od_name") : $CurrentForm->getValue("x_od_name");
        if (!$this->od_name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->od_name->Visible = false; // Disable update for API request
            } else {
                $this->od_name->setFormValue($val);
            }
        }

        // Check field name 'city_class' first before field var 'x_city_class'
        $val = $CurrentForm->hasValue("city_class") ? $CurrentForm->getValue("city_class") : $CurrentForm->getValue("x_city_class");
        if (!$this->city_class->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->city_class->Visible = false; // Disable update for API request
            } else {
                $this->city_class->setFormValue($val);
            }
        }

        // Check field name 'income_class' first before field var 'x_income_class'
        $val = $CurrentForm->hasValue("income_class") ? $CurrentForm->getValue("income_class") : $CurrentForm->getValue("x_income_class");
        if (!$this->income_class->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->income_class->Visible = false; // Disable update for API request
            } else {
                $this->income_class->setFormValue($val);
            }
        }

        // Check field name 'rural_urban' first before field var 'x_rural_urban'
        $val = $CurrentForm->hasValue("rural_urban") ? $CurrentForm->getValue("rural_urban") : $CurrentForm->getValue("x_rural_urban");
        if (!$this->rural_urban->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->rural_urban->Visible = false; // Disable update for API request
            } else {
                $this->rural_urban->setFormValue($val);
            }
        }

        // Check field name 'population_2015' first before field var 'x_population_2015'
        $val = $CurrentForm->hasValue("population_2015") ? $CurrentForm->getValue("population_2015") : $CurrentForm->getValue("x_population_2015");
        if (!$this->population_2015->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->population_2015->Visible = false; // Disable update for API request
            } else {
                $this->population_2015->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'population_2020' first before field var 'x_population_2020'
        $val = $CurrentForm->hasValue("population_2020") ? $CurrentForm->getValue("population_2020") : $CurrentForm->getValue("x_population_2020");
        if (!$this->population_2020->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->population_2020->Visible = false; // Disable update for API request
            } else {
                $this->population_2020->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'status' first before field var 'x_status'
        $val = $CurrentForm->hasValue("status") ? $CurrentForm->getValue("status") : $CurrentForm->getValue("x_status");
        if (!$this->status->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->status->Visible = false; // Disable update for API request
            } else {
                $this->status->setFormValue($val);
            }
        }

        // Check field name 'display' first before field var 'x_display'
        $val = $CurrentForm->hasValue("display") ? $CurrentForm->getValue("display") : $CurrentForm->getValue("x_display");
        if (!$this->display->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->display->Visible = false; // Disable update for API request
            } else {
                $this->display->setFormValue($val);
            }
        }
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->code_10->CurrentValue = $this->code_10->FormValue;
        $this->name->CurrentValue = $this->name->FormValue;
        $this->psgc_code->CurrentValue = $this->psgc_code->FormValue;
        $this->level->CurrentValue = $this->level->FormValue;
        $this->od_name->CurrentValue = $this->od_name->FormValue;
        $this->city_class->CurrentValue = $this->city_class->FormValue;
        $this->income_class->CurrentValue = $this->income_class->FormValue;
        $this->rural_urban->CurrentValue = $this->rural_urban->FormValue;
        $this->population_2015->CurrentValue = $this->population_2015->FormValue;
        $this->population_2020->CurrentValue = $this->population_2020->FormValue;
        $this->status->CurrentValue = $this->status->FormValue;
        $this->display->CurrentValue = $this->display->FormValue;
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
        $this->code_10->setDbValue($row['code_10']);
        $this->name->setDbValue($row['name']);
        $this->psgc_code->setDbValue($row['psgc_code']);
        $this->level->setDbValue($row['level']);
        $this->od_name->setDbValue($row['od_name']);
        $this->city_class->setDbValue($row['city_class']);
        $this->income_class->setDbValue($row['income_class']);
        $this->rural_urban->setDbValue($row['rural_urban']);
        $this->population_2015->setDbValue($row['population_2015']);
        $this->population_2020->setDbValue($row['population_2020']);
        $this->status->setDbValue($row['status']);
        $this->display->setDbValue($row['display']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['code_10'] = $this->code_10->DefaultValue;
        $row['name'] = $this->name->DefaultValue;
        $row['psgc_code'] = $this->psgc_code->DefaultValue;
        $row['level'] = $this->level->DefaultValue;
        $row['od_name'] = $this->od_name->DefaultValue;
        $row['city_class'] = $this->city_class->DefaultValue;
        $row['income_class'] = $this->income_class->DefaultValue;
        $row['rural_urban'] = $this->rural_urban->DefaultValue;
        $row['population_2015'] = $this->population_2015->DefaultValue;
        $row['population_2020'] = $this->population_2020->DefaultValue;
        $row['status'] = $this->status->DefaultValue;
        $row['display'] = $this->display->DefaultValue;
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

        // code_10
        $this->code_10->RowCssClass = "row";

        // name
        $this->name->RowCssClass = "row";

        // psgc_code
        $this->psgc_code->RowCssClass = "row";

        // level
        $this->level->RowCssClass = "row";

        // od_name
        $this->od_name->RowCssClass = "row";

        // city_class
        $this->city_class->RowCssClass = "row";

        // income_class
        $this->income_class->RowCssClass = "row";

        // rural_urban
        $this->rural_urban->RowCssClass = "row";

        // population_2015
        $this->population_2015->RowCssClass = "row";

        // population_2020
        $this->population_2020->RowCssClass = "row";

        // status
        $this->status->RowCssClass = "row";

        // display
        $this->display->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // code_10
            $this->code_10->ViewValue = $this->code_10->CurrentValue;

            // name
            $this->name->ViewValue = $this->name->CurrentValue;

            // psgc_code
            $this->psgc_code->ViewValue = $this->psgc_code->CurrentValue;

            // level
            $this->level->ViewValue = $this->level->CurrentValue;

            // od_name
            $this->od_name->ViewValue = $this->od_name->CurrentValue;

            // city_class
            $this->city_class->ViewValue = $this->city_class->CurrentValue;

            // income_class
            $this->income_class->ViewValue = $this->income_class->CurrentValue;

            // rural_urban
            $this->rural_urban->ViewValue = $this->rural_urban->CurrentValue;

            // population_2015
            $this->population_2015->ViewValue = $this->population_2015->CurrentValue;
            $this->population_2015->ViewValue = FormatNumber($this->population_2015->ViewValue, $this->population_2015->formatPattern());

            // population_2020
            $this->population_2020->ViewValue = $this->population_2020->CurrentValue;
            $this->population_2020->ViewValue = FormatNumber($this->population_2020->ViewValue, $this->population_2020->formatPattern());

            // status
            $this->status->ViewValue = $this->status->CurrentValue;

            // display
            $this->display->ViewValue = $this->display->CurrentValue;

            // code_10
            $this->code_10->HrefValue = "";

            // name
            $this->name->HrefValue = "";

            // psgc_code
            $this->psgc_code->HrefValue = "";

            // level
            $this->level->HrefValue = "";

            // od_name
            $this->od_name->HrefValue = "";

            // city_class
            $this->city_class->HrefValue = "";

            // income_class
            $this->income_class->HrefValue = "";

            // rural_urban
            $this->rural_urban->HrefValue = "";

            // population_2015
            $this->population_2015->HrefValue = "";

            // population_2020
            $this->population_2020->HrefValue = "";

            // status
            $this->status->HrefValue = "";

            // display
            $this->display->HrefValue = "";
        } elseif ($this->RowType == RowType::ADD) {
            // code_10
            $this->code_10->setupEditAttributes();
            if (!$this->code_10->Raw) {
                $this->code_10->CurrentValue = HtmlDecode($this->code_10->CurrentValue);
            }
            $this->code_10->EditValue = HtmlEncode($this->code_10->CurrentValue);
            $this->code_10->PlaceHolder = RemoveHtml($this->code_10->caption());

            // name
            $this->name->setupEditAttributes();
            if (!$this->name->Raw) {
                $this->name->CurrentValue = HtmlDecode($this->name->CurrentValue);
            }
            $this->name->EditValue = HtmlEncode($this->name->CurrentValue);
            $this->name->PlaceHolder = RemoveHtml($this->name->caption());

            // psgc_code
            $this->psgc_code->setupEditAttributes();
            if (!$this->psgc_code->Raw) {
                $this->psgc_code->CurrentValue = HtmlDecode($this->psgc_code->CurrentValue);
            }
            $this->psgc_code->EditValue = HtmlEncode($this->psgc_code->CurrentValue);
            $this->psgc_code->PlaceHolder = RemoveHtml($this->psgc_code->caption());

            // level
            $this->level->setupEditAttributes();
            if (!$this->level->Raw) {
                $this->level->CurrentValue = HtmlDecode($this->level->CurrentValue);
            }
            $this->level->EditValue = HtmlEncode($this->level->CurrentValue);
            $this->level->PlaceHolder = RemoveHtml($this->level->caption());

            // od_name
            $this->od_name->setupEditAttributes();
            if (!$this->od_name->Raw) {
                $this->od_name->CurrentValue = HtmlDecode($this->od_name->CurrentValue);
            }
            $this->od_name->EditValue = HtmlEncode($this->od_name->CurrentValue);
            $this->od_name->PlaceHolder = RemoveHtml($this->od_name->caption());

            // city_class
            $this->city_class->setupEditAttributes();
            if (!$this->city_class->Raw) {
                $this->city_class->CurrentValue = HtmlDecode($this->city_class->CurrentValue);
            }
            $this->city_class->EditValue = HtmlEncode($this->city_class->CurrentValue);
            $this->city_class->PlaceHolder = RemoveHtml($this->city_class->caption());

            // income_class
            $this->income_class->setupEditAttributes();
            if (!$this->income_class->Raw) {
                $this->income_class->CurrentValue = HtmlDecode($this->income_class->CurrentValue);
            }
            $this->income_class->EditValue = HtmlEncode($this->income_class->CurrentValue);
            $this->income_class->PlaceHolder = RemoveHtml($this->income_class->caption());

            // rural_urban
            $this->rural_urban->setupEditAttributes();
            if (!$this->rural_urban->Raw) {
                $this->rural_urban->CurrentValue = HtmlDecode($this->rural_urban->CurrentValue);
            }
            $this->rural_urban->EditValue = HtmlEncode($this->rural_urban->CurrentValue);
            $this->rural_urban->PlaceHolder = RemoveHtml($this->rural_urban->caption());

            // population_2015
            $this->population_2015->setupEditAttributes();
            $this->population_2015->EditValue = $this->population_2015->CurrentValue;
            $this->population_2015->PlaceHolder = RemoveHtml($this->population_2015->caption());
            if (strval($this->population_2015->EditValue) != "" && is_numeric($this->population_2015->EditValue)) {
                $this->population_2015->EditValue = FormatNumber($this->population_2015->EditValue, null);
            }

            // population_2020
            $this->population_2020->setupEditAttributes();
            $this->population_2020->EditValue = $this->population_2020->CurrentValue;
            $this->population_2020->PlaceHolder = RemoveHtml($this->population_2020->caption());
            if (strval($this->population_2020->EditValue) != "" && is_numeric($this->population_2020->EditValue)) {
                $this->population_2020->EditValue = FormatNumber($this->population_2020->EditValue, null);
            }

            // status
            $this->status->setupEditAttributes();
            if (!$this->status->Raw) {
                $this->status->CurrentValue = HtmlDecode($this->status->CurrentValue);
            }
            $this->status->EditValue = HtmlEncode($this->status->CurrentValue);
            $this->status->PlaceHolder = RemoveHtml($this->status->caption());

            // display
            $this->display->setupEditAttributes();
            if (!$this->display->Raw) {
                $this->display->CurrentValue = HtmlDecode($this->display->CurrentValue);
            }
            $this->display->EditValue = HtmlEncode($this->display->CurrentValue);
            $this->display->PlaceHolder = RemoveHtml($this->display->caption());

            // Add refer script

            // code_10
            $this->code_10->HrefValue = "";

            // name
            $this->name->HrefValue = "";

            // psgc_code
            $this->psgc_code->HrefValue = "";

            // level
            $this->level->HrefValue = "";

            // od_name
            $this->od_name->HrefValue = "";

            // city_class
            $this->city_class->HrefValue = "";

            // income_class
            $this->income_class->HrefValue = "";

            // rural_urban
            $this->rural_urban->HrefValue = "";

            // population_2015
            $this->population_2015->HrefValue = "";

            // population_2020
            $this->population_2020->HrefValue = "";

            // status
            $this->status->HrefValue = "";

            // display
            $this->display->HrefValue = "";
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
            if ($this->code_10->Visible && $this->code_10->Required) {
                if (!$this->code_10->IsDetailKey && EmptyValue($this->code_10->FormValue)) {
                    $this->code_10->addErrorMessage(str_replace("%s", $this->code_10->caption(), $this->code_10->RequiredErrorMessage));
                }
            }
            if ($this->name->Visible && $this->name->Required) {
                if (!$this->name->IsDetailKey && EmptyValue($this->name->FormValue)) {
                    $this->name->addErrorMessage(str_replace("%s", $this->name->caption(), $this->name->RequiredErrorMessage));
                }
            }
            if ($this->psgc_code->Visible && $this->psgc_code->Required) {
                if (!$this->psgc_code->IsDetailKey && EmptyValue($this->psgc_code->FormValue)) {
                    $this->psgc_code->addErrorMessage(str_replace("%s", $this->psgc_code->caption(), $this->psgc_code->RequiredErrorMessage));
                }
            }
            if ($this->level->Visible && $this->level->Required) {
                if (!$this->level->IsDetailKey && EmptyValue($this->level->FormValue)) {
                    $this->level->addErrorMessage(str_replace("%s", $this->level->caption(), $this->level->RequiredErrorMessage));
                }
            }
            if ($this->od_name->Visible && $this->od_name->Required) {
                if (!$this->od_name->IsDetailKey && EmptyValue($this->od_name->FormValue)) {
                    $this->od_name->addErrorMessage(str_replace("%s", $this->od_name->caption(), $this->od_name->RequiredErrorMessage));
                }
            }
            if ($this->city_class->Visible && $this->city_class->Required) {
                if (!$this->city_class->IsDetailKey && EmptyValue($this->city_class->FormValue)) {
                    $this->city_class->addErrorMessage(str_replace("%s", $this->city_class->caption(), $this->city_class->RequiredErrorMessage));
                }
            }
            if ($this->income_class->Visible && $this->income_class->Required) {
                if (!$this->income_class->IsDetailKey && EmptyValue($this->income_class->FormValue)) {
                    $this->income_class->addErrorMessage(str_replace("%s", $this->income_class->caption(), $this->income_class->RequiredErrorMessage));
                }
            }
            if ($this->rural_urban->Visible && $this->rural_urban->Required) {
                if (!$this->rural_urban->IsDetailKey && EmptyValue($this->rural_urban->FormValue)) {
                    $this->rural_urban->addErrorMessage(str_replace("%s", $this->rural_urban->caption(), $this->rural_urban->RequiredErrorMessage));
                }
            }
            if ($this->population_2015->Visible && $this->population_2015->Required) {
                if (!$this->population_2015->IsDetailKey && EmptyValue($this->population_2015->FormValue)) {
                    $this->population_2015->addErrorMessage(str_replace("%s", $this->population_2015->caption(), $this->population_2015->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->population_2015->FormValue)) {
                $this->population_2015->addErrorMessage($this->population_2015->getErrorMessage(false));
            }
            if ($this->population_2020->Visible && $this->population_2020->Required) {
                if (!$this->population_2020->IsDetailKey && EmptyValue($this->population_2020->FormValue)) {
                    $this->population_2020->addErrorMessage(str_replace("%s", $this->population_2020->caption(), $this->population_2020->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->population_2020->FormValue)) {
                $this->population_2020->addErrorMessage($this->population_2020->getErrorMessage(false));
            }
            if ($this->status->Visible && $this->status->Required) {
                if (!$this->status->IsDetailKey && EmptyValue($this->status->FormValue)) {
                    $this->status->addErrorMessage(str_replace("%s", $this->status->caption(), $this->status->RequiredErrorMessage));
                }
            }
            if ($this->display->Visible && $this->display->Required) {
                if (!$this->display->IsDetailKey && EmptyValue($this->display->FormValue)) {
                    $this->display->addErrorMessage(str_replace("%s", $this->display->caption(), $this->display->RequiredErrorMessage));
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
        if ($this->code_10->CurrentValue != "") { // Check field with unique index
            $filter = "(\"code_10\" = '" . AdjustSql($this->code_10->CurrentValue, $this->Dbid) . "')";
            $rsChk = $this->loadRs($filter)->fetch();
            if ($rsChk !== false) {
                $idxErrMsg = str_replace("%f", $this->code_10->caption(), $Language->phrase("DupIndex"));
                $idxErrMsg = str_replace("%v", $this->code_10->CurrentValue, $idxErrMsg);
                $this->setFailureMessage($idxErrMsg);
                return false;
            }
        }
        $conn = $this->getConnection();

        // Load db values from old row
        $this->loadDbValues($rsold);

        // Call Row Inserting event
        $insertRow = $this->rowInserting($rsold, $rsnew);

        // Check if key value entered
        if ($insertRow && $this->ValidateKey && strval($rsnew['code_10']) == "") {
            $this->setFailureMessage($Language->phrase("InvalidKeyValue"));
            $insertRow = false;
        }

        // Check for duplicate key
        if ($insertRow && $this->ValidateKey) {
            $filter = $this->getRecordFilter($rsnew);
            $rsChk = $this->loadRs($filter)->fetch();
            if ($rsChk !== false) {
                $keyErrMsg = str_replace("%f", $filter, $Language->phrase("DupKey"));
                $this->setFailureMessage($keyErrMsg);
                $insertRow = false;
            }
        }
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

        // code_10
        $this->code_10->setDbValueDef($rsnew, $this->code_10->CurrentValue, false);

        // name
        $this->name->setDbValueDef($rsnew, $this->name->CurrentValue, false);

        // psgc_code
        $this->psgc_code->setDbValueDef($rsnew, $this->psgc_code->CurrentValue, false);

        // level
        $this->level->setDbValueDef($rsnew, $this->level->CurrentValue, false);

        // od_name
        $this->od_name->setDbValueDef($rsnew, $this->od_name->CurrentValue, false);

        // city_class
        $this->city_class->setDbValueDef($rsnew, $this->city_class->CurrentValue, false);

        // income_class
        $this->income_class->setDbValueDef($rsnew, $this->income_class->CurrentValue, false);

        // rural_urban
        $this->rural_urban->setDbValueDef($rsnew, $this->rural_urban->CurrentValue, false);

        // population_2015
        $this->population_2015->setDbValueDef($rsnew, $this->population_2015->CurrentValue, false);

        // population_2020
        $this->population_2020->setDbValueDef($rsnew, $this->population_2020->CurrentValue, false);

        // status
        $this->status->setDbValueDef($rsnew, $this->status->CurrentValue, false);

        // display
        $this->display->setDbValueDef($rsnew, $this->display->CurrentValue, false);
        return $rsnew;
    }

    /**
     * Restore add form from row
     * @param array $row Row
     */
    protected function restoreAddFormFromRow($row)
    {
        if (isset($row['code_10'])) { // code_10
            $this->code_10->setFormValue($row['code_10']);
        }
        if (isset($row['name'])) { // name
            $this->name->setFormValue($row['name']);
        }
        if (isset($row['psgc_code'])) { // psgc_code
            $this->psgc_code->setFormValue($row['psgc_code']);
        }
        if (isset($row['level'])) { // level
            $this->level->setFormValue($row['level']);
        }
        if (isset($row['od_name'])) { // od_name
            $this->od_name->setFormValue($row['od_name']);
        }
        if (isset($row['city_class'])) { // city_class
            $this->city_class->setFormValue($row['city_class']);
        }
        if (isset($row['income_class'])) { // income_class
            $this->income_class->setFormValue($row['income_class']);
        }
        if (isset($row['rural_urban'])) { // rural_urban
            $this->rural_urban->setFormValue($row['rural_urban']);
        }
        if (isset($row['population_2015'])) { // population_2015
            $this->population_2015->setFormValue($row['population_2015']);
        }
        if (isset($row['population_2020'])) { // population_2020
            $this->population_2020->setFormValue($row['population_2020']);
        }
        if (isset($row['status'])) { // status
            $this->status->setFormValue($row['status']);
        }
        if (isset($row['display'])) { // display
            $this->display->setFormValue($row['display']);
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("PsgcList"), "", $this->TableVar, true);
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
