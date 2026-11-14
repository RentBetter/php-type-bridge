<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SameNamedAliasFixtures\Notes\Response;

use PTGS\TypeBridge\Status\HttpOk;
use PTGS\TypeBridge\Tests\Fixture\SameNamedAliasFixtures\Common\Shape\CommonShapes;

/**
 * Declares a `Meta` of its own and imports Common's under another name: two types, each named as
 * the file names it, in its properties and its own aliases alike.
 *
 * @phpstan-import-type Meta from CommonShapes as CommonMeta
 * @phpstan-type Meta = array{
 *     page: int,
 * }
 * @phpstan-type Pair = array{
 *     own: Meta,
 *     common: CommonMeta,
 * }
 */
final class NoteResponse implements HttpOk
{
    /** @var Meta */
    public array $meta = ['page' => 1];

    /** @var CommonMeta */
    public array $common = ['source' => 'x'];

    /** @var Pair */
    public array $pair;
}
