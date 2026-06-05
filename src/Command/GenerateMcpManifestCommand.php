<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Command;

use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Mcp\McpManifestBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'typebridge:mcp',
    description: 'Generate an MCP tool manifest (tools.json) from #[McpTool] endpoint contracts.',
)]
final class GenerateMcpManifestCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::OPTIONAL, 'Source directory to scan', 'src')
            ->addArgument('output', InputArgument::OPTIONAL, 'File to write the manifest to', 'tools.json')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Optional PHP config file that returns TypeBridge settings');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $sourceDir */
        $sourceDir = $input->getArgument('source');
        /** @var string $outputFile */
        $outputFile = $input->getArgument('output');
        /** @var string|null $configFile */
        $configFile = $input->getOption('config');

        $config = null !== $configFile ? TypeBridgeConfig::fromFile($configFile) : new TypeBridgeConfig();

        $responseCollector = new ResponseClassCollector();
        $contracts = (new EndpointContractCollector(requirementTypes: $config->requirementTypes))
            ->collect($sourceDir, $responseCollector->collectIndex($sourceDir));

        $manifest = (new McpManifestBuilder())->build($contracts);

        $json = json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);

        $directory = \dirname($outputFile);
        if (!is_dir($directory)) {
            mkdir($directory, recursive: true);
        }
        file_put_contents($outputFile, $json . "\n");

        $toolCount = \count($manifest['tools']);
        $io->success(\sprintf('Wrote %d MCP tool%s to %s', $toolCount, 1 === $toolCount ? '' : 's', $outputFile));

        return Command::SUCCESS;
    }
}
