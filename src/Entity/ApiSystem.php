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
 * Entity class for "api_systems" table
 */
#[Entity]
#[Table(schema: "uac", name: "api_systems")]
class ApiSystem extends AbstractEntity
{
    #[Id]
    #[Column(name: "system_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "api_systems_system_id_seq")]
    private int $systemId;

    #[Column(name: "system_code", type: "string", unique: true)]
    private string $systemCode;

    #[Column(name: "system_name", type: "string")]
    private string $systemName;

    #[Column(name: "base_url", type: "string", nullable: true)]
    private ?string $baseUrl;

    #[Column(name: "auth_type", type: "string", nullable: true)]
    private ?string $authType;

    #[Column(name: "auth_token", type: "text", nullable: true)]
    private ?string $authToken;

    #[Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive;

    #[Column(name: "created_at", type: "datetimetz", nullable: true)]
    private ?DateTime $createdAt;

    #[Column(name: "updated_at", type: "datetimetz", nullable: true)]
    private ?DateTime $updatedAt;

    public function __construct()
    {
        $this->authType = "bearer";
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

    public function getSystemCode(): string
    {
        return HtmlDecode($this->systemCode);
    }

    public function setSystemCode(string $value): static
    {
        $this->systemCode = RemoveXss($value);
        return $this;
    }

    public function getSystemName(): string
    {
        return HtmlDecode($this->systemName);
    }

    public function setSystemName(string $value): static
    {
        $this->systemName = RemoveXss($value);
        return $this;
    }

    public function getBaseUrl(): ?string
    {
        return HtmlDecode($this->baseUrl);
    }

    public function setBaseUrl(?string $value): static
    {
        $this->baseUrl = RemoveXss($value);
        return $this;
    }

    public function getAuthType(): ?string
    {
        return HtmlDecode($this->authType);
    }

    public function setAuthType(?string $value): static
    {
        $this->authType = RemoveXss($value);
        return $this;
    }

    public function getAuthToken(): ?string
    {
        return HtmlDecode($this->authToken);
    }

    public function setAuthToken(?string $value): static
    {
        $this->authToken = RemoveXss($value);
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
}
