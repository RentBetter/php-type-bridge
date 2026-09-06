<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Command;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Shape\ShapeRenderer;
use PTGS\TypeBridge\Shape\ShapeScaffolder;
use PTGS\TypeBridge\Support\FormTypeInspector;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Scaffolds the `@phpstan-type _self` shapes that #[ApiRequest] demands of input DTOs.
 *
 * Adopting the request half of the contract fails closed: the collector refuses any input class
 * without a `_self`, so a project turning it on faces every input DTO at once. Writing those by
 * hand is where a migration quietly goes wrong — a shape that misdescribes the wire is published
 * to every client and believed.
 *
 * Prints by default and writes only with --write, because a generated contract deserves to be
 * read before it is committed.
 */
#[AsCommand(
    name: 'typebridge:shapes',
    description: 'Scaffold @phpstan-type _self shapes for the input DTOs behind #[ApiRequest].',
)]
final class ScaffoldShapesCommand extends Command
{
    public function __construct(
        private readonly PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
        private readonly FormTypeInspector $formTypeInspector = new FormTypeInspector(),
        private readonly ShapeScaffolder $scaffolder = new ShapeScaffolder(),
        private readonly ShapeRenderer $renderer = new ShapeRenderer(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::OPTIONAL, 'Source directory to scan', 'src')
            ->addOption('write', null, InputOption::VALUE_NONE, 'Insert the shapes into the DTO files instead of printing them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $sourceDir */
        $sourceDir = $input->getArgument('source');
        $write = true === $input->getOption('write');

        $classFiles = $this->classLocator->classesIn($sourceDir);

        $scaffolded = 0;
        $skipped = 0;
        /** @var list<array{class: string, fields: list<string>}> $needsAttention */
        $needsAttention = [];

        foreach ($this->inputDataClasses($classFiles) as $dataClass => $fields) {
            $file = $classFiles[$dataClass] ?? null;
            if (null === $file) {
                $needsAttention[] = ['class' => $dataClass, 'fields' => ['not found under ' . $sourceDir]];

                continue;
            }

            $contents = file_get_contents($file);
            if (false === $contents) {
                continue;
            }

            if (isset($this->docHelper->extractPhpStanTypes($contents)['_self'])) {
                ++$skipped;

                continue;
            }

            ['shape' => $shape, 'unresolved' => $unresolved] = $this->scaffolder->scaffold($dataClass, $fields);

            if ([] !== $unresolved) {
                // Emitting a shape with a guessed field would publish a wrong contract. Name the
                // fields and leave the class alone.
                $needsAttention[] = ['class' => $dataClass, 'fields' => $unresolved];

                continue;
            }

            $block = $this->renderer->renderSelfDocBlock($shape);

            if ($write) {
                file_put_contents($file, $this->insertDocBlock($contents, $dataClass, $block));
            } else {
                $io->section($dataClass);
                $io->writeln($block);
            }

            ++$scaffolded;
        }

        if ([] !== $needsAttention) {
            $io->warning('Needs a hand-written shape — a field could not be typed from the form or the DTO:');
            foreach ($needsAttention as $entry) {
                $io->writeln(\sprintf('  %s — %s', $entry['class'], implode(', ', $entry['fields'])));
            }
        }

        $io->success(\sprintf(
            '%d shape%s %s, %d already declared, %d need attention.',
            $scaffolded,
            1 === $scaffolded ? '' : 's',
            $write ? 'written' : 'scaffolded (re-run with --write to apply)',
            $skipped,
            \count($needsAttention),
        ));

        return Command::SUCCESS;
    }

    /**
     * Every input DTO reachable from an #[ApiRequest], mapped to the form fields that bind it.
     *
     * @param array<string, string> $classFiles
     *
     * @return array<string, list<\PTGS\TypeBridge\Model\CollectedFormField>>
     */
    private function inputDataClasses(array $classFiles): array
    {
        $found = [];

        foreach (array_keys($classFiles) as $className) {
            if (!$this->classLocator->isLoadable($className)) {
                continue;
            }

            foreach ((new ReflectionClass($className))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(ApiRequest::class) as $attribute) {
                    $request = $attribute->newInstance();
                    foreach ([$request->query, $request->body] as $formClass) {
                        if (null === $formClass) {
                            continue;
                        }

                        try {
                            $resolved = $this->formTypeInspector->inspect($formClass);
                        } catch (Throwable) {
                            // A form that cannot be built is a separate problem, and the contract
                            // rules already report it. Scaffolding should not also fall over.
                            continue;
                        }

                        if (null !== $resolved['dataClass']) {
                            $found[$resolved['dataClass']] = $resolved['fields'];
                        }
                    }
                }
            }
        }

        return $found;
    }

    private function insertDocBlock(string $contents, string $dataClass, string $block): string
    {
        $shortName = substr($dataClass, (int) strrpos($dataClass, '\\') + 1);
        $pattern = '/^((?:final\s+|readonly\s+|abstract\s+)*class\s+' . preg_quote($shortName, '/') . '\b)/m';

        return (string) preg_replace($pattern, $block . "\n" . '$1', $contents, 1);
    }
}
