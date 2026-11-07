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
 * Entity class for "document_header_assets" table
 */
#[Entity]
#[Table(schema: "uac", name: "document_header_assets")]
class DocumentHeaderAsset extends AbstractEntity
{
    #[Id]
    #[Column(name: "header_asset_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "document_header_assets_header_asset_id_seq")]
    private int $headerAssetId;

    #[Column(name: "asset_code", type: "string", unique: true)]
    private string $assetCode;

    #[Column(name: "asset_name", type: "string")]
    private string $assetName;

    #[Column(name: "document_asset_type_id", type: "integer")]
    private int $documentAssetTypeId;

    #[Column(name: "scope_system_code", type: "string", nullable: true)]
    private ?string $scopeSystemCode;

    #[Column(name: "scope_document_code", type: "string", nullable: true)]
    private ?string $scopeDocumentCode;

    #[Column(name: "file_name", type: "string", nullable: true)]
    private ?string $fileName;

    #[Column(name: "mime_type", type: "string", nullable: true)]
    private ?string $mimeType;

    #[Column(name: "file_size", type: "integer", nullable: true)]
    private ?int $fileSize;

    #[Column(name: "checksum_sha256", type: "string", nullable: true)]
    private ?string $checksumSha256;

    #[Column(name: "image_width", type: "integer", nullable: true)]
    private ?int $imageWidth;

    #[Column(name: "image_height", type: "integer", nullable: true)]
    private ?int $imageHeight;

    #[Column(name: "render_width", type: "integer")]
    private int $renderWidth;

    #[Column(name: "render_height", type: "integer")]
    private int $renderHeight;

    #[Column(name: "image_data", type: "text", nullable: true)]
    private ?string $imageData;

    #[Column(name: "is_active", type: "boolean")]
    private bool $isActive;

    #[Column(type: "text", nullable: true)]
    private ?string $notes;

    #[Column(name: "created_at", type: "datetime")]
    private DateTime $createdAt;

    #[Column(name: "updated_at", type: "datetime")]
    private DateTime $updatedAt;

    #[Column(name: "created_by", type: "integer", nullable: true)]
    private ?int $createdBy;

    #[Column(name: "updated_by", type: "integer", nullable: true)]
    private ?int $updatedBy;

    #[Column(name: "header_slot_id", type: "integer", nullable: true)]
    private ?int $headerSlotId;

    #[Column(name: "part_kind", type: "string")]
    private string $partKind;

    #[Column(name: "column_pct", type: "decimal", nullable: true)]
    private ?string $columnPct;

    #[Column(type: "string", nullable: true)]
    private ?string $align;

    #[Column(name: "text_line1", type: "text", nullable: true)]
    private ?string $textLine1;

    #[Column(name: "text_line2", type: "text", nullable: true)]
    private ?string $textLine2;

    #[Column(name: "font_name", type: "string", nullable: true)]
    private ?string $fontName;

    #[Column(name: "line1_size_pt", type: "decimal", nullable: true)]
    private ?string $line1SizePt;

    #[Column(name: "line2_size_pt", type: "decimal", nullable: true)]
    private ?string $line2SizePt;

    #[Column(name: "line1_bold", type: "boolean", nullable: true)]
    private ?bool $line1Bold;

    #[Column(name: "line2_bold", type: "boolean", nullable: true)]
    private ?bool $line2Bold;

    #[Column(name: "line1_color", type: "string", nullable: true)]
    private ?string $line1Color;

    #[Column(name: "line2_color", type: "string", nullable: true)]
    private ?string $line2Color;

    public function __construct()
    {
        $this->partKind = "image";
    }

    public function getHeaderAssetId(): int
    {
        return $this->headerAssetId;
    }

    public function setHeaderAssetId(int $value): static
    {
        $this->headerAssetId = $value;
        return $this;
    }

    public function getAssetCode(): string
    {
        return HtmlDecode($this->assetCode);
    }

    public function setAssetCode(string $value): static
    {
        $this->assetCode = RemoveXss($value);
        return $this;
    }

    public function getAssetName(): string
    {
        return HtmlDecode($this->assetName);
    }

    public function setAssetName(string $value): static
    {
        $this->assetName = RemoveXss($value);
        return $this;
    }

    public function getDocumentAssetTypeId(): int
    {
        return $this->documentAssetTypeId;
    }

    public function setDocumentAssetTypeId(int $value): static
    {
        $this->documentAssetTypeId = $value;
        return $this;
    }

    public function getScopeSystemCode(): ?string
    {
        return HtmlDecode($this->scopeSystemCode);
    }

    public function setScopeSystemCode(?string $value): static
    {
        $this->scopeSystemCode = RemoveXss($value);
        return $this;
    }

    public function getScopeDocumentCode(): ?string
    {
        return HtmlDecode($this->scopeDocumentCode);
    }

    public function setScopeDocumentCode(?string $value): static
    {
        $this->scopeDocumentCode = RemoveXss($value);
        return $this;
    }

    public function getFileName(): ?string
    {
        return HtmlDecode($this->fileName);
    }

    public function setFileName(?string $value): static
    {
        $this->fileName = RemoveXss($value);
        return $this;
    }

    public function getMimeType(): ?string
    {
        return HtmlDecode($this->mimeType);
    }

    public function setMimeType(?string $value): static
    {
        $this->mimeType = RemoveXss($value);
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $value): static
    {
        $this->fileSize = $value;
        return $this;
    }

    public function getChecksumSha256(): ?string
    {
        return HtmlDecode($this->checksumSha256);
    }

    public function setChecksumSha256(?string $value): static
    {
        $this->checksumSha256 = RemoveXss($value);
        return $this;
    }

    public function getImageWidth(): ?int
    {
        return $this->imageWidth;
    }

    public function setImageWidth(?int $value): static
    {
        $this->imageWidth = $value;
        return $this;
    }

    public function getImageHeight(): ?int
    {
        return $this->imageHeight;
    }

    public function setImageHeight(?int $value): static
    {
        $this->imageHeight = $value;
        return $this;
    }

    public function getRenderWidth(): int
    {
        return $this->renderWidth;
    }

    public function setRenderWidth(int $value): static
    {
        $this->renderWidth = $value;
        return $this;
    }

    public function getRenderHeight(): int
    {
        return $this->renderHeight;
    }

    public function setRenderHeight(int $value): static
    {
        $this->renderHeight = $value;
        return $this;
    }

    public function getImageData(): ?string
    {
        return HtmlDecode($this->imageData);
    }

    public function setImageData(?string $value): static
    {
        $this->imageData = RemoveXss($value);
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

    public function getNotes(): ?string
    {
        return HtmlDecode($this->notes);
    }

    public function setNotes(?string $value): static
    {
        $this->notes = RemoveXss($value);
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

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $value): static
    {
        $this->updatedAt = $value;
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

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?int $value): static
    {
        $this->updatedBy = $value;
        return $this;
    }

    public function getHeaderSlotId(): ?int
    {
        return $this->headerSlotId;
    }

    public function setHeaderSlotId(?int $value): static
    {
        $this->headerSlotId = $value;
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

    public function getColumnPct(): ?string
    {
        return $this->columnPct;
    }

    public function setColumnPct(?string $value): static
    {
        $this->columnPct = $value;
        return $this;
    }

    public function getAlign(): ?string
    {
        return HtmlDecode($this->align);
    }

    public function setAlign(?string $value): static
    {
        $this->align = RemoveXss($value);
        return $this;
    }

    public function getTextLine1(): ?string
    {
        return HtmlDecode($this->textLine1);
    }

    public function setTextLine1(?string $value): static
    {
        $this->textLine1 = RemoveXss($value);
        return $this;
    }

    public function getTextLine2(): ?string
    {
        return HtmlDecode($this->textLine2);
    }

    public function setTextLine2(?string $value): static
    {
        $this->textLine2 = RemoveXss($value);
        return $this;
    }

    public function getFontName(): ?string
    {
        return HtmlDecode($this->fontName);
    }

    public function setFontName(?string $value): static
    {
        $this->fontName = RemoveXss($value);
        return $this;
    }

    public function getLine1SizePt(): ?string
    {
        return $this->line1SizePt;
    }

    public function setLine1SizePt(?string $value): static
    {
        $this->line1SizePt = $value;
        return $this;
    }

    public function getLine2SizePt(): ?string
    {
        return $this->line2SizePt;
    }

    public function setLine2SizePt(?string $value): static
    {
        $this->line2SizePt = $value;
        return $this;
    }

    public function getLine1Bold(): ?bool
    {
        return $this->line1Bold;
    }

    public function setLine1Bold(?bool $value): static
    {
        $this->line1Bold = $value;
        return $this;
    }

    public function getLine2Bold(): ?bool
    {
        return $this->line2Bold;
    }

    public function setLine2Bold(?bool $value): static
    {
        $this->line2Bold = $value;
        return $this;
    }

    public function getLine1Color(): ?string
    {
        return HtmlDecode($this->line1Color);
    }

    public function setLine1Color(?string $value): static
    {
        $this->line1Color = RemoveXss($value);
        return $this;
    }

    public function getLine2Color(): ?string
    {
        return HtmlDecode($this->line2Color);
    }

    public function setLine2Color(?string $value): static
    {
        $this->line2Color = RemoveXss($value);
        return $this;
    }
}
