<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Input\TreeData;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A tree whose children are trees: the root form's own type, nested.
 *
 * @extends AbstractFormType<TreeData>
 */
final class TreeType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['constraints' => [new Assert\NotBlank()]])
            ->add('children', CollectionType::class, ['entry_type' => self::class, 'allow_add' => true, 'prototype' => false, 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => TreeData::class]);
    }
}
