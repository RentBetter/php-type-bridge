<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Controller;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Controller\ApiResponsesRequiredRule;

/**
 * @extends RuleTestCase<ApiResponsesRequiredRule>
 */
final class ApiResponsesRequiredRuleTest extends RuleTestCase
{
    protected function getRule(): ApiResponsesRequiredRule
    {
        return new ApiResponsesRequiredRule();
    }

    public function testAcceptsAnnotatedApiControllerMethods(): void
    {
        $this->analyse([
            __DIR__ . '/../../../Fixture/Fixtures/Projects/Controller/ProjectController.php',
        ], []);
    }

    public function testRejectsMissingApiResponsesAttribute(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Controller/Negative/MissingApiResponsesController.php',
        ], [[
            'API controller method "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Controller\Negative\MissingApiResponsesController::index" must declare #[ApiResponses([...])].',
            12,
        ]]);
    }
}
