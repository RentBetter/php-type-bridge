<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

/**
 * A library's base whose property names something the library can't see: no class, no alias.
 */
abstract class AbstractWidgetsResponse
{
    /** @var list<Widget> */
    public array $widgets = [];
}
