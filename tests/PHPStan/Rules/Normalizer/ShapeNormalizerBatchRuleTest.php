<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Normalizer;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Normalizer\ShapeNormalizerBatchRule;

/**
 * @extends RuleTestCase<ShapeNormalizerBatchRule>
 */
final class ShapeNormalizerBatchRuleTest extends RuleTestCase
{
    protected function getRule(): ShapeNormalizerBatchRule
    {
        return new ShapeNormalizerBatchRule($this->createReflectionProvider());
    }

    public function testFlagsNormalizeInArrayFunctionAndLoop(): void
    {
        $this->analyse(
            [__DIR__ . '/../../Fixtures/Normalizer/Negative/LoopingService.php'],
            [
                [
                    'ShapeNormalizer::normalize() is called inside an array_map() callback. Give the normalizer a batch method (e.g. normalizeMany()) that owns the iteration, and call that instead.',
                    21,
                ],
                [
                    'ShapeNormalizer::normalize() is called inside a loop. Give the normalizer a batch method (e.g. normalizeMany()) that owns the iteration, and call that instead.',
                    33,
                ],
            ],
        );
    }

    public function testAllowsBatchMethodSingleNormalizeAndOwnThisLoop(): void
    {
        $this->analyse(
            [
                __DIR__ . '/../../Fixtures/Normalizer/Positive/BatchingService.php',
                __DIR__ . '/../../Fixtures/Normalizer/WidgetNormalizer.php',
            ],
            [],
        );
    }
}
