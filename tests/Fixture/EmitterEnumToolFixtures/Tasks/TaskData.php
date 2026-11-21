<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmitterEnumToolFixtures\Tasks;

/**
 * @phpstan-type _self = array{
 *     meta?: MetaData,
 * }
 */
final class TaskData
{
    public ?MetaData $meta = null;
}
