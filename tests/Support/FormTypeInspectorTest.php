<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\FormTypeInspector;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\CreateProjectRequestType;
use PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\Inspector\Form\NestedBrokenRequestType;
use PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\Inspector\Form\NonContractRootType;
use RuntimeException;

final class FormTypeInspectorTest extends TestCase
{
    public function test_it_inspects_contract_form_types_and_nested_custom_forms(): void
    {
        $inspected = (new FormTypeInspector())->inspect(CreateProjectRequestType::class);

        self::assertSame(
            'PTGS\\TypeBridge\\Tests\\Fixture\\Fixtures\\Projects\\Input\\CreateProjectRequestData',
            $inspected['dataClass'],
        );
        self::assertCount(1, $inspected['fields']);
        self::assertSame('project', $inspected['fields'][0]->name);
        self::assertSame(
            'PTGS\\TypeBridge\\Tests\\Fixture\\Fixtures\\Projects\\Form\\CreateProjectInputType',
            $inspected['fields'][0]->formTypeClass,
        );
        self::assertCount(5, $inspected['fields'][0]->children);
    }

    public function test_it_rejects_top_level_forms_that_do_not_implement_contract_form_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(NonContractRootType::class);

        (new FormTypeInspector())->inspect(NonContractRootType::class);
    }

    public function test_it_rejects_nested_custom_forms_that_do_not_implement_contract_form_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BrokenLeafType');

        (new FormTypeInspector())->inspect(NestedBrokenRequestType::class);
    }
}
