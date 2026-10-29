<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Type;

use PHPStan\Rules\Classes\LocalTypeAliasesCheck;
use PHPStan\Rules\Classes\LocalTypeAliasesRule;
use PHPStan\Testing\RuleTestCase;

/**
 * An alias extending a shape is reported where PHPStan cannot read it: a key it declares again
 * other than to narrow it, and an invalid type among keys that are all optional, which PHPStan
 * alone drops with those keys, unchecked.
 *
 * @extends RuleTestCase<LocalTypeAliasesRule>
 */
final class ShapeIntersectionAliasTest extends RuleTestCase
{
    protected function getRule(): LocalTypeAliasesRule
    {
        return new LocalTypeAliasesRule(self::getContainer()->getByType(LocalTypeAliasesCheck::class));
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../../extension.neon'];
    }

    public function testAnAliasPhpStanCannotReadIsReported(): void
    {
        $this->analyse([__DIR__ . '/Fixtures/ShapeIntersectionAliases.php'], [
            ['Invalid type definition detected in type alias ModesData.', 15],
            ['Invalid type definition detected in type alias ClashData.', 15],
            ['Invalid type definition detected in type alias LoosenedData.', 15],
        ]);
    }
}
