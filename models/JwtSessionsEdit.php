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
class JwtSessionsEdit extends JwtSessions
{
    use MessagesTrait;

    // Page ID
    public $PageID = "edit";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "JwtSessionsEdit";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "JwtSessionsEdit";

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
        $this->session_id->setVisibility();
        $this->token_hash->setVisibility();
        $this->user_id->setVisibility();
        $this->system->setVisibility();
        $this->issued_at->setVisibility();
        $this->expires_at->setVisibility();
        $this->client_ip->setVisibility();
        $this->user_agent->setVisibility();
        $this->revoked_at->setVisibility();
        $this->revoke_reason->setVisibility();
    }

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'jwt_sessions';
        $this->TableName = 'jwt_sessions';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-desktop-table ew-edit-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (jwt_sessions)
        if (!isset($GLOBALS["jwt_sessions"]) || $GLOBALS["jwt_sessions"]::class == PROJECT_NAMESPACE . "jwt_sessions") {
            $GLOBALS["jwt_sessions"] = &$this;
        }

        // Table name (for backward compatibility only)
        if (!defined(PROJECT_NAMESPACE . "TABLE_NAME")) {
            define(PROJECT_NAMESPACE . "TABLE_NAME", 'jwt_sessions');
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
                        $result["view"] = SameString($pageName, "JwtSessionsView"); // If View page, no primary button
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
            $key .= @$ar['session_id'];
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
            $this->session_id->Visible = false;
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
            if (($keyValue = Get("session_id") ?? Key(0) ?? Route(2)) !== null) {
                $this->session_id->setQueryStringValue($keyValue);
                $this->session_id->setOldValue($this->session_id->QueryStringValue);
            } elseif (Post("session_id") !== null) {
                $this->session_id->setFormValue(Post("session_id"));
                $this->session_id->setOldValue($this->session_id->FormValue);
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
                if (($keyValue = Get("session_id") ?? Route("session_id")) !== null) {
                    $this->session_id->setQueryStringValue($keyValue);
                    $loadByQuery = true;
                } else {
                    $this->session_id->CurrentValue = null;
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
                        $this->terminate("JwtSessionsList"); // No matching record, return to list
                        return;
                    }
                break;
            case "update": // Update
                $returnUrl = $this->getReturnUrl();
                if (GetPageName($returnUrl) == "JwtSessionsList") {
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
                        if (GetPageName($returnUrl) != "JwtSessionsList") {
                            Container("app.flash")->addMessage("Return-Url", $returnUrl); // Save return URL
                            $returnUrl = "JwtSessionsList"; // Return list page content
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

        // Check field name 'session_id' first before field var 'x_session_id'
        $val = $CurrentForm->hasValue("session_id") ? $CurrentForm->getValue("session_id") : $CurrentForm->getValue("x_session_id");
        if (!$this->session_id->IsDetailKey) {
            $this->session_id->setFormValue($val);
        }

        // Check field name 'token_hash' first before field var 'x_token_hash'
        $val = $CurrentForm->hasValue("token_hash") ? $CurrentForm->getValue("token_hash") : $CurrentForm->getValue("x_token_hash");
        if (!$this->token_hash->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->token_hash->Visible = false; // Disable update for API request
            } else {
                $this->token_hash->setFormValue($val);
            }
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

        // Check field name 'system' first before field var 'x_system'
        $val = $CurrentForm->hasValue("system") ? $CurrentForm->getValue("system") : $CurrentForm->getValue("x_system");
        if (!$this->system->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->system->Visible = false; // Disable update for API request
            } else {
                $this->system->setFormValue($val);
            }
        }

        // Check field name 'issued_at' first before field var 'x_issued_at'
        $val = $CurrentForm->hasValue("issued_at") ? $CurrentForm->getValue("issued_at") : $CurrentForm->getValue("x_issued_at");
        if (!$this->issued_at->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->issued_at->Visible = false; // Disable update for API request
            } else {
                $this->issued_at->setFormValue($val, true, $validate);
            }
            $this->issued_at->CurrentValue = UnFormatDateTime($this->issued_at->CurrentValue, $this->issued_at->formatPattern());
        }

        // Check field name 'expires_at' first before field var 'x_expires_at'
        $val = $CurrentForm->hasValue("expires_at") ? $CurrentForm->getValue("expires_at") : $CurrentForm->getValue("x_expires_at");
        if (!$this->expires_at->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->expires_at->Visible = false; // Disable update for API request
            } else {
                $this->expires_at->setFormValue($val, true, $validate);
            }
            $this->expires_at->CurrentValue = UnFormatDateTime($this->expires_at->CurrentValue, $this->expires_at->formatPattern());
        }

        // Check field name 'client_ip' first before field var 'x_client_ip'
        $val = $CurrentForm->hasValue("client_ip") ? $CurrentForm->getValue("client_ip") : $CurrentForm->getValue("x_client_ip");
        if (!$this->client_ip->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->client_ip->Visible = false; // Disable update for API request
            } else {
                $this->client_ip->setFormValue($val);
            }
        }

        // Check field name 'user_agent' first before field var 'x_user_agent'
        $val = $CurrentForm->hasValue("user_agent") ? $CurrentForm->getValue("user_agent") : $CurrentForm->getValue("x_user_agent");
        if (!$this->user_agent->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->user_agent->Visible = false; // Disable update for API request
            } else {
                $this->user_agent->setFormValue($val);
            }
        }

        // Check field name 'revoked_at' first before field var 'x_revoked_at'
        $val = $CurrentForm->hasValue("revoked_at") ? $CurrentForm->getValue("revoked_at") : $CurrentForm->getValue("x_revoked_at");
        if (!$this->revoked_at->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->revoked_at->Visible = false; // Disable update for API request
            } else {
                $this->revoked_at->setFormValue($val, true, $validate);
            }
            $this->revoked_at->CurrentValue = UnFormatDateTime($this->revoked_at->CurrentValue, $this->revoked_at->formatPattern());
        }

        // Check field name 'revoke_reason' first before field var 'x_revoke_reason'
        $val = $CurrentForm->hasValue("revoke_reason") ? $CurrentForm->getValue("revoke_reason") : $CurrentForm->getValue("x_revoke_reason");
        if (!$this->revoke_reason->IsDetailKey) {
            if (IsApi() && $val === null) {
                $this->revoke_reason->Visible = false; // Disable update for API request
            } else {
                $this->revoke_reason->setFormValue($val);
            }
        }
    }

    // Restore form values
    public function restoreFormValues()
    {
        global $CurrentForm;
        $this->session_id->CurrentValue = $this->session_id->FormValue;
        $this->token_hash->CurrentValue = $this->token_hash->FormValue;
        $this->user_id->CurrentValue = $this->user_id->FormValue;
        $this->system->CurrentValue = $this->system->FormValue;
        $this->issued_at->CurrentValue = $this->issued_at->FormValue;
        $this->issued_at->CurrentValue = UnFormatDateTime($this->issued_at->CurrentValue, $this->issued_at->formatPattern());
        $this->expires_at->CurrentValue = $this->expires_at->FormValue;
        $this->expires_at->CurrentValue = UnFormatDateTime($this->expires_at->CurrentValue, $this->expires_at->formatPattern());
        $this->client_ip->CurrentValue = $this->client_ip->FormValue;
        $this->user_agent->CurrentValue = $this->user_agent->FormValue;
        $this->revoked_at->CurrentValue = $this->revoked_at->FormValue;
        $this->revoked_at->CurrentValue = UnFormatDateTime($this->revoked_at->CurrentValue, $this->revoked_at->formatPattern());
        $this->revoke_reason->CurrentValue = $this->revoke_reason->FormValue;
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
        $this->session_id->setDbValue($row['session_id']);
        $this->token_hash->setDbValue($row['token_hash']);
        $this->user_id->setDbValue($row['user_id']);
        $this->system->setDbValue($row['system']);
        $this->issued_at->setDbValue($row['issued_at']);
        $this->expires_at->setDbValue($row['expires_at']);
        $this->client_ip->setDbValue($row['client_ip']);
        $this->user_agent->setDbValue($row['user_agent']);
        $this->revoked_at->setDbValue($row['revoked_at']);
        $this->revoke_reason->setDbValue($row['revoke_reason']);
    }

    // Return a row with default values
    protected function newRow()
    {
        $row = [];
        $row['session_id'] = $this->session_id->DefaultValue;
        $row['token_hash'] = $this->token_hash->DefaultValue;
        $row['user_id'] = $this->user_id->DefaultValue;
        $row['system'] = $this->system->DefaultValue;
        $row['issued_at'] = $this->issued_at->DefaultValue;
        $row['expires_at'] = $this->expires_at->DefaultValue;
        $row['client_ip'] = $this->client_ip->DefaultValue;
        $row['user_agent'] = $this->user_agent->DefaultValue;
        $row['revoked_at'] = $this->revoked_at->DefaultValue;
        $row['revoke_reason'] = $this->revoke_reason->DefaultValue;
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

        // session_id
        $this->session_id->RowCssClass = "row";

        // token_hash
        $this->token_hash->RowCssClass = "row";

        // user_id
        $this->user_id->RowCssClass = "row";

        // system
        $this->system->RowCssClass = "row";

        // issued_at
        $this->issued_at->RowCssClass = "row";

        // expires_at
        $this->expires_at->RowCssClass = "row";

        // client_ip
        $this->client_ip->RowCssClass = "row";

        // user_agent
        $this->user_agent->RowCssClass = "row";

        // revoked_at
        $this->revoked_at->RowCssClass = "row";

        // revoke_reason
        $this->revoke_reason->RowCssClass = "row";

        // View row
        if ($this->RowType == RowType::VIEW) {
            // session_id
            $this->session_id->ViewValue = $this->session_id->CurrentValue;

            // token_hash
            $this->token_hash->ViewValue = $this->token_hash->CurrentValue;

            // user_id
            $this->user_id->ViewValue = $this->user_id->CurrentValue;
            $this->user_id->ViewValue = FormatNumber($this->user_id->ViewValue, $this->user_id->formatPattern());

            // system
            $this->system->ViewValue = $this->system->CurrentValue;

            // issued_at
            $this->issued_at->ViewValue = $this->issued_at->CurrentValue;
            $this->issued_at->ViewValue = FormatDateTime($this->issued_at->ViewValue, $this->issued_at->formatPattern());

            // expires_at
            $this->expires_at->ViewValue = $this->expires_at->CurrentValue;
            $this->expires_at->ViewValue = FormatDateTime($this->expires_at->ViewValue, $this->expires_at->formatPattern());

            // client_ip
            $this->client_ip->ViewValue = $this->client_ip->CurrentValue;

            // user_agent
            $this->user_agent->ViewValue = $this->user_agent->CurrentValue;

            // revoked_at
            $this->revoked_at->ViewValue = $this->revoked_at->CurrentValue;
            $this->revoked_at->ViewValue = FormatDateTime($this->revoked_at->ViewValue, $this->revoked_at->formatPattern());

            // revoke_reason
            $this->revoke_reason->ViewValue = $this->revoke_reason->CurrentValue;

            // session_id
            $this->session_id->HrefValue = "";

            // token_hash
            $this->token_hash->HrefValue = "";

            // user_id
            $this->user_id->HrefValue = "";

            // system
            $this->system->HrefValue = "";

            // issued_at
            $this->issued_at->HrefValue = "";

            // expires_at
            $this->expires_at->HrefValue = "";

            // client_ip
            $this->client_ip->HrefValue = "";

            // user_agent
            $this->user_agent->HrefValue = "";

            // revoked_at
            $this->revoked_at->HrefValue = "";

            // revoke_reason
            $this->revoke_reason->HrefValue = "";
        } elseif ($this->RowType == RowType::EDIT) {
            // session_id
            $this->session_id->setupEditAttributes();
            $this->session_id->EditValue = $this->session_id->CurrentValue;

            // token_hash
            $this->token_hash->setupEditAttributes();
            if (!$this->token_hash->Raw) {
                $this->token_hash->CurrentValue = HtmlDecode($this->token_hash->CurrentValue);
            }
            $this->token_hash->EditValue = HtmlEncode($this->token_hash->CurrentValue);
            $this->token_hash->PlaceHolder = RemoveHtml($this->token_hash->caption());

            // user_id
            $this->user_id->setupEditAttributes();
            $this->user_id->EditValue = $this->user_id->CurrentValue;
            $this->user_id->PlaceHolder = RemoveHtml($this->user_id->caption());
            if (strval($this->user_id->EditValue) != "" && is_numeric($this->user_id->EditValue)) {
                $this->user_id->EditValue = FormatNumber($this->user_id->EditValue, null);
            }

            // system
            $this->system->setupEditAttributes();
            if (!$this->system->Raw) {
                $this->system->CurrentValue = HtmlDecode($this->system->CurrentValue);
            }
            $this->system->EditValue = HtmlEncode($this->system->CurrentValue);
            $this->system->PlaceHolder = RemoveHtml($this->system->caption());

            // issued_at
            $this->issued_at->setupEditAttributes();
            $this->issued_at->EditValue = HtmlEncode(FormatDateTime($this->issued_at->CurrentValue, $this->issued_at->formatPattern()));
            $this->issued_at->PlaceHolder = RemoveHtml($this->issued_at->caption());

            // expires_at
            $this->expires_at->setupEditAttributes();
            $this->expires_at->EditValue = HtmlEncode(FormatDateTime($this->expires_at->CurrentValue, $this->expires_at->formatPattern()));
            $this->expires_at->PlaceHolder = RemoveHtml($this->expires_at->caption());

            // client_ip
            $this->client_ip->setupEditAttributes();
            if (!$this->client_ip->Raw) {
                $this->client_ip->CurrentValue = HtmlDecode($this->client_ip->CurrentValue);
            }
            $this->client_ip->EditValue = HtmlEncode($this->client_ip->CurrentValue);
            $this->client_ip->PlaceHolder = RemoveHtml($this->client_ip->caption());

            // user_agent
            $this->user_agent->setupEditAttributes();
            $this->user_agent->EditValue = HtmlEncode($this->user_agent->CurrentValue);
            $this->user_agent->PlaceHolder = RemoveHtml($this->user_agent->caption());

            // revoked_at
            $this->revoked_at->setupEditAttributes();
            $this->revoked_at->EditValue = HtmlEncode(FormatDateTime($this->revoked_at->CurrentValue, $this->revoked_at->formatPattern()));
            $this->revoked_at->PlaceHolder = RemoveHtml($this->revoked_at->caption());

            // revoke_reason
            $this->revoke_reason->setupEditAttributes();
            if (!$this->revoke_reason->Raw) {
                $this->revoke_reason->CurrentValue = HtmlDecode($this->revoke_reason->CurrentValue);
            }
            $this->revoke_reason->EditValue = HtmlEncode($this->revoke_reason->CurrentValue);
            $this->revoke_reason->PlaceHolder = RemoveHtml($this->revoke_reason->caption());

            // Edit refer script

            // session_id
            $this->session_id->HrefValue = "";

            // token_hash
            $this->token_hash->HrefValue = "";

            // user_id
            $this->user_id->HrefValue = "";

            // system
            $this->system->HrefValue = "";

            // issued_at
            $this->issued_at->HrefValue = "";

            // expires_at
            $this->expires_at->HrefValue = "";

            // client_ip
            $this->client_ip->HrefValue = "";

            // user_agent
            $this->user_agent->HrefValue = "";

            // revoked_at
            $this->revoked_at->HrefValue = "";

            // revoke_reason
            $this->revoke_reason->HrefValue = "";
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
            if ($this->session_id->Visible && $this->session_id->Required) {
                if (!$this->session_id->IsDetailKey && EmptyValue($this->session_id->FormValue)) {
                    $this->session_id->addErrorMessage(str_replace("%s", $this->session_id->caption(), $this->session_id->RequiredErrorMessage));
                }
            }
            if ($this->token_hash->Visible && $this->token_hash->Required) {
                if (!$this->token_hash->IsDetailKey && EmptyValue($this->token_hash->FormValue)) {
                    $this->token_hash->addErrorMessage(str_replace("%s", $this->token_hash->caption(), $this->token_hash->RequiredErrorMessage));
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
            if ($this->system->Visible && $this->system->Required) {
                if (!$this->system->IsDetailKey && EmptyValue($this->system->FormValue)) {
                    $this->system->addErrorMessage(str_replace("%s", $this->system->caption(), $this->system->RequiredErrorMessage));
                }
            }
            if ($this->issued_at->Visible && $this->issued_at->Required) {
                if (!$this->issued_at->IsDetailKey && EmptyValue($this->issued_at->FormValue)) {
                    $this->issued_at->addErrorMessage(str_replace("%s", $this->issued_at->caption(), $this->issued_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->issued_at->FormValue, $this->issued_at->formatPattern())) {
                $this->issued_at->addErrorMessage($this->issued_at->getErrorMessage(false));
            }
            if ($this->expires_at->Visible && $this->expires_at->Required) {
                if (!$this->expires_at->IsDetailKey && EmptyValue($this->expires_at->FormValue)) {
                    $this->expires_at->addErrorMessage(str_replace("%s", $this->expires_at->caption(), $this->expires_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->expires_at->FormValue, $this->expires_at->formatPattern())) {
                $this->expires_at->addErrorMessage($this->expires_at->getErrorMessage(false));
            }
            if ($this->client_ip->Visible && $this->client_ip->Required) {
                if (!$this->client_ip->IsDetailKey && EmptyValue($this->client_ip->FormValue)) {
                    $this->client_ip->addErrorMessage(str_replace("%s", $this->client_ip->caption(), $this->client_ip->RequiredErrorMessage));
                }
            }
            if ($this->user_agent->Visible && $this->user_agent->Required) {
                if (!$this->user_agent->IsDetailKey && EmptyValue($this->user_agent->FormValue)) {
                    $this->user_agent->addErrorMessage(str_replace("%s", $this->user_agent->caption(), $this->user_agent->RequiredErrorMessage));
                }
            }
            if ($this->revoked_at->Visible && $this->revoked_at->Required) {
                if (!$this->revoked_at->IsDetailKey && EmptyValue($this->revoked_at->FormValue)) {
                    $this->revoked_at->addErrorMessage(str_replace("%s", $this->revoked_at->caption(), $this->revoked_at->RequiredErrorMessage));
                }
            }
            if (!CheckDate($this->revoked_at->FormValue, $this->revoked_at->formatPattern())) {
                $this->revoked_at->addErrorMessage($this->revoked_at->getErrorMessage(false));
            }
            if ($this->revoke_reason->Visible && $this->revoke_reason->Required) {
                if (!$this->revoke_reason->IsDetailKey && EmptyValue($this->revoke_reason->FormValue)) {
                    $this->revoke_reason->addErrorMessage(str_replace("%s", $this->revoke_reason->caption(), $this->revoke_reason->RequiredErrorMessage));
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

        // Check field with unique index (token_hash)
        if ($this->token_hash->CurrentValue != "") {
            $filterChk = "(\"token_hash\" = '" . AdjustSql($this->token_hash->CurrentValue, $this->Dbid) . "')";
            $filterChk .= " AND NOT (" . $filter . ")";
            $this->CurrentFilter = $filterChk;
            $sqlChk = $this->getCurrentSql();
            $rsChk = $conn->executeQuery($sqlChk);
            if (!$rsChk) {
                return false;
            }
            if ($rsChk->fetch()) {
                $idxErrMsg = str_replace("%f", $this->token_hash->caption(), $Language->phrase("DupIndex"));
                $idxErrMsg = str_replace("%v", $this->token_hash->CurrentValue, $idxErrMsg);
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

        // token_hash
        $this->token_hash->setDbValueDef($rsnew, $this->token_hash->CurrentValue, $this->token_hash->ReadOnly);

        // user_id
        $this->user_id->setDbValueDef($rsnew, $this->user_id->CurrentValue, $this->user_id->ReadOnly);

        // system
        $this->system->setDbValueDef($rsnew, $this->system->CurrentValue, $this->system->ReadOnly);

        // issued_at
        $this->issued_at->setDbValueDef($rsnew, UnFormatDateTime($this->issued_at->CurrentValue, $this->issued_at->formatPattern()), $this->issued_at->ReadOnly);

        // expires_at
        $this->expires_at->setDbValueDef($rsnew, UnFormatDateTime($this->expires_at->CurrentValue, $this->expires_at->formatPattern()), $this->expires_at->ReadOnly);

        // client_ip
        $this->client_ip->setDbValueDef($rsnew, $this->client_ip->CurrentValue, $this->client_ip->ReadOnly);

        // user_agent
        $this->user_agent->setDbValueDef($rsnew, $this->user_agent->CurrentValue, $this->user_agent->ReadOnly);

        // revoked_at
        $this->revoked_at->setDbValueDef($rsnew, UnFormatDateTime($this->revoked_at->CurrentValue, $this->revoked_at->formatPattern()), $this->revoked_at->ReadOnly);

        // revoke_reason
        $this->revoke_reason->setDbValueDef($rsnew, $this->revoke_reason->CurrentValue, $this->revoke_reason->ReadOnly);
        return $rsnew;
    }

    /**
     * Restore edit form from row
     * @param array $row Row
     */
    protected function restoreEditFormFromRow($row)
    {
        if (isset($row['token_hash'])) { // token_hash
            $this->token_hash->CurrentValue = $row['token_hash'];
        }
        if (isset($row['user_id'])) { // user_id
            $this->user_id->CurrentValue = $row['user_id'];
        }
        if (isset($row['system'])) { // system
            $this->system->CurrentValue = $row['system'];
        }
        if (isset($row['issued_at'])) { // issued_at
            $this->issued_at->CurrentValue = $row['issued_at'];
        }
        if (isset($row['expires_at'])) { // expires_at
            $this->expires_at->CurrentValue = $row['expires_at'];
        }
        if (isset($row['client_ip'])) { // client_ip
            $this->client_ip->CurrentValue = $row['client_ip'];
        }
        if (isset($row['user_agent'])) { // user_agent
            $this->user_agent->CurrentValue = $row['user_agent'];
        }
        if (isset($row['revoked_at'])) { // revoked_at
            $this->revoked_at->CurrentValue = $row['revoked_at'];
        }
        if (isset($row['revoke_reason'])) { // revoke_reason
            $this->revoke_reason->CurrentValue = $row['revoke_reason'];
        }
    }

    // Set up Breadcrumb
    protected function setupBreadcrumb()
    {
        global $Breadcrumb, $Language;
        $Breadcrumb = new Breadcrumb("index");
        $url = CurrentUrl();
        $Breadcrumb->add("list", $this->TableVar, $this->addMasterUrl("JwtSessionsList"), "", $this->TableVar, true);
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
