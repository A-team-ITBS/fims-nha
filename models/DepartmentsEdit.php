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
class DepartmentsEdit extends Departments
{
    use MessagesTrait;

    // Page ID
    public $PageID = "edit";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "DepartmentsEdit";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "DepartmentsEdit";

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
        $this->department_id->setVisibility();
        $this->department_name->setVisibility();
        $this->description->setVisibility();
        $this->parent_department_id->setVisibility();
        $this->department_head_id->setVisibility();
        $this->department_code->setVisibility();
        $this->department_acronym->setVisibility();
        $this->office_location->setVisibility();
        $this->contact_number->setVisibility();
        $this->_email->setVisibility();
        $this->is_active->setVisibility();
        $this->created_at->setVisibility();
        $this->updated_at->setVisibility();
        $this->rc_id->setVisibility();
        $this->office_id->setVisibility();
        $this->department_type->setVisibility();
        $this->hierarchy_level->setVisibility();
        $this->is_budget_unit->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'departments';
        $this->TableName = 'departments';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-edit-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (departments)
        if (!isset($GLOBALS["departments"]) || $GLOBALS["departments"]::class == PROJECT_NAMESPACE . "departments") {
            $GLOBALS["departments"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'departments');
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
                        $result["view"] = SameString($pageName, "DepartmentsView"); // If View page, no primary button
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
            $key .= @$ar['department_id'];
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
            $this->department_id->Visible = false;
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
        $this->setupLookupOptions($this->parent_department_id);
        $this->setupLookupOptions($this->is_active);
        $this->setupLookupOptions($this->is_budget_unit);

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
            if (($keyValue = Get("department_id") ?? Key(0) ?? Route(2)) !== null) {
                $this->department_id->setQueryStringValue($keyValue);
                $this->department_id->setOldValue($this->department_id->QueryStringValue);
            } elseif (Post("department_id") !== null) {
                $this->department_id->setFormValue(Post("department_id"));
                $this->department_id->setOldValue($this->department_id->FormValue);
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
                if (($keyValue = Get("department_id") ?? Route("department_id")) !== null) {
                    $this->department_id->setQueryStringValue($keyValue);
                    $loadByQuery = true;
                } else {
                    $this->department_id->CurrentValue = null;
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
                        $this->terminate("DepartmentsList"); // No matching record, return to list
                        return;
                    }
                break;
            case "update": // Update
                $returnUrl = $this->getReturnUrl();
                if (GetPageName($returnUrl) == "DepartmentsList") {
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
                        if (GetPageName($returnUrl) != "DepartmentsList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "DepartmentsList"; // Return list page content
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

        // Check field name 'department_id' first before field var 'x_department_id'
        $val = $CurrentForm->hasValue("department_id") ? $CurrentForm->getValue("department_id") : $CurrentForm->getValue("x_department_id");
        if (!$this->department_id->IsDetailKey) {
            $this->department_id->setFormValue($val);
        }

        // Check field name 'department_name' first before field var 'x_department_name'
        $val = $CurrentForm->hasValue("department_name") ? $CurrentForm->getValue("department_name") : $CurrentForm->getValue("x_department_name");
        if (!$this->department_name->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->department_name->Visible = false; // Disable update for API request
            } else {
                $this->department_name->setFormValue($val);
            }
        }

        // Check field name 'description' first before field var 'x_description'
        $val = $CurrentForm->hasValue("description") ? $CurrentForm->getValue("description") : $CurrentForm->getValue("x_description");
        if (!$this->description->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->description->Visible = false; // Disable update for API request
            } else {
                $this->description->setFormValue($val);
            }
        }

        // Check field name 'parent_department_id' first before field var 'x_parent_department_id'
        $val = $CurrentForm->hasValue("parent_department_id") ? $CurrentForm->getValue("parent_department_id") : $CurrentForm->getValue("x_parent_department_id");
        if (!$this->parent_department_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->parent_department_id->Visible = false; // Disable update for API request
            } else {
                $this->parent_department_id->setFormValue($val);
            }
        }

        // Check field name 'department_head_id' first before field var 'x_department_head_id'
        $val = $CurrentForm->hasValue("department_head_id") ? $CurrentForm->getValue("department_head_id") : $CurrentForm->getValue("x_department_head_id");
        if (!$this->department_head_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->department_head_id->Visible = false; // Disable update for API request
            } else {
                $this->department_head_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'department_code' first before field var 'x_department_code'
        $val = $CurrentForm->hasValue("department_code") ? $CurrentForm->getValue("department_code") : $CurrentForm->getValue("x_department_code");
        if (!$this->department_code->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->department_code->Visible = false; // Disable update for API request
            } else {
                $this->department_code->setFormValue($val);
            }
        }

        // Check field name 'department_acronym' first before field var 'x_department_acronym'
        $val = $CurrentForm->hasValue("department_acronym") ? $CurrentForm->getValue("department_acronym") : $CurrentForm->getValue("x_department_acronym");
        if (!$this->department_acronym->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->department_acronym->Visible = false; // Disable update for API request
            } else {
                $this->department_acronym->setFormValue($val);
            }
        }

        // Check field name 'office_location' first before field var 'x_office_location'
        $val = $CurrentForm->hasValue("office_location") ? $CurrentForm->getValue("office_location") : $CurrentForm->getValue("x_office_location");
        if (!$this->office_location->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->office_location->Visible = false; // Disable update for API request
            } else {
                $this->office_location->setFormValue($val);
            }
        }

        // Check field name 'contact_number' first before field var 'x_contact_number'
        $val = $CurrentForm->hasValue("contact_number") ? $CurrentForm->getValue("contact_number") : $CurrentForm->getValue("x_contact_number");
        if (!$this->contact_number->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->contact_number->Visible = false; // Disable update for API request
            } else {
                $this->contact_number->setFormValue($val);
            }
        }

        // Check field name 'email' first before field var 'x__email'
        $val = $CurrentForm->hasValue("email") ? $CurrentForm->getValue("email") : $CurrentForm->getValue("x__email");
        if (!$this->_email->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->_email->Visible = false; // Disable update for API request
            } else {
                $this->_email->setFormValue($val);
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

        // Check field name 'rc_id' first before field var 'x_rc_id'
        $val = $CurrentForm->hasValue("rc_id") ? $CurrentForm->getValue("rc_id") : $CurrentForm->getValue("x_rc_id");
        if (!$this->rc_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->rc_id->Visible = false; // Disable update for API request
            } else {
                $this->rc_id->setFormValue($val, true, $validate);
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

        // Check field name 'department_type' first before field var 'x_department_type'
        $val = $CurrentForm->hasValue("department_type") ? $CurrentForm->getValue("department_type") : $CurrentForm->getValue("x_department_type");
        if (!$this->department_type->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->department_type->Visible = false; // Disable update for API request
            } else {
                $this->department_type->setFormValue($val);
            }
        }

        // Check field name 'hierarchy_level' first before field var 'x_hierarchy_level'
        $val = $CurrentForm->hasValue("hierarchy_level") ? $CurrentForm->getValue("hierarchy_level") : $CurrentForm->getValue("x_hierarchy_level");
        if (!$this->hierarchy_level->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->hierarchy_level->Visible = false; // Disable update for API request
            } else {
                $this->hierarchy_level->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'is_budget_unit' first before field var 'x_is_budget_unit'
        $val = $CurrentForm->hasValue("is_budget_unit") ? $CurrentForm->getValue("is_budget_unit") : $CurrentForm->getValue("x_is_budget_unit");
        if (!$this->is_budget_unit->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->is_budget_unit->Visible = false; // Disable update for API request
            } else {
                $this->is_budget_unit->setFormValue($val);
            }
        }
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->department_id->CurrentValue = $this->department_id->FormValue;
        $this->department_name->CurrentValue = $this->department_name->FormValue;
        $this->description->CurrentValue = $this->description->FormValue;
        $this->parent_department_id->CurrentValue = $this->parent_department_id->FormValue;
        $this->department_head_id->CurrentValue = $this->department_head_id->FormValue;
        $this->department_code->CurrentValue = $this->department_code->FormValue;
        $this->department_acronym->CurrentValue = $this->department_acronym->FormValue;
        $this->office_location->CurrentValue = $this->office_location->FormValue;
        $this->contact_number->CurrentValue = $this->contact_number->FormValue;
        $this->_email->CurrentValue = $this->_email->FormValue;
        $this->is_active->CurrentValue = $this->is_active->FormValue;
        $this->created_at->CurrentValue = $this->created_at->FormValue;
        $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        $this->updated_at->CurrentValue = $this->updated_at->FormValue;
        $this->updated_at->CurrentValue = UnFormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern());
        $this->rc_id->CurrentValue = $this->rc_id->FormValue;
        $this->office_id->CurrentValue = $this->office_id->FormValue;
        $this->department_type->CurrentValue = $this->department_type->FormValue;
        $this->hierarchy_level->CurrentValue = $this->hierarchy_level->FormValue;
        $this->is_budget_unit->CurrentValue = $this->is_budget_unit->FormValue;
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
        $this->department_id->setDbValue($row['department_id']);
        $this->department_name->setDbValue($row['department_name']);
        $this->description->setDbValue($row['description']);
        $this->parent_department_id->setDbValue($row['parent_department_id']);
        $this->department_head_id->setDbValue($row['department_head_id']);
        $this->department_code->setDbValue($row['department_code']);
        $this->department_acronym->setDbValue($row['department_acronym']);
        $this->office_location->setDbValue($row['office_location']);
        $this->contact_number->setDbValue($row['contact_number']);
        $this->_email->setDbValue($row['email']);
        $this->is_active->setDbValue((ConvertToBool($row['is_active']) ? "1" : "0"));
        $this->created_at->setDbValue($row['created_at']);
        $this->updated_at->setDbValue($row['updated_at']);
        $this->rc_id->setDbValue($row['rc_id']);
        $this->office_id->setDbValue($row['office_id']);
        $this->department_type->setDbValue($row['department_type']);
        $this->hierarchy_level->setDbValue($row['hierarchy_level']);
        $this->is_budget_unit->setDbValue((ConvertToBool($row['is_budget_unit']) ? "1" : "0"));
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['department_id'] = $this->department_id->DefaultValue;
        $row['department_name'] = $this->department_name->DefaultValue;
        $row['description'] = $this->description->DefaultValue;
        $row['parent_department_id'] = $this->parent_department_id->DefaultValue;
        $row['department_head_id'] = $this->department_head_id->DefaultValue;
        $row['department_code'] = $this->department_code->DefaultValue;
        $row['department_acronym'] = $this->department_acronym->DefaultValue;
        $row['office_location'] = $this->office_location->DefaultValue;
        $row['contact_number'] = $this->contact_number->DefaultValue;
        $row['email'] = $this->_email->DefaultValue;
        $row['is_active'] = $this->is_active->DefaultValue;
        $row['created_at'] = $this->created_at->DefaultValue;
        $row['updated_at'] = $this->updated_at->DefaultValue;
        $row['rc_id'] = $this->rc_id->DefaultValue;
        $row['office_id'] = $this->office_id->DefaultValue;
        $row['department_type'] = $this->department_type->DefaultValue;
        $row['hierarchy_level'] = $this->hierarchy_level->DefaultValue;
        $row['is_budget_unit'] = $this->is_budget_unit->DefaultValue;
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

        // department_id
        $this->department_id->RowCssClass = "row";

        // department_name
        $this->department_name->RowCssClass = "row";

        // description
        $this->description->RowCssClass = "row";

        // parent_department_id
        $this->parent_department_id->RowCssClass = "row";

        // department_head_id
        $this->department_head_id->RowCssClass = "row";

        // department_code
        $this->department_code->RowCssClass = "row";

        // department_acronym
        $this->department_acronym->RowCssClass = "row";

        // office_location
        $this->office_location->RowCssClass = "row";

        // contact_number
        $this->contact_number->RowCssClass = "row";

        // email
        $this->_email->RowCssClass = "row";

        // is_active
        $this->is_active->RowCssClass = "row";

        // created_at
        $this->created_at->RowCssClass = "row";

        // updated_at
        $this->updated_at->RowCssClass = "row";

        // rc_id
        $this->rc_id->RowCssClass = "row";

        // office_id
        $this->office_id->RowCssClass = "row";

        // department_type
        $this->department_type->RowCssClass = "row";

        // hierarchy_level
        $this->hierarchy_level->RowCssClass = "row";

        // is_budget_unit
        $this->is_budget_unit->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // department_id
            $this->department_id->ViewValue = $this->department_id->CurrentValue;

            // department_name
            $this->department_name->ViewValue = $this->department_name->CurrentValue;

            // description
            $this->description->ViewValue = $this->description->CurrentValue;

            // parent_department_id
            $curVal = strval($this->parent_department_id->CurrentValue);
            if ($curVal != "") {
                $this->parent_department_id->ViewValue = $this->parent_department_id->lookupCacheOption($curVal);
                if ($this->parent_department_id->ViewValue === null) { // Lookup from database
                    $filterWrk = SearchFilter($this->parent_department_id->Lookup->getTable()->Fields["department_id"]->searchExpression(), "=", $curVal, $this->parent_department_id->Lookup->getTable()->Fields["department_id"]->searchDataType(), "");
                    $lookupFilter = $this->parent_department_id->getSelectFilter($this); // PHP
                    $sqlWrk = $this->parent_department_id->Lookup->getSql(false, $filterWrk, $lookupFilter, $this, true, true);
                    $conn = Conn();
                    $config = $conn->getConfiguration();
                    $config->setResultCache($this->Cache);
                    $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                    $ari = count($rswrk);
                    if ($ari > 0) { // Lookup values found
                        $arwrk = $this->parent_department_id->Lookup->renderViewRow($rswrk[0]);
                        $this->parent_department_id->ViewValue = $this->parent_department_id->displayValue($arwrk);
                    } else {
                        $this->parent_department_id->ViewValue = FormatNumber($this->parent_department_id->CurrentValue, $this->parent_department_id->formatPattern());
                    }
                }
            } else {
                $this->parent_department_id->ViewValue = null;
            }

            // department_head_id
            $this->department_head_id->ViewValue = $this->department_head_id->CurrentValue;
            $this->department_head_id->ViewValue = FormatNumber($this->department_head_id->ViewValue, $this->department_head_id->formatPattern());

            // department_code
            $this->department_code->ViewValue = $this->department_code->CurrentValue;

            // department_acronym
            $this->department_acronym->ViewValue = $this->department_acronym->CurrentValue;

            // office_location
            $this->office_location->ViewValue = $this->office_location->CurrentValue;

            // contact_number
            $this->contact_number->ViewValue = $this->contact_number->CurrentValue;

            // email
            $this->_email->ViewValue = $this->_email->CurrentValue;

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

            // rc_id
            $this->rc_id->ViewValue = $this->rc_id->CurrentValue;
            $this->rc_id->ViewValue = FormatNumber($this->rc_id->ViewValue, $this->rc_id->formatPattern());

            // office_id
            $this->office_id->ViewValue = $this->office_id->CurrentValue;
            $this->office_id->ViewValue = FormatNumber($this->office_id->ViewValue, $this->office_id->formatPattern());

            // department_type
            $this->department_type->ViewValue = $this->department_type->CurrentValue;

            // hierarchy_level
            $this->hierarchy_level->ViewValue = $this->hierarchy_level->CurrentValue;
            $this->hierarchy_level->ViewValue = FormatNumber($this->hierarchy_level->ViewValue, $this->hierarchy_level->formatPattern());

            // is_budget_unit
            if (ConvertToBool($this->is_budget_unit->CurrentValue)) {
                $this->is_budget_unit->ViewValue = $this->is_budget_unit->tagCaption(1) != "" ? $this->is_budget_unit->tagCaption(1) : "Yes";
            } else {
                $this->is_budget_unit->ViewValue = $this->is_budget_unit->tagCaption(2) != "" ? $this->is_budget_unit->tagCaption(2) : "No";
            }

            // department_id
            $this->department_id->HrefValue = "";

            // department_name
            $this->department_name->HrefValue = "";

            // description
            $this->description->HrefValue = "";

            // parent_department_id
            $this->parent_department_id->HrefValue = "";

            // department_head_id
            $this->department_head_id->HrefValue = "";

            // department_code
            $this->department_code->HrefValue = "";

            // department_acronym
            $this->department_acronym->HrefValue = "";

            // office_location
            $this->office_location->HrefValue = "";

            // contact_number
            $this->contact_number->HrefValue = "";

            // email
            $this->_email->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // updated_at
            $this->updated_at->HrefValue = "";

            // rc_id
            $this->rc_id->HrefValue = "";

            // office_id
            $this->office_id->HrefValue = "";

            // department_type
            $this->department_type->HrefValue = "";

            // hierarchy_level
            $this->hierarchy_level->HrefValue = "";

            // is_budget_unit
            $this->is_budget_unit->HrefValue = "";
        } elseif ($this->RowType == RowType::EDIT) {
            // department_id
            $this->department_id->setupEditAttributes();
            $this->department_id->EditValue = $this->department_id->CurrentValue;

            // department_name
            $this->department_name->setupEditAttributes();
            if (!$this->department_name->Raw) {
                $this->department_name->CurrentValue = HtmlDecode($this->department_name->CurrentValue);
            }
            $this->department_name->EditValue = HtmlEncode($this->department_name->CurrentValue);
            $this->department_name->PlaceHolder = RemoveHtml($this->department_name->caption());

            // description
            $this->description->setupEditAttributes();
            $this->description->EditValue = HtmlEncode($this->description->CurrentValue);
            $this->description->PlaceHolder = RemoveHtml($this->description->caption());

            // parent_department_id
            $this->parent_department_id->setupEditAttributes();
            $curVal = trim(strval($this->parent_department_id->CurrentValue));
            if ($curVal != "") {
                $this->parent_department_id->ViewValue = $this->parent_department_id->lookupCacheOption($curVal);
            } else {
                $this->parent_department_id->ViewValue = $this->parent_department_id->Lookup !== null && is_array($this->parent_department_id->lookupOptions()) && count($this->parent_department_id->lookupOptions()) > 0 ? $curVal : null;
            }
            if ($this->parent_department_id->ViewValue !== null) { // Load from cache
                $this->parent_department_id->EditValue = array_values($this->parent_department_id->lookupOptions());
            } else { // Lookup from database
                if ($curVal == "") {
                    $filterWrk = "0=1";
                } else {
                    $filterWrk = SearchFilter($this->parent_department_id->Lookup->getTable()->Fields["department_id"]->searchExpression(), "=", $this->parent_department_id->CurrentValue, $this->parent_department_id->Lookup->getTable()->Fields["department_id"]->searchDataType(), "");
                }
                $lookupFilter = $this->parent_department_id->getSelectFilter($this); // PHP
                $sqlWrk = $this->parent_department_id->Lookup->getSql(true, $filterWrk, $lookupFilter, $this, false, true);
                $conn = Conn();
                $config = $conn->getConfiguration();
                $config->setResultCache($this->Cache);
                $rswrk = $conn->executeCacheQuery($sqlWrk, [], [], $this->CacheProfile)->fetchAll();
                $ari = count($rswrk);
                $arwrk = $rswrk;
                $this->parent_department_id->EditValue = $arwrk;
            }
            $this->parent_department_id->PlaceHolder = RemoveHtml($this->parent_department_id->caption());

            // department_head_id
            $this->department_head_id->setupEditAttributes();
            $this->department_head_id->EditValue = $this->department_head_id->CurrentValue;
            $this->department_head_id->PlaceHolder = RemoveHtml($this->department_head_id->caption());
            if (strval($this->department_head_id->EditValue) != "" && is_numeric($this->department_head_id->EditValue)) {
                $this->department_head_id->EditValue = FormatNumber($this->department_head_id->EditValue, null);
            }

            // department_code
            $this->department_code->setupEditAttributes();
            if (!$this->department_code->Raw) {
                $this->department_code->CurrentValue = HtmlDecode($this->department_code->CurrentValue);
            }
            $this->department_code->EditValue = HtmlEncode($this->department_code->CurrentValue);
            $this->department_code->PlaceHolder = RemoveHtml($this->department_code->caption());

            // department_acronym
            $this->department_acronym->setupEditAttributes();
            if (!$this->department_acronym->Raw) {
                $this->department_acronym->CurrentValue = HtmlDecode($this->department_acronym->CurrentValue);
            }
            $this->department_acronym->EditValue = HtmlEncode($this->department_acronym->CurrentValue);
            $this->department_acronym->PlaceHolder = RemoveHtml($this->department_acronym->caption());

            // office_location
            $this->office_location->setupEditAttributes();
            if (!$this->office_location->Raw) {
                $this->office_location->CurrentValue = HtmlDecode($this->office_location->CurrentValue);
            }
            $this->office_location->EditValue = HtmlEncode($this->office_location->CurrentValue);
            $this->office_location->PlaceHolder = RemoveHtml($this->office_location->caption());

            // contact_number
            $this->contact_number->setupEditAttributes();
            if (!$this->contact_number->Raw) {
                $this->contact_number->CurrentValue = HtmlDecode($this->contact_number->CurrentValue);
            }
            $this->contact_number->EditValue = HtmlEncode($this->contact_number->CurrentValue);
            $this->contact_number->PlaceHolder = RemoveHtml($this->contact_number->caption());

            // email
            $this->_email->setupEditAttributes();
            if (!$this->_email->Raw) {
                $this->_email->CurrentValue = HtmlDecode($this->_email->CurrentValue);
            }
            $this->_email->EditValue = HtmlEncode($this->_email->CurrentValue);
            $this->_email->PlaceHolder = RemoveHtml($this->_email->caption());

            // is_active
            $this->is_active->EditValue = $this->is_active->options(false);
            $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

            // created_at
            $this->created_at->setupEditAttributes();
            $this->created_at->EditValue = HtmlEncode(FormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()));
            $this->created_at->PlaceHolder = RemoveHtml($this->created_at->caption());

            // updated_at
            $this->updated_at->setupEditAttributes();
            $this->updated_at->EditValue = HtmlEncode(FormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern()));
            $this->updated_at->PlaceHolder = RemoveHtml($this->updated_at->caption());

            // rc_id
            $this->rc_id->setupEditAttributes();
            $this->rc_id->EditValue = $this->rc_id->CurrentValue;
            $this->rc_id->PlaceHolder = RemoveHtml($this->rc_id->caption());
            if (strval($this->rc_id->EditValue) != "" && is_numeric($this->rc_id->EditValue)) {
                $this->rc_id->EditValue = FormatNumber($this->rc_id->EditValue, null);
            }

            // office_id
            $this->office_id->setupEditAttributes();
            $this->office_id->EditValue = $this->office_id->CurrentValue;
            $this->office_id->PlaceHolder = RemoveHtml($this->office_id->caption());
            if (strval($this->office_id->EditValue) != "" && is_numeric($this->office_id->EditValue)) {
                $this->office_id->EditValue = FormatNumber($this->office_id->EditValue, null);
            }

            // department_type
            $this->department_type->setupEditAttributes();
            if (!$this->department_type->Raw) {
                $this->department_type->CurrentValue = HtmlDecode($this->department_type->CurrentValue);
            }
            $this->department_type->EditValue = HtmlEncode($this->department_type->CurrentValue);
            $this->department_type->PlaceHolder = RemoveHtml($this->department_type->caption());

            // hierarchy_level
            $this->hierarchy_level->setupEditAttributes();
            $this->hierarchy_level->EditValue = $this->hierarchy_level->CurrentValue;
            $this->hierarchy_level->PlaceHolder = RemoveHtml($this->hierarchy_level->caption());
            if (strval($this->hierarchy_level->EditValue) != "" && is_numeric($this->hierarchy_level->EditValue)) {
                $this->hierarchy_level->EditValue = FormatNumber($this->hierarchy_level->EditValue, null);
            }

            // is_budget_unit
            $this->is_budget_unit->EditValue = $this->is_budget_unit->options(false);
            $this->is_budget_unit->PlaceHolder = RemoveHtml($this->is_budget_unit->caption());

            // Edit refer script

            // department_id
            $this->department_id->HrefValue = "";

            // department_name
            $this->department_name->HrefValue = "";

            // description
            $this->description->HrefValue = "";

            // parent_department_id
            $this->parent_department_id->HrefValue = "";

            // department_head_id
            $this->department_head_id->HrefValue = "";

            // department_code
            $this->department_code->HrefValue = "";

            // department_acronym
            $this->department_acronym->HrefValue = "";

            // office_location
            $this->office_location->HrefValue = "";

            // contact_number
            $this->contact_number->HrefValue = "";

            // email
            $this->_email->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // updated_at
            $this->updated_at->HrefValue = "";

            // rc_id
            $this->rc_id->HrefValue = "";

            // office_id
            $this->office_id->HrefValue = "";

            // department_type
            $this->department_type->HrefValue = "";

            // hierarchy_level
            $this->hierarchy_level->HrefValue = "";

            // is_budget_unit
            $this->is_budget_unit->HrefValue = "";
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
            if ($this->department_id->Visible && $this->department_id->Required) {
                if (!$this->department_id->IsDetailKey && EmptyValue($this->department_id->FormValue)) {
                    $this->department_id->addErrorMessage(str_replace("%s", $this->department_id->caption(), $this->department_id->RequiredErrorMessage));
                }
            }
            if ($this->department_name->Visible && $this->department_name->Required) {
                if (!$this->department_name->IsDetailKey && EmptyValue($this->department_name->FormValue)) {
                    $this->department_name->addErrorMessage(str_replace("%s", $this->department_name->caption(), $this->department_name->RequiredErrorMessage));
                }
            }
            if ($this->description->Visible && $this->description->Required) {
                if (!$this->description->IsDetailKey && EmptyValue($this->description->FormValue)) {
                    $this->description->addErrorMessage(str_replace("%s", $this->description->caption(), $this->description->RequiredErrorMessage));
                }
            }
            if ($this->parent_department_id->Visible && $this->parent_department_id->Required) {
                if (!$this->parent_department_id->IsDetailKey && EmptyValue($this->parent_department_id->FormValue)) {
                    $this->parent_department_id->addErrorMessage(str_replace("%s", $this->parent_department_id->caption(), $this->parent_department_id->RequiredErrorMessage));
                }
            }
            if ($this->department_head_id->Visible && $this->department_head_id->Required) {
                if (!$this->department_head_id->IsDetailKey && EmptyValue($this->department_head_id->FormValue)) {
                    $this->department_head_id->addErrorMessage(str_replace("%s", $this->department_head_id->caption(), $this->department_head_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->department_head_id->FormValue)) {
                $this->department_head_id->addErrorMessage($this->department_head_id->getErrorMessage(false));
            }
            if ($this->department_code->Visible && $this->department_code->Required) {
                if (!$this->department_code->IsDetailKey && EmptyValue($this->department_code->FormValue)) {
                    $this->department_code->addErrorMessage(str_replace("%s", $this->department_code->caption(), $this->department_code->RequiredErrorMessage));
                }
            }
            if ($this->department_acronym->Visible && $this->department_acronym->Required) {
                if (!$this->department_acronym->IsDetailKey && EmptyValue($this->department_acronym->FormValue)) {
                    $this->department_acronym->addErrorMessage(str_replace("%s", $this->department_acronym->caption(), $this->department_acronym->RequiredErrorMessage));
                }
            }
            if ($this->office_location->Visible && $this->office_location->Required) {
                if (!$this->office_location->IsDetailKey && EmptyValue($this->office_location->FormValue)) {
                    $this->office_location->addErrorMessage(str_replace("%s", $this->office_location->caption(), $this->office_location->RequiredErrorMessage));
                }
            }
            if ($this->contact_number->Visible && $this->contact_number->Required) {
                if (!$this->contact_number->IsDetailKey && EmptyValue($this->contact_number->FormValue)) {
                    $this->contact_number->addErrorMessage(str_replace("%s", $this->contact_number->caption(), $this->contact_number->RequiredErrorMessage));
                }
            }
            if ($this->_email->Visible && $this->_email->Required) {
                if (!$this->_email->IsDetailKey && EmptyValue($this->_email->FormValue)) {
                    $this->_email->addErrorMessage(str_replace("%s", $this->_email->caption(), $this->_email->RequiredErrorMessage));
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
            if ($this->updated_at->Visible && $this->updated_at->Required) {
                if (!$this->updated_at->IsDetailKey && EmptyValue($this->updated_at->FormValue)) {
                    $this->updated_at->addErrorMessage(str_replace("%s", $this->updated_at->caption(), $this->updated_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->updated_at->FormValue, $this->updated_at->formatPattern())) {
                $this->updated_at->addErrorMessage($this->updated_at->getErrorMessage(false));
            }
            if ($this->rc_id->Visible && $this->rc_id->Required) {
                if (!$this->rc_id->IsDetailKey && EmptyValue($this->rc_id->FormValue)) {
                    $this->rc_id->addErrorMessage(str_replace("%s", $this->rc_id->caption(), $this->rc_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->rc_id->FormValue)) {
                $this->rc_id->addErrorMessage($this->rc_id->getErrorMessage(false));
            }
            if ($this->office_id->Visible && $this->office_id->Required) {
                if (!$this->office_id->IsDetailKey && EmptyValue($this->office_id->FormValue)) {
                    $this->office_id->addErrorMessage(str_replace("%s", $this->office_id->caption(), $this->office_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->office_id->FormValue)) {
                $this->office_id->addErrorMessage($this->office_id->getErrorMessage(false));
            }
            if ($this->department_type->Visible && $this->department_type->Required) {
                if (!$this->department_type->IsDetailKey && EmptyValue($this->department_type->FormValue)) {
                    $this->department_type->addErrorMessage(str_replace("%s", $this->department_type->caption(), $this->department_type->RequiredErrorMessage));
                }
            }
            if ($this->hierarchy_level->Visible && $this->hierarchy_level->Required) {
                if (!$this->hierarchy_level->IsDetailKey && EmptyValue($this->hierarchy_level->FormValue)) {
                    $this->hierarchy_level->addErrorMessage(str_replace("%s", $this->hierarchy_level->caption(), $this->hierarchy_level->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->hierarchy_level->FormValue)) {
                $this->hierarchy_level->addErrorMessage($this->hierarchy_level->getErrorMessage(false));
            }
            if ($this->is_budget_unit->Visible && $this->is_budget_unit->Required) {
                if ($this->is_budget_unit->FormValue == "") {
                    $this->is_budget_unit->addErrorMessage(str_replace("%s", $this->is_budget_unit->caption(), $this->is_budget_unit->RequiredErrorMessage));
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

        // department_name
        $this->department_name->setDbValueDef($rsnew, $this->department_name->CurrentValue, $this->department_name->ReadOnly);

        // description
        $this->description->setDbValueDef($rsnew, $this->description->CurrentValue, $this->description->ReadOnly);

        // parent_department_id
        $this->parent_department_id->setDbValueDef($rsnew, $this->parent_department_id->CurrentValue, $this->parent_department_id->ReadOnly);

        // department_head_id
        $this->department_head_id->setDbValueDef($rsnew, $this->department_head_id->CurrentValue, $this->department_head_id->ReadOnly);

        // department_code
        $this->department_code->setDbValueDef($rsnew, $this->department_code->CurrentValue, $this->department_code->ReadOnly);

        // department_acronym
        $this->department_acronym->setDbValueDef($rsnew, $this->department_acronym->CurrentValue, $this->department_acronym->ReadOnly);

        // office_location
        $this->office_location->setDbValueDef($rsnew, $this->office_location->CurrentValue, $this->office_location->ReadOnly);

        // contact_number
        $this->contact_number->setDbValueDef($rsnew, $this->contact_number->CurrentValue, $this->contact_number->ReadOnly);

        // email
        $this->_email->setDbValueDef($rsnew, $this->_email->CurrentValue, $this->_email->ReadOnly);

        // is_active
        $tmpBool = $this->is_active->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_active->setDbValueDef($rsnew, $tmpBool, $this->is_active->ReadOnly);

        // created_at
        $this->created_at->setDbValueDef($rsnew, UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()), $this->created_at->ReadOnly);

        // updated_at
        $this->updated_at->setDbValueDef($rsnew, UnFormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern()), $this->updated_at->ReadOnly);

        // rc_id
        $this->rc_id->setDbValueDef($rsnew, $this->rc_id->CurrentValue, $this->rc_id->ReadOnly);

        // office_id
        $this->office_id->setDbValueDef($rsnew, $this->office_id->CurrentValue, $this->office_id->ReadOnly);

        // department_type
        $this->department_type->setDbValueDef($rsnew, $this->department_type->CurrentValue, $this->department_type->ReadOnly);

        // hierarchy_level
        $this->hierarchy_level->setDbValueDef($rsnew, $this->hierarchy_level->CurrentValue, $this->hierarchy_level->ReadOnly);

        // is_budget_unit
        $tmpBool = $this->is_budget_unit->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_budget_unit->setDbValueDef($rsnew, $tmpBool, $this->is_budget_unit->ReadOnly);
        return $rsnew;
    }

    /**
     * Restore edit form from row
     * @param array $row Row
     */
    protected function restoreEditFormFromRow($row)
    {
        if (isset($row['department_name'])) { // department_name
            $this->department_name->CurrentValue = $row['department_name'];
        }
        if (isset($row['description'])) { // description
            $this->description->CurrentValue = $row['description'];
        }
        if (isset($row['parent_department_id'])) { // parent_department_id
            $this->parent_department_id->CurrentValue = $row['parent_department_id'];
        }
        if (isset($row['department_head_id'])) { // department_head_id
            $this->department_head_id->CurrentValue = $row['department_head_id'];
        }
        if (isset($row['department_code'])) { // department_code
            $this->department_code->CurrentValue = $row['department_code'];
        }
        if (isset($row['department_acronym'])) { // department_acronym
            $this->department_acronym->CurrentValue = $row['department_acronym'];
        }
        if (isset($row['office_location'])) { // office_location
            $this->office_location->CurrentValue = $row['office_location'];
        }
        if (isset($row['contact_number'])) { // contact_number
            $this->contact_number->CurrentValue = $row['contact_number'];
        }
        if (isset($row['email'])) { // email
            $this->_email->CurrentValue = $row['email'];
        }
        if (isset($row['is_active'])) { // is_active
            $this->is_active->CurrentValue = $row['is_active'];
        }
        if (isset($row['created_at'])) { // created_at
            $this->created_at->CurrentValue = $row['created_at'];
        }
        if (isset($row['updated_at'])) { // updated_at
            $this->updated_at->CurrentValue = $row['updated_at'];
        }
        if (isset($row['rc_id'])) { // rc_id
            $this->rc_id->CurrentValue = $row['rc_id'];
        }
        if (isset($row['office_id'])) { // office_id
            $this->office_id->CurrentValue = $row['office_id'];
        }
        if (isset($row['department_type'])) { // department_type
            $this->department_type->CurrentValue = $row['department_type'];
        }
        if (isset($row['hierarchy_level'])) { // hierarchy_level
            $this->hierarchy_level->CurrentValue = $row['hierarchy_level'];
        }
        if (isset($row['is_budget_unit'])) { // is_budget_unit
            $this->is_budget_unit->CurrentValue = $row['is_budget_unit'];
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("DepartmentsList"), "", $this->TableVar, true);
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
                case "x_parent_department_id":
                    $lookupFilter = $fld->getSelectFilter(); // PHP
                    break;
                case "x_is_active":
                    break;
                case "x_is_budget_unit":
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
