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
 * Entity class for "offices" table
 */
#[Entity]
#[Table(schema: "uac", name: "offices")]
class Office extends AbstractEntity
{
    #[Id]
    #[Column(name: "office_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "offices_office_id_seq")]
    private int $officeId;

    #[Column(name: "office_name", type: "string")]
    private string $officeName;

    #[Column(name: "office_code", type: "string")]
    private string $officeCode;

    #[Column(name: "office_type", type: "string", nullable: true)]
    private ?string $officeType;

    #[Column(type: "text", nullable: true)]
    private ?string $location;

    #[Column(type: "text", nullable: true)]
    private ?string $address;

    #[Column(type: "string", nullable: true)]
    private ?string $region;

    #[Column(type: "string", nullable: true)]
    private ?string $province;

    #[Column(name: "city_municipality", type: "string", nullable: true)]
    private ?string $cityMunicipality;

    #[Column(name: "office_contact_number", type: "string", nullable: true)]
    private ?string $officeContactNumber;

    #[Column(name: "office_email", type: "string", nullable: true)]
    private ?string $officeEmail;

    #[Column(name: "office_telephone", type: "string", nullable: true)]
    private ?string $officeTelephone;

    #[Column(name: "office_fax", type: "string", nullable: true)]
    private ?string $officeFax;

    #[Column(name: "parent_office_id", type: "integer", nullable: true)]
    private ?int $parentOfficeId;

    #[Column(name: "is_active", type: "boolean", nullable: true)]
    private ?bool $isActive;

    #[Column(name: "date_opened", type: "date", nullable: true)]
    private ?DateTime $dateOpened;

    #[Column(name: "date_closed", type: "date", nullable: true)]
    private ?DateTime $dateClosed;

    #[Column(name: "created_by", type: "integer", nullable: true)]
    private ?int $createdBy;

    #[Column(name: "created_at", type: "datetimetz", nullable: true)]
    private ?DateTime $createdAt;

    #[Column(name: "modified_by", type: "integer", nullable: true)]
    private ?int $modifiedBy;

    #[Column(name: "modified_at", type: "datetimetz", nullable: true)]
    private ?DateTime $modifiedAt;

    #[Column(type: "text", nullable: true)]
    private ?string $remarks;

    #[Column(type: "json", nullable: true)]
    private mixed $metadata;

    #[Column(name: "rc_id", type: "integer", nullable: true)]
    private ?int $rcId;

    #[Column(name: "region_code", type: "string", nullable: true)]
    private ?string $regionCode;

    public function getOfficeId(): int
    {
        return $this->officeId;
    }

    public function setOfficeId(int $value): static
    {
        $this->officeId = $value;
        return $this;
    }

    public function getOfficeName(): string
    {
        return HtmlDecode($this->officeName);
    }

    public function setOfficeName(string $value): static
    {
        $this->officeName = RemoveXss($value);
        return $this;
    }

    public function getOfficeCode(): string
    {
        return HtmlDecode($this->officeCode);
    }

    public function setOfficeCode(string $value): static
    {
        $this->officeCode = RemoveXss($value);
        return $this;
    }

    public function getOfficeType(): ?string
    {
        return HtmlDecode($this->officeType);
    }

    public function setOfficeType(?string $value): static
    {
        $this->officeType = RemoveXss($value);
        return $this;
    }

    public function getLocation(): ?string
    {
        return HtmlDecode($this->location);
    }

    public function setLocation(?string $value): static
    {
        $this->location = RemoveXss($value);
        return $this;
    }

    public function getAddress(): ?string
    {
        return HtmlDecode($this->address);
    }

    public function setAddress(?string $value): static
    {
        $this->address = RemoveXss($value);
        return $this;
    }

    public function getRegion(): ?string
    {
        return HtmlDecode($this->region);
    }

    public function setRegion(?string $value): static
    {
        $this->region = RemoveXss($value);
        return $this;
    }

    public function getProvince(): ?string
    {
        return HtmlDecode($this->province);
    }

    public function setProvince(?string $value): static
    {
        $this->province = RemoveXss($value);
        return $this;
    }

    public function getCityMunicipality(): ?string
    {
        return HtmlDecode($this->cityMunicipality);
    }

    public function setCityMunicipality(?string $value): static
    {
        $this->cityMunicipality = RemoveXss($value);
        return $this;
    }

    public function getOfficeContactNumber(): ?string
    {
        return HtmlDecode($this->officeContactNumber);
    }

    public function setOfficeContactNumber(?string $value): static
    {
        $this->officeContactNumber = RemoveXss($value);
        return $this;
    }

    public function getOfficeEmail(): ?string
    {
        return HtmlDecode($this->officeEmail);
    }

    public function setOfficeEmail(?string $value): static
    {
        $this->officeEmail = RemoveXss($value);
        return $this;
    }

    public function getOfficeTelephone(): ?string
    {
        return HtmlDecode($this->officeTelephone);
    }

    public function setOfficeTelephone(?string $value): static
    {
        $this->officeTelephone = RemoveXss($value);
        return $this;
    }

    public function getOfficeFax(): ?string
    {
        return HtmlDecode($this->officeFax);
    }

    public function setOfficeFax(?string $value): static
    {
        $this->officeFax = RemoveXss($value);
        return $this;
    }

    public function getParentOfficeId(): ?int
    {
        return $this->parentOfficeId;
    }

    public function setParentOfficeId(?int $value): static
    {
        $this->parentOfficeId = $value;
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

    public function getDateOpened(): ?DateTime
    {
        return $this->dateOpened;
    }

    public function setDateOpened(?DateTime $value): static
    {
        $this->dateOpened = $value;
        return $this;
    }

    public function getDateClosed(): ?DateTime
    {
        return $this->dateClosed;
    }

    public function setDateClosed(?DateTime $value): static
    {
        $this->dateClosed = $value;
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

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $value): static
    {
        $this->createdAt = $value;
        return $this;
    }

    public function getModifiedBy(): ?int
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(?int $value): static
    {
        $this->modifiedBy = $value;
        return $this;
    }

    public function getModifiedAt(): ?DateTime
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(?DateTime $value): static
    {
        $this->modifiedAt = $value;
        return $this;
    }

    public function getRemarks(): ?string
    {
        return HtmlDecode($this->remarks);
    }

    public function setRemarks(?string $value): static
    {
        $this->remarks = RemoveXss($value);
        return $this;
    }

    public function getMetadata(): mixed
    {
        return HtmlDecode($this->metadata);
    }

    public function setMetadata(mixed $value): static
    {
        $this->metadata = RemoveXss($value);
        return $this;
    }

    public function getRcId(): ?int
    {
        return $this->rcId;
    }

    public function setRcId(?int $value): static
    {
        $this->rcId = $value;
        return $this;
    }

    public function getRegionCode(): ?string
    {
        return HtmlDecode($this->regionCode);
    }

    public function setRegionCode(?string $value): static
    {
        $this->regionCode = RemoveXss($value);
        return $this;
    }
}
