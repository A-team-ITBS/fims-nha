<?php

namespace PHPMaker2024\FIMS\Entity;

use DateTime;
use DateTimeImmutable;
use DateInterval;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\SequenceGenerator;
use Doctrine\DBAL\Types\Types;
use PHPMaker2024\FIMS\AbstractEntity;
use PHPMaker2024\FIMS\AdvancedSecurity;
use PHPMaker2024\FIMS\UserProfile;
use function PHPMaker2024\FIMS\Config;
use function PHPMaker2024\FIMS\EntityManager;
use function PHPMaker2024\FIMS\RemoveXss;
use function PHPMaker2024\FIMS\HtmlDecode;
use function PHPMaker2024\FIMS\EncryptPassword;

/**
 * Entity class for "user_level_permissions" table
 */
#[Entity]
#[Table(schema: "uac", name: "user_level_permissions")]
class UserLevelPermission extends AbstractEntity
{
    #[Id]
    #[Column(name: "user_level_id", type: "integer")]
    private int $userLevelId;

    #[Id]
    #[Column(name: "table_name", type: "string")]
    private string $tableName;

    #[Column(type: "integer")]
    private int $permission;

    public function __construct(int $userLevelId, string $tableName)
    {
        $this->userLevelId = $userLevelId;
        $this->tableName = $tableName;
    }

    public function getUserLevelId(): int
    {
        return $this->userLevelId;
    }

    public function setUserLevelId(int $value): static
    {
        $this->userLevelId = $value;
        return $this;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function setTableName(string $value): static
    {
        $this->tableName = $value;
        return $this;
    }

    public function getPermission(): int
    {
        return $this->permission;
    }

    public function setPermission(int $value): static
    {
        $this->permission = $value;
        return $this;
    }
}
