<?php

namespace PHPWatch\PhpKeywordEnhancer\Tests;

use PHPWatch\PhpKeywordEnhancer\KeywordEnhancer;
use PHPUnit\Framework\TestCase;

class KeywordEnhancerTest extends TestCase {
    public static function dataLinkToSecurityAnnouncements(): array {
        return [
            [
                'eloadfont(). (CVE-2022-31630) (cmb)',
                'eloadfont(). ([CVE-2022-31630](https://nvd.nist.gov/vuln/detail/CVE-2022-31630)) (cmb)',
            ],
            [
                'eloadfont(). (CVE-1987-31630) (cmb)',
                'eloadfont(). (CVE-1987-31630) (cmb)',
            ],
            [
                'Fixed bug #81726: phar wrapper: DOS when using quine gzip file. (CVE-2022-31628). (cmb)',
                'Fixed bug [#81726](https://bugs.php.net/bug.php?id=81726): phar wrapper: DOS when using quine gzip file. ([CVE-2022-31628](https://nvd.nist.gov/vuln/detail/CVE-2022-31628)). (cmb)',
            ],
        ];
    }

    public static function dataLinksToBugsPhp(): array {
        return [
            [
                'Fixed bug #81739: OOB read due to',
                'Fixed bug [#81739](https://bugs.php.net/bug.php?id=81739): OOB read due to',
            ],
            [
                'Fixed bug #45: OOB read due to',
                'Fixed bug #45: OOB read due to',
            ],
            [
                'Fixed bug #5943: OOB read due to',
                'Fixed bug #5943: OOB read due to',
            ],
            [
                'Fixed bug #11453: OOB read due to',
                'Fixed bug #11453: OOB read due to',
            ],
            [
                'Fixed bug #123456: OOB read due to',
                'Fixed bug #123456: OOB read due to',
            ],
        ];
    }

    public static function dataLinkToGitHub(): array {
        return [
            [
                'Fixed bug GH-1: OOB read due to',
                'Fixed bug GH-1: OOB read due to',
            ],
            [
                'Fixed bug GH-123: OOB read due to',
                'Fixed bug [GH-123](https://github.com/php/php-src/issues/123): OOB read due to',
            ],
        ];
    }

    public static function dataCodifyText(): array {
        return [
            ['password_hash()', '`password_hash()`'],
            ['`password_hash()`', '`password_hash()`'],
            [
                '`password_hash()` and password_hash()',
                '`password_hash()` and `password_hash()`',
            ],
            ['password_hash()', '`password_hash()`'],
        ];
    }

    /**
     * @covers \PHPWatch\PhpKeywordEnhancer\KeywordEnhancer::enhance
     */
    public function testReturnsVerbatimOnEmptyStrings(): void {
        self::assertSame('', KeywordEnhancer::enhance(''));
        self::assertSame(' ', KeywordEnhancer::enhance(' '));
        self::assertSame('test', KeywordEnhancer::enhance('test'));
    }

    /**
     * @covers       \PHPWatch\PhpKeywordEnhancer\KeywordEnhancer::linkToBug
     * @dataProvider dataLinksToBugsPhp
     */
    public function testLinksToBugsPhp(string $input, string $expected): void {
        self::assertSame($expected, KeywordEnhancer::enhance($input));
    }

    /**
     * @covers       \PHPWatch\PhpKeywordEnhancer\KeywordEnhancer::linkToGitHub
     * @dataProvider dataLinkToGitHub
     */
    public function testLinkToGitHub(string $input, string $expected): void {
        self::assertSame($expected, KeywordEnhancer::enhance($input));
    }

    /**
     * @covers       \PHPWatch\PhpKeywordEnhancer\KeywordEnhancer::linkToSecurityAnnouncements
     * @dataProvider dataLinkToSecurityAnnouncements
     */
    public function testLinkToSecurityAnnouncements(string $input, string $expected): void {
        self::assertSame($expected, KeywordEnhancer::enhance($input));
    }

    /**
     * @covers       \PHPWatch\PhpKeywordEnhancer\KeywordEnhancer::codifyText
     * @dataProvider dataCodifyText
     */
    public function testCodifyText(string $input, string $expected): void {
        self::assertSame($expected, KeywordEnhancer::enhance($input));
    }


}
