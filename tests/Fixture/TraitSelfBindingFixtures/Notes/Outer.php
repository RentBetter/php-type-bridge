<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\TraitSelfBindingFixtures\Notes;

/**
 * Binds Inner's param to constants of `self`: the class using this trait, as PHP and PHPStan read it.
 */
trait Outer
{
    /** @use Inner<self::KIND_*> */
    use Inner;
}
