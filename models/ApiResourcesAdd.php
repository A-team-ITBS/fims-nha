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
class ApiResourcesAdd extends ApiResources
{
    use MessagesTrait;

    // Page ID
    public $PageID = "add";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "ApiResourcesAdd";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "ApiResourcesAdd";

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
        $this->resource_id->Visible = false;
        $this->system_id->setVisibility();
        $this->resource_key->setVisibility();
        $this->caption->setVisibility();
        $this->http_method->setVisibility();
        $this->path_template->setVisibility();
        $this->mode->setVisibility();
        $this->required_fields->setVisibility();
        $this->field_map->setVisibility();
        $this->sample_payload->setVisibility();
        $this->request_body->setVisibility();
        $this->data_path->setVisibility();
        $this->base_url->setVisibility();
        $this->auth_token->setVisibility();
        $this->is_active->setVisibility();
        $this->created_at->setVisibility();
        $this->updated_at->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'api_resources';
        $this->TableName = 'api_resources';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-add-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (api_resources)
        if (!isset($GLOBALS["api_resources"]) || $GLOBALS["api_resources"]::class == PROJECT_NAMESPACE . "api_resources") {
            $GLOBALS["api_resources"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'api_resources');
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
                        $result["view"] = SameString($pageName, "ApiResourcesView"); // If View page, no primary button
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
            $key .= @$ar['resource_id'];
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
            $this->resource_id->Visible = false;
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
            if (($keyValue = Get("resource_id") ?? Route("resource_id")) !== null) {
                $this->resource_id->setQueryStringValue($keyValue);
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
                    $this->terminate("ApiResourcesList"); // No matching record, return to list
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
                    if (GetPageName($returnUrl) == "ApiResourcesList") {
                        $returnUrl = $this->addMasterUrl($returnUrl); // List page, return to List page with correct master key if necessary
                    } elseif (GetPageName($returnUrl) == "ApiResourcesView") {
                        $returnUrl = $this->getViewUrl(); // View page, return to View page with keyurl directly
                    }

                    // Handle UseAjaxActions with return page
                    if ($this->IsModal && $this->UseAjaxActions) {
                        $this->IsModal = false;
                        if (GetPageName($returnUrl) != "ApiResourcesList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "ApiResourcesList"; // Return list page content
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
        $this->http_method->DefaultValue = $this->http_method->getDefault(); // PHP
        $this->http_method->OldValue = $this->http_method->DefaultValue;
        $this->mode->DefaultValue = $this->mode->getDefault(); // PHP
        $this->mode->OldValue = $this->mode->DefaultValue;
        $this->required_fields->DefaultValue = $this->required_fields->getDefault(); // PHP
        $this->required_fields->OldValue = $this->required_fields->DefaultValue;
        $this->field_map->DefaultValue = $this->field_map->getDefault(); // PHP
        $this->field_map->OldValue = $this->field_map->DefaultValue;
        $this->sample_payload->DefaultValue = $this->sample_payload->getDefault(); // PHP
        $this->sample_payload->OldValue = $this->sample_payload->DefaultValue;
        $this->request_body->DefaultValue = $this->request_body->getDefault(); // PHP
        $this->request_body->OldValue = $this->request_body->DefaultValue;
        $this->data_path->DefaultValue = $this->data_path->getDefault(); // PHP
        $this->data_path->OldValue = $this->data_path->DefaultValue;
        $this->base_url->DefaultValue = $this->base_url->getDefault(); // PHP
        $this->base_url->OldValue = $this->base_url->DefaultValue;
        $this->auth_token->DefaultValue = $this->auth_token->getDefault(); // PHP
        $this->auth_token->OldValue = $this->auth_token->DefaultValue;
    }

    // Load form values
    protected function loadFormValues()
    {
        // Load from form
        global $CurrentForm;
        $validate = !Config("SERVER_VALIDATE");

        // Check field name 'system_id' first before field var 'x_system_id'
        $val = $CurrentForm->hasValue("system_id") ? $CurrentForm->getValue("system_id") : $CurrentForm->getValue("x_system_id");
        if (!$this->system_id->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->system_id->Visible = false; // Disable update for API request
            } else {
                $this->system_id->setFormValue($val, true, $validate);
            }
        }

        // Check field name 'resource_key' first before field var 'x_resource_key'
        $val = $CurrentForm->hasValue("resource_key") ? $CurrentForm->getValue("resource_key") : $CurrentForm->getValue("x_resource_key");
        if (!$this->resource_key->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->resource_key->Visible = false; // Disable update for API request
            } else {
                $this->resource_key->setFormValue($val);
            }
        }

        // Check field name 'caption' first before field var 'x_caption'
        $val = $CurrentForm->hasValue("caption") ? $CurrentForm->getValue("caption") : $CurrentForm->getValue("x_caption");
        if (!$this->caption->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->caption->Visible = false; // Disable update for API request
            } else {
                $this->caption->setFormValue($val);
            }
        }

        // Check field name 'http_method' first before field var 'x_http_method'
        $val = $CurrentForm->hasValue("http_method") ? $CurrentForm->getValue("http_method") : $CurrentForm->getValue("x_http_method");
        if (!$this->http_method->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->http_method->Visible = false; // Disable update for API request
            } else {
                $this->http_method->setFormValue($val);
            }
        }

        // Check field name 'path_template' first before field var 'x_path_template'
        $val = $CurrentForm->hasValue("path_template") ? $CurrentForm->getValue("path_template") : $CurrentForm->getValue("x_path_template");
        if (!$this->path_template->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->path_template->Visible = false; // Disable update for API request
            } else {
                $this->path_template->setFormValue($val);
            }
        }

        // Check field name 'mode' first before field var 'x_mode'
        $val = $CurrentForm->hasValue("mode") ? $CurrentForm->getValue("mode") : $CurrentForm->getValue("x_mode");
        if (!$this->mode->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->mode->Visible = false; // Disable update for API request
            } else {
                $this->mode->setFormValue($val);
            }
        }

        // Check field name 'required_fields' first before field var 'x_required_fields'
        $val = $CurrentForm->hasValue("required_fields") ? $CurrentForm->getValue("required_fields") : $CurrentForm->getValue("x_required_fields");
        if (!$this->required_fields->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->required_fields->Visible = false; // Disable update for API request
            } else {
                $this->required_fields->setFormValue($val);
            }
        }

        // Check field name 'field_map' first before field var 'x_field_map'
        $val = $CurrentForm->hasValue("field_map") ? $CurrentForm->getValue("field_map") : $CurrentForm->getValue("x_field_map");
        if (!$this->field_map->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->field_map->Visible = false; // Disable update for API request
            } else {
                $this->field_map->setFormValue($val);
            }
        }

        // Check field name 'sample_payload' first before field var 'x_sample_payload'
        $val = $CurrentForm->hasValue("sample_payload") ? $CurrentForm->getValue("sample_payload") : $CurrentForm->getValue("x_sample_payload");
        if (!$this->sample_payload->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->sample_payload->Visible = false; // Disable update for API request
            } else {
                $this->sample_payload->setFormValue($val);
            }
        }

        // Check field name 'request_body' first before field var 'x_request_body'
        $val = $CurrentForm->hasValue("request_body") ? $CurrentForm->getValue("request_body") : $CurrentForm->getValue("x_request_body");
        if (!$this->request_body->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->request_body->Visible = false; // Disable update for API request
            } else {
                $this->request_body->setFormValue($val);
            }
        }

        // Check field name 'data_path' first before field var 'x_data_path'
        $val = $CurrentForm->hasValue("data_path") ? $CurrentForm->getValue("data_path") : $CurrentForm->getValue("x_data_path");
        if (!$this->data_path->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->data_path->Visible = false; // Disable update for API request
            } else {
                $this->data_path->setFormValue($val);
            }
        }

        // Check field name 'base_url' first before field var 'x_base_url'
        $val = $CurrentForm->hasValue("base_url") ? $CurrentForm->getValue("base_url") : $CurrentForm->getValue("x_base_url");
        if (!$this->base_url->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->base_url->Visible = false; // Disable update for API request
            } else {
                $this->base_url->setFormValue($val);
            }
        }

        // Check field name 'auth_token' first before field var 'x_auth_token'
        $val = $CurrentForm->hasValue("auth_token") ? $CurrentForm->getValue("auth_token") : $CurrentForm->getValue("x_auth_token");
        if (!$this->auth_token->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->auth_token->Visible = false; // Disable update for API request
            } else {
                $this->auth_token->setFormValue($val);
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

        // Check field name 'resource_id' first before field var 'x_resource_id'
        $val = $CurrentForm->hasValue("resource_id") ? $CurrentForm->getValue("resource_id") : $CurrentForm->getValue("x_resource_id");
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->system_id->CurrentValue = $this->system_id->FormValue;
        $this->resource_key->CurrentValue = $this->resource_key->FormValue;
        $this->caption->CurrentValue = $this->caption->FormValue;
        $this->http_method->CurrentValue = $this->http_method->FormValue;
        $this->path_template->CurrentValue = $this->path_template->FormValue;
        $this->mode->CurrentValue = $this->mode->FormValue;
        $this->required_fields->CurrentValue = $this->required_fields->FormValue;
        $this->field_map->CurrentValue = $this->field_map->FormValue;
        $this->sample_payload->CurrentValue = $this->sample_payload->FormValue;
        $this->request_body->CurrentValue = $this->request_body->FormValue;
        $this->data_path->CurrentValue = $this->data_path->FormValue;
        $this->base_url->CurrentValue = $this->base_url->FormValue;
        $this->auth_token->CurrentValue = $this->auth_token->FormValue;
        $this->is_active->CurrentValue = $this->is_active->FormValue;
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
        $this->resource_id->setDbValue($row['resource_id']);
        $this->system_id->setDbValue($row['system_id']);
        $this->resource_key->setDbValue($row['resource_key']);
        $this->caption->setDbValue($row['caption']);
        $this->http_method->setDbValue($row['http_method']);
        $this->path_template->setDbValue($row['path_template']);
        $this->mode->setDbValue($row['mode']);
        $this->required_fields->setDbValue($row['required_fields']);
        $this->field_map->setDbValue($row['field_map']);
        $this->sample_payload->setDbValue($row['sample_payload']);
        $this->request_body->setDbValue($row['request_body']);
        $this->data_path->setDbValue($row['data_path']);
        $this->base_url->setDbValue($row['base_url']);
        $this->auth_token->setDbValue($row['auth_token']);
        $this->is_active->setDbValue((ConvertToBool($row['is_active']) ? "1" : "0"));
        $this->created_at->setDbValue($row['created_at']);
        $this->updated_at->setDbValue($row['updated_at']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['resource_id'] = $this->resource_id->DefaultValue;
        $row['system_id'] = $this->system_id->DefaultValue;
        $row['resource_key'] = $this->resource_key->DefaultValue;
        $row['caption'] = $this->caption->DefaultValue;
        $row['http_method'] = $this->http_method->DefaultValue;
        $row['path_template'] = $this->path_template->DefaultValue;
        $row['mode'] = $this->mode->DefaultValue;
        $row['required_fields'] = $this->required_fields->DefaultValue;
        $row['field_map'] = $this->field_map->DefaultValue;
        $row['sample_payload'] = $this->sample_payload->DefaultValue;
        $row['request_body'] = $this->request_body->DefaultValue;
        $row['data_path'] = $this->data_path->DefaultValue;
        $row['base_url'] = $this->base_url->DefaultValue;
        $row['auth_token'] = $this->auth_token->DefaultValue;
        $row['is_active'] = $this->is_active->DefaultValue;
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

        // resource_id
        $this->resource_id->RowCssClass = "row";

        // system_id
        $this->system_id->RowCssClass = "row";

        // resource_key
        $this->resource_key->RowCssClass = "row";

        // caption
        $this->caption->RowCssClass = "row";

        // http_method
        $this->http_method->RowCssClass = "row";

        // path_template
        $this->path_template->RowCssClass = "row";

        // mode
        $this->mode->RowCssClass = "row";

        // required_fields
        $this->required_fields->RowCssClass = "row";

        // field_map
        $this->field_map->RowCssClass = "row";

        // sample_payload
        $this->sample_payload->RowCssClass = "row";

        // request_body
        $this->request_body->RowCssClass = "row";

        // data_path
        $this->data_path->RowCssClass = "row";

        // base_url
        $this->base_url->RowCssClass = "row";

        // auth_token
        $this->auth_token->RowCssClass = "row";

        // is_active
        $this->is_active->RowCssClass = "row";

        // created_at
        $this->created_at->RowCssClass = "row";

        // updated_at
        $this->updated_at->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // resource_id
            $this->resource_id->ViewValue = $this->resource_id->CurrentValue;

            // system_id
            $this->system_id->ViewValue = $this->system_id->CurrentValue;
            $this->system_id->ViewValue = FormatNumber($this->system_id->ViewValue, $this->system_id->formatPattern());

            // resource_key
            $this->resource_key->ViewValue = $this->resource_key->CurrentValue;

            // caption
            $this->caption->ViewValue = $this->caption->CurrentValue;

            // http_method
            $this->http_method->ViewValue = $this->http_method->CurrentValue;

            // path_template
            $this->path_template->ViewValue = $this->path_template->CurrentValue;

            // mode
            $this->mode->ViewValue = $this->mode->CurrentValue;

            // required_fields
            $this->required_fields->ViewValue = $this->required_fields->CurrentValue;

            // field_map
            $this->field_map->ViewValue = $this->field_map->CurrentValue;

            // sample_payload
            $this->sample_payload->ViewValue = $this->sample_payload->CurrentValue;

            // request_body
            $this->request_body->ViewValue = $this->request_body->CurrentValue;

            // data_path
            $this->data_path->ViewValue = $this->data_path->CurrentValue;

            // base_url
            $this->base_url->ViewValue = $this->base_url->CurrentValue;

            // auth_token
            $this->auth_token->ViewValue = $this->auth_token->CurrentValue;

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

            // system_id
            $this->system_id->HrefValue = "";

            // resource_key
            $this->resource_key->HrefValue = "";

            // caption
            $this->caption->HrefValue = "";

            // http_method
            $this->http_method->HrefValue = "";

            // path_template
            $this->path_template->HrefValue = "";

            // mode
            $this->mode->HrefValue = "";

            // required_fields
            $this->required_fields->HrefValue = "";

            // field_map
            $this->field_map->HrefValue = "";

            // sample_payload
            $this->sample_payload->HrefValue = "";

            // request_body
            $this->request_body->HrefValue = "";

            // data_path
            $this->data_path->HrefValue = "";

            // base_url
            $this->base_url->HrefValue = "";

            // auth_token
            $this->auth_token->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

            // created_at
            $this->created_at->HrefValue = "";

            // updated_at
            $this->updated_at->HrefValue = "";
        } elseif ($this->RowType == RowType::ADD) {
            // system_id
            $this->system_id->setupEditAttributes();
            $this->system_id->EditValue = $this->system_id->CurrentValue;
            $this->system_id->PlaceHolder = RemoveHtml($this->system_id->caption());
            if (strval($this->system_id->EditValue) != "" && is_numeric($this->system_id->EditValue)) {
                $this->system_id->EditValue = FormatNumber($this->system_id->EditValue, null);
            }

            // resource_key
            $this->resource_key->setupEditAttributes();
            if (!$this->resource_key->Raw) {
                $this->resource_key->CurrentValue = HtmlDecode($this->resource_key->CurrentValue);
            }
            $this->resource_key->EditValue = HtmlEncode($this->resource_key->CurrentValue);
            $this->resource_key->PlaceHolder = RemoveHtml($this->resource_key->caption());

            // caption
            $this->caption->setupEditAttributes();
            if (!$this->caption->Raw) {
                $this->caption->CurrentValue = HtmlDecode($this->caption->CurrentValue);
            }
            $this->caption->EditValue = HtmlEncode($this->caption->CurrentValue);
            $this->caption->PlaceHolder = RemoveHtml($this->caption->caption());

            // http_method
            $this->http_method->setupEditAttributes();
            if (!$this->http_method->Raw) {
                $this->http_method->CurrentValue = HtmlDecode($this->http_method->CurrentValue);
            }
            $this->http_method->EditValue = HtmlEncode($this->http_method->CurrentValue);
            $this->http_method->PlaceHolder = RemoveHtml($this->http_method->caption());

            // path_template
            $this->path_template->setupEditAttributes();
            if (!$this->path_template->Raw) {
                $this->path_template->CurrentValue = HtmlDecode($this->path_template->CurrentValue);
            }
            $this->path_template->EditValue = HtmlEncode($this->path_template->CurrentValue);
            $this->path_template->PlaceHolder = RemoveHtml($this->path_template->caption());

            // mode
            $this->mode->setupEditAttributes();
            if (!$this->mode->Raw) {
                $this->mode->CurrentValue = HtmlDecode($this->mode->CurrentValue);
            }
            $this->mode->EditValue = HtmlEncode($this->mode->CurrentValue);
            $this->mode->PlaceHolder = RemoveHtml($this->mode->caption());

            // required_fields
            $this->required_fields->setupEditAttributes();
            $this->required_fields->EditValue = HtmlEncode($this->required_fields->CurrentValue);
            $this->required_fields->PlaceHolder = RemoveHtml($this->required_fields->caption());

            // field_map
            $this->field_map->setupEditAttributes();
            $this->field_map->EditValue = HtmlEncode($this->field_map->CurrentValue);
            $this->field_map->PlaceHolder = RemoveHtml($this->field_map->caption());

            // sample_payload
            $this->sample_payload->setupEditAttributes();
            $this->sample_payload->EditValue = HtmlEncode($this->sample_payload->CurrentValue);
            $this->sample_payload->PlaceHolder = RemoveHtml($this->sample_payload->caption());

            // request_body
            $this->request_body->setupEditAttributes();
            $this->request_body->EditValue = HtmlEncode($this->request_body->CurrentValue);
            $this->request_body->PlaceHolder = RemoveHtml($this->request_body->caption());

            // data_path
            $this->data_path->setupEditAttributes();
            if (!$this->data_path->Raw) {
                $this->data_path->CurrentValue = HtmlDecode($this->data_path->CurrentValue);
            }
            $this->data_path->EditValue = HtmlEncode($this->data_path->CurrentValue);
            $this->data_path->PlaceHolder = RemoveHtml($this->data_path->caption());

            // base_url
            $this->base_url->setupEditAttributes();
            if (!$this->base_url->Raw) {
                $this->base_url->CurrentValue = HtmlDecode($this->base_url->CurrentValue);
            }
            $this->base_url->EditValue = HtmlEncode($this->base_url->CurrentValue);
            $this->base_url->PlaceHolder = RemoveHtml($this->base_url->caption());

            // auth_token
            $this->auth_token->setupEditAttributes();
            $this->auth_token->EditValue = HtmlEncode($this->auth_token->CurrentValue);
            $this->auth_token->PlaceHolder = RemoveHtml($this->auth_token->caption());

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

            // Add refer script

            // system_id
            $this->system_id->HrefValue = "";

            // resource_key
            $this->resource_key->HrefValue = "";

            // caption
            $this->caption->HrefValue = "";

            // http_method
            $this->http_method->HrefValue = "";

            // path_template
            $this->path_template->HrefValue = "";

            // mode
            $this->mode->HrefValue = "";

            // required_fields
            $this->required_fields->HrefValue = "";

            // field_map
            $this->field_map->HrefValue = "";

            // sample_payload
            $this->sample_payload->HrefValue = "";

            // request_body
            $this->request_body->HrefValue = "";

            // data_path
            $this->data_path->HrefValue = "";

            // base_url
            $this->base_url->HrefValue = "";

            // auth_token
            $this->auth_token->HrefValue = "";

            // is_active
            $this->is_active->HrefValue = "";

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
            if ($this->system_id->Visible && $this->system_id->Required) {
                if (!$this->system_id->IsDetailKey && EmptyValue($this->system_id->FormValue)) {
                    $this->system_id->addErrorMessage(str_replace("%s", $this->system_id->caption(), $this->system_id->RequiredErrorMessage));
                }
            }
            if (!CheckInteger($this->system_id->FormValue)) {
                $this->system_id->addErrorMessage($this->system_id->getErrorMessage(false));
            }
            if ($this->resource_key->Visible && $this->resource_key->Required) {
                if (!$this->resource_key->IsDetailKey && EmptyValue($this->resource_key->FormValue)) {
                    $this->resource_key->addErrorMessage(str_replace("%s", $this->resource_key->caption(), $this->resource_key->RequiredErrorMessage));
                }
            }
            if ($this->caption->Visible && $this->caption->Required) {
                if (!$this->caption->IsDetailKey && EmptyValue($this->caption->FormValue)) {
                    $this->caption->addErrorMessage(str_replace("%s", $this->caption->caption(), $this->caption->RequiredErrorMessage));
                }
            }
            if ($this->http_method->Visible && $this->http_method->Required) {
                if (!$this->http_method->IsDetailKey && EmptyValue($this->http_method->FormValue)) {
                    $this->http_method->addErrorMessage(str_replace("%s", $this->http_method->caption(), $this->http_method->RequiredErrorMessage));
                }
            }
            if ($this->path_template->Visible && $this->path_template->Required) {
                if (!$this->path_template->IsDetailKey && EmptyValue($this->path_template->FormValue)) {
                    $this->path_template->addErrorMessage(str_replace("%s", $this->path_template->caption(), $this->path_template->RequiredErrorMessage));
                }
            }
            if ($this->mode->Visible && $this->mode->Required) {
                if (!$this->mode->IsDetailKey && EmptyValue($this->mode->FormValue)) {
                    $this->mode->addErrorMessage(str_replace("%s", $this->mode->caption(), $this->mode->RequiredErrorMessage));
                }
            }
            if ($this->required_fields->Visible && $this->required_fields->Required) {
                if (!$this->required_fields->IsDetailKey && EmptyValue($this->required_fields->FormValue)) {
                    $this->required_fields->addErrorMessage(str_replace("%s", $this->required_fields->caption(), $this->required_fields->RequiredErrorMessage));
                }
            }
            if ($this->field_map->Visible && $this->field_map->Required) {
                if (!$this->field_map->IsDetailKey && EmptyValue($this->field_map->FormValue)) {
                    $this->field_map->addErrorMessage(str_replace("%s", $this->field_map->caption(), $this->field_map->RequiredErrorMessage));
                }
            }
            if ($this->sample_payload->Visible && $this->sample_payload->Required) {
                if (!$this->sample_payload->IsDetailKey && EmptyValue($this->sample_payload->FormValue)) {
                    $this->sample_payload->addErrorMessage(str_replace("%s", $this->sample_payload->caption(), $this->sample_payload->RequiredErrorMessage));
                }
            }
            if ($this->request_body->Visible && $this->request_body->Required) {
                if (!$this->request_body->IsDetailKey && EmptyValue($this->request_body->FormValue)) {
                    $this->request_body->addErrorMessage(str_replace("%s", $this->request_body->caption(), $this->request_body->RequiredErrorMessage));
                }
            }
            if ($this->data_path->Visible && $this->data_path->Required) {
                if (!$this->data_path->IsDetailKey && EmptyValue($this->data_path->FormValue)) {
                    $this->data_path->addErrorMessage(str_replace("%s", $this->data_path->caption(), $this->data_path->RequiredErrorMessage));
                }
            }
            if ($this->base_url->Visible && $this->base_url->Required) {
                if (!$this->base_url->IsDetailKey && EmptyValue($this->base_url->FormValue)) {
                    $this->base_url->addErrorMessage(str_replace("%s", $this->base_url->caption(), $this->base_url->RequiredErrorMessage));
                }
            }
            if ($this->auth_token->Visible && $this->auth_token->Required) {
                if (!$this->auth_token->IsDetailKey && EmptyValue($this->auth_token->FormValue)) {
                    $this->auth_token->addErrorMessage(str_replace("%s", $this->auth_token->caption(), $this->auth_token->RequiredErrorMessage));
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

        // system_id
        $this->system_id->setDbValueDef($rsnew, $this->system_id->CurrentValue, false);

        // resource_key
        $this->resource_key->setDbValueDef($rsnew, $this->resource_key->CurrentValue, false);

        // caption
        $this->caption->setDbValueDef($rsnew, $this->caption->CurrentValue, false);

        // http_method
        $this->http_method->setDbValueDef($rsnew, $this->http_method->CurrentValue, strval($this->http_method->CurrentValue) == "");

        // path_template
        $this->path_template->setDbValueDef($rsnew, $this->path_template->CurrentValue, false);

        // mode
        $this->mode->setDbValueDef($rsnew, $this->mode->CurrentValue, strval($this->mode->CurrentValue) == "");

        // required_fields
        $this->required_fields->setDbValueDef($rsnew, $this->required_fields->CurrentValue, strval($this->required_fields->CurrentValue) == "");

        // field_map
        $this->field_map->setDbValueDef($rsnew, $this->field_map->CurrentValue, strval($this->field_map->CurrentValue) == "");

        // sample_payload
        $this->sample_payload->setDbValueDef($rsnew, $this->sample_payload->CurrentValue, strval($this->sample_payload->CurrentValue) == "");

        // request_body
        $this->request_body->setDbValueDef($rsnew, $this->request_body->CurrentValue, strval($this->request_body->CurrentValue) == "");

        // data_path
        $this->data_path->setDbValueDef($rsnew, $this->data_path->CurrentValue, strval($this->data_path->CurrentValue) == "");

        // base_url
        $this->base_url->setDbValueDef($rsnew, $this->base_url->CurrentValue, strval($this->base_url->CurrentValue) == "");

        // auth_token
        $this->auth_token->setDbValueDef($rsnew, $this->auth_token->CurrentValue, strval($this->auth_token->CurrentValue) == "");

        // is_active
        $tmpBool = $this->is_active->CurrentValue;
        if ($tmpBool != "1" && $tmpBool != "0") {
            $tmpBool = !empty($tmpBool) ? "1" : "0";
        }
        $this->is_active->setDbValueDef($rsnew, $tmpBool, strval($this->is_active->CurrentValue) == "");

        // created_at
        $this->created_at->setDbValueDef($rsnew, UnFormatDateTime($this->created_at->CurrentValue, $this->created_at->formatPattern()), strval($this->created_at->CurrentValue) == "");

        // updated_at
        $this->updated_at->setDbValueDef($rsnew, UnFormatDateTime($this->updated_at->CurrentValue, $this->updated_at->formatPattern()), strval($this->updated_at->CurrentValue) == "");
        return $rsnew;
    }

    /**
     * Restore add form from row
     * @param array $row Row
     */
    protected function restoreAddFormFromRow($row)
    {
        if (isset($row['system_id'])) { // system_id
            $this->system_id->setFormValue($row['system_id']);
        }
        if (isset($row['resource_key'])) { // resource_key
            $this->resource_key->setFormValue($row['resource_key']);
        }
        if (isset($row['caption'])) { // caption
            $this->caption->setFormValue($row['caption']);
        }
        if (isset($row['http_method'])) { // http_method
            $this->http_method->setFormValue($row['http_method']);
        }
        if (isset($row['path_template'])) { // path_template
            $this->path_template->setFormValue($row['path_template']);
        }
        if (isset($row['mode'])) { // mode
            $this->mode->setFormValue($row['mode']);
        }
        if (isset($row['required_fields'])) { // required_fields
            $this->required_fields->setFormValue($row['required_fields']);
        }
        if (isset($row['field_map'])) { // field_map
            $this->field_map->setFormValue($row['field_map']);
        }
        if (isset($row['sample_payload'])) { // sample_payload
            $this->sample_payload->setFormValue($row['sample_payload']);
        }
        if (isset($row['request_body'])) { // request_body
            $this->request_body->setFormValue($row['request_body']);
        }
        if (isset($row['data_path'])) { // data_path
            $this->data_path->setFormValue($row['data_path']);
        }
        if (isset($row['base_url'])) { // base_url
            $this->base_url->setFormValue($row['base_url']);
        }
        if (isset($row['auth_token'])) { // auth_token
            $this->auth_token->setFormValue($row['auth_token']);
        }
        if (isset($row['is_active'])) { // is_active
            $this->is_active->setFormValue($row['is_active']);
        }
        if (isset($row['created_at'])) { // created_at
            $this->created_at->setFormValue($row['created_at']);
        }
        if (isset($row['updated_at'])) { // updated_at
            $this->updated_at->setFormValue($row['updated_at']);
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("ApiResourcesList"), "", $this->TableVar, true);
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
