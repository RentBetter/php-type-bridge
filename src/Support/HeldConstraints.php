<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\When;

/**
 * The constraints a value is held to, read through what nests them. What a Sequentially or a
 * Compound nests holds whenever it does, as if written beside it; what an AtLeastOneOf or a When
 * nests only may; and what an All or a Collection nests holds the value's items or keys, not it.
 *
 * A request is validated in the Default group, and a composite validates what it nests in the group
 * it is validated in: one confined to other groups holds nothing, however deep — a nested one with
 * no groups of its own takes its composite's.
 */
final class HeldConstraints
{
    /**
     * Each constraint, and each one a Sequentially or a Compound among them nests, at any depth:
     * those a value is always held to.
     *
     * @param list<Constraint> $constraints
     *
     * @return list<Constraint>
     */
    public static function always(array $constraints): array
    {
        $held = [];
        foreach (array_filter($constraints, self::validatedByDefault(...)) as $constraint) {
            $held[] = $constraint;
            if ($constraint instanceof Sequentially || $constraint instanceof Compound) {
                $held = [...$held, ...self::always(self::nested($constraint))];
            }
        }

        return $held;
    }

    /**
     * Each constraint, and each one nested in it that may hold the same value: through any
     * composite but an All or a Collection, and either branch of a When.
     *
     * @param list<Constraint> $constraints
     *
     * @return list<Constraint>
     */
    public static function any(array $constraints): array
    {
        $reached = [];
        foreach (array_filter($constraints, self::validatedByDefault(...)) as $constraint) {
            $reached[] = $constraint;
            // A When is a Composite, and asked first: both its branches, whatever a Symfony version's
            // Composite says it nests.
            $nested = match (true) {
                $constraint instanceof All, $constraint instanceof Collection => [],
                $constraint instanceof When => [...self::listOf($constraint->constraints), ...self::listOf($constraint->otherwise)],
                $constraint instanceof Composite => self::nested($constraint),
                default => [],
            };
            $reached = [...$reached, ...self::any($nested)];
        }

        return $reached;
    }

    /**
     * Whether a request is validated against the constraint: it is in the Default group.
     */
    public static function validatedByDefault(Constraint $constraint): bool
    {
        $groups = $constraint->groups;

        return null === $groups || \in_array(Constraint::DEFAULT_GROUP, $groups, true);
    }

    /**
     * @return list<Constraint>
     */
    private static function nested(Composite $constraint): array
    {
        return self::listOf($constraint->getNestedConstraints());
    }

    /**
     * @param array<mixed>|Constraint $constraints
     *
     * @return list<Constraint>
     */
    private static function listOf(array|Constraint $constraints): array
    {
        return array_values(array_filter(\is_array($constraints) ? $constraints : [$constraints], static fn (mixed $one): bool => $one instanceof Constraint));
    }
}
