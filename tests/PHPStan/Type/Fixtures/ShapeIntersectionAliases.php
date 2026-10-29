<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Type\Fixtures;

/**
 * @phpstan-type BaseData = array{id: string, name?: string}
 * @phpstan-type AdminData = BaseData & array{'$admin': array{email: string}}
 * @phpstan-type NamedData = BaseData & array{name: string}
 * @phpstan-type ModesData = BaseData & array{modes?: list<value-of<self::MODE_*>>}
 * @phpstan-type ClashData = BaseData & array{id: int}
 * @phpstan-type LoosenedData = BaseData & array{id?: string}
 */
final class ShapeIntersectionAliases
{
    public const string MODE_SIMPLE = 'simple';
    public const string MODE_FULL = 'full';
}
