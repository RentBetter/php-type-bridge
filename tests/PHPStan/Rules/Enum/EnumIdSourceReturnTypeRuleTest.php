<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Enum;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Enum\EnumIdSourceReturnTypeRule;

/**
 * @extends RuleTestCase<EnumIdSourceReturnTypeRule>
 */
final class EnumIdSourceReturnTypeRuleTest extends RuleTestCase
{
    protected function getRule(): EnumIdSourceReturnTypeRule
    {
        return new EnumIdSourceReturnTypeRule();
    }

    public function testAcceptsStringReturningIdSource(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Enum/ValidIdSourceEnum.php',
        ], []);
    }

    public function testFlagsNonStringIdSource(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Enum/InvalidIdSourceEnum.php',
        ], [
            [
                'EnumIdSource method "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Enum\InvalidIdSourceEnum::code()" must be declared `: string` (non-nullable) so its literal can be emitted.',
                14,
            ],
        ]);
    }
}
