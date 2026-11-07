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
 * Entity class for "api_resources" table
 */
#[Entity]
#[Table(schema: "uac", name: "api_resources")]
class ApiResource extends AbstractEntity
{
    #[Id]
    #[Column(name: "resource_id", type: "integer", unique: true)]
    #[GeneratedValue(strategy: "SEQUENCE")]
    #[SequenceGenerator(sequenceName: "api_resources_resource_id_seq")]
    private int $resourceId;

    #[Column(name: "system_id", type: "integer")]
    private int $systemId;

    #[Column(name: "resource_key", type: "string")]
    private string $resourceKey;

    #[Column(type: "string", nullable: true)]
    private ?string $caption;

    #[Column(name: "http_method", type: "string", nullable: true)]
    private ?string $httpMethod;

    #[Column(name: "path_template", type: "string", nullable: true)]
    private ?string $pathTemplate;

    #[Column(type: "string", nullable: true)]
    private ?string $mode;

    #[Column(name: "required_fields", type: "json", nullable: true)]
    private mixed $requiredFields;

    #[Column(name: "field_map", type: "json", nullable: true)]
    private mixed $fieldMap;

    #[Column(name: "sample_payload", type: "json", nullable: true)]
    private mixed $samplePayload;

    #[Column(name: "request_body", type: "json", nullable: true)]
    private mixed $requestBody;

    #[Column(name: "data_path", type: "string", nullable: true)]
    private ?string $dataPath;

    #[Column(name: "base_url", type: "string", nullable: true)]
    private ?string $baseUrl;

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
        $this->httpMethod = "get";
        $this->mode = "mock";
        $this->requiredFields = "[]";
        $this->fieldMap = "{}";
        $this->samplePayload = "[]";
        $this->requestBody = "{}";
        $this->dataPath = "";
        $this->baseUrl = "";
        $this->authToken = "";
    }

    public function getResourceId(): int
    {
        return $this->resourceId;
    }

    public function setResourceId(int $value): static
    {
        $this->resourceId = $value;
        return $this;
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

    public function getResourceKey(): string
    {
        return HtmlDecode($this->resourceKey);
    }

    public function setResourceKey(string $value): static
    {
        $this->resourceKey = RemoveXss($value);
        return $this;
    }

    public function getCaption(): ?string
    {
        return HtmlDecode($this->caption);
    }

    public function setCaption(?string $value): static
    {
        $this->caption = RemoveXss($value);
        return $this;
    }

    public function getHttpMethod(): ?string
    {
        return HtmlDecode($this->httpMethod);
    }

    public function setHttpMethod(?string $value): static
    {
        $this->httpMethod = RemoveXss($value);
        return $this;
    }

    public function getPathTemplate(): ?string
    {
        return HtmlDecode($this->pathTemplate);
    }

    public function setPathTemplate(?string $value): static
    {
        $this->pathTemplate = RemoveXss($value);
        return $this;
    }

    public function getMode(): ?string
    {
        return HtmlDecode($this->mode);
    }

    public function setMode(?string $value): static
    {
        $this->mode = RemoveXss($value);
        return $this;
    }

    public function getRequiredFields(): mixed
    {
        return HtmlDecode($this->requiredFields);
    }

    public function setRequiredFields(mixed $value): static
    {
        $this->requiredFields = RemoveXss($value);
        return $this;
    }

    public function getFieldMap(): mixed
    {
        return HtmlDecode($this->fieldMap);
    }

    public function setFieldMap(mixed $value): static
    {
        $this->fieldMap = RemoveXss($value);
        return $this;
    }

    public function getSamplePayload(): mixed
    {
        return HtmlDecode($this->samplePayload);
    }

    public function setSamplePayload(mixed $value): static
    {
        $this->samplePayload = RemoveXss($value);
        return $this;
    }

    public function getRequestBody(): mixed
    {
        return HtmlDecode($this->requestBody);
    }

    public function setRequestBody(mixed $value): static
    {
        $this->requestBody = RemoveXss($value);
        return $this;
    }

    public function getDataPath(): ?string
    {
        return HtmlDecode($this->dataPath);
    }

    public function setDataPath(?string $value): static
    {
        $this->dataPath = RemoveXss($value);
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
