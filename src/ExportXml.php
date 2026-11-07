<?php

namespace PHPMaker2024\FIMS;

/**
 * Export to XML
 */
class ExportXml extends AbstractExport
{
    public static $NullString = "null";
    public $XmlDoc;
    public $HasParent;
    public $FileExtension = "xml";
    public $Disposition = "inline";

    // Constructor
    public function __construct($table = null)
    {
        parent::__construct($table);
        $this->XmlDoc = new XmlDocument(); // Always utf-8
    }

    // Style
    public function setStyle($style)
    {
    }

    // Field caption
    public function exportCaption($fld)
    {
    }

    // Field value
    public function exportValue($fld)
    {
    }

    // Field aggregate
    public function exportAggregate($fld, $type)
    {
    }

    // Get meta tag for charset
    protected function charsetMetaTag()
    {
    }

    // Table header
    public function exportTableHeader()
    {
        $this->HasParent = is_object($this->XmlDoc->documentElement());
        if (!$this->HasParent) {
            $this->XmlDoc->addRoot($this->Table->TableVar);
        }
    }

    // Export a value (caption, field value, or aggregate)
    protected function exportValueEx($fld, $val)
    {
    }

    // Begin a row
    public function beginExportRow($rowCnt = 0)
    {
        if ($rowCnt <= 0) {
            return;
        }
        if ($this->HasParent) {
            $this->XmlDoc->addRow($this->Table->TableVar);
        } else {
            $this->XmlDoc->addRow();
        }
    }

    // End a row
    public function endExportRow($rowCnt = 0)
    {
    }

    // Empty row
    public function exportEmptyRow()
    {
    }

    // Page break
    public function exportPageBreak()
    {
    }

    // Export a field
    public function exportField($fld)
    {
        if ($fld->Exportable && $fld->DataType != DataType::BLOB) {
            if ($fld->UploadMultiple) {
                $exportValue = $fld->Upload->DbValue;
            } else {
                $exportValue = $fld->exportValue();
            }
            if ($exportValue === null) {
                $exportValue = self::$NullString;
            }
            $this->XmlDoc->addField($fld->Param, $exportValue);
        }
    }

    // Table Footer
    public function exportTableFooter()
    {
    }

    // Add HTML tags
    public function exportHeaderAndFooter()
    {
    }

    // Export
    public function export($fileName = "", $output = true, $save = false)
    {
        $this->Text = $this->XmlDoc->xml();
        if ($save) { // Save to folder
            SaveFile(ExportPath(true), $this->getSaveFileName(), $this->Text);
        }
        if ($output) { // Output
            $this->writeHeaders($fileName);
            $this->write();
        }
    }
}
