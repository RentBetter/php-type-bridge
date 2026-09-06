<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\MultiPropertyScopeFixtures\Common\Security;

use Attribute;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Security\FixtureScope;

/**
 * A scope attribute that carries more than scopes — the common real shape, modelled on a
 * consumer's #[Authorize]: one scope, plus a route param => entity class map used for
 * ownership checks, plus a flag.
 *
 * Reading every public property off this would collect the entity class names as scopes,
 * which is why `mcpScopeProperty` exists.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Authorize
{
    /**
     * @param array<string, class-string> $entities route param => entity class
     */
    public function __construct(
        public FixtureScope $scope,
        public array $entities = [],
        public bool $allowAccessToken = false,
    ) {}
}
