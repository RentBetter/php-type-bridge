<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Form;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Form\ContractFormTypeRule;

/**
 * @extends RuleTestCase<ContractFormTypeRule>
 */
final class ContractFormTypeRuleTest extends RuleTestCase
{
    protected function getRule(): ContractFormTypeRule
    {
        return new ContractFormTypeRule();
    }

    public function testAcceptsAdvancedPositiveContractForm(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Form/Positive/AdvancedOwnerType.php',
            __DIR__ . '/../../Fixtures/Form/Positive/AdvancedRequestType.php',
        ], []);
    }

    public function testRejectsMissingDataClass(): void
    {
        $this->analyse([
            __DIR__ . '/../../../InvalidFixture/Fixtures/MissingDataClass/Form/MissingDataClassRequestType.php',
        ], [[
            'Contract form "PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingDataClass\Form\MissingDataClassRequestType" must configure data_class "PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingDataClass\Input\MissingDataClassRequestData"; found "null".',
            16,
        ]]);
    }

    public function testRejectsMissingSelfType(): void
    {
        $this->analyse([
            __DIR__ . '/../../../InvalidFixture/Fixtures/MissingSelf/Form/MissingSelfRequestType.php',
        ], [[
            'Contract form data class "PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\MissingSelf\Input\MissingSelfRequestData" must declare @phpstan-type _self.',
            17,
        ]]);
    }

    public function testRejectsNestedCustomFormWithoutMarker(): void
    {
        $this->analyse([
            __DIR__ . '/../../../InvalidFixture/Fixtures/Inspector/Form/NestedBrokenRequestType.php',
        ], [[
            'Form class "PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\Inspector\Form\BrokenLeafType" must implement "PTGS\TypeBridge\Contract\ContractFormType" to participate in TypeBridge request contracts.',
            16,
        ]]);
    }

    public function testRejectsMismatchedDataClass(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Form/Negative/MismatchedRequestType.php',
        ], [[
            'Contract form "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative\MismatchedRequestType" must configure data_class "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative\MismatchedRequestData"; found "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative\OtherRequestData".',
            16,
        ]]);
    }

    public function testRejectsMissingPropertyPathTarget(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Form/Negative/MissingPropertyRequestType.php',
        ], [[
            'Contract form "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative\MissingPropertyRequestType" maps field "assignee" to missing property path "ownerId" on "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative\MissingPropertyRequestData".',
            16,
        ]]);
    }

    public function testRejectsWrongLeafPropertyType(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Form/Negative/WrongTypeRequestType.php',
        ], [[
            'Contract form "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative\WrongTypeRequestType" field "count" expects property "count" to be compatible with "int".',
            16,
        ]]);
    }
}
