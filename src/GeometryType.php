<?php

namespace PHPMaker2024\FIMS;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/**
 * Geometry
 */
class GeometryType extends Type
{
    public const NAME = 'geometry';

    public function getName()
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return 'GEOMETRY';
    }

    public function canRequireSQLConversion()
    {
        return true;
    }

    // public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string // For DBAL 4 (PHP ^8.1)
    public function convertToPHPValueSQL($sqlExpr, $platform)
    {
        if ($platform instanceof MySQLPlatform || $platform instanceof PostgreSQLPlatform) { // MySQL/PostgreSQL
            return sprintf('ST_AsText(%s)', $sqlExpr);
        } elseif ($platform instanceof SQLServerPlatform) { // Microsoft SQL Server
            return sprintf('%s.ToString()', $sqlExpr);
        }
        return $sqlExpr;
    }

    public function convertToDatabaseValueSQL($sqlExpr, AbstractPlatform $platform)
    {
        return ($platform instanceof MySQLPlatform || $platform instanceof PostgreSQLPlatform) // MySQL/PostgreSQL
            ? sprintf('ST_GeomFromText(%s)', $sqlExpr)
            : $sqlExpr;
    }
}
