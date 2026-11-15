<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassConstImportFixtures;

// A top-level `Foo::class` ahead of a `use`, which PHP allows: no declaration yet.
const DEFAULT_VIEW = \stdClass::class;

use PTGS\TypeBridge\Tests\Fixture\EnumTypedFixtures\Notes\ErrorCode;

/** @phpstan-type Coded = array{code: ErrorCode} */
final class CodedView {}
