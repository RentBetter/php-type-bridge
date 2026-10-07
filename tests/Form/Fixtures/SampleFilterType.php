<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Form\Fixtures;

use PTGS\TypeBridge\Form\AbstractFilterType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * SampleType's fields as query-string filters, for RequestFormProcessor::processQueryForm().
 * A non-numeric `count` fails its integer transformation, as in the body fixture.
 *
 * @extends AbstractFilterType<SampleData>
 */
final class SampleFilterType extends AbstractFilterType
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
