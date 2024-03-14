<?php
/*
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace ElGigi\FlysystemUsefulAdapters\Tests;

use Closure;
use ElGigi\FlysystemUsefulAdapters\FallbackAdapter;
use Exception;
use League\Flysystem\FilesystemAdapter;

class FailureAdapter extends FallbackAdapter
{
    private ?string $countMethod = null;
    private int $count = 0;

    public function __construct(FilesystemAdapter $adapter, private ?int $nbFailure = null)
    {
        parent::__construct($adapter);
    }

    /**
     * @inheritDoc
     */
    protected function callAdapter(string $method, array $args, ?Closure $callback = null): mixed
    {
        if ($this->countMethod !== $method) {
            $this->countMethod = $method;
            $this->count = 0;
        }
        $this->count++;

        if (null === $this->nbFailure || $this->count <= $this->nbFailure) {
            throw new Exception('Failure adapter');
        }

        return parent::callAdapter($method, $args, $callback);
    }
}