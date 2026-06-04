<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Normalizer;

/**
 * Base for TypeBridge ShapeNormalizer implementations.
 *
 * Provides filterNulls() so normalizers can drop null-valued keys before returning,
 * matching the convention of never serializing null values.
 */
abstract class AbstractShapeNormalizer
{
    /**
     * @template TArray of array<string, mixed>
     *
     * @param TArray $data
     *
     * @return TArray
     */
    protected function filterNulls(array $data): array
    {
        /** @var TArray $filtered */
        $filtered = array_filter($data, static fn(mixed $value): bool => null !== $value);

        return $filtered;
    }
}
