<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Input;

/**
 * A section of an outline.
 *
 * @phpstan-type _self = array{
 *     heading: string,
 *     sections?: list<SectionData>,
 * }
 */
final class SectionData
{
    public ?string $heading = null;

    /** @var list<SectionData> */
    public array $sections = [];
}
