<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SharedFileTraitFixtures\Notes;

// CountBase is declared in TagBase's file, which no autoloader finds by its name.
require_once __DIR__ . '/TagBase.php';

final class ListCountsResponse extends CountBase
{
}
