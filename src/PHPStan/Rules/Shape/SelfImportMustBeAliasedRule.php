<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Shape;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Requires an imported `_self` type alias to be renamed at the import site with `as`.
 *
 * `_self` names the shape a class declares for *itself*. Imported without renaming, it keeps
 * that name in the importing class, where `@return _self` then reads as "this class's own
 * shape" while actually meaning another class's — and the importing class, being a normalizer
 * or a response DTO, has no shape of its own to confuse it with.
 *
 * The alias also may not collide with a class in scope: PHPStan rejects those with
 * `Type alias X already exists as a class in scope`, so an entity's own name is never
 * available to its shape. `<Something>Data` is the convention.
 *
 * Fires only where `_self` is imported, so it cannot reach code that has not adopted the
 * contract conventions.
 *
 * @implements Rule<ClassLike>
 */
final class SelfImportMustBeAliasedRule implements Rule
{
    private const string PATTERN = '/@phpstan-import-type\s+_self\s+from\s+(?P<source>[\\\\\w]+)(?P<alias>\s+as\s+\w+)?/';

    public function getNodeType(): string
    {
        return ClassLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (null === $docComment = $node->getDocComment()) {
            return [];
        }

        if (0 === preg_match_all(self::PATTERN, $docComment->getText(), $matches, \PREG_SET_ORDER)) {
            return [];
        }

        $errors = [];
        foreach ($matches as $match) {
            if ('' !== ($match['alias'] ?? '')) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Import of `_self` from %s must be aliased — `@phpstan-import-type _self from %s as <Something>Data`. '
                    . 'Unaliased it is imported under the name `_self`, which in this class reads as its own shape '
                    . 'while meaning another class\'s. The alias may not match a class already in scope.',
                $match['source'],
                $match['source'],
            ))
                ->identifier('typeBridge.shape.selfImportMustBeAliased')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }
}
