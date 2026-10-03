<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Enum;

use JsonSerializable;
use UnitEnum;

/**
 * An enum the API sends as an object — `{"id": "WARNING", "name": "Warning", …}`, whatever
 * jsonSerialize() returns — and as its bare id to a request that asks for ids.
 *
 * A normaliser hands a case over through IncludeMarkers::enum(), and IncludeResolver writes it
 * out: whole, as jsonSerialize() returns it; as id() alone, to a request sending
 * `X-Enum-Format: id`; or cut to the field list an expand path gives it (`checks.status(name)`).
 * An expand naming the enum's path sends it whole even to a request that asked for ids.
 *
 * A backed enum that does not implement this goes out as its value, whichever the request asks for.
 */
interface ApiEnum extends UnitEnum, JsonSerializable
{
    /**
     * The case's stable id: what goes out in the case's place to a request that asks for ids,
     * and conventionally the `id` key of what jsonSerialize() returns — the key a field list
     * always keeps.
     */
    public function id(): string;

    /**
     * @return array<string, mixed> the case as an object
     */
    public function jsonSerialize(): array;
}
