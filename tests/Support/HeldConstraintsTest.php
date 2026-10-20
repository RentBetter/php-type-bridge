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

    public function testANestedConstraintConfinedToAnotherGroupHoldsNoValueValidatedByDefault(): void
    {
        // Validated in the Default group, a sequence validates what it nests in that group: one
        // confined to another is skipped, though the sequence is in both. One with no groups of its
        // own takes the sequence's.
        $strict = new Regex('/^\\d+$/', groups: ['Strict']);
        $blank = new NotBlank();
        $sequence = new Sequentially([$strict, $blank], groups: ['Default', 'Strict']);
        $when = new When(static fn (): bool => true, constraints: [new Length(max: 3, groups: ['Strict'])], groups: ['Default', 'Strict']);

        self::assertSame([$sequence, $blank], HeldConstraints::always([$sequence]));
        self::assertSame([$sequence, $blank, $when], HeldConstraints::any([$sequence, $when]));
        self::assertSame([], HeldConstraints::always([new Length(max: 3, groups: ['Strict'])]));
    }

    public function testASequencesHoldAlways(): void
    {
        $blank = new NotBlank();
        $sequence = new Sequentially([$blank]);

        self::assertSame([$sequence, $blank], HeldConstraints::always([$sequence]));
        self::assertSame([$sequence, $blank], HeldConstraints::any([$sequence]));
    }
}
