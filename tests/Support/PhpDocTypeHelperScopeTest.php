<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ScalarType;
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

    public function test_a_name_whose_class_cant_be_declared_in_this_install_is_no_class(): void
    {
        // DevOnlyRule's declaration throws without its require-dev interface: a bare name that
        // could mean it means no class, and its routes are none — nothing throws.
        $resolved = (new PhpDocTypeHelper())->resolveImportedNames(new NameRefType('DevOnlyRule'), [], 'PTGS\\TypeBridge\\Tests\\Fixture\\UnloadableFixtures\\DevOnly\\Other');
        self::assertInstanceOf(NameRefType::class, $resolved);
        self::assertNull($resolved->class);

        self::assertNull((new DeclaredRoutes())->routeFor(DevOnlyRule::class, 'check'));
    }
}
