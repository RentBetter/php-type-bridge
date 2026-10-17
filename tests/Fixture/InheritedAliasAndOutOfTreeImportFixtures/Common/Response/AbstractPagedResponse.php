<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedAliasAndOutOfTreeImportFixtures\Common\Response;

use PTGS\TypeBridge\Status\HttpOk;

/**
 * Declares an alias of its own, which PhpDocTypeCollector reads its file for too.
 *
 * @phpstan-type Page = array{
 *     total: int,
 * }
 * @phpstan-import-type _self from \Vendor\Audit\Trail as AuditTrail
 */
abstract class AbstractPagedResponse implements HttpOk
{
    /** @var AuditTrail|null */
    protected ?array $audit = null;

    public function __construct(
        /** @var Page */
        public readonly array $page,
    ) {}
}
