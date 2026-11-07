<?php

namespace PHPMaker2024\FIMS;

/**
 * Reset Login Retry Action class
 */
class ResetLoginRetryAction extends ListAction
{
    // Constructor
    public function __construct(
        public string $Action = "resetloginretry",
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
        $this->Caption = $this->language->phrase("ResetLoginRetryBtn");
        $this->SuccessMessage = $this->language->phrase("ResetLoginRetrySuccess");
        $this->FailureMessage = $this->language->phrase("ResetLoginRetryFailure");
        $this->Allowed = IsAdmin();
    }

    // Handle the action
    public function handle(array $row, object $listPage): bool
    {
        if ($listPage->TableName == Config("USER_TABLE_NAME")) {
            return UserProfile::create()
                ->setUserName($row[Config("LOGIN_USERNAME_FIELD_NAME")])
                ->load(HtmlDecode($row[Config("USER_PROFILE_FIELD_NAME")] ?? ""))
                ->resetLoginRetry();
        }
        return false;
    }
}
