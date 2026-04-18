<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Route
{
    /**
     * @param list<string>|null $methods
     */
    public function __construct(
        public ?string $path = null,
        public ?string $name = null,
        public ?array $methods = null,
    ) {}
}
