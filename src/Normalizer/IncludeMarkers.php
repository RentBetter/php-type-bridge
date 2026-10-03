<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Normalizer;

use BackedEnum;
use Closure;
use LogicException;
use PTGS\TypeBridge\Enum\ApiEnum;
use PTGS\TypeBridge\Http\Include\EnumCase;
use PTGS\TypeBridge\Http\Include\IncludeResolver;
use PTGS\TypeBridge\Http\Include\Optional;
use PTGS\TypeBridge\Http\Include\Ref;
use PTGS\TypeBridge\Http\Include\RefNormalizer;
use Stringable;

/**
 * The helpers a shape normaliser marks what a request shapes with: optional() for a key the
 * request has to `?include=`, ref() and refs() for a related record it can `?expand=`, enum() for
 * an enum it can ask for whole or as ids. None of them computes anything: {@see IncludeResolver}
 * writes the markers out when the response is serialised, so no controller has to know what its
 * shapes point at.
 *
 * A shape such a normaliser writes holds scalars, lists and shapes of them, and these markers —
 * no object for json_encode to turn into JSON on its own terms — so what reaches the wire is
 * decided in the normaliser. ShapeValuesRule (includes.neon) holds the line.
 *
 * ref(), refs() and enum() take null and give null back, so an absent relation is one call and
 * a null filter drops it; given a value, each promises a marker, so a required key stays checked.
 */
trait IncludeMarkers
{
    /**
     * A key the request has to ask for by its path in `?include=` — `checks.debug` — computed
     * only when it does. The shape declares it `included<T>`. Keep the closure to data already in
     * hand: it runs once per row, so one that queries is an N+1; a related entity is a ref(),
     * which expands a whole response's worth in one query.
     *
     * @template T
     *
     * @param Closure(): T $value
     *
     * @return Optional<T>
     */
    protected function optional(Closure $value): Optional
    {
        return new Optional($value);
    }

    /**
     * A reference to a related entity: sent as its bare id, or as the record itself where the
     * request's `?expand=` names the key's path, shaped by the {@see RefNormalizer} for its class.
     * The shape declares the key `ref<RecordData>`, naming the shape that normaliser writes. A
     * relation held only as an id is a `new Ref(Entity::class, $id)`, with nothing loaded.
     *
     * @return ($entity is null ? null : Ref)
     */
    protected function ref(?object $entity): ?Ref
    {
        return null === $entity ? null : new Ref($this->refClass($entity), $this->refId($entity));
    }

    /**
     * References to a collection of related entities, each sent as its id unless expanded.
     *
     * @param iterable<object> $entities
     *
     * @return list<Ref>
     */
    protected function refs(iterable $entities): array
    {
        $refs = [];
        foreach ($entities as $entity) {
            $refs[] = new Ref($this->refClass($entity), $this->refId($entity));
        }

        return $refs;
    }

    /**
     * An enum. An {@see ApiEnum} is sent as the object it serialises to, or as its bare id to a
     * request sending `X-Enum-Format: id`; an expand naming the key's path sends it whole either
     * way, or cut to a field list (`checks.status(name)`). Any other backed enum is sent as its
     * value. The shape declares the key `enum<Status>`. One place for every enum a normaliser
     * sends, so a change to how they go out has one place to go.
     *
     * @template T of ApiEnum|BackedEnum
     *
     * @param T|null $case
     *
     * @return ($case is null ? null : EnumCase<T>)
     */
    protected function enum(ApiEnum|BackedEnum|null $case): ?EnumCase
    {
        return null === $case ? null : new EnumCase($case);
    }

    /**
     * The class a reference names: the entity's own. RefRegistry matches it with is_a(), so a
     * Doctrine proxy's class finds its entity's normaliser.
     *
     * @return class-string
     */
    protected function refClass(object $entity): string
    {
        return $entity::class;
    }

    /**
     * The id a reference sends: the entity's getId(), as a string. Override it for entities that
     * carry their id some other way.
     */
    protected function refId(object $entity): string
    {
        if (!method_exists($entity, 'getId')) {
            throw new LogicException(\sprintf('%s has no getId(), so ref() cannot name it: override refId() for it, or make the Ref directly.', $entity::class));
        }

        $id = $entity->getId();
        if (\is_string($id)) {
            return $id;
        }
        if (\is_int($id) || $id instanceof Stringable) {
            return (string) $id;
        }

        throw new LogicException(\sprintf('%s::getId() returned %s, which a reference cannot send as an id: override refId() for it.', $entity::class, get_debug_type($id)));
    }
}
