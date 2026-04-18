<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Contract;

use Symfony\Component\Form\FormTypeInterface;

/**
 * Marker interface for Symfony form types that participate in TypeBridge request contracts.
 *
 * @template TData of object
 */
interface ContractFormType extends FormTypeInterface
{
}
