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
class ResetPassword extends Users
{
    use MessagesTrait;

    // Page ID
    public $PageID = "reset_password";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Page object name
    public $PageObjName = "ResetPassword";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "resetpassword";

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

    // Constructor
    public function __construct()
    {
        parent::__construct();
        global $Language, $DashboardReport, $DebugTimer, $UserTable;
        $this->TableVar = 'users';
        $this->TableName = 'users';

        // Table CSS class
        $this->TableClass = "table table-striped table-bordered table-hover table-sm ew-view-table";

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Table object (users)
        if (!isset($GLOBALS["users"]) || $GLOBALS["users"]::class == PROJECT_NAMESPACE . "users") {
            $GLOBALS["users"] = &$this;
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
                WriteJson(["url" => $url]);
            } else {
                SaveDebugMessage();
                Redirect(GetUrl($url));
            }
        }
        return; // Return to controller
    }
    public $Email;
    public $IsModal = false;
    public $OffsetColumnClass = ""; // Override user table

    /**
     * Page run
     *
     * @return void
     */
    public function run()
    {
        global $ExportType, $Language, $Security, $CurrentForm, $Breadcrumb, $SkipHeaderFooter;

        // Create Email field object (used by validation only)
        $this->Email = new DbField(Container("usertable"), "email", "email", "email", "", 202, 255, -1, false, "", false, false, false);
        $this->Email->EditAttrs->appendClass("form-control ew-form-control");

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
        }
        $this->CurrentAction = Param("action"); // Set up current action

        // Global Page Loading event (in userfn*.php)
        DispatchEvent(new PageLoadingEvent($this), PageLoadingEvent::NAME);

        // Page Load event
        if (method_exists($this, "pageLoad")) {
            $this->pageLoad();
        }

        // Check modal
        if ($this->IsModal) {
            $SkipHeaderFooter = true;
        }
        $Breadcrumb = Breadcrumb::create("index")->add("reset_password", "ResetPwd", CurrentUrl(), "", "", true);
        $this->Heading = $Language->phrase("ResetPwd");
        $postBack = IsPost();
        $validEmail = false;
        $action = "";
        $userName = "";
        $activateCode = "";
        $filter = null;
        if ($postBack) {
            // Setup variables
            $this->Email->setFormValue(Post($this->Email->FieldVar));
            $validEmail = $this->validateForm();
            if ($validEmail) {
                $action = "reset"; // Prompt user to change password
            }

            // Set up filter
            if (Container("usertable")->Fields[Config("USER_EMAIL_FIELD_NAME")]?->isEncrypt()) { // If encrypted, need to loop through all records
                $filter = null;
            } else {
                $filter = [Config("USER_EMAIL_PROPERTY_NAME") => $this->Email->CurrentValue];
            }

        // Handle email activation
        } elseif (Get("action") != "") {
            $action = Get("action");
            $userName = Get("user");
            $activateCode = Decrypt(Get("code"));
            @list($activateUserName, $dt) = explode(",", $activateCode);
            if (
                $userName != $activateUserName ||
                EmptyValue($dt) ||
                DateDiff($dt, StdCurrentDateTime(), "n") < 0 ||
                DateDiff($dt, StdCurrentDateTime(), "n") > Config("RESET_PASSWORD_TIME_LIMIT") ||
                !SameText($action, "reset")
            ) { // Email activation
                if ($this->getFailureMessage() == "") {
                    $this->setFailureMessage($Language->phrase("ActivateFailed")); // Set activate failed message
                }
                $this->terminate("login"); // Go to login page
                return;
            }
            if (SameText($action, "reset")) {
                $action = "resetpassword";
            }
            $filter = [Config("LOGIN_USERNAME_PROPERTY_NAME") => $userName];
        }
        if ($action != "") {
            if ($this->UpdateTable != $this->TableName && $this->UpdateTable != $this->getSqlFrom()) { // Note: The username field name must be the same
                $entityClass = GetEntityClass($this->UpdateTable);
                if ($entityClass) {
                    $userRepository = GetUserEntityManager()->getRepository($entityClass);
                } else {
                    throw new \Exception("Entity class for UpdateTable not found.");
                }
            } else {
                $userRepository = GetUserRepository();
            }
            $users = $filter ? $userRepository->findBy($filter) : $userRepository->findAll();
            if ($users) {
                $validEmail = false;
                foreach ($users as $user) {
                    if ($action == "resetpassword") { // Check username if email activation
                        $validEmail = SameString($userName, $user->get(Config("LOGIN_USERNAME_FIELD_NAME")));
                    } else {
                        $validEmail = SameText($this->Email->CurrentValue, $user->get(Config("USER_EMAIL_FIELD_NAME")));
                    }
                    if ($validEmail) {
                        // Call User Recover Password event
                        $validEmail = $this->userRecoverPassword($user->toArray());
                        if ($validEmail) {
                            $userName = $user->get(Config("LOGIN_USERNAME_FIELD_NAME"));
                            $password = $user->get(Config("LOGIN_PASSWORD_FIELD_NAME"));
                        }
                    }
                    if ($validEmail) {
                        break;
                    }
                }
                if ($validEmail) {
                    if (SameText($action, "resetpassword")) { // Reset password
                        $_SESSION[SESSION_USER_PROFILE_USER_NAME] = $userName; // Save login user name
                        $_SESSION[SESSION_STATUS] = "passwordreset";
                        $this->terminate("changepassword");
                        return;
                    } else {
                        $emailSent = false;
                        $activateLink = FullUrl("", "resetpwd") . "?action=reset&user=" . rawurlencode($userName) .
                            "&code=" . Encrypt($userName . "," . StdCurrentDateTime());
                        $email = new Email();
                        $email->load(Config("EMAIL_RESET_PASSWORD_TEMPLATE"), data: [
                            "From" => Config("SENDER_EMAIL"), // Replace Sender
                            "To" => $this->Email->CurrentValue, // Replace Sender
                            "ActivateLink" => $activateLink,
                            "UserName" => $userName
                        ]);
                        $args = ["rs" => $user->toArray()];
                        if ($this->emailSending($email, $args)) {
                            $emailSent = $email->send();
                        }
                        if (!$emailSent) {
                            $this->setFailureMessage($email->SendErrDescription); // Set up error message
                        }
                    }
                }
            }
            $this->setSuccessMessage($Language->phrase("ResetPasswordResponse")); // Set up success message
            $this->terminate("login"); // Return to login page
            return;
        }

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

    // Validate form
    protected function validateForm()
    {
        global $Language;

        // Check if validation required
        if (!Config("SERVER_VALIDATE")) {
            return true;
        }
        $validateForm = true;
        if (EmptyValue($this->Email->CurrentValue)) {
            $this->Email->addErrorMessage(str_replace("%s", $Language->phrase("Email"), $Language->phrase("EnterRequiredField")));
            $validateForm = false;
        }
        if (!CheckEmail($this->Email->CurrentValue)) {
            $this->Email->addErrorMessage($Language->phrase("IncorrectEmail"));
            $validateForm = false;
        }

        // Call Form Custom Validate event
        $formCustomError = "";
        $validateForm = $validateForm && $this->formCustomValidate($formCustomError);
        if ($formCustomError != "") {
            $this->setFailureMessage($formCustomError);
        }
        return $validateForm;
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
    // $type = ''|'success'|'failure'
    public function messageShowing(&$msg, $type)
    {
        // Example:
        //if ($type == "success") $msg = "your success message";
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

    // Email Sending event
    public function emailSending($email, $args)
    {
        //var_dump($email, $args); exit();
        return true;
    }

    // Form Custom Validate event
    public function formCustomValidate(&$customError)
    {
        // Return error message in $customError
        return true;
    }

    // User RecoverPassword event
    public function userRecoverPassword($rs)
    {
        // Return false to abort
        return true;
    }
}
