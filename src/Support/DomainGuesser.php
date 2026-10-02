<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use InvalidArgumentException;

/**
 * The domain a source file belongs to, which is the module its types are emitted into: the
 * directories it sits in, from the source root, to `depth` levels. At 1, `Admin/SystemChecks/
 * Entity/Check.php` is in `Admin`; at 2, in `Admin/SystemChecks`. A file in fewer directories
 * than that is in all of them, and a file directly under the root is its own domain.
 *
 * A file under one of the root sources is in the root domain, `''`, whatever its depth: a
 * directory source covers everything beneath it, a file source only itself.
 */
final readonly class DomainGuesser
{
    /**
     * @param list<string> $rootSources paths relative to the source root, `/`-separated
     */
    public function __construct(
        private int $depth = 1,
        private array $rootSources = [],
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

        if ($this->isRootSource(str_replace('\\', '/', $relative))) {
            return '';
        }

        $segments = preg_split('#[\\\\/]#', $relative);
        if (false === $segments) {
            return 'Common';
        }

        $directories = \array_slice($segments, 0, -1);
        $domain = [] === $directories ? $segments[0] : implode('/', \array_slice($directories, 0, $this->depth));

        return '' === $domain ? 'Common' : $domain;
    }

    private function isRootSource(string $relative): bool
    {
        foreach ($this->rootSources as $source) {
            if ($relative === $source || str_starts_with($relative, $source . '/')) {
                return true;
            }
        }

        return false;
    }
}
