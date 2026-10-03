<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

/**
 * A normaliser that can expand references to its entity: given the ids a response's refs named
 * at expanded paths, it fetches them in one query and returns their shapes, in any order. A shape
 * it returns may hold refs, optionals and enums of its own, which IncludeResolver writes out in
 * turn as the request's paths go on to name them.
 *
 * TypeBridgeBundle tags every autoconfigured implementation with {@see self::TAG}, which is how
 * RefRegistry finds it.
 */
interface RefNormalizer
{
    public const string TAG = 'type_bridge.ref_normalizer';

    /**
     * @return class-string the entity whose refs it expands; a proxy or subclass matches too
     */
    public static function entityClass(): string;

    /**
     * @param list<string> $ids
     *
     * @return list<array<string, mixed>> each shape with its `id`, so the ref it answers is found
     */
    public function resolveMany(array $ids): array;
}
