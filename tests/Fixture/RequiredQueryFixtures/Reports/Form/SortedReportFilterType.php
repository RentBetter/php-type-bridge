<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form;

use PTGS\TypeBridge\Form\AbstractFilterType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input\SortedReportFilterData;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractFilterType<SortedReportFilterData>
 */
final class SortedReportFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('sort', TextType::class, ['required' => false])->add('product', TextType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => SortedReportFilterData::class,
        ]);
    }
}
