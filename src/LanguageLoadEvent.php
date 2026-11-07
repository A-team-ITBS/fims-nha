<?php

namespace PHPMaker2024\FIMS;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Language Load Event
 */
class LanguageLoadEvent extends Event
{
    public const NAME = "language.load";

    public function __construct(protected Language $language)
    {
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function getSubject(): Language
    {
        return $this->language;
    }

    public function setPhrase($id, $value)
    {
        $this->language->setPhrase($id, $value);
    }
}
