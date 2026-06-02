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

namespace ElGigi\FlysystemUsefulAdapters;

use ElGigi\FlysystemUsefulAdapters\Ignore\IgnoreMatcher;
use Generator;
use League\Flysystem\Config;
use League\Flysystem\DecoratedAdapter;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

/**
 * Decorates a filesystem adapter and filters entries based on gitignore-style
 * ignore files (e.g. `.docignore`).
 *
 * Ignore files are resolved as a cascade (root down to the entry directory),
 * following gitignore semantics (negation, anchoring, `**`, directory-only).
 *
 * When `$strict` is true (default), an ignored path is treated as if it does
 * not exist for every operation. When false, only `listContents()` is filtered
 * and direct accesses are passed through to the inner adapter.
 *
 * The ignore files themselves are always hidden from `listContents()` but
 * remain readable.
 */
class IgnoreFilesystemAdapter extends DecoratedAdapter
{
    private IgnoreMatcher $matcher;

    /**
     * @param FilesystemAdapter $adapter
     * @param string|string[] $ignoreFilenames One or several ignore filenames.
     * @param bool $strict Hide ignored paths from every operation (true) or only from listings (false).
     */
    public function __construct(
        FilesystemAdapter $adapter,
        string|array $ignoreFilenames = '.gitignore',
        private bool $strict = true,
    ) {
        parent::__construct($adapter);

        $this->matcher = new IgnoreMatcher($adapter, $ignoreFilenames);
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        if ($this->isHidden($path, false)) {
            return false;
        }

        return parent::fileExists($path);
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $path): bool
    {
        if ($this->strict && $this->matcher->isIgnored($path, true)) {
            return false;
        }

        return parent::directoryExists($path);
    }

    /**
     * @inheritDoc
     */
    public function read(string $path): string
    {
        if ($this->isHidden($path, false)) {
            throw UnableToReadFile::fromLocation($path, 'Path is ignored.');
        }

        return parent::read($path);
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path)
    {
        if ($this->isHidden($path, false)) {
            throw UnableToReadFile::fromLocation($path, 'Path is ignored.');
        }

        return parent::readStream($path);
    }

    /**
     * @inheritDoc
     */
    public function write(string $path, string $contents, Config $config): void
    {
        if ($this->isHidden($path, false)) {
            throw UnableToWriteFile::atLocation($path, 'Path is ignored.');
        }

        parent::write($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        if ($this->isHidden($path, false)) {
            throw UnableToWriteFile::atLocation($path, 'Path is ignored.');
        }

        parent::writeStream($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path): void
    {
        if ($this->isHidden($path, false)) {
            // Already treated as non-existent: nothing to delete.
            return;
        }

        parent::delete($path);
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
        if ($this->strict && $this->matcher->isIgnored($path, true)) {
            return;
        }

        parent::deleteDirectory($path);
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, Config $config): void
    {
        if ($this->strict && $this->matcher->isIgnored($path, true)) {
            throw UnableToCreateDirectory::atLocation($path, 'Path is ignored.');
        }

        parent::createDirectory($path, $config);
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        if ($this->isHidden($path, false)) {
            throw UnableToSetVisibility::atLocation($path, 'Path is ignored.');
        }

        parent::setVisibility($path, $visibility);
    }

    /**
     * @inheritDoc
     */
    public function visibility(string $path): FileAttributes
    {
        if ($this->isHidden($path, false)) {
            throw UnableToRetrieveMetadata::visibility($path, 'Path is ignored.');
        }

        return parent::visibility($path);
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): FileAttributes
    {
        if ($this->isHidden($path, false)) {
            throw UnableToRetrieveMetadata::mimeType($path, 'Path is ignored.');
        }

        return parent::mimeType($path);
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): FileAttributes
    {
        if ($this->isHidden($path, false)) {
            throw UnableToRetrieveMetadata::lastModified($path, 'Path is ignored.');
        }

        return parent::lastModified($path);
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): FileAttributes
    {
        if ($this->isHidden($path, false)) {
            throw UnableToRetrieveMetadata::fileSize($path, 'Path is ignored.');
        }

        return parent::fileSize($path);
    }

    /**
     * @inheritDoc
     */
    public function listContents(string $path, bool $deep): iterable
    {
        return $this->filterListing(parent::listContents($path, $deep));
    }

    /**
     * @inheritDoc
     */
    public function move(string $source, string $destination, Config $config): void
    {
        if ($this->strict
            && ($this->matcher->isIgnored($source, false) || $this->matcher->isIgnored($destination, false))
        ) {
            throw UnableToMoveFile::because('Path is ignored.', $source, $destination);
        }

        parent::move($source, $destination, $config);
    }

    /**
     * @inheritDoc
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        if ($this->strict
            && ($this->matcher->isIgnored($source, false) || $this->matcher->isIgnored($destination, false))
        ) {
            throw UnableToCopyFile::because('Path is ignored.', $source, $destination);
        }

        parent::copy($source, $destination, $config);
    }

    /**
     * Filter a listing: drop ignored entries and the ignore files themselves.
     *
     * @param iterable<StorageAttributes> $listing
     *
     * @return Generator<StorageAttributes>
     */
    private function filterListing(iterable $listing): Generator
    {
        foreach ($listing as $item) {
            if ($this->matcher->isIgnoreFile($item->path())) {
                continue;
            }

            if ($this->matcher->isIgnored($item->path(), $item->isDir())) {
                continue;
            }

            yield $item;
        }
    }

    /**
     * Whether a path must be hidden for direct operations (strict mode only),
     * the ignore files themselves remaining accessible.
     *
     * @param string $path
     * @param bool $isDir
     *
     * @return bool
     */
    private function isHidden(string $path, bool $isDir): bool
    {
        if (!$this->strict) {
            return false;
        }

        if ($this->matcher->isIgnoreFile($path)) {
            return false;
        }

        return $this->matcher->isIgnored($path, $isDir);
    }
}
