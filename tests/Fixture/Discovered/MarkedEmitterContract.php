<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Discovered;

use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Emitter\TypeEmitter;

/**
 * An interface carrying the attribute: there is nothing of it to make.
 */
#[AsTypeBridgeEmitter('contract')]
interface MarkedEmitterContract extends TypeEmitter
{
}
