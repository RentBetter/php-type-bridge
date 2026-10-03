<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

use BackedEnum;
use LogicException;
use PTGS\TypeBridge\Contract\ApiSuccessResponse;
use PTGS\TypeBridge\Enum\ApiEnum;

/**
 * Builds a response DTO's JSON body against the request's `?include=` and `?expand=`:
 *
 * - every Optional is resolved where the include names its path, and its key dropped where not;
 * - every Ref is the record it points at where the expand names its path, and its id where not;
 * - every EnumCase is its case — or its bare id, to a request that asked for ids — and whole
 *   wherever the expand names its path.
 *
 * Expanded records are fetched a level at a time: every ref the paths reach at one level is
 * fetched by its entity's RefNormalizer — one query per entity, however many rows point at it —
 * and each record is then walked like the rest of the body, so a ref inside it expands when the
 * path goes on to name it. Paths are finite, so the levels are too: a reference cycle is only
 * followed as far as a path spells it out.
 *
 * A field list on any expand path cuts what is there to those keys and its `id`, keeping any key
 * a deeper path goes on into: a related record (`checks.definition(name)`), an enum
 * (`checks.status(name)`), or the rows themselves (`checks(name,status)`) — the leanest response
 * a request can ask for. An enum an expand names goes out whole, even to a request that asked
 * for ids.
 *
 * Markers are found in the response's public properties and the arrays beneath them; an object
 * other than a marker is left for json_encode, and is not looked into.
 *
 * Controllers never see any of it: they normalise and return the DTO.
 */
final readonly class IncludeResolver
{
    public function __construct(
        private RefRegistry $refs,
    ) {}

    /**
     * @param bool $enumIds write each enum an expand did not name as its bare id (`X-Enum-Format: id`)
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidIncludeException a path this response has nothing for, or an expand asking
     *                                 for something it cannot give
     */
    public function body(ApiSuccessResponse $response, IncludeTree $include, IncludeTree $expand, bool $enumIds = false): array
    {
        $fields = get_object_vars($response);
        self::assertRoots($include, 'include', $fields);
        self::assertRoots($expand, 'expand', $fields);

        $pending = [];
        $body = $this->walkEntries($fields, $include, $expand, '', $pending);

        $this->expand($pending);

        return array_map(static fn (mixed $value): mixed => self::settle($value, $enumIds), $body);
    }

    /**
     * Resolves or drops each Optional beneath a node, stands an ExpandedRef in for each Ref the
     * expand names, and cuts whatever an expand's field list names. A list's items sit at the same
     * point in the trees as the list itself.
     *
     * @param list<ExpandedRef> $pending the refs to fetch, added to as they are found
     */
    private function walk(mixed $node, ?IncludeTree $include, ?IncludeTree $expand, string $path, array &$pending): mixed
    {
        if ($node instanceof Ref) {
            return null === $expand ? $node->id : $pending[] = new ExpandedRef($node, $path, $include, $expand);
        }

        // Left for settle() unless expanded: whether it goes out whole or as its id is the request's.
        if ($node instanceof EnumCase) {
            if (null === $expand) {
                return $node;
            }
            if (!$node->case instanceof ApiEnum) {
                throw new InvalidIncludeException('expand', \sprintf('`%s` is a single value, so there is nothing to expand or narrow.', $path));
            }
            if ([] !== $expand->keys()) {
                throw new InvalidIncludeException('expand', \sprintf('`%s` is an enum, with nothing to expand inside it; a field list picks its keys, as `%1$s(name)`.', $path));
            }

            return self::narrow(self::plain($node->case), $expand, $include);
        }

        if (null === $expand || null === $node || (\is_array($node) && array_is_list($node))) {
            return \is_array($node) ? $this->walkEntries($node, $include, $expand, $path, $pending) : $node;
        }

        if (!\is_array($node)) {
            throw new InvalidIncludeException('expand', \sprintf('`%s` is a single value, so there is nothing to expand or narrow.', $path));
        }

        // Ending here without a field list, an object asks for nothing it does not already have.
        if (null === $expand->fields() && [] === $expand->keys()) {
            throw new InvalidIncludeException('expand', \sprintf('`%s` is not a reference, so there is nothing to expand; a field list narrows it, as `%1$s(id)`.', $path));
        }

        return $this->walkEntries(self::narrow($node, $expand, $include), $include, $expand, $path, $pending);
    }

    /**
     * Walks each entry of an array — the response's own fields first of all, at the root path
     * `''`. A fetched record starts here rather than in walk(): the path that expanded it may end
     * at it, and the record is what that path asked for.
     *
     * @param array<array-key, mixed> $node
     * @param list<ExpandedRef> $pending
     *
     * @return array<array-key, mixed>
     */
    private function walkEntries(array $node, ?IncludeTree $include, ?IncludeTree $expand, string $path, array &$pending): array
    {
        $out = [];
        foreach ($node as $key => $value) {
            $isItem = \is_int($key);
            $childInclude = $isItem ? $include : $include?->child($key);

            if ($value instanceof Optional) {
                if (null === $childInclude) {
                    continue;
                }
                $value = $value->resolve();
            }

            $out[$key] = $isItem
                ? $this->walk($value, $childInclude, $expand, $path, $pending)
                : $this->walk($value, $childInclude, $expand?->child($key), '' === $path ? $key : "{$path}.{$key}", $pending);
        }

        return array_is_list($node) ? array_values($out) : $out;
    }

    /**
     * Fetches the records the pending refs point at, a level at a time, and walks each into its
     * ExpandedRef — which may find refs at the next level down.
     *
     * @param list<ExpandedRef> $pending
     */
    private function expand(array $pending): void
    {
        /** @var array<class-string, array<string, array<string, mixed>>> $records by entity, then id */
        $records = [];

        while ([] !== $pending) {
            $level = $pending;
            $pending = [];

            /** @var array<class-string, RefNormalizer> $normalizers */
            $normalizers = [];
            /** @var array<class-string, array<string, true>> $wanted the ids to fetch, by entity */
            $wanted = [];
            foreach ($level as $expanded) {
                $normalizer = $this->normalizerFor($expanded->ref);
                $entity = $normalizer::entityClass();
                if (!isset($records[$entity][$expanded->ref->id])) {
                    $normalizers[$entity] = $normalizer;
                    $wanted[$entity][$expanded->ref->id] = true;
                }
            }

            foreach ($wanted as $entity => $ids) {
                $normalizer = $normalizers[$entity];
                foreach ($normalizer->resolveMany(array_map(strval(...), array_keys($ids))) as $record) {
                    $id = $record['id'] ?? null;
                    if (!\is_string($id) && !\is_int($id)) {
                        throw new LogicException(\sprintf('%s::resolveMany() returned a %s without its id.', $normalizer::class, $entity));
                    }
                    $records[$entity][(string) $id] = $record;
                }
            }

            foreach ($level as $expanded) {
                $entity = $this->normalizerFor($expanded->ref)::entityClass();
                $record = $records[$entity][$expanded->ref->id]
                    ?? throw new LogicException(\sprintf('`%s` references %s %s, and fetching it found nothing.', $expanded->path, $entity, $expanded->ref->id));
                $record = self::narrow($record, $expanded->expand, $expanded->include);
                $expanded->value = $this->walkEntries($record, $expanded->include, $expanded->expand, $expanded->path, $pending);
            }
        }
    }

    /**
     * A record cut to its path's field list, when it has one: its `id`, the fields named, and the
     * keys a deeper expand or include path goes on into. Cut before it is walked, so nothing it
     * drops is computed or fetched. A field the record does not have is skipped, not refused: an
     * optional key is absent on some rows, and only the shape could tell the two apart.
     *
     * @param array<array-key, mixed> $record
     *
     * @return array<array-key, mixed>
     */
    private static function narrow(array $record, IncludeTree $expand, ?IncludeTree $include): array
    {
        if (null === $fields = $expand->fields()) {
            return $record;
        }

        return array_intersect_key($record, array_fill_keys(['id', ...$fields, ...$expand->keys(), ...$include?->keys() ?? []], true));
    }

    /**
     * An enum case as the plain array it serialises to, so the bare-ids pass for a request that
     * asked for ids leaves it whole: the expand asked for it.
     *
     * @return array<array-key, mixed>
     */
    private static function plain(ApiEnum $case): array
    {
        $plain = json_decode(json_encode($case, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($plain)) {
            throw new LogicException(\sprintf('%s serialised to something other than an object.', $case::class));
        }

        return $plain;
    }

    private function normalizerFor(Ref $ref): RefNormalizer
    {
        return $this->refs->for($ref->class)
            ?? throw new LogicException(\sprintf('Nothing expands a reference to %s: its shape normaliser needs to implement %s.', $ref->class, RefNormalizer::class));
    }

    /**
     * The body as it goes out: every ExpandedRef replaced by the record it stood in for, and every
     * EnumCase by its case, or its bare id when the request asked for ids.
     */
    private static function settle(mixed $node, bool $enumIds): mixed
    {
        if ($node instanceof ExpandedRef) {
            return self::settle($node->value, $enumIds);
        }

        if ($node instanceof EnumCase) {
            return $enumIds ? self::idOf($node->case) : $node->case;
        }

        return \is_array($node) ? array_map(static fn (mixed $child): mixed => self::settle($child, $enumIds), $node) : $node;
    }

    private static function idOf(ApiEnum|BackedEnum $case): int|string
    {
        return $case instanceof ApiEnum ? $case->id() : $case->value;
    }

    /**
     * Every root of a path names a field of this response. Deeper paths are not checked here:
     * that needs the shapes, which only TypeBridge can compile.
     *
     * @param 'include'|'expand' $param
     * @param array<array-key, mixed> $fields
     */
    private static function assertRoots(IncludeTree $tree, string $param, array $fields): void
    {
        foreach ($tree->keys() as $key) {
            if (!\array_key_exists($key, $fields)) {
                throw new InvalidIncludeException($param, \sprintf('`%s` is not part of this response, whose fields are %s.', $key, implode(', ', array_keys($fields))));
            }
        }
    }
}
