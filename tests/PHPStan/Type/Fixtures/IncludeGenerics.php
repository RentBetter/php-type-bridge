<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Type\Fixtures;

use PTGS\TypeBridge\Tests\Http\Include\Fixtures\CheckStatus;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\Priority;

use function PHPStan\Testing\assertType;

/**
 * @phpstan-type RecordData = array{id: string}
 * @phpstan-type Shape = array{
 *     debug: included<int>,
 *     notes: lazy<string>,
 *     owner: ref<RecordData>,
 *     status: enum<CheckStatus>,
 *     priority?: enum<Priority>,
 * }
 */
final class IncludeGenerics
{
    /**
     * @param Shape $shape
     */
    public function read(array $shape): void
    {
        assertType('int|PTGS\TypeBridge\Http\Include\Optional<int>', $shape['debug']);
        assertType('PTGS\TypeBridge\Http\Include\Optional<string>|string', $shape['notes']);
        assertType('PTGS\TypeBridge\Http\Include\Ref', $shape['owner']);
        assertType('PTGS\TypeBridge\Http\Include\EnumCase<PTGS\TypeBridge\Tests\Http\Include\Fixtures\CheckStatus>', $shape['status']);
        assertType('PTGS\TypeBridge\Http\Include\EnumCase<PTGS\TypeBridge\Tests\Http\Include\Fixtures\Priority>', $shape['priority'] ?? throw new \LogicException());
    }
}
