<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\TraitSelfBindingFixtures\Notes;

/**
 * @template T
 */
trait Inner
{
    /** @var T */
    public string $kind = 'a';
}
