<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input\RecordVerdictData;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Enum\Severity;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Form\IdEnumType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A verdict is one of three statuses, narrowed on the form; its floor is any status but
 * SKIPPED, narrowed on the data class. The rest are bounded on one or the other, or both.
 *
 * @extends AbstractFormType<RecordVerdictData>
 */
final class RecordVerdictType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', IdEnumType::class, [
                'enum' => Severity::class,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\Choice(choices: [Severity::OK, Severity::WARNING, Severity::ERROR]),
                ],
            ])
            ->add('floor', IdEnumType::class, ['enum' => Severity::class, 'required' => false])
            ->add('retries', IntegerType::class, ['required' => false, 'constraints' => [new Assert\PositiveOrZero()]])
            ->add('ratio', NumberType::class, ['required' => false])
            ->add('note', TextType::class, ['required' => false, 'constraints' => [new Assert\Length(max: 255)]])
            ->add('readings', CollectionType::class, [
                'entry_type' => ReadingType::class,
                'allow_add' => true,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => RecordVerdictData::class,
        ]);
    }
}
