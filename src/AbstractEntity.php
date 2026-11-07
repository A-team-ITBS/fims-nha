<?php

namespace PHPMaker2024\FIMS;

use Doctrine\ORM\Mapping\Column;
use ReflectionClass;
use ReflectionProperty;
use ReflectionAttribute;

/**
 * Abstract entity class
 */
abstract class AbstractEntity
{
    private array $propertyNames;

    /**
     * Initiate property names
     */
    public function initiate()
    {
        if (isset($this->propertyNames)) {
            return;
        }
        $reflect = new ReflectionClass($this);
        $props = $reflect->getProperties(ReflectionProperty::IS_PRIVATE);
        foreach ($props as $prop) {
            $attributes = $prop->getAttributes(Column::class, ReflectionAttribute::IS_INSTANCEOF);
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $fldname = $instance->options["name"] ?? $instance->name ?? $prop->getName();
                $this->propertyNames[$fldname] = $prop->getName();
            }
        }
    }

    /**
     * Check if column is initialized
     *
     * @param string $name Column name
     * @return bool
     */
    public function isInitialized($name)
    {
        $this->initiate();
        $propName = $this->propertyNames[$name] ?? null;
        if ($propName) {
            $rp = new ReflectionProperty($this, $propName);
            $rp->setAccessible(true); // For PHP 8.0 only
            return $rp->isInitialized($this);
        }
        return false;
    }

    /**
     * Get value by column name
     *
     * @param string $name Column name
     * @return mixed
     */
    public function get($name)
    {
        $this->initiate();
        $method = "get" . ($this->propertyNames[$name] ?? $name); // Method name is case-insensitive
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }

    /**
     * Set value by column name
     *
     * @param string $name Column name
     * @param mixed $value Value
     * @return static
     */
    public function set($name, $value): static
    {
        $this->initiate();
        $method = "set" . ($this->propertyNames[$name] ?? $name); // Method name is case-insensitive
        if (method_exists($this, $method)) {
            $this->$method($value);
        }
        return $this;
    }

    /**
     * Convert to array with column name as keys
     *
     * @return array
     */
    public function toArray()
    {
        $this->initiate();
        $names = array_keys($this->propertyNames);
        return array_combine($names, array_map(fn ($name) => $this->isInitialized($name) ? $this->get($name) : null, $names));
    }
}
