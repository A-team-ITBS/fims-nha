<?php

namespace PHPMaker2024\FIMS;

/**
 * Pager class
 */
class Pager
{
    public $NextButton;
    public $FirstButton;
    public $PrevButton;
    public $LastButton;
    public $PageSize;
    public $FromIndex;
    public $ToIndex;
    public $RecordCount;
    public $Range;
    public $Visible = true;
    public $AutoHidePager = true;
    public $AutoHidePageSizeSelector = true;
    public $UsePageSizeSelector = true;
    public $PageSizes;
    public $ItemPhraseId = "Record";
    public $Table;
    public $PageNumberName;
    public $PagePhraseId = "Page";
    public $ContextClass = "";
    private $PageSizeAll = false; // Handle page size = -1 (ALL)
    public static $FormatIntegerFunc = PROJECT_NAMESPACE . "FormatInteger";

    // Constructor
    public function __construct($table, $fromIndex, $pageSize, $recordCount, $pageSizes = "", $range = 10, $autoHidePager = null, $autoHidePageSizeSelector = null, $usePageSizeSelector = null)
    {
        $this->Table = $table;
        $this->ContextClass = CheckClassName($this->Table->TableVar);
        $this->AutoHidePager = $autoHidePager === null ? Config("AUTO_HIDE_PAGER") : $autoHidePager;
        $this->AutoHidePageSizeSelector = $autoHidePageSizeSelector === null ? Config("AUTO_HIDE_PAGE_SIZE_SELECTOR") : $autoHidePageSizeSelector;
        $this->UsePageSizeSelector = $usePageSizeSelector === null ? true : $usePageSizeSelector;
        $this->FromIndex = (int)$fromIndex;
        $this->PageSize = (int)$pageSize;
        $this->RecordCount = (int)$recordCount;
        $this->Range = (int)$range;
        $this->PageSizes = $pageSizes;
        // Handle page size = 0
        if ($this->PageSize == 0) {
            $this->PageSize = $this->RecordCount > 0 ? $this->RecordCount : 10;
        }
        // Handle page size = -1 (ALL)
        if ($this->PageSize == -1 || $this->PageSize == $this->RecordCount) {
            $this->PageSizeAll = true;
            $this->PageSize = $this->RecordCount > 0 ? $this->RecordCount : 10;
        }
        $this->PageNumberName = Config("TABLE_PAGE_NUMBER");
    }

    // Is visible
    public function isVisible()
    {
        return $this->RecordCount > 0 && $this->Visible;
    }

    // Render
    public function render()
    {
        global $Language;
        $html = "";
        $formatInteger = self::$FormatIntegerFunc;
        if ($this->isVisible()) {
            // Do not show record numbers for View/Edit page
            if ($this->PagePhraseId !== "Record") {
                $html .= <<<RECORD
                    <div class="ew-pager ew-rec">
                        <div class="d-inline-flex">
                            <div class="ew-pager-rec me-1">{$Language->phrase($this->ItemPhraseId)}</div>
                            <div class="ew-pager-start me-1">{$formatInteger($this->FromIndex)}</div>
                            <div class="ew-pager-to me-1">{$Language->phrase("To")}</div>
                            <div class="ew-pager-end me-1">{$formatInteger($this->ToIndex)}</div>
                            <div class="ew-pager-of me-1">{$Language->phrase("Of")}</div>
                            <div class="ew-pager-count me-1" data-count="' . $this->RecordCount . '">{$formatInteger($this->RecordCount)}</div>
                        </div>
                    </div>
                    RECORD;
            }
        }
        // Page size selector
        if ($this->UsePageSizeSelector && !empty($this->PageSizes) && !($this->AutoHidePageSizeSelector && $this->RecordCount <= $this->PageSize)) {
            $pageSizes = explode(",", $this->PageSizes);
            $optionsHtml = "";
            foreach ($pageSizes as $pageSize) {
                if (intval($pageSize) > 0) {
                    $optionsHtml .= '<option value="' . $pageSize . '"' . ($this->PageSize == $pageSize ? ' selected' : '') . '>' . $formatInteger($pageSize) . '</option>';
                } else {
                    $optionsHtml .= '<option value="ALL"' . ($this->PageSizeAll ? ' selected' : '') . '>' . $Language->phrase("AllRecords") . '</option>';
                }
            };
            $tableRecPerPage = Config("TABLE_REC_PER_PAGE");
            $url = CurrentDashboardPageUrl();
            $useAjax = $this->Table->UseAjaxActions;
            $ajax = $useAjax ? "true" : "false";
            $html .= <<<SELECTOR
                <div class="ew-pager">
                <select name="{$tableRecPerPage}" class="form-select form-select-sm ew-tooltip" title="{$Language->phrase("RecordsPerPage")}" data-ew-action="change-page-size" data-ajax="{$ajax}" data-url="{$url}">
                {$optionsHtml}
                </select>
                </div>
                SELECTOR;
        }
        return $html;
    }
}
