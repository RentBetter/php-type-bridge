<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative;

use PTGS\TypeBridge\Contract\ContractFormType;
use PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Positive\AdvancedState;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @implements ContractFormType<ScalarMultipleEnumRequestData>
 */
final class ScalarMultipleEnumRequestType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('states', EnumType::class, [
            'class' => AdvancedState::class,
            'multiple' => true,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ScalarMultipleEnumRequestData::class,
        ]);
    }
}
