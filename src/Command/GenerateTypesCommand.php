<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Command;

use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Emitter\DomainAssembler;
use PTGS\TypeBridge\Emitter\EmitterRegistry;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'typebridge:generate',
    description: 'Generate TypeScript API contract types from PHP response contracts.',
)]
final class GenerateTypesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::OPTIONAL, 'Source directory to scan', 'src')
            ->addArgument('output', InputArgument::OPTIONAL, 'Directory to write generated TypeScript files', 'src')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Optional PHP config file that returns TypeBridge settings')
            ->addOption('discovered-only', null, InputOption::VALUE_NONE, 'Skip the built-in @phpstan-type / response / endpoint conventions; run only Discovered-mode emitters');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $sourceDir */
        $sourceDir = $input->getArgument('source');
        /** @var string $outputDir */
        $outputDir = $input->getArgument('output');
        /** @var string|null $configFile */
        $configFile = $input->getOption('config');
        $discoveredOnly = (bool) $input->getOption('discovered-only');

        $config = null !== $configFile ? TypeBridgeConfig::fromFile($configFile) : new TypeBridgeConfig();

        $enumResolver = new EnumResolver();
        $candidateClasses = array_keys((new PhpFileClassLocator())->classesIn($sourceDir));
        $registry = EmitterRegistry::fromAttributeScan($candidateClasses);

        $mapper = new DomainMapper($outputDir, $config->output);
        $emitter = new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: $mapper,
            naming: $config->typescript,
            preserveNull: $config->preserveNull,
            registry: $registry,
            assembler: new DomainAssembler($config->output->header, $config->output->declarationOrder->strategy()),
            importSort: $config->output->importOrder->strategy(),
        );

        $files = [];
        if (!$discoveredOnly) {
            $enumResolver->scanDirectory($sourceDir);
            $responseCollector = new ResponseClassCollector();
            $files = $emitter->emit(
                (new PhpDocTypeCollector())->collect($sourceDir),
                $responseCollector->collect($sourceDir),
                (new EndpointContractCollector(requirementTypes: $config->requirementTypes))->collect($sourceDir, $responseCollector->collectIndex($sourceDir)),
            );
        }

        $files = array_merge($files, $emitter->emitDiscovered($candidateClasses));

        foreach ($files as $domain => $typescript) {
            $path = '' === $domain ? $mapper->getRootOutputPath() : $mapper->getOutputPath($domain);
            $directory = \dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, recursive: true);
            }
            file_put_contents($path, $typescript);
            $io->writeln($path);
        }

        return Command::SUCCESS;
    }
}
