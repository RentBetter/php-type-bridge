<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form;

use PTGS\TypeBridge\Contract\ContractFormType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Input\CreateProjectRequestData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @implements ContractFormType<CreateProjectRequestData>
 */
final class CreateProjectRequestType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('project', CreateProjectInputType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateProjectRequestData::class,
        ]);
    }
}
