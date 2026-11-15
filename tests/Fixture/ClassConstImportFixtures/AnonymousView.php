<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassConstImportFixtures;

use PTGS\TypeBridge\Tests\Fixture\EnumTypedFixtures\Notes\ErrorCode;

// An anonymous class's trait, and a closure's variables: neither is an import of the file's.
$helper = new class {
    use \PTGS\TypeBridge\Tests\Fixture\ClassConstImportFixtures\Helper;
};
$y = 1;
$read = function () use ($y): int {
    return $y;
};

/** @phpstan-type Coded = array{code: ErrorCode} */
final class AnonymousView {}
