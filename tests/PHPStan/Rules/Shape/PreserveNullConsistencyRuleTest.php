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

    public function testHoldsAnEnumsShapeToItTooByTheNameItIsEmittedAs(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/MissingPreserveNullStatus.php',
            __DIR__ . '/../../Fixtures/Shape/Negative/MissingPreserveNullNamedStatus.php',
        ], [
            [
                'Field `hint` in shape `MissingPreserveNullStatusData` (on PTGS\\TypeBridge\\Tests\\PHPStan\\Fixtures\\Shape\\Negative\\MissingPreserveNullStatus) has `T|null` annotation but is not listed in `typeBridge.preserveNull`. Change the annotation to `?T` (emits as `hint?: T`; field is omitted when null), or add `MissingPreserveNullStatusData.hint` to preserveNull if null is semantically meaningful for this field.',
                15,
            ],
            [
                'Field `hint` in shape `MissingPreserveNullNamedStatus` (on PTGS\\TypeBridge\\Tests\\PHPStan\\Fixtures\\Shape\\Negative\\MissingPreserveNullNamedStatus) has `T|null` annotation but is not listed in `typeBridge.preserveNull`. Change the annotation to `?T` (emits as `hint?: T`; field is omitted when null), or add `MissingPreserveNullNamedStatus.hint` to preserveNull if null is semantically meaningful for this field.',
                17,
            ],
        ]);
    }

    public function testHoldsAnInterfacesAndATraitsShapesToItToo(): void
    {
        // Both are collected and emitted as a class's are, and the emitter holds them to it.
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/MissingPreserveNullContract.php',
            __DIR__ . '/../../Fixtures/Shape/Negative/MissingPreserveNullTrait.php',
        ], [
            [
                'Field `nickname` in shape `MissingPreserveNullContract` (on PTGS\\TypeBridge\\Tests\\PHPStan\\Fixtures\\Shape\\Negative\\MissingPreserveNullContract) has `T|null` annotation but is not listed in `typeBridge.preserveNull`. Change the annotation to `?T` (emits as `nickname?: T`; field is omitted when null), or add `MissingPreserveNullContract.nickname` to preserveNull if null is semantically meaningful for this field.',
                16,
            ],
            [
                'Field `nickname` in shape `SharedNickname` (on PTGS\\TypeBridge\\Tests\\PHPStan\\Fixtures\\Shape\\Negative\\MissingPreserveNullTrait) has `T|null` annotation but is not listed in `typeBridge.preserveNull`. Change the annotation to `?T` (emits as `nickname?: T`; field is omitted when null), or add `SharedNickname.nickname` to preserveNull if null is semantically meaningful for this field.',
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
