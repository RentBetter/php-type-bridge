<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\BrokenAttributeFixtures\Notes;

/**
 * An attribute whose declaration needs a require-dev class: loading it throws.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class AuditedAttribute extends NotInstalledBase
{
}
