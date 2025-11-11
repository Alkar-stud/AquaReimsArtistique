<?php
namespace app\Models\Swimmer;

use app\Models\AbstractModel;

class SwimmerGroup extends AbstractModel
{
    private string $name;
    private ?string $coach = null;
    private bool $is_active = true;
    private int $order;

    /** @var Swimmer[] */
    private array $swimmers = [];

    // --- GETTERS ---
    public function getName(): string { return $this->name; }
    public function getCoach(): ?string { return $this->coach; }
    public function getIsActive(): bool { return $this->is_active; }
    public function getOrder(): int { return $this->order; }
    public function getSwimmers(): array { return $this->swimmers; }

    // --- SETTERS ---
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function setCoach(?string $coach): self { $this->coach = $coach; return $this; }
    public function setIsActive(bool $is_active): self { $this->is_active = $is_active; return $this; }
    public function setOrder(int $order): self { $this->order = $order; return $this; }
    public function setSwimmers(array $swimmers): self { $this->swimmers = $swimmers; return $this; }
    public function addSwimmer(Swimmer $swimmer): self {
        foreach ($this->swimmers as $s) { if ($s->getId() === $swimmer->getId()) { return $this; } }
        $this->swimmers[] = $swimmer; return $this;
    }
    public function removeSwimmer(Swimmer $swimmer): self
    {
        $id = $swimmer->getId();
        $this->swimmers = array_values(array_filter(
            $this->swimmers,
            fn (Swimmer $s) => $s->getId() !== $id
        ));
        return $this;
    }
}
