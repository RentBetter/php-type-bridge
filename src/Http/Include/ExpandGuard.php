<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

/**
 * Whether the current request may expand a reference to an entity — the app's answer, for an app
 * whose callers may not see every record a reference leads to. Without one, every reference
 * expands.
 *
 * IncludeResolver asks it about each entity an expand reaches, once a request, before anything of
 * that entity is fetched; a refusal is an ExpandRefusedException, which the subscriber answers
 * with a 403.
 */
interface ExpandGuard
{
    /**
     * @param class-string $entityClass the entity a RefNormalizer expands, never a Doctrine proxy
     */
    public function mayExpand(string $entityClass): bool;
}
