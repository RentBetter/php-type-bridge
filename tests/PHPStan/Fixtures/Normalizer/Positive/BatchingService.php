<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Normalizer\Positive;

use PTGS\TypeBridge\Tests\PHPStan\Fixtures\Normalizer\Widget;
use PTGS\TypeBridge\Tests\PHPStan\Fixtures\Normalizer\WidgetNormalizer;

final class BatchingService
{
    public function __construct(private readonly WidgetNormalizer $normalizer) {}

    /**
     * @param list<Widget> $widgets
     *
     * @return list<array<string, mixed>>
     */
    public function batch(array $widgets): array
    {
        // OK: the normalizer owns the iteration.
        return $this->normalizer->normalizeMany($widgets);
    }

    /**
     * @return array<string, mixed>
     */
    public function single(Widget $widget): array
    {
        // OK: a single normalize() outside any loop.
        return $this->normalizer->normalize($widget);
    }
}
