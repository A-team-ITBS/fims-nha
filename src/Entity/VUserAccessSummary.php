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
 * Entity class for "v_user_access_summary" table
 */
#[Entity]
#[Table(schema: "uac", name: "v_user_access_summary")]
class VUserAccessSummary extends AbstractEntity
{
    #[Column(name: "user_id", type: "integer", nullable: true)]
    private ?int $userId;

    #[Column(type: "string", nullable: true)]
    private ?string $username;

    #[Column(type: "string", nullable: true)]
    private ?string $email;

    #[Column(name: "full_name", type: "text", nullable: true)]
    private ?string $fullName;

    #[Column(name: "department_name", type: "string", nullable: true)]
    private ?string $departmentName;

    #[Column(name: "department_code", type: "string", nullable: true)]
    private ?string $departmentCode;

    #[Column(name: "department_type", type: "string", nullable: true)]
    private ?string $departmentType;

    #[Column(name: "rc_code", type: "string", nullable: true)]
    private ?string $rcCode;

    #[Column(name: "rc_name", type: "string", nullable: true)]
    private ?string $rcName;

    #[Column(name: "office_name", type: "string", nullable: true)]
    private ?string $officeName;

    #[Column(name: "office_code", type: "string", nullable: true)]
    private ?string $officeCode;

    #[Column(type: "text", nullable: true)]
    private ?string $roles;

    #[Column(name: "accessible_systems", type: "text", nullable: true)]
    private ?string $accessibleSystems;

    #[Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive;

    #[Column(name: "last_login", type: "datetime", nullable: true)]
    private ?DateTime $lastLogin;

    #[Column(name: "date_created", type: "datetime", nullable: true)]
    private ?DateTime $dateCreated;

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $value): static
    {
        $this->userId = $value;
        return $this;
    }

    public function getUsername(): ?string
    {
        return HtmlDecode($this->username);
    }

    public function setUsername(?string $value): static
    {
        $this->username = RemoveXss($value);
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

    public function getFullName(): ?string
    {
        return HtmlDecode($this->fullName);
    }

    public function setFullName(?string $value): static
    {
        $this->fullName = RemoveXss($value);
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

    public function getRoles(): ?string
    {
        return HtmlDecode($this->roles);
    }

    public function setRoles(?string $value): static
    {
        $this->roles = RemoveXss($value);
        return $this;
    }

    public function getAccessibleSystems(): ?string
    {
        return HtmlDecode($this->accessibleSystems);
    }

    public function setAccessibleSystems(?string $value): static
    {
        $this->accessibleSystems = RemoveXss($value);
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

    public function getLastLogin(): ?DateTime
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?DateTime $value): static
    {
        $this->lastLogin = $value;
        return $this;
    }

    public function getDateCreated(): ?DateTime
    {
        return $this->dateCreated;
    }

    public function setDateCreated(?DateTime $value): static
    {
        $this->dateCreated = $value;
        return $this;
    }
}
