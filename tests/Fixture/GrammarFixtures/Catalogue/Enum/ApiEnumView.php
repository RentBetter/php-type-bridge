<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\GrammarFixtures\Catalogue\Enum;

/**
 * The envelope every serialised enum shares — unsealed, because each enum adds its own keys.
 *
 * @phpstan-type Theme = array{color: string}
 * @phpstan-type ThemeState = 'success'|'danger'
 * @phpstan-type ApiEnumData array{id: string, name: string, theme?: Theme|ThemeState, ...}
 */
interface ApiEnumView {}
