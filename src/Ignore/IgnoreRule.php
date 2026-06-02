<?php
/*
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace ElGigi\FlysystemUsefulAdapters\Ignore;

/**
 * A single compiled gitignore pattern.
 *
 * Patterns are matched against paths relative to the directory of the ignore
 * file they were declared in (see {@see IgnoreFile}).
 */
final class IgnoreRule
{
    private function __construct(
        private bool $negated,
        private bool $directoryOnly,
        private ?string $regex,
    ) {
    }

    /**
     * Parse a single gitignore line into a rule.
     *
     * Returns null for blank lines, comments and invalid patterns (a pattern
     * ending with an unescaped backslash never matches anything).
     *
     * @param string $line
     *
     * @return self|null
     */
    public static function fromLine(string $line): ?self
    {
        // Strip trailing whitespace unless escaped with a backslash.
        $line = self::trimTrailingWhitespace($line);

        // Blank line matches no files.
        if ('' === $line) {
            return null;
        }

        // A line starting with '#' is a comment ('\#' is a literal hash).
        if (str_starts_with($line, '#')) {
            return null;
        }
        if (str_starts_with($line, '\#')) {
            $line = substr($line, 1);
        }

        // Optional negation prefix ('\!' is a literal exclamation mark).
        $negated = false;
        if (str_starts_with($line, '!')) {
            $negated = true;
            $line = substr($line, 1);
        } elseif (str_starts_with($line, '\!')) {
            $line = substr($line, 1);
        }

        if ('' === $line) {
            return null;
        }

        // Trailing slash means the pattern matches directories only.
        $directoryOnly = false;
        if (str_ends_with($line, '/') && !str_ends_with($line, '\/')) {
            $directoryOnly = true;
            $line = rtrim($line, '/');
        }

        if ('' === $line) {
            return null;
        }

        // A backslash at the end of a pattern is invalid and never matches.
        if (self::endsWithDanglingBackslash($line)) {
            return new self($negated, $directoryOnly, null);
        }

        $regex = self::compile($line);

        return new self($negated, $directoryOnly, $regex);
    }

    /**
     * Does this rule negate a previous match?
     *
     * @return bool
     */
    public function isNegated(): bool
    {
        return $this->negated;
    }

    /**
     * Check whether the rule matches the given relative path.
     *
     * @param string $relativePath Path relative to the ignore file directory, no leading slash.
     * @param bool $isDir Whether the path is a directory.
     *
     * @return bool
     */
    public function matches(string $relativePath, bool $isDir): bool
    {
        if (null === $this->regex) {
            return false;
        }

        if ($this->directoryOnly && !$isDir) {
            return false;
        }

        return 1 === preg_match($this->regex, $relativePath);
    }

    /**
     * Compile a gitignore glob pattern into a PCRE regular expression.
     *
     * @param string $pattern
     *
     * @return string
     */
    private static function compile(string $pattern): string
    {
        // A separator at the beginning or middle anchors the pattern to the
        // ignore file directory. Otherwise it may match at any level below.
        $anchored = str_contains(rtrim($pattern, '/'), '/');
        $pattern = ltrim($pattern, '/');

        $regex = self::globToRegex($pattern);

        if ($anchored) {
            $prefix = '^';
        } else {
            // May match at any depth: either at the root or under any directory.
            $prefix = '^(?:.*/)?';
        }

        // Match the path exactly. Ignoring a directory's contents is handled by
        // the matcher walking parent segments, not by the rule itself.
        return '#' . $prefix . $regex . '$#';
    }

    /**
     * Convert a gitignore glob (without anchoring concerns) to a regex body.
     *
     * @param string $pattern
     *
     * @return string
     */
    private static function globToRegex(string $pattern): string
    {
        $regex = '';
        $length = strlen($pattern);

        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];

            switch ($char) {
                case '\\':
                    // Escape next character literally.
                    $next = $pattern[$i + 1] ?? '';
                    if ('' !== $next) {
                        $regex .= preg_quote($next, '#');
                        $i++;
                    }
                    break;

                case '*':
                    if (($pattern[$i + 1] ?? '') === '*') {
                        // Consume the second asterisk.
                        $i++;
                        $before = $i - 2 >= 0 ? $pattern[$i - 2] : '';
                        $after = $pattern[$i + 1] ?? '';

                        $beforeOk = '' === $before || '/' === $before;
                        $afterOk = '' === $after || '/' === $after;

                        if ($beforeOk && $afterOk) {
                            if ('/' === $after) {
                                // "**/" matches zero or more directories.
                                $regex .= '(?:.*/)?';
                                $i++; // consume the following slash
                            } else {
                                // Trailing "**" matches everything.
                                $regex .= '.*';
                            }
                            break;
                        }

                        // Not a valid "**", treat as two single asterisks.
                        $regex .= '[^/]*[^/]*';
                        break;
                    }

                    // Single asterisk: anything except a slash.
                    $regex .= '[^/]*';
                    break;

                case '?':
                    // Any single character except a slash.
                    $regex .= '[^/]';
                    break;

                case '[':
                    $class = self::consumeCharacterClass($pattern, $i, $length);
                    if (null === $class) {
                        // Unterminated class, treat '[' literally.
                        $regex .= '\[';
                        break;
                    }
                    $regex .= $class;
                    break;

                default:
                    $regex .= preg_quote($char, '#');
                    break;
            }
        }

        return $regex;
    }

    /**
     * Consume a character class starting at $i (the opening bracket) and
     * advance the index past the closing bracket. Returns the regex fragment.
     *
     * @param string $pattern
     * @param int $i
     * @param int $length
     *
     * @return string|null
     */
    private static function consumeCharacterClass(string $pattern, int &$i, int $length): ?string
    {
        $j = $i + 1;
        $class = '[';

        // Leading negation.
        if (($pattern[$j] ?? '') === '!' || ($pattern[$j] ?? '') === '^') {
            $class .= '^';
            $j++;
        }

        // A leading ']' is a literal.
        if (($pattern[$j] ?? '') === ']') {
            $class .= '\]';
            $j++;
        }

        $closed = false;
        for (; $j < $length; $j++) {
            $char = $pattern[$j];

            if (']' === $char) {
                $closed = true;
                break;
            }

            if ('\\' === $char) {
                $next = $pattern[$j + 1] ?? '';
                if ('' !== $next) {
                    $class .= '\\' . $next;
                    $j++;
                    continue;
                }
            }

            // Keep ranges (a-z) as-is, escape regex meta otherwise.
            if (in_array($char, ['^', '\\'], true)) {
                $class .= '\\' . $char;
            } else {
                $class .= $char;
            }
        }

        if (!$closed) {
            return null;
        }

        $i = $j;

        return $class . ']';
    }

    /**
     * Remove trailing whitespace unless escaped with a backslash.
     *
     * @param string $line
     *
     * @return string
     */
    private static function trimTrailingWhitespace(string $line): string
    {
        $length = strlen($line);
        $end = $length;

        while ($end > 0 && in_array($line[$end - 1], [' ', "\t"], true)) {
            // Count preceding backslashes to know if the space is escaped.
            $backslashes = 0;
            $k = $end - 2;
            while ($k >= 0 && '\\' === $line[$k]) {
                $backslashes++;
                $k--;
            }

            if (1 === $backslashes % 2) {
                // Escaped whitespace, keep it (and drop the escaping backslash).
                return substr($line, 0, $end - 2) . $line[$end - 1] . substr($line, $end);
            }

            $end--;
        }

        return substr($line, 0, $end);
    }

    /**
     * Whether the pattern ends with an odd number of backslashes (dangling).
     *
     * @param string $pattern
     *
     * @return bool
     */
    private static function endsWithDanglingBackslash(string $pattern): bool
    {
        $backslashes = 0;
        for ($i = strlen($pattern) - 1; $i >= 0 && '\\' === $pattern[$i]; $i--) {
            $backslashes++;
        }

        return 1 === $backslashes % 2;
    }
}
