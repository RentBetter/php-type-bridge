<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LocaleListFixtures\Prefs;

/**
 * @phpstan-type _self = array{
 *     locales?: list<'en'|'fr'>,
 *     codes?: list<1|2>,
 *     tags?: list<'a'|'b'>,
 *     inner?: InnerData,
 * }
 */
final class PrefData
{
    /** @var list<string> */
    public array $locales = [];

    /** @var list<int> */
    public array $codes = [];

    /** @var list<string> */
    public array $tags = [];

    public ?InnerData $inner = null;
}
