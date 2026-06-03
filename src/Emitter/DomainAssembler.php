<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

/**
 * Assembles a single module's TypeScript from the header, import lines, and the
 * ordered {@see EmittedBlock}s contributed by emitters.
 *
 * Blocks are ordered by {@see EmittedBlock::$order} (stably, so within an order
 * the emitter contribution order is preserved). Each distinct non-null banner is
 * emitted once, immediately before the first block that carries it. Parts are
 * separated by a single blank line.
 */
final readonly class DomainAssembler
{
    public function __construct(
        private string $header = '// AUTO-GENERATED. DO NOT EDIT.',
    ) {}

    /**
     * @param list<string>       $importLines fully-rendered `import type { ... } from '...';` lines
     * @param list<EmittedBlock> $blocks
     */
    public function assemble(array $importLines, array $blocks): string
    {
        $parts = [$this->header];

        if ([] !== $importLines) {
            $parts[] = implode("\n", $importLines);
        }

        $ordered = $blocks;
        usort($ordered, static fn(EmittedBlock $a, EmittedBlock $b): int => $a->order <=> $b->order);

        $emittedBanners = [];
        foreach ($ordered as $block) {
            $code = $block->code;
            if (null !== $block->banner && !isset($emittedBanners[$block->banner])) {
                $code = $block->banner . "\n" . $code;
                $emittedBanners[$block->banner] = true;
            }
            $parts[] = $code;
        }

        return rtrim(implode("\n\n", $parts)) . "\n";
    }
}
