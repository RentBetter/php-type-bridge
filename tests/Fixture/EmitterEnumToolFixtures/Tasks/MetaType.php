<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmitterEnumToolFixtures\Tasks;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Compound with no fields: it reads what is sent whole.
 *
 * @extends AbstractFormType<MetaData>
 */
final class MetaType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => MetaData::class, 'compound' => true]);
    }
}
