<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryBaseLib;

/**
 * A library's own enum: outside the source tree, so never emitted.
 */
enum Status: string
{
    case Live = 'live';
    case Gone = 'gone';
}
