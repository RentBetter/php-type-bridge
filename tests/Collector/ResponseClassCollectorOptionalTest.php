<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\OptionalResponseFixtures\Notes\Response\ShowNoteResponse;

/**
 * A response property annotated `?T` is TS-optional — `field?: T`, off the wire when null — the
 * same reading `?T` gets inside a `_self` shape. `T|null` stays a required key that may be null.
 */
final class ResponseClassCollectorOptionalTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/OptionalResponseFixtures';

    public function test_a_nullable_response_property_is_collected_as_optional(): void
    {
        $response = (new ResponseClassCollector())->collectIndex(self::SRC)[ShowNoteResponse::class];
        $optional = array_combine(
            array_map(static fn(CollectedResponseProperty $property): string => $property->name, $response->properties),
            array_map(static fn(CollectedResponseProperty $property): bool => $property->optional, $response->properties),
        );

        self::assertSame(['note' => false, 'replies' => true, 'history' => false], $optional);
    }

    public function test_it_emits_as_an_optional_key_and_an_explicit_null_as_a_required_one(): void
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        $output = (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        );

        self::assertStringContainsString("export interface ShowNoteResponse {\n  note: NoteView;\n  replies?: NoteView[];\n  history: NoteView[] | null;\n}", $output['Notes']);
    }
}
