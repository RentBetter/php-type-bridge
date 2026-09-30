<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use ReflectionProperty;

/**
 * A promoted property is typed either by its own `@var` or by the constructor's `@param` for
 * it — the spec writes response properties the second way — so both have to be read.
 */
final class PhpDocTypeHelperPropertyTypeTest extends TestCase
{
    public function test_a_property_is_typed_by_its_own_var(): void
    {
        self::assertSame('list<string>', $this->typeOf('documented'));
    }

    public function test_a_promoted_property_without_one_is_typed_by_the_constructor_param(): void
    {
        self::assertSame('list<array{path: string, message: string}>', $this->typeOf('promoted'));
    }

    public function test_its_own_var_wins_over_the_constructor_param(): void
    {
        self::assertSame('list<float>', $this->typeOf('overridden'));
    }

    public function test_the_param_is_matched_by_name(): void
    {
        self::assertNull($this->typeOf('undocumented'), 'The other parameters\' @param tags are not its type');
    }

    public function test_a_plain_property_is_not_typed_by_a_constructor_param_of_the_same_name(): void
    {
        self::assertNull($this->typeOf('assigned'), 'The parameter is only assigned to it; PHPStan does not carry its type across');
    }

    private function typeOf(string $property): ?string
    {
        return (new PhpDocTypeHelper())->extractPropertyType(new ReflectionProperty(PropertyTypeFixture::class, $property));
    }
}

final class PropertyTypeFixture
{
    /** @var list<string> */
    public array $documented = [];

    public array $assigned = [];

    /**
     * @param list<array{path: string, message: string}> $promoted
     * @param list<int> $overridden
     * @param list<int> $assigned
     */
    public function __construct(
        public array $promoted,
        /** @var list<float> */
        public array $overridden,
        public array $undocumented,
        array $assigned,
    ) {
        $this->assigned = $assigned;
    }
}
