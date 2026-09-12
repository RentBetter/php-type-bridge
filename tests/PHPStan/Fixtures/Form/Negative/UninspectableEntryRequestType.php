<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative;

use PTGS\TypeBridge\Contract\ContractFormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The same problem as RequiresOptionRequestType, one level down: the form itself builds fine,
 * but its collection's *entry* type cannot — an EnumType with no `class`.
 *
 * That build happens inside collectFields(), which runs outside inspect()'s try, so it used to
 * escape the inspector entirely and PHPStan reported an internal error and abandoned the whole
 * run. It has to be a finding about this form instead.
 *
 * @implements ContractFormType<OtherRequestData>
 */
final class UninspectableEntryRequestType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('statuses', CollectionType::class, [
            'entry_type' => EnumType::class,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OtherRequestData::class]);
    }
}
