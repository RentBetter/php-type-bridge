<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input\ContactData;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * `address` is required, so left out Symfony makes it a new AddressData; `billing` is not, so
 * left out it is null.
 *
 * @extends AbstractFormType<ContactData>
 */
final class ContactType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('address', AddressType::class)
            ->add('billing', AddressType::class, ['required' => false])
            ->add('ref', RefType::class, ['required' => false])
            ->add('postal', PostalType::class)
            ->add('phones', CollectionType::class, ['entry_type' => PhoneType::class, 'allow_add' => true])
            ->add('refs', CollectionType::class, ['entry_type' => RefType::class, 'allow_add' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => ContactData::class]);
    }
}
