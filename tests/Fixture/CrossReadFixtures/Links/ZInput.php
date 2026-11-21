<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CrossReadFixtures\Links;

/**
 * Reads the pair from both: NData, kept for `n`, is read again inside KData for `k`.
 *
 * @phpstan-type _self = array{
 *     n?: NData,
 *     k?: KData,
 * }
 */
final class ZInput
{
}
