<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan;

use PHPStan\Rules\Classes\LocalTypeAliasesCheck;
use PHPStan\Rules\Classes\LocalTypeAliasesRule;
use PHPStan\Testing\RuleTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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
        return [__DIR__ . '/../../extension.neon'];
    }

    public function testPhpStanReadsEveryGrammarFixtureAlias(): void
    {
        $files = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../Fixture/GrammarFixtures', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $files[] = $file->getPathname();
        }
        sort($files);

        self::assertNotEmpty($files);
        $this->analyse($files, []);
    }
}
