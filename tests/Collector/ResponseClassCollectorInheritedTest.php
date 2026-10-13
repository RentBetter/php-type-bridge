<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Common\Response\ConflictResponse;
use PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Common\Response\NotFoundResponse;
use PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Notes\Response\ListNotesResponse;
use PTGS\TypeBridge\Tests\Fixture\InheritedResponseFixtures\Notes\Response\NoteGoneResponse;

/**
 * A property a response inherits is on the wire — TypeBridgeResponseSubscriber and the throwable
 * listener write every public property — so it is published, typed as the file that declares it
 * says. An error response that extends a base class holding the `{error: …}` envelope generated
 * as an empty interface before.
 */
final class ResponseClassCollectorInheritedTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/InheritedResponseFixtures';

    public function test_an_inherited_property_is_collected_with_the_type_its_declaring_file_gives_it(): void
    {
        $index = (new ResponseClassCollector())->collectIndex(self::SRC);

        self::assertSame(['error' => 'ErrorBodyData'], self::rawTypes($index[NotFoundResponse::class]));
        self::assertSame(['error' => 'ErrorBodyData'], self::rawTypes($index[NoteGoneResponse::class]), 'From a base class in another domain');
        self::assertSame(['error' => 'ErrorBodyData'], self::rawTypes($index[ConflictResponse::class]), 'Redeclared, it is still one property');
        self::assertSame(['notes' => 'list<string>', 'total' => 'int<0, max>'], self::rawTypes($index[ListNotesResponse::class]), 'Its own, then what it inherits');
    }

    public function test_the_base_classs_import_travels_with_the_property(): void
    {
        $imports = (new ResponseClassCollector())->collectIndex(self::SRC)[NoteGoneResponse::class]->imports;

        self::assertSame(['ErrorBodyData'], array_map(static fn(object $import): string => $import->localAlias, $imports));
        self::assertSame('Common', $imports[0]->targetDomain);
    }

    public function test_it_emits_the_envelope_on_every_error_response_and_imports_it_across_domains(): void
    {
        $output = self::emit();

        self::assertStringContainsString("export interface NotFoundResponse {\n  error: ErrorBody;\n}", $output['Common']);
        self::assertStringContainsString("export interface ConflictResponse {\n  error: ErrorBody;\n}", $output['Common']);
        self::assertStringContainsString("export interface NoteGoneResponse {\n  error: ErrorBody;\n}", $output['Notes']);
        self::assertMatchesRegularExpression('/import type \{[^}]*\bErrorBody\b[^}]*\} from \'[^\']*Common[^\']*\';/i', $output['Notes']);
        self::assertStringContainsString("export interface ListNotesResponse {\n  notes: string[];\n  total: number;\n}", $output['Notes']);
    }

    /**
     * @return array<string, string>
     */
    private static function rawTypes(CollectedApiResponseClass $response): array
    {
        return array_combine(
            array_map(static fn(CollectedResponseProperty $property): string => $property->name, $response->properties),
            array_map(static fn(CollectedResponseProperty $property): string => $property->rawType, $response->properties),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function emit(): array
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        );
    }
}
