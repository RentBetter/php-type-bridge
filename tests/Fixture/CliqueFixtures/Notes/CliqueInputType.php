<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CliqueFixtures\Notes;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Read whole.
 *
 * @extends AbstractFormType<Clique1>
 */
final class CliqueInputType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => Clique1::class, 'compound' => true]);
    }
}
