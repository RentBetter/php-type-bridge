<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\RecursiveFormFixtures\Outlines\Input\BookData;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A book with one section embedded whole, not in a list — a section that holds sections of its own.
 *
 * @extends AbstractFormType<BookData>
 */
final class BookType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('appendix', SectionType::class, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => BookData::class]);
    }
}
