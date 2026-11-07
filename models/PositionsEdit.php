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
class PositionsEdit extends Positions
{
    use MessagesTrait;

    // Page ID
    public $PageID = "edit";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "PositionsEdit";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "PositionsEdit";

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
        $this->position_id->setVisibility();
        $this->position_category_id->setVisibility();
        $this->position_code->setVisibility();
        $this->position_title->setVisibility();
        $this->position_level->setVisibility();
        $this->job_description->setVisibility();
        $this->salary_grade->setVisibility();
        $this->is_active->setVisibility();
        $this->created_by->setVisibility();
        $this->created_at->setVisibility();
        $this->updated_at->setVisibility();
        $this->step_increment->setVisibility();
        $this->qualification_standards->setVisibility();
        $this->competency_requirements->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'positions';
        $this->TableName = 'positions';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-edit-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (positions)
        if (!isset($GLOBALS["positions"]) || $GLOBALS["positions"]::class == PROJECT_NAMESPACE . "positions") {
            $GLOBALS["positions"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'positions');
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
                        $result["view"] = SameString($pageName, "PositionsView"); // If View page, no primary button
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
            $key .= @$ar['position_id'];
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
            $this->position_id->Visible = false;
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
        $this->setupLookupOptions($this->position_category_id);
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
            if (($keyValue = Get("position_id") ?? Key(0) ?? Route(2)) !== null) {
                $this->position_id->setQueryStringValue($keyValue);
                $this->position_id->setOldValue($this->position_id->QueryStringValue);
            } elseif (Post("position_id") !== null) {
                $this->position_id->setFormValue(Post("position_id"));
                $this->position_id->setOldValue($this->position_id->FormValue);
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
                if (($keyValue = Get("position_id") ?? Route("position_id")) !== null) {
                    $this->position_id->setQueryStringValue($keyValue);
                    $loadByQuery = true;
                } else {
                    $this->position_id->CurrentValue = null;
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
                        $this->terminate("PositionsList"); // No matching record, return to list
                        return;
                    }
                break;
            case "update": // Update
                $returnUrl = $this->getReturnUrl();
                if (GetPageName($returnUrl) == "PositionsList") {
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
                        if (GetPageName($returnUrl) != "PositionsList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "PositionsList"; // Return list page content
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

        // Check field name 'position_id' first before field var 'x_position_id'
        $val = $CurrentForm->hasValue("position_id") ? $CurrentForm->getValue("position_id") : $CurrentForm->getValue("x_position_id");
        if (!$this->position_id->IsDetailKey) {
            $this->position_id->setFormValue($val);
        }

        // Check field name 'position_category_id' first before field var 'x_position_category_id'
        $val = $CurrentForm->hasValue("position_category_id") ? $CurrentForm->getValue("position_category_id") : $CurrentForm->getValue("x_position_category_id");
        if (!$this->position_category_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->position_category_id->Visible = false; // Disable update for API request
            } else {
                $this->position_category_id->setFormValue($val);
            }
        }

        // Check field name 'position_code' first before field var 'x_position_code'
        $val = $CurrentForm->hasValue("position_code") ? $CurrentForm->getValue("position_code") : $CurrentForm->getValue("x_position_code");
        if (!$this->position_code->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->position_code->Visible = false; // Disable update for API request
            } else {
                $this->position_code->setFormValue($val);
            }
        }

        // Check field name 'position_title' first before field var 'x_position_title'
        $val = $CurrentForm->hasValue("position_title") ? $CurrentForm->getValue("position_title") : $CurrentForm->getValue("x_position_title");
        if (!$this->position_title->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->position_title->Visible = false; // Disable update for API request
            } else {
                $this->position_title->setFormValue($val);
            }
        }

        // Check field name 'position_level' first before field var 'x_position_level'
        $val = $CurrentForm->hasValue("position_level") ? $CurrentForm->getValue("position_level") : $CurrentForm->getValue("x_position_level");
        if (!$this->position_level->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->position_level->Visible = false; // Disable update for API request
            } else {
                $this->position_level->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'job_description' first before field var 'x_job_description'
        $val = $CurrentForm->hasValue("job_description") ? $CurrentForm->getValue("job_description") : $CurrentForm->getValue("x_job_description");
        if (!$this->job_description->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->job_description->Visible = false; // Disable update for API request
            } else {
                $this->job_description->setFormValue($val);
            }
        }

        // Check field name 'salary_grade' first before field var 'x_salary_grade'
        $val = $CurrentForm->hasValue("salary_grade") ? $CurrentForm->getValue("salary_grade") : $CurrentForm->getValue("x_salary_grade");
        if (!$this->salary_grade->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->salary_grade->Visible = false; // Disable update for API request
            } else {
                $this->salary_grade->setFormValue($val, true, $validate);
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

        // Check field name 'step_increment' first before field var 'x_step_increment'
        $val = $CurrentForm->hasValue("step_increment") ? $CurrentForm->getValue("step_increment") : $CurrentForm->getValue("x_step_increment");
        if (!$this->step_increment->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->step_increment->Visible = false; // Disable update for API request
            } else {
                $this->step_increment->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'qualification_standards' first before field var 'x_qualification_standards'
        $val = $CurrentForm->hasValue("qualification_standards") ? $CurrentForm->getValue("qualification_standards") : $CurrentForm->getValue("x_qualification_standards");
        if (!$this->qualification_standards->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->qualification_standards->Visible = false; // Disable update for API request
            } else {
                $this->qualification_standards->setFormValue($val);
            }
        }

        // Check field name 'competency_requirements' first before field var 'x_competency_requirements'
        $val = $CurrentForm->hasValue("competency_requirements") ? $CurrentForm->getValue("competency_requirements") : $CurrentForm->getValue("x_competency_requirements");
        if (!$this->competency_requirements->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->competency_requirements->Visible = false; // Disable update for API request
            } else {
                $this->competency_requirements->setFormValue($val);
            }
        }
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->position_id->CurrentValue = $this->position_id->FormValue;
        $this->position_category_id->CurrentValue = $this->position_category_id->FormValue;
        $this->position_code->CurrentValue = $this->position_code->FormValue;
        $this->position_title->CurrentValue = $this->position_title->FormValue;
        $this->position_level->CurrentValue = $this->position_level->FormValue;
        $this->job_description->CurrentValue = $this->job_description->FormValue;
        $this->salary_grade->CurrentValue = $this->salary_grade->FormValue;
        $this->is_active->CurrentValue = $this->is_active->FormValue;
        $this->created_by->CurrentValue = $this->created_by->FormValue;
        $this->created_at->CurrentValue = $this->created_at->FormValue;
        $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        $this->updated_at->CurrentValue = $this->updated_at->FormValue;
        $this->updated_at->CurrentValue = UnFormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern());
        $this->step_increment->CurrentValue = $this->step_increment->FormValue;
        $this->qualification_standards->CurrentValue = $this->qualification_standards->FormValue;
        $this->competency_requirements->CurrentValue = $this->competency_requirements->FormValue;
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
        $this->position_id->setDbValue($row['position_id']);
        $this->position_category_id->setDbValue($row['position_category_id']);
        $this->position_code->setDbValue($row['position_code']);
        $this->position_title->setDbValue($row['position_title']);
        $this->position_level->setDbValue($row['position_level']);
        $this->job_description->setDbValue($row['job_description']);
        $this->salary_grade->setDbValue($row['salary_grade']);
        $this->is_active->setDbValue((ConvertToBool($row['is_active']) ? "1" : "0"));
        $this->created_by->setDbValue($row['created_by']);
        $this->created_at->setDbValue($row['created_at']);
        $this->updated_at->setDbValue($row['updated_at']);
        $this->step_increment->setDbValue($row['step_increment']);
        $this->qualification_standards->setDbValue($row['qualification_standards']);
        $this->competency_requirements->setDbValue($row['competency_requirements']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['position_id'] = $this->position_id->DefaultValue;
        $row['position_category_id'] = $this->position_category_id->DefaultValue;
        $row['position_code'] = $this->position_code->DefaultValue;
        $row['position_title'] = $this->position_title->DefaultValue;
        $row['position_level'] = $this->position_level->DefaultValue;
        $row['job_description'] = $this->job_description->DefaultValue;
        $row['salary_grade'] = $this->salary_grade->DefaultValue;
        $row['is_active'] = $this->is_active->DefaultValue;
        $row['created_by'] = $this->created_by->DefaultValue;
        $row['created_at'] = $this->created_at->DefaultValue;
        $row['updated_at'] = $this->updated_at->DefaultValue;
        $row['step_increment'] = $this->step_increment->DefaultValue;
        $row['qualification_standards'] = $this->qualification_standards->DefaultValue;
        $row['competency_requirements'] = $this->competency_requirements->DefaultValue;
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

        // position_id
        $this->position_id->RowCssClass = "row";

        // position_category_id
        $this->position_category_id->RowCssClass = "row";

        // position_code
        $this->position_code->RowCssClass = "row";

        // position_title
        $this->position_title->RowCssClass = "row";

        // position_level
        $this->position_level->RowCssClass = "row";

        // job_description
        $this->job_description->RowCssClass = "row";

        // salary_grade
        $this->salary_grade->RowCssClass = "row";

        // is_active
        $this->is_active->RowCssClass = "row";

        // created_by
        $this->created_by->RowCssClass = "row";

        // created_at
        $this->created_at->RowCssClass = "row";

        // updated_at
        $this->updated_at->RowCssClass = "row";

        // step_increment
        $this->step_increment->RowCssClass = "row";

        // qualification_standards
        $this->qualification_standards->RowCssClass = "row";

        // competency_requirements
        $this->competency_requirements->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // position_id
            $this->position_id->ViewValue = $this->position_id->CurrentValue;

            // position_category_id
            $curVal = strval($this->position_category_id->CurrentValue);
            if ($curVal != "") {
                $this->position_category_id->ViewValue = $this->position_category_id->lookupCacheOption($curVal);
                if ($this->position_category_id->ViewValue === null) { // Lookup from database
                    $filterWrk = SearchFilter($this->position_category_id->Lookup->getTable()->Fields["position_category_id"]->searchExpression(), "=", $curVal, $this->position_category_id->Lookup->getTable()->Fields["position_category_id"]->searchDataType(), "");
                    $sqlWrk = $this->position_category_id->Lookup->getSql(false, $filterWrk, '', $this, true, true);
                    $conn = Conn();
                    $config = $conn->getConfiguration();
                    $config->setResultCache($this->Cache);
                    $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                    $ari = count($rswrk);
                    if ($ari > 0) { // Lookup values found
                        $arwrk = $this->position_category_id->Lookup->renderViewRow($rswrk[0]);
                        $this->position_category_id->ViewValue = $this->position_category_id->displayValue($arwrk);
                    } else {
                        $this->position_category_id->ViewValue = FormatNumber($this->position_category_id->CurrentValue, $this->position_category_id->formatPattern());
                    }
                }
            } else {
                $this->position_category_id->ViewValue = null;
            }

            // position_code
            $this->position_code->ViewValue = $this->position_code->CurrentValue;

            // position_title
            $this->position_title->ViewValue = $this->position_title->CurrentValue;

            // position_level
            $this->position_level->ViewValue = $this->position_level->CurrentValue;
            $this->position_level->ViewValue = FormatNumber($this->position_level->ViewValue, $this->position_level->formatPattern());

            // job_description
            $this->job_description->ViewValue = $this->job_description->CurrentValue;

            // salary_grade
            $this->salary_grade->ViewValue = $this->salary_grade->CurrentValue;
            $this->salary_grade->ViewValue = FormatNumber($this->salary_grade->ViewValue, $this->salary_grade->formatPattern());

            // is_active
            if (ConvertToBool($this->is_active->CurrentValue)) {
                $this->is_active->ViewValue = $this->is_active->tagCaption(1) != "" ? $this->is_active->tagCaption(1) : "Yes";
            } else {
                $this->is_active->ViewValue = $this->is_active->tagCaption(2) != "" ? $this->is_active->tagCaption(2) : "No";
            }

            // created_by
            $this->created_by->ViewValue = $this->created_by->CurrentValue;
            $this->created_by->ViewValue = FormatNumber($this->created_by->ViewValue, $this->created_by->formatPattern());

            // created_at
            $this->created_at->ViewValue = $this->created_at->CurrentValue;
            $this->created_at->ViewValue = FormatDateTime($this->created_at->ViewValue, $this->created_at->formatPattern());

            // updated_at
            $this->updated_at->ViewValue = $this->updated_at->CurrentValue;
            $this->updated_at->ViewValue = FormatDateTime($this->updated_at->ViewValue, $this->updated_at->formatPattern());

            // step_increment
            $this->step_increment->ViewValue = $this->step_increment->CurrentValue;
            $this->step_increment->ViewValue = FormatNumber($this->step_increment->ViewValue, $this->step_increment->formatPattern());

            // qualification_standards
            $this->qualification_standards->ViewValue = $this->qualification_standards->CurrentValue;

            // competency_requirements
            $this->competency_requirements->ViewValue = $this->competency_requirements->CurrentValue;

            // position_id
            $this->position_id->HrefValue = "";

            // position_category_id
            $this->position_category_id->HrefValue = "";

            // position_code
            $this->position_code->HrefValue = "";

            // position_title
            $this->position_title->HrefValue = "";

            // position_level
            $this->position_level->HrefValue = "";

            // job_description
            $this->job_description->HrefValue = "";

            // salary_grade
            $this->salary_grade->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // created_by
            $this->created_by->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // updated_at
            $this->updated_at->HrefValue = "";

            // step_increment
            $this->step_increment->HrefValue = "";

            // qualification_standards
            $this->qualification_standards->HrefValue = "";

            // competency_requirements
            $this->competency_requirements->HrefValue = "";
        } elseif ($this->RowType == RowType::EDIT) {
            // position_id
            $this->position_id->setupEditAttributes();
            $this->position_id->EditValue = $this->position_id->CurrentValue;

            // position_category_id
            $this->position_category_id->setupEditAttributes();
            $curVal = trim(strval($this->position_category_id->CurrentValue));
            if ($curVal != "") {
                $this->position_category_id->ViewValue = $this->position_category_id->lookupCacheOption($curVal);
            } else {
                $this->position_category_id->ViewValue = $this->position_category_id->Lookup !== null && is_array($this->position_category_id->lookupOptions()) && count($this->position_category_id->lookupOptions()) > 0 ? $curVal : null;
            }
            if ($this->position_category_id->ViewValue !== null) { // Load from cache
                $this->position_category_id->EditValue = array_values($this->position_category_id->lookupOptions());
            } else { // Lookup from database
                if ($curVal == "") {
                    $filterWrk = "0=1";
                } else {
                    $filterWrk = SearchFilter($this->position_category_id->Lookup->getTable()->Fields["position_category_id"]->searchExpression(), "=", $this->position_category_id->CurrentValue, $this->position_category_id->Lookup->getTable()->Fields["position_category_id"]->searchDataType(), "");
                }
                $sqlWrk = $this->position_category_id->Lookup->getSql(true, $filterWrk, '', $this, false, true);
                $conn = Conn();
                $config = $conn->getConfiguration();
                $config->setResultCache($this->Cache);
                $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                $ari = count($rswrk);
                $arwrk = $rswrk;
                $this->position_category_id->EditValue = $arwrk;
            }
            $this->position_category_id->PlaceHolder = RemoveHtml($this->position_category_id->caption());

            // position_code
            $this->position_code->setupEditAttributes();
            if (!$this->position_code->Raw) {
                $this->position_code->CurrentValue = HtmlDecode($this->position_code->CurrentValue);
            }
            $this->position_code->EditValue = HtmlEncode($this->position_code->CurrentValue);
            $this->position_code->PlaceHolder = RemoveHtml($this->position_code->caption());

            // position_title
            $this->position_title->setupEditAttributes();
            if (!$this->position_title->Raw) {
                $this->position_title->CurrentValue = HtmlDecode($this->position_title->CurrentValue);
            }
            $this->position_title->EditValue = HtmlEncode($this->position_title->CurrentValue);
            $this->position_title->PlaceHolder = RemoveHtml($this->position_title->caption());

            // position_level
            $this->position_level->setupEditAttributes();
            $this->position_level->EditValue = $this->position_level->CurrentValue;
            $this->position_level->PlaceHolder = RemoveHtml($this->position_level->caption());
            if (strval($this->position_level->EditValue) != "" && is_numeric($this->position_level->EditValue)) {
                $this->position_level->EditValue = FormatNumber($this->position_level->EditValue, null);
            }

            // job_description
            $this->job_description->setupEditAttributes();
            $this->job_description->EditValue = HtmlEncode($this->job_description->CurrentValue);
            $this->job_description->PlaceHolder = RemoveHtml($this->job_description->caption());

            // salary_grade
            $this->salary_grade->setupEditAttributes();
            $this->salary_grade->EditValue = $this->salary_grade->CurrentValue;
            $this->salary_grade->PlaceHolder = RemoveHtml($this->salary_grade->caption());
            if (strval($this->salary_grade->EditValue) != "" && is_numeric($this->salary_grade->EditValue)) {
                $this->salary_grade->EditValue = FormatNumber($this->salary_grade->EditValue, null);
            }

            // is_active
            $this->is_active->EditValue = $this->is_active->options(false);
            $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

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

            // updated_at
            $this->updated_at->setupEditAttributes();
            $this->updated_at->EditValue = HtmlEncode(FormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern()));
            $this->updated_at->PlaceHolder = RemoveHtml($this->updated_at->caption());

            // step_increment
            $this->step_increment->setupEditAttributes();
            $this->step_increment->EditValue = $this->step_increment->CurrentValue;
            $this->step_increment->PlaceHolder = RemoveHtml($this->step_increment->caption());
            if (strval($this->step_increment->EditValue) != "" && is_numeric($this->step_increment->EditValue)) {
                $this->step_increment->EditValue = FormatNumber($this->step_increment->EditValue, null);
            }

            // qualification_standards
            $this->qualification_standards->setupEditAttributes();
            $this->qualification_standards->EditValue = HtmlEncode($this->qualification_standards->CurrentValue);
            $this->qualification_standards->PlaceHolder = RemoveHtml($this->qualification_standards->caption());

            // competency_requirements
            $this->competency_requirements->setupEditAttributes();
            $this->competency_requirements->EditValue = HtmlEncode($this->competency_requirements->CurrentValue);
            $this->competency_requirements->PlaceHolder = RemoveHtml($this->competency_requirements->caption());

            // Edit refer script

            // position_id
            $this->position_id->HrefValue = "";

            // position_category_id
            $this->position_category_id->HrefValue = "";

            // position_code
            $this->position_code->HrefValue = "";

            // position_title
            $this->position_title->HrefValue = "";

            // position_level
            $this->position_level->HrefValue = "";

            // job_description
            $this->job_description->HrefValue = "";

            // salary_grade
            $this->salary_grade->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // created_by
            $this->created_by->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // updated_at
            $this->updated_at->HrefValue = "";

            // step_increment
            $this->step_increment->HrefValue = "";

            // qualification_standards
            $this->qualification_standards->HrefValue = "";

            // competency_requirements
            $this->competency_requirements->HrefValue = "";
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
            if ($this->position_id->Visible && $this->position_id->Required) {
                if (!$this->position_id->IsDetailKey && EmptyValue($this->position_id->FormValue)) {
                    $this->position_id->addErrorMessage(str_replace("%s", $this->position_id->caption(), $this->position_id->RequiredErrorMessage));
                }
            }
            if ($this->position_category_id->Visible && $this->position_category_id->Required) {
                if (!$this->position_category_id->IsDetailKey && EmptyValue($this->position_category_id->FormValue)) {
                    $this->position_category_id->addErrorMessage(str_replace("%s", $this->position_category_id->caption(), $this->position_category_id->RequiredErrorMessage));
                }
            }
            if ($this->position_code->Visible && $this->position_code->Required) {
                if (!$this->position_code->IsDetailKey && EmptyValue($this->position_code->FormValue)) {
                    $this->position_code->addErrorMessage(str_replace("%s", $this->position_code->caption(), $this->position_code->RequiredErrorMessage));
                }
            }
            if ($this->position_title->Visible && $this->position_title->Required) {
                if (!$this->position_title->IsDetailKey && EmptyValue($this->position_title->FormValue)) {
                    $this->position_title->addErrorMessage(str_replace("%s", $this->position_title->caption(), $this->position_title->RequiredErrorMessage));
                }
            }
            if ($this->position_level->Visible && $this->position_level->Required) {
                if (!$this->position_level->IsDetailKey && EmptyValue($this->position_level->FormValue)) {
                    $this->position_level->addErrorMessage(str_replace("%s", $this->position_level->caption(), $this->position_level->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->position_level->FormValue)) {
                $this->position_level->addErrorMessage($this->position_level->getErrorMessage(false));
            }
            if ($this->job_description->Visible && $this->job_description->Required) {
                if (!$this->job_description->IsDetailKey && EmptyValue($this->job_description->FormValue)) {
                    $this->job_description->addErrorMessage(str_replace("%s", $this->job_description->caption(), $this->job_description->RequiredErrorMessage));
                }
            }
            if ($this->salary_grade->Visible && $this->salary_grade->Required) {
                if (!$this->salary_grade->IsDetailKey && EmptyValue($this->salary_grade->FormValue)) {
                    $this->salary_grade->addErrorMessage(str_replace("%s", $this->salary_grade->caption(), $this->salary_grade->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->salary_grade->FormValue)) {
                $this->salary_grade->addErrorMessage($this->salary_grade->getErrorMessage(false));
            }
            if ($this->is_active->Visible && $this->is_active->Required) {
                if ($this->is_active->FormValue == "") {
                    $this->is_active->addErrorMessage(str_replace("%s", $this->is_active->caption(), $this->is_active->RequiredErrorMessage));
                }
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
            if ($this->updated_at->Visible && $this->updated_at->Required) {
                if (!$this->updated_at->IsDetailKey && EmptyValue($this->updated_at->FormValue)) {
                    $this->updated_at->addErrorMessage(str_replace("%s", $this->updated_at->caption(), $this->updated_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->updated_at->FormValue, $this->updated_at->formatPattern())) {
                $this->updated_at->addErrorMessage($this->updated_at->getErrorMessage(false));
            }
            if ($this->step_increment->Visible && $this->step_increment->Required) {
                if (!$this->step_increment->IsDetailKey && EmptyValue($this->step_increment->FormValue)) {
                    $this->step_increment->addErrorMessage(str_replace("%s", $this->step_increment->caption(), $this->step_increment->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->step_increment->FormValue)) {
                $this->step_increment->addErrorMessage($this->step_increment->getErrorMessage(false));
            }
            if ($this->qualification_standards->Visible && $this->qualification_standards->Required) {
                if (!$this->qualification_standards->IsDetailKey && EmptyValue($this->qualification_standards->FormValue)) {
                    $this->qualification_standards->addErrorMessage(str_replace("%s", $this->qualification_standards->caption(), $this->qualification_standards->RequiredErrorMessage));
                }
            }
            if ($this->competency_requirements->Visible && $this->competency_requirements->Required) {
                if (!$this->competency_requirements->IsDetailKey && EmptyValue($this->competency_requirements->FormValue)) {
                    $this->competency_requirements->addErrorMessage(str_replace("%s", $this->competency_requirements->caption(), $this->competency_requirements->RequiredErrorMessage));
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

        // position_category_id
        $this->position_category_id->setDbValueDef($rsnew, $this->position_category_id->CurrentValue, $this->position_category_id->ReadOnly);

        // position_code
        $this->position_code->setDbValueDef($rsnew, $this->position_code->CurrentValue, $this->position_code->ReadOnly);

        // position_title
        $this->position_title->setDbValueDef($rsnew, $this->position_title->CurrentValue, $this->position_title->ReadOnly);

        // position_level
        $this->position_level->setDbValueDef($rsnew, $this->position_level->CurrentValue, $this->position_level->ReadOnly);

        // job_description
        $this->job_description->setDbValueDef($rsnew, $this->job_description->CurrentValue, $this->job_description->ReadOnly);

        // salary_grade
        $this->salary_grade->setDbValueDef($rsnew, $this->salary_grade->CurrentValue, $this->salary_grade->ReadOnly);

        // is_active
        $tmpBool = $this->is_active->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_active->setDbValueDef($rsnew, $tmpBool, $this->is_active->ReadOnly);

        // created_by
        $this->created_by->setDbValueDef($rsnew, $this->created_by->CurrentValue, $this->created_by->ReadOnly);

        // created_at
        $this->created_at->setDbValueDef($rsnew, UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()), $this->created_at->ReadOnly);

        // updated_at
        $this->updated_at->setDbValueDef($rsnew, UnFormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern()), $this->updated_at->ReadOnly);

        // step_increment
        $this->step_increment->setDbValueDef($rsnew, $this->step_increment->CurrentValue, $this->step_increment->ReadOnly);

        // qualification_standards
        $this->qualification_standards->setDbValueDef($rsnew, $this->qualification_standards->CurrentValue, $this->qualification_standards->ReadOnly);

        // competency_requirements
        $this->competency_requirements->setDbValueDef($rsnew, $this->competency_requirements->CurrentValue, $this->competency_requirements->ReadOnly);
        return $rsnew;
    }

    /**
     * Restore edit form from row
     * @param array $row Row
     */
    protected function restoreEditFormFromRow($row)
    {
        if (isset($row['position_category_id'])) { // position_category_id
            $this->position_category_id->CurrentValue = $row['position_category_id'];
        }
        if (isset($row['position_code'])) { // position_code
            $this->position_code->CurrentValue = $row['position_code'];
        }
        if (isset($row['position_title'])) { // position_title
            $this->position_title->CurrentValue = $row['position_title'];
        }
        if (isset($row['position_level'])) { // position_level
            $this->position_level->CurrentValue = $row['position_level'];
        }
        if (isset($row['job_description'])) { // job_description
            $this->job_description->CurrentValue = $row['job_description'];
        }
        if (isset($row['salary_grade'])) { // salary_grade
            $this->salary_grade->CurrentValue = $row['salary_grade'];
        }
        if (isset($row['is_active'])) { // is_active
            $this->is_active->CurrentValue = $row['is_active'];
        }
        if (isset($row['created_by'])) { // created_by
            $this->created_by->CurrentValue = $row['created_by'];
        }
        if (isset($row['created_at'])) { // created_at
            $this->created_at->CurrentValue = $row['created_at'];
        }
        if (isset($row['updated_at'])) { // updated_at
            $this->updated_at->CurrentValue = $row['updated_at'];
        }
        if (isset($row['step_increment'])) { // step_increment
            $this->step_increment->CurrentValue = $row['step_increment'];
        }
        if (isset($row['qualification_standards'])) { // qualification_standards
            $this->qualification_standards->CurrentValue = $row['qualification_standards'];
        }
        if (isset($row['competency_requirements'])) { // competency_requirements
            $this->competency_requirements->CurrentValue = $row['competency_requirements'];
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("PositionsList"), "", $this->TableVar, true);
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
                case "x_position_category_id":
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
