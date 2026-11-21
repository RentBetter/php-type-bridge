<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Its `label`'s `empty_data` and each of its `tags`' is application code, which counts each time it
 * runs.
 *
 * @extends AbstractFormType<CountedBlankData>
 */
final class CountedBlankType extends AbstractFormType
{
    public static int $runs = 0;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $count = static function (): string {
            ++self::$runs;

            return 'none';
        };
        $builder
            ->add('label', TextType::class, ['empty_data' => $count])
            ->add('tags', CollectionType::class, ['entry_type' => TextType::class, 'entry_options' => ['empty_data' => $count]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => CountedBlankData::class]);
    }
}
