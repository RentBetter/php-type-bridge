<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Mcp;

/**
 * Regex a tool's schema can carry as a `pattern`: what PCRE, which the server matches with, and
 * ECMA-262 in Unicode mode, which Ajv compiles a schema's pattern as, read alike — and compile. A
 * pattern either reads differently would let a client refuse what the server takes, or send what it
 * refuses; one Ajv can't compile refuses every call.
 */
final class PortableRegex
{
    /**
     * A Regex constraint's pattern as a whole-value rule both dialects read alike: anchored at both
     * ends (`/^…$/`) — unanchored, PCRE matches anywhere and across lines, which no padding with `.*`
     * reproduces — with no alternation outside a group (`^a|b$` is `(^a)|(b$)`) and no flag but `D`
     * and `u`; its body between the anchors, read as {@see self::readsTheSame()} reads it under its
     * `u`, and its flags. Null for any other.
     *
     * @return array{0: string, 1: string}|null the body between the anchors, and the flags
     */
    public static function wholeValueRule(string $pattern): ?array
    {
        $open = $pattern[0] ?? '';
        $close = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'][$open] ?? $open;
        $end = strrpos($pattern, $close);
        if ('' === $open || ctype_alnum($open) || '\\' === $open || false === $end || 0 === $end) {
            return null;
        }

        $flags = substr($pattern, $end + 1);
        if (\strlen($flags) !== strspn($flags, 'Du')) {
            return null;
        }

        $body = substr($pattern, 1, $end - 1);
        // PCRE reads the body as written, an escaped delimiter included. Escaped, one of these is
        // the character itself, which Unicode mode takes only bare; any other keeps its escape for
        // the reading below to judge — `\/` is a slash in both, `\-` in a class is no range and
        // `\,` in a count no quantifier, which unescaping them would make.
        if (str_contains('#~!@%;:\'"=&`', $open)) {
            $body = str_replace('\\' . $open, $open, $body);
        }

        if (!str_starts_with($body, '^') || !str_ends_with($body, '$') || 1 === strspn(strrev(substr($body, 0, -1)), '\\') % 2) {
            return null;
        }
        $inner = substr($body, 1, -1);

        if (!self::readsTheSame($inner, alternationOutsideGroups: false, unicode: str_contains($flags, 'u'))) {
            return null;
        }

        return [$inner, $flags];
    }

    /**
     * A route requirement as a `pattern`: the router matches it against the whole segment, so it is
     * anchored (`^(?:…)$`). The requirement is as the router holds it ({@see \PTGS\TypeBridge\Model\CollectedPathParam::$requirement}),
     * the anchors Symfony strips already off — stripping them again would take an escaped `\$` at
     * its end for one. Null for a requirement holding a `%…%` container placeholder, which the router
     * resolves and the literal text is not, and for one that does not read the same in JSON Schema's
     * dialect ({@see self::readsTheSame()}) under the route's `utf8` option — or under either,
     * when the route leaves it to the router's default.
     */
    public static function routePattern(string $requirement, ?bool $utf8): ?string
    {
        if ('' === $requirement || str_contains($requirement, '%') || !self::readsTheSame($requirement, alternationOutsideGroups: true, unicode: $utf8)) {
            return null;
        }

        return '^(?:' . $requirement . ')$';
    }

    /**
     * Whether a regex fragment reads the same, and compiles, as ECMA-262 in Unicode mode — how Ajv
     * compiles a schema's `pattern`, and an argument check that can't compile refuses every call.
     * Read token by token against what both dialects agree on, rather than searched for what they
     * don't:
     *
     * - printable ASCII literals; outside a class, the syntax characters only escaped;
     * - the escapes `\d \D \w \W \b \B \n \r \t \f` and escaped syntax characters (`\.`, `\/`, …) —
     *   not `\s` or `\v`, whose sets differ, nor a backreference, an octal, `\x`, `\p` or an escaped
     *   letter or `_`;
     * - classes of literals, ranges between two literals in order, and `\d \D \w \W` — no nested
     *   bracket, so no POSIX class, and no range from a class escape (`[\w-.]`);
     * - plain and `(?:` groups, `*`, `+`, `?` and `{n}`, `{n,}`, `{n,m}` (n ≤ m) after something to
     *   repeat, each optionally lazy — not possessive;
     * - no `.` (PCRE's matches `\r`, and a route's a newline too; ECMA's neither), no `^` or `$`
     *   (the caller anchors the whole), and `|` outside a group only where the caller wraps it.
     *
     * And what PCRE's mode changes. Without `u` it matches bytes, ECMA code points: alike only where
     * nothing can match a byte of a multibyte character — no negated class, `\D` or `\W`. With `u`,
     * PHP's PCRE reads `\d \w \b` and their negations as Unicode (UCP), ECMA as ASCII: none of them.
     * Not knowing the mode (`$unicode` null), neither.
     */
    public static function readsTheSame(string $fragment, bool $alternationOutsideGroups, ?bool $unicode): bool
    {
        if ('' === $fragment || 1 === preg_match('/[^\x20-\x7E]/', $fragment)) {
            return false;
        }

        $byteSafe = true !== $unicode;
        $classEscapes = 'dDwW';
        $boundaries = 'bB';
        if ($byteSafe) {
            $classEscapes = str_replace(['D', 'W'], '', $classEscapes);
        }
        if (false !== $unicode) {
            $classEscapes = '';
            $boundaries = '';
        }

        $length = \strlen($fragment);
        $depth = 0;
        $repeatable = false;
        $i = 0;
        while ($i < $length) {
            $char = $fragment[$i];

            if ('\\' === $char) {
                $escaped = $fragment[$i + 1] ?? '';
                if ('' !== $escaped && str_contains($classEscapes . 'nrtf', $escaped)) {
                    $repeatable = true;
                } elseif ('' !== $escaped && str_contains($boundaries, $escaped)) {
                    $repeatable = false;
                } elseif ('' !== $escaped && str_contains('^$\.*+?()[]{}|/', $escaped)) {
                    $repeatable = true;
                } else {
                    return false;
                }
                $i += 2;

                continue;
            }

            if ('[' === $char) {
                $close = self::classEnd($fragment, $i, $classEscapes, negatable: !$byteSafe);
                if (null === $close) {
                    return false;
                }
                $i = $close + 1;
                $repeatable = true;

                continue;
            }

            if ('(' === $char) {
                if ('?' === ($fragment[$i + 1] ?? '')) {
                    if (':' !== ($fragment[$i + 2] ?? '')) {
                        return false;
                    }
                    $i += 2;
                }
                ++$depth;
                ++$i;
                $repeatable = false;

                continue;
            }

            if (')' === $char) {
                if (0 === $depth) {
                    return false;
                }
                --$depth;
                ++$i;
                $repeatable = true;

                continue;
            }

            if ('|' === $char) {
                if (0 === $depth && !$alternationOutsideGroups) {
                    return false;
                }
                ++$i;
                $repeatable = false;

                continue;
            }

            if (str_contains('*+?{', $char)) {
                if (!$repeatable) {
                    return false;
                }
                if ('{' === $char) {
                    if (1 !== preg_match('/\G\{(\d+)(?:(,)(\d*))?\}/', $fragment, $match, 0, $i)
                        || (isset($match[3]) && '' !== $match[3] && (int) $match[1] > (int) $match[3])) {
                        return false;
                    }
                    $i += \strlen($match[0]);
                } else {
                    ++$i;
                }
                if ('+' === ($fragment[$i] ?? '')) {
                    return false;
                }
                if ('?' === ($fragment[$i] ?? '')) {
                    ++$i;
                }
                $repeatable = false;

                continue;
            }

            if (str_contains('}].^$', $char)) {
                return false;
            }

            ++$i;
            $repeatable = true;
        }

        return 0 === $depth;
    }

    /**
     * Whether a fragment {@see self::readsTheSame()} accepts can match a character Symfony's trimming
     * takes off a value: a space, a control character — `\n \r \t \f`, and a class's `\b`, a
     * backspace — or whatever a negated class matches. Read token by token: `\\n` is a backslash and
     * an `n`, and `\b` outside a class a word boundary, which matches no character at all.
     */
    public static function matchesPadding(string $fragment): bool
    {
        $length = \strlen($fragment);
        $inClass = false;
        for ($i = 0; $i < $length; ++$i) {
            $char = $fragment[$i];
            if ('\\' === $char) {
                $escaped = $fragment[++$i] ?? '';
                if ('' !== $escaped && (str_contains('nrtfvsDW', $escaped) || ($inClass && 'b' === $escaped))) {
                    return true;
                }

                continue;
            }
            if (' ' === $char) {
                return true;
            }
            if (!$inClass && '[' === $char) {
                $inClass = true;
                if ('^' === ($fragment[$i + 1] ?? '')) {
                    return true;
                }
            } elseif ($inClass && ']' === $char) {
                $inClass = false;
            }
        }

        return false;
    }

    /**
     * Where the class opening at `$open` closes, when every part of it reads the same in both
     * dialects: literals, escaped syntax characters and `\n \r \t \f \b \-`, the class escapes the
     * mode allows (`$classEscapes`), and ranges between two literals in order — and negated only
     * where the mode matches code points (`$negatable`). Null otherwise: a `[` inside (a POSIX class,
     * or nesting), a range from or to a class escape, an empty class.
     */
    private static function classEnd(string $fragment, int $open, string $classEscapes, bool $negatable): ?int
    {
        $length = \strlen($fragment);
        $i = $open + 1;
        if ('^' === ($fragment[$i] ?? '')) {
            if (!$negatable) {
                return null;
            }
            ++$i;
        }
        if (']' === ($fragment[$i] ?? '')) {
            return null;
        }

        /** @var list<array{kind: 'literal'|'set'|'dash', char: string}> $atoms */
        $atoms = [];
        while ($i < $length && ']' !== $fragment[$i]) {
            $char = $fragment[$i];
            if ('[' === $char) {
                return null;
            }
            if ('\\' === $char) {
                $escaped = $fragment[$i + 1] ?? '';
                if ('' !== $escaped && str_contains($classEscapes, $escaped)) {
                    $atoms[] = ['kind' => 'set', 'char' => $escaped];
                } elseif ('' !== $escaped && str_contains('nrtfb', $escaped)) {
                    $atoms[] = ['kind' => 'literal', 'char' => ['n' => "\n", 'r' => "\r", 't' => "\t", 'f' => "\f", 'b' => "\x08"][$escaped]];
                } elseif ('' !== $escaped && str_contains('^$\.*+?()[]{}|/-', $escaped)) {
                    $atoms[] = ['kind' => 'literal', 'char' => $escaped];
                } else {
                    return null;
                }
                $i += 2;

                continue;
            }
            $atoms[] = ['kind' => '-' === $char ? 'dash' : 'literal', 'char' => $char];
            ++$i;
        }
        if ($i >= $length) {
            return null;
        }

        $count = \count($atoms);
        for ($k = 0; $k < $count; ++$k) {
            // A range may start at a dash (`[--0]`), which is a literal there; one out of order is
            // refused by both dialects.
            if ($k + 2 < $count && 'dash' === $atoms[$k + 1]['kind']) {
                [$from, $to] = [$atoms[$k], $atoms[$k + 2]];
                if ('set' === $from['kind'] || 'set' === $to['kind'] || \ord($from['char']) > \ord($to['char'])) {
                    return null;
                }
                $k += 2;
            }
        }

        return $i;
    }
}
