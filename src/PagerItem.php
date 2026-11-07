<?php

namespace PHPMaker2024\FIMS;

/**
 * Pager item class
 */
class PagerItem
{
    /**
     * Constructor
     *
     * @param int $contextClass Context class
     * @param int $pageSize Page size
     * @param int $start Record number (1-based)
     * @param string $text Text
     * @param bool $enabled Enabled
     * @return void
     */
    public function __construct(
        public $ContextClass,
        public $PageSize,
        public $Start = 1,
        public $Text = "",
        public $Enabled = false
    ) {
    }

    /**
     * Get page number
     *
     * @return int
     */
    public function getPageNumber(): int
    {
        return ($this->PageSize > 0 && $this->Start > 0) ? ceil($this->Start / $this->PageSize) : 1;
    }

    /**
     * Get URL or query string
     *
     * @param string $url URL without query string
     * @param string $table TableVar
     * @return string
     */
    public function getUrl($url = ""): string
    {
        global $DashboardReport;
        $qs = Config("TABLE_PAGE_NUMBER") . "=" . $this->getPageNumber();
        if ($DashboardReport) {
            $qs .= "&" . Config("PAGE_DASHBOARD") . "=" . $DashboardReport;
        }
        return $url ? UrlAddQuery($url, $qs) : $qs;
    }

    /**
     * Get "disabled" class
     *
     * @return string
     */
    public function getDisabledClass(): string
    {
        return $this->Enabled ? "" : " disabled";
    }

    /**
     * Get "active" class
     *
     * @return string
     */
    public function getActiveClass(): string
    {
        return $this->Enabled ? "" : " active";
    }

    /**
     * Get attributes
     * - data-ew-action and data-url for normal List pages
     * - data-page for other pages
     *
     * @param string $url URL without query string
     * @param string $action Action (redirect/refresh)
     * @return string
     */
    public function getAttributes($url = "", $action = "redirect"): string
    {
        return 'data-ew-action="' . ($this->Enabled ? $action : "none") . '" data-url="' . $this->getUrl($url) . '" data-page="' . $this->getPageNumber() . '"' .
            ($this->ContextClass ? ' data-context="' . HtmlEncode($this->ContextClass) . '"' : "");
    }
}
