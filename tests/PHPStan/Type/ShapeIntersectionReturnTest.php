<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Type;

use PHPStan\Rules\FunctionReturnTypeCheck;
use PHPStan\Rules\Methods\ReturnTypeRule;
use PHPStan\Testing\RuleTestCase;

/**
 * A normaliser returning a shape that extends another is checked against both: the base it spreads
 * and the keys it adds.
 *
 * @extends RuleTestCase<ReturnTypeRule>
 */
final class ShapeIntersectionReturnTest extends RuleTestCase
{
    protected function getRule(): ReturnTypeRule
    {
        return new ReturnTypeRule(self::getContainer()->getByType(FunctionReturnTypeCheck::class));
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../../extension.neon'];
    }

    public function testAReturnIsCheckedAgainstTheBaseAndTheExtension(): void
    {
        $method = 'Method PTGS\TypeBridge\Tests\PHPStan\Type\Fixtures\ShapeIntersectionReturns::';
        $admin = "array{id: string, name?: string, '\$admin': array{email: string}}";
        $badEmail = "Offset '\$admin' (array{email: string}) does not accept type array{email: 1}: Offset 'email' (string) does not accept type int.";

        $this->analyse([__DIR__ . '/Fixtures/ShapeIntersectionReturns.php'], [
            [
                $method . "leavesOutTheExtension() should return {$admin} but returns array{id: string, name?: string}.",
                31,
                "Array does not have offset '\$admin'.",
            ],
            [
                $method . "mistypesTheExtension() should return {$admin} but returns array{id: string, name?: string, '\$admin': array{email: 1}}.",
                39,
                $badEmail,
            ],
            [
                $method . "mistypesTheBase() should return {$admin} but returns array{id: 'a', name: 1, '\$admin': array{email: 'a@example.com'}}.",
                47,
                "Offset 'name' (string) does not accept type int.",
            ],
            [
                $method . "mistypesAnOptionalExtension() should return array{id: string, name?: string, '\$admin'?: array{email: string}} but returns array{id: string, name?: string, '\$admin': array{email: 1}}.",
                55,
                $badEmail,
            ],
            [
                $method . 'leavesOutANarrowedKey() should return array{id: string, name: string} but returns array{id: string, name?: string}.',
                63,
                "Array might not have offset 'name'.",
            ],
            [
                $method . "mistypesAnOpenExtension() should return array{id: string, name?: string, flag: bool, ...} but returns array{id: string, name?: string, flag: 'yes'}.",
                79,
                "Offset 'flag' (bool) does not accept type string.",
            ],
            [
                $method . 'emptiesANonEmptyBase() should return non-empty-array{a?: int, b?: int, c?: int} but returns array{}.',
                87,
                'array{} is empty.',
            ],
        ]);
    }
}
