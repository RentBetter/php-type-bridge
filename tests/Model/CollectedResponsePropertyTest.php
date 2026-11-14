<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Model;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;

/**
 * A model a custom emitter may build: what it took before stays where it was.
 */
final class CollectedResponsePropertyTest extends TestCase
{
    public function test_it_takes_its_arguments_in_the_places_they_always_had(): void
    {
        $property = new CollectedResponseProperty('total', 'int', (new PhpDocShapeParser())->parse('int'), true);

        self::assertTrue($property->optional);
        self::assertSame([], $property->imports, 'Read in a scope that imports nothing');
        self::assertNull($property->writtenIn);
    }
}
