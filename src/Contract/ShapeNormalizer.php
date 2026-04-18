<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Contract;

/**
 * @template TSource of object
 * @template TShapeOwner of object
 */
interface ShapeNormalizer
{
    /**
     * @param TSource $source
     * @return array
     */
    public function normalize(object $source): array;
}
