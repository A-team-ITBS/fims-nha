<?php

namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Bytes
 */
class BytesType extends Type
{
    const NAME = 'bytes';

    public function getName()
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return $platform->getBinaryTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return bin2hex($value ?? ''); // Convert binary data to hex string
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return hex2bin($value ?? ''); // Convert hex string to binary data
    }
}
