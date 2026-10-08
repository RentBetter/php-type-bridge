<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Form;

use PTGS\TypeBridge\Contract\ContractFormType;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input\LabelOperations;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Reads what is sent whole, before Symfony maps anything: "a name, or the fields to make one, one
 * or many" is not a shape child fields can bind. So the form has no children to show.
 *
 * @implements ContractFormType<LabelOperations>
 */
final class LabelsType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $event->getForm()->setData(new LabelOperations());
            $event->setData([]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'allow_extra_fields' => true,
            'data_class' => LabelOperations::class,
        ]);
    }
}
