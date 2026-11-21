<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Input;

/**
 * A tree.
 *
 * @phpstan-type _self = array{
 *     name: string,
 *     children?: list<TreeData>,
 * }
 */
final class TreeData
{
    public ?string $name = null;

    /** @var list<TreeData> */
    public array $children = [];
}
