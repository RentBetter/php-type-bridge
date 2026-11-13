<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

use RuntimeException;

/**
 * An expand reached a reference the app's ExpandGuard will not expand for this request. It names
 * the path, never the entity, since the message goes back to the caller.
 */
final class ExpandRefusedException extends RuntimeException
{
    public function __construct(
        public readonly string $path,
    ) {
        parent::__construct(\sprintf('`%s` cannot be expanded by this caller.', $path));
    }
}
