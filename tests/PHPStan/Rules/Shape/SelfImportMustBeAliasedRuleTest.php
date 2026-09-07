<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Shape;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Shape\SelfImportMustBeAliasedRule;

/**
 * @extends RuleTestCase<SelfImportMustBeAliasedRule>
 */
final class SelfImportMustBeAliasedRuleTest extends RuleTestCase
{
    protected function getRule(): SelfImportMustBeAliasedRule
    {
        return new SelfImportMustBeAliasedRule();
    }

    public function testIgnoresAClassThatImportsNothing(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Valid/CleanView.php',
        ], []);
    }

    public function testAcceptsAnAliasedSelfImport(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Valid/AliasedSelfImportNormalizer.php',
        ], []);
    }

    public function testFlagsAnUnaliasedSelfImport(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/UnaliasedSelfImportNormalizer.php',
        ], [
            [
                'Import of `_self` from CleanView must be aliased — `@phpstan-import-type _self from CleanView as <Something>Data`. Unaliased it is imported under the name `_self`, which in this class reads as its own shape while meaning another class\'s. The alias may not match a class already in scope.',
                15,
            ],
        ]);
    }
}
