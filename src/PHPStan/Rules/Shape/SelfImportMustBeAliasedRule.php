<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Shape;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
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
 * The import tags come from PHPStan's resolved PHPDoc rather than a regex over raw docblock
 * text: the tag is parsed properly and its source class resolved against the file's imports,
 * so a docblock wrapped across lines, or one naming its source by short name, alias or
 * fully-qualified name, all read the same way here.
 *
 * @implements Rule<InClassNode>
 */
final class SelfImportMustBeAliasedRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];
        $line = $node->getOriginalNode()->getStartLine();

        foreach ($node->getClassReflection()->getResolvedPhpDoc()?->getTypeAliasImportTags() ?? [] as $tag) {
            if ('_self' !== $tag->getImportedAlias() || null !== $tag->getImportedAs()) {
                continue;
            }

            // The short name is what the author wrote and what the suggested fix has to echo
            // back; the resolved name is correct but unreadable in a message.
            $source = self::shortName($tag->getImportedFrom());

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Import of `_self` from %s must be aliased — `@phpstan-import-type _self from %s as <Something>Data`. '
                    . 'Unaliased it is imported under the name `_self`, which in this class reads as its own shape '
                    . 'while meaning another class\'s. The alias may not match a class already in scope.',
                $source,
                $source,
            ))
                ->identifier('typeBridge.shape.selfImportMustBeAliased')
                ->line($line)
                ->build();
        }

        return $errors;
    }

    private static function shortName(string $className): string
    {
        $position = strrpos($className, '\\');

        return false === $position ? $className : substr($className, $position + 1);
    }
}
