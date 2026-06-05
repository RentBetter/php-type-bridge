<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Normalizer;

use PTGS\TypeBridge\Contract\ShapeNormalizer;

/**
 * @implements ShapeNormalizer<Widget, Widget>
 */
final class WidgetNormalizer implements ShapeNormalizer
{
    /**
     * @param Widget $source
     *
     * @return array<string, mixed>
     */
    public function normalize(object $source): array
    {
        return ['id' => $source->id];
    }

    /**
     * A normalizer's own batch method may loop $this->normalize() — it owns the iteration,
     * so the rule must not flag this.
     *
     * @param list<Widget> $sources
     *
     * @return list<array<string, mixed>>
     */
    public function normalizeMany(array $sources): array
    {
        return array_map(fn (Widget $widget): array => $this->normalize($widget), $sources);
    }
}
