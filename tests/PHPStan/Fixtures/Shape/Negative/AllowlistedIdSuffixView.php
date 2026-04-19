<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative;

/**
 * Fixture for allowlist test — `stripeCustomerId` is allowlisted, `badId` is not.
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     stripeCustomerId: string,
 *     badId: string,
 * }
 */
final class AllowlistedIdSuffixView
{
}
