<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan;

use PHPStan\Rules\Classes\LocalTypeAliasesCheck;
use PHPStan\Rules\Classes\LocalTypeAliasesRule;
use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\Support\PhpFileClassLocator;

/**
 * The grammar fixtures are the PHPStan syntax a real codebase writes, so PHPStan has to read
 * every alias in them: a form only the generator understands is one whose shapes go unchecked.
 *
 * @extends RuleTestCase<LocalTypeAliasesRule>
 */
final class GrammarFixtureAliasesTest extends RuleTestCase
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
        return [\dirname(__DIR__, 2) . '/extension.neon'];
    }

    public function testPhpStanReadsEveryGrammarFixtureAlias(): void
    {
        $files = array_values((new PhpFileClassLocator())->classesIn(__DIR__ . '/../Fixture/GrammarFixtures'));

        self::assertNotEmpty($files);
        $this->analyse($files, []);
    }
}
