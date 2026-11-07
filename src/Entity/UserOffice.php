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
 * Entity class for "user_offices" table
 */
#[Entity]
#[Table(schema: "uac", name: "user_offices")]
class UserOffice extends AbstractEntity
{
    #[Id]
    #[Column(name: "user_office_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "user_offices_user_office_id_seq")]
    private int $userOfficeId;

    #[Column(name: "user_id", type: "integer")]
    private int $userId;

    #[Column(name: "office_id", type: "integer")]
    private int $officeId;

    #[Column(name: "is_primary", type: "boolean", nullable: true)]
    private ?bool $isPrimary;

    #[Column(name: "access_start_date", type: "date")]
    private DateTime $accessStartDate;

    #[Column(name: "access_end_date", type: "date", nullable: true)]
    private ?DateTime $accessEndDate;

    #[Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive;

    #[Column(name: "assigned_by", type: "integer", nullable: true)]
    private ?int $assignedBy;

    #[Column(name: "assigned_at", type: "datetimetz", nullable: true)]
    private ?DateTime $assignedAt;

    public function getUserOfficeId(): int
    {
        return $this->userOfficeId;
    }

    public function setUserOfficeId(int $value): static
    {
        $this->userOfficeId = $value;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $value): static
    {
        $this->userId = $value;
        return $this;
    }

    public function getOfficeId(): int
    {
        return $this->officeId;
    }

    public function setOfficeId(int $value): static
    {
        $this->officeId = $value;
        return $this;
    }

    public function getIsPrimary(): ?bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(?bool $value): static
    {
        $this->isPrimary = $value;
        return $this;
    }

    public function getAccessStartDate(): DateTime
    {
        return $this->accessStartDate;
    }

    public function setAccessStartDate(DateTime $value): static
    {
        $this->accessStartDate = $value;
        return $this;
    }

    public function getAccessEndDate(): ?DateTime
    {
        return $this->accessEndDate;
    }

    public function setAccessEndDate(?DateTime $value): static
    {
        $this->accessEndDate = $value;
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
