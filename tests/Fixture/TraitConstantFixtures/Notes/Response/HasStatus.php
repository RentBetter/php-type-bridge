<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\TraitConstantFixtures\Notes\Response;

/**
 * `self::` in a trait is the class using it, as PHP and PHPStan read it: the constants live there.
 */
trait HasStatus
{
    /** @var self::STATUS_* */
    public string $status;
}
