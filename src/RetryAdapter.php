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
use InvalidArgumentException;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use Throwable;

class RetryAdapter extends CallableAdapter
{
    public function __construct(
        private FilesystemAdapter $adapter,
        private int $time = 5000,
        private int $retry = 2,
        private float $multiplier = 1.0,
    ) {
        if ($this->retry < 1) {
            throw new InvalidArgumentException('Retry count must be at least 1');
        }

        if ($this->time < 0) {
            throw new InvalidArgumentException('Time must be a positive integer');
        }

        if ($this->multiplier < 1) {
            throw new InvalidArgumentException('Multiplier must be greater than or equal to 1');
        }
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

            // Do not sleep after the last attempt
            if ($i < $this->retry - 1) {
                usleep((int)($this->time * ($this->multiplier ** $i) * 1000));
            }
        }

        throw $adapterException;
    }
}
