<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use RuntimeException;

/**
 * Project-wide aliases declared in config rather than as a `@phpstan-type` on a class,
 * so a shape can name a primitive without every file importing it.
 */
final class ConfigTypeAliasTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/TypeAliasFixtures';

    /**
     * @param array<string, string> $aliases
     * @return array<string, string>
     */
    private function emit(array $aliases): array
    {
        return (new TypeScriptEmitter(
            enumResolver: new EnumResolver(),
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
            typeAliases: $aliases,
        ))->emit((new PhpDocTypeCollector())->collect(self::SRC));
    }

    public function test_it_declares_the_alias_in_the_domain_that_references_it(): void
    {
        $output = $this->emit(['UuidStr' => 'string']);

        self::assertStringContainsString('export type UuidStr = string;', $output['Widgets']);
        self::assertStringContainsString('id: UuidStr;', $output['Widgets']);
    }

    public function test_it_leaves_the_alias_out_of_domains_that_never_mention_it(): void
    {
        $output = $this->emit(['UuidStr' => 'string']);

        self::assertStringNotContainsString('UuidStr', $output['Gadgets']);
    }

    public function test_an_unaliased_name_is_still_an_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown TypeScript symbol "UuidStr"');

        $this->emit([]);
    }

    public function test_a_class_declared_shape_may_not_shadow_a_config_alias(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TypeScript naming collision');

        $this->emit(['WidgetView' => 'string']);
    }

    public function test_config_rejects_an_alias_that_is_not_an_identifier(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not a valid TypeScript identifier');

        TypeBridgeConfig::fromArray(['typeAliases' => ['not a name' => 'string']]);
    }
}
