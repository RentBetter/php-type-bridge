<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnValidFixtures\Boxes;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * @extends AbstractFormType<ShellData>
 */
final class ShellType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('lid', LidType::class, ['constraints' => [new Valid()]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => ShellData::class]);
    }
}
