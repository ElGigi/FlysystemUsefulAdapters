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

use Closure;
use DateTimeInterface;
use Generator;
use League\Flysystem\CalculateChecksumFromStream;
use League\Flysystem\ChecksumProvider;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;

abstract class CallableAdapter implements FilesystemAdapter
{
    use CalculateChecksumFromStream;

    /**
     * Call adapter.
     *
     * @param string $method
     * @param array $args
     * @param Closure|null $callback
     *
     * @return mixed
     * @throws FilesystemException
     */
    abstract protected function callAdapter(string $method, array $args, ?Closure $callback = null): mixed;

    /**
     * @inheritDoc
     */
    public function read(string $path): string
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path]
        );
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path)
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path]
        );
    }

    /**
     * @inheritDoc
     */
    public function listContents(string $path, bool $deep): Generator
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path, 'deep' => $deep]
        );
    }

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path]
        );
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $path): bool
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path]
        );
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): FileAttributes
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path]
        );
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): FileAttributes
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path]
        );
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): FileAttributes
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path]
        );
    }

    /**
     * @inheritDoc
     */
    public function visibility(string $path): FileAttributes
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path]
        );
    }

    /**
     * @inheritDoc
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $this->callAdapter(
            __FUNCTION__,
            ['path' => $path, 'contents' => $contents, 'config' => $config]
        );
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $pos = ftell($contents);
        $this->callAdapter(
            __FUNCTION__,
            ['path' => $path, 'contents' => $contents, 'config' => $config],
            function (array &$args) use ($pos, $contents) {
                if (false === $pos) {
                    return false;
                }

                return fseek($args['contents'], $pos) !== -1;
            }
        );
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        $this->callAdapter(
            __FUNCTION__,
            ['path' => $path, 'visibility' => $visibility]
        );
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path): void
    {
        $this->callAdapter(
            __FUNCTION__,
            ['path' => $path]
        );
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
        $this->callAdapter(
            __FUNCTION__,
            ['path' => $path]
        );
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, Config $config): void
    {
        $this->callAdapter(
            __FUNCTION__,
            ['path' => $path, 'config' => $config]
        );
    }

    /**
     * @inheritDoc
     */
    public function move(string $source, string $destination, Config $config): void
    {
        $this->callAdapter(
            __FUNCTION__,
            ['source' => $source, 'destination' => $destination, 'config' => $config]
        );
    }

    /**
     * @inheritDoc
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        $this->callAdapter(
            __FUNCTION__,
            ['source' => $source, 'destination' => $destination, 'config' => $config]
        );
    }

    /**
     * @throws FilesystemException
     * @see ChecksumProvider::checksum()
     */
    public function checksum(string $path, Config $config): string
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path, 'config' => $config]
        );
    }

    /**
     * @throws FilesystemException
     * @see PublicUrlGenerator::publicUrl()
     */
    public function publicUrl(string $path, Config $config): string
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path, 'config' => $config]
        );
    }

    /**
     * @throws FilesystemException
     * @see TemporaryUrlGenerator::temporaryUrl()
     */
    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, Config $config): string
    {
        return $this->callAdapter(
            __FUNCTION__,
            ['path' => $path, 'expiresAt' => $expiresAt, 'config' => $config]
        );
    }
}
