<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

use PTGS\TypeBridge\Tests\Fixture\LibraryBaseOwnedFixtures\Notes\View\OwnerView;

/**
 * A library's base whose property names a class the source tree gives a type: that type, as
 * this file's `use` reads the name.
 */
abstract class AbstractOwnedResponse
{
    /** @var OwnerView */
    public OwnerView $owner;
}
