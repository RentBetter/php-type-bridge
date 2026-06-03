<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

/**
 * A {@see TypeEmitter} together with the convention metadata it was registered
 * with (from #[AsTypeBridgeEmitter] or the built-in defaults).
 */
final readonly class RegisteredEmitter
{
    public function __construct(
        public TypeEmitter $emitter,
        public string $convention,
        public int $priority,
        public EmitMode $mode,
    ) {}
}
