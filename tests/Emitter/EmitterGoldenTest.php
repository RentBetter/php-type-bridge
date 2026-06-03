<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Tests\Fixture\AliasFixtureProject;
use PTGS\TypeBridge\Tests\Fixture\FixtureProject;

/**
 * Full-file golden lock on the emitter's output.
 *
 * The other emitter tests use assertStringContainsString, which cannot catch
 * section reordering, duplicated banners, or stray whitespace. This test asserts
 * the entire emitted string per domain — and the domain set itself — so the
 * emitter-framework refactor (which reshuffles assembly internally) is provably
 * output-preserving.
 *
 * To re-capture after an INTENTIONAL output change:
 *   UPDATE_GOLDENS=1 vendor/bin/phpunit --filter EmitterGoldenTest
 */
final class EmitterGoldenTest extends TestCase
{
    public function test_fixture_project_output_matches_golden(): void
    {
        $this->assertMatchesGolden('FixtureProject', $this->emitFixtureProject());
    }

    public function test_alias_fixture_project_output_matches_golden(): void
    {
        $this->assertMatchesGolden('AliasFixtureProject', $this->emitAliasFixtureProject());
    }

    /**
     * @return array<string, string>
     */
    private function emitFixtureProject(): array
    {
        $srcDir = FixtureProject::srcDir();
        $typeDomains = (new PhpDocTypeCollector())->collect($srcDir);
        $responseCollector = new ResponseClassCollector();
        $responseDomains = $responseCollector->collect($srcDir);
        $contracts = (new EndpointContractCollector())->collect($srcDir, $responseCollector->collectIndex($srcDir));

        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($srcDir);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
            preserveNull: ['ProjectAdminView.internalNotes'],
        ))->emit($typeDomains, $responseDomains, $contracts);
    }

    /**
     * @return array<string, string>
     */
    private function emitAliasFixtureProject(): array
    {
        $srcDir = AliasFixtureProject::srcDir();
        $responseCollector = new ResponseClassCollector();
        $responseDomains = $responseCollector->collect($srcDir);
        $contracts = (new EndpointContractCollector())->collect($srcDir, $responseCollector->collectIndex($srcDir));

        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($srcDir);

        return (new TypeScriptEmitter($enumResolver, new DomainMapper('/tmp/type-bridge-output')))
            ->emit([], $responseDomains, $contracts);
    }

    /**
     * @param array<string, string> $output
     */
    private function assertMatchesGolden(string $project, array $output): void
    {
        ksort($output);
        $projectDir = __DIR__ . '/../Fixture/golden/' . $project;

        if (false !== getenv('UPDATE_GOLDENS')) {
            if (!is_dir($projectDir)) {
                mkdir($projectDir, recursive: true);
            }
            foreach ($output as $domain => $typescript) {
                file_put_contents($projectDir . '/' . $domain . '.genTypes.ts', $typescript);
            }
            self::markTestSkipped(\sprintf('Captured %d golden file(s) for %s.', \count($output), $project));
        }

        self::assertDirectoryExists($projectDir, \sprintf('No goldens for %s. Run with UPDATE_GOLDENS=1 to capture.', $project));

        $goldenFiles = glob($projectDir . '/*.genTypes.ts') ?: [];
        $expectedDomains = array_map(
            static fn(string $file): string => basename($file, '.genTypes.ts'),
            $goldenFiles,
        );
        sort($expectedDomains);

        self::assertSame(
            $expectedDomains,
            array_keys($output),
            \sprintf('Domain set drift for %s (a domain file was added or dropped).', $project),
        );

        foreach ($output as $domain => $typescript) {
            self::assertSame(
                file_get_contents($projectDir . '/' . $domain . '.genTypes.ts'),
                $typescript,
                \sprintf('Output drift for %s/%s.', $project, $domain),
            );
        }
    }
}
