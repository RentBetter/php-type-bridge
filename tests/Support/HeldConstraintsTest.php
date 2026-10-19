<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\HeldConstraints;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\When;

/**
 * The constraints a value is held to, read through what nests them.
 */
final class HeldConstraintsTest extends TestCase
{
    public function testAWhensBranchesMayHoldButNeedNot(): void
    {
        // Both its `constraints` and its `otherwise`: one of them holds, which is no saying which.
        $then = new Regex('/^[A-Z]+$/');
        $otherwise = new Length(max: 3);
        $when = new When(static fn (): bool => true, constraints: [$then], otherwise: [$otherwise]);

        self::assertSame([$when, $then, $otherwise], HeldConstraints::any([$when]));
        self::assertSame([$when], HeldConstraints::always([$when]));
    }

    public function testASequencesHoldAlways(): void
    {
        $blank = new NotBlank();
        $sequence = new Sequentially([$blank]);

        self::assertSame([$sequence, $blank], HeldConstraints::always([$sequence]));
        self::assertSame([$sequence, $blank], HeldConstraints::any([$sequence]));
    }
}
