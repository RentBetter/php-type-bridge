<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Command;

use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
            ->addArgument('output', InputArgument::OPTIONAL, 'Directory to write generated TypeScript files', 'src');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $sourceDir */
        $sourceDir = $input->getArgument('source');
        /** @var string $outputDir */
        $outputDir = $input->getArgument('output');

        $typeCollector = new PhpDocTypeCollector();
        $responseCollector = new ResponseClassCollector();
        $endpointCollector = new EndpointContractCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory($sourceDir);

        $domains = $typeCollector->collect($sourceDir);
        $responses = $responseCollector->collect($sourceDir);
        $contracts = $endpointCollector->collect($sourceDir, $responseCollector->collectIndex($sourceDir));

        $emitter = new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper($outputDir),
        );

        foreach ($emitter->emit($domains, $responses, $contracts) as $domain => $typescript) {
            $path = (new DomainMapper($outputDir))->getOutputPath($domain);
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
