<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Model\CollectedType;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\MapType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ParsedTypes;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ScopedType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Support\TypeScriptTypes;

/**
 * A map keyed by an alias, keyed as one written out would be ({@see MapType::keyOf()}): by any key
 * where the alias stands for one no array has, or for every one an array has; by the alias where it
 * stands for a key an array has. The parser can't tell, an alias being declared elsewhere — another
 * class, another module — and the emitter can, where every module's aliases are known. A project-wide
 * alias is read by its TypeScript: any key where that is (`string | number`). A class's name is kept.
 */
final class AliasedMapKeys
{
    /** @var array<string, array<string, CollectedType>> by module, its aliases by name */
    private array $aliases = [];

    /**
     * @param array<string, CollectedDomain> $domains
     * @param array<string, string>          $typeAliases the config's project-wide aliases, name => TypeScript
     */
    public function __construct(array $domains, private readonly array $typeAliases = [])
    {
        foreach ($domains as $domain) {
            foreach ($domain->types as $type) {
                $this->aliases[$type->domain][$type->name] = $type;
            }
        }
    }

    /**
     * Each module, its maps keyed as their aliases are; one with none as it was.
     *
     * @param array<string, CollectedDomain> $domains
     *
     * @return array<string, CollectedDomain>
     */
    public function inDomains(array $domains): array
    {
        $keyed = [];
        foreach ($domains as $name => $domain) {
            $types = array_map(fn (CollectedType $type): CollectedType => $this->inType($type), $domain->types);
            if ($types === $domain->types) {
                $keyed[$name] = $domain;

                continue;
            }
            $keyed[$name] = new CollectedDomain($domain->name);
            $keyed[$name]->types = $types;
            $keyed[$name]->selfImports = $domain->selfImports;
        }

        return $keyed;
    }

    /**
     * Each response, its properties' maps keyed as their aliases are — read where each property is
     * written; one with none as it was.
     *
     * @param array<string, list<CollectedApiResponseClass>> $responses
     *
     * @return array<string, list<CollectedApiResponseClass>>
     */
    public function inResponses(array $responses): array
    {
        return array_map(fn (array $domainResponses): array => array_map(function (CollectedApiResponseClass $response): CollectedApiResponseClass {
            $properties = array_map(function (CollectedResponseProperty $property) use ($response): CollectedResponseProperty {
                $parsed = $this->keyed($property->parsed, $property->imports, $property->writtenInDomain ?? $response->domain);

                return $parsed === $property->parsed ? $property : new CollectedResponseProperty(
                    name: $property->name,
                    rawType: $property->rawType,
                    parsed: $parsed,
                    optional: $property->optional,
                    imports: $property->imports,
                    writtenIn: $property->writtenIn,
                    writtenInDomain: $property->writtenInDomain,
                );
            }, $response->properties);

            return $properties === $response->properties ? $response : new CollectedApiResponseClass(
                className: $response->className,
                name: $response->name,
                domain: $response->domain,
                sourceFile: $response->sourceFile,
                status: $response->status,
                error: $response->error,
                properties: $properties,
                imports: $response->imports,
                declaresProperties: $response->declaresProperties,
            );
        }, $domainResponses), $responses);
    }

    private function inType(CollectedType $type): CollectedType
    {
        $parsed = $this->keyed($type->parsed, $type->imports, $type->domain);

        return $parsed === $type->parsed ? $type : new CollectedType(
            name: $type->name,
            definition: $type->definition,
            parsed: $parsed,
            sourceFile: $type->sourceFile,
            domain: $type->domain,
            ownerClass: $type->ownerClass,
            imports: $type->imports,
            isSelf: $type->isSelf,
        );
    }

    /**
     * The type with each map keyed by an alias keyed as the alias is, its names read with the file's
     * imports and the module's aliases — a part written in another file with that file's.
     *
     * @param list<ImportedType> $imports
     */
    private function keyed(ParsedType $type, array $imports, ?string $domain): ParsedType
    {
        if ($type instanceof ScopedType) {
            $inner = $this->keyed($type->inner, $type->imports, $type->domain);

            return $inner === $type->inner ? $type : new ScopedType($inner, $type->imports, $type->domain);
        }

        $children = ParsedTypes::children($type);
        $keyed = array_map(fn (ParsedType $child): ParsedType => $this->keyed($child, $imports, $domain), $children);
        if ($type instanceof MapType) {
            // A key no name is in was keyed as it was built; one a name is in, as what it stands for:
            // any key where that is one no array has, or every one an array has.
            $standsFor = $this->standsFor($keyed[0], $imports, $domain, []);
            $keyedAs = MapType::keyOf($standsFor);
            // A project-wide alias's TypeScript says what it is: `string | number` is any key.
            $anyKey = $standsFor instanceof NameRefType && null === $standsFor->class && isset($this->typeAliases[$standsFor->name])
                && TypeScriptTypes::isAnyKey($this->typeAliases[$standsFor->name], $this->typeAliases);
            if ($anyKey || ($keyedAs instanceof ScalarType && 'array-key' === $keyedAs->type)) {
                $keyed[0] = new ScalarType('array-key');
            }
        }

        return $keyed === $children ? $type : ParsedTypes::withChildren($type, $keyed);
    }

    /**
     * What a key stands for: an alias followed to what it is declared as, through the aliases it
     * names in turn and a union's members; the key itself where it names nothing this can follow.
     *
     * @param list<ImportedType>  $imports
     * @param array<string, true> $seen    the aliases followed on the way here
     */
    private function standsFor(ParsedType $key, array $imports, ?string $domain, array $seen): ParsedType
    {
        if ($key instanceof ScopedType) {
            return $this->standsFor($key->inner, $key->imports, $key->domain, $seen);
        }
        if ($key instanceof UnionType) {
            $members = array_map(fn (ParsedType $member): ParsedType => $this->standsFor($member, $imports, $domain, $seen), $key->types);

            return $members === $key->types ? $key : new UnionType($members);
        }
        if (!$key instanceof NameRefType) {
            return $key;
        }

        $alias = $this->aliasNamed($key, $imports, $domain);
        if (null === $alias || isset($seen[$alias->domain . '::' . $alias->name])) {
            return $key;
        }
        $seen[$alias->domain . '::' . $alias->name] = true;

        return $this->standsFor($alias->parsed, $alias->imports, $alias->domain, $seen);
    }

    /**
     * The alias a name is, as the converter reads it ({@see TypeToTsConverter::referenceOf()}): a
     * name the file imports, that import; otherwise the module's alias of that name, ahead of a class.
     *
     * @param list<ImportedType> $imports
     */
    private function aliasNamed(NameRefType $name, array $imports, ?string $domain): ?CollectedType
    {
        if (null === $name->class) {
            foreach ($imports as $import) {
                if ($import->localAlias === $name->name) {
                    return $this->aliases[$import->targetDomain][$import->targetTypeName] ?? null;
                }
            }
        }

        return null === $domain ? null : $this->aliases[$domain][$name->name] ?? null;
    }
}
