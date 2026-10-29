<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Fields named by number, as a year: PHP keys them as integers.
 *
 * @extends AbstractFormType<array<string, int|null>>
 */
final class YearlyTotalsType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('2025', IntegerType::class)->add('2026', IntegerType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => null]);
    }
}
