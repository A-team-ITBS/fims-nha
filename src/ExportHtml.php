<?php

namespace PHPMaker2024\FIMS;

/**
 * Export to HTML
 */
class ExportHtml extends AbstractExport
{
    public $FileExtension = "html";
    public $UseCharset = true; // Add charset to content type

    // Export field value
    public function exportFieldValue($fld)
    {
        $exportValue = $fld->exportValue();
        if ($fld->ExportFieldImage && $fld->ViewTag == "IMAGE") {
            $exportValue = GetFileImgTag(array_map(fn($v) => ImageFileToBase64Url($v), $fld->getTempImage()));
        } elseif ($fld->ExportFieldImage && $fld->ExportHrefValue != "") { // Export custom view tag
            $exportValue = GetFileImgTag(ImageFileToBase64Url($fld->ExportHrefValue));
        }
        return $exportValue;
    }

    /**
     * Add image to end of page
     *
     * @param string $imagefn Image file
     * @param string $break Break type (before/after/none)
     * @return void
     */
    public function addImage($imagefn, $break = false)
    {
        $classes = "ew-export";
        if (SameText($break, "before")) {
            $classes .= " break-before-page";
        } elseif (SameText($break, "after")) {
            $classes .= " break-after-page";
        } elseif (SameText($break, "none")) {
            $classes .= " break-after-avoid";
        }
        $html = '<div class="' . $classes . '">' . GetFileImgTag(ImageFileToBase64Url($imagefn)) . "</div>";
        if (ContainsText($this->Text, "</body>")) {
            $this->Text = str_replace("</body>", $html . "</body>", $this->Text); // Insert before </body>
        } else {
            $this->Text .= $html; // Append to end
        }
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
