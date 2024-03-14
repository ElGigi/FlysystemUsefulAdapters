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
use League\Flysystem\FilesystemAdapter;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Throwable;

class LogAdapter extends CallableAdapter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private FilesystemAdapter $adapter,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    protected function callAdapter(string $method, array $args, ?Closure $callback = null): mixed
    {
        $exception = null;
        try {
            return $this->adapter->{$method}(...$args);
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $this->log($method, $args, $exception);
        }
    }

    /**
     * Log.
     *
     * @param string $method
     * @param array $args
     * @param Throwable|null $exception
     *
     * @return void
     */
    protected function log(string $method, array $args, ?Throwable $exception = null): void
    {
        $level = match (null !== $exception) {
            true => 'error',
            false => match ($method) {
                'createDirectory',
                'write',
                'writeStream',
                'copy',
                'move',
                'delete',
                'deleteDirectory',
                'setVisibility' => 'notice',
                default => 'debug',
            },
        };

        if (null === $level) {
            return;
        }

        $this->logger?->log(
            $level,
            match ($method) {
                'fileExists' => 'Check existent of file "{path}"',
                'directoryExists' => 'Check existent of directory "{path}"',
                'write', 'writeStream' => 'Write file "{path}"',
                'read', 'readStream' => 'Read file "{path}"',
                'delete' => 'Delete file "{path}"',
                'deleteDirectory' => 'Delete directory "{path}"',
                'createDirectory' => 'Create directory "{path}"',
                'setVisibility' => 'Update visibility of "{path}"',
                'visibility' => 'Retrieve visibility of "{path}"',
                'mimeType' => 'Retrieve mime type of "{path}"',
                'lastModified' => 'Retrieve last modified date time of "{path}"',
                'fileSize' => 'Retrieve file size of "{path}"',
                'listContents' => 'List contents of "{path}" (deep: {deep})',
                'move' => 'Move file "{source}" to "{destination}"',
                'copy' => 'Copy file "{source}" to "{destination}"',
                default => null,
            },
            array_intersect_key(
                $args,
                ['path' => null, 'source' => null, 'destination' => null, 'deep' => null],
            ),
        );
    }
}
