<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

final class DomainGuesser
{
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

        $domain = $segments[0];

        return '' === $domain ? 'Common' : $domain;
    }
}
