<?php

namespace PHPMaker2024\FIMS;

use DiDom\Document;
use DiDom\Element;

/**
 * Export to Excel
 */
class ExportExcel extends AbstractExport
{
    public $FileExtension = "xls";
    public $UseCharset = true; // Add charset to content type
    public $UseBom = true; // Output byte order mark
    public $UseInlineStyles = true; // Use inline styles (Does not support multiple CSS classes)
    public $ExportImages = false; // Does not support images

    // Export a value (caption, field value, or aggregate)
    protected function exportValueEx($fld, $val)
    {
        if (in_array($fld->DataType, [DataType::STRING, DataType::MEMO]) && is_numeric($val)) {
            $val = "=\"" . strval($val) . "\"";
        }
        $this->Text .= parent::exportValueEx($fld, $val);
    }

    // Export
    public function export($fileName = "", $output = true, $save = false)
    {
        $this->adjustHtml();
        if ($save) { // Save to folder
            SaveFile(ExportPath(true), $this->getSaveFileName(), $this->Text);
        }
        if ($output) { // Output
            $this->writeHeaders($fileName);
            $this->write();
        }
    }
}
