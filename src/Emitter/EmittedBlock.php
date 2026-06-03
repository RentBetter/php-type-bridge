<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

/**
 * A single emitted TypeScript declaration block.
 *
 * Blocks are ordered across all emitters in a domain by {@see self::$order}; the
 * assembler emits each distinct non-null {@see self::$banner} once, before the
 * first block that carries it. {@see self::$code} is the full declaration text
 * (possibly multi-line) with no surrounding blank lines.
 */
final readonly class EmittedBlock
{
    public function __construct(
        public int $order,
        public ?string $banner,
        public string $code,
    ) {}
}
