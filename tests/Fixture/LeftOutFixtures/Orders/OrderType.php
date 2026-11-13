<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LeftOutFixtures\Orders;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractFormType<OrderData>
 */
final class OrderType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, ['required' => false])
            ->add('qty', IntegerType::class, ['required' => false, 'empty_data' => '0'])
            ->add('note', TextType::class, ['required' => false, 'empty_data' => ''])
            ->add('label', TextType::class, ['required' => false])
            ->add('memo', TextType::class, ['required' => false, 'mapped' => false, 'constraints' => [new NotBlank()]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => OrderData::class]);
    }
}
