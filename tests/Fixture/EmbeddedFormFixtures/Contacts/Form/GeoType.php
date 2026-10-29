<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input\GeoData;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<GeoData>
 */
final class GeoType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('lat', TextType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => GeoData::class]);
    }
}
