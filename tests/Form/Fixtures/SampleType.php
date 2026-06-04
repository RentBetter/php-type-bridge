<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Form\Fixtures;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Concrete contract form extending the bundle's AbstractFormType, used to exercise
 * RequestFormProcessor. Submitting a non-numeric string for `count` triggers an
 * integer transformation failure, so the form is invalid without requiring the
 * Validator component to be installed.
 *
 * @extends AbstractFormType<SampleData>
 */
final class SampleType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('count', IntegerType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => SampleData::class,
        ]);
    }
}
