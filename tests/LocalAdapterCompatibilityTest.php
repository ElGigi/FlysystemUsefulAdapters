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

use ElGigi\FlysystemUsefulAdapters\FallbackAdapter;
use ElGigi\FlysystemUsefulAdapters\LogAdapter;
use ElGigi\FlysystemUsefulAdapters\ReadWriteAdapter;
use ElGigi\FlysystemUsefulAdapters\RetryAdapter;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\TestCase;

class LocalAdapterCompatibilityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/flysystem-useful-adapters-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        (new LocalFilesystemAdapter($this->root))->deleteDirectory('');
    }

    public static function decorators(): array
    {
        return [
            'fallback' => ['fallback'],
            'retry' => ['retry'],
            'log' => ['log'],
            'read-write' => ['read-write'],
        ];
    }

    private function decorate(string $decorator, FilesystemAdapter $adapter): FilesystemAdapter
    {
        return match ($decorator) {
            'fallback' => new FallbackAdapter($adapter),
            'retry' => new RetryAdapter($adapter, time: 0, retry: 1),
            'log' => new LogAdapter($adapter, new FakeLogger()),
            'read-write' => new ReadWriteAdapter([$adapter], [$adapter]),
        };
    }

    /**
     * @dataProvider decorators
     */
    public function testFileExists(string $decorator): void
    {
        $local = new LocalFilesystemAdapter($this->root);
        $adapter = $this->decorate($decorator, $local);

        $this->assertFalse($adapter->fileExists('missing.txt'));
        $local->write('present.txt', 'content', new Config());
        $this->assertTrue($adapter->fileExists('present.txt'));
    }

    /**
     * @dataProvider decorators
     */
    public function testDirectoryExists(string $decorator): void
    {
        $local = new LocalFilesystemAdapter($this->root);
        $adapter = $this->decorate($decorator, $local);

        $this->assertFalse($adapter->directoryExists('missing'));
        $local->createDirectory('present', new Config());
        $this->assertTrue($adapter->directoryExists('present'));
    }

    /**
     * @dataProvider decorators
     */
    public function testDeleteDirectory(string $decorator): void
    {
        $local = new LocalFilesystemAdapter($this->root);
        $adapter = $this->decorate($decorator, $local);

        $adapter->deleteDirectory('missing');
        $local->write('directory/nested/file.txt', 'content', new Config());
        $adapter->deleteDirectory('directory');

        $this->assertFalse($local->directoryExists('directory'));
        $this->assertFalse($local->fileExists('directory/nested/file.txt'));
    }

    public function testFallbackFindsEntriesInSecondaryAdapter(): void
    {
        $primary = new LocalFilesystemAdapter($this->root . '/primary');
        $secondary = new LocalFilesystemAdapter($this->root . '/secondary');
        $adapter = new FallbackAdapter($primary, $secondary);
        $secondary->write('directory/file.txt', 'content', new Config());

        $this->assertTrue($adapter->fileExists('directory/file.txt'));
        $this->assertTrue($adapter->directoryExists('directory'));
        $this->assertFalse($adapter->fileExists('missing'));
        $this->assertFalse($adapter->directoryExists('missing'));
    }

    public function testReadWriteDeletesOnlyFromWriter(): void
    {
        $reader = new LocalFilesystemAdapter($this->root . '/reader');
        $writer = new LocalFilesystemAdapter($this->root . '/writer');
        $reader->write('directory/file.txt', 'reader', new Config());
        $writer->write('directory/file.txt', 'writer', new Config());
        $adapter = new ReadWriteAdapter([$reader], [$writer]);

        $adapter->deleteDirectory('directory');

        $this->assertTrue($reader->fileExists('directory/file.txt'));
        $this->assertFalse($writer->directoryExists('directory'));
    }

    public function testLogContextKeepsNamedKeys(): void
    {
        $logger = new class extends FakeLogger {
            public array $contexts = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->contexts[] = $context;
                parent::log($level, $message, $context);
            }
        };
        $adapter = new LogAdapter(new LocalFilesystemAdapter($this->root), $logger);

        $this->assertFalse($adapter->fileExists('missing.txt'));
        $adapter->write('source.txt', 'content', new Config());
        $adapter->copy('source.txt', 'destination.txt', new Config());
        iterator_to_array($adapter->listContents('', true));

        $this->assertSame([
            ['path' => 'missing.txt'],
            ['path' => 'source.txt'],
            ['source' => 'source.txt', 'destination' => 'destination.txt'],
            ['path' => '', 'deep' => true],
        ], $logger->contexts);
        $this->assertSame('Check existent of file "missing.txt"', $logger->getLogs()['debug'][0]);
    }

    public static function retryingDecorators(): array
    {
        return [
            'fallback' => ['fallback'],
            'retry' => ['retry'],
            'read-write' => ['read-write'],
        ];
    }

    /**
     * @dataProvider retryingDecorators
     */
    public function testStreamIsRepositionedBeforeSecondAttempt(string $decorator): void
    {
        $local = new class($this->root) extends LocalFilesystemAdapter {
            public int $attempts = 0;

            public function writeStream(string $path, $contents, Config $config): void
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    stream_get_contents($contents);
                    throw UnableToWriteFile::atLocation($path, 'Simulated failure after consuming the stream');
                }

                parent::writeStream($path, $contents, $config);
            }
        };
        $adapter = match ($decorator) {
            'fallback' => new FallbackAdapter($local, $local),
            'retry' => new RetryAdapter($local, time: 0, retry: 2),
            'read-write' => new ReadWriteAdapter([$local], [$local, $local]),
        };
        $stream = fopen('php://temp', 'w+');

        try {
            fwrite($stream, 'prefix-content');
            fseek($stream, 7);
            $adapter->writeStream('file.txt', $stream, new Config());

            $this->assertSame(2, $local->attempts);
            $this->assertSame('content', $local->read('file.txt'));
        } finally {
            fclose($stream);
        }
    }
}
