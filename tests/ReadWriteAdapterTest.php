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
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
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
}
