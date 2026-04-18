<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Positive;

use PTGS\TypeBridge\Contract\ContractFormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @implements ContractFormType<AdvancedRequestData>
 */
final class AdvancedRequestType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('enabled', BooleanType::class, ['required' => false])
            ->add('settings', JsonType::class, ['required' => false])
            ->add('startsAt', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('tags', CollectionType::class, [
                'required' => false,
                'entry_type' => TextType::class,
                'allow_add' => true,
            ])
            ->add('state', EnumType::class, [
                'class' => AdvancedState::class,
            ])
            ->add('assignee', TextType::class, [
                'required' => false,
                'property_path' => 'ownerId',
            ])
            ->add('owner', AdvancedOwnerType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AdvancedRequestData::class,
        ]);
    }
}
