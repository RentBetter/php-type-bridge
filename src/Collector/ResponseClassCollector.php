<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use PTGS\TypeBridge\Support\ClassFileTypes;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;

final class ResponseClassCollector
{
    /** @var array<string, array<class-string, CollectedApiResponseClass>> by source directory */
    private array $indexes = [];

    /** @var array<string, array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>, domain: string|null}|null> by source directory and class */
    private array $typeContexts = [];

    private readonly ClassFileTypes $files;

    private readonly DomainGuesser $domainGuesser;

    private readonly PhpDocTypeHelper $docHelper;

    /**
     * @param array<string, string> $typeAliases the project-wide aliases, name => TypeScript type: a
     *        library's property may be typed with one, as PHPStan reads it in any file
     */
    public function __construct(
        private readonly StatusCodeResolver $statusCodeResolver = new StatusCodeResolver(),
        private readonly PhpDocShapeParser $parser = new PhpDocShapeParser(),
        ?PhpFileClassLocator $classLocator = null,
        ?DomainGuesser $domainGuesser = null,
        ?PhpDocTypeHelper $docHelper = null,
        ?ClassFileTypes $files = null,
        private readonly array $typeAliases = [],
    ) {
        $this->files = ClassFileTypes::sharedBy($files, $docHelper, $domainGuesser, $classLocator);
        $this->domainGuesser = $this->files->domainGuesser;
        $this->docHelper = $this->files->docHelper;
    }

    /**
     * @return array<string, list<CollectedApiResponseClass>>
     */
    public function collect(string $srcDir): array
    {
        $domains = [];
        foreach ($this->collectIndex($srcDir) as $response) {
            $domains[$response->domain] ??= [];
            $domains[$response->domain][] = $response;
        }

        ksort($domains);

        return $domains;
    }

    /**
     * Read once per source directory, however often it is asked for: a generation run asks for the
     * grouped and the indexed form both.
     *
     * @return array<class-string, CollectedApiResponseClass>
     */
    public function collectIndex(string $srcDir): array
    {
        return $this->indexes[$srcDir] ??= $this->readIndex($srcDir);
    }

    /**
     * @return array<class-string, CollectedApiResponseClass>
     */
    private function readIndex(string $srcDir): array
    {
        $classFiles = $this->files->classesIn($srcDir);
        $responses = [];

        foreach ($classFiles as $className => $file) {
            if (!$this->files->loadable($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract() || $reflection->isInterface() || !$reflection->implementsInterface(ApiResponse::class)) {
                continue;
            }

            $this->statusCodeResolver->assertResponseClass($reflection);
            $context = $this->typeContext($className, $file, $srcDir, $classFiles);
            if (null === $context) {
                continue;
            }

            $status = $this->statusCodeResolver->resolve($reflection);
            $properties = [];
            // A 204 is sent with no body at all: nothing it declares or inherits is published.
            foreach (204 === $status ? [] : $reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                // An inherited property is on the wire as much as the class's own — the response
                // subscriber writes every public property — so it is published too. Every property
                // is read in the file it is written in — the class's, a base class's, a trait's, a
                // library's — and converted in that file's scope, whatever another file imports
                // under the same names, and whichever module the response is in.
                $declaringClass = self::writtenIn($property);
                $declared = $this->typeContext($declaringClass, $classFiles[$declaringClass] ?? $this->fileOf($declaringClass), $srcDir, $classFiles);
                if (null === $declared) {
                    throw new RuntimeException(\sprintf('The %s property %s::$%s is sent, but the file %s declares it in could not be read.', $className, $declaringClass, $property->getName(), $classFiles[$declaringClass] ?? $declaringClass));
                }

                $rawType = $this->resolvePropertyType($property);
                $where = \sprintf('The %s property %s::$%s', $className, $declaringClass, $property->getName());
                $type = $this->parser->parse($rawType);
                $unresolved = $this->docHelper->unresolvedImportUsedIn($type, $declared['unresolved'], $where);
                if (null !== $unresolved) {
                    throw $unresolved;
                }

                // Names are the file's; `self::` is the class, which for a trait is the one using it.
                // A generic base's template params are what the response binds them to.
                $templates = $declaringClass === $className ? [] : $this->templateBindings($reflection, $declaringClass, $srcDir, $classFiles);
                $parsed = $this->docHelper->resolveImportedNames($type, $declared['imports'], $declaringClass, $declared['classImports'], array_keys($templates), selfClass: $property->getDeclaringClass()->getName());
                if (null === $declared['domain']) {
                    $parsed = $this->docHelper->namesAsClasses($parsed, \sprintf('%s is outside the source tree, in no module, so a name it writes can only be a class or a project-wide alias. %s', $declaringClass, $where), [...array_keys($this->typeAliases), ...array_keys($templates)]);
                    $parsed = $this->docHelper->enumsAsWritten($parsed, $declaringClass, $declared['classImports'], $classFiles, $where);
                }
                $parsed = $this->docHelper->substituted($parsed, array_map(static fn (array $bound): ParsedType => $bound['type'], $templates));
                $properties[] = new CollectedResponseProperty(
                    name: $property->getName(),
                    rawType: $rawType,
                    parsed: $parsed,
                    // `?T` is the TS-optional form, as it is in a `_self` shape: the key is left off
                    // the wire when null (a side-load not asked for). `T|null` parses as a required
                    // nullable and stays a key that is always present.
                    optional: $parsed instanceof NullableType && $parsed->optional,
                    imports: [...array_values($declared['imports']), ...array_merge(...array_values(array_map(static fn (array $bound): array => $bound['imports'], $templates)))],
                    writtenIn: $declaringClass === $className ? null : $declaringClass,
                    writtenInDomain: $declaringClass === $className ? null : $declared['domain'],
                );
            }

            $responses[$className] = new CollectedApiResponseClass(
                className: $className,
                name: $reflection->getShortName(),
                domain: $this->domainGuesser->guess($srcDir, $file),
                sourceFile: $file,
                status: $status,
                error: $this->statusCodeResolver->isError($reflection),
                properties: $properties,
                imports: array_values($context['imports']),
                declaresProperties: self::declaresProperties($reflection),
            );
        }

        ksort($responses);

        return $responses;
    }

    /**
     * The scope a property written in a class's file is read in: the types the file imports and the
     * aliases it declares itself — both as imports, the latter from its own module, so a property
     * converted in this scope finds them wherever it is emitted — the classes it `use`s, and its
     * module. An import that can't be emitted, and an alias the class keeps for PHPStan, are set
     * aside: they fail only a published property typed with them. Read once per class.
     *
     * A library's file, outside the source tree, is in no module and emits nothing: every alias it
     * imports or declares is set aside, and its module is null. A class outside it with no file to
     * read has no names of its own, and is in no module either.
     *
     * @param array<string, string> $classFiles
     *
     * @return array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>, domain: string|null}|null
     */
    private function typeContext(string $className, string $file, string $srcDir, array $classFiles): ?array
    {
        $key = $srcDir . "\0" . $className;
        if (\array_key_exists($key, $this->typeContexts)) {
            return $this->typeContexts[$key];
        }

        return $this->typeContexts[$key] = isset($classFiles[$className])
            ? $this->readTypeContext($className, $file, $srcDir, $classFiles)
            : $this->readLibraryTypeContext($className, $file, $srcDir, $classFiles);
    }

    /**
     * @param array<string, string> $classFiles
     *
     * @return array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>, domain: string}|null
     */
    private function readTypeContext(string $className, string $file, string $srcDir, array $classFiles): ?array
    {
        if (!$this->files->readable($file)) {
            return null;
        }

        $scope = $this->files->scope($className, $file, $srcDir, $classFiles);
        $imports = $scope['imports'];
        $domain = $this->domainGuesser->guess($srcDir, $file);
        foreach (array_keys($scope['definitions']) as $alias) {
            $imports[$alias] = new ImportedType(
                localAlias: $alias,
                targetClass: $className,
                targetTypeName: $this->docHelper->emittedTypeName($alias, $className),
                targetDomain: $domain,
                sourceAlias: $alias,
            );
        }

        return [
            'imports' => $imports,
            'unresolved' => $scope['unresolved'],
            'classImports' => $this->files->classImports($file),
            'domain' => $domain,
        ];
    }

    /**
     * @param array<string, string> $classFiles
     *
     * @return array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>, domain: null}
     */
    private function readLibraryTypeContext(string $className, string $file, string $srcDir, array $classFiles): array
    {
        // A class with no file — declared by PHP or an extension, or at run time — has no names of
        // its own to read; its properties are as their declarations spell them.
        if ('' === $file || !$this->files->readable($file)) {
            return ['imports' => [], 'unresolved' => [], 'classImports' => [], 'domain' => null];
        }

        $read = $this->files->imports($className, $file, $srcDir, $classFiles);
        $unresolved = $read['unresolved'];
        foreach (array_keys($read['imports']) as $alias) {
            $unresolved[$alias] = \sprintf('"%s" is imported by %s, outside the source tree: a name a library writes is read as a class, never an alias.', $alias, $className);
        }
        foreach (array_keys($this->files->definitions($file)) as $alias) {
            $unresolved[$alias] = \sprintf('"%s" is declared by %s, outside the source tree: it is never emitted, so nothing sent can be typed with it.', $alias, $className);
        }

        return [
            'imports' => [],
            'unresolved' => $unresolved,
            'classImports' => $this->files->classImports($file),
            'domain' => null,
        ];
    }

    /**
     * What the template params of a base the response extends stand for, as the classes between them
     * bind them: each class's `@extends Parent<…>` binds its parent's params, in its own file and in
     * terms of its own params, which the class below it bound in turn. A param nothing binds is its
     * bound (`@template L of string`), read where it is declared, or `mixed`. None for a trait, or a
     * base with no params.
     *
     * @param ReflectionClass<object> $response
     * @param array<string, string>   $classFiles
     *
     * @return array<string, array{type: ParsedType, imports: list<ImportedType>}>
     */
    private function templateBindings(ReflectionClass $response, string $base, string $srcDir, array $classFiles): array
    {
        $chain = [];
        for ($class = $response; false !== $class && $class->getName() !== $base; $class = $class->getParentClass()) {
            $chain[] = $class;
        }
        if (false === $class || [] === $this->docHelper->templateParams((string) $class->getDocComment())) {
            return [];
        }

        $bindings = [];
        foreach ($chain as $class) {
            $parent = $class->getParentClass();
            if (false === $parent) {
                return [];
            }
            $args = $this->docHelper->extendsArgs((string) $class->getDocComment()) ?? [];
            $next = [];
            foreach ($this->docHelper->templateParams((string) $parent->getDocComment()) as $i => $param) {
                $next[$param['name']] = match (true) {
                    isset($args[$i]) => $this->boundType($args[$i], $class->getName(), $bindings, $srcDir, $classFiles),
                    null !== $param['bound'] => $this->boundType($param['bound'], $parent->getName(), [], $srcDir, $classFiles),
                    default => ['type' => new ScalarType('mixed'), 'imports' => []],
                };
            }
            $bindings = $next;
        }

        return $bindings;
    }

    /**
     * A type a template param is bound to, read in the file that writes it — its names that file's,
     * its own template params what they are bound to. An alias it names is carried under a name its
     * file alone uses, so the base's file it is substituted into reads it as this one does.
     *
     * @param array<string, array{type: ParsedType, imports: list<ImportedType>}> $bindings the writing class's own params
     * @param array<string, string>                                                $classFiles
     *
     * @return array{type: ParsedType, imports: list<ImportedType>}
     */
    private function boundType(string $written, string $class, array $bindings, string $srcDir, array $classFiles): array
    {
        $context = $this->typeContext($class, $classFiles[$class] ?? $this->fileOf($class), $srcDir, $classFiles);
        if (null === $context) {
            throw new RuntimeException(\sprintf('%s binds a template param to `%s`, but its file could not be read.', $class, $written));
        }

        $where = \sprintf('The template param %s binds to `%s`', $class, $written);
        $type = $this->parser->parse($written);
        $unresolved = $this->docHelper->unresolvedImportUsedIn($type, $context['unresolved'], $where);
        if (null !== $unresolved) {
            throw $unresolved;
        }
        $parsed = $this->docHelper->resolveImportedNames($type, $context['imports'], $class, $context['classImports'], array_keys($bindings));
        if (null === $context['domain']) {
            $parsed = $this->docHelper->namesAsClasses($parsed, \sprintf('%s is outside the source tree, in no module, so a name it writes can only be a class or a project-wide alias. %s', $class, $where), [...array_keys($this->typeAliases), ...array_keys($bindings)]);
            $parsed = $this->docHelper->enumsAsWritten($parsed, $class, $context['classImports'], $classFiles, $where);
        }

        $renamed = [];
        $imports = [];
        foreach (ImportedType::usedIn(array_values($context['imports']), $parsed) as $import) {
            $name = $import->localAlias . '@' . $class;
            $renamed[$import->localAlias] = new NameRefType($name);
            $imports[] = new ImportedType($name, $import->targetClass, $import->targetTypeName, $import->targetDomain, $import->sourceAlias);
        }
        $parsed = $this->docHelper->substituted($parsed, $renamed);

        return [
            'type' => $this->docHelper->substituted($parsed, array_map(static fn (array $bound): ParsedType => $bound['type'], $bindings)),
            'imports' => [...$imports, ...array_merge(...array_values(array_map(static fn (array $bound): array => $bound['imports'], $bindings)))],
        ];
    }

    /**
     * The class or trait whose file a property is written in. Reflection names the class a trait's
     * property is used in; the trait that declares it — the innermost, its doc comment the
     * property's — is where its annotation was written and its names are imported. In the source
     * tree or not: a library's base class or trait is read in its own file, as PHPStan reads it.
     */
    private static function writtenIn(ReflectionProperty $property): string
    {
        $class = $property->getDeclaringClass();
        $traits = $class->getTraits();
        while ([] !== $traits) {
            $trait = array_shift($traits);
            if ($trait->hasProperty($property->getName()) && $trait->getProperty($property->getName())->getDocComment() === $property->getDocComment()) {
                $class = $trait;
                $traits = $trait->getTraits();
            }
        }

        return $class->getName();
    }

    /**
     * Whether the class declares public properties of its own, inherited ones aside.
     *
     * @param ReflectionClass<object> $class
     */
    private static function declaresProperties(ReflectionClass $class): bool
    {
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic() && $property->getDeclaringClass()->getName() === $class->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The file a class outside the source tree is declared in; '' for one with none — declared by
     * PHP or an extension, or at run time.
     */
    private function fileOf(string $class): string
    {
        if (!$this->files->loadable($class)) {
            return '';
        }

        return (string) new ReflectionClass($class)->getFileName();
    }

    private function resolvePropertyType(ReflectionProperty $property): string
    {
        return $this->docHelper->extractPropertyType($property) ?? self::nativeType($property->getType(), $property);
    }

    /**
     * A property's native type, as a type PHPDoc writes it, where no docblock says more: what it
     * may hold is all it says, so it is what is sent. A null it admits is kept — `?string` is
     * `string|null`, written whether null or not. A class is the one Reflection names, written whole,
     * so it is read as that class wherever the property is written. `array` is a list or an object of
     * anything, as json_encode writes one; `object` and `iterable`, and an intersection, say nothing
     * of the JSON they make.
     */
    private static function nativeType(?ReflectionType $type, ReflectionProperty $property): string
    {
        if ($type instanceof ReflectionNamedType) {
            $named = self::namedType($type, $property);

            return $type->allowsNull() && !\in_array($type->getName(), ['mixed', 'null'], true) ? $named . '|null' : $named;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                static fn (ReflectionType $member): string => $member instanceof ReflectionNamedType ? self::namedType($member, $property) : 'mixed',
                $type->getTypes(),
            ));
        }

        return 'mixed';
    }

    private static function namedType(ReflectionNamedType $type, ReflectionProperty $property): string
    {
        $name = $type->getName();
        if (!$type->isBuiltin()) {
            $class = match (strtolower($name)) {
                'self' => $property->getDeclaringClass()->getName(),
                'parent' => $property->getDeclaringClass()->getParentClass() ?: null,
                default => $name,
            };

            return '\\' . ($class instanceof ReflectionClass ? $class->getName() : $class ?? $name);
        }

        return match ($name) {
            'array' => 'array<array-key, mixed>',
            'object', 'iterable' => 'mixed',
            default => $name,
        };
    }
}
