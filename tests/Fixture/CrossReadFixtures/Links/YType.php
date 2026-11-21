<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CrossReadFixtures\Links;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<YInput>
 */
final class YType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => YInput::class, 'compound' => true]);
    }
}
