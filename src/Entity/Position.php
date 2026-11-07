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
 * Entity class for "positions" table
 */
#[Entity]
#[Table(schema: "uac", name: "positions")]
class Position extends AbstractEntity
{
    #[Id]
    #[Column(name: "position_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "positions_position_id_seq")]
    private int $positionId;

    #[Column(name: "position_category_id", type: "integer", nullable: true)]
    private ?int $positionCategoryId;

    #[Column(name: "position_code", type: "string")]
    private string $positionCode;

    #[Column(name: "position_title", type: "string")]
    private string $positionTitle;

    #[Column(name: "position_level", type: "integer", nullable: true)]
    private ?int $positionLevel;

    #[Column(name: "job_description", type: "text", nullable: true)]
    private ?string $jobDescription;

    #[Column(name: "salary_grade", type: "integer", nullable: true)]
    private ?int $salaryGrade;

    #[Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive;

    #[Column(name: "created_by", type: "integer", nullable: true)]
    private ?int $createdBy;

    #[Column(name: "created_at", type: "datetimetz", nullable: true)]
    private ?DateTime $createdAt;

    #[Column(name: "updated_at", type: "datetimetz", nullable: true)]
    private ?DateTime $updatedAt;

    #[Column(name: "step_increment", type: "integer", nullable: true)]
    private ?int $stepIncrement;

    #[Column(name: "qualification_standards", type: "text", nullable: true)]
    private ?string $qualificationStandards;

    #[Column(name: "competency_requirements", type: "json", nullable: true)]
    private mixed $competencyRequirements;

    public function __construct()
    {
        $this->stepIncrement = 1;
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

    public function getPositionCategoryId(): ?int
    {
        return $this->positionCategoryId;
    }

    public function setPositionCategoryId(?int $value): static
    {
        $this->positionCategoryId = $value;
        return $this;
    }

    public function getPositionCode(): string
    {
        return HtmlDecode($this->positionCode);
    }

    public function setPositionCode(string $value): static
    {
        $this->positionCode = RemoveXss($value);
        return $this;
    }

    public function getPositionTitle(): string
    {
        return HtmlDecode($this->positionTitle);
    }

    public function setPositionTitle(string $value): static
    {
        $this->positionTitle = RemoveXss($value);
        return $this;
    }

    public function getPositionLevel(): ?int
    {
        return $this->positionLevel;
    }

    public function setPositionLevel(?int $value): static
    {
        $this->positionLevel = $value;
        return $this;
    }

    public function getJobDescription(): ?string
    {
        return HtmlDecode($this->jobDescription);
    }

    public function setJobDescription(?string $value): static
    {
        $this->jobDescription = RemoveXss($value);
        return $this;
    }

    public function getSalaryGrade(): ?int
    {
        return $this->salaryGrade;
    }

    public function setSalaryGrade(?int $value): static
    {
        $this->salaryGrade = $value;
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

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $value): static
    {
        $this->createdBy = $value;
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

    public function getStepIncrement(): ?int
    {
        return $this->stepIncrement;
    }

    public function setStepIncrement(?int $value): static
    {
        $this->stepIncrement = $value;
        return $this;
    }

    public function getQualificationStandards(): ?string
    {
        return HtmlDecode($this->qualificationStandards);
    }

    public function setQualificationStandards(?string $value): static
    {
        $this->qualificationStandards = RemoveXss($value);
        return $this;
    }

    public function getCompetencyRequirements(): mixed
    {
        return HtmlDecode($this->competencyRequirements);
    }

    public function setCompetencyRequirements(mixed $value): static
    {
        $this->competencyRequirements = RemoveXss($value);
        return $this;
    }
}
