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

use ElGigi\FlysystemUsefulAdapters\RetryAdapter;
use InvalidArgumentException;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Exception;

class RetryAdapterTest extends FilesystemAdapterTestCase
{
    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new RetryAdapter(
            adapter: new FailureAdapter(new InMemoryFilesystemAdapter(), 2),
            time: 10,
            retry: 3,
        );
    }

    public function testFailureExceedRetries()
    {
        $adapter = new RetryAdapter(
            adapter: new FailureAdapter(new InMemoryFilesystemAdapter(), 2),
            time: 10,
            retry: 2,
        );

        $this->expectException(Exception::class);
        $adapter->read('fake');
    }

    public function testSucceedAfterFailures()
    {
        $adapter = new RetryAdapter(
            adapter: new FailureAdapter(new InMemoryFilesystemAdapter(), 2),
            time: 1,
            retry: 3,
        );

        $this->assertFalse($adapter->fileExists('fake'));
    }

    public function testConstructorRejectsRetryBelowOne()
    {
        $this->expectException(InvalidArgumentException::class);

        new RetryAdapter(new InMemoryFilesystemAdapter(), retry: 0);
    }

    public function testConstructorRejectsNegativeTime()
    {
        $this->expectException(InvalidArgumentException::class);

        new RetryAdapter(new InMemoryFilesystemAdapter(), time: -1);
    }

    public function testConstructorRejectsMultiplierBelowOne()
    {
        $this->expectException(InvalidArgumentException::class);

        new RetryAdapter(new InMemoryFilesystemAdapter(), multiplier: 0.5);
    }

    public function testSucceedWithMultiplier()
    {
        $adapter = new RetryAdapter(
            adapter: new FailureAdapter(new InMemoryFilesystemAdapter(), 2),
            time: 1,
            retry: 3,
            multiplier: 2.0,
        );

        $this->assertFalse($adapter->fileExists('fake'));
    }
}
