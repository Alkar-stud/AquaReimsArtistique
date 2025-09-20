<?php

namespace app\Models;

use DateTime;
use DateTimeInterface;

class Config
{
    private int $id;
    private string $label;
    private string $config_key;
    private string $config_value;
    private ?string $config_type = null;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;

    public function getId(): int { return $this->id; }
    public function getLabel(): string { return $this->label; }
    public function getConfigKey(): string { return $this->config_key; }
    public function getConfigValue(): string { return $this->config_value; }
    public function getConfigType(): ?string { return $this->config_type; }
    public function getCreatedAt(): DateTimeInterface { return $this->created_at; }
    public function getUpdatedAt(): ?DateTimeInterface { return $this->updated_at; }

    public function setId(int $id): self { $this->id = $id; return $this; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }
    public function setConfigKey(string $config_key): self { $this->config_key = $config_key; return $this; }
    public function setConfigValue(string $config_value): self { $this->config_value = $config_value; return $this; }
    public function setConfigType(?string $config_type): self { $this->config_type = $config_type; return $this; }
    public function setCreatedAt(string $created_at): self { $this->created_at = new DateTime($created_at); return $this; }
    public function setUpdatedAt(?string $updated_at): self { $this->updated_at = $updated_at ? new DateTime($updated_at) : null; return $this; }
}