<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\EmitterRegistry;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\ClassRefEmitter\ShapedEmitter;
use RuntimeException;

/**
 * A shape can name a class — `total: MoneyInterface` — for the JSON that class serialises to,
 * as PHPStan-typed code does wherever an array holds objects that json_encode then serialises.
 */
final class ClassReferenceTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/ClassRefFixtures';

    /** @var array<string, string> */
    private static array $output;

    public static function setUpBeforeClass(): void
    {
        self::$output = self::emit(self::SRC);
    }

    public function test_a_class_named_through_a_use_statement_is_its_self_shape_imported_from_its_module(): void
    {
        self::assertStringContainsString('  total: MoneyInterface;', self::$output['Reports']);
        self::assertStringContainsString("import type { MoneyInterface } from '../Money/genTypes';", self::$output['Reports']);
    }

    public function test_a_class_with_no_self_shape_takes_its_nearest_ancestors(): void
    {
        self::assertStringContainsString('  subTotal: MoneyInterface;', self::$output['Reports']);
    }

    public function test_a_fully_qualified_name_resolves_the_same(): void
    {
        self::assertStringContainsString('  net: MoneyInterface;', self::$output['Reports']);
    }

    public function test_a_class_its_emitter_publishes_a_type_for_is_that_type(): void
    {
        self::assertStringContainsString('  status: TaskStatus;', self::$output['Reports']);
        self::assertStringContainsString("import type { TaskStatus } from '../Shaped/genTypes';", self::$output['Reports']);
    }

    /**
     * An alias always wins over a class of the same name — whether the file declares it or
     * another file of the domain does.
     */
    public function test_an_alias_of_the_same_name_wins_over_the_class(): void
    {
        self::assertStringContainsString("export interface ExpenseItem {\n  total: MoneyInterface;\n  subTotal: MoneyInterface;\n  net: MoneyInterface;\n  status: TaskStatus;\n  summary: Summary;\n}", self::$output['Reports']);
        self::assertStringContainsString("export interface Totals {\n  summary: Summary;\n}", self::$output['Reports']);
    }

    public function test_a_response_property_typed_by_a_class_is_its_json(): void
    {
        self::assertStringContainsString("export interface ShowReportResponse {\n  total: MoneyInterface;\n  lines: MoneyInterface[];\n}", self::$output['Reports']);
    }

    public function test_a_php_stan_only_class_emits_nothing(): void
    {
        self::assertStringNotContainsString('Draft', self::$output['Reports']);
    }

    public function test_php_stan_only_can_name_the_aliases_it_covers(): void
    {
        self::assertStringNotContainsString('export interface Occupancy {', self::$output['Reports']);
        self::assertStringContainsString("export interface OccupancySerialised {\n  from: string;\n}", self::$output['Reports']);
    }

    public function test_a_class_with_no_serialised_shape_is_an_error_that_says_what_to_do(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DateTimeImmutable.*`_self`.*#\[PhpStanOnly\]/s');

        self::emit(__DIR__ . '/../Fixture/ClassRefErrorFixtures');
    }

    /**
     * The built-in conventions both claim an enum that declares an alias, at equal priority. They
     * publish no type, so they have no say in what it is called: the error is the missing shape,
     * not the tie.
     */
    public function test_an_enum_only_built_in_conventions_claim_is_the_missing_shape_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Priority.*`_self`/s');

        self::emit(__DIR__ . '/../Fixture/ClassRefUnclaimedFixtures');
    }

    /**
     * @return array<string, string>
     */
    private static function emit(string $src): array
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($src);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
            registry: EmitterRegistry::fromAttributeScan([ShapedEmitter::class]),
        ))->emit(
            (new PhpDocTypeCollector())->collect($src),
            $responseCollector->collect($src),
            (new EndpointContractCollector())->collect($src, $responseCollector->collectIndex($src)),
        );
    }
}
