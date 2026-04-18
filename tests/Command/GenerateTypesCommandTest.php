<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Command;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Command\GenerateTypesCommand;
use PTGS\TypeBridge\Tests\Fixture\FixtureProject;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateTypesCommandTest extends TestCase
{
    public function test_it_loads_typescript_naming_from_a_config_file(): void
    {
        $workspace = sys_get_temp_dir() . '/type-bridge-command-' . bin2hex(random_bytes(6));
        mkdir($workspace, recursive: true);

        $configFile = $workspace . '/type-bridge.php';
        file_put_contents($configFile, <<<'PHP'
<?php

return [
    'typescript' => [
        'interfacePrefix' => 'I',
        'enumValueSuffix' => 'Id',
        'enumShapeSuffix' => '',
        'bodyAliasSuffix' => 'Payload',
    ],
];
PHP);

        $outputDir = $workspace . '/generated';

        $tester = new CommandTester(new GenerateTypesCommand());
        $exitCode = $tester->execute([
            'source' => FixtureProject::srcDir(),
            'output' => $outputDir,
            '--config' => $configFile,
        ]);

        self::assertSame(0, $exitCode);

        $projects = file_get_contents($outputDir . '/Projects/genTypes.ts');
        self::assertNotFalse($projects);
        self::assertStringContainsString("export type ProjectStatusId = 'draft' | 'active';", $projects);
        self::assertStringContainsString('export interface IProjectStatus {', $projects);
        self::assertStringContainsString('export type ProjectCreatePayload = ICreateProjectRequestData;', $projects);
    }
}
