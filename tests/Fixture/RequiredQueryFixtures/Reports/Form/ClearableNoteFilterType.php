<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form;

use PTGS\TypeBridge\Form\AbstractFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input\ClearableNoteFilterData;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFilterType<ClearableNoteFilterData>
 */
final class ClearableNoteFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('note', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => ClearableNoteFilterData::class,
        ]);
    }
}
