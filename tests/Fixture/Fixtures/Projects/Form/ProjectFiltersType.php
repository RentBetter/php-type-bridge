<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form;

use PTGS\TypeBridge\Contract\ContractFormType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input\ProjectFiltersData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @implements ContractFormType<ProjectFiltersData>
 */
final class ProjectFiltersType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('search', TextType::class, ['required' => false])
            ->add('page', IntegerType::class, ['required' => false])
            ->add('archived', CheckboxType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectFiltersData::class,
        ]);
    }
}
