<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueBodyNoToolFixtures\Prices;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Read whole: its data class's contract is all that says what it takes.
 *
 * @extends AbstractFormType<FeeData>
 */
final class FeeType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => FeeData::class, 'compound' => true]);
    }
}
