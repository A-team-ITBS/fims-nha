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
 * Entity class for "department_systems" table
 */
#[Entity]
#[Table(schema: "uac", name: "department_systems")]
class DepartmentSystem extends AbstractEntity
{
    #[Id]
    #[Column(name: "department_system_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "department_systems_department_system_id_seq")]
    private int $departmentSystemId;

    #[Column(name: "department_id", type: "integer")]
    private int $departmentId;

    #[Column(name: "system_id", type: "integer")]
    private int $systemId;

    #[Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive;

    #[Column(name: "access_level", type: "string", nullable: true)]
    private ?string $accessLevel;

    #[Column(name: "assigned_by", type: "integer", nullable: true)]
    private ?int $assignedBy;

    #[Column(name: "assigned_at", type: "datetimetz", nullable: true)]
    private ?DateTime $assignedAt;

    public function __construct()
    {
        $this->accessLevel = "full";
    }

    public function getDepartmentSystemId(): int
    {
        return $this->departmentSystemId;
    }

    public function setDepartmentSystemId(int $value): static
    {
        $this->departmentSystemId = $value;
        return $this;
    }

    public function getDepartmentId(): int
    {
        return $this->departmentId;
    }

    public function setDepartmentId(int $value): static
    {
        $this->departmentId = $value;
        return $this;
    }

    public function getSystemId(): int
    {
        return $this->systemId;
    }

    public function setSystemId(int $value): static
    {
        $this->systemId = $value;
        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $value): static
    {
        $this->isActive = $value;
        return $this;
    }

    public function getAccessLevel(): ?string
    {
        return HtmlDecode($this->accessLevel);
    }

    public function setAccessLevel(?string $value): static
    {
        $this->accessLevel = RemoveXss($value);
        return $this;
    }

    public function getAssignedBy(): ?int
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(?int $value): static
    {
        $this->assignedBy = $value;
        return $this;
    }

    public function getAssignedAt(): ?DateTime
    {
        return $this->assignedAt;
    }

    public function setAssignedAt(?DateTime $value): static
    {
        $this->assignedAt = $value;
        return $this;
    }
}
