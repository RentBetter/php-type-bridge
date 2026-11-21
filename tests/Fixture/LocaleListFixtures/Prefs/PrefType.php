<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LocaleListFixtures\Prefs;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<PrefData>
 */
final class PrefType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('locales', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true])
            // Read through a transformer: what is sent isn't what the literals describe.
            ->add('codes', CollectionType::class, ['entry_type' => NumberType::class, 'allow_add' => true])
            // A listener of the collection's own may rewrite each entry.
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true])
            ->add('inner', InnerType::class);
        $builder->get('tags')->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {});
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => PrefData::class]);
    }
}
