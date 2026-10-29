<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Model;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Parser\ShapeType;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CollectedFormFieldTest extends TestCase
{
    public function test_a_field_with_what_the_source_declares_keeps_everything_the_form_said(): void
    {
        // Every argument given something other than its default, so one not carried over shows.
        $arguments = [];
        foreach ((new ReflectionMethod(CollectedFormField::class, '__construct'))->getParameters() as $parameter) {
            $type = $parameter->getType();
            $name = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
            $arguments[$parameter->getName()] = match ($name) {
                'bool' => !($parameter->isDefaultValueAvailable() && true === $parameter->getDefaultValue()),
                'string' => $parameter->getName() . '-value',
                'array' => [new NotBlank()],
                ShapeType::class => new ShapeType([]),
                default => $parameter->getName() . '-value',
            };
        }
        $field = new CollectedFormField(...$arguments);

        $declared = $field->withDeclared([], [], null);

        $kept = array_diff_key(get_object_vars($field), array_flip(['children', 'entryChildren', 'contract']));
        self::assertSame($kept, array_diff_key(get_object_vars($declared), array_flip(['children', 'entryChildren', 'contract'])));
        self::assertSame([[], [], null], [$declared->children, $declared->entryChildren, $declared->contract]);
    }
}
