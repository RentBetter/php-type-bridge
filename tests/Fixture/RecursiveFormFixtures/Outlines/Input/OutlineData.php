<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Input;

/**
 * An outline.
 *
 * @phpstan-type _self = array{
 *     title: string,
 *     sections?: list<SectionData>,
 * }
 */
final class OutlineData
{
    public ?string $title = null;

    /** @var list<SectionData> */
    public array $sections = [];
}
