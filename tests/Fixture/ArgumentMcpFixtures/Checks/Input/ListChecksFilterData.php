<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input;

use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Enum\Area;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Enum\Severity;

/**
 * `rank` is declared an int, as its backing value is, which its form still reads as a string.
 *
 * @phpstan-type _self = array{
 *     area?: string,
 *     areas?: list<string>,
 *     rank?: int,
 *     level?: string,
 *     owner?: string,
 * }
 */
final class ListChecksFilterData
{
    public ?Area $area = null;

    /** @var list<Area> */
    public array $areas = [];

    public ?Severity $rank = null;

    public ?string $level = null;

    public ?string $owner = null;
}
