<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use Closure;
use PTGS\TypeBridge\Contract\ContractFormType;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Model\FailedBlank;
use ReflectionAttribute;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\Exception\ExceptionInterface as FormException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface as OptionsException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

final class FormTypeInspector
{
    private FormFactoryInterface $formFactory;

    /** The types the inspection factory knows, where it built that factory itself; null for one given it. */
    private ?FormRegistryInterface $registry = null;

    /** Reads a class's validation metadata, for whether a property cascades ({@see self::cascades()}). */
    private ?ValidatorInterface $validatorMetadata = null;

    public function __construct(?FormFactoryInterface $formFactory = null)
    {
        // The validator extension makes form-level validation options (constraints,
        // validation_groups, …) valid when a form is built for inspection — consuming apps
        // routinely attach field constraints. No validation is ever run here; the form is
        // built only to read its field structure.
        if (null === $formFactory) {
            $this->registry = new FormRegistry([new CoreExtension(), new ValidatorExtension(Validation::createValidator())], new ResolvedFormTypeFactory());
            $formFactory = new FormFactory($this->registry);
        }
        $this->formFactory = $formFactory;
    }

    /**
     * @param class-string $formClass
     * @return array{dataClass: ?class-string, fields: list<CollectedFormField>}
     */
    public function inspect(string $formClass): array
    {
        if (!class_exists($formClass)) {
            throw new RuntimeException(\sprintf('Form class "%s" was not found.', $formClass));
        }

        $this->assertFormTypeInterface($formClass);
        $this->assertContractFormType($formClass);

        try {
            $builder = $this->formFactory->createBuilder($formClass);
        } catch (OptionsException|FormException $exception) {
            // A form built here gets no options, so one that requires them — a `project` to
            // scope its entity choices, say — cannot be inspected. That is a fact about the
            // form worth reporting as such, not an analysis crash: a RuntimeException is what
            // the validator turns into a finding and the collector into a generation error.
            throw new RuntimeException(\sprintf(
                'Form "%s" cannot be built for inspection: %s Give the option a default, or keep the form off the contract surface.',
                $formClass,
                $exception->getMessage(),
            ), previous: $exception);
        }

        /** @var class-string|null $dataClass */
        $dataClass = $builder->getOption('data_class');

        return [
            'dataClass' => $dataClass,
            'fields' => $this->collectFields($builder, self::rewritesChildren($builder->getFormConfig()), validated: true),
        ];
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param bool                        $rewritten whether a listener of the form or of one holding it may
     *                                               rewrite what its fields are sent ({@see self::rewritesChildren()})
     * @param bool                        $validated whether the object the form's data is gets its own constraints
     *                                               validated: Symfony validates the root's data, and an embedded
     *                                               object only where its property cascades (#[Assert\Valid]) — a
     *                                               field's own `constraints` option holds wherever it is submitted
     *
     * @return list<CollectedFormField>
     */
    private function collectFields(FormBuilderInterface $builder, bool $rewritten, bool $validated): array
    {
        $fields = [];
        $ownerClass = $builder->getFormConfig()->getDataClass();

        foreach ($builder->all() as $name => $child) {
            $config = $child->getFormConfig();
            $formTypeClass = $config->getType()->getInnerType()::class;
            if (!$this->isFrameworkFormType($formTypeClass) && $this->requiresContractMarker($config)) {
                $this->assertContractFormType($formTypeClass);
            }

            $name = (string) $name;
            $propertyPath = $this->resolvePropertyPath($config, $name);
            $property = $config->getMapped() ? $this->boundProperty($ownerClass, $propertyPath) : null;
            $formConstraints = $this->collectConstraints($config, null);
            $constraints = $validated ? $this->collectConstraints($config, $property) : $formConstraints;
            // What it holds is validated in turn only where it cascades from a validated object: a
            // Valid among its constraints, or its class's #[Assert\Cascade].
            $cascades = $validated && (array_any(HeldConstraints::always($constraints), static fn (Constraint $constraint): bool => $constraint instanceof Valid)
                || (null !== $property && null !== $ownerClass && $this->cascades($ownerClass, $property->getName())));
            $entry = $this->entryBuilder($config);

            $fields[] = new CollectedFormField(
                name: $name,
                formTypeClass: $formTypeClass,
                required: $config->getRequired(),
                mapped: $config->getMapped(),
                compound: $config->getCompound(),
                dataClass: $config->getDataClass(),
                propertyPath: $propertyPath,
                entryTypeClass: $this->resolveEntryTypeClass($config),
                entryDataClass: self::entryDataClass($entry),
                enumClass: $this->resolveStringOption($config, 'class'),
                input: $this->resolveStringOption($config, 'input'),
                multiple: $this->isMultipleChoice($config),
                hasModelTransformers: [] !== $config->getModelTransformers(),
                hasViewTransformers: [] !== $config->getViewTransformers(),
                // An expanded choice draws its choices as buttons, children named 0, 1, …: it reads
                // what is sent itself, and none of them is a field a request sends — as a field, or
                // as each entry of a collection.
                children: self::isChoice($config) ? [] : $this->collectFields($child, $rewritten || self::rewritesChildren($config), $cascades),
                entryChildren: null !== $entry && $entry->getCompound() && !self::isChoice($entry->getFormConfig()) ? $this->collectFields($entry, $rewritten || self::rewritesChildren($config) || self::rewritesChildren($entry->getFormConfig()), $cascades) : [],
                choiceValues: $this->resolveChoiceValues($config, $constraints),
                constraints: $constraints,
                ownerClass: $property?->getDeclaringClass()->getName(),
                trim: false !== $this->resolveOption($config, 'trim'),
                blankValue: $this->blankValue($config, self::mappedFieldNames($child)),
                entryConstraints: $this->entryConstraints($entry, $constraints),
                entryBlankValue: null === $entry ? null : $this->blankValue($entry->getFormConfig(), self::mappedFieldNames($entry)),
                transformsValue: $rewritten || self::transformsValue($config),
                formConstraints: $formConstraints,
            );
        }

        return $fields;
    }

    /**
     * What a field reaches validation as when it is left out of a request, sent as null or sent
     * blank: Symfony submits it null, and its form makes of that what Form::submit() does — its
     * `empty_data`, through its view transformers and then its model ones, '' read as null where it
     * has no view transformer. So a text field is null, or '' where TextType keeps an `empty_data` of
     * '' a string, or `'n/a'` where that is its `empty_data`; a number is null, or 0 from an
     * `empty_data` of '0'; a checkbox is left unticked, false; a choice is none, a multiple one [].
     * Listeners, which see only what was sent, are not run.
     *
     * A compound field is what its type makes it: a collection is emptied, []; an expanded choice
     * chooses none; an embedded form is a new object of its data class where it is required, null
     * where it isn't, and with no data class the array its fields are written into, each blank. A
     * choice a loader fills is never loaded here. One whose transformer fails a blank is a
     * {@see FailedBlank}: the form is invalid, as Symfony submits it.
     *
     * @param FormConfigInterface<mixed> $config
     * @param list<string>               $fieldNames the fields an embedded form writes into its data
     */
    private function blankValue(FormConfigInterface $config, array $fieldNames = []): mixed
    {
        if ($config->getCompound()) {
            for ($type = $config->getType(); null !== $type; $type = $type->getParent()) {
                if ($type->getInnerType() instanceof CollectionType) {
                    return [];
                }
            }
            if ($this->isMultipleChoice($config)) {
                return [];
            }
        } elseif (null !== $this->resolveOption($config, 'choice_loader')) {
            return $this->isMultipleChoice($config) ? [] : null;
        }

        try {
            $value = $config->getEmptyData();
            if ($value instanceof Closure) {
                $value = $value(new Form($config), null);
            }
            // An embedded form with no data class is the array its fields are written into, each
            // blank; one with a data class is what its `empty_data` makes — a new object of the class
            // where the form is required, null where it isn't.
            if ($config->getCompound() && null === $config->getDataClass() && [] === $value) {
                $value = array_fill_keys($fieldNames, null);
            }
            $viewTransformers = $config->getViewTransformers();
            if ([] === $viewTransformers && '' === $value) {
                $value = null;
            }
            foreach ([...array_reverse($viewTransformers), ...array_reverse($config->getModelTransformers())] as $transformer) {
                $value = $transformer->reverseTransform($value);
            }

            return $value;
        } catch (TransformationFailedException) {
            return new FailedBlank();
        } catch (Throwable) {
            // An `empty_data` closure that reads what only a request has: what it makes is unknown.
            return null;
        }
    }

    /**
     * Whether the validator cascades into what the class's property holds, as its metadata says:
     * a #[Assert\Valid] on the property, or the class's #[Assert\Cascade], which cascades into
     * every property it holds an object or an array in.
     */
    private function cascades(string $class, string $property): bool
    {
        try {
            $metadata = ($this->validatorMetadata ??= Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator())->getMetadataFor($class);
        } catch (Throwable) {
            return false;
        }

        return $metadata instanceof ClassMetadataInterface
            && array_any($metadata->getPropertyMetadata($property), static fn (PropertyMetadataInterface $member): bool => CascadingStrategy::CASCADE === $member->getCascadingStrategy());
    }

    /**
     * The names of the fields a form writes into its data.
     *
     * @param FormBuilderInterface<mixed> $form
     *
     * @return list<string>
     */
    private static function mappedFieldNames(FormBuilderInterface $form): array
    {
        $names = [];
        foreach ($form->all() as $name => $field) {
            if ($field->getMapped()) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /**
     * Whether the form changes a submitted value before it is validated: a model transformer does, and
     * a view transformer — a number's, a date's, a choice's — but TextType's own, which turns only a
     * null into ''. So does a listener that rewrites what was sent before it is read (PRE_SUBMIT) or
     * once it is (SUBMIT): UrlType's adding a default protocol, a project's own. Symfony's trimming is
     * the `trim` option's to say.
     *
     * @param FormConfigInterface<mixed> $config
     */
    private static function transformsValue(FormConfigInterface $config): bool
    {
        if ([] !== $config->getModelTransformers()) {
            return true;
        }
        foreach ($config->getViewTransformers() as $transformer) {
            if (!$transformer instanceof TextType) {
                return true;
            }
        }
        foreach ([FormEvents::PRE_SUBMIT, FormEvents::SUBMIT] as $event) {
            foreach ($config->getEventDispatcher()->getListeners($event) as $listener) {
                if (!\is_array($listener) || !$listener[0] instanceof TrimListener) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a listener of a compound form may rewrite what its fields are sent before they are
     * validated: one that reads the form's submitted data before its fields do (PRE_SUBMIT) or its
     * data once they have (SUBMIT) — which fields it touches it doesn't say. A collection's resizing,
     * which adds and removes entries but rewrites none, and Symfony's trimming do not.
     *
     * A type extension the application registers is not loaded here, and what its listeners do is
     * not seen.
     *
     * @param FormConfigInterface<mixed> $config
     */
    private static function rewritesChildren(FormConfigInterface $config): bool
    {
        foreach ([FormEvents::PRE_SUBMIT, FormEvents::SUBMIT] as $event) {
            foreach ($config->getEventDispatcher()->getListeners($event) as $listener) {
                if (!\is_array($listener) || !($listener[0] instanceof TrimListener || $listener[0] instanceof ResizeFormListener)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * What each entry of a collection is held to: the constraints its entry type gives it — its own
     * and its `entry_options`', resolved as the collection builds each entry — and those an All the
     * collection is always held to applies to every entry, Default group alone in both.
     *
     * @param FormBuilderInterface<mixed>|null $entry       the collection's entry, built as it builds one
     * @param list<Constraint>                 $constraints the collection field's own
     *
     * @return list<Constraint>
     */
    private function entryConstraints(?FormBuilderInterface $entry, array $constraints): array
    {
        $held = null === $entry ? [] : $this->collectConstraints($entry->getFormConfig(), null);
        foreach (HeldConstraints::always($constraints) as $constraint) {
            if ($constraint instanceof All) {
                $nested = \is_array($constraint->constraints) ? $constraint->constraints : [$constraint->constraints];
                $held = [...$held, ...array_values(array_filter($nested, static fn(mixed $one): bool => $one instanceof Constraint && HeldConstraints::validatedByDefault($one)))];
            }
        }

        return $held;
    }

    /**
     * The values a choice field accepts, as its built form reads a submitted one: the values of
     * its choice list. They are what a request sends whatever the choices themselves are — an
     * enum's backing value under Symfony's EnumType, whatever `choice_value` reads off each
     * choice where a type sets it (an id, say), the choices themselves in a plain list — so
     * reading them here answers every type in the choice family the same way, its own included.
     *
     * An `Assert\Choice` with explicit `choices` narrows them — one the field is always held to,
     * beside its others or in a Sequentially or a Compound ({@see HeldConstraints::always()}). Its
     * choices are model data, an enum case rather than its id, so they become values through the
     * same choice list; with `match: false` they are the ones refused instead.
     *
     * Null for a field with no choice list, and for one that loads its choices: a loader may
     * query (EntityType's does), and inspection never runs one.
     *
     * @param FormConfigInterface<mixed> $config
     * @param list<Constraint>           $constraints
     *
     * @return list<string>|null
     */
    private function resolveChoiceValues(FormConfigInterface $config, array $constraints): ?array
    {
        if (!$config->hasAttribute('choice_list') || null !== $this->resolveOption($config, 'choice_loader')) {
            return null;
        }

        $choiceList = $config->getAttribute('choice_list');
        if (!$choiceList instanceof ChoiceListInterface) {
            return null;
        }

        $values = $choiceList->getValues();
        foreach (HeldConstraints::always($constraints) as $constraint) {
            if (!$constraint instanceof Choice || null === $constraint->choices) {
                continue;
            }

            $named = $choiceList->getValuesForChoices($constraint->choices);
            $values = $constraint->match ? array_intersect($values, $named) : array_diff($values, $named);
        }

        return array_values($values);
    }

    /**
     * The constraints a submitted value is held to: the field's own `constraints` option, then
     * the constraint attributes on the data-class property it binds. A constraint confined to
     * groups other than Default is left out — a request may never be validated against it.
     *
     * @param FormConfigInterface<mixed> $config
     * @param ReflectionProperty|null    $property the data-class property the field binds, if any
     *
     * @return list<Constraint>
     */
    private function collectConstraints(FormConfigInterface $config, ?ReflectionProperty $property): array
    {
        $constraints = [];
        $option = $this->resolveOption($config, 'constraints');
        foreach (\is_array($option) ? $option : [$option] as $constraint) {
            if ($constraint instanceof Constraint) {
                $constraints[] = $constraint;
            }
        }

        if (null !== $property) {
            $constraints = [...$constraints, ...$this->propertyConstraints($property)];
        }

        return array_values(array_filter($constraints, HeldConstraints::validatedByDefault(...)));
    }

    /**
     * @return list<Constraint>
     */
    private function propertyConstraints(ReflectionProperty $property): array
    {
        $constraints = [];
        foreach ($property->getAttributes(Constraint::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            try {
                $constraints[] = $attribute->newInstance();
            } catch (Throwable $exception) {
                // The application cannot validate against it either; named here rather than
                // left to abandon a PHPStan run, as a form that cannot be built is.
                throw new RuntimeException(\sprintf(
                    'Constraint #[%s] on "%s::$%s" cannot be read for inspection: %s',
                    $attribute->getName(),
                    $property->getDeclaringClass()->getName(),
                    $property->getName(),
                    $exception->getMessage(),
                ), previous: $exception);
            }
        }

        return $constraints;
    }

    /**
     * The property a field's value is written to, when its path names one directly on the data
     * class. A deeper path (`address.street`) or an index (`[street]`) binds no property of its own.
     */
    private function boundProperty(?string $ownerClass, string $propertyPath): ?ReflectionProperty
    {
        if (null === $ownerClass || !class_exists($ownerClass) || 1 !== preg_match('/^\w+$/', $propertyPath) || !property_exists($ownerClass, $propertyPath)) {
            return null;
        }

        return new ReflectionProperty($ownerClass, $propertyPath);
    }

    /**
     * A collection's entries do not exist on the builder — only a prototype does — so its entry type
     * is built on its own, with the collection's `entry_options` as each entry is: the emitters get
     * the shape of an item, not an opaque entry class, and what an item is held to. Those its type
     * doesn't define are left out: they belong to a form extension the application loads and
     * inspection doesn't (`csrf_protection`, a documentation bundle's), and say nothing it reads.
     *
     * @param FormConfigInterface<mixed> $config
     *
     * @return FormBuilderInterface<mixed>|null null for a field that is no collection
     */
    private function entryBuilder(FormConfigInterface $config): ?FormBuilderInterface
    {
        $entryTypeClass = $this->resolveEntryTypeClass($config);
        if (null === $entryTypeClass) {
            return null;
        }

        $entryOptions = $this->resolveOption($config, 'entry_options');
        $entryOptions = \is_array($entryOptions) ? $entryOptions : [];

        try {
            if (null !== $this->registry) {
                $defined = $this->registry->getType($entryTypeClass)->getOptionsResolver()->getDefinedOptions();
                $entryOptions = array_intersect_key($entryOptions, array_flip($defined));
            }

            return $this->formFactory->createNamedBuilder(
                name: '__entry__',
                type: $entryTypeClass,
                options: $entryOptions,
            );
        } catch (OptionsException|FormException $exception) {
            // Same fact as a top-level form that cannot be built, one level down: a collection
            // whose entry type needs options — an EnumType without its `class`, say — cannot be
            // inspected either. This build sits inside collectFields(), which runs *outside*
            // inspect()'s try, so without catching it here the exception leaves the inspector
            // entirely and PHPStan reports an internal error and abandons the whole run rather
            // than reporting the one form.
            throw new RuntimeException(\sprintf(
                'Collection entry type "%s" cannot be built for inspection: %s Give the option a default, or keep the form off the contract surface.',
                $entryTypeClass,
                $exception->getMessage(),
            ), previous: $exception);
        }
    }

    /**
     * @param class-string $formClass
     * @phpstan-assert class-string<FormTypeInterface<mixed>> $formClass
     */
    private function assertFormTypeInterface(string $formClass): void
    {
        if (is_a($formClass, FormTypeInterface::class, true)) {
            return;
        }

        throw new RuntimeException(\sprintf(
            'Form class "%s" must implement "%s".',
            $formClass,
            FormTypeInterface::class,
        ));
    }

    /**
     * @param class-string $formClass
     * @phpstan-assert class-string<ContractFormType<object>> $formClass
     */
    private function assertContractFormType(string $formClass): void
    {
        if (is_a($formClass, ContractFormType::class, true)) {
            return;
        }

        throw new RuntimeException(\sprintf(
            'Form class "%s" must implement "%s" to participate in TypeBridge request contracts.',
            $formClass,
            ContractFormType::class,
        ));
    }

    private function isFrameworkFormType(string $formClass): bool
    {
        return str_starts_with($formClass, 'Symfony\\Component\\Form\\');
    }

    /**
     * @param FormConfigInterface<mixed> $config
     */
    private function requiresContractMarker(FormConfigInterface $config): bool
    {
        return $config->getCompound() || null !== $config->getDataClass();
    }

    /**
     * @param FormConfigInterface<mixed> $config
     */
    private function resolvePropertyPath(FormConfigInterface $config, string $default): string
    {
        $propertyPath = $config->getPropertyPath();
        if (null === $propertyPath) {
            return $default;
        }

        return (string) $propertyPath;
    }

    /**
     * @param FormConfigInterface<mixed> $config
     */
    private function resolveEntryTypeClass(FormConfigInterface $config): ?string
    {
        $entryType = $this->resolveStringOption($config, 'entry_type');
        if (null === $entryType || !class_exists($entryType)) {
            return $entryType;
        }

        return is_a($entryType, FormTypeInterface::class, true) ? $entryType : null;
    }

    /**
     * @param FormBuilderInterface<mixed>|null $entry
     */
    private static function entryDataClass(?FormBuilderInterface $entry): ?string
    {
        /** @var class-string|null $dataClass */
        $dataClass = $entry?->getOption('data_class');

        return $dataClass;
    }

    /**
     * Whether the field holds a *list* of its leaf type rather than one of it.
     *
     * That is what `multiple` means on ChoiceType and on everything built on it — EnumType,
     * CountryType, an application's own choice-based type. It is also an ordinary option
     * name, which any form type is free to define for something else entirely, so the
     * option alone is not the question: the type has to be in the choice family, which is
     * a question about the *resolved* type's parent chain. EnumType does not extend
     * ChoiceType, it names it as its parent, so a class check answers it wrongly.
     *
     * @param FormConfigInterface<mixed> $config
     */
    private function isMultipleChoice(FormConfigInterface $config): bool
    {
        return $config->hasOption('multiple') && true === $config->getOption('multiple') && self::isChoice($config);
    }

    /**
     * Whether the field is of the choice family: ChoiceType, or a type built on it.
     *
     * @param FormConfigInterface<mixed> $config
     */
    private static function isChoice(FormConfigInterface $config): bool
    {
        for ($type = $config->getType(); null !== $type; $type = $type->getParent()) {
            if ($type->getInnerType() instanceof ChoiceType) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param FormConfigInterface<mixed> $config
     */
    private function resolveStringOption(FormConfigInterface $config, string $option): ?string
    {
        $value = $this->resolveOption($config, $option);

        return \is_string($value) ? $value : null;
    }

    /**
     * An option's value, or null where the field's type does not define it.
     *
     * @param FormConfigInterface<mixed> $config
     */
    private function resolveOption(FormConfigInterface $config, string $option): mixed
    {
        return $config->hasOption($option) ? $config->getOption($option) : null;
    }
}
