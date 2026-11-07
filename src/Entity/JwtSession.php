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
 * Entity class for "jwt_sessions" table
 */
#[Entity]
#[Table(schema: "uac", name: "jwt_sessions")]
class JwtSession extends AbstractEntity
{
    #[Id]
    #[Column(name: "session_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "jwt_sessions_session_id_seq")]
    private int $sessionId;

    #[Column(name: "token_hash", type: "string", unique: true)]
    private string $tokenHash;

    #[Column(name: "user_id", type: "integer")]
    private int $userId;

    #[Column(type: "string")]
    private string $system;

    #[Column(name: "issued_at", type: "datetimetz")]
    private DateTime $issuedAt;

    #[Column(name: "expires_at", type: "datetimetz")]
    private DateTime $expiresAt;

    #[Column(name: "client_ip", type: "string", nullable: true)]
    private ?string $clientIp;

    #[Column(name: "user_agent", type: "text", nullable: true)]
    private ?string $userAgent;

    #[Column(name: "revoked_at", type: "datetimetz", nullable: true)]
    private ?DateTime $revokedAt;

    #[Column(name: "revoke_reason", type: "string", nullable: true)]
    private ?string $revokeReason;

    public function __construct()
    {
        $this->system = "libis";
    }

    public function getSessionId(): int
    {
        return $this->sessionId;
    }

    public function setSessionId(int $value): static
    {
        $this->sessionId = $value;
        return $this;
    }

    public function getTokenHash(): string
    {
        return HtmlDecode($this->tokenHash);
    }

    public function setTokenHash(string $value): static
    {
        $this->tokenHash = RemoveXss($value);
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

    public function getSystem(): string
    {
        return HtmlDecode($this->system);
    }

    public function setSystem(string $value): static
    {
        $this->system = RemoveXss($value);
        return $this;
    }

    public function getIssuedAt(): DateTime
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(DateTime $value): static
    {
        $this->issuedAt = $value;
        return $this;
    }

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTime $value): static
    {
        $this->expiresAt = $value;
        return $this;
    }

    public function getClientIp(): ?string
    {
        return HtmlDecode($this->clientIp);
    }

    public function setClientIp(?string $value): static
    {
        $this->clientIp = RemoveXss($value);
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return HtmlDecode($this->userAgent);
    }

    public function setUserAgent(?string $value): static
    {
        $this->userAgent = RemoveXss($value);
        return $this;
    }

    public function getRevokedAt(): ?DateTime
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?DateTime $value): static
    {
        $this->revokedAt = $value;
        return $this;
    }

    public function getRevokeReason(): ?string
    {
        return HtmlDecode($this->revokeReason);
    }

    public function setRevokeReason(?string $value): static
    {
        $this->revokeReason = RemoveXss($value);
        return $this;
    }
}
