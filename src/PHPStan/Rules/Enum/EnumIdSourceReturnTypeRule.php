<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Enum;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PTGS\TypeBridge\Enum\EnumIdSource;

/**
 * Statically enforces the EnumIdSource execution contract: a method referenced by
 * #[EnumIdSource(method: ...)] must be declared `: string` (non-nullable), because
 * that method IS the wire — its literal materialises in the generated TS.
 *
 * The precise literal values can't be known statically (they require execution),
 * but the `: string` contract can be — catching violations before codegen runs.
 * Map/reflection id sources are validated at codegen time by EnumIdResolver.
 *
 * @implements Rule<Enum_>
 */
final class EnumIdSourceReturnTypeRule implements Rule
{
    public function getNodeType(): string
    {
        return Enum_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $method = $this->idSourceMethod($node);
        if (null === $method) {
            return [];
        }

        $enumName = $node->namespacedName?->toString() ?? $node->name?->toString() ?? '<anonymous>';

        $methodNode = $node->getMethod($method);
        if (null === $methodNode) {
            return [$this->failure(
                \sprintf('EnumIdSource method "%s::%s()" does not exist.', $enumName, $method),
                $node->getStartLine(),
            )];
        }

        if ($this->returnsNonNullableString($methodNode)) {
            return [];
        }

        return [$this->failure(
            \sprintf('EnumIdSource method "%s::%s()" must be declared `: string` (non-nullable) so its literal can be emitted.', $enumName, $method),
            $methodNode->getStartLine(),
        )];
    }

    private function idSourceMethod(Enum_ $node): ?string
    {
        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (EnumIdSource::class !== $attribute->name->toString()) {
                    continue;
                }

                foreach ($attribute->args as $index => $arg) {
                    $isMethodArg = (null !== $arg->name && 'method' === $arg->name->toString())
                        || (null === $arg->name && 0 === $index);

                    if ($isMethodArg && $arg->value instanceof String_) {
                        return $arg->value->value;
                    }
                }
            }
        }

        return null;
    }

    private function returnsNonNullableString(ClassMethod $method): bool
    {
        return $method->returnType instanceof Identifier && 'string' === $method->returnType->name;
    }

    private function failure(string $message, int $line): \PHPStan\Rules\IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('typeBridge.enumIdSource.mustReturnString')
            ->line($line)
            ->build();
    }
}
