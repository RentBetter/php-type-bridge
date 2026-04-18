<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form;

use PTGS\TypeBridge\Contract\ContractFormType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input\ProjectSettingsData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @implements ContractFormType<ProjectSettingsData>
 */
final class ProjectSettingsType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('notifyOwner', CheckboxType::class)
            ->add('timezone', TextType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectSettingsData::class,
        ]);
    }
}
