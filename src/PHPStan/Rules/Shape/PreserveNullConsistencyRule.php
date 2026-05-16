<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Shape;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use Throwable;

/**
 * Enforces the TypeBridge `preserveNull` invariant on @phpstan-type shapes.
 *
 * For each nullable field declared in a @phpstan-type alias on a class:
 *
 *   - If the field is listed in `preserveNull` config, its annotation MUST be `T|null`
 *     (will emit as `field: T | null`).
 *   - Otherwise its annotation MUST be `?T` (will emit as `field?: T`).
 *
 * The runtime emitter applies the same check; this rule surfaces drift at analysis time
 * before it can ship as a broken codegen.
 *
 * @implements Rule<Class_>
 */
final class PreserveNullConsistencyRule implements Rule
{
    /** @var array<string, true> indexed by "ShapeName.fieldName" */
    private readonly array $preserveNullIndex;

    /**
     * @param list<string> $preserveNull list of "ShapeName.fieldName" entries
     */
    public function __construct(
        array $preserveNull = [],
        private readonly PhpDocShapeParser $parser = new PhpDocShapeParser(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
    ) {
        $this->preserveNullIndex = array_fill_keys($preserveNull, true);
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $docComment = $node->getDocComment();
        if (null === $docComment) {
            return [];
        }

        $definitions = $this->docHelper->extractPhpStanTypes($docComment->getText());
        if ([] === $definitions) {
            return [];
        }

        $ownerClass = $node->namespacedName?->toString()
            ?? $scope->getClassReflection()?->getName()
            ?? ($node->name->name ?? '<anonymous>');
        $startLine = $node->getStartLine();

        $errors = [];
        foreach ($definitions as $alias => $raw) {
            try {
                $parsed = $this->parser->parse($raw);
            } catch (Throwable) {
                continue;
            }

            $shape = $this->extractShape($parsed);
            if (null === $shape) {
                continue;
            }

            $shapeName = $this->shapeName($alias, $ownerClass);
            foreach ($shape->fields as $field) {
                $error = $this->checkField($field, $shapeName, $ownerClass, $startLine);
                if (null !== $error) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    private function extractShape(ParsedType $parsed): ?ShapeType
    {
        if ($parsed instanceof ShapeType) {
            return $parsed;
        }

        if ($parsed instanceof IntersectionType) {
            return $parsed->extra;
        }

        return null;
    }

    private function shapeName(string $alias, string $ownerClass): string
    {
        if ('_self' !== $alias) {
            return $alias;
        }

        $position = strrpos($ownerClass, '\\');

        return false === $position ? $ownerClass : substr($ownerClass, $position + 1);
    }

    private function checkField(ShapeField $field, string $shapeName, string $ownerClass, int $line): ?\PHPStan\Rules\IdentifierRuleError
    {
        if (!$field->type instanceof NullableType) {
            return null;
        }

        $key = $shapeName . '.' . $field->name;
        $inPreserveNull = isset($this->preserveNullIndex[$key]);

        if ($inPreserveNull && $field->type->optional) {
            return RuleErrorBuilder::message(\sprintf(
                'Field `%s` in shape `%s` (on %s) is listed in `typeBridge.preserveNull` '
                . 'but its @phpstan-type annotation is `?T`. Change the annotation to '
                . '`T|null` so the wire emits the null, or remove `%s` from preserveNull.',
                $field->name,
                $shapeName,
                $ownerClass,
                $key,
            ))
                ->identifier('typeBridge.preserveNull.shouldBeExplicitNull')
                ->line($line)
                ->build();
        }

        if (!$inPreserveNull && !$field->type->optional) {
            return RuleErrorBuilder::message(\sprintf(
                'Field `%s` in shape `%s` (on %s) has `T|null` annotation but is not '
                . 'listed in `typeBridge.preserveNull`. Change the annotation to `?T` '
                . '(emits as `%s?: T`; field is omitted when null), or add `%s` to '
                . 'preserveNull if null is semantically meaningful for this field.',
                $field->name,
                $shapeName,
                $ownerClass,
                $field->name,
                $key,
            ))
                ->identifier('typeBridge.preserveNull.shouldBeOptional')
                ->line($line)
                ->build();
        }

        return null;
    }
}
