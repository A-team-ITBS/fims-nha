<?php

namespace PHPMaker2024\FIMS;

use DiDom\Document;
use DiDom\Element;

/**
 * Class for export to PDF
 */
class ExportPdf extends AbstractExport
{
    public static $Options = [];
    public $PdfBackend = "";
    public $FileExtension = "pdf";
    public $PageSize = "a4";
    public $PageOrientation = "portrait";

    // Constructor
    public function __construct($table = null)
    {
        parent::__construct($table);
        $this->PdfBackend = Config("PDF_BACKEND");
        $this->StyleSheet = Config("PDF_STYLESHEET_FILENAME");
        if ($this->Table) {
            $this->PageSize = $this->Table->ExportPageSize;
            $this->PageOrientation = $this->Table->ExportPageOrientation;
        }
    }

    // Table header
    public function exportTableHeader()
    {
        $this->Text .= "<table class=\"ew-table\">\r\n";
    }

    // Export a value (caption, field value, or aggregate)
    protected function exportValueEx($fld, $val)
    {
        $wrkVal = strval($val);
        $wrkVal = "<td" . ($this->ExportStyles ? $fld->cellStyles() : "") . ">" . $wrkVal . "</td>\r\n";
        $this->Line .= $wrkVal;
        $this->Text .= $wrkVal;
    }

    // Begin a row
    public function beginExportRow($rowCnt = 0)
    {
        $this->FldCnt = 0;
        if ($this->Horizontal) {
            if ($rowCnt == -1) {
                $classname = "ew-table-footer";
            } elseif ($rowCnt == 0) {
                $classname = "ew-table-header";
            } else {
                $classname = (($rowCnt % 2) == 1) ? "" : "ew-table-alt-row";
            }
            $this->Line = "<tr" . ($this->ExportStyles ? ' class="' . $classname . '"' : '') . ">";
            $this->Text .= $this->Line;
        }
    }

    // End a row
    public function endExportRow($rowCnt = 0)
    {
        if ($this->Horizontal) {
            $this->Line .= "</tr>";
            $this->Text .= "</tr>";
            if ($rowCnt == 0) {
                $this->Header = $this->Line;
            }
        }
    }

    // Page break
    public function exportPageBreak()
    {
        if ($this->Horizontal) {
            $this->Text .= "</table>\r\n" . // End current table
                Config("PAGE_BREAK_HTML") . "\r\n" . // Page break
                "<table class=\"ew-table\">\r\n" . // New table
                $this->Header; // Add table header
        }
    }

    // Export a field
    public function exportField($fld)
    {
        if (!$fld->Exportable) {
            return;
        }
        $exportValue = $fld->exportValue();
        if ($fld->ExportFieldImage && $fld->ViewTag == "IMAGE") {
            $exportValue = GetFileImgTag($fld->getTempImage());
        } elseif ($fld->ExportFieldImage && $fld->ExportHrefValue != "") { // Export custom view tag
            $exportValue = GetFileImgTag($fld->ExportHrefValue);
        } else {
            $exportValue = str_replace("<br>", "\r\n", $exportValue ?? "");
            $exportValue = strip_tags($exportValue);
            $exportValue = str_replace("\r\n", "<br>", $exportValue ?? "");
        }
        if ($this->Horizontal) { // Horizontal
            $this->exportValueEx($fld, $exportValue);
        } else { // Vertical, export as a row
            $this->FldCnt++;
            $fld->CellCssClass = ($this->FldCnt % 2 == 1) ? "" : "ew-table-alt-row";
            $cellStyles = $this->ExportStyles ? $fld->cellStyles() : "";
            $this->Text .= "<tr><td" . $cellStyles . ">" . $fld->exportCaption() . "</td>" .
                "<td" . $cellStyles . ">" . $exportValue . "</td></tr>";
        }
    }

    /**
     * Append image
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
        $html = '<div class="' . $classes . '">' . GetFileImgTag($imagefn) . "</div>";
        if (ContainsText($this->Text, "</body>")) {
            $this->Text = str_replace("</body>", $html . "</body>", $this->Text); // Insert before </body>
        } else {
            $this->Text .= $html; // Append to end
        }
    }

    // Adjust HTML before export
    protected function adjustHtml()
    {
        if (!ContainsText($this->Text, "</body>")) {
            $this->exportHeaderAndFooter(); // Add header and footer to $this->Text
        }
        $doc = &$this->getDocument($this->Text);
        $this->adjustPageBreak($doc);
        $css = $this->styles();
        $style = $doc->first("head > style");
        if (!$style) {
            $style = $doc->createElement("style", $css);
            $head = $doc->first("head");
            if (!$head) {
                $head = $doc->createElement("head");
                $doc->appendChild($head);
            }
            $head->appendChild($style); // Add style tag
        } elseif ($style && $style->text() != $css) {
            $style->setValue($css); // Replace styles for PDF
        }
        $spans = $doc->find("span");
        foreach ($spans as $span) {
            $classNames = $span->getAttribute("class") ?? "";
            if ($classNames == "ew-filter-caption") { // Insert colon
                $span->parent()->insertBefore($doc->createElement("span", ":&nbsp;"), $span->nextSibling());
            } elseif (preg_match('/\bicon\-\w+\b/', $classNames)) { // Remove icons
                $span->remove();
            }
        }

        // Remove card headers
        $divs = $doc->find("div.card-header");
        array_walk($divs, fn($el) => $el->remove());

        // Set image sizes
        $images = $doc->find("img");
        $portrait = SameText($this->PageOrientation, "portrait");
        foreach ($images as $image) {
            $imagefn = $image->getAttribute("src") ?? "";
            if (file_exists($imagefn)) {
                $imagefn = realpath($imagefn);
                $size = getimagesize($imagefn); // Get image size
                if ($size[0] != 0) {
                    if (SameText($this->PageSize, "letter")) { // Letter paper (8.5 in. by 11 in.)
                        $w = $portrait ? 216 : 279;
                    } elseif (SameText($this->PageSize, "legal")) { // Legal paper (8.5 in. by 14 in.)
                        $w = $portrait ? 216 : 356;
                    } else {
                        $w = $portrait ? 210 : 297; // A4 paper (210 mm by 297 mm)
                    }
                    $w = min($size[0], ($w - 20 * 2) / 25.4 * 72 * Config("PDF_IMAGE_SCALE_FACTOR")); // Resize image, adjust the scale factor if necessary
                    $h = $w / $size[0] * $size[1];
                    $image->setAttribute("width", $w);
                    $image->setAttribute("height", $h);
                }
            }
        }

        // Output HTML
        $this->setDocument($doc);
    }

    // Export
    public function export($fileName = "", $output = true, $save = false)
    {
        @ini_set("memory_limit", Config("PDF_MEMORY_LIMIT"));
        @set_time_limit(Config("PDF_TIME_LIMIT"));
        $this->adjustHtml();
        $options = new \Dompdf\Options(self::$Options);
        $options->set("pdfBackend", $this->PdfBackend);
        $options->set("isRemoteEnabled", true); // Support remote images such as S3
        $chroot = $options->getChroot();
        $chroot[] = UploadTempPathRoot();
        $chroot[] = UploadTempPath();
        $chroot[] = dirname(CssFile(Config("PDF_STYLESHEET_FILENAME"), false));
        $options->setChroot($chroot);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($this->Text);
        $dompdf->setPaper($this->PageSize, $this->PageOrientation);
        $dompdf->render();
        $this->Text = $dompdf->output();
        if ($save) { // Save to folder
            SaveFile(ExportPath(true), $this->getSaveFileName(), $this->Text);
        }
        if ($output) { // Output
            $this->writeHeaders($fileName);
            $this->write();
        }
    }

    // Destructor
    public function __destruct()
    {
    }
}
