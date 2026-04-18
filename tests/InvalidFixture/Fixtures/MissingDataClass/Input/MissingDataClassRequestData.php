<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingDataClass\Input;

/**
 * @phpstan-type _self = array{
 *     title: string,
 * }
 */
final class MissingDataClassRequestData
{
    public ?string $title = null;
}
