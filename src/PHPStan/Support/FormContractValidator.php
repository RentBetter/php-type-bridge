<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Support;

use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Support\FormTypeInspector;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

final class FormContractValidator
{
    public function __construct(
        private readonly FormTypeInspector $formTypeInspector = new FormTypeInspector(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
    ) {}

    /**
     * @param class-string $formClass
     * @return list<string>
     */
    public function validate(string $formClass): array
    {
        $errors = [];
        $reflection = new ReflectionClass($formClass);

        $expectedDataClass = $this->resolveGenericDataClass($reflection);
        if (null === $expectedDataClass) {
            $errors[] = \sprintf(
                'Contract form "%s" must declare @implements ContractFormType<FooData>.',
                $formClass,
            );

            return $errors;
        }

        try {
            $inspected = $this->formTypeInspector->inspect($formClass);
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();

            return $errors;
        }

        if ($inspected['dataClass'] !== $expectedDataClass) {
            $errors[] = \sprintf(
                'Contract form "%s" must configure data_class "%s"; found "%s".',
                $formClass,
                $expectedDataClass,
                $inspected['dataClass'] ?? 'null',
            );
        }

        if (!$this->declaresSelfType($expectedDataClass)) {
            $errors[] = \sprintf(
                'Contract form data class "%s" must declare @phpstan-type _self.',
                $expectedDataClass,
            );
        }

        foreach ($inspected['fields'] as $field) {
            $errors = [...$errors, ...$this->validateField(
                formClass: $formClass,
                rootDataClass: $expectedDataClass,
                field: $field,
            )];
        }

        return $errors;
    }

    /**
     * @template TObject of object
     * @param ReflectionClass<TObject> $reflection
     * @return class-string|null
     */
    private function resolveGenericDataClass(ReflectionClass $reflection): ?string
    {
        $docComment = $reflection->getDocComment();
        if (false === $docComment) {
            return null;
        }

        if (!preg_match('/@implements\s+[\\\\\w]+\s*<\s*([\\\\\w]+)\s*>/', $docComment, $matches)) {
            return null;
        }

        $dataClass = ltrim($matches[1], '\\');
        if (class_exists($dataClass)) {
            return $dataClass;
        }

        if (null !== ($importedClass = $this->resolveImportedClass($reflection, $dataClass))) {
            return $importedClass;
        }

        $namespace = $reflection->getNamespaceName();
        if ('' !== $namespace) {
            $namespaced = $namespace . '\\' . $dataClass;
            if (class_exists($namespaced)) {
                return $namespaced;
            }
        }

        return null;
    }

    /**
     * @param class-string $rootDataClass
     * @return list<string>
     */
    private function validateField(string $formClass, string $rootDataClass, CollectedFormField $field): array
    {
        if (!$field->mapped) {
            return [];
        }

        $property = $this->resolveProperty($rootDataClass, $field->propertyPath ?? $field->name);
        if (null === $property) {
            return [\sprintf(
                'Contract form "%s" maps field "%s" to missing property path "%s" on "%s".',
                $formClass,
                $field->name,
                $field->propertyPath ?? $field->name,
                $rootDataClass,
            )];
        }

        if ($this->shouldSkipTypeValidation($field)) {
            return [];
        }

        if (CollectionType::class === $field->formTypeClass) {
            return $this->validateCollectionField($formClass, $property, $field);
        }

        if (null !== $field->dataClass && [] !== $field->children) {
            $errors = [];
            if (!class_exists($field->dataClass)) {
                $errors[] = \sprintf(
                    'Contract form "%s" field "%s" references unknown nested data class "%s".',
                    $formClass,
                    $field->name,
                    $field->dataClass,
                );

                return $errors;
            }

            if (!$this->propertyAcceptsClass($property, $field->dataClass)) {
                $errors[] = \sprintf(
                    'Contract form "%s" field "%s" expects property "%s" to be "%s".',
                    $formClass,
                    $field->name,
                    $property->getName(),
                    $field->dataClass,
                );
            }

            foreach ($field->children as $child) {
                $errors = [...$errors, ...$this->validateField($formClass, $field->dataClass, $child)];
            }

            return $errors;
        }

        $expectedType = $this->expectedLeafType($field);
        if (null === $expectedType) {
            return [];
        }

        if (!$this->propertyMatchesExpectedType($property, $expectedType)) {
            return [\sprintf(
                'Contract form "%s" field "%s" expects property "%s" to be compatible with "%s".',
                $formClass,
                $field->name,
                $property->getName(),
                $expectedType,
            )];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function validateCollectionField(string $formClass, ReflectionProperty $property, CollectedFormField $field): array
    {
        if (!$this->propertyIsArray($property)) {
            return [\sprintf(
                'Contract form "%s" collection field "%s" expects property "%s" to be an array.',
                $formClass,
                $field->name,
                $property->getName(),
            )];
        }

        if (null !== $field->entryDataClass) {
            if (!$this->propertyDocContainsArrayValueType($property, $field->entryDataClass)) {
                return [\sprintf(
                    'Contract form "%s" collection field "%s" expects property "%s" to document "%s" items.',
                    $formClass,
                    $field->name,
                    $property->getName(),
                    $field->entryDataClass,
                )];
            }

            return [];
        }

        if (null === $field->entryTypeClass) {
            return [];
        }

        $expectedType = $this->expectedLeafType($field, $field->entryTypeClass);
        if (null === $expectedType) {
            return [];
        }

        if (!$this->propertyDocContainsArrayValueType($property, $expectedType)) {
            return [\sprintf(
                'Contract form "%s" collection field "%s" expects property "%s" to document "%s" items.',
                $formClass,
                $field->name,
                $property->getName(),
                $expectedType,
            )];
        }

        return [];
    }

    private function expectedLeafType(CollectedFormField $field, ?string $overrideFormTypeClass = null): ?string
    {
        $formTypeClass = $overrideFormTypeClass ?? $field->formTypeClass;

        return match ($formTypeClass) {
            TextType::class, TextareaType::class => 'string',
            IntegerType::class => 'int',
            NumberType::class => 'float',
            CheckboxType::class => 'bool',
            EnumType::class => $field->enumClass,
            DateType::class, DateTimeType::class => $this->expectedDateType($field->input),
            default => match ($this->shortName($formTypeClass)) {
                'BooleanType' => 'bool',
                'JsonType' => 'array',
                default => null,
            },
        };
    }

    private function expectedDateType(?string $input): string
    {
        return match ($input) {
            'datetime_immutable', 'string' => 'DateTimeImmutable',
            'datetime' => 'DateTime',
            default => 'DateTimeInterface',
        };
    }

    private function shouldSkipTypeValidation(CollectedFormField $field): bool
    {
        if (!$field->hasModelTransformers && !$field->hasViewTransformers) {
            return false;
        }

        return match ($field->formTypeClass) {
            IntegerType::class,
            NumberType::class,
            EnumType::class,
            DateType::class,
            DateTimeType::class,
            CheckboxType::class,
            CollectionType::class => false,
            default => true,
        };
    }

    /**
     * @param class-string $rootDataClass
     */
    private function resolveProperty(string $rootDataClass, string $propertyPath): ?ReflectionProperty
    {
        $segments = array_values(array_filter(explode('.', $propertyPath), static fn (string $segment): bool => '' !== $segment));
        if ([] === $segments) {
            return null;
        }

        $currentClass = $rootDataClass;
        $property = null;

        foreach ($segments as $index => $segment) {
            if (!property_exists($currentClass, $segment)) {
                return null;
            }

            $property = new ReflectionProperty($currentClass, $segment);
            if ($index === array_key_last($segments)) {
                return $property;
            }

            $currentClass = $this->resolveTraversablePropertyClass($property) ?? '';
            if ('' === $currentClass) {
                return null;
            }
        }

        return $property;
    }

    /**
     * @return class-string|null
     */
    private function resolveTraversablePropertyClass(ReflectionProperty $property): ?string
    {
        foreach ($this->namedTypes($property->getType()) as $namedType) {
            if (!$namedType->isBuiltin() && class_exists($namedType->getName())) {
                return $namedType->getName();
            }
        }

        return null;
    }

    private function propertyMatchesExpectedType(ReflectionProperty $property, ?string $expectedType): bool
    {
        if (null === $expectedType) {
            return true;
        }

        if ('array' === $expectedType) {
            return $this->propertyIsArray($property);
        }

        if (\in_array($expectedType, ['string', 'int', 'float', 'bool'], true)) {
            return $this->propertyAcceptsBuiltin($property, $expectedType);
        }

        if ('DateTimeInterface' === $expectedType) {
            return $this->propertyAcceptsAnyClass($property, [
                \DateTimeInterface::class,
                \DateTimeImmutable::class,
                \DateTime::class,
            ]);
        }

        if ('DateTimeImmutable' === $expectedType) {
            return $this->propertyAcceptsAnyClass($property, [
                \DateTimeImmutable::class,
                \DateTimeInterface::class,
            ]);
        }

        if ('DateTime' === $expectedType) {
            return $this->propertyAcceptsAnyClass($property, [
                \DateTime::class,
                \DateTimeInterface::class,
            ]);
        }

        return $this->propertyAcceptsClass($property, $expectedType);
    }

    private function propertyIsArray(ReflectionProperty $property): bool
    {
        return $this->propertyAcceptsBuiltin($property, 'array');
    }

    private function propertyAcceptsBuiltin(ReflectionProperty $property, string $expectedBuiltin): bool
    {
        foreach ($this->namedTypes($property->getType()) as $type) {
            if ($type->isBuiltin() && $expectedBuiltin === $type->getName()) {
                return true;
            }
        }

        return false;
    }

    private function propertyAcceptsClass(ReflectionProperty $property, string $expectedClass): bool
    {
        foreach ($this->namedTypes($property->getType()) as $type) {
            if ($type->isBuiltin()) {
                continue;
            }

            if ($type->getName() === $expectedClass || is_a($type->getName(), $expectedClass, true) || is_a($expectedClass, $type->getName(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<class-string> $expectedClasses
     */
    private function propertyAcceptsAnyClass(ReflectionProperty $property, array $expectedClasses): bool
    {
        foreach ($expectedClasses as $expectedClass) {
            if ($this->propertyAcceptsClass($property, $expectedClass)) {
                return true;
            }
        }

        return false;
    }

    private function propertyDocContainsArrayValueType(ReflectionProperty $property, string $expectedType): bool
    {
        $docType = $this->extractPropertyDocType($property);
        if (null === $docType) {
            return false;
        }

        $pattern = preg_quote(ltrim($expectedType, '\\'), '/');

        return 1 === preg_match(\sprintf(
            '/(?:^|[<|(,])%1$s(?:\[\]|[>|),]|$)|(?:list|array)<(?:int,\s*)?%1$s>/',
            $pattern,
        ), $docType);
    }

    private function extractPropertyDocType(ReflectionProperty $property): ?string
    {
        $docComment = $property->getDocComment();
        if (false === $docComment) {
            return null;
        }

        $type = $this->docHelper->extractVarType($docComment);

        return null !== $type && '' !== $type ? ltrim($type, '\\') : null;
    }

    /**
     * @template TObject of object
     * @param ReflectionClass<TObject> $reflection
     * @return class-string|null
     */
    private function resolveImportedClass(ReflectionClass $reflection, string $shortName): ?string
    {
        $file = $reflection->getFileName();
        if (false === $file) {
            return null;
        }

        $content = file_get_contents($file);
        if (false === $content) {
            return null;
        }

        if (!preg_match_all('/^use\s+([^;]+?)(?:\s+as\s+(\w+))?;/m', $content, $matches, \PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            $importedClass = ltrim($match[1], '\\');
            $alias = $match[2] ?? $this->shortName($importedClass);

            if ($alias !== $shortName) {
                continue;
            }

            return class_exists($importedClass) ? $importedClass : null;
        }

        return null;
    }

    /**
     * @return list<ReflectionNamedType>
     */
    private function namedTypes(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type];
        }

        if ($type instanceof ReflectionUnionType) {
            return array_values(array_filter(
                $type->getTypes(),
                static fn (ReflectionType $innerType): bool => $innerType instanceof ReflectionNamedType,
            ));
        }

        return [];
    }

    /**
     * @param class-string $dataClass
     */
    private function declaresSelfType(string $dataClass): bool
    {
        if (!class_exists($dataClass)) {
            return false;
        }

        $reflection = new ReflectionClass($dataClass);
        $docComment = $reflection->getDocComment();
        if (false === $docComment) {
            return false;
        }

        return str_contains($docComment, '@phpstan-type _self');
    }

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');
        if (false === $position) {
            return $className;
        }

        return substr($className, $position + 1);
    }
}
