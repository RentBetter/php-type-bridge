<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueQueryAliasFixtures\Prices;

use PTGS\TypeBridge\Form\AbstractFilterType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFilterType<PriceData>
 */
final class PriceType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class)->add('price', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => PriceData::class]);
    }
}
