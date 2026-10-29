<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Form;

use PTGS\TypeBridge\Form\AbstractFilterType;
use PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Input\ScopedReportFilterData;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFilterType<ScopedReportFilterData>
 */
final class ScopedReportFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('product', TextType::class)->add('period', TextType::class, ['required' => false, 'trim' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => ScopedReportFilterData::class,
        ]);
    }
}
