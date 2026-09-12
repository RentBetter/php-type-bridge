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

    /**
     * The shape nearly every application actually uses: a base class implements the contract
     * and concrete forms extend it. Such a form cannot declare `@implements` — PHPStan's
     * generics.noParent rejects it on a class implementing no interface directly — so reading
     * only `@implements`, only on the leaf, made the rule unsatisfiable for all of them.
     */
    public function testAcceptsAFormThatInheritsTheContractFromItsParent(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Form/Positive/InheritedContractType.php',
            __DIR__ . '/../../Fixtures/Form/Positive/AbstractInheritedContractType.php',
            __DIR__ . '/../../Fixtures/Form/Positive/InheritedContractData.php',
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

    /**
     * A collection whose entry type cannot be built is reported against the form, not raised as
     * an internal error that abandons the analysis. The entry build sits inside collectFields(),
     * outside inspect()'s own try, so it needed catching in its own right.
     */
    public function testReportsACollectionWhoseEntryTypeCannotBeBuilt(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Form/Negative/UninspectableEntryRequestType.php',
        ], [[
            'Collection entry type "Symfony\\Component\\Form\\Extension\\Core\\Type\\EnumType" cannot be built for inspection: An error has occurred resolving the options of the form "Symfony\\Component\\Form\\Extension\\Core\\Type\\EnumType": The required option "class" is missing. Give the option a default, or keep the form off the contract surface.',
            24,
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

    public function testReportsAFormThatCannotBeBuiltWithoutOptions(): void
    {
        // Inspection builds the form with no options. One that requires an option — a
        // `project` to scope its choices, say — is a finding to act on, not an analysis crash
        // that hides every other result behind "Result is incomplete".
        $this->analyse([
            __DIR__ . '/../../Fixtures/Form/Negative/RequiresOptionRequestType.php',
        ], [[
            'Form "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative\RequiresOptionRequestType" cannot be built for inspection: An error has occurred resolving the options of the form "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative\RequiresOptionRequestType": The required option "project" is missing. Give the option a default, or keep the form off the contract surface.',
            20,
        ]]);
    }
}
