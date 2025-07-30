<?php
namespace app\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)] // Permet d'utiliser l'attribut sur une classe ou une méthode
class Route
{
    public function __construct(
        public string $path,
        public ?string $name = null
    ) {}
}