<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Contract;

use Symfony\Component\Form\FormTypeInterface;

/**
 * Marker interface for Symfony form types that participate in TypeBridge request contracts.
 *
 * FormTypeInterface is treated as generic by phpstan/phpstan-symfony's stubs, so the
 * data-type variable is forwarded to it here. That lets a consumer (and the bundle's own
 * AbstractFormType) write "@extends AbstractType of TData, @implements ContractFormType of
 * TData" and have the generic flow through cleanly, with no generics conflict and no
 * suppression pragma needed on the subclass.
 *
 * @template TData of object
 *
 * @extends FormTypeInterface<TData>
 */
interface ContractFormType extends FormTypeInterface
{
}
