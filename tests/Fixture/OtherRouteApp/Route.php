<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OtherRouteApp;

use Attribute;

/**
 * Another framework's route attribute, named as Symfony's is: Symfony's loader reads none of it.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Route
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        public string $path,
        public array $methods = [],
    ) {}
}
