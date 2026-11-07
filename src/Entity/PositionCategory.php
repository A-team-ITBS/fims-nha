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
 * Entity class for "position_categories" table
 */
#[Entity]
#[Table(schema: "uac", name: "position_categories")]
class PositionCategory extends AbstractEntity
{
    #[Id]
    #[Column(name: "position_category_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "position_categories_position_category_id_seq")]
    private int $positionCategoryId;

    #[Column(name: "category_name", type: "string")]
    private string $categoryName;

    #[Column(type: "text", nullable: true)]
    private ?string $description;

    #[Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive;

    #[Column(name: "created_at", type: "datetimetz", nullable: true)]
    private ?DateTime $createdAt;

    public function getPositionCategoryId(): int
    {
        return $this->positionCategoryId;
    }

    public function setPositionCategoryId(int $value): static
    {
        $this->positionCategoryId = $value;
        return $this;
    }

    public function getCategoryName(): string
    {
        return HtmlDecode($this->categoryName);
    }

    public function setCategoryName(string $value): static
    {
        $this->categoryName = RemoveXss($value);
        return $this;
    }

    public function getDescription(): ?string
    {
        return HtmlDecode($this->description);
    }

    public function setDescription(?string $value): static
    {
        $this->description = RemoveXss($value);
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
}
