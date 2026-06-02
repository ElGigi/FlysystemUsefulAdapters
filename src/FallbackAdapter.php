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

class FallbackAdapter extends CallableAdapter
{
    private array $adapters;

    public function __construct(FilesystemAdapter $adapter, FilesystemAdapter ...$fallback)
    {
        $this->adapters = [
            $adapter,
            ...$fallback
        ];
    }

    /**
     * @inheritDoc
     */
    protected function callAdapter(string $method, array $args, ?Closure $callback = null): mixed
    {
        $adapterException = null;
        $lastResult = false;
        $hasResult = false;

        foreach ($this->adapters as $adapter) {
            try {
                $result = $adapter->{$method}(...$args);
                $lastResult = $result;
                $hasResult = true;

                if (false === $result) {
                    continue;
                }

                return $result;
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

        // At least one adapter answered (even with a FALSE result): stay transparent
        if ($hasResult) {
            return $lastResult;
        }

        throw $adapterException;
    }
}
