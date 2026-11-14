<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SharedOpaqueAliasFixtures\Prices\Form;

use PTGS\TypeBridge\Form\AbstractFilterType;
use PTGS\TypeBridge\Tests\Fixture\SharedOpaqueAliasFixtures\Prices\Input\PickPricesData;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFilterType<PickPricesData>
 */
final class PickPricesType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('amount', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults(['data_class' => PickPricesData::class]);
    }
}
