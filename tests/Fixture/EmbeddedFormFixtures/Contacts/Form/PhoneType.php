<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input\PhoneData;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<PhoneData>
 */
final class PhoneType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('number', TextType::class)->add('label', TextType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => PhoneData::class]);
    }
}
