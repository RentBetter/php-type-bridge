<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LocaleListFixtures\Prefs;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A form whose listener may rewrite what its fields are sent, entries of its collections among them.
 *
 * @extends AbstractFormType<InnerData>
 */
final class InnerType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('labels', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true])
            ->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {});
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => InnerData::class]);
    }
}
