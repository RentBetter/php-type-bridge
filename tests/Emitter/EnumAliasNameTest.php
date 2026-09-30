<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;

/**
 * An enum's `_self` is named for the enum — `ThemeStateData` — but that is its shape, not every
 * alias the enum declares. Naming them all that emitted the enum's other aliases under the shape's
 * name, and an enum with both collided with itself.
 */
final class EnumAliasNameTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/EnumAliasFixtures';

    public function test_an_enums_other_aliases_keep_their_names(): void
    {
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        $output = (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
        ))->emit((new PhpDocTypeCollector())->collect(self::SRC))['Formatter'];

        self::assertStringContainsString("export type ThemeStateId = 'none' | 'info' | 'danger';", $output);
        self::assertStringContainsString("export interface ThemeStateData {\n  state: ThemeStateId;\n}", $output);
    }
}
