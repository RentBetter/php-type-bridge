<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Input\CommentData;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A comment and its replies, which are comments with no replies of their own: its options stop the
 * nesting one level down.
 *
 * @extends AbstractFormType<CommentData>
 */
final class CommentType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('body', TextType::class, ['constraints' => [new Assert\NotBlank()]]);
        if ($options['with_replies']) {
            $builder->add('replies', CollectionType::class, [
                'entry_type' => self::class,
                'entry_options' => ['with_replies' => false],
                'allow_add' => true,
                'prototype' => false,
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => CommentData::class, 'with_replies' => true]);
        $resolver->setAllowedTypes('with_replies', 'bool');
    }
}
