<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SharedFileTraitFixtures\Notes;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Status\HttpOk;

/** Two classes in one file, each binding the trait its own way. */
abstract class TagBase implements ApiResponse, HttpOk
{
    /** @use HasItems<string> */
    use HasItems;
}

abstract class CountBase implements ApiResponse, HttpOk
{
    /** @use HasItems<int> */
    use HasItems;
}
