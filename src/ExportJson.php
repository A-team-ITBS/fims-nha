<?php

namespace PHPMaker2024\FIMS;

/**
 * Export to JSON
 */
class ExportJson extends AbstractExport
{
    public $FileExtension = "json";
    public $Disposition = "inline";
    public $HasParent;
    protected $Items;
    protected $Item;

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
        $this->HasParent = isset($this->Items);
        if ($this->HasParent) {
            if (is_array($this->Items)) {
                $this->Items[$this->Table->TableName] = [];
            } elseif (is_object($this->Items)) {
                $this->Items->{$this->Table->TableName} = [];
            }
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
        $this->Item = new \stdClass();
    }

    // End a row
    public function endExportRow($rowCnt = 0)
    {
        if ($rowCnt <= 0) {
            return;
        }
        if ($this->HasParent) {
            if (is_array($this->Items)) {
                $this->Items[$this->Table->TableName][] = $this->Item;
            } elseif (is_object($this->Items)) {
                $this->Items->{$this->Table->TableName}[] = $this->Item;
            }
        } else {
            if (is_array($this->Items)) {
                $this->Items[] = $this->Item;
            } elseif (is_object($this->Items)) {
                $this->Items = [$this->Items, $this->Item]; // Convert to array
            } else {
                $this->Items = $this->Item;
            }
        }
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
                $this->Item->{$fld->Name} = $fld->Upload->DbValue;
            } else {
                $this->Item->{$fld->Name} = $fld->exportValue();
            }
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
        $encodingOptions = Config("DEBUG") ? JSON_PRETTY_PRINT : 0;
        $json = json_encode(ConvertToUtf8($this->Items), $encodingOptions);
        if ($json === false) {
            $json = json_encode(["json_encode_error" => json_last_error()], $encodingOptions);
        }
        $this->Text = $json;
        if ($save) { // Save to folder
            SaveFile(ExportPath(true), $this->getSaveFileName(), $this->Text);
        }
        if ($output) { // Output
            $this->writeHeaders($fileName);
            $this->write();
        }
    }
}
