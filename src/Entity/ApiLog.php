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
 * Entity class for "api_logs" table
 */
#[Entity]
#[Table(schema: "uac", name: "api_logs")]
class ApiLog extends AbstractEntity
{
    #[Id]
    #[Column(name: "log_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "api_logs_log_id_seq")]
    private int $logId;

    #[Column(name: "resource_id", type: "integer", nullable: true)]
    private ?int $resourceId;

    #[Column(name: "mode_used", type: "string", nullable: true)]
    private ?string $modeUsed;

    #[Column(name: "http_status", type: "integer", nullable: true)]
    private ?int $httpStatus;

    #[Column(name: "duration_ms", type: "integer", nullable: true)]
    private ?int $durationMs;

    #[Column(type: "boolean", nullable: true)]
    private ?bool $success;

    #[Column(type: "text", nullable: true)]
    private ?string $message;

    #[Column(name: "created_at", type: "datetimetz", nullable: true)]
    private ?DateTime $createdAt;

    public function getLogId(): int
    {
        return $this->logId;
    }

    public function setLogId(int $value): static
    {
        $this->logId = $value;
        return $this;
    }

    public function getResourceId(): ?int
    {
        return $this->resourceId;
    }

    public function setResourceId(?int $value): static
    {
        $this->resourceId = $value;
        return $this;
    }

    public function getModeUsed(): ?string
    {
        return HtmlDecode($this->modeUsed);
    }

    public function setModeUsed(?string $value): static
    {
        $this->modeUsed = RemoveXss($value);
        return $this;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function setHttpStatus(?int $value): static
    {
        $this->httpStatus = $value;
        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $value): static
    {
        $this->durationMs = $value;
        return $this;
    }

    public function getSuccess(): ?bool
    {
        return $this->success;
    }

    public function setSuccess(?bool $value): static
    {
        $this->success = $value;
        return $this;
    }

    public function getMessage(): ?string
    {
        return HtmlDecode($this->message);
    }

    public function setMessage(?string $value): static
    {
        $this->message = RemoveXss($value);
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
}
