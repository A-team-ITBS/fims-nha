<?php

namespace PHPMaker2024\FIMS;

/**
 * Menu item class
 */
class MenuItem
{
    public $SubMenu = null; // Data type = Menu
    public $Target = "";
    public $Href = ""; // Href attribute
    public $Active = false;
    public $Attrs; // HTML attributes
    public $Level = 0;

    // Constructor
    public function __construct(
        public $Id = "",
        public $Name = "",
        public $Text = "",
        public $Url = "",
        public $ParentId = -1,
        public $Allowed = true,
        public $IsHeader = false,
        public $IsCustomUrl = false,
        public $Icon = "",
        public $Label = "", // HTML (for vertical menu only)
        public $IsNavbarItem = false,
        public $IsSidebarItem = false
    ) {
        $this->Attrs = new Attributes();
    }

    // Set property case-insensitively (for backward compatibility) // PHP
    public function __set($name, $value)
    {
        $vars = get_class_vars($this::class);
        foreach ($vars as $key => $val) {
            if (SameText($name, $key)) {
                $this->$key = $value;
                break;
            }
        }
    }

    // Get property case-insensitively (for backward compatibility) // PHP
    public function __get($name)
    {
        $vars = get_class_vars($this::class);
        foreach ($vars as $key => $val) {
            if (SameText($name, $key)) {
                return $this->$key;
                break;
            }
        }
        return null;
    }

    // Add submenu item
    public function addItem($item)
    {
        if ($this->SubMenu === null) {
            $this->SubMenu = new Menu($this->Id);
        }
        $this->SubMenu->Level = $this->Level + 1;
        $this->SubMenu->addItem($item);
    }

    // Set attribute
    public function setAttribute($name, $value)
    {
        if (is_string($this->Attrs) && !preg_match('/\b' . preg_quote($name, '/') . '\s*=/', $this->Attrs)) { // Only set if attribute does not already exist
            $this->Attrs .= ' ' . $name . '="' . $value . '"';
        } elseif ($this->Attrs instanceof Attributes) {
            if (StartsText("on", $name)) { // Events
                $this->Attrs->append($name, $value, ";");
            } elseif (SameText("class", $name)) { // Class
                $this->Attrs->appendClass($value);
            } else {
                $this->Attrs->append($name, $value);
            }
        }
    }

    // Render
    public function render($deep = true)
    {
        $url = GetUrl($this->Url);
        if (IsMobile() && !$this->IsCustomUrl && $url != "#") {
            $url = str_replace("#", (ContainsString($url, "?") ? "&" : "?") . "hash=", $url);
        }
        if ($url == "") {
            $this->setAttribute("data-ew-action", "none");
        }
        $icon = trim($this->Icon);
        if ($icon && ContainsString($icon, "fa-")) {
            $ar = ClassList($icon);
            if (count(array_intersect($ar, ["fa-solid", "fa-regular", "fa-light", "fa-thin", "fa-duotone", "fa-sharp", "fa-brands"])) == 0) {
                $ar[] = "fa-solid";
            }
            $icon = implode(" ", $ar);
        }
        $hasItems = $deep && $this->SubMenu !== null;
        $isOpened = $hasItems && $this->SubMenu->isOpened();
        $class = "";
        if ($this->IsNavbarItem) {
            AppendClass($class, SameString($this->ParentId, "-1") || $this->IsSidebarItem ? "nav-link" : "dropdown-item");
            if ($this->Active) {
                AppendClass($class, "active");
            }
            if ($hasItems && !$this->IsSidebarItem) {
                AppendClass($class, "dropdown-toggle ew-dropdown");
            }
        } else {
            AppendClass($class, "nav-link");
            if ($this->Active || $isOpened) {
                AppendClass($class, "active");
            }
        }
        AppendClass($class, @$this->Attrs["class"]); // Move all user classes at end
        $this->Attrs["class"] = $class; // Save classes to Attrs
        $attrs = is_string($this->Attrs) ? $this->Attrs : $this->Attrs->toString();
        return [
            "id" => $this->Id,
            "name" => $this->Name,
            "text" => $this->Text,
            "parentId" => $this->ParentId,
            "level" => $this->Level,
            "href" => $url,
            "attrs" => $attrs,
            "target" => $this->Target,
            "isHeader" => $this->IsHeader,
            "active" => $this->Active,
            "icon" => $icon,
            "label" => $this->Label,
            "isNavbarItem" => $this->IsNavbarItem,
            "items" => $hasItems ? $this->SubMenu->render() : null,
            "open" => $isOpened
        ];
    }
}
