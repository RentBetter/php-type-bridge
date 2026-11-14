<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\ClassConstantType;
use PTGS\TypeBridge\Parser\GenericType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ScopedType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Routing\DeclaredRoutes;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Tests\Fixture\UnloadableFixtures\DevOnly\DevOnlyRule;

final class PhpDocTypeHelperScopeTest extends TestCase
{
    public function test_a_bound_intersection_keeps_its_null_where_a_reader_sees_it(): void
    {
        // `?(Meta & array{x: int})`, and the same as a union with null: the intersection is scoped
        // whole, its `?` and its null left outside.
        $meta = new IntersectionType(new NameRefType('Meta'), new ShapeType([new ShapeField('x', new ScalarType('int'), optional: false)]));
        $helper = new PhpDocTypeHelper();

        $optional = $helper->scoped(new NullableType($meta, optional: true), [], 'Notes');
        self::assertInstanceOf(NullableType::class, $optional);
        self::assertTrue($optional->optional);
        self::assertTrue((new ShapeField('meta', $optional, optional: false))->mayBeLeftOut());

        $union = $helper->scoped(new UnionType([$meta, new ScalarType('int'), new ScalarType('null')]), [], 'Notes');
        self::assertTrue(ShapeField::admitsNull($union));
    }

    public function test_only_the_intersection_in_a_bound_type_is_scoped_whole(): void
    {
        // `included<Meta & array{x: int}>` and `list<Meta & array{x: int}>`: the generic and the list
        // stay what a reader sees, the intersection inside them read whole where it is written.
        $meta = new IntersectionType(new NameRefType('Meta'), new ShapeType([new ShapeField('x', new ScalarType('int'), optional: false)]));
        $helper = new PhpDocTypeHelper();

        $included = $helper->scoped(new GenericType('included', [$meta]), [], 'Notes');
        self::assertInstanceOf(GenericType::class, $included);
        self::assertInstanceOf(ScopedType::class, $included->arguments[0]);
        self::assertSame($meta, $included->arguments[0]->inner);

        $list = $helper->scoped(new ListType($meta), [], 'Notes');
        self::assertInstanceOf(ListType::class, $list);
        self::assertInstanceOf(ScopedType::class, $list->inner);
    }

    public function test_a_name_is_the_class_php_resolves_it_to(): void
    {
        // A `use` names the class whether it exists or not: none of that name is no class, not the
        // global one PHP never looks at. Unqualified and not imported, a name no class of the
        // namespace has is still read, leniently, as the global class.
        $helper = new PhpDocTypeHelper();

        self::assertNull($helper->resolveClassName('DateTimeImmutable', 'App\\Notes\\Note', ['DateTimeImmutable' => 'App\\Missing\\DateTimeImmutable']));
        self::assertSame('DateTimeImmutable', $helper->resolveClassName('DateTimeImmutable', 'App\\Notes\\Note', []));
        self::assertSame('PTGS\\TypeBridge\\Support\\ClassNames', $helper->resolveClassName('Support\\ClassNames', 'PTGS\\TypeBridge\\Note', []));
    }

    public function test_a_name_whose_class_cant_be_declared_in_this_install_is_no_class(): void
    {
        // DevOnlyRule's declaration throws without its require-dev interface: a bare name that
        // could mean it means no class, and its routes are none — nothing throws.
        $resolved = (new PhpDocTypeHelper())->resolveImportedNames(new NameRefType('DevOnlyRule'), [], 'PTGS\\TypeBridge\\Tests\\Fixture\\UnloadableFixtures\\DevOnly\\Other');
        self::assertInstanceOf(NameRefType::class, $resolved);
        self::assertNull($resolved->class);

        self::assertNull((new DeclaredRoutes())->routeFor(DevOnlyRule::class, 'check'));
    }

    public function test_a_constant_of_a_class_that_cant_be_declared_is_one_it_cant_find(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Class "DevOnlyRule" in `DevOnlyRule::STATE_*` could not be found.');

        (new PhpDocTypeHelper())->resolveImportedNames(new ClassConstantType('DevOnlyRule', 'STATE_*'), [], 'PTGS\\TypeBridge\\Tests\\Fixture\\UnloadableFixtures\\DevOnly\\Other');
    }
}
