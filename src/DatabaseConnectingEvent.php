<?php

namespace PHPMaker2024\FIMS;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Database Connecting Event
 */
class DatabaseConnectingEvent extends GenericEvent
{
    public const NAME = "database.connecting";

    /**
     * Deprecated keys
     */
    public static $keys = [
        "pass" => "password",
        "db" => "dbname"
    ];

    /**
     * Sensitive keys
     */
    public static $sensitiveKeys = [
        "password"
    ];

    /**
     * Convert deprecated keys
     *
     * @param string $key Deprecated key
     * @return string Key
     */
    private function convertKey(string $key): string
    {
        if (IsDebug() && in_array($key, array_keys(self::$keys))) {
            Log("Deprecated: Using \"$key\" is deprecated, use \"" . self::$keys[$key] . "\" instead.");
        }
        return self::$keys[$key] ?? $key;
    }

    /**
     * Get argument by key
     *
     * @throws \InvalidArgumentException if key is not found
     */
    public function getArgument(string $key): mixed
    {
        return parent::getArgument($this->convertKey($key));
    }

    /**
     * Add argument to event
     *
     * @return $this
     */
    public function setArgument(string $key, mixed $value): static
    {
        $key = $this->convertKey($key);
        parent::setArgument($key, $value);
        if (IsDebug()) {
            if (in_array($key, self::$sensitiveKeys) && is_string($value)) {
                $value = str_pad(substr($value, 0, 1), strlen($value) - 1, "*");
            }
            Log(self::NAME . ": Set \"$key\" to " . json_encode($value));
        }
        return $this;
    }

    /**
     * Has argument
     */
    public function hasArgument(string $key): bool
    {
        return parent::hasArgument($this->convertKey($key));
    }
}
