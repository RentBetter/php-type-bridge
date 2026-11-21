<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedSelfFixtures\Orders;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<OrderData>
 */
final class OrderType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('address', AddressType::class, ['required' => false])->add('hidden', HiddenType::class, ['required' => false])->add('maybe', MaybeType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => OrderData::class]);
    }
}
