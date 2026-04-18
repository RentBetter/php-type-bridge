<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingDataClass\Form;

use PTGS\TypeBridge\Contract\ContractFormType;
use PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingDataClass\Input\MissingDataClassRequestData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @implements ContractFormType<MissingDataClassRequestData>
 */
final class MissingDataClassRequestType extends AbstractType implements ContractFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('title', TextType::class);
    }
}
