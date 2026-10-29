<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\AliasCliqueFixtures\Notes;

/**
 * Aliases each naming every other: read through on every path but a bounded number.
 *
 * @phpstan-type A1 = array{a2?: A2, a3?: A3, a4?: A4, a5?: A5, a6?: A6, a7?: A7, a8?: A8, a9?: A9, a10?: A10}
 * @phpstan-type A2 = array{a1?: A1, a3?: A3, a4?: A4, a5?: A5, a6?: A6, a7?: A7, a8?: A8, a9?: A9, a10?: A10}
 * @phpstan-type A3 = array{a1?: A1, a2?: A2, a4?: A4, a5?: A5, a6?: A6, a7?: A7, a8?: A8, a9?: A9, a10?: A10}
 * @phpstan-type A4 = array{a1?: A1, a2?: A2, a3?: A3, a5?: A5, a6?: A6, a7?: A7, a8?: A8, a9?: A9, a10?: A10}
 * @phpstan-type A5 = array{a1?: A1, a2?: A2, a3?: A3, a4?: A4, a6?: A6, a7?: A7, a8?: A8, a9?: A9, a10?: A10}
 * @phpstan-type A6 = array{a1?: A1, a2?: A2, a3?: A3, a4?: A4, a5?: A5, a7?: A7, a8?: A8, a9?: A9, a10?: A10}
 * @phpstan-type A7 = array{a1?: A1, a2?: A2, a3?: A3, a4?: A4, a5?: A5, a6?: A6, a8?: A8, a9?: A9, a10?: A10}
 * @phpstan-type A8 = array{a1?: A1, a2?: A2, a3?: A3, a4?: A4, a5?: A5, a6?: A6, a7?: A7, a9?: A9, a10?: A10}
 * @phpstan-type A9 = array{a1?: A1, a2?: A2, a3?: A3, a4?: A4, a5?: A5, a6?: A6, a7?: A7, a8?: A8, a10?: A10}
 * @phpstan-type A10 = array{a1?: A1, a2?: A2, a3?: A3, a4?: A4, a5?: A5, a6?: A6, a7?: A7, a8?: A8, a9?: A9}
 * @phpstan-type _self = array{
 *     a1?: A1,
 * }
 */
final class AliasCliqueData
{
}
