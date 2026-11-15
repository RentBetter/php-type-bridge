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
 *
 * {@see self::$sortKey} is the name used when the output's declarationOrder is
 * SortOrder::Name; blocks within an order group are sorted by it only when every
 * block in that group provides one (otherwise declared order is preserved).
 *
 * {@see self::$declares} are the names the block declares at the module's top level —
 * `Record` for `export interface Record { … }`. A discovered emitter states them, as its
 * code names them as it is: no name it states may be one its module declares or imports,
 * another discovered emitter's, or a TypeScript global the module's code uses, and one
 * that is fails generation, naming both. What a block doesn't state is held to nothing.
 */
final readonly class EmittedBlock
{
    /**
     * @param list<string> $declares the names the block declares at the module's top level
     */
    public function __construct(
        public int $order,
        public ?string $banner,
        public string $code,
        public ?string $sortKey = null,
        public array $declares = [],
    ) {}
}
