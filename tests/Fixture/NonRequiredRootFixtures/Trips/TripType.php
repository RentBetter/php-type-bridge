<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NonRequiredRootFixtures\Trips;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Not required, as a form whose fields may all be left out is: every form it holds is not either,
 * whatever its own `required` says.
 *
 * @extends AbstractFormType<TripData>
 */
final class TripType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('leg', LegType::class)
            ->add('legs', CollectionType::class, ['entry_type' => LegType::class, 'allow_add' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => TripData::class, 'required' => false]);
    }
}
