<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LargeInputFixtures\Items;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<BigData>
 */
final class BigType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => BigData::class, 'compound' => true]);
    }
}
