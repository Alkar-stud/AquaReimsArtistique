<?php
namespace app\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Route
{
    public function __construct(
        public string $path,
        public ?string $name = null
    ) {}
}