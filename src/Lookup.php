<?php

namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\Query\QueryBuilder;
use Exception;

/**
 * Lookup class
 */
class Lookup
{
    public static $ModalLookupSearchType = "AND"; // "AND" or "OR" or "=" or ""
    public static $ModalLookupSearchOperator = "LIKE"; // "LIKE" or "STARTS WITH" or "ENDS WITH"
    public static $KeepCrLf = false;
    public $LookupType = "";
    public $Options = null;
    public $Template = "";
    public $CurrentFilter = "";
    public $UserSelect = "";
    public $UserFilter = "";
    public $UserOrderBy = "";
    public $FilterFields = [];
    public $FilterValues = [];
    public $SearchValue = "";
    public $SearchExpression = "";
    public $PageSize = -1;
    public $Offset = -1;
    public $LookupFilter = "";
    public $RenderViewFunc = "renderListRow";
    public $RenderEditFunc = "renderEditRow";
    public $LinkTable = "";
    public $Field = null;
    public $Distinct = false;
    public $LinkField = "";
    public $DisplayFields = [];
    public $GroupByField = "";
    public $GroupByExpression = "";
    public $ParentFields = [];
    public $ChildFields = [];
    public $FilterFieldVars = [];
    public $AutoFillSourceFields = [];
    public $AutoFillTargetFields = [];
    public $IsAutoFillTargetField = false;
    public $Table = null;
    public $FormatAutoFill = false;
    public $UseParentFilter = false;
    public $LookupAllDisplayFields = false;
    private $rendering = false;
    private $cache; // Doctrine cache
    private $cacheProfile; // Doctrine cache profile

    /**
     * Constructor for the Lookup class
     *
     * @param object $field
     * @param string $linkTable
     * @param bool $distinct
     * @param string $linkField
     * @param array $displayFields
     * @param string $groupByField
     * @param string $groupByExpression
     * @param array $parentFields
     * @param array $childFields
     * @param array $filterFields
     * @param array $filterFieldVars
     * @param array $autoFillSourceFields
     * @param array $autoFillTargetFields
     * @param string $orderBy
     * @param string $template
     */
    public function __construct(
        $field,
        $linkTable,
        $distinct,
        $linkField,
        $displayFields = [],
        $groupByField = "",
        $groupByExpression = "",
        $parentFields = [],
        $childFields = [],
        $filterFields = [],
        $filterFieldVars = [],
        $autoFillSourceFields = [],
        $autoFillTargetFields = [],
        $isAutoFillTargetField = false,
        $orderBy = "",
        $template = "",
        $searchExpression = ""
    ) {
        $this->Field = $field;
        $this->LinkTable = $linkTable;
        $this->Distinct = $distinct;
        $this->LinkField = $linkField;
        $this->DisplayFields = $displayFields;
        $this->GroupByField = $groupByField;
        $this->GroupByExpression = $groupByExpression;
        $this->ParentFields = $parentFields;
        $this->ChildFields = $childFields;
        foreach ($filterFields as $filterField) {
            $this->FilterFields[$filterField] = "="; // Default filter operator
        }
        $this->FilterFieldVars = $filterFieldVars;
        $this->AutoFillSourceFields = $autoFillSourceFields;
        $this->AutoFillTargetFields = $autoFillTargetFields;
        $this->IsAutoFillTargetField = $isAutoFillTargetField;
        $this->UserOrderBy = $orderBy;
        $this->Template = $template;
        $this->SearchExpression = $searchExpression;
        $this->cache = new \Symfony\Component\Cache\Adapter\ArrayAdapter();
        $this->cacheProfile = new \Doctrine\DBAL\Cache\QueryCacheProfile(0, $this->Field->Name);
        $this->LookupAllDisplayFields = Config("LOOKUP_ALL_DISPLAY_FIELDS");
    }

    /**
     * Get lookup SQL based on current filter/lookup filter, call Lookup_Selecting if necessary
     *
     * @param bool $useParentFilter
     * @param string $currentFilter
     * @param string|callable $lookupFilter
     * @param object $page
     * @param bool $skipFilterFields
     * @return QueryBuilder
     */
    public function getSql($useParentFilter = true, $currentFilter = "", $lookupFilter = "", $page = null, $skipFilterFields = false, $clearUserFilter = false)
    {
        $this->UseParentFilter = $useParentFilter; // Save last call
        $this->CurrentFilter = $currentFilter;
        $this->LookupFilter = $lookupFilter; // Save last call
        if ($clearUserFilter) {
            $this->UserFilter = "";
        }
        $filter = $this->getWhere($useParentFilter);
        $newFilter = $filter;
        $fld = $page?->Fields[$this->Field->Name] ?? null;
        if ($fld != null && method_exists($page, "lookupSelecting")) {
            $page->lookupSelecting($fld, $newFilter); // Call Lookup Selecting
        }
        if ($filter != $newFilter) { // Filter changed
            AddFilter($this->UserFilter, $newFilter);
        }
        if ($lookupFilter != "") { // Add lookup filter as part of user filter
            AddFilter($this->UserFilter, $lookupFilter);
        }
        return $this->getSqlPart("", true, $useParentFilter, $skipFilterFields);
    }

    /**
     * Set options
     *
     * @param array $options Input options with formats:
     *  1. Manual input data, e.g.: [ ["lv1", "dv", "dv2", "dv3", "dv4"], ["lv2", "dv", "dv2", "dv3", "dv4"], ...]
     *  2. Data from $rs->getRows(), e.g.: [ ["Field1" => "lv1", "Field2" => "dv2", ...], ["Field1" => "lv2", "Field2" => "dv2", ...], ...]
     * @return bool Output array ["lv1" => ["lf" => "lv1", "df" => "dv", ...], ...]
     */
    public function setOptions($options)
    {
        $opts = $this->formatOptions($options);
        if ($opts === null) {
            return false;
        }
        $this->Options = $opts;
        return true;
    }

    /**
     * Set filter field operator
     *
     * @param string $name Filter field name
     * @param string $opr Filter search operator
     * @return void
     */
    public function setFilterOperator($name, $opr)
    {
        if (array_key_exists($name, $this->FilterFields) && IsValidOperator($opr)) {
            $this->FilterFields[$name] = $opr;
        }
    }

    /**
     * Get user parameters hidden tag, if user SELECT/WHERE/ORDER BY clause is not empty
     *
     * @param string $var Variable name
     * @return string
     */
    public function getParamTag($currentPage, $var)
    {
        $this->UserSelect = "";
        $this->UserFilter = "";
        $this->UserOrderBy = "";
        $this->getSql($this->UseParentFilter, $this->CurrentFilter, $this->LookupFilter, $currentPage); // Call Lookup_Selecting again based on last setting
        $ar = [];
        if ($this->UserSelect != "") {
            $ar["s"] = Encrypt($this->UserSelect);
        }
        if ($this->UserFilter != "") {
            $ar["f"] = Encrypt($this->UserFilter);
        }
        if ($this->UserOrderBy != "") {
            $ar["o"] = Encrypt($this->UserOrderBy);
        }
        if (count($ar) > 0) {
            return '<input type="hidden" id="' . $var . '" name="' . $var . '" value="' . http_build_query($ar) . '">';
        }
        return "";
    }

    /**
     * Output client side list
     *
     * @return string
     */
    public function toClientList($page)
    {
        return [
            "page" => $page->PageObjName,
            "field" => $this->Field->Name,
            "linkField" => $this->LinkField,
            "displayFields" => $this->DisplayFields,
            "groupByField" => $this->GroupByField,
            "parentFields" => $page->PageID != "grid" && $this->hasParentTable() ? [] : $this->ParentFields,
            "childFields" => $this->ChildFields,
            "filterFields" => $page->PageID != "grid" && $this->hasParentTable() ? [] : array_keys($this->FilterFields),
            "filterFieldVars" => $page->PageID != "grid" && $this->hasParentTable() ? [] : $this->FilterFieldVars,
            "ajax" => $this->LinkTable != "",
            "autoFillTargetFields" => $this->AutoFillTargetFields,
            "template" => $this->Template
        ];
    }

    /**
     * Execute SQL and write JSON response
     *
     * @return bool
     */
    public function toJson($page = null, $response = true)
    {
        if ($page === null) {
            return false;
        }

        // Get table object
        $tbl = $this->getTable();

        // Check if dashboard report / lookup to report source table
        $isReport = $page->TableReportType == "dashboard"
            ? ($tbl->TableType == "REPORT")
            : ($page->TableType == "REPORT" && property_exists($page, "ReportSourceTable") && in_array($tbl->TableVar, [$page->ReportSourceTable, $page->TableVar]));
        $renderer = $isReport ? $page : $tbl;

        // Update expression for grouping fields (reports)
        if ($isReport) {
            foreach ($this->DisplayFields as $i => $displayField) {
                if (!EmptyValue($displayField)) {
                    $pageDisplayField = $page->Fields[$displayField] ?? null;
                    $tblDisplayField = $tbl->Fields[$displayField] ?? null;
                    if ($pageDisplayField && $tblDisplayField && !EmptyValue($pageDisplayField->LookupExpression)) {
                        if (!EmptyValue($this->UserOrderBy)) {
                            $this->UserOrderBy = str_replace($tblDisplayField->Expression, $pageDisplayField->LookupExpression, $this->UserOrderBy);
                        }
                        $tblDisplayField->Expression = $pageDisplayField->LookupExpression;
                        $this->Distinct = true; // Use DISTINCT for grouping fields
                    }
                }
            }
        }
        $filterValues = count($this->FilterValues) > 0 ? array_slice($this->FilterValues, 1) : [];
        $useParentFilter = count($filterValues) == count(array_filter($filterValues)) || !$this->hasParentTable() && $this->LookupType != "filter";
        $sql = $this->getSql($useParentFilter, "", "", $page, !$useParentFilter);
        $pageSize = $this->PageSize;
        $offset = $this->Offset;
        $recordCnt = ($pageSize > 0) ? $tbl->getRecordCount($sql) : 0; // Get record count first
        $rsarr = [];
        $fldCnt = 0;
        try {
            $stmt = $this->executeQuery($sql, $pageSize, $offset);
            if ($stmt) {
                $rsarr = $stmt->fetchAllAssociative();
                $fldCnt = $stmt->columnCount();
            }
        } catch (Exception $e) {
            if (Config("DEBUG")) {
                LogError($e->getMessage(), ["sql" => $sql, "pageSize" => $pageSize, "offset" => $offset]);
            }
        }
        if (is_array($rsarr)) {
            $rowCnt = count($rsarr);
            $totalCnt = ($pageSize > 0) ? $recordCnt : $rowCnt;

            // Clean output buffer
            if ($response && ob_get_length()) {
                ob_clean();
            }

            // Output
            foreach ($rsarr as &$row) {
                $keys = array_keys($row);
                $keyCnt = count($keys);
                $linkField = $renderer->Fields[$this->LinkField] ?? null;
                if ($linkField) {
                    if (IsFloatType($linkField->Type) && is_numeric($row[$keys[0]])) { // Format float format field as string
                        $row[$keys[0]] = strval((float)$row[$keys[0]]);
                    }
                    $linkField->setDbValue($row[$keys[0]]);
                }
                for ($i = 1; $i < $keyCnt; $i++) {
                    $val = &$row[$keys[$i]];
                    $val = ConvertToUtf8(strval($val));
                    if (!self::$KeepCrLf) {
                        $val = str_replace(["\r", "\n", "\t"], [" ", " ", " "], $val);
                    }
                    if (SameText($this->LookupType, "autofill")) {
                        $autoFillSourceFieldName = $this->AutoFillSourceFields[$i - 1] ?? "";
                        $autoFillSourceField = $renderer->Fields[$autoFillSourceFieldName] ?? null;
                        if ($autoFillSourceField) {
                            $autoFillSourceField->setDbValue($val);
                        }
                    }
                }
                if (SameText($this->LookupType, "autofill")) {
                    if ($this->FormatAutoFill) { // Format auto fill
                        $renderer->RowType = RowType::EDIT;
                        $fn = $this->RenderEditFunc;
                        $render = method_exists($renderer, $fn);
                        if ($render) {
                            $renderer->$fn();
                        }
                        for ($i = 0; $i < $fldCnt; $i++) {
                            $autoFillSourceFieldName = $this->AutoFillSourceFields[$i] ?? "";
                            $autoFillSourceField = $renderer->Fields[$autoFillSourceFieldName] ?? null;
                            if ($autoFillSourceField) {
                                $row["af" . $i] = (!$render || $autoFillSourceField->AutoFillOriginalValue)
                                    ? $autoFillSourceField->CurrentValue
                                    : ((is_array($autoFillSourceField->EditValue) || $autoFillSourceField->EditValue === null)
                                        ? $autoFillSourceField->CurrentValue
                                        : $autoFillSourceField->EditValue);
                            }
                        }
                    }
                } elseif ($this->LookupType != "unknown") { // Format display fields for known lookup type
                    $row = $this->renderViewRow($row, $renderer);
                }
            }

            // Set up advanced filter (reports)
            if ($isReport) {
                if (in_array($this->LookupType, ["updateoption", "modal", "autosuggest"])) {
                    if (method_exists($page, "pageFilterLoad")) {
                        $page->pageFilterLoad();
                    }
                    $linkField = $page->Fields[$this->LinkField] ?? null;
                    if ($linkField && is_array($linkField->AdvancedFilters)) {
                        $ar = [];
                        foreach ($linkField->AdvancedFilters as $filter) {
                            if ($filter->Enabled) {
                                $ar[] = ["lf" => $filter->ID, "df" => $filter->Name];
                            }
                        }
                        $rsarr = array_merge($ar, $rsarr);
                    }
                }
            }
            $result = ["result" => "OK", "records" => $rsarr, "totalRecordCount" => $totalCnt];
            if (Config("DEBUG")) {
                $result["sql"] = is_string($sql) ? $sql : $sql->getSQL();
            }
            if ($response) {
                WriteJson($result);
                return true;
            } else {
                return $result;
            }
        }
        return false;
    }

    /**
     * Render view row
     *
     * @param object $row Input data
     * @param object $renderer Renderer
     * @return object Output data
     */
    public function renderViewRow($row, $renderer = null)
    {
        if ($this->rendering) { // Avoid recursive calls
            return $row;
        }

        // Use table as renderer if not defined / renderer is dashboard
        $sameTable = false;
        $tbl = $this->getTable();
        if ($renderer == null || $renderer->PageID == "dashboard") {
            $renderer = $tbl;
        } elseif ($renderer->TableName == $tbl->TableName) {
            $sameTable = true; // Lookup table same as renderer table
        }

        // Check if render View function exists
        $fn = $this->RenderViewFunc;
        $render = method_exists($renderer, $fn);
        if (!$render) {
            return $row;
        }
        $this->rendering = true;

        // Set up DbValue/CurrentValue
        foreach ($this->DisplayFields as $index => $name) {
            $displayField = $renderer->Fields[$name] ?? null;
            if ($displayField) {
                $sfx = $index > 0 ? $index + 1 : "";
                $displayField->setDbValue($row["df" . $sfx]);
            }
        }

        // Render data
        $rowType = $renderer->RowType; // Save RowType
        $renderer->RowType = RowType::VIEW;
        $renderer->$fn();
        $renderer->RowType = $rowType; // Restore RowType

        // Output data from ViewValue
        foreach ($this->DisplayFields as $index => $name) {
            $displayField = $renderer->Fields[$name] ?? null;
            if ($displayField) {
                $sfx = $index > 0 ? $index + 1 : "";
                $viewValue = $displayField->getViewValue();
                // Make sure that ViewValue is not empty and not self lookup field (except Date/Time) and not field with user values
                if (!EmptyString($viewValue) && !($sameTable && $name == $this->Field->Name && !in_array($displayField->DataType, [DataType::DATE, DataType::TIME]) && $displayField->OptionCount == 0)) {
                    $row["df" . $sfx] = $viewValue;
                }
            }
        }
        $this->rendering = false;
        return $row;
    }

    /**
     * Get table object
     *
     * @return object
     */
    public function getTable()
    {
        if ($this->LinkTable == "") {
            return null;
        }
        $this->Table ??= Container($this->LinkTable);
        return $this->Table;
    }

    /**
     * Has parent table
     *
     * @return bool
     */
    public function hasParentTable()
    {
        if (is_array($this->ParentFields)) {
            foreach ($this->ParentFields as $parentField) {
                if (strval($parentField) != "" && ContainsText($parentField, " ")) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get part of lookup SQL
     *
     * @param string $part Part of the SQL (select|where|orderby|"")
     * @param bool $isUser Whether the CurrentFilter, UserFilter and UserSelect properties should be used
     * @param bool $useParentFilter Use parent filter
     * @param bool $skipFilterFields Skip filter fields
     * @return string|QueryBuilder Part of SQL, or QueryBuilder if $part unspecified
     */
    protected function getSqlPart($part = "", $isUser = true, $useParentFilter = true, $skipFilterFields = false)
    {
        $tbl = $this->getTable();
        if ($tbl === null) {
            return "";
        }

        // Set up SELECT ... FROM ...
        $dbid = $tbl->Dbid;
        $queryBuilder = $tbl->getQueryBuilder();
        if ($this->Distinct) {
            $queryBuilder->distinct();
        }

        // Set up link field
        $linkField = $tbl->Fields[$this->LinkField] ?? null;
        if (!$linkField) {
            return "";
        }
        $select = $linkField->Expression;
        if ($this->LookupType != "unknown") { // Known lookup types
            $select .= " AS " . QuotedName("lf", $dbid);
        }
        $queryBuilder->select($select);

        // Group By field
        $groupByField = $tbl->Fields[$this->GroupByField] ?? null;

        // Set up lookup fields
        $lookupCnt = 0;
        if (SameText($this->LookupType, "autofill")) {
            if (is_array($this->AutoFillSourceFields)) {
                foreach ($this->AutoFillSourceFields as $i => $autoFillSourceField) {
                    $autoFillSourceField = $tbl->Fields[$autoFillSourceField] ?? null;
                    if (!$autoFillSourceField) {
                        $select = "'' AS " . QuotedName("af" . $i, $dbid);
                    } else {
                        $select = $autoFillSourceField->Expression . " AS " . QuotedName("af" . $i, $dbid);
                    }
                    $queryBuilder->addSelect($select);
                    if (!$autoFillSourceField->AutoFillOriginalValue) {
                        $this->FormatAutoFill = true;
                    }
                    $lookupCnt++;
                }
            }
        } else {
            if (is_array($this->DisplayFields)) {
                foreach ($this->DisplayFields as $i => $displayField) {
                    $displayField = $tbl->Fields[$displayField] ?? null;
                    if (!$displayField) {
                        $select = "'' AS " . QuotedName("df" . ($i == 0 ? "" : $i + 1), $dbid);
                    } else {
                        $select = $displayField->Expression;
                        if ($this->LookupType != "unknown") { // Known lookup types
                            $select .= " AS " . QuotedName("df" . ($i == 0 ? "" : $i + 1), $dbid);
                        }
                    }
                    $queryBuilder->addSelect($select);
                    $lookupCnt++;
                }
            }
            if (is_array($this->FilterFields) && !$useParentFilter && !$skipFilterFields) {
                $i = 0;
                foreach ($this->FilterFields as $filterField => $filterOpr) {
                    $filterField = $tbl->Fields[$filterField] ?? null;
                    if (!$filterField) {
                        $select = "'' AS " . QuotedName("ff" . ($i == 0 ? "" : $i + 1), $dbid);
                    } else {
                        $select = $filterField->Expression;
                        if ($this->LookupType != "unknown") { // Known lookup types
                            $select .= " AS " . QuotedName("ff" . ($i == 0 ? "" : $i + 1), $dbid);
                        }
                    }
                    $queryBuilder->addSelect($select);
                    $i++;
                    $lookupCnt++;
                }
            }
            if ($groupByField) {
                $select = $this->GroupByExpression;
                if ($this->LookupType != "unknown") { // Known lookup types
                    $select .= " AS " . QuotedName("gf", $dbid);
                }
                $queryBuilder->addSelect($select);
            }
        }
        if ($lookupCnt == 0) {
            return "";
        }
        $queryBuilder->resetQueryPart("from")->from($tbl->getSqlFrom());

        // User SELECT
        $select = "";
        if ($this->UserSelect != "" && $isUser) {
            $select = $this->UserSelect;
        }

        // Set up WHERE
        $where = "";

        // Set up user id filter
        if (method_exists($tbl, "applyUserIDFilters")) {
            $where = $tbl->applyUserIDFilters($where, "lookup");
        }

        // Set up current filter
        $cnt = count($this->FilterValues);
        if ($cnt > 0 && !(SameText($this->LookupType, "updateoption") && $this->IsAutoFillTargetField)) { // Load all records if IsAutoFillTargetField
            $val = $this->FilterValues[0];
            if ($val != "") {
                $val = strval($val);
                if ($linkField->DataType == DataType::GUID && !CheckGuid($val)) {
                    AddFilter($where, "1=0"); // Disallow
                } else {
                    AddFilter($where, $this->getFilter($linkField, "=", $val, $tbl->Dbid));
                }
            }

            // Set up parent filters
            if (is_array($this->FilterFields) && $useParentFilter && !($isUser && preg_match('/\{v(\d)\}/i', $this->UserFilter))) { // UserFilter does not contain ({v<n>})
                $i = 1;
                foreach ($this->FilterFields as $filterField => $filterOpr) {
                    if ($filterField != "") {
                        $filterField = $tbl->Fields[$filterField] ?? null;
                        if (!$filterField) {
                            return "";
                        }
                        if ($cnt <= $i) {
                            AddFilter($where, "1=0"); // Disallow
                        } else {
                            $val = strval($this->FilterValues[$i]);
                            AddFilter($where, $this->getFilter($filterField, $filterOpr, $val, $tbl->Dbid));
                        }
                    }
                    $i++;
                }
            }
        }

        // Set up search
        if ($this->SearchValue != "") {
            // Normal autosuggest
            if (SameText($this->LookupType, "autosuggest") && !$this->LookupAllDisplayFields) {
                AddFilter($where, $this->getAutoSuggestFilter($this->SearchValue, $tbl->Dbid));
            } else { // Use quick search logic
                AddFilter($where, $this->getModalSearchFilter($this->SearchValue, $tbl->Dbid));
            }
        }

        // Add filters
        if ($this->CurrentFilter != "" && $isUser) {
            AddFilter($where, $this->CurrentFilter);
        }

        // User Filter
        if ($this->UserFilter != "" && $isUser) {
            AddFilter($where, $this->getUserFilter());
        }

        // Set up ORDER BY
        $orderBy = $this->UserOrderBy;
        if ($groupByField) { // Sort GroupByField first
            if (StartsString("(", $this->GroupByExpression) && EndsString(")", $this->GroupByExpression)) {
                $groupByExpression = QuotedName("gf", $dbid);
            } else {
                $groupByExpression = $this->GroupByExpression;
            }
            $orderBy = $groupByExpression . " ASC" . (EmptyValue($orderBy) ? "" : ", " . $orderBy);
        }

        // Return SQL part
        if ($part == "select") {
            return $select != "" ? $select : $queryBuilder->getSQL();
        } elseif ($part == "where") {
            return $where;
        } elseif ($part == "orderby") {
            return $orderBy;
        } else {
            if ($select != "") {
                $sql = $select;
                $dbType = GetConnectionType($tbl->Dbid);
                if ($where != "") {
                    $sql .= " WHERE " . $where;
                }
                if ($orderBy != "") {
                    if ($dbType == "MSSQL") {
                        $sql .= " /*BeginOrderBy*/ORDER BY " . $orderBy . "/*EndOrderBy*/";
                    } else {
                        $sql .= " ORDER BY " . $orderBy;
                    }
                }
                return $sql;
            } else {
                if ($where != "") {
                    $queryBuilder->where($where);
                }
                $flds = GetSortFields($orderBy);
                if (is_array($flds)) {
                    foreach ($flds as $fld) {
                        $queryBuilder->addOrderBy($fld[0], $fld[1]);
                    }
                }
                return $queryBuilder;
            }
        }
    }

    /**
     * Get user filter
     *
     * @return string
     */
    protected function getUserFilter()
    {
        $filter = $this->UserFilter;
        if (preg_match_all('/\{v(\d)\}/i', $filter, $matches, PREG_SET_ORDER)) { // Match {v<n>} to FilterValues
            foreach ($matches as $match) {
                $index = intval($match[1]);
                $value = $this->FilterValues[$index] ?? null;
                if (!EmptyValue($value)) { // Replace {v<n>}
                    $filter = str_replace($match[0], AdjustSql($value, $this->getTable()->Dbid), $filter);
                } else { // No filter value found, ignore filter
                    Log("Value for {$match[0]} not found.");
                    return "";
                }
            }
        }
        return $filter;
    }

    /**
     * Get filter
     *
     * @param DbField $fld Field Object
     * @param string $opr Search Operator
     * @param string $val Search Value
     * @param string $dbid Database ID
     * @return string Search Filter (SQL WHERE part)
     */
    protected function getFilter($fld, $opr, $val, $dbid)
    {
        $valid = $val != "";
        $where = "";
        $ar = $this->Field->isMultiSelect() ? explode(Config("MULTIPLE_OPTION_SEPARATOR"), $val) : [$val];
        if ($fld->DataType == DataType::NUMBER) { // Validate numeric fields
            foreach ($ar as $val) {
                if (!is_numeric($val)) {
                    $valid = false;
                }
            }
        }
        if ($valid) {
            if ($opr == "=") { // Use the IN operator
                foreach ($ar as &$val) {
                    $val = QuotedValue($val, $fld, $dbid);
                }
                $where = $fld->Expression . " IN (" . implode(", ", $ar) . ")";
            } else { // Custom operator
                $dbtype = GetConnectionType($dbid);
                foreach ($ar as $val) {
                    if (in_array($opr, ["LIKE", "NOT LIKE", "STARTS WITH", "ENDS WITH"])) {
                        $fldOpr = ($opr == "NOT LIKE") ? "NOT LIKE" : "LIKE";
                        $filter = LikeOrNotLike($fldOpr, Wildcard($val, $opr, $dbid), $dbid);
                    } else {
                        $fldOpr = $opr;
                        $val = QuotedValue($val, $fld, $dbid);
                        $filter = $fld->Expression . $fldOpr . $val;
                    }
                    AddFilter($where, $filter, "OR");
                }
            }
        } else {
            $where = "1=0"; // Disallow
        }
        return $where;
    }

    /**
     * Get Where part
     *
     * @return string
     */
    protected function getWhere($useParentFilter = false)
    {
        return $this->getSqlPart("where", false, $useParentFilter);
    }

    /**
     * Execute query
     *
     * @param string|QueryBuilder $sql SQL or QueryBuilder of the SQL to be executed
     * @param int $pageSize
     * @param int $offset
     * @return Result
     */
    protected function executeQuery($sql, $pageSize, $offset)
    {
        $tbl = $this->getTable();
        if ($tbl === null) {
            return null;
        }
        if ($sql instanceof QueryBuilder) { // Query builder
            if ($offset > -1) {
                $sql->setFirstResult($offset);
            }
            if ($pageSize > 0) {
                $sql->setMaxResults($pageSize);
            }
            $sql = $sql->getSQL();
        }
        $conn = $tbl->getConnection();
        $config = $conn->getConfiguration();
        $config->setResultCache($this->cache);
        return $conn->executeCacheQuery($sql, [], [], $this->cacheProfile);
    }

    /**
     * Get search expression
     *
     * @return string
     */
    protected function getSearchExpression()
    {
        if (EmptyValue($this->SearchExpression)) {
            $tbl = $this->getTable();
            $displayField = $tbl->Fields[$this->DisplayFields[0]] ?? null;
            if ($displayField) {
                $this->SearchExpression = $displayField->Expression;
            }
        }
        return $this->SearchExpression;
    }

    /**
     * Get auto suggest filter
     *
     * @param string $sv Search value
     * @return string
     */
    protected function getAutoSuggestFilter($sv, $dbid)
    {
        return $this->getSearchExpression() . Like(Wildcard($sv, "STARTS WITH", $dbid), $dbid);
    }

    /**
     * Get modal search filter
     *
     * @param string $sv Search value
     * @param array $dbid Database ID
     * @return string
     */
    protected function getModalSearchFilter($sv, $dbid)
    {
        if (EmptyString($sv)) {
            return "";
        }
        $search = trim($sv);
        $searchType = self::$ModalLookupSearchType;
        $ar = GetQuickSearchKeywords($search, $searchType);
        $filter = "";
        foreach ($ar as $keyword) {
            if ($keyword != "") {
                $thisFilter = $this->getSearchExpression() . Like(Wildcard($keyword, self::$ModalLookupSearchOperator, $dbid), $dbid);
                AddFilter($filter, $thisFilter, $searchType);
            }
        }
        return $filter;
    }

    /**
     * Format options
     *
     * @param array $options Input options with formats:
     *  1. Manual input data, e.g. [ ["lv", "dv", "dv2", "dv3", "dv4"], ["lv", "dv", "dv2", "dv3", "dv4"], ... ]
     *  2. Data from database, e.g. [ ["Field1" => "lv", "Field2" => "dv", ...], ["Field1" => "lv", "Field2" => "dv", ...], ... ]
     * @return array ["lv" => ["lf" => "lv", "df" => "dv", ...], ...]
     */
    protected function formatOptions($options)
    {
        if (!is_array($options)) {
            return null;
        }
        $keys = ["lf", "df", "df2", "df3", "df4", "ff", "ff2", "ff3", "ff4"];
        $opts = [];
        $cnt = count($keys);

        // Check values
        foreach ($options as &$ar) {
            if (is_array($ar)) {
                if ($cnt > count($ar)) {
                    $cnt = count($ar);
                }
            }
        }

        // Set up options
        if ($cnt >= 2) {
            $keys = array_splice($keys, 0, $cnt);
            foreach ($options as &$ar) {
                if (is_array($ar)) {
                    $ar = array_splice($ar, 0, $cnt);
                    $ar = array_combine($keys, $ar); // Set keys
                    $lv = $ar["lf"]; // First value as link value
                    $opts[$lv] = $ar;
                }
            }
        } else {
            return null;
        }
        return $opts;
    }
}
