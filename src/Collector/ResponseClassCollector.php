<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Model\ImportedType;
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

    /** @var array<string, array<class-string, CollectedApiResponseClass>> by source directory: with their status alone */
    private array $statusIndexes = [];

    /** @var array<string, array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>, domain: string|null}|null> by source directory and class */
    private array $typeContexts = [];

    /** @var array<string, array<string, ParsedType>> by source directory, response and base: what the base's template params are bound to */
    private array $templateBindings = [];

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
        return $this->indexes[$srcDir] ??= $this->readIndex($srcDir, withProperties: true);
    }

    /**
     * The responses by what an endpoint answers with alone — each one's status, and whether it is an
     * error — none of what any of them sends read: what a tool needs, which a response it never
     * answers with, typed with what TypeBridge can't read, has no say in.
     *
     * @return array<class-string, CollectedApiResponseClass>
     */
    public function statusIndex(string $srcDir): array
    {
        return $this->statusIndexes[$srcDir] ??= $this->readIndex($srcDir, withProperties: false);
    }

    /**
     * @return array<class-string, CollectedApiResponseClass>
     */
    private function readIndex(string $srcDir, bool $withProperties): array
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
            $context = $withProperties ? $this->typeContext($className, $file, $srcDir, $classFiles) : ['imports' => []];
            // A status alone is read off the class: its file's types are none of it.
            if (null === $context || ($withProperties && !$this->files->readable($file))) {
                continue;
            }

            $status = $this->statusCodeResolver->resolve($reflection);
            $properties = [];
            // A 204 is sent with no body at all: nothing it declares or inherits is published.
            foreach (204 === $status || !$withProperties ? [] : $reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
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
                // A generic base's template params are what the response binds them to, and a generic
                // trait's what the class using it does.
                $templates = match (true) {
                    $declaringClass === $className => [],
                    $declaringClass !== $property->getDeclaringClass()->getName() => $this->traitBindings($reflection, $property, $srcDir, $classFiles),
                    default => $this->templateBindings($reflection, $declaringClass, $srcDir, $classFiles),
                };
                $parsed = $this->docHelper->substituted(
                    $this->readInFile($type, $declaringClass, $declared, $classFiles, array_keys($templates), $where, $property->getDeclaringClass()->getName()),
                    $templates,
                );
                $properties[] = new CollectedResponseProperty(
                    name: $property->getName(),
                    rawType: $rawType,
                    parsed: $parsed,
                    // `?T` is the TS-optional form, as it is in a `_self` shape: the key is left off
                    // the wire when null (a side-load not asked for). `T|null` parses as a required
                    // nullable and stays a key that is always present.
                    optional: $parsed instanceof NullableType && $parsed->optional,
                    imports: array_values($declared['imports']),
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
     * A type as the file it is written in means it: its names that file's, and a template param it
     * names left for what binds it. A file outside the source tree is in no module, so each name it
     * writes is spelt as the class it names — but a project-wide alias, or a template param — and a
     * `value-of<…>` names the enum its file does.
     *
     * @param array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>, domain: string|null} $context
     * @param array<string, string> $classFiles
     * @param list<string>          $templateNames
     */
    private function readInFile(ParsedType $type, string $class, array $context, array $classFiles, array $templateNames, string $where, ?string $selfClass = null): ParsedType
    {
        $parsed = $this->docHelper->resolveImportedNames($type, $context['imports'], $class, $context['classImports'], $templateNames, selfClass: $selfClass);
        if (null === $context['domain']) {
            $parsed = $this->docHelper->namesAsClasses($parsed, \sprintf('%s is outside the source tree, in no module, so a name it writes can only be a class or a project-wide alias. %s', $class, $where), [...array_keys($this->typeAliases), ...$templateNames]);
            $parsed = $this->docHelper->enumsAsWritten($parsed, $class, $context['classImports'], $classFiles, $where);
        }

        return $parsed;
    }

    /**
     * What the template params of a base the response extends stand for, as the classes between them
     * bind them: each class's `@extends Parent<…>` binds its parent's params, in its own file and in
     * terms of its own params, which the class below it bound in turn. A param nothing binds is its
     * default (`@template D = bool`), else its bound (`@template L of string`), read where it is
     * declared, else `mixed`. None for a trait, or a base with no params. Worked out once for each
     * response and base, however many properties the base declares.
     *
     * @param ReflectionClass<object> $response
     * @param array<string, string>   $classFiles
     *
     * @return array<string, ParsedType>
     */
    private function templateBindings(ReflectionClass $response, string $base, string $srcDir, array $classFiles): array
    {
        return $this->templateBindings[$srcDir . "\0" . $response->getName() . "\0" . $base] ??= $this->bindTemplates($response, $base, $srcDir, $classFiles);
    }

    /**
     * @param ReflectionClass<object> $response
     * @param array<string, string>   $classFiles
     *
     * @return array<string, ParsedType>
     */
    private function bindTemplates(ReflectionClass $response, string $base, string $srcDir, array $classFiles): array
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
            $bindings = $this->paramsBound($parent, $this->docHelper->extendsArgs((string) $class->getDocComment()) ?? [], $class->getName(), $bindings, $srcDir, $classFiles);
        }

        return $bindings;
    }

    /**
     * What a generic trait's template params are bound to where a property it writes is used: by the
     * class using it — its `@use Trait<…>`, read in that class's file, its own params what the
     * response binds them to — and through a trait that uses another, by that one in turn.
     *
     * @param ReflectionClass<object> $response
     * @param array<string, string>   $classFiles
     *
     * @return array<string, ParsedType>
     */
    private function traitBindings(ReflectionClass $response, ReflectionProperty $property, string $srcDir, array $classFiles): array
    {
        $user = $property->getDeclaringClass();
        $key = $srcDir . "\0" . $response->getName() . "\0" . $user->getName() . "\0" . self::writtenIn($property);
        if (isset($this->templateBindings[$key])) {
            return $this->templateBindings[$key];
        }

        $bindings = $user->getName() === $response->getName() ? [] : $this->templateBindings($response, $user->getName(), $srcDir, $classFiles);
        foreach (self::traitPath($property) as $trait) {
            $bindings = $this->paramsBound($trait, $this->files->usesArgs($classFiles[$user->getName()] ?? $this->fileOf($user->getName()), $trait->getShortName()), $user->getName(), $bindings, $srcDir, $classFiles);
            $user = $trait;
        }

        return $this->templateBindings[$key] = $bindings;
    }

    /**
     * The generic class or trait's template params as `$writer` binds them (`$args`, in order): what
     * is written read in its file, its own params `$bindings`; one it doesn't bind its default, else
     * its bound, else mixed.
     *
     * @param ReflectionClass<object>    $generic
     * @param list<string>               $args
     * @param array<string, ParsedType>  $bindings
     * @param array<string, string>      $classFiles
     *
     * @return array<string, ParsedType>
     */
    private function paramsBound(ReflectionClass $generic, array $args, string $writer, array $bindings, string $srcDir, array $classFiles): array
    {
        $bound = [];
        foreach ($this->docHelper->templateParams((string) $generic->getDocComment()) as $i => $param) {
            $unbound = $param['default'] ?? $param['bound'];
            $bound[$param['name']] = match (true) {
                isset($args[$i]) => $this->boundType($args[$i], $writer, $bindings, $srcDir, $classFiles),
                null !== $unbound => $this->boundType($unbound, $generic->getName(), [], $srcDir, $classFiles),
                default => new ScalarType('mixed'),
            };
        }

        return $bound;
    }

    /**
     * A type a template param is bound to, as the file that writes it means it — its own template
     * params what they are bound to in turn — each name it uses kept in that file's scope
     * ({@see PhpDocTypeHelper::scoped()}): put in the base's property, it is still read where it is
     * written, its `?` and its brackets the base's property's to see.
     *
     * @param array<string, ParsedType> $bindings the writing class's own params
     * @param array<string, string>     $classFiles
     */
    private function boundType(string $written, string $class, array $bindings, string $srcDir, array $classFiles): ParsedType
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

        // Its own params first: what they are bound to is scoped where that is written.
        return $this->docHelper->scoped(
            $this->docHelper->substituted($this->readInFile($type, $class, $context, $classFiles, array_keys($bindings), $where), $bindings),
            array_values($context['imports']),
            $context['domain'],
        );
    }

    /**
     * The class or trait whose file a property is written in. Reflection names the class a trait's
     * property is used in; the trait that declares it — the innermost, its doc comment the
     * property's — is where its annotation was written and its names are imported. In the source
     * tree or not: a library's base class or trait is read in its own file, as PHPStan reads it.
     */
    private static function writtenIn(ReflectionProperty $property): string
    {
        $path = self::traitPath($property);

        return ([] === $path ? $property->getDeclaringClass() : $path[array_key_last($path)])->getName();
    }

    /**
     * The traits on the way from the class a property is used in to the one that writes it, each
     * used by the one before: none where the class writes it itself.
     *
     * @return list<ReflectionClass<object>>
     */
    private static function traitPath(ReflectionProperty $property): array
    {
        $path = [];
        $traits = $property->getDeclaringClass()->getTraits();
        while ([] !== $traits) {
            $trait = array_shift($traits);
            if ($trait->hasProperty($property->getName()) && $trait->getProperty($property->getName())->getDocComment() === $property->getDocComment()) {
                $path[] = $trait;
                $traits = $trait->getTraits();
            }
        }

        return $path;
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
