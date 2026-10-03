<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Config\TypeScriptNaming;
use PTGS\TypeBridge\Emitter\ConversionScope;
use PTGS\TypeBridge\Emitter\EmittedNames;
use PTGS\TypeBridge\Emitter\SymbolRegistry;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Emitter\TypeToTsConverter;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;

/**
 * A quoted PHPDoc key is written as TypeScript needs it: bare when it is an identifier — `$type`
 * is one — and quoted only when it is not.
 */
final class QuotedKeyTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/QuotedKeyFixtures';

    /** @var array<string, string> */
    private static array $output;

    public static function setUpBeforeClass(): void
    {
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        self::$output = (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
        ))->emit((new PhpDocTypeCollector())->collect(self::SRC));
    }

    public function test_a_dollar_key_stays_bare_on_an_interface(): void
    {
        self::assertStringContainsString("export interface Reference {\n  \$type: Kind;\n  id: string;\n}", self::$output['Common']);
    }

    public function test_a_key_that_is_not_an_identifier_is_quoted_on_an_interface(): void
    {
        self::assertStringContainsString("  'content-type': string;\n", self::$output['Common']);
        self::assertStringContainsString("  'x-retry'?: number;\n", self::$output['Common']);
        self::assertStringContainsString("  inline: { \$type: string; id: string };\n", self::$output['Common']);
    }

    public function test_inline_shapes_quote_only_what_needs_it(): void
    {
        self::assertSame('{ $type: string; id: string }', $this->convert("array{'\$type': string, \"id\": string}"));
        self::assertSame("{ 'my-key': number; 'it\\'s'?: string; 0: boolean }", $this->convert("array{'my-key': int, \"it's\"?: string, 0: bool}"));
    }

    public function test_property_names(): void
    {
        self::assertSame('id', TypeToTsConverter::propertyName('id'));
        self::assertSame('$type', TypeToTsConverter::propertyName('$type'));
        self::assertSame('_private', TypeToTsConverter::propertyName('_private'));
        self::assertSame('10', TypeToTsConverter::propertyName('10'));
        self::assertSame("'01'", TypeToTsConverter::propertyName('01'));
        self::assertSame("'my-key'", TypeToTsConverter::propertyName('my-key'));
        self::assertSame("'2fa'", TypeToTsConverter::propertyName('2fa'));
        self::assertSame("''", TypeToTsConverter::propertyName(''));
        self::assertSame("'a\\\\b'", TypeToTsConverter::propertyName('a\\b'));
    }

    private function convert(string $phpDoc): string
    {
        $converter = new TypeToTsConverter(
            new EmittedNames(new TypeScriptNaming(), new EnumResolver()),
            new SymbolRegistry([]),
        );

        return $converter->convert((new PhpDocShapeParser())->parse($phpDoc), new ConversionScope('Test'));
    }
}
