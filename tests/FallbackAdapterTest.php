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
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;

class FallbackAdapterTest extends FilesystemAdapterTestCase
{
    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        return new FallbackAdapter(
            new FailureAdapter(new InMemoryFilesystemAdapter()),
            new FailureAdapter(new InMemoryFilesystemAdapter()),
            new InMemoryFilesystemAdapter(),
        );
    }

    public function testFileExists()
    {
        $fallback = new FallbackAdapter(
            $adapter1 = new InMemoryFilesystemAdapter(),
            $adapter2 = new InMemoryFilesystemAdapter(),
        );

        $adapter1->write('foo/baz', 'test', new Config());
        $adapter2->write('foo/bar', 'test', new Config());

        $this->assertTrue($fallback->fileExists('foo/baz'));
        $this->assertTrue($fallback->fileExists('foo/bar'));
    }
}
