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

use ElGigi\FlysystemUsefulAdapters\LogAdapter;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Throwable;

class LogAdapterTest extends FilesystemAdapterTestCase
{
    public static FakeLogger $logger;

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new LogAdapter(
            adapter: new InMemoryFilesystemAdapter(),
            logger: new FakeLogger(),
        );
    }

    public function testLogs()
    {
        $adapter = new LogAdapter(
            adapter: new InMemoryFilesystemAdapter(),
            logger: $logger = new FakeLogger(),
        );

        $adapter->listContents('list', true);
        $adapter->writeStream('list/test.txt', fopen('php://memory', 'r+'), new Config());
        $adapter->readStream('list/test.txt');
        try {
            $adapter->readStream('list/not-found.txt');
        } catch (Throwable) {
        }

        $this->assertEquals(
            [
                'notice' => [
                    'Write file "list/test.txt"',
                ],
                'debug' => [
                    'List contents of "list" (deep: 1)',
                    'Read file "list/test.txt"',
                ],
                'error' => [
                    'Read file "list/not-found.txt"',
                ],
            ],
            $logger->getLogs(),
        );
    }
}
