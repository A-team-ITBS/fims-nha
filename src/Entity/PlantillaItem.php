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
 * Entity class for "plantilla_items" table
 */
#[Entity]
#[Table(schema: "uac", name: "plantilla_items")]
class PlantillaItem extends AbstractEntity
{
    #[Id]
    #[Column(name: "plantilla_item_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "plantilla_items_plantilla_item_id_seq")]
    private int $plantillaItemId;

    #[Column(name: "department_id", type: "integer")]
    private int $departmentId;

    #[Column(name: "position_id", type: "integer")]
    private int $positionId;

    #[Column(name: "item_number", type: "string")]
    private string $itemNumber;

    #[Column(name: "item_description", type: "string", nullable: true)]
    private ?string $itemDescription;

    #[Column(name: "authorized_count", type: "integer", nullable: true)]
    private ?int $authorizedCount;

    #[Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive;

    #[Column(name: "effective_date", type: "date")]
    private DateTime $effectiveDate;

    #[Column(name: "abolition_date", type: "date", nullable: true)]
    private ?DateTime $abolitionDate;

    #[Column(name: "annual_budget_allocation", type: "decimal", nullable: true)]
    private ?string $annualBudgetAllocation;

    #[Column(name: "monthly_salary_allocation", type: "decimal", nullable: true)]
    private ?string $monthlySalaryAllocation;

    #[Column(type: "text", nullable: true)]
    private ?string $remarks;

    #[Column(name: "created_by", type: "integer", nullable: true)]
    private ?int $createdBy;

    #[Column(name: "approved_by", type: "integer", nullable: true)]
    private ?int $approvedBy;

    #[Column(name: "created_at", type: "datetimetz", nullable: true)]
    private ?DateTime $createdAt;

    #[Column(name: "updated_at", type: "datetimetz", nullable: true)]
    private ?DateTime $updatedAt;

    public function __construct()
    {
        $this->authorizedCount = 1;
    }

    public function getPlantillaItemId(): int
    {
        return $this->plantillaItemId;
    }

    public function setPlantillaItemId(int $value): static
    {
        $this->plantillaItemId = $value;
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

    public function getPositionId(): int
    {
        return $this->positionId;
    }

    public function setPositionId(int $value): static
    {
        $this->positionId = $value;
        return $this;
    }

    public function getItemNumber(): string
    {
        return HtmlDecode($this->itemNumber);
    }

    public function setItemNumber(string $value): static
    {
        $this->itemNumber = RemoveXss($value);
        return $this;
    }

    public function getItemDescription(): ?string
    {
        return HtmlDecode($this->itemDescription);
    }

    public function setItemDescription(?string $value): static
    {
        $this->itemDescription = RemoveXss($value);
        return $this;
    }

    public function getAuthorizedCount(): ?int
    {
        return $this->authorizedCount;
    }

    public function setAuthorizedCount(?int $value): static
    {
        $this->authorizedCount = $value;
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

    public function getEffectiveDate(): DateTime
    {
        return $this->effectiveDate;
    }

    public function setEffectiveDate(DateTime $value): static
    {
        $this->effectiveDate = $value;
        return $this;
    }

    public function getAbolitionDate(): ?DateTime
    {
        return $this->abolitionDate;
    }

    public function setAbolitionDate(?DateTime $value): static
    {
        $this->abolitionDate = $value;
        return $this;
    }

    public function getAnnualBudgetAllocation(): ?string
    {
        return $this->annualBudgetAllocation;
    }

    public function setAnnualBudgetAllocation(?string $value): static
    {
        $this->annualBudgetAllocation = $value;
        return $this;
    }

    public function getMonthlySalaryAllocation(): ?string
    {
        return $this->monthlySalaryAllocation;
    }

    public function setMonthlySalaryAllocation(?string $value): static
    {
        $this->monthlySalaryAllocation = $value;
        return $this;
    }

    public function getRemarks(): ?string
    {
        return HtmlDecode($this->remarks);
    }

    public function setRemarks(?string $value): static
    {
        $this->remarks = RemoveXss($value);
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $value): static
    {
        $this->createdBy = $value;
        return $this;
    }

    public function getApprovedBy(): ?int
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?int $value): static
    {
        $this->approvedBy = $value;
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
}
