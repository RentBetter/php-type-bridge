<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form;

/**
 * @phpstan-type _self = array{
 *     label?: string,
 *     tags?: list<string>,
 * }
 */
final class CountedBlankData
{
    public ?string $label = null;

    /** @var list<string> */
    public array $tags = [];
}
