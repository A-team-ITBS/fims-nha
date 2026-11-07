<?php

namespace PHPMaker2024\FIMS;

use Symfony\Component\Finder\Finder;

/**
 * SMS class
 */
abstract class Sms
{
    public $Recipient = ""; // Recipient
    public $Content = ""; // Content
    public $SendErrDescription; // Send error description
    protected $data;

    /**
     * Load message from template name
     *
     * @param string $name Template name
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
        $finder = Finder::create()->files()->in(Config("LANGUAGE_FOLDER"))->name($parts["filename"] . "." . $langId . "." . $parts["extension"]); // Template for the language ID
        if (!$finder->hasResults()) {
            $finder->files()->name($parts["filename"]  . ".en-US." . $parts["extension"]); // Fallback to en-US
        }
        if ($finder->hasResults()) {
            $wrk = "";
            $view = Container("sms.view");
            foreach ($finder as $file) {
                $wrk = $view->fetchTemplate($file->getFileName(), $data);
            }
            $this->Content = $wrk;
        } else {
            throw new \Exception("Failed to load sms template '$name' for language '$langId'");
        }
    }

    /**
     * Replace content
     *
     * @param string $find String to find
     * @param string $replaceWith String to replace
     * @return void
     */
    public function replaceContent($find, $replaceWith)
    {
        $this->Content = str_replace($find, $replaceWith, $this->Content);
    }

    /**
     * Send SMS
     *
     * @return bool Whether SMS is sent successfully
     */
    public function send()
    {
        //var_dump($this->Content, $this->Recipient);
        return false; // Not implemented
    }
}
