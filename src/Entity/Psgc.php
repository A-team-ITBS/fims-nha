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
 * Entity class for "psgc" table
 */
#[Entity]
#[Table(schema: "uac", name: "psgc")]
class Psgc extends AbstractEntity
{
    #[Id]
    #[Column(name: "code_10", type: "string", unique: true)]
    private string $code10;

    #[Column(type: "string", nullable: true)]
    private ?string $name;

    #[Column(name: "psgc_code", type: "string", nullable: true)]
    private ?string $psgcCode;

    #[Column(type: "string", nullable: true)]
    private ?string $level;

    #[Column(name: "od_name", type: "string", nullable: true)]
    private ?string $odName;

    #[Column(name: "city_class", type: "string", nullable: true)]
    private ?string $cityClass;

    #[Column(name: "income_class", type: "string", nullable: true)]
    private ?string $incomeClass;

    #[Column(name: "rural_urban", type: "string", nullable: true)]
    private ?string $ruralUrban;

    #[Column(name: "population_2015", type: "integer", nullable: true)]
    private ?int $population2015;

    #[Column(name: "population_2020", type: "integer", nullable: true)]
    private ?int $population2020;

    #[Column(type: "string", nullable: true)]
    private ?string $status;

    #[Column(type: "string", nullable: true)]
    private ?string $display;

    public function getCode10(): string
    {
        return $this->code10;
    }

    public function setCode10(string $value): static
    {
        $this->code10 = $value;
        return $this;
    }

    public function getName(): ?string
    {
        return HtmlDecode($this->name);
    }

    public function setName(?string $value): static
    {
        $this->name = RemoveXss($value);
        return $this;
    }

    public function getPsgcCode(): ?string
    {
        return HtmlDecode($this->psgcCode);
    }

    public function setPsgcCode(?string $value): static
    {
        $this->psgcCode = RemoveXss($value);
        return $this;
    }

    public function getLevel(): ?string
    {
        return HtmlDecode($this->level);
    }

    public function setLevel(?string $value): static
    {
        $this->level = RemoveXss($value);
        return $this;
    }

    public function getOdName(): ?string
    {
        return HtmlDecode($this->odName);
    }

    public function setOdName(?string $value): static
    {
        $this->odName = RemoveXss($value);
        return $this;
    }

    public function getCityClass(): ?string
    {
        return HtmlDecode($this->cityClass);
    }

    public function setCityClass(?string $value): static
    {
        $this->cityClass = RemoveXss($value);
        return $this;
    }

    public function getIncomeClass(): ?string
    {
        return HtmlDecode($this->incomeClass);
    }

    public function setIncomeClass(?string $value): static
    {
        $this->incomeClass = RemoveXss($value);
        return $this;
    }

    public function getRuralUrban(): ?string
    {
        return HtmlDecode($this->ruralUrban);
    }

    public function setRuralUrban(?string $value): static
    {
        $this->ruralUrban = RemoveXss($value);
        return $this;
    }

    public function getPopulation2015(): ?int
    {
        return $this->population2015;
    }

    public function setPopulation2015(?int $value): static
    {
        $this->population2015 = $value;
        return $this;
    }

    public function getPopulation2020(): ?int
    {
        return $this->population2020;
    }

    public function setPopulation2020(?int $value): static
    {
        $this->population2020 = $value;
        return $this;
    }

    public function getStatus(): ?string
    {
        return HtmlDecode($this->status);
    }

    public function setStatus(?string $value): static
    {
        $this->status = RemoveXss($value);
        return $this;
    }

    public function getDisplay(): ?string
    {
        return HtmlDecode($this->display);
    }

    public function setDisplay(?string $value): static
    {
        $this->display = RemoveXss($value);
        return $this;
    }
}
