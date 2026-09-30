<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Attribute;

use Attribute;

/**
 * `@phpstan-type` aliases that type PHP arrays which are never serialised — working data holding
 * objects (`DateTimeImmutable`, entities) — so they exist for PHPStan alone and TypeBridge emits
 * nothing for them.
 *
 * On the class that declares them: every alias it declares, or only those named when the class
 * also declares shapes that are sent — `#[PhpStanOnly(['Draft'])]`. A shape that is sent as JSON
 * must not import one: its type would have nothing to point at.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PhpStanOnly
{
    /**
     * @param list<string> $aliases the aliases that are PHPStan-only; none named means all of them
     */
    public function __construct(
        public array $aliases = [],
    ) {}

    public function covers(string $alias): bool
    {
        return [] === $this->aliases || \in_array($alias, $this->aliases, true);
    }
}
