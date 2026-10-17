<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnresolvedImportUsedFixtures\Common\View;

/**
 * @phpstan-import-type _self from \Vendor\Missing\Gone as Gone
 * @phpstan-type _self = array{
 *     gone: Gone,
 * }
 */
final class ThingView {}
