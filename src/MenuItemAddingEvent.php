<?php

namespace PHPMaker2024\FIMS;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Menu Item Adding Event
 */
class MenuItemAddingEvent extends Event
{
    public const NAME = "menu.item.adding";

    public function __construct(
        private $menuItem = null,
        private $menu = null)
    {
    }

    public function getMenuItem(): MenuItem
    {
        return $this->menuItem;
    }

    public function getSubject(): MenuItem
    {
        return $this->menuItem;
    }

    public function setMenuItem(MenuItem $value)
    {
        $this->menuItem = $value;
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }

    public function setMenu(Menu $value)
    {
        $this->menu = $value;
    }
}
