<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LocaleListFixtures\Prefs;

/**
 * @phpstan-type _self = array{
 *     labels?: list<'x'|'y'>,
 * }
 */
final class InnerData
{
    /** @var list<string> */
    public array $labels = [];
}
