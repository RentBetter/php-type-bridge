<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Positive;

use PTGS\TypeBridge\Contract\ContractFormType;
use Symfony\Component\Form\AbstractType;

/**
 * The shape almost every real application uses: a base class implements the contract, and
 * concrete forms extend it. A concrete form therefore cannot carry `@implements` — PHPStan's
 * generics.noParent rejects that on a class which implements no interface directly — so the
 * data class has to be readable from `@extends`.
 *
 * @template T of object
 *
 * @implements ContractFormType<T>
 */
abstract class AbstractInheritedContractType extends AbstractType implements ContractFormType {}
