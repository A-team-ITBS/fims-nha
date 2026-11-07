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
 * Entity class for "document_asset_types" table
 */
#[Entity]
#[Table(schema: "uac", name: "document_asset_types")]
class DocumentAssetType extends AbstractEntity
{
    #[Id]
    #[Column(name: "document_asset_type_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "document_asset_types_document_asset_type_id_seq")]
    private int $documentAssetTypeId;

    #[Column(type: "string")]
    private string $category;

    #[Column(name: "type_code", type: "string")]
    private string $typeCode;

    #[Column(name: "type_name", type: "string")]
    private string $typeName;

    #[Column(name: "default_render_width", type: "integer")]
    private int $defaultRenderWidth;

    #[Column(name: "default_render_height", type: "integer")]
    private int $defaultRenderHeight;

    #[Column(name: "is_active", type: "boolean")]
    private bool $isActive;

    #[Column(name: "created_at", type: "datetime")]
    private DateTime $createdAt;

    public function getDocumentAssetTypeId(): int
    {
        return $this->documentAssetTypeId;
    }

    public function setDocumentAssetTypeId(int $value): static
    {
        $this->documentAssetTypeId = $value;
        return $this;
    }

    public function getCategory(): string
    {
        return HtmlDecode($this->category);
    }

    public function setCategory(string $value): static
    {
        $this->category = RemoveXss($value);
        return $this;
    }

    public function getTypeCode(): string
    {
        return HtmlDecode($this->typeCode);
    }

    public function setTypeCode(string $value): static
    {
        $this->typeCode = RemoveXss($value);
        return $this;
    }

    public function getTypeName(): string
    {
        return HtmlDecode($this->typeName);
    }

    public function setTypeName(string $value): static
    {
        $this->typeName = RemoveXss($value);
        return $this;
    }

    public function getDefaultRenderWidth(): int
    {
        return $this->defaultRenderWidth;
    }

    public function setDefaultRenderWidth(int $value): static
    {
        $this->defaultRenderWidth = $value;
        return $this;
    }

    public function getDefaultRenderHeight(): int
    {
        return $this->defaultRenderHeight;
    }

    public function setDefaultRenderHeight(int $value): static
    {
        $this->defaultRenderHeight = $value;
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
