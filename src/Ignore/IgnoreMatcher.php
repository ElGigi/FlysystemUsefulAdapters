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

use League\Flysystem\FilesystemAdapter;
use Throwable;

/**
 * Resolves gitignore-style ignore rules across a directory hierarchy
 * (cascade), reading the ignore files through a Flysystem adapter.
 */
final class IgnoreMatcher
{
    /** @var string[] */
    private array $ignoreFilenames;

    /**
     * Lazily loaded ignore files, keyed by directory (relative to root).
     *
     * @var array<string, IgnoreFile>
     */
    private array $files = [];

    /**
     * @param FilesystemAdapter $adapter
     * @param string|string[] $ignoreFilenames
     */
    public function __construct(
        private readonly FilesystemAdapter $adapter,
        string|array $ignoreFilenames = '.gitignore',
    ) {
        $names = is_array($ignoreFilenames) ? array_values($ignoreFilenames) : [$ignoreFilenames];
        $names = array_filter($names, fn($name) => is_string($name) && '' !== $name);

        if ([] === $names) {
            $names = ['.gitignore'];
        }

        $this->ignoreFilenames = $names;
    }

    /**
     * The configured ignore filenames.
     *
     * @return string[]
     */
    public function getIgnoreFilenames(): array
    {
        return $this->ignoreFilenames;
    }

    /**
     * Whether the given path is itself one of the ignore files.
     *
     * @param string $path
     *
     * @return bool
     */
    public function isIgnoreFile(string $path): bool
    {
        $basename = basename($this->normalize($path));

        return in_array($basename, $this->ignoreFilenames, true);
    }

    /**
     * Whether a path is ignored, taking the full directory cascade and parent
     * directories into account.
     *
     * @param string $path
     * @param bool $isDir
     *
     * @return bool
     */
    public function isIgnored(string $path, bool $isDir): bool
    {
        $path = $this->normalize($path);

        if ('' === $path) {
            return false;
        }

        // A path is ignored if any of its parent directories is ignored
        // (gitignore does not allow re-including under an ignored directory).
        $segments = explode('/', $path);
        $current = '';

        foreach ($segments as $index => $segment) {
            $current = '' === $current ? $segment : $current . '/' . $segment;
            $isLast = $index === count($segments) - 1;
            $segmentIsDir = $isLast ? $isDir : true;

            if ($this->isPathIgnored($current, $segmentIsDir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the ignore status of a single path (without parent cascade),
     * applying the precedence of ignore files from root down to its directory.
     *
     * @param string $path
     * @param bool $isDir
     *
     * @return bool
     */
    private function isPathIgnored(string $path, bool $isDir): bool
    {
        $directory = $this->dirname($path);
        $ignored = false;

        // From the root ('') down to the path directory, deeper files override.
        foreach ($this->directoryChain($directory) as $dir) {
            $file = $this->loadDirectory($dir);
            if ($file->isEmpty()) {
                continue;
            }

            $relative = '' === $dir ? $path : substr($path, strlen($dir) + 1);
            $result = $file->isIgnored($relative, $isDir);

            if (null !== $result) {
                $ignored = $result;
            }
        }

        return $ignored;
    }

    /**
     * Yield directories from the root ('') down to (and including) $directory.
     *
     * @param string $directory
     *
     * @return string[]
     */
    private function directoryChain(string $directory): array
    {
        $chain = [''];

        if ('' === $directory) {
            return $chain;
        }

        $segments = explode('/', $directory);
        $current = '';
        foreach ($segments as $segment) {
            $current = '' === $current ? $segment : $current . '/' . $segment;
            $chain[] = $current;
        }

        return $chain;
    }

    /**
     * Load (and cache) the ignore rules declared in a directory.
     *
     * @param string $directory
     *
     * @return IgnoreFile
     */
    private function loadDirectory(string $directory): IgnoreFile
    {
        if (isset($this->files[$directory])) {
            return $this->files[$directory];
        }

        $contents = [];
        foreach ($this->ignoreFilenames as $filename) {
            $path = '' === $directory ? $filename : $directory . '/' . $filename;
            $content = $this->readIgnoreFile($path);
            if (null !== $content) {
                $contents[] = $content;
            }
        }

        return $this->files[$directory] = IgnoreFile::parse($directory, ...$contents);
    }

    /**
     * Read an ignore file through the adapter, returning null when absent or
     * unreadable.
     *
     * @param string $path
     *
     * @return string|null
     */
    private function readIgnoreFile(string $path): ?string
    {
        try {
            if (!$this->adapter->fileExists($path)) {
                return null;
            }

            return $this->adapter->read($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Directory part of a path, '' for the root.
     *
     * @param string $path
     *
     * @return string
     */
    private function dirname(string $path): string
    {
        $pos = strrpos($path, '/');

        return false === $pos ? '' : substr($path, 0, $pos);
    }

    /**
     * Normalize a path: strip leading/trailing slashes.
     *
     * @param string $path
     *
     * @return string
     */
    private function normalize(string $path): string
    {
        return trim($path, '/');
    }
}
