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
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\CheckStatus;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\Priority;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\Thing;

/**
 * Exposes the IncludeMarkers helpers to a test. phpstan.neon.dist analyses it too, which is what
 * has PHPStan look into the trait — nothing in src uses it — and holds normalize() to a shape
 * written with the include generics, as a consumer's would be.
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     status: enum<CheckStatus>,
 *     priority?: enum<Priority>,
 *     thing?: ref<array{id: string}>,
 *     things: list<ref<array{id: string}>>,
 *     debug: included<array<string, mixed>>,
 *     notes?: included<string>,
 * }
 */
final class MarkingNormalizer
{
    use IncludeMarkers;

    /**
     * @return _self
     */
    public function normalize(Thing $thing, ?Priority $priority): array
    {
        return array_filter([
            'id' => $thing->getId(),
            'status' => $this->enum(CheckStatus::Ok),
            'priority' => $this->enum($priority),
            'thing' => $this->ref($thing),
            'things' => $this->refs([$thing]),
            'debug' => $this->optional(static fn (): array => ['seen' => 1]),
            'notes' => $this->optional(static fn (): string => 'notes'),
        ], static fn (mixed $value): bool => null !== $value);
    }

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
