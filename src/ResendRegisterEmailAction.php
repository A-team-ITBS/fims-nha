<?php

namespace PHPMaker2024\FIMS;

/**
 * Resend Register Email Action class
 */
class ResendRegisterEmailAction extends ListAction
{
    // Constructor
    public function __construct(
        public string $Action = "resendregisteremail",
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
        $this->Caption = $this->language->phrase("ResendRegisterEmailBtn");
        $this->SuccessMessage = $this->language->phrase("ResendRegisterEmailSuccess");
        $this->FailureMessage = $this->language->phrase("ResendRegisterEmailFailure");
        $this->Allowed = IsAdmin();
    }

    // Handle the action
    public function handle(array $row, object $listPage): bool
    {
        if (method_exists($listPage, "sendRegisterEmail")) {
            return $listPage->sendRegisterEmail($row);
        }
        return false;
    }
}
