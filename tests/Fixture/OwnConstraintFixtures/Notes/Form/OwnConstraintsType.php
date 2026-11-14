<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnConstraintFixtures\Notes\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\OwnConstraintFixtures\Notes\Input\OwnConstraintsData;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;

/**
 * A field's own NotBlank at the top of its `constraints`, one a Sequentially holds, and one in a
 * group of its own.
 *
 * @extends AbstractFormType<OwnConstraintsData>
 */
final class OwnConstraintsType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plain', TextType::class, ['constraints' => [new NotBlank()]])
            ->add('nested', TextType::class, ['constraints' => [new Sequentially([new NotBlank(), new Length(max: 5)])]])
            ->add('grouped', TextType::class, ['constraints' => [new NotBlank(groups: ['create'])]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults(['data_class' => OwnConstraintsData::class]);
    }
}
