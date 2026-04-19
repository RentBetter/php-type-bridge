<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Shape;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Shape\NoTypeIdPairRule;

/**
 * @extends RuleTestCase<NoTypeIdPairRule>
 */
final class NoTypeIdPairRuleTest extends RuleTestCase
{
    protected function getRule(): NoTypeIdPairRule
    {
        return new NoTypeIdPairRule();
    }

    public function testAcceptsShapeWithoutTypeIdPair(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Valid/CleanView.php',
        ], []);
    }

    public function testFlagsEntityTypeAndEntityIdPair(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Shape/Negative/HasTypeIdPairView.php',
        ], [
            [
                'Shape `_self` on PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative\HasTypeIdPairView must not pair `entityType` and `entityId`. Use a single compound `entity: "{type}-{uuid}"` field instead — the request side (URL/body) uses the same `"{type}-{uuid}"` format, and the compound keeps a single parse helper across wire and store.',
                15,
            ],
        ]);
    }
}
