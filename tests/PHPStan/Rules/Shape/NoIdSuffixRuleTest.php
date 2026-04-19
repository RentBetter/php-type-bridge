<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Shape;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Shape\NoIdSuffixRule;

/**
 * @extends RuleTestCase<NoIdSuffixRule>
 */
final class NoIdSuffixRuleTest extends RuleTestCase
{
    /** @var list<string> */
    private array $allowIdSuffix = [];

    protected function getRule(): NoIdSuffixRule
    {
        return new NoIdSuffixRule($this->allowIdSuffix);
    }

    public function testAcceptsShapeWithoutIdSuffix(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Valid/CleanView.php',
        ], []);
    }

    public function testAcceptsNonStringIdFields(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Valid/NonStringIdFieldView.php',
        ], []);
    }

    public function testFlagsIdSuffixOnStringFields(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/HasIdSuffixView.php',
        ], [
            [
                'Field `projectId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `project` instead. Add `projectId` to `typeBridge.shapeNaming.allowIdSuffix` if this is an external-system identifier.',
                14,
            ],
            [
                'Field `authorId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `author` instead. Add `authorId` to `typeBridge.shapeNaming.allowIdSuffix` if this is an external-system identifier.',
                14,
            ],
        ]);
    }

    public function testAllowlistSkipsListedFieldsOnly(): void
    {
        $this->allowIdSuffix = ['stripeCustomerId'];

        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/AllowlistedIdSuffixView.php',
        ], [
            [
                'Field `badId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\AllowlistedIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `bad` instead. Add `badId` to `typeBridge.shapeNaming.allowIdSuffix` if this is an external-system identifier.',
                16,
            ],
        ]);
    }
}
