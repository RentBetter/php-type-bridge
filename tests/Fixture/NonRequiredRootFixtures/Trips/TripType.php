<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NonRequiredRootFixtures\Trips;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

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
            // No key of TripData's: held to its form's `required`, which TripType's makes false.
            ->add('ref', TextType::class)
            ->add('legs', CollectionType::class, ['entry_type' => LegType::class, 'allow_add' => true])
            // A tag sent blank is what its `empty_data` makes of it, which asks whether it is required.
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'entry_options' => ['empty_data' => static fn (FormInterface $form): ?string => $form->isRequired() ? 'none' : null, 'constraints' => [new NotNull()]], 'allow_add' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => TripData::class, 'required' => false]);
    }
}
