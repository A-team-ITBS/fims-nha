<?php

namespace PHPMaker2024\FIMS;

/**
 * XML document class
 */
class XmlDocument
{
    public $RootTagName;
    public $SubTblName = '';
    public $RowTagName;
    public $XmlDoc = false;
    public $XmlTbl;
    public $XmlSubTbl;
    public $XmlRow;
    public $NullValue = "null";

    // Constructor
    public function __construct()
    {
        $this->XmlDoc = new \DOMDocument("1.0", "utf-8");
    }

    // XML tag name
    protected function xmlTagName($name)
    {
        $name = ConvertToUtf8($name);
        if (!preg_match('/\A(?!XML)[a-z][\w0-9-]*/i', $name)) {
            $name = "_" . $name;
        }
        return $name;
    }

    // Load
    public function load($fileName)
    {
        $filePath = realpath($fileName);
        return file_exists($filePath) ? $this->XmlDoc->load($filePath) : false;
    }

    // Get document element
    public function &documentElement()
    {
        $el = $this->XmlDoc->documentElement;
        return $ele;
    }

    // Get attribute
    public function getAttribute($element, $name)
    {
        return $element ? ConvertFromUtf8($element->getAttribute($name)) : "";
    }

    // Set attribute
    public function setAttribute($element, $name, $value)
    {
        !$element || $element->setAttribute($name, ConvertToUtf8($value));
    }

    // Select single node
    public function selectSingleNode($query)
    {
        $elements = $this->selectNodes($query);
        return ($elements->length > 0) ? $elements->item(0) : null;
    }

    // Select nodes
    public function selectNodes($query)
    {
        $xpath = new \DOMXPath($this->XmlDoc);
        return $xpath->query($query);
    }

    // Add root
    public function addRoot($rootTagName = 'table')
    {
        $this->RootTagName = $this->xmlTagName($rootTagName);
        $this->XmlTbl = $this->XmlDoc->createElement($this->RootTagName);
        $this->XmlDoc->appendChild($this->XmlTbl);
    }

    // Add row
    public function addRow($tableTagName = '', $rowTagName = 'row')
    {
        $this->RowTagName = $this->xmlTagName($rowTagName);
        $this->XmlRow = $this->XmlDoc->createElement($this->RowTagName);
        if ($tableTagName == '') {
            if ($this->XmlTbl) {
                $this->XmlTbl->appendChild($this->XmlRow);
            }
        } else {
            if ($this->SubTblName == '' || $this->SubTblName != $tableTagName) {
                $this->SubTblName = $this->xmlTagName($tableTagName);
                $this->XmlSubTbl = $this->XmlDoc->createElement($this->SubTblName);
                $this->XmlTbl->appendChild($this->XmlSubTbl);
            }
            if ($this->XmlSubTbl) {
                $this->XmlSubTbl->appendChild($this->XmlRow);
            }
        }
    }

    // Add field
    public function addField($name, $value)
    {
        $value ??= $this->NullValue;
        $value = ConvertToUtf8($value); // Convert to UTF-8
        $xmlfld = $this->XmlDoc->createElement($this->xmlTagName($name));
        $this->XmlRow->appendChild($xmlfld);
        $xmlfld->appendChild($this->XmlDoc->createTextNode($value));
    }

    // Get XML
    public function xml()
    {
        return $this->XmlDoc->saveXML();
    }
}
