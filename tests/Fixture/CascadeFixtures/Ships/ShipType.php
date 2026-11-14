<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CascadeFixtures\Ships;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Form\PostalType;

/**
 * @extends AbstractFormType<ShipData>
 */
final class ShipType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('postal', PostalType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => ShipData::class]);
    }
}
