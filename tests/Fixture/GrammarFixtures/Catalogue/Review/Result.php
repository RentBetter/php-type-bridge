<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GrammarFixtures\Catalogue\Review;

/**
 * Constant sets standing in for enums: the type is whichever of their values.
 *
 * @phpstan-type ResultAction = self::RESULT_*
 * @phpstan-type ModeChoice = array{manualModes?: list<value-of<self::MODE_*>>}
 * @phpstan-type Retry = self::RETRY_LIMIT
 */
final class Result
{
    public const string RESULT_APPROVE = 'approve';
    public const string RESULT_REJECT = 'reject';
    public const string OTHER = 'not a result';
    public const string MODE_SIMPLE = 'simple';
    public const string MODE_FULL = 'full';
    public const int RETRY_LIMIT = 3;
}
