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

use ElGigi\FlysystemUsefulAdapters\ReadWriteAdapter;
use InvalidArgumentException;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\ReadOnly\ReadOnlyFilesystemAdapter;

class ReadWriteAdapterTest extends FilesystemAdapterTestCase
{
    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new ReadWriteAdapter(
            [new ReadOnlyFilesystemAdapter($adapter = new InMemoryFilesystemAdapter())],
            [$adapter],
        );
    }

    public function testConstructorRejectsEmptyReaders()
    {
        $this->expectException(InvalidArgumentException::class);

        new ReadWriteAdapter([], [new InMemoryFilesystemAdapter()]);
    }

    public function testConstructorRejectsEmptyWriters()
    {
        $this->expectException(InvalidArgumentException::class);

        new ReadWriteAdapter([new InMemoryFilesystemAdapter()], []);
    }

    public function testWritesGoToWriterAndReadsToReader()
    {
        $reader = new InMemoryFilesystemAdapter();
        $writer = new InMemoryFilesystemAdapter();

        $adapter = new ReadWriteAdapter([$reader], [$writer]);

        // Write is routed to the writer only
        $adapter->write('foo.txt', 'content', new Config());
        $this->assertTrue($writer->fileExists('foo.txt'));
        $this->assertFalse($reader->fileExists('foo.txt'));

        // Read is routed to the reader only
        $reader->write('bar.txt', 'from-reader', new Config());
        $this->assertSame('from-reader', $adapter->read('bar.txt'));
    }
}
