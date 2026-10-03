<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Include;

use PTGS\TypeBridge\Contract\ShapeNormalizer;

/**
 * Makes no markers, so its view's shapes are its own business.
 *
 * @implements ShapeNormalizer<Note, NoteView>
 */
final class UnmarkedNormalizer implements ShapeNormalizer
{
    public function normalize(object $source): array
    {
        return ['id' => 'n-1'];
    }
}
