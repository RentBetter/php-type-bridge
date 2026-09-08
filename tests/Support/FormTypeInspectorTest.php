<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\FormTypeInspector;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\AddProjectNotesRequestType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\CreateProjectRequestType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\ProjectNoteType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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

    public function test_it_inspects_a_collection_entry_type_as_entry_children(): void
    {
        // The collection itself has no children on the builder — entries only exist once
        // data is bound — so the entry type is built on its own and its fields collected.
        $inspected = (new FormTypeInspector())->inspect(AddProjectNotesRequestType::class);

        [$notes, $labels] = $inspected['fields'];

        self::assertSame('notes', $notes->name);
        self::assertSame(ProjectNoteType::class, $notes->entryTypeClass);
        self::assertSame([], $notes->children);
        self::assertSame(['text', 'author'], array_map(static fn ($field) => $field->name, $notes->entryChildren));
        self::assertTrue($notes->entryChildren[0]->required);
        self::assertFalse($notes->entryChildren[1]->required);

        // A scalar entry has no fields to collect.
        self::assertSame('labels', $labels->name);
        self::assertSame(TextType::class, $labels->entryTypeClass);
        self::assertSame([], $labels->entryChildren);
        self::assertFalse($labels->required);
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
