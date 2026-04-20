<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\UnknownTypeRef\View;

/**
 * @phpstan-type _self = array{
 *     id: string,
 *     ghost: GhostType,
 * }
 */
final class UnknownTypeRefView
{
    public string $id = '';
}
