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

use ElGigi\FlysystemUsefulAdapters\IgnoreFilesystemAdapter;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;

class IgnoreFilesystemAdapterTest extends FilesystemAdapterTestCase
{
    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        // Use an ignore filename that never appears so the standard suite is unaffected.
        return new IgnoreFilesystemAdapter(
            adapter: new InMemoryFilesystemAdapter(),
            ignoreFilenames: '.never-match-ignore',
        );
    }

    private function buildAdapter(array $files, string|array $ignoreFilenames = '.docignore', bool $strict = true): array
    {
        $inner = new InMemoryFilesystemAdapter();
        foreach ($files as $path => $contents) {
            $inner->write($path, $contents, new Config());
        }

        return [new IgnoreFilesystemAdapter($inner, $ignoreFilenames, $strict), $inner];
    }

    /**
     * @param iterable<StorageAttributes> $listing
     *
     * @return string[]
     */
    private function paths(iterable $listing): array
    {
        $paths = [];
        foreach ($listing as $item) {
            $paths[] = $item->path();
        }
        sort($paths);

        return $paths;
    }

    public function testListContentsFiltersIgnored(): void
    {
        [$adapter] = $this->buildAdapter([
            '.docignore' => "*.log\n",
            'app.log' => 'x',
            'keep.txt' => 'x',
        ]);

        $this->assertSame(['keep.txt'], $this->paths($adapter->listContents('', false)));
    }

    public function testIgnoreFileItselfIsHiddenFromListing(): void
    {
        [$adapter] = $this->buildAdapter([
            '.docignore' => "*.log\n",
            'keep.txt' => 'x',
        ]);

        $this->assertSame(['keep.txt'], $this->paths($adapter->listContents('', false)));
    }

    public function testIgnoreFileRemainsReadable(): void
    {
        [$adapter] = $this->buildAdapter([
            '.docignore' => "*.log\n",
        ]);

        $this->assertTrue($adapter->fileExists('.docignore'));
        $this->assertSame("*.log\n", $adapter->read('.docignore'));
    }

    public function testStrictReadThrows(): void
    {
        [$adapter] = $this->buildAdapter([
            '.docignore' => "*.log\n",
            'app.log' => 'secret',
        ]);

        $this->expectException(UnableToReadFile::class);
        $adapter->read('app.log');
    }

    public function testStrictFileExistsFalse(): void
    {
        [$adapter] = $this->buildAdapter([
            '.docignore' => "*.log\n",
            'app.log' => 'secret',
        ]);

        $this->assertFalse($adapter->fileExists('app.log'));
    }

    public function testStrictFileSizeThrows(): void
    {
        [$adapter] = $this->buildAdapter([
            '.docignore' => "*.log\n",
            'app.log' => 'secret',
        ]);

        $this->expectException(UnableToRetrieveMetadata::class);
        $adapter->fileSize('app.log');
    }

    public function testStrictWriteThrows(): void
    {
        [$adapter] = $this->buildAdapter([
            '.docignore' => "*.log\n",
        ]);

        $this->expectException(UnableToWriteFile::class);
        $adapter->write('new.log', 'data', new Config());
    }

    public function testNonStrictAllowsDirectAccessButFiltersListing(): void
    {
        [$adapter] = $this->buildAdapter(
            [
                '.docignore' => "*.log\n",
                'app.log' => 'secret',
                'keep.txt' => 'x',
            ],
            '.docignore',
            false,
        );

        // Direct access passes through.
        $this->assertTrue($adapter->fileExists('app.log'));
        $this->assertSame('secret', $adapter->read('app.log'));

        // listContents is still filtered.
        $this->assertSame(['keep.txt'], $this->paths($adapter->listContents('', false)));
    }

    public function testMultipleIgnoreFilenames(): void
    {
        [$adapter] = $this->buildAdapter(
            [
                '.docignore' => "*.log\n",
                '.exportignore' => "*.tmp\n",
                'a.log' => 'x',
                'b.tmp' => 'x',
                'c.txt' => 'x',
            ],
            ['.docignore', '.exportignore'],
        );

        $this->assertSame(['c.txt'], $this->paths($adapter->listContents('', false)));
    }

    public function testDeepListingFiltersNestedCascade(): void
    {
        [$adapter] = $this->buildAdapter([
            '.docignore' => "*.log\n",
            'sub/.docignore' => "!keep.log\n",
            'sub/keep.log' => 'x',
            'sub/drop.log' => 'x',
            'root.log' => 'x',
            'root.txt' => 'x',
        ]);

        $paths = $this->paths($adapter->listContents('', true));

        $this->assertContains('root.txt', $paths);
        $this->assertContains('sub/keep.log', $paths);
        $this->assertContains('sub', $paths);
        $this->assertNotContains('root.log', $paths);
        $this->assertNotContains('sub/drop.log', $paths);
    }
}
