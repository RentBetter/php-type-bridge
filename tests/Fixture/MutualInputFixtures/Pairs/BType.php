<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MutualInputFixtures\Pairs;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<BData>
 */
final class BType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('a', TextType::class, ['required' => false])->add('y', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => BData::class]);
    }
}
