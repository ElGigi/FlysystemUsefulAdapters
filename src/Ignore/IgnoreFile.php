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
 * A set of {@see IgnoreRule} parsed from one or more ignore files located in
 * the same directory ($baseDir).
 *
 * Rules are kept in declaration order; the last matching rule decides the
 * outcome (gitignore semantics).
 */
final class IgnoreFile
{
    /** @var IgnoreRule[] */
    private array $rules;

    /**
     * @param string $baseDir Directory (relative to the filesystem root) the rules apply to.
     * @param IgnoreRule[] $rules
     */
    public function __construct(
        private readonly string $baseDir,
        array $rules = [],
    ) {
        $this->rules = $rules;
    }

    /**
     * Parse ignore file contents into an IgnoreFile.
     *
     * Multiple contents (several ignore filenames in the same directory) are
     * concatenated in the provided order.
     *
     * @param string $baseDir
     * @param string ...$contents
     *
     * @return self
     */
    public static function parse(string $baseDir, string ...$contents): self
    {
        $rules = [];

        foreach ($contents as $content) {
            $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

            foreach ($lines as $line) {
                $rule = IgnoreRule::fromLine($line);
                if (null !== $rule) {
                    $rules[] = $rule;
                }
            }
        }

        return new self($baseDir, $rules);
    }

    /**
     * The directory (relative to the filesystem root) the rules are anchored to.
     *
     * @return string
     */
    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * Whether this file has no rules.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return [] === $this->rules;
    }

    /**
     * Determine whether a path (relative to $baseDir) is ignored by these rules.
     *
     * Returns null when no rule matches, true when ignored, false when
     * explicitly re-included by a negated rule.
     *
     * @param string $relativePath
     * @param bool $isDir
     *
     * @return bool|null
     */
    public function isIgnored(string $relativePath, bool $isDir): ?bool
    {
        $ignored = null;

        foreach ($this->rules as $rule) {
            if (!$rule->matches($relativePath, $isDir)) {
                continue;
            }

            $ignored = !$rule->isNegated();
        }

        return $ignored;
    }
}
