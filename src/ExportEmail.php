<?php

namespace PHPMaker2024\FIMS;

use DiDom\Document;
use DiDom\Element;
use Illuminate\Support\Collection;

/**
 * Export to email
 */
class ExportEmail extends AbstractExport
{
    public $FileExtension = "html";
    public $Disposition = "inline";

    // Table header
    public function exportTableHeader()
    {
        $this->Text .= "<table style=\"border-collapse: collapse;\">";
    }

    // Cell styles
    protected function cellStyles($fld)
    {
        $styles = Config("EXPORT_TABLE_CELL_STYLES");
        if (is_array($styles)) {
            $style = array_reduce(array_keys($styles), fn($carry, $key) => $carry .= $key . ":" . $styles[$key] . ";", "");
            $fld->CellAttrs->prepend("style", $style, ";");
        }
        return $this->ExportStyles ? $fld->cellStyles() : "";
    }

    // Export field value
    public function exportFieldValue($fld)
    {
        $exportValue = $fld->exportValue();
        if ($fld->ExportFieldImage && $fld->ViewTag == "IMAGE") {
            if ($fld->ImageResize) {
                $exportValue = GetFileImgTag($fld->getTempImage());
            } elseif ($fld->ExportHrefValue != "" && is_object($fld->Upload)) {
                if (!EmptyValue($fld->Upload->DbValue)) {
                    $exportValue = GetFileATag($fld, $fld->ExportHrefValue);
                }
            }
        } elseif ($fld->ExportFieldImage && $fld->ExportHrefValue != "") { // Export custom view tag, e.g. barcode
            $exportValue = GetFileImgTag($fld->ExportHrefValue);
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
        $html = '<div class="' . $classes . '">' . GetFileImgTag($imagefn) . "</div>";
        if (ContainsText($this->Text, "</body>")) {
            $this->Text = str_replace("</body>", $html . "</body>", $this->Text); // Insert before </body>
        } else {
            $this->Text .= $html; // Append to end
        }
    }

    /**
     * Add temp image to $TempImages
     *
     * @param string $tmpimage Temp image file name
     * @return void
     */
    public function addTempImage($tmpimage)
    {
        global $TempImages;
        $folder = UploadTempPath(true);
        $ext = Collection::make([".gif", ".jpg", ".png"])->first(fn ($ext) => file_exists($folder . $tmpimage . $ext));
        if ($ext) {
            $tmpimage .= $ext; // Add file extension
            if (!in_array($tmpimage, $TempImages)) { // Add to TempImages
                $TempImages[] = $tmpimage;
            }
        }
    }

    /**
     * Get temp image as Base64 data URL
     *
     * @param string $tmpimage Temp image file name
     * @return string
     */
    public function getBase64Url($tmpimage)
    {
        $folder = UploadTempPath(true);
        $ext = Collection::make([".gif", ".jpg", ".png"])->first(fn ($ext) => file_exists($folder . $tmpimage . $ext));
        return $ext ? ImageFileToBase64Url($folder . $tmpimage . $ext) : $tmpimage;
    }

    /**
     * Adjust src attribute of image tags
     *
     * @param string $html HTML
     * @return string HTML
     */
    public function adjustImage($html)
    {
        $doc = &$this->getDocument($html);
        $inline = $this->getDisposition() == "inline"; // Inline
        $images = $doc->find("img");
        foreach ($images as $image) {
            $src = $image->attr("src");
            if (StartsString("data:", $src) && ContainsString($src, ";base64,")) { // Data URL
                if ($inline) { // Inline (No change required if disposition is "attachment")
                    $image->attr("src", TempImage(DataFromBase64Url($src), true)); // Create temp image as cid URL
                }
            } else { // Not embedded image
                if (file_exists($src)) {
                    if ($inline) { // Inline
                        $image->attr("src", TempImage(file_get_contents($src), true)); // Create temp image as cid URL
                    } else { // Attachment
                        $image->attr("src", ImageFileToBase64Url($src)); // Replace image by data URL
                    }
                }
            }
        }
        return $doc->format()->html();
    }

    /**
     * Send email
     *
     * @param string $fileName File name of attachment
     * @return array Result
     */
    public function send($fileName)
    {
        global $TempImages, $Language;
        $sender = Param("sender", "");
        $recipient = Param("recipient", "");
        $cc = Param("cc", "");
        $bcc = Param("bcc", "");
        $subject = Param("subject", "");
        $message = Param("message", "");
        $inline = $this->getDisposition() == "inline"; // Inline
        $content = $this->adjustImage($this->Text);

        // Send email
        $email = new Email();
        $email->Sender = $sender; // Sender
        $email->Recipient = $recipient; // Recipient
        $email->Cc = $cc; // Cc
        $email->Bcc = $bcc; // Bcc
        $email->Subject = $subject; // Subject
        $email->Format = "html";
        if ($message != "") {
            $message = RemoveXss($message) . "<br><br>";
        }
        $email->Content = $message;
        if ($inline) { // Inline
            foreach ($TempImages as $tmpimage) {
                $email->addEmbeddedImage($tmpimage);
            }
            $email->Content .= $content;
        } else { // Attachment
            $email->addAttachment($fileName, $content);
        }
        $args = [];
        $emailSent = false;
        $tbl = $this->Table;
        if (!method_exists($this->Table, "emailSending") || $this->Table->emailSending($email, $args)) {
            $emailSent = $email->send();
        }

        // Check email sent status
        if ($emailSent) {
            // Update email sent count
            $_SESSION[Config("EXPORT_EMAIL_COUNTER")] = ($_SESSION[Config("EXPORT_EMAIL_COUNTER")] ?? 0) + 1;

            // Sent email success
            return ["success" => true, "message" => $Language->phrase("SendEmailSuccess")];
        } else {
            // Sent email failure
            return ["success" => false, "message" => !EmptyValue($email->SendErrDescription) ? $email->SendErrDescription : $Language->phrase("FailedToSendMail")];
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
            return $this->send($fileName); // Send email
        }
    }

    // Destructor
    public function __destruct()
    {
    }
}
