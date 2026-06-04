<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Generics\Fixtures;

use PTGS\TypeBridge\Contract\ContractFormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Proves a form can declare AbstractType<TData> and ContractFormType<TData> together
 * with no generics conflict and no suppression pragma — the payoff of forwarding the
 * template on ContractFormType.
 *
 * @extends AbstractType<WidgetData>
 *
 * @implements ContractFormType<WidgetData>
 */
final class WidgetType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', TextType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'data_class' => WidgetData::class,
        ]);
    }
}
