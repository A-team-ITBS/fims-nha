<?php

namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

/**
 * HierarchyId
 */
class HierarchyIdType extends Type
{
    public const NAME = 'hierarchyid';

    public function getName()
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return 'HIERARCHYID';
    }

    public function canRequireSQLConversion()
    {
        return true;
    }

    // public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string // For DBAL 4 (PHP ^8.1)
    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        if ($platform instanceof SQLServerPlatform) { // Microsoft SQL Server
            return sprintf('%s.ToString()', $sqlExpr);
        }
        return $sqlExpr;
    }

    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return $sqlExpr;
    }
}
