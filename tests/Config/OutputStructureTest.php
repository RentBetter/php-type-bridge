<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Config;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Config\ImportStrategy;
use PTGS\TypeBridge\Config\OutputStructure;
use PTGS\TypeBridge\Config\SegmentCase;
use PTGS\TypeBridge\Config\SortOrder;
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
        self::assertSame(SortOrder::Declared, $structure->declarationOrder);
        self::assertSame(SortOrder::Name, $structure->importOrder);
    }

    public function test_parses_a_full_structure(): void
    {
        $structure = OutputStructure::fromArray([
            'segmentCase' => 'perSegmentLcFirst',
            'rootModule' => 'genTypes.ts',
            'importStrategy' => 'alias',
            'aliasBase' => '@/api/genTypes',
            'header' => '// custom header',
            'declarationOrder' => 'name',
            'importOrder' => 'declared',
        ]);

        self::assertSame(SegmentCase::PerSegmentLcFirst, $structure->segmentCase);
        self::assertSame('genTypes.ts', $structure->rootModule);
        self::assertSame(ImportStrategy::Alias, $structure->importStrategy);
        self::assertSame('@/api/genTypes', $structure->aliasBase);
        self::assertSame('// custom header', $structure->header);
        self::assertSame(SortOrder::Name, $structure->declarationOrder);
        self::assertSame(SortOrder::Declared, $structure->importOrder);
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

    public function test_domain_depth_defaults_to_the_top_directory(): void
    {
        self::assertSame(1, OutputStructure::fromArray([])->domainDepth);
        self::assertSame(2, OutputStructure::fromArray(['domainDepth' => 2])->domainDepth);
    }

    public function test_rejects_a_domain_depth_below_one(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('domainDepth');

        OutputStructure::fromArray(['domainDepth' => 0]);
    }

    public function test_root_sources_are_paths_under_the_source_directory(): void
    {
        self::assertSame([], OutputStructure::fromArray([])->rootSources);
        self::assertSame(['_', 'Event/EntityRef.php'], OutputStructure::fromArray([
            'rootModule' => 'genTypes.ts',
            'rootSources' => ['_/', '/Event/EntityRef.php'],
        ])->rootSources);
    }

    public function test_root_sources_need_a_root_module(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rootModule');

        OutputStructure::fromArray(['rootSources' => ['_']]);
    }

    public function test_rejects_a_root_source_that_is_not_a_path(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('rootSources');

        OutputStructure::fromArray(['rootModule' => 'genTypes.ts', 'rootSources' => ['_', '']]);
    }
}
