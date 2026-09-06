<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Shape;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Shape\ShapeRenderer;
use PTGS\TypeBridge\Shape\ShapeScaffolder;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class ShapeScaffolderTest extends TestCase
{
    public function testDerivesAShapeFromTheFormAndTheDto(): void
    {
        ['shape' => $shape, 'unresolved' => $unresolved] = (new ShapeScaffolder())->scaffold(
            ScaffoldDto::class,
            [
                $this->field('title', TextType::class, required: true),
                $this->field('description', TextType::class, required: false),
                $this->field('status', EnumType::class, required: false, enumClass: ScaffoldStatus::class),
                // A DateType binds to a DateTimeImmutable but travels as a string — only the DTO
                // knows the first half and only the form knows the key exists.
                $this->field('dueDate', DateType::class, required: false),
                $this->field('archived', CheckboxType::class, required: true),
                $this->field('order', IntegerType::class, required: true),
            ],
        );

        self::assertSame([], $unresolved);
        self::assertSame(
            <<<'DOC'
                /**
                 * @phpstan-type _self = array{
                 *     title: string,
                 *     description?: string,
                 *     status?: value-of<ScaffoldStatus>,
                 *     dueDate?: string,
                 *     archived: bool,
                 *     order: int,
                 * }
                 */
                DOC,
            (new ShapeRenderer())->renderSelfDocBlock($shape),
        );
    }

    public function testReportsFieldsItCannotTypeInsteadOfGuessing(): void
    {
        // `tags` is an untyped array on the DTO and a bare TextType on the form. A guess here
        // would publish a wrong contract to every client, so it is named and left alone.
        ['shape' => $shape, 'unresolved' => $unresolved] = (new ShapeScaffolder())->scaffold(
            ScaffoldDto::class,
            [
                $this->field('title', TextType::class, required: true),
                $this->field('tags', 'App\\Form\\MysteryType', required: false),
            ],
        );

        self::assertSame(['tags'], $unresolved);
        self::assertCount(1, $shape->fields);
    }

    public function testUnmappedFieldsAreNotPartOfTheContract(): void
    {
        ['shape' => $shape] = (new ShapeScaffolder())->scaffold(
            ScaffoldDto::class,
            [
                $this->field('title', TextType::class, required: true),
                $this->field('description', TextType::class, required: false, mapped: false),
            ],
        );

        self::assertSame(['title'], array_map(static fn ($f) => $f->name, $shape->fields));
    }

    private function field(
        string $name,
        string $formTypeClass,
        bool $required,
        bool $mapped = true,
        ?string $enumClass = null,
    ): CollectedFormField {
        return new CollectedFormField(
            name: $name,
            formTypeClass: $formTypeClass,
            required: $required,
            mapped: $mapped,
            compound: false,
            dataClass: null,
            enumClass: $enumClass,
        );
    }
}
