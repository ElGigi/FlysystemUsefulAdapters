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
use Throwable;

class RetryAdapter extends CallableAdapter
{
    public function __construct(
        private FilesystemAdapter $adapter,
        private int $time = 5000,
        private int $retry = 2,
    ) {
    }

    /**
     * @inheritDoc
     */
    protected function callAdapter(string $method, array $args, ?Closure $callback = null): mixed
    {
        $adapterException = null;

        for ($i = 0; $i < $this->retry; $i++) {
            try {
                return $this->adapter->{$method}(...$args);
            } catch (Throwable $exception) {
                $adapterException = $exception;
            }

            if (null !== $callback) {
                $result = $callback($args);
                if (false === $result) {
                    throw $adapterException;
                }
            }
            usleep($this->time * 1000);
        }

        throw $adapterException;
    }
}
