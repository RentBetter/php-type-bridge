<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative;

use PTGS\TypeBridge\Contract\ContractFormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A contract form that cannot be built without an option — the shape of a form that scopes
 * its choices to an entity handed in at processForm() time. Inspection builds forms with no
 * options, so this one must be reported as un-inspectable, not crash the analysis.
 *
 * @implements ContractFormType<OtherRequestData>
 */
final class RequiresOptionRequestType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => OtherRequestData::class,
        ]);
        $resolver->setRequired('project');
    }
}
