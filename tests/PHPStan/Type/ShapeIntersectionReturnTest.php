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
                27,
                "Array does not have offset '\$admin'.",
            ],
            [
                $method . "mistypesTheExtension() should return {$admin} but returns array{id: string, name?: string, '\$admin': array{email: 1}}.",
                35,
                $badEmail,
            ],
            [
                $method . "mistypesTheBase() should return {$admin} but returns array{id: 'a', name: 1, '\$admin': array{email: 'a@example.com'}}.",
                43,
                "Offset 'name' (string) does not accept type int.",
            ],
            [
                $method . "mistypesAnOptionalExtension() should return array{id: string, name?: string, '\$admin'?: array{email: string}} but returns array{id: string, name?: string, '\$admin': array{email: 1}}.",
                51,
                $badEmail,
            ],
        ]);
    }
}
