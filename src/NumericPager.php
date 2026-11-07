<?php

namespace PHPMaker2024\FIMS;

/**
 * Numeric pager class
 */
class NumericPager extends Pager
{
    public $Items = [];
    public $ButtonCount = 0;

    // Constructor
    public function __construct($table, $fromIndex, $pageSize, $recordCount, $pageSizes = "", $range = 10, $autoHidePager = null, $autoHidePageSizeSelector = null, $usePageSizeSelector = null)
    {
        parent::__construct($table, $fromIndex, $pageSize, $recordCount, $pageSizes, $range, $autoHidePager, $autoHidePageSizeSelector, $usePageSizeSelector);
        if ($this->AutoHidePager && $fromIndex == 1 && $recordCount <= $pageSize) {
            $this->Visible = false;
        }
        $this->FirstButton = new PagerItem($this->ContextClass, $pageSize);
        $this->PrevButton = new PagerItem($this->ContextClass, $pageSize);
        $this->NextButton = new PagerItem($this->ContextClass, $pageSize);
        $this->LastButton = new PagerItem($this->ContextClass, $pageSize);
        $this->init();
    }

    // Init pager
    protected function init()
    {
        if ($this->FromIndex > $this->RecordCount) {
            $this->FromIndex = $this->RecordCount;
        }
        $this->ToIndex = $this->FromIndex + $this->PageSize - 1;
        if ($this->ToIndex > $this->RecordCount) {
            $this->ToIndex = $this->RecordCount;
        }
        $this->setupNumericPager();

        // Update button count
        if ($this->FirstButton->Enabled) {
            $this->ButtonCount++;
        }
        if ($this->PrevButton->Enabled) {
            $this->ButtonCount++;
        }
        if ($this->NextButton->Enabled) {
            $this->ButtonCount++;
        }
        if ($this->LastButton->Enabled) {
            $this->ButtonCount++;
        }
        $this->ButtonCount += count($this->Items);
    }

    // Add pager item
    protected function addPagerItem($startIndex, $text, $enabled)
    {
        $this->Items[] = new PagerItem($this->ContextClass, $this->PageSize, $startIndex, $text, $enabled);
    }

    // Setup pager items
    protected function setupNumericPager()
    {
        if ($this->RecordCount > $this->PageSize) {
            $eof = ($this->RecordCount < ($this->FromIndex + $this->PageSize));
            $hasPrev = ($this->FromIndex > 1);

            // First Button
            $tempIndex = 1;
            $this->FirstButton->Start = $tempIndex;
            $this->FirstButton->Enabled = ($this->FromIndex > $tempIndex);

            // Prev Button
            $tempIndex = $this->FromIndex - $this->PageSize;
            if ($tempIndex < 1) {
                $tempIndex = 1;
            }
            $this->PrevButton->Start = $tempIndex;
            $this->PrevButton->Enabled = $hasPrev;

            // Page links
            if ($hasPrev || !$eof) {
                $x = 1;
                $y = 1;
                $dx1 = (int)(($this->FromIndex - 1) / ($this->PageSize * $this->Range)) * $this->PageSize * $this->Range + 1;
                $dy1 = (int)(($this->FromIndex - 1) / ($this->PageSize * $this->Range)) * $this->Range + 1;
                if (($dx1 + $this->PageSize * $this->Range - 1) > $this->RecordCount) {
                    $dx2 = (int)($this->RecordCount / $this->PageSize) * $this->PageSize + 1;
                    $dy2 = (int)($this->RecordCount / $this->PageSize) + 1;
                } else {
                    $dx2 = $dx1 + $this->PageSize * $this->Range - 1;
                    $dy2 = $dy1 + $this->Range - 1;
                }
                while ($x <= $this->RecordCount) {
                    if ($x >= $dx1 && $x <= $dx2) {
                        $this->addPagerItem($x, $y, $this->FromIndex != $x);
                        $x += $this->PageSize;
                        $y++;
                    } elseif ($x >= ($dx1 - $this->PageSize * $this->Range) && $x <= ($dx2 + $this->PageSize * $this->Range)) {
                        if ($x + $this->Range * $this->PageSize < $this->RecordCount) {
                            $this->addPagerItem($x, $y . "-" . ($y + $this->Range - 1), true);
                        } else {
                            $ny = (int)(($this->RecordCount - 1) / $this->PageSize) + 1;
                            if ($ny == $y) {
                                $this->addPagerItem($x, $y, true);
                            } else {
                                $this->addPagerItem($x, $y . "-" . $ny, true);
                            }
                        }
                        $x += $this->Range * $this->PageSize;
                        $y += $this->Range;
                    } else {
                        $x += $this->Range * $this->PageSize;
                        $y += $this->Range;
                    }
                }
            }

            // Next Button
            $tempIndex = $this->FromIndex + $this->PageSize;
            $this->NextButton->Start = $tempIndex;
            $this->NextButton->Enabled = !$eof;

            // Last Button
            $tempIndex = (int)(($this->RecordCount - 1) / $this->PageSize) * $this->PageSize + 1;
            $this->LastButton->Start = $tempIndex;
            $this->LastButton->Enabled = ($this->FromIndex < $tempIndex);
        }
    }

    // Render
    public function render()
    {
        global $Language;
        $html = "";
        $url = CurrentDashboardPageUrl();
        $useAjax = $this->Table->UseAjaxActions;
        $action = $useAjax ? "refresh" : "redirect";
        if ($this->isVisible()) {
            $html .= '<li class="page-item' . $this->FirstButton->getDisabledClass() . '"><a class="page-link" data-value="first" ' . $this->FirstButton->getAttributes($url, $action) . ' aria-label="' . $Language->phrase("PagerFirst") . '"><i class="fa-solid fa-angles-left"></i></a></li>';
            $html .= '<li class="page-item' . $this->PrevButton->getDisabledClass() . '"><a class="page-link" data-value="prev" ' . $this->PrevButton->getAttributes($url, $action) . ' aria-label="' . $Language->phrase("PagerPrevious") . '"><i class="fa-solid fa-angle-left"></i></a></li>';
            foreach ($this->Items as $pagerItem) {
                $html .= '<li class="page-item' . $pagerItem->getActiveClass() . '"><a class="page-link" ' . $pagerItem->getAttributes($url, $action) . '>' . FormatInteger($pagerItem->Text) . '</a></li>';
            }
            $html .= '<li class="page-item' . $this->NextButton->getDisabledClass() . '"><a class="page-link" data-value="next" ' . $this->NextButton->getAttributes($url, $action) . ' aria-label="' . $Language->phrase("PagerNext") . '"><i class="fa-solid fa-angle-right"></i></a></li>';
            $html .= '<li class="page-item' . $this->LastButton->getDisabledClass() . '"><a class="page-link" data-value="last" ' . $this->LastButton->getAttributes($url, $action) . ' aria-label="' . $Language->phrase("PagerLast") . '"><i class="fa-solid fa-angles-right"></i></a></li>';
            $html = <<<PAGER
                <div class="ew-pager">
                    <div class="ew-numeric-page">
                        <ul class="pagination pagination-sm">
                        {$html}
                        </ul>
                    </div>
                </div>
                PAGER;
        }
        $html .= parent::render();
        return $html;
    }
}
