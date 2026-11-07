<?php

namespace PHPMaker2024\FIMS;

use Dflydev\DotAccessData\Data;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Langauge class
 */
class Language
{
    public static bool $SortByName = false;
    public static bool $SortByCaseInsensitiveName = false;
    public static bool $SortBySize = false;
    public static bool $ReverseSorting = false;
    public static bool $UseCache = false;
    public static string $CACHE_FILE = "LanguageCache.*.php"; // Language file under CACHE_FOLDER
    public $Data = null;
    public $LanguageId;
    public $LanguageFolder;
    public $Template = ""; // JsRender template
    public $Method = "prependTo"; // JsRender template method
    public $Target = ".navbar-nav.ms-auto"; // JsRender template target
    public $Type = "LI"; // LI/DROPDOWN (for used with top Navbar) or SELECT/RADIO (NOT for used with top Navbar)

    // Constructor
    public function __construct()
    {
        $this->setLanguage(Param("language"));
    }

    // Set language
    public function setLanguage($langId)
    {
        global $CurrentLanguage, $EventDispatcher;
        $this->LanguageFolder = Config("LANGUAGE_FOLDER");
        if ($langId) {
            $this->LanguageId = $langId;
            $_SESSION[SESSION_LANGUAGE_ID] = $this->LanguageId;
        } elseif (Session(SESSION_LANGUAGE_ID) != "") {
            $this->LanguageId = Session(SESSION_LANGUAGE_ID);
        } else {
            $this->LanguageId = Config("DEFAULT_LANGUAGE_ID");
        }
        $CurrentLanguage = $this->LanguageId;
        $this->loadLanguage($this->LanguageId);

        // Dispatch event
        DispatchEvent(new LanguageLoadEvent($this), LanguageLoadEvent::NAME);
        SetClientVar("languages", ["languages" => $this->getLanguages()]);
    }

    // Parse XML
    protected function parseXml($xml, &$values)
    {
        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); // Always return in utf-8
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, $xml, $values);
        $errorCode = xml_get_error_code($parser);
        if ($errorCode > 0) {
            throw new \Exception(xml_error_string($errorCode));
        }
        xml_parser_free($parser);
    }

    /**
     * Load XML
     * <ew-language> // level 1
     *     <global> // level 2
     *         <phrase/> // level 3
     *         <extension> // level 3
     *             <phrase/> // level 4
     * @param string $xml XML
     * @return void
     */
    protected function loadXml($xml)
    {
        $data = new Data();
        $xml = trim($xml);
        if (!$xml) {
            return $data;
        }
        $this->parseXml(trim($xml), $xmlValues);
        if (!is_array($xmlValues)) {
            return $data;
        }
        $tags = [];
        foreach ($xmlValues as $xmlValue) {
            $attributes = null; // Reset attributes first
            extract($xmlValue); // Extract as $tag (string), $type (string), $level (int) and $attributes (array)
            if ($level == 1) {
                continue; // Skip root tag
            }
            if ($type == "open" || $type == "complete") { // Open tag like '<tag ...>' or complete tag like '<tag/>'
                if ($attributes["id"] ?? false) { // Has "id" attribute
                    $convert = fn ($id) => ($tags[2] ?? "") == "global" && $level > 3 // Extension phrases
                        ? $id // Keep the id as camel case as JavaScript
                        : strtolower($id);
                    if ($type == "open") {
                        $tag .= "." . $convert($attributes["id"]); // Convert id to lowercase
                    } elseif ($type == "complete") { // <phrase/>
                        $tag = $convert($attributes["id"]); // Convert id to lowercase
                    }
                    unset($attributes["id"]);
                }
                $tags[$level] = $tag;
                if (is_array($attributes) && count($attributes) > 0 && $level > 1) {
                    $data->set(implode(".", array_filter(array_slice($tags, 0, $level - 1))), ConvertFromUtf8($attributes));
                }
            }
        }
        return $data;
    }

    // Get cache folder
    protected static function getCacheFolder()
    {
        return __DIR__ . "/../" . Config("CACHE_FOLDER") . "/";
    }

    // Load language file(s)
    protected function loadLanguage($id)
    {
        global $CURRENCY_CODE, $CURRENCY_SYMBOL, $DECIMAL_SEPARATOR, $GROUPING_SEPARATOR,
            $NUMBER_FORMAT, $CURRENCY_FORMAT, $PERCENT_SYMBOL, $PERCENT_FORMAT, $NUMBERING_SYSTEM,
            $DATE_FORMAT, $TIME_FORMAT, $DATE_SEPARATOR, $TIME_SEPARATOR, $TIME_ZONE;
        $cacheFile = str_replace("*", $id, self::getCacheFolder() . self::$CACHE_FILE);
        if (self::$UseCache && !IsRemote($cacheFile) && file_exists($cacheFile)) {
            $this->Data = new Data(require $cacheFile);
        } else {
            $this->Data = new Data();
            $finder = new Finder();
            $finder->files()->in($this->LanguageFolder)->name("*.$id.xml"); // Find all *.$id.xml
            if (!$finder->hasResults()) {
                LogError("Missing language files for language ID '$id'");
                $finder->files()->in($this->LanguageFolder)->name("*.en-US.xml"); // Fallback to en-US
            }
            if (self::$SortBySize && method_exists($finder, "sortBySize")) {
                $finder->sortBySize();
            }
            if (self::$SortByName) {
                $finder->sortByName();
            }
            if (self::$SortByCaseInsensitiveName) {
                if (method_exists($finder, "sortByCaseInsensitiveName")) {
                    $finder->sortByCaseInsensitiveName();
                } else {
                    $finder->sortByName();
                }
            }
            if (self::$ReverseSorting) {
                $finder->reverseSorting();
            }
            foreach ($finder as $file) {
                try {
                    $this->Data->importData($this->loadXml($file->getContents()));
                } catch (\Exception $e) {
                    $_SESSION[SESSION_LANGUAGE_ID] = ""; // Clear the saved language ID from session
                    throw new \Exception("Error occurred when parsing " . $file->getFilename() . ": " . $e->getMessage() . ". Make sure it is well-formed.");
                }
            }
            if (self::$UseCache && CreateFolder(self::getCacheFolder())) {
                file_put_contents($cacheFile, "<?php return " . VarExporter::export($this->Data->export()) . ";");
            }
        }

        // Set up locale for the language
        $locale = LocaleConvert();
        $CURRENCY_CODE = $locale["currency_code"];
        $CURRENCY_SYMBOL = $locale["currency_symbol"];
        $DECIMAL_SEPARATOR = $locale["decimal_separator"];
        $GROUPING_SEPARATOR = $locale["grouping_separator"];
        $NUMBER_FORMAT = $locale["number"];
        $CURRENCY_FORMAT = $locale["currency"];
        $PERCENT_SYMBOL = $locale["percent_symbol"];
        $PERCENT_FORMAT = $locale["percent"];
        $NUMBERING_SYSTEM = $locale["numbering_system"];
        $DATE_FORMAT = $locale["date"];
        $TIME_FORMAT = $locale["time"];
        $DATE_SEPARATOR = $locale["date_separator"];
        $TIME_SEPARATOR = $locale["time_separator"];
        $TIME_ZONE = $locale["time_zone"];

        // Set up time zone from locale file (see https://www.php.net/timezones for supported time zones)
        if (!empty($TIME_ZONE)) {
            date_default_timezone_set($TIME_ZONE);
        }

        // Save to Laravel session
        LaravelSession(["Language" => $this->LanguageId, "TimeZone" => $TIME_ZONE]);
    }

    // Get value only
    protected function getValue($data)
    {
        $collect = Collection::make($data);
        if ($collect->count() > 0) {
            if ($collect->every(fn ($v) => is_array($v))) { // Array of array
                return $collect->map(fn ($v) => $this->getValue($v))->all();
            }
            return $collect->get("value") ?? "";
        }
        return "";
    }

    // Has data
    public function hasData($id)
    {
        return $this->Data->has(strtolower($id ?? ""));
    }

    // Set data
    public function setData($id, $value)
    {
        $this->Data->set(strtolower($id ?? ""), $value);
    }

    // Get data
    public function getData($id)
    {
        return $this->Data->get(strtolower($id ?? ""), "");
    }

    /**
     * Get phrase
     *
     * @param string $id Phrase ID
     * @param mixed $useText (true => text only, false => icon only, null => both)
     * @return string|array
     */
    public function phrase($id, $useText = false)
    {
        $className = $this->getData("global." . $id . ".class");
        if ($this->hasData("global." . $id)) {
            $data = $this->getData("global." . $id);
            $value = $this->getValue($data);
        } else {
            $value = $id;
        }
        if (is_string($value) && $useText !== true && $className != "") {
            if ($useText === null && $value !== "") { // Use both icon and text
                AppendClass($className, "me-2");
            }
            if (preg_match('/\bspinner\b/', $className)) { // Spinner
                $res = '<div class="' . $className . '" role="status"><span class="visually-hidden">' . $value . '</span></div>';
            } else { // Icon
                $res = '<i data-phrase="' . $id . '" class="' . $className . '"><span class="visually-hidden">' . $value . '</span></i>';
            }
            if ($useText === null && $value !== "") { // Use both icon and text
                $res .= $value;
            }
            return $res;
        }
        return $value;
    }

    // Set phrase
    public function setPhrase($id, $value)
    {
        $this->setPhraseAttr($id, "value", $value);
    }

    // Get project phrase
    public function projectPhrase($id)
    {
        return $this->getData("project." . $id . ".value");
    }

    // Set project phrase
    public function setProjectPhrase($id, $value)
    {
        $this->setData("project." . $id . ".value", $value);
    }

    // Get menu phrase
    public function menuPhrase($menuId, $id)
    {
        return $this->getData("project.menu." . $menuId . "." . $id . ".value");
    }

    // Set menu phrase
    public function setMenuPhrase($menuId, $id, $value)
    {
        $this->setData("project.menu." . $menuId . "." . $id . ".value", $value);
    }

    // Get table phrase
    public function tablePhrase($tblVar, $id)
    {
        return $this->getData("project.table." . $tblVar .  "." . $id . ".value");
    }

    // Set table phrase
    public function setTablePhrase($tblVar, $id, $value)
    {
        $this->setData("project.table." . $tblVar .  "." . $id . ".value", $value);
    }

    // Get chart phrase
    public function chartPhrase($tblVar, $chtVar, $id)
    {
        return $this->getData("project.table." . $tblVar .  ".chart." . $chtVar . "." . $id . ".value");
    }

    // Set chart phrase
    public function setChartPhrase($tblVar, $chtVar, $id, $value)
    {
        $this->setData("project.table." . $tblVar .  ".chart." . $chtVar . "." . $id . ".value", $value);
    }

    // Get field phrase
    public function fieldPhrase($tblVar, $fldVar, $id)
    {
        return $this->getData("project.table." . $tblVar .  ".field." . $fldVar . "." . $id . ".value");
    }

    // Set field phrase
    public function setFieldPhrase($tblVar, $fldVar, $id, $value)
    {
        $this->setData("project.table." . $tblVar .  ".field." . $fldVar . "." . $id . ".value", $value);
    }

    // Get phrase attribute
    protected function phraseAttr($id, $name)
    {
        return $this->getData("global." . $id . "." . $name);
    }

    // Set phrase attribute
    protected function setPhraseAttr($id, $name, $value)
    {
        $this->setData("global." . $id . "." . $name, $value);
    }

    // Get phrase class
    public function phraseClass($id)
    {
        return $this->phraseAttr($id, "class");
    }

    // Set phrase attribute
    public function setPhraseClass($id, $value)
    {
        $this->setPhraseAttr($id, "class", $value);
    }

    // Output array as JSON
    public function arrayToJson()
    {
        $ar = $this->Data->get("global");
        $keys = array_keys($ar);
        $res = array_combine($keys, array_map(fn($id) => $this->phrase($id, true), $keys));
        return JsonEncode($res);
    }

    // Output phrases to client side as JSON
    public function toJson()
    {
        return "ew.language.phrases = " . $this->arrayToJson() . ";";
    }

    // Output languages as array
    protected function getLanguages()
    {
        global $LANGUAGES, $CurrentLanguage;
        $ar = [];
        if (is_array($LANGUAGES) && count($LANGUAGES) > 1) {
            $finder = new Finder();
            $finder->files()->in($this->LanguageFolder)->name(Config("LANGUAGES_FILE")); // Find languages.xml
            foreach ($finder as $file) {
                $data = $this->loadXml($file->getContents());
                foreach ($LANGUAGES as $langId) {
                    $lang = array_merge([ "id" => $langId ], $data->has("global." . strtolower($langId)) ? $data->get("global." . strtolower($langId)) : [ "desc" => $this->phrase($langId) ]);
                    $lang["selected"] = $langId == $CurrentLanguage;
                    $ar[] = $lang;
                }
                break; // Only one file
            }
        }
        return $ar;
    }

    // Set template
    public function setTemplate($value)
    {
        $this->Template = $value;
    }

    // Get template
    public function getTemplate()
    {
        if ($this->Template == "") {
            if (SameText($this->Type, "LI")) { // LI template (for used with top Navbar)
                return '{{for languages}}<li class="nav-item"><a class="nav-link{{if selected}} active{{/if}} ew-tooltip" title="{{>desc}}" data-ew-action="language" data-language="{{:id}}">{{:id}}</a></li>{{/for}}';
            } elseif (SameText($this->Type, "DROPDOWN")) { // DROPDOWN template (for used with top Navbar)
                return '<li class="nav-item dropdown"><a class="nav-link" data-bs-toggle="dropdown"><i class="fa-solid fa-globe ew-icon"></i></span></a><div class="dropdown-menu dropdown-menu-lg dropdown-menu-end">{{for languages}}<a class="dropdown-item{{if selected}} active{{/if}}" data-ew-action="language" data-language="{{:id}}">{{>desc}}</a>{{/for}}</div></li>';
            } elseif (SameText($this->Type, "SELECT")) { // SELECT template (NOT for used with top Navbar)
                return '<div class="ew-language-option"><select class="form-select" id="ew-language" name="ew-language" data-ew-action="language">{{for languages}}<option value="{{:id}}"{{if selected}} selected{{/if}}>{{:desc}}</option>{{/for}}</select></div>';
            } elseif (SameText($this->Type, "RADIO")) { // RADIO template (NOT for used with top Navbar)
                return '<div class="ew-language-option"><div class="btn-group" data-bs-toggle="buttons">{{for languages}}<input type="radio" name="ew-language" id="ew-Language-{{:id}}" data-ew-action="language"{{if selected}} checked{{/if}} value="{{:id}}"><label class="btn btn-default ew-tooltip" for="ew-language-{{:id}}" data-container="body" data-bs-placement="bottom" title="{{>desc}}">{{:id}}</label>{{/for}}</div></div>';
            }
        }
        return $this->Template;
    }
}
