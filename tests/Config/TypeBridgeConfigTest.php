<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Config;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use RuntimeException;

final class TypeBridgeConfigTest extends TestCase
{
    public function test_defaults_when_array_is_empty(): void
    {
        $config = TypeBridgeConfig::fromArray([]);

        self::assertSame('', $config->typescript->interfacePrefix);
        self::assertSame([], $config->preserveNull);
    }

    public function test_parses_typescript_and_preserve_null_sections(): void
    {
        $config = TypeBridgeConfig::fromArray([
            'typescript' => [
                'interfacePrefix' => 'I',
            ],
            'preserveNull' => [
                'IProject.archivedAt',
                'IProjectStage.parentId',
            ],
        ]);

        self::assertSame('I', $config->typescript->interfacePrefix);
        self::assertSame(['IProject.archivedAt', 'IProjectStage.parentId'], $config->preserveNull);
        self::assertTrue($config->isPreserveNull('IProject', 'archivedAt'));
        self::assertFalse($config->isPreserveNull('IProject', 'unrelated'));
    }

    public function test_parses_output_structure_section(): void
    {
        $config = TypeBridgeConfig::fromArray([
            'output' => [
                'segmentCase' => 'perSegmentLcFirst',
                'importStrategy' => 'alias',
                'aliasBase' => '@/api/genTypes',
            ],
        ]);

        self::assertSame(\PTGS\TypeBridge\Config\SegmentCase::PerSegmentLcFirst, $config->output->segmentCase);
        self::assertSame('@/api/genTypes', $config->output->aliasBase);
    }

    public function test_rejects_unknown_top_level_keys(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown TypeBridge config keys: bogus');

        TypeBridgeConfig::fromArray(['bogus' => []]);
    }

    public function test_rejects_non_list_preserve_null(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('preserveNull');

        TypeBridgeConfig::fromArray(['preserveNull' => ['key' => 'value']]);
    }

    public function test_rejects_preserve_null_entries_without_dot_separator(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ShapeName.fieldName');

        TypeBridgeConfig::fromArray(['preserveNull' => ['no_dot']]);
    }

    public function test_rejects_non_string_preserve_null_entries(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('preserveNull');

        TypeBridgeConfig::fromArray(['preserveNull' => [123]]);
    }

    public function test_from_file_loads_php_array(): void
    {
        $path = sys_get_temp_dir() . '/type-bridge-config-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($path, <<<'PHP'
<?php

return [
    'typescript' => ['interfacePrefix' => 'I'],
    'preserveNull' => ['IFoo.bar'],
];
PHP);

        try {
            $config = TypeBridgeConfig::fromFile($path);
            self::assertSame('I', $config->typescript->interfacePrefix);
            self::assertSame(['IFoo.bar'], $config->preserveNull);
        } finally {
            unlink($path);
        }
    }

    public function test_from_file_errors_when_path_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('was not found');

        TypeBridgeConfig::fromFile('/nonexistent/path/config.php');
    }
}
