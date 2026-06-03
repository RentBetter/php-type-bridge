<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Config;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Config\ImportStrategy;
use PTGS\TypeBridge\Config\OutputStructure;
use PTGS\TypeBridge\Config\SegmentCase;
use RuntimeException;

final class OutputStructureTest extends TestCase
{
    public function test_defaults_reproduce_historical_output(): void
    {
        $structure = OutputStructure::fromArray([]);

        self::assertSame(SegmentCase::AsIs, $structure->segmentCase);
        self::assertNull($structure->rootModule);
        self::assertSame(ImportStrategy::RelativeSibling, $structure->importStrategy);
        self::assertNull($structure->aliasBase);
        self::assertSame('// AUTO-GENERATED. DO NOT EDIT.', $structure->header);
    }

    public function test_parses_a_full_structure(): void
    {
        $structure = OutputStructure::fromArray([
            'segmentCase' => 'perSegmentLcFirst',
            'rootModule' => 'genTypes.ts',
            'importStrategy' => 'alias',
            'aliasBase' => '@/api/genTypes',
            'header' => '// custom header',
        ]);

        self::assertSame(SegmentCase::PerSegmentLcFirst, $structure->segmentCase);
        self::assertSame('genTypes.ts', $structure->rootModule);
        self::assertSame(ImportStrategy::Alias, $structure->importStrategy);
        self::assertSame('@/api/genTypes', $structure->aliasBase);
        self::assertSame('// custom header', $structure->header);
    }

    public function test_alias_strategy_requires_alias_base(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"aliasBase" is required when "importStrategy" is "alias"');

        OutputStructure::fromArray(['importStrategy' => 'alias']);
    }

    public function test_rejects_unknown_keys(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown TypeBridge output config keys: bogus');

        OutputStructure::fromArray(['bogus' => 'x']);
    }

    public function test_rejects_invalid_segment_case(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"segmentCase" must be one of: asIs, perSegmentLcFirst');

        OutputStructure::fromArray(['segmentCase' => 'nope']);
    }
}
