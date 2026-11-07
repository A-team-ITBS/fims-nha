<?php

namespace PHPMaker2024\FIMS;

use DiDom\Document;
use DiDom\Element;

/**
 * Export to Word
 */
class ExportWord extends AbstractExport
{
    public $FileExtension = "doc";
    public $UseCharset = true; // Add charset to content type
    public $UseBom = true; // Output byte order mark
    public $UseInlineStyles = true; // Use inline styles (Does not support multiple CSS classes)
    public $ExportImages = false; // Does not support images

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
