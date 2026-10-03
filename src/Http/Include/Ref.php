<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

/**
 * A reference to a related record in a normalised shape: sent as the record's id, or — where the
 * request's `?expand=` names its path — as the record itself, shaped by the {@see RefNormalizer}
 * for its entity. Made by IncludeMarkers::ref(), or directly for a relation held only as an id;
 * written out by IncludeResolver. A shape declares such a key as `ref<RecordData>`, naming the
 * shape it expands to, and TypeBridge emits it as `Ref<RecordData>`.
 */
final readonly class Ref
{
    /**
     * @param class-string $class the entity it points at; a proxy or subclass expands too
     */
    public function __construct(
        public string $class,
        public string $id,
    ) {}
}
