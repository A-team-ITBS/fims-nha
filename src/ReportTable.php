<?php

namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Report table class
 */
class ReportTable extends DbTableBase
{
    public $ReportSourceTable;
    public $RowTotalType; // Row summary type
    public $RowTotalSubType; // Row total type
    public $RowGroupLevel; // Row group level
    public $ShowReport = true;

    // Constructor
    public function __construct()
    {
        parent::__construct();
        $this->ShowDrillDownFilter = Config("SHOW_DRILLDOWN_FILTER");
        $this->UseDrillDownPanel = Config("USE_DRILLDOWN_PANEL");
    }

    // Session Group Per Page
    public function getGroupPerPage()
    {
        return Session(PROJECT_NAME . "_" . $this->TableVar . "_grpperpage");
    }

    public function setGroupPerPage($v)
    {
        $_SESSION[PROJECT_NAME . "_" . $this->TableVar . "_grpperpage"] = $v;
    }

    // Session Start Group
    public function getStartGroup()
    {
        return Session(PROJECT_NAME . "_" . $this->TableVar . "_start");
    }

    public function setStartGroup($v)
    {
        $_SESSION[PROJECT_NAME . "_" . $this->TableVar . "_start"] = $v;
    }

    // Session Order By
    public function getOrderBy()
    {
        return Session(PROJECT_NAME . "_" . $this->TableVar . "_orderby");
    }

    public function setOrderBy($v)
    {
        $_SESSION[PROJECT_NAME . "_" . $this->TableVar . "_orderby"] = $v;
    }

    // Session Order By (for non-grouping fields)
    public function getDetailOrderBy()
    {
        return Session(PROJECT_NAME . "_" . $this->TableVar . "_detailorderby");
    }

    public function setDetailOrderBy($v)
    {
        $_SESSION[PROJECT_NAME . "_" . $this->TableVar . "_detailorderby"] = $v;
    }

    // Reset attributes for table object
    public function resetAttributes()
    {
        $this->RowAttrs = new Attributes();
        foreach ($this->Fields as $fld) {
            $fld->resetAttributes();
        }
    }

    /**
     * Build Report SQL
     *
     * @param string|QueryBuilder $select
     * @param string $from
     * @param string $where
     * @param string $groupBy
     * @param string $having
     * @param string $orderBy
     * @param string $filter
     * @param string $sort
     * @return QueryBuilder
     */
    public function buildReportSql($select, $from, $where, $groupBy, $having, $orderBy, $filter, $sort)
    {
        if (is_string($select)) {
            $queryBuilder = $this->getQueryBuilder()->select($select);
        } elseif ($select instanceof QueryBuilder) {
            $queryBuilder = $select;
        }
        if ($from != "") {
            $queryBuilder->resetQueryPart("from")->from($from);
        }
        if ($where != "") {
            $queryBuilder->where($where);
        }
        if ($filter != "") {
            $queryBuilder->andWhere($filter);
        }
        if ($groupBy != "") {
            $queryBuilder->groupBy($groupBy);
        }
        if ($having != "") {
            $queryBuilder->having($having);
        }
        $flds = UpdateSortFields($orderBy, $sort, 1);
        if (is_array($flds)) {
            foreach ($flds as $fld) {
                $queryBuilder->addOrderBy($fld[0], $fld[1]);
            }
        }
        return $queryBuilder;
    }

    /**
     * Report row attributes
     *
     * @return string Row Attributes
     */
    public function rowAttributes()
    {
        $level = $this->hideGroupLevel();
        $hide = $level > 0;
        if ($hide && $this->RowGroupLevel == $level && $this->RowType == RowType::TOTAL && $this->RowTotalSubType == RowTotal::HEADER) { // Do not hide current grouping header
            $hide = false;
        }
        if ($hide) {
            $this->RowAttrs->appendClass("ew-rpt-grp-hide-" . $level);
        }
        $attrs = parent::rowAttributes();
        if ($hide) {
            $this->RowAttrs->removeClass("ew-rpt-grp-hide-" . $level);
        }
        return $attrs;
    }

    /**
     * Hide group level
     *
     * @return int Hide group level
     */
    public function hideGroupLevel()
    {
        $fields = array_filter($this->Fields, fn($fld) => $fld->GroupingFieldId > 0); // Get all grouping fields
        usort($fields, fn($f1, $f2) => $f1->GroupingFieldId - $f2->GroupingFieldId); // Sort by GroupingFieldId
        foreach ($fields as $fld) {
            if (!$fld->Expanded && $fld->GroupingFieldId <= $this->RowGroupLevel) {
                return $fld->GroupingFieldId;
            }
        }
        return 0;
    }
}
