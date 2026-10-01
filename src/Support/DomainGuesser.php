<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use InvalidArgumentException;

/**
 * The domain a source file belongs to, which is the module its types are emitted into: the
 * directories it sits in, from the source root, to `depth` levels. At 1, `Admin/SystemChecks/
 * Entity/Check.php` is in `Admin`; at 2, in `Admin/SystemChecks`. A file in fewer directories
 * than that is in all of them, and a file directly under the root is its own domain.
 */
final readonly class DomainGuesser
{
    public function __construct(
        private int $depth = 1,
    ) {
        if ($depth < 1) {
            throw new InvalidArgumentException(\sprintf('A domain is at least one directory deep; got a depth of %d.', $depth));
        }
    }

    public function guess(string $srcDir, string $file): string
    {
        $relative = ltrim(str_replace($srcDir, '', $file), DIRECTORY_SEPARATOR);
        if ('' === $relative) {
            return 'Common';
        }

        $segments = preg_split('#[\\\\/]#', $relative);
        if (false === $segments) {
            return 'Common';
        }

        $directories = \array_slice($segments, 0, -1);
        $domain = [] === $directories ? $segments[0] : implode('/', \array_slice($directories, 0, $this->depth));

        return '' === $domain ? 'Common' : $domain;
    }
}
