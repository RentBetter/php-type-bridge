<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SelfNamingInputFixtures\Nodes;

/**
 * Names itself: its `parent` is the class being read, so it stays a name.
 *
 * @phpstan-type _self = array{
 *     name: string,
 *     parent?: NodeInput,
 * }
 */
final class NodeInput
{
    public ?string $name = null;

    public ?NodeInput $parent = null;
}
