<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form;

use PTGS\TypeBridge\Contract\ContractFormType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Enum\ProjectStatus;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input\CreateProjectInputData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @implements ContractFormType<CreateProjectInputData>
 */
final class CreateProjectInputType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('clientId', TextType::class)
            ->add('status', EnumType::class, ['class' => ProjectStatus::class])
            ->add('settings', ProjectSettingsType::class)
            ->add('nickname', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateProjectInputData::class,
        ]);
    }
}
