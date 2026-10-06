<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Form;

use PTGS\TypeBridge\Form\AbstractFilterType;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input\ListChecksFilterData;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Enum\Area;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Enum\Severity;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Form\IdEnumType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One field per member of the choice family: an id-matching type, Symfony's EnumType over a
 * string- and an int-backed enum, a plain list of choices, and one whose choices are loaded —
 * by a loader that must never run while the form is only being inspected.
 *
 * @extends AbstractFilterType<ListChecksFilterData>
 */
final class ListChecksFilterType extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('area', IdEnumType::class, ['enum' => Area::class, 'required' => false])
            ->add('areas', EnumType::class, ['class' => Area::class, 'multiple' => true, 'required' => false])
            ->add('rank', EnumType::class, ['class' => Severity::class, 'required' => false])
            ->add('level', ChoiceType::class, ['choices' => ['Low' => 'low', 'High' => 'high'], 'required' => false])
            ->add('owner', ChoiceType::class, [
                'choice_loader' => new CallbackChoiceLoader(static fn (): never => throw new \LogicException('Inspection ran a choice loader.')),
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => ListChecksFilterData::class,
        ]);
    }
}
