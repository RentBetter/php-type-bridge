<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LibraryEnumFixtures\Notes\Enum;

/**
 * The application's own `Status`: not the library's, whose short name it shares.
 */
enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}
