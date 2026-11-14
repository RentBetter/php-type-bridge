<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input;

/**
 * Names itself: read once round, not forever.
 *
 * @phpstan-type _self = array{
 *     label: string,
 *     children?: list<NodeData>,
 * }
 */
final class NodeData
{
    public ?string $label = null;

    /** @var list<NodeData> */
    public array $children = [];
}
