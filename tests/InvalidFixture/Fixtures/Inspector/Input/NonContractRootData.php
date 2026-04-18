<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\Inspector\Input;

/**
 * @phpstan-type _self = array{
 *     title: string,
 * }
 */
final class NonContractRootData
{
    public ?string $title = null;
}
