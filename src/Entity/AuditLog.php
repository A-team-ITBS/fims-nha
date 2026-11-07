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
 * Entity class for "audit_logs" table
 */
#[Entity]
#[Table(schema: "uac", name: "audit_logs")]
class AuditLog extends AbstractEntity
{
    #[Id]
    #[Column(type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "audit_logs_id_seq")]
    private int $id;

    #[Column(name: "date_time", type: "datetime")]
    private DateTime $dateTime;

    #[Column(type: "string", nullable: true)]
    private ?string $script;

    #[Column(name: "\"user\"", options: ["name" => "user"], type: "string", nullable: true)]
    private ?string $user;

    #[Column(type: "string", nullable: true)]
    private ?string $action;

    #[Column(name: "\"table\"", options: ["name" => "table"], type: "string", nullable: true)]
    private ?string $table;

    #[Column(type: "string", nullable: true)]
    private ?string $field;

    #[Column(name: "key_value", type: "text", nullable: true)]
    private ?string $keyValue;

    #[Column(name: "old_value", type: "text", nullable: true)]
    private ?string $oldValue;

    #[Column(name: "new_value", type: "text", nullable: true)]
    private ?string $newValue;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $value): static
    {
        $this->id = $value;
        return $this;
    }

    public function getDateTime(): DateTime
    {
        return $this->dateTime;
    }

    public function setDateTime(DateTime $value): static
    {
        $this->dateTime = $value;
        return $this;
    }

    public function getScript(): ?string
    {
        return HtmlDecode($this->script);
    }

    public function setScript(?string $value): static
    {
        $this->script = RemoveXss($value);
        return $this;
    }

    public function getUser(): ?string
    {
        return HtmlDecode($this->user);
    }

    public function setUser(?string $value): static
    {
        $this->user = RemoveXss($value);
        return $this;
    }

    public function getAction(): ?string
    {
        return HtmlDecode($this->action);
    }

    public function setAction(?string $value): static
    {
        $this->action = RemoveXss($value);
        return $this;
    }

    public function getTable(): ?string
    {
        return HtmlDecode($this->table);
    }

    public function setTable(?string $value): static
    {
        $this->table = RemoveXss($value);
        return $this;
    }

    public function getField(): ?string
    {
        return HtmlDecode($this->field);
    }

    public function setField(?string $value): static
    {
        $this->field = RemoveXss($value);
        return $this;
    }

    public function getKeyValue(): ?string
    {
        return HtmlDecode($this->keyValue);
    }

    public function setKeyValue(?string $value): static
    {
        $this->keyValue = RemoveXss($value);
        return $this;
    }

    public function getOldValue(): ?string
    {
        return HtmlDecode($this->oldValue);
    }

    public function setOldValue(?string $value): static
    {
        $this->oldValue = RemoveXss($value);
        return $this;
    }

    public function getNewValue(): ?string
    {
        return HtmlDecode($this->newValue);
    }

    public function setNewValue(?string $value): static
    {
        $this->newValue = RemoveXss($value);
        return $this;
    }
}
