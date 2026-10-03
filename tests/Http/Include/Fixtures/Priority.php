<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Include\Fixtures;

/**
 * A plain backed enum: sent as its value, whichever format the request asks for.
 */
enum Priority: int
{
    case Low = 1;
    case High = 2;
}
