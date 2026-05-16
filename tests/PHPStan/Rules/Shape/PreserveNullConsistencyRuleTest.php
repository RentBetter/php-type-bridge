<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Shape;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Shape\PreserveNullConsistencyRule;

/**
 * @extends RuleTestCase<PreserveNullConsistencyRule>
 */
final class PreserveNullConsistencyRuleTest extends RuleTestCase
{
    /** @var list<string> */
    private array $preserveNull = [];

    protected function getRule(): PreserveNullConsistencyRule
    {
        return new PreserveNullConsistencyRule($this->preserveNull);
    }

    public function testAcceptsOptionalNullableAnnotation(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Valid/OptionalNullableView.php',
        ], []);
    }

    public function testAcceptsExplicitNullWhenInPreserveNullConfig(): void
    {
        $this->preserveNull = ['PreserveNullableView.archivedAt'];

        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Valid/PreserveNullableView.php',
        ], []);
    }

    public function testFlagsExplicitNullNotInPreserveNullConfig(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/MissingPreserveNullView.php',
        ], [
            [
                'Field `nickname` in shape `MissingPreserveNullView` (on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\MissingPreserveNullView) has `T|null` annotation but is not listed in `typeBridge.preserveNull`. Change the annotation to `?T` (emits as `nickname?: T`; field is omitted when null), or add `MissingPreserveNullView.nickname` to preserveNull if null is semantically meaningful for this field.',
                16,
            ],
        ]);
    }

    public function testFlagsOptionalAnnotationOnPreserveNullField(): void
    {
        $this->preserveNull = ['UnnecessaryOptionalView.archivedAt'];

        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/UnnecessaryOptionalView.php',
        ], [
            [
                'Field `archivedAt` in shape `UnnecessaryOptionalView` (on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\UnnecessaryOptionalView) is listed in `typeBridge.preserveNull` but its @phpstan-type annotation is `?T`. Change the annotation to `T|null` so the wire emits the null, or remove `UnnecessaryOptionalView.archivedAt` from preserveNull.',
                16,
            ],
        ]);
    }
}
