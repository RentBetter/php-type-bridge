<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Input\SectionData;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A section, holding sections of its own to any depth: a collection of this type.
 *
 * @extends AbstractFormType<SectionData>
 */
final class SectionType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('heading', TextType::class, ['constraints' => [new Assert\NotBlank()]])
            ->add('sections', CollectionType::class, ['entry_type' => self::class, 'allow_add' => true, 'prototype' => false, 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => SectionData::class]);
    }
}
