<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PartlyQualifiedFixtures\Orders;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\PartlyQualifiedFixtures\Orders\Input\AddressType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<OrderInput>
 */
final class OrderInputType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('address', AddressType::class)->add('billing', AddressType::class)->add('shipping', AddressType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => OrderInput::class]);
    }
}
