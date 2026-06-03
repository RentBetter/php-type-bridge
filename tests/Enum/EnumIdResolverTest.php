<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Enum;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Enum\EnumIdResolver;
use PTGS\TypeBridge\Enum\EnumIdSource;
use PTGS\TypeBridge\Enum\EnumIdSourceMode;
use PTGS\TypeBridge\Tests\Fixture\Enum\BadReturn;
use PTGS\TypeBridge\Tests\Fixture\Enum\Colour;
use PTGS\TypeBridge\Tests\Fixture\Enum\Mapper;
use PTGS\TypeBridge\Tests\Fixture\Enum\Prefixed;
use ReflectionEnum;
use RuntimeException;

final class EnumIdResolverTest extends TestCase
{
    public function test_method_source_executes_id_per_case(): void
    {
        $ids = (new EnumIdResolver())->resolve(new ReflectionEnum(Colour::class), new EnumIdSource(method: 'id'));

        self::assertSame(['COLOUR_RED', 'COLOUR_GREEN'], $ids);
    }

    public function test_backing_value_source(): void
    {
        $ids = (new EnumIdResolver())->resolve(new ReflectionEnum(Colour::class), new EnumIdSource(source: EnumIdSourceMode::BackingValue));

        self::assertSame(['red', 'green'], $ids);
    }

    public function test_case_name_source(): void
    {
        $ids = (new EnumIdResolver())->resolve(new ReflectionEnum(Colour::class), new EnumIdSource(source: EnumIdSourceMode::CaseName));

        self::assertSame(['RED', 'GREEN'], $ids);
    }

    public function test_prefixed_case_name_source_uses_id_prefix_constant(): void
    {
        $ids = (new EnumIdResolver())->resolve(new ReflectionEnum(Prefixed::class), new EnumIdSource(source: EnumIdSourceMode::PrefixedCaseName));

        self::assertSame(['PFX_ONE', 'PFX_TWO'], $ids);
    }

    public function test_map_source_executes_static_method_per_case(): void
    {
        $ids = (new EnumIdResolver())->resolve(new ReflectionEnum(Colour::class), new EnumIdSource(map: [Mapper::class, 'idFor']));

        self::assertSame(['M_RED', 'M_GREEN'], $ids);
    }

    public function test_method_source_rejects_non_string_return(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be public, accept 0 argument(s), and be declared `: string`');

        (new EnumIdResolver())->resolve(new ReflectionEnum(BadReturn::class), new EnumIdSource(method: 'code'));
    }
}
