<?php

namespace PHPMaker2024\FIMS;

/**
 * List action class
 */
class ListAction
{
    protected $language;

    // Constructor
    public function __construct(
        public string $Action = "",
        public string $Caption = "",
        public bool $Allowed = true,
        public string $Method = ACTION_POSTBACK, // Post back (p) / Ajax (a)
        public string $Select = ACTION_MULTIPLE, // Multiple (m) / Single (s) / Custom (c)
        public string $ConfirmMessage = "", // Message or Swal config
        public string $Icon = "fa-solid fa-star ew-icon", // Icon
        public string $Success = "", // JavaScript callback function name
        public mixed $Handler = null, // PHP callable to handle the action
        public string $SuccessMessage = "", // Default success message
        public string $FailureMessage = "", // Default failure message
    ) {
        $this->language = Container("app.language");
    }

    // Handle the action
    public function handle(array $row, object $listPage): bool
    {
        if (is_callable($this->Handler)) {
            $handler = $this->Handler;
            return $handler($row, $listPage);
        }
        return true;
    }

    // To JSON
    public function toJson(bool $htmlEncode = false): string
    {
        $json = JsonEncode([
            "msg" => $this->ConfirmMessage,
            "action" => $this->Action,
            "method" => $this->Method,
            "select" => $this->Select,
            "success" => $this->Success
        ]);
        return $htmlEncode ? HtmlEncode($json) : $json;
    }

    // To data-* attributes
    public function toDataAttributes(): string
    {
        return (new Attributes([
            "data-msg" => HtmlEncode($this->ConfirmMessage),
            "data-action" => HtmlEncode($this->Action),
            "data-method" => HtmlEncode($this->Method),
            "data-select" => HtmlEncode($this->Select),
            "data-success" => HtmlEncode($this->Success)
        ]))->toString();
    }
}
