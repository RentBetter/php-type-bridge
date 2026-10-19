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

    /**
     * The class's say on which of its aliases are for PHPStan alone; null where it says none are, and
     * where it can't be loaded to say: a class kept under src/ for tooling, whose declaration needs a
     * require-dev package, throws in an install without one.
     */
    public static function of(string $class): ?self
    {
        try {
            if (!class_exists($class) && !interface_exists($class) && !trait_exists($class) && !enum_exists($class)) {
                return null;
            }
        } catch (\Error) {
            return null;
        }

        $attributes = (new \ReflectionClass($class))->getAttributes(self::class);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }
}
