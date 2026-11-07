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
class Logout
{
    use MessagesTrait;

    // Page ID
    public $PageID = "logout";

    // Project ID
    public $ProjectID = PROJECT_ID;

    // Table name
    public $TableName;

    // Table variable
    public $TableVar;

    // Page object name
    public $PageObjName = "Logout";

    // View file path
    public $View = null;

    // Title
    public $Title = null; // Title for <title> tag

    // Rendering View
    public $RenderingView = false;

    // CSS class/style
    public $CurrentPageName = "logout";

    // Page headings
    public $Heading = "";
    public $Subheading = "";

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

    // Constructor
    public function __construct()
    {
        global $Language, $DashboardReport, $DebugTimer, $UserTable;

        // Initialize
        $GLOBALS["Page"] = &$this;

        // Language object
        $Language = Container("app.language");

        // Start timer
        $DebugTimer = Container("debug.timer");

        // Debug message
        LoadDebugMessage();

        // Open connection
        $GLOBALS["Conn"] ??= GetConnection();

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
            SaveDebugMessage();
            Redirect(GetUrl($url));
        }
        return; // Return to controller
    }

    /**
     * Page run
     *
     * @return void
     */
    public function run()
    {
        global $ExportType, $Language, $Security, $CurrentForm;

        // Use layout
        $this->UseLayout = $this->UseLayout && ConvertToBool(Param(Config("PAGE_LAYOUT"), true));

        // View
        $this->View = Get(Config("VIEW"));

        // Load user profile
        if (IsLoggedIn()) {
            Profile()->setUserName(CurrentUserName())->loadFromStorage();
        }

        // Global Page Loading event (in userfn*.php)
        DispatchEvent(new PageLoadingEvent($this), PageLoadingEvent::NAME);

        // Page Load event
        if (method_exists($this, "pageLoad")) {
            $this->pageLoad();
        }
        $validate = true;
        $username = $Security->currentUserName();

        // Call User LoggingOut event
        $validate = $this->userLoggingOut($username);
        if (!$validate) {
            $lastUrl = $Security->lastUrl();
            if ($lastUrl == "") {
                $lastUrl = "index";
            }
            $this->terminate($lastUrl); // Go to last accessed URL
            return;
        } else {
            $params = $_GET;
            $flash = Container("app.flash");

            // Remove cookie
            RemoveCookie("LastUrl"); // Clear last URL

            // Clear jwt from AutoLogin Cookie
            if ($jwt = ReadCookie("AutoLogin")) {
                WriteCookie(
                    "AutoLogin",
                    CreateJwt(["username" => DecodeJwt($jwt)["username"] ?? ""], Config("REMEMBER_ME_EXPIRY_TIME")),
                    time() + Config("REMEMBER_ME_EXPIRY_TIME")
                ); // Write cookie without autologin
            }

            // Password changed (after expired password)
            $isPasswordChanged = Config("USE_TWO_FACTOR_AUTHENTICATION") && Session(SESSION_STATUS) == "passwordchanged";

            // Load user profile
            if (IsLoggedIn()) {
                Profile()->setUserName(CurrentUserName())->loadFromStorage();
                // Check force logout / session expired
                $isForceLogout = Profile()->isForceLogout(session_id());
                $isValidUser = Profile()->isValidUser(session_id(), false);
                // Clear session ID
                Profile()->removeUser(session_id());
            } else {
                $isForceLogout = false;
                $isValidUser = true; // Anonymous user
            }
            $this->writeAuditTrailOnLogout();

            // Call User LoggedOut event
            $this->userLoggedOut($username);

            // Clean upload temp folder
            CleanUploadTempPaths(session_id());

            // Invalidate Laravel session first
            LaravelSession()?->invalidate();

            // Unset all of the session variables
            $_SESSION = [];
            if ($params["deleted"] ?? false) {
                $flash->addMessage("heading", $Language->phrase("Notice"));
                $flash->addMessage("success", $Language->phrase("PersonalDataDeleteSuccess"));
                $isValidUser = true;
            }

            // If force logout / session expired, show message
            if ($isForceLogout) {
                $flash->addMessage("heading", $Language->phrase("Notice"));
                $flash->addMessage("failure", $Language->phrase("UserForceLogout"));
            } elseif (!$isValidUser) {
                $flash->addMessage("heading", $Language->phrase("Notice"));
                $flash->addMessage("failure", $Language->phrase("SessionExpired"));
            }

            // If password changed, show login message
            if ($isPasswordChanged) {
                $flash->addMessage("heading", $Language->phrase("Notice"));
                $flash->addMessage("failure", $Language->phrase("LoginAfterPasswordChanged"));
            }

            // If session expired, show expired message
            if ($params["expired"] ?? false) {
                $flash->addMessage("heading", $Language->phrase("Notice"));
                $flash->addMessage("failure", $Language->phrase("SessionExpired"));
            }
            session_write_close();

            // Delete the session cookie and kill the session
            RemoveCookie(session_name());

            // Remove user and profile
            Container(["app.user" => null, "user.profile" => null]);

            // Go to login page
            $this->terminate("login");
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

    // Write audit trail on logout
    protected function writeAuditTrailOnLogout()
    {
        global $Language;
        WriteAuditLog(CurrentUserIdentifier(), $Language->phrase("AuditTrailLogout"), CurrentUserIP());
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

    // User Logging Out event
    public function userLoggingOut($usr)
    {
        // Enter your code here
        // To cancel, set return value to false;
        return true;
    }

    // User Logged Out event
    public function userLoggedOut($usr)
    {
        //Log("User Logged Out");
    }
}
