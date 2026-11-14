<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BoundOrderFixtures\Notes;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<BInput>
 */
final class BType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => BInput::class, 'compound' => true]);
    }
}
