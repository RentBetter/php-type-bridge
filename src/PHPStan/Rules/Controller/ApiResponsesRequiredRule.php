<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Controller;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PTGS\TypeBridge\PHPStan\Support\ApiMethodInspector;

/**
 * @implements Rule<ClassMethod>
 */
final class ApiResponsesRequiredRule implements Rule
{
    public function __construct(
        private readonly ApiMethodInspector $apiMethodInspector = new ApiMethodInspector(),
    ) {}

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return [];
        }

        $methodName = $node->name->toString();
        $method = $this->apiMethodInspector->inspect($classReflection->getName(), $methodName);
        if (null === $method || !$method->isApiRoute() || $method->hasApiResponses) {
            return [];
        }

        return [RuleErrorBuilder::message(\sprintf(
            'API controller method "%s::%s" must declare #[ApiResponses([...])].',
            $classReflection->getName(),
            $methodName,
        ))
            ->identifier('typeBridge.apiResponsesRequired')
            ->line($node->getStartLine())
            ->build()];
    }
}
