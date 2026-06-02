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

use ElGigi\FlysystemUsefulAdapters\Ignore\IgnoreRule;
use PHPUnit\Framework\TestCase;

class IgnoreRuleTest extends TestCase
{
    public static function provideBlankAndComments(): array
    {
        return [
            'blank' => [''],
            'spaces' => ['   '],
            'comment' => ['# a comment'],
        ];
    }

    /**
     * @dataProvider provideBlankAndComments
     */
    public function testBlankAndCommentsReturnNull(string $line): void
    {
        $this->assertNull(IgnoreRule::fromLine($line));
    }

    public function testEscapedHashIsLiteral(): void
    {
        $rule = IgnoreRule::fromLine('\#keep');
        $this->assertNotNull($rule);
        $this->assertTrue($rule->matches('#keep', false));
    }

    public function testNegation(): void
    {
        $rule = IgnoreRule::fromLine('!foo.html');
        $this->assertNotNull($rule);
        $this->assertTrue($rule->isNegated());
        $this->assertTrue($rule->matches('foo.html', false));
    }

    public function testEscapedBangIsLiteral(): void
    {
        $rule = IgnoreRule::fromLine('\!important!.txt');
        $this->assertNotNull($rule);
        $this->assertFalse($rule->isNegated());
        $this->assertTrue($rule->matches('!important!.txt', false));
    }

    public function testDanglingBackslashNeverMatches(): void
    {
        $rule = IgnoreRule::fromLine('foo\\');
        $this->assertNotNull($rule);
        $this->assertFalse($rule->matches('foo', false));
    }

    public function testTrailingSpacesIgnored(): void
    {
        $rule = IgnoreRule::fromLine('foo   ');
        $this->assertNotNull($rule);
        $this->assertTrue($rule->matches('foo', false));
    }

    public function testEscapedTrailingSpaceKept(): void
    {
        $rule = IgnoreRule::fromLine('foo\\ ');
        $this->assertNotNull($rule);
        $this->assertTrue($rule->matches('foo ', false));
        $this->assertFalse($rule->matches('foo', false));
    }

    /**
     * @dataProvider provideMatches
     */
    public function testMatches(string $pattern, string $path, bool $isDir, bool $expected): void
    {
        $rule = IgnoreRule::fromLine($pattern);
        $this->assertNotNull($rule, "Rule should parse: $pattern");
        $this->assertSame(
            $expected,
            $rule->matches($path, $isDir),
            sprintf('Pattern "%s" against "%s" (dir: %s)', $pattern, $path, $isDir ? 'yes' : 'no'),
        );
    }

    public static function provideMatches(): array
    {
        return [
            // hello.* matches at any level
            'hello.* root file' => ['hello.*', 'hello.txt', false, true],
            'hello.* nested file' => ['hello.*', 'a/hello.java', false, true],
            'hello.* no dot' => ['hello.*', 'hello', false, false],

            // /hello.* anchored to root only
            '/hello.* root' => ['/hello.*', 'hello.txt', false, true],
            '/hello.* nested' => ['/hello.*', 'a/hello.java', false, false],

            // doc/frotz/ directory-only and anchored
            'doc/frotz/ dir' => ['doc/frotz/', 'doc/frotz', true, true],
            'doc/frotz/ file' => ['doc/frotz/', 'doc/frotz', false, false],
            'doc/frotz/ nested' => ['doc/frotz/', 'a/doc/frotz', true, false],

            // frotz/ matches at any level, directory only
            'frotz/ root' => ['frotz/', 'frotz', true, true],
            'frotz/ nested' => ['frotz/', 'a/frotz', true, true],
            'frotz/ file' => ['frotz/', 'frotz', false, false],

            // foo/* matches direct children only
            'foo/* direct file' => ['foo/*', 'foo/test.json', false, true],
            'foo/* direct dir' => ['foo/*', 'foo/bar', true, true],
            'foo/* deep file' => ['foo/*', 'foo/bar/hello.c', false, false],

            // **/foo matches foo anywhere
            '**/foo root' => ['**/foo', 'foo', false, true],
            '**/foo nested' => ['**/foo', 'a/b/foo', false, true],

            // **/foo/bar
            '**/foo/bar nested' => ['**/foo/bar', 'x/foo/bar', false, true],
            '**/foo/bar root' => ['**/foo/bar', 'foo/bar', false, true],

            // abc/** matches everything inside
            'abc/** child' => ['abc/**', 'abc/x', false, true],
            'abc/** deep' => ['abc/**', 'abc/x/y', false, true],
            'abc/** self' => ['abc/**', 'abc', true, false],

            // a/**/b matches zero or more dirs
            'a/**/b direct' => ['a/**/b', 'a/b', false, true],
            'a/**/b one' => ['a/**/b', 'a/x/b', false, true],
            'a/**/b many' => ['a/**/b', 'a/x/y/b', false, true],

            // single star does not cross slash
            '* no slash' => ['*.log', 'dir/file.log', false, true], // unanchored, matches anywhere
            '*.log anchored' => ['/*.log', 'dir/file.log', false, false],

            // ? single char
            '? single' => ['file?.txt', 'file1.txt', false, true],
            '? not slash' => ['file?.txt', 'file/.txt', false, false],

            // character class
            'class match' => ['*.[oa]', 'lib.o', false, true],
            'class match a' => ['*.[oa]', 'lib.a', false, true],
            'class no match' => ['*.[oa]', 'lib.c', false, false],
            'class range' => ['[a-c]at', 'bat', false, true],
            'class range out' => ['[a-c]at', 'zat', false, false],

            // a rule matches the path exactly; ignoring contents is the matcher's job
            'dir exact' => ['build', 'build', true, true],
            'dir not contents' => ['build', 'build/output.txt', false, false],

            // escaped asterisk literal
            'escaped star' => ['foo\\*', 'foo*', false, true],
            'escaped star no glob' => ['foo\\*', 'foobar', false, false],
        ];
    }

    public function testDocFrotzLeadingSlashEquivalent(): void
    {
        // "doc/frotz" and "/doc/frotz" have the same effect.
        $a = IgnoreRule::fromLine('doc/frotz');
        $b = IgnoreRule::fromLine('/doc/frotz');

        $this->assertTrue($a->matches('doc/frotz', false));
        $this->assertTrue($b->matches('doc/frotz', false));
        $this->assertFalse($a->matches('a/doc/frotz', false));
        $this->assertFalse($b->matches('a/doc/frotz', false));
    }
}
