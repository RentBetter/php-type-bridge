<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

/**
 * How the orchestrator decides which classes an emitter renders.
 */
enum EmitMode
{
    /**
     * Emit only classes that are "touched" — reached by reference from another
     * emitted declaration (e.g. an enum referenced via value-of). This is the
     * behaviour of the built-in conventions.
     */
    case Referenced;

    /**
     * Scan every class and emit each one whose claims() returns true, regardless
     * of whether anything references it. Needed for discovery-driven catalogues
     * such as the RB API-enum convention.
     */
    case Discovered;
}
