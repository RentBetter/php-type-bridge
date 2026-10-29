<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ImportedAliasNameFixtures\Lists\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\ImportedAliasNameFixtures\Lists\Input\ListFilterData;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<ListFilterData>
 */
final class ListFilterType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('filter', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => ListFilterData::class]);
    }
}
