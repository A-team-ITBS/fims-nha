<?php

namespace PHPMaker2024\FIMS;

/**
 * Force Logout User Action class
 */
class ForceLogoutUserAction extends ListAction
{
    // Constructor
    public function __construct(
        public string $Action = "forcelogoutuser",
        public string $Caption = "",
        public bool $Allowed = true,
        public string $Method = ACTION_AJAX, // Post back (p) / Ajax (a)
        public string $Select = ACTION_SINGLE, // Multiple (m) / Single (s)
        public string $ConfirmMessage = "", // Message or Swal config
        public string $Icon = "fa-solid fa-star ew-icon", // Icon
        public string $Success = "", // JavaScript callback function name
        public mixed $Handler = null, // PHP callable to handle the action
        public string $SuccessMessage = "", // Default success message
        public string $FailureMessage = "", // Default failure message
    ) {
        $this->language = Container("app.language");
        $this->Caption = $this->language->phrase("ForceLogoutUserBtn");
        $this->SuccessMessage = $this->language->phrase("ForceLogoutUserSuccess");
        $this->FailureMessage = $this->language->phrase("ForceLogoutUserFailure");
        $this->Allowed = IsAdmin();
    }

    // Handle the action
    public function handle(array $row, object $listPage): bool
    {
        if ($listPage->TableName == Config("USER_TABLE_NAME") && UserProfile::$FORCE_LOGOUT_USER) {
            $user = $row[Config("LOGIN_USERNAME_FIELD_NAME")];
            $result = UserProfile::create()
                ->setUserName($user)
                ->load(HtmlDecode($row[Config("USER_PROFILE_FIELD_NAME")] ?? ""))
                ->forceLogoutUser();
            if ($result) {
                WriteJson(["successMessage" => str_replace("%u", $user, $this->SuccessMessage), "disabled" => true]); // Disable the button
            } else {
                WriteJson(["failureMessage" => str_replace("%u", $user, $this->FailureMessage)]);
            }
            return $result;
        }
        return false;
    }
}
