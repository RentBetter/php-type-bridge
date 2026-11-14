<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EnumSelfKeyFixtures\Notes;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFormType<NoteInput>
 */
final class NoteInputType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => NoteInput::class, 'compound' => true]);
    }
}
