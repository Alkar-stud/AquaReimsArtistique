<?php

namespace app\Models\User;

use app\Models\AbstractModel;
use DateTime;
use DateTimeInterface;

class Role extends AbstractModel
{
    private string $label;
    private int $level;

    // --- GETTERS ---
    public function getLabel(): string { return $this->label; }
    public function getLevel(): int { return $this->level; }

    // --- SETTERS ---
    public function setLabel(string $label): self { $this->label = $label; return $this; }
    public function setLevel(int $level): self { $this->level = $level; return $this; }

}
