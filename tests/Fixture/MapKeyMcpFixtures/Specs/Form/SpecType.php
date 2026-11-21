<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MapKeyMcpFixtures\Specs\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\MapKeyMcpFixtures\Specs\Input\SpecData;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Sent whole: its data class's shape says what it takes.
 *
 * @extends AbstractFormType<SpecData>
 */
final class SpecType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults(['data_class' => SpecData::class]);
    }
}
