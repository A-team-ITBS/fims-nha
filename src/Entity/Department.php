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
 * Entity class for "departments" table
 */
#[Entity]
#[Table(schema: "uac", name: "departments")]
class Department extends AbstractEntity
{
    #[Id]
    #[Column(name: "department_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "departments_department_id_seq")]
    private int $departmentId;

    #[Column(name: "department_name", type: "string")]
    private string $departmentName;

    #[Column(type: "text", nullable: true)]
    private ?string $description;

    #[Column(name: "parent_department_id", type: "integer", nullable: true)]
    private ?int $parentDepartmentId;

    #[Column(name: "department_head_id", type: "integer", nullable: true)]
    private ?int $departmentHeadId;

    #[Column(name: "department_code", type: "string", nullable: true)]
    private ?string $departmentCode;

    #[Column(name: "department_acronym", type: "string", nullable: true)]
    private ?string $departmentAcronym;

    #[Column(name: "office_location", type: "string", nullable: true)]
    private ?string $officeLocation;

    #[Column(name: "contact_number", type: "string", nullable: true)]
    private ?string $contactNumber;

    #[Column(type: "string", nullable: true)]
    private ?string $email;

    #[Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive;

    #[Column(name: "created_at", type: "datetimetz", nullable: true)]
    private ?DateTime $createdAt;

    #[Column(name: "updated_at", type: "datetimetz", nullable: true)]
    private ?DateTime $updatedAt;

    #[Column(name: "rc_id", type: "integer", nullable: true)]
    private ?int $rcId;

    #[Column(name: "office_id", type: "integer", nullable: true)]
    private ?int $officeId;

    #[Column(name: "department_type", type: "string", nullable: true)]
    private ?string $departmentType;

    #[Column(name: "hierarchy_level", type: "integer", nullable: true)]
    private ?int $hierarchyLevel;

    #[Column(name: "is_budget_unit", type: "boolean", nullable: true)]
    private ?bool $isBudgetUnit;

    public function __construct()
    {
        $this->hierarchyLevel = 1;
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

    public function getDepartmentName(): string
    {
        return HtmlDecode($this->departmentName);
    }

    public function setDepartmentName(string $value): static
    {
        $this->departmentName = RemoveXss($value);
        return $this;
    }

    public function getDescription(): ?string
    {
        return HtmlDecode($this->description);
    }

    public function setDescription(?string $value): static
    {
        $this->description = RemoveXss($value);
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

    public function getDepartmentHeadId(): ?int
    {
        return $this->departmentHeadId;
    }

    public function setDepartmentHeadId(?int $value): static
    {
        $this->departmentHeadId = $value;
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

    public function getDepartmentAcronym(): ?string
    {
        return HtmlDecode($this->departmentAcronym);
    }

    public function setDepartmentAcronym(?string $value): static
    {
        $this->departmentAcronym = RemoveXss($value);
        return $this;
    }

    public function getOfficeLocation(): ?string
    {
        return HtmlDecode($this->officeLocation);
    }

    public function setOfficeLocation(?string $value): static
    {
        $this->officeLocation = RemoveXss($value);
        return $this;
    }

    public function getContactNumber(): ?string
    {
        return HtmlDecode($this->contactNumber);
    }

    public function setContactNumber(?string $value): static
    {
        $this->contactNumber = RemoveXss($value);
        return $this;
    }

    public function getEmail(): ?string
    {
        return HtmlDecode($this->email);
    }

    public function setEmail(?string $value): static
    {
        $this->email = RemoveXss($value);
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

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $value): static
    {
        $this->createdAt = $value;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $value): static
    {
        $this->updatedAt = $value;
        return $this;
    }

    public function getRcId(): ?int
    {
        return $this->rcId;
    }

    public function setRcId(?int $value): static
    {
        $this->rcId = $value;
        return $this;
    }

    public function getOfficeId(): ?int
    {
        return $this->officeId;
    }

    public function setOfficeId(?int $value): static
    {
        $this->officeId = $value;
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

    public function getIsBudgetUnit(): ?bool
    {
        return $this->isBudgetUnit;
    }

    public function setIsBudgetUnit(?bool $value): static
    {
        $this->isBudgetUnit = $value;
        return $this;
    }
}
