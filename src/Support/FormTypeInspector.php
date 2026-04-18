<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PTGS\TypeBridge\Contract\ContractFormType;
use PTGS\TypeBridge\Model\CollectedFormField;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Forms;
use RuntimeException;

final class FormTypeInspector
{
    private FormFactoryInterface $formFactory;

    public function __construct(?FormFactoryInterface $formFactory = null)
    {
        $this->formFactory = $formFactory ?? Forms::createFormFactory();
    }

    /**
     * @param class-string<FormTypeInterface> $formClass
     * @return array{dataClass: ?class-string, fields: list<CollectedFormField>}
     */
    public function inspect(string $formClass): array
    {
        if (!class_exists($formClass)) {
            throw new RuntimeException(\sprintf('Form class "%s" was not found.', $formClass));
        }

        if (!is_a($formClass, FormTypeInterface::class, true)) {
            throw new RuntimeException(\sprintf(
                'Form class "%s" must implement "%s".',
                $formClass,
                FormTypeInterface::class,
            ));
        }

        $this->assertContractFormType($formClass);

        $builder = $this->formFactory->createBuilder($formClass);

        /** @var class-string|null $dataClass */
        $dataClass = $builder->getOption('data_class');

        return [
            'dataClass' => $dataClass,
            'fields' => $this->collectFields($builder),
        ];
    }

    /**
     * @return list<CollectedFormField>
     */
    private function collectFields(FormBuilderInterface $builder): array
    {
        $fields = [];

        foreach ($builder->all() as $name => $child) {
            $config = $child->getFormConfig();
            $formTypeClass = $config->getType()->getInnerType()::class;
            if (!$this->isFrameworkFormType($formTypeClass) && $this->requiresContractMarker($config)) {
                $this->assertContractFormType($formTypeClass);
            }

            $fields[] = new CollectedFormField(
                name: $name,
                formTypeClass: $formTypeClass,
                required: $config->getRequired(),
                mapped: $config->getMapped(),
                compound: $config->getCompound(),
                dataClass: $config->getDataClass(),
                propertyPath: $this->resolvePropertyPath($config, $name),
                entryTypeClass: $this->resolveEntryTypeClass($config),
                entryDataClass: $this->resolveEntryDataClass($config),
                enumClass: $this->resolveStringOption($config, 'class'),
                input: $this->resolveStringOption($config, 'input'),
                hasModelTransformers: [] !== $config->getModelTransformers(),
                hasViewTransformers: [] !== $config->getViewTransformers(),
                children: $this->collectFields($child),
            );
        }

        return $fields;
    }

    /**
     * @param class-string<FormTypeInterface> $formClass
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

    private function requiresContractMarker(FormConfigInterface $config): bool
    {
        return $config->getCompound() || null !== $config->getDataClass();
    }

    private function resolvePropertyPath(FormConfigInterface $config, string $default): string
    {
        $propertyPath = $config->getPropertyPath();
        if (null === $propertyPath) {
            return $default;
        }

        return (string) $propertyPath;
    }

    private function resolveEntryTypeClass(FormConfigInterface $config): ?string
    {
        $entryType = $this->resolveStringOption($config, 'entry_type');
        if (null === $entryType || !class_exists($entryType)) {
            return $entryType;
        }

        return is_a($entryType, FormTypeInterface::class, true) ? $entryType : null;
    }

    private function resolveEntryDataClass(FormConfigInterface $config): ?string
    {
        $entryTypeClass = $this->resolveEntryTypeClass($config);
        if (null === $entryTypeClass) {
            return null;
        }

        $entryBuilder = $this->formFactory->createNamedBuilder(
            name: '__entry__',
            type: $entryTypeClass,
        );

        /** @var class-string|null $dataClass */
        $dataClass = $entryBuilder->getOption('data_class');

        return $dataClass;
    }

    private function resolveStringOption(FormConfigInterface $config, string $option): ?string
    {
        if (!$config->hasOption($option)) {
            return null;
        }

        $value = $config->getOption($option);

        return \is_string($value) ? $value : null;
    }
}
