<?php

namespace PHPWatch\PHPCommitBuilder\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPWatch\PHPCommitBuilder\KeywordEnhancer;
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
                'Fixed bug [GH-11453](https://github.com/php/php-src/issues/11453): OOB read due to',
                'Fixed bug [GH-11453](https://github.com/php/php-src/issues/11453): OOB read due to',
            ],
            [
                'Fixed bug #11453: OOB read due to',
                'Fixed bug [GH-11453](https://github.com/php/php-src/issues/11453): OOB read due to',
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
            [
                'password_hash()',
                '`password_hash()`'
            ],
            [
                '`password_hash()`',
                '`password_hash()`'
            ],
            [
                '`password_hash()` and password_hash()',
                '`password_hash()` and `password_hash()`',
            ],
            [
                'Non-canonical cast names (boolean), (integer), (double), and (binary) have been deprecated.',
                'Non-canonical cast names (boolean), (integer), (double), and (binary) have been deprecated.',
            ],
            [
                'The $exclude_disabled parameter of the get_defined_functions() function has been deprecated, as it no longer has any effect since PHP 8.0.',
                'The `$exclude_disabled` parameter of the `get_defined_functions()` function has been deprecated, as it no longer has any effect since PHP 8.0.',
            ],
            [
                'The backtick operator as an alias for shell_exec() has been deprecated.',
                'The backtick operator as an alias for `shell_exec()` has been deprecated.',
            ],
            [
                'Returning null from __debugInfo() has been deprecated.',
                'Returning null from `__debugInfo()` has been deprecated.',
            ],
            [
                'Returning null from `__debugInfo()` has been deprecated.',
                'Returning null from `__debugInfo()` has been deprecated.',
            ],
            [
                'Returning null from __debugInfo has been deprecated.',
                'Returning null from `__debugInfo` has been deprecated.',
            ],
            [
                'Support #[\Override] on properties.',
                'Support `#[\Override]` on properties.',
            ],
            [
                'Support #[Override] on properties.',
                'Support `#[Override]` on properties.',
            ],
            [
                'Support `#[Override]` on properties.',
                'Support `#[Override]` on properties.',
            ],
            [
                'Support https://example.com#test on properties.',
                'Support https://example.com#test on properties.',
            ],
            [
                'The curl_close() function has been deprecated.',
                'The `curl_close()` function has been deprecated.',
            ],
            [
                'The curl_share_close() function has been deprecated.',
                'The `curl_share_close()` function has been deprecated.',
            ],
            [
                'The DATE_RFC7231 and DateTimeInterface::RFC7231 constants have been deprecated.',
                'The `DATE_RFC7231` and `DateTimeInterface::RFC7231` constants have been deprecated.',
            ],
            [
                'The $context parameter of the finfo_buffer() function has been deprecated as it is ignored.',
                'The `$context` parameter of the `finfo_buffer()` function has been deprecated as it is ignored.',
            ],
            [
                'The $key_length parameter for openssl_pkey_derive() has been deprecated.',
                'The `$key_length` parameter for `openssl_pkey_derive()` has been deprecated.',
            ],
            [
                'ext/standard/tests/file/bug81145.phpt: unlink file before exit',
                '`ext/standard/tests/file/bug81145.phpt`: unlink file before exit',
            ],
            [
                'ext/standard: unlink file before exit',
                '`ext/standard`: unlink file before exit',
            ],
            [
                'Setting TEST_PHP_EXECUTABLE hasn\'t been necessary for ages',
                'Setting `TEST_PHP_EXECUTABLE` hasn\'t been necessary for ages',
            ],
            [
                'Add an enum for HASH_KEY_IS_* constants',
                'Add an enum for `HASH_KEY_IS_*` constants',
            ],
            [
                'Add an enum for HASH_KEY_IS_*f constants',
                'Add an enum for HASH_KEY_IS_*f constants',
            ],
            [
                'Add an enum for HASH_KEY_IS_ constants',
                'Add an enum for `HASH_KEY_IS_` constants',
            ],
            [
                'Add an enum for HASH_KEY_IS constants',
                'Add an enum for `HASH_KEY_IS` constants',
            ],
        ];
    }

    public function testReturnsVerbatimOnEmptyStrings(): void {
        self::assertSame('', KeywordEnhancer::enhance(''));
        self::assertSame(' ', KeywordEnhancer::enhance(' '));
        self::assertSame('test', KeywordEnhancer::enhance('test'));
    }

    #[DataProvider('dataLinksToBugsPhp')]
    public function testLinksToBugsPhp(string $input, string $expected): void {
        self::assertSame($expected, KeywordEnhancer::enhance($input));
    }

    #[DataProvider('dataLinkToGitHub')]
    public function testLinkToGitHub(string $input, string $expected): void {
        self::assertSame($expected, KeywordEnhancer::enhance($input));
    }

    #[DataProvider('dataLinkToSecurityAnnouncements')]
    public function testLinkToSecurityAnnouncements(string $input, string $expected): void {
        self::assertSame($expected, KeywordEnhancer::enhance($input));
    }

    #[DataProvider('dataCodifyText')]
    public function testCodifyText(string $input, string $expected): void {
        self::assertSame($expected, KeywordEnhancer::enhance($input));
    }


}
