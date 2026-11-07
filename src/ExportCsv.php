<?php

namespace PHPMaker2024\FIMS;

/**
 * Export to CSV
 */
class ExportCsv extends AbstractExport
{
    public $FileExtension = "csv";
    public $UseCharset = true; // Add charset to content type
    public $UseBom = true; // Output byte order mark
    public $QuoteChar = "\"";
    public $Separator = ",";

    // Style
    public function setStyle($style)
    {
        $this->Horizontal = true;
    }

    // Set horizontal
    public function setHorizontal(bool $value)
    {
        $this->Horizontal = true;
    }

    // Table header
    public function exportTableHeader()
    {
    }

    // Export a value (caption, field value, or aggregate)
    protected function exportValueEx($fld, $val)
    {
        if ($fld->DataType != DataType::BLOB) {
            if ($this->Line != "") {
                $this->Line .= $this->Separator;
            }
            $this->Line .= $this->QuoteChar . str_replace($this->QuoteChar, $this->QuoteChar . $this->QuoteChar, strval($val)) . $this->QuoteChar;
        }
    }

    // Field aggregate
    public function exportAggregate($fld, $type)
    {
    }

    // Begin a row
    public function beginExportRow($rowCnt = 0)
    {
        $this->Line = "";
    }

    // End a row
    public function endExportRow($rowCnt = 0)
    {
        $this->Line .= "\r\n";
        $this->Text .= $this->Line;
    }

    // Empty row
    public function exportEmptyRow()
    {
    }

    // Export a field
    public function exportField($fld)
    {
        if (!$fld->Exportable) {
            return;
        }
        if ($fld->UploadMultiple) {
            $this->exportValueEx($fld, $fld->Upload->DbValue);
        } else {
            $this->exportValue($fld);
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
        if ($save) { // Save to folder
            SaveFile(ExportPath(true), $this->getSaveFileName(), $this->Text);
        }
        if ($output) { // Output
            $this->writeHeaders($fileName);
            $this->write();
        }
    }
}
