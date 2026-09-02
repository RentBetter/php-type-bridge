<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Controller;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Controller\MutatingApiRequestRequiredRule;

/**
 * @extends RuleTestCase<MutatingApiRequestRequiredRule>
 */
final class MutatingApiRequestRequiredRuleTest extends RuleTestCase
{
    protected function getRule(): MutatingApiRequestRequiredRule
    {
        return new MutatingApiRequestRequiredRule();
    }

    public function testAcceptsAnnotatedMutatingApiControllerMethods(): void
    {
        $this->analyse([
            __DIR__ . '/../../../Fixture/Fixtures/Projects/Controller/ProjectController.php',
        ], []);
    }

    public function testAcceptsABareApiRequestOnAMutatingMethodWithNoInput(): void
    {
        // A mutating route that takes nothing still declares #[ApiRequest], bare, so the
        // absence of input is stated rather than an omission for the rule to catch.
        $this->analyse([
            __DIR__ . '/../../Fixtures/Controller/Positive/NoInputMutatingController.php',
        ], []);
    }

    public function testRejectsMutatingEndpointsWithoutApiRequest(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Controller/Negative/MissingApiRequestController.php',
        ], [[
            'Mutating API controller method "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Controller\Negative\MissingApiRequestController::create" must declare #[ApiRequest(...)].',
            13,
        ]]);
    }
}
