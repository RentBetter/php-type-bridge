<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Command;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Command\GenerateTypesCommand;
use PTGS\TypeBridge\Tests\Fixture\FixtureProject;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateTypesCommandTest extends TestCase
{
    /** @var list<string> the directories the test has written under the system's temp dir */
    private array $workspaces = [];

    protected function tearDown(): void
    {
        foreach ($this->workspaces as $workspace) {
            if (!is_dir($workspace)) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workspace, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($workspace);
        }
    }

    public function test_it_loads_typescript_naming_from_a_config_file(): void
    {
        $workspace = $this->workspace();
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
    'preserveNull' => [
        'ProjectAdminView.internalNotes',
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
        self::assertStringContainsString('export type CreateProjectPayload = ICreateProjectRequestData;', $projects);
    }

    /**
     * The attribute says `/ping`; the fixture's routes.yaml prefixes the namespace with `/api`,
     * so the constant carries the path Symfony serves.
     */
    public function test_an_endpoint_constant_takes_the_served_path_from_the_routing_config(): void
    {
        $outputDir = $this->workspace();
        $fixture = __DIR__ . '/../Fixture/RoutedMcpFixtures';

        $tester = new CommandTester(new GenerateTypesCommand());
        $exitCode = $tester->execute([
            'source' => $fixture,
            'output' => $outputDir,
            '--config' => $fixture . '/type-bridge.php',
        ]);

        self::assertSame(0, $exitCode);

        $ping = file_get_contents($outputDir . '/Ping/genTypes.ts');
        self::assertNotFalse($ping);
        self::assertStringContainsString("export const Ping: Endpoint<PingEndpointMap> = {\n  method: 'GET',\n  path: '/api/ping',\n};", $ping);
    }

    public function test_a_tool_production_doesnt_serve_still_has_its_typescript(): void
    {
        // Its #[Route] is confined to dev: no tool of it is served in prod, which the MCP manifest
        // says; its Endpoint is described by that route all the same.
        $outputDir = $this->workspace();
        $tester = new CommandTester(new GenerateTypesCommand());

        self::assertSame(0, $tester->execute(['source' => __DIR__ . '/../Fixture/DevRoutedApp', 'output' => $outputDir]));
        self::assertStringContainsString("path: '/debug/notes/{id}'", (string) file_get_contents($outputDir . '/Notes/genTypes.ts'));
    }

    public function test_a_class_this_install_cant_load_is_no_part_of_what_it_generates(): void
    {
        // DevOnlyRule's declaration throws without its require-dev interface: the command runs on,
        // as the collectors do, and emits nothing of it.
        $outputDir = $this->workspace();
        $tester = new CommandTester(new GenerateTypesCommand());

        self::assertSame(0, $tester->execute(['source' => __DIR__ . '/../Fixture/UnloadableFixtures', 'output' => $outputDir]));
    }

    /**
     * Its first #[Route] is confined to `dev`: where the application is deployed in dev, that is the
     * route it is described by; in prod, the second.
     */
    public function test_an_endpoint_is_described_by_its_route_in_the_configs_env(): void
    {
        $fixture = __DIR__ . '/../Fixture/TwiceRoutedApp';
        foreach (['dev' => "path: '/dev/notes/{code}'", 'prod' => "path: '/env/notes/{id}'"] as $env => $path) {
            $workspace = $this->workspace();
            mkdir($workspace);
            file_put_contents($workspace . '/type-bridge.php', "<?php\n\nreturn ['routingEnv' => '" . $env . "'];\n");

            $tester = new CommandTester(new GenerateTypesCommand());
            self::assertSame(0, $tester->execute(['source' => $fixture, 'output' => $workspace . '/out', '--config' => $workspace . '/type-bridge.php']));

            $notes = file_get_contents($workspace . '/out/Notes/genTypes.ts');
            self::assertNotFalse($notes);
            self::assertMatchesRegularExpression('/export const ShowEnvNote\b[^;]*' . preg_quote($path, '/') . '/', $notes, $env);
        }
    }

    /**
     * A directory under the system's temp dir, removed when the test is done.
     */
    private function workspace(): string
    {
        return $this->workspaces[] = sys_get_temp_dir() . '/type-bridge-command-' . bin2hex(random_bytes(6));
    }
}
