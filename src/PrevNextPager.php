<?php

namespace PHPMaker2024\FIMS;

/**
 * PrevNext pager class
 */
class PrevNextPager extends Pager
{
    public $PageCount;
    public $CurrentPageNumber;
    public $Modal;
    public $Url;

    // Constructor
    public function __construct($table, $fromIndex, $pageSize, $recordCount, $pageSizes = "", $range = 10, $autoHidePager = null, $autoHidePageSizeSelector = null, $usePageSizeSelector = null, $isModal = false, $url = null)
    {
        parent::__construct($table, $fromIndex, $pageSize, $recordCount, $pageSizes, $range, $autoHidePager, $autoHidePageSizeSelector, $usePageSizeSelector);
        $this->FirstButton = new PagerItem($this->ContextClass, $pageSize);
        $this->PrevButton = new PagerItem($this->ContextClass, $pageSize);
        $this->NextButton = new PagerItem($this->ContextClass, $pageSize);
        $this->LastButton = new PagerItem($this->ContextClass, $pageSize);
        $this->Modal = $isModal;
        $this->Url = $url;
        $this->init();
    }

    // Init pager
    protected function init()
    {
        $this->CurrentPageNumber = (int)(($this->FromIndex - 1) / $this->PageSize) + 1;
        if ($this->CurrentPageNumber <= 0) { // Make sure page number >= 1
            $this->CurrentPageNumber = 1;
        }
        $this->PageCount = (int)(($this->RecordCount - 1) / $this->PageSize) + 1;
        if ($this->AutoHidePager && $this->PageCount == 1) {
            $this->Visible = false;
        }
        if ($this->FromIndex > $this->RecordCount) {
            $this->FromIndex = $this->RecordCount;
        }
        $this->ToIndex = $this->FromIndex + $this->PageSize - 1;
        if ($this->ToIndex > $this->RecordCount) {
            $this->ToIndex = $this->RecordCount;
        }

        // First Button
        $tempIndex = 1;
        $this->FirstButton->Start = $tempIndex;
        $this->FirstButton->Enabled = ($tempIndex != $this->FromIndex);

        // Prev Button
        $tempIndex = $this->FromIndex - $this->PageSize;
        if ($tempIndex < 1) {
            $tempIndex = 1;
        }
        $this->PrevButton->Start = $tempIndex;
        $this->PrevButton->Enabled = ($tempIndex != $this->FromIndex);

        // Next Button
        $tempIndex = $this->FromIndex + $this->PageSize;
        if ($tempIndex > $this->RecordCount) {
            $tempIndex = $this->FromIndex;
        }
        $this->NextButton->Start = $tempIndex;
        $this->NextButton->Enabled = ($tempIndex != $this->FromIndex);

        // Last Button
        $tempIndex = (int)(($this->RecordCount - 1) / $this->PageSize) * $this->PageSize + 1;
        $this->LastButton->Start = $tempIndex;
        $this->LastButton->Enabled = ($tempIndex != $this->FromIndex);
    }

    // Render
    public function render()
    {
        global $Language;
        $html = "";
        if ($this->isVisible()) {
            $url = $this->Url ?? CurrentDashboardPageUrl();
            $useAjax = $this->Table->UseAjaxActions;
            $action = $useAjax || $this->Modal ? "refresh" : "redirect";
            $firstBtn = '<button class="btn btn-default' . $this->FirstButton->getDisabledClass() . '" data-value="first" data-table="' . $this->Table->TableVar . '" title="' . $Language->phrase("PagerFirst") . '" ' . $this->FirstButton->getAttributes($url, $action) . '><i class="fa-solid fa-angles-left ew-icon"></i></button>';
            $prevBtn = '<button class="btn btn-default' . $this->PrevButton->getDisabledClass() . '" data-value="prev" data-table="' . $this->Table->TableVar . '" title="' . $Language->phrase("PagerPrevious") . '" ' . $this->PrevButton->getAttributes($url, $action) . '><i class="fa-solid fa-angle-left ew-icon"></i></button>';
            $nextBtn = '<button class="btn btn-default' . $this->NextButton->getDisabledClass() . '" data-value="next" data-table="' . $this->Table->TableVar . '" title="' . $Language->phrase("PagerNext") . '" ' . $this->NextButton->getAttributes($url, $action) . '><i class="fa-solid fa-angle-right ew-icon"></i></button>';
            $lastBtn = '<button class="btn btn-default' . $this->LastButton->getDisabledClass() . '" data-value="last" data-table="' . $this->Table->TableVar . '" title="' . $Language->phrase("PagerLast") . '" ' . $this->LastButton->getAttributes($url, $action) . '><i class="fa-solid fa-angles-right ew-icon"></i></button>';
            $formatInteger = self::$FormatIntegerFunc;
            $pagePhrase = $Language->phrase($this->PagePhraseId);
            $pageNumber = '<!-- current page number --><input class="form-control ew-page-number" type="text" data-ew-action="change-page" data-ajax="' . ($useAjax ? "true" : "false") . '" data-url="' . $url . '" data-pagesize="' . $this->PageSize . '" data-pagecount="' . $this->PageCount . '"' . ($this->ContextClass ? ' data-context="' . HtmlEncode($this->ContextClass) . '"' : "") . ' name="' . $this->PageNumberName . '" value="' . $formatInteger($this->CurrentPageNumber) . '"' . ($this->Modal ? " disabled" : "") . '>';
            $html = <<<PAGER
                <div class="ew-pager">
                    <span>{$pagePhrase}&nbsp;</span>
                    <div class="ew-prev-next">
                        <div class="input-group input-group-sm">
                            <!-- first page button -->
                            {$firstBtn}
                            <!-- previous page button -->
                            {$prevBtn}
                            {$pageNumber}
                            <!-- next page button -->
                            {$nextBtn}
                            <!-- last page button -->
                            {$lastBtn}
                        </div>
                    </div>
                    <span>&nbsp;{$Language->phrase("Of")}&nbsp;{$formatInteger($this->PageCount)}</span>
                </div>
                PAGER;
        }
        $html .= parent::render();
        return $html;
    }
}
