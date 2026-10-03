<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Type;

use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class IncludeTypeNodeResolverExtensionTest extends TypeInferenceTestCase
{
    /**
     * @return iterable<mixed>
     */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/Fixtures/IncludeGenerics.php');
    }

    #[DataProvider('dataFileAsserts')]
    public function testFileAsserts(string $assertType, string $file, mixed ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/Fixtures/include-generics.neon'];
    }
}
