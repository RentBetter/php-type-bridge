<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\FilelessBaseFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\FilelessBaseFixtures\FilelessBase;

/**
 * Extends a class with no file to read — one declared at run time, as eval or an extension declares
 * it ({@see \PTGS\TypeBridge\Tests\Collector\ResponseClassCollectorInheritedTest}). Its public
 * properties are sent all the same, so they are published, typed as their declarations say.
 */
final class ShowNoteResponse extends FilelessBase implements HttpOk
{
    public string $title = '';
}
