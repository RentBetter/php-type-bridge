<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Enum;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A backed enum must not be parsed inside a method that already receives a form DTO:
 * `TaskStatus::tryFrom($value)` in `listByAccount(Account $a, TaskFilterData $filter)` means the
 * DTO typed that field as a string when its domain is really a closed set.
 *
 * Two bugs follow, and the first is the dangerous one:
 *
 * - `tryFrom()` returns null for an unrecognised value and the caller almost always drops it, so
 *   `?status=nonsense` silently returns *unfiltered* results instead of erroring. A wrong answer
 *   that looks right is the worst outcome for a caller that cannot see the query it produced —
 *   and a generated client or an LLM tool call is exactly that caller.
 * - The emitted contract advertises `string` where the domain is a union, so a client has no way
 *   to learn the valid values. That is the part this package exists to prevent.
 *
 * The fix for both: type the DTO property as the enum and use Symfony's `EnumType` in the form
 * (`multiple: true` for a list). Symfony then rejects an unknown value with a 422 before the
 * service is reached, the service takes the enum directly, and the emitted type is the union.
 *
 * The rule keys off the enclosing method's parameters rather than the parsed expression, because
 * by the time the value reaches `tryFrom` it is usually a loop variable
 * (`foreach ($filter->status as $value)`) — matching on the argument misses the commonest shape.
 *
 * Parsing genuinely-external input — a webhook body, a CSV row, a hostname — happens in methods
 * that take raw strings or arrays rather than form DTOs, so it does not trip this.
 *
 * @implements Rule<StaticCall>
 */
final class NoEnumParseOfDtoPropertyRule implements Rule
{
    private const array PARSE_METHODS = ['tryFrom', 'from'];

    /**
     * @param list<string> $dtoClassSuffixes class-name suffixes treated as form DTOs
     */
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly array $dtoClassSuffixes = ['Data'],
    ) {}

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $method = $node->name->name;
        if (!\in_array($method, self::PARSE_METHODS, true)) {
            return [];
        }

        $enumName = $this->backedEnumName($node, $scope);
        if (null === $enumName) {
            return [];
        }

        $dtoClass = $this->dtoParameterOfEnclosingFunction($scope);
        if (null === $dtoClass) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                '%s::%s() parses an enum in a method taking the DTO %s. Type the DTO property as %s and use '
                . 'EnumType in the form (multiple: true for a list), so an invalid value is a 422 instead of a '
                . 'silently dropped filter.',
                $this->shortName($enumName),
                $method,
                $this->shortName($dtoClass),
                $this->shortName($enumName),
            ))
                ->identifier('typeBridge.noEnumParseOfDtoProperty')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    /**
     * The called class, when it is (or may be) a backed enum. `from`/`tryFrom` are all but unique
     * to backed enums, so a class the ReflectionProvider cannot see gets the benefit of the doubt
     * rather than being skipped — otherwise the rule would go quiet exactly where analysis is
     * weakest, which is the wrong way round for a correctness rule.
     */
    private function backedEnumName(StaticCall $node, Scope $scope): ?string
    {
        if (!$node->class instanceof Node\Name) {
            return null;
        }

        $className = $scope->resolveName($node->class);

        if (!$this->reflectionProvider->hasClass($className)) {
            return $className;
        }

        $reflection = $this->reflectionProvider->getClass($className);

        return $reflection->isEnum() && null !== $reflection->getBackedEnumType() ? $className : null;
    }

    private function dtoParameterOfEnclosingFunction(Scope $scope): ?string
    {
        $function = $scope->getFunction();
        if (null === $function) {
            return null;
        }

        // The declared signature, not a call-site-resolved one — there is no call being resolved
        // here, only the enclosing method's own parameters.
        $variant = $function->getVariants()[0];

        foreach ($variant->getParameters() as $parameter) {
            foreach ($parameter->getType()->getObjectClassNames() as $className) {
                foreach ($this->dtoClassSuffixes as $suffix) {
                    if (str_ends_with($className, $suffix)) {
                        return $className;
                    }
                }
            }
        }

        return null;
    }

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');

        return false === $position ? $className : substr($className, $position + 1);
    }
}
