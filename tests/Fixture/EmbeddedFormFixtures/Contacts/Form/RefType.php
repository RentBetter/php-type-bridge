<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input\RefData;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Compound with no fields: it reads what is sent whole.
 *
 * @extends AbstractFormType<RefData>
 */
final class RefType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => RefData::class, 'compound' => true]);
    }
}
