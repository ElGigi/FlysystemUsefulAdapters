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
use League\Flysystem\ReadOnly\ReadOnlyFilesystemAdapter;
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

    public function testLogMessagesAndLevels()
    {
        $adapter = new LogAdapter(
            adapter: new InMemoryFilesystemAdapter(),
            logger: $logger = new FakeLogger(),
        );

        $adapter->createDirectory('dir', new Config());
        $adapter->write('dir/a.txt', 'content', new Config());
        $adapter->fileExists('dir/a.txt');
        $adapter->directoryExists('dir');
        $adapter->mimeType('dir/a.txt');
        $adapter->fileSize('dir/a.txt');
        $adapter->lastModified('dir/a.txt');
        $adapter->setVisibility('dir/a.txt', 'public');
        $adapter->visibility('dir/a.txt');
        $adapter->copy('dir/a.txt', 'dir/b.txt', new Config());
        $adapter->move('dir/b.txt', 'dir/c.txt', new Config());
        $adapter->delete('dir/a.txt');
        $adapter->deleteDirectory('dir');

        $this->assertEquals(
            [
                'Create directory "dir"',
                'Write file "dir/a.txt"',
                'Update visibility of "dir/a.txt"',
                'Copy file "dir/a.txt" to "dir/b.txt"',
                'Move file "dir/b.txt" to "dir/c.txt"',
                'Delete file "dir/a.txt"',
                'Delete directory "dir"',
            ],
            $logger->getLogs()['notice'],
        );

        $this->assertEquals(
            [
                'Check existent of file "dir/a.txt"',
                'Check existent of directory "dir"',
                'Retrieve mime type of "dir/a.txt"',
                'Retrieve file size of "dir/a.txt"',
                'Retrieve last modified date time of "dir/a.txt"',
                'Retrieve visibility of "dir/a.txt"',
            ],
            $logger->getLogs()['debug'],
        );
    }

    public function testWriteFailureIsLoggedAsError()
    {
        $adapter = new LogAdapter(
            adapter: new ReadOnlyFilesystemAdapter(new InMemoryFilesystemAdapter()),
            logger: $logger = new FakeLogger(),
        );

        try {
            $adapter->write('foo.txt', 'content', new Config());
        } catch (Throwable) {
        }

        $this->assertEquals(
            ['Write file "foo.txt"'],
            $logger->getLogs()['error'],
        );
    }
}
