<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IdOfFixtures\Marked\View;

/**
 * Sits in the same domain the id union is published into, so it must reference the symbol
 * without importing it from itself.
 *
 * @phpstan-type _self = array{
 *     status: id-of<GammaStatus>,
 * }
 */
final class LocalView
{
}
