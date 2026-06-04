<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Form;

use PTGS\TypeBridge\Contract\ContractFormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Typed form base for JSON body forms (POST/PUT).
 *
 * Subclasses MUST override configureOptions to set data_class, and provide a
 * phpstan-type annotation: `@extends AbstractFormType<MyData>`
 *
 * @template T of object
 *
 * @extends AbstractType<T>
 *
 * @implements ContractFormType<T>
 */
abstract class AbstractFormType extends AbstractType implements ContractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
        ]);
    }
}
