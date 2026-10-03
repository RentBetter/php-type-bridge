<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QuotedKeyFixtures\Common\View;

use PTGS\TypeBridge\Tests\Fixture\QuotedKeyFixtures\Common\Enum\Kind;

/**
 * A polymorphic reference: the discriminator is `$type`, a key PHPDoc can only spell quoted.
 *
 * @phpstan-type _self = array{'$type': value-of<Kind>, id: string}
 */
final class Reference
{
}
