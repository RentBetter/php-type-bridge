<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QueryFieldContractFixtures\Prices;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<PriceFilter>
 */
final class PriceFilterType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('price', PriceType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => PriceFilter::class]);
    }
}
