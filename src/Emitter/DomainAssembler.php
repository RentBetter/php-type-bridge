<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Sorting\DeclaredOrder;
use PTGS\TypeBridge\Sorting\SortStrategy;

/**
 * Assembles a single module's TypeScript from the header, import lines, and the
 * ordered {@see EmittedBlock}s contributed by emitters.
 *
 * Blocks are grouped by {@see EmittedBlock::$order}; within a group the injected
 * {@see SortStrategy} orders them by their sort key. Each distinct non-null banner
 * is emitted once, before the first block that carries it. Parts are separated by
 * a single blank line.
 */
final readonly class DomainAssembler
{
    public function __construct(
        private string $header = '// AUTO-GENERATED. DO NOT EDIT.',
        private SortStrategy $declarationSort = new DeclaredOrder(),
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

        $emittedBanners = [];
        foreach ($this->orderBlocks($blocks) as $block) {
            $code = $block->code;
            if (null !== $block->banner && !isset($emittedBanners[$block->banner])) {
                $code = $block->banner . "\n" . $code;
                $emittedBanners[$block->banner] = true;
            }
            $parts[] = $code;
        }

        return rtrim(implode("\n\n", $parts)) . "\n";
    }

    /**
     * @param list<EmittedBlock> $blocks
     * @return list<EmittedBlock>
     */
    private function orderBlocks(array $blocks): array
    {
        /** @var array<int, list<EmittedBlock>> $groups */
        $groups = [];
        foreach ($blocks as $block) {
            $groups[$block->order][] = $block;
        }
        ksort($groups);

        $ordered = [];
        foreach ($groups as $group) {
            $sorted = $this->declarationSort->sort($group, static fn (EmittedBlock $block): ?string => $block->sortKey);
            foreach ($sorted as $block) {
                $ordered[] = $block;
            }
        }

        return $ordered;
    }
}
