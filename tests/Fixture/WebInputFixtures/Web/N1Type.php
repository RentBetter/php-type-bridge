<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\WebInputFixtures\Web;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<N1Data>
 */
final class N1Type extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('n2', TextType::class, ['required' => false])->add('n3', TextType::class, ['required' => false])->add('n4', TextType::class, ['required' => false])->add('n5', TextType::class, ['required' => false])->add('n6', TextType::class, ['required' => false])->add('n7', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => N1Data::class]);
    }
}
