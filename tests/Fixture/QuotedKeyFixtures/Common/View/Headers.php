<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QuotedKeyFixtures\Common\View;

/**
 * Keys that are not TypeScript identifiers either, and an inline shape carrying the reference.
 *
 * @phpstan-import-type _self from Reference as ReferenceData
 *
 * @phpstan-type _self = array{
 *     "content-type": string,
 *     'x-retry'?: int,
 *     subject: ReferenceData,
 *     inline: array{'$type': string, id: string},
 * }
 */
final class Headers
{
}
