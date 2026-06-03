<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Attribute;

use Attribute;
use PTGS\TypeBridge\Emitter\EmitMode;

/**
 * Registers a {@see \PTGS\TypeBridge\Emitter\TypeEmitter} under a named convention.
 *
 * Discovered by {@see \PTGS\TypeBridge\Emitter\EmitterRegistry::fromAttributeScan()}
 * so a consuming application can contribute emitters without modifying TypeBridge.
 * When two emitters both claim a class, the higher priority wins; an equal-priority
 * tie is a hard error.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTypeBridgeEmitter
{
    public function __construct(
        public string $convention,
        public int $priority = 0,
        public EmitMode $mode = EmitMode::Referenced,
    ) {}
}
