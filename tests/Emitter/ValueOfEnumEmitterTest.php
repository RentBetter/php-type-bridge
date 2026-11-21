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
 * An enum's value union as TypeScript can read it, whatever its values hold.
 */
final class ValueOfEnumEmitterTest extends TestCase
{
    public function test_a_value_is_a_string_literal_escaped_and_no_values_are_never(): void
    {
        $src = __DIR__ . '/../Fixture/EnumLiteralFixtures';
        $responses = new ResponseClassCollector();
        $enums = new EnumResolver();
        $enums->scanDirectory($src);

        $clock = (new TypeScriptEmitter(enumResolver: $enums, domainMapper: new DomainMapper('/tmp/type-bridge-output')))->emit(
            (new PhpDocTypeCollector())->collect($src),
            $responses->collect($src),
            (new EndpointContractCollector())->collect($src, $responses->collectIndex($src)),
        )['Clock'];

        self::assertStringContainsString("export type Time = 'o\\'clock' | 'a\\\\b';", $clock);
        self::assertStringContainsString('export type Nothing = never;', $clock);
    }
}
