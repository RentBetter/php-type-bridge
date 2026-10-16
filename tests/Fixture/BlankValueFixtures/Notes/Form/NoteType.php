<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Input\NoteData;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * What a blank value becomes: null by default; an empty string where a text field's `empty_data`
 * is '' (TextType then keeps it a string); still null for a number, whatever its `empty_data`.
 * And what a collection holds each entry to: its `All` and its `entry_options` constraints.
 *
 * @extends AbstractFormType<NoteData>
 */
final class NoteType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('code', TextType::class, ['empty_data' => ''])
            ->add('count', IntegerType::class, ['empty_data' => ''])
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'allow_add' => true, 'entry_options' => ['constraints' => [new NotBlank()]]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => NoteData::class]);
    }
}
