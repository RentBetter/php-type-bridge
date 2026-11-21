<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnloadableImportFixtures\Notes\Input;

use PTGS\TypeBridge\Tests\Fixture\UnloadableImportFixtures\DevOnly\DevOnlyCodes;

/**
 * @phpstan-import-type Code from DevOnlyCodes
 * @phpstan-type _self = array{
 *     code: Code,
 * }
 */
final class NoteData
{
    public string $code = '';
}
