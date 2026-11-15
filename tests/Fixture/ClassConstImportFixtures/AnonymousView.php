<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassConstImportFixtures;

use PTGS\TypeBridge\Tests\Fixture\EnumTypedFixtures\Notes\ErrorCode;

// A closure's variables, and an anonymous class's trait: neither is an import of the file's.
$y = 1;
$read = function () use ($y): int {
    return $y;
};
$helper = new class {
    use \PTGS\TypeBridge\Tests\Fixture\ClassConstImportFixtures\Helper;
};

/** @phpstan-type Coded = array{code: ErrorCode} */
final class AnonymousView {}
