<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Enum;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Enum\NoEnumParseOfDtoPropertyRule;

/**
 * @extends RuleTestCase<NoEnumParseOfDtoPropertyRule>
 */
final class NoEnumParseOfDtoPropertyRuleTest extends RuleTestCase
{
    protected function getRule(): NoEnumParseOfDtoPropertyRule
    {
        return new NoEnumParseOfDtoPropertyRule($this->createReflectionProvider());
    }

    public function testRejectsEnumParsingInMethodsTakingAFormDto(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Enum/EnumParseOfDtoProperty.php',
        ], [
            [
                'TaskStatus::tryFrom() parses an enum in a method taking the DTO TaskFilterData. Type the DTO '
                . 'property as TaskStatus and use EnumType in the form (multiple: true for a list), so an invalid '
                . 'value is a 422 instead of a silently dropped filter.',
                39,
            ],
            [
                'TaskStatus::from() parses an enum in a method taking the DTO TaskFilterData. Type the DTO '
                . 'property as TaskStatus and use EnumType in the form (multiple: true for a list), so an invalid '
                . 'value is a 422 instead of a silently dropped filter.',
                51,
            ],
        ]);
    }
}
