<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QueryFieldContractFixtures\Prices;

use PTGS\TypeBridge\Form\AbstractFormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Reads the value sent whole: what it takes is PriceData's `_self`, which only a tool reads.
 *
 * @extends AbstractFormType<PriceData>
 */
final class PriceType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => PriceData::class, 'compound' => true]);
    }
}
