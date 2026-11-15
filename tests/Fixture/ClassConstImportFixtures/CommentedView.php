<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassConstImportFixtures;

use PTGS\TypeBridge\Tests\Fixture\EnumTypedFixtures\Notes\ErrorCode /* the one Notes sends */;
use PTGS\TypeBridge\Tests\Fixture\EnumTypedFixtures\Notes\{function strlen, ConflictResponse};

/** @phpstan-type Coded = array{code: ErrorCode} */
final class CommentedView {}
