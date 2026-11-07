<?php

namespace PHPMaker2024\FIMS;

/**
 * Summary field class
 */
class SummaryField
{
    public $SummaryCaption;
    public $SummaryViewAttrs;
    public $SummaryLinkAttrs;
    public $SummaryCurrentValues;
    public $SummaryViewValues;
    public $SummaryValues;
    public $SummaryValueCounts;
    public $SummaryGroupValues;
    public $SummaryGroupValueCounts;
    public $SummaryInitValue;
    public $SummaryRowSummary;
    public $SummaryRowCount;

    // Constructor
    public function __construct(
        public $FieldVar, // Field variable name
        public $Name, // Field name
        public $Expression, // Field expression (used in SQL)
        public $SummaryType,
    ) {
    }

    // Summary view attributes
    public function summaryViewAttributes($i)
    {
        if (is_array($this->SummaryViewAttrs)) {
            $attrs = $this->SummaryViewAttrs[$i] ?? null;
            if (is_array($attrs)) {
                $att = new Attributes($attrs);
                return $att->toString();
            }
        }
        return "";
    }

    // Summary link attributes
    public function summaryLinkAttributes($i)
    {
        if (is_array($this->SummaryLinkAttrs)) {
            $attrs = $this->SummaryLinkAttrs[$i] ?? null;
            if (is_array($attrs)) {
                $att = new Attributes($attrs);
                $att->checkLinkAttributes();
                return $att->toString();
            }
        }
        return "";
    }
}
