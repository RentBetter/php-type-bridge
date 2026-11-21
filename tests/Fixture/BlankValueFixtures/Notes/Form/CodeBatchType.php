<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form;

use PTGS\TypeBridge\Form\AbstractFormType;
use PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Input\CodeBatchData;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * No listener of its own: an embedded form's rewrites its fields, an entry type's each entry's, a
 * collection's own each entry's too — and a collection's resizing rewrites none.
 *
 * @extends AbstractFormType<CodeBatchData>
 */
final class CodeBatchType extends AbstractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', UppercasedCodeType::class)
            ->add('codes', CollectionType::class, ['entry_type' => UppercasedCodeType::class, 'allow_add' => true])
            ->add('plainCodes', CollectionType::class, ['entry_type' => PlainCodeType::class, 'allow_add' => true])
            ->add(
                $builder->create('listedCodes', CollectionType::class, ['entry_type' => PlainCodeType::class, 'allow_add' => true])
                    ->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
                        $data = $event->getData();
                        if (\is_array($data)) {
                            $event->setData(array_values($data));
                        }
                    }),
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(['data_class' => CodeBatchData::class]);
    }
}
