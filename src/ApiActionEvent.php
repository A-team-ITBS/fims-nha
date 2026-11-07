<?php

namespace PHPMaker2024\FIMS;

use Symfony\Component\EventDispatcher\GenericEvent;
use Slim\App;

/**
 * API Action Event
 */
class ApiActionEvent extends GenericEvent
{
    public const NAME = "api.action";

    public function getApp(): App
    {
        return $this->subject;
    }
}
