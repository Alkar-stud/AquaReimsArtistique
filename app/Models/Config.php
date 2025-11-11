<?php

namespace app\Models;

class Config extends AbstractModel
{
    private string $label;
    private string $config_key;
    private string $config_value;
    private ?string $config_type = null;

    // --- GETTERS ---
    public function getLabel(): string { return $this->label; }
    public function getConfigKey(): string { return $this->config_key; }
    public function getConfigValue(): string { return $this->config_value; }
    public function getConfigType(): ?string { return $this->config_type; }

    // --- SETTERS ---
    public function setLabel(string $label): self { $this->label = $label; return $this; }
    public function setConfigKey(string $config_key): self { $this->config_key = $config_key; return $this; }
    public function setConfigValue(string $config_value): self { $this->config_value = $config_value; return $this; }
    public function setConfigType(?string $config_type): self { $this->config_type = $config_type; return $this; }

}