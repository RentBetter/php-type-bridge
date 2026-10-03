<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Normalizer\Fixtures;

use BackedEnum;
use Closure;
use PTGS\TypeBridge\Enum\ApiEnum;
use PTGS\TypeBridge\Http\Include\EnumCase;
use PTGS\TypeBridge\Http\Include\Optional;
use PTGS\TypeBridge\Http\Include\Ref;
use PTGS\TypeBridge\Normalizer\IncludeMarkers;

/**
 * Exposes the IncludeMarkers helpers to a test. phpstan.neon.dist analyses it too, which is what
 * has PHPStan look into the trait: nothing in src uses it.
 */
final class MarkingNormalizer
{
    use IncludeMarkers;

    /**
     * @template T
     *
     * @param Closure(): T $value
     *
     * @return Optional<T>
     */
    public function exposeOptional(Closure $value): Optional
    {
        return $this->optional($value);
    }

    public function exposeRef(object $entity): Ref
    {
        return $this->ref($entity);
    }

    /**
     * @param iterable<object> $entities
     *
     * @return list<Ref>
     */
    public function exposeRefs(iterable $entities): array
    {
        return $this->refs($entities);
    }

    /**
     * @template T of ApiEnum|BackedEnum
     *
     * @param T $case
     *
     * @return EnumCase<T>
     */
    public function exposeEnum(ApiEnum|BackedEnum $case): EnumCase
    {
        return $this->enum($case);
    }

    /**
     * An absent value stays absent. The return type has PHPStan check that the helpers say so.
     *
     * @return array{null, null}
     */
    public function exposeNulls(): array
    {
        return [$this->ref(null), $this->enum(null)];
    }
}
