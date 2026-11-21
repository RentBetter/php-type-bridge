<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnValidFixtures\Boxes;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * Each item's own `constraints` cascade into it, though its property doesn't.
 *
 * @extends AbstractFormType<BoxData>
 */
final class BoxType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('items', CollectionType::class, ['entry_type' => ItemType::class, 'entry_options' => ['constraints' => [new Valid()]], 'allow_add' => true])
            ->add('shell', ShellType::class)
            // Its entries not required: one left with every field blank is null, and one with a field
            // sent has its `lid` null where it is left out.
            ->add('bags', CollectionType::class, ['entry_type' => BagType::class, 'entry_options' => ['required' => false, 'constraints' => [new Valid()]], 'allow_add' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => BoxData::class]);
    }
}
