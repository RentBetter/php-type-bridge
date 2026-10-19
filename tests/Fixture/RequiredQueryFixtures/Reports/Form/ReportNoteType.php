<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input\ReportNoteData;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<ReportNoteData>
 */
final class ReportNoteType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('note', TextType::class)
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true])
            ->add('code', TextType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => ReportNoteData::class,
        ]);
    }
}
