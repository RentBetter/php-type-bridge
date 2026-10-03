<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Normalizer;

use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Normalizer\ShapeValuesRule;

/**
 * @extends RuleTestCase<ShapeValuesRule>
 */
final class ShapeValuesRuleTest extends RuleTestCase
{
    private const string WHY = '. A shape a normaliser writes with IncludeMarkers holds scalars, lists and shapes, and the markers it makes (enum(), ref(), optional()), so every decision about what goes out is made in the normaliser.';

    private const string FIXTURES = __DIR__ . '/../../Fixtures/Include/';

    protected function getRule(): ShapeValuesRule
    {
        return new ShapeValuesRule(self::getContainer()->getByType(TypeNodeResolver::class));
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../../../includes.neon'];
    }

    public function testAShapeTheNormaliserDeclaresHoldsOnlyScalarsAndMarkers(): void
    {
        $this->analyse([self::FIXTURES . 'ReportNormalizer.php'], [
            ['Shape RawEnumData on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Include\ReportNormalizer holds PTGS\TypeBridge\Tests\Http\Include\Fixtures\CheckStatus' . self::WHY, 29],
            ['Shape NestedObjectData on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Include\ReportNormalizer holds DateTimeImmutable' . self::WHY, 29],
        ]);
    }

    public function testTheShapeOfTheViewItWritesIsHeldToItToo(): void
    {
        $this->analyse([self::FIXTURES . 'NoteNormalizer.php'], [
            ['Shape _self on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Include\NoteView, which PTGS\TypeBridge\Tests\PHPStan\Fixtures\Include\NoteNormalizer writes, holds DateTimeImmutable' . self::WHY, 15],
        ]);
    }

    public function testANormaliserThatMakesNoMarkersIsLeftAlone(): void
    {
        $this->analyse([self::FIXTURES . 'UnmarkedNormalizer.php', self::FIXTURES . 'NoteView.php'], []);
    }
}
