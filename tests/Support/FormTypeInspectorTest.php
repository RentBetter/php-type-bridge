<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Support\FormTypeInspector;
use PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form\AliasListType;
use PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form\CodeBatchType;
use PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form\NoteType;
use PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form\TagListType;
use PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form\YearlyTotalsType;
use PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form\UppercasedCodeType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\AddProjectNotesRequestType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\CreateProjectRequestType;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Form\ProjectNoteType;
use PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Form\ReportStatusType;
use PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\Inspector\Form\NestedBrokenRequestType;
use PTGS\TypeBridge\Tests\InvalidFixture\Fixtures\Inspector\Form\NonContractRootType;
use RuntimeException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

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
        // What a field's constraints refuse is read as well as what its form says.
        self::assertInstanceOf(NotBlank::class, $notes->entryChildren[0]->constraints[0] ?? null, 'text carries NotBlank');
        self::assertSame([], $notes->entryChildren[1]->constraints);

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

    public function test_it_records_what_a_blank_value_reaches_validation_as(): void
    {
        // Left out of a request, sent as null or sent blank, a field is submitted null; what its
        // constraints are then asked about is what its form makes of that.
        $fields = array_column((new FormTypeInspector())->inspect(NoteType::class)['fields'], null, 'name');

        self::assertNull($fields['title']->blankValue, 'A blank text field reaches validation as null');
        self::assertSame('', $fields['code']->blankValue, "With empty_data '', TextType keeps it ''");
        self::assertNull($fields['count']->blankValue, "A number's transformer still makes it null");
        self::assertSame([], $fields['tags']->blankValue, 'A collection is emptied');
        self::assertSame([], $fields['kinds']->blankValue, 'A multiple choice chooses none');
        self::assertNull($fields['status']->blankValue, 'A single choice is none');
        self::assertFalse($fields['pinned']->blankValue, 'A checkbox is left unticked');
        self::assertSame('n/a', $fields['fallback']->blankValue, 'Its empty_data');
        self::assertSame(0, $fields['quantity']->blankValue, "Its empty_data, as the number's transformer reads it");
    }

    public function test_it_records_what_each_collection_entry_is_held_to(): void
    {
        $fields = array_column((new FormTypeInspector())->inspect(NoteType::class)['fields'], null, 'name');

        $kinds = array_map(static fn(object $constraint): string => $constraint::class, $fields['tags']->entryConstraints);
        sort($kinds);
        self::assertSame([NotBlank::class, NotNull::class], $kinds, 'Its entry_options constraints, and what an All on its property holds each entry to');
        self::assertSame([], $fields['title']->entryConstraints);
    }

    public function test_an_entry_is_held_to_what_its_type_sets_and_not_to_another_groups_constraints(): void
    {
        $fields = array_column((new FormTypeInspector())->inspect(NoteType::class)['fields'], null, 'name');

        $kinds = array_map(static fn(object $constraint): string => $constraint::class, $fields['labels']->entryConstraints);
        self::assertSame([NotNull::class], $kinds, 'LabelType sets a NotNull of its own');
        $kinds = array_map(static fn(object $constraint): string => $constraint::class, $fields['notes']->entryConstraints);
        self::assertSame([Length::class], $kinds, "A NotNull only in the 'strict' group never sees a request, given to the entry or by an All");
    }

    public function test_a_field_rebuilt_with_its_declared_contracts_keeps_what_the_form_does_to_it(): void
    {
        $fields = array_column((new FormTypeInspector())->inspect(NoteType::class)['fields'], null, 'name');

        self::assertSame('', $fields['code']->withDeclared([], [], null)->blankValue);
        self::assertFalse($fields['pinned']->withDeclared([], [], null)->blankValue);
        self::assertCount(2, $fields['tags']->withDeclared([], [], null)->entryConstraints);
    }

    public function test_it_records_what_a_blank_entry_reaches_validation_as(): void
    {
        $fields = array_column((new FormTypeInspector())->inspect(NoteType::class)['fields'], null, 'name');

        self::assertSame('', $fields['codes']->entryBlankValue, "Its entry_options give each entry empty_data ''");
        self::assertNull($fields['tags']->entryBlankValue);
        self::assertFalse($fields['flags']->entryBlankValue, 'An entry that is a checkbox is left unticked');
        self::assertNull($fields['title']->entryBlankValue, 'No entries at all');
        self::assertSame('', $fields['codes']->withDeclared([], [], null)->entryBlankValue);
    }

    public function test_it_records_whether_the_form_transforms_a_value_before_it_is_validated(): void
    {
        $fields = array_column((new FormTypeInspector())->inspect(NoteType::class)['fields'], null, 'name');

        self::assertFalse($fields['title']->transformsValue);
        self::assertFalse($fields['code']->transformsValue, "TextType's own transformer only makes a null ''");
        self::assertTrue($fields['count']->transformsValue, 'A number is read from what was sent');
        self::assertTrue($fields['ref']->transformsValue, 'A model transformer');
        self::assertTrue($fields['ref']->withDeclared([], [], null)->transformsValue);
    }

    public function test_a_field_named_by_number_is_read_by_that_name(): void
    {
        $fields = (new FormTypeInspector())->inspect(YearlyTotalsType::class)['fields'];

        self::assertSame(['2025', '2026'], array_map(static fn(CollectedFormField $field): string => $field->name, $fields));
    }

    public function test_an_expanded_choice_is_one_field_not_the_buttons_it_draws(): void
    {
        // Its radio buttons are children named 0, 1, … — none of them a field a request sends.
        $fields = array_column((new FormTypeInspector())->inspect(ReportStatusType::class)['fields'], null, 'name');

        self::assertSame(['open', 'closed'], $fields['status']->choiceValues);
        self::assertSame([], $fields['status']->children);
    }

    public function test_a_choice_or_an_all_nested_where_it_always_holds_is_read_too(): void
    {
        // A Sequentially's constraints hold as if written beside it: its Choice narrows the values,
        // its All holds each entry. One of them confined to another group, as a request is never
        // validated in, holds nothing.
        $fields = array_column((new FormTypeInspector())->inspect(NoteType::class)['fields'], null, 'name');

        self::assertSame(['bug', 'idea'], $fields['kind']->choiceValues);
        self::assertSame([NotNull::class], array_map(static fn(object $constraint): string => $constraint::class, $fields['refs']->entryConstraints));
    }

    public function test_an_entry_option_of_an_extension_inspection_does_not_load_is_no_matter(): void
    {
        // It means something to the application, not to what inspection reads; the entry is built
        // with the options its type has, and held to their constraints.
        $fields = array_column((new FormTypeInspector())->inspect(AliasListType::class)['fields'], null, 'name');

        self::assertSame([NotBlank::class], array_map(static fn(object $constraint): string => $constraint::class, $fields['aliases']->entryConstraints));
    }

    public function test_a_listener_of_the_form_holding_a_field_may_rewrite_it(): void
    {
        // The form's own PRE_SUBMIT listener rewrites its children's values before they are
        // validated: which it touches it doesn't say, so none is read as sent.
        $fields = array_column((new FormTypeInspector())->inspect(UppercasedCodeType::class)['fields'], null, 'name');

        self::assertTrue($fields['code']->transformsValue);
        self::assertTrue($fields['label']->transformsValue);
    }

    public function test_an_expanded_choice_entry_reads_what_it_is_sent_itself(): void
    {
        // Its radio buttons, named 0, 1, …, are no fields an entry is sent: each entry is a value.
        $fields = array_column((new FormTypeInspector())->inspect(TagListType::class)['fields'], null, 'name');

        self::assertSame([], $fields['tags']->entryChildren);
    }

    public function test_a_listener_rewrites_the_fields_of_the_form_it_is_on_alone(): void
    {
        // Embedded or as each entry, UppercasedCodeType's listener rewrites its fields; a collection's
        // resizing rewrites none of its entries'.
        $fields = array_column((new FormTypeInspector())->inspect(CodeBatchType::class)['fields'], null, 'name');
        $rewritten = static fn(array $children): array => array_map(static fn(CollectedFormField $child): bool => $child->transformsValue, array_column($children, null, 'name'));

        self::assertSame(['code' => true, 'label' => true], $rewritten($fields['code']->children));
        self::assertSame(['code' => true, 'label' => true], $rewritten($fields['codes']->entryChildren));
        self::assertSame(['code' => false, 'label' => false], $rewritten($fields['plainCodes']->entryChildren));
        self::assertSame(['code' => true, 'label' => true], $rewritten($fields['listedCodes']->entryChildren));
    }

    public function test_a_listener_that_rewrites_what_is_sent_transforms_it_too(): void
    {
        // Before it is validated, as a transformer would: UrlType given a default protocol adds it
        // to `example.com`, which the Regex then checks; a project's listener lowercases a slug.
        // Trimming is the `trim` option's to say, not this.
        $fields = array_column((new FormTypeInspector())->inspect(NoteType::class)['fields'], null, 'name');

        self::assertTrue($fields['site']->transformsValue, "UrlType's default protocol");
        self::assertTrue($fields['slug']->transformsValue, 'A PRE_SUBMIT listener of its own');
        self::assertFalse($fields['title']->transformsValue, 'Symfony\'s trim is no rewrite');
    }

    public function test_it_records_whether_the_form_trims_a_fields_value(): void
    {
        $fields = array_column((new FormTypeInspector())->inspect(\PTGS\TypeBridge\Tests\Fixture\ReimportedScopeFixtures\Reports\Form\ScopedReportFilterType::class)['fields'], null, 'name');

        self::assertTrue($fields['product']->trim, 'Symfony trims by default');
        self::assertFalse($fields['period']->trim);
    }
}
