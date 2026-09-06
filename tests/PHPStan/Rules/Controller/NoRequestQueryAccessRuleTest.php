<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Rules\Controller;

use PHPStan\Testing\RuleTestCase;
use PTGS\TypeBridge\PHPStan\Rules\Controller\NoRequestQueryAccessRule;

/**
 * @extends RuleTestCase<NoRequestQueryAccessRule>
 */
final class NoRequestQueryAccessRuleTest extends RuleTestCase
{
    protected function getRule(): NoRequestQueryAccessRule
    {
        return new NoRequestQueryAccessRule();
    }

    public function testRejectsRawQueryAndRequestBagAccess(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixtures/Controller/Negative/RawRequestQueryController.php',
        ], [
            [
                '$request->query->getString() reads input straight off the Request. Bind it with a form and '
                . 'declare #[ApiRequest(query: MyFilterType::class)], so the parameter appears in the generated '
                . 'contract and an invalid value is a 422 rather than a silently ignored filter.',
                16,
            ],
            [
                '$request->query->getInt() reads input straight off the Request. Bind it with a form and '
                . 'declare #[ApiRequest(query: MyFilterType::class)], so the parameter appears in the generated '
                . 'contract and an invalid value is a 422 rather than a silently ignored filter.',
                17,
            ],
            [
                '$request->request->get() reads input straight off the Request. Bind it with a form and '
                . 'declare #[ApiRequest(body: MyFilterType::class)], so the parameter appears in the generated '
                . 'contract and an invalid value is a 422 rather than a silently ignored filter.',
                24,
            ],
        ]);
    }

    public function testAcceptsControllersThatBindThroughForms(): void
    {
        $this->analyse([
            __DIR__ . '/../../../Fixture/Fixtures/Projects/Controller/ProjectController.php',
        ], []);
    }
}
