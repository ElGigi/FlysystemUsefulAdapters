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
use League\Flysystem\FilesystemException;
use League\Flysystem\ReadOnly\ReadOnlyFilesystemAdapter;
use Throwable;

class ReadWriteAdapter extends CallableAdapter
{
    private array $readers;
    private array $writers;

    public function __construct(array $readers, array $writers)
    {
        // Make readers as readonly to ensure no write on them
        $this->readers = array_map(
            fn(FilesystemAdapter $adapter) => new ReadOnlyFilesystemAdapter($adapter),
            array_filter($readers, fn($adapter) => $adapter instanceof FilesystemAdapter),
        );
        $this->writers = array_filter($writers, fn($adapter) => $adapter instanceof FilesystemAdapter);
    }

    /**
     * Get adapters for method.
     *
     * @param string $method
     *
     * @return array
     */
    protected function getAdapters(string $method): array
    {
        return $this->{match ($method) {
            'write',
            'writeStream',
            'delete',
            'copy',
            'move',
            'createDirectory',
            'deleteDirectory',
            'setVisibility' => 'writers',
            default => 'readers'
        }};
    }

    /**
     * @inheritDoc
     */
    protected function callAdapter(string $method, array $args, ?Closure $callback = null): mixed
    {
        $adapterException = null;

        foreach ($this->getAdapters($method) as $adapter) {
            try {
                return $adapter->{$method}(...$args);
            } catch (Throwable $exception) {
                $adapterException = $exception;
            }

            if (null !== $callback) {
                $result = $callback($args);
                if (false === $result) {
                    throw $adapterException;
                }
            }
        }

        throw $adapterException;
    }
}
