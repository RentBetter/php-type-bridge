<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Include;

use PTGS\TypeBridge\Contract\ShapeNormalizer;
use PTGS\TypeBridge\Normalizer\IncludeMarkers;

/**
 * Writes the shape its view declares, as Setout's normalisers do.
 *
 * @implements ShapeNormalizer<Note, NoteView>
 */
final class NoteNormalizer implements ShapeNormalizer
{
    use IncludeMarkers;

    public function normalize(object $source): array
    {
        return ['id' => 'n-1'];
    }
}
