<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Normalizer\Negative;

use PTGS\TypeBridge\Tests\PHPStan\Fixtures\Normalizer\Widget;
use PTGS\TypeBridge\Tests\PHPStan\Fixtures\Normalizer\WidgetNormalizer;

final class LoopingService
{
    public function __construct(private readonly WidgetNormalizer $normalizer) {}

    /**
     * @param list<Widget> $widgets
     *
     * @return list<array<string, mixed>>
     */
    public function viaArrayMap(array $widgets): array
    {
        return array_map(fn (Widget $widget): array => $this->normalizer->normalize($widget), $widgets);
    }

    /**
     * @param list<Widget> $widgets
     *
     * @return list<array<string, mixed>>
     */
    public function viaForeach(array $widgets): array
    {
        $out = [];
        foreach ($widgets as $widget) {
            $out[] = $this->normalizer->normalize($widget);
        }

        return $out;
    }
}
