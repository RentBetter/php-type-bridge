<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Input\NoteData;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * A collection whose entry options include one from a form extension the application loads and
 * inspection doesn't (`csrf_protection`, Symfony's CSRF extension). With `allow_add` off there is no
 * prototype, so nothing has built an entry with them yet.
 *
 * @extends AbstractFormType<NoteData>
 */
final class AliasListType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('aliases', CollectionType::class, [
            'entry_type' => TextType::class,
            'entry_options' => ['csrf_protection' => false, 'constraints' => [new NotBlank()]],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => NoteData::class]);
    }
}
