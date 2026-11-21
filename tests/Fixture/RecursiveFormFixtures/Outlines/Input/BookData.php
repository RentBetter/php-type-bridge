<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Input;

/**
 * A book.
 *
 * @phpstan-type _self = array{
 *     title: string,
 *     appendix?: SectionData,
 * }
 */
final class BookData
{
    public ?string $title = null;

    public ?SectionData $appendix = null;
}
