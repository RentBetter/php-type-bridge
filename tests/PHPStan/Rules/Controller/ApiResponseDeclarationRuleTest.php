<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Controller;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Controller\ApiResponseDeclarationRule;

/**
 * @extends RuleTestCase<ApiResponseDeclarationRule>
 */
final class ApiResponseDeclarationRuleTest extends RuleTestCase
{
    protected function getRule(): ApiResponseDeclarationRule
    {
        return new ApiResponseDeclarationRule();
    }

    public function testAcceptsDeclaredResponseTypes(): void
    {
        $this->analyse([
            __DIR__ . '/../../../Fixture/Fixtures/Projects/Controller/ProjectController.php',
        ], []);
    }

    public function testRejectsUndeclaredReturnType(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Controller/Negative/UndeclaredReturnController.php',
        ], [[
            'API controller method "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Controller\Negative\UndeclaredReturnController::show" returns "PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\ShowProjectResponse" but it is missing from #[ApiResponses].',
            14,
        ]]);
    }

    public function testRejectsUndeclaredThrownErrorType(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Controller/Negative/UndeclaredThrowController.php',
        ], [[
            'API controller method "PTGS\TypeBridge\Tests\PHPStan\Fixtures\Controller\Negative\UndeclaredThrowController::show" throws "PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Response\ValidationErrorResponse" but it is missing from #[ApiResponses].',
            14,
        ]]);
    }
}
