<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Config\ImportStrategy;
use PTGS\TypeBridge\Config\OutputStructure;
use PTGS\TypeBridge\Config\SegmentCase;
use PTGS\TypeBridge\Support\DomainMapper;

final class DomainMapperTest extends TestCase
{
    public function test_default_structure_uses_verbatim_dirs_and_sibling_imports(): void
    {
        $mapper = new DomainMapper('/out');

        self::assertSame('/out/Projects/genTypes.ts', $mapper->getOutputPath('Projects'));
        self::assertSame('../Common/genTypes', $mapper->getRelativeImportPath('Projects', 'Common'));
    }

    public function test_per_segment_lcfirst_lowercases_each_namespace_segment(): void
    {
        $mapper = new DomainMapper('/out', new OutputStructure(segmentCase: SegmentCase::PerSegmentLcFirst));

        self::assertSame('/out/listings/channels/genTypes.ts', $mapper->getOutputPath('Listings\\Channels'));
        self::assertSame('/out/listings/channels/genTypes.ts', $mapper->getOutputPath('Listings/Channels'));
    }

    public function test_root_output_path_and_alias_import(): void
    {
        $mapper = new DomainMapper('/out', new OutputStructure(
            segmentCase: SegmentCase::PerSegmentLcFirst,
            rootModule: 'genTypes.ts',
            importStrategy: ImportStrategy::Alias,
            aliasBase: '@/api/genTypes',
        ));

        self::assertSame('/out/genTypes.ts', $mapper->getRootOutputPath());
        self::assertSame('@/api/genTypes', $mapper->getRootImportPath('Listings\\Channels'));
    }

    public function test_relative_root_import_reflects_domain_depth(): void
    {
        $mapper = new DomainMapper('/out', new OutputStructure(
            segmentCase: SegmentCase::PerSegmentLcFirst,
            rootModule: 'genTypes.ts',
        ));

        self::assertSame('../../genTypes', $mapper->getRootImportPath('Listings\\Channels'));
        self::assertSame('../genTypes', $mapper->getRootImportPath('Root'));
    }
}
