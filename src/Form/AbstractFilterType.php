<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Form;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Typed form base for GET query-parameter filter forms.
 *
 * - CSRF is disabled (read-only GET requests)
 * - `allow_extra_fields` is true so unrelated query params are silently ignored
 * - method is GET
 *
 * Subclasses define fields for each recognised filter param and provide:
 *   `@extends AbstractFilterType<MyFilterData>`
 *
 * @template T of object
 *
 * @extends AbstractFormType<T>
 */
abstract class AbstractFilterType extends AbstractFormType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'method' => 'GET',
            'allow_extra_fields' => true,
        ]);
    }

    /**
     * Empty prefix — query params map directly to field names without a wrapper key.
     */
    public function getBlockPrefix(): string
    {
        return '';
    }
}
