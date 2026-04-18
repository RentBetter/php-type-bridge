<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\Inspector\Input;

/**
 * @phpstan-type _self = array{
 *     title: string,
 * }
 */
final class NestedBrokenRequestData
{
    public ?string $title = null;
}
