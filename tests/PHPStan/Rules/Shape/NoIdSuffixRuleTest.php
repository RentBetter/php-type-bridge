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
                'Field `projectId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `project` instead. Add `HasIdSuffixView.projectId` to `typeBridge.shapeNaming.allowIdSuffix` if this is an external-system identifier, or the bare `projectId` if the name is an external identifier in every shape.',
                14,
            ],
            [
                'Field `authorId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `author` instead. Add `HasIdSuffixView.authorId` to `typeBridge.shapeNaming.allowIdSuffix` if this is an external-system identifier, or the bare `authorId` if the name is an external identifier in every shape.',
                14,
            ],
        ]);
    }

    /**
     * A name defensible on one shape should not be quietly permitted on every shape, which is
     * all a bare entry can express. The qualified form scopes it, as `preserveNull` already
     * does — here `badId` is exempt on AllowlistedIdSuffixView and still flagged elsewhere.
     */
    public function testAQualifiedEntryExemptsOnlyItsOwnShape(): void
    {
        $this->allowIdSuffix = ['AllowlistedIdSuffixView.badId', 'stripeCustomerId'];

        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/AllowlistedIdSuffixView.php',
        ], []);

        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/HasIdSuffixView.php',
        ], [
            ['Field `projectId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `project` instead. Add `HasIdSuffixView.projectId` to `typeBridge.shapeNaming.allowIdSuffix` if this is an external-system identifier, or the bare `projectId` if the name is an external identifier in every shape.', 14],
            ['Field `authorId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `author` instead. Add `HasIdSuffixView.authorId` to `typeBridge.shapeNaming.allowIdSuffix` if this is an external-system identifier, or the bare `authorId` if the name is an external identifier in every shape.', 14],
        ]);
    }

    public function testAllowlistSkipsListedFieldsOnly(): void
    {
        $this->allowIdSuffix = ['stripeCustomerId'];

        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/AllowlistedIdSuffixView.php',
        ], [
            [
                'Field `badId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\AllowlistedIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `bad` instead. Add `AllowlistedIdSuffixView.badId` to `typeBridge.shapeNaming.allowIdSuffix` if this is an external-system identifier, or the bare `badId` if the name is an external identifier in every shape.',
                16,
            ],
        ]);
    }
}
