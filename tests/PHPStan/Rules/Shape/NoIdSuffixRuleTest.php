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
                'Field `projectId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `project` instead. If this is an external-system identifier, add `HasIdSuffixView.projectId` to `typeBridge.shapeNaming.allowIdSuffix`, or `_global.projectId` if the name is one in every shape.',
                14,
            ],
            [
                'Field `authorId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `author` instead. If this is an external-system identifier, add `HasIdSuffixView.authorId` to `typeBridge.shapeNaming.allowIdSuffix`, or `_global.authorId` if the name is one in every shape.',
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
        $this->allowIdSuffix = ['AllowlistedIdSuffixView.badId', '_global.stripeCustomerId'];

        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/AllowlistedIdSuffixView.php',
        ], []);

        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/HasIdSuffixView.php',
        ], [
            ['Field `projectId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `project` instead. If this is an external-system identifier, add `HasIdSuffixView.projectId` to `typeBridge.shapeNaming.allowIdSuffix`, or `_global.projectId` if the name is one in every shape.', 14],
            ['Field `authorId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `author` instead. If this is an external-system identifier, add `HasIdSuffixView.authorId` to `typeBridge.shapeNaming.allowIdSuffix`, or `_global.authorId` if the name is one in every shape.', 14],
        ]);
    }

    /**
     * A bare entry no longer exempts anything, and the error says so rather than leaving
     * someone staring at config that plainly names the field they are being told about.
     */
    public function testABareEntryNoLongerMatchesAndTheErrorExplainsWhy(): void
    {
        $this->allowIdSuffix = ['stripeCustomerId'];

        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/AllowlistedIdSuffixView.php',
        ], [
            ['Field `stripeCustomerId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\AllowlistedIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `stripeCustomer` instead. If this is an external-system identifier, add `AllowlistedIdSuffixView.stripeCustomerId` to `typeBridge.shapeNaming.allowIdSuffix`, or `_global.stripeCustomerId` if the name is one in every shape. (`stripeCustomerId` is currently listed unqualified, which no longer matches — a bare entry exempted the name in every shape, which was rarely what it was written for.)', 16],
            ['Field `badId` in `_self` shape on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\AllowlistedIdSuffixView must not end with `Id`. Reference fields should be named after the entity (singular): use `bad` instead. If this is an external-system identifier, add `AllowlistedIdSuffixView.badId` to `typeBridge.shapeNaming.allowIdSuffix`, or `_global.badId` if the name is one in every shape.', 16],
        ]);
    }

    public function testGlobalScopeExemptsTheNameEverywhere(): void
    {
        $this->allowIdSuffix = ['_global.stripeCustomerId', 'AllowlistedIdSuffixView.badId'];

        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/AllowlistedIdSuffixView.php',
        ], []);
    }
}
