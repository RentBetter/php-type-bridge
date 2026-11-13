<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;

/**
 * A property a generic base declares, as its subclass binds it: whatever its param is bound to, or
 * defaults to, or is bounded by.
 */
final class GenericBaseEdgeTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/GenericBaseEdgeFixtures';

    public function test_a_param_extended_by_a_shape_is_what_it_is_bound_to_extended(): void
    {
        // `T & array{total: int}`, T bound to Meta: Meta, extended.
        self::assertStringContainsString('meta: Meta & { total: number };', $this->emit()['Metas'] ?? '');
    }

    public function test_a_params_default_naming_another_is_that_one_as_bound(): void
    {
        // TList defaults to `list<TItem>`, and TItem is bound to string.
        self::assertStringContainsString('items: string[];', $this->emit()['Items'] ?? '');
    }

    public function test_a_param_bound_by_nothing_is_its_bound_as_phpstan_reads_it(): void
    {
        // `of array`: any array, which json_encode writes as an object or a list.
        self::assertStringContainsString('data: Record<string, unknown> | unknown[];', $this->emit()['Data'] ?? '');
    }

    public function test_phpstans_keywords_are_what_phpstan_reads_them_as(): void
    {
        $plain = $this->emit()['Plain'] ?? '';

        self::assertStringContainsString('data: Record<string, unknown> | unknown[];', $plain);
        self::assertStringContainsString('more: unknown;', $plain);
        self::assertStringContainsString('each: unknown;', $plain);
        self::assertStringContainsString('rows: unknown[];', $plain);
    }

    /**
     * @return array<string, string>
     */
    private function emit(): array
    {
        $responses = new ResponseClassCollector();
        $enums = new EnumResolver();
        $enums->scanDirectory(self::SRC);

        return (new TypeScriptEmitter(enumResolver: $enums, domainMapper: new DomainMapper('/tmp/type-bridge-output')))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responses->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responses->collectIndex(self::SRC)),
        );
    }
}
