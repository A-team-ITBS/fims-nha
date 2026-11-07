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
class PlantillaItemsEdit extends PlantillaItems
{
    use MessagesTrait;

    // Page ID
    public $PageID = "edit";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "PlantillaItemsEdit";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "PlantillaItemsEdit";

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
        $this->plantilla_item_id->setVisibility();
        $this->department_id->setVisibility();
        $this->position_id->setVisibility();
        $this->item_number->setVisibility();
        $this->item_description->setVisibility();
        $this->authorized_count->setVisibility();
        $this->is_active->setVisibility();
        $this->effective_date->setVisibility();
        $this->abolition_date->setVisibility();
        $this->annual_budget_allocation->setVisibility();
        $this->monthly_salary_allocation->setVisibility();
        $this->remarks->setVisibility();
        $this->created_by->setVisibility();
        $this->approved_by->setVisibility();
        $this->created_at->setVisibility();
        $this->updated_at->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'plantilla_items';
        $this->TableName = 'plantilla_items';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-edit-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (plantilla_items)
        if (!isset($GLOBALS["plantilla_items"]) || $GLOBALS["plantilla_items"]::class == PROJECT_NAMESPACE . "plantilla_items") {
            $GLOBALS["plantilla_items"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'plantilla_items');
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
                        $result["view"] = SameString($pageName, "PlantillaItemsView"); // If View page, no primary button
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
            $key .= @$ar['plantilla_item_id'];
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
            $this->plantilla_item_id->Visible = false;
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
            if (($keyValue = Get("plantilla_item_id") ?? Key(0) ?? Route(2)) !== null) {
                $this->plantilla_item_id->setQueryStringValue($keyValue);
                $this->plantilla_item_id->setOldValue($this->plantilla_item_id->QueryStringValue);
            } elseif (Post("plantilla_item_id") !== null) {
                $this->plantilla_item_id->setFormValue(Post("plantilla_item_id"));
                $this->plantilla_item_id->setOldValue($this->plantilla_item_id->FormValue);
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
                if (($keyValue = Get("plantilla_item_id") ?? Route("plantilla_item_id")) !== null) {
                    $this->plantilla_item_id->setQueryStringValue($keyValue);
                    $loadByQuery = true;
                } else {
                    $this->plantilla_item_id->CurrentValue = null;
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
                        $this->terminate("PlantillaItemsList"); // No matching record, return to list
                        return;
                    }
                break;
            case "update": // Update
                $returnUrl = $this->getReturnUrl();
                if (GetPageName($returnUrl) == "PlantillaItemsList") {
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
                        if (GetPageName($returnUrl) != "PlantillaItemsList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "PlantillaItemsList"; // Return list page content
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

        // Check field name 'plantilla_item_id' first before field var 'x_plantilla_item_id'
        $val = $CurrentForm->hasValue("plantilla_item_id") ? $CurrentForm->getValue("plantilla_item_id") : $CurrentForm->getValue("x_plantilla_item_id");
        if (!$this->plantilla_item_id->IsDetailKey) {
            $this->plantilla_item_id->setFormValue($val);
        }

        // Check field name 'department_id' first before field var 'x_department_id'
        $val = $CurrentForm->hasValue("department_id") ? $CurrentForm->getValue("department_id") : $CurrentForm->getValue("x_department_id");
        if (!$this->department_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->department_id->Visible = false; // Disable update for API request
            } else {
                $this->department_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'position_id' first before field var 'x_position_id'
        $val = $CurrentForm->hasValue("position_id") ? $CurrentForm->getValue("position_id") : $CurrentForm->getValue("x_position_id");
        if (!$this->position_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->position_id->Visible = false; // Disable update for API request
            } else {
                $this->position_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'item_number' first before field var 'x_item_number'
        $val = $CurrentForm->hasValue("item_number") ? $CurrentForm->getValue("item_number") : $CurrentForm->getValue("x_item_number");
        if (!$this->item_number->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->item_number->Visible = false; // Disable update for API request
            } else {
                $this->item_number->setFormValue($val);
            }
        }

        // Check field name 'item_description' first before field var 'x_item_description'
        $val = $CurrentForm->hasValue("item_description") ? $CurrentForm->getValue("item_description") : $CurrentForm->getValue("x_item_description");
        if (!$this->item_description->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->item_description->Visible = false; // Disable update for API request
            } else {
                $this->item_description->setFormValue($val);
            }
        }

        // Check field name 'authorized_count' first before field var 'x_authorized_count'
        $val = $CurrentForm->hasValue("authorized_count") ? $CurrentForm->getValue("authorized_count") : $CurrentForm->getValue("x_authorized_count");
        if (!$this->authorized_count->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->authorized_count->Visible = false; // Disable update for API request
            } else {
                $this->authorized_count->setFormValue($val, true, $validate);
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

        // Check field name 'effective_date' first before field var 'x_effective_date'
        $val = $CurrentForm->hasValue("effective_date") ? $CurrentForm->getValue("effective_date") : $CurrentForm->getValue("x_effective_date");
        if (!$this->effective_date->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->effective_date->Visible = false; // Disable update for API request
            } else {
                $this->effective_date->setFormValue($val, true, $validate);
            }
            $this->effective_date->CurrentValue = UnFormatDateTime($this->effective_date->CurrentValue, $this->effective_date->formatPattern());
        }

        // Check field name 'abolition_date' first before field var 'x_abolition_date'
        $val = $CurrentForm->hasValue("abolition_date") ? $CurrentForm->getValue("abolition_date") : $CurrentForm->getValue("x_abolition_date");
        if (!$this->abolition_date->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->abolition_date->Visible = false; // Disable update for API request
            } else {
                $this->abolition_date->setFormValue($val, true, $validate);
            }
            $this->abolition_date->CurrentValue = UnFormatDateTime($this->abolition_date->CurrentValue, $this->abolition_date->formatPattern());
        }

        // Check field name 'annual_budget_allocation' first before field var 'x_annual_budget_allocation'
        $val = $CurrentForm->hasValue("annual_budget_allocation") ? $CurrentForm->getValue("annual_budget_allocation") : $CurrentForm->getValue("x_annual_budget_allocation");
        if (!$this->annual_budget_allocation->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->annual_budget_allocation->Visible = false; // Disable update for API request
            } else {
                $this->annual_budget_allocation->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'monthly_salary_allocation' first before field var 'x_monthly_salary_allocation'
        $val = $CurrentForm->hasValue("monthly_salary_allocation") ? $CurrentForm->getValue("monthly_salary_allocation") : $CurrentForm->getValue("x_monthly_salary_allocation");
        if (!$this->monthly_salary_allocation->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->monthly_salary_allocation->Visible = false; // Disable update for API request
            } else {
                $this->monthly_salary_allocation->setFormValue($val, true, $validate);
            }
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

        // Check field name 'created_by' first before field var 'x_created_by'
        $val = $CurrentForm->hasValue("created_by") ? $CurrentForm->getValue("created_by") : $CurrentForm->getValue("x_created_by");
        if (!$this->created_by->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->created_by->Visible = false; // Disable update for API request
            } else {
                $this->created_by->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'approved_by' first before field var 'x_approved_by'
        $val = $CurrentForm->hasValue("approved_by") ? $CurrentForm->getValue("approved_by") : $CurrentForm->getValue("x_approved_by");
        if (!$this->approved_by->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->approved_by->Visible = false; // Disable update for API request
            } else {
                $this->approved_by->setFormValue($val, true, $validate);
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
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->plantilla_item_id->CurrentValue = $this->plantilla_item_id->FormValue;
        $this->department_id->CurrentValue = $this->department_id->FormValue;
        $this->position_id->CurrentValue = $this->position_id->FormValue;
        $this->item_number->CurrentValue = $this->item_number->FormValue;
        $this->item_description->CurrentValue = $this->item_description->FormValue;
        $this->authorized_count->CurrentValue = $this->authorized_count->FormValue;
        $this->is_active->CurrentValue = $this->is_active->FormValue;
        $this->effective_date->CurrentValue = $this->effective_date->FormValue;
        $this->effective_date->CurrentValue = UnFormatDateTime($this->effective_date->CurrentValue, $this->effective_date->formatPattern());
        $this->abolition_date->CurrentValue = $this->abolition_date->FormValue;
        $this->abolition_date->CurrentValue = UnFormatDateTime($this->abolition_date->CurrentValue, $this->abolition_date->formatPattern());
        $this->annual_budget_allocation->CurrentValue = $this->annual_budget_allocation->FormValue;
        $this->monthly_salary_allocation->CurrentValue = $this->monthly_salary_allocation->FormValue;
        $this->remarks->CurrentValue = $this->remarks->FormValue;
        $this->created_by->CurrentValue = $this->created_by->FormValue;
        $this->approved_by->CurrentValue = $this->approved_by->FormValue;
        $this->created_at->CurrentValue = $this->created_at->FormValue;
        $this->created_at->CurrentValue = UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern());
        $this->updated_at->CurrentValue = $this->updated_at->FormValue;
        $this->updated_at->CurrentValue = UnFormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern());
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
        $this->plantilla_item_id->setDbValue($row['plantilla_item_id']);
        $this->department_id->setDbValue($row['department_id']);
        $this->position_id->setDbValue($row['position_id']);
        $this->item_number->setDbValue($row['item_number']);
        $this->item_description->setDbValue($row['item_description']);
        $this->authorized_count->setDbValue($row['authorized_count']);
        $this->is_active->setDbValue((ConvertToBool($row['is_active']) ? "1" : "0"));
        $this->effective_date->setDbValue($row['effective_date']);
        $this->abolition_date->setDbValue($row['abolition_date']);
        $this->annual_budget_allocation->setDbValue($row['annual_budget_allocation']);
        $this->monthly_salary_allocation->setDbValue($row['monthly_salary_allocation']);
        $this->remarks->setDbValue($row['remarks']);
        $this->created_by->setDbValue($row['created_by']);
        $this->approved_by->setDbValue($row['approved_by']);
        $this->created_at->setDbValue($row['created_at']);
        $this->updated_at->setDbValue($row['updated_at']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['plantilla_item_id'] = $this->plantilla_item_id->DefaultValue;
        $row['department_id'] = $this->department_id->DefaultValue;
        $row['position_id'] = $this->position_id->DefaultValue;
        $row['item_number'] = $this->item_number->DefaultValue;
        $row['item_description'] = $this->item_description->DefaultValue;
        $row['authorized_count'] = $this->authorized_count->DefaultValue;
        $row['is_active'] = $this->is_active->DefaultValue;
        $row['effective_date'] = $this->effective_date->DefaultValue;
        $row['abolition_date'] = $this->abolition_date->DefaultValue;
        $row['annual_budget_allocation'] = $this->annual_budget_allocation->DefaultValue;
        $row['monthly_salary_allocation'] = $this->monthly_salary_allocation->DefaultValue;
        $row['remarks'] = $this->remarks->DefaultValue;
        $row['created_by'] = $this->created_by->DefaultValue;
        $row['approved_by'] = $this->approved_by->DefaultValue;
        $row['created_at'] = $this->created_at->DefaultValue;
        $row['updated_at'] = $this->updated_at->DefaultValue;
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

        // plantilla_item_id
        $this->plantilla_item_id->RowCssClass = "row";

        // department_id
        $this->department_id->RowCssClass = "row";

        // position_id
        $this->position_id->RowCssClass = "row";

        // item_number
        $this->item_number->RowCssClass = "row";

        // item_description
        $this->item_description->RowCssClass = "row";

        // authorized_count
        $this->authorized_count->RowCssClass = "row";

        // is_active
        $this->is_active->RowCssClass = "row";

        // effective_date
        $this->effective_date->RowCssClass = "row";

        // abolition_date
        $this->abolition_date->RowCssClass = "row";

        // annual_budget_allocation
        $this->annual_budget_allocation->RowCssClass = "row";

        // monthly_salary_allocation
        $this->monthly_salary_allocation->RowCssClass = "row";

        // remarks
        $this->remarks->RowCssClass = "row";

        // created_by
        $this->created_by->RowCssClass = "row";

        // approved_by
        $this->approved_by->RowCssClass = "row";

        // created_at
        $this->created_at->RowCssClass = "row";

        // updated_at
        $this->updated_at->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // plantilla_item_id
            $this->plantilla_item_id->ViewValue = $this->plantilla_item_id->CurrentValue;

            // department_id
            $this->department_id->ViewValue = $this->department_id->CurrentValue;
            $this->department_id->ViewValue = FormatNumber($this->department_id->ViewValue, $this->department_id->formatPattern());

            // position_id
            $this->position_id->ViewValue = $this->position_id->CurrentValue;
            $this->position_id->ViewValue = FormatNumber($this->position_id->ViewValue, $this->position_id->formatPattern());

            // item_number
            $this->item_number->ViewValue = $this->item_number->CurrentValue;

            // item_description
            $this->item_description->ViewValue = $this->item_description->CurrentValue;

            // authorized_count
            $this->authorized_count->ViewValue = $this->authorized_count->CurrentValue;
            $this->authorized_count->ViewValue = FormatNumber($this->authorized_count->ViewValue, $this->authorized_count->formatPattern());

            // is_active
            if (ConvertToBool($this->is_active->CurrentValue)) {
                $this->is_active->ViewValue = $this->is_active->tagCaption(1) != "" ? $this->is_active->tagCaption(1) : "Yes";
            } else {
                $this->is_active->ViewValue = $this->is_active->tagCaption(2) != "" ? $this->is_active->tagCaption(2) : "No";
            }

            // effective_date
            $this->effective_date->ViewValue = $this->effective_date->CurrentValue;
            $this->effective_date->ViewValue = FormatDateTime($this->effective_date->ViewValue, $this->effective_date->formatPattern());

            // abolition_date
            $this->abolition_date->ViewValue = $this->abolition_date->CurrentValue;
            $this->abolition_date->ViewValue = FormatDateTime($this->abolition_date->ViewValue, $this->abolition_date->formatPattern());

            // annual_budget_allocation
            $this->annual_budget_allocation->ViewValue = $this->annual_budget_allocation->CurrentValue;
            $this->annual_budget_allocation->ViewValue = FormatNumber($this->annual_budget_allocation->ViewValue, $this->annual_budget_allocation->formatPattern());

            // monthly_salary_allocation
            $this->monthly_salary_allocation->ViewValue = $this->monthly_salary_allocation->CurrentValue;
            $this->monthly_salary_allocation->ViewValue = FormatNumber($this->monthly_salary_allocation->ViewValue, $this->monthly_salary_allocation->formatPattern());

            // remarks
            $this->remarks->ViewValue = $this->remarks->CurrentValue;

            // created_by
            $this->created_by->ViewValue = $this->created_by->CurrentValue;
            $this->created_by->ViewValue = FormatNumber($this->created_by->ViewValue, $this->created_by->formatPattern());

            // approved_by
            $this->approved_by->ViewValue = $this->approved_by->CurrentValue;
            $this->approved_by->ViewValue = FormatNumber($this->approved_by->ViewValue, $this->approved_by->formatPattern());

            // created_at
            $this->created_at->ViewValue = $this->created_at->CurrentValue;
            $this->created_at->ViewValue = FormatDateTime($this->created_at->ViewValue, $this->created_at->formatPattern());

            // updated_at
            $this->updated_at->ViewValue = $this->updated_at->CurrentValue;
            $this->updated_at->ViewValue = FormatDateTime($this->updated_at->ViewValue, $this->updated_at->formatPattern());

            // plantilla_item_id
            $this->plantilla_item_id->HrefValue = "";

            // department_id
            $this->department_id->HrefValue = "";

            // position_id
            $this->position_id->HrefValue = "";

            // item_number
            $this->item_number->HrefValue = "";

            // item_description
            $this->item_description->HrefValue = "";

            // authorized_count
            $this->authorized_count->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // effective_date
            $this->effective_date->HrefValue = "";

            // abolition_date
            $this->abolition_date->HrefValue = "";

            // annual_budget_allocation
            $this->annual_budget_allocation->HrefValue = "";

            // monthly_salary_allocation
            $this->monthly_salary_allocation->HrefValue = "";

            // remarks
            $this->remarks->HrefValue = "";

            // created_by
            $this->created_by->HrefValue = "";

            // approved_by
            $this->approved_by->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // updated_at
            $this->updated_at->HrefValue = "";
        } elseif ($this->RowType == RowType::EDIT) {
            // plantilla_item_id
            $this->plantilla_item_id->setupEditAttributes();
            $this->plantilla_item_id->EditValue = $this->plantilla_item_id->CurrentValue;

            // department_id
            $this->department_id->setupEditAttributes();
            $this->department_id->EditValue = $this->department_id->CurrentValue;
            $this->department_id->PlaceHolder = RemoveHtml($this->department_id->caption());
            if (strval($this->department_id->EditValue) != "" && is_numeric($this->department_id->EditValue)) {
                $this->department_id->EditValue = FormatNumber($this->department_id->EditValue, null);
            }

            // position_id
            $this->position_id->setupEditAttributes();
            $this->position_id->EditValue = $this->position_id->CurrentValue;
            $this->position_id->PlaceHolder = RemoveHtml($this->position_id->caption());
            if (strval($this->position_id->EditValue) != "" && is_numeric($this->position_id->EditValue)) {
                $this->position_id->EditValue = FormatNumber($this->position_id->EditValue, null);
            }

            // item_number
            $this->item_number->setupEditAttributes();
            if (!$this->item_number->Raw) {
                $this->item_number->CurrentValue = HtmlDecode($this->item_number->CurrentValue);
            }
            $this->item_number->EditValue = HtmlEncode($this->item_number->CurrentValue);
            $this->item_number->PlaceHolder = RemoveHtml($this->item_number->caption());

            // item_description
            $this->item_description->setupEditAttributes();
            if (!$this->item_description->Raw) {
                $this->item_description->CurrentValue = HtmlDecode($this->item_description->CurrentValue);
            }
            $this->item_description->EditValue = HtmlEncode($this->item_description->CurrentValue);
            $this->item_description->PlaceHolder = RemoveHtml($this->item_description->caption());

            // authorized_count
            $this->authorized_count->setupEditAttributes();
            $this->authorized_count->EditValue = $this->authorized_count->CurrentValue;
            $this->authorized_count->PlaceHolder = RemoveHtml($this->authorized_count->caption());
            if (strval($this->authorized_count->EditValue) != "" && is_numeric($this->authorized_count->EditValue)) {
                $this->authorized_count->EditValue = FormatNumber($this->authorized_count->EditValue, null);
            }

            // is_active
            $this->is_active->EditValue = $this->is_active->options(false);
            $this->is_active->PlaceHolder = RemoveHtml($this->is_active->caption());

            // effective_date
            $this->effective_date->setupEditAttributes();
            $this->effective_date->EditValue = HtmlEncode(FormatDateTime($this->effective_date->CurrentValue, $this->effective_date->formatPattern()));
            $this->effective_date->PlaceHolder = RemoveHtml($this->effective_date->caption());

            // abolition_date
            $this->abolition_date->setupEditAttributes();
            $this->abolition_date->EditValue = HtmlEncode(FormatDateTime($this->abolition_date->CurrentValue, $this->abolition_date->formatPattern()));
            $this->abolition_date->PlaceHolder = RemoveHtml($this->abolition_date->caption());

            // annual_budget_allocation
            $this->annual_budget_allocation->setupEditAttributes();
            $this->annual_budget_allocation->EditValue = $this->annual_budget_allocation->CurrentValue;
            $this->annual_budget_allocation->PlaceHolder = RemoveHtml($this->annual_budget_allocation->caption());
            if (strval($this->annual_budget_allocation->EditValue) != "" && is_numeric($this->annual_budget_allocation->EditValue)) {
                $this->annual_budget_allocation->EditValue = FormatNumber($this->annual_budget_allocation->EditValue, null);
            }

            // monthly_salary_allocation
            $this->monthly_salary_allocation->setupEditAttributes();
            $this->monthly_salary_allocation->EditValue = $this->monthly_salary_allocation->CurrentValue;
            $this->monthly_salary_allocation->PlaceHolder = RemoveHtml($this->monthly_salary_allocation->caption());
            if (strval($this->monthly_salary_allocation->EditValue) != "" && is_numeric($this->monthly_salary_allocation->EditValue)) {
                $this->monthly_salary_allocation->EditValue = FormatNumber($this->monthly_salary_allocation->EditValue, null);
            }

            // remarks
            $this->remarks->setupEditAttributes();
            $this->remarks->EditValue = HtmlEncode($this->remarks->CurrentValue);
            $this->remarks->PlaceHolder = RemoveHtml($this->remarks->caption());

            // created_by
            $this->created_by->setupEditAttributes();
            $this->created_by->EditValue = $this->created_by->CurrentValue;
            $this->created_by->PlaceHolder = RemoveHtml($this->created_by->caption());
            if (strval($this->created_by->EditValue) != "" && is_numeric($this->created_by->EditValue)) {
                $this->created_by->EditValue = FormatNumber($this->created_by->EditValue, null);
            }

            // approved_by
            $this->approved_by->setupEditAttributes();
            $this->approved_by->EditValue = $this->approved_by->CurrentValue;
            $this->approved_by->PlaceHolder = RemoveHtml($this->approved_by->caption());
            if (strval($this->approved_by->EditValue) != "" && is_numeric($this->approved_by->EditValue)) {
                $this->approved_by->EditValue = FormatNumber($this->approved_by->EditValue, null);
            }

            // created_at
            $this->created_at->setupEditAttributes();
            $this->created_at->EditValue = HtmlEncode(FormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()));
            $this->created_at->PlaceHolder = RemoveHtml($this->created_at->caption());

            // updated_at
            $this->updated_at->setupEditAttributes();
            $this->updated_at->EditValue = HtmlEncode(FormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern()));
            $this->updated_at->PlaceHolder = RemoveHtml($this->updated_at->caption());

            // Edit refer script

            // plantilla_item_id
            $this->plantilla_item_id->HrefValue = "";

            // department_id
            $this->department_id->HrefValue = "";

            // position_id
            $this->position_id->HrefValue = "";

            // item_number
            $this->item_number->HrefValue = "";

            // item_description
            $this->item_description->HrefValue = "";

            // authorized_count
            $this->authorized_count->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // effective_date
            $this->effective_date->HrefValue = "";

            // abolition_date
            $this->abolition_date->HrefValue = "";

            // annual_budget_allocation
            $this->annual_budget_allocation->HrefValue = "";

            // monthly_salary_allocation
            $this->monthly_salary_allocation->HrefValue = "";

            // remarks
            $this->remarks->HrefValue = "";

            // created_by
            $this->created_by->HrefValue = "";

            // approved_by
            $this->approved_by->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // updated_at
            $this->updated_at->HrefValue = "";
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
            if ($this->plantilla_item_id->Visible && $this->plantilla_item_id->Required) {
                if (!$this->plantilla_item_id->IsDetailKey && EmptyValue($this->plantilla_item_id->FormValue)) {
                    $this->plantilla_item_id->addErrorMessage(str_replace("%s", $this->plantilla_item_id->caption(), $this->plantilla_item_id->RequiredErrorMessage));
                }
            }
            if ($this->department_id->Visible && $this->department_id->Required) {
                if (!$this->department_id->IsDetailKey && EmptyValue($this->department_id->FormValue)) {
                    $this->department_id->addErrorMessage(str_replace("%s", $this->department_id->caption(), $this->department_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->department_id->FormValue)) {
                $this->department_id->addErrorMessage($this->department_id->getErrorMessage(false));
            }
            if ($this->position_id->Visible && $this->position_id->Required) {
                if (!$this->position_id->IsDetailKey && EmptyValue($this->position_id->FormValue)) {
                    $this->position_id->addErrorMessage(str_replace("%s", $this->position_id->caption(), $this->position_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->position_id->FormValue)) {
                $this->position_id->addErrorMessage($this->position_id->getErrorMessage(false));
            }
            if ($this->item_number->Visible && $this->item_number->Required) {
                if (!$this->item_number->IsDetailKey && EmptyValue($this->item_number->FormValue)) {
                    $this->item_number->addErrorMessage(str_replace("%s", $this->item_number->caption(), $this->item_number->RequiredErrorMessage));
                }
            }
            if ($this->item_description->Visible && $this->item_description->Required) {
                if (!$this->item_description->IsDetailKey && EmptyValue($this->item_description->FormValue)) {
                    $this->item_description->addErrorMessage(str_replace("%s", $this->item_description->caption(), $this->item_description->RequiredErrorMessage));
                }
            }
            if ($this->authorized_count->Visible && $this->authorized_count->Required) {
                if (!$this->authorized_count->IsDetailKey && EmptyValue($this->authorized_count->FormValue)) {
                    $this->authorized_count->addErrorMessage(str_replace("%s", $this->authorized_count->caption(), $this->authorized_count->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->authorized_count->FormValue)) {
                $this->authorized_count->addErrorMessage($this->authorized_count->getErrorMessage(false));
            }
            if ($this->is_active->Visible && $this->is_active->Required) {
                if ($this->is_active->FormValue == "") {
                    $this->is_active->addErrorMessage(str_replace("%s", $this->is_active->caption(), $this->is_active->RequiredErrorMessage));
                }
            }
            if ($this->effective_date->Visible && $this->effective_date->Required) {
                if (!$this->effective_date->IsDetailKey && EmptyValue($this->effective_date->FormValue)) {
                    $this->effective_date->addErrorMessage(str_replace("%s", $this->effective_date->caption(), $this->effective_date->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->effective_date->FormValue, $this->effective_date->formatPattern())) {
                $this->effective_date->addErrorMessage($this->effective_date->getErrorMessage(false));
            }
            if ($this->abolition_date->Visible && $this->abolition_date->Required) {
                if (!$this->abolition_date->IsDetailKey && EmptyValue($this->abolition_date->FormValue)) {
                    $this->abolition_date->addErrorMessage(str_replace("%s", $this->abolition_date->caption(), $this->abolition_date->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->abolition_date->FormValue, $this->abolition_date->formatPattern())) {
                $this->abolition_date->addErrorMessage($this->abolition_date->getErrorMessage(false));
            }
            if ($this->annual_budget_allocation->Visible && $this->annual_budget_allocation->Required) {
                if (!$this->annual_budget_allocation->IsDetailKey && EmptyValue($this->annual_budget_allocation->FormValue)) {
                    $this->annual_budget_allocation->addErrorMessage(str_replace("%s", $this->annual_budget_allocation->caption(), $this->annual_budget_allocation->RequiredErrorMessage));
                }
            }
            if (!CheckNumber($this->annual_budget_allocation->FormValue)) {
                $this->annual_budget_allocation->addErrorMessage($this->annual_budget_allocation->getErrorMessage(false));
            }
            if ($this->monthly_salary_allocation->Visible && $this->monthly_salary_allocation->Required) {
                if (!$this->monthly_salary_allocation->IsDetailKey && EmptyValue($this->monthly_salary_allocation->FormValue)) {
                    $this->monthly_salary_allocation->addErrorMessage(str_replace("%s", $this->monthly_salary_allocation->caption(), $this->monthly_salary_allocation->RequiredErrorMessage));
                }
            }
            if (!CheckNumber($this->monthly_salary_allocation->FormValue)) {
                $this->monthly_salary_allocation->addErrorMessage($this->monthly_salary_allocation->getErrorMessage(false));
            }
            if ($this->remarks->Visible && $this->remarks->Required) {
                if (!$this->remarks->IsDetailKey && EmptyValue($this->remarks->FormValue)) {
                    $this->remarks->addErrorMessage(str_replace("%s", $this->remarks->caption(), $this->remarks->RequiredErrorMessage));
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
            if ($this->approved_by->Visible && $this->approved_by->Required) {
                if (!$this->approved_by->IsDetailKey && EmptyValue($this->approved_by->FormValue)) {
                    $this->approved_by->addErrorMessage(str_replace("%s", $this->approved_by->caption(), $this->approved_by->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->approved_by->FormValue)) {
                $this->approved_by->addErrorMessage($this->approved_by->getErrorMessage(false));
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

        // department_id
        $this->department_id->setDbValueDef($rsnew, $this->department_id->CurrentValue, $this->department_id->ReadOnly);

        // position_id
        $this->position_id->setDbValueDef($rsnew, $this->position_id->CurrentValue, $this->position_id->ReadOnly);

        // item_number
        $this->item_number->setDbValueDef($rsnew, $this->item_number->CurrentValue, $this->item_number->ReadOnly);

        // item_description
        $this->item_description->setDbValueDef($rsnew, $this->item_description->CurrentValue, $this->item_description->ReadOnly);

        // authorized_count
        $this->authorized_count->setDbValueDef($rsnew, $this->authorized_count->CurrentValue, $this->authorized_count->ReadOnly);

        // is_active
        $tmpBool = $this->is_active->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_active->setDbValueDef($rsnew, $tmpBool, $this->is_active->ReadOnly);

        // effective_date
        $this->effective_date->setDbValueDef($rsnew, UnFormatDateTime($this->effective_date->CurrentValue, $this->effective_date->formatPattern()), $this->effective_date->ReadOnly);

        // abolition_date
        $this->abolition_date->setDbValueDef($rsnew, UnFormatDateTime($this->abolition_date->CurrentValue, $this->abolition_date->formatPattern()), $this->abolition_date->ReadOnly);

        // annual_budget_allocation
        $this->annual_budget_allocation->setDbValueDef($rsnew, $this->annual_budget_allocation->CurrentValue, $this->annual_budget_allocation->ReadOnly);

        // monthly_salary_allocation
        $this->monthly_salary_allocation->setDbValueDef($rsnew, $this->monthly_salary_allocation->CurrentValue, $this->monthly_salary_allocation->ReadOnly);

        // remarks
        $this->remarks->setDbValueDef($rsnew, $this->remarks->CurrentValue, $this->remarks->ReadOnly);

        // created_by
        $this->created_by->setDbValueDef($rsnew, $this->created_by->CurrentValue, $this->created_by->ReadOnly);

        // approved_by
        $this->approved_by->setDbValueDef($rsnew, $this->approved_by->CurrentValue, $this->approved_by->ReadOnly);

        // created_at
        $this->created_at->setDbValueDef($rsnew, UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()), $this->created_at->ReadOnly);

        // updated_at
        $this->updated_at->setDbValueDef($rsnew, UnFormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern()), $this->updated_at->ReadOnly);
        return $rsnew;
    }

    /**
     * Restore edit form from row
     * @param array $row Row
     */
    protected function restoreEditFormFromRow($row)
    {
        if (isset($row['department_id'])) { // department_id
            $this->department_id->CurrentValue = $row['department_id'];
        }
        if (isset($row['position_id'])) { // position_id
            $this->position_id->CurrentValue = $row['position_id'];
        }
        if (isset($row['item_number'])) { // item_number
            $this->item_number->CurrentValue = $row['item_number'];
        }
        if (isset($row['item_description'])) { // item_description
            $this->item_description->CurrentValue = $row['item_description'];
        }
        if (isset($row['authorized_count'])) { // authorized_count
            $this->authorized_count->CurrentValue = $row['authorized_count'];
        }
        if (isset($row['is_active'])) { // is_active
            $this->is_active->CurrentValue = $row['is_active'];
        }
        if (isset($row['effective_date'])) { // effective_date
            $this->effective_date->CurrentValue = $row['effective_date'];
        }
        if (isset($row['abolition_date'])) { // abolition_date
            $this->abolition_date->CurrentValue = $row['abolition_date'];
        }
        if (isset($row['annual_budget_allocation'])) { // annual_budget_allocation
            $this->annual_budget_allocation->CurrentValue = $row['annual_budget_allocation'];
        }
        if (isset($row['monthly_salary_allocation'])) { // monthly_salary_allocation
            $this->monthly_salary_allocation->CurrentValue = $row['monthly_salary_allocation'];
        }
        if (isset($row['remarks'])) { // remarks
            $this->remarks->CurrentValue = $row['remarks'];
        }
        if (isset($row['created_by'])) { // created_by
            $this->created_by->CurrentValue = $row['created_by'];
        }
        if (isset($row['approved_by'])) { // approved_by
            $this->approved_by->CurrentValue = $row['approved_by'];
        }
        if (isset($row['created_at'])) { // created_at
            $this->created_at->CurrentValue = $row['created_at'];
        }
        if (isset($row['updated_at'])) { // updated_at
            $this->updated_at->CurrentValue = $row['updated_at'];
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("PlantillaItemsList"), "", $this->TableVar, true);
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
