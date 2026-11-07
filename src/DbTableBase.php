<?php

namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Table classes
 */
// Common class for table and report
class DbTableBase
{
    public $TableVar;
    public $TableName;
    public $TableType;
    public $Dbid = "DB"; // Table database id
    public $Visible = true;
    public $Charts = [];
    public $Fields = [];
    public $Rows = []; // Data for Custom Template
    public $OldKey = ""; // Old key (for edit/copy)
    public $OldKeyName = ""; // Old key name (for edit/copy)
    public $Recordset; // Result set
    public $CurrentRow; // Current row // PHP
    public $UseCustomTemplate = false; // Use custom template
    public $Export; // Export
    public $ExportAll;
    public $ExportPageBreakCount; // Page break per every n record (PDF only)
    public $ExportPageOrientation; // Page orientation (PDF only)
    public $ExportPageSize; // Page size (PDF only)
    public $ExportExcelPageOrientation; // Page orientation (Excel only)
    public $ExportExcelPageSize; // Page size (Excel only)
    public $ExportWordVersion; // Word version (Word only)
    public $ExportWordPageOrientation; // Page orientation (Word only)
    public $ExportWordPageSize; // Page size (Word only)
    public $ExportWordColumnWidth; // Page orientation (Word only)
    public $SendEmail; // Send email
    public $PageBreakHtml;
    public $ExportPageBreaks = true; // Page breaks when export
    public $ImportInsertOnly; // Import by insert only
    public $ImportUseTransaction = false; // Import use transaction
    public $ImportMaxFailures = 0; // Import maximum number of failures
    public $BasicSearch; // Basic search
    public $QueryRules; // Rules from jQuery Query builder
    public $CurrentFilter; // Current filter
    public $CurrentOrder; // Current order
    public $CurrentOrderType; // Current order type
    public $RowCount = 0;
    public $RowType; // Row type
    public $CssClass; // CSS class
    public $CssStyle; // CSS style
    public $RowAttrs; // Row custom attributes
    public $CurrentAction; // Current action
    public $ActionValue; // Action value
    public $LastAction; // Last action
    public $UserIDAllowSecurity = 0; // User ID allowed permissions
    public $Count = 0; // Record count (as detail table)
    public $UpdateTable = ""; // Update Table
    public $SearchOption = ""; // Search option
    public $Filter = "";
    public $DefaultFilter = "";
    public $Sort = "";
    public $Pager;
    public $AutoHidePager;
    public $AutoHidePageSizeSelector;
    public $QueryBuilder;
    protected $TableCaption = "";
    protected $PageCaption = [];
    public $RouteCompositeKeySeparator = "/"; // Composite key separator for routing
    public $UseTransaction = false;
    public $RowAction = ""; // Row action
    public $ValidationErrors = []; // Server side validation errors for Grid-Add/Edit and Multi-Edit
    protected $Cache; // Doctrine cache
    protected $CacheProfile; // Doctrine cache profile

    // Charts related
    public $SourceTableIsCustomView = false;
    public $TableReportType;
    public $ShowDrillDownFilter;
    public $UseDrillDownPanel; // Use drill down panel
    public $DrillDown = false;
    public $DrillDownInPanel = false;

    // Table
    public $TableClass = "";
    public $TableGridClass = ""; // CSS class for .card (with a leading space)
    public $TableContainerClass = ""; // CSS class for .card-body (e.g. height of the main table)
    public $TableContainerStyle = ""; // CSS style for .card-body (e.g. height of the main table)
    public $UseResponsiveTable = false;
    public $ResponsiveTableClass = "";
    public $ContainerClass = "p-0";
    public $ContextClass = ""; // CSS class name as context
    public $ShowCurrentFilter;

    // Default field properties
    public $UploadPath;
    public $OldUploadPath;
    public $HrefPath;
    public $UploadAllowedFileExt;
    public $UploadMaxFileSize;
    public $UploadMaxFileCount;
    public $ImageCropper;
    public $UseColorbox;
    public $AutoFillOriginalValue;
    public $UseLookupCache;
    public $LookupCacheCount;
    public $ExportOriginalValue;
    public $ExportFieldCaption;
    public $ExportFieldImage;
    public $DefaultNumberFormat;

    // Constructor
    public function __construct()
    {
        $this->OldKeyName = Config("FORM_OLD_KEY_NAME");
        $this->SearchOption = Config("SEARCH_OPTION");
        $this->ImportInsertOnly = Config("IMPORT_INSERT_ONLY");
        $this->ImportMaxFailures = Config("IMPORT_MAX_FAILURES");
        $this->AutoHidePager = Config("AUTO_HIDE_PAGER");
        $this->AutoHidePageSizeSelector = Config("AUTO_HIDE_PAGE_SIZE_SELECTOR");
        $this->UseResponsiveTable = !IsExport() && Config("USE_RESPONSIVE_TABLE");
        $this->ResponsiveTableClass = Config("RESPONSIVE_TABLE_CLASS");
        $this->TableContainerClass = $this->UseResponsiveTable ? $this->ResponsiveTableClass : "";
        $this->RowAttrs = new Attributes();
        $this->ShowCurrentFilter = Config("SHOW_CURRENT_FILTER");

        // Default field properties
        $this->UploadPath = Config("UPLOAD_DEST_PATH");
        $this->OldUploadPath = Config("UPLOAD_DEST_PATH");
        $this->HrefPath = Config("UPLOAD_HREF_PATH");
        $this->UploadAllowedFileExt = Config("UPLOAD_ALLOWED_FILE_EXT");
        $this->UploadMaxFileSize = Config("MAX_FILE_SIZE");
        $this->UploadMaxFileCount = Config("MAX_FILE_COUNT");
        $this->ImageCropper = Config("IMAGE_CROPPER");
        $this->UseColorbox = Config("USE_COLORBOX");
        $this->AutoFillOriginalValue = Config("AUTO_FILL_ORIGINAL_VALUE");
        $this->UseLookupCache = Config("USE_LOOKUP_CACHE");
        $this->LookupCacheCount = Config("LOOKUP_CACHE_COUNT");
        $this->ExportOriginalValue = Config("EXPORT_ORIGINAL_VALUE");
        $this->ExportFieldCaption = Config("EXPORT_FIELD_CAPTION");
        $this->ExportFieldImage = Config("EXPORT_FIELD_IMAGE");
        $this->DefaultNumberFormat = Config("DEFAULT_NUMBER_FORMAT");

        // Page break
        $this->PageBreakHtml = Config("PAGE_BREAK_HTML");
    }

    // Get database type
    public function getDbType()
    {
        return GetConnectionType($this->Dbid);
    }

    // Get Connection
    public function getConnection()
    {
        return Conn($this->Dbid);
    }

    // Check if transaction supported
    public function supportsTransaction(): bool
    {
        $support = true;
        $dbtype = $this->getDbType();
        if ($dbtype == "MYSQL" && $this->TableName != "") {
            $support = $_SESSION[SESSION_MYSQL_ENGINES][$this->Dbid][$this->TableName] ?? null;
            if ($support === null) {
                $sql = "SHOW TABLE STATUS WHERE Engine = 'MyISAM' AND Name = '" . AdjustSql($this->TableName, $this->Dbid) . "'";
                try {
                    $support = $this->getConnection()->executeQuery($sql)->rowCount() == 0;
                } catch (\Exception $e) {
                    $support = false;
                }
                $_SESSION[SESSION_MYSQL_ENGINES][$this->Dbid][$this->TableName] = $support;
            }
        }
        return $support;
    }

    /**
     * Get query builder
     *
     * @param string $type Type of query builder: 'insert'|'update'|'delete'|'qb'
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function getQueryBuilder($type = "")
    {
        $conn = $this->getConnection();
        $qb = $conn->createQueryBuilder();
        return $this->UpdateTable
            ? match ($type) {
                "insert" => $qb->insert($this->UpdateTable),
                "update" => $qb->update($this->UpdateTable),
                "delete" => $qb->delete($this->UpdateTable),
                default => $qb->from($this->UpdateTable) // SELECT statement by default
            }
            : $qb;
    }

    // Get entity manager
    public function getEntityManager()
    {
        return EntityManager($this->Dbid);
    }

    // Find field by param
    public function fieldByParam($param)
    {
        $ar = array_filter($this->Fields, fn($fld) => $fld->Param == $param);
        return array_shift($ar);
    }

    // Fetch current row
    public function fetch($cnt = 1)
    {
        for ($i = 0; $i < $cnt; $i++) {
            $this->CurrentRow = $this->Recordset->fetchAssociative();
        }
        return $this->CurrentRow;
    }

    // Check if fixed header table
    public function isFixedHeaderTable(): bool
    {
        return ContainsClass($this->TableClass, Config("FIXED_HEADER_TABLE_CLASS"));
    }

    /**
     * Set fixed header table
     *
     * @param bool $enabled Whether enable fixed header table
     * @param string $height Height of table container (CSS class name)
     * @return void
     */
    public function setFixedHeaderTable(bool $enabled, string $height = null)
    {
        if ($enabled && !$this->isExport()) {
            AppendClass($this->TableClass, Config("FIXED_HEADER_TABLE_CLASS"));
            $height ??= Config("FIXED_HEADER_TABLE_HEIGHT");
            if ($height) {
                AppendClass($this->TableContainerClass, $height);
                AppendClass($this->TableContainerClass, "overflow-y-auto");
            }
        } else {
            RemoveClass($this->TableClass, Config("FIXED_HEADER_TABLE_CLASS"));
            AppendClass($this->TableContainerClass, "h-auto"); // Override height class
            RemoveClass($this->TableContainerClass, "overflow-y-auto");
        }
    }

    /**
     * Build SELECT statement
     *
     * @param string $select
     * @param string $from
     * @param string $where
     * @param string $groupBy
     * @param string $having
     * @param string $orderBy
     * @param string $filter
     * @param string $sort
     * @return QueryBuilder
     */
    public function buildSelectSql($select, $from, $where, $groupBy, $having, $orderBy, $filter, $sort)
    {
        if (is_string($select)) {
            $queryBuilder = $this->getQueryBuilder()->select($select);;
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
        if ($sort != "") {
            $orderBy = $sort;
        }
        $flds = GetSortFields($orderBy);
        if (is_array($flds)) {
            foreach ($flds as $fld) {
                $queryBuilder->addOrderBy($fld[0], $fld[1]);
            }
        }
        return $queryBuilder;
    }

    // Build filter from array
    protected function arrayToFilter(&$ar)
    {
        $filter = "";
        foreach ($ar as $name => $value) {
            if (array_key_exists($name, $this->Fields)) {
                AddFilter($filter, QuotedName($this->Fields[$name]->Name, $this->Dbid) . '=' . QuotedValue($value, $this->Fields[$name]->DataType, $this->Dbid));
            }
        }
        return $filter;
    }

    // Reset attributes for table object
    public function resetAttributes()
    {
        $this->CssClass = "";
        $this->CssStyle = "";
        $this->RowAttrs = new Attributes();
        foreach ($this->Fields as $fld) {
            $fld->resetAttributes();
        }
    }

    // Set a property of all fields
    public function setFieldProperties($name, $value)
    {
        foreach ($this->Fields as $fld) {
            if (property_exists($fld, $name)) {
                $fld->$name = $value;
            } elseif (method_exists($fld, $name)) {
                $fld->$name($value);
            }
        }
    }

    // Set current values (for number/date/time fields only)
    public function setCurrentValues(array $row)
    {
        foreach ($row as $name => $value) {
            if (isset($this->Fields[$name]) && in_array($this->Fields[$name]->DataType, [DataType::NUMBER, DataType::DATE, DataType::TIME])) {
                $this->Fields[$name]->CurrentValue = $value;
            }
        }
    }

    // Setup field titles
    public function setupFieldTitles()
    {
        foreach ($this->Fields as $fld) {
            if (strval($fld->title()) != "") {
                $fld->EditAttrs["data-bs-toggle"] = "tooltip";
                $fld->EditAttrs["title"] = HtmlEncode($fld->title());
            }
        }
    }

    // Get field values
    public function getFieldValues($propertyname)
    {
        $values = [];
        foreach ($this->Fields as $fldname => $fld) {
            $values[$fldname] = $fld->$propertyname;
        }
        return $values;
    }

    // Get parameter types
    public function getParameterTypes()
    {
        return $this->getFieldValues("ParameterType");
    }

    // Get field cell attributes
    public function fieldCellAttributes()
    {
        $values = [];
        foreach ($this->Fields as $fldname => $fld) {
            $values[$fld->Param] = $fld->cellAttributes();
        }
        return $values;
    }

    // Get field DB values for Custom Template
    public function customTemplateFieldValues()
    {
        $values = [];
        foreach ($this->Fields as $fldname => $fld) {
            if (in_array($fld->DataType, Config("CUSTOM_TEMPLATE_DATATYPES")) && $fld->Visible) {
                if (is_string($fld->DbValue) && strlen($fld->DbValue) > Config("DATA_STRING_MAX_LENGTH")) {
                    $values[$fld->Param] = substr($fld->DbValue, 0, Config("DATA_STRING_MAX_LENGTH"));
                } else {
                    $values[$fld->Param] = $fld->HtmlTag == "FILE" ? $fld->Upload->DbValue : $fld->DbValue;
                }
            }
        }
        return $values;
    }

    // Set table caption
    public function setTableCaption($v)
    {
        $this->TableCaption = $v;
    }

    // Table caption
    public function tableCaption()
    {
        global $Language;
        if ($this->TableCaption == "") {
            $this->TableCaption = $Language->tablePhrase($this->TableVar, "TblCaption");
        }
        return $this->TableCaption;
    }

    // Set page caption
    public function setPageCaption($page, $v)
    {
        $this->PageCaption[$page] = $v;
    }

    // Page caption
    public function pageCaption($page)
    {
        global $Language;
        $caption = @$this->PageCaption[$page];
        if ($caption != "") {
            return $caption;
        } else {
            $caption = $Language->tablePhrase($this->TableVar, "TblPageCaption" . $page);
            if ($caption == "") {
                $caption = "Page " . $page;
            }
            return $caption;
        }
    }

    // Row styles
    public function rowStyles()
    {
        $att = "";
        $style = Concat($this->CssStyle, $this->RowAttrs["style"], ";");
        $class = $this->CssClass;
        AppendClass($class, $this->RowAttrs["class"]);
        if ($style != "") {
            $att .= ' style="' . $style . '"';
        }
        if ($class != '') {
            $att .= ' class="' . $class . '"';
        }
        return $att;
    }

    // Row attributes
    public function rowAttributes()
    {
        $att = $this->rowStyles();
        if (!$this->isExport()) {
            $attrs = $this->RowAttrs->toString(["class", "style"]);
            if ($attrs != "") {
                $att .= $attrs;
            }
        }
        return $att;
    }

    // Field object by name
    public function fields($fldname)
    {
        return $this->Fields[$fldname];
    }

    // Has Invalid fields
    public function hasInvalidFields(): bool
    {
        foreach ($this->Fields as $fldname => $fld) {
            if ($fld->IsInvalid) {
                return true;
            }
        }
        return false;
    }

    // Visible field count
    public function visibleFieldCount()
    {
        $cnt = 0;
        foreach ($this->Fields as $fld) {
            if ($fld->Visible) {
                $cnt++;
            }
        }
        return $cnt;
    }

    // Is export
    public function isExport($format = ""): bool
    {
        if ($format) {
            return SameText($this->Export, $format);
        } else {
            return $this->Export != "";
        }
    }

    /**
     * Set use lookup cache
     *
     * @param bool $b Use lookup cache or not
     * @return void
     */
    public function setUseLookupCache($b)
    {
        foreach ($this->Fields as $fld) {
            $fld->UseLookupCache = $b;
        }
    }

    /**
     * Set Lookup cache count
     *
     * @param int $i Lookup cache count
     * @return void
     */
    public function setLookupCacheCount($i)
    {
        foreach ($this->Fields as $fld) {
            $fld->LookupCacheCount = $i;
        }
    }

    /**
     * Convert table properties to client side variables
     *
     * @param string[] $tablePropertyNames Table property names
     * @param string[] $fieldPropertyNames Field property names
     * @return array
     */
    public function toClientVar(array $tablePropertyNames = [], array $fieldPropertyNames = [])
    {
        if (empty($tablePropertyNames) && empty($fieldPropertyNames)) { // No arguments
            $tablePropertyNames = Config("TABLE_CLIENT_VARS"); // Use default
            $fieldPropertyNames = Config("FIELD_CLIENT_VARS"); // Use default
        }
        $props = [];
        foreach ($tablePropertyNames as $name) {
            if (method_exists($this, $name)) {
                $props[lcfirst($name)] = $this->$name();
            } elseif (property_exists($this, $name)) {
                $props[lcfirst($name)] = $this->$name;
            }
        }
        if (count($fieldPropertyNames) > 0) {
            $props["fields"] = [];
            foreach ($this->Fields as $fld) {
                $props["fields"][$fld->Param] = [];
                foreach ($fieldPropertyNames as $name) {
                    if (method_exists($fld, $name)) {
                        $props["fields"][$fld->Param][lcfirst($name)] = $fld->$name();
                    } elseif (property_exists($fld, $name)) {
                        $props["fields"][$fld->Param][lcfirst($name)] = $fld->$name;
                    }
                };
            }
        }
        return array_merge_recursive(GetClientVar("tables", $this->TableVar) ?? [], $props); // Merge $ClientVariables["tables"][$this->TableVar]
    }

    // URL encode
    public function urlEncode($str)
    {
        return urlencode($str);
    }

    // Print
    public function raw($str)
    {
        return $str;
    }

    // Get validation errors
    public function getValidationErrors()
    {
        // Check if validation required
        if (!Config("SERVER_VALIDATE")) {
            return null;
        }
        $errors = [];
        foreach ($this->Fields as $field) {
            if ($field->IsInvalid) {
                $errors[$field->Param] = $field->getErrorMessage();
            }
        }
        return count($errors) > 0  ? $errors : null;
    }

    // Session Rule (QueryBuilder)
    public function getSessionRules()
    {
        return Session(PROJECT_NAME . "_" . $this->TableVar . "_" . Config("TABLE_RULES"));
    }

    public function setSessionRules($v)
    {
        $_SESSION[PROJECT_NAME . "_" . $this->TableVar . "_" . Config("TABLE_RULES")] = $v;
    }

    // Dashboard Filter
    public function getDashboardFilter($dashboardVar, $tableVar)
    {
        return Session(PROJECT_NAME . "_" . $dashboardVar . "_" . $tableVar . "_" . Config("DASHBOARD_FILTER"));
    }

    public function setDashboardFilter($dashboardVar, $tableVar, $v)
    {
        $_SESSION[PROJECT_NAME . "_" . $dashboardVar . "_" . $tableVar . "_" . Config("DASHBOARD_FILTER")] = $v;
    }

    // For obsolete properties only
    public function __set($name, $value)
    {
        if (EndsString("_Count", $name)) { // <DetailTable>_Count
            $t = preg_replace('/_Count$/', "", $name);
            throw new \Exception("Obsolete property: " . $name . ", please use Container('" . $t . "')->Count.");
        } elseif (Config("DEBUG")) {
            throw new \Exception("Undefined property: " . $name . ".");
        }
    }

    // For obsolete properties only
    public function __get($name)
    {
        if (EndsString("_Count", $name)) { // <DetailTable>_Count
            $t = preg_replace('/_Count$/', "", $name);
            throw new \Exception("Obsolete property: " . $name . ", please use Container('" . $t . "')->Count.");
        } elseif (Config("DEBUG")) {
            throw new \Exception("Undefined property: " . $name . ".");
        }
        return null;
    }
}
