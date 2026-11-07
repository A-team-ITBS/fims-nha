<?php

namespace PHPMaker2024\FIMS;

use Symfony\Component\Finder\Finder;

/**
 * Email class
 */
class Email
{
    protected $data = [];
    public $LanguageFolder;
    public $Sender = ""; // Sender
    public $Recipient = ""; // Recipient
    public $Cc = ""; // Cc
    public $Bcc = ""; // Bcc
    public $Subject = ""; // Subject
    public $Format = ""; // Format
    public $Content = ""; // Content
    public $Attachments = []; // Attachments
    public $EmbeddedImages = []; // Embedded image
    public $Charset = ""; // Charset
    public $SendErrDescription; // Send error description
    public $SmtpSecure = ""; // Send secure option
    protected $Members = []; // PHPMailer members

    // Constructor
    public function __construct()
    {
        $this->Charset = Config("EMAIL_CHARSET");
        $this->SmtpSecure = Config("SMTP.SECURE_OPTION");
        $this->LanguageFolder = Config("LANGUAGE_FOLDER");
    }

    // Set PHPMailer property
    public function __set($name, $value)
    {
        $this->Members[$name] = $value;
    }

    // Call PHPMailer method
    public function __call($name, $arguments)
    {
        $this->Members[$name] = $arguments;
    }

    /**
     * Load message from template name
     *
     * @param string $name Template file name
     * @param string $langId Language ID
     * @param array $data Data for template
     * @return void
     */
    public function load($name, $langId = "", $data = [])
    {
        global $CurrentLanguage;
        $langId = $langId ?: $CurrentLanguage;
        $this->data = $data;
        $parts = pathinfo($name);
        $finder = Finder::create()->files()->in($this->LanguageFolder)->name($parts["filename"] . "." . $langId . "." . $parts["extension"]); // Template for the language ID
        if (!$finder->hasResults()) {
            $finder->files()->name($parts["filename"]  . ".en-US." . $parts["extension"]); // Fallback to en-US
        }
        if ($finder->hasResults()) {
            $wrk = "";
            $view = Container("email.view");
            foreach ($finder as $file) {
                $wrk = $view->fetchTemplate($file->getFileName(), $data);
            }
            if ($wrk && preg_match('/\r\r|\n\n|\r\n\r\n/', $wrk, $m, PREG_OFFSET_CAPTURE)) { // Locate header and email content
                $i = $m[0][1];
                $header = trim(substr($wrk, 0, $i)) . "\r\n"; // Add last CrLf for matching
                $this->Content = trim(substr($wrk, $i));
                if (preg_match_all('/(Subject|From|To|Cc|Bcc|Format)\s*:\s*(.*?(?=((Subject|From|To|Cc|Bcc|Format)\s*:|\r|\n)))/m', $header ?: "", $m)) {
                    $ar = array_combine($m[1], $m[2]);
                    $this->Subject = trim($ar["Subject"] ?? "");
                    $this->Sender = trim($ar["From"] ?? "");
                    $this->Recipient = trim($ar["To"] ?? "");
                    $this->Cc = trim($ar["Cc"] ?? "");
                    $this->Bcc = trim($ar["Bcc"] ?? "");
                    $this->Format = trim($ar["Format"] ?? "");
                }
            }
        } else {
            throw new \Exception("Failed to load email template '$name' for language '$langId'");
        }
    }

    // Get template data
    public function getData()
    {
        return $this->data;
    }

    // Replace sender
    public function replaceSender($sender, $senderName = "")
    {
        if ($senderName != "") {
            $sender = $senderName . " <" . $sender . ">";
        }
        $this->Sender = $sender;
    }

    // Replace recipient
    public function replaceRecipient($recipient, $recipientName = "")
    {
        if ($recipientName != "") {
            $recipient = $recipientName . " <" . $recipient . ">";
        }
        $this->addRecipient($recipient);
    }

    // Add recipient
    public function addRecipient($recipient, $recipientName = "")
    {
        if ($recipientName != "") {
            $recipient = $recipientName . " <" . $recipient . ">";
        }
        $this->Recipient = Concat($this->Recipient, $recipient, ";");
    }

    // Add cc email
    public function addCc($cc, $ccName = "")
    {
        if ($ccName != "") {
            $cc = $ccName . " <" . $cc . ">";
        }
        $this->Cc = Concat($this->Cc, $cc, ";");
    }

    // Add bcc email
    public function addBcc($bcc, $bccName = "")
    {
        if ($bccName != "") {
            $bcc = $bccName . " <" . $bcc . ">";
        }
        $this->Bcc = Concat($this->Bcc, $bcc, ";");
    }

    // Replace subject
    public function replaceSubject($subject)
    {
        $this->Subject = $subject;
    }

    // Replace content
    public function replaceContent($find, $replaceWith)
    {
        $this->Content = str_replace($find, $replaceWith, $this->Content);
    }

    /**
     * Add embedded image
     *
     * @param string $image File name of image (in global upload folder)
     * @return void
     */
    public function addEmbeddedImage($image)
    {
        if ($image != "") {
            $this->EmbeddedImages[] = $image;
        }
    }

    /**
     * Add attachment
     *
     * @param string $fileName Full file path (without $content) or file name (with $content)
     * @param string $content File content
     * @return void
     */
    public function addAttachment($fileName, $content = "")
    {
        if ($fileName != "") {
            $this->Attachments[] = ["filename" => $fileName, "content" => $content];
        }
    }

    /**
     * Send email
     *
     * @return bool Whether email is sent successfully
     */
    public function send()
    {
        global $CurrentLanguage;
        $langId = str_replace("-", "_", strtolower($CurrentLanguage));
        if (!in_array($langId, ["pt_br", "sr_latn", "zh_cn"])) {
            $langId = explode("_", $langId)[0];
        }
        if ($langId != "en" && !array_key_exists("setLanguage", $this->Members)) {
            $this->Members["setLanguage"] = [$langId];
        }
        $result = SendEmail(
            $this->Sender,
            $this->Recipient,
            $this->Cc,
            $this->Bcc,
            $this->Subject,
            $this->Content,
            $this->Format,
            $this->Charset,
            $this->SmtpSecure,
            $this->Attachments,
            $this->EmbeddedImages,
            $this->Members
        );
        if ($result === true) {
            return true;
        } else { // Error
            $this->SendErrDescription = $result;
            return false;
        }
    }
}
