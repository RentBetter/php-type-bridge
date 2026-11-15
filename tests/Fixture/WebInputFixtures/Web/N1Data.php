<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\WebInputFixtures\Web;

/**
 * One of seven that each name the other six.
 *
 * @phpstan-type _self = array{
 *     n2?: N2Data,
 *     n3?: N3Data,
 *     n4?: N4Data,
 *     n5?: N5Data,
 *     n6?: N6Data,
 *     n7?: N7Data,
 * }
 */
final class N1Data
{
    public ?string $n2 = null;

    public ?string $n3 = null;

    public ?string $n4 = null;

    public ?string $n5 = null;

    public ?string $n6 = null;

    public ?string $n7 = null;
}
