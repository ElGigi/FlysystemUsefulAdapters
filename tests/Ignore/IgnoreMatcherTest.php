<?php
/*
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2024 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace ElGigi\FlysystemUsefulAdapters\Tests\Ignore;

use ElGigi\FlysystemUsefulAdapters\Ignore\IgnoreMatcher;
use League\Flysystem\Config;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;

class IgnoreMatcherTest extends TestCase
{
    private function adapterWith(array $files): InMemoryFilesystemAdapter
    {
        $adapter = new InMemoryFilesystemAdapter();
        foreach ($files as $path => $contents) {
            $adapter->write($path, $contents, new Config());
        }

        return $adapter;
    }

    public function testSimpleRootIgnore(): void
    {
        $adapter = $this->adapterWith([
            '.docignore' => "*.log\ncache/\n",
            'app.log' => 'x',
            'keep.txt' => 'x',
        ]);
        $matcher = new IgnoreMatcher($adapter, '.docignore');

        $this->assertTrue($matcher->isIgnored('app.log', false));
        $this->assertFalse($matcher->isIgnored('keep.txt', false));
        $this->assertTrue($matcher->isIgnored('cache', true));
    }

    public function testNestedCascade(): void
    {
        // Root ignores *.log; subdir re-includes keep.log and ignores tmp.
        $adapter = $this->adapterWith([
            '.docignore' => "*.log\n",
            'sub/.docignore' => "!keep.log\ntmp\n",
        ]);
        $matcher = new IgnoreMatcher($adapter, '.docignore');

        $this->assertTrue($matcher->isIgnored('sub/other.log', false));
        $this->assertFalse($matcher->isIgnored('sub/keep.log', false));
        $this->assertTrue($matcher->isIgnored('sub/tmp', false));
        $this->assertTrue($matcher->isIgnored('root.log', false));
    }

    public function testParentDirectoryIgnoredHidesChildren(): void
    {
        $adapter = $this->adapterWith([
            '.docignore' => "build/\n",
        ]);
        $matcher = new IgnoreMatcher($adapter, '.docignore');

        $this->assertTrue($matcher->isIgnored('build', true));
        $this->assertTrue($matcher->isIgnored('build/output/file.txt', false));
    }

    public function testCannotReincludeUnderIgnoredParent(): void
    {
        // gitignore: a file cannot be re-included if its parent dir is excluded.
        $adapter = $this->adapterWith([
            '.docignore' => "build/\n!build/keep.txt\n",
        ]);
        $matcher = new IgnoreMatcher($adapter, '.docignore');

        $this->assertTrue($matcher->isIgnored('build/keep.txt', false));
    }

    public function testExcludeEverythingExceptFooBar(): void
    {
        // The doc example: exclude everything except directory foo/bar.
        $adapter = $this->adapterWith([
            '.docignore' => "/*\n!/foo\n/foo/*\n!/foo/bar\n",
        ]);
        $matcher = new IgnoreMatcher($adapter, '.docignore');

        $this->assertTrue($matcher->isIgnored('other', true));
        $this->assertFalse($matcher->isIgnored('foo', true));
        $this->assertTrue($matcher->isIgnored('foo/baz', true));
        $this->assertFalse($matcher->isIgnored('foo/bar', true));
    }

    public function testMultipleIgnoreFilesConcatenated(): void
    {
        $adapter = $this->adapterWith([
            '.docignore' => "*.log\n",
            '.exportignore' => "*.tmp\n",
        ]);
        $matcher = new IgnoreMatcher($adapter, ['.docignore', '.exportignore']);

        $this->assertTrue($matcher->isIgnored('a.log', false));
        $this->assertTrue($matcher->isIgnored('a.tmp', false));
        $this->assertFalse($matcher->isIgnored('a.txt', false));
    }

    public function testIsIgnoreFile(): void
    {
        $adapter = $this->adapterWith([]);
        $matcher = new IgnoreMatcher($adapter, ['.docignore', '.exportignore']);

        $this->assertTrue($matcher->isIgnoreFile('.docignore'));
        $this->assertTrue($matcher->isIgnoreFile('sub/.exportignore'));
        $this->assertFalse($matcher->isIgnoreFile('sub/file.txt'));
    }
}
