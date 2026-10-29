<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GenericBaseFixtures\Common\Response;

/**
 * Its params in PHPStan's own spellings.
 *
 * @phpstan-template K
 * @template-covariant V
 */
abstract class AbstractPairResponse
{
    /** @var K */
    public mixed $key = null;

    /** @var V */
    public mixed $value = null;
}
