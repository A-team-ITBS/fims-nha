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
 * Entity class for "v_department_hierarchy" table
 */
#[Entity]
#[Table(schema: "uac", name: "v_department_hierarchy")]
class VDepartmentHierarchy extends AbstractEntity
{
    #[Column(name: "department_id", type: "integer", nullable: true)]
    private ?int $departmentId;

    #[Column(name: "department_name", type: "string", nullable: true)]
    private ?string $departmentName;

    #[Column(name: "department_code", type: "string", nullable: true)]
    private ?string $departmentCode;

    #[Column(name: "department_type", type: "string", nullable: true)]
    private ?string $departmentType;

    #[Column(name: "hierarchy_level", type: "integer", nullable: true)]
    private ?int $hierarchyLevel;

    #[Column(name: "parent_department_id", type: "integer", nullable: true)]
    private ?int $parentDepartmentId;

    #[Column(name: "is_budget_unit", type: "boolean", nullable: true)]
    private ?bool $isBudgetUnit;

    #[Column(name: "rc_code", type: "string", nullable: true)]
    private ?string $rcCode;

    #[Column(name: "rc_name", type: "string", nullable: true)]
    private ?string $rcName;

    #[Column(name: "office_name", type: "string", nullable: true)]
    private ?string $officeName;

    #[Column(name: "office_code", type: "string", nullable: true)]
    private ?string $officeCode;

    #[Column(name: "full_path", type: "text", nullable: true)]
    private ?string $fullPath;

    #[Column(type: "integer", nullable: true)]
    private ?int $depth;

    public function getDepartmentId(): ?int
    {
        return $this->departmentId;
    }

    public function setDepartmentId(?int $value): static
    {
        $this->departmentId = $value;
        return $this;
    }

    public function getDepartmentName(): ?string
    {
        return HtmlDecode($this->departmentName);
    }

    public function setDepartmentName(?string $value): static
    {
        $this->departmentName = RemoveXss($value);
        return $this;
    }

    public function getDepartmentCode(): ?string
    {
        return HtmlDecode($this->departmentCode);
    }

    public function setDepartmentCode(?string $value): static
    {
        $this->departmentCode = RemoveXss($value);
        return $this;
    }

    public function getDepartmentType(): ?string
    {
        return HtmlDecode($this->departmentType);
    }

    public function setDepartmentType(?string $value): static
    {
        $this->departmentType = RemoveXss($value);
        return $this;
    }

    public function getHierarchyLevel(): ?int
    {
        return $this->hierarchyLevel;
    }

    public function setHierarchyLevel(?int $value): static
    {
        $this->hierarchyLevel = $value;
        return $this;
    }

    public function getParentDepartmentId(): ?int
    {
        return $this->parentDepartmentId;
    }

    public function setParentDepartmentId(?int $value): static
    {
        $this->parentDepartmentId = $value;
        return $this;
    }

    public function getIsBudgetUnit(): ?bool
    {
        return $this->isBudgetUnit;
    }

    public function setIsBudgetUnit(?bool $value): static
    {
        $this->isBudgetUnit = $value;
        return $this;
    }

    public function getRcCode(): ?string
    {
        return HtmlDecode($this->rcCode);
    }

    public function setRcCode(?string $value): static
    {
        $this->rcCode = RemoveXss($value);
        return $this;
    }

    public function getRcName(): ?string
    {
        return HtmlDecode($this->rcName);
    }

    public function setRcName(?string $value): static
    {
        $this->rcName = RemoveXss($value);
        return $this;
    }

    public function getOfficeName(): ?string
    {
        return HtmlDecode($this->officeName);
    }

    public function setOfficeName(?string $value): static
    {
        $this->officeName = RemoveXss($value);
        return $this;
    }

    public function getOfficeCode(): ?string
    {
        return HtmlDecode($this->officeCode);
    }

    public function setOfficeCode(?string $value): static
    {
        $this->officeCode = RemoveXss($value);
        return $this;
    }

    public function getFullPath(): ?string
    {
        return HtmlDecode($this->fullPath);
    }

    public function setFullPath(?string $value): static
    {
        $this->fullPath = RemoveXss($value);
        return $this;
    }

    public function getDepth(): ?int
    {
        return $this->depth;
    }

    public function setDepth(?int $value): static
    {
        $this->depth = $value;
        return $this;
    }
}
