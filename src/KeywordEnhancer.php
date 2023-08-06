<?php

namespace PHPWatch\PHPCommitBuilder;

class KeywordEnhancer {
    protected const CODIFY_PATTERNS = [
        '/\b(?<!`)(?:zend|php)_[a-z_]+\(\)(?!`)/i', // zend_foo_bar()
        '/\b(?<!`)(?:zend|php)_[a-z_*]+\b(?![`.(])\*?/i', // zend_foo_bar()
        '/\b(?<![`\/])[a-z][a-z\d_-]+(.stubs?)?\.(phpt?|c|h)(?![`.?])/', // run-tests.php / foo.stub.php foo.stubs.php / test-foo-bar.phpt,
        '/\b(?<!`)ext\/[a-z_]+\b(?![`\/])/', // ext-names
        '/\b(?<!`)[A-Z][A-Za-z]+::[a-z][A-Za-z_]+\(\)(?![`\/])/', // Class::methods()
        '/\b(?<!`)[A-Z][A-Za-z]+::[A-Z_]+\b(?![`\/])/', // Class::CONSTANTS
        '/\b(?<!`)[A-Z][A-Za-z]+::[a-z][A-Za-z_\d]+\b(?![`\/])/', // Class::constants
        '/\b(?<!`)[a-z]+_[a-z]+(?:_[a-z_]+)?\(\)(?![`\/])/', // Functions with underscores and ()
        '/\b(?<!`)(?:ldap|ftp|array|mb|stream|open|hash)_[a-z_]+\d?\b(?![`\/])/', // Functions with underscores and no ()
        '/\b(?<!`)xleak\b(?![`\/])/i', // xleak
    ];

    public static function enhance(string $inputText): string {
        return static::format($inputText);
    }

    public static function enhanceCommit(string $commitSubject, string $shortHash): string {
        return static::format($commitSubject, $shortHash);
    }

    private static function format(string $inputText, ?string $shortHash = null): string {
        $inputText = static::linkToBug($inputText);
        $inputText = static::linkToGitHub($inputText, $shortHash);
        $inputText = static::codifyText($inputText);
        $inputText = static::linkToSecurityAnnouncements($inputText);

        return $inputText;
    }

    private static function linkToBug(string $subject): string {
        if (preg_match('/#[5-9]\d{4}/', $subject)) {
            return preg_replace('/#(\d{5})/', '[#$1](https://bugs.php.net/bug.php?id=$1)', $subject);
        }

        return $subject;
    }

    private static function linkToGitHub(string $subject, ?string $shortHash = null): string {
        $subject = preg_replace('/\bGH-(\d{3,6})\b/', "[GH-$1](https://github.com/php/php-src/issues/$1)", $subject);
        $subject = preg_replace(
            '/\b(([a-fA-F\d]){8})([a-fA-F\d]){4,32}\b/',
            "[$1](https://github.com/php/php-src/commit/$0)",
            $subject
        );

        if (preg_match('/\(#(\d{3,6})\)$/', $subject)) {
            return preg_replace('/\(#(\d{3,6})\)$/', "in [GH-$1](https://github.com/php/php-src/pull/$1)", $subject);
        }

        if (preg_match('/Closes GH-(\d{3,6})\D?/', $subject, $matches)) {
            $subject .= sprintf(" in [GH-%d](https://github.com/php/php-src/pull/%d)", $matches[1], $matches[1]);
            return $subject;
        }

        if ($shortHash) {
            return $subject . sprintf(' in [%s](https://github.com/php/php-src/commit/%s)', $shortHash, $shortHash);
        }

        return $subject;
    }

    private static function linkToSecurityAnnouncements(string $inputText): string {
        if (preg_match('/(CVE-20\d\d-\d{1,5})\D/', $inputText)) {
            $inputText = preg_replace(
                '/(CVE-20\d\d-\d{1,5})(\D)/',
                "[$1](https://nvd.nist.gov/vuln/detail/$1)$2",
                $inputText
            );
        }

        return $inputText;
    }

    private static function codifyText(string $input): string {
        foreach (static::CODIFY_PATTERNS as $pattern) {
            $input = preg_replace($pattern, '`$0`', $input);
        }

        return $input;
    }
}
