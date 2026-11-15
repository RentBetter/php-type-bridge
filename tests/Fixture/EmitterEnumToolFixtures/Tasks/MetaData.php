<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmitterEnumToolFixtures\Tasks;

/**
 * @phpstan-type _self = array{
 *     status: TaskStatus,
 *     note?: string,
 * }
 */
final class MetaData
{
    public ?TaskStatus $status = null;

    public ?string $note = null;
}
