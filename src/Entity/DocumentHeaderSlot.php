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
 * Entity class for "document_header_slots" table
 */
#[Entity]
#[Table(schema: "uac", name: "document_header_slots")]
class DocumentHeaderSlot extends AbstractEntity
{
    #[Id]
    #[Column(name: "header_slot_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "document_header_slots_header_slot_id_seq")]
    private int $headerSlotId;

    #[Column(name: "slot_code", type: "string", unique: true)]
    private string $slotCode;

    #[Column(name: "slot_name", type: "string")]
    private string $slotName;

    #[Column(name: "part_kind", type: "string")]
    private string $partKind;

    #[Column(name: "display_order", type: "integer")]
    private int $displayOrder;

    #[Column(name: "default_column_pct", type: "decimal")]
    private string $defaultColumnPct;

    #[Column(name: "default_render_width", type: "integer", nullable: true)]
    private ?int $defaultRenderWidth;

    #[Column(name: "default_render_height", type: "integer", nullable: true)]
    private ?int $defaultRenderHeight;

    #[Column(name: "default_align", type: "string")]
    private string $defaultAlign;

    #[Column(name: "is_active", type: "boolean")]
    private bool $isActive;

    #[Column(name: "created_at", type: "datetime")]
    private DateTime $createdAt;

    public function __construct()
    {
        $this->defaultAlign = "center";
    }

    public function getHeaderSlotId(): int
    {
        return $this->headerSlotId;
    }

    public function setHeaderSlotId(int $value): static
    {
        $this->headerSlotId = $value;
        return $this;
    }

    public function getSlotCode(): string
    {
        return HtmlDecode($this->slotCode);
    }

    public function setSlotCode(string $value): static
    {
        $this->slotCode = RemoveXss($value);
        return $this;
    }

    public function getSlotName(): string
    {
        return HtmlDecode($this->slotName);
    }

    public function setSlotName(string $value): static
    {
        $this->slotName = RemoveXss($value);
        return $this;
    }

    public function getPartKind(): string
    {
        return HtmlDecode($this->partKind);
    }

    public function setPartKind(string $value): static
    {
        $this->partKind = RemoveXss($value);
        return $this;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $value): static
    {
        $this->displayOrder = $value;
        return $this;
    }

    public function getDefaultColumnPct(): string
    {
        return $this->defaultColumnPct;
    }

    public function setDefaultColumnPct(string $value): static
    {
        $this->defaultColumnPct = $value;
        return $this;
    }

    public function getDefaultRenderWidth(): ?int
    {
        return $this->defaultRenderWidth;
    }

    public function setDefaultRenderWidth(?int $value): static
    {
        $this->defaultRenderWidth = $value;
        return $this;
    }

    public function getDefaultRenderHeight(): ?int
    {
        return $this->defaultRenderHeight;
    }

    public function setDefaultRenderHeight(?int $value): static
    {
        $this->defaultRenderHeight = $value;
        return $this;
    }

    public function getDefaultAlign(): string
    {
        return HtmlDecode($this->defaultAlign);
    }

    public function setDefaultAlign(string $value): static
    {
        $this->defaultAlign = RemoveXss($value);
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $value): static
    {
        $this->isActive = $value;
        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $value): static
    {
        $this->createdAt = $value;
        return $this;
    }
}
