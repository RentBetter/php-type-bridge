<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use PTGS\TypeBridge\Attribute\ApiRequest;
use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedEndpointRequest;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\FormTypeInspector;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class EndpointContractCollector
{
    public function __construct(
        private readonly PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
        private readonly DomainGuesser $domainGuesser = new DomainGuesser(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
        private readonly FormTypeInspector $formTypeInspector = new FormTypeInspector(),
    ) {}

    /**
     * @param array<class-string, CollectedApiResponseClass> $responseIndex
     * @return array<string, list<CollectedEndpointContract>>
     */
    public function collect(string $srcDir, array $responseIndex): array
    {
        $classFiles = $this->classLocator->classesIn($srcDir);
        $contracts = [];

        foreach ($classFiles as $className => $file) {
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                foreach ($method->getAttributes(ApiResponses::class) as $attribute) {
                    /** @var ApiResponses $instance */
                    $instance = $attribute->newInstance();

                    $responses = [];
                    foreach ($instance->responses as $responseClass) {
                        if (!isset($responseIndex[$responseClass])) {
                            throw new RuntimeException(\sprintf(
                                'Endpoint "%s::%s" references unknown response class "%s".',
                                $className,
                                $method->getName(),
                                $responseClass,
                            ));
                        }

                        $responses[] = $responseIndex[$responseClass];
                    }

                    $domain = $this->domainGuesser->guess($srcDir, $file);
                    $contracts[$domain] ??= [];
                    $contracts[$domain][] = new CollectedEndpointContract(
                        name: $this->endpointName($reflection->getShortName(), $method->getName()),
                        domain: $domain,
                        controllerClass: $className,
                        methodName: $method->getName(),
                        responses: $responses,
                        request: $this->resolveRequestContract($method, $srcDir, $classFiles),
                    );
                }
            }
        }

        ksort($contracts);

        return $contracts;
    }

    private function endpointName(string $controllerShortName, string $methodName): string
    {
        $base = preg_replace('/Controller$/', '', $controllerShortName) ?: $controllerShortName;

        return $base . ucfirst($methodName);
    }

    /**
     * @param array<string, string> $classFiles
     */
    private function resolveRequestContract(ReflectionMethod $method, string $srcDir, array $classFiles): ?CollectedEndpointRequest
    {
        $attributes = $method->getAttributes(ApiRequest::class);
        if ([] === $attributes) {
            return null;
        }

        /** @var ApiRequest $request */
        $request = $attributes[0]->newInstance();

        $collected = new CollectedEndpointRequest(
            query: null !== $request->query ? $this->resolveFormClass($request->query, $srcDir, $classFiles) : null,
            body: null !== $request->body ? $this->resolveFormClass($request->body, $srcDir, $classFiles) : null,
            path: null !== $request->path ? $this->resolveInputReference(null, $request->path, $srcDir, $classFiles) : null,
        );

        return $collected->hasAnyInput() ? $collected : null;
    }

    /**
     * @param array<string, string> $classFiles
     * @param class-string<\PTGS\TypeBridge\Contract\ContractFormType<object>> $formClass
     */
    private function resolveFormClass(string $formClass, string $srcDir, array $classFiles): CollectedInputReference
    {
        if (!isset($classFiles[$formClass])) {
            throw new RuntimeException(\sprintf(
                'Endpoint form class "%s" was not found in "%s".',
                $formClass,
                $srcDir,
            ));
        }

        $resolvedForm = $this->formTypeInspector->inspect($formClass);
        if (null === $resolvedForm['dataClass']) {
            throw new RuntimeException(\sprintf(
                'Form "%s" must configure a non-null data_class for TypeBridge request contracts.',
                $formClass,
            ));
        }

        return $this->resolveInputReference(
            formClass: $formClass,
            ownerClass: $resolvedForm['dataClass'],
            srcDir: $srcDir,
            classFiles: $classFiles,
            fields: $resolvedForm['fields'],
        );
    }

    /**
     * @param array<string, string> $classFiles
     * @param class-string|null $formClass
     * @param class-string $ownerClass
     * @param list<\PTGS\TypeBridge\Model\CollectedFormField> $fields
     */
    private function resolveInputReference(?string $formClass, string $ownerClass, string $srcDir, array $classFiles, array $fields = []): CollectedInputReference
    {
        $file = $classFiles[$ownerClass] ?? null;
        if (null === $file) {
            throw new RuntimeException(\sprintf(
                'Input contract class "%s" was not found in "%s".',
                $ownerClass,
                $srcDir,
            ));
        }

        $content = file_get_contents($file);
        if (false === $content) {
            throw new RuntimeException(\sprintf(
                'Input contract class "%s" could not be read from "%s".',
                $ownerClass,
                $file,
            ));
        }

        $definitions = $this->docHelper->extractPhpStanTypes($content);
        if (!isset($definitions['_self'])) {
            throw new RuntimeException(\sprintf(
                'Input contract class "%s" must declare @phpstan-type _self.',
                $ownerClass,
            ));
        }

        return new CollectedInputReference(
            formClass: $formClass,
            ownerClass: $ownerClass,
            typeName: $this->emittedTypeName($ownerClass),
            domain: $this->domainGuesser->guess($srcDir, $file),
            fields: $fields,
        );
    }

    /**
     * Mirrors PhpDocTypeCollector::_self name emission for endpoint input references.
     *
     * @param class-string $ownerClass
     */
    private function emittedTypeName(string $ownerClass): string
    {
        $shortName = $this->shortName($ownerClass);
        if (enum_exists($ownerClass)) {
            return $shortName . 'Data';
        }

        return $shortName;
    }

    /**
     * @param class-string $className
     */
    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');
        if (false === $position) {
            return $className;
        }

        return substr($className, $position + 1);
    }
}
