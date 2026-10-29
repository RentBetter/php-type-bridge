<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CyclicImportFixtures\Notes\Input;

/**
 * Imports `Code` from NoteCodes, which imports it back from here: neither declares it.
 *
 * @phpstan-import-type Code from NoteCodes
 * @phpstan-type _self = array{
 *     code: Code,
 * }
 */
final class NoteData
{
    public string $code = '';
}
