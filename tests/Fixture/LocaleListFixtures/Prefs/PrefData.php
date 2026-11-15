<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LocaleListFixtures\Prefs;

/**
 * @phpstan-type _self = array{
 *     locales?: list<'en'|'fr'>,
 * }
 */
final class PrefData
{
    /** @var list<string> */
    public array $locales = [];
}
